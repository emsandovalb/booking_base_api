<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Business;
use App\Models\Court;
use App\Models\Staff;
use App\Models\StaffRole;
use App\Models\StaffService;
use App\Models\User;
use App\Services\BookingAvailabilityService;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Races the locks can't see (e.g. a cache driver without real locks) end at
 * the database's one-booking-per-slot index. Every entry point must turn
 * that into "slot taken" — never a 500, never a half-applied reschedule.
 */
class BookingSchedulerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // 10:00 on 2026-10-20 in Costa Rica.
        $this->travelTo(CarbonImmutable::parse('2026-10-20 16:00:00', 'UTC'));
    }

    public function test_public_booking_reports_a_lost_race_as_slot_taken(): void
    {
        [$business, $service, $staff] = $this->catalog();
        $this->booking($business, $service, $staff, '2026-10-21 10:00:00', 'confirmed');
        $this->pretendEverySlotIsFree();

        $this->from("/b/{$business->slug}")
            ->post("/b/{$business->slug}/book", [
                'service_id' => $service->id, 'staff_id' => $staff->id,
                'date' => '2026-10-21', 'time' => '10:00',
                'customer_name' => 'Ana', 'customer_phone' => '8888-0000',
            ])
            ->assertRedirect("/b/{$business->slug}")
            ->assertSessionHasErrors('time');

        $this->assertSame(1, Booking::count());
    }

    public function test_api_reschedule_that_loses_the_race_keeps_the_original(): void
    {
        [$business, $service, $staff] = $this->catalog();
        $client = User::factory()->create();
        $original = $this->booking($business, $service, $staff, '2026-10-22 10:00:00', 'pending', $client);
        $this->booking($business, $service, $staff, '2026-10-23 11:00:00', 'confirmed');
        $this->pretendEverySlotIsFree();
        Sanctum::actingAs($client);

        $this->postJson("/api/v1/reservations/{$original->id}/rebook", [
            'date' => '2026-10-23', 'time_slot' => '11:00',
        ], ['X-Business-Slug' => $business->slug])
            ->assertStatus(422);

        // Before the fix the cancellation committed even though the
        // replacement was never created, losing the appointment.
        $this->assertSame('pending', $original->fresh()->status);
        $this->assertSame(2, Booking::count());
    }

    public function test_owner_reschedule_that_loses_the_race_keeps_the_original(): void
    {
        [$business, $service, $staff, $owner] = $this->catalog();
        $original = $this->booking($business, $service, $staff, '2026-10-23 10:00:00', 'confirmed');
        $this->booking($business, $service, $staff, '2026-10-24 11:00:00', 'confirmed');
        $this->pretendEverySlotIsFree();
        $this->actingAs($owner);

        $this->post("/app/{$business->slug}/bookings/{$original->id}/reschedule", [
            'date' => '2026-10-24', 'time' => '11:00',
        ])->assertStatus(422);

        $this->assertSame('confirmed', $original->fresh()->status);
        $this->assertSame(2, Booking::count());
    }

    public function test_all_entry_points_store_the_same_server_side_fields(): void
    {
        [$business, $service, $staff] = $this->catalog();
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/v1/reservations', [
            'resource_id' => $service->id, 'staff_id' => $staff->id,
            'date' => '2026-10-21', 'time_slot' => '10:00',
        ], ['X-Business-Slug' => $business->slug])->assertCreated();
        $this->post("/b/{$business->slug}/book", [
            'service_id' => $service->id, 'staff_id' => $staff->id,
            'date' => '2026-10-21', 'time' => '11:00',
            'customer_name' => 'Ana', 'customer_phone' => '8888-0000',
        ])->assertRedirect();

        foreach (Booking::all() as $booking) {
            $this->assertEquals(8000, $booking->total_price);
            $this->assertSame(30, $booking->duration_minutes);
            $this->assertSame('pending', $booking->status);
            $this->assertSame('unpaid', $booking->payment_status);
            $this->assertMatchesRegularExpression('/^[A-Z0-9]{8}$/', $booking->booking_code);
        }
        $this->assertNotNull(Booking::whereNull('user_id')->firstOrFail()->public_token);
    }

    private function pretendEverySlotIsFree(): void
    {
        $this->partialMock(BookingAvailabilityService::class, function ($mock) {
            $mock->shouldReceive('hasSlot')->andReturn(true);
        });
    }

    private function catalog(): array
    {
        $business = Business::create([
            'name' => 'Race Barber', 'slug' => 'race-barber', 'business_type' => 'barbershop',
            'status' => 'active', 'timezone' => 'America/Costa_Rica',
        ]);
        $owner = User::factory()->create(['role' => 'admin']);
        $business->users()->attach($owner, ['role' => 'owner', 'status' => 'active', 'accepted_at' => now()]);
        $service = Court::create([
            'business_id' => $business->id, 'name' => 'Corte', 'address' => 'Puntarenas',
            'duration_hours' => 1, 'duration_minutes' => 30, 'price_per_hour' => 8000,
            'open_hour' => '09:00', 'close_hour' => '18:00', 'rating' => 5, 'status' => 'active',
        ]);
        $role = StaffRole::create(['name' => 'Barber', 'slug' => 'barber-race', 'description' => 'Barber']);
        $staff = Staff::create([
            'business_id' => $business->id, 'staff_role_id' => $role->id, 'name' => 'Carlos', 'is_active' => true,
        ]);
        StaffService::create(['staff_id' => $staff->id, 'court_id' => $service->id, 'is_primary' => true]);

        return [$business, $service, $staff, $owner];
    }

    private function booking(Business $business, Court $service, Staff $staff, string $date, string $status, ?User $client = null): Booking
    {
        return Booking::create([
            'user_id' => ($client ?? User::factory()->create())->id, 'court_id' => $service->id,
            'business_id' => $business->id, 'staff_id' => $staff->id, 'date' => Carbon::parse($date),
            'time_slot' => 'slot', 'duration_hours' => 1, 'duration_minutes' => 30, 'status' => $status,
            'booking_code' => 'RC'.Booking::count().'XXXX', 'total_price' => 8000,
        ]);
    }
}
