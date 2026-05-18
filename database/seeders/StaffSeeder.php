<?php

namespace Database\Seeders;

use App\Models\Court;
use App\Models\Staff;
use App\Models\StaffRole;
use App\Models\StaffService;
use App\Models\User;
use Illuminate\Database\Seeder;

class StaffSeeder extends Seeder
{
    public function run(): void
    {
        $roles = StaffRole::query()->get()->keyBy('slug');
        $courts = Court::query()->orderBy('id')->get()->values();
        $admin = User::query()->where('email', 'demo@example.com')->first();

        $staffMembers = [
            [
                'name' => 'Maria Lopez',
                'role' => 'consultant',
                'user_id' => $admin?->id,
                'email' => 'maria.lopez@example.com',
                'phone' => '+1-555-0101',
                'bio' => 'Customer-facing consultant for premium bookings.',
                'services' => [0],
            ],
            [
                'name' => 'John Barber',
                'role' => 'barber',
                'user_id' => null,
                'email' => 'john.barber@example.com',
                'phone' => '+1-555-0102',
                'bio' => 'Specialist in service coordination and grooming sessions.',
                'services' => [0, 1],
            ],
            [
                'name' => 'Ana Trainer',
                'role' => 'trainer',
                'user_id' => null,
                'email' => 'ana.trainer@example.com',
                'phone' => '+1-555-0103',
                'bio' => 'Focused on training space scheduling and support.',
                'services' => [2],
            ],
            [
                'name' => 'Sophia Consultant',
                'role' => 'consultant',
                'user_id' => null,
                'email' => 'sophia.consultant@example.com',
                'phone' => '+1-555-0104',
                'bio' => 'Consulting support across multiple service spaces.',
                'services' => [1, 3],
            ],
        ];

        foreach ($staffMembers as $member) {
            $role = $roles[$member['role']] ?? null;
            if (!$role) {
                continue;
            }

            $staff = Staff::updateOrCreate(
                ['name' => $member['name']],
                [
                    'user_id' => $member['user_id'],
                    'staff_role_id' => $role->id,
                    'email' => $member['email'],
                    'phone' => $member['phone'],
                    'bio' => $member['bio'],
                    'is_active' => true,
                ]
            );

            foreach ($member['services'] as $index) {
                $court = $courts->get($index);
                if (!$court) {
                    continue;
                }

                StaffService::updateOrCreate(
                    [
                        'staff_id' => $staff->id,
                        'court_id' => $court->id,
                    ],
                    [
                        'is_primary' => $index === ($member['services'][0] ?? null),
                    ]
                );
            }
        }
    }
}
