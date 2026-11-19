<?php

namespace Tests\Feature\Auctions;

use App\Models\Bid;
use App\Models\Listing;
use App\Models\User;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CloseExpiredAuctionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_expired_listing_is_closed_with_the_highest_bidder_as_winner(): void
    {
        $listing = Listing::factory()->create(['title' => 'Cement', 'ends_at' => now()->addHour()]);
        $winner = User::factory()->create();
        Bid::factory()->for($listing)->create(['amount' => 1000]);
        Bid::factory()->for($listing)->for($winner)->create(['amount' => 1500]);
        Bid::factory()->for($listing)->create(['amount' => 1200]);

        $this->travel(2)->hours();

        $this->artisan('auctions:close')
            ->expectsOutputToContain("won by Bidder {$winner->bidder_number} for R1,500.00")
            ->expectsOutput('Closed 1 listing.')
            ->assertSuccessful();

        $listing->refresh();
        $this->assertSame(Listing::STATUS_CLOSED, $listing->status);
        $this->assertSame($winner->id, $listing->winner_id);
    }

    public function test_on_a_tie_the_earliest_bid_wins(): void
    {
        $listing = Listing::factory()->expired()->create();
        $first = Bid::factory()->for($listing)->create(['amount' => 800, 'created_at' => now()->subMinutes(10)]);
        Bid::factory()->for($listing)->create(['amount' => 800, 'created_at' => now()->subMinutes(5)]);

        $this->artisan('auctions:close')->assertSuccessful();

        $this->assertSame($first->user_id, $listing->fresh()->winner_id);
    }

    public function test_expired_listing_without_bids_is_closed_with_no_winner(): void
    {
        $listing = Listing::factory()->expired()->create();

        $this->artisan('auctions:close')->expectsOutputToContain('no bids')->assertSuccessful();

        $listing->refresh();
        $this->assertSame(Listing::STATUS_CLOSED, $listing->status);
        $this->assertNull($listing->winner_id);
    }

    public function test_listings_still_running_are_left_open(): void
    {
        $running = Listing::factory()->create(['ends_at' => now()->addMinute()]);

        $this->artisan('auctions:close')->expectsOutput('No expired listings to close.')->assertSuccessful();

        $this->assertSame(Listing::STATUS_ACTIVE, $running->fresh()->status);
    }

    public function test_already_closed_listings_are_not_changed(): void
    {
        $originalWinner = User::factory()->create();
        $closed = Listing::factory()->closed()->create(['winner_id' => $originalWinner->id]);
        Bid::factory()->for($closed)->create(['amount' => 99999]); // must not change the recorded winner

        $this->artisan('auctions:close')->expectsOutput('No expired listings to close.')->assertSuccessful();

        $this->assertSame($originalWinner->id, $closed->fresh()->winner_id);
    }

    public function test_closes_every_expired_listing_and_keeps_end_times(): void
    {
        $a = Listing::factory()->expired()->create();
        $b = Listing::factory()->create(['ends_at' => now()->subHours(3)]);
        $open = Listing::factory()->create();
        $endsAt = $b->ends_at;

        $this->artisan('auctions:close')->expectsOutput('Closed 2 listings.')->assertSuccessful();

        $this->assertSame(Listing::STATUS_CLOSED, $a->fresh()->status);
        $this->assertSame(Listing::STATUS_CLOSED, $b->fresh()->status);
        $this->assertTrue($b->fresh()->ends_at->equalTo($endsAt));
        $this->assertSame(Listing::STATUS_ACTIVE, $open->fresh()->status);
    }

    public function test_running_twice_is_safe(): void
    {
        Listing::factory()->expired()->create();

        $this->artisan('auctions:close')->expectsOutput('Closed 1 listing.');
        $this->artisan('auctions:close')->expectsOutput('No expired listings to close.');
    }

    public function test_command_is_scheduled_every_minute(): void
    {
        $event = collect(app(Schedule::class)->events())
            ->first(fn ($event) => str_contains($event->command, 'auctions:close'));

        $this->assertNotNull($event, 'auctions:close is not scheduled.');
        $this->assertSame('* * * * *', $event->expression);
        $this->assertTrue($event->withoutOverlapping);
    }
}
