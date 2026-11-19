<div class="flex max-w-5xl flex-col gap-8">
    <x-page-header title="Dashboard" description="Welcome back, {{ Str::before(auth()->user()->name, ' ') }}." />

    <x-install-app />

    @if (auth()->user()->is_admin)
        <div class="grid gap-4 sm:grid-cols-2">
            <div class="flex items-start gap-4 rounded-2xl border border-zinc-200 bg-white p-5 shadow-xs dark:border-zinc-700 dark:bg-zinc-900">
                <span class="flex size-10 items-center justify-center rounded-xl bg-orange-100 text-orange-600 dark:bg-orange-400/15 dark:text-orange-400">
                    <flux:icon.tag />
                </span>
                <div>
                    <flux:text size="sm">Open listings</flux:text>
                    <div class="text-3xl font-bold tabular-nums tracking-tight text-zinc-900 dark:text-white" data-stat="open">{{ $openListings }}</div>
                </div>
            </div>
            <div class="flex items-start gap-4 rounded-2xl border border-zinc-200 bg-white p-5 shadow-xs dark:border-zinc-700 dark:bg-zinc-900">
                <span class="flex size-10 items-center justify-center rounded-xl bg-zinc-100 text-zinc-600 dark:bg-zinc-800 dark:text-zinc-300">
                    <flux:icon.clock />
                </span>
                <div>
                    <flux:text size="sm">Ended, waiting to close</flux:text>
                    <div class="text-3xl font-bold tabular-nums tracking-tight text-zinc-900 dark:text-white" data-stat="awaiting">{{ $awaitingClose }}</div>
                </div>
            </div>
        </div>

        <div>
            <flux:button variant="primary" icon="shield-check" :href="route('admin.listings.index')" wire:navigate>Manage listings</flux:button>
        </div>
    @else
        <div class="grid gap-4 sm:grid-cols-3">
            <div class="flex flex-col gap-3 rounded-2xl bg-linear-to-br from-orange-500 to-orange-600 p-5 text-white shadow-md shadow-orange-600/20">
                <span class="flex size-10 items-center justify-center rounded-xl bg-white/20">
                    <flux:icon.hashtag />
                </span>
                <div>
                    <div class="text-sm text-orange-100">You are</div>
                    <div class="whitespace-nowrap text-2xl font-bold tabular-nums tracking-tight sm:text-3xl" data-stat="bidder">Bidder {{ auth()->user()->bidder_number }}</div>
                    <div class="mt-1 text-sm text-orange-100">This is the only name other bidders see.</div>
                </div>
            </div>

            <a href="{{ route('my-bids') }}" wire:navigate
                class="group flex flex-col gap-3 rounded-2xl border border-zinc-200 bg-white p-5 shadow-xs transition hover:-translate-y-0.5 hover:border-emerald-300 hover:shadow-md dark:border-zinc-700 dark:bg-zinc-900 dark:hover:border-emerald-500/50">
                <span class="flex size-10 items-center justify-center rounded-xl bg-emerald-100 text-emerald-600 dark:bg-emerald-400/15 dark:text-emerald-400">
                    <flux:icon.arrow-trending-up />
                </span>
                <div>
                    <flux:text size="sm">Winning now</flux:text>
                    <div class="text-3xl font-bold tabular-nums tracking-tight text-zinc-900 dark:text-white" data-stat="winning">{{ $winningNow }}</div>
                    <flux:text size="sm">{{ Str::plural('active auction', $winningNow) }} where you're the top bidder</flux:text>
                </div>
            </a>

            <a href="{{ route('my-bids') }}" wire:navigate
                class="group flex flex-col gap-3 rounded-2xl border border-zinc-200 bg-white p-5 shadow-xs transition hover:-translate-y-0.5 hover:border-amber-300 hover:shadow-md dark:border-zinc-700 dark:bg-zinc-900 dark:hover:border-amber-500/50">
                <span class="flex size-10 items-center justify-center rounded-xl bg-amber-100 text-amber-600 dark:bg-amber-400/15 dark:text-amber-400">
                    <flux:icon.trophy />
                </span>
                <div>
                    <flux:text size="sm">Auctions won</flux:text>
                    <div class="text-3xl font-bold tabular-nums tracking-tight text-zinc-900 dark:text-white" data-stat="won">{{ $auctionsWon }}</div>
                    <flux:text size="sm">{{ $auctionsWon === 1 ? 'auction' : 'auctions' }} you've won</flux:text>
                </div>
            </a>
        </div>

        <div class="flex flex-wrap gap-2">
            <flux:button variant="primary" icon="tag" :href="route('listings.index')" wire:navigate>
                Browse {{ $openListings }} open {{ Str::plural('listing', $openListings) }}
            </flux:button>
            <flux:button icon="banknotes" :href="route('my-bids')" wire:navigate>My bids</flux:button>
        </div>
    @endif
</div>
