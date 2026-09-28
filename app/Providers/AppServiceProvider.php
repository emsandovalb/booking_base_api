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

        // Same email+IP keying as `login`: these are all unauthenticated
        // endpoints that accept an email, so the abuse shape (credential
        // stuffing, account enumeration, mailbox-bombing a target via
        // password reset) is the same one `login` already guards against.
        RateLimiter::for('register', function (Request $request) {
            return Limit::perMinute(5)->by(LoginThrottle::key($request));
        });
        RateLimiter::for('password-forgot', function (Request $request) {
            return Limit::perMinute(5)->by(LoginThrottle::key($request));
        });
        RateLimiter::for('password-reset', function (Request $request) {
            return Limit::perMinute(5)->by(LoginThrottle::key($request));
        });

        // Booking create/reschedule require auth:sanctum, so the caller is
        // always an authenticated user — key by user id rather than
        // email+IP. Generous enough for a legitimate customer clicking
        // through several time slots, tight enough to stop a scripted
        // agenda-filling attack from one account.
        RateLimiter::for('booking-write', function (Request $request) {
            return Limit::perMinute(20)->by($request->user()?->id ?: $request->ip());
        });
    }
}
