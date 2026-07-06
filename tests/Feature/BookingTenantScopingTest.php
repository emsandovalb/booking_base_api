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
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BookingTenantScopingTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsUser(): User
    {
        $user = User::factory()->create(['role' => 'user']);
        Sanctum::actingAs($user);

        return $user;
    }

    private function createBusiness(string $slug, string $name): Business
    {
        return Business::create([
            'name' => $name,
            'slug' => $slug,
            'legal_name' => $name,
            'status' => 'active',
        ]);
    }

    private function createResource(Business $business, string $name): Court
    {
        return Court::factory()->create([
            'name' => $name,
            'business_id' => $business->id,
            'status' => 'active',
        ]);
    }

    private function createRole(string $slug, string $name): StaffRole
    {
        return StaffRole::create([
            'name' => $name,
            'slug' => $slug,
            'description' => $name,
        ]);
    }

    private function createStaff(
        Business $business,
        Court $court,
        string $name,
        ?StaffRole $role = null,
    ): Staff {
        $role ??= $this->createRole('consultant-' . uniqid(), 'Consultant');

        $staff = Staff::create([
            'business_id' => $business->id,
            'staff_role_id' => $role->id,
            'name' => $name,
            'email' => strtolower(str_replace(' ', '.', $name)) . '@example.com',
            'is_active' => true,
        ]);

        StaffService::create([
            'staff_id' => $staff->id,
            'court_id' => $court->id,
            'is_primary' => true,
        ]);

        return $staff;
    }

    private function createBookingForUser(
        User $user,
        Court $court,
        ?Business $business,
        string $timeSlot = '6:00 PM to 7:00 PM',
    ): Booking {
        return Booking::create([
            'user_id' => $user->id,
            'court_id' => $court->id,
            'business_id' => $business?->id,
            'date' => Carbon::parse('2026-07-08 18:00:00'),
            'time_slot' => $timeSlot,
            'duration_hours' => 1,
            'status' => 'pending',
            'booking_code' => Str::upper(Str::random(6)),
            'total_price' => 0,
        ]);
    }

    public function test_booking_created_with_business_slug_gets_business_id(): void
    {
        $user = $this->actingAsUser();
        $business = $this->createBusiness('barberia-tres-amigos', 'Barbería Tres Amigos');
        $court = $this->createResource($business, 'Tres Amigos Chair');

        $response = $this->postJson('/api/v1/bookings', [
            'court_id' => $court->id,
            'date' => Carbon::now()->addDay()->toIso8601String(),
            'time_slot' => '6:00 PM to 7:00 PM',
        ], [
            'X-Business-Slug' => $business->slug,
        ]);

        $response->assertCreated();
        $response->assertJsonPath('business_id', $business->id);

        $this->assertDatabaseHas('bookings', [
            'user_id' => $user->id,
            'court_id' => $court->id,
            'business_id' => $business->id,
        ]);
    }

    public function test_booking_creation_fails_if_resource_belongs_to_another_business(): void
    {
        $this->actingAsUser();
        $businessA = $this->createBusiness('barberia-tres-amigos', 'Barbería Tres Amigos');
        $businessB = $this->createBusiness('salon-aurora', 'Salón Aurora');
        $court = $this->createResource($businessB, 'Salon Aurora Chair');

        $response = $this->postJson('/api/v1/bookings', [
            'court_id' => $court->id,
            'date' => Carbon::now()->addDay()->toIso8601String(),
            'time_slot' => '6:00 PM to 7:00 PM',
        ], [
            'X-Business-Slug' => $businessA->slug,
        ]);

        $response->assertStatus(422);
        $response->assertJsonPath('message', 'Selected court does not belong to this business');
    }

    public function test_booking_creation_fails_if_staff_belongs_to_another_business(): void
    {
        $this->actingAsUser();
        $businessA = $this->createBusiness('barberia-tres-amigos', 'Barbería Tres Amigos');
        $businessB = $this->createBusiness('salon-aurora', 'Salón Aurora');
        $court = $this->createResource($businessA, 'Tres Amigos Chair');
        $staff = $this->createStaff($businessB, $court, 'Aurora Staff');

        $response = $this->postJson('/api/v1/bookings', [
            'court_id' => $court->id,
            'staff_id' => $staff->id,
            'date' => Carbon::now()->addDay()->toIso8601String(),
            'time_slot' => '6:00 PM to 7:00 PM',
        ], [
            'X-Business-Slug' => $businessA->slug,
        ]);

        $response->assertStatus(422);
        $response->assertJsonPath('message', 'Selected staff does not belong to this business');
    }

    public function test_booking_index_with_tres_amigos_slug_only_returns_tres_amigos_bookings(): void
    {
        $user = $this->actingAsUser();
        $tresAmigos = $this->createBusiness('barberia-tres-amigos', 'Barbería Tres Amigos');
        $salonAurora = $this->createBusiness('salon-aurora', 'Salón Aurora');
        $tresCourt = $this->createResource($tresAmigos, 'Tres Amigos Chair');
        $auroraCourt = $this->createResource($salonAurora, 'Aurora Chair');

        $tresBooking = $this->createBookingForUser($user, $tresCourt, $tresAmigos);
        $this->createBookingForUser($user, $auroraCourt, $salonAurora);

        $response = $this->getJson('/api/v1/bookings', [
            'X-Business-Slug' => $tresAmigos->slug,
        ]);

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.id', $tresBooking->id);
        $response->assertJsonPath('data.0.business_id', $tresAmigos->id);
    }

    public function test_booking_index_with_salon_aurora_slug_only_returns_salon_aurora_bookings(): void
    {
        $user = $this->actingAsUser();
        $tresAmigos = $this->createBusiness('barberia-tres-amigos', 'Barbería Tres Amigos');
        $salonAurora = $this->createBusiness('salon-aurora', 'Salón Aurora');
        $tresCourt = $this->createResource($tresAmigos, 'Tres Amigos Chair');
        $auroraCourt = $this->createResource($salonAurora, 'Aurora Chair');

        $this->createBookingForUser($user, $tresCourt, $tresAmigos);
        $auroraBooking = $this->createBookingForUser($user, $auroraCourt, $salonAurora);

        $response = $this->getJson('/api/v1/bookings', [
            'X-Business-Slug' => $salonAurora->slug,
        ]);

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.id', $auroraBooking->id);
        $response->assertJsonPath('data.0.business_id', $salonAurora->id);
    }

    public function test_booking_detail_with_wrong_business_slug_returns_404(): void
    {
        $user = $this->actingAsUser();
        $tresAmigos = $this->createBusiness('barberia-tres-amigos', 'Barbería Tres Amigos');
        $salonAurora = $this->createBusiness('salon-aurora', 'Salón Aurora');
        $court = $this->createResource($tresAmigos, 'Tres Amigos Chair');
        $booking = $this->createBookingForUser($user, $court, $tresAmigos);

        $response = $this->getJson('/api/v1/bookings/' . $booking->id, [
            'X-Business-Slug' => $salonAurora->slug,
        ]);

        $response->assertNotFound();
    }

    public function test_cancel_with_wrong_business_slug_returns_404(): void
    {
        $user = $this->actingAsUser();
        $tresAmigos = $this->createBusiness('barberia-tres-amigos', 'Barbería Tres Amigos');
        $salonAurora = $this->createBusiness('salon-aurora', 'Salón Aurora');
        $court = $this->createResource($tresAmigos, 'Tres Amigos Chair');
        $booking = $this->createBookingForUser($user, $court, $tresAmigos);

        $response = $this->postJson('/api/v1/bookings/' . $booking->id . '/cancel', [], [
            'X-Business-Slug' => $salonAurora->slug,
        ]);

        $response->assertNotFound();
    }

    public function test_rebook_with_wrong_business_slug_returns_404(): void
    {
        $user = $this->actingAsUser();
        $tresAmigos = $this->createBusiness('barberia-tres-amigos', 'Barbería Tres Amigos');
        $salonAurora = $this->createBusiness('salon-aurora', 'Salón Aurora');
        $court = $this->createResource($tresAmigos, 'Tres Amigos Chair');
        $booking = $this->createBookingForUser($user, $court, $tresAmigos);

        $response = $this->postJson('/api/v1/bookings/' . $booking->id . '/rebook', [
            'date' => Carbon::now()->addDays(2)->toIso8601String(),
            'time_slot' => '7:00 PM to 8:00 PM',
        ], [
            'X-Business-Slug' => $salonAurora->slug,
        ]);

        $response->assertNotFound();
    }

    public function test_rebook_preserves_business_id(): void
    {
        $user = $this->actingAsUser();
        $business = $this->createBusiness('barberia-tres-amigos', 'Barbería Tres Amigos');
        $court = $this->createResource($business, 'Tres Amigos Chair');
        $booking = $this->createBookingForUser($user, $court, $business);

        $response = $this->postJson('/api/v1/bookings/' . $booking->id . '/rebook', [
            'date' => Carbon::now()->addDays(2)->toIso8601String(),
            'time_slot' => '7:00 PM to 8:00 PM',
        ], [
            'X-Business-Slug' => $business->slug,
        ]);

        $response->assertCreated();
        $response->assertJsonPath('business_id', $business->id);

        $this->assertDatabaseHas('bookings', [
            'court_id' => $court->id,
            'business_id' => $business->id,
        ]);
    }

    public function test_no_business_slug_keeps_legacy_compatibility(): void
    {
        $user = $this->actingAsUser();
        $business = $this->createBusiness('barberia-tres-amigos', 'Barbería Tres Amigos');
        $court = $this->createResource($business, 'Tres Amigos Chair');

        $response = $this->postJson('/api/v1/bookings', [
            'court_id' => $court->id,
            'date' => Carbon::now()->addDay()->toIso8601String(),
            'time_slot' => '6:00 PM to 7:00 PM',
        ]);

        $response->assertCreated();
        $response->assertJsonPath('business_id', null);

        $this->assertDatabaseHas('bookings', [
            'user_id' => $user->id,
            'court_id' => $court->id,
            'business_id' => null,
        ]);

        $index = $this->getJson('/api/v1/bookings');
        $index->assertOk();
        $index->assertJsonCount(1, 'data');
        $index->assertJsonPath('data.0.business_id', null);
    }
}
