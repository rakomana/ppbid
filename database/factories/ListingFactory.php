<?php

namespace Database\Factories;

use App\Models\Listing;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Listing>
 */
class ListingFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title' => ucfirst(fake()->words(3, true)),
            'description' => fake()->optional()->paragraph(),
            'min_price' => fake()->randomElement([100, 250, 500, 1000, 2500]),
            'quantity' => fake()->numberBetween(1, 20),
            'ends_at' => now()->addDay(),
        ];
    }

    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'ends_at' => now()->subMinute(),
        ]);
    }

    public function closed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Listing::STATUS_CLOSED,
            'ends_at' => now()->subMinute(),
        ]);
    }
}
