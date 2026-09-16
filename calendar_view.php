<?php
require_once 'db.php';

if (!isLoggedIn() && !isStudent()) {
    header("Location: index.php");
    exit();
}

$user_id = intval($_SESSION['user_id'] ?? 0);
$role = $_SESSION['role'] ?? '';

// Figure out which class(es)/grade/division this viewer belongs to, so we
// only show events relevant to them plus anything untargeted (ALL).
$my_class_ids = [];
if ($role === 'teacher') {
    $rows = dbFetchAll($conn, "SELECT DISTINCT class_id FROM teacher_class WHERE teacher_id = ?", "i", [$user_id]);
    $my_class_ids = array_column($rows, 'class_id');
} elseif ($role === 'attendance_submitter') {
    $rows = dbFetchAll($conn, "SELECT DISTINCT class_id FROM attendance_assignments WHERE submitter_id = ?", "i", [$user_id]);
    $my_class_ids = array_column($rows, 'class_id');
} elseif (isStudent()) {
    $srow = dbFetchOne($conn, "SELECT class_id FROM students WHERE id = ?", "i", [$_SESSION['student_id']]);
    if ($srow) $my_class_ids = [$srow['class_id']];
}
$class_ids_sql = !empty($my_class_ids) ? implode(',', array_map('intval', $my_class_ids)) : '0';

$event_types = [
    'teaching' => '📚 ትምህርት', 'exam' => '📝 ፈተና', 'revision' => '📖 ክለሳ',
    'holiday' => '🎉 በዓል', 'church' => '⛪ የቤተክርስቲያን በዓል', 'meeting' => '👨‍🏫 የመምህራን ስብሰባ',
    'assessment' => '📋 ምዘና', 'announcement' => '📢 ማስታወቂያ', 'important' => '⚠️ አስፈላጊ',
];

$events = dbFetchAll(
    $conn,
    "SELECT DISTINCT e.* FROM calendar_events e
     LEFT JOIN calendar_event_targets t ON t.event_id = e.id
     WHERE e.is_deleted = 0 AND e.event_date >= DATE_SUB(CURDATE(), INTERVAL 3 DAY)
       AND (t.id IS NULL OR t.class_id IN ($class_ids_sql)
            OR t.grade_id IN (SELECT grade_id FROM classes WHERE id IN ($class_ids_sql))
            OR t.division_id IN (SELECT g.division_id FROM grades g JOIN classes c ON c.grade_id = g.id WHERE c.id IN ($class_ids_sql)))
     ORDER BY e.event_date ASC"
);

$nav_active = 'calendar_view';
?>
<!DOCTYPE html>
<html lang="am">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>ካሌንደር | አጸደ ትጉሃን</title>
<?php include 'pwa_head.php'; ?>
<style>
:root { --brown-dark:#8B4513; --gold-primary:#FFD700; --gold-pale:#FFF8DC; }
* { margin:0; padding:0; box-sizing:border-box; font-family:'Segoe UI',sans-serif; }
body { background:#FAF9F6; }
.main-container { max-width:800px; margin:20px auto; padding:0 15px 60px; }
.card { background:white; border-radius:14px; padding:15px; margin-bottom:20px; box-shadow:0 4px 12px rgba(0,0,0,0.08); }
.event-row { display:flex; gap:12px; padding:12px 5px; border-bottom:1px solid #eee; align-items:flex-start; }
.event-row:last-child { border-bottom:none; }
.eth-date { min-width:90px; font-weight:700; color:var(--brown-dark); font-size:14px; }
.empty { text-align:center; color:#999; padding:40px 20px; }
</style>
</head>
<body>
<?php include 'mobile_nav.php'; ?>
<div class="main-container">
    <div class="card">
        <h2 style="color:var(--brown-dark); margin-bottom:10px;">📅 የትምህርት ካሌንደር</h2>
        <?php if (empty($events)): ?>
            <div class="empty">📭 ምንም መጪ ክስተት የለም።</div>
        <?php else: foreach ($events as $e): ?>
        <div class="event-row">
            <div class="eth-date"><?php echo $e['ethiopian_day'] . ' ' . getEthiopianMonthName($e['ethiopian_month']); ?></div>
            <div>
                <strong><?php echo htmlspecialchars($event_types[$e['event_type']] ?? $e['event_type']); ?> — <?php echo htmlspecialchars($e['title']); ?></strong>
                <?php if ($e['description']): ?><div style="font-size:13px; color:#777;"><?php echo htmlspecialchars($e['description']); ?></div><?php endif; ?>
            </div>
        </div>
        <?php endforeach; endif; ?>
    </div>
</div>
</body>
</html>
<?php mysqli_close($conn); ?>
