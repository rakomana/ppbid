<?php

namespace App\Notifications;

class AuctionEndingSoon extends AuctionNotification
{
    protected function kind(): string
    {
        return 'ending_soon';
    }

    protected function title(): string
    {
        return 'Auction ending soon';
    }

    protected function message(): string
    {
        $minutes = max(1, (int) ceil(now()->diffInSeconds($this->listing->ends_at) / 60));

        return "“{$this->listing->title}” ends in {$minutes} ".str('minute')->plural($minutes).', at '.$this->listing->ends_at->format('H:i').'.';
    }
}
