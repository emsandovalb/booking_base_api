<?php

namespace Tests\Feature;

use App\Models\Court;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CourtImageSecurityTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsAdmin(): User
    {
        $user = User::factory()->create(['role' => 'admin']);
        Sanctum::actingAs($user);

        return $user;
    }

    public function test_admin_can_create_court_with_valid_data_url_image()
    {
        $this->actingAsAdmin();

        $dataUrl = 'data:image/jpeg;base64,' . base64_encode(str_repeat('a', 100));

        $response = $this->postJson('/api/v1/courts', [
            'name' => 'Test Court',
            'address' => 'Somewhere',
            'images' => [$dataUrl],
        ]);

        $response->assertCreated();
        $court = Court::first();
        $this->assertNotEmpty($court->images);
    }

    public function test_http_image_url_causes_422()
    {
        $this->actingAsAdmin();

        $response = $this->postJson('/api/v1/courts', [
            'name' => 'Test Court',
            'address' => 'Somewhere',
            'images' => ['http://example.com/image.jpg'],
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['images.0']);
    }

    public function test_local_file_path_causes_422()
    {
        $this->actingAsAdmin();

        $response = $this->postJson('/api/v1/courts', [
            'name' => 'Test Court',
            'address' => 'Somewhere',
            'images' => ['/etc/passwd'],
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['images.0']);
    }

    public function test_too_large_image_causes_422()
    {
        $this->actingAsAdmin();

        $largeBinary = random_bytes(3 * 1024 * 1024);
        $dataUrl = 'data:image/jpeg;base64,' . base64_encode($largeBinary);

        $response = $this->postJson('/api/v1/courts', [
            'name' => 'Test Court',
            'address' => 'Somewhere',
            'images' => [$dataUrl],
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['images.0']);
    }
}

