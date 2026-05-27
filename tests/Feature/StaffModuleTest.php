<?php

namespace Tests\Feature;

use App\Models\Booking;
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
        $role = $this->createRole('consultant', 'Consultant');
        $court = Court::factory()->create();

        $staffA = Staff::create([
            'staff_role_id' => $role->id,
            'name' => 'Ana Trainer',
            'email' => 'ana@example.com',
            'is_active' => true,
        ]);
        $staffB = Staff::create([
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

        $response = $this->getJson('/api/v1/staff');

        $response->assertOk();
        $response->assertJsonCount(2, 'data');
        $response->assertJsonFragment(['name' => 'Ana Trainer']);
        $response->assertJsonFragment(['name' => 'Maria Lopez']);
    }

    public function test_staff_show_returns_record_with_relations(): void
    {
        $role = $this->createRole('barber', 'Barber');
        $court = Court::factory()->create();
        $staff = Staff::create([
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

        $response = $this->getJson('/api/v1/staff/' . $staff->id);

        $response->assertOk();
        $response->assertJsonPath('id', $staff->id);
        $response->assertJsonPath('role.slug', 'barber');
        $response->assertJsonCount(1, 'services');
        $response->assertJsonPath('services.0.resource.id', $court->id);
    }

    public function test_resource_staff_lookup_returns_staff_for_resource(): void
    {
        $role = $this->createRole('trainer', 'Trainer');
        $resource = Court::factory()->create();
        $otherResource = Court::factory()->create();
        $assigned = Staff::create([
            'staff_role_id' => $role->id,
            'name' => 'Ana Trainer',
            'email' => 'ana@example.com',
            'is_active' => true,
        ]);
        $other = Staff::create([
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

        $response = $this->getJson('/api/v1/resources/' . $resource->id . '/staff');

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.name', 'Ana Trainer');
        $response->assertJsonCount(1, 'data.0.services');
        $response->assertJsonPath('data.0.services.0.resource.id', $resource->id);
    }

    public function test_admin_can_create_staff(): void
    {
        $admin = $this->actingAsAdmin();
        $role = $this->createRole('barber', 'Barber');

        $response = $this->postJson('/api/v1/staff', [
            'name' => 'New Barber',
            'email' => 'new@example.com',
            'phone' => '+1-555-1111',
            'bio' => 'Experienced barber',
            'staff_role_id' => $role->id,
            'is_active' => true,
        ]);

        $response->assertCreated();
        $response->assertJsonPath('name', 'New Barber');
        $this->assertDatabaseHas('staff', [
            'name' => 'New Barber',
            'email' => 'new@example.com',
            'staff_role_id' => $role->id,
            'is_active' => true,
        ]);
    }

    public function test_admin_can_update_staff(): void
    {
        $admin = $this->actingAsAdmin();
        $role = $this->createRole('barber', 'Barber');
        $alternateRole = $this->createRole('senior-barber', 'Senior Barber');
        $staff = Staff::create([
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
        ]);

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
        $role = $this->createRole('barber', 'Barber');
        $staff = Staff::create([
            'staff_role_id' => $role->id,
            'name' => 'Active Barber',
            'email' => 'active@example.com',
            'is_active' => true,
        ]);

        $response = $this->patchJson('/api/v1/staff/' . $staff->id . '/deactivate');

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
        $role = $this->createRole('barber', 'Barber');
        $staff = Staff::create([
            'staff_role_id' => $role->id,
            'name' => 'Assign Barber',
            'email' => 'assign@example.com',
            'is_active' => true,
        ]);
        $resource = Court::factory()->create();

        $response = $this->postJson('/api/v1/staff/' . $staff->id . '/services', [
            'resource_id' => $resource->id,
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('staff_services', [
            'staff_id' => $staff->id,
            'court_id' => $resource->id,
        ]);
    }

    public function test_admin_can_remove_staff_from_resource(): void
    {
        $admin = $this->actingAsAdmin();
        $role = $this->createRole('barber', 'Barber');
        $staff = Staff::create([
            'staff_role_id' => $role->id,
            'name' => 'Remove Barber',
            'email' => 'remove@example.com',
            'is_active' => true,
        ]);
        $resource = Court::factory()->create();
        StaffService::create([
            'staff_id' => $staff->id,
            'court_id' => $resource->id,
            'is_primary' => true,
        ]);

        $response = $this->deleteJson('/api/v1/staff/' . $staff->id . '/services/' . $resource->id);

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
        $role = $this->createRole('barber', 'Barber');
        $staff = Staff::create([
            'staff_role_id' => $role->id,
            'name' => 'Read Only Barber',
            'email' => 'readonly@example.com',
            'is_active' => true,
        ]);
        $resource = Court::factory()->create();

        $createResponse = $this->postJson('/api/v1/staff', [
            'name' => 'Blocked Barber',
            'staff_role_id' => $role->id,
        ]);
        $createResponse->assertStatus(403);

        $updateResponse = $this->patchJson('/api/v1/staff/' . $staff->id, [
            'name' => 'Blocked Update',
        ]);
        $updateResponse->assertStatus(403);

        $deactivateResponse = $this->patchJson('/api/v1/staff/' . $staff->id . '/deactivate');
        $deactivateResponse->assertStatus(403);

        $assignResponse = $this->postJson('/api/v1/staff/' . $staff->id . '/services', [
            'resource_id' => $resource->id,
        ]);
        $assignResponse->assertStatus(403);

        $removeResponse = $this->deleteJson('/api/v1/staff/' . $staff->id . '/services/' . $resource->id);
        $removeResponse->assertStatus(403);
    }

    public function test_existing_resource_and_reservation_routes_still_work(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $court = Court::factory()->create();
        Booking::create([
            'user_id' => $user->id,
            'court_id' => $court->id,
            'date' => Carbon::now()->addDay(),
            'time_slot' => '6:00 PM to 7:00 PM',
            'duration_hours' => 1,
            'status' => 'pending',
            'booking_code' => 'ABC123',
            'total_price' => 10,
        ]);

        $resources = $this->getJson('/api/v1/resources');
        $resources->assertOk();
        $resources->assertJsonFragment(['id' => $court->id]);

        $reservations = $this->getJson('/api/v1/reservations');
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

    private function createRole(string $slug, string $name): StaffRole
    {
        return StaffRole::create([
            'name' => $name,
            'slug' => $slug,
            'description' => $name,
        ]);
    }
}
