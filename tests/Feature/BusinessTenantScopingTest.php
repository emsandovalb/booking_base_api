<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\Court;
use App\Models\Staff;
use App\Models\StaffRole;
use App\Models\StaffService;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BusinessTenantScopingTest extends TestCase
{
    use RefreshDatabase;

    public function test_resources_can_be_filtered_by_business_slug(): void
    {
        $businessA = $this->createBusiness('barberia-tres-amigos', 'Barberia Tres Amigos');
        $businessB = $this->createBusiness('salon-aurora', 'Salon Aurora');

        $resourceA = $this->createResource($businessA, 'Tres Amigos Corte');
        $resourceB = $this->createResource($businessB, 'Aurora Facial');
        $legacyResource = Court::factory()->create();

        $response = $this->getJson('/api/v1/resources?business_slug=barberia-tres-amigos');

        $response->assertOk();
        $response->assertJsonFragment(['id' => $resourceA->id]);
        $response->assertJsonMissing(['id' => $resourceB->id]);
        $response->assertJsonMissing(['id' => $legacyResource->id]);
    }

    public function test_staff_can_be_filtered_by_business_slug(): void
    {
        $businessA = $this->createBusiness('barberia-tres-amigos', 'Barberia Tres Amigos');
        $businessB = $this->createBusiness('salon-aurora', 'Salon Aurora');
        $role = $this->createRole('barber', 'Barber');

        $staffA = $this->createStaff($businessA, $role, 'Carlos Barber');
        $staffB = $this->createStaff($businessB, $role, 'Alicia Stylist');
        $legacyStaff = Staff::create([
            'staff_role_id' => $role->id,
            'name' => 'Legacy Barber',
            'email' => 'legacy@example.com',
            'is_active' => true,
        ]);

        $response = $this->getJson('/api/v1/staff', [
            'X-Business-Slug' => 'barberia-tres-amigos',
        ]);

        $response->assertOk();
        $response->assertJsonFragment(['id' => $staffA->id]);
        $response->assertJsonMissing(['id' => $staffB->id]);
        $response->assertJsonMissing(['id' => $legacyStaff->id]);
    }

    public function test_resource_staff_lookup_respects_business(): void
    {
        $businessA = $this->createBusiness('barberia-tres-amigos', 'Barberia Tres Amigos');
        $businessB = $this->createBusiness('salon-aurora', 'Salon Aurora');
        $role = $this->createRole('barber', 'Barber');

        $resourceA = $this->createResource($businessA, 'Tres Amigos Corte');
        $resourceB = $this->createResource($businessB, 'Aurora Facial');
        $staffA = $this->createStaff($businessA, $role, 'Carlos Barber');
        $staffB = $this->createStaff($businessB, $role, 'Alicia Stylist');

        StaffService::create([
            'staff_id' => $staffA->id,
            'court_id' => $resourceA->id,
            'is_primary' => true,
        ]);
        StaffService::create([
            'staff_id' => $staffB->id,
            'court_id' => $resourceB->id,
            'is_primary' => true,
        ]);

        $response = $this->getJson('/api/v1/resources/' . $resourceA->id . '/staff', [
            'X-Business-Slug' => 'barberia-tres-amigos',
        ]);

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.id', $staffA->id);
        $response->assertJsonMissing(['id' => $staffB->id]);
    }

    public function test_create_resource_with_business_slug_assigns_business_id(): void
    {
        $admin = $this->actingAsAdmin();
        $business = $this->createBusiness('barberia-tres-amigos', 'Barberia Tres Amigos');
        $this->assignBusinessMembership($admin, $business, 'owner');

        $response = $this->postJson('/api/v1/resources', [
            'name' => 'Nuevo Servicio',
            'address' => 'Puntarenas, Costa Rica',
            'price_per_hour' => 7500,
            'rating' => 4.5,
        ], [
            'X-Business-Slug' => $business->slug,
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('courts', [
            'name' => 'Nuevo Servicio',
            'business_id' => $business->id,
        ]);
    }

    public function test_create_staff_with_business_slug_assigns_business_id(): void
    {
        $admin = $this->actingAsAdmin();
        $business = $this->createBusiness('barberia-tres-amigos', 'Barberia Tres Amigos');
        $this->assignBusinessMembership($admin, $business, 'admin');
        $role = $this->createRole('barber', 'Barber');

        $response = $this->postJson('/api/v1/staff', [
            'name' => 'Nuevo Barbero',
            'staff_role_id' => $role->id,
            'is_active' => true,
        ], [
            'X-Business-Slug' => $business->slug,
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('staff', [
            'name' => 'Nuevo Barbero',
            'business_id' => $business->id,
        ]);
    }

    public function test_no_business_slug_keeps_compatibility(): void
    {
        $role = $this->createRole('barber', 'Barber');
        $legacyResource = Court::factory()->create();
        $legacyStaff = Staff::create([
            'staff_role_id' => $role->id,
            'name' => 'Legacy Barber',
            'email' => 'legacy@example.com',
            'is_active' => true,
        ]);

        $resources = $this->getJson('/api/v1/resources');
        $staff = $this->getJson('/api/v1/staff');

        $resources->assertOk();
        $staff->assertOk();
        $resources->assertJsonFragment(['id' => $legacyResource->id]);
        $staff->assertJsonFragment(['id' => $legacyStaff->id]);
    }

    public function test_admin_can_assign_staff_to_resource_in_same_business(): void
    {
        $admin = $this->actingAsAdmin();
        $business = $this->createBusiness('barberia-tres-amigos', 'Barberia Tres Amigos');
        $this->assignBusinessMembership($admin, $business, 'admin');
        $role = $this->createRole('barber', 'Barber');
        $staff = $this->createStaff($business, $role, 'Carlos Barber');
        $resource = $this->createResource($business, 'Tres Amigos Corte');

        $response = $this->postJson('/api/v1/staff/' . $staff->id . '/services', [
            'resource_id' => $resource->id,
        ], [
            'X-Business-Slug' => $business->slug,
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('staff_services', [
            'staff_id' => $staff->id,
            'court_id' => $resource->id,
        ]);
    }

    public function test_cannot_assign_staff_from_tres_amigos_to_salon_aurora_resource(): void
    {
        $admin = $this->actingAsAdmin();
        $tresAmigos = $this->createBusiness('barberia-tres-amigos', 'Barberia Tres Amigos');
        $salonAurora = $this->createBusiness('salon-aurora', 'Salon Aurora');
        $role = $this->createRole('barber', 'Barber');
        $staff = $this->createStaff($tresAmigos, $role, 'Carlos Barber');
        $resource = $this->createResource($salonAurora, 'Aurora Facial');

        $response = $this->postJson('/api/v1/staff/' . $staff->id . '/services', [
            'resource_id' => $resource->id,
        ], [
            'X-Business-Slug' => $tresAmigos->slug,
        ]);

        $response->assertStatus(403);
        $this->assertDatabaseMissing('staff_services', [
            'staff_id' => $staff->id,
            'court_id' => $resource->id,
        ]);
    }

    public function test_cannot_assign_salon_aurora_staff_to_tres_amigos_resource(): void
    {
        $admin = $this->actingAsAdmin();
        $tresAmigos = $this->createBusiness('barberia-tres-amigos', 'Barberia Tres Amigos');
        $salonAurora = $this->createBusiness('salon-aurora', 'Salon Aurora');
        $role = $this->createRole('barber', 'Barber');
        $staff = $this->createStaff($salonAurora, $role, 'Alicia Stylist');
        $resource = $this->createResource($tresAmigos, 'Tres Amigos Corte');

        $response = $this->postJson('/api/v1/staff/' . $staff->id . '/services', [
            'resource_id' => $resource->id,
        ], [
            'X-Business-Slug' => $salonAurora->slug,
        ]);

        $response->assertStatus(403);
        $this->assertDatabaseMissing('staff_services', [
            'staff_id' => $staff->id,
            'court_id' => $resource->id,
        ]);
    }

    public function test_no_business_slug_keeps_legacy_compatibility_for_staff_assignment(): void
    {
        $admin = $this->actingAsAdmin();
        $tresAmigos = $this->createBusiness('barberia-tres-amigos', 'Barberia Tres Amigos');
        $salonAurora = $this->createBusiness('salon-aurora', 'Salon Aurora');
        $role = $this->createRole('barber', 'Barber');
        $staff = $this->createStaff($tresAmigos, $role, 'Carlos Barber');
        $resource = $this->createResource($salonAurora, 'Aurora Facial');

        $response = $this->postJson('/api/v1/staff/' . $staff->id . '/services', [
            'resource_id' => $resource->id,
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('staff_services', [
            'staff_id' => $staff->id,
            'court_id' => $resource->id,
        ]);
    }

    private function createBusiness(string $slug, string $name): Business
    {
        return Business::create([
            'name' => $name,
            'slug' => $slug,
            'business_type' => 'barbershop',
            'status' => 'active',
        ]);
    }

    private function createRole(string $slug, string $name): StaffRole
    {
        return StaffRole::create([
            'name' => $name,
            'slug' => $slug,
            'description' => $name,
        ]);
    }

    private function createResource(Business $business, string $name): Court
    {
        return Court::create([
            'business_id' => $business->id,
            'name' => $name,
            'address' => 'Puntarenas, Costa Rica',
            'price_per_hour' => 5000,
            'rating' => 4.8,
            'status' => 'active',
        ]);
    }

    private function createStaff(Business $business, StaffRole $role, string $name): Staff
    {
        return Staff::create([
            'business_id' => $business->id,
            'staff_role_id' => $role->id,
            'name' => $name,
            'email' => strtolower(str_replace(' ', '.', $name)) . '@example.com',
            'is_active' => true,
        ]);
    }

    private function actingAsAdmin(): User
    {
        $admin = User::factory()->create();
        $admin->forceFill(['role' => 'admin'])->save();
        Sanctum::actingAs($admin);

        return $admin;
    }

    private function assignBusinessMembership(User $user, Business $business, string $role = 'admin'): void
    {
        $business->users()->syncWithoutDetaching([
            $user->id => [
                'role' => $role,
                'status' => 'active',
                'accepted_at' => now(),
                'metadata' => [
                    'test' => true,
                ],
            ],
        ]);
    }
}
