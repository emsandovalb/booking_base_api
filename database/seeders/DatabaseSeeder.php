<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Court;
use App\Models\Event;
use App\Models\User;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::updateOrCreate(
            ['email' => 'demo@example.com'],
            [
                'name' => 'Admin Demo',
                'password' => bcrypt('password'),
                'role' => 'admin',
            ]
        );
        User::updateOrCreate(
            ['email' => 'client@example.com'],
            [
                'name' => 'Client Demo',
                'password' => bcrypt('password'),
                'role' => 'user',
            ]
        );

        $demoResources = [
            [
                'name' => 'Meeting Room',
                'category' => 'business',
                'facilities' => ['WiFi', 'Projector', 'Air conditioning'],
            ],
            [
                'name' => 'Consultation Room',
                'category' => 'health',
                'facilities' => ['Privacy', 'Desk', 'Waiting area'],
            ],
            [
                'name' => 'Service Station',
                'category' => 'service',
                'facilities' => ['Reception', 'Parking', 'WiFi'],
            ],
            [
                'name' => 'Training Space',
                'category' => 'education',
                'facilities' => ['Projector', 'Chairs', 'Whiteboard'],
            ],
        ];

        foreach ($demoResources as $resource) {
            Court::factory()->create([
                'name' => $resource['name'],
                'category' => $resource['category'],
                'facilities' => $resource['facilities'],
                'owner_id' => $admin->id,
            ]);
        }

        $this->call(StaffRoleSeeder::class);
        $this->call(StaffSeeder::class);

        Event::factory()->count(5)->create();

        $this->call(BarbershopDemoSeeder::class);
        $this->call(LanguageSeeder::class);
    }
}
