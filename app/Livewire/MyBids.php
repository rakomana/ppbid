<?php

namespace App\Livewire;

use App\Models\Listing;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('My bids')]
class MyBids extends Component
{
    public const WINNING = 'Winning';

    public const OUTBID = 'Outbid';

    public const WON = 'Won';

    public const LOST = 'Lost';

    /**
     * Every listing the user has bid on, with their highest bid and result.
     *
     * @return Collection<int, Listing>
     */
    private function listings(int $userId): Collection
    {
        return Listing::query()
            ->whereHas('bids', fn (Builder $bids) => $bids->where('user_id', $userId))
            ->withMax(['bids as my_highest_bid' => fn (Builder $bids) => $bids->where('user_id', $userId)], 'amount')
            ->withMax('bids', 'amount')
            ->withLeaderId()
            ->get()
            ->each(function (Listing $listing) use ($userId) {
                $listing->setAttribute('my_status', $this->statusFor($listing, $userId));
            });
    }

    /**
     * Still open: Winning / Outbid. Ended: Won / Lost.
     *
     * A listing past its end time but not yet closed by the scheduler is already
     * decided (no more bids are accepted), so it is shown as Won / Lost too.
     */
    private function statusFor(Listing $listing, int $userId): string
    {
        if ($listing->isOpen()) {
            return (int) $listing->leader_id === $userId ? self::WINNING : self::OUTBID;
        }

        $winnerId = $listing->status === Listing::STATUS_CLOSED ? $listing->winner_id : $listing->leader_id;

        return (int) $winnerId === $userId ? self::WON : self::LOST;
    }

    public function render()
    {
        [$active, $finished] = $this->listings(auth()->id())->partition(fn (Listing $listing) => $listing->isOpen());

        return view('livewire.my-bids', [
            'active' => $active->sortBy('ends_at')->values(),
            'finished' => $finished->sortByDesc('ends_at')->values(),
        ]);
    }
}
