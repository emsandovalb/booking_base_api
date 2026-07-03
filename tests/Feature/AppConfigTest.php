<?php

namespace Tests\Feature;

use App\Models\Business;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AppConfigTest extends TestCase
{
    use RefreshDatabase;

    public function test_default_app_config_endpoint_is_public_and_returns_expected_structure(): void
    {
        $response = $this->getJson('/api/v1/app-config');

        $response->assertOk();
        $this->assertAppConfigStructure($response->json());
        $response->assertJsonPath('identity.short_name', 'Tres Amigos');
        $response->assertJsonPath('features.show_staff', true);
        $response->assertJsonPath('features.show_admin_dashboard', true);
    }

    public function test_business_slug_app_config_endpoint_returns_tres_amigos_config(): void
    {
        $this->createTresAmigosBusiness();

        $response = $this->getJson('/api/v1/businesses/barberia-tres-amigos/app-config');

        $response->assertOk();
        $this->assertAppConfigStructure($response->json());
        $response->assertJsonPath('identity.display_name', 'BARBERÍA TRES AMIGOS');
        $response->assertJsonPath('colors.primary_gold', '#D4A84F');
        $response->assertJsonPath('terminology.service', 'servicio');
    }

    public function test_business_slug_app_config_endpoint_returns_salon_aurora_config(): void
    {
        $this->createTresAmigosBusiness();
        $this->createSalonAuroraBusiness();

        $response = $this->getJson('/api/v1/businesses/salon-aurora/app-config');

        $response->assertOk();
        $this->assertAppConfigStructure($response->json());
        $response->assertJsonPath('identity.display_name', 'SALÓN AURORA');
        $response->assertJsonPath('colors.primary_gold', '#E6B7A9');
        $response->assertJsonPath('terminology.service', 'tratamiento');
    }

    public function test_business_slug_configs_have_distinct_branding_and_copy(): void
    {
        $this->createTresAmigosBusiness();
        $this->createSalonAuroraBusiness();

        $tresAmigos = $this->getJson('/api/v1/businesses/barberia-tres-amigos/app-config');
        $salonAurora = $this->getJson('/api/v1/businesses/salon-aurora/app-config');

        $tresAmigos->assertOk();
        $salonAurora->assertOk();

        $this->assertNotSame(
            $tresAmigos->json('identity.display_name'),
            $salonAurora->json('identity.display_name')
        );
        $this->assertNotSame(
            $tresAmigos->json('colors.primary_gold'),
            $salonAurora->json('colors.primary_gold')
        );
        $this->assertNotSame(
            $tresAmigos->json('terminology.service'),
            $salonAurora->json('terminology.service')
        );
    }

    public function test_unknown_business_slug_returns_404(): void
    {
        $response = $this->getJson('/api/v1/businesses/unknown-business/app-config');

        $response->assertNotFound();
    }

    public function test_inactive_business_returns_404(): void
    {
        Business::create([
            'name' => 'Inactive Shop',
            'slug' => 'inactive-shop',
            'business_type' => 'barbershop',
            'status' => 'inactive',
        ]);

        $response = $this->getJson('/api/v1/businesses/inactive-shop/app-config');

        $response->assertNotFound();
    }

    private function createTresAmigosBusiness(string $status = 'active'): Business
    {
        $whiteLabel = config('white_label');

        return Business::create([
            'name' => 'Barbería Tres Amigos',
            'slug' => 'barberia-tres-amigos',
            'legal_name' => 'Barbería Tres Amigos S.A.',
            'business_type' => 'barbershop',
            'status' => $status,
            'app_config' => [
                'identity' => $whiteLabel['identity'],
                'hours' => $whiteLabel['hours'],
                'policies' => $whiteLabel['policies'],
                'terminology' => $whiteLabel['terminology'],
            ],
            'contact_config' => [
                'contact' => $whiteLabel['contact'],
            ],
            'branding_config' => [
                'assets' => $whiteLabel['assets'],
                'colors' => $whiteLabel['colors'],
            ],
            'feature_config' => [
                'features' => $whiteLabel['features'],
            ],
            'metadata' => [
                'source' => 'test',
            ],
        ]);
    }

    private function createSalonAuroraBusiness(string $status = 'active'): Business
    {
        $whiteLabel = config('white_label');

        return Business::create([
            'name' => 'Salón Aurora',
            'slug' => 'salon-aurora',
            'business_type' => 'salon',
            'status' => $status,
            'app_config' => [
                'identity' => [
                    'app_name' => 'Salón Aurora',
                    'display_name' => 'SALÓN AURORA',
                    'short_name' => 'Aurora',
                    'tagline' => 'Belleza, estilo y cuidado personal',
                    'subtitle' => 'Tu momento, tu estilo',
                    'location_short' => 'San José, Costa Rica',
                    'location_full' => 'San José, Costa Rica',
                    'rating' => 4.8,
                    'review_count' => 96,
                ],
                'hours' => $whiteLabel['hours'],
                'policies' => [
                    'cancellation_window_hours' => 6,
                    'cancellation_policy_text' => 'Podés cancelar o reprogramar hasta 6 horas antes de tu cita.',
                ],
                'terminology' => [
                    'service' => 'tratamiento',
                    'services' => 'tratamientos',
                    'appointment' => 'cita',
                    'appointments' => 'citas',
                    'staff' => 'estilista',
                    'staff_plural' => 'estilistas',
                    'staff_display_name' => 'estilista',
                    'manager' => 'administradora',
                    'business_profile' => 'perfil del salón',
                    'gallery' => 'galería',
                    'reviews' => 'opiniones',
                ],
            ],
            'contact_config' => [
                'contact' => [
                    'phone' => '+506 7000-0000',
                    'whatsapp' => '+506 7000-0000',
                    'email' => 'hola@salonaurora.com',
                    'instagram' => '@salonaurora',
                    'website' => 'https://salonaurora.com',
                    'address' => 'San José, Costa Rica',
                ],
            ],
            'branding_config' => [
                'assets' => $whiteLabel['assets'],
                'colors' => [
                    'primary_gold' => '#E6B7A9',
                    'primary_gold_light' => '#F3D4CC',
                    'primary_gold_dark' => '#A66A5E',
                ],
            ],
            'feature_config' => [
                'features' => $whiteLabel['features'],
            ],
            'metadata' => [
                'source' => 'test',
                'demo_tenant' => 'salon-aurora',
            ],
        ]);
    }

    private function assertAppConfigStructure(array $payload): void
    {
        $this->assertArrayHasKey('identity', $payload);
        $this->assertArrayHasKey('assets', $payload);
        $this->assertArrayHasKey('colors', $payload);
        $this->assertArrayHasKey('contact', $payload);
        $this->assertArrayHasKey('hours', $payload);
        $this->assertArrayHasKey('policies', $payload);
        $this->assertArrayHasKey('terminology', $payload);
        $this->assertArrayHasKey('features', $payload);

        $this->assertArrayHasKey('app_name', $payload['identity']);
        $this->assertArrayHasKey('short_name', $payload['identity']);
        $this->assertArrayHasKey('logo_transparent', $payload['assets']);
        $this->assertArrayHasKey('primary_gold', $payload['colors']);
        $this->assertArrayHasKey('phone', $payload['contact']);
        $this->assertArrayHasKey('label', $payload['hours']);
        $this->assertArrayHasKey('cancellation_window_hours', $payload['policies']);
        $this->assertArrayHasKey('service', $payload['terminology']);
        $this->assertArrayHasKey('show_staff', $payload['features']);
    }
}
