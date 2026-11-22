/* Reside Kenya Admin — service worker: push notifications + offline fallback. */
const VERSION = 'reside-admin-v2';
const OFFLINE_URL = '/admin/offline.html';

self.addEventListener('install', (e) => {
  e.waitUntil(caches.open(VERSION).then((c) => c.addAll([OFFLINE_URL, '/admin/icons/admin-192.png'])));
  self.skipWaiting();
});

self.addEventListener('activate', (e) => {
  e.waitUntil(caches.keys().then((keys) => Promise.all(keys.filter((k) => k !== VERSION).map((k) => caches.delete(k)))).then(() => self.clients.claim()));
});

// Always go to the network for pages (data must be live); show a friendly page when offline.
self.addEventListener('fetch', (e) => {
  if (e.request.mode === 'navigate') {
    e.respondWith(fetch(e.request).catch(() => caches.match(OFFLINE_URL)));
  }
});

self.addEventListener('push', (e) => {
  let d = {};
  try { d = e.data ? e.data.json() : {}; } catch (_) { d = { title: 'Reside Kenya', body: e.data && e.data.text() }; }
  const title = d.title || 'Reside Kenya';
  const opts = {
    body: d.body || '',
    icon: '/admin/icons/admin-192.png',
    badge: '/admin/icons/badge-96.png',
    tag: d.tag || 'reside',
    renotify: true,
    requireInteraction: true,
    data: { url: d.url || '/admin/' },
  };
  const jobs = [self.registration.showNotification(title, opts)];
  if (typeof d.badge === 'number' && self.navigator && 'setAppBadge' in self.navigator) {
    jobs.push(d.badge > 0 ? self.navigator.setAppBadge(d.badge) : self.navigator.clearAppBadge());
  }
  e.waitUntil(Promise.all(jobs).catch(() => {}));
});

self.addEventListener('notificationclick', (e) => {
  e.notification.close();
  const url = new URL((e.notification.data && e.notification.data.url) || '/admin/', self.location.origin).href;
  e.waitUntil(
    self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then((wins) => {
      for (const w of wins) {
        if (w.url.startsWith(self.location.origin + '/admin')) { w.focus(); return w.navigate(url); }
      }
      return self.clients.openWindow(url);
    })
  );
});
