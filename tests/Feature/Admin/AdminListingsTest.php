<?php

namespace Tests\Feature\Admin;

use App\Actions\CloseListing;
use App\Livewire\Admin\Listings\Form;
use App\Livewire\Admin\Listings\Index;
use App\Models\Bid;
use App\Models\Listing;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AdminListingsTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->admin()->create();
    }

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get('/admin/listings')->assertRedirect('/login');
    }

    public function test_normal_users_get_403_on_admin_pages(): void
    {
        $listing = Listing::factory()->create();
        $this->actingAs(User::factory()->create());

        $this->get('/admin')->assertForbidden();
        $this->get('/admin/listings')->assertForbidden();
        $this->get('/admin/listings/create')->assertForbidden();
        $this->get("/admin/listings/{$listing->id}/edit")->assertForbidden();
    }

    public function test_normal_users_cannot_call_admin_component_actions(): void
    {
        $listing = Listing::factory()->create();
        $this->actingAs(User::factory()->create());

        Livewire::test(Index::class)->assertForbidden();
        Livewire::test(Form::class)->assertForbidden();

        $this->assertSame(Listing::STATUS_ACTIVE, $listing->fresh()->status);
    }

    public function test_admin_sees_listings_index_with_bid_summary(): void
    {
        $listing = Listing::factory()->create(['title' => 'Cement bags', 'min_price' => 1000]);
        Bid::factory()->for($listing)->create(['amount' => 1250]);
        Bid::factory()->for($listing)->create(['amount' => 1100]);

        $this->actingAs($this->admin())
            ->get('/admin/listings')
            ->assertOk()
            ->assertSee('Cement bags')
            ->assertSee('R1,000.00')
            ->assertSee('R1,250.00');
    }

    public function test_admin_link_shows_only_for_admins(): void
    {
        $this->actingAs($this->admin())->get('/dashboard')->assertSee(route('admin.listings.index'));
        $this->actingAs(User::factory()->create())->get('/dashboard')->assertDontSee(route('admin.listings.index'));
    }

    public function test_new_listing_form_defaults_expiry_to_twelve_hours(): void
    {
        $this->actingAs($this->admin());
        $this->travelTo(now()->setTime(9, 30));

        Livewire::test(Form::class)
            ->assertSet('ends_at', now()->addHours(12)->format('Y-m-d\TH:i'));
    }

    public function test_default_expiry_length_is_configurable(): void
    {
        $this->actingAs($this->admin());
        config(['auction.default_duration_hours' => 6]);

        Livewire::test(Form::class)
            ->assertSet('ends_at', now()->addHours(6)->format('Y-m-d\TH:i'));
    }

    public function test_admin_can_create_a_listing(): void
    {
        $this->actingAs($this->admin());
        $endsAt = now()->addDays(3)->startOfMinute();

        Livewire::test(Form::class)
            ->set('title', 'Roof sheets')
            ->set('description', '')
            ->set('min_price', '4200.50')
            ->set('quantity', '25')
            ->set('ends_at', $endsAt->format('Y-m-d\TH:i'))
            ->call('save')
            ->assertHasNoErrors()
            ->assertRedirect(route('admin.listings.index'));

        $listing = Listing::sole();
        $this->assertSame('Roof sheets', $listing->title);
        $this->assertNull($listing->description);
        $this->assertSame('4200.50', $listing->min_price);
        $this->assertSame(25, $listing->quantity);
        $this->assertTrue($listing->ends_at->equalTo($endsAt));
        $this->assertSame(Listing::STATUS_ACTIVE, $listing->status);
    }

    public function test_create_validates_input(): void
    {
        $this->actingAs($this->admin());

        Livewire::test(Form::class)
            ->set('title', '')
            ->set('min_price', '0')
            ->set('quantity', '0')
            ->set('ends_at', now()->subHour()->format('Y-m-d\TH:i'))
            ->call('save')
            ->assertHasErrors([
                'title' => 'required',
                'min_price' => 'min',
                'quantity' => 'min',
                'ends_at' => 'after',
            ]);

        Livewire::test(Form::class)
            ->set('title', str_repeat('a', 256))
            ->set('min_price', 'abc')
            ->set('quantity', '1.5')
            ->set('ends_at', 'not a date')
            ->call('save')
            ->assertHasErrors([
                'title' => 'max',
                'min_price' => 'numeric',
                'quantity' => 'integer',
                'ends_at' => 'date',
            ]);

        $this->assertSame(0, Listing::count());
    }

    public function test_admin_can_edit_a_listing_without_bids(): void
    {
        $this->actingAs($this->admin());
        $listing = Listing::factory()->create(['min_price' => 500, 'quantity' => 2]);

        Livewire::test(Form::class, ['listing' => $listing])
            ->assertSet('min_price', '500.00')
            ->assertSet('hasBids', false)
            ->set('title', 'Renamed')
            ->set('min_price', '650')
            ->set('quantity', '3')
            ->call('save')
            ->assertHasNoErrors();

        $listing->refresh();
        $this->assertSame('Renamed', $listing->title);
        $this->assertSame('650.00', $listing->min_price);
        $this->assertSame(3, $listing->quantity);
    }

    public function test_price_and_quantity_are_locked_once_a_listing_has_bids(): void
    {
        $this->actingAs($this->admin());
        $listing = Listing::factory()->create(['min_price' => 500, 'quantity' => 2]);
        Bid::factory()->for($listing)->create(['amount' => 500]);

        Livewire::test(Form::class, ['listing' => $listing])
            ->assertSet('hasBids', true)
            ->assertSeeHtml('disabled')
            ->set('min_price', '100')
            ->call('save')
            ->assertHasErrors('min_price');

        Livewire::test(Form::class, ['listing' => $listing])
            ->set('quantity', '10')
            ->call('save')
            ->assertHasErrors('min_price');

        $this->assertSame('500.00', $listing->fresh()->min_price);
        $this->assertSame(2, $listing->fresh()->quantity);

        // Other fields can still be edited.
        Livewire::test(Form::class, ['listing' => $listing])
            ->set('title', 'New title')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame('New title', $listing->fresh()->title);
    }

    public function test_closed_listings_cannot_be_edited(): void
    {
        $listing = Listing::factory()->closed()->create();

        $this->actingAs($this->admin())
            ->get("/admin/listings/{$listing->id}/edit")
            ->assertForbidden();
    }

    public function test_close_now_closes_and_records_the_highest_earliest_bidder(): void
    {
        $this->actingAs($this->admin());
        $listing = Listing::factory()->create(['ends_at' => now()->addDay()]);
        $first = Bid::factory()->for($listing)->create(['amount' => 900]);
        Bid::factory()->for($listing)->create(['amount' => 900]); // tie, later
        Bid::factory()->for($listing)->create(['amount' => 800]);

        Livewire::test(Index::class)
            ->call('closeNow', $listing->id)
            ->assertSee('has been closed');

        $listing->refresh();
        $this->assertSame(Listing::STATUS_CLOSED, $listing->status);
        $this->assertSame($first->user_id, $listing->winner_id);
        $this->assertFalse($listing->ends_at->isFuture());
    }

    public function test_closing_a_listing_without_bids_has_no_winner_and_is_idempotent(): void
    {
        $listing = Listing::factory()->expired()->create();
        $endsAt = $listing->ends_at;

        $close = app(CloseListing::class);
        $close->handle($listing);
        $close->handle($listing);

        $listing->refresh();
        $this->assertSame(Listing::STATUS_CLOSED, $listing->status);
        $this->assertNull($listing->winner_id);
        $this->assertTrue($listing->ends_at->equalTo($endsAt), 'An already-expired end time is kept.');
    }
}
