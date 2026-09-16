<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Business;
use App\Models\Court;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminBookingTransitionTest extends TestCase
{
    use RefreshDatabase;

    public function test_business_owner_can_confirm_a_pending_booking_and_it_persists(): void
    {
        [$owner, $business, $booking] = $this->bookingForBusinessAdmin('owner');
        Sanctum::actingAs($owner);

        $this->postJson("/api/v1/reservations/{$booking->id}/confirm", [], $this->headers($business))
            ->assertOk()
            ->assertJsonPath('status', 'confirmed');

        $this->assertDatabaseHas('bookings', ['id' => $booking->id, 'status' => 'confirmed']);
        $this->getJson("/api/v1/reservations/{$booking->id}", $this->headers($business))
            ->assertOk()
            ->assertJsonPath('status', 'confirmed');
    }

    public function test_business_admin_can_reject_a_pending_booking_and_it_persists(): void
    {
        [$admin, $business, $booking] = $this->bookingForBusinessAdmin('admin');
        Sanctum::actingAs($admin);

        $this->postJson("/api/v1/reservations/{$booking->id}/reject", [], $this->headers($business))
            ->assertOk()
            ->assertJsonPath('status', 'rejected');

        $this->assertDatabaseHas('bookings', ['id' => $booking->id, 'status' => 'rejected']);
        $this->getJson("/api/v1/reservations/{$booking->id}", $this->headers($business))
            ->assertOk()
            ->assertJsonPath('status', 'rejected');
    }

    public function test_business_admin_can_cancel_pending_or_confirmed_bookings_without_client_time_limit(): void
    {
        [$admin, $business, $pending] = $this->bookingForBusinessAdmin('admin', 'pending', now()->addHour());
        $confirmed = $this->createBooking($business, 'confirmed', now()->addHour());
        Sanctum::actingAs($admin);

        foreach ([$pending, $confirmed] as $booking) {
            $this->postJson("/api/v1/reservations/{$booking->id}/cancel", [], $this->headers($business))
                ->assertOk()
                ->assertJsonPath('status', 'cancelled');
            $this->getJson("/api/v1/reservations/{$booking->id}", $this->headers($business))
                ->assertOk()
                ->assertJsonPath('status', 'cancelled');
        }

        $this->assertDatabaseHas('bookings', ['id' => $pending->id, 'status' => 'cancelled']);
        $this->assertDatabaseHas('bookings', ['id' => $confirmed->id, 'status' => 'cancelled']);
    }

    public function test_admin_from_another_business_cannot_transition_a_booking(): void
    {
        [, $business, $booking] = $this->bookingForBusinessAdmin('owner');
        $otherAdmin = User::factory()->create(['role' => 'admin']);
        $otherBusiness = Business::create([
            'name' => 'Other Barber', 'slug' => 'other-barber', 'business_type' => 'barbershop', 'status' => 'active',
        ]);
        $otherBusiness->users()->attach($otherAdmin, ['role' => 'owner', 'status' => 'active', 'accepted_at' => now()]);
        Sanctum::actingAs($otherAdmin);

        $this->postJson("/api/v1/reservations/{$booking->id}/confirm", [], $this->headers($business))
            ->assertForbidden();
        $this->assertDatabaseHas('bookings', ['id' => $booking->id, 'status' => 'pending']);
    }

    public function test_terminal_or_invalid_transitions_are_rejected(): void
    {
        [$admin, $business, $booking] = $this->bookingForBusinessAdmin('admin', 'confirmed');
        Sanctum::actingAs($admin);

        $this->postJson("/api/v1/reservations/{$booking->id}/confirm", [], $this->headers($business))
            ->assertStatus(422);
        $this->postJson("/api/v1/reservations/{$booking->id}/reject", [], $this->headers($business))
            ->assertStatus(422);
    }

    private function bookingForBusinessAdmin(string $membershipRole, string $status = 'pending', ?Carbon $date = null): array
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $business = Business::create([
            'name' => 'Bemuss', 'slug' => 'bemuss', 'business_type' => 'barbershop', 'status' => 'active',
        ]);
        $business->users()->attach($admin, ['role' => $membershipRole, 'status' => 'active', 'accepted_at' => now()]);

        return [$admin, $business, $this->createBooking($business, $status, $date ?? Carbon::parse('2026-07-08 10:00:00'))];
    }

    private function createBooking(Business $business, string $status, Carbon $date): Booking
    {
        $client = User::factory()->create();
        $court = Court::create([
            'business_id' => $business->id, 'name' => 'Corte', 'address' => 'Guatemala',
            'price_per_hour' => 100, 'rating' => 5, 'status' => 'active',
        ]);

        return Booking::create([
            'user_id' => $client->id, 'court_id' => $court->id, 'business_id' => $business->id,
            'date' => $date, 'time_slot' => '10:00 AM to 11:00 AM', 'duration_hours' => 1,
            'status' => $status, 'booking_code' => 'BK' . Booking::count() . 'X', 'total_price' => 100,
        ]);
    }

    private function headers(Business $business): array
    {
        return ['X-Business-Slug' => $business->slug];
    }
}
