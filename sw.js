// Service Worker - required for PWA standalone (no address bar)
const CACHE = 'qalb-pos-v1';

// PHP files that must never be served from cache (session-dependent or write endpoints)
const NO_CACHE = [
    'fetchcart.php',
    'addtocart.php',
    'unset_session.php',
    'updatebilling_status.php',
    'deleteproduct.php',
    'productajax.php',
    'shopadmin_todaysproduct.php',
    'shopadmin_adddaily.php',
    'ajaxreqshopadmin.php',
];

function isDynamic(url) {
    return NO_CACHE.some(file => url.includes(file));
}

self.addEventListener('install', e => self.skipWaiting());
self.addEventListener('activate', e => e.waitUntil(clients.claim()));

// Fetch handler: dynamic PHP always network only; static assets network-first with cache fallback
self.addEventListener('fetch', e => {
    // Only handle GET requests
    if (e.request.method !== 'GET') return;

    // Dynamic PHP: always go to network, never cache
    if (isDynamic(e.request.url)) {
        e.respondWith(fetch(e.request));
        return;
    }

    // Static assets: network-first, fall back to cache
    e.respondWith(
        fetch(e.request)
            .then(res => {
                const resClone = res.clone();
                caches.open(CACHE).then(cache => cache.put(e.request, resClone));
                return res;
            })
            .catch(() => caches.match(e.request))
    );
});
