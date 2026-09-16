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

    public function test_super_admin_can_create_event()
    {
        $user = User::factory()->create(['role' => 'user', 'is_super_admin' => true]);
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/events', [
            'title' => 'My secure event',
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('events', [
            'title' => 'My secure event',
        ]);
    }

    public function test_non_super_admin_cannot_create_event()
    {
        $user = User::factory()->create(['role' => 'user', 'is_super_admin' => false]);
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/events', [
            'title' => 'Should not be created',
        ]);

        $response->assertStatus(403);
        $this->assertDatabaseMissing('events', [
            'title' => 'Should not be created',
        ]);
    }

    public function test_legacy_global_admin_role_alone_cannot_create_event()
    {
        // The legacy `role` column no longer grants this either — only
        // is_super_admin does, matching the #2/#3 platform-flag model.
        $user = User::factory()->create(['role' => 'admin', 'is_super_admin' => false]);
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/events', [
            'title' => 'Legacy role should not work',
        ]);

        $response->assertStatus(403);
    }

    public function test_events_index_is_public()
    {
        Event::factory()->create();

        $response = $this->getJson('/api/v1/events');
        $response->assertOk();
    }
}

