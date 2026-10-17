<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Business;
use App\Models\Court;
use App\Models\Staff;
use App\Models\StaffRole;
use App\Models\StaffService;
use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Booking dates are the business's local wall-clock time. These tests pin
 * the real clock to a UTC instant and check every "is it in the past?"
 * decision is made on the business's clock, not on UTC.
 */
class BusinessTimezoneTest extends TestCase
{
    use RefreshDatabase;

    // 2026-10-20 16:00 UTC is 10:00 in Costa Rica (UTC-6).
    private const MORNING_UTC = '2026-10-20 16:00:00';

    // 2026-10-21 03:00 UTC is still 2026-10-20 21:00 in Costa Rica.
    private const EVENING_UTC = '2026-10-21 03:00:00';

    public function test_local_now_is_the_business_wall_clock(): void
    {
        $this->travelTo(CarbonImmutable::parse(self::MORNING_UTC, 'UTC'));
        [$business] = $this->catalog('America/Costa_Rica');

        $this->assertSame('2026-10-20 10:00', $business->localNow()->format('Y-m-d H:i'));
    }

    public function test_invalid_or_missing_timezone_falls_back_to_the_configured_default(): void
    {
        config(['booking.default_timezone' => 'America/Bogota']);
        [$business] = $this->catalog('Not/AZone');
        $this->assertSame('America/Bogota', $business->timezone());

        $business->update(['timezone' => null]);
        $this->assertSame('America/Bogota', $business->fresh()->timezone());
    }

    public function test_afternoon_slots_stay_available_in_the_local_morning(): void
    {
        $this->travelTo(CarbonImmutable::parse(self::MORNING_UTC, 'UTC'));
        [$business, $service, $staff] = $this->catalog('America/Costa_Rica');

        $values = collect(
            $this->getJson("/b/{$business->slug}/availability?service_id={$service->id}&staff_id={$staff->id}&date=2026-10-20")
                ->assertOk()
                ->json('slots')
        )->pluck('value');

        // Under UTC every slot before 16:00 would have been hidden as "past".
        $this->assertTrue($values->contains('10:00'));
        $this->assertTrue($values->contains('14:00'));
        $this->assertFalse($values->contains('09:45'));
    }

    public function test_same_business_slots_follow_its_own_timezone(): void
    {
        $this->travelTo(CarbonImmutable::parse(self::MORNING_UTC, 'UTC'));
        // 16:00 UTC is 18:00 in Madrid: the 09:00-18:00 day is over.
        [$business, $service, $staff] = $this->catalog('Europe/Madrid');

        $this->getJson("/b/{$business->slug}/availability?service_id={$service->id}&staff_id={$staff->id}&date=2026-10-20")
            ->assertOk()
            ->assertJsonPath('slots', []);
    }

    public function test_api_booking_for_this_afternoon_is_accepted(): void
    {
        $this->travelTo(CarbonImmutable::parse(self::MORNING_UTC, 'UTC'));
        [$business, $service, $staff] = $this->catalog('America/Costa_Rica');
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/v1/reservations', [
            'resource_id' => $service->id,
            'staff_id' => $staff->id,
            'date' => '2026-10-20',
            'time_slot' => '14:00',
        ], ['X-Business-Slug' => $business->slug])
            ->assertCreated();

        $this->assertSame('2026-10-20 14:00', Booking::firstOrFail()->date->format('Y-m-d H:i'));
    }

    public function test_api_booking_earlier_today_is_rejected_as_past(): void
    {
        $this->travelTo(CarbonImmutable::parse(self::MORNING_UTC, 'UTC'));
        [$business, $service, $staff] = $this->catalog('America/Costa_Rica');
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/v1/reservations', [
            'resource_id' => $service->id,
            'staff_id' => $staff->id,
            'date' => '2026-10-20',
            'time_slot' => '09:00',
        ], ['X-Business-Slug' => $business->slug])
            ->assertStatus(422);
    }

    public function test_public_booking_accepts_the_local_date_after_utc_midnight(): void
    {
        $this->travelTo(CarbonImmutable::parse(self::EVENING_UTC, 'UTC'));
        [$business, $service, $staff] = $this->catalog('America/Costa_Rica');

        // Locally it is still the 20th; UTC's "today" (the 21st) must not
        // reject it as a past date. The day is over, so no slots remain.
        $this->getJson("/b/{$business->slug}/availability?service_id={$service->id}&staff_id={$staff->id}&date=2026-10-20")
            ->assertOk()
            ->assertJsonPath('slots', []);
    }

    public function test_cancellation_window_is_measured_on_the_local_clock(): void
    {
        $this->travelTo(CarbonImmutable::parse(self::MORNING_UTC, 'UTC'));
        [$business, $service, $staff] = $this->catalog('America/Costa_Rica');
        $client = User::factory()->create();
        Sanctum::actingAs($client);

        // 15:00 local is five hours away: allowed. Under UTC it looked past.
        $later = $this->booking($business, $service, $staff, $client, '2026-10-20 15:00:00');
        $this->postJson("/api/v1/reservations/{$later->id}/cancel", [], ['X-Business-Slug' => $business->slug])
            ->assertOk()
            ->assertJsonPath('status', 'cancelled');

        // 13:00 local is three hours away: inside the four-hour window.
        $soon = $this->booking($business, $service, $staff, $client, '2026-10-20 13:00:00');
        $this->postJson("/api/v1/reservations/{$soon->id}/cancel", [], ['X-Business-Slug' => $business->slug])
            ->assertStatus(422);
    }

    public function test_cancellation_window_follows_the_business_policy(): void
    {
        $this->travelTo(CarbonImmutable::parse(self::MORNING_UTC, 'UTC'));
        [$business, $service, $staff] = $this->catalog('America/Costa_Rica');
        $client = User::factory()->create();
        Sanctum::actingAs($client);
        $headers = ['X-Business-Slug' => $business->slug];

        // Ten hours ahead of the local 10:00.
        $business->update(['app_config' => ['policies' => ['cancellation_window_hours' => 24]]]);
        $strict = $this->booking($business, $service, $staff, $client, '2026-10-20 20:00:00');
        $this->postJson("/api/v1/reservations/{$strict->id}/cancel", [], $headers)
            ->assertStatus(422)
            ->assertJsonPath('message', 'Cannot cancel within 24 hours of the start time');

        $business->update(['app_config' => ['policies' => ['cancellation_window_hours' => 2]]]);
        $this->postJson("/api/v1/reservations/{$strict->id}/cancel", [], $headers)
            ->assertOk();

        // Without a policy the platform default (4 hours) applies.
        $business->update(['app_config' => null]);
        $this->assertSame(4, $business->fresh()->cancellationWindowHours());
        $this->getJson("/api/v1/public/businesses/{$business->slug}/config")
            ->assertJsonPath('policies.cancellation_window_hours', 4);
    }

    public function test_app_configs_expose_the_business_timezone(): void
    {
        [$business] = $this->catalog('America/Costa_Rica');

        $this->getJson("/api/v1/businesses/{$business->slug}/app-config")
            ->assertOk()
            ->assertJsonPath('timezone', 'America/Costa_Rica')
            ->assertJsonPath('utc_offset_minutes', -360);
        $this->getJson("/api/v1/public/businesses/{$business->slug}/config")
            ->assertOk()
            ->assertJsonPath('timezone', 'America/Costa_Rica')
            ->assertJsonPath('utc_offset_minutes', -360);
    }

    private function catalog(string $timezone): array
    {
        $business = Business::create([
            'name' => 'Tz Barber', 'slug' => 'tz-barber', 'business_type' => 'barbershop',
            'status' => 'active', 'timezone' => $timezone,
        ]);
        $service = Court::create([
            'business_id' => $business->id, 'name' => 'Corte', 'address' => 'Puntarenas',
            'duration_hours' => 1, 'duration_minutes' => 30, 'price_per_hour' => 100,
            'open_hour' => '09:00', 'close_hour' => '18:00', 'rating' => 5, 'status' => 'active',
        ]);
        $role = StaffRole::create(['name' => 'Barber', 'slug' => 'barber-tz', 'description' => 'Barber']);
        $staff = Staff::create([
            'business_id' => $business->id, 'staff_role_id' => $role->id, 'name' => 'Tz Barber', 'is_active' => true,
        ]);
        StaffService::create(['staff_id' => $staff->id, 'court_id' => $service->id, 'is_primary' => true]);

        return [$business, $service, $staff];
    }

    private function booking(Business $business, Court $service, Staff $staff, User $client, string $date): Booking
    {
        return Booking::create([
            'user_id' => $client->id, 'court_id' => $service->id, 'business_id' => $business->id,
            'staff_id' => $staff->id, 'date' => Carbon::parse($date), 'time_slot' => 'slot',
            'duration_hours' => 1, 'duration_minutes' => 30, 'status' => 'confirmed',
            'booking_code' => 'TZ'.Booking::count().'X', 'total_price' => 100,
        ]);
    }
}
