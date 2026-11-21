@props(['notification'])

@php
    $kind = $notification->data['kind'] ?? null;

    [$icon, $tone] = match ($kind) {
        'outbid' => ['arrow-trending-up', 'bg-orange-100 text-orange-600 dark:bg-orange-400/15 dark:text-orange-400'],
        'ending_soon' => ['clock', 'bg-amber-100 text-amber-600 dark:bg-amber-400/15 dark:text-amber-400'],
        'won' => ['trophy', 'bg-emerald-100 text-emerald-600 dark:bg-emerald-400/15 dark:text-emerald-400'],
        'lost' => ['flag', 'bg-zinc-100 text-zinc-500 dark:bg-zinc-700 dark:text-zinc-300'],
        'return_logged', 'return_requested' => ['arrow-uturn-left', 'bg-amber-100 text-amber-600 dark:bg-amber-400/15 dark:text-amber-400'],
        'return_approved', 'return_received' => ['arrow-path', 'bg-sky-100 text-sky-600 dark:bg-sky-400/15 dark:text-sky-400'],
        'return_replaced' => ['check-badge', 'bg-emerald-100 text-emerald-600 dark:bg-emerald-400/15 dark:text-emerald-400'],
        'return_rejected' => ['x-circle', 'bg-red-100 text-red-600 dark:bg-red-400/15 dark:text-red-400'],
        'closed' => ['check-circle', 'bg-sky-100 text-sky-600 dark:bg-sky-400/15 dark:text-sky-400'],
        default => ['bell', 'bg-zinc-100 text-zinc-500 dark:bg-zinc-700 dark:text-zinc-300'],
    };

    $unread = $notification->read_at === null;
@endphp

<a href="{{ route('notifications.open', $notification->id) }}" wire:navigate data-notification="{{ $kind }}"
    {{ $attributes->class([
        'flex items-start gap-3 rounded-lg px-3 py-2.5 transition hover:bg-zinc-100 dark:hover:bg-zinc-800',
        'bg-orange-50/70 dark:bg-orange-400/5' => $unread,
    ]) }}>
    <span class="flex size-8 shrink-0 items-center justify-center rounded-full {{ $tone }}">
        <flux:icon :icon="$icon" variant="micro" />
    </span>

    <span class="min-w-0 flex-1">
        <span class="flex items-center gap-2">
            <span @class(['truncate text-sm text-zinc-900 dark:text-white', 'font-semibold' => $unread, 'font-medium' => ! $unread])>{{ $notification->data['title'] ?? '' }}</span>
            @if ($unread)
                <span class="size-2 shrink-0 rounded-full bg-orange-500" aria-label="Unread"></span>
            @endif
        </span>
        <span class="mt-0.5 block text-sm text-zinc-600 dark:text-zinc-300">{{ $notification->data['message'] ?? '' }}</span>
        <span class="mt-1 block text-xs text-zinc-400 dark:text-zinc-500" title="{{ $notification->created_at->format('j M Y H:i') }}">{{ $notification->created_at->diffForHumans() }}</span>
    </span>
</a>
