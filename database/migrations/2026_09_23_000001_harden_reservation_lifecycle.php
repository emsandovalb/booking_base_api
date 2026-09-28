<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->string('payment_status')->default('unpaid')->after('status');
            $table->foreignId('rebooked_from_booking_id')->nullable()->after('court_id')->constrained('bookings')->nullOnDelete();
            $table->timestamp('completed_at')->nullable()->after('occupied_slot_at');
            $table->index(['business_id', 'staff_id', 'status', 'date'], 'bookings_business_staff_status_date_index');
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropIndex('bookings_business_staff_status_date_index');
            $table->dropConstrainedForeignId('rebooked_from_booking_id');
            $table->dropColumn(['payment_status', 'completed_at']);
        });
    }
};
