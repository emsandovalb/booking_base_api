<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Business;
use App\Models\Court;
use App\Models\Staff;
use App\Services\BookingAvailabilityService;
use App\Services\BookingScheduler;
use App\Services\SuperAdmin\AuditLogService;
use App\Support\BusinessContext;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Http\Request;

class BookingController extends Controller
{
    public function __construct(
        private readonly BookingAvailabilityService $availability,
        private readonly BookingScheduler $scheduler,
    ) {}

    public function index(Request $request)
    {
        $context = $this->context($request);
        if (!$context) return response()->json(['message' => 'Business not found'], 404);

        $query = Booking::with(['court', 'staff'])->where('user_id', $request->user()->id);
        $context->applyTo($query);
        $status = $request->query('status');
        if ($status === 'active') {
            $query->whereIn('status', Booking::BLOCKING_STATUSES)->where('date', '>=', $context->business()->localNow());
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
        $canManage = $this->canManage($request, $booking, $context);
        if ($booking->user_id !== $request->user()->id && !$canManage) {
            return response()->json(['message' => 'Forbidden'], 403);
        }
        if ($reason = $booking->rescheduleBlockReason($canManage, $context->business()->localNow())) {
            return response()->json(['message' => $reason], 422);
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
            // Moving an appointment keeps its confirmation and payment.
            'status' => $booking->status,
            'payment_status' => $booking->payment_status,
        ], $booking);
    }

    public function cancel(Request $request, Booking $booking)
    {
        $context = $this->context($request);
        if (!$context) return response()->json(['message' => 'Business not found'], 404);
        $isOwner = $booking->user_id === $request->user()->id;
        if (!$isOwner && !$this->canManage($request, $booking, $context)) return response()->json(['message' => 'Forbidden'], 403);
        if (!in_array($booking->status, Booking::BLOCKING_STATUSES, true)) return response()->json(['message' => 'Reservation cannot be cancelled'], 422);
        if ($isOwner && $context->business()->localNow()->diffInHours($booking->date, false) < 4) return response()->json(['message' => 'Cannot cancel within 4 hours of the start time'], 422);
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

        // An empty body keeps the original "mark as paid" contract.
        $target = $request->validate([
            'payment_status' => ['nullable', 'in:paid,unpaid'],
        ])['payment_status'] ?? 'paid';
        if ($target === 'paid' && in_array($booking->status, ['cancelled', 'rejected'], true)) {
            return response()->json(['message' => "A {$booking->status} reservation cannot be marked as paid"], 422);
        }

        $previous = $booking->payment_status;
        if ($previous !== $target) {
            $booking->update(['payment_status' => $target]);
            app(AuditLogService::class)->record($context->business(), $request->user(), 'booking.payment_status_changed', $booking,
                ['payment_status' => $previous], ['payment_status' => $target]);
        }
        return response()->json($booking->load(['court', 'staff']));
    }

    private function schedule(BusinessContext $context, Court $service, ?Staff $staff, CarbonImmutable $start, array $attributes, ?Booking $replacing = null)
    {
        try {
            $booking = $this->scheduler->book($context->businessId(), $service, $staff, $start, $attributes, $replacing);
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
        } else {
            // A service performed by staff must be booked against one of
            // them: a staff-less booking would hold no professional's
            // calendar, letting a second client take the same barber.
            $staffedQuery = Staff::query()->where('is_active', true)
                ->whereHas('courts', fn ($query) => $query->whereKey($service->id));
            $context->applyTo($staffedQuery);
            if ($staffedQuery->exists()) abort(422, 'Select a professional for this service');
        }

        try {
            $start = $this->availability->startFrom($data['date'], $data['time_slot'] ?? null);
        } catch (\InvalidArgumentException) {
            abort(422, 'Invalid appointment time');
        }
        if ($start->lessThan($context->business()->localNow())) abort(422, 'Appointments must be scheduled in the future');
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
        // Completing frees the slot for new bookings (see Booking::booted()),
        // which is only correct once the appointment has actually happened —
        // completing it early would let someone else book the same staff
        // member for a slot that hasn't occurred yet.
        if ($target === 'completed' && $booking->date->greaterThan($this->businessNow($booking))) {
            return response()->json(['message' => 'Cannot mark a reservation completed before its scheduled time'], 422);
        }
        $booking->status = $target;
        if ($target === 'completed') $booking->completed_at = now();
        $booking->save();
        return response()->json($booking->load(['court', 'staff']));
    }

    private function businessNow(Booking $booking): CarbonImmutable
    {
        return $booking->business?->localNow() ?? Business::wallClockNow();
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

}
