<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WebLoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_page_is_visible(): void
    {
        $this->get('/login')->assertOk()->assertSee('Acceso Super Admin');
    }

    public function test_admin_can_log_in_and_reach_super_admin(): void
    {
        $user = User::factory()->create([
            'email' => 'admin@example.com',
            'password' => bcrypt('secret123'),
        ]);
        $user->forceFill(['role' => 'admin', 'is_super_admin' => true])->save();

        $this->post('/login', [
            'email' => 'admin@example.com',
            'password' => 'secret123',
        ])->assertRedirect(route('super-admin.dashboard'));

        $this->assertAuthenticatedAs($user);
    }

    public function test_login_failure_shows_error(): void
    {
        User::factory()->create([
            'email' => 'admin@example.com',
            'password' => bcrypt('secret123'),
        ]);

        $this->from('/login')
            ->post('/login', [
                'email' => 'admin@example.com',
                'password' => 'wrong-password',
            ])
            ->assertRedirect('/login')
            ->assertSessionHasErrors('email');
    }

    public function test_user_can_log_out(): void
    {
        $user = User::factory()->create([
            'email' => 'admin@example.com',
            'password' => bcrypt('secret123'),
        ]);
        $user->forceFill(['role' => 'admin', 'is_super_admin' => true])->save();

        $this->actingAs($user)
            ->post('/logout')
            ->assertRedirect(route('login'));

        $this->assertGuest();
    }
}
