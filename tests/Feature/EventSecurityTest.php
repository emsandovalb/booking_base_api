<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class EventSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_create_event()
    {
        $response = $this->postJson('/api/v1/events', [
            'title' => 'Test event',
        ]);

        $response->assertStatus(401);
    }

    public function test_authenticated_user_can_create_event()
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/events', [
            'title' => 'My secure event',
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('events', [
            'title' => 'My secure event',
        ]);
    }

    public function test_events_index_is_public()
    {
        Event::factory()->create();

        $response = $this->getJson('/api/v1/events');
        $response->assertOk();
    }
}

