<?php
/**
 * cron_alerts.php
 * Automated Background Alert & Push Notification Dispatcher
 *
 * Can be executed via:
 * 1. CLI / Terminal: php cron_alerts.php
 * 2. Scheduled Webhook / cPanel Cron: GET /cron_alerts.php?token=atsede_cron_2026_secure
 * 3. Browser Dashboard (Admin Manual Trigger)
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/services/push/VapidManager.php';
require_once __DIR__ . '/services/push/WebPushService.php';

// Ensure required tables & columns exist across all MySQL/MariaDB engines
if (function_exists('ensureExamTablesExist')) {
    @ensureExamTablesExist($conn);
}
if (function_exists('ensureNotificationPushColumn')) {
    @ensureNotificationPushColumn($conn);
}

// --------------------------------------------------------------------------
// 1. Authentication & Security Check
// --------------------------------------------------------------------------
$isCli = (php_sapi_name() === 'cli' || defined('STDIN'));
$isAdminSession = (isset($_SESSION['role']) && $_SESSION['role'] === 'admin');

// Retrieve secure cron token from settings or fallback to default
$storedToken = 'atsede_cron_2026_secure';
if (isset($conn) && $conn) {
    $tokenRow = dbFetchOne($conn, "SELECT setting_value FROM settings WHERE setting_key = 'cron_secret_token' LIMIT 1");
    if ($tokenRow && !empty($tokenRow['setting_value'])) {
        $storedToken = trim($tokenRow['setting_value']);
    } else {
        // Auto-seed default cron token if not present
        @dbExecute($conn, "INSERT IGNORE INTO settings (setting_key, setting_value, updated_at) VALUES ('cron_secret_token', ?, NOW())", "s", [$storedToken]);
    }
}

$providedToken = $_GET['token'] ?? $_POST['token'] ?? '';
$isAuthorized = $isCli || $isAdminSession || (!empty($providedToken) && hash_equals($storedToken, $providedToken));

// If unauthorized access
if (!$isAuthorized) {
    http_response_code(403);
    if (!empty($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'error' => 'ያልተፈቀደ ሙከራ! ትክክለኛ የደህንነት ቶከን (token) ያስፈልጋል።'], JSON_UNESCAPED_UNICODE);
    } else {
        echo "<!DOCTYPE html><html lang='am'><head><meta charset='utf-8'><title>403 የተከለከለ</title>";
        echo "<style>body{font-family:sans-serif;text-align:center;padding:50px;background:#fff5f5;color:#991b1b;}</style></head><body>";
        echo "<h2>⚠️ 403 ያልተፈቀደ የመዳረሻ ሙከራ</h2>";
        echo "<p>ይህ የጀርባ ክሮን ስራ የሚሰራው በደህንነት ቶከን ወይም በአስተዳዳሪ ብቻ ነው።</p>";
        echo "<p>ምሳሌ: <code>cron_alerts.php?token=YOUR_CRON_TOKEN</code></p>";
        echo "</body></html>";
    }
    exit();
}

// --------------------------------------------------------------------------
// 2. Core Worker Functions
// --------------------------------------------------------------------------

$cronReport = [
    'timestamp' => date('Y-m-d H:i:s'),
    'executed_by' => $isCli ? 'CLI' : ($isAdminSession ? 'Admin (' . ($_SESSION['username'] ?? 'session') . ')' : 'Webhook Token'),
    'actions' => [],
    'notifications_created' => 0,
    'push_sent' => 0,
    'push_failed' => 0,
    'errors' => []
];

/**
 * A. Ethiopian Feast Day Alerts (Disabled as per user preference: focus on real school news & exams, no repeated welcome/greeting blasts)
 */
function runFeastAlerts($conn, &$cronReport) {
    // Repeated holiday welcome greetings disabled to prevent notification fatigue.
    // The calendar page itself displays church saints and feast markers, while push alerts are reserved exclusively for school news & exams.
    $cronReport['actions'][] = "የበዓላት አጠቃላይ ሰላምታ ማሳወቂያ እንዲጠፋ ተደርጓል (ማሳወቂያዎች ለት/ቤት ዜናዎች እና ፈተናዎች ብቻ እንዲሆኑ)።";
}

/**
 * B. Sunday 10:50 AM Children Teachers Alert
 */
function runSundayTeacherAlerts($conn, &$cronReport) {
    $dt = new DateTime('now', new DateTimeZone('Africa/Addis_Ababa'));
    $dow = intval($dt->format('w')); // 0 = Sunday
    $hour = intval($dt->format('H'));
    $min = intval($dt->format('i'));

    if ($dow === 0) {
        // Ethiopian 05:00 is 11:00 AM. 10 minutes before is 10:50 AM.
        $timeMinutes = $hour * 60 + $min;
        $alertStart = 10 * 60 + 45; // 10:45 AM
        $alertEnd = 12 * 60;        // 12:00 PM

        if ($timeMinutes >= $alertStart && $timeMinutes <= $alertEnd) {
            $todayGreg = $dt->format('Y-m-d');
            $marker = "children_teacher_sunday_alert:{$todayGreg}";
            $chk = dbFetchOne($conn, "SELECT id FROM notifications WHERE related_page = ?", "s", [$marker]);
            if (!$chk) {
                // Find all teachers teaching children classes (Division 1 / Grades 1-6)
                $tRows = dbFetchAll(
                    $conn,
                    "SELECT DISTINCT tc.teacher_id 
                     FROM teacher_class tc 
                     JOIN classes c ON tc.class_id = c.id 
                     LEFT JOIN grades g ON c.grade_id = g.id 
                     WHERE g.division_id = 1 OR c.name LIKE '%ህፃናት%' OR c.description LIKE '%ህፃናት%'"
                );
                $teacherIds = array_column($tRows, 'teacher_id');
                if (!empty($teacherIds)) {
                    $title = "⏰ የህፃናት ክፍል መምህራን ማስታወሻ";
                    $message = "ልጆችዎ እየጠበቁዎት ነው ቤተክርስቲያን ይገኙ! (ትምህርት 05:00 ይጀምራል)";
                    $notifId = createNotification($conn, $title, $message, ['users' => $teacherIds], 'high', null, $marker);
                    $cronReport['notifications_created']++;
                    $cronReport['actions'][] = "የእሁድ 10:50 AM የህፃናት መምህራን ማስታወሻ ተልኳል (ለ " . count($teacherIds) . " መምህራን)።";
                }
            } else {
                $cronReport['actions'][] = "የእሁድ 10:50 AM ማስታወሻ ቀደም ሲል ዛሬ ተልኳል።";
            }
        } else {
            $cronReport['actions'][] = "ዛሬ እሁድ ነው ግን ሰዓቱ አልደረሰም (" . $dt->format('h:i A') . ")።";
        }
    } else {
        $cronReport['actions'][] = "ዛሬ እሁድ አይደለም (" . $dt->format('l') . ")።";
    }
}

/**
 * C. Academic Calendar Events Alert (Today & Tomorrow)
 */
function runCalendarAndExamAlerts($conn, &$cronReport) {
    $now = new DateTime('now', new DateTimeZone('Africa/Addis_Ababa'));
    $today = $now->format('Y-m-d');
    
    $tomorrowDt = clone $now;
    $tomorrowDt->modify('+1 day');
    $tomorrow = $tomorrowDt->format('Y-m-d');

    // 1. Check upcoming school news, exams, and assessments (Holiday welcome messages excluded)
    $events = dbFetchAll(
        $conn,
        "SELECT id, title, description, event_type, event_date, priority, reminder_days_before 
         FROM calendar_events 
         WHERE event_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 3 DAY) 
           AND event_type IN ('exam', 'assessment', 'important', 'announcement', 'meeting', 'teaching', 'revision')
           AND (is_deleted = 0 OR is_deleted IS NULL)
         ORDER BY event_date ASC"
    );

    foreach ($events as $ev) {
        $evDate = $ev['event_date'];
        $diffDays = (new DateTime($today))->diff(new DateTime($evDate))->days;
        $dayLabel = ($diffDays === 0) ? "የዛሬ" : (($diffDays === 1) ? "የነገ" : "የ{$diffDays} ቀን በኋላ");
        $marker = "cal_event_{$ev['id']}:{$ev['event_date']}";
        
        $chk = dbFetchOne($conn, "SELECT id FROM notifications WHERE related_page = ?", "s", [$marker]);
        if (!$chk) {
            $prefix = "📢 የት/ቤት ዜና ማስታወቂያ፡ ";
            if ($ev['event_type'] === 'exam') {
                $prefix = "📝 የፈተና ማስታወሻ ({$dayLabel})፡ ";
            } elseif ($ev['event_type'] === 'assessment') {
                $prefix = "📋 የምዘና ማስታወሻ ({$dayLabel})፡ ";
            } elseif ($ev['event_type'] === 'important') {
                $prefix = "⚠️ አስፈላጊ ማስታወቂያ ({$dayLabel})፡ ";
            } elseif ($ev['event_type'] === 'announcement') {
                $prefix = "📢 አዲስ ዜና ({$dayLabel})፡ ";
            }

            $title = $prefix . $ev['title'];
            $msg = ($ev['description'] ? $ev['description'] . " — " : "") . "ቀን፡ {$ev['event_date']} (አጸደ ትጉሃን)";
            
            // Gather targets
            $targets = [];
            $tRows = dbFetchAll($conn, "SELECT division_id, grade_id, class_id, teacher_id FROM calendar_event_targets WHERE event_id = ?", "i", [$ev['id']]);
            foreach ($tRows as $tr) {
                if (!empty($tr['teacher_id'])) {
                    $targets[] = ['user_id' => intval($tr['teacher_id'])];
                } elseif (!empty($tr['class_id'])) {
                    $targets[] = ['class_id' => intval($tr['class_id'])];
                } elseif (!empty($tr['grade_id'])) {
                    $targets[] = ['grade_id' => intval($tr['grade_id'])];
                } elseif (!empty($tr['division_id'])) {
                    $targets[] = ['division_id' => intval($tr['division_id'])];
                }
            }

            createNotification($conn, $title, $msg, $targets, $ev['priority'] ?? 'high', $ev['id'], $marker);
            $cronReport['notifications_created']++;
            $cronReport['actions'][] = "የዜና/ፈተና ማሳወቂያ ተፈጠረ፡ {$ev['title']} ({$dayLabel})";
        }
    }

    // 2. Check pending exam reviews for admins
    $pendingExams = dbFetchAll(
        $conn,
        "SELECT e.id, e.subject_name, e.title, u.name as teacher_name, c.name as class_name 
         FROM exams e 
         JOIN users u ON e.teacher_id = u.id 
         JOIN classes c ON e.class_id = c.id 
         WHERE e.status = 'submitted' 
         LIMIT 5"
    );

    if (!empty($pendingExams)) {
        $marker = "pending_exams_admin_alert:" . $today;
        $chk = dbFetchOne($conn, "SELECT id FROM notifications WHERE related_page = ?", "s", [$marker]);
        if (!$chk) {
            $count = count($pendingExams);
            $title = "📝 ያልተገመገሙ የፈተና ጥያቄዎች አሉ ({$count})";
            $message = "መምህራን ያቀረቧቸው {$count} የፈተና ጥያቄዎች ግምገማ እና ማጽደቅ በመጠባበቅ ላይ ናቸው። እባክዎ ይገምግሙ።";
            
            // Send to admins
            $adminRows = dbFetchAll($conn, "SELECT id FROM users WHERE role = 'admin'");
            $adminIds = array_column($adminRows, 'id');
            if (!empty($adminIds)) {
                createNotification($conn, $title, $message, ['users' => $adminIds], 'high', null, $marker);
                $cronReport['notifications_created']++;
                $cronReport['actions'][] = "ለአስተዳዳሪዎች የፈተና ግምገማ ማሳወቂያ ተልኳል።";
            }
        }
    }
}

/**
 * D. Web Push Dispatch to All Subscribed Devices
 * Ensure that all recent notifications are pushed to registered devices
 */
function dispatchPendingPushAlerts($conn, &$cronReport) {
    if (!class_exists('WebPushService')) return;

    // Get active subscriptions
    $subs = dbFetchAll($conn, "SELECT id, user_id, endpoint, p256dh, auth, device_name FROM push_subscriptions");
    if (empty($subs)) {
        $cronReport['actions'][] = "ምንም የተመዘገበ የስልክ ፑሽ ማሳወቂያ (push subscription) የለም።";
        return;
    }

    // Check notifications that haven't been pushed yet (push_sent = 0)
    $pendingNotifs = dbFetchAll(
        $conn,
        "SELECT id, title, message, related_page, priority, created_at 
         FROM notifications 
         WHERE (push_sent = 0 OR push_sent IS NULL)
         ORDER BY id DESC LIMIT 10"
    );

    $totalSent = 0;
    $totalFailed = 0;

    foreach ($pendingNotifs as $notif) {
        $pushData = [
            'title' => $notif['title'],
            'body' => $notif['message'],
            'url' => (!empty($notif['related_page']) && strpos($notif['related_page'], '/') === 0) 
                        ? $notif['related_page'] 
                        : '/exam/notifications.php',
            'tag' => 'notif_' . $notif['id']
        ];

        // Fetch targets for this notification
        $targets = dbFetchAll($conn, "SELECT division_id, grade_id, class_id, user_id FROM notification_targets WHERE notification_id = ?", "i", [$notif['id']]);
        
        $res = WebPushService::sendToTarget($conn, $targets, $pushData);
        $totalSent += ($res['sent'] ?? 0);
        $totalFailed += ($res['failed'] ?? 0);

        @dbExecute($conn, "UPDATE notifications SET push_sent = 1 WHERE id = ?", "i", [$notif['id']]);
    }

    $cronReport['push_sent'] += $totalSent;
    $cronReport['push_failed'] += $totalFailed;
    $cronReport['actions'][] = "የዌብ ፑሽ ውጤት፡ {$totalSent} ተልኳል፣ {$totalFailed} አልተሳካም።";
}

// --------------------------------------------------------------------------
// 3. Execution
// --------------------------------------------------------------------------
try {
    runFeastAlerts($conn, $cronReport);
    runSundayTeacherAlerts($conn, $cronReport);
    runCalendarAndExamAlerts($conn, $cronReport);
    dispatchPendingPushAlerts($conn, $cronReport);
} catch (Exception $e) {
    $cronReport['errors'][] = $e->getMessage();
}

// --------------------------------------------------------------------------
// 4. Output Response (CLI, JSON, or HTML Dashboard)
// --------------------------------------------------------------------------
$wantsJson = $isCli || (isset($_GET['format']) && $_GET['format'] === 'json') || (!empty($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false);

if ($wantsJson) {
    if (!$isCli) {
        header('Content-Type: application/json; charset=utf-8');
    }
    echo json_encode(['success' => empty($cronReport['errors']), 'report' => $cronReport], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit();
}

// HTML Dashboard view for administrators
?>
<!DOCTYPE html>
<html lang="am" dir="ltr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>የጀርባ ራስ-ሰር ማሳወቂያዎች ማዕከል (Background Cron Alert Engine)</title>
    <link rel="icon" type="image/png" href="images/icon.png">
    <link rel="stylesheet" href="assets/css/mobile.css">
    <style>
        :root {
            --primary: #1e3a8a;
            --primary-dark: #172554;
            --accent: #d97706;
            --bg: #f8fafc;
            --card-bg: #ffffff;
            --text: #1e293b;
            --border: #e2e8f0;
            --success: #15803d;
        }
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            background: var(--bg);
            color: var(--text);
            margin: 0;
            padding: 20px 12px;
            display: flex;
            justify-content: center;
        }
        .container {
            width: 100%;
            max-width: 800px;
        }
        .card {
            background: var(--card-bg);
            border-radius: 16px;
            border: 1px solid var(--border);
            box-shadow: 0 4px 16px rgba(0,0,0,0.06);
            padding: 24px;
            margin-bottom: 20px;
        }
        .header-title {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-top: 0;
            color: var(--primary);
            font-size: 1.4rem;
        }
        .badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
        }
        .badge-success { background: #dcfce7; color: #166534; }
        .badge-info { background: #dbeafe; color: #1e40af; }
        .stat-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
            gap: 12px;
            margin: 20px 0;
        }
        .stat-box {
            background: #f1f5f9;
            padding: 14px;
            border-radius: 12px;
            text-align: center;
            border: 1px solid var(--border);
        }
        .stat-num {
            font-size: 1.8rem;
            font-weight: 700;
            color: var(--primary);
        }
        .stat-lbl {
            font-size: 0.85rem;
            color: #64748b;
            margin-top: 4px;
        }
        .log-box {
            background: #0f172a;
            color: #38bdf8;
            padding: 16px;
            border-radius: 10px;
            font-family: monospace;
            font-size: 0.9rem;
            max-height: 250px;
            overflow-y: auto;
            white-space: pre-wrap;
            line-height: 1.6;
        }
        .btn-run {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 10px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
            box-shadow: 0 4px 12px rgba(30, 58, 138, 0.25);
            transition: transform 0.15s ease;
        }
        .btn-run:hover {
            transform: translateY(-2px);
        }
        .cron-code {
            background: #f8fafc;
            border: 1px dashed #cbd5e1;
            padding: 12px;
            border-radius: 8px;
            font-family: monospace;
            font-size: 0.85rem;
            color: #0f172a;
            word-break: break-all;
            margin-top: 8px;
        }
    </style>
</head>
<body>
<div class="container">
    <div class="card">
        <h1 class="header-title">
            <span>⚙️</span>
            <span>የጀርባ ራስ-ሰር ማሳወቂያዎች ማዕከል</span>
        </h1>
        <p style="color: #64748b; font-size: 0.95rem; margin-top: -6px;">
            ይህ ማዕከል አፑ ተዘግቶ በነበረበት ጊዜም በዓላትን፣ የእሁድ 10:50 ማስታወሻዎችን እና የፈተና ቀናትን በሰዓታቸው አፍልቆ በ Web Push ወደ ተጠቃሚዎች ስልክ በቀጥታ ይልካል።
        </p>

        <div class="stat-grid">
            <div class="stat-box">
                <div class="stat-num"><?= $cronReport['notifications_created'] ?></div>
                <div class="stat-lbl">የተፈጠሩ ማሳወቂያዎች</div>
            </div>
            <div class="stat-box">
                <div class="stat-num" style="color: var(--success);"><?= $cronReport['push_sent'] ?></div>
                <div class="stat-lbl">የተላኩ የስልክ ፑሽ (Push)</div>
            </div>
            <div class="stat-box">
                <div class="stat-num"><?= $cronReport['executed_by'] ?></div>
                <div class="stat-lbl">የተከናወነበት መንገድ</div>
            </div>
        </div>

        <div style="margin: 20px 0; display: flex; gap: 12px; flex-wrap: wrap;">
            <a href="cron_alerts.php?token=<?= urlencode($storedToken) ?>" class="btn-run">
                <span>🔄</span> አሁን በድጋሚ አስኪድ (Run Now)
            </a>
            <a href="dashboard_admin.php" class="btn-run" style="background: #475569;">
                <span>⬅️</span> ወደ ዳሽቦርድ ተመለስ
            </a>
        </div>

        <h3 style="margin-top: 24px; color: var(--primary);">📋 የስራው ውጤት መዝገብ (Execution Logs)</h3>
        <div class="log-box">
[<?= date('Y-m-d H:i:s') ?>] Cron Runner Started.
<?php foreach ($cronReport['actions'] as $act): ?>
▶ <?= htmlspecialchars($act) ?>

<?php endforeach; ?>
<?php if (!empty($cronReport['errors'])): ?>
⚠️ ስህተቶች:
<?php foreach ($cronReport['errors'] as $err): ?>
  - <?= htmlspecialchars($err) ?>

<?php endforeach; ?>
<?php endif; ?>
[<?= date('Y-m-d H:i:s') ?>] Cron Task Completed Successfully.
        </div>
    </div>

    <!-- cPanel & Webhook Setup Instructions -->
    <div class="card">
        <h3 style="margin-top: 0; color: var(--primary); display: flex; align-items: center; gap: 8px;">
            <span>🌐</span> አፑ ሳይከፈት በጀርባ በየ 5 ደቂቃው ራሱ እንዲሰራ የማዋቀሪያ መመሪያ
        </h3>
        <p style="font-size: 0.9rem; color: #475569; line-height: 1.6;">
            አስተዳዳሪው ወይም ተጠቃሚው አፑን ባይከፍቱ እንኳ ሰርቨሩ በራሱ በየ 5 ደቂቃው ማሳወቂያዎችን እንዲልክ ከታች ከተጠቀሱት አማራጮች አንዱን ይጠቀሙ፡
        </p>

        <h4 style="margin-bottom: 4px; color: #334155;">አማራጭ 1፡ በነጻ የዌብ ክሮን (ለምሳሌ cron-job.org / uptimerobot.com)</h4>
        <p style="font-size: 0.85rem; color: #64748b; margin-top: 0;">
            በ <a href="https://cron-job.org" target="_blank" style="color: var(--primary); font-weight: 600;">cron-job.org</a> ላይ በነጻ ይመዝገቡና በየ 5 ወይም 15 ደቂቃው የሚከተለውን URL እንዲጠራ ያድርጉ፡
        </p>
        <?php
            $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https" : "http";
            $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
            $dir = dirname($_SERVER['REQUEST_URI'] ?? '/exam');
            $fullUrl = $protocol . "://" . $host . rtrim($dir, '/') . "/cron_alerts.php?token=" . urlencode($storedToken);
        ?>
        <div class="cron-code"><?= htmlspecialchars($fullUrl) ?></div>

        <h4 style="margin-top: 16px; margin-bottom: 4px; color: #334155;">አማራጭ 2፡ በ cPanel Cron Jobs</h4>
        <p style="font-size: 0.85rem; color: #64748b; margin-top: 0;">
            በሆስቲንግዎ cPanel ውስጥ ወደ <strong>Cron Jobs</strong> ገጽ በመሄድ በየ 5 ደቂቃው (<code>*/5 * * * *</code>) የሚከተለውን ትዕዛዝ ያስቀምጡ፡
        </p>
        <div class="cron-code">wget -q -O - "<?= htmlspecialchars($fullUrl) ?>" >/dev/null 2>&1</div>

        <h4 style="margin-top: 16px; margin-bottom: 4px; color: #334155;">አማራጭ 3፡ በሊኑክስ / ቪፒኤስ ተርሚናል (crontab -e)</h4>
        <div class="cron-code">*/5 * * * * /usr/bin/php <?= htmlspecialchars(__FILE__) ?> >/dev/null 2>&1</div>
    </div>
</div>
</body>
</html>
