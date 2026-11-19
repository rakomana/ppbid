{{--
    "Install ppbid" card. Shows only when the app isn't already installed and the
    device can install it: a one-tap button on Android/Chrome, step-by-step on iPhone.
--}}
<div
    x-data="{
        canPrompt: !! window.ppbidInstallPrompt,
        installed: window.matchMedia('(display-mode: standalone)').matches || navigator.standalone === true,
        ios: /iphone|ipad|ipod/i.test(navigator.userAgent) || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1),
        dismissed: (() => { try { return localStorage.getItem('ppbid-install-dismissed') === '1' } catch (e) { return false } })(),
        get show() { return ! this.installed && ! this.dismissed && (this.canPrompt || this.ios) },
        async install() {
            const prompt = window.ppbidInstallPrompt;
            if (! prompt) return;
            prompt.prompt();
            const { outcome } = await prompt.userChoice;
            window.ppbidInstallPrompt = null;
            this.canPrompt = false;
            if (outcome === 'accepted') this.installed = true;
        },
        dismiss() {
            this.dismissed = true;
            try { localStorage.setItem('ppbid-install-dismissed', '1') } catch (e) {}
        },
    }"
    x-on:ppbid-installable.window="canPrompt = true"
    x-on:ppbid-installed.window="installed = true"
    x-show="show"
    x-cloak
    data-install-app
    {{ $attributes->class('relative flex items-start gap-4 rounded-2xl border border-orange-200 bg-orange-50 p-5 dark:border-orange-400/20 dark:bg-orange-400/10') }}
>
    <img src="/apple-touch-icon.png" alt="" class="size-12 shrink-0 rounded-xl shadow-sm" />

    <div class="flex min-w-0 flex-1 flex-col gap-2">
        <div>
            <div class="font-semibold text-zinc-900 dark:text-white">Get the ppbid app</div>
            <flux:text size="sm">Add ppbid to your home screen to open it full-screen, like any other app.</flux:text>
        </div>

        <template x-if="canPrompt">
            <div>
                <flux:button size="sm" variant="primary" icon="arrow-down-tray" x-on:click="install()">Install app</flux:button>
            </div>
        </template>

        <template x-if="! canPrompt && ios">
            <ol class="list-inside list-decimal text-sm text-zinc-600 dark:text-zinc-300">
                <li>Tap the <span class="font-medium">Share</span> button in Safari.</li>
                <li>Choose <span class="font-medium">Add to Home Screen</span>.</li>
            </ol>
        </template>
    </div>

    <button type="button" x-on:click="dismiss()" aria-label="Dismiss"
        class="rounded-md p-1 text-zinc-400 hover:bg-orange-100 hover:text-zinc-600 dark:hover:bg-orange-400/10 dark:hover:text-zinc-200">
        <flux:icon.x-mark variant="micro" />
    </button>
</div>
