<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\Court;
use App\Models\Staff;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

class BookingAvailabilityService
{
    public function durationMinutes(Court $service): int
    {
        return max(10, (int) ($service->duration_minutes ?: (($service->duration_hours ?: 1) * 60)));
    }

    public function slotLabel(CarbonImmutable $start, Court $service): string
    {
        return $start->format('g:i A').' to '.$start->addMinutes($this->durationMinutes($service))->format('g:i A');
    }

    public function startFrom(string $date, ?string $timeSlot = null): CarbonImmutable
    {
        $start = CarbonImmutable::parse($date);
        if ($start->format('H:i') !== '00:00' || blank($timeSlot)) return $start;
        if (!preg_match('/(\d{1,2}):(\d{2})\s*([AP]M)?/i', $timeSlot, $matches)) {
            throw new \InvalidArgumentException('Invalid appointment time.');
        }
        $hour = (int) $matches[1];
        $minute = (int) $matches[2];
        $meridiem = strtoupper($matches[3] ?? '');
        if ($meridiem !== '') {
            $hour %= 12;
            if ($meridiem === 'PM') $hour += 12;
        }
        return $start->setTime($hour, $minute);
    }

    public function slots(Court $service, ?Staff $staff, string $date, ?int $excludeBookingId = null): array
    {
        $day = CarbonImmutable::createFromFormat('Y-m-d', $date)->startOfDay();
        $business = $service->relationLoaded('business') ? $service->business : $service->business()->first();
        $schedule = data_get($business?->metadata, 'onboarding.weekly_schedule', []);
        $daySchedule = $schedule[strtolower($day->englishDayOfWeek)] ?? null;

        if (is_array($daySchedule) && ! ($daySchedule['is_open'] ?? false)) {
            return [];
        }

        $businessOpen = is_array($daySchedule) ? ($daySchedule['opens_at'] ?? null) : null;
        $businessClose = is_array($daySchedule) ? ($daySchedule['closes_at'] ?? null) : null;
        // A service is bookable only during the intersection of its own hours
        // and the business weekly schedule; neither layer may extend the other.
        $serviceOpen = $service->open_hour ?: '09:00';
        $serviceClose = $service->close_hour ?: '18:00';
        $open = $this->atTime($day, $this->laterTime($businessOpen ?: $serviceOpen, $serviceOpen));
        $close = $this->atTime($day, $this->earlierTime($businessClose ?: $serviceClose, $serviceClose));
        if ($close->lessThanOrEqualTo($open)) {
            return [];
        }
        $duration = $this->durationMinutes($service);

        $bookings = Booking::query()
            ->blocking()
            ->where('business_id', $service->business_id)
            // With a staff member selected, that staff's calendar is the scarce
            // resource. Without one (reservation_staff_selection disabled for
            // this business), the service/court itself is — so only bookings
            // against this same court, also staff-less, can conflict.
            ->when(
                $staff,
                fn ($query) => $query->where('staff_id', $staff->id),
                fn ($query) => $query->where('court_id', $service->id)->whereNull('staff_id'),
            )
            ->whereBetween('date', [$day, $day->endOfDay()])
            ->when($excludeBookingId, fn ($query) => $query->whereKeyNot($excludeBookingId))
            ->with('court:id,duration_minutes,duration_hours')
            ->get();

        $slots = [];
        for ($start = $open; $start->addMinutes($duration)->lessThanOrEqualTo($close); $start = $start->addMinutes(15)) {
            $end = $start->addMinutes($duration);
            if ($start->isPast() || $this->overlaps($start, $end, $bookings)) {
                continue;
            }
            $slots[] = [
                'value' => $start->format('H:i'),
                'label' => $start->format('g:i A'),
                'end' => $end->format('g:i A'),
            ];
        }

        return $slots;
    }

    public function hasSlot(Court $service, ?Staff $staff, string $date, string $time, ?int $excludeBookingId = null): bool
    {
        return collect($this->slots($service, $staff, $date, $excludeBookingId))
            ->contains(fn (array $slot) => $slot['value'] === $time);
    }

    private function overlaps(CarbonImmutable $start, CarbonImmutable $end, Collection $bookings): bool
    {
        return $bookings->contains(function (Booking $booking) use ($start, $end) {
            $bookingStart = CarbonImmutable::parse($booking->date);
            $minutes = $booking->duration_minutes
                ?: ($booking->court ? $this->durationMinutes($booking->court) : (($booking->duration_hours ?: 1) * 60));
            $bookingEnd = $bookingStart->addMinutes($minutes);

            return $start->lessThan($bookingEnd) && $end->greaterThan($bookingStart);
        });
    }

    private function atTime(CarbonImmutable $day, string $time): CarbonImmutable
    {
        [$hour, $minute] = array_pad(array_map('intval', explode(':', $time)), 2, 0);

        return $day->setTime($hour, $minute);
    }

    private function laterTime(string $left, string $right): string
    {
        return $this->timeMinutes($left) >= $this->timeMinutes($right) ? $left : $right;
    }

    private function earlierTime(string $left, string $right): string
    {
        return $this->timeMinutes($left) <= $this->timeMinutes($right) ? $left : $right;
    }

    private function timeMinutes(string $time): int
    {
        [$hour, $minute] = array_pad(array_map('intval', explode(':', $time)), 2, 0);
        return ($hour * 60) + $minute;
    }
}
