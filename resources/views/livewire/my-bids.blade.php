@php
    use App\Livewire\MyBids;
    use App\Support\Money;

    $badgeColor = [
        MyBids::WINNING => 'green',
        MyBids::OUTBID => 'amber',
        MyBids::WON => 'green',
        MyBids::LOST => 'zinc',
    ];
@endphp

<div class="flex max-w-4xl flex-col gap-10">
    <x-page-header title="My bids" description="Everything you've bid on: what you're winning, where you've been outbid, and what you've won." />

    @if ($active->isEmpty() && $finished->isEmpty())
        <flux:card class="flex flex-col items-start gap-3">
            <flux:text>You haven't bid on anything yet.</flux:text>
            <flux:button variant="primary" :href="route('listings.index')" wire:navigate>Browse listings</flux:button>
        </flux:card>
    @endif

    @foreach (['In progress' => $active, 'Finished' => $finished] as $heading => $listings)
        @continue($listings->isEmpty())

        <section class="flex flex-col gap-3" wire:key="section-{{ Str::slug($heading) }}">
            <flux:heading size="lg" level="2">{{ $heading }} ({{ $listings->count() }})</flux:heading>

            <ul class="divide-y divide-zinc-200 overflow-hidden rounded-2xl border border-zinc-200 bg-white shadow-xs dark:divide-zinc-800 dark:border-zinc-700 dark:bg-zinc-900">
                @foreach ($listings as $listing)
                    <li wire:key="my-bid-{{ $listing->id }}" data-status="{{ $listing->my_status }}"
                        class="flex flex-wrap items-center justify-between gap-x-6 gap-y-2 px-4 py-3">
                        <div class="min-w-0 basis-full sm:basis-0 sm:flex-1">
                            <a href="{{ route('listings.show', $listing) }}" wire:navigate
                                class="block truncate font-medium text-zinc-900 hover:underline dark:text-white">{{ $listing->title }}</a>
                            <div class="text-sm text-zinc-500 dark:text-zinc-400">
                                Qty {{ $listing->quantity }} ·
                                @if ($listing->isOpen())
                                    {{ $listing->timeRemaining() }}
                                @else
                                    ended {{ $listing->ends_at->format('j M Y H:i') }}
                                @endif
                            </div>
                        </div>

                        <dl class="flex gap-6 text-sm">
                            <div>
                                <dt class="text-zinc-500 dark:text-zinc-400">Your highest bid</dt>
                                <dd class="font-semibold tabular-nums text-zinc-900 dark:text-white">{{ Money::format($listing->my_highest_bid) }}</dd>
                            </div>
                            <div>
                                <dt class="text-zinc-500 dark:text-zinc-400">{{ $listing->isOpen() ? 'Top bid' : 'Winning bid' }}</dt>
                                <dd class="font-semibold tabular-nums text-zinc-900 dark:text-white">{{ Money::format($listing->bids_max_amount) }}</dd>
                            </div>
                        </dl>

                        <div class="ms-auto flex items-center justify-end gap-2 sm:ms-0 sm:w-44">
                            @if ($listing->my_status === MyBids::OUTBID)
                                <flux:button size="xs" :href="route('listings.show', $listing)" wire:navigate>Bid again</flux:button>
                            @endif
                            @if ($listing->my_status === MyBids::WON)
                                <flux:button size="xs" icon="document-arrow-down" :href="route('listings.invoice', $listing)" data-invoice-link>Invoice</flux:button>
                            @endif
                            <flux:badge size="sm" :color="$badgeColor[$listing->my_status]"
                                :icon="in_array($listing->my_status, [MyBids::WINNING, MyBids::WON]) ? 'trophy' : null">
                                {{ $listing->my_status }}
                            </flux:badge>
                        </div>
                    </li>
                @endforeach
            </ul>
        </section>
    @endforeach
</div>
