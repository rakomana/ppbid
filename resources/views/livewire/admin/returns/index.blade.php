@php
    use App\Models\ReturnRequest;

    $actionLabels = [
        'approve' => 'Approve return',
        'reject' => 'Reject return',
        'receive' => 'Mark faulty units received',
        'replace' => 'Mark units replaced',
    ];
@endphp

<div class="flex flex-col gap-6">
    <x-page-header title="Returns" description="Faulty units logged by winners. Units are replaced, never refunded." />

    @if (session('status'))
        <flux:callout variant="success" icon="check-circle" :heading="session('status')" />
    @endif

    <div class="flex flex-col gap-3 sm:flex-row">
        <div class="sm:w-80">
            <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" placeholder="Search listing, buyer or RET number" />
        </div>
        <div class="sm:w-48">
        <flux:select wire:model.live="status">
            <flux:select.option value="open">Open ({{ $openCount }})</flux:select.option>
            <flux:select.option value="all">All</flux:select.option>
            @foreach (ReturnRequest::STATUS_LABELS as $value => $label)
                <flux:select.option :value="$value">{{ $label }}</flux:select.option>
            @endforeach
        </flux:select>
        </div>
    </div>

    <flux:table :paginate="$returns">
        <flux:table.columns>
            <flux:table.column>Return</flux:table.column>
            <flux:table.column>Listing</flux:table.column>
            <flux:table.column>Buyer</flux:table.column>
            <flux:table.column align="end">Units</flux:table.column>
            <flux:table.column>Reason</flux:table.column>
            <flux:table.column>Status</flux:table.column>
            <flux:table.column></flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @forelse ($returns as $return)
                <flux:table.row :key="$return->id" data-return-row="{{ $return->status }}">
                    <flux:table.cell variant="strong">
                        {{ $return->number() }}
                        <div class="text-xs font-normal text-zinc-500 dark:text-zinc-400">{{ $return->created_at->format('j M Y H:i') }}</div>
                    </flux:table.cell>
                    <flux:table.cell class="max-w-48">
                        <a href="{{ route('listings.show', $return->listing) }}#returns" wire:navigate class="block truncate hover:underline">{{ $return->listing->title }}</a>
                        <div class="text-xs text-zinc-500 dark:text-zinc-400">Lot of {{ $return->listing->quantity }}</div>
                    </flux:table.cell>
                    <flux:table.cell>
                        {{ $return->user->name }}
                        <div class="text-xs text-zinc-500 dark:text-zinc-400">Bidder {{ $return->user->bidder_number }}</div>
                    </flux:table.cell>
                    <flux:table.cell align="end" class="tabular-nums">
                        @if ($return->quantity_approved !== null && $return->quantity_approved !== $return->quantity_requested)
                            {{ $return->quantity_approved }} <span class="text-zinc-400">of {{ $return->quantity_requested }}</span>
                        @else
                            {{ $return->units() }}
                        @endif
                    </flux:table.cell>
                    <flux:table.cell class="max-w-48">
                        <div class="truncate">{{ $return->reasonLabel() }}</div>
                        @if ($return->details)
                            <div class="truncate text-xs text-zinc-500 dark:text-zinc-400" title="{{ $return->details }}">{{ $return->details }}</div>
                        @endif
                    </flux:table.cell>
                    <flux:table.cell>
                        <flux:badge size="sm" :color="$return->statusColor()">{{ $return->statusLabel() }}</flux:badge>
                        @if ($return->admin_note)
                            <div class="mt-1 max-w-36 truncate text-xs text-zinc-500 dark:text-zinc-400" title="{{ $return->admin_note }}">{{ $return->admin_note }}</div>
                        @endif
                    </flux:table.cell>
                    <flux:table.cell align="end">
                        <div class="flex justify-end gap-2">
                            @switch($return->status)
                                @case(ReturnRequest::STATUS_REQUESTED)
                                    <flux:button size="sm" variant="primary" wire:click="open({{ $return->id }}, 'approve')">Approve</flux:button>
                                    <flux:button size="sm" variant="danger" wire:click="open({{ $return->id }}, 'reject')">Reject</flux:button>
                                    @break
                                @case(ReturnRequest::STATUS_APPROVED)
                                    <flux:button size="sm" wire:click="open({{ $return->id }}, 'receive')">Received</flux:button>
                                    <flux:button size="sm" variant="primary" wire:click="open({{ $return->id }}, 'replace')">Replaced</flux:button>
                                    @break
                                @case(ReturnRequest::STATUS_RECEIVED)
                                    <flux:button size="sm" variant="primary" wire:click="open({{ $return->id }}, 'replace')">Replaced</flux:button>
                                    @break
                                @case(ReturnRequest::STATUS_REPLACED)
                                    <flux:button size="sm" icon="document-arrow-down" :href="route('returns.note', $return)" data-note-link>Note</flux:button>
                                    @break
                            @endswitch
                        </div>
                    </flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="7" class="text-center">No returns here.</flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>

    <flux:modal name="handle-return" class="w-full max-w-md">
        @if ($current)
            <form wire:submit="save" class="flex flex-col gap-4">
                <div>
                    <flux:heading size="lg">{{ $actionLabels[$action] ?? '' }}</flux:heading>
                    <flux:text class="mt-1">
                        {{ $current->number() }} · {{ $current->user->name }} · {{ $current->units() }} {{ Str::plural('unit', $current->units()) }} of “{{ $current->listing->title }}”
                    </flux:text>
                </div>

                @if ($action === 'approve')
                    <flux:input wire:model="quantity" type="number" min="1" :max="$current->quantity_requested" label="Units to replace"
                        :description="'The buyer logged '.$current->quantity_requested.'. Approve fewer if some tested fine.'" required />
                @endif

                <flux:textarea wire:model="note" rows="3"
                    :label="$action === 'reject' ? 'Reason (the buyer sees this)' : 'Note for the buyer (optional)'"
                    :placeholder="match ($action) {
                        'approve' => 'e.g. Drop them off at the counter, Mon–Fri 8–5.',
                        'reject' => 'e.g. All 3 units passed MemTest86 here.',
                        'replace' => 'e.g. Replaced with the same model.',
                        default => '',
                    }" />

                <div class="flex justify-end gap-2">
                    <flux:modal.close>
                        <flux:button variant="ghost">Cancel</flux:button>
                    </flux:modal.close>
                    <flux:button type="submit" :variant="$action === 'reject' ? 'danger' : 'primary'">
                        {{ ['approve' => 'Approve', 'reject' => 'Reject', 'receive' => 'Mark received', 'replace' => 'Mark replaced'][$action] ?? 'Save' }}
                    </flux:button>
                </div>
            </form>
        @endif
    </flux:modal>
</div>
