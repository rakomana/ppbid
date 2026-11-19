<?php

namespace App\Console\Commands;

use App\Models\Listing;
use App\Notifications\AuctionEndingSoon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Notification;

class NotifyEndingSoon extends Command
{
    protected $signature = 'auctions:notify-ending-soon';

    protected $description = 'Tell bidders when a listing they bid on is about to end (once per listing)';

    public function handle(): int
    {
        $minutes = (int) config('auction.ending_soon_minutes');
        $sent = 0;

        Listing::query()
            ->where('status', Listing::STATUS_ACTIVE)
            ->whereNull('ending_soon_notified_at')
            ->where('ends_at', '>', now())
            ->where('ends_at', '<=', now()->addMinutes($minutes))
            ->orderBy('id')
            ->each(function (Listing $listing) use (&$sent) {
                // Claim the listing first, so two overlapping runs can't both send.
                $claimed = Listing::query()
                    ->whereKey($listing->id)
                    ->whereNull('ending_soon_notified_at')
                    ->update(['ending_soon_notified_at' => now()]);

                if (! $claimed) {
                    return;
                }

                $bidders = $listing->activeBidders();
                Notification::send($bidders, new AuctionEndingSoon($listing));
                $sent += $bidders->count();
            });

        $this->info("Sent {$sent} ending-soon ".str('notification')->plural($sent).'.');

        return self::SUCCESS;
    }
}
