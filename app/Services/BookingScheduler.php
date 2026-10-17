<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\Court;
use App\Models\Staff;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * The single place where appointments are written. The API, the public
 * booking page and the owner app all book through here, so they share the
 * same calendar lock, availability check and stored fields.
 */
class BookingScheduler
{
    public function __construct(private readonly BookingAvailabilityService $availability) {}

    /**
     * Books $start for $service (and $staff, when the service is performed
     * by staff) atomically. Returns null when the slot is no longer free.
     *
     * With $replacing (a reschedule), that booking is cancelled in the same
     * transaction, and only if it still has the status the caller validated;
     * a failed insert rolls the cancellation back.
     *
     * $attributes carries who the booking is for (user_id or customer_*),
     * plus optional status, payment_status, public_token and
     * rebooked_from_booking_id. Price, duration and slot are always derived
     * from the service here, never taken from the request.
     *
     * @throws LockTimeoutException when another request holds this calendar.
     */
    public function book(
        int $businessId,
        Court $service,
        ?Staff $staff,
        CarbonImmutable $start,
        array $attributes,
        ?Booking $replacing = null,
    ): ?Booking {
        // The scarce resource is the professional's calendar, or the service
        // itself when nobody performs it. The court-only key keeps the
        // pre-existing "booking-lock:court:{id}" format.
        $lockKey = $staff
            ? "booking-lock:business:{$businessId}:staff:{$staff->id}"
            : "booking-lock:court:{$service->id}";

        try {
            return Cache::lock($lockKey, 10)->block(3, fn () => DB::transaction(
                fn () => $this->bookLocked($businessId, $service, $staff, $start, $attributes, $replacing),
            ));
        } catch (SlotTakenException) {
            // Thrown inside the transaction so the reschedule's cancellation
            // is rolled back together with the failed insert.
            return null;
        }
    }

    private function bookLocked(
        int $businessId,
        Court $service,
        ?Staff $staff,
        CarbonImmutable $start,
        array $attributes,
        ?Booking $replacing,
    ): ?Booking {
        // Row lock as a second guard for cache drivers without real locks.
        if ($staff) {
            Staff::query()->whereKey($staff->id)->lockForUpdate()->firstOrFail();
        }

        $original = $replacing
            ? Booking::query()->lockForUpdate()->find($replacing->id)
            : null;
        if ($replacing && (! $original || $original->status !== $replacing->status)) {
            return null;
        }

        if (! $this->availability->hasSlot($service, $staff, $start->toDateString(), $start->format('H:i'), $replacing?->id)) {
            return null;
        }

        $minutes = $this->availability->durationMinutes($service);

        try {
            $original?->update(['status' => 'cancelled']);

            return Booking::create(array_merge([
                'status' => 'pending',
                'payment_status' => 'unpaid',
            ], $attributes, [
                'court_id' => $service->id,
                'business_id' => $businessId,
                'staff_id' => $staff?->id,
                'date' => $start,
                'time_slot' => $this->availability->slotLabel($start, $service),
                'duration_hours' => max(1, (int) ceil($minutes / 60)),
                'duration_minutes' => $minutes,
                'booking_code' => $this->newBookingCode(),
                // price_per_hour is a legacy column name: it holds the
                // service's flat price per appointment.
                'total_price' => round((float) ($service->price_per_hour ?? 0), 2),
            ]));
        } catch (QueryException $exception) {
            // The occupied-slot unique index is the last line of defence
            // against a race the locks didn't catch: report "taken", not 500.
            if ($this->isOccupiedSlotConflict($exception)) {
                throw new SlotTakenException();
            }
            throw $exception;
        }
    }

    private function newBookingCode(): string
    {
        do {
            $code = Str::upper(Str::random(8));
        } while (Booking::query()->where('booking_code', $code)->exists());

        return $code;
    }

    private function isOccupiedSlotConflict(QueryException $exception): bool
    {
        return str_contains($exception->getMessage(), 'bookings_occupancy_slot_unique')
            || str_contains($exception->getMessage(), 'occupied_slot_at');
    }
}
