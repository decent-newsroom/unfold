self.addEventListener('install', async (event) => {
  // Activate the service worker immediately after install
  await self.skipWaiting();
});

self.addEventListener('activate', (event) => {
  // Take control of all clients immediately
  event.waitUntil(self.clients.claim());
});

// No fetch or cache handlers — fully fall-through
