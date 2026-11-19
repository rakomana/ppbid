// Installable app (PWA): register the service worker.
if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => {
        navigator.serviceWorker.register('/sw.js').catch(() => {
            // Not fatal: the site works the same without it.
        });
    });
}

// Keep the browser's "install app" prompt so the install card can show it later,
// even after moving between pages with wire:navigate.
window.addEventListener('beforeinstallprompt', (event) => {
    event.preventDefault();
    window.ppbidInstallPrompt = event;
    window.dispatchEvent(new CustomEvent('ppbid-installable'));
});

window.addEventListener('appinstalled', () => {
    window.ppbidInstallPrompt = null;
    window.dispatchEvent(new CustomEvent('ppbid-installed'));
});
