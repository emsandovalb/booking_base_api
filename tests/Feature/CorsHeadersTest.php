<?php

namespace Tests\Feature;

use Database\Seeders\BusinessSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * config/cors.php has no hardcoded origin fallback: the allow-list comes
 * exclusively from FRONTEND_URL / FRONTEND_URLS. These tests set that
 * config directly (rather than relying on whatever happens to be in the
 * test environment's .env) so the fail-closed behavior is explicit.
 */
class CorsHeadersTest extends TestCase
{
    use RefreshDatabase;

    public function test_resources_endpoint_allows_an_explicitly_configured_origin(): void
    {
        $this->seed(BusinessSeeder::class);

        $origin = 'https://reservas.barberiatresamigos.com';
        config(['cors.allowed_origins' => [$origin]]);

        $this->withHeaders([
            'Accept' => 'application/json',
            'Origin' => $origin,
            'X-Business-Slug' => 'tres-amigos',
        ])
            ->getJson('/api/v1/resources?page=1&per_page=1')
            ->assertOk()
            ->assertHeader('Access-Control-Allow-Origin', $origin);
    }

    public function test_resources_endpoint_allows_multiple_configured_origins(): void
    {
        $this->seed(BusinessSeeder::class);

        $primary = 'https://reservas.barberiatresamigos.com';
        $secondary = 'https://admin.barberiatresamigos.com';
        config(['cors.allowed_origins' => [$primary, $secondary]]);

        $this->withHeaders([
            'Accept' => 'application/json',
            'Origin' => $secondary,
            'X-Business-Slug' => 'tres-amigos',
        ])
            ->getJson('/api/v1/resources?page=1&per_page=1')
            ->assertOk()
            ->assertHeader('Access-Control-Allow-Origin', $secondary);
    }

    public function test_unconfigured_origin_is_rejected_not_silently_trusted(): void
    {
        $this->seed(BusinessSeeder::class);

        // Two allowed origins so the underlying fruitcake/php-cors library
        // actually compares the request Origin against the list — with a
        // single configured origin it short-circuits and echoes that one
        // origin back unconditionally (safe in practice: the browser still
        // enforces same-origin match on its side), which would make this
        // assertion test the wrong thing.
        config(['cors.allowed_origins' => [
            'https://reservas.barberiatresamigos.com',
            'https://admin.barberiatresamigos.com',
        ]]);

        $this->withHeaders([
            'Accept' => 'application/json',
            'Origin' => 'http://evil.example.com',
            'X-Business-Slug' => 'tres-amigos',
        ])
            ->getJson('/api/v1/resources?page=1&per_page=1')
            ->assertOk()
            ->assertHeaderMissing('Access-Control-Allow-Origin');
    }

    public function test_empty_allow_list_fails_closed_when_no_origin_env_vars_are_set(): void
    {
        $this->seed(BusinessSeeder::class);

        config(['cors.allowed_origins' => []]);

        $this->withHeaders([
            'Accept' => 'application/json',
            'Origin' => 'http://127.0.0.1:18080',
            'X-Business-Slug' => 'tres-amigos',
        ])
            ->getJson('/api/v1/resources?page=1&per_page=1')
            ->assertOk()
            ->assertHeaderMissing('Access-Control-Allow-Origin');
    }

    public function test_config_has_no_hardcoded_dev_origin_fallback(): void
    {
        // Guards against the fallback creeping back in: with FRONTEND_URL
        // and FRONTEND_URLS both unset, the allow-list must be empty.
        putenv('FRONTEND_URL');
        putenv('FRONTEND_URLS');

        $allowedOrigins = require config_path('cors.php');

        $this->assertSame([], $allowedOrigins['allowed_origins']);
        $this->assertFalse($allowedOrigins['supports_credentials']);
    }
}
