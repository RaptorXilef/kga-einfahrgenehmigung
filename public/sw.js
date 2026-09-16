/**
 * KGA Einfahrts-Manager - Service Worker
 *
 * Strategie:
 * - Statische Assets (/assets/*): Cache-First, Fallback Network.
 * - HTML-Seiten: Network-First, Fallback Cache.
 * - API & Admin: STRICTLY Network Only (Bypass Cache).
 */

const CACHE_VERSION = 'v1.0.0'; // <- Hier bei Updates einfach v1.0.1 draus machen!
const STATIC_CACHE = `kga-static-${CACHE_VERSION}`;
const DYNAMIC_CACHE = `kga-dynamic-${CACHE_VERSION}`;

// Kritische Core-Assets, inkl. der neuen Offline-Seite
const PRECACHE_ASSETS = [
    '/assets/css/main.min.css',
    '/assets/js/app.js',
    '/assets/js/core/Bootstrapper.js',
    '/assets/js/core/Api.js',
    '/assets/js/core/Notifier.js',
    '/offline.html',
];

// 1. INSTALLATION
self.addEventListener('install', (event) => {
    // Erzwingt, dass der wartende SW sofort aktiv wird
    self.skipWaiting();
    event.waitUntil(
        caches.open(STATIC_CACHE).then((cache) => {
            console.info('[SW] Pre-Caching gestartet...');
            return cache.addAll(PRECACHE_ASSETS);
        })
    );
});

// 2. AKTIVIERUNG & GARBAGE COLLECTION
self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches
            .keys()
            .then((keys) => {
                return Promise.all(
                    keys.map((key) => {
                        // Alte Caches löschen, wenn wir die Versionnummer oben ändern
                        if (key !== STATIC_CACHE && key !== DYNAMIC_CACHE) {
                            console.info(`[SW] Lösche alten Cache: ${key}`);
                            return caches.delete(key);
                        }
                    })
                );
            })
            .then(() => self.clients.claim()) // Übernimmt sofort die Kontrolle über alle offenen Tabs
    );
});

// 3. FETCH INTERCEPTOR (Der Türsteher)
self.addEventListener('fetch', (event) => {
    // Grundregel 1: Nur GET-Requests cachen. POST/PUT/DELETE niemals abfangen!
    if (event.request.method !== 'GET') return;

    const url = new URL(event.request.url);

    // API & Admin umgehen den Cache komplett
    const networkOnlyRoutes = ['/api/', '/admin', '/check', '/checkout', '/success'];
    if (networkOnlyRoutes.some((route) => url.pathname.includes(route))) {
        return; // Verlässt den Service Worker, Browser macht ganz normal weiter
    }

    // A) Assets (Bilder, CSS, JS) -> Cache First
    if (url.pathname.startsWith('/assets/')) {
        event.respondWith(
            caches.match(event.request).then((cachedResponse) => {
                // Wenn im Cache: Sofort ausliefern (blitzschnell)
                if (cachedResponse) return cachedResponse;

                // Wenn nicht im Cache: Aus dem Netz laden und dynamisch für später cachen
                return (
                    fetch(event.request)
                        .then((networkResponse) => {
                            // Gültige Antworten cachen
                            if (
                                networkResponse &&
                                networkResponse.status === 200 &&
                                networkResponse.type === 'basic'
                            ) {
                                const responseToCache = networkResponse.clone();
                                caches.open(DYNAMIC_CACHE).then((cache) => {
                                    cache.put(event.request, responseToCache);
                                });
                            }
                            return networkResponse;
                        })
                        // Optional: Fallback-Image für fehlende WebP-Bilder
                        .catch(() => null)
                );
            })
        );
        return;
    }

    // B) HTML Navigation -> Network First, Fallback to Offline
    if (event.request.mode === 'navigate') {
        event.respondWith(
            fetch(event.request)
                .then((networkResponse) => {
                    // Bei Erfolg: Die frisch geladene Seite im Cache als Offline-Fallback speichern
                    return caches.open(DYNAMIC_CACHE).then((cache) => {
                        cache.put(event.request, networkResponse.clone());
                        return networkResponse;
                    });
                })
                .catch(() => {
                    // Wenn offline: Zeige die zuletzt gecachte Version dieser Seite
                    return caches.match(event.request).then((cachedResponse) => {
                        // 1. Zuerst schauen, ob wir genau DIESE Seite zufällig im Cache haben
                        if (cachedResponse) {
                            return cachedResponse;
                        }
                        // 2. Wenn nicht, werfen wir unsere schöne statische Offline-Seite aus!
                        return caches.match('/offline.html');
                    });
                })
        );
    }
});
