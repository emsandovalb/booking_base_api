<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AuthMeBusinessMembershipTest extends TestCase
{
    use RefreshDatabase;

    public function test_auth_me_with_business_slug_returns_active_owner_membership_and_manage_capability(): void
    {
        $business = $this->createBusiness('jc-studio', 'JC Studio');
        $user = User::factory()->create([
            'role' => 'user',
        ]);
        $this->attachMembership($user, $business, 'owner', 'active');

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/auth/me', [
            'X-Business-Slug' => $business->slug,
        ]);

        $response->assertOk();
        $response->assertJsonPath('user.business.id', $business->id);
        $response->assertJsonPath('user.business.slug', $business->slug);
        $response->assertJsonPath('user.business_role', 'owner');
        $response->assertJsonPath('user.business_status', 'active');
        $response->assertJsonPath('user.can_manage_business', true);
    }

    public function test_auth_me_with_business_slug_returns_active_admin_membership_and_manage_capability(): void
    {
        $business = $this->createBusiness('jc-studio', 'JC Studio');
        $user = User::factory()->create([
            'role' => 'user',
        ]);
        $this->attachMembership($user, $business, 'admin', 'active');

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/auth/me', [
            'X-Business-Slug' => $business->slug,
        ]);

        $response->assertOk();
        $response->assertJsonPath('user.business_role', 'admin');
        $response->assertJsonPath('user.business_status', 'active');
        $response->assertJsonPath('user.can_manage_business', true);
    }

    public function test_auth_me_with_business_slug_returns_client_membership_without_manage_capability(): void
    {
        $business = $this->createBusiness('jc-studio', 'JC Studio');
        $user = User::factory()->create([
            'role' => 'user',
        ]);
        $this->attachMembership($user, $business, 'client', 'active');

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/auth/me', [
            'X-Business-Slug' => $business->slug,
        ]);

        $response->assertOk();
        $response->assertJsonPath('user.business_role', 'client');
        $response->assertJsonPath('user.business_status', 'active');
        $response->assertJsonPath('user.can_manage_business', false);
    }

    public function test_auth_me_with_wrong_business_slug_returns_no_membership_and_no_manage_capability(): void
    {
        $targetBusiness = $this->createBusiness('jc-studio', 'JC Studio');
        $otherBusiness = $this->createBusiness('other-studio', 'Other Studio');
        $user = User::factory()->create([
            'role' => 'user',
        ]);
        $this->attachMembership($user, $otherBusiness, 'owner', 'active');

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/auth/me', [
            'X-Business-Slug' => $targetBusiness->slug,
        ]);

        $response->assertOk();
        $response->assertJsonPath('user.business', null);
        $response->assertJsonPath('user.business_role', null);
        $response->assertJsonPath('user.business_status', null);
        $response->assertJsonPath('user.can_manage_business', false);
    }

    public function test_auth_me_with_suspended_membership_returns_no_manage_capability(): void
    {
        $business = $this->createBusiness('jc-studio', 'JC Studio');
        $user = User::factory()->create([
            'role' => 'user',
        ]);
        $this->attachMembership($user, $business, 'owner', 'suspended');

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/auth/me', [
            'X-Business-Slug' => $business->slug,
        ]);

        $response->assertOk();
        $response->assertJsonPath('user.business', null);
        $response->assertJsonPath('user.business_role', null);
        $response->assertJsonPath('user.business_status', null);
        $response->assertJsonPath('user.can_manage_business', false);
    }

    public function test_auth_me_without_business_slug_has_no_manage_capability_even_for_legacy_global_role(): void
    {
        // The legacy `role` column no longer grants business-management
        // rights on its own — canManageBusiness() is purely membership-based,
        // and there's no business to manage without a business context.
        $user = User::factory()->create([
            'role' => 'admin',
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/auth/me');

        $response->assertOk();
        $response->assertJsonPath('user.business', null);
        $response->assertJsonPath('user.can_manage_business', false);
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

    private function attachMembership(
        User $user,
        Business $business,
        string $role,
        string $status,
    ): void {
        $business->users()->syncWithoutDetaching([
            $user->id => [
                'role' => $role,
                'status' => $status,
                'accepted_at' => now(),
            ],
        ]);
    }
}
