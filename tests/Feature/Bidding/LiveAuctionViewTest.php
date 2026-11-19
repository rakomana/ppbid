<?php

namespace Tests\Feature\Bidding;

use App\Actions\PlaceBid;
use App\Livewire\Listings\Show;
use App\Models\Listing;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class LiveAuctionViewTest extends TestCase
{
    use RefreshDatabase;

    private Listing $listing;

    private User $alice;

    private User $bob;

    private User $carol;

    protected function setUp(): void
    {
        parent::setUp();

        $this->listing = Listing::factory()->create(['min_price' => 100, 'ends_at' => now()->addHour()]);
        $this->alice = User::factory()->create(['name' => 'Alice Secret', 'email' => 'alice-secret@example.com']);
        $this->bob = User::factory()->create();
        $this->carol = User::factory()->create();
    }

    private function bid(User $user, float $amount): void
    {
        app(PlaceBid::class)->handle($user, $this->listing, $amount);
    }

    public function test_live_section_polls_every_five_seconds_while_active(): void
    {
        $this->actingAs($this->alice);

        Livewire::test(Show::class, ['listing' => $this->listing])->assertSeeHtml('wire:poll.5s');

        // Still polls after expiry until the listing is actually closed, so the winner appears.
        $this->listing->update(['ends_at' => now()->subMinute()]);
        Livewire::test(Show::class, ['listing' => $this->listing])->assertSeeHtml('wire:poll.5s');
    }

    public function test_polling_stops_once_the_listing_is_closed(): void
    {
        $this->actingAs($this->alice);
        $component = Livewire::test(Show::class, ['listing' => $this->listing])->assertSeeHtml('wire:poll.5s');

        $this->listing->forceFill(['status' => Listing::STATUS_CLOSED])->save();

        $component->call('$refresh')->assertDontSeeHtml('wire:poll');
    }

    public function test_a_refresh_picks_up_bids_from_other_users(): void
    {
        $this->actingAs($this->alice);
        $component = Livewire::test(Show::class, ['listing' => $this->listing])->assertSee('Starting at R100.00');

        $this->bid($this->bob, 150);

        $component->call('$refresh') // what wire:poll does
            ->assertSee('R150.00')
            ->assertSee("Bidder {$this->bob->bidder_number}")
            ->assertDontSee('Starting at R100.00');
    }

    public function test_leader_box_shows_top_bid_and_leader(): void
    {
        $this->bid($this->bob, 100);
        $this->bid($this->carol, 250);
        $this->actingAs($this->alice);

        Livewire::test(Show::class, ['listing' => $this->listing])
            ->assertSeeInOrder(['Current top bid', 'R250.00', 'Leading:', "Bidder {$this->carol->bidder_number}"]);
    }

    public function test_participants_show_each_bidders_highest_bid_highest_first(): void
    {
        $this->bid($this->alice, 100);
        $this->bid($this->bob, 110);
        $this->bid($this->alice, 120);
        $this->bid($this->carol, 130);
        $this->actingAs($this->alice);

        $component = Livewire::test(Show::class, ['listing' => $this->listing]);

        $this->assertSame([
            ['user_id' => $this->carol->id, 'amount' => 130.0],
            ['user_id' => $this->alice->id, 'amount' => 120.0],
            ['user_id' => $this->bob->id, 'amount' => 110.0],
        ], $component->viewData('participants')->all());

        $component->assertSeeInOrder([
            'Participants (3)',
            "Bidder {$this->carol->bidder_number}", 'Leader', 'R130.00',
            "Bidder {$this->alice->bidder_number}", '(You)', 'R120.00',
            "Bidder {$this->bob->bidder_number}", 'R110.00',
            'Bid history',
        ]);
    }

    public function test_bid_history_lists_all_bids_newest_first_with_relative_time(): void
    {
        $this->travelTo(now()->subMinutes(5));
        $this->bid($this->alice, 100);
        $this->travelBack();
        $this->travelTo(now()->subMinutes(2));
        $this->bid($this->bob, 110);
        $this->travelBack();
        $this->bid($this->alice, 120);

        $this->actingAs($this->carol);

        $component = Livewire::test(Show::class, ['listing' => $this->listing]);

        $this->assertSame(['120.00', '110.00', '100.00'], $component->viewData('bids')->pluck('amount')->all());

        $component->assertSeeInOrder([
            'Bid history',
            "Bidder {$this->alice->bidder_number}", 'R120.00', 'ago',
            "Bidder {$this->bob->bidder_number}", 'R110.00', '2 minutes ago',
            "Bidder {$this->alice->bidder_number}", 'R100.00', '5 minutes ago',
        ]);
    }

    public function test_outbid_notice_shows_only_to_outbid_bidders_while_open(): void
    {
        $this->bid($this->alice, 100);
        $this->bid($this->bob, 150);

        $this->actingAs($this->alice);
        Livewire::test(Show::class, ['listing' => $this->listing])
            ->assertSee("You've been outbid. Minimum next bid is R160.00.");

        $this->actingAs($this->bob);
        Livewire::test(Show::class, ['listing' => $this->listing])
            ->assertDontSee('outbid')
            ->assertSee('You are currently the highest bidder');

        $this->actingAs($this->carol);
        Livewire::test(Show::class, ['listing' => $this->listing])->assertDontSee('outbid');

        $this->listing->forceFill(['status' => Listing::STATUS_CLOSED])->save();
        $this->actingAs($this->alice);
        Livewire::test(Show::class, ['listing' => $this->listing])->assertDontSee('outbid');
    }

    public function test_success_message_is_replaced_by_outbid_notice_after_being_outbid(): void
    {
        $this->actingAs($this->alice);

        $component = Livewire::test(Show::class, ['listing' => $this->listing])
            ->set('amount', '100')
            ->call('placeBid')
            ->assertSee('has been placed');

        $this->bid($this->bob, 200);

        $component->call('$refresh')
            ->assertDontSee('has been placed')
            ->assertSee("You've been outbid. Minimum next bid is R210.00.");
    }

    public function test_countdown_targets_ends_at_and_shows_auction_ended_when_closed(): void
    {
        $this->actingAs($this->alice);

        Livewire::test(Show::class, ['listing' => $this->listing])
            ->assertSeeHtml('data-countdown')
            ->assertSeeHtml('end: '.$this->listing->ends_at->getTimestampMs());

        $this->listing->forceFill(['status' => Listing::STATUS_CLOSED])->save();

        Livewire::test(Show::class, ['listing' => $this->listing])
            ->assertDontSeeHtml('data-countdown')
            ->assertSee('Auction ended');
    }

    public function test_live_view_never_shows_names_or_emails(): void
    {
        $this->bid($this->alice, 100);
        $this->bid($this->bob, 150);
        $this->actingAs($this->carol);

        Livewire::test(Show::class, ['listing' => $this->listing])
            ->assertDontSee('Alice Secret')
            ->assertDontSee('alice-secret@example.com')
            ->assertDontSee($this->bob->name)
            ->assertDontSee($this->bob->email);
    }

    public function test_untouched_prefilled_amount_follows_the_minimum_as_others_bid(): void
    {
        $this->actingAs($this->alice);
        $component = Livewire::test(Show::class, ['listing' => $this->listing])->assertSet('amount', '100.00');

        $this->bid($this->bob, 200);

        $component->call('$refresh')->assertSet('amount', '210.00');
    }

    public function test_an_amount_the_user_typed_is_not_overwritten_by_a_refresh(): void
    {
        $this->actingAs($this->alice);
        $component = Livewire::test(Show::class, ['listing' => $this->listing])->set('amount', '500');

        $this->bid($this->bob, 200);

        $component->call('$refresh')->assertSet('amount', '500');
    }
}
