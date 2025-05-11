const CACHE_NAME = 'newsroom-pwa-v0.0.1';
const URLS_TO_CACHE = [
  '/offline.html'
];

// Install: cache initial assets
self.addEventListener('install', (event) => {
  event.waitUntil(
    caches.open(CACHE_NAME).then(async (cache) => {
      const urls = URLS_TO_CACHE.map(async (url) => {
        try {
          const response = await fetch(url);
          if (response.ok && response.type === 'basic') {
            await cache.put(url, response.clone());
          } else {
            console.warn(`[SW] Skipped caching ${url}: invalid response`);
          }
        } catch (err) {
          console.warn(`[SW] Failed to fetch ${url}:`, err);
        }
      });

      await Promise.all(urls);
    })
  );
  self.skipWaiting();
});

// Activate: clean up old caches
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

// Fetch: serve from cache, fallback to network, then offline
self.addEventListener('fetch', (event) => {
  const request = event.request;

  // Only handle HTTP GET requests
  if (
    request.method !== 'GET' ||
    !request.url.startsWith('http')
  ) {
    return;
  }

  // Skip cache for dynamic routes
  const isDynamic = request.url.includes('/cat/') ;
  if (isDynamic) {
    return; // Don't intercept
  }

  event.respondWith(
    caches.match(request).then((cached) => {
      if (cached) return cached;

      return fetch(request)
        .then((response) => {
          // Optionally cache fetched responses
          if (
            response &&
            response.status === 200 &&
            response.type === 'basic'
          ) {
            const responseClone = response.clone();
            caches.open(CACHE_NAME).then((cache) =>
              cache.put(request, responseClone)
            );
          }
          return response;
        })
        .catch(() => caches.match('/offline.html'));
    })
  );
});
