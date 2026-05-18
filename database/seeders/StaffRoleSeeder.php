<?php

namespace Database\Seeders;

use App\Models\StaffRole;
use Illuminate\Database\Seeder;

class StaffRoleSeeder extends Seeder
{
    public function run(): void
    {
        $roles = [
            ['name' => 'Barber', 'slug' => 'barber', 'description' => 'Provides grooming and style services.'],
            ['name' => 'Trainer', 'slug' => 'trainer', 'description' => 'Supports coaching and training services.'],
            ['name' => 'Consultant', 'slug' => 'consultant', 'description' => 'Handles consulting and advisory services.'],
            ['name' => 'Coordinator', 'slug' => 'coordinator', 'description' => 'Coordinates bookings and client support.'],
        ];

        foreach ($roles as $role) {
            StaffRole::updateOrCreate(
                ['slug' => $role['slug']],
                [
                    'name' => $role['name'],
                    'description' => $role['description'],
                ]
            );
        }
    }
}
