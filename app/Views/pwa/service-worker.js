/* Entrenaments JSPM - Service Worker.
 * Només desa recursos estàtics públics (CSS, JS, icones) i la pàgina sense connexió.
 * No intercepta ni desa mai pàgines, formularis (POST), sessions ni dades privades. */
'use strict';

const VERSION = '__VERSION__';
const CACHE_PREFIX = 'jspm-static-';
const CACHE = CACHE_PREFIX + VERSION;
const SCOPE = self.registration.scope;
const PRECACHE = __PRECACHE__;
const OFFLINE_URL = PRECACHE[0];
const STATIC_PREFIXES = [new URL('assets/', SCOPE).pathname, new URL('icones/', SCOPE).pathname];

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(CACHE)
            .then((cache) => cache.addAll(PRECACHE))
            .then(() => self.skipWaiting())
    );
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys()
            .then((keys) => Promise.all(keys.filter((k) => k.startsWith(CACHE_PREFIX) && k !== CACHE).map((k) => caches.delete(k))))
            .then(() => self.clients.claim())
    );
});

self.addEventListener('fetch', (event) => {
    const req = event.request;
    if (req.method !== 'GET') { return; }
    const url = new URL(req.url);
    if (url.origin !== self.location.origin) { return; }

    // Pàgines: sempre de la xarxa (contenen dades privades). Només si no hi ha connexió es mostra l'avís.
    if (req.mode === 'navigate') {
        event.respondWith(fetch(req).catch(() => caches.match(OFFLINE_URL)));
        return;
    }

    // Recursos estàtics versionats: primer la memòria cau.
    if (STATIC_PREFIXES.some((p) => url.pathname.startsWith(p))) {
        event.respondWith(
            caches.open(CACHE).then((cache) =>
                cache.match(req).then((hit) => hit || fetch(req).then((res) => {
                    if (res.ok && res.type === 'basic') { cache.put(req, res.clone()); }
                    return res;
                }))
            )
        );
    }
});

self.addEventListener('push', (event) => {
    let data = {};
    try { data = event.data ? event.data.json() : {}; } catch (e) { data = { title: 'Entrenaments JSPM', body: event.data ? event.data.text() : '' }; }
    const icon = new URL('icones/icon-192', SCOPE).href;
    event.waitUntil(self.registration.showNotification(data.title || 'Entrenaments JSPM', {
        body: data.body || '',
        icon: icon,
        badge: icon,
        data: { url: data.url || SCOPE },
    }));
});

self.addEventListener('notificationclick', (event) => {
    event.notification.close();
    const target = (event.notification.data && event.notification.data.url) || SCOPE;
    event.waitUntil(
        self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then((list) => {
            for (const c of list) {
                if (c.url.startsWith(SCOPE) && 'focus' in c) {
                    return 'navigate' in c ? c.navigate(target).then((w) => (w || c).focus()) : c.focus();
                }
            }
            return self.clients.openWindow(target);
        })
    );
});
