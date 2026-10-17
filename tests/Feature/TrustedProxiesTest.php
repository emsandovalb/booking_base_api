<?php

namespace Tests\Feature;

use App\Providers\AppServiceProvider;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Behind a load balancer the client IP and scheme come from X-Forwarded-*
 * headers, which are only honoured for proxies listed in TRUSTED_PROXIES.
 */
class TrustedProxiesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Route::get('/_test/client', fn (Request $request) => [
            'ip' => $request->ip(),
            'secure' => $request->secure(),
        ]);
    }

    protected function tearDown(): void
    {
        TrustProxies::flushState();

        parent::tearDown();
    }

    private function bootWithProxies(?string $value): void
    {
        config(['app.trusted_proxies' => $value]);
        (new AppServiceProvider($this->app))->boot();
    }

    private function requestThroughProxy()
    {
        return $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.5'])
            ->withHeaders([
                'X-Forwarded-For' => '203.0.113.9',
                'X-Forwarded-Proto' => 'https',
            ])
            ->getJson('/_test/client');
    }

    public function test_forwarded_headers_are_ignored_when_no_proxy_is_trusted(): void
    {
        $this->bootWithProxies(null);

        $this->requestThroughProxy()
            ->assertJson(['ip' => '10.0.0.5', 'secure' => false]);
    }

    public function test_forwarded_headers_are_used_for_a_listed_proxy(): void
    {
        $this->bootWithProxies('10.0.0.1, 10.0.0.5');

        $this->requestThroughProxy()
            ->assertJson(['ip' => '203.0.113.9', 'secure' => true]);
    }

    public function test_wildcard_trusts_any_proxy(): void
    {
        $this->bootWithProxies('*');

        $this->requestThroughProxy()
            ->assertJson(['ip' => '203.0.113.9', 'secure' => true]);
    }
}
