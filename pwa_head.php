<?php
/**
 * pwa_head.php - Shared PWA meta tags + service worker registration + offline sync + Dark Mode
 * Usage: include inside <head> after <title> on every page
 */
$currentUserKey = '';
$userDarkModePref = 0; // 0 = Auto (Ethiopian Day/Night), 1 = Manual Dark, 2 = Manual Light

if (!empty($_SESSION['user_id'])) {
    $currentUserKey = 'user_' . (int)$_SESSION['user_id'];
    if (isset($_SESSION['dark_mode'])) {
        $userDarkModePref = (int)$_SESSION['dark_mode'];
    } elseif (isset($conn) && function_exists('getUserDarkMode')) {
        $userDarkModePref = getUserDarkMode($conn, (int)$_SESSION['user_id'], false);
        $_SESSION['dark_mode'] = $userDarkModePref;
    }
} elseif (!empty($_SESSION['student_id'])) {
    $currentUserKey = 'student_' . (int)$_SESSION['student_id'];
    if (isset($_SESSION['dark_mode'])) {
        $userDarkModePref = (int)$_SESSION['dark_mode'];
    } elseif (isset($conn) && function_exists('getUserDarkMode')) {
        $userDarkModePref = getUserDarkMode($conn, (int)$_SESSION['student_id'], true);
        $_SESSION['dark_mode'] = $userDarkModePref;
    }
} else {
    $userDarkModePref = isset($_SESSION['dark_mode']) ? (int)$_SESSION['dark_mode'] : 0;
}

// Check Ethiopian time (UTC+3 / EAT: 18:00 - 05:59 is Night)
$isNightTimeInEthiopia = function_exists('isEthiopianNightTime') ? isEthiopianNightTime() : false;
if ($userDarkModePref === 1) {
    $isDarkModeActive = true;
} elseif ($userDarkModePref === 2) {
    $isDarkModeActive = false;
} else {
    // Mode 0: Auto based on Ethiopian Time
    $isDarkModeActive = $isNightTimeInEthiopia;
}
?>
    <script>
    (function() {
        window.ATS_CURRENT_USER_KEY = <?php echo json_encode($currentUserKey); ?>;
        window.ATS_SERVER_DARK_ACTIVE = <?php echo $isDarkModeActive ? 'true' : 'false'; ?>;
        window.ATS_SERVER_DARK_PREF = <?php echo (int)$userDarkModePref; ?>;

        // Ethiopian Time calculation (UTC+3 hours)
        function checkEthiopianNight() {
            var now = new Date();
            var utc = now.getTime() + (now.getTimezoneOffset() * 60000);
            var eat = new Date(utc + (3600000 * 3));
            var h = eat.getHours();
            return (h >= 18 || h < 6);
        }
        window.checkEthiopianNight = checkEthiopianNight;

        var storageKey = window.ATS_CURRENT_USER_KEY ? ('atsede_dark_pref_' + window.ATS_CURRENT_USER_KEY) : 'atsede_dark_pref_guest';
        var cachedPref = localStorage.getItem(storageKey);

        var isDark = false;
        if (cachedPref === '1') {
            isDark = true;
        } else if (cachedPref === '2') {
            isDark = false;
        } else if (window.ATS_SERVER_DARK_PREF === 1) {
            isDark = true;
            localStorage.setItem(storageKey, '1');
        } else if (window.ATS_SERVER_DARK_PREF === 2) {
            isDark = false;
            localStorage.setItem(storageKey, '2');
        } else {
            // Auto mode: Follow Ethiopian Day / Night!
            isDark = window.ATS_SERVER_DARK_ACTIVE || checkEthiopianNight();
        }

        if (isDark) {
            document.documentElement.classList.add('dark-mode');
            document.documentElement.setAttribute('data-theme', 'dark');
        } else {
            document.documentElement.classList.remove('dark-mode');
            document.documentElement.setAttribute('data-theme', 'light');
        }
    })();
    </script>
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="theme-color" content="#8B4513">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="አጸደ ትምህርት">
    <link rel="manifest" href="/exam/manifest.json">
    <link rel="apple-touch-icon" href="/exam/images/icon.png">
    <link rel="icon" type="image/png" href="/exam/images/icon.png">
    <link rel="stylesheet" href="/exam/assets/css/mobile.css">
    <link rel="stylesheet" href="/exam/assets/css/dark-mode.css?v=<?php echo file_exists(__DIR__ . '/assets/css/dark-mode.css') ? filemtime(__DIR__ . '/assets/css/dark-mode.css') : 1; ?>">

    <!-- Offline DB, Sync Manager, Push Notifications, and Offline Calendar Alerts Scripts -->
    <?php
    // Automated check for Church Feasts & Sunday 04:50 Children Teacher alerts
    if (isset($conn) && function_exists('checkAutomatedSchoolAlerts')) {
        @checkAutomatedSchoolAlerts($conn);
    }
    $is_child_t = false;
    if (isset($_SESSION['role']) && $_SESSION['role'] === 'teacher' && !empty($_SESSION['user_id']) && isset($conn)) {
        $uid = intval($_SESSION['user_id']);
        $chk_ct = dbFetchOne($conn, "SELECT 1 FROM teacher_class tc JOIN classes c ON tc.class_id = c.id LEFT JOIN grades g ON c.grade_id = g.id WHERE tc.teacher_id = ? AND (g.division_id = 1 OR c.name LIKE '%ህፃናት%' OR c.description LIKE '%ህፃናት%') LIMIT 1", "i", [$uid]);
        if ($chk_ct) $is_child_t = true;
    }
    ?>
    <script>
        window.IS_CHILDREN_TEACHER = <?php echo $is_child_t ? 'true' : 'false'; ?>;
        if (window.IS_CHILDREN_TEACHER) {
            localStorage.setItem('is_children_teacher', '1');
        } else if (<?php echo (isset($_SESSION['user_id']) && ($_SESSION['role'] ?? '') === 'teacher') ? 'true' : 'false'; ?>) {
            localStorage.removeItem('is_children_teacher');
        }
    </script>
    <script src="/exam/assets/js/offline-db.js"></script>
    <script src="/exam/assets/js/sync-manager.js"></script>
    <script src="/exam/assets/js/push-notifications.js"></script>
    <script src="/exam/assets/js/offline-calendar-alerts.js?v=<?php echo file_exists(__DIR__ . '/assets/js/offline-calendar-alerts.js') ? filemtime(__DIR__ . '/assets/js/offline-calendar-alerts.js') : 1; ?>"></script>

    <script>
    // Per-Account Dark Mode Toggle Handler
    window.toggleDarkMode = async function() {
        var isDarkNow = document.documentElement.classList.contains('dark-mode');
        var targetDark = !isDarkNow;
        var targetMode = targetDark ? 1 : 2; // 1 = Manual Dark, 2 = Manual Light

        // Immediate responsive UI update
        document.documentElement.classList.toggle('dark-mode', targetDark);
        document.documentElement.setAttribute('data-theme', targetDark ? 'dark' : 'light');
        if (document.body) {
            document.body.classList.toggle('dark-mode', targetDark);
        }
        updateAllDarkModeButtons(targetDark);

        var storageKey = window.ATS_CURRENT_USER_KEY ? ('atsede_dark_pref_' + window.ATS_CURRENT_USER_KEY) : 'atsede_dark_pref_guest';
        localStorage.setItem(storageKey, String(targetMode));

        try {
            await fetch('/exam/api/toggle_dark_mode.php', {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ mode: targetMode })
            });
        } catch (e) {
            console.debug('Dark mode preference saved locally.');
        }
    };

    function updateAllDarkModeButtons(isDark) {
        var icon = document.getElementById('darkModeIcon');
        var text = document.getElementById('darkModeText');
        var drawerIcon = document.getElementById('drawerDarkIcon');
        var drawerText = document.getElementById('drawerDarkText');
        var floatIcon = document.querySelector('.floating-dark-icon');

        if (icon) icon.textContent = isDark ? '☀️' : '🌙';
        if (text) text.textContent = isDark ? 'ብርሃን' : 'ጨለማ';
        if (drawerIcon) drawerIcon.textContent = isDark ? '☀️' : '🌙';
        if (drawerText) drawerText.textContent = isDark ? 'የብርሃን ገጽታ' : 'የጨለማ ገጽታ';
        if (floatIcon) floatIcon.textContent = isDark ? '☀️' : '🌙';
    }

    document.addEventListener('DOMContentLoaded', function() {
        var isDark = document.documentElement.classList.contains('dark-mode');
        if (document.body) {
            document.body.classList.toggle('dark-mode', isDark);
        }
        updateAllDarkModeButtons(isDark);
    });

    // Service Worker registration
    if ('serviceWorker' in navigator) {
        window.addEventListener('load', () => {
            navigator.serviceWorker.register('/exam/sw.js', { scope: '/exam/' })
                .then(reg => {
                    // Update check
                    if (reg.waiting) reg.waiting.postMessage({ type: 'SKIP_WAITING' });
                })
                .catch(err => console.log('SW failed:', err));
        });
    }

    // PWA Install prompt
    let _deferredPWA = null;
    window.addEventListener('beforeinstallprompt', (e) => {
        e.preventDefault(); _deferredPWA = e;
        const b = document.getElementById('pwa-install-banner');
        if (b) b.classList.add('show');
    });
    function installPWA() {
        if (!_deferredPWA) return;
        _deferredPWA.prompt();
        _deferredPWA.userChoice.then(() => {
            _deferredPWA = null;
            const b = document.getElementById('pwa-install-banner');
            if (b) b.classList.remove('show');
        });
    }
    function dismissPWA() {
        const b = document.getElementById('pwa-install-banner');
        if (b) b.classList.remove('show');
    }

    // Hamburger nav toggle
    function toggleMobileNav() {
        const btn = document.getElementById('hamburger-btn');
        const drawer = document.getElementById('nav-drawer');
        if (!btn || !drawer) return;
        const isOpen = drawer.classList.toggle('open');
        btn.classList.toggle('open', isOpen);
    }
    document.addEventListener('click', function(e) {
        const nav = document.querySelector('.site-nav');
        const btn = document.getElementById('hamburger-btn');
        if (nav && btn && !nav.contains(e.target)) {
            const d = document.getElementById('nav-drawer');
            if (d) d.classList.remove('open');
            if (btn) btn.classList.remove('open');
        }
    });
    </script>

<?php
// Session to IndexedDB sync bridge (runs on every authenticated page visit)
if (isset($conn) && function_exists('isLoggedIn') && function_exists('isStudent')) {
    if (isLoggedIn() || isStudent()) {
        $bridgeUserId = isLoggedIn() ? (int)($_SESSION['user_id'] ?? 0) : (int)($_SESSION['student_id'] ?? 0);
        $bridgeRole = isLoggedIn() ? ($_SESSION['role'] ?? 'teacher') : 'student';
        $bridgeName = isLoggedIn() ? trim($_SESSION['user_name'] ?? '') : trim($_SESSION['student_name'] ?? '');
        $bridgeUsername = '';
        if (isLoggedIn()) {
            $bridgeUsername = trim($_SESSION['username'] ?? '');
            if ($bridgeUsername === '' && $bridgeUserId > 0) {
                $uRow = dbFetchOne($conn, "SELECT username FROM users WHERE id = ?", "i", [$bridgeUserId]);
                if ($uRow && !empty($uRow['username'])) {
                    $bridgeUsername = trim($uRow['username']);
                    $_SESSION['username'] = $bridgeUsername;
                }
            }
        } else {
            $bridgeUsername = $bridgeName;
        }

        $bridgeToken = function_exists('getOrCreateOfflineToken') ? getOrCreateOfflineToken($conn, $bridgeUserId, $bridgeRole) : null;
        if ($bridgeToken) {
            $clientUserData = [
                'id' => $bridgeUserId,
                'username' => $bridgeUsername,
                'name' => $bridgeName,
                'role' => $bridgeRole,
                'token' => $bridgeToken,
                'expiresAt' => date('Y-m-d H:i:s', strtotime('+30 days'))
            ];
            $currSem = function_exists('getCurrentSemester') ? getCurrentSemester($conn) : null;
            if ($currSem) {
                $clientUserData['active_semester'] = $currSem;
            }
            if ($bridgeRole === 'teacher') {
                $assignedClassIds = [];
                $semId = $currSem ? (int)$currSem['id'] : 0;
                $tClasses = dbFetchAll($conn, "SELECT class_id FROM teacher_class WHERE teacher_id = ?" . ($semId ? " AND semester_id = $semId" : ""), "i", [$bridgeUserId]);
                foreach ($tClasses as $tc) {
                    $assignedClassIds[] = (int)$tc['class_id'];
                }
                $clientUserData['class_ids'] = $assignedClassIds;
            }
            echo '<script>
            window.CURRENT_USER = ' . json_encode($clientUserData, JSON_UNESCAPED_UNICODE) . ';
            document.addEventListener("DOMContentLoaded", () => {
                // Request browser persistent storage to guarantee 2+ weeks offline persistence
                if (navigator.storage && navigator.storage.persist) {
                    navigator.storage.persist().catch(() => {});
                }

                if (typeof OfflineDB !== "undefined" && window.CURRENT_USER) {
                    OfflineDB.saveAuth(window.CURRENT_USER, window.CURRENT_USER.token, window.CURRENT_USER.expiresAt)
                        .then(() => {
                            if (window.CURRENT_USER.active_semester && OfflineDB.setActiveSemester) {
                                OfflineDB.setActiveSemester(window.CURRENT_USER.active_semester);
                            }
                            if (typeof SyncManager !== "undefined") {
                                SyncManager.fullSync();
                            }
                        }).catch(e => console.warn("Offline auth sync:", e));
                }

                // Proactively pre-cache the real PHP dashboards so navigation offline ALWAYS serves the same PHP UI
                if ("caches" in window && navigator.onLine) {
                    caches.open("exam-pwa-v14").then(cache => {
                        const urlsToPrecache = [
                            "/exam/",
                            "/exam/index.php",
                            "/exam/offline.html",
                            "/exam/calendar_view.php",
                            "/exam/print_orthodox_calendar.php",
                            window.location.pathname,
                            window.location.href
                        ];
                        const r = window.CURRENT_USER?.role;
                        if (r === "teacher") {
                            urlsToPrecache.push(
                                "/exam/dashboard_teacher.php",
                                "/exam/dashboard_attendance.php",
                                "/exam/lesson_plan_editor.php",
                                "/exam/teacher_marking_scheme.php"
                            );
                        } else if (r === "admin") {
                            urlsToPrecache.push(
                                "/exam/dashboard_admin.php",
                                "/exam/dashboard_attendance.php",
                                "/exam/lesson_plan_editor.php",
                                "/exam/manage_teachers.php",
                                "/exam/manage_classes.php",
                                "/exam/manage_students.php"
                            );
                        } else if (r === "attendance_submitter") {
                            urlsToPrecache.push(
                                "/exam/dashboard_attendance.php"
                            );
                        } else if (r === "student") {
                            urlsToPrecache.push("/exam/dashboard_student.php");
                        }
                        urlsToPrecache.forEach(u => {
                            fetch(u, { credentials: "same-origin" })
                                .then(res => {
                                    if (res && res.status === 200) {
                                        cache.put(u, res.clone());
                                        try {
                                            const clean = new URL(u, window.location.origin).pathname;
                                            cache.put(clean, res);
                                        } catch(e) {}
                                    }
                                }).catch(() => {});
                        });
                    }).catch(() => {});
                }
            });
            </script>' . PHP_EOL;
        }
    }
}
?>
<!-- Offline / Online Floating Pill Banner -->
<style>
.ats-offline-pill {
    position: fixed;
    top: 14px;
    left: 50%;
    transform: translateX(-50%);
    background: linear-gradient(135deg, #DC2626, #B91C1C);
    color: #FFFFFF;
    font-size: 12px;
    font-weight: 700;
    padding: 7px 16px;
    border-radius: 25px;
    box-shadow: 0 4px 15px rgba(0,0,0,0.3);
    z-index: 999999;
    display: none;
    align-items: center;
    gap: 8px;
    animation: atsPopIn 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
    border: 1px solid rgba(255,255,255,0.3);
    pointer-events: none;
}
.ats-offline-pill.online {
    background: linear-gradient(135deg, #059669, #047857);
}
@keyframes atsPopIn {
    from { opacity: 0; transform: translate(-50%, -15px); }
    to { opacity: 1; transform: translate(-50%, 0); }
}
</style>
<div id="atsOfflinePill" class="ats-offline-pill" role="status" aria-live="polite">
    <span class="pill-dot">●</span>
    <span class="pill-text">Offline (መረጃዎች በስልክዎ / PC ይቀመጣል)</span>
</div>
<script>
(function() {
    function updateConnPill() {
        var pill = document.getElementById('atsOfflinePill');
        if (!pill) return;
        if (!navigator.onLine) {
            pill.className = 'ats-offline-pill';
            pill.querySelector('.pill-text').textContent = '📡 Offline (መረጃዎች በስልክዎ / PC ይቀመጣል)';
            pill.style.display = 'inline-flex';
        } else {
            if (pill.style.display === 'inline-flex') {
                pill.className = 'ats-offline-pill online';
                pill.querySelector('.pill-text').textContent = '✅ ተገናኝቷል (በማመሳሰል ላይ...)';
                setTimeout(function() { pill.style.display = 'none'; }, 2000);
            }
        }
    }
    window.addEventListener('online', updateConnPill);
    window.addEventListener('offline', updateConnPill);
    document.addEventListener('DOMContentLoaded', updateConnPill);
})();
</script>

<!-- Floating Dark Mode Toggle (Auto-hidden on pages that have .site-header) -->
<button type="button" id="floatingDarkModeToggle" class="floating-dark-toggle" onclick="toggleDarkMode()" title="የገጽ ገጽታ ቀይር" aria-label="Toggle Dark Mode">
  <span class="floating-dark-icon">🌙</span>
</button>
