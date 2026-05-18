<?php

namespace Tests\Feature;

use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AuthMeTeamsTest extends TestCase
{
    use RefreshDatabase;

    public function test_auth_me_includes_user_teams(): void
    {
        $user = User::factory()->create();
        $team = Team::create([
            'user_id' => $user->id,
            'name' => 'Tigres FC',
            'status' => 'active',
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/auth/me');

        $response->assertOk();
        $response->assertJsonCount(1, 'teams');
        $response->assertJsonFragment([
            'id' => $team->id,
            'user_id' => $user->id,
            'name' => 'Tigres FC',
        ]);
    }
}
