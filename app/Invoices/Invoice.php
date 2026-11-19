<?php

namespace App\Invoices;

use App\Models\Listing;
use App\Models\User;
use Carbon\CarbonInterface;
use InvalidArgumentException;

/**
 * The invoice for a won auction, worked out from the closed listing.
 *
 * Nothing is stored: the number comes from the listing (listing 123 => "INV-000123"),
 * and the amount is the winning bid, which can't change once the listing is closed.
 * There is no VAT, so the winning bid is the total.
 */
final class Invoice
{
    private function __construct(
        public readonly Listing $listing,
        public readonly User $buyer,
        public readonly float $amount,
    ) {}

    public static function for(Listing $listing): self
    {
        if (! self::existsFor($listing)) {
            throw new InvalidArgumentException("Listing {$listing->id} has no winner to invoice.");
        }

        return new self($listing, $listing->winner, (float) $listing->highestBid()->amount);
    }

    /**
     * Only closed listings with a winner have an invoice.
     */
    public static function existsFor(Listing $listing): bool
    {
        return $listing->status === Listing::STATUS_CLOSED && $listing->winner_id !== null;
    }

    public static function numberFor(int $listingId): string
    {
        return 'INV-'.str_pad((string) $listingId, 6, '0', STR_PAD_LEFT);
    }

    public function number(): string
    {
        return self::numberFor($this->listing->id);
    }

    /** The invoice date is when the auction ended. */
    public function date(): CarbonInterface
    {
        return $this->listing->ends_at;
    }

    public function dueDate(): CarbonInterface
    {
        return $this->listing->ends_at->copy()->addDays(config('invoice.payment_terms_days'));
    }

    public function filename(): string
    {
        return "ppbid-{$this->number()}.pdf";
    }
}
