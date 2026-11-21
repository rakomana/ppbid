@php
    use App\Models\Listing;
    use App\Models\User;
    use App\Support\Money;
@endphp

<div class="flex max-w-5xl flex-col gap-6">
    <div>
        <flux:button variant="ghost" size="sm" icon="arrow-left" :href="route('listings.index')" wire:navigate>
            All listings
        </flux:button>
    </div>

    <div class="flex flex-col gap-2">
        <flux:heading size="xl" level="1">{{ $listing->title }}</flux:heading>
        <div>
            @if ($listing->isOpen())
                <flux:badge size="sm" color="green">Open</flux:badge>
            @elseif ($listing->status === Listing::STATUS_CLOSED)
                <flux:badge size="sm" color="zinc">Closed</flux:badge>
            @else
                <flux:badge size="sm" color="amber">Ended, closing</flux:badge>
            @endif
        </div>
    </div>

    <flux:callout icon="cube" color="sky">
        <flux:callout.heading>Bid is for all {{ $listing->quantity }} {{ Str::plural('item', $listing->quantity) }}</flux:callout.heading>
        <flux:callout.text>This listing is sold as one lot. The highest bid when it ends wins the whole quantity.</flux:callout.text>
    </flux:callout>

    {{-- Live section: refreshes every 5 seconds until the listing is closed. --}}
    <div
        @if ($isLive) wire:poll.5s @endif
        wire:key="live-{{ $isLive ? 'polling' : 'closed' }}"
        class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_22rem]"
    >
        {{-- Right column (first on mobile): countdown, leader and bid form --}}
        <div class="flex flex-col gap-4 lg:order-2">
            @if ($listing->status === Listing::STATUS_CLOSED)
                {{-- Final result, from the winner recorded when the listing closed. --}}
                @if ($listing->winner_id !== null && $listing->winner_id === auth()->id())
                    <flux:callout variant="success" icon="trophy" heading="You won this auction!">
                        <flux:callout.text>Your bid of {{ Money::format($topBid?->amount) }} wins all {{ $listing->quantity }} {{ Str::plural('item', $listing->quantity) }}.</flux:callout.text>
                        <x-slot name="actions">
                            <flux:button size="sm" variant="primary" icon="document-arrow-down" :href="route('listings.invoice', $listing)" data-invoice-link>
                                Download invoice
                            </flux:button>
                        </x-slot>
                    </flux:callout>
                @endif

                <div data-result @class([
                    'flex flex-col gap-1 rounded-xl border p-5',
                    'border-emerald-300 bg-emerald-50 dark:border-emerald-700 dark:bg-emerald-950/40' => $listing->winner_id,
                    'border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-900' => ! $listing->winner_id,
                ])>
                    <div class="text-sm text-zinc-500 dark:text-zinc-400">Auction ended {{ $listing->ends_at->format('j M Y H:i') }}</div>
                    @if ($listing->winner_id)
                        <div class="flex items-start gap-2 text-lg font-semibold text-zinc-900 dark:text-white">
                            <flux:icon.trophy variant="mini" class="mt-1 shrink-0 text-emerald-600 dark:text-emerald-400" />
                            <span>Won by Bidder {{ User::bidderNumberFor($listing->winner_id) }} for <span class="tabular-nums" data-top-bid>{{ Money::format($topBid?->amount) }}</span></span>
                        </div>
                    @else
                        <div class="text-lg font-semibold text-zinc-900 dark:text-white">Closed with no bids</div>
                    @endif

                    @if ($listing->winner_id && auth()->user()->is_admin)
                        <div class="mt-2">
                            <flux:button size="sm" icon="document-arrow-down" :href="route('listings.invoice', $listing)" data-invoice-link>
                                Invoice {{ \App\Invoices\Invoice::numberFor($listing->id) }}
                            </flux:button>
                        </div>
                    @endif
                </div>

                @if ($participants->contains('user_id', auth()->id()) && $listing->winner_id !== auth()->id())
                    <flux:text>You didn't win this auction. Your highest bid was {{ Money::format($participants->firstWhere('user_id', auth()->id())['amount']) }}.</flux:text>
                @endif
            @else
            <div @class([
                'flex flex-col gap-3 rounded-xl border p-5',
                'border-emerald-300 bg-emerald-50 dark:border-emerald-700 dark:bg-emerald-950/40' => $topBid,
                'border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-900' => ! $topBid,
            ])>
                <div class="flex items-center justify-between gap-2 text-sm">
                    <span class="text-zinc-500 dark:text-zinc-400">{{ $listing->isOpen() ? 'Ends in' : 'Status' }}</span>

                    @if ($listing->isOpen())
                        <span
                            wire:ignore
                            wire:key="countdown-{{ $listing->ends_at->getTimestamp() }}"
                            data-countdown
                            class="font-semibold tabular-nums text-zinc-900 dark:text-white"
                            x-data="{
                                end: {{ $listing->ends_at->getTimestampMs() }},
                                offset: {{ now()->getTimestampMs() }} - Date.now(),
                                remaining: null,
                                timer: null,
                                init() {
                                    this.tick();
                                    this.timer = setInterval(() => this.tick(), 1000);
                                },
                                destroy() {
                                    clearInterval(this.timer);
                                },
                                tick() {
                                    this.remaining = Math.max(0, Math.ceil((this.end - (Date.now() + this.offset)) / 1000));
                                    if (this.remaining === 0) {
                                        clearInterval(this.timer);
                                        $wire.$refresh();
                                    }
                                },
                                get label() {
                                    if (this.remaining === null) return '';
                                    if (this.remaining === 0) return 'Auction ended';
                                    const d = Math.floor(this.remaining / 86400);
                                    const h = Math.floor(this.remaining % 86400 / 3600);
                                    const m = Math.floor(this.remaining % 3600 / 60);
                                    const s = this.remaining % 60;
                                    const pad = (n) => String(n).padStart(2, '0');
                                    return (d ? d + 'd ' : '') + pad(h) + ':' + pad(m) + ':' + pad(s);
                                },
                            }"
                            x-text="label"
                        >{{ $listing->timeRemaining() }}</span>
                    @else
                        <span class="font-semibold text-zinc-900 dark:text-white">Auction ended</span>
                    @endif
                </div>

                <flux:separator variant="subtle" />

                @if ($topBid)
                    <div>
                        <div class="text-sm text-emerald-700 dark:text-emerald-400">{{ $listing->isOpen() ? 'Current top bid' : 'Winning bid' }}</div>
                        <div class="text-3xl font-semibold tabular-nums text-zinc-900 dark:text-white" data-top-bid>{{ Money::format($topBid->amount) }}</div>
                    </div>
                    <div class="flex items-center gap-2 text-sm text-zinc-600 dark:text-zinc-300">
                        <flux:icon.trophy variant="micro" class="text-emerald-600 dark:text-emerald-400" />
                        <span>
                            {{ $listing->isOpen() ? 'Leading:' : 'Won by' }}
                            <span class="font-semibold text-zinc-900 dark:text-white">Bidder {{ User::bidderNumberFor($topBid->user_id) }}</span>
                        </span>
                        @if ($isLeader)
                            <flux:badge size="sm" color="green">You</flux:badge>
                        @endif
                    </div>
                @else
                    <div>
                        <div class="text-sm text-zinc-500 dark:text-zinc-400">No bids yet</div>
                        <div class="text-3xl font-semibold tabular-nums text-zinc-900 dark:text-white">Starting at {{ Money::format($listing->min_price) }}</div>
                    </div>
                @endif
            </div>
            @endif

            @if ($isOutbid)
                <flux:callout variant="warning" icon="exclamation-triangle"
                    heading="You've been outbid. Minimum next bid is {{ Money::format($minimumNextBid) }}." />
            @endif

            @if ($successMessage)
                <flux:callout variant="success" icon="check-circle" :heading="$successMessage" />
            @endif

            @if (! $listing->isOpen())
                <flux:card class="border-dashed">
                    <flux:text>This listing has ended. No more bids are accepted.</flux:text>
                </flux:card>
            @elseif (auth()->user()->is_admin)
                <flux:card class="border-dashed">
                    <flux:text>You can't bid as an admin.</flux:text>
                </flux:card>
            @elseif (\App\Support\Impersonation::isActive())
                <flux:card class="border-dashed" data-impersonation-no-bid>
                    <flux:text>Bidding is turned off while you're logged in as another user.</flux:text>
                </flux:card>
            @else
                <flux:card>
                    <form wire:submit="placeBid" class="flex flex-col gap-4">
                        <flux:field>
                            <flux:label>Your bid for all {{ $listing->quantity }} {{ Str::plural('item', $listing->quantity) }}</flux:label>

                            <flux:input.group>
                                <flux:input.group.prefix>R</flux:input.group.prefix>
                                <flux:input
                                    wire:model="amount"
                                    type="text"
                                    inputmode="decimal"
                                    autocomplete="off"
                                    aria-label="Bid amount in rand"
                                    required
                                />
                            </flux:input.group>

                            <flux:description>Minimum bid: {{ Money::format($minimumNextBid) }}</flux:description>
                            <flux:error name="amount" />
                        </flux:field>

                        @if ($isLeader)
                            <flux:text size="sm">You are currently the highest bidder.</flux:text>
                        @endif

                        <flux:button type="submit" variant="primary" class="w-full">
                            Place bid
                        </flux:button>
                    </form>
                </flux:card>
            @endif
        </div>

        {{-- Left column: details, participants and bid history --}}
        <div class="flex flex-col gap-8 lg:order-1">
            <div class="flex flex-col gap-6">
                @if (filled($listing->description))
                    <flux:text class="whitespace-pre-line">{{ $listing->description }}</flux:text>
                @endif

                <dl class="grid grid-cols-2 gap-x-6 gap-y-4">
                    <div>
                        <dt class="text-sm text-zinc-500 dark:text-zinc-400">Quantity</dt>
                        <dd class="font-medium tabular-nums text-zinc-900 dark:text-white">{{ $listing->quantity }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm text-zinc-500 dark:text-zinc-400">Minimum price</dt>
                        <dd class="font-medium tabular-nums text-zinc-900 dark:text-white">{{ Money::format($listing->min_price) }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm text-zinc-500 dark:text-zinc-400">Ends</dt>
                        <dd class="font-medium tabular-nums text-zinc-900 dark:text-white">{{ $listing->ends_at->format('j M Y H:i') }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm text-zinc-500 dark:text-zinc-400">Bids</dt>
                        <dd class="font-medium tabular-nums text-zinc-900 dark:text-white">{{ $bids->count() }}</dd>
                    </div>
                </dl>
            </div>

            @if ($listing->status === Listing::STATUS_CLOSED && $listing->winner_id && (auth()->user()->is_admin || $listing->winner_id === auth()->id()))
                <livewire:listings.returns :listing="$listing" :key="'returns-'.$listing->id" />
            @endif

            <section class="flex flex-col gap-3" data-participants>
                <flux:heading size="lg" level="2">Participants ({{ $participants->count() }})</flux:heading>

                @if ($participants->isEmpty())
                    <flux:text>No one has bid yet.</flux:text>
                @else
                    <ul class="divide-y divide-zinc-200 overflow-hidden rounded-2xl border border-zinc-200 bg-white shadow-xs dark:divide-zinc-800 dark:border-zinc-700 dark:bg-zinc-900">
                        @foreach ($participants as $participant)
                            @php $leads = $topBid && $participant['user_id'] === $topBid->user_id; @endphp
                            <li wire:key="participant-{{ $participant['user_id'] }}" @class([
                                'flex flex-wrap items-center justify-between gap-x-3 gap-y-1 px-4 py-2.5',
                                'bg-emerald-50 dark:bg-emerald-950/40' => $leads,
                            ])>
                                <span class="flex flex-wrap items-center gap-2">
                                    <span class="whitespace-nowrap font-medium text-zinc-900 dark:text-white">Bidder {{ User::bidderNumberFor($participant['user_id']) }}</span>
                                    @if ($participant['user_id'] === auth()->id())
                                        <span class="text-sm text-zinc-500 dark:text-zinc-400">(You)</span>
                                    @endif
                                    @if ($leads)
                                        <flux:badge size="sm" color="green" icon="trophy">{{ $listing->isOpen() ? 'Leader' : 'Winner' }}</flux:badge>
                                    @endif
                                </span>
                                <span class="font-semibold tabular-nums text-zinc-900 dark:text-white">{{ Money::format($participant['amount']) }}</span>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </section>

            <section class="flex flex-col gap-3" data-history>
                <flux:heading size="lg" level="2">Bid history</flux:heading>

                @if ($history->isEmpty())
                    <flux:text>No bids yet.</flux:text>
                @else
                    <ul class="divide-y divide-zinc-200 overflow-hidden rounded-2xl border border-zinc-200 bg-white shadow-xs dark:divide-zinc-800 dark:border-zinc-700 dark:bg-zinc-900">
                        @foreach ($history as $bid)
                            <li wire:key="bid-{{ $bid->id }}" @if ($bid->isVoided()) data-withdrawn @endif @class([
                                'flex flex-wrap items-center justify-between gap-x-3 gap-y-1 px-4 py-2.5 text-sm',
                                'bg-zinc-50 dark:bg-zinc-800/40' => $bid->isVoided(),
                            ])>
                                <span class="flex items-center gap-2 whitespace-nowrap text-zinc-900 dark:text-white">
                                    <span @class(['text-zinc-400 line-through dark:text-zinc-500' => $bid->isVoided()])>Bidder {{ User::bidderNumberFor($bid->user_id) }}</span>
                                    @if ($bid->user_id === auth()->id())
                                        <span class="text-zinc-500 dark:text-zinc-400">(You)</span>
                                    @endif
                                    @if ($bid->isVoided())
                                        <flux:tooltip content="This bidder's account was deactivated, so the bid no longer counts.">
                                            <flux:badge size="sm" color="zinc">Withdrawn</flux:badge>
                                        </flux:tooltip>
                                    @endif
                                </span>
                                <span class="flex items-center gap-4">
                                    <span @class([
                                        'font-semibold tabular-nums',
                                        'text-zinc-900 dark:text-white' => ! $bid->isVoided(),
                                        'text-zinc-400 line-through dark:text-zinc-500' => $bid->isVoided(),
                                    ])>{{ Money::format($bid->amount) }}</span>
                                    <time datetime="{{ $bid->created_at->toIso8601String() }}" title="{{ $bid->created_at->format('j M Y H:i:s') }}"
                                        class="text-right text-zinc-500 dark:text-zinc-400">{{ $bid->created_at->diffForHumans() }}</time>
                                </span>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </section>
        </div>
    </div>
</div>
