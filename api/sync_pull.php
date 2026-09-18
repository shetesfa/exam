<?php
/**
 * GET api/sync_pull.php?since=2026-08-01T00:00:00Z
 *
 * Returns deltas since last sync, scoped strictly by role & assignments:
 *   - teacher: only classes in teacher_class for active semester
 *   - attendance_submitter: only classes in attendance_assignments
 *   - admin: all classes
 *   - student: only their own class + own marks/attendance
 */
require_once __DIR__ . '/api_common.php';

$authUser = auth_from_token($conn);
$rawSince = $_GET['since'] ?? '1970-01-01 00:00:00';
$since = date('Y-m-d H:i:s', strtotime($rawSince) ?: 0);

$currentSemester = getCurrentSemester($conn);
$semesterId = $currentSemester ? (int)$currentSemester['id'] : 0;

$classIds = [];
switch ($authUser['role']) {
    case 'teacher':
        $q = "SELECT class_id FROM teacher_class WHERE teacher_id = ? AND semester_id = ?";
        $rows = dbFetchAll($conn, $q, "ii", [$authUser['id'], $semesterId]);
        foreach ($rows as $r) $classIds[] = (int)$r['class_id'];
        break;

    case 'attendance_submitter':
        $q = "SELECT class_id FROM attendance_assignments WHERE submitter_id = ? AND semester_id = ?";
        $rows = dbFetchAll($conn, $q, "ii", [$authUser['id'], $semesterId]);
        foreach ($rows as $r) $classIds[] = (int)$r['class_id'];
        break;

    case 'student':
        $classIds = [(int)($authUser['class_id'] ?? 0)];
        break;

    case 'admin':
        // Admins see all classes
        break;
}

if ($authUser['role'] !== 'admin' && empty($classIds)) {
    json_out([
        'success' => true,
        'classes' => [],
        'students' => [],
        'attendance' => [],
        'marks' => [],
        'server_time' => date('c')
    ]);
}

$classFilter = empty($classIds) ? '' : "AND class_id IN (" . implode(',', array_map('intval', $classIds)) . ")";

// 1. Classes
$classes = [];
if ($authUser['role'] === 'admin') {
    $classes = dbFetchAll($conn, "SELECT id, name, grade_id, description FROM classes ORDER BY name");
} else {
    $classes = dbFetchAll($conn, "SELECT id, name, grade_id, description FROM classes WHERE id IN (" . implode(',', array_map('intval', $classIds)) . ") ORDER BY name");
}

// 2. Students
$students = [];
if ($authUser['role'] === 'student') {
    $students = dbFetchAll($conn, "SELECT id, name, class_id, parent_phone, updated_at FROM students WHERE id = ? AND (is_deleted = 0 OR is_deleted IS NULL)", "i", [$authUser['id']]);
} else {
    $students = dbFetchAll(
        $conn,
        "SELECT id, name, class_id, parent_phone, updated_at FROM students WHERE updated_at > ? AND (is_deleted = 0 OR is_deleted IS NULL) $classFilter ORDER BY name",
        "s",
        [$since]
    );
}

// 3. Attendance
$attendance = [];
if ($authUser['role'] === 'student') {
    $attendance = dbFetchAll(
        $conn,
        "SELECT id, student_id, class_id, attendance_date, status, last_updated, local_uuid FROM attendance_records WHERE student_id = ? AND last_updated > ? AND (is_deleted = 0 OR is_deleted IS NULL)",
        "is",
        [$authUser['id'], $since]
    );
} else {
    $attendance = dbFetchAll(
        $conn,
        "SELECT id, student_id, class_id, attendance_date, status, last_updated, local_uuid FROM attendance_records WHERE last_updated > ? AND (is_deleted = 0 OR is_deleted IS NULL) $classFilter",
        "s",
        [$since]
    );
}

// 4. Marks
$marks = [];
if ($authUser['role'] === 'student') {
    $marks = dbFetchAll(
        $conn,
        "SELECT id, student_id, class_id, semester_id, assignment, participation, attendance, mid, final, total, last_updated, local_uuid FROM marks WHERE student_id = ? AND last_updated > ? AND (is_deleted = 0 OR is_deleted IS NULL)",
        "is",
        [$authUser['id'], $since]
    );
} elseif ($authUser['role'] === 'teacher') {
    // Teachers pull their own marks in their classes
    $marks = dbFetchAll(
        $conn,
        "SELECT id, student_id, class_id, semester_id, assignment, participation, attendance, mid, final, total, last_updated, local_uuid FROM marks WHERE teacher_id = ? AND last_updated > ? AND (is_deleted = 0 OR is_deleted IS NULL) $classFilter",
        "is",
        [$authUser['id'], $since]
    );
} else {
    // Admins or attendance submitters
    $marks = dbFetchAll(
        $conn,
        "SELECT id, student_id, class_id, semester_id, assignment, participation, attendance, mid, final, total, last_updated, local_uuid FROM marks WHERE last_updated > ? AND (is_deleted = 0 OR is_deleted IS NULL) $classFilter",
        "s",
        [$since]
    );
}

json_out([
    'success' => true,
    'classes' => $classes,
    'students' => $students,
    'attendance' => $attendance,
    'marks' => $marks,
    'active_semester' => $currentSemester,
    'server_time' => date('c')
]);
