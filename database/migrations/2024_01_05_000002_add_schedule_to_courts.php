<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('courts', function (Blueprint $table) {
            $table->string('open_hour')->nullable()->after('contact_phone'); // HH:mm
            $table->string('close_hour')->nullable()->after('open_hour'); // HH:mm
        });
    }

    public function down(): void
    {
        Schema::table('courts', function (Blueprint $table) {
            $table->dropColumn(['open_hour', 'close_hour']);
        });
    }
};

