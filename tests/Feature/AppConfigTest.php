<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Support\BrandingConfig;
use Database\Seeders\BusinessSeeder;
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

    public function test_jc_studio_app_config_endpoint_returns_persisted_branding_values(): void
    {
        Business::create([
            'name' => 'JC Studio',
            'slug' => 'jc-studio',
            'legal_name' => 'JC Studio Capilar S.A.',
            'business_type' => 'salon',
            'status' => 'active',
            'app_config' => [
                'identity' => [
                    'app_name' => 'JC Studio',
                    'display_name' => 'JC Studio Capilar',
                    'short_name' => 'JC',
                    'tagline' => 'Cortes, asesorias y experiencias premium',
                    'subtitle' => 'Tu estilo, tu experiencia',
                    'location_short' => 'Zapote',
                    'location_full' => 'Zapote, Costa Rica',
                    'rating' => 4.9,
                    'review_count' => 128,
                ],
                'terminology' => [
                    'business' => 'salón',
                    'business_profile' => 'perfil de la barbería',
                    'service' => 'servicio',
                    'services' => 'servicios',
                    'appointment' => 'cita',
                    'appointments' => 'citas',
                    'staff' => 'barbero',
                    'staff_plural' => 'barberos',
                    'staff_display_name' => 'barbero',
                    'manager' => 'administrador',
                    'gallery' => 'galería',
                    'reviews' => 'opiniones',
                ],
            ],
            'contact_config' => [
                'contact' => [
                    'phone' => '+506 8888-3366',
                    'whatsapp' => '+506 8888-3366',
                    'email' => 'hola@jcstudiocapilar.com',
                    'instagram' => '@JC Studio capilar',
                    'website' => 'https://jcstudiocapilar.com',
                    'facebook' => 'Barbería Tres Amigos',
                    'address' => 'San Jose , Zapote, Quesada Duran',
                ],
            ],
            'branding_config' => [
                'assets' => [
                    'logo_transparent' => 'assets/branding/logo_transparent.png',
                    'app_icon' => 'assets/branding/app_icon.png',
                    'hero_background' => 'assets/branding/barbershop_hero_bg.png',
                    'service_placeholder' => 'assets/branding/service_placeholder.png',
                    'premium_service_placeholder' => 'assets/branding/service_placeholder_premium.png',
                    'staff_placeholder' => 'assets/branding/barber_placeholder.png',
                    'profile_placeholder' => 'assets/branding/profile_placeholder.png',
                ],
                'colors' => [
                    'primary_gold' => '#030708',
                    'primary_gold_light' => '#FFFFFF',
                    'primary_gold_dark' => '#9B6F24',
                    'background' => '#E0E0E0',
                ],
                'appearance' => [
                    'theme_preset' => 'barber_luxury',
                ],
            ],
            'feature_config' => [
                'features' => config('white_label')['features'],
            ],
        ]);

        $response = $this->getJson('/api/v1/businesses/jc-studio/app-config');

        $response->assertOk();
        $this->assertAppConfigStructure($response->json());
        $response->assertJsonPath('identity.short_name', 'JC');
        $response->assertJsonPath('identity.display_name', 'JC Studio Capilar');
        $response->assertJsonPath('assets.logo_transparent', 'assets/branding/logo_transparent.png');
        $response->assertJsonPath('assets.hero_background', 'assets/branding/barbershop_hero_bg.png');
        $response->assertJsonPath('terminology.staff_display_name', 'barbero');
        $response->assertJsonPath('appearance.theme_preset', 'barber_luxury');
        $response->assertJsonPath('appearance.theme_mode', 'dark');
        $response->assertJsonPath('contact.facebook', 'Barbería Tres Amigos');
        $response->assertJsonPath('colors.primary_gold', '#030708');
        $response->assertJsonPath('colors.primary', '#030708');
        $this->assertMatchesRegularExpression('/^#[0-9A-F]{6}$/', strtoupper($response->json('colors.input_background')));
    }

    public function test_business_seeder_writes_safe_jc_studio_branding_values(): void
    {
        $this->seed(BusinessSeeder::class);

        $business = Business::query()
            ->where('slug', 'jc-studio')
            ->firstOrFail();

        $this->assertSame('', data_get($business->branding_config, 'assets.logo_transparent'));
        $this->assertSame('', data_get($business->branding_config, 'assets.hero_background'));
        $this->assertSame('estilista', data_get($business->app_config, 'terminology.staff_display_name'));
        $this->assertSame('JC Studio Capilar', data_get($business->contact_config, 'contact.facebook'));
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

    public function test_safe_theme_generation_returns_readable_button_text_and_legacy_aliases(): void
    {
        $colors = BrandingConfig::normalizeColors([
            'primary_gold' => '#F7E6D5',
            'primary_gold_light' => '#FFF4EC',
            'primary_gold_dark' => '#D8B8A6',
        ]);

        $appearance = BrandingConfig::normalizeAppearance([
            'theme_preset' => 'not-a-real-preset',
        ]);

        $this->assertSame('#F7E6D5', $colors['primary']);
        $this->assertSame('#FFF4EC', $colors['primary_light']);
        $this->assertSame('#D8B8A6', $colors['primary_dark']);
        $this->assertSame('generic_dark', $appearance['theme_preset']);
        $this->assertSame('dark', $appearance['theme_mode']);
    }

    public function test_branding_input_derives_required_palette_fields_from_simple_inputs(): void
    {
        $branding = BrandingConfig::normalizeBrandingInput([
            'identity' => [
                'app_name' => 'JC Studio',
                'display_name' => 'JC STUDIO',
                'short_name' => 'JC',
            ],
            'colors' => [
                'primary' => '#F6F1EC',
                'accent' => '#A86E63',
                'background' => '#FAF8F5',
            ],
            'appearance' => [
                'theme_preset' => 'elegant_light',
            ],
        ], 'salon', 'jc-studio');

        $this->assertSame('elegant_light', $branding['appearance']['theme_preset']);
        $this->assertSame('light', $branding['appearance']['theme_mode']);
        $this->assertSame('#F6F1EC', $branding['colors']['primary']);
        $this->assertSame('#A86E63', $branding['colors']['accent']);
        $this->assertMatchesRegularExpression('/^#[0-9A-F]{6}$/', $branding['colors']['input_background']);
        $this->assertMatchesRegularExpression('/^#[0-9A-F]{6}$/', $branding['colors']['placeholder']);
        $this->assertMatchesRegularExpression('/^#[0-9A-F]{6}$/', $branding['colors']['disabled_text']);
    }

    public function test_tres_amigos_resolve_retains_exact_brand_assets(): void
    {
        $business = $this->createTresAmigosBusiness();

        $branding = BrandingConfig::resolveForBusiness($business);

        $this->assertSame('barberia-tres-amigos', $branding['slug']);
        $this->assertSame('BARBERÍA TRES AMIGOS', $branding['identity']['display_name']);
        $this->assertSame('assets/branding/logo_transparent.png', $branding['assets']['logo_transparent']);
        $this->assertSame('assets/branding/barbershop_hero_bg.png', $branding['assets']['hero_background']);
        $this->assertSame('barbero', $branding['terminology']['staff']);
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
