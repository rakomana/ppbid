<?php

namespace App\Actions;

use App\Models\Bid;
use App\Models\Listing;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class DeactivateUser
{
    /**
     * Deactivate a user and withdraw their bids on listings that aren't closed yet.
     *
     * Withdrawn bids are kept for the history but stop counting, so each affected
     * listing falls back to its next-highest bid. Closed listings are not touched:
     * finished results stay as they were.
     *
     * @return int The number of listings whose bids were withdrawn.
     */
    public function handle(User $user): int
    {
        return DB::transaction(function () use ($user) {
            // Lock the affected listings, the same lock PlaceBid and CloseListing take,
            // so no bid or close can happen half-way through.
            $listingIds = Listing::query()
                ->where('status', Listing::STATUS_ACTIVE)
                ->whereHas('bids', fn ($bids) => $bids->where('user_id', $user->id))
                ->orderBy('id')
                ->lockForUpdate()
                ->pluck('id');

            $user->forceFill(['deactivated_at' => now()])->save();

            Bid::query()
                ->where('user_id', $user->id)
                ->whereIn('listing_id', $listingIds)
                ->whereNull('voided_at')
                ->update(['voided_at' => now()]);

            return $listingIds->count();
        });
    }
}
