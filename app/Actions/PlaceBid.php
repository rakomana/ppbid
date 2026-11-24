<?php

namespace App\Actions;

use App\Models\Bid;
use App\Models\Listing;
use App\Models\User;
use App\Notifications\Outbid;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PlaceBid
{
    /**
     * Place a bid for the full lot.
     *
     * The listing row is locked for the whole check-and-insert, so bids on the
     * same listing are processed one at a time and every bid is checked against
     * the true current top bid.
     *
     * @throws ValidationException
     */
    public function handle(User $user, Listing $listing, float $amount): Bid
    {
        $previousLeaderId = null;

        $bid = DB::transaction(function () use ($user, $listing, $amount, &$previousLeaderId) {
            $listing = Listing::query()->lockForUpdate()->findOrFail($listing->id);

            if ($user->is_admin) {
                $this->reject("Admins can't place bids.");
            }

            // Checked against the database, not the possibly stale model passed in.
            if ($user->fresh()?->isDeactivated()) {
                $this->reject('Your account has been deactivated, so you can no longer bid.');
            }

            if (! $listing->isOpen()) {
                $this->reject('This listing has closed. No more bids are accepted.');
            }

            $highest = $listing->highestBid();

            if ($highest && $highest->user_id === $user->id) {
                $this->reject('You are already the highest bidder.');
            }

            $minimum = $listing->minimumNextBid();

            if ($this->cents($amount) < $this->cents($minimum)) {
                $this->reject($highest
                    ? 'Your bid must be at least '.Money::format($minimum).' (the top bid plus '.Money::format($listing->bidIncrement()).').'
                    : 'Your bid must be at least the minimum price of '.Money::format($minimum).'.');
            }

            if ($listing->buyNowAvailable() && $this->cents($amount) >= $this->cents((float) $listing->buy_now_price)) {
                $this->reject('That is at or above the Buy Now price of '.Money::format($listing->buy_now_price).'. Use Buy Now to win it straight away.');
            }

            $previousLeaderId = $highest?->user_id;

            return $listing->bids()->create([
                'user_id' => $user->id,
                'amount' => round($amount, 2),
            ]);
        });

        // Sent after the bid is saved, so nobody is told about a bid that didn't happen.
        if ($previousLeaderId) {
            $this->notifyOutbid($previousLeaderId, $bid);
        }

        return $bid;
    }

    /**
     * Tell the previous leader they've been outbid. Older unread "outbid" notifications
     * for the same listing are replaced, so a bidding war doesn't flood the bell.
     */
    private function notifyOutbid(int $userId, Bid $bid): void
    {
        $previousLeader = User::query()->whereNull('deactivated_at')->find($userId);

        if (! $previousLeader) {
            return;
        }

        $previousLeader->unreadNotifications()
            ->where('type', Outbid::class)
            ->where('data->listing_id', $bid->listing_id)
            ->delete();

        $previousLeader->notify(new Outbid($bid->listing, (float) $bid->amount));
    }

    /**
     * Compare money as whole cents to avoid floating-point surprises.
     */
    private function cents(float $amount): int
    {
        return (int) round($amount * 100);
    }

    /**
     * @throws ValidationException
     */
    private function reject(string $message): never
    {
        throw ValidationException::withMessages(['amount' => $message]);
    }
}
