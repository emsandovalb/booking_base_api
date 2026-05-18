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
        $role = StaffRole::create([
            'name' => 'Consultant',
            'slug' => 'consultant',
            'description' => 'Consulting',
        ]);
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
        $role = StaffRole::create([
            'name' => 'Barber',
            'slug' => 'barber',
            'description' => 'Barber',
        ]);
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
        $role = StaffRole::create([
            'name' => 'Trainer',
            'slug' => 'trainer',
            'description' => 'Trainer',
        ]);
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
}
