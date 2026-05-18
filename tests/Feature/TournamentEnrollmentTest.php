<?php

namespace Tests\Feature;

use App\Models\Team;
use App\Models\Tournament;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TournamentEnrollmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_non_admin_can_enroll_owned_team(): void
    {
        $user = User::factory()->create();
        $team = Team::create([
            'user_id' => $user->id,
            'name' => 'Tigres FC',
            'status' => 'active',
        ]);
        $tournament = Tournament::create([
            'user_id' => $user->id,
            'name' => 'Copa Apertura',
            'status' => 'draft',
            'max_teams' => 8,
        ]);

        Sanctum::actingAs($user);

        $response = $this->postJson("/api/v1/tournaments/{$tournament->id}/teams", [
            'team_id' => $team->id,
        ]);

        $response->assertCreated();
        $response->assertJsonPath('enrollment.team_id', $team->id);
        $response->assertJsonPath('enrollment.tournament_id', $tournament->id);
        $response->assertJsonPath('enrollment.status', 'pending');
        $response->assertJsonPath('tournament.id', $tournament->id);
        $response->assertJsonPath('tournament.teams_count', 1);

        $list = $this->getJson("/api/v1/tournaments/{$tournament->id}/teams");
        $list->assertOk();
        $list->assertJsonPath('data.0.id', $team->id);
        $list->assertJsonPath('data.0.pivot.status', 'pending');

        $this->assertDatabaseHas('tournament_teams', [
            'tournament_id' => $tournament->id,
            'team_id' => $team->id,
            'status' => 'pending',
        ]);
    }

    public function test_admin_cannot_enroll_as_normal_participant(): void
    {
        $admin = User::factory()->create();
        $admin->forceFill(['role' => 'admin'])->save();

        $team = Team::create([
            'user_id' => $admin->id,
            'name' => 'Admin FC',
            'status' => 'active',
        ]);
        $tournament = Tournament::create([
            'user_id' => $admin->id,
            'name' => 'Copa Apertura',
            'status' => 'draft',
        ]);

        Sanctum::actingAs($admin);

        $response = $this->postJson("/api/v1/tournaments/{$tournament->id}/teams", [
            'team_id' => $team->id,
        ]);

        $response->assertStatus(403);
        $response->assertJsonPath('message', 'Forbidden');
    }

    public function test_duplicate_enrollment_is_blocked(): void
    {
        $user = User::factory()->create();
        $team = Team::create([
            'user_id' => $user->id,
            'name' => 'Tigres FC',
            'status' => 'active',
        ]);
        $tournament = Tournament::create([
            'user_id' => $user->id,
            'name' => 'Copa Apertura',
            'status' => 'draft',
            'max_teams' => 8,
        ]);

        Sanctum::actingAs($user);

        $first = $this->postJson("/api/v1/tournaments/{$tournament->id}/teams", [
            'team_id' => $team->id,
        ]);
        $first->assertCreated();

        $duplicate = $this->postJson("/api/v1/tournaments/{$tournament->id}/teams", [
            'team_id' => $team->id,
        ]);

        $duplicate->assertStatus(422);
        $duplicate->assertJsonPath('message', 'Team is already enrolled in this tournament');
    }

    public function test_enrollment_blocked_when_max_teams_reached(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        $teamOne = Team::create([
            'user_id' => $user->id,
            'name' => 'Tigres FC',
            'status' => 'active',
        ]);
        $teamTwo = Team::create([
            'user_id' => $other->id,
            'name' => 'Leones FC',
            'status' => 'active',
        ]);
        $tournament = Tournament::create([
            'user_id' => $user->id,
            'name' => 'Copa Apertura',
            'status' => 'draft',
            'max_teams' => 1,
        ]);

        Sanctum::actingAs($user);

        $first = $this->postJson("/api/v1/tournaments/{$tournament->id}/teams", [
            'team_id' => $teamOne->id,
        ]);
        $first->assertCreated();

        Sanctum::actingAs($other);

        $full = $this->postJson("/api/v1/tournaments/{$tournament->id}/teams", [
            'team_id' => $teamTwo->id,
        ]);

        $full->assertStatus(422);
        $full->assertJsonPath('message', 'Tournament is full');
    }

    public function test_enrollment_blocked_for_inactive_tournament(): void
    {
        $user = User::factory()->create();
        $team = Team::create([
            'user_id' => $user->id,
            'name' => 'Tigres FC',
            'status' => 'active',
        ]);
        $tournament = Tournament::create([
            'user_id' => $user->id,
            'name' => 'Copa Cerrada',
            'status' => 'inactive',
        ]);

        Sanctum::actingAs($user);

        $response = $this->postJson("/api/v1/tournaments/{$tournament->id}/teams", [
            'team_id' => $team->id,
        ]);

        $response->assertStatus(422);
        $response->assertJsonPath('message', 'Tournament is closed');
    }

    public function test_unauthorized_team_enrollment_is_blocked(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $team = Team::create([
            'user_id' => $owner->id,
            'name' => 'Tigres FC',
            'status' => 'active',
        ]);
        $tournament = Tournament::create([
            'user_id' => $owner->id,
            'name' => 'Copa Apertura',
            'status' => 'draft',
            'max_teams' => 8,
        ]);

        Sanctum::actingAs($other);

        $response = $this->postJson("/api/v1/tournaments/{$tournament->id}/teams", [
            'team_id' => $team->id,
        ]);

        $response->assertStatus(403);
        $response->assertJsonPath('message', 'Forbidden');
    }
}
