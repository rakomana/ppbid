<?php

namespace Tests\Feature\Results;

use App\Actions\CloseListing;
use App\Actions\PlaceBid;
use App\Livewire\Dashboard;
use App\Livewire\Listings\Show;
use App\Livewire\MyBids;
use App\Models\Listing;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ResultsAndHistoryTest extends TestCase
{
    use RefreshDatabase;

    private User $alice;

    private User $bob;

    protected function setUp(): void
    {
        parent::setUp();

        $this->alice = User::factory()->create();
        $this->bob = User::factory()->create();
    }

    private function bid(User $user, Listing $listing, float $amount): void
    {
        app(PlaceBid::class)->handle($user, $listing, $amount);
    }

    private function close(Listing $listing): void
    {
        app(CloseListing::class)->handle($listing);
    }

    // --- Closed listing detail page -----------------------------------------

    public function test_closed_listing_shows_winner_and_amount_and_tells_the_winner(): void
    {
        $listing = Listing::factory()->create(['min_price' => 1000, 'quantity' => 40]);
        $this->bid($this->alice, $listing, 1000);
        $this->bid($this->bob, $listing, 1250);
        $this->close($listing);

        $this->actingAs($this->bob);
        Livewire::test(Show::class, ['listing' => $listing])
            ->assertSee("Won by Bidder {$this->bob->bidder_number} for")
            ->assertSee('R1,250.00')
            ->assertSee('You won this auction!')
            ->assertSee('wins all 40 items')
            ->assertDontSee('Place bid');

        $this->actingAs($this->alice);
        Livewire::test(Show::class, ['listing' => $listing])
            ->assertSee("Won by Bidder {$this->bob->bidder_number} for")
            ->assertDontSee('You won this auction!')
            ->assertSee('Your highest bid was R1,000.00', false);
    }

    public function test_closed_listing_without_bids_says_so(): void
    {
        $listing = Listing::factory()->create();
        $this->close($listing);

        $this->actingAs($this->alice);
        Livewire::test(Show::class, ['listing' => $listing])
            ->assertSee('Closed with no bids')
            ->assertDontSee('Won by')
            ->assertDontSee('You won this auction!');
    }

    // --- My bids ------------------------------------------------------------

    public function test_my_bids_requires_login_and_is_in_the_navigation(): void
    {
        $this->get('/my-bids')->assertRedirect('/login');

        $this->actingAs($this->alice)->get('/dashboard')->assertSee(route('my-bids'));
        $this->actingAs(User::factory()->admin()->create())->get('/dashboard')->assertDontSee(route('my-bids'));
    }

    public function test_my_bids_shows_winning_outbid_won_and_lost(): void
    {
        $winning = Listing::factory()->create(['title' => 'Winning lot', 'min_price' => 100, 'ends_at' => now()->addHour()]);
        $this->bid($this->bob, $winning, 100);
        $this->bid($this->alice, $winning, 150);

        $outbid = Listing::factory()->create(['title' => 'Outbid lot', 'min_price' => 100, 'ends_at' => now()->addHours(2)]);
        $this->bid($this->alice, $outbid, 100);
        $this->bid($this->bob, $outbid, 300);
        $this->bid($this->alice, $outbid, 310);
        $this->bid($this->bob, $outbid, 400);

        $won = Listing::factory()->create(['title' => 'Won lot', 'min_price' => 100]);
        $this->bid($this->alice, $won, 500);
        $this->close($won);

        $lost = Listing::factory()->create(['title' => 'Lost lot', 'min_price' => 100]);
        $this->bid($this->alice, $lost, 100);
        $this->bid($this->bob, $lost, 900);
        $this->close($lost);

        Listing::factory()->create(['title' => 'Never bid on']);

        $this->actingAs($this->alice);

        $component = Livewire::test(MyBids::class);

        $statuses = $component->viewData('active')->merge($component->viewData('finished'))
            ->mapWithKeys(fn (Listing $l) => [$l->title => [$l->my_status, $l->my_highest_bid]])
            ->all();

        $this->assertEquals([
            'Winning lot' => ['Winning', '150.00'],
            'Outbid lot' => ['Outbid', '310.00'],
            'Won lot' => ['Won', '500.00'],
            'Lost lot' => ['Lost', '100.00'],
        ], $statuses);

        $component
            ->assertSeeInOrder(['In progress (2)', 'Winning lot', 'Outbid lot', 'Finished (2)'])
            ->assertSee('Bid again')
            ->assertDontSee('Never bid on');
    }

    public function test_listing_past_its_end_time_but_not_yet_closed_counts_as_finished(): void
    {
        $listing = Listing::factory()->create(['min_price' => 100]);
        $this->bid($this->alice, $listing, 100);
        $listing->update(['ends_at' => now()->subMinute()]);

        $this->actingAs($this->alice);
        $component = Livewire::test(MyBids::class);

        $this->assertCount(0, $component->viewData('active'));
        $this->assertSame('Won', $component->viewData('finished')->first()->my_status);
    }

    public function test_my_bids_empty_state(): void
    {
        $this->actingAs($this->alice);

        Livewire::test(MyBids::class)->assertSee("You haven't bid on anything yet.", false);
    }

    // --- Dashboard ----------------------------------------------------------

    public function test_dashboard_shows_bidder_number_winning_now_and_auctions_won(): void
    {
        // Winning now: 2
        foreach (range(1, 2) as $i) {
            $this->bid($this->alice, Listing::factory()->create(['min_price' => 100]), 100);
        }

        // Outbid: not counted
        $outbid = Listing::factory()->create(['min_price' => 100]);
        $this->bid($this->alice, $outbid, 100);
        $this->bid($this->bob, $outbid, 200);

        // Won: 1, lost: not counted
        $won = Listing::factory()->create(['min_price' => 100]);
        $this->bid($this->alice, $won, 100);
        $this->close($won);

        $lost = Listing::factory()->create(['min_price' => 100]);
        $this->bid($this->alice, $lost, 100);
        $this->bid($this->bob, $lost, 200);
        $this->close($lost);

        $this->actingAs($this->alice);

        Livewire::test(Dashboard::class)
            ->assertViewHas('winningNow', 2)
            ->assertViewHas('auctionsWon', 1)
            ->assertSee("Bidder {$this->alice->bidder_number}");
    }

    public function test_admin_dashboard_links_to_admin_area(): void
    {
        Listing::factory()->count(2)->create();
        Listing::factory()->expired()->create();

        $this->actingAs(User::factory()->admin()->create());

        Livewire::test(Dashboard::class)
            ->assertViewHas('openListings', 2)
            ->assertViewHas('awaitingClose', 1)
            ->assertSee('Manage listings')
            ->assertDontSee('Auctions won');
    }
}
