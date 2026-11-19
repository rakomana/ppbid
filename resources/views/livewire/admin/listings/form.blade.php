<div class="flex max-w-2xl flex-col gap-6">
    <div>
        <flux:button variant="ghost" size="sm" icon="arrow-left" :href="route('admin.listings.index')" wire:navigate>
            Back to listings
        </flux:button>
    </div>

    <x-page-header
        :title="$listing ? 'Edit listing' : 'Create listing'"
        description="The whole quantity is sold as one lot to the highest bidder." />

    <form wire:submit="save" class="flex flex-col gap-6 rounded-2xl border border-zinc-200 bg-white p-6 shadow-xs dark:border-zinc-700 dark:bg-zinc-900">
        <flux:input wire:model="title" label="Title" required maxlength="255" />

        <flux:textarea wire:model="description" label="Description" badge="Optional" rows="4" />

        @if ($this->hasBids)
            <flux:callout variant="warning" icon="lock-closed"
                heading="This listing has bids, so the minimum price and quantity are locked." />
        @endif

        <div class="grid gap-6 sm:grid-cols-2">
            <flux:input
                wire:model="min_price"
                label="Minimum price (R)"
                type="text"
                inputmode="decimal"
                placeholder="0.00"
                required
                :disabled="$this->hasBids"
            />

            <flux:input
                wire:model="quantity"
                label="Quantity"
                description:trailing="The whole quantity is sold as one lot."
                type="number"
                step="1"
                min="1"
                required
                :disabled="$this->hasBids"
            />
        </div>

        <flux:input
            wire:model="ends_at"
            label="Expires at"
            description:trailing="South African time (SAST). New listings default to {{ config('auction.default_duration_hours') }} hours from now."
            type="datetime-local"
            required
        />

        <div class="flex gap-2">
            <flux:button type="submit" variant="primary">
                {{ $listing ? 'Save changes' : 'Create listing' }}
            </flux:button>
            <flux:button :href="route('admin.listings.index')" wire:navigate>Cancel</flux:button>
        </div>
    </form>
</div>
