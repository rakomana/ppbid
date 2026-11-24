<?php

namespace App\Livewire\Admin\Listings;

use App\Actions\CloseListing;
use App\Models\Listing;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Title('Admin: Listings')]
class Index extends Component
{
    use WithPagination;

    /**
     * Runs on every request, including actions like "Close now",
     * not only on the initial page load that the route middleware covers.
     */
    public function boot(): void
    {
        abort_unless(auth()->user()?->is_admin, 403);
    }

    public function closeNow(int $listingId, CloseListing $closeListing): void
    {
        $listing = $closeListing->handle(Listing::findOrFail($listingId), auth()->user());

        session()->flash('status', "“{$listing->title}” has been closed.");
    }

    public function render()
    {
        $listings = Listing::query()
            ->with('winner')
            ->withCount('bids')
            ->withMax('bids', 'amount')
            ->withExists(['bids as bought_now' => fn ($query) => $query->where('is_buy_now', true)])
            ->orderBy('status')          // active before closed
            ->orderBy('ends_at')
            ->paginate(20);

        return view('livewire.admin.listings.index', [
            'listings' => $listings,
        ]);
    }
}
