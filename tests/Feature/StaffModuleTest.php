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
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class StaffModuleTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_index_returns_staff_list(): void
    {
        $business = $this->createBusiness('barberia-tres-amigos', 'Barberia Tres Amigos');
        $role = $this->createRole('consultant', 'Consultant');
        $court = Court::factory()->create(['business_id' => $business->id]);

        $staffA = Staff::create([
            'business_id' => $business->id,
            'staff_role_id' => $role->id,
            'name' => 'Ana Trainer',
            'email' => 'ana@example.com',
            'is_active' => true,
        ]);
        $staffB = Staff::create([
            'business_id' => $business->id,
            'staff_role_id' => $role->id,
            'name' => 'Maria Lopez',
            'email' => 'maria@example.com',
            'is_active' => true,
        ]);

        StaffService::create([
            'staff_id' => $staffA->id,
            'court_id' => $court->id,
            'is_primary' => true,
        ]);

        $response = $this->getJson('/api/v1/staff', $this->headers($business));

        $response->assertOk();
        $response->assertJsonCount(2, 'data');
        $response->assertJsonFragment(['name' => 'Ana Trainer']);
        $response->assertJsonFragment(['name' => 'Maria Lopez']);
    }

    public function test_staff_show_returns_record_with_relations(): void
    {
        $business = $this->createBusiness('barberia-tres-amigos', 'Barberia Tres Amigos');
        $role = $this->createRole('barber', 'Barber');
        $court = Court::factory()->create(['business_id' => $business->id]);
        $staff = Staff::create([
            'business_id' => $business->id,
            'staff_role_id' => $role->id,
            'name' => 'John Barber',
            'email' => 'john@example.com',
            'phone' => '+1-555-0102',
            'is_active' => true,
        ]);
        StaffService::create([
            'staff_id' => $staff->id,
            'court_id' => $court->id,
            'is_primary' => true,
        ]);

        $response = $this->getJson('/api/v1/staff/' . $staff->id, $this->headers($business));

        $response->assertOk();
        $response->assertJsonPath('id', $staff->id);
        $response->assertJsonPath('role.slug', 'barber');
        $response->assertJsonCount(1, 'services');
        $response->assertJsonPath('services.0.resource.id', $court->id);
    }

    public function test_resource_staff_lookup_returns_staff_for_resource(): void
    {
        $business = $this->createBusiness('barberia-tres-amigos', 'Barberia Tres Amigos');
        $role = $this->createRole('trainer', 'Trainer');
        $resource = Court::factory()->create(['business_id' => $business->id]);
        $otherResource = Court::factory()->create(['business_id' => $business->id]);
        $assigned = Staff::create([
            'business_id' => $business->id,
            'staff_role_id' => $role->id,
            'name' => 'Ana Trainer',
            'email' => 'ana@example.com',
            'is_active' => true,
        ]);
        $other = Staff::create([
            'business_id' => $business->id,
            'staff_role_id' => $role->id,
            'name' => 'Sophia Consultant',
            'email' => 'sophia@example.com',
            'is_active' => true,
        ]);
        StaffService::create([
            'staff_id' => $assigned->id,
            'court_id' => $resource->id,
            'is_primary' => true,
        ]);
        StaffService::create([
            'staff_id' => $other->id,
            'court_id' => $otherResource->id,
            'is_primary' => true,
        ]);

        $response = $this->getJson('/api/v1/resources/' . $resource->id . '/staff', $this->headers($business));

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.name', 'Ana Trainer');
        $response->assertJsonCount(1, 'data.0.services');
        $response->assertJsonPath('data.0.services.0.resource.id', $resource->id);
    }

    public function test_admin_can_create_staff(): void
    {
        $admin = $this->actingAsAdmin();
        $business = $this->createBusiness('barberia-tres-amigos', 'Barberia Tres Amigos');
        $this->attachMembership($admin, $business, 'owner');
        $role = $this->createRole('barber', 'Barber');

        $response = $this->postJson('/api/v1/staff', [
            'name' => 'New Barber',
            'email' => 'new@example.com',
            'phone' => '+1-555-1111',
            'bio' => 'Experienced barber',
            'staff_role_id' => $role->id,
            'is_active' => true,
        ], $this->headers($business));

        $response->assertCreated();
        $response->assertJsonPath('name', 'New Barber');
        $this->assertDatabaseHas('staff', [
            'name' => 'New Barber',
            'email' => 'new@example.com',
            'staff_role_id' => $role->id,
            'is_active' => true,
            'business_id' => $business->id,
        ]);
    }

    public function test_admin_can_update_staff(): void
    {
        $admin = $this->actingAsAdmin();
        $business = $this->createBusiness('barberia-tres-amigos', 'Barberia Tres Amigos');
        $this->attachMembership($admin, $business, 'owner');
        $role = $this->createRole('barber', 'Barber');
        $alternateRole = $this->createRole('senior-barber', 'Senior Barber');
        $staff = Staff::create([
            'business_id' => $business->id,
            'staff_role_id' => $role->id,
            'name' => 'Old Barber',
            'email' => 'old@example.com',
            'is_active' => true,
        ]);

        $response = $this->patchJson('/api/v1/staff/' . $staff->id, [
            'name' => 'Updated Barber',
            'email' => 'updated@example.com',
            'staff_role_id' => $alternateRole->id,
            'is_active' => false,
        ], $this->headers($business));

        $response->assertOk();
        $response->assertJsonPath('name', 'Updated Barber');
        $response->assertJsonPath('role.slug', 'senior-barber');
        $this->assertDatabaseHas('staff', [
            'id' => $staff->id,
            'name' => 'Updated Barber',
            'email' => 'updated@example.com',
            'staff_role_id' => $alternateRole->id,
            'is_active' => false,
        ]);
    }

    public function test_admin_can_deactivate_staff(): void
    {
        $admin = $this->actingAsAdmin();
        $business = $this->createBusiness('barberia-tres-amigos', 'Barberia Tres Amigos');
        $this->attachMembership($admin, $business, 'owner');
        $role = $this->createRole('barber', 'Barber');
        $staff = Staff::create([
            'business_id' => $business->id,
            'staff_role_id' => $role->id,
            'name' => 'Active Barber',
            'email' => 'active@example.com',
            'is_active' => true,
        ]);

        $response = $this->patchJson('/api/v1/staff/' . $staff->id . '/deactivate', [], $this->headers($business));

        $response->assertOk();
        $response->assertJsonPath('is_active', false);
        $this->assertDatabaseHas('staff', [
            'id' => $staff->id,
            'is_active' => false,
        ]);
    }

    public function test_admin_can_assign_staff_to_resource(): void
    {
        $admin = $this->actingAsAdmin();
        $business = $this->createBusiness('barberia-tres-amigos', 'Barberia Tres Amigos');
        $this->attachMembership($admin, $business, 'owner');
        $role = $this->createRole('barber', 'Barber');
        $staff = Staff::create([
            'business_id' => $business->id,
            'staff_role_id' => $role->id,
            'name' => 'Assign Barber',
            'email' => 'assign@example.com',
            'is_active' => true,
        ]);
        $resource = Court::factory()->create(['business_id' => $business->id]);

        $response = $this->postJson('/api/v1/staff/' . $staff->id . '/services', [
            'resource_id' => $resource->id,
        ], $this->headers($business));

        $response->assertOk();
        $this->assertDatabaseHas('staff_services', [
            'staff_id' => $staff->id,
            'court_id' => $resource->id,
        ]);
    }

    public function test_admin_can_remove_staff_from_resource(): void
    {
        $admin = $this->actingAsAdmin();
        $business = $this->createBusiness('barberia-tres-amigos', 'Barberia Tres Amigos');
        $this->attachMembership($admin, $business, 'owner');
        $role = $this->createRole('barber', 'Barber');
        $staff = Staff::create([
            'business_id' => $business->id,
            'staff_role_id' => $role->id,
            'name' => 'Remove Barber',
            'email' => 'remove@example.com',
            'is_active' => true,
        ]);
        $resource = Court::factory()->create(['business_id' => $business->id]);
        StaffService::create([
            'staff_id' => $staff->id,
            'court_id' => $resource->id,
            'is_primary' => true,
        ]);

        $response = $this->deleteJson('/api/v1/staff/' . $staff->id . '/services/' . $resource->id, [], $this->headers($business));

        $response->assertOk();
        $this->assertDatabaseMissing('staff_services', [
            'staff_id' => $staff->id,
            'court_id' => $resource->id,
        ]);
    }

    public function test_non_admin_cannot_write_staff(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $business = $this->createBusiness('barberia-tres-amigos', 'Barberia Tres Amigos');
        $role = $this->createRole('barber', 'Barber');
        $staff = Staff::create([
            'business_id' => $business->id,
            'staff_role_id' => $role->id,
            'name' => 'Read Only Barber',
            'email' => 'readonly@example.com',
            'is_active' => true,
        ]);
        $resource = Court::factory()->create(['business_id' => $business->id]);

        $createResponse = $this->postJson('/api/v1/staff', [
            'name' => 'Blocked Barber',
            'staff_role_id' => $role->id,
        ], $this->headers($business));
        $createResponse->assertStatus(403);

        $updateResponse = $this->patchJson('/api/v1/staff/' . $staff->id, [
            'name' => 'Blocked Update',
        ], $this->headers($business));
        $updateResponse->assertStatus(403);

        $deactivateResponse = $this->patchJson('/api/v1/staff/' . $staff->id . '/deactivate', [], $this->headers($business));
        $deactivateResponse->assertStatus(403);

        $assignResponse = $this->postJson('/api/v1/staff/' . $staff->id . '/services', [
            'resource_id' => $resource->id,
        ], $this->headers($business));
        $assignResponse->assertStatus(403);

        $removeResponse = $this->deleteJson('/api/v1/staff/' . $staff->id . '/services/' . $resource->id, [], $this->headers($business));
        $removeResponse->assertStatus(403);
    }

    public function test_business_owner_can_create_and_manage_staff_with_business_slug(): void
    {
        $owner = User::factory()->create();
        Sanctum::actingAs($owner);
        $business = $this->createBusiness('barberia-tres-amigos', 'Barberia Tres Amigos');
        $this->attachMembership($owner, $business, 'owner');
        $role = $this->createRole('barber', 'Barber');

        $createResponse = $this->postJson('/api/v1/staff', [
            'name' => 'Owner Barber',
            'email' => 'owner.barber@example.com',
            'staff_role_id' => $role->id,
            'is_active' => true,
        ], [
            'X-Business-Slug' => $business->slug,
        ]);

        $createResponse->assertCreated();
        $createResponse->assertJsonPath('name', 'Owner Barber');

        $staffId = $createResponse->json('id');

        $updateResponse = $this->patchJson('/api/v1/staff/' . $staffId, [
            'name' => 'Owner Barber Updated',
            'staff_role_id' => $role->id,
        ], [
            'X-Business-Slug' => $business->slug,
        ]);

        $updateResponse->assertOk();
        $updateResponse->assertJsonPath('name', 'Owner Barber Updated');

        $deactivateResponse = $this->patchJson('/api/v1/staff/' . $staffId . '/deactivate', [], [
            'X-Business-Slug' => $business->slug,
        ]);

        $deactivateResponse->assertOk();
        $deactivateResponse->assertJsonPath('is_active', false);
    }

    public function test_existing_resource_and_reservation_routes_still_work_with_business_slug(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $business = $this->createBusiness('barberia-tres-amigos', 'Barberia Tres Amigos');

        $court = Court::factory()->create(['business_id' => $business->id]);
        Booking::create([
            'user_id' => $user->id,
            'court_id' => $court->id,
            'business_id' => $business->id,
            'date' => Carbon::now()->addDay(),
            'time_slot' => '6:00 PM to 7:00 PM',
            'duration_hours' => 1,
            'status' => 'pending',
            'booking_code' => 'ABC123',
            'total_price' => 10,
        ]);

        $resources = $this->getJson('/api/v1/resources', $this->headers($business));
        $resources->assertOk();
        $resources->assertJsonFragment(['id' => $court->id]);

        $reservations = $this->getJson('/api/v1/reservations', $this->headers($business));
        $reservations->assertOk();
        $reservations->assertJsonCount(1, 'data');
    }

    private function actingAsAdmin(): User
    {
        $admin = User::factory()->create();
        $admin->forceFill(['role' => 'admin'])->save();
        Sanctum::actingAs($admin);

        return $admin;
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

    private function headers(Business $business): array
    {
        return ['X-Business-Slug' => $business->slug];
    }

    private function attachMembership(User $user, Business $business, string $role): void
    {
        $business->users()->syncWithoutDetaching([
            $user->id => [
                'role' => $role,
                'status' => 'active',
                'accepted_at' => now(),
            ],
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
}
