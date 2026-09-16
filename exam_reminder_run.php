<?php
/**
 * exam_reminder_run.php
 *
 * Scans calendar_events of type 'exam' and creates a notification for any
 * reminder interval (from reminder_days_before, e.g. "14,7,3,1,0") whose
 * day has arrived today. Idempotent: checks a marker in related_page before
 * inserting, so running this twice in a day does not duplicate reminders.
 *
 * Can be run two ways:
 *  1. Admin clicks the button on this page (manual trigger - always works,
 *     no server cron access required, fine for XAMPP/shared hosting).
 *  2. If your host allows a cron job or Windows Task Scheduler entry, point
 *     it at this URL once a day, e.g.:
 *       php /path/to/exam/exam_reminder_run.php --cron
 *     (the --cron flag skips the HTML page and admin-session check, since a
 *     cron job has no logged-in session; it still only touches calendar
 *     data and never anything security-sensitive).
 */
require_once 'db.php';

$is_cron = (php_sapi_name() === 'cli' && in_array('--cron', $argv ?? []));

if (!$is_cron) {
    requireAdmin();
}

function runExamReminders($conn) {
    $today = new DateTime('today');
    $results = [];

    $exams = dbFetchAll(
        $conn,
        "SELECT e.*, d.name_am AS target_division, g.name_am AS target_grade, c.name AS target_class,
                t.class_id, t.grade_id, t.division_id
         FROM calendar_events e
         LEFT JOIN calendar_event_targets t ON t.event_id = e.id
         LEFT JOIN divisions d ON t.division_id = d.id
         LEFT JOIN grades g ON t.grade_id = g.id
         LEFT JOIN classes c ON t.class_id = c.id
         WHERE e.event_type = 'exam' AND e.is_deleted = 0 AND e.event_date >= CURDATE()
           AND e.reminder_days_before IS NOT NULL AND e.reminder_days_before != ''"
    );

    foreach ($exams as $exam) {
        $examDate = new DateTime($exam['event_date']);
        $daysUntil = (int)$today->diff($examDate)->format('%r%a');
        if ($daysUntil < 0) continue;

        $intervals = array_map('intval', explode(',', $exam['reminder_days_before']));
        if (!in_array($daysUntil, $intervals, true)) continue;

        $marker = 'exam_reminder:' . $exam['id'] . ':' . $daysUntil;
        $already = dbFetchOne(
            $conn,
            "SELECT id FROM notifications WHERE related_page = ? AND related_event_id = ?",
            "si",
            [$marker, $exam['id']]
        );
        if ($already) continue; // already sent this interval for this exam

        $when = $daysUntil === 0 ? 'ዛሬ ነው' : "በ$daysUntil ቀን ውስጥ ይጀምራል";
        $message = $exam['title'] . " ($when)";

        $targets = [];
        if ($exam['class_id']) $targets[] = ['class_id' => $exam['class_id']];
        elseif ($exam['grade_id']) $targets[] = ['grade_id' => $exam['grade_id']];
        elseif ($exam['division_id']) $targets[] = ['division_id' => $exam['division_id']];
        // else: untargeted -> everyone

        createNotification($conn, '🔔 የፈተና ማስታወሻ', $message, $targets, 'high', $exam['id'], $marker);

        // Send real browser/phone Web Push notification
        if (file_exists(__DIR__ . '/services/push/WebPushService.php')) {
            require_once __DIR__ . '/services/push/WebPushService.php';
            WebPushService::sendToTarget($conn, $targets, [
                'title' => '🔔 የፈተና ማስታወሻ: ' . $exam['title'],
                'body' => $message,
                'url' => '/exam/calendar_view.php',
                'icon' => '/exam/images/icon.png'
            ]);
        }

        $results[] = "{$exam['title']} - $daysUntil day(s) before";
    }
    return $results;
}

if ($is_cron) {
    $sent = runExamReminders($conn);
    echo count($sent) . " reminder(s) sent.\n";
    foreach ($sent as $s) echo " - $s\n";
    exit(0);
}

$ran = false;
$sent = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['run_now'])) {
    if (verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $sent = runExamReminders($conn);
        auditLog($conn, 'exam_reminders_run', null, null, count($sent) . ' reminder(s) sent');
        $ran = true;
    }
}
$nav_active = 'exam_reminder_run';
?>
<!DOCTYPE html>
<html lang="am">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>የፈተና ማስታወሻ | አጸደ ትጉሃን</title>
<?php include 'pwa_head.php'; ?>
<style>
:root { --brown-dark:#8B4513; --gold-primary:#FFD700; --success:#10B981; }
* { margin:0; padding:0; box-sizing:border-box; font-family:'Segoe UI',sans-serif; }
body { background:#FAF9F6; }
.main-container { max-width:600px; margin:30px auto; padding:0 15px; }
.card { background:white; border-radius:14px; padding:25px; box-shadow:0 4px 12px rgba(0,0,0,0.08); text-align:center; }
.btn { background:var(--gold-primary); color:var(--brown-dark); border:none; padding:12px 28px; border-radius:10px; font-weight:700; cursor:pointer; font-size:15px; }
.result { text-align:left; background:#F0FDF4; border-radius:8px; padding:12px; margin-top:15px; font-size:13px; }
@media (max-width: 480px) {
    .main-container { padding: 0 12px 30px; margin: 15px auto; }
    .card { padding: 18px 14px; border-radius: 10px; }
    .btn { width: 100%; font-size: 14px; min-height: 44px; display: inline-flex; align-items: center; justify-content: center; }
}
</style>
</head>
<body>
<?php include 'mobile_nav.php'; ?>
<div class="main-container">
    <div class="card">
        <h2 style="color:var(--brown-dark); margin-bottom:10px;">🔔 የፈተና ማስታወሻ ማመንጫ</h2>
        <p style="color:#777; font-size:14px; margin-bottom:20px;">
            ከካላንደር ላይ ያሉ የፈተና ቀናትን በመፈተሽ ለዛሬ የታሰቡ ማስታወሻዎችን ለተማሪዎችና መምህራን ይልካል። ከአንድ ጊዜ በላይ ቢጫኑትም የተላኩት በድጋሚ አይላኩም።
        </p>
        <form method="POST">
            <?php echo csrfField(); ?>
            <button type="submit" name="run_now" class="btn">▶️ ማስታወሻዎችን አሁን ላክ</button>
        </form>
        <?php if ($ran): ?>
        <div class="result">
            <?php if (empty($sent)): ?>
                ዛሬ የሚላክ ምንም የፈተና ማስታወሻ የለም።
            <?php else: ?>
                ✅ <?php echo count($sent); ?> ማስታወሻ(ዎች) ተልኳል:
                <ul style="margin:8px 0 0 20px;">
                    <?php foreach ($sent as $s): ?><li><?php echo htmlspecialchars($s); ?></li><?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
</div>
</body>
</html>
<?php mysqli_close($conn); ?>
