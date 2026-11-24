<?php

use App\Actions\CloseListing;
use App\Invoices\Invoice;
use App\Models\Listing;

/*
|--------------------------------------------------------------------------
| PDF invoices for won auctions
|--------------------------------------------------------------------------
*/

function wonListing(array $attributes = []): array
{
    $listing = Listing::factory()->create(['min_price' => 100, 'quantity' => 40, 'ends_at' => now()->addHour(), ...$attributes]);
    [$winner, $loser] = [bidder(['name' => 'Wendy Winner']), bidder()];

    placeBid($loser, $listing, 100);
    placeBid($winner, $listing, 1250.5);
    app(CloseListing::class)->handle($listing);

    return [$listing->fresh(), $winner, $loser];
}

it('numbers invoices from the listing id', function () {
    expect(Invoice::numberFor(7))->toBe('INV-000007')
        ->and(Invoice::numberFor(123456))->toBe('INV-123456');
});

it('lets the winner download a PDF invoice', function () {
    [$listing, $winner] = wonListing();

    $response = $this->actingAs($winner)->get(route('listings.invoice', $listing));

    $response->assertOk()
        ->assertHeader('content-type', 'application/pdf')
        ->assertDownload('ppbid-'.Invoice::numberFor($listing->id).'.pdf');

    expect(substr($response->getContent(), 0, 4))->toBe('%PDF');
});

it('lets admins download any invoice', function () {
    [$listing] = wonListing();

    $this->actingAs(admin())->get(route('listings.invoice', $listing))->assertOk();
});

it('does not let other bidders download the invoice', function () {
    [$listing, , $loser] = wonListing();

    $this->actingAs($loser)->get(route('listings.invoice', $listing))->assertForbidden();
    $this->actingAs(bidder())->get(route('listings.invoice', $listing))->assertForbidden();
});

it('has no invoice for open listings or listings closed without bids', function () {
    $open = Listing::factory()->create(['min_price' => 100]);
    $bidder = bidder();
    placeBid($bidder, $open, 100);

    $noBids = Listing::factory()->create();
    app(CloseListing::class)->handle($noBids);

    $this->actingAs(admin())->get(route('listings.invoice', $open))->assertNotFound();
    $this->actingAs(admin())->get(route('listings.invoice', $noBids))->assertNotFound();
});

it('requires login', function () {
    [$listing] = wonListing();

    $this->get(route('listings.invoice', $listing))->assertRedirect('/login');
});

it('shows the seller, buyer, item, total and banking details, with no VAT', function () {
    config([
        'invoice.company_name' => 'Acme Building Supplies',
        'invoice.company_address' => '12 Main Road|Germiston|1401',
        'invoice.bank_name' => 'FNB',
        'invoice.bank_account_number' => '62000000000',
        'invoice.bank_branch_code' => '250655',
        'invoice.payment_terms_days' => 7,
    ]);
    [$listing, $winner] = wonListing(['title' => 'Cement 50kg bags']);
    $invoice = Invoice::for($listing);

    $html = view('invoices.pdf', ['invoice' => $invoice])->render();

    expect($html)
        ->toContain('Acme Building Supplies')
        ->toContain('12 Main Road<br>')
        ->toContain('Germiston<br>')
        ->toContain($invoice->number())
        ->toContain('Wendy Winner')
        ->toContain($winner->email)
        ->toContain('Bidder '.$winner->bidder_number)
        ->toContain('Cement 50kg bags')
        ->toContain('one lot of 40 items')
        ->toContain('R1,250.50')
        ->toContain('Not charged')
        ->toContain('FNB')
        ->toContain('62000000000')
        ->toContain($listing->ends_at->copy()->addDays(7)->format('j M Y'));
});

it('leaves out the banking box when no bank details are set', function () {
    config(['invoice.bank_name' => '', 'invoice.bank_account_number' => '']);
    [$listing] = wonListing();

    expect(view('invoices.pdf', ['invoice' => Invoice::for($listing)])->render())
        ->not->toContain('Banking details');
});

it('shows the invoice button to the winner and admins only', function () {
    [$listing, $winner, $loser] = wonListing();

    $this->actingAs($winner)->get(route('listings.show', $listing))->assertSee('Download invoice');
    $this->actingAs($winner)->get(route('my-bids'))->assertSee(route('listings.invoice', $listing));
    $this->actingAs(admin())->get(route('listings.show', $listing))->assertSee(Invoice::numberFor($listing->id));
    $this->actingAs(admin())->get(route('admin.listings.index'))->assertSee(route('listings.invoice', $listing));

    $this->actingAs($loser)->get(route('listings.show', $listing))
        ->assertDontSee('Download invoice')
        ->assertDontSee(route('listings.invoice', $listing));
});

it('shows the WhatsApp share button to the winner and admins only', function () {
    [$listing, $winner, $loser] = wonListing();
    $filename = 'ppbid-'.Invoice::numberFor($listing->id).'.pdf';

    $this->actingAs($winner)->get(route('listings.show', $listing))
        ->assertSee('Share invoice')
        ->assertSee('data-share-invoice', false)
        ->assertSee($filename);
    $this->actingAs($winner)->get(route('my-bids'))->assertSee('data-share-invoice', false);
    $this->actingAs(admin())->get(route('listings.show', $listing))->assertSee('data-share-invoice', false);
    $this->actingAs(admin())->get(route('admin.listings.index'))->assertSee('data-share-invoice', false);

    $this->actingAs($loser)->get(route('listings.show', $listing))->assertDontSee('data-share-invoice', false);
    $this->actingAs($loser)->get(route('my-bids'))->assertDontSee('data-share-invoice', false);
});

it('has no share button on open listings', function () {
    $listing = Listing::factory()->create(['min_price' => 100, 'ends_at' => now()->addHour()]);
    placeBid($bidder = bidder(), $listing, 100);

    $this->actingAs($bidder)->get(route('listings.show', $listing))->assertDontSee('data-share-invoice', false);
    $this->actingAs(admin())->get(route('admin.listings.index'))->assertDontSee('data-share-invoice', false);
});
