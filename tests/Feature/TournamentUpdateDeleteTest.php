<?php

namespace Tests\Feature;

use App\Models\Court;
use App\Models\Tournament;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TournamentUpdateDeleteTest extends TestCase
{
    use RefreshDatabase;

    private function makeAdmin(User $user): User
    {
        $user->forceFill(['role' => 'admin'])->save();

        return $user;
    }

    public function test_owner_can_update_tournament(): void
    {
        $user = $this->makeAdmin(User::factory()->create());
        $court = Court::factory()->create(['owner_id' => $user->id]);
        $tournament = Tournament::create([
            'user_id' => $user->id,
            'court_id' => $court->id,
            'name' => 'Copa Inicial',
            'status' => 'draft',
        ]);

        Sanctum::actingAs($user);

        $response = $this->putJson('/api/v1/tournaments/' . $tournament->id, [
            'name' => 'Copa Actualizada',
            'court_id' => $court->id,
            'max_teams' => 16,
        ]);

        $response->assertOk();
        $response->assertJsonPath('name', 'Copa Actualizada');
        $response->assertJsonPath('max_teams', 16);

        $this->assertDatabaseHas('tournaments', [
            'id' => $tournament->id,
            'name' => 'Copa Actualizada',
            'max_teams' => 16,
            'user_id' => $user->id,
        ]);
    }

    public function test_non_owner_cannot_update_tournament(): void
    {
        $owner = $this->makeAdmin(User::factory()->create());
        $other = User::factory()->create();
        $court = Court::factory()->create(['owner_id' => $owner->id]);
        $tournament = Tournament::create([
            'user_id' => $owner->id,
            'court_id' => $court->id,
            'name' => 'Copa Inicial',
            'status' => 'draft',
        ]);

        Sanctum::actingAs($other);

        $response = $this->putJson('/api/v1/tournaments/' . $tournament->id, [
            'name' => 'Copa Robada',
        ]);

        $response->assertStatus(403);
        $response->assertJsonPath('message', 'Forbidden');
    }

    public function test_update_fails_validation_for_bad_payload(): void
    {
        $user = $this->makeAdmin(User::factory()->create());
        $court = Court::factory()->create(['owner_id' => $user->id]);
        $tournament = Tournament::create([
            'user_id' => $user->id,
            'court_id' => $court->id,
            'name' => 'Copa Inicial',
            'status' => 'draft',
        ]);

        Sanctum::actingAs($user);

        $response = $this->putJson('/api/v1/tournaments/' . $tournament->id, [
            'court_id' => 999999,
            'max_teams' => 1,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['court_id', 'max_teams']);
    }

    public function test_owner_can_close_tournament_without_deleting_row(): void
    {
        $user = $this->makeAdmin(User::factory()->create());
        $court = Court::factory()->create(['owner_id' => $user->id]);
        $tournament = Tournament::create([
            'user_id' => $user->id,
            'court_id' => $court->id,
            'name' => 'Copa Inicial',
            'status' => 'draft',
        ]);

        Sanctum::actingAs($user);

        $response = $this->deleteJson('/api/v1/tournaments/' . $tournament->id);

        $response->assertOk();
        $response->assertJsonPath('message', 'Tournament closed');
        $response->assertJsonPath('tournament.status', 'inactive');

        $this->assertDatabaseHas('tournaments', [
            'id' => $tournament->id,
            'status' => 'inactive',
        ]);
    }
}
