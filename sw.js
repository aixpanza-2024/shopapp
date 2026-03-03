// Service Worker — required for PWA standalone (no address bar)
const CACHE = 'qalb-pos-v1';

self.addEventListener('install', e => self.skipWaiting());
self.addEventListener('activate', e => e.waitUntil(clients.claim()));

// Fetch handler: network-first, fall back to cache
self.addEventListener('fetch', e => {
    // Only handle GET requests
    if (e.request.method !== 'GET') return;

    e.respondWith(
        fetch(e.request)
            .then(res => {
                // Cache a clone of the response
                const resClone = res.clone();
                caches.open(CACHE).then(cache => cache.put(e.request, resClone));
                return res;
            })
            .catch(() => caches.match(e.request))
    );
});
