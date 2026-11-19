<?php

use App\Livewire\Listings\Show;
use App\Livewire\NotificationBell;
use App\Models\Listing;
use App\Support\Impersonation;
use Livewire\Livewire;

/*
|--------------------------------------------------------------------------
| Admins logging in as another user ("Log in as")
|--------------------------------------------------------------------------
*/

function startImpersonating($test, $admin, $user)
{
    return $test->actingAs($admin)->post(route('admin.users.impersonate', $user));
}

it('lets an admin log in as a bidder and see the app as them', function () {
    $admin = admin();
    $alice = bidder(['name' => 'Alice']);

    startImpersonating($this, $admin, $alice)->assertRedirect(route('dashboard'));

    $this->assertAuthenticatedAs($alice);
    expect(session(Impersonation::SESSION_KEY))->toBe($admin->id);

    $this->get('/dashboard')->assertOk()
        ->assertSee('data-impersonation-banner', false)
        ->assertSee("You're logged in as", false)
        ->assertSee('Return to admin')
        ->assertSee('Bidder '.$alice->bidder_number);
});

it('returns to the admin account', function () {
    $admin = admin();
    $alice = bidder(['name' => 'Alice']);
    startImpersonating($this, $admin, $alice);

    $this->post(route('impersonate.stop'))
        ->assertRedirect(route('admin.users.index'))
        ->assertSessionHas('status', "You're back in your admin account (you were viewing as Alice).");

    $this->assertAuthenticatedAs($admin);
    expect(session()->has(Impersonation::SESSION_KEY))->toBeFalse();
    $this->get('/admin/users')->assertOk()->assertDontSee('data-impersonation-banner', false);
});

it('logs out completely if the admin lost admin access meanwhile', function () {
    $admin = admin();
    startImpersonating($this, $admin, bidder());
    $admin->forceFill(['is_admin' => false])->save();

    $this->post(route('impersonate.stop'))->assertRedirect(route('login'));
    $this->assertGuest();
});

it('only lets admins impersonate', function () {
    $this->actingAs(bidder())->post(route('admin.users.impersonate', bidder()))->assertForbidden();
});

it("won't impersonate admins, deactivated users or yourself", function (string $who, string $message) {
    $admin = admin();
    $target = match ($who) {
        'admin' => admin(),
        'deactivated' => bidder(['deactivated_at' => now()]),
        'self' => $admin,
    };

    $this->actingAs($admin)->from('/admin/users')
        ->post(route('admin.users.impersonate', $target))
        ->assertRedirect('/admin/users')
        ->assertSessionHas('error', $message);

    $this->assertAuthenticatedAs($admin);
})->with([
    'another admin' => ['admin', "You can't log in as another admin."],
    'a deactivated user' => ['deactivated', "You can't log in as a deactivated user. Reactivate them first."],
    'yourself' => ['self', "You can't log in as yourself."],
]);

it('keeps the admin area closed while impersonating', function () {
    startImpersonating($this, admin(), bidder());

    $this->get('/admin/users')->assertForbidden();
    $this->post(route('admin.users.impersonate', bidder()))->assertForbidden();
});

it('turns bidding off while impersonating', function () {
    $listing = Listing::factory()->create(['min_price' => 100]);
    $alice = bidder();
    startImpersonating($this, admin(), $alice);

    $this->get(route('listings.show', $listing))
        ->assertSee("Bidding is turned off while you're logged in as another user.", false);

    Livewire::actingAs($alice)->test(Show::class, ['listing' => $listing])
        ->set('amount', '500')
        ->call('placeBid')
        ->assertHasErrors('amount');

    expect($listing->bids()->count())->toBe(0);
});

it('blocks account settings while impersonating, but not appearance', function () {
    startImpersonating($this, admin(), bidder());

    $this->get('/settings/profile')->assertForbidden();
    $this->get('/settings/password')->assertForbidden();
    $this->get('/settings/appearance')->assertOk();
});

it("doesn't mark the user's notifications as read while impersonating", function () {
    $listing = Listing::factory()->create(['min_price' => 100]);
    [$alice, $bob] = [bidder(), bidder()];
    placeBid($alice, $listing, 100);
    placeBid($bob, $listing, 150);
    $notification = $alice->notifications()->sole();

    startImpersonating($this, admin(), $alice);

    $this->get(route('notifications.open', $notification->id))->assertRedirect(route('listings.show', $listing));
    Livewire::actingAs($alice)->test(NotificationBell::class)->call('markAllRead');

    expect($notification->fresh()->read_at)->toBeNull();
});

it('shows "Log in as" for bidders only', function () {
    $admin = admin();
    $alice = bidder();
    $otherAdmin = admin();
    $gone = bidder(['deactivated_at' => now()]);

    $this->actingAs($admin)->get('/admin/users')
        ->assertSee(route('admin.users.impersonate', $alice))
        ->assertDontSee(route('admin.users.impersonate', $otherAdmin))
        ->assertDontSee(route('admin.users.impersonate', $gone))
        ->assertDontSee(route('admin.users.impersonate', $admin));

    $this->actingAs($admin)->get(route('admin.users.edit', $alice))->assertSee(route('admin.users.impersonate', $alice));
});

it('has nothing to stop when not impersonating', function () {
    $this->actingAs(admin())->post(route('impersonate.stop'))->assertNotFound();
});
