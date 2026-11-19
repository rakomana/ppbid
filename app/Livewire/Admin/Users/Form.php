<?php

namespace App\Livewire\Admin\Users;

use App\Actions\DeactivateUser;
use App\Models\Listing;
use App\Models\User;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;

class Form extends Component
{
    #[Locked]
    public ?User $user = null;

    public string $name = '';

    public string $email = '';

    public string $password = '';

    public bool $is_admin = false;

    /**
     * Runs on every request, including "Save",
     * not only on the initial page load that the route middleware covers.
     */
    public function boot(): void
    {
        abort_unless(auth()->user()?->is_admin, 403);
    }

    public function mount(?User $user = null): void
    {
        if (! $user?->exists) {
            return;
        }

        $this->user = $user;
        $this->name = $user->name;
        $this->email = $user->email;
        $this->is_admin = (bool) $user->is_admin;
    }

    /**
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', Rule::unique(User::class)->ignore($this->user)],
            'password' => [$this->user ? 'nullable' : 'required', 'string', Password::defaults()],
            'is_admin' => ['boolean'],
        ];
    }

    public function generatePassword(): void
    {
        $this->password = Str::password(12, symbols: false);
        $this->resetErrorBag('password');
    }

    public function save()
    {
        $this->email = Str::lower(trim($this->email));
        $data = $this->validate();

        if ($this->user?->is(auth()->user()) && ! $data['is_admin']) {
            $this->addError('is_admin', "You can't remove your own admin access.");

            return null;
        }

        $user = $this->user ?? new User;

        // is_admin and email_verified_at are not mass assignable, on purpose.
        $user->forceFill([
            'name' => $data['name'],
            'email' => $data['email'],
            'is_admin' => $data['is_admin'],
        ]);

        if (! $user->exists) {
            $user->email_verified_at = now();
        }

        if (filled($data['password'])) {
            $user->password = $data['password'];
        }

        $isNew = ! $user->exists;
        $user->save();

        session()->flash('status', $isNew
            ? "{$user->name} has been added as ".($user->is_admin ? 'an admin' : "Bidder {$user->bidder_number}").'. They can log in with the password you set.'
            : "{$user->name} has been updated.");

        return $this->redirectRoute('admin.users.index', navigate: true);
    }

    /**
     * Deactivate instead of delete: the user can no longer log in or bid, and their
     * bids on listings that haven't closed are withdrawn. Closed results are kept.
     */
    public function deactivate(DeactivateUser $deactivateUser): void
    {
        if (! $this->user || $this->user->is(auth()->user())) {
            $this->addError('status', "You can't deactivate your own account.");

            return;
        }

        $withdrawn = $deactivateUser->handle($this->user);

        session()->flash('status', "{$this->user->name} has been deactivated. They can no longer log in or bid."
            .($withdrawn ? " Their bids on {$withdrawn} ".Str::plural('listing', $withdrawn).' were withdrawn.' : ''));
        $this->redirectRoute('admin.users.index', navigate: true);
    }

    public function reactivate(): void
    {
        if (! $this->user) {
            return;
        }

        $this->user->forceFill(['deactivated_at' => null])->save();

        session()->flash('status', "{$this->user->name} has been reactivated and can log in again.");
        $this->redirectRoute('admin.users.index', navigate: true);
    }

    /**
     * Listings not yet closed where this user has bids that count, and how many of
     * those they're leading. These are the bids deactivation would withdraw.
     *
     * @return array{listings: int, leading: int}
     */
    #[Computed]
    public function bidsAtStake(): array
    {
        if (! $this->user) {
            return ['listings' => 0, 'leading' => 0];
        }

        $listings = Listing::query()
            ->where('status', Listing::STATUS_ACTIVE)
            ->whereHas('bids', fn ($bids) => $bids->where('user_id', $this->user->id))
            ->withLeaderId()
            ->get();

        return [
            'listings' => $listings->count(),
            'leading' => $listings->filter(fn (Listing $listing) => (int) $listing->leader_id === $this->user->id)->count(),
        ];
    }

    public function render()
    {
        return view('livewire.admin.users.form')
            ->title($this->user ? 'Admin: Edit user' : 'Admin: Add user');
    }
}
