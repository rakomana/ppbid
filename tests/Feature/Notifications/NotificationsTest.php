<?php

use App\Actions\CloseListing;
use App\Actions\DeactivateUser;
use App\Livewire\Admin\Listings\Form as ListingForm;
use App\Livewire\Admin\Listings\Index as AdminListings;
use App\Livewire\NotificationBell;
use App\Models\Listing;
use App\Notifications\AuctionClosedForAdmin;
use App\Notifications\AuctionEndingSoon;
use App\Notifications\AuctionLost;
use App\Notifications\AuctionNotification;
use App\Notifications\AuctionWon;
use App\Notifications\Outbid;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;

/*
|--------------------------------------------------------------------------
| In-app notifications (never emailed)
|--------------------------------------------------------------------------
*/

function listingEndingIn(int $minutes, array $attributes = []): Listing
{
    return Listing::factory()->create(['min_price' => 100, 'ends_at' => now()->addMinutes($minutes), ...$attributes]);
}

describe('outbid', function () {
    it('tells the previous leader they have been outbid', function () {
        $listing = listingEndingIn(600, ['title' => 'Cement']);
        [$alice, $bob] = [bidder(), bidder()];

        placeBid($alice, $listing, 100);
        expect($alice->notifications)->toBeEmpty();

        placeBid($bob, $listing, 150);

        $notification = $alice->fresh()->notifications->sole();
        expect($notification->type)->toBe(Outbid::class)
            ->and($notification->data)->toMatchArray([
                'kind' => 'outbid',
                'title' => "You've been outbid",
                'listing_id' => $listing->id,
            ])
            ->and($notification->data['message'])->toContain('R150.00')
            ->and($bob->fresh()->notifications)->toBeEmpty();
    });

    it('keeps only the latest unread outbid notice per listing', function () {
        $listing = listingEndingIn(600);
        [$alice, $bob] = [bidder(), bidder()];

        placeBid($alice, $listing, 100);
        placeBid($bob, $listing, 110);
        placeBid($alice, $listing, 120);
        placeBid($bob, $listing, 130);

        $notifications = $alice->fresh()->unreadNotifications;
        expect($notifications)->toHaveCount(1)
            ->and($notifications->first()->data['message'])->toContain('R130.00');
    });

    it('does not notify a deactivated previous leader', function () {
        $listing = listingEndingIn(600);
        [$alice, $bob, $carol] = [bidder(), bidder(), bidder()];

        placeBid($alice, $listing, 100);
        placeBid($bob, $listing, 110);
        $bob->forceFill(['deactivated_at' => now()])->save();
        placeBid($carol, $listing, 120);

        expect($bob->fresh()->notifications)->toBeEmpty();
    });

    it('never shows other bidders by name', function () {
        $listing = listingEndingIn(600);
        $alice = bidder();
        $bob = bidder(['name' => 'Bob Secretname']);

        placeBid($alice, $listing, 100);
        placeBid($bob, $listing, 110);

        expect(json_encode($alice->fresh()->notifications->sole()->data))
            ->not->toContain('Secretname')
            ->not->toContain($bob->email);
    });
});

describe('closing', function () {
    it('tells the winner, the other bidders and the admins', function () {
        $admin = admin();
        $listing = listingEndingIn(600, ['title' => 'Bricks']);
        [$alice, $bob] = [bidder(), bidder(['name' => 'Bob Winner'])];

        placeBid($alice, $listing, 100);
        placeBid($bob, $listing, 250);
        $this->travel(11)->hours();

        app(CloseListing::class)->handle($listing);

        $won = $bob->fresh()->notifications->firstWhere('type', AuctionWon::class);
        expect($won->data['message'])->toBe('You won “Bricks” for R250.00. Your invoice is ready on the listing page.');

        $lost = $alice->fresh()->notifications->firstWhere('type', AuctionLost::class);
        expect($lost->data['title'])->toBe('Auction ended')
            ->and($lost->data['message'])->toContain('R250.00')
            ->and($lost->data['message'])->not->toContain('Bob Winner');

        $closed = $admin->fresh()->notifications->sole();
        expect($closed->type)->toBe(AuctionClosedForAdmin::class)
            ->and($closed->data['message'])->toContain('Bob Winner')
            ->and($closed->data['message'])->toContain('R250.00');
    });

    it('only notifies once when a listing is closed twice', function () {
        admin();
        $listing = listingEndingIn(600);
        $alice = bidder();
        placeBid($alice, $listing, 100);

        app(CloseListing::class)->handle($listing);
        app(CloseListing::class)->handle($listing);

        expect($alice->fresh()->notifications)->toHaveCount(1);
    });

    it('tells admins when a listing closes with no bids', function () {
        $admin = admin();
        $listing = listingEndingIn(600, ['title' => 'Sand']);

        app(CloseListing::class)->handle($listing);

        expect($admin->fresh()->notifications->sole()->data['message'])->toBe('“Sand” closed with no bids.');
    });

    it('says when an admin closed it early, and skips the admin who did it', function () {
        [$closer, $otherAdmin] = [admin(), admin()];
        $listing = listingEndingIn(600, ['title' => 'Tiles']);
        [$alice, $bob] = [bidder(), bidder()];
        placeBid($alice, $listing, 100);
        placeBid($bob, $listing, 200);

        Livewire::actingAs($closer)->test(AdminListings::class)->call('closeNow', $listing->id);

        expect($bob->fresh()->notifications->firstWhere('type', AuctionWon::class)->data['message'])
            ->toBe('An admin closed “Tiles” early. You won “Tiles” for R200.00. Your invoice is ready on the listing page.')
            ->and($alice->fresh()->notifications->firstWhere('type', AuctionLost::class)->data['title'])
            ->toBe('Auction closed early')
            ->and($otherAdmin->fresh()->notifications->sole()->data['title'])->toBe('Listing closed early')
            ->and($closer->fresh()->notifications)->toBeEmpty();
    });

    it('does not notify deactivated bidders when a listing closes', function () {
        $listing = listingEndingIn(600);
        [$alice, $bob] = [bidder(), bidder()];
        placeBid($alice, $listing, 100);
        placeBid($bob, $listing, 200);
        app(DeactivateUser::class)->handle($alice);

        app(CloseListing::class)->handle($listing);

        expect($alice->fresh()->notifications->where('type', '!=', Outbid::class))->toBeEmpty();
    });
});

describe('ending soon', function () {
    it('tells each bidder once when a listing has an hour or less left', function () {
        $soon = listingEndingIn(600, ['title' => 'Paint']);
        $later = listingEndingIn(600);
        [$alice, $bob] = [bidder(), bidder()];
        placeBid($alice, $soon, 100);
        placeBid($bob, $soon, 110);
        placeBid($alice, $later, 100);

        $soon->update(['ends_at' => now()->addMinutes(45)]);
        $later->update(['ends_at' => now()->addHours(2)]);

        $this->artisan('auctions:notify-ending-soon')->assertSuccessful();
        $this->artisan('auctions:notify-ending-soon')->assertSuccessful();

        foreach ([$alice, $bob] as $user) {
            $reminders = $user->fresh()->notifications->where('type', AuctionEndingSoon::class);
            expect($reminders)->toHaveCount(1)
                ->and($reminders->first()->data['listing_id'])->toBe($soon->id)
                ->and($reminders->first()->data['message'])->toBe('“Paint” ends in 45 minutes, at '.$soon->fresh()->ends_at->format('H:i').'.');
        }
    });

    it('sends a new reminder when an admin changes the end time', function () {
        $admin = admin();
        $listing = listingEndingIn(30);
        $alice = bidder();
        placeBid($alice, $listing, 100);

        $this->artisan('auctions:notify-ending-soon');

        Livewire::actingAs($admin)->test(ListingForm::class, ['listing' => $listing])
            ->set('ends_at', now()->addMinutes(50)->format('Y-m-d\TH:i'))
            ->call('save')
            ->assertHasNoErrors();

        expect($listing->fresh()->ending_soon_notified_at)->toBeNull();

        $this->artisan('auctions:notify-ending-soon');

        expect($alice->fresh()->notifications->where('type', AuctionEndingSoon::class))->toHaveCount(2);
    });

    it('is scheduled every minute', function () {
        $this->artisan('schedule:list')->expectsOutputToContain('auctions:notify-ending-soon');
    });
});

describe('in-app only', function () {
    it('uses the database channel only, never email', function () {
        $listing = Listing::factory()->make();

        foreach ([new Outbid($listing, 1), new AuctionEndingSoon($listing), new AuctionWon($listing, 1),
            new AuctionLost($listing, 1), new AuctionClosedForAdmin($listing, 1)] as $notification) {
            expect($notification)->toBeInstanceOf(AuctionNotification::class)
                ->and($notification->via(bidder()))->toBe(['database']);
        }
    });

    it('sends no mail during a whole auction', function () {
        Mail::fake();
        admin();
        $listing = listingEndingIn(30);
        [$alice, $bob] = [bidder(), bidder()];

        placeBid($alice, $listing, 100);
        placeBid($bob, $listing, 110);
        $this->artisan('auctions:notify-ending-soon');
        $this->travel(31)->minutes();
        $this->artisan('auctions:close');

        Mail::assertNothingOutgoing();
    });

    it('has no forgot-password or email verification pages', function () {
        $this->get('/forgot-password')->assertNotFound();
        $this->get('/reset-password/abc')->assertNotFound();
        $this->actingAs(bidder())->get('/verify-email')->assertNotFound();
    });

    it('tells users to ask an admin if they forget their password', function () {
        $this->get('/login')->assertOk()
            ->assertDontSee('Forgot your password?')
            ->assertSee('forgot your password? Ask an administrator.');
    });
});

describe('bell and notifications page', function () {
    it('shows the unread count and latest notifications', function () {
        $listing = listingEndingIn(600, ['title' => 'Roof sheets']);
        [$alice, $bob] = [bidder(), bidder()];
        placeBid($alice, $listing, 100);
        placeBid($bob, $listing, 150);

        $this->actingAs($alice)->get('/listings')
            ->assertOk()
            ->assertSee('data-notification-bell', false)
            ->assertSee('Notifications (1 unread)')
            ->assertSee("You've been outbid");
    });

    it('marks everything read', function () {
        $listing = listingEndingIn(600);
        [$alice, $bob] = [bidder(), bidder()];
        placeBid($alice, $listing, 100);
        placeBid($bob, $listing, 150);

        Livewire::actingAs($alice)->test(NotificationBell::class)->call('markAllRead');

        expect($alice->fresh()->unreadNotifications)->toBeEmpty();
    });

    it('pops up only notifications that arrived since the last check', function () {
        $listing = listingEndingIn(600);
        [$alice, $bob] = [bidder(), bidder()];
        placeBid($alice, $listing, 100);
        placeBid($bob, $listing, 150);   // alice already has this one when the page opens

        $bell = Livewire::actingAs($alice)->test(NotificationBell::class, ['toasts' => true]);
        $bell->call('poll')->assertNotDispatched('ppbid-toast');

        $other = listingEndingIn(600, ['title' => 'Gravel']);
        placeBid($alice, $other, 100);
        placeBid($bob, $other, 200);

        $bell->call('poll')->assertDispatched('ppbid-toast', fn ($name, $params) => $params['title'] === "You've been outbid"
            && str_contains($params['message'], 'Gravel'));

        $bell->call('poll')->assertNotDispatched('ppbid-toast');
    });

    it('opens a notification: marks it read and goes to the listing', function () {
        $listing = listingEndingIn(600);
        [$alice, $bob] = [bidder(), bidder()];
        placeBid($alice, $listing, 100);
        placeBid($bob, $listing, 150);
        $notification = $alice->notifications()->sole();

        $this->actingAs($alice)->get(route('notifications.open', $notification->id))
            ->assertRedirect(route('listings.show', $listing));

        expect($notification->fresh()->read_at)->not->toBeNull();
    });

    it("can't open someone else's notification", function () {
        $listing = listingEndingIn(600);
        [$alice, $bob] = [bidder(), bidder()];
        placeBid($alice, $listing, 100);
        placeBid($bob, $listing, 150);

        $this->actingAs($bob)->get(route('notifications.open', $alice->notifications()->sole()->id))->assertNotFound();
    });

    it('lists notifications on the notifications page', function () {
        $listing = listingEndingIn(600);
        [$alice, $bob] = [bidder(), bidder()];
        placeBid($alice, $listing, 100);
        placeBid($bob, $listing, 150);

        $this->actingAs($alice)->get('/notifications')->assertOk()
            ->assertSee("You've been outbid")
            ->assertSee('Mark all as read');

        $this->actingAs($bob)->get('/notifications')->assertOk()->assertSee('No notifications yet');
    });
});
