/* Service worker minimal untuk PWA — memenuhi syarat "bisa dipasang di beranda"
 * (Chrome mensyaratkan adanya service worker dengan handler `fetch`).
 *
 * Sengaja TIDAK memakai precache/strategi cache agresif: aplikasi produksi ini
 * sering diperbarui dan index.html dikirim `no-cache` (lihat .htaccess), sehingga
 * semua permintaan cukup diteruskan ke jaringan (network-first). Bila offline,
 * permintaan yang sudah ada di cache HTTP browser tetap dilayani dari sana. */
self.addEventListener('install', () => {
  self.skipWaiting()
})

self.addEventListener('activate', (event) => {
  event.waitUntil(self.clients.claim())
})

self.addEventListener('fetch', (event) => {
  event.respondWith(
    fetch(event.request).catch(() =>
      caches.match(event.request).then((res) => res || Response.error()),
    ),
  )
})
