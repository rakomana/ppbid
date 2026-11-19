<?php

namespace App\Notifications;

use App\Models\Listing;
use App\Support\Money;

class Outbid extends AuctionNotification
{
    public function __construct(Listing $listing, public float $amount)
    {
        parent::__construct($listing);
    }

    protected function kind(): string
    {
        return 'outbid';
    }

    protected function title(): string
    {
        return "You've been outbid";
    }

    protected function message(): string
    {
        return "The top bid on “{$this->listing->title}” is now ".Money::format($this->amount).'.';
    }
}
