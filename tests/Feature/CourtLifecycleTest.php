<?php

namespace Tests\Feature;

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

    public function test_delete_deactivates_court_and_keeps_it_for_admin()
    {
        $admin = $this->actingAsAdmin();

        $court = Court::factory()->create([
            'owner_id' => $admin->id,
            'status' => 'active',
        ]);

        $response = $this->deleteJson('/api/v1/courts/' . $court->id);
        $response->assertOk();
        $response->assertJsonPath('court.status', 'inactive');

        $this->assertDatabaseHas('courts', [
            'id' => $court->id,
            'status' => 'inactive',
        ]);

        // Inactive courts are not visible in public listing
        Sanctum::actingAs($this->actingAsUser());
        $list = $this->getJson('/api/v1/courts');
        $list->assertOk();
        $this->assertEmpty(
            collect($list->json('data'))->where('id', $court->id)
        );

        // But owner admin still sees it in /my/grounds
        Sanctum::actingAs($admin);
        $mine = $this->getJson('/api/v1/my/grounds');
        $mine->assertOk();
        $this->assertNotEmpty(
            collect($mine->json('data'))->where('id', $court->id)
        );
    }

    public function test_inactive_court_cannot_be_booked()
    {
        $user = $this->actingAsUser();

        $court = Court::factory()->create([
            'status' => 'inactive',
        ]);

        $payload = [
            'court_id' => $court->id,
            'date' => Carbon::now()->addDay()->toIso8601String(),
            'time_slot' => '6:00 PM to 7:00 PM',
        ];

        $response = $this->postJson('/api/v1/bookings', $payload);
        $response->assertStatus(422);
        $response->assertJsonPath('message', 'Court is inactive and cannot be booked');
    }

    public function test_rebook_fails_when_court_is_inactive()
    {
        $user = $this->actingAsUser();

        $court = Court::factory()->create([
            'status' => 'active',
        ]);

        $bookingResponse = $this->postJson('/api/v1/bookings', [
            'court_id' => $court->id,
            'date' => Carbon::now()->addDay()->toIso8601String(),
            'time_slot' => '6:00 PM',
        ]);
        $bookingResponse->assertCreated();
        $bookingId = $bookingResponse->json('id');

        // Deactivate court via DELETE
        $admin = User::factory()->create(['role' => 'admin']);
        Sanctum::actingAs($admin);
        $this->deleteJson('/api/v1/courts/' . $court->id)->assertOk();

        // Back as booking owner
        Sanctum::actingAs($user);
        $rebookPayload = [
            'date' => Carbon::now()->addDays(2)->toIso8601String(),
            'time_slot' => '7:00 PM',
        ];

        $rebook = $this->postJson('/api/v1/bookings/' . $bookingId . '/rebook', $rebookPayload);
        $rebook->assertStatus(422);
        $rebook->assertJsonPath('message', 'Court is inactive and cannot be rebooked');
    }
}

