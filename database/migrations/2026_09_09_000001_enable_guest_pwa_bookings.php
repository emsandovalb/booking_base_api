<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            // A service can be delivered by multiple barbers at the same time.
            // Staff is therefore the scarce resource for the SaaS booking flow.
            $table->dropUnique('bookings_court_occupied_slot_unique');
            $table->dropForeign(['user_id']);
            $table->unsignedBigInteger('user_id')->nullable()->change();
            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
            $table->string('customer_name')->nullable()->after('user_id');
            $table->string('customer_phone', 40)->nullable()->after('customer_name');
            $table->string('customer_email')->nullable()->after('customer_phone');
            $table->unsignedSmallInteger('duration_minutes')->nullable()->after('duration_hours');
            $table->uuid('public_token')->nullable()->unique()->after('booking_code');
            $table->string('occupancy_key')->nullable()->after('occupied_slot_at');
            $table->index(['business_id', 'date', 'status'], 'bookings_business_date_status_index');
            $table->index(['staff_id', 'date', 'status'], 'bookings_staff_date_status_index');
            $table->unique(['occupancy_key', 'occupied_slot_at'], 'bookings_occupancy_slot_unique');
        });

        foreach (\Illuminate\Support\Facades\DB::table('bookings')->select(['id', 'court_id', 'staff_id'])->get() as $booking) {
            \Illuminate\Support\Facades\DB::table('bookings')->where('id', $booking->id)->update([
                'occupancy_key' => $booking->staff_id ? 'staff:'.$booking->staff_id : 'court:'.$booking->court_id,
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropUnique('bookings_occupancy_slot_unique');
            $table->dropIndex('bookings_business_date_status_index');
            $table->dropIndex('bookings_staff_date_status_index');
            $table->dropUnique(['public_token']);
            $table->dropColumn(['customer_name', 'customer_phone', 'customer_email', 'duration_minutes', 'public_token', 'occupancy_key']);
            $table->dropForeign(['user_id']);
            $table->unsignedBigInteger('user_id')->nullable(false)->change();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->unique(['court_id', 'occupied_slot_at'], 'bookings_court_occupied_slot_unique');
        });
    }
};
