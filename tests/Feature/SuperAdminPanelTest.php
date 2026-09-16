<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Business;
use App\Models\Court;
use App\Models\Staff;
use App\Models\StaffRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SuperAdminPanelTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_access_super_admin(): void
    {
        $response = $this->get('/super-admin');

        $response->assertRedirect('/login');
    }

    public function test_non_admin_cannot_access_super_admin(): void
    {
        $user = User::factory()->create(['name' => 'Regular User']);
        $user->forceFill(['role' => 'user'])->save();

        $this->actingAs($user)
            ->get('/super-admin')
            ->assertForbidden();
    }

    public function test_admin_can_view_dashboard(): void
    {
        $admin = $this->makeAdmin();
        Business::factory()->count(2)->create();
        $user = User::factory()->create();
        $court = Court::factory()->create();
        Booking::query()->create([
            'user_id' => $user->id,
            'court_id' => $court->id,
            'business_id' => Business::query()->first()->id,
            'date' => now(),
            'time_slot' => '10:00 - 11:00',
            'status' => 'confirmed',
            'booking_code' => 'BOOK-ADMIN-001',
            'total_price' => 50,
        ]);

        $this->actingAs($admin)
            ->get('/super-admin')
            ->assertOk()
            ->assertSee('Dashboard')
            ->assertSee('Total negocios');
    }

    public function test_admin_can_view_businesses_list(): void
    {
        $admin = $this->makeAdmin();
        Business::factory()->create(['name' => 'Sala Uno']);
        Business::factory()->create(['name' => 'Sala Dos']);

        $this->actingAs($admin)
            ->get('/super-admin/businesses')
            ->assertOk()
            ->assertSee('Sala Uno')
            ->assertSee('Sala Dos');
    }

    public function test_guest_cannot_access_business_workspace(): void
    {
        $business = Business::factory()->create(['name' => 'Workspace Test']);

        $this->get(route('super-admin.businesses.workspace', $business))
            ->assertRedirect('/login');
    }

    public function test_non_admin_cannot_access_business_workspace(): void
    {
        $user = User::factory()->create(['name' => 'Regular User']);
        $user->forceFill(['role' => 'user'])->save();
        $business = Business::factory()->create(['name' => 'Workspace Test']);

        $this->actingAs($user)
            ->get(route('super-admin.businesses.workspace', $business))
            ->assertForbidden();
    }

    public function test_admin_can_open_business_workspace(): void
    {
        $admin = $this->makeAdmin();
        $business = $this->createWorkspaceBusiness();

        $this->actingAs($admin)
            ->get(route('super-admin.businesses.workspace', $business))
            ->assertOk()
            ->assertSee('Workspace')
            ->assertSee('Overview')
            ->assertSee($business->name)
            ->assertSee('Reservations')
            ->assertSee('Services')
            ->assertSee('Staff')
            ->assertSee('Members')
            ->assertSee('Manage Members')
            ->assertSee('Total Members')
            ->assertSee('Active Members')
            ->assertSee('Pending')
            ->assertSee('Admins')
            ->assertSee('Reviews')
            ->assertSee('Gallery Photos');
    }

    public function test_workspace_shows_correct_business(): void
    {
        $admin = $this->makeAdmin();
        $tresAmigos = $this->createWorkspaceBusiness([
            'name' => 'Barberia Tres Amigos',
            'slug' => 'barberia-tres-amigos',
        ]);
        $aurora = Business::factory()->create([
            'name' => 'Salon Aurora',
            'slug' => 'salon-aurora',
        ]);

        $this->actingAs($admin)
            ->get(route('super-admin.businesses.workspace', $tresAmigos))
            ->assertOk()
            ->assertSee('Barberia Tres Amigos')
            ->assertDontSee('Salon Aurora');
    }

    public function test_admin_can_create_business(): void
    {
        $admin = $this->makeAdmin();

        $payload = $this->wizardPayload([
            'business' => [
                'slug' => 'nuevo-espacio',
                'name' => 'Nuevo Espacio',
            ],
            'brand' => [
                'app_name' => 'Nuevo Espacio',
                'display_name' => 'NUEVO ESPACIO',
            ],
            'owner' => [
                'full_name' => 'Owner Nuevo',
                'email' => 'owner-nuevo@example.com',
                'password' => 'Password123!',
                'password_confirmation' => 'Password123!',
            ],
        ]);

        $response = $this->actingAs($admin)->post('/super-admin/businesses', $payload);

        $business = Business::query()->where('slug', 'nuevo-espacio')->firstOrFail();
        $owner = User::query()->where('email', 'owner-nuevo@example.com')->firstOrFail();

        $response->assertRedirect(route('super-admin.businesses.workspace', $business));
        $this->assertDatabaseHas('businesses', [
            'slug' => 'nuevo-espacio',
            'name' => 'Nuevo Espacio',
            'status' => 'active',
        ]);
        $this->assertSame('Nuevo Espacio', $business->name);
        $this->assertSame('Nuevo Espacio', data_get($business->app_config, 'identity.app_name'));
        $this->assertDatabaseHas('business_user', [
            'business_id' => $business->id,
            'user_id' => $owner->id,
            'role' => 'owner',
            'status' => 'active',
        ]);
        $this->assertNotNull($business->users()->first()?->pivot->accepted_at);
    }

    public function test_admin_can_edit_business(): void
    {
        $admin = $this->makeAdmin();
        $business = Business::factory()->create([
            'slug' => 'negocio-editar',
            'status' => 'active',
        ]);

        $payload = $this->businessPayload([
            'name' => 'Negocio Editado',
            'slug' => 'negocio-editado',
            'status' => 'inactive',
            'identity' => [
                'app_name' => 'Bemuss Editado',
                'display_name' => 'BEMUSS EDITADO',
                'short_name' => 'Editado',
                'tagline' => 'Nueva etiqueta',
                'subtitle' => 'Nueva subtítulo',
                'location_short' => 'Ciudad central',
                'location_full' => 'Ciudad central, Costa Rica',
            ],
            'contact' => [
                'email' => 'editado@example.com',
                'website' => 'https://example.com',
            ],
            'branding' => [
                'colors' => [
                    'primary' => '#112233',
                    'accent' => '#445566',
                ],
                'appearance' => [
                    'theme_preset' => 'elegant_light',
                ],
            ],
            'policies' => [
                'cancellation_window_hours' => 8,
                'cancellation_policy_text' => 'Editar política',
            ],
            'features' => [
                'show_staff' => false,
                'show_reviews' => true,
            ],
        ]);

        $response = $this->actingAs($admin)->put("/super-admin/businesses/{$business->id}", $payload);

        $response->assertRedirect(route('super-admin.businesses.show', $business));
        $business->refresh();

        $this->assertSame('Negocio Editado', $business->name);
        $this->assertSame('negocio-editado', $business->slug);
        $this->assertSame('inactive', $business->status);
        $this->assertSame('Bemuss Editado', data_get($business->app_config, 'identity.app_name'));
        $this->assertSame('#112233', data_get($business->branding_config, 'colors.primary'));
        $this->assertSame('#445566', data_get($business->branding_config, 'colors.accent'));
        $this->assertSame('light', data_get($business->branding_config, 'appearance.theme_mode'));
        $this->assertFalse(data_get($business->feature_config, 'features.show_staff'));
        $this->assertTrue(data_get($business->feature_config, 'features.show_reviews'));
    }

    public function test_admin_can_suspend_and_activate_business(): void
    {
        $admin = $this->makeAdmin();
        $business = Business::factory()->create(['status' => 'active']);

        $this->actingAs($admin)
            ->patch("/super-admin/businesses/{$business->id}/suspend")
            ->assertRedirect();

        $business->refresh();
        $this->assertSame('suspended', $business->status);

        $this->actingAs($admin)
            ->patch("/super-admin/businesses/{$business->id}/activate")
            ->assertRedirect();

        $business->refresh();
        $this->assertSame('active', $business->status);
    }

    public function test_business_slug_must_be_unique(): void
    {
        $admin = $this->makeAdmin();
        Business::factory()->create(['slug' => 'unique-slug']);

        $payload = $this->wizardPayload([
            'business' => [
                'slug' => 'unique-slug',
                'name' => 'Duplicado',
            ],
            'brand' => [
                'app_name' => 'Duplicado',
                'display_name' => 'DUPLICADO',
            ],
            'owner' => [
                'full_name' => 'Duplicado Owner',
                'email' => 'duplicado@example.com',
                'password' => 'Password123!',
                'password_confirmation' => 'Password123!',
            ],
        ]);

        $this->actingAs($admin)
            ->from('/super-admin/businesses/create')
            ->post('/super-admin/businesses', $payload)
            ->assertSessionHasErrors(['business.slug']);

        $this->assertDatabaseCount('businesses', 1);
    }

    private function makeAdmin(): User
    {
        $admin = User::factory()->create(['name' => 'Super Admin']);
        $admin->forceFill(['role' => 'admin', 'is_super_admin' => true])->save();

        return $admin;
    }

    private function createWorkspaceBusiness(array $overrides = []): Business
    {
        $business = Business::factory()->create(array_replace([
            'name' => 'Workspace Business',
            'slug' => 'workspace-business',
            'app_config' => [
                'identity' => [
                    'app_name' => 'Workspace App',
                    'display_name' => 'WORKSPACE APP',
                    'short_name' => 'Workspace',
                    'rating' => 4.8,
                    'review_count' => 27,
                ],
                'features' => [
                    'show_gallery' => true,
                    'show_reviews' => true,
                    'show_staff' => true,
                    'reservation_staff_selection' => true,
                    'admin_staff_management' => true,
                    'show_business_profile' => true,
                    'show_admin_dashboard' => true,
                ],
            ],
        ], $overrides));

        $courtOne = Court::factory()->create([
            'business_id' => $business->id,
            'name' => 'Service One',
            'images' => ['https://example.com/one.jpg', 'https://example.com/two.jpg'],
        ]);

        $courtTwo = Court::factory()->create([
            'business_id' => $business->id,
            'name' => 'Service Two',
            'images' => ['https://example.com/three.jpg'],
        ]);

        $staffRole = StaffRole::create([
            'name' => 'Stylist',
            'slug' => 'stylist',
            'description' => 'Workspace test role',
        ]);

        Staff::create([
            'business_id' => $business->id,
            'staff_role_id' => $staffRole->id,
            'name' => 'Alex Stylist',
            'email' => 'alex@example.com',
            'phone' => '+1 555 111 2222',
            'bio' => 'Senior stylist',
            'is_active' => true,
        ]);

        $memberA = User::factory()->create(['name' => 'Member One']);
        $memberB = User::factory()->create(['name' => 'Member Two']);
        $memberC = User::factory()->create(['name' => 'Member Three']);

        $business->users()->attach($memberA->id, ['role' => 'owner', 'status' => 'active']);
        $business->users()->attach($memberB->id, ['role' => 'admin', 'status' => 'active']);
        $business->users()->attach($memberC->id, ['role' => 'staff', 'status' => 'active']);

        Booking::query()->create([
            'user_id' => $memberA->id,
            'court_id' => $courtOne->id,
            'business_id' => $business->id,
            'date' => now(),
            'time_slot' => '10:00 - 11:00',
            'status' => 'confirmed',
            'booking_code' => 'BOOK-WORKSPACE-001',
            'total_price' => 50,
        ]);

        Booking::query()->create([
            'user_id' => $memberB->id,
            'court_id' => $courtTwo->id,
            'business_id' => $business->id,
            'date' => now(),
            'time_slot' => '11:00 - 12:00',
            'status' => 'confirmed',
            'booking_code' => 'BOOK-WORKSPACE-002',
            'total_price' => 75,
        ]);

        return $business->fresh();
    }

    private function businessPayload(array $overrides = []): array
    {
        $payload = [
            'name' => 'Bemuss Nuevo',
            'slug' => 'bemuss-nuevo',
            'legal_name' => 'Bemuss Nuevo S.A.',
            'business_type' => 'barbershop',
            'status' => 'active',
            'identity' => [
                'app_name' => 'Bemuss Nuevo',
                'display_name' => 'BEMUSS NUEVO',
                'short_name' => 'Nuevo',
                'tagline' => 'Experiencia premium',
                'subtitle' => 'Reserva y administra',
                'location_short' => 'San José',
                'location_full' => 'San José, Costa Rica',
            ],
            'contact' => [
                'phone' => '+506 8888-9999',
                'whatsapp' => '+506 8888-9999',
                'email' => 'hola@bemuss.test',
                'instagram' => '@bemuss',
                'website' => 'https://bemuss.test',
                'address' => 'San José, Costa Rica',
            ],
            'branding' => [
                'colors' => [
                    'primary' => '#D4A84F',
                    'accent' => '#B77A3E',
                ],
                'appearance' => [
                    'theme_preset' => 'barber_luxury',
                ],
            ],
            'policies' => [
                'cancellation_window_hours' => 6,
                'cancellation_policy_text' => 'Cancelar con 6 horas de anticipación.',
            ],
            'features' => [
                'show_staff' => true,
                'reservation_staff_selection' => true,
                'admin_staff_management' => true,
                'show_gallery' => true,
                'show_reviews' => true,
                'show_business_profile' => true,
                'show_admin_dashboard' => true,
            ],
        ];

        return array_replace_recursive($payload, $overrides);
    }

    private function wizardPayload(array $overrides = []): array
    {
        $payload = [
            'business' => [
                'name' => 'Bemuss Nuevo',
                'slug' => 'bemuss-nuevo',
                'legal_name' => 'Bemuss Nuevo S.A.',
                'business_type' => 'barbershop',
                'status' => 'active',
            ],
            'brand' => [
                'app_name' => 'Bemuss Nuevo',
                'display_name' => 'BEMUSS NUEVO',
                'short_name' => 'Nuevo',
                'tagline' => 'Experiencia premium',
                'subtitle' => 'Reserva y administra',
                'primary_color' => '#D4A84F',
                'secondary_color' => '#E8C36A',
                'background_color' => '#07111f',
            ],
            'contact' => [
                'phone' => '+506 8888-9999',
                'whatsapp' => '+506 8888-9999',
                'email' => 'hola@bemuss.test',
                'instagram' => '@bemuss',
                'facebook' => 'Bemuss Nuevo',
                'website' => 'https://bemuss.test',
                'address' => 'San José, Costa Rica',
                'country' => 'Costa Rica',
                'city' => 'San José',
            ],
            'hours' => [
                'cancellation_window_hours' => 6,
                'cancellation_policy_text' => 'Cancelar con 6 horas de anticipación.',
                'schedule' => [
                    'monday' => ['is_open' => '1', 'opens_at' => '09:00', 'closes_at' => '18:00'],
                    'tuesday' => ['is_open' => '1', 'opens_at' => '09:00', 'closes_at' => '18:00'],
                    'wednesday' => ['is_open' => '1', 'opens_at' => '09:00', 'closes_at' => '18:00'],
                    'thursday' => ['is_open' => '1', 'opens_at' => '09:00', 'closes_at' => '18:00'],
                    'friday' => ['is_open' => '1', 'opens_at' => '09:00', 'closes_at' => '18:00'],
                    'saturday' => ['is_open' => '1', 'opens_at' => '09:00', 'closes_at' => '18:00'],
                    'sunday' => ['is_open' => '0', 'opens_at' => '09:00', 'closes_at' => '18:00'],
                ],
            ],
            'owner' => [
                'full_name' => 'Owner Nuevo',
                'email' => 'owner-nuevo@example.com',
                'password' => 'Password123!',
                'password_confirmation' => 'Password123!',
            ],
            'features' => [
                'show_staff' => '1',
                'reservation_staff_selection' => '1',
                'admin_staff_management' => '1',
                'show_gallery' => '1',
                'show_reviews' => '1',
                'show_business_profile' => '1',
                'show_admin_dashboard' => '1',
            ],
        ];

        return array_replace_recursive($payload, $overrides);
    }
}
