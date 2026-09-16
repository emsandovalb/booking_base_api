<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SuperAdminBusinessMembersTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_access_members_index(): void
    {
        $business = $this->createBusinessWithOwner();

        $this->get(route('super-admin.businesses.members.index', $business))
            ->assertRedirect('/login');
    }

    public function test_non_admin_cannot_access_members_index(): void
    {
        $user = User::factory()->create();
        $user->forceFill(['role' => 'user'])->save();
        $business = $this->createBusinessWithOwner();

        $this->actingAs($user)
            ->get(route('super-admin.businesses.members.index', $business))
            ->assertForbidden();
    }

    public function test_admin_can_view_members(): void
    {
        $admin = $this->makeAdmin();
        $business = $this->createBusinessWithOwner();
        $member = User::factory()->create([
            'name' => 'Member One',
            'email' => 'member.one@example.com',
        ]);
        $business->users()->attach($member->id, [
            'role' => 'staff',
            'status' => 'active',
            'accepted_at' => now(),
            'metadata' => ['source' => 'test'],
        ]);

        $this->actingAs($admin)
            ->get(route('super-admin.businesses.members.index', $business))
            ->assertOk()
            ->assertSee('Manage business members')
            ->assertSee('Member One')
            ->assertSee('member.one@example.com')
            ->assertSee('Staff');
    }

    public function test_admin_can_add_member_and_existing_user_attaches(): void
    {
        $admin = $this->makeAdmin();
        $business = $this->createBusinessWithOwner();
        $existing = User::factory()->create([
            'name' => 'Existing User',
            'email' => 'existing@example.com',
        ]);

        $this->actingAs($admin)
            ->post(route('super-admin.businesses.members.store', $business), [
                'full_name' => 'Existing User Should Stay',
                'email' => $existing->email,
                'temporary_password' => 'Password123!',
                'role' => 'manager',
                'status' => 'active',
                'send_invitation_later' => '1',
                'metadata' => '{"source":"manual"}',
            ])
            ->assertRedirect(route('super-admin.businesses.members.index', $business));

        $this->assertDatabaseHas('business_user', [
            'business_id' => $business->id,
            'user_id' => $existing->id,
            'role' => 'manager',
            'status' => 'active',
        ]);
        $this->assertSame(3, User::query()->count());
        $this->assertSame('Existing User', $existing->fresh()->name);
    }

    public function test_admin_can_create_new_member(): void
    {
        $admin = $this->makeAdmin();
        $business = $this->createBusinessWithOwner();

        $this->actingAs($admin)
            ->post(route('super-admin.businesses.members.store', $business), [
                'full_name' => 'New Member',
                'email' => 'new.member@example.com',
                'temporary_password' => 'Password123!',
                'role' => 'staff',
                'status' => 'pending',
                'send_invitation_later' => '1',
                'metadata' => '{"source":"wizard"}',
            ])
            ->assertRedirect(route('super-admin.businesses.members.index', $business));

        $newUser = User::query()->where('email', 'new.member@example.com')->firstOrFail();

        $this->assertDatabaseHas('users', [
            'id' => $newUser->id,
            'name' => 'New Member',
            'email' => 'new.member@example.com',
        ]);
        $this->assertDatabaseHas('business_user', [
            'business_id' => $business->id,
            'user_id' => $newUser->id,
            'role' => 'staff',
            'status' => 'pending',
        ]);
    }

    public function test_admin_can_update_member_role(): void
    {
        $admin = $this->makeAdmin();
        $business = $this->createBusinessWithOwner();
        $member = $this->attachMember($business, 'member@example.com', 'staff', 'active');

        $this->actingAs($admin)
            ->put(route('super-admin.businesses.members.update', [$business, $member]), [
                'role' => 'manager',
                'status' => 'active',
                'metadata' => '{"note":"updated"}',
            ])
            ->assertRedirect(route('super-admin.businesses.members.index', $business));

        $this->assertDatabaseHas('business_user', [
            'business_id' => $business->id,
            'user_id' => $member->id,
            'role' => 'manager',
            'status' => 'active',
        ]);
    }

    public function test_admin_can_update_member_status(): void
    {
        $admin = $this->makeAdmin();
        $business = $this->createBusinessWithOwner();
        $member = $this->attachMember($business, 'status@example.com', 'staff', 'active');

        $this->actingAs($admin)
            ->patch(route('super-admin.businesses.members.status', [$business, $member]), [
                'status' => 'suspended',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('business_user', [
            'business_id' => $business->id,
            'user_id' => $member->id,
            'status' => 'suspended',
        ]);
    }

    public function test_cannot_delete_last_owner(): void
    {
        $admin = $this->makeAdmin();
        $business = $this->createBusinessWithOwner();
        $owner = $business->users()->wherePivot('role', 'owner')->firstOrFail();

        $this->actingAs($admin)
            ->from(route('super-admin.businesses.members.index', $business))
            ->delete(route('super-admin.businesses.members.destroy', [$business, $owner]))
            ->assertSessionHasErrors('role');

        $this->assertDatabaseHas('business_user', [
            'business_id' => $business->id,
            'user_id' => $owner->id,
            'role' => 'owner',
        ]);
    }

    public function test_cannot_demote_last_owner(): void
    {
        $admin = $this->makeAdmin();
        $business = $this->createBusinessWithOwner();
        $owner = $business->users()->wherePivot('role', 'owner')->firstOrFail();

        $this->actingAs($admin)
            ->from(route('super-admin.businesses.members.edit', [$business, $owner]))
            ->put(route('super-admin.businesses.members.update', [$business, $owner]), [
                'role' => 'admin',
                'status' => 'active',
                'metadata' => '{}',
            ])
            ->assertSessionHasErrors('role');

        $this->assertDatabaseHas('business_user', [
            'business_id' => $business->id,
            'user_id' => $owner->id,
            'role' => 'owner',
        ]);
    }

    public function test_membership_removed_and_user_preserved(): void
    {
        $admin = $this->makeAdmin();
        $business = $this->createBusinessWithOwner();
        $member = $this->attachMember($business, 'remove@example.com', 'staff', 'active');

        $this->actingAs($admin)
            ->delete(route('super-admin.businesses.members.destroy', [$business, $member]))
            ->assertRedirect(route('super-admin.businesses.members.index', $business));

        $this->assertDatabaseMissing('business_user', [
            'business_id' => $business->id,
            'user_id' => $member->id,
        ]);
        $this->assertDatabaseHas('users', [
            'id' => $member->id,
            'email' => 'remove@example.com',
        ]);
    }

    private function makeAdmin(): User
    {
        $admin = User::factory()->create([
            'name' => 'Super Admin',
        ]);
        $admin->forceFill(['role' => 'admin', 'is_super_admin' => true])->save();

        return $admin;
    }

    private function createBusinessWithOwner(): Business
    {
        $business = Business::factory()->create([
            'name' => 'Member Business',
            'slug' => 'member-business',
            'status' => 'active',
        ]);

        $owner = User::factory()->create([
            'name' => 'Business Owner',
            'email' => 'owner@example.com',
        ]);

        $business->users()->attach($owner->id, [
            'role' => 'owner',
            'status' => 'active',
            'accepted_at' => now(),
            'metadata' => ['source' => 'factory'],
        ]);

        return $business->fresh();
    }

    private function attachMember(Business $business, string $email, string $role, string $status): User
    {
        $member = User::factory()->create([
            'name' => ucfirst(explode('@', $email)[0]),
            'email' => $email,
        ]);

        $business->users()->attach($member->id, [
            'role' => $role,
            'status' => $status,
            'accepted_at' => $status === 'active' ? now() : null,
            'invited_at' => $status === 'pending' ? now() : null,
            'metadata' => ['source' => 'test'],
        ]);

        return $member;
    }
}
