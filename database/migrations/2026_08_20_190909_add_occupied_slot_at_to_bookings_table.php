<?php

use App\Models\Booking;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * DB-level backstop against double-booking races, on top of the
     * application-level lock in BookingController::store(). MySQL has no
     * partial/filtered unique index and no interval-exclusion constraint
     * (unlike Postgres' EXCLUDE USING gist), and `time_slot` is a free-text
     * label that can't be trusted as a uniqueness key on its own — so a
     * plain `unique(court_id, date, time_slot)` isn't available and a
     * blanket `unique(court_id, date)` would incorrectly block legitimate
     * rebooking of a slot freed by cancellation.
     *
     * Instead: `occupied_slot_at` mirrors `date` only while a booking is in
     * a blocking status (pending/confirmed) — kept in sync by
     * Booking::booted()'s saving hook — and is NULL otherwise. A cancelled
     * or rejected booking's row keeps its history but no longer occupies
     * anything, so it can never collide. The unique index on
     * (court_id, occupied_slot_at) then genuinely enforces "at most one
     * active booking per court per exact start instant" at the database
     * level, immune to any bug in the application-side overlap check.
     *
     * This only catches exact-same-start-instant collisions, not two
     * different-but-time-overlapping bookings (e.g. 6:00 PM+2h vs
     * 7:00 PM+1h) — closing that fully would need `time_slot` replaced by
     * real start/end columns. Flagged as a known gap, not silently
     * papered over: the application-level lock is what actually prevents
     * the general overlap race; this index is the last-resort backstop
     * for the dominant real-world case (concurrent bookers hitting the
     * same slot the availability endpoint offered).
     */
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->timestamp('occupied_slot_at')->nullable()->after('date');
        });

        // Backfill: existing blocking bookings start occupying their slot;
        // terminal-status bookings (cancelled/rejected) stay NULL.
        DB::table('bookings')
            ->whereIn('status', Booking::BLOCKING_STATUSES)
            ->update(['occupied_slot_at' => DB::raw('date')]);

        Schema::table('bookings', function (Blueprint $table) {
            $table->unique(['court_id', 'occupied_slot_at'], 'bookings_court_occupied_slot_unique');
            $table->index(['court_id', 'status', 'date'], 'bookings_court_status_date_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropUnique('bookings_court_occupied_slot_unique');
            $table->dropIndex('bookings_court_status_date_index');
            $table->dropColumn('occupied_slot_at');
        });
    }
};
