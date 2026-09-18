/**
 * sw.js - Service Worker for PWA (v4)
 * Provides offline application shell, safe caching, and Web Push notifications.
 */
const CACHE_NAME = 'exam-pwa-v14';
const SHELL_URLS = [
    '/exam/',
    '/exam/index.php',
    '/exam/manifest.json',
    '/exam/images/icon.png',
    '/exam/assets/css/mobile.css',
    '/exam/assets/css/dark-mode.css',
    '/exam/assets/js/offline-db.js',
    '/exam/assets/js/sync-manager.js',
    '/exam/assets/js/push-notifications.js',
    '/exam/assets/js/offline-calendar-alerts.js',
    '/exam/offline.html',
    '/exam/calendar_view.php',
    '/exam/dashboard_attendance.php',
    '/exam/lesson_plan_editor.php',
    '/exam/dashboard_teacher.php',
    '/exam/print_orthodox_calendar.php'
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
    // When online: fetch and cache the exact page.
    // When offline: serve the EXACT cached REAL PHP PAGE so the UI is 100% identical to online!
    if (event.request.mode === 'navigate' || url.pathname.endsWith('.php') || url.pathname.endsWith('/')) {
        event.respondWith(
            fetch(event.request)
                .then((resp) => {
                    if (resp && resp.status === 200) {
                        const clone = resp.clone();
                        caches.open(CACHE_NAME).then((c) => {
                            // Store under exact request URL and clean pathname
                            c.put(event.request, clone.clone());
                            c.put(url.pathname, clone);
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

                    // 4. Route-aware fallback to the REAL cached PHP page:
                    if (url.pathname.includes('calendar')) {
                        match = await caches.match('/exam/calendar_view.php', { ignoreSearch: true });
                        if (match) return match;
                    }
                    if (url.pathname.includes('lesson_plan') || url.pathname.includes('plan')) {
                        match = await caches.match('/exam/lesson_plan_editor.php', { ignoreSearch: true });
                        if (match) return match;
                    }
                    if (url.pathname.includes('print_orthodox')) {
                        match = await caches.match('/exam/print_orthodox_calendar.php', { ignoreSearch: true });
                        if (match) return match;
                    }
                    if (url.pathname.includes('attendance')) {
                        match = await caches.match('/exam/dashboard_attendance.php', { ignoreSearch: true });
                        if (match) return match;
                    }
                    if (url.pathname.includes('teacher') || url.pathname.includes('mark')) {
                        match = await caches.match('/exam/dashboard_teacher.php', { ignoreSearch: true });
                        if (match) return match;
                    }
                    if (url.pathname.includes('admin') || url.pathname.includes('manage_')) {
                        match = await caches.match('/exam/dashboard_admin.php', { ignoreSearch: true });
                        if (match) return match;
                    }
                    if (url.pathname.includes('student')) {
                        match = await caches.match('/exam/dashboard_student.php', { ignoreSearch: true });
                        if (match) return match;
                    }

                    // 5. Fallback to any cached real PHP dashboard
                    const realDashboards = [
                        '/exam/dashboard_teacher.php',
                        '/exam/dashboard_attendance.php',
                        '/exam/dashboard_admin.php',
                        '/exam/dashboard_student.php',
                        '/exam/index.php',
                        '/exam/login.php'
                    ];
                    for (const rd of realDashboards) {
                        match = await caches.match(rd, { ignoreSearch: true });
                        if (match) return match;
                    }

                    // 6. Only as absolute fallback if zero PHP pages were ever cached
                    return caches.match('/exam/offline.html');
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
// WEB PUSH NOTIFICATIONS
// ============================================

self.addEventListener('push', (event) => {
    let payload = {
        title: '⛪ አጸደ ትጉሃን ሰንበት ትምህርት ቤት',
        body: 'አዲስ ማሳወቂያ ደርሶዎታል!',
        url: '/exam/notifications.php',
        icon: '/exam/images/icon.png',
        badge: '/exam/images/icon.png'
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
        icon: payload.icon || '/exam/images/icon.png',
        badge: payload.badge || '/exam/images/icon.png',
        vibrate: [200, 100, 200],
        data: {
            url: payload.url || '/exam/notifications.php'
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
    const targetUrl = event.notification.data?.url || '/exam/notifications.php';

    event.waitUntil(
        clients.matchAll({ type: 'window', includeUncontrolled: true }).then((windowClients) => {
            for (let client of windowClients) {
                if (client.url.includes('/exam/') && 'focus' in client) {
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