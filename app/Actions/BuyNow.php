<?php

namespace App\Actions;

use App\Models\Bid;
use App\Models\Listing;
use App\Models\User;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BuyNow
{
    public function __construct(private CloseListing $closeListing) {}

    /**
     * Buy the full lot at its Buy Now price and close the listing.
     *
     * The purchase is recorded as a bid at the Buy Now price, so the winner,
     * invoice and returns work exactly as for an auction won by bidding.
     * The listing row is locked like PlaceBid, so a bid and a Buy Now arriving
     * together are handled one at a time.
     *
     * @param  float  $expectedPrice  The price the bidder confirmed; rejected if the admin has changed it since.
     *
     * @throws ValidationException
     */
    public function handle(User $user, Listing $listing, float $expectedPrice): Bid
    {
        return DB::transaction(function () use ($user, $listing, $expectedPrice) {
            $listing = Listing::query()->lockForUpdate()->findOrFail($listing->id);

            if ($user->is_admin) {
                $this->reject("Admins can't buy listings.");
            }

            // Checked against the database, not the possibly stale model passed in.
            if ($user->fresh()?->isDeactivated()) {
                $this->reject('Your account has been deactivated, so you can no longer buy.');
            }

            if (! $listing->isOpen()) {
                $this->reject('This listing has closed. It can no longer be bought.');
            }

            if (! $listing->buyNowAvailable()) {
                $this->reject('Buy Now is no longer available on this listing.');
            }

            if ($this->cents((float) $listing->buy_now_price) !== $this->cents($expectedPrice)) {
                $this->reject('The Buy Now price has changed to '.Money::format($listing->buy_now_price).'. Please check it and try again.');
            }

            $bid = $listing->bids()->create([
                'user_id' => $user->id,
                'amount' => $listing->buy_now_price,
                'is_buy_now' => true,
            ]);

            // Closed in the same transaction, so no bid can land between the purchase and the close.
            $this->closeListing->handle($listing);

            return $bid;
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
        throw ValidationException::withMessages(['buyNow' => $message]);
    }
}
