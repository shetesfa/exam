/**
 * offline-calendar-alerts.js
 * Caches academic calendar and exam schedules for 100% offline availability
 * and provides local reminders/notifications even without internet connectivity.
 */

(function() {
    const STORAGE_KEY = 'atsede_calendar_events';
    const NOTIFIED_KEY = 'atsede_calendar_notified_events';

    const OfflineCalendar = {
        apiBase: (function() {
            if (window.EXAM_API_BASE) return window.EXAM_API_BASE;
            const p = window.location.pathname;
            if (p.includes('/exam-main/')) return 'api/';
            return '/exam/api/';
        })(),

        async syncAndCheck() {
            if (navigator.onLine) {
                await this.fetchAndCache();
            }
            this.checkUpcomingEvents();
        },

        async fetchAndCache() {
            try {
                const res = await fetch(this.apiBase + 'calendar_events.php', { credentials: 'same-origin' });
                if (res.ok) {
                    const data = await res.json();
                    if (data.success && Array.isArray(data.events)) {
                        localStorage.setItem(STORAGE_KEY, JSON.stringify(data.events));
                        localStorage.setItem(STORAGE_KEY + '_time', Date.now().toString());
                    }
                }
            } catch (e) {
                console.debug('[OfflineCalendar] Cache fetch skipped (offline or network error):', e);
            }
        },

        getCachedEvents() {
            try {
                const raw = localStorage.getItem(STORAGE_KEY);
                return raw ? JSON.parse(raw) : [];
            } catch (e) {
                return [];
            }
        },

        checkUpcomingEvents() {
            const events = this.getCachedEvents();
            if (!events || events.length === 0) return;

            const now = new Date();
            const todayStr = now.toISOString().slice(0, 10);
            
            // Look up to 3 days ahead
            const maxDate = new Date();
            maxDate.setDate(now.getDate() + 3);
            const maxStr = maxDate.toISOString().slice(0, 10);

            const upcoming = events.filter(e => {
                if (!e.event_date) return false;
                return e.event_date >= todayStr && e.event_date <= maxStr;
            });

            if (upcoming.length > 0) {
                this.renderAlertBanner(upcoming);
                this.triggerLocalNotifications(upcoming, todayStr);
            }
        },

        renderAlertBanner(upcomingEvents) {
            // Do not duplicate banner if already rendered or dismissed in this session
            if (document.getElementById('offline-calendar-alert-banner') || sessionStorage.getItem('calendar_banner_dismissed')) {
                return;
            }

            const banner = document.createElement('div');
            banner.id = 'offline-calendar-alert-banner';
            banner.style.cssText = `
                position: fixed;
                bottom: 20px;
                right: 20px;
                max-width: 380px;
                background: linear-gradient(135deg, #FFFDF7, #FEF3C7);
                border: 2px solid #D97706;
                border-radius: 12px;
                box-shadow: 0 8px 24px rgba(0,0,0,0.15);
                padding: 14px 16px;
                z-index: 10000;
                font-family: 'Segoe UI', Tahoma, sans-serif;
                color: #78350F;
                animation: slideUp 0.3s ease;
            `;

            let itemsHtml = upcomingEvents.map(e => {
                const ethDate = (e.ethiopian_month && e.ethiopian_day) ? `${e.ethiopian_month}/${e.ethiopian_day}` : e.event_date;
                const isExam = e.event_type === 'exam' || e.title.includes('ፈተና');
                const badge = isExam ? '📝 ፈተና' : '📅 ሁነት';
                return `
                    <div style="margin-top:6px; font-size:13px; line-height:1.4;">
                        <strong>${badge} (${ethDate}):</strong> ${escapeHtml(e.title)}
                    </div>
                `;
            }).join('');

            banner.innerHTML = `
                <div style="display:flex; justify-content:space-between; align-items:flex-start; gap:10px;">
                    <div style="display:flex; align-items:center; gap:8px;">
                        <span style="font-size:20px;">🔔</span>
                        <strong style="font-size:14px; color:#92400E;">የቀን መቁጠሪያ ማስታወሻ</strong>
                    </div>
                    <button id="closeCalBanner" style="background:none; border:none; color:#B45309; font-size:18px; cursor:pointer; padding:0 4px;">&times;</button>
                </div>
                ${itemsHtml}
                <div style="margin-top:10px; display:flex; justify-content:flex-end;">
                    <a href="/exam/calendar_view.php" style="font-size:12px; font-weight:700; color:#8B4513; text-decoration:underline;">ሙሉ ካላንደር ተመልከት &rarr;</a>
                </div>
            `;

            document.body.appendChild(banner);

            document.getElementById('closeCalBanner').addEventListener('click', () => {
                banner.remove();
                sessionStorage.setItem('calendar_banner_dismissed', 'true');
            });
        },

        triggerLocalNotifications(upcomingEvents, todayStr) {
            if (!('Notification' in window) || Notification.permission !== 'granted') return;

            let notified = {};
            try {
                notified = JSON.parse(localStorage.getItem(NOTIFIED_KEY) || '{}');
            } catch (e) {
                notified = {};
            }

            upcomingEvents.forEach(e => {
                const key = `${todayStr}_${e.id}`;
                if (!notified[key]) {
                    notified[key] = true;
                    const isExam = e.event_type === 'exam' || e.title.includes('ፈተና');
                    const title = isExam ? `📝 መጪ ፈተና: ${e.title}` : `📅 የካላንደር ማስታወሻ: ${e.title}`;
                    const body = e.description || `ቀን፡ ${e.ethiopian_month}/${e.ethiopian_day}/${e.ethiopian_year} ዓ.ም`;

                    try {
                        if (navigator.serviceWorker && navigator.serviceWorker.controller) {
                            navigator.serviceWorker.ready.then(reg => {
                                reg.showNotification(title, {
                                    body: body,
                                    icon: '/exam/images/icon.png',
                                    badge: '/exam/images/icon.png',
                                    tag: `cal_${e.id}`,
                                    data: { url: '/exam/calendar_view.php' }
                                });
                            });
                        } else {
                            new Notification(title, {
                                body: body,
                                icon: '/exam/images/icon.png'
                            });
                        }
                    } catch (err) {
                        console.debug('[OfflineCalendar] Local notification error:', err);
                    }
                }
            });

            try {
                localStorage.setItem(NOTIFIED_KEY, JSON.stringify(notified));
            } catch (e) {}
        }
    };

    function escapeHtml(str) {
        if (!str) return '';
        return str.replace(/[&<>"']/g, function(m) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[m];
        });
    }

    window.OfflineCalendar = OfflineCalendar;

    document.addEventListener('DOMContentLoaded', () => {
        OfflineCalendar.syncAndCheck();
    });

    window.addEventListener('online', () => {
        OfflineCalendar.syncAndCheck();
    });
})();
