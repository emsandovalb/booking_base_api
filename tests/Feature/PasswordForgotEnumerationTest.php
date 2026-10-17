<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * The forgot-password endpoint answers the same way for registered and
 * unknown emails, so it can't be used to discover who has an account.
 */
class PasswordForgotEnumerationTest extends TestCase
{
    use RefreshDatabase;

    public function test_known_email_gets_generic_response_and_a_reset_link(): void
    {
        Notification::fake();
        $user = User::factory()->create(['email' => 'known@example.com']);

        $this->postJson('/api/v1/auth/password/forgot', ['email' => 'known@example.com'])
            ->assertOk()
            ->assertJsonPath('message', 'If an account exists for that email, a password reset link has been sent.');

        Notification::assertSentTo($user, ResetPassword::class);
    }

    public function test_unknown_email_gets_the_same_generic_response(): void
    {
        Notification::fake();

        $this->postJson('/api/v1/auth/password/forgot', ['email' => 'nobody@example.com'])
            ->assertOk()
            ->assertJsonPath('message', 'If an account exists for that email, a password reset link has been sent.');

        Notification::assertNothingSent();
    }

    public function test_malformed_email_is_still_rejected(): void
    {
        $this->postJson('/api/v1/auth/password/forgot', ['email' => 'not-an-email'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('email');
    }
}
