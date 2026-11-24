<?php

namespace App\Notifications;

use App\Models\Listing;
use App\Support\Money;

class AuctionWon extends AuctionNotification
{
    public function __construct(Listing $listing, public float $amount, public bool $closedEarly = false, public bool $boughtNow = false)
    {
        parent::__construct($listing);
    }

    protected function kind(): string
    {
        return 'won';
    }

    protected function title(): string
    {
        return 'You won!';
    }

    protected function message(): string
    {
        if ($this->boughtNow) {
            return "You bought “{$this->listing->title}” with Buy Now for ".Money::format($this->amount).'. Your invoice is ready on the listing page.';
        }

        return ($this->closedEarly ? 'An admin closed “'.$this->listing->title.'” early. ' : '')
            ."You won “{$this->listing->title}” for ".Money::format($this->amount).'. Your invoice is ready on the listing page.';
    }
}
