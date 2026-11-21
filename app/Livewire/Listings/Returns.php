<?php

namespace App\Livewire\Listings;

use App\Actions\HandleReturn;
use App\Actions\LogReturn;
use App\Models\Listing;
use App\Models\ReturnRequest;
use App\Support\Impersonation;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Returns on a won listing: the winner logs faulty units for replacement
 * and follows their progress. Admins see the list here too.
 */
class Returns extends Component
{
    #[Locked]
    public Listing $listing;

    public bool $showForm = false;

    public string $quantity = '1';

    public string $reason = '';

    public string $details = '';

    public ?string $status = null;

    public function mount(Listing $listing): void
    {
        $this->listing = $listing;
        abort_unless($this->canView(), 403);
    }

    private function canView(): bool
    {
        $user = auth()->user();

        return $user->is_admin || ($this->listing->winner_id !== null && $this->listing->winner_id === $user->id);
    }

    private function isWinner(): bool
    {
        return $this->listing->winner_id === auth()->id();
    }

    public function logReturn(LogReturn $logReturn): void
    {
        abort_unless($this->isWinner(), 403);
        $this->status = null;

        if (Impersonation::isActive()) {
            throw ValidationException::withMessages(['quantity' => "Returns can't be logged while you're logged in as another user."]);
        }

        $available = $this->listing->fresh()->returnableQuantity();

        $this->validate([
            'quantity' => ['required', 'integer', 'min:1', 'max:'.max(1, $available)],
            'reason' => ['required', Rule::in(array_keys(ReturnRequest::REASONS))],
            'details' => ['nullable', 'string', 'max:2000'],
        ], attributes: ['quantity' => 'quantity', 'reason' => 'reason']);

        $return = $logReturn->handle(auth()->user(), $this->listing, (int) $this->quantity, $this->reason, $this->details);

        $this->reset('showForm', 'reason', 'details');
        $this->quantity = '1';
        $this->status = "Return {$return->number()} logged. We'll let you know once it's been looked at.";
    }

    public function cancel(int $returnId, HandleReturn $handleReturn): void
    {
        abort_unless($this->isWinner(), 403);

        if (Impersonation::isActive()) {
            return;
        }

        $return = $this->listing->returnRequests()->findOrFail($returnId);
        $handleReturn->cancel($return, auth()->user());

        $this->status = "Return {$return->number()} cancelled.";
    }

    public function render()
    {
        $this->listing->refresh();

        return view('livewire.listings.returns', [
            'returns' => $this->listing->returnRequests()->latest('id')->get(),
            'isWinner' => $this->isWinner(),
            'canLog' => $this->isWinner() && $this->listing->acceptsReturns() && ! Impersonation::isActive(),
            'available' => $this->listing->returnableQuantity(),
        ]);
    }
}
