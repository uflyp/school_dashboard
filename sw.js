// Service Worker for WebSekolah System V2.1
const CACHE_NAME = 'websekolah-v2.1-cache';

// Only cache pure static assets (CSS, JS) — NEVER cache dynamic PHP pages containing CSRF/Session
const urlsToCache = [
  './assets/css/output.css',
  './assets/js/clock.js',
  './assets/js/app.js',
  './assets/js/tour.js'
];

self.addEventListener('install', (event) => {
  self.skipWaiting();
  event.waitUntil(
    caches.open(CACHE_NAME).then((cache) => {
      return cache.addAll(urlsToCache);
    })
  );
});

// Clean up old stale caches on activation
self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys().then((cacheNames) => {
      return Promise.all(
        cacheNames.map((cacheName) => {
          if (cacheName !== CACHE_NAME) {
            return caches.delete(cacheName);
          }
        })
      );
    }).then(() => self.clients.claim())
  );
});

// Fetch Strategy:
// - Navigation & PHP dynamic requests: NETWORK-FIRST (Always fresh from server to ensure valid CSRF tokens)
// - Static assets (CSS, JS, Fonts, Images): Cache with network fallback
self.addEventListener('fetch', (event) => {
  const request = event.request;

  // Ignore non-GET requests (e.g. POST form submissions)
  if (request.method !== 'GET') {
    return;
  }

  // Network-First for HTML navigations or dynamic PHP endpoints
  if (request.mode === 'navigate' || request.url.includes('.php')) {
    event.respondWith(
      fetch(request).catch(() => {
        return caches.match(request);
      })
    );
    return;
  }

  // Cache-First with Network fallback for static assets (CSS, JS, Fonts, Images)
  event.respondWith(
    caches.match(request).then((response) => {
      return response || fetch(request).then((networkResponse) => {
        return networkResponse;
      });
    })
  );
});
