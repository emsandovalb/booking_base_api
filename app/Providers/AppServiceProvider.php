<?php

namespace App\Providers;

use App\Support\LoginThrottle;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Session-based Super Admin login (routes/web.php POST /login).
        // The mobile API's /auth/login already has the generic
        // throttle:api (60/min) from the framework default — this is
        // specifically a tighter, per-credential lockout for the web
        // login that reaches the Super Admin panel.
        RateLimiter::for('login', function (Request $request) {
            return Limit::perMinute(5)->by(LoginThrottle::key($request));
        });
    }
}
