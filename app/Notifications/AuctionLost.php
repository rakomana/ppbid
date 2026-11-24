<?php

namespace App\Notifications;

use App\Models\Listing;
use App\Models\User;
use App\Support\Money;

class AuctionLost extends AuctionNotification
{
    public function __construct(Listing $listing, public float $amount, public bool $closedEarly = false, public bool $boughtNow = false)
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
        // Bidders only ever see other bidders as bidder numbers.
        if ($this->boughtNow && $this->listing->winner_id) {
            return "“{$this->listing->title}” was bought with Buy Now by Bidder ".User::bidderNumberFor($this->listing->winner_id)
                .' for '.Money::format($this->amount).'.';
        }

        return ($this->closedEarly ? 'An admin closed “'.$this->listing->title.'” early. ' : "“{$this->listing->title}” has ended. ")
            ."You didn't win: the winning bid was ".Money::format($this->amount).'.';
    }
}
