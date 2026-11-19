@props([
    'title',
    'description' => null,
])

<div {{ $attributes->class('flex flex-wrap items-end justify-between gap-4') }}>
    <div class="flex min-w-0 flex-col gap-1">
        <flux:heading size="xl" level="1" class="tracking-tight">{{ $title }}</flux:heading>
        @if ($description)
            <flux:text>{{ $description }}</flux:text>
        @endif
    </div>

    @isset($actions)
        <div class="flex flex-wrap items-center gap-2">{{ $actions }}</div>
    @endisset
</div>
