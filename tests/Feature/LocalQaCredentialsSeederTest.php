<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\User;
use Database\Seeders\LocalQaCredentialsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class LocalQaCredentialsSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_idempotently_configures_only_the_requested_local_qa_credentials(): void
    {
        $jcStudio = $this->business('JC Studio', 'jc-studio');
        $tresAmigos = $this->business('Barbería Tres Amigos', 'barberia-tres-amigos');

        $jcOwner = User::factory()->create([
            'email' => 'macchie.23@gmail.com',
            'password' => Hash::make('original-unknown-password'),
            'role' => 'user',
        ]);
        $jcStudio->users()->attach($jcOwner->id, [
            'role' => 'owner',
            'status' => 'active',
            'accepted_at' => now(),
        ]);

        $barbershopOwner = User::factory()->create([
            'email' => 'barbershop.owner@example.com',
            'password' => Hash::make('password'),
            'role' => 'admin',
        ]);
        $demoOwner = User::factory()->create([
            'email' => 'demo@example.com',
            'password' => Hash::make('password'),
            'role' => 'admin',
        ]);
        $tresAmigos->users()->attach($barbershopOwner->id, ['role' => 'owner', 'status' => 'active']);
        $tresAmigos->users()->attach($demoOwner->id, ['role' => 'owner', 'status' => 'active']);

        $tresAmigosHashes = [
            $barbershopOwner->email => $barbershopOwner->password,
            $demoOwner->email => $demoOwner->password,
        ];

        $this->seed(LocalQaCredentialsSeeder::class);
        $this->seed(LocalQaCredentialsSeeder::class);

        $superAdmin = User::query()->where('email', 'admin@bemuss.local')->firstOrFail();
        $this->assertTrue($superAdmin->is_super_admin);
        $this->assertSame('admin', $superAdmin->role);
        $this->assertTrue(Hash::check('Bemuss123!', $superAdmin->password));
        $this->assertSame(1, User::query()->where('email', 'admin@bemuss.local')->count());
        $this->assertSame(0, $superAdmin->businesses()->count());

        $jcOwner->refresh();
        $this->assertTrue(Hash::check('Password123!', $jcOwner->password));
        $this->assertSame('user', $jcOwner->role);
        $this->assertFalse($jcOwner->is_super_admin);
        $this->assertSame('owner', $jcOwner->activeBusinessMembership($jcStudio)?->role);

        foreach ([$barbershopOwner, $demoOwner] as $owner) {
            $owner->refresh();
            $this->assertSame($tresAmigosHashes[$owner->email], $owner->password);
            $this->assertTrue(Hash::check('password', $owner->password));
            $this->assertSame('owner', $owner->activeBusinessMembership($tresAmigos)?->role);
        }

        $this->post('/login', [
            'email' => 'admin@bemuss.local',
            'password' => 'Bemuss123!',
        ])->assertRedirect(route('super-admin.dashboard'));
        $this->get('/super-admin')->assertOk();

        $this->postJson('/api/v1/auth/login', [
            'email' => 'macchie.23@gmail.com',
            'password' => 'Password123!',
        ], [
            'X-Business-Slug' => 'jc-studio',
        ])->assertOk()
            ->assertJsonPath('user.business_slug', 'jc-studio')
            ->assertJsonPath('user.business_role', 'owner')
            ->assertJsonPath('user.can_manage_business', true);

        $this->postJson('/api/v1/auth/login', [
            'email' => 'barbershop.owner@example.com',
            'password' => 'password',
        ], [
            'X-Business-Slug' => 'barberia-tres-amigos',
        ])->assertOk()
            ->assertJsonPath('user.business_slug', 'barberia-tres-amigos')
            ->assertJsonPath('user.business_role', 'owner');
    }

    private function business(string $name, string $slug): Business
    {
        return Business::query()->create([
            'name' => $name,
            'slug' => $slug,
            'business_type' => 'barbershop',
            'status' => 'active',
        ]);
    }
}
