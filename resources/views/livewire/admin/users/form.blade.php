<div class="flex max-w-2xl flex-col gap-6">
    <div>
        <flux:button variant="ghost" size="sm" icon="arrow-left" :href="route('admin.users.index')" wire:navigate>
            Back to users
        </flux:button>
    </div>

    <x-page-header
        :title="$user ? 'Edit user' : 'Add user'"
        :description="$user
            ? 'Bidder '.$user->bidder_number.' · added '.$user->created_at->format('j M Y')
            : 'Give the person their email and password so they can log in.'" />

    @if ($user?->isDeactivated())
        <flux:callout variant="warning" icon="no-symbol"
            heading="This account was deactivated on {{ $user->deactivated_at->format('j M Y H:i') }}."
            text="They can't log in or bid. Their bids and results are kept." />
    @endif

    <form wire:submit="save" class="flex flex-col gap-6 rounded-2xl border border-zinc-200 bg-white p-6 shadow-xs dark:border-zinc-700 dark:bg-zinc-900">
        <flux:input wire:model="name" label="Full name" required maxlength="255" autocomplete="off" />

        <flux:input wire:model="email" label="Email address" type="email" required maxlength="255" autocomplete="off" />

        <flux:field>
            <flux:label>{{ $user ? 'New password' : 'Password' }}</flux:label>
            <div class="flex gap-2">
                <flux:input wire:model="password" type="password" viewable autocomplete="new-password"
                    :placeholder="$user ? 'Leave blank to keep the current password' : 'At least 8 characters'" class="flex-1" />
                <flux:button type="button" icon="sparkles" wire:click="generatePassword">Generate</flux:button>
            </div>
            <flux:description>Use the eye icon to see the password so you can share it.</flux:description>
            <flux:error name="password" />
        </flux:field>

        <flux:field variant="inline">
            <flux:checkbox wire:model="is_admin" />
            <flux:label>Admin</flux:label>
            <flux:error name="is_admin" />
        </flux:field>
        <flux:text size="sm" class="-mt-4">Admins manage listings and users, and can't place bids.</flux:text>

        <div class="flex gap-2">
            <flux:button type="submit" variant="primary">{{ $user ? 'Save changes' : 'Add user' }}</flux:button>
            <flux:button :href="route('admin.users.index')" wire:navigate>Cancel</flux:button>
        </div>
    </form>

    @if ($user && ! $user->is(auth()->user()))
        <section class="flex flex-col gap-3 rounded-2xl border border-zinc-200 bg-white p-6 shadow-xs dark:border-zinc-700 dark:bg-zinc-900" data-account-status>
            <flux:heading size="lg" level="2">Account status</flux:heading>

            @if ($user->isDeactivated())
                <flux:text>
                    Reactivate this account to let {{ $user->name }} log in and bid again.
                    Bids that were withdrawn when they were deactivated stay withdrawn; they can bid again.
                </flux:text>
                <div>
                    <flux:button icon="arrow-path" wire:click="reactivate">Reactivate account</flux:button>
                </div>
            @else
                @php ['listings' => $atStake, 'leading' => $leading] = $this->bidsAtStake; @endphp

                <flux:text>
                    Deactivating signs {{ $user->name }} out and stops them logging in or bidding.
                    Their bids on listings that haven't closed are withdrawn and stop counting.
                    Nothing is deleted: closed results, the bid history and their bidder number are kept, and you can reactivate them at any time.
                </flux:text>

                @if ($atStake > 0)
                    <flux:callout variant="warning" icon="exclamation-triangle"
                        heading="Their bids on {{ $atStake }} open {{ Str::plural('listing', $atStake) }} will be withdrawn."
                        :text="$leading > 0
                            ? 'They\'re the top bidder on '.$leading.' of them. The next-highest bid will take the lead.'
                            : 'They aren\'t leading any of them, so the current leaders won\'t change.'" />
                @endif

                <div>
                    <flux:button variant="danger" icon="no-symbol" wire:click="deactivate"
                        wire:confirm="Deactivate {{ $user->name }}? They'll be signed out, won't be able to log in or bid, and their bids on {{ $atStake }} open {{ Str::plural('listing', $atStake) }} will be withdrawn.">
                        Deactivate account
                    </flux:button>
                </div>
            @endif

            <flux:error name="status" />
        </section>
    @endif
</div>
