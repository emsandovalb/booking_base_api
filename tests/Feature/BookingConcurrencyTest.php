<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Business;
use App\Models\Court;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Covers BookingController::createBookingUnderLock() — the fix for the
 * double-booking race where two concurrent requests for the same slot
 * could both pass the overlap check before either committed.
 *
 * PHP test execution is single-threaded and this repo's dev/test DB is
 * sqlite (no real cross-connection row locking, and in-memory sqlite
 * connections are isolated from each other besides), so genuine parallel
 * HTTP requests aren't something this suite can drive deterministically.
 * Per the task's own "at minimum simulate the race by manually
 * interleaving" allowance, these tests manually interleave: pre-acquire
 * the same lock/state a concurrent request would hold, then verify the
 * request under test is correctly blocked/rejected rather than sailing
 * through — exercising the exact mechanism (Cache::lock keyed by court)
 * production concurrency would contend on.
 */
class BookingConcurrencyTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsUser(): User
    {
        $user = User::factory()->create(['role' => 'user']);
        \Laravel\Sanctum\Sanctum::actingAs($user);

        return $user;
    }

    private function createBusiness(): Business
    {
        return Business::create([
            'name' => 'Barberia Tres Amigos',
            'slug' => 'barberia-tres-amigos-'.uniqid(),
            'business_type' => 'barbershop',
            'status' => 'active',
        ]);
    }

    private function headers(Business $business): array
    {
        return ['X-Business-Slug' => $business->slug];
    }

    public function test_concurrent_request_holding_the_court_lock_forces_the_second_request_to_wait_then_conflict(): void
    {
        $this->actingAsUser();
        $business = $this->createBusiness();
        $court = Court::factory()->create(['business_id' => $business->id, 'status' => 'active']);
        $start = Carbon::now()->addDays(2)->setTime(10, 0);

        // Simulate "request A is mid-transaction": it already holds the
        // per-court lock and hasn't committed or released it yet.
        $lock = Cache::lock('booking-lock:court:'.$court->id, 10);
        $this->assertTrue($lock->get(), 'Pre-acquiring the lock to simulate an in-flight request should succeed.');

        try {
            // "Request B" arrives for the exact same court while A still
            // holds the lock. It must not be allowed to skip ahead of A
            // and create a second booking — it should block briefly, then
            // fail clearly (409) rather than silently double-booking.
            $response = $this->postJson('/api/v1/bookings', [
                'court_id' => $court->id,
                'date' => $start->toIso8601String(),
                'time_slot' => '10:00 AM to 11:00 AM',
            ], $this->headers($business));

            $response->assertStatus(409);
            $this->assertDatabaseMissing('bookings', ['court_id' => $court->id]);
        } finally {
            $lock->release();
        }
    }

    public function test_second_request_proceeds_once_the_lock_is_released_and_correctly_detects_the_slot_is_now_taken(): void
    {
        $user = $this->actingAsUser();
        $business = $this->createBusiness();
        $court = Court::factory()->create(['business_id' => $business->id, 'status' => 'active']);
        $start = Carbon::now()->addDays(2)->setTime(10, 0);

        // Manually interleave: "request A" runs its entire critical
        // section (acquire lock, check overlap, insert, release) to
        // completion first — this is exactly what BookingController's
        // locked path does internally, just driven by hand here so the
        // test can then assert on "request B" arriving after.
        $lockA = Cache::lock('booking-lock:court:'.$court->id, 10);
        $this->assertTrue($lockA->get());
        $bookingA = Booking::create([
            'user_id' => $user->id,
            'court_id' => $court->id,
            'business_id' => $business->id,
            'date' => $start,
            'time_slot' => '10:00 AM to 11:00 AM',
            'duration_hours' => 1,
            'status' => 'pending',
            'booking_code' => Str::upper(Str::random(6)),
            'total_price' => 0,
        ]);
        $lockA->release();

        // "Request B" now runs the real endpoint for the identical slot.
        $response = $this->postJson('/api/v1/bookings', [
            'court_id' => $court->id,
            'date' => $start->toIso8601String(),
            'time_slot' => '10:00 AM to 11:00 AM',
        ], $this->headers($business));

        $response->assertStatus(422);
        $response->assertJsonPath('message', 'Time slot already booked');
        $this->assertDatabaseCount('bookings', 1);
        $this->assertDatabaseHas('bookings', ['id' => $bookingA->id]);
    }

    public function test_db_level_backstop_rejects_a_duplicate_insert_that_bypasses_the_application_lock(): void
    {
        // Proves the second layer independently of the first: even if
        // something inserted directly at the same exact instant for the
        // same court without ever going through Cache::lock() (a bug,
        // a raw query, a different code path), the unique index on
        // (court_id, occupied_slot_at) still refuses the duplicate.
        $user = User::factory()->create();
        $business = $this->createBusiness();
        $court = Court::factory()->create(['business_id' => $business->id, 'status' => 'active']);
        $start = Carbon::parse('2026-09-01 10:00:00');

        Booking::create([
            'user_id' => $user->id,
            'court_id' => $court->id,
            'business_id' => $business->id,
            'date' => $start,
            'time_slot' => '10:00 AM to 11:00 AM',
            'duration_hours' => 1,
            'status' => 'pending',
            'booking_code' => Str::upper(Str::random(6)),
            'total_price' => 0,
        ]);

        $this->expectException(\Illuminate\Database\QueryException::class);

        Booking::create([
            'user_id' => $user->id,
            'court_id' => $court->id,
            'business_id' => $business->id,
            'date' => $start,
            'time_slot' => '10:00 AM to 11:00 AM',
            'duration_hours' => 1,
            'status' => 'pending',
            'booking_code' => Str::upper(Str::random(6)),
            'total_price' => 0,
        ]);
    }

    public function test_db_level_backstop_allows_rebooking_the_same_instant_after_the_original_is_cancelled(): void
    {
        // Guards against the backstop being too aggressive: a cancelled
        // booking must free its slot for the unique index too, not just
        // for the application-level overlap check.
        $user = User::factory()->create();
        $business = $this->createBusiness();
        $court = Court::factory()->create(['business_id' => $business->id, 'status' => 'active']);
        $start = Carbon::parse('2026-09-01 10:00:00');

        $original = Booking::create([
            'user_id' => $user->id,
            'court_id' => $court->id,
            'business_id' => $business->id,
            'date' => $start,
            'time_slot' => '10:00 AM to 11:00 AM',
            'duration_hours' => 1,
            'status' => 'pending',
            'booking_code' => Str::upper(Str::random(6)),
            'total_price' => 0,
        ]);

        $original->status = 'cancelled';
        $original->save();
        $this->assertNull($original->fresh()->occupied_slot_at);

        $replacement = Booking::create([
            'user_id' => $user->id,
            'court_id' => $court->id,
            'business_id' => $business->id,
            'date' => $start,
            'time_slot' => '10:00 AM to 11:00 AM',
            'duration_hours' => 1,
            'status' => 'pending',
            'booking_code' => Str::upper(Str::random(6)),
            'total_price' => 0,
        ]);

        $this->assertNotNull($replacement->id);
        $this->assertEquals($start->toDateTimeString(), $replacement->occupied_slot_at->toDateTimeString());
    }

    public function test_lock_timeout_returns_a_clear_conflict_not_a_silent_failure(): void
    {
        $this->actingAsUser();
        $business = $this->createBusiness();
        $court = Court::factory()->create(['business_id' => $business->id, 'status' => 'active']);

        $lock = Cache::lock('booking-lock:court:'.$court->id, 10);
        $this->assertTrue($lock->get());

        try {
            $response = $this->postJson('/api/v1/bookings', [
                'court_id' => $court->id,
                'date' => Carbon::now()->addDay()->toIso8601String(),
                'time_slot' => '2:00 PM to 3:00 PM',
            ], $this->headers($business));

            $response->assertStatus(409);
            $response->assertJsonPath(
                'message',
                'This slot is currently being booked by someone else. Please try again.'
            );
        } finally {
            $lock->release();
        }
    }
}
