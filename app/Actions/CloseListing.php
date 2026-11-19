<?php

namespace App\Actions;

use App\Models\Listing;
use App\Models\User;
use App\Notifications\AuctionClosedForAdmin;
use App\Notifications\AuctionLost;
use App\Notifications\AuctionWon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

class CloseListing
{
    /**
     * Close a listing and record the highest bidder as the winner.
     *
     * Used by the admin "Close now" button and the scheduled closing command.
     * Safe to call twice: an already-closed listing is returned unchanged and
     * nobody is notified again.
     *
     * @param  User|null  $closedBy  The admin who pressed "Close now", so they aren't notified about their own action.
     */
    public function handle(Listing $listing, ?User $closedBy = null): Listing
    {
        $justClosed = false;
        $closedEarly = false;

        $listing = DB::transaction(function () use ($listing, &$justClosed, &$closedEarly) {
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
                $closedEarly = true;
            }

            $listing->save();
            $justClosed = true;

            return $listing;
        });

        if ($justClosed) {
            $this->notify($listing, $closedEarly, $closedBy);
        }

        return $listing;
    }

    /**
     * In-app notifications: the winner, everyone else who bid, and the admins.
     */
    private function notify(Listing $listing, bool $closedEarly, ?User $closedBy): void
    {
        $listing->load('winner');
        $amount = $listing->highestBid()?->amount;

        foreach ($listing->activeBidders() as $bidder) {
            $bidder->notify($bidder->id === $listing->winner_id
                ? new AuctionWon($listing, (float) $amount, $closedEarly)
                : new AuctionLost($listing, (float) $amount, $closedEarly));
        }

        $admins = User::query()
            ->where('is_admin', true)
            ->whereNull('deactivated_at')
            ->when($closedBy, fn ($query) => $query->whereKeyNot($closedBy->id))
            ->get();

        Notification::send($admins, new AuctionClosedForAdmin($listing, $amount === null ? null : (float) $amount, $closedEarly));
    }
}
