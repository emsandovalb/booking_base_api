<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SuperAdminBusinessWizardTest extends TestCase
{
    use RefreshDatabase;

    public function test_wizard_page_loads(): void
    {
        $admin = $this->makeAdmin();

        $this->actingAs($admin)
            ->get('/super-admin/businesses/create')
            ->assertOk()
            ->assertSee('Multi-step business creation wizard')
            ->assertSee('Business Identity')
            ->assertSee('Owner Account')
            ->assertSee('Review');
    }

    public function test_wizard_validation_works(): void
    {
        $admin = $this->makeAdmin();

        $payload = $this->wizardPayload([
            'business' => [
                'name' => '',
                'slug' => 'validation-slug',
            ],
            'owner' => [
                'email' => 'invalid-email',
                'password' => 'short',
                'password_confirmation' => 'short',
            ],
        ]);

        $this->actingAs($admin)
            ->from('/super-admin/businesses/create')
            ->post('/super-admin/businesses', $payload)
            ->assertSessionHasErrors([
                'business.name',
                'owner.email',
                'owner.password',
            ]);
    }

    private function makeAdmin(): User
    {
        $admin = User::factory()->create(['name' => 'Super Admin']);
        $admin->forceFill(['role' => 'admin', 'is_super_admin' => true])->save();

        return $admin;
    }

    private function wizardPayload(array $overrides = []): array
    {
        $payload = [
            'business' => [
                'name' => 'Bemuss Wizard',
                'slug' => 'bemuss-wizard',
                'legal_name' => 'Bemuss Wizard S.A.',
                'business_type' => 'barbershop',
                'status' => 'active',
            ],
            'brand' => [
                'app_name' => 'Bemuss Wizard',
                'display_name' => 'BEMUSS WIZARD',
                'short_name' => 'Wizard',
                'tagline' => 'Premium onboarding',
                'subtitle' => 'Step by step',
                'primary_color' => '#D4A84F',
                'secondary_color' => '#E8C36A',
                'background_color' => '#07111f',
            ],
            'contact' => [
                'phone' => '+506 8888-9999',
                'whatsapp' => '+506 8888-9999',
                'email' => 'hola@bemuss.test',
                'website' => 'https://bemuss.test',
                'instagram' => '@bemuss',
                'facebook' => 'Bemuss Wizard',
                'address' => 'San Jose, Costa Rica',
                'country' => 'Costa Rica',
                'city' => 'San Jose',
            ],
            'hours' => [
                'cancellation_window_hours' => 6,
                'cancellation_policy_text' => 'Cancelar con 6 horas de anticipacion.',
                'schedule' => [
                    'monday' => ['is_open' => '1', 'opens_at' => '09:00', 'closes_at' => '18:00'],
                    'tuesday' => ['is_open' => '1', 'opens_at' => '09:00', 'closes_at' => '18:00'],
                    'wednesday' => ['is_open' => '1', 'opens_at' => '09:00', 'closes_at' => '18:00'],
                    'thursday' => ['is_open' => '1', 'opens_at' => '09:00', 'closes_at' => '18:00'],
                    'friday' => ['is_open' => '1', 'opens_at' => '09:00', 'closes_at' => '18:00'],
                    'saturday' => ['is_open' => '1', 'opens_at' => '09:00', 'closes_at' => '18:00'],
                    'sunday' => ['is_open' => '0', 'opens_at' => '09:00', 'closes_at' => '18:00'],
                ],
            ],
            'owner' => [
                'full_name' => 'Wizard Owner',
                'email' => 'wizard-owner@example.com',
                'password' => 'Password123!',
                'password_confirmation' => 'Password123!',
            ],
            'features' => [
                'show_staff' => '1',
                'reservation_staff_selection' => '1',
                'admin_staff_management' => '1',
                'show_gallery' => '1',
                'show_reviews' => '1',
                'show_business_profile' => '1',
                'show_admin_dashboard' => '1',
            ],
        ];

        return array_replace_recursive($payload, $overrides);
    }
}
