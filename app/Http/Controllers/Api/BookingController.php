<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Court;
use App\Models\Staff;
use App\Support\BusinessContext;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Carbon\Carbon;

class BookingController extends Controller
{
    public function index(Request $request)
    {
        $context = BusinessContext::fromRequest($request);
        if (!$context->isValid()) {
            return response()->json(['message' => 'Business not found'], 404);
        }

        $q = Booking::with(['court', 'staff'])->where('user_id', $request->user()->id);
        $context->applyTo($q);
        // status=active|completed
        $status = $request->query('status');
        if ($status === 'active') {
            $q->where('date', '>=', now());
        } elseif ($status === 'completed') {
            $q->where('date', '<', now());
        }
        return $q->latest()->paginate(20);
    }

    public function store(Request $request)
    {
        $context = BusinessContext::fromRequest($request);
        if (!$context->isValid()) {
            return response()->json(['message' => 'Business not found'], 404);
        }

        // Reservations use the public resource vocabulary while this legacy
        // persistence layer still calls the relation court. Normalize once at
        // the boundary so a selected staff member is validated and stored with
        // the same resource that the client selected.
        if (!$request->filled('court_id') && $request->filled('resource_id')) {
            $request->merge(['court_id' => $request->input('resource_id')]);
        }

        $data = $request->validate([
            'court_id' => 'required|exists:courts,id',
            'staff_id' => 'nullable|integer|exists:staff,id',
            'date' => 'required|date',
            'time_slot' => 'required|string',
            'total_price' => 'nullable|numeric',
            'duration_hours' => 'nullable|integer|min:1|max:12',
        ]);

        $courtQuery = Court::query()->whereKey($data['court_id']);
        $context->applyTo($courtQuery);
        $court = $courtQuery->first();
        if (!$court) {
            return response()->json(['message' => 'Selected court does not belong to this business'], 422);
        }
        if ($court->status !== 'active') {
            return response()->json(['message' => 'Court is inactive and cannot be booked'], 422);
        }

        if (!empty($data['staff_id'])) {
            $staffQuery = Staff::query()->whereKey($data['staff_id']);
            $context->applyTo($staffQuery);
            $staff = $staffQuery->first();
            if (!$staff) {
                return response()->json(['message' => 'Selected staff does not belong to this business'], 422);
            }

            $staffLinked = Staff::query()
                ->whereKey($staff->id)
                ->whereHas('courts', function ($query) use ($data) {
                    $query->whereKey($data['court_id']);
                })
                ->exists();

            if (!$staffLinked) {
                return response()->json(['message' => 'Staff is not linked to this court'], 422);
            }
        }

        // Ensure all new bookings have a non-null duration_hours
        $durationHours = $data['duration_hours'] ?? 1;
        $data['duration_hours'] = $durationHours;

        $start = Carbon::parse($data['date']);
        $end = $this->parseSlotEnd($start, $data['time_slot'], $durationHours);

        $outcome = $this->createBookingUnderLock($data, $context, $request->user()->id, $start, $end);

        if ($outcome instanceof Booking) {
            return response()->json($outcome->load(['court', 'staff']), 201);
        }

        return response()->json(['message' => $outcome['message']], $outcome['status']);
    }

    /**
     * Serializes booking creation for a single court so two concurrent
     * requests for the same slot can't both pass the overlap check before
     * either commits. Two layers, deliberately not just one:
     *
     * 1. Cache::lock() — a driver-portable mutual-exclusion lock (works
     *    identically on the database/array/redis cache stores). This is
     *    the layer that actually protects this app today: the dev/current
     *    DB_CONNECTION is sqlite, whose query grammar compiles
     *    lockForUpdate() to nothing (no row-level locking support at all),
     *    so relying on lockForUpdate() alone would silently provide zero
     *    protection under sqlite specifically.
     * 2. DB::transaction() + lockForUpdate() on the court row — real
     *    row-level locking on MySQL/Postgres, defense in depth for
     *    whichever production DB connection actually ends up configured.
     *
     * Both are still just the application layer; see the
     * bookings_court_occupied_slot_unique index (migration
     * 2026_08_20_190909) for the DB-level backstop that holds even if
     * this method has a bug or is bypassed entirely.
     */
    private function createBookingUnderLock(
        array $data,
        BusinessContext $context,
        int $userId,
        Carbon $start,
        Carbon $end
    ): Booking|array {
        $lock = Cache::lock('booking-lock:court:'.$data['court_id'], 10);

        try {
            return $lock->block(3, function () use ($data, $context, $userId, $start, $end) {
                return DB::transaction(function () use ($data, $context, $userId, $start, $end) {
                    // No-op on sqlite (see docblock above); real FOR UPDATE on MySQL/Postgres.
                    Court::query()->whereKey($data['court_id'])->lockForUpdate()->first();

                    $dayStart = (clone $start)->startOfDay();
                    $dayEnd = (clone $start)->endOfDay();

                    $existing = Booking::blocking()
                        ->where('court_id', $data['court_id'])
                        ->whereBetween('date', [$dayStart, $dayEnd])
                        ->lockForUpdate()
                        ->get();

                    foreach ($existing as $b) {
                        $s2 = Carbon::parse($b->date);
                        // Use each existing booking's own persisted duration_hours with a fallback
                        $existingDuration = $b->duration_hours ?? 1;
                        $e2 = $this->parseSlotEnd($s2, $b->time_slot, $existingDuration);
                        if ($start < $e2 && $end > $s2) {
                            return ['status' => 422, 'message' => 'Time slot already booked'];
                        }
                    }

                    try {
                        return Booking::create(array_merge($data, [
                            'user_id' => $userId,
                            'business_id' => $context->businessId(),
                            'staff_id' => $data['staff_id'] ?? null,
                            'status' => 'pending',
                            'booking_code' => Str::upper(Str::random(6)),
                        ]));
                    } catch (QueryException $e) {
                        if ($this->isOccupiedSlotConflict($e)) {
                            return ['status' => 422, 'message' => 'Time slot already booked'];
                        }
                        throw $e;
                    }
                });
            });
        } catch (LockTimeoutException $e) {
            return [
                'status' => 409,
                'message' => 'This slot is currently being booked by someone else. Please try again.',
            ];
        }
    }

    private function isOccupiedSlotConflict(QueryException $e): bool
    {
        return $e->getCode() === '23000'
            && (
                str_contains($e->getMessage(), 'bookings_court_occupied_slot_unique')
                || str_contains($e->getMessage(), 'occupied_slot_at')
            );
    }

    public function show(Request $request, Booking $booking)
    {
        // Note: $booking is resolved via the scoped route binding in routes/api.php,
        // which already guarantees it belongs to the current business context.
        $context = BusinessContext::fromRequest($request);
        if (!$context->isValid()) {
            return response()->json(['message' => 'Business not found'], 404);
        }

        if ($booking->user_id !== $request->user()->id && !$this->canAdminOverrideBooking($request, $context)) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        return response()->json($booking->load(['court', 'user', 'staff']));
    }

    public function rebook(Request $request, Booking $booking)
    {
        $context = BusinessContext::fromRequest($request);
        if (!$context->isValid()) {
            return response()->json(['message' => 'Business not found'], 404);
        }

        if ($booking->user_id !== $request->user()->id && !$this->canAdminOverrideBooking($request, $context)) {
            return response()->json(['message' => 'Forbidden'], 403);
        }
        $data = $request->validate([
            'date' => 'required|date',
            'time_slot' => 'required|string',
        ]);

        $court = Court::find($booking->court_id);
        if (!$court || $court->status !== 'active') {
            return response()->json(['message' => 'Court is inactive and cannot be rebooked'], 422);
        }
        $new = Booking::create([
            'user_id' => $request->user()->id,
            'court_id' => $booking->court_id,
            'business_id' => $booking->business_id,
            'staff_id' => $booking->staff_id,
            'date' => $data['date'],
            'time_slot' => $data['time_slot'],
            'duration_hours' => $booking->duration_hours ?? 1,
            'status' => 'pending',
            'booking_code' => Str::upper(Str::random(6)),
            'total_price' => $booking->total_price,
        ]);
        return response()->json($new->load(['court', 'staff']), 201);
    }

    public function cancel(Request $request, Booking $booking)
    {
        $context = BusinessContext::fromRequest($request);
        if (!$context->isValid()) {
            return response()->json(['message' => 'Business not found'], 404);
        }

        $isOwner = $booking->user_id === $request->user()->id;
        $isBusinessAdmin = $this->canManageBooking($request, $booking, $context);
        if (!$isOwner && !$isBusinessAdmin) {
            return response()->json(['message' => 'Forbidden'], 403);
        }
        if ($booking->status === 'cancelled') {
            return response()->json($booking->load(['court', 'staff']));
        }
        $hoursUntil = now()->diffInHours($booking->date, false);
        if (!$isBusinessAdmin && $hoursUntil < 4) {
            return response()->json(['message' => 'Cannot cancel within 4 hours of the start time'], 422);
        }
        return $this->transition($booking, 'cancelled');
    }

    public function confirm(Request $request, Booking $booking)
    {
        return $this->adminTransition($request, $booking, 'confirmed');
    }

    public function reject(Request $request, Booking $booking)
    {
        return $this->adminTransition($request, $booking, 'rejected');
    }

    private function adminTransition(Request $request, Booking $booking, string $targetStatus)
    {
        $context = BusinessContext::fromRequest($request);
        if (!$context->isValid()) {
            return response()->json(['message' => 'Business not found'], 404);
        }
        if (!$this->canManageBooking($request, $booking, $context)) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        return $this->transition($booking, $targetStatus);
    }

    private function transition(Booking $booking, string $targetStatus)
    {
        $allowedSourceStatuses = $targetStatus === 'cancelled'
            ? ['pending', 'confirmed']
            : ['pending'];

        if (!in_array($booking->status, $allowedSourceStatuses, true)) {
            return response()->json([
                'message' => "Booking cannot be changed from {$booking->status} to {$targetStatus}",
            ], 422);
        }

        $booking->status = $targetStatus;
        $booking->save();

        return response()->json($booking->load(['court', 'staff']));
    }

    private function canAdminOverrideBooking(Request $request, BusinessContext $context): bool
    {
        $user = $request->user();
        if (!$user) {
            return false;
        }

        $business = $context->currentBusiness();
        if (!$business) {
            return false;
        }

        return $context->userCanManageBusiness($user, $business);
    }

    private function canManageBooking(Request $request, Booking $booking, BusinessContext $context): bool
    {
        $user = $request->user();
        if (!$user) {
            return false;
        }

        $business = $booking->business;
        if (!$business) {
            return false;
        }

        return $context->userCanManageBusiness($user, $business);
    }

    private function parseSlotEnd(\Carbon\Carbon $start, string $slot, int $fallbackHours)
    {
        if (preg_match('/to\s*(\d{1,2}):(\d{2})\s*([AP]M)/i', $slot, $m)) {
            $h = ((int)$m[1]) % 12;
            $min = (int)$m[2];
            $ampm = strtoupper($m[3]);
            if ($ampm === 'PM') $h += 12;
            return (clone $start)->setTime($h, $min, 0);
        }
        return (clone $start)->addHours($fallbackHours ?: 1);
    }
}
