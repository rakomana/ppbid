@props(['user'])

{{-- "3 hours ago" with the exact time on hover, or "Never". --}}
@if ($user->last_login_at)
    <span {{ $attributes }} title="{{ $user->last_login_at->format('j M Y H:i') }}" data-last-login>{{ $user->last_login_at->diffForHumans() }}</span>
@else
    <span {{ $attributes->class('text-zinc-400 dark:text-zinc-500') }} data-last-login>Never</span>
@endif
