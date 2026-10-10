/**
 * sw.js - Service Worker for PWA (v4)
 * Provides offline application shell, safe caching, and Web Push notifications.
 */
const CACHE_NAME = 'exam-pwa-v15';

// Dynamic base path detection derived from registration scope
const BASE_PATH = (self.registration && self.registration.scope)
    ? new URL(self.registration.scope).pathname.replace(/\/$/, '')
    : (self.location.pathname.replace(/\/sw\.js$/, '').replace(/\/$/, '') || '');

const SHELL_URLS = [
    BASE_PATH + '/',
    BASE_PATH + '/index.php',
    BASE_PATH + '/manifest.json',
    BASE_PATH + '/images/icon.png',
    BASE_PATH + '/images/icon-192.png',
    BASE_PATH + '/images/icon-512.png',
    BASE_PATH + '/assets/css/mobile.css',
    BASE_PATH + '/assets/css/dark-mode.css',
    BASE_PATH + '/assets/js/offline-db.js',
    BASE_PATH + '/assets/js/sync-manager.js',
    BASE_PATH + '/assets/js/push-notifications.js',
    BASE_PATH + '/assets/js/offline-calendar-alerts.js',
    BASE_PATH + '/offline.html',
    BASE_PATH + '/calendar_view.php',
    BASE_PATH + '/dashboard_attendance.php',
    BASE_PATH + '/lesson_plan_editor.php',
    BASE_PATH + '/dashboard_teacher.php',
    BASE_PATH + '/print_orthodox_calendar.php'
];

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(CACHE_NAME).then((cache) => {
            return cache.addAll(SHELL_URLS).catch((err) => {
                console.warn('[PWA] Some shell assets failed to cache:', err);
            });
        })
    );
    self.skipWaiting();
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys().then((keys) =>
            Promise.all(keys.filter((k) => k !== CACHE_NAME).map((k) => caches.delete(k)))
        )
    );
    self.clients.claim();
});

self.addEventListener('fetch', (event) => {
    const url = new URL(event.request.url);

    // Skip cross-origin, non-GET, POST forms
    if (event.request.method !== 'GET') return;
    if (url.origin !== self.location.origin) return;

    // Never cache API or sync endpoints - always hit the network directly
    if (url.pathname.includes('/api/')) {
        return;
    }

    // Navigation requests (HTML / PHP): Network first, with dynamic page caching!
    if (event.request.mode === 'navigate' || url.pathname.endsWith('.php') || url.pathname.endsWith('/')) {
        event.respondWith(
            fetch(event.request)
                .then((resp) => {
                    if (resp && resp.status === 200) {
                        const clone = resp.clone();
                        caches.open(CACHE_NAME).then((c) => {
                            // Always store under exact request URL (with query string)
                            c.put(event.request, clone.clone());

                            // Only store under clean pathname if request has no content-affecting query params
                            const meaningfulParams = ['semester_id', 'class_id', 'student_id', 'm', 'y', 'q', 'id'];
                            const hasMeaningful = meaningfulParams.some((p) => url.searchParams.has(p));
                            if (!hasMeaningful) {
                                const cleanUrl = new URL(url.pathname, self.location.origin);
                                c.put(cleanUrl, clone);
                            }
                        });
                    }
                    return resp;
                })
                .catch(async () => {
                    // 1. Exact match (full URL with params)
                    let match = await caches.match(event.request);
                    if (match) return match;

                    // 2. Match ignoring search parameters (e.g. ?class_id=7)
                    match = await caches.match(event.request, { ignoreSearch: true });
                    if (match) return match;

                    // 3. Match clean pathname
                    match = await caches.match(url.pathname, { ignoreSearch: true });
                    if (match) return match;

                    // 4. Fallback strictly to offline.html — NEVER guess another role's dashboard
                    return caches.match(BASE_PATH + '/offline.html');
                })
        );
        return;
    }

    // Cache-first for static assets (CSS, JS, images, fonts, manifest)
    event.respondWith(
        caches.match(event.request).then((cached) => {
            if (cached) return cached;
            return fetch(event.request).then((resp) => {
                if (resp && resp.status === 200) {
                    const clone = resp.clone();
                    caches.open(CACHE_NAME).then((c) => c.put(event.request, clone));
                }
                return resp;
            });
        })
    );
});

// ============================================
// BACKGROUND SYNC (Phase 2.3)
// ============================================

self.addEventListener('sync', (event) => {
    if (event.tag === 'exam-sync' || event.tag === 'sync-changes') {
        event.waitUntil(
            self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then((windowClients) => {
                windowClients.forEach((client) => {
                    client.postMessage({ type: 'BACKGROUND_SYNC' });
                });
            })
        );
    }
});

// ============================================
// WEB PUSH NOTIFICATIONS
// ============================================

self.addEventListener('push', (event) => {
    let payload = {
        title: '⛪ አጸደ ትጉሃን ሰንበት ትምህርት ቤት',
        body: 'አዲስ ማሳወቂያ ደርሶዎታል!',
        url: BASE_PATH + '/notifications.php',
        icon: BASE_PATH + '/images/icon.png',
        badge: BASE_PATH + '/images/icon.png'
    };

    if (event.data) {
        try {
            const parsed = event.data.json();
            payload = Object.assign(payload, parsed);
        } catch (e) {
            payload.body = event.data.text() || payload.body;
        }
    }

    const options = {
        body: payload.body,
        icon: payload.icon || BASE_PATH + '/images/icon.png',
        badge: payload.badge || BASE_PATH + '/images/icon.png',
        vibrate: [200, 100, 200],
        data: {
            url: payload.url || BASE_PATH + '/notifications.php'
        },
        actions: [
            { action: 'open', title: 'ክፈት' }
        ]
    };

    event.waitUntil(
        self.registration.showNotification(payload.title, options)
    );
});

self.addEventListener('notificationclick', (event) => {
    event.notification.close();
    const targetUrl = event.notification.data?.url || BASE_PATH + '/notifications.php';

    event.waitUntil(
        clients.matchAll({ type: 'window', includeUncontrolled: true }).then((windowClients) => {
            for (let client of windowClients) {
                if (client.url.includes(BASE_PATH) && 'focus' in client) {
                    client.navigate(targetUrl);
                    return client.focus();
                }
            }
            if (clients.openWindow) {
                return clients.openWindow(targetUrl);
            }
        })
    );
});