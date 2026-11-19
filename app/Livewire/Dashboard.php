<?php

namespace App\Livewire;

use App\Models\Listing;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Dashboard')]
class Dashboard extends Component
{
    public function render()
    {
        $user = auth()->user();

        if ($user->is_admin) {
            return view('livewire.dashboard', [
                'openListings' => Listing::open()->count(),
                'awaitingClose' => Listing::where('status', Listing::STATUS_ACTIVE)->where('ends_at', '<=', now())->count(),
            ]);
        }

        $winningNow = Listing::open()
            ->whereHas('bids', fn (Builder $bids) => $bids->where('user_id', $user->id))
            ->withLeaderId()
            ->get()
            ->filter(fn (Listing $listing) => (int) $listing->leader_id === $user->id)
            ->count();

        return view('livewire.dashboard', [
            'winningNow' => $winningNow,
            'auctionsWon' => Listing::where('status', Listing::STATUS_CLOSED)->where('winner_id', $user->id)->count(),
            'openListings' => Listing::open()->count(),
        ]);
    }
}
