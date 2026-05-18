<?php

namespace Tests\Feature;

use App\Models\Court;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TournamentStoreTest extends TestCase
{
    use RefreshDatabase;

    private function makeAdmin(User $user): User
    {
        $user->forceFill(['role' => 'admin'])->save();

        return $user;
    }

    public function test_guest_cannot_create_tournament(): void
    {
        $response = $this->postJson('/api/v1/tournaments', [
            'name' => 'Copa Apertura',
        ]);

        $response->assertStatus(401);
    }

    public function test_authenticated_user_can_create_tournament(): void
    {
        $user = $this->makeAdmin(User::factory()->create());
        $court = Court::factory()->create([
            'owner_id' => $user->id,
        ]);
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/tournaments', [
            'name' => 'Copa Apertura',
            'description' => 'First season tournament',
            'format' => 'single_elimination',
            'court_id' => $court->id,
            'entry_fee' => 25,
            'prize_pool' => 250,
            'max_teams' => 8,
        ]);

        $response->assertCreated();
        $response->assertJsonPath('name', 'Copa Apertura');
        $response->assertJsonPath('user_id', $user->id);
        $response->assertJsonPath('court_id', $court->id);

        $this->assertDatabaseHas('tournaments', [
            'name' => 'Copa Apertura',
            'user_id' => $user->id,
            'court_id' => $court->id,
            'status' => 'draft',
        ]);
    }

    public function test_tournament_creation_requires_name(): void
    {
        $user = $this->makeAdmin(User::factory()->create());
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/tournaments', [
            'court_id' => 999999,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['name', 'court_id']);
    }
}
