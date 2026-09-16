<?php

namespace Database\Factories;

use App\Models\Business;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class BusinessFactory extends Factory
{
    protected $model = Business::class;

    public function definition(): array
    {
        $slug = Str::slug(fake()->unique()->company());
        $whiteLabel = config('white_label');

        return [
            'name' => fake()->company(),
            'slug' => $slug,
            'legal_name' => fake()->optional()->company().' S.A.',
            'business_type' => fake()->randomElement(['barbershop', 'salon', 'spa', 'clinic']),
            'status' => 'active',
            'app_config' => [
                'identity' => [
                    'app_name' => fake()->company(),
                    'display_name' => strtoupper(fake()->company()),
                    'short_name' => fake()->firstName(),
                    'tagline' => fake()->sentence(4),
                    'subtitle' => fake()->sentence(3),
                    'location_short' => fake()->city(),
                    'location_full' => fake()->address(),
                    'rating' => fake()->randomFloat(1, 3.5, 5),
                    'review_count' => fake()->numberBetween(0, 500),
                ],
                'policies' => $whiteLabel['policies'] ?? [],
            ],
            'contact_config' => [
                'contact' => [
                    'phone' => fake()->phoneNumber(),
                    'whatsapp' => fake()->phoneNumber(),
                    'email' => fake()->safeEmail(),
                    'instagram' => '@'.fake()->userName(),
                    'website' => fake()->url(),
                    'address' => fake()->address(),
                ],
            ],
            'branding_config' => [
                'colors' => $whiteLabel['colors'] ?? [],
            ],
            'feature_config' => [
                'features' => $whiteLabel['features'] ?? [],
            ],
            'metadata' => [
                'source' => 'factory',
            ],
        ];
    }
}
