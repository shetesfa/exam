<?php
require_once 'db.php';
requireAdmin();

$message = '';
$error = '';

// Divisions/grades/classes for the targeting picker (safe even if empty)
$divisions = dbFetchAll($conn, "SELECT * FROM divisions ORDER BY sort_order");
$grades = dbFetchAll($conn, "SELECT g.*, d.name_am AS division_name FROM grades g JOIN divisions d ON g.division_id = d.id ORDER BY d.sort_order, g.level_number");
$classes = dbFetchAll($conn, "SELECT c.*, g.name_am AS grade_name FROM classes c LEFT JOIN grades g ON c.grade_id = g.id ORDER BY c.name");

$event_types = [
    'teaching' => '📚 ትምህርት', 'exam' => '📝 ፈተና', 'revision' => '📖 ክለሳ',
    'holiday' => '🎉 በዓል', 'church' => '⛪ የቤተክርስቲያን በዓል', 'meeting' => '👨‍🏫 የመምህራን ስብሰባ',
    'assessment' => '📋 ምዘና', 'announcement' => '📢 ማስታወቂያ', 'important' => '⚠️ አስፈላጊ',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = "የደህንነት ማረጋገጫ አልተሳካም! እባክዎ እንደገና ይሞክሩ።";
    } elseif (isset($_POST['add_event'])) {
        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $event_type = $_POST['event_type'] ?? 'announcement';
        $priority = $_POST['priority'] ?? 'normal';
        $ey = intval($_POST['eth_year'] ?? 0);
        $em = intval($_POST['eth_month'] ?? 0);
        $ed = intval($_POST['eth_day'] ?? 0);
        $reminders = trim($_POST['reminder_days'] ?? '');

        // Whitelist validation
        $valid_types = ['teaching','exam','revision','holiday','church','meeting','assessment','announcement','important'];
        if (!in_array($event_type, $valid_types)) $event_type = 'announcement';
        $valid_priorities = ['low','normal','high','urgent'];
        if (!in_array($priority, $valid_priorities)) $priority = 'normal';
        // reminder_days must be a comma-separated list of positive integers
        $reminders = preg_replace('/[^0-9,]/', '', $reminders);

        if (!$title || !$ey || !$em || !$ed) {
            $error = "እባክዎ ርዕስ እና ትክክለኛ የኢትዮጵያ ቀን ያስገቡ!";
        } else {
            $gregDate = ethiopianToGregorian($ey, $em, $ed);

            dbExecute(
                $conn,
                "INSERT INTO calendar_events (title, description, event_type, event_date, ethiopian_year, ethiopian_month, ethiopian_day, priority, reminder_days_before, created_by)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
                "ssssiiissi",
                [$title, $description, $event_type, $gregDate, $ey, $em, $ed, $priority, $reminders, intval($_SESSION['user_id'])]
            );
            $eventId = mysqli_insert_id($conn);

            // Targeting - scope radio decides what row(s) go into calendar_event_targets
            $scope = $_POST['scope'] ?? 'ALL';
            $recipients = [];

            if ($scope === 'DIVISION' && !empty($_POST['division_id'])) {
                $divId = intval($_POST['division_id']);
                dbExecute($conn, "INSERT INTO calendar_event_targets (event_id, division_id) VALUES (?, ?)", "ii", [$eventId, $divId]);
                $divTeachers = dbFetchAll($conn, "SELECT DISTINCT tc.teacher_id FROM teacher_class tc JOIN classes c ON tc.class_id = c.id JOIN grades g ON c.grade_id = g.id WHERE g.division_id = ?", "i", [$divId]);
                $recipients = array_column($divTeachers, 'teacher_id');
            } elseif ($scope === 'GRADE' && !empty($_POST['grade_id'])) {
                $gradeId = intval($_POST['grade_id']);
                dbExecute($conn, "INSERT INTO calendar_event_targets (event_id, grade_id) VALUES (?, ?)", "ii", [$eventId, $gradeId]);
                $gradeTeachers = dbFetchAll($conn, "SELECT DISTINCT tc.teacher_id FROM teacher_class tc JOIN classes c ON tc.class_id = c.id WHERE c.grade_id = ?", "i", [$gradeId]);
                $recipients = array_column($gradeTeachers, 'teacher_id');
            } elseif ($scope === 'CLASS' && !empty($_POST['class_id'])) {
                $classId = intval($_POST['class_id']);
                dbExecute($conn, "INSERT INTO calendar_event_targets (event_id, class_id) VALUES (?, ?)", "ii", [$eventId, $classId]);
                $classTeachers = dbFetchAll($conn, "SELECT DISTINCT teacher_id FROM teacher_class WHERE class_id = ?", "i", [$classId]);
                $recipients = array_column($classTeachers, 'teacher_id');
            } else {
                // Scope ALL -> all teachers
                $allT = dbFetchAll($conn, "SELECT id FROM users WHERE role = 'teacher'");
                $recipients = array_column($allT, 'id');
            }

            // Real notification to affected teachers
            if (!empty($recipients) && function_exists('createNotification')) {
                $notifMsg = "አዲስ የካላንደር ሁነት፡ '{$title}' ({$em}/{$ed}/{$ey} ዓ.ም)። " . ($description ? "ማስታወሻ፡ {$description}። " : "") . "እባክዎ እንደ አስፈላጊነቱ የትምህርት ዕቅድዎን ያዛምዱ።";
                createNotification(
                    $conn,
                    "📅 የትምህርት ካላንደር ማሻሻያ / ሁነት",
                    $notifMsg,
                    ['users' => $recipients],
                    $priority === 'urgent' ? 'high' : $priority,
                    null,
                    'calendar.php'
                );
            }

            auditLog($conn, 'calendar_event_created', 'calendar_events', $eventId, $title);
            $message = "ክስተት በተሳካ ሁኔታ ተጨምሯል፤ ለመምህራንም ማሳወቂያ ተልኳል! (Event added & teachers notified!)";
        }
    } elseif (isset($_POST['seed_official_2019'])) {
        if (function_exists('seedOfficialCalendar2019')) {
            $cnt = seedOfficialCalendar2019($conn);
            $allT = dbFetchAll($conn, "SELECT id FROM users WHERE role = 'teacher'");
            $teacherIds = array_column($allT, 'id');
            if (!empty($teacherIds) && function_exists('createNotification')) {
                createNotification(
                    $conn,
                    "📅 የ2019 ዓ.ም የትምህርት ካላንደር ገብቷል",
                    "የ2019 ዓ.ም የ1ኛ መንፈቀ ዓመት ኦፊሴላዊ የትምህርት ካላንደር ሁነቶች ገብተዋል። እባክዎ ካላንደሩን በመመልከት የትምህርት ዕቅድዎን ያዛምዱ።",
                    ['users' => $teacherIds],
                    'high',
                    null,
                    'calendar.php'
                );
            }
            $message = "የ2019 ዓ.ም ኦፊሴላዊ የትምህርት ካላንደር ሁነቶች ({$cnt}) በተሳካ ሁኔታ ተመዝግበዋል! (Official 2019 calendar seeded!)";
        }
    } elseif (isset($_POST['delete_event'])) {
        $eventId = intval($_POST['event_id'] ?? 0);
        if ($eventId > 0) {
            dbExecute($conn, "UPDATE calendar_events SET is_deleted = 1 WHERE id = ?", "i", [$eventId]);
            auditLog($conn, 'calendar_event_deleted', 'calendar_events', $eventId);
            $message = "ክስተት ተሰርዟል!";
        }
    }
}

// Upcoming events (next 60 days), with resolved target label
$events = dbFetchAll(
    $conn,
    "SELECT e.*,
        d.name_am AS target_division, g.name_am AS target_grade, c.name AS target_class
     FROM calendar_events e
     LEFT JOIN calendar_event_targets t ON t.event_id = e.id
     LEFT JOIN divisions d ON t.division_id = d.id
     LEFT JOIN grades g ON t.grade_id = g.id
     LEFT JOIN classes c ON t.class_id = c.id
     WHERE e.is_deleted = 0 AND e.event_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
     ORDER BY e.event_date ASC"
);

$nav_active = 'calendar_admin';
?>
<!DOCTYPE html>
<html lang="am">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>ካሌንደር | አጸደ ትጉሃን</title>
<?php include 'pwa_head.php'; ?>
<style>
:root { --brown-dark:#8B4513; --gold-primary:#FFD700; --gold-pale:#FFF8DC; --success:#10B981; --error:#EF4444; }
* { margin:0; padding:0; box-sizing:border-box; font-family:'Segoe UI',sans-serif; }
body { background:#FAF9F6; }
.main-container { max-width:1100px; margin:12px auto; padding:0 10px 40px; }
.card { background:white; border-radius:14px; padding:15px 12px; margin-bottom:20px; box-shadow:0 4px 12px rgba(0,0,0,0.08); }
.card h2 { color:var(--brown-dark); margin-bottom:15px; font-size:18px; }
.message { padding:12px 15px; border-radius:8px; margin-bottom:15px; }
.success { background:#D1FAE5; color:var(--success); }
.error { background:#FEE2E2; color:var(--error); }
.form-grid { display:grid; grid-template-columns:1fr; gap:8px; margin-bottom:12px; }
label { display:block; font-size:13px; font-weight:600; color:#555; margin-bottom:4px; }
input, select, textarea { width:100%; padding:9px; border:1px solid #ddd; border-radius:8px; font-size:14px; }
.btn { background:var(--gold-primary); color:var(--brown-dark); border:none; padding:10px 20px; border-radius:8px; font-weight:700; cursor:pointer; }
.scope-radios { display:flex; gap:15px; flex-wrap:wrap; margin-bottom:10px; }
.scope-radios label { display:flex; align-items:center; gap:5px; font-weight:500; }
.event-row { display:flex; justify-content:space-between; align-items:flex-start; padding:12px; border-bottom:1px solid #eee; gap:8px; flex-wrap:wrap; }
.event-row:last-child { border-bottom:none; }
.badge { font-size:11px; font-weight:700; padding:3px 10px; border-radius:12px; background:var(--gold-pale); color:var(--brown-dark); white-space:nowrap; }
.eth-date { font-weight:700; color:var(--brown-dark); min-width:unset; }
.del-btn { background:none; border:none; color:var(--error); cursor:pointer; font-size:16px; }
@media (max-width: 600px) {
    .main-container { padding: 0 10px 40px; margin: 12px auto; }
    .card { padding: 15px 12px; }
    .form-grid { grid-template-columns: 1fr; gap: 8px; }
    .btn { width: 100%; min-height: 44px; font-size: 14px; }
    .event-row { flex-direction: column; align-items: flex-start; gap: 8px; }
    .eth-date { min-width: unset; }
    .event-row > form { align-self: flex-end; }
}
</style>
</head>
<body>
<?php include 'mobile_nav.php'; ?>
<div class="main-container">
    <?php if ($message): ?><div class="message success">✅ <?php echo htmlspecialchars($message); ?></div><?php endif; ?>
    <?php if ($error): ?><div class="message error">⚠️ <?php echo htmlspecialchars($error); ?></div><?php endif; ?>

    <!-- Quick Seed Official 2019 Calendar Card -->
    <div class="card notice-banner-card" style="border: 2px dashed #D97706; margin-bottom: 20px;">
        <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px;">
            <div>
                <h3 style="color:#92400E; font-size:16px;">📜 የ2019 ዓ.ም ኦፊሴላዊ የትምህርት ካላንደር</h3>
                <p style="font-size:13px; color:#78350F; margin-top:4px;">
                    ከኦፊሴላዊው ሰነድ የተወሰዱ ዋና ዋና ሁነቶች (የትምህርት መጀመሪያ፣ 1ኛ/2ኛ ወር ማጠቃለያ፣ የካቲት 14 ማጠቃለያ ፈተና) በአንድ ቁልፍ ማስገባት ይችላሉ።
                </p>
            </div>
            <form method="POST" onsubmit="return confirm('የ2019 ዓ.ም ኦፊሴላዊ የትምህርት ካላንደር ሁነቶችን ማስገባት ይፈልጋሉ?')">
                <?php echo csrfField(); ?>
                <button type="submit" name="seed_official_2019" class="btn" style="background:#8B4513; color:white; padding:10px 20px; font-weight:700;">
                    📥 የ2019 ዓ.ም ካላንደር አስገባ
                </button>
            </form>
        </div>
    </div>

    <div class="card">
        <h2>➕ አዲስ የካላንደር ሁነት መመዝገቢያ</h2>
        <form method="POST">
            <?php echo csrfField(); ?>
            <div class="form-grid">
                <div><label>ርዕስ *</label><input type="text" name="title" required placeholder="ምሳሌ፡ 1ኛ ወር ማጠቃለያ ፈተና"></div>
                <div><label>የሁነቱ ዓይነት</label>
                    <select name="event_type">
                        <?php foreach ($event_types as $k => $v): ?>
                        <option value="<?php echo $k; ?>"><?php echo $v; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div><label>አስፈላጊነት ደረጃ</label>
                    <select name="priority">
                        <option value="normal">መደበኛ</option>
                        <option value="high">ከፍተኛ</option>
                        <option value="low">ዝቅተኛ</option>
                    </select>
                </div>
            </div>
            <div class="form-grid">
                <div><label>ዓመተ ምሕረት *</label><input type="number" name="eth_year" required value="2019"></div>
                <div><label>ወር (ከ1-13) *</label><input type="number" name="eth_month" min="1" max="13" required></div>
                <div><label>ቀን *</label><input type="number" name="eth_day" min="1" max="30" required></div>
                <div><label>የቀናት ማሳሰቢያ (ለምሳሌ፡ 7,3,1)</label><input type="text" name="reminder_days" placeholder="14,7,3,1,0"></div>
            </div>
            <label>የሁነቱ ዝርዝር መግለጫ</label>
            <textarea name="description" rows="2" style="margin-bottom:12px;" placeholder="ተጨማሪ ማብራሪያ ካለ እዚህ ይጻፉ..."></textarea>

            <label>ይህ ሁነት የሚመለከተው</label>
            <div class="scope-radios">
                <label><input type="radio" name="scope" value="ALL" checked onchange="toggleScope()"> ለሁሉም ክፍሎች</label>
                <label><input type="radio" name="scope" value="DIVISION" onchange="toggleScope()"> ለደረጃ (ህፃናት / ወጣቶች)</label>
                <label><input type="radio" name="scope" value="GRADE" onchange="toggleScope()"> ለክፍል ደረጃ</label>
                <label><input type="radio" name="scope" value="CLASS" onchange="toggleScope()"> ለተወሰነ ክፍል ብቻ</label>
            </div>
            <div class="form-grid">
                <div id="pick-division" style="display:none;">
                    <select name="division_id">
                        <?php foreach ($divisions as $d): ?><option value="<?php echo $d['id']; ?>"><?php echo htmlspecialchars($d['name_am']); ?></option><?php endforeach; ?>
                    </select>
                </div>
                <div id="pick-grade" style="display:none;">
                    <select name="grade_id">
                        <?php foreach ($grades as $g): ?><option value="<?php echo $g['id']; ?>"><?php echo htmlspecialchars($g['division_name'] . ' - ' . $g['name_am']); ?></option><?php endforeach; ?>
                    </select>
                </div>
                <div id="pick-class" style="display:none;">
                    <select name="class_id">
                        <?php foreach ($classes as $c): ?><option value="<?php echo $c['id']; ?>"><?php echo htmlspecialchars($c['name']); ?></option><?php endforeach; ?>
                    </select>
                </div>
            </div>
            <button type="submit" name="add_event" class="btn">➕ ሁነቱን መዝግብ</button>
        </form>
    </div>

    <div class="card">
        <h2>📅 የተመዘገቡ መጪ ሁነቶች</h2>
        <?php if (empty($events)): ?>
            <p style="color:#999; text-align:center; padding:20px;">ምንም ክስተት አልተመዘገበም።</p>
        <?php else: foreach ($events as $e):
            $targetLabel = $e['target_class'] ?: ($e['target_grade'] ?: ($e['target_division'] ?: 'ሁሉም'));
        ?>
        <div class="event-row">
            <div class="eth-date"><?php echo $e['ethiopian_day'] . ' ' . getEthiopianMonthName($e['ethiopian_month']) . ' ' . $e['ethiopian_year']; ?></div>
            <div style="flex:1; min-width:180px;">
                <strong><?php echo htmlspecialchars($event_types[$e['event_type']] ?? $e['event_type']); ?> — <?php echo htmlspecialchars($e['title']); ?></strong>
                <?php if ($e['description']): ?><div style="font-size:13px; color:#777;"><?php echo htmlspecialchars($e['description']); ?></div><?php endif; ?>
            </div>
            <span class="badge"><?php echo htmlspecialchars($targetLabel); ?></span>
            <form method="POST" onsubmit="return confirm('ይህን ክስተት መሰረዝ እርግጠኛ ነዎት?')" style="display:inline;">
                <?php echo csrfField(); ?>
                <input type="hidden" name="event_id" value="<?php echo $e['id']; ?>">
                <button type="submit" name="delete_event" class="del-btn">🗑️</button>
            </form>
        </div>
        <?php endforeach; endif; ?>
    </div>
</div>
<script>
function toggleScope() {
    const scope = document.querySelector('input[name="scope"]:checked').value;
    document.getElementById('pick-division').style.display = scope === 'DIVISION' ? 'block' : 'none';
    document.getElementById('pick-grade').style.display = scope === 'GRADE' ? 'block' : 'none';
    document.getElementById('pick-class').style.display = scope === 'CLASS' ? 'block' : 'none';
}
</script>
</body>
</html>
<?php mysqli_close($conn); ?>
