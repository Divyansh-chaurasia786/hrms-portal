// public/service-worker.js - Ecofone HRMS Progressive Web App Service Worker
const CACHE_NAME = 'ecofone-hrms-v1.0';

self.addEventListener('install', (event) => {
    self.skipWaiting();
});

self.addEventListener('activate', (event) => {
    event.waitUntil(self.clients.claim());
});

self.addEventListener('fetch', (event) => {
    // Network-first strategy for live cloud application data
    event.respondWith(
        fetch(event.request).catch(() => caches.match(event.request))
    );
});