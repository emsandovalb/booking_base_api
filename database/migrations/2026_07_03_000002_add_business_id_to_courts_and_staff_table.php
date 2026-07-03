<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('courts', function (Blueprint $table) {
            $table->foreignId('business_id')
                ->nullable()
                ->after('owner_id')
                ->constrained('businesses')
                ->nullOnDelete();
        });

        Schema::table('staff', function (Blueprint $table) {
            $table->foreignId('business_id')
                ->nullable()
                ->after('user_id')
                ->constrained('businesses')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('courts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('business_id');
        });

        Schema::table('staff', function (Blueprint $table) {
            $table->dropConstrainedForeignId('business_id');
        });
    }
};
