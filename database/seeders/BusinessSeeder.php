<?php

namespace Database\Seeders;

use App\Models\Business;
use Illuminate\Database\Seeder;

class BusinessSeeder extends Seeder
{
    public function run(): void
    {
        $whiteLabel = config('white_label');

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
                    'assets' => $whiteLabel['assets'],
                    'colors' => $whiteLabel['colors'],
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
                    'source' => 'BusinessSeeder',
                    'seeded_from' => 'BusinessSeeder',
                    'demo_tenant' => 'salon-aurora',
                ],
            ]
        );
    }
}
