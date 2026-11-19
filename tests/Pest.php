<?php

use App\Actions\PlaceBid;
use App\Models\Bid;
use App\Models\Listing;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| Pest tests in tests/Feature run on Laravel's TestCase with a fresh database.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Helpers
|--------------------------------------------------------------------------
*/

function bidder(array $attributes = []): User
{
    return User::factory()->create($attributes);
}

function admin(): User
{
    return User::factory()->admin()->create();
}

function placeBid(User $user, Listing $listing, float $amount): Bid
{
    return app(PlaceBid::class)->handle($user, $listing, $amount);
}
