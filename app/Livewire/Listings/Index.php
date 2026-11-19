<?php

namespace App\Livewire\Listings;

use App\Models\Listing;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Listings')]
class Index extends Component
{
    public function render()
    {
        $active = $this->withBidSummary(Listing::query())
            ->where('status', Listing::STATUS_ACTIVE)
            ->where('ends_at', '>', now())
            ->orderBy('ends_at')
            ->get();

        $closed = $this->withBidSummary(Listing::query())
            ->with('winner')
            ->where('status', Listing::STATUS_CLOSED)
            ->orderByDesc('ends_at')
            ->limit(12)
            ->get();

        return view('livewire.listings.index', [
            'active' => $active,
            'closed' => $closed,
        ]);
    }

    /**
     * Adds the top bid amount (bids_max_amount) and number of distinct bidders (bidders_count).
     *
     * @param  Builder<Listing>  $query
     * @return Builder<Listing>
     */
    private function withBidSummary(Builder $query): Builder
    {
        return $query
            ->withMax('bids', 'amount')
            ->withCount(['bids as bidders_count' => function (Builder $bids) {
                $bids->select(DB::raw('count(distinct user_id)'));
            }]);
    }
}
