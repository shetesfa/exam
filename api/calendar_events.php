<?php
/**
 * api/calendar_events.php
 * Returns calendar events for offline caching and local reminders.
 */
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/api_common.php';

header('Content-Type: application/json; charset=utf-8');

$user = authenticateApiRequest($conn);
$userId = $user ? intval($user['id']) : intval($_SESSION['user_id'] ?? 0);
$role = $user ? $user['role'] : ($_SESSION['role'] ?? '');

$my_class_ids = [];
if ($role === 'teacher' && $userId > 0) {
    $rows = dbFetchAll($conn, "SELECT DISTINCT class_id FROM teacher_class WHERE teacher_id = ?", "i", [$userId]);
    $my_class_ids = array_column($rows, 'class_id');
} elseif (isStudent()) {
    $sid = intval($_SESSION['student_id'] ?? 0);
    $srow = dbFetchOne($conn, "SELECT class_id FROM students WHERE id = ?", "i", [$sid]);
    if ($srow) $my_class_ids = [intval($srow['class_id'])];
}

$class_ids_sql = !empty($my_class_ids) ? implode(',', array_map('intval', $my_class_ids)) : '0';

if ($role === 'admin' || empty($my_class_ids)) {
    $events = dbFetchAll(
        $conn,
        "SELECT id, title, description, event_type, event_date, ethiopian_year, ethiopian_month, ethiopian_day, priority, reminder_days_before
         FROM calendar_events
         WHERE is_deleted = 0 AND event_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
         ORDER BY event_date ASC LIMIT 100"
    );
} else {
    $events = dbFetchAll(
        $conn,
        "SELECT DISTINCT e.id, e.title, e.description, e.event_type, e.event_date, e.ethiopian_year, e.ethiopian_month, e.ethiopian_day, e.priority, e.reminder_days_before
         FROM calendar_events e
         LEFT JOIN calendar_event_targets t ON t.event_id = e.id
         WHERE e.is_deleted = 0 AND e.event_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
           AND (t.id IS NULL OR t.class_id IN ($class_ids_sql)
                OR t.grade_id IN (SELECT grade_id FROM classes WHERE id IN ($class_ids_sql))
                OR t.division_id IN (SELECT g.division_id FROM grades g JOIN classes c ON c.grade_id = g.id WHERE c.id IN ($class_ids_sql)))
         ORDER BY e.event_date ASC LIMIT 100"
    );
}

echo json_encode([
    'success' => true,
    'events' => $events,
    'cached_at' => date('Y-m-d H:i:s')
], JSON_UNESCAPED_UNICODE);
