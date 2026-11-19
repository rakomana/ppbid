<?php

namespace App\Notifications;

use App\Models\Listing;
use App\Support\Money;

class AuctionLost extends AuctionNotification
{
    public function __construct(Listing $listing, public float $amount, public bool $closedEarly = false)
    {
        parent::__construct($listing);
    }

    protected function kind(): string
    {
        return 'lost';
    }

    protected function title(): string
    {
        return $this->closedEarly ? 'Auction closed early' : 'Auction ended';
    }

    protected function message(): string
    {
        return ($this->closedEarly ? 'An admin closed “'.$this->listing->title.'” early. ' : "“{$this->listing->title}” has ended. ")
            ."You didn't win: the winning bid was ".Money::format($this->amount).'.';
    }
}
