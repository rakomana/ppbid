{{-- Pop-ups for new notifications, fed by the bell's "ppbid-toast" event. --}}
<div x-data="{
        toasts: [],
        add(detail) {
            const id = Date.now() + Math.random();
            this.toasts.push({ id, ...detail });
            setTimeout(() => this.remove(id), 7000);
        },
        remove(id) { this.toasts = this.toasts.filter(t => t.id !== id) },
    }"
    x-on:ppbid-toast.window="add($event.detail)"
    class="pointer-events-none fixed inset-x-4 bottom-4 z-50 flex flex-col items-end gap-2 sm:inset-x-auto sm:end-4"
    aria-live="polite">
    <template x-for="toast in toasts" :key="toast.id">
        <div x-transition.opacity.duration.200ms data-toast
            class="pointer-events-auto flex w-full max-w-sm items-start gap-3 rounded-xl border border-zinc-200 bg-white p-3 shadow-lg dark:border-zinc-700 dark:bg-zinc-800">
            <span class="mt-0.5 flex size-8 shrink-0 items-center justify-center rounded-full bg-orange-100 text-orange-600 dark:bg-orange-400/15 dark:text-orange-400">
                <flux:icon.bell-alert variant="micro" />
            </span>
            <a :href="toast.url" wire:navigate class="min-w-0 flex-1" x-on:click="remove(toast.id)">
                <span class="block text-sm font-semibold text-zinc-900 dark:text-white" x-text="toast.title"></span>
                <span class="mt-0.5 block text-sm text-zinc-600 dark:text-zinc-300" x-text="toast.message"></span>
            </a>
            <button type="button" x-on:click="remove(toast.id)" class="text-zinc-400 hover:text-zinc-700 dark:hover:text-white" aria-label="Dismiss">
                <flux:icon.x-mark variant="micro" />
            </button>
        </div>
    </template>
</div>
