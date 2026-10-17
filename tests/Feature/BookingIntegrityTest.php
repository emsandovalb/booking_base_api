<?php

namespace Tests\Feature;

use App\Models\AuditLog;
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

class BookingIntegrityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // 10:00 on 2026-10-20 in Costa Rica; appointments below are tomorrow.
        $this->travelTo(CarbonImmutable::parse('2026-10-20 16:00:00', 'UTC'));
    }

    public function test_a_service_performed_by_staff_cannot_be_booked_without_a_professional(): void
    {
        [$business, $service] = $this->catalog();
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/v1/reservations', [
            'resource_id' => $service->id, 'date' => '2026-10-21', 'time_slot' => '10:00',
        ], $this->headers($business))
            ->assertStatus(422)
            ->assertJsonPath('message', 'Select a professional for this service');

        $this->assertSame(0, Booking::count());
    }

    public function test_a_legacy_staff_less_booking_still_blocks_that_time_for_every_professional(): void
    {
        [$business, $service, $staff] = $this->catalog();
        $this->booking($business, $service, null, '2026-10-21 10:00:00', 'confirmed');
        Sanctum::actingAs(User::factory()->create());

        $this->getJson("/b/{$business->slug}/availability?service_id={$service->id}&staff_id={$staff->id}&date=2026-10-21")
            ->assertOk()
            ->assertJsonMissing(['value' => '10:00']);

        $this->postJson('/api/v1/reservations', [
            'resource_id' => $service->id, 'staff_id' => $staff->id, 'date' => '2026-10-21', 'time_slot' => '10:00',
        ], $this->headers($business))
            ->assertStatus(422);
    }

    public function test_the_price_is_computed_by_the_server_and_never_taken_from_the_client(): void
    {
        [$business, $service, $staff] = $this->catalog();
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/v1/reservations', [
            'resource_id' => $service->id, 'staff_id' => $staff->id, 'date' => '2026-10-21', 'time_slot' => '10:00',
            'total_price' => 1, 'payment_status' => 'paid', 'status' => 'confirmed',
        ], $this->headers($business))
            ->assertCreated()
            ->assertJsonPath('status', 'pending')
            ->assertJsonPath('payment_status', 'unpaid');

        $this->assertEquals(8000, Booking::firstOrFail()->total_price);
    }

    public function test_marking_paid_with_an_empty_body_keeps_the_old_contract_and_is_audited(): void
    {
        [$business, $service, $staff, $owner] = $this->catalog();
        $booking = $this->booking($business, $service, $staff, '2026-10-21 10:00:00', 'confirmed');
        Sanctum::actingAs($owner);

        $this->patchJson("/api/v1/reservations/{$booking->id}/payment-status", [], $this->headers($business))
            ->assertOk()
            ->assertJsonPath('payment_status', 'paid');

        $log = AuditLog::where('action', 'booking.payment_status_changed')->firstOrFail();
        $this->assertSame($booking->id, $log->subject_id);
        $this->assertSame($owner->id, $log->user_id);
        $this->assertSame(['payment_status' => 'paid'], $log->new_values);
    }

    public function test_a_payment_marked_by_mistake_can_be_reverted(): void
    {
        [$business, $service, $staff, $owner] = $this->catalog();
        $booking = $this->booking($business, $service, $staff, '2026-10-21 10:00:00', 'confirmed', 'paid');
        Sanctum::actingAs($owner);

        $this->patchJson("/api/v1/bookings/{$booking->id}/payment-status", ['payment_status' => 'unpaid'], $this->headers($business))
            ->assertOk()
            ->assertJsonPath('payment_status', 'unpaid');
    }

    public function test_cancelled_or_rejected_reservations_cannot_be_marked_paid(): void
    {
        [$business, $service, $staff, $owner] = $this->catalog();
        Sanctum::actingAs($owner);

        foreach (['cancelled', 'rejected'] as $status) {
            $booking = $this->booking($business, $service, $staff, '2026-10-21 10:00:00', $status);
            $this->patchJson("/api/v1/reservations/{$booking->id}/payment-status", [], $this->headers($business))
                ->assertStatus(422);
            $this->assertSame('unpaid', $booking->fresh()->payment_status);
        }
    }

    public function test_customers_cannot_change_payment_status(): void
    {
        [$business, $service, $staff] = $this->catalog();
        $client = User::factory()->create();
        $booking = $this->booking($business, $service, $staff, '2026-10-21 10:00:00', 'confirmed', 'unpaid', $client);
        Sanctum::actingAs($client);

        $this->patchJson("/api/v1/reservations/{$booking->id}/payment-status", [], $this->headers($business))
            ->assertForbidden();
    }

    public function test_invalid_payment_status_is_rejected(): void
    {
        [$business, $service, $staff, $owner] = $this->catalog();
        $booking = $this->booking($business, $service, $staff, '2026-10-21 10:00:00', 'confirmed');
        Sanctum::actingAs($owner);

        $this->patchJson("/api/v1/reservations/{$booking->id}/payment-status", ['payment_status' => 'refunded'], $this->headers($business))
            ->assertStatus(422);
    }

    public function test_the_business_can_reschedule_a_confirmed_appointment_with_24_hours_notice(): void
    {
        [$business, $service, $staff, $owner] = $this->catalog();
        // Exactly 24 hours ahead of the local 10:00 "now".
        $booking = $this->booking($business, $service, $staff, '2026-10-21 10:00:00', 'confirmed', 'paid');
        Sanctum::actingAs($owner);

        $this->postJson("/api/v1/reservations/{$booking->id}/rebook", [
            'date' => '2026-10-22', 'time_slot' => '11:00',
        ], $this->headers($business))
            ->assertCreated()
            ->assertJsonPath('status', 'confirmed')
            ->assertJsonPath('payment_status', 'paid')
            ->assertJsonPath('rebooked_from_booking_id', $booking->id);

        $this->assertSame('cancelled', $booking->fresh()->status);
    }

    public function test_a_confirmed_appointment_cannot_be_rescheduled_within_24_hours(): void
    {
        [$business, $service, $staff, $owner] = $this->catalog();
        $booking = $this->booking($business, $service, $staff, '2026-10-21 09:45:00', 'confirmed');
        Sanctum::actingAs($owner);

        $this->postJson("/api/v1/reservations/{$booking->id}/rebook", [
            'date' => '2026-10-22', 'time_slot' => '11:00',
        ], $this->headers($business))
            ->assertStatus(422)
            ->assertJsonPath('message', 'Confirmed reservations can only be rescheduled at least 24 hours in advance');

        $this->assertSame('confirmed', $booking->fresh()->status);
        $this->assertSame(1, Booking::count());
    }

    public function test_customers_cannot_reschedule_a_confirmed_appointment(): void
    {
        [$business, $service, $staff] = $this->catalog();
        $client = User::factory()->create();
        $booking = $this->booking($business, $service, $staff, '2026-10-23 10:00:00', 'confirmed', 'unpaid', $client);
        Sanctum::actingAs($client);

        $this->postJson("/api/v1/reservations/{$booking->id}/rebook", [
            'date' => '2026-10-24', 'time_slot' => '11:00',
        ], $this->headers($business))
            ->assertStatus(422)
            ->assertJsonPath('message', 'Only the business can reschedule a confirmed reservation');
    }

    public function test_customers_can_still_reschedule_a_pending_request_and_it_stays_pending(): void
    {
        [$business, $service, $staff] = $this->catalog();
        $client = User::factory()->create();
        $booking = $this->booking($business, $service, $staff, '2026-10-20 15:00:00', 'pending', 'unpaid', $client);
        Sanctum::actingAs($client);

        $this->postJson("/api/v1/reservations/{$booking->id}/rebook", [
            'date' => '2026-10-21', 'time_slot' => '11:00',
        ], $this->headers($business))
            ->assertCreated()
            ->assertJsonPath('status', 'pending');
    }

    public function test_owner_web_app_applies_the_same_24_hour_rule(): void
    {
        [$business, $service, $staff, $owner] = $this->catalog();
        $soon = $this->booking($business, $service, $staff, '2026-10-21 09:00:00', 'confirmed');
        $later = $this->booking($business, $service, $staff, '2026-10-23 09:00:00', 'confirmed', 'paid');
        $this->actingAs($owner);

        $this->post("/app/{$business->slug}/bookings/{$soon->id}/reschedule", ['date' => '2026-10-22', 'time' => '11:00'])
            ->assertStatus(422);
        $this->assertSame('confirmed', $soon->fresh()->status);

        $this->post("/app/{$business->slug}/bookings/{$later->id}/reschedule", ['date' => '2026-10-24', 'time' => '11:00'])
            ->assertRedirect();
        $moved = Booking::where('rebooked_from_booking_id', $later->id)->firstOrFail();
        $this->assertSame('confirmed', $moved->status);
        $this->assertSame('paid', $moved->payment_status);
        $this->assertSame('2026-10-24 11:00', $moved->date->format('Y-m-d H:i'));
    }

    private function catalog(): array
    {
        $business = Business::create([
            'name' => 'Integrity Barber', 'slug' => 'integrity-barber', 'business_type' => 'barbershop',
            'status' => 'active', 'timezone' => 'America/Costa_Rica',
        ]);
        $owner = User::factory()->create(['role' => 'admin']);
        $business->users()->attach($owner, ['role' => 'owner', 'status' => 'active', 'accepted_at' => now()]);
        $service = Court::create([
            'business_id' => $business->id, 'name' => 'Corte', 'address' => 'Puntarenas',
            'duration_hours' => 1, 'duration_minutes' => 30, 'price_per_hour' => 8000,
            'open_hour' => '09:00', 'close_hour' => '18:00', 'rating' => 5, 'status' => 'active',
        ]);
        $role = StaffRole::create(['name' => 'Barber', 'slug' => 'barber-integrity', 'description' => 'Barber']);
        $staff = Staff::create([
            'business_id' => $business->id, 'staff_role_id' => $role->id, 'name' => 'Carlos', 'is_active' => true,
        ]);
        StaffService::create(['staff_id' => $staff->id, 'court_id' => $service->id, 'is_primary' => true]);

        return [$business, $service, $staff, $owner];
    }

    private function booking(
        Business $business,
        Court $service,
        ?Staff $staff,
        string $date,
        string $status,
        string $paymentStatus = 'unpaid',
        ?User $client = null,
    ): Booking {
        return Booking::create([
            'user_id' => ($client ?? User::factory()->create())->id, 'court_id' => $service->id,
            'business_id' => $business->id, 'staff_id' => $staff?->id, 'date' => Carbon::parse($date),
            'time_slot' => 'slot', 'duration_hours' => 1, 'duration_minutes' => 30, 'status' => $status,
            'payment_status' => $paymentStatus, 'booking_code' => 'IN'.Booking::count().'X', 'total_price' => 8000,
        ]);
    }

    private function headers(Business $business): array
    {
        return ['X-Business-Slug' => $business->slug];
    }
}
