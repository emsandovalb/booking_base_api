<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\User;
use Database\Seeders\BusinessSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Production origins are explicit. Dynamic loopback origins are available
 * only through the repository-local CORS_ALLOW_LOCALHOST opt-in.
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
        config(['cors.allowed_origins_patterns' => []]);

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
        putenv('CORS_ALLOW_LOCALHOST');

        $allowedOrigins = require config_path('cors.php');

        $this->assertSame([], $allowedOrigins['allowed_origins']);
        $this->assertSame([], $allowedOrigins['allowed_origins_patterns']);
        $this->assertFalse($allowedOrigins['supports_credentials']);
    }

    public function test_options_auth_login_allows_a_dynamic_localhost_origin_and_tenant_header(): void
    {
        $this->allowLocalFlutterOrigins();
        $origin = 'http://localhost:57051';

        $this->options('/api/v1/auth/login', [], [
            'Origin' => $origin,
            'Access-Control-Request-Method' => 'POST',
            'Access-Control-Request-Headers' => 'content-type,x-business-slug',
        ])
            ->assertSuccessful()
            ->assertHeader('Access-Control-Allow-Origin', $origin);
    }

    public function test_post_auth_login_includes_cors_headers_for_dynamic_localhost_origin(): void
    {
        [$business, $owner] = $this->createOwner('jc-studio', 'macchie.23@gmail.com', 'Password123!');
        $this->allowLocalFlutterOrigins();
        $origin = 'http://localhost:61427';

        $this->withHeaders([
            'Origin' => $origin,
            'X-Business-Slug' => $business->slug,
        ])->postJson('/api/v1/auth/login', [
            'email' => $owner->email,
            'password' => 'Password123!',
        ])->assertOk()
            ->assertHeader('Access-Control-Allow-Origin', $origin)
            ->assertJsonPath('user.business_slug', 'jc-studio')
            ->assertJsonPath('user.business_role', 'owner');
    }

    public function test_auth_errors_keep_cors_headers_for_an_allowed_flutter_origin(): void
    {
        $this->createOwner('jc-studio', 'owner@example.com', 'Password123!');
        $this->allowLocalFlutterOrigins();
        $origin = 'http://localhost:60123';

        $this->withHeaders([
            'Origin' => $origin,
            'X-Business-Slug' => 'jc-studio',
        ])->postJson('/api/v1/auth/login', [
            'email' => 'missing@example.com',
            'password' => 'incorrect',
        ])->assertUnprocessable()
            ->assertHeader('Access-Control-Allow-Origin', $origin);
    }

    public function test_authenticated_me_allows_a_dynamic_127_origin_and_preserves_tenant_context(): void
    {
        [$business, $owner] = $this->createOwner('barberia-tres-amigos', 'barbershop.owner@example.com', 'password');
        $this->allowLocalFlutterOrigins();
        $origin = 'http://127.0.0.1:49217';

        $login = $this->withHeaders([
            'Origin' => $origin,
            'X-Business-Slug' => $business->slug,
        ])->postJson('/api/v1/auth/login', [
            'email' => $owner->email,
            'password' => 'password',
        ])->assertOk();

        $this->withHeaders([
            'Origin' => $origin,
            'Authorization' => 'Bearer '.$login->json('token'),
            'X-Business-Slug' => $business->slug,
        ])->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertHeader('Access-Control-Allow-Origin', $origin)
            ->assertJsonPath('user.business_slug', 'barberia-tres-amigos')
            ->assertJsonPath('user.business_role', 'owner')
            ->assertJsonPath('user.can_manage_business', true);
    }

    public function test_unlisted_production_like_origin_is_not_allowed_by_local_patterns(): void
    {
        $this->allowLocalFlutterOrigins();

        $this->options('/api/v1/auth/login', [], [
            'Origin' => 'https://unlisted-pwa.example.com',
            'Access-Control-Request-Method' => 'POST',
            'Access-Control-Request-Headers' => 'content-type,x-business-slug',
        ])
            ->assertHeaderMissing('Access-Control-Allow-Origin');
    }

    public function test_super_admin_session_login_is_unaffected_by_api_cors_configuration(): void
    {
        $admin = User::factory()->create([
            'email' => 'admin@bemuss.local',
            'password' => Hash::make('Bemuss123!'),
            'role' => 'admin',
        ]);
        $admin->forceFill(['is_super_admin' => true])->save();

        $this->post('/login', [
            'email' => 'admin@bemuss.local',
            'password' => 'Bemuss123!',
        ])->assertRedirect(route('super-admin.dashboard'));

        $this->get('/super-admin')->assertOk();
    }

    private function allowLocalFlutterOrigins(): void
    {
        config([
            'cors.allowed_origins' => [],
            'cors.allowed_origins_patterns' => [
                '#^https?://localhost(?::[0-9]{1,5})?$#',
                '#^https?://127\\.0\\.0\\.1(?::[0-9]{1,5})?$#',
            ],
        ]);
    }

    /** @return array{Business, User} */
    private function createOwner(string $slug, string $email, string $password): array
    {
        $business = Business::query()->create([
            'name' => $slug,
            'slug' => $slug,
            'business_type' => 'barbershop',
            'status' => 'active',
        ]);
        $owner = User::factory()->create([
            'email' => $email,
            'password' => Hash::make($password),
            'role' => 'user',
        ]);
        $business->users()->attach($owner->id, [
            'role' => 'owner',
            'status' => 'active',
            'accepted_at' => now(),
        ]);

        return [$business, $owner];
    }
}
