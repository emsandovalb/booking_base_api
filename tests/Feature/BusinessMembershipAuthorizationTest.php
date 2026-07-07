<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Business;
use App\Models\Court;
use App\Models\Staff;
use App\Models\StaffRole;
use App\Models\User;
use App\Support\BusinessContext;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BusinessMembershipAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_belong_to_business_with_role(): void
    {
        $user = User::factory()->create();
        $business = $this->createBusiness('barberia-tres-amigos', 'Barberia Tres Amigos');

        $this->attachMembership($user, $business, 'staff');

        $this->assertTrue($user->belongsToBusiness($business));
        $this->assertTrue($user->hasBusinessRole($business, 'staff'));
        $this->assertSame('staff', $user->businesses()->first()->pivot->role);
    }

    public function test_owner_and_admin_can_manage_business(): void
    {
        $business = $this->createBusiness('barberia-tres-amigos', 'Barberia Tres Amigos');
        $owner = User::factory()->create();
        $admin = User::factory()->create();

        $this->attachMembership($owner, $business, 'owner');
        $this->attachMembership($admin, $business, 'admin');

        $context = new BusinessContext($business->slug, $business);

        $this->assertTrue($context->userCanManageBusiness($owner, $business));
        $this->assertTrue($context->userCanManageBusiness($admin, $business));
    }

    public function test_client_cannot_manage_business(): void
    {
        $business = $this->createBusiness('barberia-tres-amigos', 'Barberia Tres Amigos');
        $client = User::factory()->create();

        $this->attachMembership($client, $business, 'client');

        $context = new BusinessContext($business->slug, $business);

        $this->assertFalse($context->userCanManageBusiness($client, $business));
    }

    public function test_user_from_other_business_cannot_manage_business(): void
    {
        $businessA = $this->createBusiness('barberia-tres-amigos', 'Barberia Tres Amigos');
        $businessB = $this->createBusiness('salon-aurora', 'Salon Aurora');
        $user = User::factory()->create();

        $this->attachMembership($user, $businessB, 'admin');

        $context = new BusinessContext($businessA->slug, $businessA);

        $this->assertFalse($context->userCanManageBusiness($user, $businessA));
    }

    public function test_business_admin_can_create_resource_with_slug(): void
    {
        $admin = $this->actingAsAdmin();
        $business = $this->createBusiness('barberia-tres-amigos', 'Barberia Tres Amigos');
        $this->attachMembership($admin, $business, 'owner');

        $response = $this->postJson('/api/v1/resources', [
            'name' => 'Shave Station',
            'address' => 'Puntarenas, Costa Rica',
            'price_per_hour' => 5000,
            'rating' => 4.8,
        ], [
            'X-Business-Slug' => $business->slug,
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('courts', [
            'name' => 'Shave Station',
            'business_id' => $business->id,
        ]);
    }

    public function test_non_member_admin_cannot_create_resource_for_slug(): void
    {
        $this->actingAsAdmin();
        $business = $this->createBusiness('barberia-tres-amigos', 'Barberia Tres Amigos');

        $response = $this->postJson('/api/v1/resources', [
            'name' => 'Blocked Station',
            'address' => 'Puntarenas, Costa Rica',
            'price_per_hour' => 5000,
            'rating' => 4.8,
        ], [
            'X-Business-Slug' => $business->slug,
        ]);

        $response->assertStatus(403);
        $this->assertDatabaseMissing('courts', [
            'name' => 'Blocked Station',
        ]);
    }

    public function test_client_member_cannot_create_resource_for_slug(): void
    {
        $admin = $this->actingAsAdmin();
        $business = $this->createBusiness('barberia-tres-amigos', 'Barberia Tres Amigos');
        $this->attachMembership($admin, $business, 'client');

        $response = $this->postJson('/api/v1/resources', [
            'name' => 'Client Blocked Station',
            'address' => 'Puntarenas, Costa Rica',
            'price_per_hour' => 5000,
            'rating' => 4.8,
        ], [
            'X-Business-Slug' => $business->slug,
        ]);

        $response->assertStatus(403);
    }

    public function test_no_slug_keeps_legacy_resource_creation(): void
    {
        $this->actingAsAdmin();

        $response = $this->postJson('/api/v1/resources', [
            'name' => 'Legacy Station',
            'address' => 'Puntarenas, Costa Rica',
            'price_per_hour' => 5000,
            'rating' => 4.8,
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('courts', [
            'name' => 'Legacy Station',
            'business_id' => null,
        ]);
    }

    public function test_business_admin_can_create_staff_with_slug(): void
    {
        $admin = $this->actingAsAdmin();
        $business = $this->createBusiness('barberia-tres-amigos', 'Barberia Tres Amigos');
        $this->attachMembership($admin, $business, 'admin');
        $role = $this->createRole('barber', 'Barber');

        $response = $this->postJson('/api/v1/staff', [
            'name' => 'Business Barber',
            'staff_role_id' => $role->id,
            'is_active' => true,
        ], [
            'X-Business-Slug' => $business->slug,
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('staff', [
            'name' => 'Business Barber',
            'business_id' => $business->id,
        ]);
    }

    public function test_non_member_admin_cannot_create_staff_for_slug(): void
    {
        $this->actingAsAdmin();
        $business = $this->createBusiness('barberia-tres-amigos', 'Barberia Tres Amigos');
        $role = $this->createRole('barber', 'Barber');

        $response = $this->postJson('/api/v1/staff', [
            'name' => 'Blocked Barber',
            'staff_role_id' => $role->id,
            'is_active' => true,
        ], [
            'X-Business-Slug' => $business->slug,
        ]);

        $response->assertStatus(403);
        $this->assertDatabaseMissing('staff', [
            'name' => 'Blocked Barber',
        ]);
    }

    public function test_client_member_cannot_create_staff_for_slug(): void
    {
        $admin = $this->actingAsAdmin();
        $business = $this->createBusiness('barberia-tres-amigos', 'Barberia Tres Amigos');
        $this->attachMembership($admin, $business, 'client');
        $role = $this->createRole('barber', 'Barber');

        $response = $this->postJson('/api/v1/staff', [
            'name' => 'Client Blocked Barber',
            'staff_role_id' => $role->id,
            'is_active' => true,
        ], [
            'X-Business-Slug' => $business->slug,
        ]);

        $response->assertStatus(403);
    }

    public function test_no_slug_keeps_legacy_staff_creation(): void
    {
        $this->actingAsAdmin();
        $role = $this->createRole('barber', 'Barber');

        $response = $this->postJson('/api/v1/staff', [
            'name' => 'Legacy Barber',
            'staff_role_id' => $role->id,
            'is_active' => true,
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('staff', [
            'name' => 'Legacy Barber',
            'business_id' => null,
        ]);
    }

    public function test_business_admin_can_view_admin_reservations_for_business(): void
    {
        $admin = $this->actingAsAdmin();
        $business = $this->createBusiness('barberia-tres-amigos', 'Barberia Tres Amigos');
        $court = $this->createResource($business, 'Reservation Court');
        $this->attachMembership($admin, $business, 'owner');

        $this->createBooking($admin, $court, $business);

        $response = $this->getJson('/api/v1/admin/reservations?day=2026-07-08', [
            'X-Business-Slug' => $business->slug,
        ]);

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
    }

    public function test_non_member_admin_cannot_view_admin_reservations_for_business(): void
    {
        $this->actingAsAdmin();
        $business = $this->createBusiness('barberia-tres-amigos', 'Barberia Tres Amigos');

        $response = $this->getJson('/api/v1/admin/reservations?day=2026-07-08', [
            'X-Business-Slug' => $business->slug,
        ]);

        $response->assertStatus(403);
    }

    public function test_no_slug_keeps_legacy_admin_reservations(): void
    {
        $admin = $this->actingAsAdmin();
        $business = $this->createBusiness('barberia-tres-amigos', 'Barberia Tres Amigos');
        $court = $this->createResource($business, 'Legacy Reservation Court');
        $this->createBooking($admin, $court, null);

        $response = $this->getJson('/api/v1/admin/reservations?day=2026-07-08');

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
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
            'slug' => $slug,
            'description' => $name,
        ]);
    }

    private function createResource(Business $business, string $name): Court
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

    private function createBooking(User $user, Court $court, ?Business $business): Booking
    {
        return Booking::create([
            'user_id' => $user->id,
            'court_id' => $court->id,
            'business_id' => $business?->id,
            'date' => Carbon::parse('2026-07-08 10:00:00'),
            'time_slot' => '10:00 AM to 11:00 AM',
            'duration_hours' => 1,
            'status' => 'pending',
            'booking_code' => 'ABC123',
            'total_price' => 0,
        ]);
    }

    private function actingAsAdmin(): User
    {
        $admin = User::factory()->create();
        $admin->forceFill(['role' => 'admin'])->save();
        Sanctum::actingAs($admin);

        return $admin;
    }

    private function attachMembership(User $user, Business $business, string $role): void
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
