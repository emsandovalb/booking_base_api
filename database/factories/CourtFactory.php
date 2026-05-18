<?php

namespace Database\Factories;

use App\Models\Court;
use Illuminate\Database\Eloquent\Factories\Factory;

class CourtFactory extends Factory
{
    protected $model = Court::class;

    public function definition(): array
    {
        $resourceName = fake()->randomElement([
            'Meeting Room',
            'Consultation Room',
            'Service Station',
            'Training Space',
            'Workshop Space',
            'Event Room',
        ]);

        return [
            'name' => $resourceName . ' ' . fake()->unique()->numberBetween(1, 99),
            'address' => fake()->address(),
            'price_per_hour' => fake()->randomFloat(2, 10, 100),
            'rating' => fake()->randomFloat(1, 3, 5),
            'facilities' => ['WiFi', 'Parking', 'Waiting area'],
            'images' => [
                'https://picsum.photos/seed/'.fake()->uuid().'/600/400',
                'https://picsum.photos/seed/'.fake()->uuid().'/600/400',
            ],
            'status' => 'active',
        ];
    }
}
