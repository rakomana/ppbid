<div class="flex flex-col gap-6">
    <x-page-header title="Users" description="Only admins can add people. New users can log in straight away with the password you set.">
        <x-slot:actions>
            <flux:button variant="primary" icon="user-plus" :href="route('admin.users.create')" wire:navigate>
                Add user
            </flux:button>
        </x-slot:actions>
    </x-page-header>

    @if (session('status'))
        <flux:callout variant="success" icon="check-circle" :heading="session('status')" />
    @endif

    @if (session('error'))
        <flux:callout variant="danger" icon="exclamation-triangle" :heading="session('error')" />
    @endif

    <div class="flex flex-wrap items-center gap-3">
        <div class="w-full max-w-sm">
            <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" placeholder="Search name, email or bidder #" clearable />
        </div>
        <div class="w-48">
            <flux:select wire:model.live="status" aria-label="Filter by status">
                <flux:select.option value="">Everyone</flux:select.option>
                <flux:select.option value="active">Active</flux:select.option>
                <flux:select.option value="deactivated">Deactivated</flux:select.option>
            </flux:select>
        </div>
    </div>

    <flux:table :paginate="$users">
        <flux:table.columns>
            <flux:table.column>Bidder</flux:table.column>
            <flux:table.column>Name</flux:table.column>
            <flux:table.column>Email</flux:table.column>
            <flux:table.column>Role</flux:table.column>
            <flux:table.column align="end">Bids</flux:table.column>
            <flux:table.column>Last login</flux:table.column>
            <flux:table.column>Added</flux:table.column>
            <flux:table.column></flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @forelse ($users as $user)
                <flux:table.row :key="$user->id" @class(['opacity-60' => $user->isDeactivated()])>
                    <flux:table.cell class="tabular-nums">{{ $user->bidder_number }}</flux:table.cell>
                    <flux:table.cell variant="strong">
                        {{ $user->name }}
                        @if ($user->is(auth()->user()))
                            <span class="ms-1 text-zinc-400">(you)</span>
                        @endif
                    </flux:table.cell>
                    <flux:table.cell>{{ $user->email }}</flux:table.cell>
                    <flux:table.cell>
                        <div class="flex gap-1">
                            @if ($user->is_admin)
                                <flux:badge size="sm" color="orange" icon="shield-check">Admin</flux:badge>
                            @else
                                <flux:badge size="sm" color="zinc">Bidder</flux:badge>
                            @endif
                            @if ($user->isDeactivated())
                                <flux:badge size="sm" color="red" icon="no-symbol">Deactivated</flux:badge>
                            @endif
                        </div>
                    </flux:table.cell>
                    <flux:table.cell align="end" class="tabular-nums">{{ $user->bids_count }}</flux:table.cell>
                    <flux:table.cell><x-last-login :user="$user" /></flux:table.cell>
                    <flux:table.cell>{{ $user->created_at->format('j M Y') }}</flux:table.cell>
                    <flux:table.cell align="end">
                        <div class="flex justify-end gap-2">
                            <x-impersonate-button :user="$user" />
                            <flux:button size="sm" icon="pencil-square" :href="route('admin.users.edit', $user)" wire:navigate>Edit</flux:button>
                        </div>
                    </flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="8" class="text-center">No users match “{{ $search }}”.</flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>
</div>
