<?php

namespace Database\Seeders;

use App\Actions\CloseListing;
use App\Models\Bid;
use App\Models\Listing;
use App\Models\User;
use App\Notifications\Outbid;
use Carbon\CarbonInterface;
use Illuminate\Database\Seeder;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Artisan;

/**
 * Fake listings in every state, with realistic bid histories.
 *
 * Alice (alice@example.com) is set up to be winning, outbid, a winner and a
 * loser, so every status on "My bids" and the dashboard has something in it.
 */
class AuctionSeeder extends Seeder
{
    /** @var Collection<int, User> Fake bidders used to fill out histories. */
    private Collection $crowd;

    public function run(CloseListing $closeListing): void
    {
        $alice = User::where('email', 'alice@example.com')->firstOrFail();
        $bob = User::where('email', 'bob@example.com')->firstOrFail();
        $carol = User::where('email', 'carol@example.com')->firstOrFail();

        $this->crowd = User::where('is_admin', false)
            ->whereNotIn('id', [$alice->id, $bob->id, $carol->id])
            ->get();

        // --- Open listings ---------------------------------------------------
        // [title, description, min price, quantity, ends in (minutes), bids, final bidder, must also bid]
        $open = [
            ['Cement 50kg bags', 'Pallet of general-purpose cement, sold as one lot.', 1500, 40, 10, 3, $carol, []],
            ['Steel roof sheets 3m', 'Galvanised IBR sheets, sold as one lot.', 4200, 25, 6 * 60, 0, null, []],
            ['Cordless drill', null, 750, 1, 2 * 24 * 60, 0, null, []],
            ['Clay stock bricks (pallet of 500)', 'Standard clay stock bricks, collected from our yard.', 2000, 500, 26 * 60, 6, $alice, [$bob]],
            ['Pine timber 38x114 6.6m', 'SA pine, treated. 60 lengths.', 3500, 60, 3 * 60, 5, $bob, [$alice]],
            ['PVC pipe 110mm x 6m', 'Class 34 sewer pipe.', 1200, 30, 45, 4, $carol, [$alice]],
            ['Ceramic floor tiles 600x600', '40 m² of matt grey tiles, 1.44 m² per box.', 5200, 28, 5 * 24 * 60, 3, $alice, []],
            ['Wheelbarrow 65L heavy duty', 'Steel pan, pneumatic wheel.', 450, 2, 90, 7, null, []],
            ['Interior paint 20L white', 'Low-sheen acrylic, unopened.', 900, 6, 4 * 24 * 60, 0, null, []],
            ['Concrete mixer 120L', 'Electric, 550 W. Lightly used.', 3800, 1, 8 * 60, 2, null, []],
        ];

        // Buy Now prices for some open listings, comfortably above where their bidding ends up.
        $buyNow = [
            'Steel roof sheets 3m' => 6500,
            'Cordless drill' => 1200,
            'Clay stock bricks (pallet of 500)' => 4500,
            'Concrete mixer 120L' => 5500,
        ];

        // Bid increments for some open listings; the rest use the default (AUCTION_MIN_INCREMENT).
        $increments = [
            'Clay stock bricks (pallet of 500)' => 50,
            'Ceramic floor tiles 600x600' => 100,
            'Concrete mixer 120L' => 25,
        ];

        foreach ($open as [$title, $description, $minPrice, $quantity, $endsInMinutes, $bidCount, $final, $mustBid]) {
            $listing = $this->makeListing($title, $description, $minPrice, $quantity, now()->addMinutes($endsInMinutes));

            if (isset($increments[$title])) {
                $listing->forceFill(['min_increment' => $increments[$title]])->saveQuietly();
            }

            $this->addBids($listing, $bidCount, $final, $mustBid, until: now()->subMinute());

            if (isset($buyNow[$title])) {
                $listing->forceFill(['buy_now_price' => $buyNow[$title]])->saveQuietly();
            }

            // Demo notifications: the named bidders who were overtaken see "You've been outbid".
            if ($top = $listing->highestBid()) {
                foreach ($mustBid as $user) {
                    $user->notify(new Outbid($listing, (float) $top->amount));
                }
            }
        }

        // --- Closed listings -------------------------------------------------
        // [title, description, min price, quantity, ended (hours ago), bids, final bidder, must also bid]
        $closed = [
            ['River sand (6 m³)', 'Delivered within 30 km.', 1800, 6, 20, 5, $alice, [$carol]],
            ['Galvanised wire mesh rolls', 'Ref 245, 2.4 x 6 m.', 950, 10, 48, 4, $bob, [$alice]],
            ['Angle grinder 230mm', null, 600, 1, 72, 6, $carol, []],
            ['Roof insulation rolls', '135 mm glasswool, 12 rolls.', 2400, 12, 30, 0, null, []],
            ['Steel door frames (pack of 5)', 'Single-rebate, 813 mm.', 1600, 5, 100, 3, null, []],
        ];

        foreach ($closed as [$title, $description, $minPrice, $quantity, $endedHoursAgo, $bidCount, $final, $mustBid]) {
            $endsAt = now()->subHours($endedHoursAgo);
            $listing = $this->makeListing($title, $description, $minPrice, $quantity, $endsAt);

            $this->addBids($listing, $bidCount, $final, $mustBid, until: $endsAt->copy()->subMinutes(5));

            // Same code path as the scheduled auctions:close command (it also sends the notifications).
            $closeListing->handle($listing);

            // Date those notifications to when the listing actually ended.
            DatabaseNotification::query()
                ->where('data->listing_id', $listing->id)
                ->update(['created_at' => $endsAt, 'updated_at' => $endsAt]);
        }

        // --- Bought with Buy Now -------------------------------------------
        // Alice ended this one early by paying the Buy Now price after Bob had bid.
        $endsAt = now()->subHours(6);
        $listing = $this->makeListing('Tile adhesive 20kg (pallet)', 'Grey tile adhesive, 50 bags.', 1100, 50, $endsAt);
        $listing->forceFill(['buy_now_price' => 2000])->saveQuietly();

        $this->addBids($listing, 2, null, [$bob], until: $endsAt->copy()->subHours(2));

        Bid::query()->forceCreate([
            'listing_id' => $listing->id,
            'user_id' => $alice->id,
            'amount' => 2000,
            'is_buy_now' => true,
            'created_at' => $endsAt,
            'updated_at' => $endsAt,
        ]);

        $closeListing->handle($listing);

        DatabaseNotification::query()
            ->where('data->listing_id', $listing->id)
            ->update(['created_at' => $endsAt, 'updated_at' => $endsAt]);

        // "Ending soon" notifications for listings with an hour or less left.
        Artisan::call('auctions:notify-ending-soon');
    }

    private function makeListing(string $title, ?string $description, float $minPrice, int $quantity, CarbonInterface $endsAt): Listing
    {
        $listing = Listing::create([
            'title' => $title,
            'description' => $description,
            'min_price' => $minPrice,
            'quantity' => $quantity,
            'ends_at' => $endsAt,
        ]);

        // Listed a few days before it ends, but never in the future.
        $createdAt = $endsAt->copy()->subDays(fake()->numberBetween(3, 6))->min(now()->subHours(2));
        $listing->forceFill(['created_at' => $createdAt, 'updated_at' => $createdAt])->saveQuietly();

        return $listing;
    }

    /**
     * Add a believable bid history: amounts rise by at least the increment,
     * nobody outbids themselves, and times are spread out after the listing opened.
     *
     * @param  array<int, User>  $mustBid
     */
    private function addBids(Listing $listing, int $count, ?User $final, array $mustBid, CarbonInterface $until): void
    {
        if ($count === 0) {
            return;
        }

        $bidders = $this->bidderSequence($count, $final, $mustBid);
        $times = $this->spreadTimes($count, $listing->created_at->copy()->addHour(), $until);
        $increment = $listing->bidIncrement();
        $amount = (float) $listing->min_price;

        foreach ($bidders as $i => $bidder) {
            if ($i > 0) {
                // Raise by 2–8%, rounded to R10, never less than the increment.
                $raise = round($amount * fake()->numberBetween(2, 8) / 100 / 10) * 10;
                $amount += max($increment, $raise);
            }

            Bid::query()->forceCreate([
                'listing_id' => $listing->id,
                'user_id' => $bidder->id,
                'amount' => $amount,
                'created_at' => $times[$i],
                'updated_at' => $times[$i],
            ]);
        }
    }

    /**
     * Who bids, in order. Named users in $mustBid bid early, $final bids last,
     * and the same person never bids twice in a row.
     *
     * @param  array<int, User>  $mustBid
     * @return array<int, User>
     */
    private function bidderSequence(int $count, ?User $final, array $mustBid): array
    {
        $sequence = [];
        $queue = $mustBid;

        for ($i = 0; $i < $count; $i++) {
            $previous = end($sequence) ?: null;

            if ($final && $i === $count - 1) {
                $sequence[] = $final;

                continue;
            }

            $exclude = array_filter([$previous?->id, ($final && $i === $count - 2) ? $final->id : null]);

            if ($queue && ! in_array($queue[0]->id, $exclude)) {
                $sequence[] = array_shift($queue);

                continue;
            }

            $sequence[] = $this->crowd->whereNotIn('id', $exclude)->random();
        }

        return $sequence;
    }

    /**
     * @return array<int, CarbonInterface> sorted oldest first
     */
    private function spreadTimes(int $count, CarbonInterface $from, CarbonInterface $until): array
    {
        $from = $from->min($until->copy()->subMinutes($count * 2));

        return collect(range(1, $count))
            ->map(fn () => fake()->dateTimeBetween($from, $until)->getTimestamp())
            ->sort()
            ->values()
            ->map(fn (int $timestamp) => now()->setTimestamp($timestamp))
            ->all();
    }
}
