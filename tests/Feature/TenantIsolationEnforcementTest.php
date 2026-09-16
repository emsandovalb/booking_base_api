<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Business;
use App\Models\Court;
use App\Models\Staff;
use App\Models\StaffRole;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Locks down the tenant-isolation fix in BusinessContext/route bindings:
 * business-scoped data must never be reachable without a resolved
 * business context, and a valid context for one business must never
 * expose another business's records by ID.
 */
class TenantIsolationEnforcementTest extends TestCase
{
    use RefreshDatabase;

    // -----------------------------------------------------------------
    // Missing/invalid business context is a hard failure, not a leak.
    // -----------------------------------------------------------------

    public function test_listing_resources_without_business_slug_fails_instead_of_leaking_all_tenants(): void
    {
        $businessA = $this->createBusiness('barberia-tres-amigos', 'Barberia Tres Amigos');
        $businessB = $this->createBusiness('salon-aurora', 'Salon Aurora');
        $courtA = $this->createCourt($businessA, 'Tres Amigos Corte');
        $courtB = $this->createCourt($businessB, 'Aurora Facial');

        $response = $this->getJson('/api/v1/resources');

        $response->assertStatus(404);
        $response->assertJsonMissing(['id' => $courtA->id]);
        $response->assertJsonMissing(['id' => $courtB->id]);
    }

    public function test_listing_staff_without_business_slug_fails_instead_of_leaking_all_tenants(): void
    {
        $business = $this->createBusiness('barberia-tres-amigos', 'Barberia Tres Amigos');
        $role = $this->createRole('barber', 'Barber');
        $staff = $this->createStaff($business, $role, 'Carlos Barber');

        $response = $this->getJson('/api/v1/staff');

        $response->assertStatus(404);
        $response->assertJsonMissing(['id' => $staff->id]);
    }

    public function test_fetching_a_resource_by_id_without_business_slug_fails(): void
    {
        $business = $this->createBusiness('barberia-tres-amigos', 'Barberia Tres Amigos');
        $court = $this->createCourt($business, 'Tres Amigos Corte');

        $response = $this->getJson('/api/v1/resources/' . $court->id);

        $response->assertStatus(404);
    }

    public function test_fetching_staff_by_id_without_business_slug_fails(): void
    {
        $business = $this->createBusiness('barberia-tres-amigos', 'Barberia Tres Amigos');
        $role = $this->createRole('barber', 'Barber');
        $staff = $this->createStaff($business, $role, 'Carlos Barber');

        $response = $this->getJson('/api/v1/staff/' . $staff->id);

        $response->assertStatus(404);
    }

    public function test_creating_a_booking_without_business_slug_fails(): void
    {
        $this->actingAsUser();
        $business = $this->createBusiness('barberia-tres-amigos', 'Barberia Tres Amigos');
        $court = $this->createCourt($business, 'Tres Amigos Corte');

        $response = $this->postJson('/api/v1/bookings', [
            'court_id' => $court->id,
            'date' => Carbon::now()->addDay()->toIso8601String(),
            'time_slot' => '6:00 PM to 7:00 PM',
        ]);

        $response->assertStatus(404);
        $this->assertDatabaseMissing('bookings', ['court_id' => $court->id]);
    }

    public function test_listing_bookings_without_business_slug_fails_instead_of_leaking_other_users_data(): void
    {
        $user = $this->actingAsUser();
        $business = $this->createBusiness('barberia-tres-amigos', 'Barberia Tres Amigos');
        $court = $this->createCourt($business, 'Tres Amigos Corte');
        $booking = $this->createBooking($user, $court, $business);

        $response = $this->getJson('/api/v1/bookings');

        $response->assertStatus(404);
        $response->assertJsonMissing(['id' => $booking->id]);
    }

    public function test_admin_creating_a_court_without_business_slug_fails(): void
    {
        $this->actingAsAdmin();

        $response = $this->postJson('/api/v1/courts', [
            'name' => 'Rogue Court',
            'address' => 'Nowhere',
        ]);

        $response->assertStatus(404);
        $this->assertDatabaseMissing('courts', ['name' => 'Rogue Court']);
    }

    public function test_admin_creating_staff_without_business_slug_fails(): void
    {
        $this->actingAsAdmin();
        $role = $this->createRole('barber', 'Barber');

        $response = $this->postJson('/api/v1/staff', [
            'name' => 'Rogue Barber',
            'staff_role_id' => $role->id,
        ]);

        $response->assertStatus(404);
        $this->assertDatabaseMissing('staff', ['name' => 'Rogue Barber']);
    }

    public function test_invalid_business_slug_fails_the_same_way_as_a_missing_one(): void
    {
        $response = $this->getJson('/api/v1/resources', [
            'X-Business-Slug' => 'does-not-exist',
        ]);

        $response->assertStatus(404);
    }

    // -----------------------------------------------------------------
    // A valid context for Business A must never reach Business B's data.
    // -----------------------------------------------------------------

    public function test_business_a_admin_cannot_read_business_bs_court_by_id(): void
    {
        $admin = $this->actingAsAdmin();
        $businessA = $this->createBusiness('barberia-tres-amigos', 'Barberia Tres Amigos');
        $businessB = $this->createBusiness('salon-aurora', 'Salon Aurora');
        $this->assignMembership($admin, $businessA, 'owner');
        $courtB = $this->createCourt($businessB, 'Aurora Facial');

        $response = $this->getJson('/api/v1/resources/' . $courtB->id, [
            'X-Business-Slug' => $businessA->slug,
        ]);

        $response->assertStatus(404);
    }

    public function test_business_a_admin_cannot_update_business_bs_court_by_id(): void
    {
        $admin = $this->actingAsAdmin();
        $businessA = $this->createBusiness('barberia-tres-amigos', 'Barberia Tres Amigos');
        $businessB = $this->createBusiness('salon-aurora', 'Salon Aurora');
        $this->assignMembership($admin, $businessA, 'owner');
        $courtB = $this->createCourt($businessB, 'Aurora Facial');

        $response = $this->putJson('/api/v1/courts/' . $courtB->id, [
            'name' => 'Hijacked Name',
        ], [
            'X-Business-Slug' => $businessA->slug,
        ]);

        $response->assertStatus(404);
        $this->assertDatabaseHas('courts', [
            'id' => $courtB->id,
            'name' => 'Aurora Facial',
        ]);
    }

    public function test_business_a_admin_cannot_delete_business_bs_court_by_id(): void
    {
        $admin = $this->actingAsAdmin();
        $businessA = $this->createBusiness('barberia-tres-amigos', 'Barberia Tres Amigos');
        $businessB = $this->createBusiness('salon-aurora', 'Salon Aurora');
        $this->assignMembership($admin, $businessA, 'owner');
        $courtB = $this->createCourt($businessB, 'Aurora Facial');

        $response = $this->deleteJson('/api/v1/courts/' . $courtB->id, [], [
            'X-Business-Slug' => $businessA->slug,
        ]);

        $response->assertStatus(404);
        $this->assertDatabaseHas('courts', [
            'id' => $courtB->id,
            'status' => 'active',
        ]);
    }

    public function test_business_a_admin_cannot_read_business_bs_staff_by_id(): void
    {
        $admin = $this->actingAsAdmin();
        $businessA = $this->createBusiness('barberia-tres-amigos', 'Barberia Tres Amigos');
        $businessB = $this->createBusiness('salon-aurora', 'Salon Aurora');
        $this->assignMembership($admin, $businessA, 'owner');
        $role = $this->createRole('stylist', 'Stylist');
        $staffB = $this->createStaff($businessB, $role, 'Alicia Stylist');

        $response = $this->getJson('/api/v1/staff/' . $staffB->id, [
            'X-Business-Slug' => $businessA->slug,
        ]);

        $response->assertStatus(404);
    }

    public function test_business_a_admin_cannot_update_business_bs_staff_by_id(): void
    {
        $admin = $this->actingAsAdmin();
        $businessA = $this->createBusiness('barberia-tres-amigos', 'Barberia Tres Amigos');
        $businessB = $this->createBusiness('salon-aurora', 'Salon Aurora');
        $this->assignMembership($admin, $businessA, 'owner');
        $role = $this->createRole('stylist', 'Stylist');
        $staffB = $this->createStaff($businessB, $role, 'Alicia Stylist');

        $response = $this->patchJson('/api/v1/staff/' . $staffB->id, [
            'name' => 'Hijacked Barber',
        ], [
            'X-Business-Slug' => $businessA->slug,
        ]);

        $response->assertStatus(404);
        $this->assertDatabaseHas('staff', [
            'id' => $staffB->id,
            'name' => 'Alicia Stylist',
        ]);
    }

    public function test_business_a_admin_cannot_deactivate_business_bs_staff_by_id(): void
    {
        $admin = $this->actingAsAdmin();
        $businessA = $this->createBusiness('barberia-tres-amigos', 'Barberia Tres Amigos');
        $businessB = $this->createBusiness('salon-aurora', 'Salon Aurora');
        $this->assignMembership($admin, $businessA, 'owner');
        $role = $this->createRole('stylist', 'Stylist');
        $staffB = $this->createStaff($businessB, $role, 'Alicia Stylist');

        $response = $this->patchJson('/api/v1/staff/' . $staffB->id . '/deactivate', [], [
            'X-Business-Slug' => $businessA->slug,
        ]);

        $response->assertStatus(404);
        $this->assertDatabaseHas('staff', [
            'id' => $staffB->id,
            'is_active' => true,
        ]);
    }

    public function test_business_a_user_cannot_read_business_bs_booking_by_id(): void
    {
        $user = $this->actingAsUser();
        $businessA = $this->createBusiness('barberia-tres-amigos', 'Barberia Tres Amigos');
        $businessB = $this->createBusiness('salon-aurora', 'Salon Aurora');
        $courtB = $this->createCourt($businessB, 'Aurora Facial');
        $bookingB = $this->createBooking($user, $courtB, $businessB);

        $response = $this->getJson('/api/v1/bookings/' . $bookingB->id, [
            'X-Business-Slug' => $businessA->slug,
        ]);

        $response->assertStatus(404);
    }

    public function test_business_a_admin_cannot_confirm_business_bs_booking_by_id(): void
    {
        $admin = $this->actingAsAdmin();
        $client = User::factory()->create(['role' => 'user']);
        $businessA = $this->createBusiness('barberia-tres-amigos', 'Barberia Tres Amigos');
        $businessB = $this->createBusiness('salon-aurora', 'Salon Aurora');
        $this->assignMembership($admin, $businessA, 'owner');
        $courtB = $this->createCourt($businessB, 'Aurora Facial');
        $bookingB = $this->createBooking($client, $courtB, $businessB);
        Sanctum::actingAs($admin);

        $response = $this->postJson('/api/v1/bookings/' . $bookingB->id . '/confirm', [], [
            'X-Business-Slug' => $businessA->slug,
        ]);

        $response->assertStatus(404);
        $this->assertDatabaseHas('bookings', [
            'id' => $bookingB->id,
            'status' => 'pending',
        ]);
    }

    public function test_business_a_slug_cannot_be_used_to_create_a_booking_against_business_bs_court(): void
    {
        $this->actingAsUser();
        $businessA = $this->createBusiness('barberia-tres-amigos', 'Barberia Tres Amigos');
        $businessB = $this->createBusiness('salon-aurora', 'Salon Aurora');
        $courtB = $this->createCourt($businessB, 'Aurora Facial');

        $response = $this->postJson('/api/v1/bookings', [
            'court_id' => $courtB->id,
            'date' => Carbon::now()->addDay()->toIso8601String(),
            'time_slot' => '6:00 PM to 7:00 PM',
        ], [
            'X-Business-Slug' => $businessA->slug,
        ]);

        $response->assertStatus(422);
        $this->assertDatabaseMissing('bookings', ['court_id' => $courtB->id]);
    }

    // -----------------------------------------------------------------
    // helpers
    // -----------------------------------------------------------------

    private function actingAsAdmin(): User
    {
        $admin = User::factory()->create();
        $admin->forceFill(['role' => 'admin'])->save();
        Sanctum::actingAs($admin);

        return $admin;
    }

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
            'business_type' => 'barbershop',
            'status' => 'active',
        ]);
    }

    private function createRole(string $slug, string $name): StaffRole
    {
        return StaffRole::create([
            'name' => $name,
            'slug' => $slug . '-' . uniqid(),
            'description' => $name,
        ]);
    }

    private function createCourt(Business $business, string $name): Court
    {
        return Court::create([
            'business_id' => $business->id,
            'name' => $name,
            'address' => 'Puntarenas, Costa Rica',
            'price_per_hour' => 5000,
            'rating' => 4.8,
            'status' => 'active',
        ]);
    }

    private function createStaff(Business $business, StaffRole $role, string $name): Staff
    {
        return Staff::create([
            'business_id' => $business->id,
            'staff_role_id' => $role->id,
            'name' => $name,
            'email' => strtolower(str_replace(' ', '.', $name)) . '@example.com',
            'is_active' => true,
        ]);
    }

    private function createBooking(User $user, Court $court, Business $business): Booking
    {
        return Booking::create([
            'user_id' => $user->id,
            'court_id' => $court->id,
            'business_id' => $business->id,
            'date' => Carbon::now()->addDay(),
            'time_slot' => '6:00 PM to 7:00 PM',
            'duration_hours' => 1,
            'status' => 'pending',
            'booking_code' => Str::upper(Str::random(6)),
            'total_price' => 0,
        ]);
    }

    private function assignMembership(User $user, Business $business, string $role): void
    {
        $business->users()->syncWithoutDetaching([
            $user->id => [
                'role' => $role,
                'status' => 'active',
                'accepted_at' => now(),
                'metadata' => ['test' => true],
            ],
        ]);
    }
}
