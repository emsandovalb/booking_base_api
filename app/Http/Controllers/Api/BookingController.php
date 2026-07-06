<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Court;
use App\Models\Staff;
use App\Support\BusinessContext;
use Illuminate\Http\Request;
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
        if ($context->hasSlug()) {
            $q->where('business_id', $context->businessId());
        }
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

        $data = $request->validate([
            'court_id' => 'required|exists:courts,id',
            'staff_id' => 'nullable|integer|exists:staff,id',
            'date' => 'required|date',
            'time_slot' => 'required|string',
            'total_price' => 'nullable|numeric',
            'duration_hours' => 'nullable|integer|min:1|max:12',
        ]);

        $courtQuery = Court::query()->whereKey($data['court_id']);
        if ($context->hasSlug()) {
            $courtQuery->where('business_id', $context->businessId());
        }
        $court = $courtQuery->first();
        if (!$court) {
            return response()->json(['message' => 'Selected court does not belong to this business'], 422);
        }
        if ($court->status !== 'active') {
            return response()->json(['message' => 'Court is inactive and cannot be booked'], 422);
        }

        if (!empty($data['staff_id'])) {
            $staffQuery = Staff::query()->whereKey($data['staff_id']);
            if ($context->hasSlug()) {
                $staffQuery->where('business_id', $context->businessId());
            }
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

        // Prevent overlapping bookings for the same court
        $start = Carbon::parse($data['date']);
        $end = $this->parseSlotEnd($start, $data['time_slot'], $durationHours);

        $dayStart = (clone $start)->startOfDay();
        $dayEnd = (clone $start)->endOfDay();
        $existing = Booking::where('court_id', $data['court_id'])
            ->whereBetween('date', [$dayStart, $dayEnd])
            ->get();
        foreach ($existing as $b) {
            $s2 = Carbon::parse($b->date);
            // Use each existing booking's own persisted duration_hours with a fallback
            $existingDuration = $b->duration_hours ?? 1;
            $e2 = $this->parseSlotEnd($s2, $b->time_slot, $existingDuration);
            if ($start < $e2 && $end > $s2) {
                return response()->json(['message' => 'Time slot already booked'], 422);
            }
        }

        $booking = Booking::create(array_merge($data, [
            'user_id' => $request->user()->id,
            'business_id' => $context->hasSlug() ? $context->businessId() : null,
            'staff_id' => $data['staff_id'] ?? null,
            'status' => 'pending',
            'booking_code' => Str::upper(Str::random(6)),
        ]));

        return response()->json($booking->load(['court', 'staff']), 201);
    }

    public function show(Request $request, Booking $booking)
    {
        $context = BusinessContext::fromRequest($request);
        if (!$context->isValid()) {
            return response()->json(['message' => 'Business not found'], 404);
        }

        if ($context->hasSlug() && $booking->business_id !== $context->businessId()) {
            return response()->json(['message' => 'Not found'], 404);
        }

        if ($booking->user_id !== $request->user()->id && ($request->user()->role ?? null) !== 'admin') {
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

        if ($context->hasSlug() && $booking->business_id !== $context->businessId()) {
            return response()->json(['message' => 'Not found'], 404);
        }

        if ($booking->user_id !== $request->user()->id && ($request->user()->role ?? null) !== 'admin') {
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

        if ($context->hasSlug() && $booking->business_id !== $context->businessId()) {
            return response()->json(['message' => 'Not found'], 404);
        }

        if ($booking->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Forbidden'], 403);
        }
        if ($booking->status === 'cancelled') {
            return response()->json($booking->load(['court', 'staff']));
        }
        $hoursUntil = now()->diffInHours($booking->date, false);
        if ($hoursUntil < 4) {
            return response()->json(['message' => 'Cannot cancel within 4 hours of the start time'], 422);
        }
        $booking->status = 'cancelled';
        $booking->save();
        return response()->json($booking->load(['court', 'staff']));
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
