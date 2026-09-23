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

class ReservationStaffSelectionTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsUser(): User
    {
        $user = User::factory()->create(['role' => 'user']);
        Sanctum::actingAs($user);

        return $user;
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

    private function createLinkedStaff(Business $business, Court $court, string $name = 'Ana Staff'): Staff
    {
        $role = StaffRole::create([
            'name' => 'Consultant',
            'slug' => 'consultant-' . uniqid(),
            'description' => 'Consultant',
        ]);

        $staff = Staff::create([
            'business_id' => $business->id,
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
        $business = $this->createBusiness();
        $court = Court::factory()->create(['business_id' => $business->id]);

        $response = $this->postJson('/api/v1/bookings', [
            'court_id' => $court->id,
            'date' => Carbon::now()->addDay()->toIso8601String(),
            'time_slot' => '6:00 PM to 7:00 PM',
        ], $this->headers($business));

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
        $business = $this->createBusiness();
        $court = Court::factory()->create(['business_id' => $business->id]);
        $staff = $this->createLinkedStaff($business, $court);

        $response = $this->postJson('/api/v1/bookings', [
            'court_id' => $court->id,
            'staff_id' => $staff->id,
            'date' => Carbon::now()->addDay()->toIso8601String(),
            'time_slot' => '6:00 PM to 7:00 PM',
        ], $this->headers($business));

        $response->assertCreated();
        $response->assertJsonPath('staff.id', $staff->id);

        $this->assertDatabaseHas('bookings', [
            'user_id' => $user->id,
            'court_id' => $court->id,
            'staff_id' => $staff->id,
        ]);
    }

    public function test_selected_carlos_is_persisted_and_returned_in_reservation_detail(): void
    {
        $this->actingAsUser();
        $business = $this->createBusiness();
        $court = Court::factory()->create(['business_id' => $business->id]);
        $carlos = $this->createLinkedStaff($business, $court, 'Carlos Ramírez');

        $created = $this->postJson('/api/v1/reservations', [
            'resource_id' => $court->id,
            'staff_id' => (string) $carlos->id,
            'date' => Carbon::now()->addDays(2)->toIso8601String(),
            'time_slot' => '10:00 AM to 11:00 AM',
        ], $this->headers($business))->assertCreated()
            ->assertJsonPath('staff_id', $carlos->id)
            ->assertJsonPath('staff.id', $carlos->id)
            ->assertJsonPath('staff.name', 'Carlos Ramírez')
            ->json();

        $this->assertDatabaseHas('bookings', [
            'id' => $created['id'],
            'staff_id' => $carlos->id,
        ]);

        $this->getJson('/api/v1/reservations/' . $created['id'], $this->headers($business))
            ->assertOk()
            ->assertJsonPath('staff_id', $carlos->id)
            ->assertJsonPath('staff.name', 'Carlos Ramírez');
    }

    public function test_reservation_with_invalid_staff_is_rejected(): void
    {
        $this->actingAsUser();
        $business = $this->createBusiness();
        $court = Court::factory()->create(['business_id' => $business->id]);

        $response = $this->postJson('/api/v1/bookings', [
            'court_id' => $court->id,
            'staff_id' => 999999,
            'date' => Carbon::now()->addDay()->toIso8601String(),
            'time_slot' => '6:00 PM to 7:00 PM',
        ], $this->headers($business));

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('staff_id');
    }

    public function test_reservation_with_unlinked_staff_is_rejected(): void
    {
        $this->actingAsUser();
        $business = $this->createBusiness();
        $court = Court::factory()->create(['business_id' => $business->id]);
        $otherCourt = Court::factory()->create(['business_id' => $business->id]);
        $staff = $this->createLinkedStaff($business, $otherCourt, 'Linked Elsewhere');

        $response = $this->postJson('/api/v1/bookings', [
            'court_id' => $court->id,
            'staff_id' => $staff->id,
            'date' => Carbon::now()->addDay()->toIso8601String(),
            'time_slot' => '6:00 PM to 7:00 PM',
        ], $this->headers($business));

        $response->assertStatus(422);
        $response->assertJsonPath('message', 'Staff is not linked to this court');

        $this->assertDatabaseMissing('bookings', [
            'court_id' => $court->id,
            'staff_id' => $staff->id,
        ]);
    }
}
