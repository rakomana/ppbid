<?php

use App\Actions\CloseListing;
use App\Livewire\Admin\Listings\Form;
use App\Models\Bid;
use App\Models\Listing;
use App\Models\User;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

/*
|--------------------------------------------------------------------------
| Admin access
|--------------------------------------------------------------------------
*/

it('returns 403 to non-admins on admin routes', function (Closure $uri) {
    $this->actingAs(bidder())->get($uri())->assertForbidden();
})->with([
    'admin home' => [fn () => '/admin'],
    'listings index' => [fn () => '/admin/listings'],
    'create form' => [fn () => '/admin/listings/create'],
    'edit form' => [fn () => '/admin/listings/'.Listing::factory()->create()->id.'/edit'],
]);

it('lets an admin create a listing', function () {
    $this->actingAs(admin());

    Livewire::test(Form::class)
        ->set('title', 'Roof sheets')
        ->set('min_price', '4200')
        ->set('quantity', '25')
        ->set('ends_at', now()->addDay()->format('Y-m-d\TH:i'))
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect(route('admin.listings.index'));

    expect(Listing::sole())
        ->title->toBe('Roof sheets')
        ->min_price->toBe('4200.00')
        ->quantity->toBe(25)
        ->status->toBe(Listing::STATUS_ACTIVE);
});

it('rejects a listing that ends in the past', function () {
    $this->actingAs(admin());

    Livewire::test(Form::class)
        ->set('title', 'Roof sheets')
        ->set('min_price', '4200')
        ->set('quantity', '25')
        ->set('ends_at', now()->subMinute()->format('Y-m-d\TH:i'))
        ->call('save')
        ->assertHasErrors(['ends_at' => 'after']);

    expect(Listing::count())->toBe(0);
});

/*
|--------------------------------------------------------------------------
| Bidding rules
|--------------------------------------------------------------------------
*/

it('rejects a bid below the minimum price', function () {
    $listing = Listing::factory()->create(['min_price' => 500]);

    expect(fn () => placeBid(bidder(), $listing, 499.99))
        ->toThrow(ValidationException::class, 'Your bid must be at least the minimum price of R500.00.');

    expect(Bid::count())->toBe(0);
});

it('rejects a bid below the current top bid plus the increment', function () {
    $listing = Listing::factory()->create(['min_price' => 500]);
    placeBid(bidder(), $listing, 1000);

    expect(fn () => placeBid(bidder(), $listing, 1009.99))
        ->toThrow(ValidationException::class, 'Your bid must be at least R1,010.00');

    expect(placeBid(bidder(), $listing, 1010)->amount)->toBe('1010.00');
});

it('rejects a bid after expiry, even before the listing is closed', function () {
    $listing = Listing::factory()->create(['min_price' => 100, 'ends_at' => now()->addMinute()]);
    $this->travel(2)->minutes();

    expect($listing->fresh()->status)->toBe(Listing::STATUS_ACTIVE)
        ->and(fn () => placeBid(bidder(), $listing, 500))
        ->toThrow(ValidationException::class, 'This listing has closed.');
});

it('does not let an admin bid', function () {
    $listing = Listing::factory()->create(['min_price' => 100]);

    expect(fn () => placeBid(admin(), $listing, 100))
        ->toThrow(ValidationException::class, "Admins can't place bids.");

    expect(Bid::count())->toBe(0);
});

it('does not let the highest bidder outbid themselves', function () {
    $listing = Listing::factory()->create(['min_price' => 100]);
    $user = bidder();
    placeBid($user, $listing, 100);

    expect(fn () => placeBid($user, $listing, 500))
        ->toThrow(ValidationException::class, 'You are already the highest bidder.');

    expect(Bid::count())->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Closing auctions
|--------------------------------------------------------------------------
*/

it('closes an expired listing and records the highest bidder as winner', function () {
    $listing = Listing::factory()->create(['min_price' => 100, 'ends_at' => now()->addHour()]);
    $loser = bidder();
    $winner = bidder();
    placeBid($loser, $listing, 100);
    placeBid($winner, $listing, 250);

    $this->travel(2)->hours();
    $this->artisan('auctions:close')->assertSuccessful();

    expect($listing->fresh())
        ->status->toBe(Listing::STATUS_CLOSED)
        ->winner_id->toBe($winner->id);
});

it('closes an expired listing with no bids and no winner', function () {
    $listing = Listing::factory()->expired()->create();

    $this->artisan('auctions:close')->assertSuccessful();

    expect($listing->fresh())
        ->status->toBe(Listing::STATUS_CLOSED)
        ->winner_id->toBeNull();
});

it('leaves running and already-closed listings alone', function () {
    $running = Listing::factory()->create(['ends_at' => now()->addHour()]);
    $originalWinner = bidder();
    $closed = Listing::factory()->closed()->create(['winner_id' => $originalWinner->id]);

    $this->artisan('auctions:close')->assertSuccessful();

    expect($running->fresh()->status)->toBe(Listing::STATUS_ACTIVE)
        ->and($closed->fresh()->winner_id)->toBe($originalWinner->id);
});

/*
|--------------------------------------------------------------------------
| Bidder numbers and privacy
|--------------------------------------------------------------------------
*/

it('formats bidder numbers as #001', function (int $id, string $expected) {
    expect(User::bidderNumberFor($id))->toBe($expected);
})->with([
    [1, '#001'],
    [14, '#014'],
    [999, '#999'],
    [1234, '#1234'],
]);

it('shows bidder numbers, never names or emails, on listing pages', function () {
    $alice = bidder(['name' => 'Alice Hidden', 'email' => 'alice-hidden@example.com']);
    $bob = bidder(['name' => 'Bob Hidden', 'email' => 'bob-hidden@example.com']);
    $viewer = bidder(['name' => 'Viewer Person']);

    $open = Listing::factory()->create(['min_price' => 100]);
    placeBid($alice, $open, 100);
    placeBid($bob, $open, 150);

    $closed = Listing::factory()->create(['min_price' => 100]);
    placeBid($alice, $closed, 100);
    app(CloseListing::class)->handle($closed);

    $this->actingAs($viewer);

    foreach (['/listings', "/listings/{$open->id}", "/listings/{$closed->id}", '/my-bids', '/dashboard'] as $uri) {
        $this->get($uri)
            ->assertOk()
            ->assertDontSee('Alice Hidden')
            ->assertDontSee('alice-hidden@example.com')
            ->assertDontSee('Bob Hidden')
            ->assertDontSee('bob-hidden@example.com');
    }

    $this->get("/listings/{$open->id}")
        ->assertSee('Bidder '.$alice->bidder_number)
        ->assertSee('Bidder '.$bob->bidder_number);

    $this->get('/listings')->assertSee('Won by')->assertSee('Bidder '.$alice->bidder_number);

    expect($alice->bidder_number)->toMatch('/^#\d{3,}$/');
});
