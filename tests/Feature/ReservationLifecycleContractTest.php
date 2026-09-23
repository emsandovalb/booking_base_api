<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\Court;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ReservationLifecycleContractTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_reservation_is_persisted_and_visible_to_customer_and_owner_only_in_its_tenant(): void
    {
        $jcStudio = $this->business('jc-studio');
        $otherBusiness = $this->business('barberia-tres-amigos');
        $service = Court::factory()->create([
            'business_id' => $jcStudio->id,
            'name' => 'Corte Y estilizado',
            'status' => 'active',
        ]);
        $customer = User::factory()->create();
        $owner = User::factory()->create(['role' => 'admin']);
        $jcStudio->users()->attach($owner, [
            'role' => 'owner', 'status' => 'active', 'accepted_at' => now(),
        ]);
        Sanctum::actingAs($customer);

        $start = Carbon::now()->addDays(2)->setTime(9, 0);
        $created = $this->postJson('/api/v1/reservations', [
            'resource_id' => $service->id,
            'date' => $start->toIso8601String(),
            'time_slot' => '9:00 AM to 10:00 AM',
            'duration_hours' => 1,
        ], $this->headers($jcStudio))
            ->assertCreated()
            ->assertJsonPath('business_id', $jcStudio->id)
            ->assertJsonPath('user_id', $customer->id)
            ->assertJsonPath('court_id', $service->id)
            ->assertJsonPath('status', 'pending')
            ->json();

        $this->assertIsInt($created['id']);
        $this->assertDatabaseHas('bookings', [
            'id' => $created['id'],
            'business_id' => $jcStudio->id,
            'user_id' => $customer->id,
            'court_id' => $service->id,
            'status' => 'pending',
        ]);

        $this->getJson('/api/v1/reservations', $this->headers($jcStudio))
            ->assertOk()
            ->assertJsonPath('data.0.id', $created['id']);

        Sanctum::actingAs($owner);
        $day = $start->toDateString();
        $this->getJson("/api/v1/admin/reservations?day={$day}", $this->headers($jcStudio))
            ->assertOk()
            ->assertJsonPath('data.0.id', $created['id']);

        $this->getJson('/api/v1/reservations', $this->headers($otherBusiness))
            ->assertOk()
            ->assertJsonCount(0, 'data');
        $this->getJson("/api/v1/admin/reservations?day={$day}", $this->headers($otherBusiness))
            ->assertForbidden();
    }

    public function test_owner_agenda_includes_pending_and_confirmed_bookings_for_the_requested_day_only(): void
    {
        $jcStudio = $this->business('jc-studio');
        $otherBusiness = $this->business('barberia-tres-amigos');
        $service = Court::factory()->create(['business_id' => $jcStudio->id, 'status' => 'active']);
        $owner = User::factory()->create(['role' => 'admin']);
        $customer = User::factory()->create();
        $jcStudio->users()->attach($owner, [
            'role' => 'owner', 'status' => 'active', 'accepted_at' => now(),
        ]);
        $day = Carbon::now()->addDays(3)->startOfDay();

        $pending = \App\Models\Booking::create([
            'user_id' => $customer->id, 'court_id' => $service->id,
            'business_id' => $jcStudio->id, 'date' => $day->copy()->setTime(9, 0),
            'time_slot' => '9:00 AM to 10:00 AM', 'duration_hours' => 1,
            'status' => 'pending', 'booking_code' => 'PENDING1', 'total_price' => 0,
        ]);
        $confirmed = \App\Models\Booking::create([
            'user_id' => $customer->id, 'court_id' => $service->id,
            'business_id' => $jcStudio->id, 'date' => $day->copy()->setTime(11, 0),
            'time_slot' => '11:00 AM to 12:00 PM', 'duration_hours' => 1,
            'status' => 'confirmed', 'booking_code' => 'CONFIRM1', 'total_price' => 0,
        ]);
        \App\Models\Booking::create([
            'user_id' => $customer->id, 'court_id' => $service->id,
            'business_id' => $jcStudio->id, 'date' => $day->copy()->addDay()->setTime(9, 0),
            'time_slot' => '9:00 AM to 10:00 AM', 'duration_hours' => 1,
            'status' => 'pending', 'booking_code' => 'NEXTDAY1', 'total_price' => 0,
        ]);

        Sanctum::actingAs($owner);
        $response = $this->getJson('/api/v1/admin/reservations?day='.$day->toDateString(), $this->headers($jcStudio));

        $response->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonFragment(['id' => $pending->id, 'status' => 'pending'])
            ->assertJsonFragment(['id' => $confirmed->id, 'status' => 'confirmed']);

        $this->getJson('/api/v1/admin/reservations?day='.$day->toDateString(), $this->headers($otherBusiness))
            ->assertForbidden();
    }

    private function business(string $slug): Business
    {
        return Business::create([
            'name' => $slug,
            'slug' => $slug,
            'business_type' => 'barbershop',
            'status' => 'active',
        ]);
    }

    private function headers(Business $business): array
    {
        return ['X-Business-Slug' => $business->slug];
    }
}
