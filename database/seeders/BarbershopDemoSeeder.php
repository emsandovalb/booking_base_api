<?php

namespace Database\Seeders;

use App\Models\Court;
use App\Models\Staff;
use App\Models\StaffRole;
use App\Models\StaffService;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class BarbershopDemoSeeder extends Seeder
{
    public function run(): void
    {
        $owner = User::updateOrCreate(
            ['email' => 'barbershop.owner@example.com'],
            [
                'name' => 'Barbershop Demo Owner',
                'password' => Hash::make('password'),
                'role' => 'admin',
            ]
        );

        $barberRole = StaffRole::updateOrCreate(
            ['slug' => 'barber'],
            [
                'name' => 'Barber',
                'description' => 'Provides grooming, haircut, and beard services.',
            ]
        );

        $shopAddress = 'Avenida Reforma 12-34, Zona 10, Guatemala City';
        $shopPhone = '+502 2234-7788';
        $shopEmail = 'hello@royalcutbarbershop.com';

        $services = [
            [
                'name' => 'Classic Haircut',
                'category' => 'Haircut',
                'duration_hours' => 1,
                'price_per_hour' => 45.00,
                'rating' => 4.9,
                'facilities' => [
                    'Precision scissor and clipper cut',
                    'Consultation before the service',
                    'Neck cleanup and styling finish',
                    'Complimentary coffee or water',
                ],
                'images' => [
                    'https://picsum.photos/seed/classic-haircut-1/1200/800',
                    'https://picsum.photos/seed/classic-haircut-2/1200/800',
                ],
            ],
            [
                'name' => 'Beard Trim',
                'category' => 'Beard',
                'duration_hours' => 1,
                'price_per_hour' => 28.00,
                'rating' => 4.8,
                'facilities' => [
                    'Beard shaping and line-up',
                    'Hot towel treatment',
                    'Beard oil finish',
                    'Razor detailing',
                ],
                'images' => [
                    'https://picsum.photos/seed/beard-trim-1/1200/800',
                    'https://picsum.photos/seed/beard-trim-2/1200/800',
                ],
            ],
            [
                'name' => 'Haircut + Beard',
                'category' => 'Combo',
                'duration_hours' => 2,
                'price_per_hour' => 65.00,
                'rating' => 4.9,
                'facilities' => [
                    'Full haircut and beard reshaping',
                    'Hot towel and face massage',
                    'Styled finish with premium products',
                    'Priority time slot',
                ],
                'images' => [
                    'https://picsum.photos/seed/haircut-beard-1/1200/800',
                    'https://picsum.photos/seed/haircut-beard-2/1200/800',
                ],
            ],
            [
                'name' => 'Premium Grooming',
                'category' => 'Premium',
                'duration_hours' => 2,
                'price_per_hour' => 95.00,
                'rating' => 5.0,
                'facilities' => [
                    'Luxury haircut and beard service',
                    'Scalp treatment and hot towel ritual',
                    'Premium styling products',
                    'Espresso, water, and lounge seating',
                ],
                'images' => [
                    'https://picsum.photos/seed/premium-grooming-1/1200/800',
                    'https://picsum.photos/seed/premium-grooming-2/1200/800',
                ],
            ],
            [
                'name' => 'Kids Haircut',
                'category' => 'Kids',
                'duration_hours' => 1,
                'price_per_hour' => 30.00,
                'rating' => 4.7,
                'facilities' => [
                    'Child-friendly chair and cape',
                    'Quick trim with gentle styling',
                    'Parent-friendly waiting area',
                    'Coloring sheets and snacks',
                ],
                'images' => [
                    'https://picsum.photos/seed/kids-haircut-1/1200/800',
                    'https://picsum.photos/seed/kids-haircut-2/1200/800',
                ],
            ],
        ];

        $serviceModels = [];
        foreach ($services as $service) {
            $serviceModels[$service['name']] = Court::updateOrCreate(
                ['name' => $service['name']],
                [
                    'owner_id' => $owner->id,
                    'address' => $shopAddress,
                    'category' => $service['category'],
                    'duration_hours' => $service['duration_hours'],
                    'contact_email' => $shopEmail,
                    'contact_phone' => $shopPhone,
                    'open_hour' => '09:00',
                    'close_hour' => '19:30',
                    'price_per_hour' => $service['price_per_hour'],
                    'rating' => $service['rating'],
                    'facilities' => $service['facilities'],
                    'images' => $service['images'],
                    'status' => 'active',
                ]
            );
        }

        $barbers = [
            [
                'name' => 'Carlos Ramirez',
                'email' => 'carlos.ramirez@royalcutbarbershop.com',
                'phone' => '+502 5550-1001',
                'bio' => 'Senior barber focused on clean fades, classic cuts, and sharp finishing details.',
                'services' => ['Classic Haircut', 'Haircut + Beard', 'Premium Grooming'],
            ],
            [
                'name' => 'Diego Morales',
                'email' => 'diego.morales@royalcutbarbershop.com',
                'phone' => '+502 5550-1002',
                'bio' => 'Beard specialist with a steady hand for line-ups, trims, and polished grooming sessions.',
                'services' => ['Beard Trim', 'Haircut + Beard', 'Premium Grooming'],
            ],
            [
                'name' => 'Luis Fernandez',
                'email' => 'luis.fernandez@royalcutbarbershop.com',
                'phone' => '+502 5550-1003',
                'bio' => 'Versatile barber known for family-friendly service and consistent classic cuts.',
                'services' => ['Classic Haircut', 'Kids Haircut'],
            ],
            [
                'name' => 'Andres Vega',
                'email' => 'andres.vega@royalcutbarbershop.com',
                'phone' => '+502 5550-1004',
                'bio' => 'Premium grooming barber with an eye for detail, styling, and upscale client experiences.',
                'services' => ['Premium Grooming', 'Beard Trim', 'Kids Haircut'],
            ],
        ];

        foreach ($barbers as $barberData) {
            $staff = Staff::updateOrCreate(
                ['name' => $barberData['name']],
                [
                    'user_id' => null,
                    'staff_role_id' => $barberRole->id,
                    'email' => $barberData['email'],
                    'phone' => $barberData['phone'],
                    'bio' => $barberData['bio'],
                    'is_active' => true,
                ]
            );

            foreach ($barberData['services'] as $index => $serviceName) {
                $service = $serviceModels[$serviceName] ?? null;
                if (!$service) {
                    continue;
                }

                StaffService::updateOrCreate(
                    [
                        'staff_id' => $staff->id,
                        'court_id' => $service->id,
                    ],
                    [
                        'is_primary' => $index === 0,
                    ]
                );
            }
        }
    }
}
