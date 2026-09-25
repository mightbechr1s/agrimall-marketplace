/**
 * AniMAL Service Worker - network-first with offline fallback
 * Version: 2
 *
 * Strategy: always try the network first so updated HTML/CSS/JS is served
 * immediately, and fall back to the cache only when offline. Bump CACHE_NAME
 * whenever this file changes so old caches are purged on activate.
 */

const CACHE_NAME = 'animal-v2';
const STATIC_ASSETS = [
  '/index.php',
  '/browse.php',
  '/manifest.json'
];

self.addEventListener('install', (event) => {
  event.waitUntil(
    caches.open(CACHE_NAME).then((cache) => {
      // Never fail the install just because one asset is missing/offline.
      return cache.addAll(STATIC_ASSETS).catch(() => {});
    })
  );
  self.skipWaiting();
});

self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys().then((cacheNames) =>
      Promise.all(
        cacheNames
          .filter((name) => name !== CACHE_NAME)
          .map((name) => caches.delete(name))
      )
    )
  );
  self.clients.claim();
});

async function networkFirst(request) {
  const cache = await caches.open(CACHE_NAME);

  try {
    const response = await fetch(request);
    // Cache successful, non-redirected, same-origin responses for offline use.
    if (response && response.ok && !response.redirected && response.type === 'basic') {
      cache.put(request, response.clone());
    }
    return response;
  } catch (error) {
    const cached = await cache.match(request);
    if (cached) return cached;
    return new Response('Offline', { status: 503, statusText: 'Offline' });
  }
}

self.addEventListener('fetch', (event) => {
  const { request } = event;
  const url = new URL(request.url);

  // Only handle same-origin GET requests.
  if (request.method !== 'GET') return;
  if (url.origin !== location.origin) return;

  // Let the server handle API calls directly (avoid caching sensitive JSON).
  if (url.pathname.startsWith('/api/')) return;

  event.respondWith(networkFirst(request));
});

// Allow the page to trigger an immediate update.
self.addEventListener('message', (event) => {
  if (event.data === 'SKIP_WAITING') {
    self.skipWaiting();
  }
});
