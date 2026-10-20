/*
 * Courier app service worker. Keeps the app shell and built assets available offline;
 * API calls are never cached (the page queues actions itself and replays them).
 */
const VERSION = 'courier-v1';
const SHELL = ['/courier', '/courier.webmanifest', '/icons/courier.svg', '/courier-offline.html'];

self.addEventListener('install', (event) => {
    event.waitUntil(caches.open(VERSION).then((cache) => cache.addAll(SHELL)).then(() => self.skipWaiting()));
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys()
            .then((keys) => Promise.all(keys.filter((k) => k !== VERSION).map((k) => caches.delete(k))))
            .then(() => self.clients.claim()),
    );
});

self.addEventListener('fetch', (event) => {
    const { request } = event;
    const url = new URL(request.url);

    if (request.method !== 'GET' || url.origin !== self.location.origin) {
        return;
    }

    // Hashed build assets never change: cache first.
    if (url.pathname.startsWith('/build/') || url.pathname.startsWith('/icons/')) {
        event.respondWith(
            caches.match(request).then((hit) => hit || fetch(request).then((res) => {
                const copy = res.clone();
                caches.open(VERSION).then((cache) => cache.put(request, copy));
                return res;
            })),
        );
        return;
    }

    // Courier pages: network first, fall back to the last copy, then the offline page.
    if (request.mode === 'navigate' && url.pathname.startsWith('/courier')) {
        event.respondWith(
            fetch(request)
                .then((res) => {
                    const copy = res.clone();
                    caches.open(VERSION).then((cache) => cache.put(request, copy));
                    return res;
                })
                .catch(() => caches.match(request).then((hit) => hit || caches.match('/courier-offline.html'))),
        );
    }
});
