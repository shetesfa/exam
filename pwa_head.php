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
        window.BASE_URL = <?php echo json_encode(BASE_URL); ?>;
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
    <link rel="manifest" href="manifest.json">
    <link rel="apple-touch-icon" sizes="192x192" href="images/icon-192.png">
    <link rel="apple-touch-icon" sizes="512x512" href="images/icon-512.png">
    <link rel="icon" type="image/png" sizes="192x192" href="images/icon-192.png">
    <link rel="icon" type="image/png" sizes="512x512" href="images/icon-512.png">
    <link rel="stylesheet" href="assets/css/mobile.css?v=<?php echo file_exists(__DIR__ . '/assets/css/mobile.css') ? filemtime(__DIR__ . '/assets/css/mobile.css') : time(); ?>">
    <link rel="stylesheet" href="assets/css/dark-mode.css?v=<?php echo file_exists(__DIR__ . '/assets/css/dark-mode.css') ? filemtime(__DIR__ . '/assets/css/dark-mode.css') : time(); ?>">
    <!-- Inline PWA install banner styles — always available on every page -->
    <style>
    #pwa-install-banner{display:none;position:fixed;bottom:0;left:0;right:0;background:#5C2607;color:#fff;padding:12px 18px;justify-content:space-between;align-items:center;gap:10px;z-index:99999;box-shadow:0 -4px 20px rgba(0,0,0,.35);border-top:3px solid #FFD700;font-family:'Segoe UI',Nyala,sans-serif;}
    #pwa-install-banner.show{display:flex;}
    #pwa-install-banner p{font-size:13px;line-height:1.4;margin:0;flex:1;}
    #pwa-install-banner p strong{color:#FFD700;}
    #pwa-install-btn{background:#FFD700;color:#5C2607;border:none;padding:9px 18px;border-radius:8px;font-weight:700;cursor:pointer;white-space:nowrap;font-size:13px;}
    #pwa-dismiss-btn{background:none;border:none;color:rgba(255,255,255,.7);cursor:pointer;font-size:22px;padding:2px 6px;flex-shrink:0;}
    #ios-install-tip{display:none;position:fixed;bottom:0;left:0;right:0;background:#5C2607;color:#fff;padding:14px 18px;z-index:99999;box-shadow:0 -4px 20px rgba(0,0,0,.35);border-top:3px solid #FFD700;font-family:'Segoe UI',Nyala,sans-serif;text-align:center;}
    #ios-install-tip.show{display:block;}
    #ios-install-tip p{font-size:13px;line-height:1.8;margin:0 0 8px;}
    #ios-install-tip strong{color:#FFD700;}
    #ios-tip-close{background:none;border:1px solid rgba(255,255,255,.5);color:#fff;padding:5px 16px;border-radius:6px;cursor:pointer;font-size:12px;}
    </style>
    <!-- iOS "Add to Home Screen" tip banner (shown once per session on Safari) -->
    <div id="ios-install-tip">
      <p>📱 <strong>በ iPhone/iPad:</strong> ወደ ስልክ ለመጫን → ከስር <strong>Share ⎋</strong> ይጫኑ → <strong>«Add to Home Screen»</strong> ይምረጡ</p>
      <button id="ios-tip-close" onclick="document.getElementById('ios-install-tip').classList.remove('show');try{sessionStorage.setItem('ats_ios_tip','1')}catch(e){}">ገባኝ ✓</button>
    </div>


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
    <script src="<?php echo BASE_URL; ?>assets/js/offline-db.js"></script>
    <script src="<?php echo BASE_URL; ?>assets/js/sync-manager.js"></script>
    <script src="<?php echo BASE_URL; ?>assets/js/push-notifications.js"></script>
    <script src="<?php echo BASE_URL; ?>assets/js/offline-calendar-alerts.js?v=<?php echo file_exists(__DIR__ . '/assets/js/offline-calendar-alerts.js') ? filemtime(__DIR__ . '/assets/js/offline-calendar-alerts.js') : 1; ?>"></script>

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
            await fetch('<?php echo BASE_URL; ?>api/toggle_dark_mode.php', {
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
            navigator.serviceWorker.register('<?php echo BASE_URL; ?>sw.js', { scope: '<?php echo BASE_URL; ?>' })
                .then(reg => {
                    // Update check
                    if (reg.waiting) reg.waiting.postMessage({ type: 'SKIP_WAITING' });
                })
                .catch(err => console.log('SW failed:', err));
        });
    }

    // PWA Install prompt — works on ALL pages (login + dashboard)
    let _deferredPWA = null;
    const _isIOS = /iPad|iPhone|iPod/.test(navigator.userAgent) && !window.MSStream;
    const _isInStandaloneMode = window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone;

    // Show iOS tip once per session on Safari (iOS doesn't fire beforeinstallprompt)
    if (_isIOS && !_isInStandaloneMode) {
        window.addEventListener('load', function() {
            try {
                if (sessionStorage.getItem('ats_ios_tip') !== '1') {
                    const tip = document.getElementById('ios-install-tip');
                    if (tip) setTimeout(function(){ tip.classList.add('show'); }, 1500);
                }
            } catch(e) {}
        });
    }

    function getOrCreateInstallBanner() {
        let b = document.getElementById('pwa-install-banner');
        if (!b) {
            b = document.createElement('div');
            b.id = 'pwa-install-banner';
            // Inline styles as fallback (CSS already in <style> block above)
            b.style.cssText = 'display:none;position:fixed;bottom:0;left:0;right:0;background:#5C2607;color:#fff;padding:12px 18px;justify-content:space-between;align-items:center;gap:10px;z-index:99999;box-shadow:0 -4px 20px rgba(0,0,0,.35);border-top:3px solid #FFD700;';
            b.innerHTML = '<p style="margin:0;flex:1;font-size:13px;">📱 <strong style="color:#FFD700;">አፕሊኬሽን</strong> ወደ ስልክዎ ይጫኑ!</p>' +
                          '<button id="pwa-install-btn" onclick="installPWA()" style="background:#FFD700;color:#5C2607;border:none;padding:9px 18px;border-radius:8px;font-weight:700;cursor:pointer;white-space:nowrap;font-size:13px;">⬇️ ጫን</button>' +
                          '<button id="pwa-dismiss-btn" onclick="dismissPWA()" aria-label="Dismiss" style="background:none;border:none;color:rgba(255,255,255,.7);cursor:pointer;font-size:22px;padding:2px 6px;">✕</button>';
            document.body ? document.body.appendChild(b) : document.addEventListener('DOMContentLoaded', function(){ document.body.appendChild(b); });
        }
        return b;
    }

    window.addEventListener('beforeinstallprompt', function(e) {
        e.preventDefault();
        _deferredPWA = e;
        try { if (sessionStorage.getItem('atsede_pwa_dismissed') === '1') return; } catch(err) {}
        if (_isInStandaloneMode) return; // already installed
        const b = getOrCreateInstallBanner();
        if (b) { b.style.display = 'flex'; b.classList.add('show'); }
    });

    window.installPWA = function() {
        if (!_deferredPWA) return;
        _deferredPWA.prompt();
        _deferredPWA.userChoice.then(function() {
            _deferredPWA = null;
            const b = document.getElementById('pwa-install-banner');
            if (b) { b.classList.remove('show'); b.style.display = 'none'; }
        });
    };

    window.dismissPWA = function() {
        const b = document.getElementById('pwa-install-banner');
        if (b) { b.classList.remove('show'); b.style.display = 'none'; }
        try { sessionStorage.setItem('atsede_pwa_dismissed', '1'); } catch(e) {}
    };

    // App installed event — hide banner
    window.addEventListener('appinstalled', function() {
        const b = document.getElementById('pwa-install-banner');
        if (b) { b.classList.remove('show'); b.style.display = 'none'; }
    });

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
            // Include assigned class_ids for teacher and attendance_submitter (needed for offline filtering)
            if ($bridgeRole === 'teacher' || $bridgeRole === 'attendance_submitter') {
                $assignedClassIds = [];
                $semId = $currSem ? (int)$currSem['id'] : 0;
                // Teacher: from teacher_class (AND attendance_assignments if any)
                if ($bridgeRole === 'teacher') {
                    $tClasses = dbFetchAll($conn, "SELECT class_id FROM teacher_class WHERE teacher_id = ?" . ($semId ? " AND semester_id = $semId" : ""), "i", [$bridgeUserId]);
                    foreach ($tClasses as $tc) $assignedClassIds[] = (int)$tc['class_id'];
                }
                // Both roles: also include attendance_assignments classes
                $aaClasses = dbFetchAll($conn, "SELECT class_id FROM attendance_assignments WHERE submitter_id = ?" . ($semId ? " AND semester_id = $semId" : ""), "i", [$bridgeUserId]);
                foreach ($aaClasses as $aa) {
                    $cid = (int)$aa['class_id'];
                    if (!in_array($cid, $assignedClassIds)) $assignedClassIds[] = $cid;
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

                if ("caches" in window && navigator.onLine) {
                    const baseUrl = ' . json_encode(BASE_URL) . ';
                    caches.open("exam-pwa-v15").then(cache => {
                        cache.put(baseUrl, new Response("<html><body>Offline fallback</body></html>", { headers: { "Content-Type": "text/html" } }));
                        const urlsToPrecache = [
                            baseUrl,
                            baseUrl + "index.php",
                            baseUrl + "offline.html",
                            baseUrl + "calendar_view.php",
                            baseUrl + "print_orthodox_calendar.php",
                            window.location.pathname,
                            window.location.href
                        ];
                        const r = window.CURRENT_USER?.role;
                        if (r === "teacher") {
                            urlsToPrecache.push(
                                baseUrl + "dashboard_teacher.php",
                                baseUrl + "dashboard_attendance.php",
                                baseUrl + "lesson_plan_editor.php",
                                baseUrl + "teacher_marking_scheme.php"
                            );
                        } else if (r === "admin") {
                            urlsToPrecache.push(
                                baseUrl + "dashboard_admin.php",
                                baseUrl + "dashboard_attendance.php",
                                baseUrl + "lesson_plan_editor.php",
                                baseUrl + "manage_teachers.php",
                                baseUrl + "manage_classes.php",
                                baseUrl + "manage_students.php"
                            );
                        } else if (r === "attendance_submitter") {
                            urlsToPrecache.push(baseUrl + "dashboard_attendance.php");
                        } else if (r === "student") {
                            urlsToPrecache.push(baseUrl + "dashboard_student.php");
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

<!-- Conflict Resolution Modal (Phase 2.4) -->
<div id="atsConflictModal" style="display:none; position:fixed; top:0; left:0; right:0; bottom:0; background:rgba(0,0,0,0.6); z-index:9999999; align-items:center; justify-content:center; padding:16px;">
  <div style="background:#fff; border-radius:12px; max-width:550px; width:100%; max-height:85vh; overflow-y:auto; padding:20px; box-shadow:0 8px 32px rgba(0,0,0,0.3); font-family:inherit; color:#333;">
    <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:12px; border-bottom:1px solid #eee; padding-bottom:8px;">
      <h3 style="margin:0; font-size:18px; color:#8B4513; display:flex; align-items:center; gap:8px;">⚠️ የመረጃ መደራረብ (Data Conflicts)</h3>
      <button type="button" onclick="document.getElementById('atsConflictModal').style.display='none'" style="border:none; background:none; font-size:20px; cursor:pointer; color:#888;">&times;</button>
    </div>
    <p style="font-size:13px; color:#666; margin-bottom:16px;">በመሳሪያዎ የተቀመጠው መረጃ በሰርቨር ላይ ካለው ጋር ተደራርቧል። እባክዎ የሚፈልጉትን ይምረጡ፦</p>
    <div id="atsConflictList" style="display:flex; flex-direction:column; gap:12px;"></div>
  </div>
</div>

<script>
window.checkAndShowConflictModal = async function() {
    if (typeof OfflineDB === 'undefined' || !OfflineDB.getConflictedRecords) return;
    const conflicts = await OfflineDB.getConflictedRecords();
    const container = document.getElementById('atsConflictList');
    const modal = document.getElementById('atsConflictModal');
    if (!container || !modal) return;
    
    container.innerHTML = '';
    const total = (conflicts.attendance?.length || 0) + (conflicts.marks?.length || 0);
    if (total === 0) {
        modal.style.display = 'none';
        return;
    }
    
    (conflicts.attendance || []).forEach(rec => {
        const div = document.createElement('div');
        div.style.cssText = 'border:1px solid #fed7aa; background:#fff7ed; padding:12px; border-radius:8px; font-size:13px;';
        div.innerHTML = `
            <div style="font-weight:700; margin-bottom:4px; color:#9a3412;">📅 የክትትል መረጃ (ቀን፡ ${rec.attendance_date || ''})</div>
            <div style="color:#555; margin-bottom:8px;">
                በመሳሪያዎ፡ <strong>${rec.status || ''}</strong> | በሰርቨር፡ <strong>${rec.server_data?.status || 'ያልታወቀ'}</strong>
            </div>
            <div style="display:flex; gap:8px;">
                <button type="button" onclick="resolveConflict('attendance', '${rec.local_uuid}', true)" style="flex:1; padding:6px 12px; background:#8B4513; color:#fff; border:none; border-radius:6px; font-weight:600; cursor:pointer; font-size:12px;">የእኔ ይቀመጥ</button>
                <button type="button" onclick="resolveConflict('attendance', '${rec.local_uuid}', false)" style="flex:1; padding:6px 12px; background:#e5e7eb; color:#374151; border:none; border-radius:6px; font-weight:600; cursor:pointer; font-size:12px;">የሰርቨሩ ይቀመጥ</button>
            </div>
        `;
        container.appendChild(div);
    });

    (conflicts.marks || []).forEach(rec => {
        const div = document.createElement('div');
        div.style.cssText = 'border:1px solid #fed7aa; background:#fff7ed; padding:12px; border-radius:8px; font-size:13px;';
        div.innerHTML = `
            <div style="font-weight:700; margin-bottom:4px; color:#9a3412;">📊 የውጤት መረጃ (ተማሪ ID: ${rec.student_id || ''})</div>
            <div style="color:#555; margin-bottom:8px;">
                በመሳሪያዎ ድምር፡ <strong>${rec.total || 0}</strong> | በሰርቨር ድምር፡ <strong>${rec.server_data?.total || 0}</strong>
            </div>
            <div style="display:flex; gap:8px;">
                <button type="button" onclick="resolveConflict('marks', '${rec.local_uuid}', true)" style="flex:1; padding:6px 12px; background:#8B4513; color:#fff; border:none; border-radius:6px; font-weight:600; cursor:pointer; font-size:12px;">የእኔ ይቀመጥ</button>
                <button type="button" onclick="resolveConflict('marks', '${rec.local_uuid}', false)" style="flex:1; padding:6px 12px; background:#e5e7eb; color:#374151; border:none; border-radius:6px; font-weight:600; cursor:pointer; font-size:12px;">የሰርቨሩ ይቀመጥ</button>
            </div>
        `;
        container.appendChild(div);
    });

    modal.style.display = 'flex';
};

window.resolveConflict = async function(type, uuid, keepMine) {
    if (keepMine) {
        await OfflineDB.resolveConflictKeepMine(type, uuid);
    } else {
        await OfflineDB.resolveConflictKeepServer(type, uuid);
    }
    await checkAndShowConflictModal();
    if (typeof SyncManager !== 'undefined') {
        SyncManager.fullSync();
    }
};

document.addEventListener('DOMContentLoaded', () => {
    if (typeof SyncManager !== 'undefined') {
        SyncManager.onStatusChange((status) => {
            if (status === 'conflict') {
                checkAndShowConflictModal();
            }
        });
    }
});
</script>
