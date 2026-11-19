<?php

namespace App\Livewire\Admin\Users;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Title('Admin: Users')]
class Index extends Component
{
    use WithPagination;

    #[Url(except: '')]
    public string $search = '';

    /** '' = everyone, 'active' or 'deactivated'. */
    #[Url(except: '')]
    public string $status = '';

    /**
     * Runs on every request, not only on the initial page load that the route middleware covers.
     */
    public function boot(): void
    {
        abort_unless(auth()->user()?->is_admin, 403);
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedStatus(): void
    {
        $this->resetPage();
    }

    public function render()
    {
        $term = trim($this->search);

        $users = User::query()
            ->withCount('bids')
            ->when($term !== '', function (Builder $query) use ($term) {
                $query->where(function (Builder $query) use ($term) {
                    $query->where('name', 'like', "%{$term}%")
                        ->orWhere('email', 'like', "%{$term}%");

                    // "14", "#14" or "#014" finds Bidder #014.
                    $number = ltrim($term, '#');
                    if (ctype_digit($number)) {
                        $query->orWhere('id', (int) $number);
                    }
                });
            })
            ->when($this->status === 'active', fn (Builder $query) => $query->whereNull('deactivated_at'))
            ->when($this->status === 'deactivated', fn (Builder $query) => $query->whereNotNull('deactivated_at'))
            ->orderByRaw('deactivated_at is not null') // deactivated users last
            ->orderByDesc('is_admin')
            ->orderBy('id')
            ->paginate(25);

        return view('livewire.admin.users.index', [
            'users' => $users,
        ]);
    }
}
