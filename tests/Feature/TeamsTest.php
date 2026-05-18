<?php

namespace Tests\Feature;

use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TeamsTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_create_and_list_own_team(): void
    {
        Storage::fake('public');

        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/my/teams', [
            'name' => 'Tigres FC',
            'description' => 'Equipo competitivo',
            'city' => 'San Jose',
        ]);

        $response->assertCreated();
        $response->assertJsonPath('team.name', 'Tigres FC');
        $response->assertJsonPath('team.user_id', $user->id);

        $list = $this->getJson('/api/v1/my/teams');
        $list->assertOk();
        $list->assertJsonPath('data.0.name', 'Tigres FC');
        $list->assertJsonPath('data.0.users_count', 1);

        $this->assertDatabaseHas('teams', [
            'user_id' => $user->id,
            'name' => 'Tigres FC',
        ]);
    }

    public function test_team_can_be_updated_by_owner(): void
    {
        Storage::fake('public');

        $user = User::factory()->create();
        $team = Team::create([
            'user_id' => $user->id,
            'name' => 'Tigres FC',
            'status' => 'active',
        ]);
        $team->users()->attach($user->id, [
            'role' => 'owner',
            'status' => 'active',
            'joined_at' => now(),
        ]);

        Sanctum::actingAs($user);

        $response = $this->putJson("/api/v1/my/teams/{$team->id}", [
            'name' => 'Tigres Pro',
            'city' => 'Heredia',
        ]);

        $response->assertOk();
        $response->assertJsonPath('team.name', 'Tigres Pro');
        $response->assertJsonPath('team.city', 'Heredia');
    }

    public function test_team_logo_upload_is_stored(): void
    {
        Storage::fake('public');

        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/my/teams', [
            'name' => 'Leones FC',
            'logo' => UploadedFile::fake()->create('logo.jpg', 100, 'image/jpeg'),
        ]);

        $response->assertCreated();
        $team = Team::first();
        $this->assertNotNull($team?->logo);
        Storage::disk('public')->assertExists($team->logo);
    }

    public function test_cannot_update_others_team(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $team = Team::create([
            'user_id' => $owner->id,
            'name' => 'Tigres FC',
            'status' => 'active',
        ]);

        Sanctum::actingAs($other);

        $response = $this->putJson("/api/v1/my/teams/{$team->id}", [
            'name' => 'Nope FC',
        ]);

        $response->assertStatus(403);
        $response->assertJsonPath('message', 'Forbidden');
    }
}
