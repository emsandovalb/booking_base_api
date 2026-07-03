<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
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

        Event::factory()->count(5)->create();

        $this->call(BusinessSeeder::class);
        $this->call(BarbershopDemoSeeder::class);
        $this->call(LanguageSeeder::class);
    }
}
