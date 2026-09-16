<?php

namespace Database\Seeders;

use App\Models\Business;
use App\Support\BrandingConfig;
use Illuminate\Database\Seeder;

class BusinessSeeder extends Seeder
{
    public function run(): void
    {
        $whiteLabel = config('white_label');
        $salonPreset = BrandingConfig::salonPreset();
        $jcStudioPreset = BrandingConfig::jcStudioPreset();

        Business::updateOrCreate(
            ['slug' => 'barberia-tres-amigos'],
            [
                'name' => 'Barbería Tres Amigos',
                'legal_name' => $whiteLabel['identity']['legal_name'] ?? 'Barbería Tres Amigos S.A.',
                'business_type' => 'barbershop',
                'status' => 'active',
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
                    'identity' => $whiteLabel['identity'],
                    'assets' => $whiteLabel['assets'],
                    'colors' => $whiteLabel['colors'],
                    'appearance' => $whiteLabel['appearance'],
                    'terminology' => $whiteLabel['terminology'],
                ],
                'feature_config' => [
                    'features' => $whiteLabel['features'],
                ],
                'metadata' => [
                    'source' => 'config/white_label.php',
                    'seeded_from' => 'BusinessSeeder',
                ],
            ]
        );

        Business::updateOrCreate(
            ['slug' => 'jc-studio'],
            [
                'name' => 'JC Studio',
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
                    'hours' => $whiteLabel['hours'],
                    'policies' => [
                        'cancellation_window_hours' => 4,
                        'cancellation_policy_text' => 'Podés cancelar o reprogramar hasta 4 horas antes de tu cita.',
                    ],
                    'terminology' => $jcStudioPreset['terminology'],
                ],
                'contact_config' => [
                    'contact' => [
                        'phone' => '+506 8888-3366',
                        'whatsapp' => '+506 8888-3366',
                        'email' => 'hola@jcstudiocapilar.com',
                        'instagram' => '@JC Studio capilar',
                        'website' => 'https://jcstudiocapilar.com',
                        'facebook' => 'JC Studio Capilar',
                        'address' => 'San Jose , Zapote, Quesada Duran',
                    ],
                ],
                'branding_config' => [
                    'identity' => [
                        'app_name' => 'JC Studio',
                        'display_name' => 'JC Studio Capilar',
                        'short_name' => 'JC',
                        'tagline' => 'Cortes, asesorias y experiencias premium',
                        'subtitle' => 'Tu estilo, tu experiencia',
                    ],
                    'assets' => $jcStudioPreset['assets'],
                    'colors' => $jcStudioPreset['colors'],
                    'appearance' => $jcStudioPreset['appearance'],
                    'terminology' => $jcStudioPreset['terminology'],
                ],
                'feature_config' => [
                    'features' => $whiteLabel['features'],
                ],
                'metadata' => [
                    'source' => 'super-admin',
                    'managed_by' => 1,
                ],
            ]
        );

        Business::updateOrCreate(
            ['slug' => 'salon-aurora'],
            [
                'name' => 'Salón Aurora',
                'business_type' => 'salon',
                'status' => 'active',
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
                    'terminology' => $salonPreset['terminology'],
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
                    'identity' => $salonPreset['identity'],
                    'assets' => $salonPreset['assets'],
                    'colors' => $salonPreset['colors'],
                    'appearance' => $salonPreset['appearance'],
                    'terminology' => $salonPreset['terminology'],
                ],
                'feature_config' => [
                    'features' => $whiteLabel['features'],
                ],
                'metadata' => [
                    'source' => 'BusinessSeeder',
                    'seeded_from' => 'BusinessSeeder',
                    'demo_tenant' => 'salon-aurora',
                ],
            ]
        );
    }
}
