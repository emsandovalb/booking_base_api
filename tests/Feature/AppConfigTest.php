<?php

namespace Tests\Feature;

use Tests\TestCase;

class AppConfigTest extends TestCase
{
    public function test_app_config_endpoint_is_public_and_returns_expected_structure(): void
    {
        $response = $this->getJson('/api/v1/app-config');

        $response->assertOk();
        $response->assertJsonStructure([
            'identity' => [
                'app_name',
                'display_name',
                'short_name',
                'tagline',
                'subtitle',
                'location_short',
                'location_full',
                'rating',
                'review_count',
            ],
            'assets' => [
                'logo_transparent',
                'app_icon',
                'hero_background',
                'service_placeholder',
                'premium_service_placeholder',
                'staff_placeholder',
                'profile_placeholder',
            ],
            'colors' => [
                'primary_gold',
                'primary_gold_light',
                'primary_gold_dark',
            ],
            'contact' => [
                'phone',
                'whatsapp',
                'email',
                'instagram',
                'facebook',
                'website',
                'address',
            ],
            'hours' => [
                'label',
                'weekly_summary',
                'detailed_hours',
            ],
            'policies' => [
                'cancellation_window_hours',
                'cancellation_policy_text',
            ],
            'terminology' => [
                'service',
                'services',
                'appointment',
                'appointments',
                'staff',
                'staff_plural',
                'staff_display_name',
                'manager',
                'business_profile',
                'gallery',
                'reviews',
            ],
            'features' => [
                'show_staff',
                'reservation_staff_selection',
                'admin_staff_management',
                'show_gallery',
                'show_reviews',
                'show_business_profile',
                'show_admin_dashboard',
            ],
        ]);

        $response->assertJsonFragment([
            'app_name' => 'Barbería Tres Amigos',
            'short_name' => 'Tres Amigos',
            'show_staff' => true,
            'show_admin_dashboard' => true,
        ]);
    }
}
