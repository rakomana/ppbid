<div class="flex flex-col gap-6">
    <x-page-header title="Notifications" description="Updates about your auctions. Notifications are shown here in the app only, never emailed.">
        @if ($unread)
            <x-slot:actions>
                <flux:button wire:click="markAllRead" icon="check" size="sm">Mark all as read</flux:button>
            </x-slot:actions>
        @endif
    </x-page-header>

    @if ($notifications->isEmpty())
        <div class="flex flex-col items-center gap-2 rounded-2xl border border-dashed border-zinc-300 px-6 py-12 text-center dark:border-zinc-700">
            <flux:icon.bell class="size-8 text-zinc-400" />
            <flux:heading>No notifications yet</flux:heading>
            <flux:text>You'll be told here when you're outbid, when an auction you bid on is ending, and when it closes.</flux:text>
        </div>
    @else
        <div class="flex flex-col gap-1 rounded-2xl border border-zinc-200 bg-white p-2 dark:border-zinc-700 dark:bg-zinc-900">
            @foreach ($notifications as $notification)
                <x-notification-item :notification="$notification" wire:key="page-{{ $notification->id }}" />
            @endforeach
        </div>

        {{ $notifications->links() }}
    @endif
</div>
