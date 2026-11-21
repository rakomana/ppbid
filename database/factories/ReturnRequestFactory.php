<?php

namespace Database\Factories;

use App\Models\Listing;
use App\Models\ReturnRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ReturnRequest>
 */
class ReturnRequestFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'listing_id' => Listing::factory()->closed(),
            'user_id' => User::factory(),
            'quantity_requested' => 1,
            'reason' => 'fails_test',
            'details' => null,
        ];
    }
}
