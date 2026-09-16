<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\Court;
use App\Models\User;
use Illuminate\Http\UploadedFile;
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

    private function createBusinessForAdmin(User $admin): Business
    {
        $business = Business::create([
            'name' => 'Barberia Tres Amigos',
            'slug' => 'barberia-tres-amigos-' . uniqid(),
            'business_type' => 'barbershop',
            'status' => 'active',
        ]);

        $business->users()->syncWithoutDetaching([
            $admin->id => [
                'role' => 'owner',
                'status' => 'active',
                'accepted_at' => now(),
            ],
        ]);

        return $business;
    }

    private function headers(Business $business): array
    {
        return ['X-Business-Slug' => $business->slug];
    }

    public function test_admin_can_create_court_with_valid_uploaded_image()
    {
        $admin = $this->actingAsAdmin();
        $business = $this->createBusinessForAdmin($admin);
        $image = $this->makeTinyPngUpload();

        $response = $this->post(
            '/api/v1/courts',
            [
                'name' => 'Test Court',
                'address' => 'Somewhere',
                'image' => $image,
            ],
            array_merge(['Accept' => 'application/json'], $this->headers($business))
        );

        $response->assertCreated();
        $court = Court::first();
        $this->assertNotEmpty($court->images);
        $this->assertStringStartsWith('/storage/courts/', $court->images[0]);
    }

    private function makeTinyPngUpload(): UploadedFile
    {
        $png = base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO4BqfQAAAAASUVORK5CYII='
        );

        return UploadedFile::fake()->createWithContent('service.png', $png);
    }

    public function test_http_image_url_causes_422()
    {
        $admin = $this->actingAsAdmin();
        $business = $this->createBusinessForAdmin($admin);

        $response = $this->postJson('/api/v1/courts', [
            'name' => 'Test Court',
            'address' => 'Somewhere',
            'images' => ['http://example.com/image.jpg'],
        ], $this->headers($business));

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['images.0']);
    }

    public function test_admin_can_create_court_without_image(): void
    {
        $admin = $this->actingAsAdmin();
        $business = $this->createBusinessForAdmin($admin);

        $response = $this->postJson('/api/v1/courts', [
            'name' => 'Test Court',
            'address' => 'Somewhere',
        ], $this->headers($business));

        $response->assertCreated();
        $this->assertDatabaseHas('courts', [
            'name' => 'Test Court',
            'address' => 'Somewhere',
        ]);
    }

    public function test_local_file_path_causes_422()
    {
        $admin = $this->actingAsAdmin();
        $business = $this->createBusinessForAdmin($admin);

        $response = $this->postJson('/api/v1/courts', [
            'name' => 'Test Court',
            'address' => 'Somewhere',
            'images' => ['/etc/passwd'],
        ], $this->headers($business));

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['images.0']);
    }

    public function test_too_large_image_causes_422()
    {
        $admin = $this->actingAsAdmin();
        $business = $this->createBusinessForAdmin($admin);

        $largeBinary = random_bytes(3 * 1024 * 1024);
        $dataUrl = 'data:image/jpeg;base64,' . base64_encode($largeBinary);

        $response = $this->postJson('/api/v1/courts', [
            'name' => 'Test Court',
            'address' => 'Somewhere',
            'images' => [$dataUrl],
        ], $this->headers($business));

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['images.0']);
    }
}
