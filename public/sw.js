// public/sw.js - EcoFone App Background Service Worker (v8 Clean Refresh)
const CACHE_NAME = 'ecofone-app-v8';
const ASSETS_TO_CACHE = [
    '/manifest.json',
    '/logo_icon.png',
    '/logo.png',
    '/icon-192.png',
    '/icon-512.png',
    '/favicon.png',
    'https://cdn.tailwindcss.com',
    'https://unpkg.com/lucide@latest',
    'https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap'
];

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(CACHE_NAME).then((cache) => {
            return cache.addAll(ASSETS_TO_CACHE).catch(() => {});
        })
    );
    self.skipWaiting();
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys().then((keys) => {
            return Promise.all(
                keys.map((key) => {
                    if (key !== CACHE_NAME) {
                        return caches.delete(key);
                    }
                })
            );
        })
    );
    self.clients.claim();
});

// Manage Ongoing Persistent Notification for Background Shift Keep-Alive
self.addEventListener('message', (event) => {
    if (!event.data) return;
    if (event.data.type === 'START_BACKGROUND_TRACKING') {
        self.registration.showNotification('🟢 EcoFone App • Field Shift Active', {
            body: 'Live GPS route tracking is running in the background until you punch out.',
            icon: '/icon-192.png',
            badge: '/icon-192.png',
            tag: 'ecofone_shift_active',
            ongoing: true,
            requireInteraction: true,
            silent: true,
            data: { url: '/?page=dashboard' }
        }).catch(() => {});
    } else if (event.data.type === 'STOP_BACKGROUND_TRACKING') {
        self.registration.getNotifications({ tag: 'ecofone_shift_active' }).then((notifications) => {
            notifications.forEach((n) => n.close());
        }).catch(() => {});
    }
});

self.addEventListener('notificationclick', (event) => {
    event.notification.close();
    event.waitUntil(
        self.clients.matchAll({ type: 'window' }).then((clientList) => {
            for (let i = 0; i < clientList.length; i++) {
                let client = clientList[i];
                if (client.url.includes('hrms-ecovista') && 'focus' in client) {
                    return client.focus();
                }
            }
            if (self.clients.openWindow) {
                return self.clients.openWindow('/?page=dashboard');
            }
        })
    );
});

// Periodic background sync for GPS synchronization
self.addEventListener('periodicsync', (event) => {
    if (event.tag === 'sync-location-pings') {
        event.waitUntil(
            self.clients.matchAll().then((clients) => {
                clients.forEach((client) => {
                    client.postMessage({ type: 'SYNC_GPS' });
                });
            })
        );
    }
});

// Background sync for offline queue
self.addEventListener('sync', (event) => {
    if (event.tag === 'sync-gps-pings') {
        event.waitUntil(
            self.clients.matchAll().then((clients) => {
                clients.forEach((client) => {
                    client.postMessage({ type: 'SYNC_GPS' });
                });
            })
        );
    }
});

self.addEventListener('fetch', (event) => {
    if (event.request.method !== 'GET') return;
    // Always fetch fresh HTML from network — never serve stale cached HTML pages!
    if (event.request.mode === 'navigate') {
        event.respondWith(fetch(event.request));
        return;
    }
    event.respondWith(
        fetch(event.request).catch(() => {
            return caches.match(event.request);
        })
    );
});