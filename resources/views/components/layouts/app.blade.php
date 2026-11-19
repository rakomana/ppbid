<x-layouts.app.sidebar :title="$title ?? null">
    <flux:main>
        <x-impersonation-banner class="-mx-6 -mt-6 mb-6 lg:-mx-8 lg:-mt-8" />

        {{ $slot }}
    </flux:main>
</x-layouts.app.sidebar>
