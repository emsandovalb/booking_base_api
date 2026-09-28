<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Business;
use App\Models\Court;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookingAvailabilityStatusTest extends TestCase
{
    use RefreshDatabase;

    private function authHeaders(User $user, Business $business): array
    {
        return [
            'Authorization' => 'Bearer ' . $user->createToken('test')->plainTextToken,
            'Accept' => 'application/json',
            'X-Business-Slug' => $business->slug,
        ];
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

    private function bookingPayload(Court $court, Carbon $start): array
    {
        return [
            'court_id' => $court->id,
            'date' => $start->toIso8601String(),
            'time_slot' => '10:00 AM to 11:00 AM',
        ];
    }

    public function test_an_active_overlapping_booking_is_rejected(): void
    {
        $user = User::factory()->create();
        $business = $this->createBusiness();
        $court = Court::factory()->create(['business_id' => $business->id]);
        $start = Carbon::now()->addDays(2)->setTime(10, 0);
        $headers = $this->authHeaders($user, $business);

        $this->postJson('/api/v1/bookings', $this->bookingPayload($court, $start), $headers)
            ->assertCreated()
            ->assertJsonPath('status', 'pending');

        $this->postJson('/api/v1/bookings', $this->bookingPayload($court, $start), $headers)
            ->assertStatus(422)
            ->assertJsonPath('message', 'Time slot is no longer available');
    }

    public function test_a_cancelled_booking_allows_another_booking_in_the_same_slot(): void
    {
        $user = User::factory()->create();
        $business = $this->createBusiness();
        $court = Court::factory()->create(['business_id' => $business->id]);
        $start = Carbon::now()->addDays(2)->setTime(10, 0);
        $headers = $this->authHeaders($user, $business);

        $booking = $this->postJson('/api/v1/bookings', $this->bookingPayload($court, $start), $headers)
            ->assertCreated()
            ->json();

        $this->postJson('/api/v1/bookings/' . $booking['id'] . '/cancel', [], $headers)
            ->assertOk()
            ->assertJsonPath('status', 'cancelled');

        $this->postJson('/api/v1/bookings', $this->bookingPayload($court, $start), $headers)
            ->assertCreated();
    }

    public function test_a_cancelled_booking_is_not_returned_as_occupied_in_availability(): void
    {
        $user = User::factory()->create();
        $business = $this->createBusiness();
        $court = Court::factory()->create(['business_id' => $business->id]);
        $start = Carbon::now()->addDays(2)->setTime(10, 0);
        $headers = $this->authHeaders($user, $business);

        $booking = $this->postJson('/api/v1/bookings', $this->bookingPayload($court, $start), $headers)
            ->assertCreated()
            ->json();
        $this->postJson('/api/v1/bookings/' . $booking['id'] . '/cancel', [], $headers)->assertOk();

        $this->getJson('/api/v1/courts/' . $court->id . '/availability?date=' . $start->toDateString(), $headers)
            ->assertOk()
            ->assertJsonPath('booked', []);
    }

    public function test_a_rejected_booking_does_not_block_the_slot_when_present(): void
    {
        $user = User::factory()->create();
        $business = $this->createBusiness();
        $court = Court::factory()->create(['business_id' => $business->id]);
        $start = Carbon::now()->addDays(2)->setTime(10, 0);
        $headers = $this->authHeaders($user, $business);

        $booking = $this->postJson('/api/v1/bookings', $this->bookingPayload($court, $start), $headers)
            ->assertCreated()
            ->json();
        Booking::findOrFail($booking['id'])->update(['status' => 'rejected']);

        $this->postJson('/api/v1/bookings', $this->bookingPayload($court, $start), $headers)
            ->assertCreated();
        $this->getJson('/api/v1/courts/' . $court->id . '/availability?date=' . $start->toDateString(), $headers)
            ->assertOk()
            ->assertJsonCount(1, 'booked');
    }
}
