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
}
