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

                    this.dispatchPush(title, body, `cal_${e.id}`, '/exam/calendar_view.php');
                }
            });

            try {
                localStorage.setItem(NOTIFIED_KEY, JSON.stringify(notified));
            } catch (e) {}
        },

        dispatchPush(title, body, tag, url) {
            try {
                if (navigator.serviceWorker && navigator.serviceWorker.controller) {
                    navigator.serviceWorker.ready.then(reg => {
                        reg.showNotification(title, {
                            body: body,
                            icon: '/exam/images/icon.png',
                            badge: '/exam/images/icon.png',
                            tag: tag || 'atsede_alert',
                            data: { url: url || '/exam/calendar_view.php' }
                        });
                    });
                } else if ('Notification' in window && Notification.permission === 'granted') {
                    new Notification(title, {
                        body: body,
                        icon: '/exam/images/icon.png'
                    });
                }
            } catch (err) {
                console.debug('[OfflineCalendar] Push error:', err);
            }
        },

        // Ethiopian Date Calculation (Works 100% Offline)
        getEthiopianDate(gregDate) {
            const d = gregDate || new Date();
            const gy = d.getFullYear();
            const prevEthLeap = ((gy - 8) % 4 === 3);
            const nyDay = prevEthLeap ? 12 : 11;
            const nyDate = new Date(gy, 8, nyDay);
            let ey, diff;
            if (d >= nyDate) {
                ey = gy - 7;
                diff = Math.floor((d - nyDate) / (1000 * 60 * 60 * 24));
            } else {
                ey = gy - 8;
                const prevPrevEthLeap = ((gy - 9) % 4 === 3);
                const prevNyDay = prevPrevEthLeap ? 12 : 11;
                const prevNyDate = new Date(gy - 1, 8, prevNyDay);
                diff = Math.floor((d - prevNyDate) / (1000 * 60 * 60 * 24));
            }
            let em = Math.floor(diff / 30) + 1;
            let ed = (diff % 30) + 1;
            if (em > 13) em = 13;
            return { year: ey, month: em, day: ed };
        },

        // Check Church Feast Day Greetings (Days: 3, 16, 21, 23, 26, 27)
        checkFeastAlerts() {
            const eth = this.getEthiopianDate();
            const FEASTS = {
                3: 'ቅዱስ ሩፋኤል',
                16: 'ኪዳነ ምሕረት',
                21: 'እመቤታችን ቅድስት ድንግል ማርያም',
                23: 'ቅዱስ ጊዮርጊስ',
                26: 'አቡነ ሐብተ ማርያም',
                27: 'መድኃኔዓለም'
            };
            const feastName = FEASTS[eth.day];
            if (!feastName) return;

            const now = new Date();
            const todayStr = now.toISOString().slice(0, 10);
            const alertKey = `feast_greeting_notified_${eth.year}_${eth.month}_${eth.day}_${todayStr}`;

            if (!localStorage.getItem(alertKey)) {
                localStorage.setItem(alertKey, '1');
                const title = '⛪ አጸደ ትጉሃን ሰንበት ትምህርት ቤት';
                const body = `እንኳን አደረሳችሁ ለ${feastName} ዕለት!`;
                this.dispatchPush(title, body, `feast_${eth.day}`, '/exam/calendar_view.php');
            }
        },

        // Check Sunday 04:50 (10:50 AM) Children Teacher Alert
        checkSundayTeacherAlert() {
            const isChildTeacher = (window.IS_CHILDREN_TEACHER || localStorage.getItem('is_children_teacher') === '1');
            if (!isChildTeacher) return;

            const now = new Date();
            // 0 is Sunday
            if (now.getDay() !== 0) return;

            const hours = now.getHours();
            const minutes = now.getMinutes();
            // Ethiopian 05:00 is 11:00 AM. 10 minutes before is 10:50 AM.
            // Active window: from 10:50 AM to 12:00 PM
            const timeMinutes = hours * 60 + minutes;
            const alertStart = 10 * 60 + 50; // 10:50 AM
            const alertEnd = 12 * 60;        // 12:00 PM

            if (timeMinutes >= alertStart && timeMinutes <= alertEnd) {
                const todayStr = now.toISOString().slice(0, 10);
                const alertKey = `sunday_child_teacher_alert_${todayStr}`;
                if (!localStorage.getItem(alertKey)) {
                    localStorage.setItem(alertKey, '1');
                    const title = '⏰ የህፃናት ክፍል መምህራን ማስታወሻ';
                    const body = 'ልጆችዎ እየጠበቁዎት ነው ቤተክርስቲያን ይገኙ! (ትምህርት 05:00 ይጀምራል)';
                    this.dispatchPush(title, body, 'sunday_child_teacher', '/exam/dashboard_attendance.php');
                }
            }
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
        OfflineCalendar.checkFeastAlerts();
        OfflineCalendar.checkSundayTeacherAlert();
        // Check Sunday teacher alert every minute while page is active
        setInterval(() => {
            OfflineCalendar.checkSundayTeacherAlert();
        }, 60000);
    });

    window.addEventListener('online', () => {
        OfflineCalendar.syncAndCheck();
    });
})();
