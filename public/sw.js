/*
 * ppbid service worker.
 *
 * Auctions are live, so pages and Livewire requests always go to the network
 * (never served stale from a cache). Only static files are cached:
 *  - /build/* (hashed by Vite, so they never change once built): cache-first
 *  - icons and the offline page: precached
 * If a page can't load because the device is offline, the offline page is shown.
 */

const VERSION = 'ppbid-v1';
const OFFLINE_URL = '/offline.html';
const PRECACHE = [
    OFFLINE_URL,
    '/favicon.svg',
    '/icons/icon-192.png',
    '/icons/icon-512.png',
    '/apple-touch-icon.png',
];

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(VERSION).then((cache) => cache.addAll(PRECACHE)).then(() => self.skipWaiting()),
    );
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys()
            .then((keys) => Promise.all(keys.filter((key) => key !== VERSION).map((key) => caches.delete(key))))
            .then(() => self.clients.claim()),
    );
});

self.addEventListener('fetch', (event) => {
    const request = event.request;
    const url = new URL(request.url);

    // Only handle our own GET requests; let everything else (bids, logins, Livewire) pass through.
    if (request.method !== 'GET' || url.origin !== self.location.origin) {
        return;
    }

    // Pages: always the network, with the offline page as a fallback.
    if (request.mode === 'navigate') {
        event.respondWith(fetch(request).catch(() => caches.match(OFFLINE_URL)));
        return;
    }

    // Built assets and icons: cache-first.
    if (url.pathname.startsWith('/build/') || PRECACHE.includes(url.pathname)) {
        event.respondWith(
            caches.match(request).then((cached) => cached || fetch(request).then((response) => {
                if (response.ok) {
                    const copy = response.clone();
                    caches.open(VERSION).then((cache) => cache.put(request, copy));
                }

                return response;
            })),
        );
    }
});
