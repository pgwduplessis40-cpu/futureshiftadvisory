const CACHE_NAME = 'fsa-portal-shell-v2';
const SHELL_URLS = [
    '/offline.html',
    '/manifest.webmanifest',
    '/favicon.svg',
    '/apple-touch-icon.png',
    '/icons/fsa-pwa-192.png',
    '/icons/fsa-pwa-512.png',
    '/icons/fsa-pwa-maskable-512.png',
];
const SYNC_TAG = 'portal-offline-sync';

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(CACHE_NAME).then((cache) => cache.addAll(SHELL_URLS)),
    );
    self.skipWaiting();
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches
            .keys()
            .then((keys) =>
                Promise.all(
                    keys
                        .filter((key) => key !== CACHE_NAME)
                        .map((key) => caches.delete(key)),
                ),
            ),
    );
    self.clients.claim();
});

self.addEventListener('fetch', (event) => {
    const request = event.request;
    const url = new URL(request.url);

    if (request.method !== 'GET' || url.origin !== self.location.origin) {
        return;
    }

    if (
        request.mode === 'navigate' &&
        (url.pathname.startsWith('/portal') || url.pathname === '/dashboard')
    ) {
        event.respondWith(
            fetch(request).catch(() => caches.match('/offline.html')),
        );

        return;
    }

    if (
        url.pathname.startsWith('/build/') ||
        url.pathname.startsWith('/icons/') ||
        url.pathname === '/manifest.webmanifest' ||
        url.pathname === '/favicon.svg' ||
        url.pathname === '/apple-touch-icon.png'
    ) {
        event.respondWith(
            caches.match(request).then((cached) => {
                const network = fetch(request)
                    .then((response) => {
                        const copy = response.clone();
                        caches
                            .open(CACHE_NAME)
                            .then((cache) => cache.put(request, copy));

                        return response;
                    })
                    .catch(() => cached);

                return cached || network;
            }),
        );
    }
});

self.addEventListener('sync', (event) => {
    if (event.tag === SYNC_TAG) {
        event.waitUntil(notifyClientsToSync());
    }
});

self.addEventListener('message', (event) => {
    if (event.data?.type === 'PORTAL_OFFLINE_SYNC') {
        event.waitUntil(notifyClientsToSync());
    }
});

async function notifyClientsToSync() {
    const clients = await self.clients.matchAll({
        includeUncontrolled: true,
        type: 'window',
    });

    for (const client of clients) {
        client.postMessage({ type: 'PORTAL_OFFLINE_SYNC' });
    }
}
