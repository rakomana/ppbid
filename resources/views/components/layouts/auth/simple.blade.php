<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-zinc-50 antialiased dark:bg-zinc-950">
        <div class="relative isolate flex min-h-svh flex-col items-center justify-center gap-6 overflow-hidden p-6 md:p-10">
            {{-- Soft brand glow behind the card --}}
            <div aria-hidden="true" class="pointer-events-none absolute -top-40 left-1/2 -z-10 size-[36rem] -translate-x-1/2 rounded-full bg-orange-400/25 blur-3xl dark:bg-orange-500/15"></div>

            <div class="flex w-full max-w-sm flex-col gap-6">
                <a href="{{ route('home') }}" class="flex flex-col items-center gap-3" wire:navigate>
                    <span class="flex size-12 items-center justify-center rounded-xl bg-accent text-accent-foreground shadow-lg shadow-orange-600/20">
                        <x-app-logo-icon class="size-8" />
                    </span>
                    <span class="flex flex-col items-center gap-1">
                        <span class="text-2xl font-bold leading-none tracking-tight text-zinc-900 dark:text-white">pp<span class="text-accent-content">bid</span></span>
                        <span class="text-sm text-zinc-500 dark:text-zinc-400">Bid live. Win the whole lot.</span>
                    </span>
                </a>

                <div class="flex flex-col gap-6 rounded-2xl border border-zinc-200 bg-white p-6 shadow-sm sm:p-8 dark:border-zinc-800 dark:bg-zinc-900">
                    {{ $slot }}
                </div>
            </div>
        </div>
        @fluxScripts
    </body>
</html>
