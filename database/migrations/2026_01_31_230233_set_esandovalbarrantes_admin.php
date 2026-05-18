<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('users')
            ->where('email', 'esandovalbarrantes@gmail.com')
            ->update([
                'role' => 'admin',
                'updated_at' => now(),
            ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('users')
            ->where('email', 'esandovalbarrantes@gmail.com')
            ->update([
                'role' => 'user',
                'updated_at' => now(),
            ]);
    }
};
