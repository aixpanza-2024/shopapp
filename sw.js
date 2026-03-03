// Minimal service worker — required for Chrome to offer "Install App"
self.addEventListener('install', e => self.skipWaiting());
self.addEventListener('activate', e => e.waitUntil(clients.claim()));
