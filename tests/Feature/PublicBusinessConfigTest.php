<?php

namespace Tests\Feature;

use App\Models\Business;
use Database\Seeders\BusinessSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicBusinessConfigTest extends TestCase
{
    use RefreshDatabase;

    public function test_tres_amigos_public_config_resolves_through_alias_slug(): void
    {
        $this->seed(BusinessSeeder::class);

        $business = Business::query()
            ->where('slug', 'barberia-tres-amigos')
            ->firstOrFail();

        $this->getJson('/api/v1/public/businesses/tres-amigos/config')
            ->assertOk()
            ->assertJsonPath('business_id', $business->id)
            ->assertJsonPath('slug', 'barberia-tres-amigos')
            ->assertJsonPath('display_name', 'BARBERÍA TRES AMIGOS')
            ->assertJsonPath('short_name', 'Tres Amigos')
            ->assertJsonPath('contact_phone', '+506 8888-3366')
            ->assertJsonPath('business_type', 'barbershop')
            ->assertJsonMissingPath('contact.email')
            ->assertJsonMissingPath('password')
            ->assertJsonMissingPath('tokens');
    }

    public function test_unknown_public_business_slug_returns_404(): void
    {
        $this->seed(BusinessSeeder::class);

        $this->getJson('/api/v1/public/businesses/unknown-business/config')
            ->assertNotFound()
            ->assertJsonPath('message', 'Business not found.');
    }

    public function test_public_config_is_cacheable_and_answers_304_for_a_matching_etag(): void
    {
        $this->seed(BusinessSeeder::class);

        $first = $this->getJson('/api/v1/public/businesses/barberia-tres-amigos/config')
            ->assertOk();

        $etag = $first->headers->get('ETag');
        $this->assertNotEmpty($etag);
        $this->assertStringContainsString('max-age=60', $first->headers->get('Cache-Control'));
        $this->assertStringContainsString('public', $first->headers->get('Cache-Control'));

        $this->withHeaders(['If-None-Match' => $etag])
            ->getJson('/api/v1/public/businesses/barberia-tres-amigos/config')
            ->assertStatus(304)
            ->assertContent('');
    }

    public function test_public_config_etag_changes_when_branding_changes(): void
    {
        $this->seed(BusinessSeeder::class);

        $etag = $this->getJson('/api/v1/public/businesses/barberia-tres-amigos/config')
            ->headers->get('ETag');

        Business::query()->where('slug', 'barberia-tres-amigos')->firstOrFail()
            ->forceFill(['business_type' => 'salon'])->save();

        $this->withHeaders(['If-None-Match' => $etag])
            ->getJson('/api/v1/public/businesses/barberia-tres-amigos/config')
            ->assertOk()
            ->assertJsonPath('business_type', 'salon');
    }

    public function test_unknown_slug_404_is_not_marked_cacheable(): void
    {
        $this->seed(BusinessSeeder::class);

        $response = $this->getJson('/api/v1/public/businesses/unknown-business/config')
            ->assertNotFound();

        $this->assertNull($response->headers->get('ETag'));
    }
}
