<?php

namespace App\Actions;

use App\Models\Listing;
use Illuminate\Support\Facades\DB;

class CloseListing
{
    /**
     * Close a listing and record the highest bidder as the winner.
     *
     * Used by the admin "Close now" button and the scheduled closing command.
     * Safe to call twice: an already-closed listing is returned unchanged.
     */
    public function handle(Listing $listing): Listing
    {
        return DB::transaction(function () use ($listing) {
            // Lock the row so a bid being placed at the same moment can't slip in.
            $listing = Listing::query()->lockForUpdate()->findOrFail($listing->id);

            if ($listing->status === Listing::STATUS_CLOSED) {
                return $listing;
            }

            $listing->status = Listing::STATUS_CLOSED;
            $listing->winner_id = $listing->highestBid()?->user_id;

            // When closed early, end it now so the listing shows the real end time.
            if ($listing->ends_at->isFuture()) {
                $listing->ends_at = now();
            }

            $listing->save();

            return $listing;
        });
    }
}
