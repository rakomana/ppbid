<?php

use App\Actions\CloseListing;
use App\Actions\DeactivateUser;
use App\Livewire\Admin\Users\Form;
use App\Livewire\Admin\Users\Index;
use App\Livewire\Listings\Show;
use App\Models\Bid;
use App\Models\Listing;
use App\Models\User;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Livewire\Volt\Volt;

function deactivated(array $attributes = []): User
{
    $user = bidder($attributes);
    $user->forceFill(['deactivated_at' => now()])->save();

    return $user;
}

/*
|--------------------------------------------------------------------------
| Deactivating and reactivating
|--------------------------------------------------------------------------
*/

it('lets an admin deactivate a user without deleting anything', function () {
    $this->actingAs(admin());
    $user = bidder();
    $listing = Listing::factory()->create(['min_price' => 100]);
    placeBid($user, $listing, 100);

    Livewire::test(Form::class, ['user' => $user])
        ->assertSee('Deactivate account')
        ->call('deactivate')
        ->assertRedirect(route('admin.users.index'));

    expect($user->fresh())
        ->not->toBeNull()
        ->deactivated_at->not->toBeNull()
        ->and(Bid::where('user_id', $user->id)->count())->toBe(1);
});

it('lets an admin reactivate a user', function () {
    $this->actingAs(admin());
    $user = deactivated();

    Livewire::test(Form::class, ['user' => $user])
        ->assertSee('This account was deactivated')
        ->assertSee('Reactivate account')
        ->call('reactivate');

    expect($user->fresh()->isDeactivated())->toBeFalse();
});

it('does not let an admin deactivate themselves', function () {
    $admin = admin();
    $this->actingAs($admin);

    Livewire::test(Form::class, ['user' => $admin])
        ->assertDontSee('Deactivate account')
        ->call('deactivate')
        ->assertHasErrors('status');

    expect($admin->fresh()->isDeactivated())->toBeFalse();
});

it('does not let non-admins deactivate anyone', function () {
    $target = bidder();
    $this->actingAs(bidder());

    Livewire::test(Form::class, ['user' => $target])->assertForbidden();

    expect($target->fresh()->isDeactivated())->toBeFalse();
});

it('warns which open-listing bids will be withdrawn and how many they lead', function () {
    $this->actingAs(admin());
    $user = bidder();
    placeBid($user, Listing::factory()->create(['min_price' => 100]), 100); // leading

    $outbid = Listing::factory()->create(['min_price' => 100]);
    placeBid($user, $outbid, 100);
    placeBid(bidder(), $outbid, 200); // not leading

    Livewire::test(Form::class, ['user' => $user])
        ->assertSee('Their bids on 2 open listings will be withdrawn.')
        ->assertSee("They're the top bidder on 1 of them.");
});

/*
|--------------------------------------------------------------------------
| Withdrawing bids on deactivation
|--------------------------------------------------------------------------
*/

it('withdraws the deactivated users bids so the next-highest bidder leads', function () {
    $listing = Listing::factory()->create(['min_price' => 100]);
    $runnerUp = bidder();
    $leader = bidder();
    placeBid($runnerUp, $listing, 100);
    placeBid($leader, $listing, 150);

    $withdrawn = app(DeactivateUser::class)->handle($leader);

    expect($withdrawn)->toBe(1)
        ->and($leader->fresh()->isDeactivated())->toBeTrue()
        ->and($listing->highestBid()->user_id)->toBe($runnerUp->id)
        ->and($listing->minimumNextBid())->toBe(110.0)
        ->and(Bid::where('user_id', $leader->id)->sole()->isVoided())->toBeTrue();
});

it('lets the listing close with the next-highest bidder as winner', function () {
    $listing = Listing::factory()->create(['min_price' => 100, 'ends_at' => now()->addHour()]);
    $runnerUp = bidder();
    $leader = bidder();
    placeBid($runnerUp, $listing, 100);
    placeBid($leader, $listing, 150);

    app(DeactivateUser::class)->handle($leader);
    $this->travel(2)->hours();
    $this->artisan('auctions:close')->assertSuccessful();

    expect($listing->fresh()->winner_id)->toBe($runnerUp->id);
});

it('closes with no winner if the only bidder is deactivated', function () {
    $listing = Listing::factory()->create(['min_price' => 100]);
    $only = bidder();
    placeBid($only, $listing, 100);

    app(DeactivateUser::class)->handle($only);
    app(CloseListing::class)->handle($listing);

    expect($listing->fresh()->winner_id)->toBeNull();
});

it('does not touch bids on closed listings', function () {
    $closed = Listing::factory()->create(['min_price' => 100]);
    $winner = bidder();
    placeBid($winner, $closed, 100);
    app(CloseListing::class)->handle($closed);

    expect(app(DeactivateUser::class)->handle($winner))->toBe(0);

    expect($closed->fresh()->winner_id)->toBe($winner->id)
        ->and(Bid::where('user_id', $winner->id)->sole()->isVoided())->toBeFalse();
});

it('updates every live view: leader, participants, history and the admin list', function () {
    $listing = Listing::factory()->create(['min_price' => 100]);
    $runnerUp = bidder();
    $leader = bidder();
    placeBid($runnerUp, $listing, 100);
    placeBid($leader, $listing, 150);

    app(DeactivateUser::class)->handle($leader);

    $this->actingAs($runnerUp);

    $component = Livewire::test(Show::class, ['listing' => $listing])
        ->assertSeeInOrder(['Current top bid', 'R100.00', 'Leading:', "Bidder {$runnerUp->bidder_number}"])
        ->assertSee('Withdrawn')
        ->assertSee('You are currently the highest bidder');

    expect($component->viewData('participants')->pluck('user_id')->all())->toBe([$runnerUp->id])
        ->and($component->viewData('history'))->toHaveCount(2)
        ->and($component->viewData('bids'))->toHaveCount(1);

    $this->get('/listings')->assertSee('R100.00')->assertSee('1 bidder');
});

it('keeps withdrawn bids withdrawn after reactivation, and lets them bid again', function () {
    $this->actingAs(admin());
    $listing = Listing::factory()->create(['min_price' => 100]);
    $user = bidder();
    placeBid($user, $listing, 500);

    app(DeactivateUser::class)->handle($user);
    Livewire::test(Form::class, ['user' => $user->fresh()])->call('reactivate');

    expect($listing->highestBid())->toBeNull();

    placeBid($user->fresh(), $listing, 100);
    expect($listing->highestBid()->user_id)->toBe($user->id);
});

it('deactivates through the admin form and reports withdrawn bids', function () {
    $this->actingAs(admin());
    $user = bidder(['name' => 'Gone Bidder']);
    placeBid($user, Listing::factory()->create(['min_price' => 100]), 100);

    Livewire::test(Form::class, ['user' => $user])->call('deactivate');

    expect(session('status'))->toBe('Gone Bidder has been deactivated. They can no longer log in or bid. Their bids on 1 listing were withdrawn.');
});

/*
|--------------------------------------------------------------------------
| Users list
|--------------------------------------------------------------------------
*/

it('marks deactivated users in the list and can filter by status', function () {
    $this->actingAs(admin());
    bidder(['name' => 'Active Person']);
    deactivated(['name' => 'Gone Person']);

    Livewire::test(Index::class)
        ->assertSeeInOrder(['Active Person', 'Gone Person', 'Deactivated'])
        ->set('status', 'deactivated')
        ->assertSee('Gone Person')
        ->assertDontSee('Active Person')
        ->set('status', 'active')
        ->assertSee('Active Person')
        ->assertDontSee('Gone Person');
});

/*
|--------------------------------------------------------------------------
| What a deactivated user can do
|--------------------------------------------------------------------------
*/

it('stops a deactivated user logging in', function () {
    deactivated(['email' => 'gone@example.com', 'password' => 'secret-pass']);

    Volt::test('auth.login')
        ->set('email', 'gone@example.com')
        ->set('password', 'secret-pass')
        ->call('login')
        ->assertHasErrors(['email']);

    $this->assertGuest();
});

it('still lets active users log in', function () {
    bidder(['email' => 'here@example.com', 'password' => 'secret-pass']);

    Volt::test('auth.login')
        ->set('email', 'here@example.com')
        ->set('password', 'secret-pass')
        ->call('login')
        ->assertHasNoErrors();

    $this->assertAuthenticated();
});

it('signs out a logged-in user on their next request after deactivation', function () {
    $user = bidder();
    $this->actingAs($user)->get('/listings')->assertOk();

    $user->forceFill(['deactivated_at' => now()])->save();

    $this->get('/listings')
        ->assertRedirect(route('login'))
        ->assertSessionHas('status', 'Your account has been deactivated. Ask an administrator for help.');

    $this->assertGuest();
});

it('does not let a deactivated user bid', function () {
    $user = bidder();
    $listing = Listing::factory()->create(['min_price' => 100]);
    $user->forceFill(['deactivated_at' => now()])->save();

    // Even with a stale model that still looks active.
    $stale = User::find($user->id)->forceFill(['deactivated_at' => null]);

    expect(fn () => placeBid($stale, $listing, 100))
        ->toThrow(ValidationException::class, 'Your account has been deactivated');

    expect(Bid::count())->toBe(0);
});

/*
|--------------------------------------------------------------------------
| History is kept
|--------------------------------------------------------------------------
*/

it('keeps a deactivated winner and their bids on the listing', function () {
    $winner = bidder();
    $listing = Listing::factory()->create(['min_price' => 100]);
    placeBid(bidder(), $listing, 100);
    placeBid($winner, $listing, 200);
    app(CloseListing::class)->handle($listing);

    $winner->forceFill(['deactivated_at' => now()])->save();

    $this->actingAs(bidder());

    Livewire::test(Show::class, ['listing' => $listing])
        ->assertSee("Won by Bidder {$winner->bidder_number} for")
        ->assertSee('R200.00');
});
