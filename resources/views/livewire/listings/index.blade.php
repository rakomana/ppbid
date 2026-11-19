@php use App\Support\Money; @endphp

<div class="flex flex-col gap-10">
    <section class="flex flex-col gap-5">
        <x-page-header title="Active listings" description="Each listing is sold as one lot. The highest bid when the timer runs out wins everything." />

        <x-install-app />

        @if ($active->isEmpty())
            <div class="flex flex-col items-center gap-2 rounded-2xl border border-dashed border-zinc-300 px-6 py-12 text-center dark:border-zinc-700">
                <flux:icon.tag class="size-8 text-zinc-400" />
                <flux:heading>No open listings right now</flux:heading>
                <flux:text>New listings will appear here as soon as they're added.</flux:text>
            </div>
        @else
            <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                @foreach ($active as $listing)
                    @php $endingSoon = $listing->ends_at->lte(now()->addHour()); @endphp

                    <a href="{{ route('listings.show', $listing) }}" wire:navigate wire:key="active-{{ $listing->id }}"
                        class="group flex flex-col gap-4 rounded-2xl border border-zinc-200 bg-white p-5 shadow-xs transition hover:-translate-y-0.5 hover:border-orange-300 hover:shadow-md dark:border-zinc-700 dark:bg-zinc-900 dark:hover:border-orange-400/50">
                        <div class="flex items-start justify-between gap-3">
                            <h2 class="font-semibold leading-snug text-zinc-900 group-hover:text-accent-content dark:text-white">{{ $listing->title }}</h2>
                            <flux:badge size="sm" color="zinc" class="shrink-0">Qty {{ $listing->quantity }}</flux:badge>
                        </div>

                        <div>
                            @if ($listing->bids_max_amount !== null)
                                <div class="text-xs font-medium uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Top bid</div>
                                <div class="text-2xl font-bold tabular-nums tracking-tight text-zinc-900 dark:text-white">
                                    {{ Money::format($listing->bids_max_amount) }}
                                </div>
                            @else
                                <div class="text-xs font-medium uppercase tracking-wide text-zinc-500 dark:text-zinc-400">No bids yet</div>
                                <div class="text-2xl font-bold tabular-nums tracking-tight text-zinc-900 dark:text-white">
                                    Starting at {{ Money::format($listing->min_price) }}
                                </div>
                            @endif
                        </div>

                        <div class="mt-auto flex items-center justify-between gap-3 border-t border-zinc-100 pt-3 text-sm dark:border-zinc-800">
                            <span class="flex items-center gap-1.5 text-zinc-500 dark:text-zinc-400">
                                <flux:icon.users variant="micro" />
                                {{ $listing->bidders_count }} {{ Str::plural('bidder', $listing->bidders_count) }}
                            </span>

                            @if ($endingSoon)
                                <flux:badge size="sm" color="orange" icon="clock">{{ $listing->timeRemaining() }}</flux:badge>
                            @else
                                <span class="flex items-center gap-1.5 tabular-nums text-zinc-500 dark:text-zinc-400">
                                    <flux:icon.clock variant="micro" />
                                    {{ $listing->timeRemaining() }}
                                </span>
                            @endif
                        </div>
                    </a>
                @endforeach
            </div>
        @endif
    </section>

    <section class="flex flex-col gap-4">
        <flux:heading size="lg" level="2">Recently closed</flux:heading>

        @if ($closed->isEmpty())
            <flux:text>No listings have closed yet.</flux:text>
        @else
            <div class="divide-y divide-zinc-200 overflow-hidden rounded-2xl border border-zinc-200 bg-white dark:divide-zinc-800 dark:border-zinc-700 dark:bg-zinc-900">
                @foreach ($closed as $listing)
                    <a href="{{ route('listings.show', $listing) }}" wire:navigate wire:key="closed-{{ $listing->id }}"
                        class="flex flex-wrap items-center justify-between gap-x-4 gap-y-1 px-5 py-3.5 transition hover:bg-zinc-50 dark:hover:bg-zinc-800/60">
                        <div class="min-w-0">
                            <div class="truncate font-medium text-zinc-900 dark:text-white">{{ $listing->title }}</div>
                            <div class="text-sm text-zinc-500 dark:text-zinc-400">
                                Qty {{ $listing->quantity }} · closed {{ $listing->ends_at->format('j M Y H:i') }}
                            </div>
                        </div>

                        <div class="text-sm">
                            @if ($listing->winner)
                                <span class="text-zinc-500 dark:text-zinc-400">Won by</span>
                                <span class="font-medium text-zinc-900 dark:text-white">Bidder {{ $listing->winner->bidder_number }}</span>
                                <span class="text-zinc-500 dark:text-zinc-400">for</span>
                                <span class="font-semibold tabular-nums text-zinc-900 dark:text-white">{{ Money::format($listing->bids_max_amount) }}</span>
                            @else
                                <flux:badge size="sm" color="zinc">No bids</flux:badge>
                            @endif
                        </div>
                    </a>
                @endforeach
            </div>
        @endif
    </section>
</div>
