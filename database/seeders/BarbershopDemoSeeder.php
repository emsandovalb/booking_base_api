<?php

namespace Database\Seeders;

use App\Models\Court;
use App\Models\Business;
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
                'description' => 'Provides haircut, beard, and grooming services.',
            ]
        );

        $consultantRole = StaffRole::updateOrCreate(
            ['slug' => 'consultant'],
            [
                'name' => 'Consultant',
                'description' => 'Provides customer support and style guidance.',
            ]
        );

        $tresAmigosBusiness = Business::query()
            ->where('slug', 'barberia-tres-amigos')
            ->first();
        $salonAuroraBusiness = Business::query()
            ->where('slug', 'salon-aurora')
            ->first();

        $shopAddress = 'Puntarenas, El Roble, Costa Rica';
        $shopPhone = '+506 8888-3366';
        $shopEmail = 'hola@barberiatresamigos.com';
        $businessHoursNote = "Sunday: Closed\nMonday: 10:00 AM - 7:00 PM\nTuesday: 10:00 AM - 12:00 PM, 2:00 PM - 8:00 PM\nWednesday: 10:00 AM - 12:00 PM, 2:00 PM - 8:00 PM\nThursday: 10:00 AM - 12:00 PM, 2:00 PM - 8:00 PM\nFriday: 10:00 AM - 7:00 PM\nSaturday: 10:00 AM - 7:00 PM";

        $services = [
            [
                'name' => 'Corte de cabello',
                'category' => 'haircut',
                'description' => 'Corte de cabello personalizado según tu estilo y tipo de cabello. Incluye asesoría de imagen, degradado profesional, perfilado y acabado con productos de calidad.',
                'duration_minutes' => 45,
                'duration_hours' => 1,
                'price_per_hour' => 6000.00,
                'rating' => 4.9,
                'facilities' => [
                    'Asesoría de imagen',
                    'Degradado profesional',
                    'Perfilado de contornos',
                    'Acabado con productos de calidad',
                ],
                'images' => [
                    'assets/branding/service_placeholder.png',
                ],
            ],
            [
                'name' => 'Recorte de barba',
                'category' => 'beard',
                'description' => 'Perfilado y definición de barba utilizando máquina y navaja para lograr líneas limpias y un acabado profesional. Incluye aplicación de productos para el cuidado de la barba.',
                'duration_minutes' => 30,
                'duration_hours' => 1,
                'price_per_hour' => 5000.00,
                'rating' => 4.8,
                'facilities' => [
                    'Perfilado con máquina y navaja',
                    'Líneas limpias y definidas',
                    'Productos para cuidado de la barba',
                ],
                'images' => [
                    'assets/branding/service_placeholder_premium.png',
                ],
            ],
            [
                'name' => 'Corte y Barba',
                'category' => 'combo',
                'description' => 'Servicio completo que incluye corte de cabello personalizado y arreglo de barba. Se realiza perfilado, definición de líneas y aplicación de productos para un acabado limpio y profesional.',
                'duration_minutes' => 60,
                'duration_hours' => 1,
                'price_per_hour' => 9000.00,
                'rating' => 4.9,
                'facilities' => [
                    'Corte personalizado',
                    'Arreglo y perfilado de barba',
                    'Definición de líneas',
                    'Acabado profesional',
                ],
                'images' => [
                    'assets/branding/service_placeholder.png',
                ],
            ],
            [
                'name' => 'Corte a Tijera / Cabello Largo',
                'category' => 'haircut',
                'description' => 'Servicio especializado para cabello largo o estilos que requieren trabajo detallado con tijera. Incluye asesoría de estilo, texturizado y acabado con productos profesionales para mantener movimiento y forma natural.',
                'duration_minutes' => 60,
                'duration_hours' => 1,
                'price_per_hour' => 7000.00,
                'rating' => 4.9,
                'facilities' => [
                    'Asesoría de estilo',
                    'Texturizado con tijera',
                    'Acabado con productos profesionales',
                ],
                'images' => [
                    'assets/branding/home_hero.png',
                ],
            ],
            [
                'name' => 'Experiencia Premium Completa (Corte + Barba + Spa Facial + Bebida)',
                'category' => 'premium',
                'description' => 'Servicio premium de barbería que incluye corte de cabello personalizado, arreglo y perfilado de barba, limpieza facial profunda con exfoliación, mascarilla negra, vapor ozono, masaje relajante facial y una bebida de cortesía no alcohólica. Finaliza con aplicación de productos profesionales para cabello y barba.',
                'duration_minutes' => 105,
                'duration_hours' => 2,
                'price_per_hour' => 24000.00,
                'rating' => 5.0,
                'facilities' => [
                    'Spa facial profundo',
                    'Mascarilla negra',
                    'Vapor ozono',
                    'Bebida de cortesía sin alcohol',
                ],
                'images' => [
                    'assets/branding/service_placeholder_premium.png',
                ],
            ],
            [
                'name' => 'Experiencia Premium (Corte + Spa Facial + Bebida)',
                'category' => 'premium',
                'description' => 'Experiencia completa de barbería diseñada para relajarte y mejorar tu imagen. Incluye corte de cabello personalizado, limpieza facial con exfoliación, aplicación de mascarilla negra, vapor ozono para abrir los poros, masaje relajante facial y una bebida de cortesía no alcohólica. Finaliza con productos profesionales para el cuidado del cabello y la piel.',
                'duration_minutes' => 90,
                'duration_hours' => 2,
                'price_per_hour' => 20000.00,
                'rating' => 4.9,
                'facilities' => [
                    'Limpieza facial con exfoliación',
                    'Mascarilla negra',
                    'Masaje facial relajante',
                    'Bebida de cortesía sin alcohol',
                ],
                'images' => [
                    'assets/branding/home_hero.png',
                ],
            ],
            [
                'name' => 'Perfilado de Cejas',
                'category' => 'grooming',
                'description' => 'Perfilado y limpieza de cejas utilizando navaja para mejorar la simetría del rostro y lograr un acabado limpio y natural.',
                'duration_minutes' => 10,
                'duration_hours' => 1,
                'price_per_hour' => 3000.00,
                'rating' => 4.7,
                'facilities' => [
                    'Perfilado con navaja',
                    'Acabado limpio y natural',
                ],
                'images' => [
                    'assets/branding/profile_placeholder.png',
                ],
            ],
        ];

        $serviceModels = [];
        foreach ($services as $service) {
            $serviceModels[$service['name']] = Court::updateOrCreate(
                [
                    'name' => $service['name'],
                    'business_id' => $tresAmigosBusiness?->id,
                ],
                [
                    'owner_id' => $owner->id,
                    'business_id' => $tresAmigosBusiness?->id,
                    'address' => $shopAddress,
                    'description' => $service['description'],
                    'category' => $service['category'],
                    'duration_hours' => $service['duration_hours'],
                    'duration_minutes' => $service['duration_minutes'],
                    'contact_email' => $shopEmail,
                    'contact_phone' => $shopPhone,
                    'open_hour' => '10:00',
                    'close_hour' => '19:00',
                    'business_hours_note' => $businessHoursNote,
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
                'name' => 'Carlos Ramírez',
                'email' => 'carlos@barberiatresamigos.com',
                'phone' => '+506 8888-1001',
                'bio' => 'Barbero senior enfocado en cortes clásicos, degradados limpios y acabados precisos.',
                'avatar' => 'assets/branding/barber_placeholder.png',
                'services' => ['Corte de cabello', 'Corte y Barba', 'Experiencia Premium Completa (Corte + Barba + Spa Facial + Bebida)'],
            ],
            [
                'name' => 'Diego Morales',
                'email' => 'diego@barberiatresamigos.com',
                'phone' => '+506 8888-1002',
                'bio' => 'Especialista en barba y perfilado con una mano firme para líneas limpias y acabados pulidos.',
                'avatar' => 'assets/branding/barber_placeholder.png',
                'services' => ['Recorte de barba', 'Corte y Barba', 'Experiencia Premium (Corte + Spa Facial + Bebida)'],
            ],
            [
                'name' => 'Luis Fernández',
                'email' => 'luis@barberiatresamigos.com',
                'phone' => '+506 8888-1003',
                'bio' => 'Barbero versátil con enfoque en cortes con tijera, cabello largo y servicio consistente.',
                'avatar' => 'assets/branding/barber_placeholder.png',
                'services' => ['Corte de cabello', 'Corte a Tijera / Cabello Largo', 'Perfilado de Cejas'],
            ],
            [
                'name' => 'Andrés Vega',
                'email' => 'andres@barberiatresamigos.com',
                'phone' => '+506 8888-1004',
                'bio' => 'Barbero de experiencias premium con atención al detalle, estilo y trato cercano.',
                'avatar' => 'assets/branding/barber_placeholder.png',
                'services' => ['Experiencia Premium Completa (Corte + Barba + Spa Facial + Bebida)', 'Experiencia Premium (Corte + Spa Facial + Bebida)'],
            ],
        ];

        foreach ($barbers as $barberData) {
            $staff = Staff::updateOrCreate(
                [
                    'name' => $barberData['name'],
                    'business_id' => $tresAmigosBusiness?->id,
                ],
                [
                    'user_id' => null,
                    'business_id' => $tresAmigosBusiness?->id,
                    'staff_role_id' => $barberRole->id,
                    'email' => $barberData['email'],
                    'phone' => $barberData['phone'],
                    'bio' => $barberData['bio'],
                    'avatar' => $barberData['avatar'] ?? null,
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

        if ($salonAuroraBusiness) {
            $salonService = Court::updateOrCreate(
                [
                    'name' => 'Limpieza facial express',
                    'business_id' => $salonAuroraBusiness->id,
                ],
                [
                    'owner_id' => $owner->id,
                    'business_id' => $salonAuroraBusiness->id,
                    'address' => 'San José, Costa Rica',
                    'description' => 'Servicio mínimo de demo para validar el tenant de Salón Aurora.',
                    'category' => 'facial',
                    'duration_hours' => 1,
                    'duration_minutes' => 30,
                    'contact_email' => 'hola@salonaurora.com',
                    'contact_phone' => '+506 7000-0000',
                    'open_hour' => '09:00',
                    'close_hour' => '18:00',
                    'business_hours_note' => 'Lunes a sábado, agenda limitada de demo.',
                    'price_per_hour' => 12000.00,
                    'rating' => 4.7,
                    'facilities' => ['Atención personalizada', 'Cuidado facial básico'],
                    'images' => ['assets/branding/service_placeholder.png'],
                    'status' => 'active',
                ]
            );

            $salonStaff = Staff::updateOrCreate(
                [
                    'name' => 'Alicia Demo',
                    'business_id' => $salonAuroraBusiness->id,
                ],
                [
                    'user_id' => null,
                    'business_id' => $salonAuroraBusiness->id,
                    'staff_role_id' => $consultantRole->id,
                    'email' => 'alicia@salonaurora.com',
                    'phone' => '+506 7000-0001',
                    'bio' => 'Demo staff member for Salón Aurora tenant scoping.',
                    'avatar' => 'assets/branding/barber_placeholder.png',
                    'is_active' => true,
                ]
            );

            StaffService::updateOrCreate(
                [
                    'staff_id' => $salonStaff->id,
                    'court_id' => $salonService->id,
                ],
                [
                    'is_primary' => true,
                ]
            );
        }
    }
}
