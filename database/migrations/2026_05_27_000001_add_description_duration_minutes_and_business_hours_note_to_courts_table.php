<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('courts', function (Blueprint $table) {
            $table->text('description')->nullable()->after('address');
            $table->unsignedSmallInteger('duration_minutes')->nullable()->after('duration_hours');
            $table->text('business_hours_note')->nullable()->after('close_hour');
        });
    }

    public function down(): void
    {
        Schema::table('courts', function (Blueprint $table) {
            $table->dropColumn(['description', 'duration_minutes', 'business_hours_note']);
        });
    }
};
