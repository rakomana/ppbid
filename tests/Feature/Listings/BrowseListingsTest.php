<?php

namespace Tests\Feature\Listings;

use App\Models\Bid;
use App\Models\Listing;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BrowseListingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_login(): void
    {
        $listing = Listing::factory()->create();

        $this->get('/listings')->assertRedirect('/login');
        $this->get("/listings/{$listing->id}")->assertRedirect('/login');
    }

    public function test_index_shows_open_listings_soonest_ending_first(): void
    {
        Listing::factory()->create(['title' => 'Ends later', 'ends_at' => now()->addDays(2)]);
        Listing::factory()->create(['title' => 'Ends soon', 'ends_at' => now()->addMinutes(10)]);
        Listing::factory()->create(['title' => 'Ends tomorrow', 'ends_at' => now()->addDay()]);

        $this->actingAs(User::factory()->create())
            ->get('/listings')
            ->assertOk()
            ->assertSeeInOrder(['Active listings', 'Ends soon', 'Ends tomorrow', 'Ends later', 'Recently closed']);
    }

    public function test_active_card_shows_starting_price_or_top_bid_and_bidder_count(): void
    {
        Listing::factory()->create(['title' => 'No bids yet', 'min_price' => 1250]);

        $withBids = Listing::factory()->create(['title' => 'Has bids', 'min_price' => 100]);
        $alice = User::factory()->create();
        Bid::factory()->for($withBids)->for($alice)->create(['amount' => 100]);
        Bid::factory()->for($withBids)->for($alice)->create(['amount' => 120]);
        Bid::factory()->for($withBids)->create(['amount' => 150]);

        $this->actingAs(User::factory()->create())
            ->get('/listings')
            ->assertSee('Starting at R1,250.00')
            ->assertSee('R150.00')
            ->assertSee('2 bidders'); // three bids from two distinct users
    }

    public function test_expired_listings_are_not_shown_as_active(): void
    {
        Listing::factory()->expired()->create(['title' => 'Already expired']);

        $this->actingAs(User::factory()->create())
            ->get('/listings')
            ->assertDontSee('Already expired');
    }

    public function test_closed_section_shows_winner_bidder_number_and_amount_or_no_bids(): void
    {
        $winner = User::factory()->create(['name' => 'Secret Name', 'email' => 'secret@example.com']);
        $won = Listing::factory()->closed()->create(['title' => 'Sold lot', 'winner_id' => $winner->id]);
        Bid::factory()->for($won)->for($winner)->create(['amount' => 2500]);
        Bid::factory()->for($won)->create(['amount' => 2000]);

        Listing::factory()->closed()->create(['title' => 'Unsold lot']);

        $this->actingAs(User::factory()->create())
            ->get('/listings')
            ->assertSeeInOrder(['Recently closed', 'Sold lot'])
            ->assertSee("Bidder {$winner->bidder_number}")
            ->assertSee('R2,500.00')
            ->assertSee('Unsold lot')
            ->assertSee('No bids')
            ->assertDontSee('Secret Name')
            ->assertDontSee('secret@example.com');
    }

    public function test_detail_page_shows_listing_details_and_leader(): void
    {
        $listing = Listing::factory()->create([
            'title' => 'Cement 50kg bags',
            'description' => 'Pallet of cement.',
            'min_price' => 1500,
            'quantity' => 40,
            'ends_at' => now()->addHours(3),
        ]);
        $leader = User::factory()->create(['name' => 'Leader Person', 'email' => 'leader@example.com']);
        Bid::factory()->for($listing)->create(['amount' => 1600]);
        Bid::factory()->for($listing)->for($leader)->create(['amount' => 1750]);

        $this->actingAs(User::factory()->create())
            ->get("/listings/{$listing->id}")
            ->assertOk()
            ->assertSee('Cement 50kg bags')
            ->assertSee('Pallet of cement.')
            ->assertSee('Bid is for all 40 items')
            ->assertSee('R1,500.00')
            ->assertSee($listing->ends_at->format('j M Y H:i'))
            ->assertSee('left')
            ->assertSee('R1,750.00')
            ->assertSeeInOrder(['Current top bid', 'R1,750.00', 'Leading', "Bidder {$leader->bidder_number}"])
            ->assertDontSee('Leader Person')
            ->assertDontSee('leader@example.com');
    }

    public function test_detail_page_for_listing_without_bids_shows_starting_price(): void
    {
        $listing = Listing::factory()->create(['min_price' => 750, 'quantity' => 1]);

        $this->actingAs(User::factory()->create())
            ->get("/listings/{$listing->id}")
            ->assertSee('Bid is for all 1 item')
            ->assertSee('No bids yet')
            ->assertSee('Starting at R750.00');
    }

    public function test_detail_page_for_closed_listing_shows_winner(): void
    {
        $winner = User::factory()->create();
        $listing = Listing::factory()->closed()->create(['winner_id' => $winner->id]);
        Bid::factory()->for($listing)->for($winner)->create(['amount' => 900]);

        $this->actingAs(User::factory()->create())
            ->get("/listings/{$listing->id}")
            ->assertSee("Won by Bidder {$winner->bidder_number} for", false)
            ->assertSee('R900.00')
            ->assertSee('No more bids are accepted');
    }

    public function test_missing_listing_returns_404(): void
    {
        $this->actingAs(User::factory()->create())->get('/listings/999')->assertNotFound();
    }

    public function test_navigation_shows_own_bidder_number_for_bidders_only(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/listings')
            ->assertSee('You are')
            ->assertSee("Bidder {$user->bidder_number}");

        $this->actingAs(User::factory()->admin()->create())
            ->get('/listings')
            ->assertDontSee('You are');
    }

    public function test_page_title_uses_listing_title(): void
    {
        $listing = Listing::factory()->create(['title' => 'Cordless drill']);

        $this->actingAs(User::factory()->create())
            ->get("/listings/{$listing->id}")
            ->assertSee('<title>Cordless drill · ppbid</title>', false);
    }
}
