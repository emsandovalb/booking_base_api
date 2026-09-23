<?php

namespace Tests\Feature;

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

class BusinessClosureTest extends TestCase
{
    use RefreshDatabase;

    private function createBusiness(): Business
    {
        return Business::create([
            'name' => 'Barberia Tres Amigos',
            'slug' => 'barberia-tres-amigos-' . uniqid(),
            'business_type' => 'barbershop',
            'status' => 'active',
        ]);
    }

    private function createOwner(Business $business): User
    {
        $owner = User::factory()->create(['role' => 'user']);
        $business->users()->attach($owner->id, ['role' => 'owner', 'status' => 'active', 'accepted_at' => now()]);

        return $owner;
    }

    private function createStaff(Business $business, Court $court): Staff
    {
        $role = StaffRole::create(['name' => 'Consultant', 'slug' => 'consultant-' . uniqid(), 'description' => 'Consultant']);
        $staff = Staff::create([
            'business_id' => $business->id,
            'staff_role_id' => $role->id,
            'name' => 'Carlos Ramírez',
            'is_active' => true,
        ]);
        StaffService::create(['staff_id' => $staff->id, 'court_id' => $court->id, 'is_primary' => true]);

        return $staff;
    }

    private function headers(Business $business): array
    {
        return ['X-Business-Slug' => $business->slug];
    }

    public function test_owner_can_create_a_business_wide_closure(): void
    {
        $business = $this->createBusiness();
        $owner = $this->createOwner($business);
        Sanctum::actingAs($owner);

        $date = Carbon::now()->addDays(5)->toDateString();

        $this->postJson('/api/v1/closures', ['date' => $date, 'reason' => 'Feriado nacional'], $this->headers($business))
            ->assertCreated()
            ->assertJsonPath('date', $date)
            ->assertJsonPath('staff_id', null)
            ->assertJsonPath('reason', 'Feriado nacional');

        $this->assertDatabaseHas('business_closures', [
            'business_id' => $business->id,
            'staff_id' => null,
            'date' => $date,
        ]);
    }

    public function test_a_business_wide_closure_blocks_booking_for_every_staff_member(): void
    {
        $business = $this->createBusiness();
        $owner = $this->createOwner($business);
        $court = Court::factory()->create(['business_id' => $business->id]);
        $staff = $this->createStaff($business, $court);

        $date = Carbon::now()->addDays(5);
        Sanctum::actingAs($owner);
        $this->postJson('/api/v1/closures', ['date' => $date->toDateString()], $this->headers($business))
            ->assertCreated();

        $response = $this->postJson('/api/v1/bookings', [
            'court_id' => $court->id,
            'staff_id' => $staff->id,
            'date' => $date->copy()->startOfDay()->toIso8601String(),
            'time_slot' => '10:00 AM to 10:45 AM',
        ], $this->headers($business));

        $response->assertStatus(422);
        $response->assertJsonPath('message', 'Time slot is no longer available');
    }

    public function test_a_staff_specific_closure_only_blocks_that_staff_member(): void
    {
        $business = $this->createBusiness();
        $owner = $this->createOwner($business);
        $court = Court::factory()->create(['business_id' => $business->id]);
        $staff = $this->createStaff($business, $court);
        $otherStaff = $this->createStaff($business, $court);

        $date = Carbon::now()->addDays(5);
        Sanctum::actingAs($owner);
        $this->postJson('/api/v1/closures', [
            'date' => $date->toDateString(),
            'staff_id' => $staff->id,
            'reason' => 'Día libre',
        ], $this->headers($business))->assertCreated();

        // The staff member with the closure is blocked...
        $this->postJson('/api/v1/bookings', [
            'court_id' => $court->id,
            'staff_id' => $staff->id,
            'date' => $date->copy()->startOfDay()->toIso8601String(),
            'time_slot' => '10:00 AM to 10:45 AM',
        ], $this->headers($business))->assertStatus(422);

        // ...but a different staff member on the same day is unaffected.
        $this->postJson('/api/v1/bookings', [
            'court_id' => $court->id,
            'staff_id' => $otherStaff->id,
            'date' => $date->copy()->startOfDay()->toIso8601String(),
            'time_slot' => '10:00 AM to 10:45 AM',
        ], $this->headers($business))->assertCreated();
    }

    public function test_non_admin_cannot_create_a_closure(): void
    {
        $business = $this->createBusiness();
        $client = User::factory()->create(['role' => 'user']);
        $business->users()->attach($client->id, ['role' => 'client', 'status' => 'active', 'accepted_at' => now()]);
        Sanctum::actingAs($client);

        $this->postJson('/api/v1/closures', ['date' => Carbon::now()->addDay()->toDateString()], $this->headers($business))
            ->assertForbidden();
    }

    public function test_owner_can_list_and_delete_a_closure(): void
    {
        $business = $this->createBusiness();
        $owner = $this->createOwner($business);
        Sanctum::actingAs($owner);

        $created = $this->postJson('/api/v1/closures', [
            'date' => Carbon::now()->addDays(3)->toDateString(),
        ], $this->headers($business))->json();

        $this->getJson('/api/v1/closures', $this->headers($business))
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->deleteJson('/api/v1/closures/' . $created['id'], [], $this->headers($business))
            ->assertOk();

        $this->assertDatabaseMissing('business_closures', ['id' => $created['id']]);
    }

    public function test_a_closure_from_another_business_cannot_be_deleted(): void
    {
        $businessA = $this->createBusiness();
        $ownerA = $this->createOwner($businessA);
        $businessB = $this->createBusiness();
        $ownerB = $this->createOwner($businessB);

        Sanctum::actingAs($ownerA);
        $closure = $this->postJson('/api/v1/closures', [
            'date' => Carbon::now()->addDays(3)->toDateString(),
        ], $this->headers($businessA))->json();

        Sanctum::actingAs($ownerB);
        $this->deleteJson('/api/v1/closures/' . $closure['id'], [], $this->headers($businessB))
            ->assertNotFound();

        $this->assertDatabaseHas('business_closures', ['id' => $closure['id']]);
    }
}
