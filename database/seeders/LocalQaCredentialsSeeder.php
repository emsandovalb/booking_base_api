<?php

namespace Database\Seeders;

use App\Models\Business;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

class LocalQaCredentialsSeeder extends Seeder
{
    public function run(): void
    {
        $this->ensureSafeLocalQaTarget();

        DB::transaction(function (): void {
            $jcStudio = Business::query()->where('slug', 'jc-studio')->first();
            $jcOwner = User::query()->where('email', 'macchie.23@gmail.com')->first();

            if (! $jcStudio || ! $jcOwner) {
                throw new RuntimeException('JC Studio and its existing owner must exist before running the local QA credential setup.');
            }

            $membership = DB::table('business_user')
                ->where('business_id', $jcStudio->id)
                ->where('user_id', $jcOwner->id)
                ->first();

            if (! $membership || $membership->role !== 'owner' || $membership->status !== 'active') {
                throw new RuntimeException('JC Studio owner membership must already be active with the owner role. No membership changes were made.');
            }

            $superAdmin = User::query()->firstOrNew([
                'email' => 'admin@bemuss.local',
            ]);
            $superAdmin->forceFill([
                'name' => 'Bemuss QA Super Admin',
                'password' => Hash::make('Bemuss123!'),
                'role' => 'admin',
                'is_super_admin' => true,
            ])->save();

            $jcOwner->forceFill([
                'password' => Hash::make('Password123!'),
                'role' => 'user',
                'is_super_admin' => false,
            ])->save();
        });

        $this->command?->info('Local QA credentials configured. Tres Amigos users and memberships were not changed.');
    }

    private function ensureSafeLocalQaTarget(): void
    {
        if (app()->environment('testing')) {
            return;
        }

        if (! filter_var(env('LOCAL_QA_CREDENTIALS_SETUP', false), FILTER_VALIDATE_BOOL)) {
            throw new RuntimeException('Refusing QA credential setup. Explicitly set LOCAL_QA_CREDENTIALS_SETUP=true for this one command.');
        }

        if (config('database.default') !== 'sqlite') {
            throw new RuntimeException('Refusing QA credential setup: the active database is not SQLite.');
        }

        $database = (string) config('database.connections.sqlite.database');
        $databasePath = realpath($database);
        $allowedRoot = realpath(database_path());

        if ($databasePath === false || $allowedRoot === false) {
            throw new RuntimeException('Refusing QA credential setup: the SQLite database path could not be resolved.');
        }

        $normalizedDatabase = strtolower(str_replace('\\', '/', $databasePath));
        $normalizedRoot = rtrim(strtolower(str_replace('\\', '/', $allowedRoot)), '/').'/';

        if (! str_starts_with($normalizedDatabase, $normalizedRoot)) {
            throw new RuntimeException('Refusing QA credential setup: the SQLite database is outside this project database directory.');
        }
    }
}
