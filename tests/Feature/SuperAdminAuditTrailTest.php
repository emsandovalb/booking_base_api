<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Business;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SuperAdminAuditTrailTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_denied(): void
    {
        $business = Business::factory()->create([
            'name' => 'Guest Business',
            'slug' => 'guest-business',
        ]);

        $this->get(route('super-admin.businesses.show', $business))
            ->assertRedirect('/login');
    }

    public function test_non_admin_denied(): void
    {
        $user = User::factory()->create(['name' => 'Regular User']);
        $user->forceFill(['role' => 'user'])->save();
        $business = Business::factory()->create([
            'name' => 'Denied Business',
            'slug' => 'denied-business',
        ]);

        $this->actingAs($user)
            ->get(route('super-admin.businesses.show', $business))
            ->assertForbidden();
    }

    public function test_business_creation_creates_audit(): void
    {
        $admin = $this->makeAdmin();

        $payload = $this->wizardPayload();

        $response = $this->actingAs($admin)->post(route('super-admin.businesses.store'), $payload);

        $business = Business::query()->where('slug', 'audit-new-business')->firstOrFail();

        $response->assertRedirect(route('super-admin.businesses.workspace', $business));

        $this->assertDatabaseHas('audit_logs', [
            'business_id' => $business->id,
            'action' => 'business.wizard.created',
            'user_id' => $admin->id,
        ]);
    }

    public function test_business_update_creates_audit(): void
    {
        $admin = $this->makeAdmin();
        $business = Business::factory()->create([
            'name' => 'Audit Update',
            'slug' => 'audit-update',
            'status' => 'active',
        ]);

        $payload = $this->businessPayload([
            'name' => 'Audit Update Revised',
            'slug' => 'audit-update-revised',
        ]);

        $this->actingAs($admin)
            ->put(route('super-admin.businesses.update', $business), $payload)
            ->assertRedirect(route('super-admin.businesses.show', $business));

        $this->assertDatabaseHas('audit_logs', [
            'business_id' => $business->id,
            'action' => 'business.updated',
            'user_id' => $admin->id,
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'business_id' => $business->id,
            'action' => 'workspace.configuration_updated',
            'user_id' => $admin->id,
        ]);
    }

    public function test_suspend_creates_audit(): void
    {
        $admin = $this->makeAdmin();
        $business = Business::factory()->create(['status' => 'active']);

        $this->actingAs($admin)
            ->patch(route('super-admin.businesses.suspend', $business))
            ->assertRedirect();

        $this->assertDatabaseHas('audit_logs', [
            'business_id' => $business->id,
            'action' => 'business.suspended',
            'user_id' => $admin->id,
        ]);
    }

    public function test_activate_creates_audit(): void
    {
        $admin = $this->makeAdmin();
        $business = Business::factory()->create(['status' => 'suspended']);

        $this->actingAs($admin)
            ->patch(route('super-admin.businesses.activate', $business))
            ->assertRedirect();

        $this->assertDatabaseHas('audit_logs', [
            'business_id' => $business->id,
            'action' => 'business.activated',
            'user_id' => $admin->id,
        ]);
    }

    public function test_member_create_creates_audit(): void
    {
        $admin = $this->makeAdmin();
        $business = Business::factory()->create([
            'name' => 'Member Audit',
            'slug' => 'member-audit',
        ]);

        $this->actingAs($admin)
            ->post(route('super-admin.businesses.members.store', $business), [
                'full_name' => 'New Audit Member',
                'email' => 'new-audit-member@example.com',
                'temporary_password' => 'Password123!',
                'role' => 'staff',
                'status' => 'active',
                'send_invitation_later' => '0',
                'metadata' => '{"source":"manual"}',
            ])
            ->assertRedirect(route('super-admin.businesses.members.index', $business));

        $member = User::query()->where('email', 'new-audit-member@example.com')->firstOrFail();

        $this->assertDatabaseHas('audit_logs', [
            'business_id' => $business->id,
            'action' => 'member.created',
            'user_id' => $admin->id,
            'subject_id' => $member->id,
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'business_id' => $business->id,
            'action' => 'member.attached',
            'user_id' => $admin->id,
            'subject_id' => $member->id,
        ]);
    }

    public function test_role_update_creates_audit(): void
    {
        $admin = $this->makeAdmin();
        $business = $this->createBusinessWithOwner();
        $member = $this->attachMember($business, 'role-change@example.com', 'staff', 'active');

        $this->actingAs($admin)
            ->put(route('super-admin.businesses.members.update', [$business, $member]), [
                'role' => 'manager',
                'status' => 'active',
                'metadata' => '{"note":"role"}',
            ])
            ->assertRedirect(route('super-admin.businesses.members.index', $business));

        $this->assertDatabaseHas('audit_logs', [
            'business_id' => $business->id,
            'action' => 'member.role_changed',
            'user_id' => $admin->id,
            'subject_id' => $member->id,
        ]);
    }

    public function test_status_update_creates_audit(): void
    {
        $admin = $this->makeAdmin();
        $business = $this->createBusinessWithOwner();
        $member = $this->attachMember($business, 'status-change@example.com', 'staff', 'active');

        $this->actingAs($admin)
            ->patch(route('super-admin.businesses.members.status', [$business, $member]), [
                'status' => 'suspended',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('audit_logs', [
            'business_id' => $business->id,
            'action' => 'member.status_changed',
            'user_id' => $admin->id,
            'subject_id' => $member->id,
        ]);
    }

    public function test_delete_member_creates_audit(): void
    {
        $admin = $this->makeAdmin();
        $business = $this->createBusinessWithOwner();
        $member = $this->attachMember($business, 'delete-me@example.com', 'staff', 'active');

        $this->actingAs($admin)
            ->delete(route('super-admin.businesses.members.destroy', [$business, $member]))
            ->assertRedirect(route('super-admin.businesses.members.index', $business));

        $this->assertDatabaseHas('audit_logs', [
            'business_id' => $business->id,
            'action' => 'member.removed',
            'user_id' => $admin->id,
            'subject_id' => $member->id,
        ]);
    }

    public function test_workspace_activity_loads(): void
    {
        $admin = $this->makeAdmin();
        $business = $this->createBusinessThroughWizard($admin);

        $this->actingAs($admin)
            ->get(route('super-admin.businesses.workspace', $business))
            ->assertOk()
            ->assertSee('Recent Activity')
            ->assertSee('Business created through wizard');
    }

    public function test_business_activity_filtered(): void
    {
        $admin = $this->makeAdmin();
        $business = $this->createBusinessWithOwner();

        $this->actingAs($admin)->patch(route('super-admin.businesses.suspend', $business));
        $this->actingAs($admin)->patch(route('super-admin.businesses.activate', $business));

        $this->actingAs($admin)
            ->get(route('super-admin.businesses.show', $business) . '?action=business.suspended')
            ->assertOk()
            ->assertSee('Super Admin suspended')
            ->assertDontSee('Super Admin activated');
    }

    private function makeAdmin(): User
    {
        $admin = User::factory()->create(['name' => 'Super Admin']);
        $admin->forceFill(['role' => 'admin', 'is_super_admin' => true])->save();

        return $admin;
    }

    private function createBusinessThroughWizard(User $admin): Business
    {
        $payload = $this->wizardPayload([
            'business' => [
                'slug' => 'audit-new-business',
                'name' => 'Audit New Business',
            ],
            'owner' => [
                'full_name' => 'Wizard Owner',
                'email' => 'wizard-owner@example.com',
                'password' => 'Password123!',
                'password_confirmation' => 'Password123!',
            ],
        ]);

        $this->actingAs($admin)->post(route('super-admin.businesses.store'), $payload);

        return Business::query()->where('slug', 'audit-new-business')->firstOrFail();
    }

    private function createBusinessWithOwner(): Business
    {
        $business = Business::factory()->create([
            'name' => 'Audit Business',
            'slug' => 'audit-business',
            'status' => 'active',
        ]);

        $owner = User::factory()->create([
            'name' => 'Audit Owner',
            'email' => 'audit-owner@example.com',
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

    private function businessPayload(array $overrides = []): array
    {
        $payload = [
            'name' => 'Audit Business Revised',
            'slug' => 'audit-business-revised',
            'legal_name' => 'Audit Business S.A.',
            'business_type' => 'barbershop',
            'status' => 'active',
            'identity' => [
                'app_name' => 'Audit Business',
                'display_name' => 'AUDIT BUSINESS',
                'short_name' => 'Audit',
                'tagline' => 'Audit trail',
                'subtitle' => 'Changed config',
                'location_short' => 'San Jose',
                'location_full' => 'San Jose, Costa Rica',
            ],
            'contact' => [
                'phone' => '+506 8888-9999',
                'whatsapp' => '+506 8888-9999',
                'email' => 'audit@example.com',
                'instagram' => '@audit',
                'website' => 'https://audit.test',
                'address' => 'San Jose, Costa Rica',
            ],
            'branding' => [
                'primary_gold' => '#D4A84F',
                'primary_gold_light' => '#E8C36A',
                'primary_gold_dark' => '#9B6F24',
            ],
            'policies' => [
                'cancellation_window_hours' => 6,
                'cancellation_policy_text' => 'Audit policy.',
            ],
            'features' => [
                'show_staff' => true,
                'reservation_staff_selection' => true,
                'admin_staff_management' => true,
                'show_gallery' => true,
                'show_reviews' => true,
                'show_business_profile' => true,
                'show_admin_dashboard' => true,
            ],
        ];

        return array_replace_recursive($payload, $overrides);
    }

    private function wizardPayload(array $overrides = []): array
    {
        $payload = [
            'business' => [
                'name' => 'Audit New Business',
                'slug' => 'audit-new-business',
                'legal_name' => 'Audit New Business S.A.',
                'business_type' => 'barbershop',
                'status' => 'active',
            ],
            'brand' => [
                'app_name' => 'Audit New Business',
                'display_name' => 'AUDIT NEW BUSINESS',
                'short_name' => 'Audit',
                'tagline' => 'Audit trail',
                'subtitle' => 'Tracking',
                'primary_color' => '#D4A84F',
                'secondary_color' => '#E8C36A',
                'background_color' => '#07111f',
            ],
            'contact' => [
                'phone' => '+506 8888-9999',
                'whatsapp' => '+506 8888-9999',
                'email' => 'audit-new@example.com',
                'instagram' => '@auditnew',
                'facebook' => 'Audit New Business',
                'website' => 'https://audit-new.test',
                'address' => 'San Jose, Costa Rica',
                'country' => 'Costa Rica',
                'city' => 'San Jose',
            ],
            'hours' => [
                'cancellation_window_hours' => 6,
                'cancellation_policy_text' => 'Audit policy.',
                'schedule' => [
                    'monday' => ['is_open' => '1', 'opens_at' => '09:00', 'closes_at' => '18:00'],
                    'tuesday' => ['is_open' => '1', 'opens_at' => '09:00', 'closes_at' => '18:00'],
                    'wednesday' => ['is_open' => '1', 'opens_at' => '09:00', 'closes_at' => '18:00'],
                    'thursday' => ['is_open' => '1', 'opens_at' => '09:00', 'closes_at' => '18:00'],
                    'friday' => ['is_open' => '1', 'opens_at' => '09:00', 'closes_at' => '18:00'],
                    'saturday' => ['is_open' => '1', 'opens_at' => '09:00', 'closes_at' => '18:00'],
                    'sunday' => ['is_open' => '0', 'opens_at' => '09:00', 'closes_at' => '18:00'],
                ],
            ],
            'owner' => [
                'full_name' => 'Wizard Owner',
                'email' => 'wizard-owner@example.com',
                'password' => 'Password123!',
                'password_confirmation' => 'Password123!',
            ],
            'features' => [
                'show_staff' => '1',
                'reservation_staff_selection' => '1',
                'admin_staff_management' => '1',
                'show_gallery' => '1',
                'show_reviews' => '1',
                'show_business_profile' => '1',
                'show_admin_dashboard' => '1',
            ],
        ];

        return array_replace_recursive($payload, $overrides);
    }
}
