<?php

namespace Tests\Feature\Bidding;

use App\Actions\BuyNow;
use App\Actions\PlaceBid;
use App\Livewire\Admin\Listings\Form;
use App\Livewire\Listings\Show;
use App\Models\Listing;
use App\Models\User;
use App\Notifications\AuctionClosedForAdmin;
use App\Notifications\AuctionLost;
use App\Notifications\AuctionWon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class BuyNowTest extends TestCase
{
    use RefreshDatabase;

    private Listing $listing;

    private User $alice;

    private User $bob;

    protected function setUp(): void
    {
        parent::setUp();

        $this->listing = Listing::factory()->create([
            'min_price' => 100,
            'buy_now_price' => 500,
            'ends_at' => now()->addHour(),
        ]);
        $this->alice = User::factory()->create();
        $this->bob = User::factory()->create();
    }

    private function buyNow(User $user, float $price = 500): void
    {
        app(BuyNow::class)->handle($user, $this->listing, $price);
    }

    private function assertRejected(callable $action, string $message): void
    {
        try {
            $action();
            $this->fail('Expected the action to be rejected.');
        } catch (ValidationException $e) {
            $this->assertStringContainsString($message, collect($e->errors())->flatten()->first());
        }
    }

    public function test_buy_now_wins_the_lot_and_closes_the_listing(): void
    {
        app(PlaceBid::class)->handle($this->bob, $this->listing, 150);

        $this->buyNow($this->alice);

        $listing = $this->listing->fresh();
        $this->assertSame(Listing::STATUS_CLOSED, $listing->status);
        $this->assertSame($this->alice->id, $listing->winner_id);
        $this->assertFalse($listing->ends_at->isFuture());
        $this->assertSame('500.00', $listing->highestBid()->amount);
        $this->assertTrue($listing->highestBid()->is_buy_now);
        $this->assertTrue($listing->wasBoughtNow());
    }

    public function test_the_winner_can_download_the_invoice_after_buying_now(): void
    {
        $this->buyNow($this->alice);

        $this->actingAs($this->alice)->get(route('listings.invoice', $this->listing))->assertOk();
    }

    public function test_the_current_leader_can_buy_now(): void
    {
        app(PlaceBid::class)->handle($this->alice, $this->listing, 150);

        $this->buyNow($this->alice);

        $this->assertSame($this->alice->id, $this->listing->fresh()->winner_id);
    }

    public function test_buy_now_is_rejected_for_admins_deactivated_users_and_closed_listings(): void
    {
        $admin = User::factory()->admin()->create();
        $this->assertRejected(fn () => $this->buyNow($admin), "Admins can't buy");

        $this->bob->forceFill(['deactivated_at' => now()])->save();
        $this->assertRejected(fn () => $this->buyNow($this->bob), 'deactivated');

        $this->listing->update(['ends_at' => now()->subMinute()]);
        $this->assertRejected(fn () => $this->buyNow($this->alice), 'has closed');

        $this->assertSame(0, $this->listing->bids()->count());
    }

    public function test_buy_now_is_rejected_without_a_buy_now_price(): void
    {
        $this->listing->update(['buy_now_price' => null]);

        $this->assertRejected(fn () => $this->buyNow($this->alice), 'no longer available');
        $this->assertSame(Listing::STATUS_ACTIVE, $this->listing->fresh()->status);
    }

    public function test_buy_now_is_rejected_when_the_price_changed_since_it_was_shown(): void
    {
        $this->listing->update(['buy_now_price' => 650]);

        $this->assertRejected(fn () => $this->buyNow($this->alice, 500), 'has changed to R');
        $this->assertSame(Listing::STATUS_ACTIVE, $this->listing->fresh()->status);
        $this->assertSame(0, $this->listing->bids()->count());
    }

    public function test_bids_at_or_above_the_buy_now_price_are_rejected(): void
    {
        $this->assertRejected(fn () => app(PlaceBid::class)->handle($this->alice, $this->listing, 500), 'Use Buy Now');
        $this->assertRejected(fn () => app(PlaceBid::class)->handle($this->alice, $this->listing, 800), 'Use Buy Now');

        app(PlaceBid::class)->handle($this->alice, $this->listing, 499.99);
        $this->assertSame(1, $this->listing->bids()->count());
    }

    public function test_buy_now_is_not_available_once_the_top_bid_reaches_the_price(): void
    {
        $this->listing->update(['buy_now_price' => null]);
        app(PlaceBid::class)->handle($this->bob, $this->listing, 600);
        $this->listing->update(['buy_now_price' => 550]);

        $this->assertFalse($this->listing->fresh()->buyNowAvailable());
    }

    public function test_notifications_use_buy_now_wording(): void
    {
        $admin = User::factory()->admin()->create();
        app(PlaceBid::class)->handle($this->bob, $this->listing, 150);

        $this->buyNow($this->alice);

        $won = $this->alice->notifications()->where('type', AuctionWon::class)->sole();
        $this->assertStringContainsString('with Buy Now for R', $won->data['message']);

        $lost = $this->bob->notifications()->where('type', AuctionLost::class)->sole();
        $this->assertSame('Auction ended', $lost->data['title']);
        $this->assertStringContainsString('bought with Buy Now by Bidder '.$this->alice->bidder_number, $lost->data['message']);
        $this->assertStringNotContainsString($this->alice->name, $lost->data['message']);

        $closed = $admin->notifications()->where('type', AuctionClosedForAdmin::class)->sole();
        $this->assertSame('Listing bought now', $closed->data['title']);
        $this->assertStringContainsString($this->alice->name, $closed->data['message']);
    }

    public function test_listing_page_shows_buy_now_with_a_confirmation(): void
    {
        $this->actingAs($this->alice);

        Livewire::test(Show::class, ['listing' => $this->listing])
            ->assertSee('Buy now for R')
            ->assertSeeHtml('data-buy-now-modal')
            ->assertSee('Buy it now?')
            ->assertSee('Confirm purchase')
            ->assertSeeHtml("buyNow('500.00')");

        // Opening the dialog buys nothing.
        $this->assertSame(0, $this->listing->bids()->count());
    }

    public function test_listing_page_hides_buy_now_for_admins(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        Livewire::test(Show::class, ['listing' => $this->listing])->assertDontSeeHtml('data-buy-now');
    }

    public function test_buying_from_the_listing_page_shows_the_win(): void
    {
        $this->actingAs($this->alice);

        Livewire::test(Show::class, ['listing' => $this->listing])
            ->call('buyNow', '500.00')
            ->assertHasNoErrors()
            ->assertDispatched('modal-close', name: 'buy-now')
            ->assertSee('You bought this with Buy Now!')
            ->assertSee('Bought now by Bidder');
    }

    public function test_listing_page_reports_a_changed_price(): void
    {
        $this->actingAs($this->alice);
        $component = Livewire::test(Show::class, ['listing' => $this->listing]);

        $this->listing->update(['buy_now_price' => 650]);

        $component->call('buyNow', '500.00')->assertHasErrors('buyNow');
        $this->assertSame(Listing::STATUS_ACTIVE, $this->listing->fresh()->status);
    }

    public function test_admin_can_set_and_clear_the_buy_now_price(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        Livewire::test(Form::class, ['listing' => $this->listing])
            ->set('buy_now_price', '750')
            ->call('save')
            ->assertHasNoErrors();
        $this->assertSame('750.00', $this->listing->fresh()->buy_now_price);

        Livewire::test(Form::class, ['listing' => $this->listing->fresh()])
            ->set('buy_now_price', '')
            ->call('save')
            ->assertHasNoErrors();
        $this->assertNull($this->listing->fresh()->buy_now_price);
    }

    public function test_buy_now_price_must_be_above_the_minimum_price(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        Livewire::test(Form::class, ['listing' => $this->listing])
            ->set('buy_now_price', '100')
            ->call('save')
            ->assertHasErrors(['buy_now_price' => 'gt']);
    }

    public function test_with_bids_the_buy_now_price_must_beat_the_next_valid_bid(): void
    {
        app(PlaceBid::class)->handle($this->bob, $this->listing, 300);
        $this->actingAs(User::factory()->admin()->create());

        // The next valid bid is R310 (R300 plus the R10 increment).
        Livewire::test(Form::class, ['listing' => $this->listing])
            ->set('buy_now_price', '305')
            ->call('save')
            ->assertHasErrors('buy_now_price');
        $this->assertSame('500.00', $this->listing->fresh()->buy_now_price);

        Livewire::test(Form::class, ['listing' => $this->listing])
            ->set('buy_now_price', '310')
            ->call('save')
            ->assertHasNoErrors();
        $this->assertSame('310.00', $this->listing->fresh()->buy_now_price);
    }
}
