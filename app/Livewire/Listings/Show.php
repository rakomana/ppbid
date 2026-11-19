<?php

namespace App\Livewire\Listings;

use App\Actions\PlaceBid;
use App\Models\Bid;
use App\Models\Listing;
use App\Support\Impersonation;
use App\Support\Money;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Component;

class Show extends Component
{
    #[Locked]
    public Listing $listing;

    public string $amount = '';

    /**
     * The minimum we last pre-filled. While the amount still equals it, the user
     * hasn't typed their own, so it can follow the minimum as others bid.
     */
    #[Locked]
    public string $suggestedAmount = '';

    public ?string $successMessage = null;

    public function mount(Listing $listing): void
    {
        $this->listing = $listing;
        $this->resetAmount();
    }

    /**
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        return [
            'amount' => ['required', 'numeric', 'decimal:0,2', 'min:0.01', 'max:99999999.99'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function validationAttributes(): array
    {
        return ['amount' => 'bid amount'];
    }

    public function placeBid(PlaceBid $placeBid): void
    {
        $this->successMessage = null;

        if (Impersonation::isActive()) {
            throw ValidationException::withMessages(['amount' => "Bidding is turned off while you're logged in as another user."]);
        }

        $this->validate();

        $bid = $placeBid->handle(auth()->user(), $this->listing, (float) $this->amount);

        $this->successMessage = 'Your bid of '.Money::format($bid->amount).' has been placed. You are the highest bidder.';
        $this->resetAmount();
    }

    private function resetAmount(): void
    {
        $this->amount = $this->suggestedAmount = number_format($this->listing->minimumNextBid(), 2, '.', '');
    }

    /**
     * Each bidder's highest bid, highest first.
     *
     * @param  Collection<int, Bid>  $bids
     * @return Collection<int, array{user_id: int, amount: float}>
     */
    private function participants(Collection $bids): Collection
    {
        return $bids
            ->groupBy('user_id')
            ->map(fn (Collection $userBids, int $userId) => [
                'user_id' => $userId,
                'amount' => $userBids->max(fn (Bid $bid) => (float) $bid->amount),
            ])
            ->sortByDesc('amount')
            ->values();
    }

    public function render()
    {
        // Runs on every poll, so everything below is always current.
        $this->listing->refresh();

        // Full history (including withdrawn bids), newest first; $bids are the ones that count.
        $history = $this->listing->allBids()
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get(['id', 'user_id', 'amount', 'voided_at', 'created_at']);

        $bids = $history->reject(fn (Bid $bid) => $bid->isVoided())->values();

        $topBid = $this->listing->highestBid();
        $userId = auth()->id();
        $isLeader = $topBid?->user_id === $userId;
        $hasBid = $bids->contains('user_id', $userId);

        // Don't keep saying "you are the highest bidder" after being outbid.
        if (! $isLeader) {
            $this->successMessage = null;
        }

        // Keep an untouched pre-filled amount in step with the latest minimum.
        if ($this->amount === $this->suggestedAmount) {
            $this->resetAmount();
        }

        return view('livewire.listings.show', [
            'bids' => $bids,
            'history' => $history,
            'participants' => $this->participants($bids),
            'topBid' => $topBid,
            'minimumNextBid' => $this->listing->minimumNextBid(),
            'isLeader' => $isLeader,
            'isOutbid' => $hasBid && ! $isLeader && $this->listing->isOpen(),
            'isLive' => $this->listing->status === Listing::STATUS_ACTIVE,
        ])->title($this->listing->title);
    }
}
