<?php

namespace App\Notifications;

use App\Models\Listing;
use App\Support\Money;

/**
 * Sent to admins when a listing closes. Admins may see the winner's name.
 */
class AuctionClosedForAdmin extends AuctionNotification
{
    public function __construct(Listing $listing, public ?float $amount, public bool $closedEarly = false)
    {
        parent::__construct($listing);
    }

    protected function kind(): string
    {
        return 'closed';
    }

    protected function title(): string
    {
        return $this->closedEarly ? 'Listing closed early' : 'Listing closed';
    }

    protected function message(): string
    {
        $winner = $this->listing->winner;

        return $winner
            ? "“{$this->listing->title}” sold to {$winner->name} (Bidder {$winner->bidder_number}) for ".Money::format($this->amount).'.'
            : "“{$this->listing->title}” closed with no bids.";
    }
}
