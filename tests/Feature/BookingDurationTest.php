<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Business;
use App\Models\Court;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookingDurationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Ensure migrations, including the new duration_hours column, are applied
        $this->artisan('migrate');
    }

    protected function authHeaders(User $user, ?Business $business = null): array
    {
        $token = $user->createToken('test')->plainTextToken;
        $headers = ['Authorization' => 'Bearer ' . $token, 'Accept' => 'application/json'];
        if ($business) {
            $headers['X-Business-Slug'] = $business->slug;
        }

        return $headers;
    }

    private function createBusiness(): Business
    {
        return Business::create([
            'name' => 'Barberia Tres Amigos',
            'slug' => 'barberia-tres-amigos-' . uniqid(),
            'business_type' => 'barbershop',
            'status' => 'active',
        ]);
    }

    public function test_store_persists_explicit_duration_hours(): void
    {
        $user = User::factory()->create();
        $business = $this->createBusiness();
        // Duration is derived from the service's own configuration, never
        // from client input (a client-submitted duration would let it claim
        // a shorter/longer slot than the service actually occupies) — so a
        // 2-hour booking requires a service actually configured for 2 hours.
        $court = Court::factory()->create(['business_id' => $business->id, 'duration_hours' => 2]);

        $payload = [
            'court_id' => $court->id,
            'date' => Carbon::now()->addDay()->startOfDay()->toIso8601String(),
            'time_slot' => '9:00 AM to 11:00 AM',
        ];

        $response = $this->postJson('/api/v1/bookings', $payload, $this->authHeaders($user, $business));
        $response->assertCreated();
        $response->assertJsonPath('duration_hours', 2);

        $this->assertDatabaseHas('bookings', [
            'court_id' => $court->id,
            'user_id' => $user->id,
            'duration_hours' => 2,
        ]);
    }

    public function test_store_defaults_duration_hours_to_one_when_missing(): void
    {
        $user = User::factory()->create();
        $business = $this->createBusiness();
        $court = Court::factory()->create(['business_id' => $business->id]);

        $payload = [
            'court_id' => $court->id,
            'date' => Carbon::now()->addDay()->startOfDay()->toIso8601String(),
            'time_slot' => '9:00 AM',
        ];

        $response = $this->postJson('/api/v1/bookings', $payload, $this->authHeaders($user, $business));
        $response->assertCreated();
        $response->assertJsonPath('duration_hours', 1);

        $this->assertDatabaseHas('bookings', [
            'court_id' => $court->id,
            'user_id' => $user->id,
            'duration_hours' => 1,
        ]);
    }

    public function test_overlap_uses_each_existing_bookings_duration(): void
    {
        $user = User::factory()->create();
        $business = $this->createBusiness();
        $court = Court::factory()->create(['business_id' => $business->id]);

        // Existing booking from 18:00 with duration 2h (18-20)
        $existing = Booking::create([
            'user_id' => $user->id,
            'court_id' => $court->id,
            'business_id' => $business->id,
            'date' => Carbon::parse('2026-01-01 18:00:00'),
            'time_slot' => '6:00 PM',
            'duration_hours' => 2,
            'status' => 'pending',
            'booking_code' => 'ABC123',
            'total_price' => 0,
        ]);

        $this->assertNotNull($existing->id);

        // New booking at 19:00 for 1h should overlap with existing 18-20
        $payload = [
            'court_id' => $court->id,
            'date' => '2026-01-01T19:00:00Z',
            'time_slot' => '7:00 PM',
            'duration_hours' => 1,
        ];

        $response = $this->postJson('/api/v1/bookings', $payload, $this->authHeaders($user, $business));
        $response->assertStatus(422);
    }

    public function test_overlap_fallback_for_legacy_null_duration(): void
    {
        $user = User::factory()->create();
        $business = $this->createBusiness();
        $court = Court::factory()->create(['business_id' => $business->id]);

        // Simulate legacy booking with null duration_hours (treated as 1h)
        $legacy = Booking::create([
            'user_id' => $user->id,
            'court_id' => $court->id,
            'business_id' => $business->id,
            'date' => Carbon::parse('2026-01-01 18:00:00'),
            'time_slot' => '6:00 PM',
            'duration_hours' => null,
            'status' => 'pending',
            'booking_code' => 'LEGACY1',
            'total_price' => 0,
        ]);

        $this->assertNotNull($legacy->id);

        // New booking starting at 18:30 for 1h should overlap with legacy 18-19 fallback
        $payload = [
            'court_id' => $court->id,
            'date' => '2026-01-01T18:30:00Z',
            'time_slot' => '6:30 PM',
            'duration_hours' => 1,
        ];

        $response = $this->postJson('/api/v1/bookings', $payload, $this->authHeaders($user, $business));
        $response->assertStatus(422);
    }

    public function test_rebook_preserves_duration_hours(): void
    {
        $user = User::factory()->create();
        $business = $this->createBusiness();
        // Duration comes from the service's own configuration on rebook too
        // (never from the original booking's stored value), so the service
        // itself needs the matching duration for this assertion to hold.
        $court = Court::factory()->create(['business_id' => $business->id, 'duration_hours' => 3]);

        $original = Booking::create([
            'user_id' => $user->id,
            'court_id' => $court->id,
            'business_id' => $business->id,
            'date' => Carbon::now()->addDays(2)->setTime(9, 0),
            'time_slot' => '9:00 AM',
            'duration_hours' => 3,
            'status' => 'pending',
            'booking_code' => 'ORIG01',
            'total_price' => 0,
        ]);

        $this->assertNotNull($original->id);

        $payload = [
            'date' => Carbon::now()->addDays(3)->startOfDay()->toIso8601String(),
            'time_slot' => '10:00 AM',
        ];

        $response = $this->postJson(
            '/api/v1/bookings/' . $original->id . '/rebook',
            $payload,
            $this->authHeaders($user, $business)
        );

        $response->assertCreated();
        $response->assertJsonPath('duration_hours', 3);

        $this->assertDatabaseHas('bookings', [
            'court_id' => $court->id,
            'user_id' => $user->id,
            'duration_hours' => 3,
        ]);
    }
}

