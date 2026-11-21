<?php

namespace App\Livewire\Admin\Returns;

use App\Actions\HandleReturn;
use App\Models\ReturnRequest;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Title('Admin: Returns')]
class Index extends Component
{
    use WithPagination;

    /** 'open' (default), 'all', or one status. */
    #[Url(except: 'open')]
    public string $status = 'open';

    #[Url(except: '')]
    public string $search = '';

    /** The return being handled in the modal, and what the admin is doing with it. */
    #[Locked]
    public ?int $returnId = null;

    #[Locked]
    public string $action = '';

    public string $quantity = '';

    public string $note = '';

    /**
     * Runs on every request, not only on the initial page load that the route middleware covers.
     */
    public function boot(): void
    {
        abort_unless(auth()->user()?->is_admin, 403);
    }

    public function updatedStatus(): void
    {
        $this->resetPage();
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    /**
     * Open the modal for approve / reject / received / replaced.
     */
    public function open(int $returnId, string $action): void
    {
        abort_unless(in_array($action, ['approve', 'reject', 'receive', 'replace'], true), 404);

        $return = ReturnRequest::findOrFail($returnId);

        $this->resetErrorBag();
        $this->returnId = $return->id;
        $this->action = $action;
        $this->quantity = (string) $return->units();
        $this->note = '';

        $this->modal('handle-return')->show();
    }

    public function save(HandleReturn $handleReturn): void
    {
        $return = ReturnRequest::findOrFail($this->returnId);
        $admin = auth()->user();

        if ($this->action === 'approve') {
            $this->validate(['quantity' => ['required', 'integer', 'min:1', 'max:'.$return->quantity_requested]]);
        }
        $this->validate(['note' => [$this->action === 'reject' ? 'required' : 'nullable', 'string', 'max:2000']]);

        $return = match ($this->action) {
            'approve' => $handleReturn->approve($return, $admin, (int) $this->quantity, $this->note),
            'reject' => $handleReturn->reject($return, $admin, $this->note),
            'receive' => $handleReturn->markReceived($return, $admin, $this->note),
            'replace' => $handleReturn->markReplaced($return, $admin, $this->note),
        };

        $this->modal('handle-return')->close();
        $this->reset('returnId', 'action', 'quantity', 'note');

        session()->flash('status', "Return {$return->number()} is now {$return->statusLabel()}. The buyer has been notified.");
    }

    public function render()
    {
        $term = trim($this->search);

        $returns = ReturnRequest::query()
            ->with(['listing', 'user', 'handler'])
            ->when($this->status === 'open', fn (Builder $query) => $query->open())
            ->when(array_key_exists($this->status, ReturnRequest::STATUS_LABELS), fn (Builder $query) => $query->where('status', $this->status))
            ->when($term !== '', function (Builder $query) use ($term) {
                $query->where(function (Builder $query) use ($term) {
                    $query->whereHas('listing', fn (Builder $q) => $q->where('title', 'like', "%{$term}%"))
                        ->orWhereHas('user', fn (Builder $q) => $q->where('name', 'like', "%{$term}%")->orWhere('email', 'like', "%{$term}%"));

                    // "45", "RET-45" or "RET-000045" finds that return.
                    $number = ltrim(preg_replace('/^(RET|RN)-?/i', '', $term), '0');
                    if (ctype_digit($number)) {
                        $query->orWhere('id', (int) $number);
                    }
                });
            })
            ->orderByRaw("case status when 'requested' then 0 when 'approved' then 1 when 'received' then 2 else 3 end")
            ->latest('id')
            ->paginate(25);

        return view('livewire.admin.returns.index', [
            'returns' => $returns,
            'current' => $this->returnId ? ReturnRequest::with('listing', 'user')->find($this->returnId) : null,
            'openCount' => ReturnRequest::query()->open()->count(),
        ]);
    }
}
