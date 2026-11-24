<div class="flex flex-col gap-6">
    <x-page-header title="Listings" description="Create, edit and close auction listings.">
        <x-slot:actions>
            <flux:button variant="primary" icon="plus" :href="route('admin.listings.create')" wire:navigate>
                Create listing
            </flux:button>
        </x-slot:actions>
    </x-page-header>

    @if (session('status'))
        <flux:callout variant="success" icon="check-circle" :heading="session('status')" />
    @endif

    <flux:table :paginate="$listings">
        <flux:table.columns>
            <flux:table.column>Title</flux:table.column>
            <flux:table.column align="end">Qty</flux:table.column>
            <flux:table.column align="end">Min price</flux:table.column>
            <flux:table.column align="end">Buy Now</flux:table.column>
            <flux:table.column align="end">Top bid</flux:table.column>
            <flux:table.column align="end">Bids</flux:table.column>
            <flux:table.column>Ends</flux:table.column>
            <flux:table.column>Status</flux:table.column>
            <flux:table.column></flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @forelse ($listings as $listing)
                <flux:table.row :key="$listing->id">
                    <flux:table.cell variant="strong" class="max-w-72 truncate">{{ $listing->title }}</flux:table.cell>
                    <flux:table.cell align="end">{{ $listing->quantity }}</flux:table.cell>
                    <flux:table.cell align="end">{{ \App\Support\Money::format($listing->min_price) }}</flux:table.cell>
                    <flux:table.cell align="end">
                        {{ $listing->buy_now_price !== null ? \App\Support\Money::format($listing->buy_now_price) : '—' }}
                    </flux:table.cell>
                    <flux:table.cell align="end">
                        {{ $listing->bids_max_amount !== null ? \App\Support\Money::format($listing->bids_max_amount) : '—' }}
                    </flux:table.cell>
                    <flux:table.cell align="end">{{ $listing->bids_count }}</flux:table.cell>
                    <flux:table.cell>{{ $listing->ends_at->format('j M Y H:i') }}</flux:table.cell>
                    <flux:table.cell>
                        @if ($listing->status === \App\Models\Listing::STATUS_CLOSED)
                            <flux:badge size="sm" color="zinc">
                                {{ $listing->bought_now ? 'Bought now' : 'Closed' }}{{ $listing->winner ? ' · Bidder '.$listing->winner->bidder_number : ' · no bids' }}
                            </flux:badge>
                        @elseif ($listing->ends_at->isPast())
                            <flux:badge size="sm" color="amber">Expired, closing</flux:badge>
                        @else
                            <flux:badge size="sm" color="green">Active</flux:badge>
                        @endif
                    </flux:table.cell>
                    <flux:table.cell align="end">
                        @if ($listing->status === \App\Models\Listing::STATUS_ACTIVE)
                            <div class="flex justify-end gap-2">
                                <flux:button size="sm" icon="pencil-square" :href="route('admin.listings.edit', $listing)" wire:navigate>
                                    Edit
                                </flux:button>
                                <flux:button
                                    size="sm"
                                    variant="danger"
                                    icon="lock-closed"
                                    wire:click="closeNow({{ $listing->id }})"
                                    wire:confirm="Close “{{ $listing->title }}” now? The current top bidder will win."
                                >
                                    Close now
                                </flux:button>
                            </div>
                        @elseif ($listing->winner_id)
                            <div class="flex justify-end gap-2">
                                <flux:button size="sm" icon="document-arrow-down" :href="route('listings.invoice', $listing)" data-invoice-link>
                                    Invoice
                                </flux:button>
                                <x-share-invoice :listing="$listing" />
                            </div>
                        @endif
                    </flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="9" class="text-center">No listings yet.</flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>
</div>
