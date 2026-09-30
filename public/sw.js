/**
 * AniMAL Service Worker - network-first with offline fallback
 * Version: 2
 *
 * Strategy: always try the network first so updated HTML/CSS/JS is served
 * immediately, and fall back to the cache only when offline. Bump CACHE_NAME
 * whenever this file changes so old caches are purged on activate.
 */

const CACHE_NAME = 'animal-v3';
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

// Served when the network fails and nothing cached can answer the request.
// A bare text body reads as a broken site, so return a real document.
const OFFLINE_PAGE = `<!doctype html>
<html lang="en"><head><meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Offline - AniMAL</title>
<style>body{font:16px/1.6 system-ui,sans-serif;margin:0;display:grid;min-height:100vh;place-items:center;background:#faf7f0;color:#22301f}main{max-width:32rem;padding:2rem;text-align:center}h1{font-size:1.5rem;margin:0 0 .5rem}p{margin:0 0 1.5rem;color:#55654f}</style>
</head><body><main><h1>You're offline</h1>
<p>AniMAL couldn't reach the server. Check your connection and try again.</p>
<button onclick="location.reload()">Retry</button>
</main></body></html>`;

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
    // Exact match first, then the same path ignoring the query string, so a
    // never-before-visited search URL degrades to the browse shell instead of
    // dead-ending on an error page.
    const cached = (await cache.match(request)) ||
      (await cache.match(request, { ignoreSearch: true }));
    if (cached) return cached;
    return new Response(OFFLINE_PAGE, {
      status: 503,
      statusText: 'Service Unavailable',
      headers: { 'Content-Type': 'text/html; charset=utf-8' }
    });
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
