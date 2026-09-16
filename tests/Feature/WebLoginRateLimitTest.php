<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Covers the throttle:login limiter on the session-based Super Admin
 * login (routes/web.php POST /login) — 5 attempts per minute, keyed by
 * email+IP, registered in AppServiceProvider::boot(). This is separate
 * from the API's /auth/login, which keeps the framework's generic
 * throttle:api (60/min) untouched.
 */
class WebLoginRateLimitTest extends TestCase
{
    use RefreshDatabase;

    public function test_sixth_attempt_within_the_window_is_throttled(): void
    {
        User::factory()->create([
            'email' => 'admin@example.com',
            'password' => bcrypt('secret123'),
        ]);

        for ($i = 0; $i < 5; $i++) {
            $this->from('/login')
                ->post('/login', [
                    'email' => 'admin@example.com',
                    'password' => 'wrong-password',
                ])
                ->assertRedirect('/login')
                ->assertSessionHasErrors('email');
        }

        // 6th attempt in the same minute, still wrong password: throttled
        // before credentials are even checked.
        $this->post('/login', [
            'email' => 'admin@example.com',
            'password' => 'wrong-password',
        ])->assertStatus(429);
    }

    public function test_throttle_is_keyed_per_email_so_other_accounts_are_unaffected(): void
    {
        User::factory()->create([
            'email' => 'admin@example.com',
            'password' => bcrypt('secret123'),
        ]);
        $other = User::factory()->create([
            'email' => 'other-admin@example.com',
            'password' => bcrypt('secret123'),
        ]);
        $other->forceFill(['role' => 'admin', 'is_super_admin' => true])->save();

        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', [
                'email' => 'admin@example.com',
                'password' => 'wrong-password',
            ]);
        }
        $this->post('/login', [
            'email' => 'admin@example.com',
            'password' => 'wrong-password',
        ])->assertStatus(429);

        // A different account, same IP, is not caught by admin@example.com's lockout.
        $this->post('/login', [
            'email' => 'other-admin@example.com',
            'password' => 'secret123',
        ])->assertRedirect(route('super-admin.dashboard'));
    }

    public function test_successful_login_clears_the_throttle_count(): void
    {
        $user = User::factory()->create([
            'email' => 'admin@example.com',
            'password' => bcrypt('secret123'),
        ]);
        $user->forceFill(['role' => 'admin', 'is_super_admin' => true])->save();

        // Three failed attempts, then a successful one.
        for ($i = 0; $i < 3; $i++) {
            $this->post('/login', [
                'email' => 'admin@example.com',
                'password' => 'wrong-password',
            ]);
        }
        $this->post('/login', [
            'email' => 'admin@example.com',
            'password' => 'secret123',
        ])->assertRedirect(route('super-admin.dashboard'));

        $this->assertAuthenticatedAs($user);

        // The window is reset: five more attempts should be available
        // again immediately, not still counting from before the success.
        \Illuminate\Support\Facades\Auth::logout();
        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', [
                'email' => 'admin@example.com',
                'password' => 'wrong-password',
            ])->assertStatus(302); // redirected back with a validation error, not 429
        }
    }

    public function test_throttle_resets_after_the_time_window_elapses(): void
    {
        User::factory()->create([
            'email' => 'admin@example.com',
            'password' => bcrypt('secret123'),
        ]);

        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', [
                'email' => 'admin@example.com',
                'password' => 'wrong-password',
            ]);
        }
        $this->post('/login', [
            'email' => 'admin@example.com',
            'password' => 'wrong-password',
        ])->assertStatus(429);

        $this->travel(61)->seconds();

        $this->from('/login')
            ->post('/login', [
                'email' => 'admin@example.com',
                'password' => 'wrong-password',
            ])
            ->assertRedirect('/login')
            ->assertSessionHasErrors('email');
    }
}
