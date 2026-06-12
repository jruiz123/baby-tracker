// Safe minimal SW — never blocks PHP or API
const CACHE = 'baby-tracker-v1';

self.addEventListener('install', () => self.skipWaiting());

self.addEventListener('activate', event => {
  event.waitUntil(
    caches.keys().then(keys =>
      Promise.all(keys.filter(k => k !== CACHE).map(k => caches.delete(k)))
    )
  );
  self.clients.claim();
});

self.addEventListener('fetch', event => {
  const url = new URL(event.request.url);
  // Never intercept PHP or API
  if (url.pathname.endsWith('.php') || url.pathname.includes('/api/')) return;
  // Only cache CDN
  if (url.hostname.includes('jsdelivr.net') || url.hostname.includes('googleapis.com')) {
    event.respondWith(
      caches.match(event.request).then(c =>
        c || fetch(event.request).then(r => {
          const clone = r.clone();
          caches.open(CACHE).then(cache => cache.put(event.request, clone));
          return r;
        })
      )
    );
  }
});
