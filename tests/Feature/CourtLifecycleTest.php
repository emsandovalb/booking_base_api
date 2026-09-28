<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\Court;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CourtLifecycleTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsAdmin(): User
    {
        $user = User::factory()->create(['role' => 'admin']);
        Sanctum::actingAs($user);

        return $user;
    }

    private function actingAsUser(): User
    {
        $user = User::factory()->create(['role' => 'user']);
        Sanctum::actingAs($user);

        return $user;
    }

    private function createBusiness(string $slug = 'barberia-tres-amigos', string $name = 'Barberia Tres Amigos'): Business
    {
        return Business::create([
            'name' => $name,
            'slug' => $slug,
            'business_type' => 'barbershop',
            'status' => 'active',
        ]);
    }

    private function assignMembership(User $user, Business $business, string $role = 'owner'): void
    {
        $business->users()->syncWithoutDetaching([
            $user->id => [
                'role' => $role,
                'status' => 'active',
                'accepted_at' => now(),
            ],
        ]);
    }

    private function headers(Business $business): array
    {
        return ['X-Business-Slug' => $business->slug];
    }

    public function test_delete_deactivates_court_and_keeps_it_for_admin()
    {
        $admin = $this->actingAsAdmin();
        $business = $this->createBusiness();
        $this->assignMembership($admin, $business, 'owner');

        $court = Court::factory()->create([
            'business_id' => $business->id,
            'owner_id' => $admin->id,
            'status' => 'active',
        ]);

        $response = $this->deleteJson('/api/v1/courts/' . $court->id, [], $this->headers($business));
        $response->assertOk();
        $response->assertJsonPath('court.status', 'inactive');

        $this->assertDatabaseHas('courts', [
            'id' => $court->id,
            'status' => 'inactive',
        ]);

        // Inactive courts are not visible in public listing
        Sanctum::actingAs($this->actingAsUser());
        $list = $this->getJson('/api/v1/courts', $this->headers($business));
        $list->assertOk();
        $this->assertEmpty(
            collect($list->json('data'))->where('id', $court->id)
        );

        // But owner admin still sees it in /my/grounds
        Sanctum::actingAs($admin);
        $mine = $this->getJson('/api/v1/my/grounds', $this->headers($business));
        $mine->assertOk();
        $this->assertNotEmpty(
            collect($mine->json('data'))->where('id', $court->id)
        );
    }

    public function test_inactive_court_cannot_be_booked()
    {
        $user = $this->actingAsUser();
        $business = $this->createBusiness();

        $court = Court::factory()->create([
            'business_id' => $business->id,
            'status' => 'inactive',
        ]);

        $payload = [
            'court_id' => $court->id,
            'date' => Carbon::now()->addDay()->startOfDay()->toIso8601String(),
            'time_slot' => '9:00 AM to 10:00 AM',
        ];

        $response = $this->postJson('/api/v1/bookings', $payload, $this->headers($business));
        $response->assertStatus(422);
        $response->assertJsonPath('message', 'Selected service does not belong to this business');
    }

    public function test_rebook_fails_when_court_is_inactive()
    {
        $user = $this->actingAsUser();
        $business = $this->createBusiness();

        $court = Court::factory()->create([
            'business_id' => $business->id,
            'status' => 'active',
        ]);

        $bookingResponse = $this->postJson('/api/v1/bookings', [
            'court_id' => $court->id,
            'date' => Carbon::now()->addDay()->startOfDay()->toIso8601String(),
            'time_slot' => '9:00 AM',
        ], $this->headers($business));
        $bookingResponse->assertCreated();
        $bookingId = $bookingResponse->json('id');

        // Deactivate court via DELETE
        $admin = User::factory()->create(['role' => 'admin']);
        $this->assignMembership($admin, $business, 'owner');
        Sanctum::actingAs($admin);
        $this->deleteJson('/api/v1/courts/' . $court->id, [], $this->headers($business))->assertOk();

        // Back as booking owner
        Sanctum::actingAs($user);
        $rebookPayload = [
            'date' => Carbon::now()->addDays(2)->startOfDay()->toIso8601String(),
            'time_slot' => '10:00 AM',
        ];

        $rebook = $this->postJson('/api/v1/bookings/' . $bookingId . '/rebook', $rebookPayload, $this->headers($business));
        $rebook->assertStatus(422);
        $rebook->assertJsonPath('message', 'Selected service does not belong to this business');
    }
}

