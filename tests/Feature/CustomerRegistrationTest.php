<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerRegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_valid_customer_registers_as_a_non_admin_client_of_the_current_tenant(): void
    {
        $business = $this->business();

        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'Cliente QA JC',
            'email' => 'cliente.jc@example.com',
            'password' => 'Password123!',
        ], $this->headers($business));

        $response->assertOk()
            ->assertJsonStructure(['token', 'user' => ['id', 'email', 'business_id', 'business_role', 'can_manage_business']])
            ->assertJsonPath('user.email', 'cliente.jc@example.com')
            ->assertJsonPath('user.business_id', $business->id)
            ->assertJsonPath('user.business_role', 'client')
            ->assertJsonPath('user.can_manage_business', false);

        $user = User::where('email', 'cliente.jc@example.com')->firstOrFail();
        $this->assertSame('user', $user->role);
        $this->assertFalse((bool) $user->is_super_admin);
        $this->assertDatabaseHas('business_user', [
            'business_id' => $business->id,
            'user_id' => $user->id,
            'role' => 'client',
            'status' => 'active',
        ]);
    }

    public function test_malformed_or_duplicate_customer_email_returns_laravel_validation_errors(): void
    {
        $business = $this->business();

        $this->postJson('/api/v1/auth/register', [
            'name' => 'Cliente QA JC', 'email' => 'not-an-email', 'password' => 'Password123!',
        ], $this->headers($business))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('email');

        User::factory()->create(['email' => 'cliente.jc@example.com']);
        $this->postJson('/api/v1/auth/register', [
            'name' => 'Cliente QA JC', 'email' => 'cliente.jc@example.com', 'password' => 'Password123!',
        ], $this->headers($business))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('email');
    }

    private function business(): Business
    {
        return Business::create([
            'name' => 'JC Studio', 'slug' => 'jc-studio', 'business_type' => 'barbershop', 'status' => 'active',
        ]);
    }

    private function headers(Business $business): array
    {
        return ['X-Business-Slug' => $business->slug];
    }
}
