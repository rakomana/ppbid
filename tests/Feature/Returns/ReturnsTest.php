<?php

use App\Actions\CloseListing;
use App\Actions\HandleReturn;
use App\Actions\LogReturn;
use App\Livewire\Admin\Returns\Index as AdminReturns;
use App\Livewire\Listings\Returns;
use App\Models\Listing;
use App\Models\ReturnRequest;
use App\Notifications\ReturnLogged;
use App\Notifications\ReturnUpdated;
use App\Support\Impersonation;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

/*
|--------------------------------------------------------------------------
| Returns: winners log faulty units, admins replace them (never refund)
|--------------------------------------------------------------------------
*/

function returnableLot(int $quantity = 20): array
{
    $listing = Listing::factory()->create(['min_price' => 100, 'quantity' => $quantity, 'ends_at' => now()->addHour()]);
    [$winner, $loser] = [bidder(), bidder()];

    placeBid($loser, $listing, 100);
    placeBid($winner, $listing, 500);
    app(CloseListing::class)->handle($listing);

    return [$listing->fresh(), $winner, $loser];
}

function logReturn($user, Listing $listing, int $quantity = 2, string $reason = 'fails_test'): ReturnRequest
{
    return app(LogReturn::class)->handle($user, $listing, $quantity, $reason, 'Fails MemTest86');
}

it('lets the winner log a return and tells admins', function () {
    [$listing, $winner] = returnableLot();
    $admin = admin();

    Livewire::actingAs($winner)->test(Returns::class, ['listing' => $listing])
        ->set('showForm', true)
        ->set('quantity', '3')
        ->set('reason', 'not_detected')
        ->set('details', 'Not seen in BIOS')
        ->call('logReturn')
        ->assertHasNoErrors()
        ->assertSee('logged');

    $return = ReturnRequest::sole();
    expect($return->user_id)->toBe($winner->id)
        ->and($return->quantity_requested)->toBe(3)
        ->and($return->status)->toBe(ReturnRequest::STATUS_REQUESTED)
        ->and($return->details)->toBe('Not seen in BIOS');

    $notification = $admin->notifications()->sole();
    expect($notification->type)->toBe(ReturnLogged::class)
        ->and($notification->data['message'])->toContain($winner->name)->toContain('3 units')
        ->and($notification->data['route'])->toBe('admin.returns.index');
});

it('opens the admin returns page from the admin notification', function () {
    [$listing, $winner] = returnableLot();
    $admin = admin();
    logReturn($winner, $listing);

    $this->actingAs($admin)
        ->get(route('notifications.open', $admin->notifications()->sole()->id))
        ->assertRedirect(route('admin.returns.index'));
});

it('only lets the winner log a return', function () {
    [$listing, , $loser] = returnableLot();

    expect(fn () => logReturn($loser, $listing))->toThrow(ValidationException::class);

    $this->actingAs($loser)->get(route('listings.show', $listing))->assertDontSee('Log a return');
    Livewire::actingAs($loser)->test(Returns::class, ['listing' => $listing])->assertForbidden();
});

it('caps open returns at the lot quantity', function () {
    [$listing, $winner] = returnableLot(5);

    logReturn($winner, $listing, 3);
    expect($listing->returnableQuantity())->toBe(2);

    expect(fn () => logReturn($winner, $listing, 3))->toThrow(ValidationException::class, 'between 1 and 2');
    logReturn($winner, $listing, 2);

    expect(fn () => logReturn($winner, $listing, 1))->toThrow(ValidationException::class, 'already in an open return');
});

it('frees units again once a return is replaced, rejected or cancelled', function () {
    [$listing, $winner] = returnableLot(5);
    $admin = admin();
    $handle = app(HandleReturn::class);

    $replaced = logReturn($winner, $listing, 2);
    $rejected = logReturn($winner, $listing, 2);
    $cancelled = logReturn($winner, $listing, 1);
    expect($listing->returnableQuantity())->toBe(0);

    $handle->approve($replaced, $admin, 2);
    $handle->markReplaced($replaced, $admin);
    $handle->reject($rejected, $admin, 'Passed testing');
    $handle->cancel($cancelled, $winner);

    expect($listing->returnableQuantity())->toBe(5);
});

it('counts the approved quantity, not the requested one, once approved', function () {
    [$listing, $winner] = returnableLot(5);

    $return = logReturn($winner, $listing, 4);
    app(HandleReturn::class)->approve($return, admin(), 1, 'Only one failed here');

    expect($listing->returnableQuantity())->toBe(4)
        ->and($return->fresh()->units())->toBe(1);
});

it('closes the return window after the configured days', function () {
    config(['returns.window_days' => 7]);
    [$listing, $winner] = returnableLot();

    $this->travelTo($listing->ends_at->copy()->addDays(7)->addMinute());

    expect($listing->acceptsReturns())->toBeFalse()
        ->and(fn () => logReturn($winner, $listing))->toThrow(ValidationException::class, 'window');

    $this->actingAs($winner)->get(route('listings.show', $listing))
        ->assertSee('The return window closed')
        ->assertDontSee('Log a return');
});

it('rejects an unknown reason and a zero quantity', function () {
    [$listing, $winner] = returnableLot();

    Livewire::actingAs($winner)->test(Returns::class, ['listing' => $listing])
        ->set('quantity', '0')
        ->set('reason', 'bogus')
        ->call('logReturn')
        ->assertHasErrors(['quantity', 'reason']);

    expect(ReturnRequest::count())->toBe(0);
});

it('moves a return through approved, received and replaced, telling the buyer each time', function () {
    [$listing, $winner] = returnableLot();
    $admin = admin();
    $return = logReturn($winner, $listing, 3);

    Livewire::actingAs($admin)->test(AdminReturns::class)
        ->call('open', $return->id, 'approve')
        ->set('quantity', '2')
        ->set('note', 'Drop them off at the counter')
        ->call('save')
        ->assertHasNoErrors();

    $return->refresh();
    expect($return->status)->toBe(ReturnRequest::STATUS_APPROVED)
        ->and($return->quantity_approved)->toBe(2)
        ->and($return->handled_by)->toBe($admin->id);

    Livewire::actingAs($admin)->test(AdminReturns::class)->call('open', $return->id, 'receive')->call('save');
    Livewire::actingAs($admin)->test(AdminReturns::class)->call('open', $return->id, 'replace')->call('save');

    $return->refresh();
    expect($return->status)->toBe(ReturnRequest::STATUS_REPLACED)
        ->and($return->replaced_at)->not->toBeNull();

    $messages = $winner->notifications()->where('type', ReturnUpdated::class)->get()->pluck('data.title')->all();
    expect($messages)->toEqualCanonicalizing(['Return approved', 'Faulty units received', 'Units replaced']);

    expect($winner->notifications()->where('data->title', 'Return approved')->sole()->data['message'])
        ->toContain('2 of the 3 units')
        ->toContain('Drop them off at the counter');
});

it('allows replacing straight after approval', function () {
    [$listing, $winner] = returnableLot();
    $admin = admin();
    $return = logReturn($winner, $listing);
    $handle = app(HandleReturn::class);

    $handle->approve($return, $admin, 2);
    $handle->markReplaced($return, $admin);

    expect($return->fresh()->status)->toBe(ReturnRequest::STATUS_REPLACED);
});

it('refuses steps out of order', function () {
    [$listing, $winner] = returnableLot();
    $admin = admin();
    $return = logReturn($winner, $listing);
    $handle = app(HandleReturn::class);

    expect(fn () => $handle->markReplaced($return, $admin))->toThrow(ValidationException::class)
        ->and(fn () => $handle->markReceived($return, $admin))->toThrow(ValidationException::class);

    $handle->approve($return, $admin, 2);
    expect(fn () => $handle->cancel($return, $winner))->toThrow(ValidationException::class)
        ->and(fn () => $handle->reject($return, $admin, 'no'))->toThrow(ValidationException::class);
});

it('needs a reason to reject and approves no more than was logged', function () {
    [$listing, $winner] = returnableLot();
    $return = logReturn($winner, $listing, 2);

    Livewire::actingAs(admin())->test(AdminReturns::class)
        ->call('open', $return->id, 'reject')
        ->call('save')
        ->assertHasErrors('note');

    Livewire::actingAs(admin())->test(AdminReturns::class)
        ->call('open', $return->id, 'approve')
        ->set('quantity', '3')
        ->call('save')
        ->assertHasErrors('quantity');

    expect($return->fresh()->status)->toBe(ReturnRequest::STATUS_REQUESTED);
});

it('lets the buyer cancel a return that has not been looked at', function () {
    [$listing, $winner] = returnableLot();
    $return = logReturn($winner, $listing);

    Livewire::actingAs($winner)->test(Returns::class, ['listing' => $listing])
        ->call('cancel', $return->id)
        ->assertSee('cancelled');

    expect($return->fresh()->status)->toBe(ReturnRequest::STATUS_CANCELLED);
});

it('keeps the returns page for admins only', function () {
    $this->actingAs(bidder())->get(route('admin.returns.index'))->assertForbidden();
    $this->actingAs(admin())->get(route('admin.returns.index'))->assertOk()->assertSee('Returns');
});

it('filters admin returns by status and finds them by number', function () {
    [$listing, $winner] = returnableLot();
    $admin = admin();
    $open = logReturn($winner, $listing, 1);
    $done = logReturn($winner, $listing, 1);
    app(HandleReturn::class)->reject($done, $admin, 'Fine here');

    Livewire::actingAs($admin)->test(AdminReturns::class)
        ->assertSee($open->number())
        ->assertDontSee($done->number())
        ->set('status', 'rejected')
        ->assertSee($done->number())
        ->assertDontSee($open->number())
        ->set('status', 'all')
        ->set('search', $done->number())
        ->assertSee($done->number())
        ->assertDontSee($open->number());
});

it('gives the buyer and admins a replacement note PDF once replaced', function () {
    [$listing, $winner, $loser] = returnableLot();
    $admin = admin();
    $return = logReturn($winner, $listing, 2);

    $this->actingAs($winner)->get(route('returns.note', $return))->assertNotFound();

    app(HandleReturn::class)->approve($return, $admin, 2);
    app(HandleReturn::class)->markReplaced($return, $admin, 'Same model');

    $response = $this->actingAs($winner)->get(route('returns.note', $return));
    $response->assertOk()->assertDownload('ppbid-'.$return->noteNumber().'.pdf');
    expect(substr($response->getContent(), 0, 4))->toBe('%PDF');

    $this->actingAs($admin)->get(route('returns.note', $return))->assertOk();
    $this->actingAs($loser)->get(route('returns.note', $return))->assertForbidden();

    $html = view('returns.note-pdf', ['return' => $return->fresh()->load('listing', 'user')])->render();
    expect($html)->toContain($return->noteNumber())->toContain($return->number())
        ->toContain('INV-'.str_pad((string) $listing->id, 6, '0', STR_PAD_LEFT))
        ->toContain('Same model')
        ->toContain('No refund');
});

it('blocks logging returns while impersonating', function () {
    [$listing, $winner] = returnableLot();
    $admin = admin();
    $this->actingAs($admin);
    Impersonation::start($admin, $winner);

    Livewire::test(Returns::class, ['listing' => $listing])
        ->set('quantity', '1')
        ->set('reason', 'fails_test')
        ->call('logReturn')
        ->assertHasErrors('quantity');

    expect(ReturnRequest::count())->toBe(0);

    $this->get(route('listings.show', $listing))
        ->assertSee('Logging returns is turned off')
        ->assertDontSee('Log a return');
});

it('shows a return link in my bids for won lots', function () {
    [$listing, $winner] = returnableLot();

    $this->actingAs($winner)->get(route('my-bids'))->assertSee(route('listings.show', $listing).'#returns', false);
});
