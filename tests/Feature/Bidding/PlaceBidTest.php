<?php

namespace Tests\Feature\Bidding;

use App\Actions\PlaceBid;
use App\Livewire\Listings\Show;
use App\Models\Bid;
use App\Models\Listing;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class PlaceBidTest extends TestCase
{
    use RefreshDatabase;

    private function placeBid(User $user, Listing $listing, float $amount): Bid
    {
        return app(PlaceBid::class)->handle($user, $listing, $amount);
    }

    private function assertRejected(callable $callback, string $expectedMessage): void
    {
        try {
            $callback();
            $this->fail('Expected the bid to be rejected.');
        } catch (ValidationException $e) {
            $this->assertStringContainsString($expectedMessage, $e->errors()['amount'][0]);
        }
    }

    // --- PlaceBid action ---------------------------------------------------

    public function test_first_bid_at_minimum_price_is_accepted(): void
    {
        $listing = Listing::factory()->create(['min_price' => 500]);
        $user = User::factory()->create();

        $bid = $this->placeBid($user, $listing, 500);

        $this->assertSame('500.00', $bid->amount);
        $this->assertSame($user->id, $bid->user_id);
        $this->assertSame($listing->id, $bid->listing_id);
    }

    public function test_first_bid_below_minimum_price_is_rejected(): void
    {
        $listing = Listing::factory()->create(['min_price' => 500]);

        $this->assertRejected(
            fn () => $this->placeBid(User::factory()->create(), $listing, 499.99),
            'at least the minimum price of R500.00',
        );
        $this->assertSame(0, Bid::count());
    }

    public function test_next_bid_must_beat_top_bid_by_the_increment(): void
    {
        $listing = Listing::factory()->create(['min_price' => 500]);
        $this->placeBid(User::factory()->create(), $listing, 1000);
        $other = User::factory()->create();

        $this->assertRejected(fn () => $this->placeBid($other, $listing, 1009.99), 'at least R1,010.00');

        $this->assertSame('1010.00', $this->placeBid($other, $listing, 1010)->amount);
    }

    public function test_increment_is_configurable(): void
    {
        config(['auction.min_increment' => 50]);
        $listing = Listing::factory()->create(['min_price' => 100]);
        $this->placeBid(User::factory()->create(), $listing, 100);

        $this->assertRejected(fn () => $this->placeBid(User::factory()->create(), $listing, 149), 'at least R150.00');
    }

    public function test_admins_cannot_bid(): void
    {
        $listing = Listing::factory()->create(['min_price' => 100]);

        $this->assertRejected(fn () => $this->placeBid(User::factory()->admin()->create(), $listing, 100), "Admins can't place bids");
        $this->assertSame(0, Bid::count());
    }

    public function test_cannot_bid_on_a_closed_listing(): void
    {
        $listing = Listing::factory()->closed()->create(['min_price' => 100]);

        $this->assertRejected(fn () => $this->placeBid(User::factory()->create(), $listing, 100), 'This listing has closed');
    }

    public function test_cannot_bid_after_expiry_even_if_not_yet_closed(): void
    {
        $listing = Listing::factory()->expired()->create(['min_price' => 100]);

        $this->assertRejected(fn () => $this->placeBid(User::factory()->create(), $listing, 100), 'This listing has closed');
    }

    public function test_listing_state_is_rechecked_not_taken_from_the_stale_model(): void
    {
        $listing = Listing::factory()->create(['min_price' => 100]);
        $stale = Listing::find($listing->id);

        // Someone else bids, then the listing closes, after our copy was loaded.
        $this->placeBid(User::factory()->create(), $listing, 100);
        $listing->forceFill(['status' => Listing::STATUS_CLOSED])->save();

        $this->assertRejected(fn () => $this->placeBid(User::factory()->create(), $stale, 500), 'This listing has closed');
    }

    public function test_highest_bidder_cannot_outbid_themselves(): void
    {
        $listing = Listing::factory()->create(['min_price' => 100]);
        $user = User::factory()->create();
        $this->placeBid($user, $listing, 100);

        $this->assertRejected(fn () => $this->placeBid($user, $listing, 500), 'You are already the highest bidder');
        $this->assertSame(1, Bid::count());
    }

    public function test_outbid_user_can_bid_again(): void
    {
        $listing = Listing::factory()->create(['min_price' => 100]);
        $alice = User::factory()->create();
        $bob = User::factory()->create();

        $this->placeBid($alice, $listing, 100);
        $this->placeBid($bob, $listing, 110);
        $this->placeBid($alice, $listing, 120);

        $this->assertSame($alice->id, $listing->highestBid()->user_id);
    }

    // --- Bid form on the detail page --------------------------------------

    public function test_form_is_prefilled_with_minimum_next_bid(): void
    {
        $listing = Listing::factory()->create(['min_price' => 750]);
        $this->actingAs(User::factory()->create());

        Livewire::test(Show::class, ['listing' => $listing])
            ->assertSet('amount', '750.00')
            ->assertSee('Place bid')
            ->assertSee('Minimum bid: R750.00');

        $this->placeBid(User::factory()->create(), $listing, 800);

        Livewire::test(Show::class, ['listing' => $listing])->assertSet('amount', '810.00');
    }

    public function test_user_can_place_a_bid_from_the_form(): void
    {
        $listing = Listing::factory()->create(['min_price' => 750]);
        $user = User::factory()->create();
        $this->actingAs($user);

        Livewire::test(Show::class, ['listing' => $listing])
            ->set('amount', '800')
            ->call('placeBid')
            ->assertHasNoErrors()
            ->assertSee('Your bid of R800.00 has been placed')
            ->assertSet('amount', '810.00')
            ->assertSee('You are currently the highest bidder');

        $this->assertSame($user->id, $listing->highestBid()->user_id);
    }

    public function test_form_shows_rule_errors(): void
    {
        $listing = Listing::factory()->create(['min_price' => 750]);
        $this->actingAs(User::factory()->create());

        Livewire::test(Show::class, ['listing' => $listing])
            ->set('amount', '700')
            ->call('placeBid')
            ->assertHasErrors('amount')
            ->assertSee('at least the minimum price of R750.00');

        Livewire::test(Show::class, ['listing' => $listing])
            ->set('amount', '800.123')
            ->call('placeBid')
            ->assertHasErrors(['amount' => 'decimal']);

        Livewire::test(Show::class, ['listing' => $listing])
            ->set('amount', 'abc')
            ->call('placeBid')
            ->assertHasErrors(['amount' => 'numeric']);

        $this->assertSame(0, Bid::count());
    }

    public function test_form_is_hidden_for_closed_listings(): void
    {
        $listing = Listing::factory()->closed()->create();
        $this->actingAs(User::factory()->create());

        Livewire::test(Show::class, ['listing' => $listing])
            ->assertDontSee('Place bid')
            ->assertSee('No more bids are accepted');
    }

    public function test_admins_see_a_message_instead_of_the_form(): void
    {
        $listing = Listing::factory()->create();
        $this->actingAs(User::factory()->admin()->create());

        Livewire::test(Show::class, ['listing' => $listing])
            ->assertDontSee('Place bid')
            ->assertSee("You can't bid as an admin.", false)
            ->set('amount', '99999')
            ->call('placeBid')
            ->assertHasErrors('amount');

        $this->assertSame(0, Bid::count());
    }
}
