<?php

namespace App\Support;

use App\Models\Business;
use Illuminate\Support\Arr;

class BrandingConfig
{
    public static function resolveForBusiness(Business $business): array
    {
        $preset = self::presetForBusiness($business);
        $stored = self::normalizeStoredConfig($business);

        $resolved = array_replace_recursive($preset, $stored) + [
            'business_type' => $business->business_type ?? 'generic',
            'slug' => $business->slug,
        ];

        return $resolved;
    }

    public static function presetForBusiness(?Business $business = null, ?string $businessType = null, ?string $slug = null): array
    {
        $type = self::normalizedType($businessType ?? $business?->business_type);
        $slug = self::normalizedSlug($slug ?? $business?->slug);

        if ($slug === 'barberia-tres-amigos') {
            return self::basePreset();
        }

        if ($slug === 'jc-studio') {
            return self::jcStudioPreset();
        }

        return match ($type) {
            'barbershop' => self::barbershopPreset(),
            'salon' => self::salonPreset(),
            'spa' => self::spaPreset(),
            'clinic' => self::clinicPreset(),
            default => self::genericPreset(),
        };
    }

    public static function normalizeBrandingInput(array $branding, ?string $businessType = null, ?string $slug = null): array
    {
        $preset = self::presetForBusiness(null, $businessType, $slug);
        $rawAppearance = $branding['appearance'] ?? [];
        $appearance = self::normalizeAppearance($rawAppearance);
        $colors = self::normalizeColors($branding['colors'] ?? $branding['branding'] ?? $branding);
        $assets = self::normalizeAssets($branding['assets'] ?? $branding);
        $identity = self::normalizeIdentity($branding['identity'] ?? []);
        $terminology = self::normalizeTerminology($branding['terminology'] ?? []);

        $appearance['theme_preset'] = self::normalizeThemePreset(
            $rawAppearance['theme_preset'] ?? null,
            $preset['appearance']['theme_preset'] ?? 'generic_dark'
        );
        $appearance['theme_mode'] = self::themeModeForPreset($appearance['theme_preset']);
        $colors = self::resolveSafeColors(
            $preset['colors'] ?? [],
            array_replace_recursive($preset['colors'] ?? [], $colors),
            $appearance['theme_preset']
        );

        return array_replace_recursive($preset, [
            'identity' => array_replace_recursive($preset['identity'] ?? [], $identity),
            'assets' => array_replace_recursive($preset['assets'] ?? [], $assets),
            'colors' => $colors,
            'appearance' => array_replace_recursive($preset['appearance'] ?? [], $appearance),
            'terminology' => array_replace_recursive($preset['terminology'] ?? [], $terminology),
        ]);
    }

    public static function normalizeStoredConfig(Business $business): array
    {
        $appConfig = is_array($business->app_config) ? $business->app_config : [];
        $contactConfig = is_array($business->contact_config) ? $business->contact_config : [];
        $brandingConfig = is_array($business->branding_config) ? $business->branding_config : [];
        $featureConfig = is_array($business->feature_config) ? $business->feature_config : [];

        $brandingInput = [
            'identity' => Arr::get($appConfig, 'identity', []),
            'assets' => Arr::get($brandingConfig, 'assets', []),
            'colors' => Arr::get($brandingConfig, 'colors', []),
            'appearance' => Arr::get($brandingConfig, 'appearance', []),
            'terminology' => Arr::get($appConfig, 'terminology', []),
        ];

        $normalized = self::normalizeBrandingInput(
            $brandingInput,
            $business->business_type,
            $business->slug
        );
        $storedAppearance = self::normalizeAppearance(Arr::get($brandingConfig, 'appearance', []));
        if (Arr::get($brandingConfig, 'appearance', []) === []) {
            $storedAppearance = [];
        }

        return [
            'identity' => array_replace_recursive(
                $normalized['identity'] ?? [],
                Arr::get($appConfig, 'identity', [])
            ),
            'assets' => array_replace_recursive(
                $normalized['assets'] ?? [],
                Arr::get($brandingConfig, 'assets', [])
            ),
            'colors' => array_replace_recursive(
                $normalized['colors'] ?? [],
                self::normalizeColors(Arr::get($brandingConfig, 'colors', []))
            ),
            'appearance' => array_replace_recursive(
                $normalized['appearance'] ?? [],
                $storedAppearance
            ),
            'contact' => Arr::get($contactConfig, 'contact', []),
            'hours' => Arr::get($appConfig, 'hours', []),
            'policies' => Arr::get($appConfig, 'policies', []),
            'terminology' => array_replace_recursive(
                $normalized['terminology'] ?? [],
                Arr::get($appConfig, 'terminology', [])
            ),
            'features' => Arr::get($featureConfig, 'features', []),
        ];
    }

    public static function basePreset(): array
    {
        return config('white_label');
    }

    public static function jcStudioPreset(): array
    {
        return array_replace_recursive(self::salonPreset(), [
            'identity' => [
                'app_name' => 'JC Studio',
                'display_name' => 'JC STUDIO',
                'short_name' => 'JC',
                'tagline' => 'Belleza, estilo y cuidado personal',
                'subtitle' => 'Tu momento, tu estilo',
            ],
            'assets' => [
                'logo_transparent' => '',
                'logo_dark' => '',
                'logo_light' => '',
                'hero_background' => '',
                'login_background' => '',
                'onboarding_background' => '',
                'service_placeholder' => '',
                'premium_service_placeholder' => '',
                'staff_placeholder' => '',
                'profile_placeholder' => '',
                'app_icon' => '',
            ],
            'colors' => [
                'primary' => '#C98F86',
                'primary_light' => '#E7C9C3',
                'primary_dark' => '#8D5A52',
                'secondary' => '#EFE0DC',
                'accent' => '#A86E63',
                'background' => '#FBF7F3',
                'surface' => '#F4EDE8',
                'card' => '#FFFFFF',
                'border' => '#DCC9C2',
                'text_primary' => '#1F1614',
                'text_secondary' => '#6D5751',
                'text_on_primary' => '#1F1614',
                'success' => '#1D9A73',
                'warning' => '#C98B2C',
                'danger' => '#C94D5A',
                'primary_gold' => '#C98F86',
                'primary_gold_light' => '#E7C9C3',
                'primary_gold_dark' => '#8D5A52',
            ],
            'appearance' => [
                'theme_mode' => 'light',
                'theme_preset' => 'elegant_light',
                'card_radius' => 24,
                'input_radius' => 18,
                'button_radius' => 18,
                'use_cinematic_backgrounds' => false,
                'use_logo_glow' => false,
                'use_heavy_blur' => false,
            ],
            'terminology' => [
                'business' => 'salón',
                'business_profile' => 'perfil del salón',
                'service' => 'servicio',
                'services' => 'servicios',
                'appointment' => 'cita',
                'appointments' => 'citas',
                'staff' => 'estilista',
                'staff_plural' => 'estilistas',
                'staff_display_name' => 'estilista',
                'manager' => 'administradora',
                'gallery' => 'galería',
                'reviews' => 'opiniones',
            ],
        ]);
    }

    public static function genericPreset(): array
    {
        return [
            'identity' => [
                'app_name' => 'Bemuss',
                'display_name' => 'BEMUSS',
                'short_name' => 'Bemuss',
                'tagline' => 'Reservas y experiencias premium',
                'subtitle' => 'Tu experiencia, tu marca',
                'location_short' => '',
                'location_full' => '',
                'rating' => 4.8,
                'review_count' => 0,
            ],
            'assets' => [
                'logo_transparent' => '',
                'logo_dark' => '',
                'logo_light' => '',
                'hero_background' => '',
                'login_background' => '',
                'onboarding_background' => '',
                'service_placeholder' => '',
                'premium_service_placeholder' => '',
                'staff_placeholder' => '',
                'profile_placeholder' => '',
                'app_icon' => '',
            ],
            'colors' => [
                'primary' => '#4C8BF5',
                'primary_light' => '#93B8FF',
                'primary_dark' => '#1E4FA8',
                'secondary' => '#B7CCFF',
                'accent' => '#3D67B4',
                'background' => '#0B1020',
                'surface' => '#151C31',
                'card' => '#10172A',
                'border' => '#22FFFFFF',
                'text_primary' => '#FFFFFF',
                'text_secondary' => '#B9C3DD',
                'text_on_primary' => '#0B1020',
                'success' => '#04B155',
                'warning' => '#F4B740',
                'danger' => '#EB3B5A',
                'primary_gold' => '#4C8BF5',
                'primary_gold_light' => '#93B8FF',
                'primary_gold_dark' => '#1E4FA8',
            ],
            'appearance' => [
                'theme_mode' => 'dark',
                'theme_preset' => 'generic_dark',
                'card_radius' => 24,
                'input_radius' => 18,
                'button_radius' => 18,
                'use_cinematic_backgrounds' => true,
                'use_logo_glow' => true,
                'use_heavy_blur' => false,
            ],
            'contact' => [
                'phone' => '',
                'whatsapp' => '',
                'email' => '',
                'instagram' => '',
                'facebook' => '',
                'website' => '',
                'address' => '',
            ],
            'hours' => [
                'label' => 'Horario',
                'weekly_summary' => 'Horario configurable por tenant',
                'detailed_hours' => ['Horario configurable por tenant'],
            ],
            'policies' => [
                'cancellation_window_hours' => 0,
                'cancellation_policy_text' => 'Las políticas dependen del negocio.',
            ],
            'terminology' => [
                'business' => 'negocio',
                'business_profile' => 'perfil del negocio',
                'service' => 'servicio',
                'services' => 'servicios',
                'appointment' => 'cita',
                'appointments' => 'citas',
                'staff' => 'personal',
                'staff_plural' => 'personal',
                'staff_display_name' => 'personal',
                'manager' => 'administrador',
                'gallery' => 'galería',
                'reviews' => 'reseñas',
            ],
            'features' => [
                'show_staff' => true,
                'reservation_staff_selection' => true,
                'admin_staff_management' => true,
                'show_gallery' => true,
                'show_reviews' => true,
                'show_business_profile' => true,
                'show_admin_dashboard' => true,
            ],
        ];
    }

    public static function barbershopPreset(): array
    {
        return array_replace_recursive(self::genericPreset(), [
            'identity' => [
                'app_name' => 'Barbershop',
                'display_name' => 'BARBERSHOP',
                'short_name' => 'Barbershop',
                'tagline' => 'Cortes, barba y cuidado personal',
                'subtitle' => 'Tu estilo, tu experiencia',
            ],
            'colors' => [
                'primary' => '#D4A84F',
                'primary_light' => '#E8C36A',
                'primary_dark' => '#9B6F24',
                'secondary' => '#E8D8B8',
                'accent' => '#B77A3E',
                'background' => '#090909',
                'surface' => '#1A1512',
                'card' => '#120E0B',
                'border' => '#22FFFFFF',
                'text_secondary' => '#B9AFA5',
                'text_on_primary' => '#090909',
                'primary_gold' => '#D4A84F',
                'primary_gold_light' => '#E8C36A',
                'primary_gold_dark' => '#9B6F24',
            ],
            'terminology' => [
                'business_profile' => 'perfil del negocio',
                'staff' => 'barbero',
                'staff_plural' => 'barberos',
                'staff_display_name' => 'barbero',
            ],
        ]);
    }

    public static function salonPreset(): array
    {
        return array_replace_recursive(self::genericPreset(), [
            'identity' => [
                'app_name' => 'Salón',
                'display_name' => 'SALÓN',
                'short_name' => 'Salón',
                'tagline' => 'Belleza y cuidado personal',
                'subtitle' => 'Tu momento, tu estilo',
            ],
            'colors' => [
                'primary' => '#E6B7A9',
                'primary_light' => '#F3D4CC',
                'primary_dark' => '#A66A5E',
                'secondary' => '#F4DDD7',
                'accent' => '#C87D6E',
                'background' => '#120F14',
                'surface' => '#201A21',
                'card' => '#171218',
                'border' => '#26FFFFFF',
                'text_secondary' => '#E1CFC9',
                'text_on_primary' => '#120F14',
                'primary_gold' => '#E6B7A9',
                'primary_gold_light' => '#F3D4CC',
                'primary_gold_dark' => '#A66A5E',
            ],
            'terminology' => [
                'service' => 'tratamiento',
                'services' => 'tratamientos',
                'staff' => 'estilista',
                'staff_plural' => 'estilistas',
                'staff_display_name' => 'estilista',
                'manager' => 'administradora',
                'business_profile' => 'perfil del salón',
            ],
        ]);
    }

    public static function spaPreset(): array
    {
        return array_replace_recursive(self::genericPreset(), [
            'identity' => [
                'app_name' => 'Spa',
                'display_name' => 'SPA',
                'short_name' => 'Spa',
                'tagline' => 'Bienestar, pausa y renovación',
                'subtitle' => 'Tu espacio de descanso',
            ],
            'colors' => [
                'primary' => '#79C7B8',
                'primary_light' => '#A8DDD4',
                'primary_dark' => '#2D7A70',
                'secondary' => '#CFECE7',
                'accent' => '#4EA295',
                'background' => '#071719',
                'surface' => '#102528',
                'card' => '#0D1F22',
                'border' => '#22FFFFFF',
                'text_secondary' => '#C1DED9',
                'primary_gold' => '#79C7B8',
                'primary_gold_light' => '#A8DDD4',
                'primary_gold_dark' => '#2D7A70',
            ],
            'terminology' => [
                'service' => 'tratamiento',
                'services' => 'tratamientos',
                'appointment' => 'sesión',
                'appointments' => 'sesiones',
                'staff' => 'terapeuta',
                'staff_plural' => 'terapeutas',
                'staff_display_name' => 'terapeuta',
                'manager' => 'administrador',
                'business_profile' => 'perfil del spa',
            ],
        ]);
    }

    public static function clinicPreset(): array
    {
        return array_replace_recursive(self::genericPreset(), [
            'identity' => [
                'app_name' => 'Clínica',
                'display_name' => 'CLÍNICA',
                'short_name' => 'Clínica',
                'tagline' => 'Atención profesional y confiable',
                'subtitle' => 'Cuidado con respaldo',
            ],
            'colors' => [
                'primary' => '#6BA9FF',
                'primary_light' => '#9FC5FF',
                'primary_dark' => '#2C68B8',
                'secondary' => '#D8E8FF',
                'accent' => '#4C84D0',
                'background' => '#081425',
                'surface' => '#111C31',
                'card' => '#0F1829',
                'border' => '#22FFFFFF',
                'text_secondary' => '#C0D1E8',
                'primary_gold' => '#6BA9FF',
                'primary_gold_light' => '#9FC5FF',
                'primary_gold_dark' => '#2C68B8',
            ],
            'terminology' => [
                'service' => 'consulta',
                'services' => 'consultas',
                'appointment' => 'cita',
                'appointments' => 'citas',
                'staff' => 'profesional',
                'staff_plural' => 'profesionales',
                'staff_display_name' => 'profesional',
                'manager' => 'coordinador',
                'business_profile' => 'perfil de la clínica',
            ],
        ]);
    }

    public static function normalizeIdentity(array $identity): array
    {
        return array_filter([
            'app_name' => self::stringOrNull($identity['app_name'] ?? null),
            'display_name' => self::stringOrNull($identity['display_name'] ?? null),
            'short_name' => self::stringOrNull($identity['short_name'] ?? null),
            'tagline' => self::stringOrNull($identity['tagline'] ?? null),
            'subtitle' => self::stringOrNull($identity['subtitle'] ?? null),
            'location_short' => self::stringOrNull($identity['location_short'] ?? null),
            'location_full' => self::stringOrNull($identity['location_full'] ?? null),
            'legal_name' => self::stringOrNull($identity['legal_name'] ?? null),
            'rating' => self::numberOrNull($identity['rating'] ?? null),
            'review_count' => self::integerOrNull($identity['review_count'] ?? null),
        ], static fn ($value) => $value !== null);
    }

    public static function normalizeAssets(array $assets): array
    {
        return array_filter([
            'logo_transparent' => self::stringOrNull($assets['logo_transparent'] ?? null),
            'logo_dark' => self::stringOrNull($assets['logo_dark'] ?? null),
            'logo_light' => self::stringOrNull($assets['logo_light'] ?? null),
            'hero_background' => self::stringOrNull($assets['hero_background'] ?? null),
            'login_background' => self::stringOrNull($assets['login_background'] ?? null),
            'onboarding_background' => self::stringOrNull($assets['onboarding_background'] ?? null),
            'service_placeholder' => self::stringOrNull($assets['service_placeholder'] ?? null),
            'premium_service_placeholder' => self::stringOrNull($assets['premium_service_placeholder'] ?? null),
            'staff_placeholder' => self::stringOrNull($assets['staff_placeholder'] ?? null),
            'profile_placeholder' => self::stringOrNull($assets['profile_placeholder'] ?? null),
            'app_icon' => self::stringOrNull($assets['app_icon'] ?? null),
        ], static fn ($value) => $value !== null);
    }

    public static function normalizeColors(array $colors): array
    {
        $legacyPrimary = $colors['primary_gold'] ?? null;
        $legacyLight = $colors['primary_gold_light'] ?? null;
        $legacyDark = $colors['primary_gold_dark'] ?? null;

        $primary = self::colorOrNull($colors['primary'] ?? $legacyPrimary);
        $primaryLight = self::colorOrNull($colors['primary_light'] ?? $legacyLight);
        $primaryDark = self::colorOrNull($colors['primary_dark'] ?? $legacyDark);

        return array_filter([
            'primary' => $primary,
            'primary_light' => $primaryLight,
            'primary_dark' => $primaryDark,
            'secondary' => self::colorOrNull($colors['secondary'] ?? null),
            'accent' => self::colorOrNull($colors['accent'] ?? null),
            'background' => self::colorOrNull($colors['background'] ?? null),
            'surface' => self::colorOrNull($colors['surface'] ?? null),
            'card' => self::colorOrNull($colors['card'] ?? null),
            'border' => self::colorOrNull($colors['border'] ?? null),
            'text_primary' => self::colorOrNull($colors['text_primary'] ?? null),
            'text_secondary' => self::colorOrNull($colors['text_secondary'] ?? null),
            'text_on_primary' => self::colorOrNull($colors['text_on_primary'] ?? null),
            'success' => self::colorOrNull($colors['success'] ?? null),
            'warning' => self::colorOrNull($colors['warning'] ?? null),
            'danger' => self::colorOrNull($colors['danger'] ?? null),
            'primary_gold' => $primary,
            'primary_gold_light' => $primaryLight,
            'primary_gold_dark' => $primaryDark,
        ], static fn ($value) => $value !== null);
    }

    public static function normalizeAppearance(array $appearance): array
    {
        $themePreset = self::normalizeThemePreset(
            $appearance['theme_preset'] ?? null,
            'generic_dark'
        );
        $themeMode = self::themeModeForPreset($themePreset);

        return [
            'theme_mode' => $themeMode,
            'theme_preset' => $themePreset,
            'card_radius' => self::boundedNumber($appearance['card_radius'] ?? null, 0, 64, 24),
            'input_radius' => self::boundedNumber($appearance['input_radius'] ?? null, 0, 64, 18),
            'button_radius' => self::boundedNumber($appearance['button_radius'] ?? null, 0, 64, 18),
            'use_cinematic_backgrounds' => self::boolean($appearance['use_cinematic_backgrounds'] ?? true),
            'use_logo_glow' => self::boolean($appearance['use_logo_glow'] ?? true),
            'use_heavy_blur' => self::boolean($appearance['use_heavy_blur'] ?? false),
        ];
    }

    public static function normalizeTerminology(array $terminology): array
    {
        return array_filter([
            'business' => self::stringOrNull($terminology['business'] ?? null),
            'business_profile' => self::stringOrNull($terminology['business_profile'] ?? null),
            'service' => self::stringOrNull($terminology['service'] ?? null),
            'services' => self::stringOrNull($terminology['services'] ?? null),
            'appointment' => self::stringOrNull($terminology['appointment'] ?? null),
            'appointments' => self::stringOrNull($terminology['appointments'] ?? null),
            'staff' => self::stringOrNull($terminology['staff'] ?? null),
            'staff_plural' => self::stringOrNull($terminology['staff_plural'] ?? null),
            'staff_display_name' => self::stringOrNull($terminology['staff_display_name'] ?? null),
            'manager' => self::stringOrNull($terminology['manager'] ?? null),
            'gallery' => self::stringOrNull($terminology['gallery'] ?? null),
            'reviews' => self::stringOrNull($terminology['reviews'] ?? null),
        ], static fn ($value) => $value !== null);
    }

    public static function normalizedType(?string $businessType): string
    {
        $type = strtolower(trim((string) $businessType));
        return $type !== '' ? $type : 'generic';
    }

    public static function normalizedSlug(?string $slug): string
    {
        return strtolower(trim((string) $slug));
    }

    public static function normalizeThemePreset(mixed $preset, string $fallback = 'generic_dark'): string
    {
        $value = strtolower(trim((string) $preset));
        return in_array($value, self::supportedThemePresets(), true) ? $value : $fallback;
    }

    public static function supportedThemePresets(): array
    {
        return [
            'elegant_dark',
            'elegant_light',
            'salon_rose',
            'natural',
            'corporate',
            'barber_luxury',
            'clinic_clean',
            'generic_dark',
            'generic_light',
        ];
    }

    public static function themePresetLabels(): array
    {
        return [
            'elegant_light' => 'Elegante claro',
            'elegant_dark' => 'Elegante oscuro',
            'salon_rose' => 'Salón Rose',
            'natural' => 'Natural',
            'corporate' => 'Moderno / Corporativo',
            'barber_luxury' => 'Barbería Luxury',
            'clinic_clean' => 'Clínica Clean',
        ];
    }

    public static function themeModeForPreset(string $themePreset): string
    {
        return in_array($themePreset, ['elegant_light', 'generic_light'], true)
            ? 'light'
            : 'dark';
    }

    private static function resolveSafeColors(array $presetColors, array $inputColors, string $themePreset): array
    {
        $preset = self::themePresetColors($themePreset, $presetColors);
        $primary = self::colorOrNull($inputColors['primary'] ?? $inputColors['primary_gold'] ?? null) ?? $preset['primary'];
        $accent = self::colorOrNull($inputColors['accent'] ?? null) ?? $preset['accent'];
        $background = self::colorOrNull($inputColors['background'] ?? null) ?? $preset['background'];
        $surface = self::colorOrNull($inputColors['surface'] ?? null) ?? self::mixColor($background, self::bestTextFor($background), 0.08);
        $card = self::colorOrNull($inputColors['card'] ?? null) ?? self::mixColor($surface, self::bestTextFor($surface), 0.06);
        $border = self::colorOrNull($inputColors['border'] ?? null) ?? self::borderColorFor($background, $surface);
        $textPrimary = self::colorOrNull($inputColors['text_primary'] ?? null) ?? self::bestTextFor($background);
        $textSecondary = self::colorOrNull($inputColors['text_secondary'] ?? null) ?? self::secondaryTextFor($textPrimary, $background);
        $textOnPrimary = self::colorOrNull($inputColors['text_on_primary'] ?? null) ?? self::bestTextFor($primary);
        if (self::contrastRatio($textOnPrimary, $primary) < 4.5) {
            $textOnPrimary = self::bestTextFor($primary);
        }

        $primaryLight = self::colorOrNull($inputColors['primary_light'] ?? $inputColors['primary_gold_light'] ?? null)
            ?? self::mixColor($primary, '#FFFFFF', 0.22);
        $primaryDark = self::colorOrNull($inputColors['primary_dark'] ?? $inputColors['primary_gold_dark'] ?? null)
            ?? self::mixColor($primary, '#000000', 0.20);
        $inputBackground = self::colorOrNull($inputColors['input_background'] ?? null)
            ?? self::mixColor($surface, $background, 0.38);
        $placeholder = self::colorOrNull($inputColors['placeholder'] ?? null)
            ?? self::mixColor($textSecondary, $background, 0.34);
        $disabledText = self::colorOrNull($inputColors['disabled_text'] ?? null)
            ?? self::mixColor($textSecondary, $background, 0.52);
        $disabledBackground = self::colorOrNull($inputColors['disabled_background'] ?? null)
            ?? self::mixColor($surface, $background, 0.24);

        return [
            'primary' => $primary,
            'primary_light' => $primaryLight,
            'primary_dark' => $primaryDark,
            'secondary' => self::colorOrNull($inputColors['secondary'] ?? null) ?? $preset['secondary'],
            'accent' => $accent,
            'background' => $background,
            'surface' => $surface,
            'card' => $card,
            'border' => $border,
            'text_primary' => $textPrimary,
            'text_secondary' => $textSecondary,
            'text_on_primary' => $textOnPrimary,
            'input_background' => $inputBackground,
            'placeholder' => $placeholder,
            'disabled_text' => $disabledText,
            'disabled_background' => $disabledBackground,
            'success' => self::colorOrNull($inputColors['success'] ?? null) ?? $preset['success'],
            'warning' => self::colorOrNull($inputColors['warning'] ?? null) ?? $preset['warning'],
            'danger' => self::colorOrNull($inputColors['danger'] ?? null) ?? $preset['danger'],
            'primary_gold' => $primary,
            'primary_gold_light' => $primaryLight,
            'primary_gold_dark' => $primaryDark,
        ];
    }

    private static function themePresetColors(string $themePreset, array $fallback): array
    {
        $defaults = match ($themePreset) {
            'elegant_light', 'generic_light' => [
                'primary' => '#C98F86',
                'secondary' => '#E7D7D2',
                'accent' => '#A86E63',
                'background' => '#FBF7F3',
                'surface' => '#F4EDE8',
                'card' => '#FFFFFF',
                'border' => '#DCC9C2',
                'text_primary' => '#1F1614',
                'text_secondary' => '#6D5751',
                'text_on_primary' => '#1F1614',
                'success' => '#1D9A73',
                'warning' => '#C98B2C',
                'danger' => '#C94D5A',
            ],
            'salon_rose' => [
                'primary' => '#E6B7A9',
                'secondary' => '#F4DDD7',
                'accent' => '#C87D6E',
                'background' => '#120F14',
                'surface' => '#201A21',
                'card' => '#171218',
                'border' => '#26FFFFFF',
                'text_primary' => '#FFFFFF',
                'text_secondary' => '#E1CFC9',
                'text_on_primary' => '#120F14',
                'success' => '#1D9A73',
                'warning' => '#F0A63B',
                'danger' => '#DE5770',
            ],
            'natural' => [
                'primary' => '#79C7B8',
                'secondary' => '#CFECE7',
                'accent' => '#4EA295',
                'background' => '#071719',
                'surface' => '#102528',
                'card' => '#0D1F22',
                'border' => '#22FFFFFF',
                'text_primary' => '#FFFFFF',
                'text_secondary' => '#C1DED9',
                'text_on_primary' => '#071719',
                'success' => '#1D9A73',
                'warning' => '#F0A63B',
                'danger' => '#DE5770',
            ],
            'corporate', 'clinic_clean' => [
                'primary' => '#6BA9FF',
                'secondary' => '#D8E8FF',
                'accent' => '#4C84D0',
                'background' => '#081425',
                'surface' => '#111C31',
                'card' => '#0F1829',
                'border' => '#22FFFFFF',
                'text_primary' => '#FFFFFF',
                'text_secondary' => '#C0D1E8',
                'text_on_primary' => '#081425',
                'success' => '#1D9A73',
                'warning' => '#F0A63B',
                'danger' => '#DE5770',
            ],
            'barber_luxury', 'elegant_dark', 'generic_dark' => [
                'primary' => '#D4A84F',
                'secondary' => '#E8D8B8',
                'accent' => '#B77A3E',
                'background' => '#090909',
                'surface' => '#1A1512',
                'card' => '#120E0B',
                'border' => '#22FFFFFF',
                'text_primary' => '#FFFFFF',
                'text_secondary' => '#B9AFA5',
                'text_on_primary' => '#090909',
                'success' => '#1D9A73',
                'warning' => '#F0A63B',
                'danger' => '#DE5770',
            ],
            default => $fallback,
        };

        return array_replace($fallback, $defaults);
    }

    private static function bestTextFor(string $background): string
    {
        return self::luminance($background) > 0.5 ? '#000000' : '#FFFFFF';
    }

    private static function secondaryTextFor(string $textPrimary, string $background): string
    {
        $base = self::bestTextFor($background);
        return $textPrimary === '#FFFFFF'
            ? self::mixColor($base, '#FFFFFF', 0.72)
            : self::mixColor($base, '#000000', 0.58);
    }

    private static function borderColorFor(string $background, string $surface): string
    {
        $text = self::bestTextFor($background);
        return self::mixColor($surface, $text, 0.14);
    }

    private static function mixColor(string $colorA, string $colorB, float $ratio): string
    {
        $ratio = max(0.0, min(1.0, $ratio));
        $a = self::hexToRgb($colorA);
        $b = self::hexToRgb($colorB);

        return sprintf(
            '#%02X%02X%02X',
            (int) round($a[0] * (1 - $ratio) + $b[0] * $ratio),
            (int) round($a[1] * (1 - $ratio) + $b[1] * $ratio),
            (int) round($a[2] * (1 - $ratio) + $b[2] * $ratio)
        );
    }

    private static function hexToRgb(string $hex): array
    {
        $normalized = ltrim(strtoupper($hex), '#');
        if (strlen($normalized) === 8) {
            $normalized = substr($normalized, 2);
        }
        if (strlen($normalized) === 3) {
            $normalized = implode('', array_map(static fn ($char) => $char . $char, str_split($normalized)));
        }

        return [
            hexdec(substr($normalized, 0, 2)),
            hexdec(substr($normalized, 2, 2)),
            hexdec(substr($normalized, 4, 2)),
        ];
    }

    private static function luminance(string $hex): float
    {
        [$r, $g, $b] = self::hexToRgb($hex);
        $transform = static function (int $channel): float {
            $value = $channel / 255;
            return $value <= 0.03928
                ? $value / 12.92
                : pow(($value + 0.055) / 1.055, 2.4);
        };

        return (0.2126 * $transform($r)) + (0.7152 * $transform($g)) + (0.0722 * $transform($b));
    }

    private static function contrastRatio(string $foreground, string $background): float
    {
        $l1 = self::luminance($foreground);
        $l2 = self::luminance($background);
        $lighter = max($l1, $l2);
        $darker = min($l1, $l2);

        return ($lighter + 0.05) / ($darker + 0.05);
    }

    private static function stringOrNull(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $text = trim((string) $value);
        return $text === '' ? null : $text;
    }

    private static function integerOrNull(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_int($value)) {
            return $value;
        }

        if (is_numeric($value)) {
            return (int) $value;
        }

        return null;
    }

    private static function numberOrNull(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_int($value) || is_float($value)) {
            return (float) $value;
        }

        if (is_numeric($value)) {
            return (float) $value;
        }

        return null;
    }

    private static function boundedNumber(mixed $value, int $min, int $max, int $fallback): int
    {
        $parsed = self::integerOrNull($value) ?? $fallback;
        return max($min, min($max, $parsed));
    }

    private static function boolean(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        $text = strtolower(trim((string) $value));
        return in_array($text, ['1', 'true', 'yes', 'on'], true);
    }

    private static function colorOrNull(mixed $value): ?string
    {
        $text = self::stringOrNull($value);
        if ($text === null) {
            return null;
        }

        if (preg_match('/^#?(?:[0-9a-fA-F]{3}|[0-9a-fA-F]{6}|[0-9a-fA-F]{8})$/', $text) !== 1) {
            return null;
        }

        return str_starts_with($text, '#') ? strtoupper($text) : '#' . strtoupper($text);
    }
}
