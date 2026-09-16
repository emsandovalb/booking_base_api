<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Event;
use App\Models\User;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // `role: admin` here is NOT a super-admin grant — it only still
        // matters for Tournament/Event/Team management, which hasn't been
        // migrated off the legacy global role yet (tracked separately).
        // It has no effect on business actions (courts/staff/bookings),
        // which are purely business_user-membership-scoped, and it does
        // NOT grant Super Admin panel access.
        //
        // Deliberately not setting is_super_admin here: this seeder runs
        // automatically (`db:seed`, `migrate --seed`) in every environment
        // it's invoked in, and auto-granting platform-wide access on a
        // well-known email/password would reintroduce the same silent,
        // environment-wide privilege escalation that BootstrapAdminSeeder
        // was built to replace. For local Super Admin panel access, run:
        //   BOOTSTRAP_ADMIN_EMAIL=demo@example.com php artisan db:seed --class=BootstrapAdminSeeder
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
