{{-- Shown on every page while an admin is logged in as another user. --}}
@if (\App\Support\Impersonation::isActive())
    <div data-impersonation-banner
        {{ $attributes->class('sticky top-0 z-40 flex flex-wrap items-center justify-center gap-x-4 gap-y-2 border-b border-amber-300 bg-amber-100 px-4 py-2 text-sm text-amber-900 dark:border-amber-500/40 dark:bg-amber-950 dark:text-amber-100') }}>
        <span class="flex items-center gap-2">
            <flux:icon.eye variant="mini" class="shrink-0" />
            <span>
                You're logged in as <strong>{{ auth()->user()->name }}</strong> (Bidder {{ auth()->user()->bidder_number }}).
                Bidding and account settings are turned off.
            </span>
        </span>

        <form method="POST" action="{{ route('impersonate.stop') }}">
            @csrf
            <flux:button type="submit" size="xs" variant="primary" icon="arrow-uturn-left">Return to admin</flux:button>
        </form>
    </div>
@endif
