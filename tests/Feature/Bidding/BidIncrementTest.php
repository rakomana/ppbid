<?php

namespace Tests\Feature\Bidding;

use App\Actions\PlaceBid;
use App\Livewire\Admin\Listings\Form;
use App\Livewire\Listings\Show;
use App\Models\Listing;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class BidIncrementTest extends TestCase
{
    use RefreshDatabase;

    private function placeBid(Listing $listing, float $amount, ?User $user = null): void
    {
        app(PlaceBid::class)->handle($user ?? User::factory()->create(), $listing, $amount);
    }

    public function test_a_listing_without_its_own_increment_uses_the_default(): void
    {
        config(['auction.min_increment' => 10]);
        $listing = Listing::factory()->create(['min_price' => 100]);

        $this->assertSame(10.0, $listing->bidIncrement());
        $this->placeBid($listing, 100);
        $this->assertSame(110.0, $listing->minimumNextBid());
    }

    public function test_a_listing_increment_replaces_the_default(): void
    {
        config(['auction.min_increment' => 10]);
        $listing = Listing::factory()->create(['min_price' => 100, 'min_increment' => 50]);
        $this->placeBid($listing, 100);

        try {
            $this->placeBid($listing, 149.99);
            $this->fail('Expected the bid to be rejected.');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('at least R150.00 (the top bid plus R50.00)', $e->errors()['amount'][0]);
        }

        $this->placeBid($listing, 150);
        $this->assertSame(200.0, $listing->fresh()->minimumNextBid());
    }

    public function test_the_first_bid_only_needs_the_minimum_price(): void
    {
        $listing = Listing::factory()->create(['min_price' => 100, 'min_increment' => 50]);

        $this->assertSame(100.0, $listing->minimumNextBid());
    }

    public function test_listing_page_shows_the_increment(): void
    {
        $listing = Listing::factory()->create(['min_price' => 100, 'min_increment' => 25]);
        $this->actingAs(User::factory()->create());

        Livewire::test(Show::class, ['listing' => $listing])
            ->assertSeeHtml('data-bid-increment')
            ->assertSee('steps of at least R25.00');
    }

    public function test_bid_form_has_stepper_buttons_using_the_increment_and_minimum(): void
    {
        $listing = Listing::factory()->create(['min_price' => 21000, 'min_increment' => 50]);
        $this->actingAs(User::factory()->create());

        Livewire::test(Show::class, ['listing' => $listing])
            ->assertSeeHtml('data-step="50.00"')
            ->assertSeeHtml('data-minimum="21000.00"')
            ->assertSeeHtml('aria-label="Raise bid by R50.00"')
            ->assertSeeHtml('aria-label="Lower bid by R50.00"');

        // After a bid, the stepper starts from the new minimum.
        $this->placeBid($listing, 21000);
        Livewire::test(Show::class, ['listing' => $listing->fresh()])->assertSeeHtml('data-minimum="21050.00"');
    }

    public function test_admin_can_set_and_clear_the_increment_before_any_bids(): void
    {
        $listing = Listing::factory()->create(['min_price' => 100]);
        $this->actingAs(User::factory()->admin()->create());

        Livewire::test(Form::class, ['listing' => $listing])
            ->set('min_increment', '50')
            ->call('save')
            ->assertHasNoErrors();
        $this->assertSame('50.00', $listing->fresh()->min_increment);

        Livewire::test(Form::class, ['listing' => $listing->fresh()])
            ->set('min_increment', '')
            ->call('save')
            ->assertHasNoErrors();
        $this->assertNull($listing->fresh()->min_increment);
    }

    public function test_admin_can_create_a_listing_with_an_increment(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        Livewire::test(Form::class)
            ->set('title', 'Bricks')
            ->set('min_price', '2000')
            ->set('min_increment', '100')
            ->set('quantity', '500')
            ->set('ends_at', now()->addDay()->format('Y-m-d\TH:i'))
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame('100.00', Listing::where('title', 'Bricks')->sole()->min_increment);
    }

    public function test_increment_must_be_at_least_one_rand(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        Livewire::test(Form::class, ['listing' => Listing::factory()->create()])
            ->set('min_increment', '0.50')
            ->call('save')
            ->assertHasErrors(['min_increment' => 'min']);
    }

    public function test_increment_is_locked_once_the_listing_has_bids(): void
    {
        $listing = Listing::factory()->create(['min_price' => 100, 'min_increment' => 20]);
        $this->placeBid($listing, 100);
        $this->actingAs(User::factory()->admin()->create());

        Livewire::test(Form::class, ['listing' => $listing])
            ->assertSee('bid increment and quantity are locked')
            ->set('min_increment', '50')
            ->call('save')
            ->assertHasErrors('min_price');
        $this->assertSame('20.00', $listing->fresh()->min_increment);

        // Other fields can still be edited.
        Livewire::test(Form::class, ['listing' => $listing])
            ->set('title', 'Renamed')
            ->call('save')
            ->assertHasNoErrors();
        $this->assertSame('Renamed', $listing->fresh()->title);
    }

    public function test_buy_now_floor_uses_the_listing_increment(): void
    {
        $listing = Listing::factory()->create(['min_price' => 100, 'min_increment' => 50, 'buy_now_price' => 500]);
        $this->placeBid($listing, 300);
        $this->actingAs(User::factory()->admin()->create());

        // The next valid bid is R350 (R300 plus this listing's R50 increment).
        Livewire::test(Form::class, ['listing' => $listing])
            ->set('buy_now_price', '340')
            ->call('save')
            ->assertHasErrors('buy_now_price');

        Livewire::test(Form::class, ['listing' => $listing])
            ->set('buy_now_price', '350')
            ->call('save')
            ->assertHasNoErrors();
    }
}
