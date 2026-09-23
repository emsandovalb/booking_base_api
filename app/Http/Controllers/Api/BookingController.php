<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Court;
use App\Models\Staff;
use App\Services\BookingAvailabilityService;
use App\Support\BusinessContext;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class BookingController extends Controller
{
    public function __construct(private readonly BookingAvailabilityService $availability) {}

    public function index(Request $request)
    {
        $context = $this->context($request);
        if (!$context) return response()->json(['message' => 'Business not found'], 404);

        $query = Booking::with(['court', 'staff'])->where('user_id', $request->user()->id);
        $context->applyTo($query);
        $status = $request->query('status');
        if ($status === 'active') {
            $query->whereIn('status', Booking::BLOCKING_STATUSES)->where('date', '>=', now());
        } elseif ($status === 'completed') {
            $query->where('status', 'completed');
        } elseif (in_array($status, ['cancelled', 'rejected'], true)) {
            $query->where('status', $status);
        }
        return $query->latest()->paginate(20);
    }

    public function store(Request $request)
    {
        $context = $this->context($request);
        if (!$context) return response()->json(['message' => 'Business not found'], 404);
        if (!$request->filled('court_id') && $request->filled('resource_id')) {
            $request->merge(['court_id' => $request->input('resource_id')]);
        }

        $data = $this->validatedAppointment($request);
        [$service, $staff, $start] = $this->selection($context, $data);
        return $this->schedule($context, $service, $staff, $start, ['user_id' => $request->user()->id]);
    }

    public function show(Request $request, Booking $booking)
    {
        $context = $this->context($request);
        if (!$context) return response()->json(['message' => 'Business not found'], 404);
        if ($booking->user_id !== $request->user()->id && !$this->canManage($request, $booking, $context)) {
            return response()->json(['message' => 'Forbidden'], 403);
        }
        return response()->json($booking->load(['court', 'user', 'staff', 'rebookedFrom']));
    }

    public function rebook(Request $request, Booking $booking)
    {
        $context = $this->context($request);
        if (!$context) return response()->json(['message' => 'Business not found'], 404);
        if ($booking->user_id !== $request->user()->id && !$this->canManage($request, $booking, $context)) {
            return response()->json(['message' => 'Forbidden'], 403);
        }
        if ($booking->status !== 'pending') {
            return response()->json(['message' => 'Only pending reservations can be rescheduled'], 422);
        }

        // A reschedule can retain its service and professional when the client only sends a new date/time.
        $request->merge([
            'court_id' => $request->input('court_id', $request->input('resource_id', $booking->court_id)),
            'staff_id' => $request->input('staff_id', $booking->staff_id),
        ]);
        $data = $this->validatedAppointment($request);
        [$service, $staff, $start] = $this->selection($context, $data);
        return $this->schedule($context, $service, $staff, $start, [
            'user_id' => $booking->user_id,
            'customer_name' => $booking->customer_name,
            'customer_phone' => $booking->customer_phone,
            'customer_email' => $booking->customer_email,
            'rebooked_from_booking_id' => $booking->id,
        ], $booking);
    }

    public function cancel(Request $request, Booking $booking)
    {
        $context = $this->context($request);
        if (!$context) return response()->json(['message' => 'Business not found'], 404);
        $isOwner = $booking->user_id === $request->user()->id;
        if (!$isOwner && !$this->canManage($request, $booking, $context)) return response()->json(['message' => 'Forbidden'], 403);
        if (!in_array($booking->status, Booking::BLOCKING_STATUSES, true)) return response()->json(['message' => 'Reservation cannot be cancelled'], 422);
        if ($isOwner && now()->diffInHours($booking->date, false) < 4) return response()->json(['message' => 'Cannot cancel within 4 hours of the start time'], 422);
        return $this->transition($booking, 'cancelled');
    }

    public function confirm(Request $request, Booking $booking) { return $this->adminTransition($request, $booking, 'confirmed'); }
    public function reject(Request $request, Booking $booking) { return $this->adminTransition($request, $booking, 'rejected'); }
    public function complete(Request $request, Booking $booking) { return $this->adminTransition($request, $booking, 'completed'); }

    public function markPaid(Request $request, Booking $booking)
    {
        $context = $this->context($request);
        if (!$context) return response()->json(['message' => 'Business not found'], 404);
        if (!$this->canManage($request, $booking, $context)) return response()->json(['message' => 'Forbidden'], 403);
        $booking->update(['payment_status' => 'paid']);
        return response()->json($booking->load(['court', 'staff']));
    }

    private function schedule(BusinessContext $context, Court $service, ?Staff $staff, CarbonImmutable $start, array $attributes, ?Booking $replacing = null)
    {
        // Businesses that don't use staff selection (reservation_staff_selection
        // feature flag off) still book directly against the service/court, so
        // the scarce resource being locked is the staff member when one is
        // selected, or the court/service itself when it isn't. The court-only
        // key intentionally matches the pre-existing "booking-lock:court:{id}"
        // format so it keeps interoperating with any caller still using it.
        $lockKey = $staff
            ? "booking-lock:business:{$context->businessId()}:staff:{$staff->id}"
            : "booking-lock:court:{$service->id}";
        $lock = Cache::lock($lockKey, 10);
        try {
            $booking = $lock->block(3, function () use ($context, $service, $staff, $start, $attributes, $replacing) {
                return DB::transaction(function () use ($context, $service, $staff, $start, $attributes, $replacing) {
                    if ($staff) {
                        Staff::query()->whereKey($staff->id)->lockForUpdate()->firstOrFail();
                    }
                    $original = $replacing
                        ? Booking::query()->lockForUpdate()->findOrFail($replacing->id)
                        : null;
                    if ($original && $original->status !== 'pending') return null;
                    if (!$this->availability->hasSlot($service, $staff, $start->toDateString(), $start->format('H:i'), $replacing?->id)) {
                        return null;
                    }

                    $minutes = $this->availability->durationMinutes($service);
                    try {
                        // Free the old slot inside this transaction before inserting the
                        // replacement. If insertion fails, the transaction restores it.
                        if ($original) $original->update(['status' => 'cancelled']);
                        $booking = Booking::create(array_merge($attributes, [
                            'court_id' => $service->id,
                            'business_id' => $context->businessId(),
                            'staff_id' => $staff?->id,
                            'date' => $start,
                            'time_slot' => $this->availability->slotLabel($start, $service),
                            'duration_hours' => max(1, (int) ceil($minutes / 60)),
                            'duration_minutes' => $minutes,
                            'status' => 'pending',
                            'payment_status' => 'unpaid',
                            'booking_code' => Str::upper(Str::random(8)),
                            'total_price' => round((float) ($service->price_per_hour ?? 0), 2),
                        ]));
                    } catch (QueryException $exception) {
                        if ($this->occupiedSlotConflict($exception)) return null;
                        throw $exception;
                    }
                    return $booking;
                });
            });
        } catch (LockTimeoutException) {
            return response()->json(['message' => 'This slot is currently being booked. Please try again.'], 409);
        }

        if (!$booking) return response()->json(['message' => 'Time slot is no longer available'], 422);
        return response()->json($booking->load(['court', 'staff', 'rebookedFrom']), 201);
    }

    private function validatedAppointment(Request $request): array
    {
        return $request->validate([
            'court_id' => ['required', 'integer', 'exists:courts,id'],
            'staff_id' => ['nullable', 'integer', 'exists:staff,id'],
            'date' => ['required', 'date'],
            'time_slot' => ['nullable', 'string'],
        ]);
    }

    private function selection(BusinessContext $context, array $data): array
    {
        $serviceQuery = Court::query()->whereKey($data['court_id'])->where('status', 'active');
        $context->applyTo($serviceQuery);
        $service = $serviceQuery->first();
        if (!$service) abort(422, 'Selected service does not belong to this business');

        $staff = null;
        if (!empty($data['staff_id'])) {
            $staffQuery = Staff::query()->whereKey($data['staff_id'])->where('is_active', true);
            $context->applyTo($staffQuery);
            $staff = $staffQuery->whereHas('courts', fn ($query) => $query->whereKey($service->id))->first();
            if (!$staff) abort(422, 'Staff is not linked to this court');
        }

        try {
            $start = $this->availability->startFrom($data['date'], $data['time_slot'] ?? null);
        } catch (\InvalidArgumentException) {
            abort(422, 'Invalid appointment time');
        }
        if ($start->isPast()) abort(422, 'Appointments must be scheduled in the future');
        return [$service, $staff, $start];
    }

    private function adminTransition(Request $request, Booking $booking, string $target)
    {
        $context = $this->context($request);
        if (!$context) return response()->json(['message' => 'Business not found'], 404);
        if (!$this->canManage($request, $booking, $context)) return response()->json(['message' => 'Forbidden'], 403);
        return $this->transition($booking, $target);
    }

    private function transition(Booking $booking, string $target)
    {
        $allowed = match ($target) {
            'confirmed', 'rejected' => ['pending'],
            'completed' => ['confirmed'],
            'cancelled' => ['pending', 'confirmed'],
        };
        if (!in_array($booking->status, $allowed, true)) return response()->json(['message' => "Reservation cannot be changed from {$booking->status} to {$target}"], 422);
        $booking->status = $target;
        if ($target === 'completed') $booking->completed_at = now();
        $booking->save();
        return response()->json($booking->load(['court', 'staff']));
    }

    private function context(Request $request): ?BusinessContext
    {
        $context = BusinessContext::fromRequest($request);
        return $context->isValid() ? $context : null;
    }

    private function canManage(Request $request, Booking $booking, BusinessContext $context): bool
    {
        return $request->user() && $booking->business_id === $context->businessId()
            && $context->userCanManageBusiness($request->user(), $booking->business);
    }

    private function occupiedSlotConflict(QueryException $exception): bool
    {
        return str_contains($exception->getMessage(), 'bookings_occupancy_slot_unique')
            || str_contains($exception->getMessage(), 'occupied_slot_at');
    }
}
