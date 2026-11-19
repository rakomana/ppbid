<div wire:poll.15s="poll" data-notification-bell>
    <flux:dropdown position="bottom" :align="$align">
        <button type="button"
            class="relative flex size-9 items-center justify-center rounded-lg text-zinc-500 transition hover:bg-zinc-100 hover:text-zinc-900 dark:text-zinc-400 dark:hover:bg-zinc-800 dark:hover:text-white"
            aria-label="Notifications{{ $unread ? " ($unread unread)" : '' }}">
            <flux:icon :icon="$unread ? 'bell-alert' : 'bell'" variant="outline" class="size-5" />

            @if ($unread)
                <span data-unread-count class="absolute -end-0.5 -top-0.5 flex h-4.5 min-w-4.5 items-center justify-center rounded-full bg-orange-500 px-1 text-[10px] font-bold leading-none text-white ring-2 ring-white dark:ring-zinc-900">
                    {{ $unread > 99 ? '99+' : $unread }}
                </span>
            @endif
        </button>

        <flux:menu class="w-[22rem] max-w-[calc(100vw-2rem)] p-0!">
            <div class="flex items-center justify-between gap-2 border-b border-zinc-200 px-4 py-2.5 dark:border-zinc-700">
                <span class="text-sm font-semibold text-zinc-900 dark:text-white">Notifications</span>

                @if ($unread)
                    <button type="button" wire:click="markAllRead" class="text-xs font-medium text-accent-content hover:underline">
                        Mark all as read
                    </button>
                @endif
            </div>

            <div class="max-h-96 overflow-y-auto p-1.5">
                @forelse ($notifications as $notification)
                    <x-notification-item :notification="$notification" wire:key="bell-{{ $notification->id }}" />
                @empty
                    <div class="flex flex-col items-center gap-1 px-4 py-8 text-center">
                        <flux:icon.bell class="size-6 text-zinc-300 dark:text-zinc-600" />
                        <span class="text-sm text-zinc-500 dark:text-zinc-400">No notifications yet</span>
                    </div>
                @endforelse
            </div>

            <a href="{{ route('notifications') }}" wire:navigate
                class="block border-t border-zinc-200 px-4 py-2.5 text-center text-sm font-medium text-accent-content hover:bg-zinc-50 dark:border-zinc-700 dark:hover:bg-zinc-800">
                View all notifications
            </a>
        </flux:menu>
    </flux:dropdown>
</div>
