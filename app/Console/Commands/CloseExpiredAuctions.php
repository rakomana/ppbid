<?php

namespace App\Console\Commands;

use App\Actions\CloseListing;
use App\Models\Listing;
use App\Support\Money;
use Illuminate\Console\Command;

class CloseExpiredAuctions extends Command
{
    protected $signature = 'auctions:close';

    protected $description = 'Close active listings whose end time has passed and record the winners';

    public function handle(CloseListing $closeListing): int
    {
        $closed = 0;

        Listing::query()
            ->where('status', Listing::STATUS_ACTIVE)
            ->where('ends_at', '<=', now())
            ->orderBy('id')
            ->each(function (Listing $listing) use ($closeListing, &$closed) {
                $listing = $closeListing->handle($listing)->load('winner');
                $closed++;

                $this->line(sprintf(
                    'Closed #%d "%s": %s',
                    $listing->id,
                    $listing->title,
                    $listing->winner
                        ? 'won by Bidder '.$listing->winner->bidder_number.' for '.Money::format($listing->highestBid()?->amount)
                        : 'no bids',
                ));
            });

        $this->info($closed === 0 ? 'No expired listings to close.' : "Closed {$closed} ".str('listing')->plural($closed).'.');

        return self::SUCCESS;
    }
}
