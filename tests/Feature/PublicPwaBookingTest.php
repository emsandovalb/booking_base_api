<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Business;
use App\Models\Court;
use App\Models\Staff;
use App\Models\StaffRole;
use App\Models\StaffService;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class PublicPwaBookingTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_page_resolves_tenant_and_never_exposes_another_business(): void
    {
        [$business, $service] = $this->catalog('barber-house', 'Barber House', 'Classic cut');
        [, $otherService] = $this->catalog('urban-cut', 'Urban Cut', 'Urban fade');

        $this->get('/b/barber-house')
            ->assertOk()
            ->assertSee('Barber House')
            ->assertSee($service->name)
            ->assertDontSee($otherService->name);
    }

    public function test_customer_can_book_without_authentication_under_the_correct_business(): void
    {
        [$business, $service, $staff] = $this->catalog('barber-house', 'Barber House', 'Classic cut');
        $date = now()->addDay()->toDateString();

        $response = $this->post('/b/barber-house/book', [
            'service_id' => $service->id,
            'staff_id' => $staff->id,
            'date' => $date,
            'time' => '10:00',
            'customer_name' => 'Ana López',
            'customer_phone' => '+502 5555 0101',
            'customer_email' => 'ana@example.com',
        ]);

        $booking = Booking::firstOrFail();
        $response->assertRedirect();
        $this->assertNull($booking->user_id);
        $this->assertSame($business->id, $booking->business_id);
        $this->assertSame('Ana López', $booking->customer_name);
        $this->assertNotNull($booking->public_token);

        $confirmation = URL::temporarySignedRoute('booking.confirmed', now()->addHour(), [
            'slug' => $business->slug,
            'token' => $booking->public_token,
        ]);
        $this->get($confirmation)->assertOk()->assertSee('Cita confirmada')->assertSee($booking->booking_code);
    }

    public function test_booking_removes_overlapping_staff_slots_but_other_staff_remains_available(): void
    {
        [$business, $service, $staff] = $this->catalog('barber-house', 'Barber House', 'Classic cut');
        $otherStaff = Staff::create([
            'business_id' => $business->id,
            'staff_role_id' => $staff->staff_role_id,
            'name' => 'Second Barber',
            'is_active' => true,
        ]);
        StaffService::create(['staff_id' => $otherStaff->id, 'court_id' => $service->id, 'is_primary' => true]);
        $date = now()->addDay()->toDateString();

        $this->post('/b/barber-house/book', [
            'service_id' => $service->id, 'staff_id' => $staff->id, 'date' => $date, 'time' => '10:00',
            'customer_name' => 'One', 'customer_phone' => '1111',
        ])->assertRedirect();

        $this->getJson("/b/barber-house/availability?service_id={$service->id}&staff_id={$staff->id}&date={$date}")
            ->assertOk()->assertJsonMissing(['value' => '10:00']);
        $this->getJson("/b/barber-house/availability?service_id={$service->id}&staff_id={$otherStaff->id}&date={$date}")
            ->assertOk()->assertJsonFragment(['value' => '10:00']);

        $this->post('/b/barber-house/book', [
            'service_id' => $service->id, 'staff_id' => $otherStaff->id, 'date' => $date, 'time' => '10:00',
            'customer_name' => 'Two', 'customer_phone' => '2222',
        ])->assertRedirect();
        $this->assertSame(2, Booking::count());
    }

    public function test_public_availability_respects_the_onboarded_weekly_schedule(): void
    {
        [$business, $service, $staff] = $this->catalog('barber-house', 'Barber House', 'Classic cut');
        $business->update([
            'metadata' => [
                'onboarding' => [
                    'weekly_schedule' => [
                        'sunday' => ['is_open' => false, 'opens_at' => '09:00', 'closes_at' => '18:00'],
                    ],
                ],
            ],
        ]);
        $sunday = now()->next('Sunday')->toDateString();

        $this->getJson("/b/barber-house/availability?service_id={$service->id}&staff_id={$staff->id}&date={$sunday}")
            ->assertOk()
            ->assertExactJson(['slots' => []]);
    }

    public function test_business_dashboard_and_mutations_are_tenant_isolated(): void
    {
        [$business, $service, $staff] = $this->catalog('barber-house', 'Barber House', 'Classic cut');
        [$otherBusiness] = $this->catalog('urban-cut', 'Urban Cut', 'Urban fade');
        $owner = User::factory()->create();
        $business->users()->attach($owner, ['role' => 'owner', 'status' => 'active']);
        $this->actingAs($owner);
        $booking = Booking::create([
            'business_id' => $business->id, 'court_id' => $service->id, 'staff_id' => $staff->id,
            'customer_name' => 'Guest Client', 'customer_phone' => '5555', 'date' => now()->addDay()->setTime(10, 0),
            'time_slot' => '10:00 AM to 10:30 AM', 'duration_hours' => 1, 'duration_minutes' => 30,
            'status' => 'pending', 'booking_code' => 'PWA10001', 'public_token' => fake()->uuid(), 'total_price' => 100,
        ]);

        $this->get('/app/barber-house')->assertOk();
        $this->get('/app/urban-cut')->assertForbidden();
        $this->get("/app/barber-house/bookings/{$booking->id}")
            ->assertOk()
            ->assertSee('Detalle de cita')
            ->assertSee('Guest Client')
            ->assertSee('Confirmar cita');
        $this->get("/app/urban-cut/bookings/{$booking->id}")->assertForbidden();
        $this->post("/app/urban-cut/bookings/{$booking->id}/confirm")->assertForbidden();

        // Reschedule only works while pending — the same rule the native API
        // enforces in BookingController::rebook() — and it replaces the
        // booking with a new row (linked via rebooked_from_booking_id)
        // instead of mutating the original in place.
        $this->post("/app/barber-house/bookings/{$booking->id}/reschedule", [
            'date' => now()->addDays(2)->toDateString(),
            'time' => '10:30',
        ])->assertRedirect();
        $this->assertDatabaseHas('bookings', ['id' => $booking->id, 'status' => 'cancelled']);
        $rescheduled = Booking::where('rebooked_from_booking_id', $booking->id)->firstOrFail();
        $this->assertSame('pending', $rescheduled->status);

        $this->post("/app/barber-house/bookings/{$rescheduled->id}/confirm")
            ->assertRedirect();
        $this->assertDatabaseHas('bookings', ['id' => $rescheduled->id, 'status' => 'confirmed']);
        $this->post("/app/barber-house/bookings/{$rescheduled->id}/cancel")->assertRedirect();
        $this->assertDatabaseHas('bookings', ['id' => $rescheduled->id, 'status' => 'cancelled']);
        $this->get('/app/barber-house/agenda')->assertOk()->assertSee('Agenda');
        $this->get('/app/barber-house/services')->assertOk()->assertSee('Classic cut');
        $this->get('/app/barber-house/staff')->assertOk()->assertSee('Barber House Barber');
        $this->get('/app/barber-house/share')->assertOk()->assertSee('/b/barber-house');
        $this->post('/app/barber-house/services', [
            'name' => 'Beard trim', 'price_per_hour' => 80, 'duration_minutes' => 20,
            'open_hour' => '09:00', 'close_hour' => '18:00', 'status' => 'active',
        ])->assertRedirect();
        $newService = Court::where('business_id', $business->id)->where('name', 'Beard trim')->firstOrFail();
        $this->post('/app/barber-house/staff', [
            'name' => 'New Barber', 'phone' => '5555', 'service_ids' => [$newService->id],
        ])->assertRedirect();
        $this->assertDatabaseHas('staff', ['business_id' => $business->id, 'name' => 'New Barber']);
        $this->assertNotSame($business->id, $otherBusiness->id);
    }

    public function test_manifest_and_service_worker_are_available(): void
    {
        [$business] = $this->catalog('barber-house', 'Barber House', 'Classic cut');
        $business->update(['branding_config' => ['assets' => ['app_icon' => 'assets/branding/logo_transparent.png']]]);

        $this->get('/b/barber-house/manifest.webmanifest')
            ->assertOk()
            ->assertHeader('content-type', 'application/manifest+json')
            ->assertJsonPath('start_url', url('/b/'.$business->slug))
            ->assertJsonPath('display', 'standalone')
            ->assertJsonCount(2, 'icons');
        $this->get('/service-worker.js')->assertOk()->assertSee('addEventListener');
        $this->get('/pwa/barber-house/icon-192.svg')
            ->assertOk()
            ->assertHeader('content-type', 'image/svg+xml')
            ->assertSee('data:image/png;base64', false);
    }

    private function catalog(string $slug, string $name, string $serviceName): array
    {
        $business = Business::create(['name' => $name, 'slug' => $slug, 'business_type' => 'barbershop', 'status' => 'active']);
        $service = Court::create([
            'business_id' => $business->id, 'name' => $serviceName, 'address' => 'Guatemala',
            'duration_hours' => 1, 'duration_minutes' => 30, 'price_per_hour' => 100,
            'open_hour' => '10:00', 'close_hour' => '12:00', 'rating' => 5, 'status' => 'active',
        ]);
        $role = StaffRole::create(['name' => 'Barber', 'slug' => 'barber-'.fake()->unique()->word(), 'description' => 'Barber']);
        $staff = Staff::create([
            'business_id' => $business->id, 'staff_role_id' => $role->id, 'name' => $name.' Barber', 'is_active' => true,
        ]);
        StaffService::create(['staff_id' => $staff->id, 'court_id' => $service->id, 'is_primary' => true]);

        return [$business, $service, $staff];
    }
}
