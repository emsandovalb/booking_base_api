<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Platform-level flag, distinct from the legacy `role` column and
            // from per-business admin/owner membership on business_user.
            // Grants access to the Super Admin panel only. Never set this
            // via a migration for a specific person — use BootstrapAdminSeeder.
            $table->boolean('is_super_admin')->default(false)->after('role');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('is_super_admin');
        });
    }
};
