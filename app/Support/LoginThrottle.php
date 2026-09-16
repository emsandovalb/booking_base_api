<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Shared key-building for the `login` rate limiter, used both when
 * registering the limiter (AppServiceProvider) and when clearing it on a
 * successful login (LoginController) — kept in one place so the two never
 * drift out of sync.
 */
class LoginThrottle
{
    private const LIMITER_NAME = 'login';

    public static function key(Request $request): string
    {
        $email = (string) $request->string('email');

        return Str::transliterate(Str::lower($email).'|'.$request->ip());
    }

    /**
     * The actual cache key ThrottleRequests::handleRequestUsingNamedLimiter()
     * stores hits under: md5($limiterName.$limit->key) when key-hashing is
     * enabled (the framework default since Laravel 10). RateLimiter::clear()
     * needs this exact form, not the raw key() above, or a successful login
     * won't actually reset what the middleware is counting against.
     */
    public static function cacheKey(Request $request): string
    {
        return md5(self::LIMITER_NAME.self::key($request));
    }
}
