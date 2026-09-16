<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Grants platform-level Super Admin access to one existing account, chosen
 * by the operator via BOOTSTRAP_ADMIN_EMAIL in .env — never hardcoded.
 *
 * Intentionally not called from DatabaseSeeder::run(). Run it explicitly,
 * once, per environment:
 *
 *   php artisan db:seed --class=BootstrapAdminSeeder
 *
 * The target user must already exist (register the account first). This
 * seeder only flips is_super_admin; it never creates an account or sets
 * a password, so there's no default credential to leak.
 */
class BootstrapAdminSeeder extends Seeder
{
    public function run(): void
    {
        $email = trim((string) env('BOOTSTRAP_ADMIN_EMAIL', ''));

        if ($email === '') {
            $this->command?->warn(
                'BOOTSTRAP_ADMIN_EMAIL is not set in .env; skipping super admin bootstrap.'
            );

            return;
        }

        $user = User::where('email', $email)->first();

        if (!$user) {
            $this->command?->warn(
                "No user found with email [{$email}]. Register that account first, then re-run this seeder."
            );

            return;
        }

        if ($user->is_super_admin) {
            $this->command?->info("[{$email}] already has super admin access.");

            return;
        }

        $user->forceFill(['is_super_admin' => true])->save();

        $this->command?->info("Granted super admin access to [{$email}].");
    }
}
