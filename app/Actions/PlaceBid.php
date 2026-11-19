<?php

namespace App\Actions;

use App\Models\Bid;
use App\Models\Listing;
use App\Models\User;
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
        return DB::transaction(function () use ($user, $listing, $amount) {
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
                    ? 'Your bid must be at least '.Money::format($minimum).' (the top bid plus '.Money::format(config('auction.min_increment')).').'
                    : 'Your bid must be at least the minimum price of '.Money::format($minimum).'.');
            }

            return $listing->bids()->create([
                'user_id' => $user->id,
                'amount' => round($amount, 2),
            ]);
        });
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
