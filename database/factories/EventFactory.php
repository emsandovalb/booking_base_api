<?php

namespace Database\Factories;

use App\Models\Event;
use Illuminate\Database\Eloquent\Factories\Factory;

class EventFactory extends Factory
{
    protected $model = Event::class;

    public function definition(): array
    {
        return [
            'title' => 'Copa '.fake()->city(),
            'description' => fake()->sentence(12),
            'date' => now()->addDays(rand(1, 30)),
            'price' => fake()->randomFloat(2, 0, 200),
            'location' => fake()->address(),
        ];
    }
}

