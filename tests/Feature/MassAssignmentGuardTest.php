<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Confirms is_super_admin (User) and role (business_user pivot) cannot be
 * set or escalated through any user-facing request payload — only through
 * BootstrapAdminSeeder and the Super Admin panel's own validated writes.
 */
class MassAssignmentGuardTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_update_cannot_set_is_super_admin(): void
    {
        $user = User::factory()->create(['role' => 'user', 'is_super_admin' => false]);
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/auth/profile', [
            'name' => 'Still Just A User',
            'is_super_admin' => true,
        ]);

        $response->assertOk();
        $this->assertFalse($user->fresh()->is_super_admin);
    }

    public function test_profile_update_cannot_set_legacy_role(): void
    {
        $user = User::factory()->create(['role' => 'user']);
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/auth/profile', [
            'name' => 'Still Just A User',
            'role' => 'admin',
        ]);

        $response->assertOk();
        $this->assertSame('user', $user->fresh()->role);
    }

    public function test_registration_payload_cannot_set_is_super_admin_or_role(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'New Person',
            'email' => 'new.person@example.com',
            'password' => 'password123',
            'is_super_admin' => true,
            'role' => 'admin',
        ]);

        $response->assertOk();

        $user = User::where('email', 'new.person@example.com')->firstOrFail();
        $this->assertFalse((bool) $user->is_super_admin);
        $this->assertSame('user', $user->role);
    }

    public function test_business_member_cannot_escalate_their_own_pivot_role_via_profile_update(): void
    {
        $business = Business::create([
            'name' => 'Barberia Tres Amigos',
            'slug' => 'barberia-tres-amigos-' . uniqid(),
            'business_type' => 'barbershop',
            'status' => 'active',
        ]);
        $user = User::factory()->create(['role' => 'user']);
        $business->users()->syncWithoutDetaching([
            $user->id => [
                'role' => 'client',
                'status' => 'active',
                'accepted_at' => now(),
            ],
        ]);
        Sanctum::actingAs($user);

        // There is no API surface for a user to write their own business_user
        // pivot role; the profile endpoint doesn't accept business/pivot
        // fields at all, so smuggling one in should have zero effect.
        $this->postJson('/api/v1/auth/profile', [
            'name' => 'Client Trying To Escalate',
            'business_role' => 'owner',
            'businesses' => [['id' => $business->id, 'pivot' => ['role' => 'owner']]],
        ])->assertOk();

        $this->assertDatabaseHas('business_user', [
            'business_id' => $business->id,
            'user_id' => $user->id,
            'role' => 'client',
        ]);
    }
}
