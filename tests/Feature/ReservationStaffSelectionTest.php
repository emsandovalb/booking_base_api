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

class ReservationStaffSelectionTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsUser(): User
    {
        $user = User::factory()->create(['role' => 'user']);
        Sanctum::actingAs($user);

        return $user;
    }

    private function createLinkedStaff(Court $court, string $name = 'Ana Staff'): Staff
    {
        $role = StaffRole::create([
            'name' => 'Consultant',
            'slug' => 'consultant-' . uniqid(),
            'description' => 'Consultant',
        ]);

        $staff = Staff::create([
            'staff_role_id' => $role->id,
            'name' => $name,
            'email' => strtolower(str_replace(' ', '.', $name)) . '@example.com',
            'is_active' => true,
        ]);

        StaffService::create([
            'staff_id' => $staff->id,
            'court_id' => $court->id,
            'is_primary' => true,
        ]);

        return $staff;
    }

    public function test_reservation_without_staff_still_creates_successfully(): void
    {
        $user = $this->actingAsUser();
        $court = Court::factory()->create();

        $response = $this->postJson('/api/v1/bookings', [
            'court_id' => $court->id,
            'date' => Carbon::now()->addDay()->toIso8601String(),
            'time_slot' => '6:00 PM to 7:00 PM',
        ]);

        $response->assertCreated();
        $response->assertJsonPath('staff', null);

        $this->assertDatabaseHas('bookings', [
            'user_id' => $user->id,
            'court_id' => $court->id,
            'staff_id' => null,
        ]);
    }

    public function test_reservation_with_valid_staff_creates_successfully(): void
    {
        $user = $this->actingAsUser();
        $court = Court::factory()->create();
        $staff = $this->createLinkedStaff($court);

        $response = $this->postJson('/api/v1/bookings', [
            'court_id' => $court->id,
            'staff_id' => $staff->id,
            'date' => Carbon::now()->addDay()->toIso8601String(),
            'time_slot' => '6:00 PM to 7:00 PM',
        ]);

        $response->assertCreated();
        $response->assertJsonPath('staff.id', $staff->id);

        $this->assertDatabaseHas('bookings', [
            'user_id' => $user->id,
            'court_id' => $court->id,
            'staff_id' => $staff->id,
        ]);
    }

    public function test_reservation_with_invalid_staff_is_rejected(): void
    {
        $this->actingAsUser();
        $court = Court::factory()->create();

        $response = $this->postJson('/api/v1/bookings', [
            'court_id' => $court->id,
            'staff_id' => 999999,
            'date' => Carbon::now()->addDay()->toIso8601String(),
            'time_slot' => '6:00 PM to 7:00 PM',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('staff_id');
    }

    public function test_reservation_with_unlinked_staff_is_rejected(): void
    {
        $this->actingAsUser();
        $court = Court::factory()->create();
        $otherCourt = Court::factory()->create();
        $staff = $this->createLinkedStaff($otherCourt, 'Linked Elsewhere');

        $response = $this->postJson('/api/v1/bookings', [
            'court_id' => $court->id,
            'staff_id' => $staff->id,
            'date' => Carbon::now()->addDay()->toIso8601String(),
            'time_slot' => '6:00 PM to 7:00 PM',
        ]);

        $response->assertStatus(422);
        $response->assertJsonPath('message', 'Staff is not linked to this court');

        $this->assertDatabaseMissing('bookings', [
            'court_id' => $court->id,
            'staff_id' => $staff->id,
        ]);
    }
}
