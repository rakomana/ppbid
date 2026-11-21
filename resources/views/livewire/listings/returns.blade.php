@php
    use App\Models\ReturnRequest;
@endphp

<section class="flex flex-col gap-3" id="returns" data-returns>
    <div class="flex flex-wrap items-center justify-between gap-2">
        <flux:heading size="lg" level="2">Returns</flux:heading>

        @if ($canLog && $available > 0 && ! $showForm)
            <flux:button size="sm" icon="arrow-uturn-left" wire:click="$set('showForm', true)" data-log-return>
                Log a return
            </flux:button>
        @endif
    </div>

    @if ($status)
        <flux:callout variant="success" icon="check-circle" :heading="$status" />
    @endif

    @if ($isWinner)
        <flux:text size="sm">
            Faulty units are replaced, not refunded.
            @if ($listing->acceptsReturns())
                You can log a return until {{ $listing->returnDeadline()->format('j M Y H:i') }}.
            @else
                The return window closed on {{ $listing->returnDeadline()->format('j M Y H:i') }}.
            @endif
        </flux:text>
    @endif

    @if ($isWinner && $listing->acceptsReturns() && \App\Support\Impersonation::isActive())
        <flux:card class="border-dashed" data-impersonation-no-return>
            <flux:text>Logging returns is turned off while you're logged in as another user. Ask the buyer to log it themselves.</flux:text>
        </flux:card>
    @endif

    @if ($canLog && $available === 0 && $returns->isNotEmpty())
        <flux:text size="sm">Every unit of this lot is already in an open return.</flux:text>
    @endif

    @if ($showForm && $canLog)
        <flux:card>
            <form wire:submit="logReturn" class="flex flex-col gap-4" data-return-form>
                <flux:input wire:model="quantity" type="number" min="1" :max="$available" label="Faulty units"
                    :description="'Up to '.$available.' of the '.$listing->quantity.' you won.'" required />

                <flux:select wire:model="reason" label="What's wrong?" placeholder="Choose a reason" required>
                    @foreach (ReturnRequest::REASONS as $value => $label)
                        <flux:select.option :value="$value">{{ $label }}</flux:select.option>
                    @endforeach
                </flux:select>

                <flux:textarea wire:model="details" label="Details" rows="3"
                    placeholder="e.g. 2 sticks fail MemTest86 at 2400MHz" />

                <div class="flex gap-2">
                    <flux:button type="submit" variant="primary">Log return</flux:button>
                    <flux:button type="button" variant="ghost" wire:click="$set('showForm', false)">Cancel</flux:button>
                </div>
            </form>
        </flux:card>
    @endif

    @if ($returns->isEmpty())
        <flux:text>No returns logged.</flux:text>
    @else
        <ul class="divide-y divide-zinc-200 overflow-hidden rounded-2xl border border-zinc-200 bg-white shadow-xs dark:divide-zinc-800 dark:border-zinc-700 dark:bg-zinc-900">
            @foreach ($returns as $return)
                <li wire:key="return-{{ $return->id }}" data-return="{{ $return->status }}" class="flex flex-col gap-1 px-4 py-3 text-sm">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <span class="font-medium text-zinc-900 dark:text-white">
                            {{ $return->number() }} ·
                            @if ($return->quantity_approved !== null && $return->quantity_approved !== $return->quantity_requested)
                                {{ $return->quantity_approved }} of {{ $return->quantity_requested }} units approved
                            @else
                                {{ $return->units() }} {{ Str::plural('unit', $return->units()) }}
                            @endif
                        </span>
                        <span class="flex items-center gap-2">
                            @if ($return->status === ReturnRequest::STATUS_REPLACED)
                                <flux:button size="xs" icon="document-arrow-down" :href="route('returns.note', $return)" data-note-link>
                                    Replacement note
                                </flux:button>
                            @endif
                            @if ($isWinner && $return->status === ReturnRequest::STATUS_REQUESTED && ! \App\Support\Impersonation::isActive())
                                <flux:button size="xs" variant="ghost" wire:click="cancel({{ $return->id }})"
                                    wire:confirm="Cancel return {{ $return->number() }}?">Cancel</flux:button>
                            @endif
                            <flux:badge size="sm" :color="$return->statusColor()">{{ $return->statusLabel() }}</flux:badge>
                        </span>
                    </div>
                    <div class="text-zinc-600 dark:text-zinc-300">{{ $return->reasonLabel() }}@if ($return->details) — {{ $return->details }}@endif</div>
                    @if ($return->admin_note)
                        <div class="text-zinc-600 dark:text-zinc-300"><span class="font-medium">Note:</span> {{ $return->admin_note }}</div>
                    @endif
                    <div class="text-xs text-zinc-400 dark:text-zinc-500">
                        Logged {{ $return->created_at->format('j M Y H:i') }}
                        @if ($return->replaced_at) · replaced {{ $return->replaced_at->format('j M Y H:i') }} @endif
                    </div>
                </li>
            @endforeach
        </ul>
    @endif

    @if (auth()->user()->is_admin)
        <div>
            <flux:button size="sm" variant="ghost" icon="arrow-right" :href="route('admin.returns.index')" wire:navigate>Manage returns</flux:button>
        </div>
    @endif
</section>
