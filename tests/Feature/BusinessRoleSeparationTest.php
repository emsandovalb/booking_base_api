<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\Court;
use App\Models\Staff;
use App\Models\StaffRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Locks in the split between per-business membership (business_user pivot)
 * and the platform-level is_super_admin flag: neither the legacy `role`
 * column nor is_super_admin should grant business-management rights, and
 * business membership alone should never grant Super Admin panel access.
 */
class BusinessRoleSeparationTest extends TestCase
{
    use RefreshDatabase;

    public function test_plain_user_with_business_owner_membership_can_create_a_court(): void
    {
        $owner = User::factory()->create(['role' => 'user']);
        Sanctum::actingAs($owner);
        $business = $this->createBusiness();
        $this->assignMembership($owner, $business, 'owner');

        $response = $this->postJson('/api/v1/courts', [
            'name' => 'Owner Chair',
            'address' => 'Somewhere',
        ], $this->headers($business));

        $response->assertCreated();
        $this->assertDatabaseHas('courts', [
            'name' => 'Owner Chair',
            'business_id' => $business->id,
        ]);
    }

    public function test_plain_user_with_business_admin_membership_can_manage_staff(): void
    {
        $admin = User::factory()->create(['role' => 'user']);
        Sanctum::actingAs($admin);
        $business = $this->createBusiness();
        $this->assignMembership($admin, $business, 'admin');
        $role = StaffRole::create(['name' => 'Barber', 'slug' => 'barber-' . uniqid(), 'description' => 'Barber']);

        $response = $this->postJson('/api/v1/staff', [
            'name' => 'Managed Barber',
            'staff_role_id' => $role->id,
        ], $this->headers($business));

        $response->assertCreated();
    }

    public function test_legacy_global_admin_role_without_business_membership_cannot_manage_a_court(): void
    {
        $legacyAdmin = User::factory()->create(['role' => 'admin']);
        Sanctum::actingAs($legacyAdmin);
        $business = $this->createBusiness();

        $response = $this->postJson('/api/v1/courts', [
            'name' => 'Rogue Chair',
            'address' => 'Somewhere',
        ], $this->headers($business));

        $response->assertStatus(403);
        $this->assertDatabaseMissing('courts', ['name' => 'Rogue Chair']);
    }

    public function test_legacy_global_admin_role_without_business_membership_cannot_view_courts_via_mine(): void
    {
        $legacyAdmin = User::factory()->create(['role' => 'admin']);
        Sanctum::actingAs($legacyAdmin);
        $business = $this->createBusiness();
        Court::factory()->create(['business_id' => $business->id]);

        $response = $this->getJson('/api/v1/my/grounds', $this->headers($business));

        $response->assertOk();
        $response->assertJsonPath('data', []);
    }

    public function test_super_admin_flag_without_business_membership_cannot_manage_a_court(): void
    {
        $superAdmin = User::factory()->create(['role' => 'user', 'is_super_admin' => true]);
        Sanctum::actingAs($superAdmin);
        $business = $this->createBusiness();

        $response = $this->postJson('/api/v1/courts', [
            'name' => 'Platform Chair',
            'address' => 'Somewhere',
        ], $this->headers($business));

        $response->assertStatus(403);
        $this->assertDatabaseMissing('courts', ['name' => 'Platform Chair']);
    }

    public function test_super_admin_flag_without_business_membership_cannot_manage_staff(): void
    {
        $superAdmin = User::factory()->create(['role' => 'user', 'is_super_admin' => true]);
        Sanctum::actingAs($superAdmin);
        $business = $this->createBusiness();
        $role = StaffRole::create(['name' => 'Barber', 'slug' => 'barber-' . uniqid(), 'description' => 'Barber']);

        $response = $this->postJson('/api/v1/staff', [
            'name' => 'Platform Barber',
            'staff_role_id' => $role->id,
        ], $this->headers($business));

        $response->assertStatus(403);
    }

    public function test_super_admin_flag_without_business_membership_cannot_confirm_a_booking(): void
    {
        $superAdmin = User::factory()->create(['role' => 'user', 'is_super_admin' => true]);
        $client = User::factory()->create(['role' => 'user']);
        $business = $this->createBusiness();
        $court = Court::factory()->create(['business_id' => $business->id, 'status' => 'active']);
        $booking = \App\Models\Booking::create([
            'user_id' => $client->id,
            'court_id' => $court->id,
            'business_id' => $business->id,
            'date' => now()->addDay(),
            'time_slot' => '6:00 PM to 7:00 PM',
            'duration_hours' => 1,
            'status' => 'pending',
            'booking_code' => 'SUPR01',
            'total_price' => 0,
        ]);
        Sanctum::actingAs($superAdmin);

        $response = $this->postJson('/api/v1/bookings/' . $booking->id . '/confirm', [], $this->headers($business));

        $response->assertStatus(403);
        $this->assertDatabaseHas('bookings', ['id' => $booking->id, 'status' => 'pending']);
    }

    public function test_business_owner_membership_alone_cannot_reach_the_super_admin_panel(): void
    {
        $owner = User::factory()->create(['role' => 'user']);
        $business = $this->createBusiness();
        $this->assignMembership($owner, $business, 'owner');

        $this->actingAs($owner)
            ->get('/super-admin')
            ->assertForbidden();
    }

    public function test_legacy_global_admin_role_alone_cannot_reach_the_super_admin_panel(): void
    {
        $legacyAdmin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($legacyAdmin)
            ->get('/super-admin')
            ->assertForbidden();
    }

    public function test_is_super_admin_flag_reaches_the_super_admin_panel_without_any_business_membership(): void
    {
        $superAdmin = User::factory()->create(['role' => 'user', 'is_super_admin' => true]);

        $this->actingAs($superAdmin)
            ->get('/super-admin')
            ->assertOk();
    }

    private function createBusiness(): Business
    {
        return Business::create([
            'name' => 'Barberia Tres Amigos',
            'slug' => 'barberia-tres-amigos-' . uniqid(),
            'business_type' => 'barbershop',
            'status' => 'active',
        ]);
    }

    private function headers(Business $business): array
    {
        return ['X-Business-Slug' => $business->slug];
    }

    private function assignMembership(User $user, Business $business, string $role): void
    {
        $business->users()->syncWithoutDetaching([
            $user->id => [
                'role' => $role,
                'status' => 'active',
                'accepted_at' => now(),
            ],
        ]);
    }
}
