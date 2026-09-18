<?php
/**
 * POST api/sync_push.php
 * Handles offline changes pushed by clients for attendance and marks.
 */
require_once __DIR__ . '/api_common.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_error('POST required', 405);
}

$authUser = auth_from_token($conn);
$body = read_json_body();
$results = ['attendance' => [], 'marks' => []];

$currentSemester = getCurrentSemester($conn);
$semesterId = $currentSemester ? (int)$currentSemester['id'] : 0;
$userId = (int)$authUser['id'];
$userRole = $authUser['role'];

// Students cannot push changes
if ($userRole === 'student') {
    json_error('Students are read-only and cannot push updates', 403);
}

// Check role permissions from database
$canEditAttendance = false;
$canEditMarks = false;

if ($userRole === 'admin') {
    $canEditAttendance = true;
    $canEditMarks = true;
} else {
    $userRow = dbFetchOne($conn, "SELECT can_edit_marks, can_edit_attendance FROM users WHERE id = ?", "i", [$userId]);
    if ($userRole === 'teacher') {
        $canEditMarks = $userRow ? (bool)$userRow['can_edit_marks'] : true;
        $canEditAttendance = canMarkAttendance($conn, $userId, $semesterId);
    } elseif ($userRole === 'attendance_submitter') {
        $canEditAttendance = true;
        $canEditMarks = false;
    }
}

// 1. Process Attendance Push
foreach (($body['attendance'] ?? []) as $rec) {
    $uuid = trim($rec['local_uuid'] ?? '');
    $studentId = (int)($rec['student_id'] ?? 0);
    $classId = (int)($rec['class_id'] ?? 0);
    $date = trim($rec['attendance_date'] ?? '');
    $status = trim($rec['status'] ?? 'absent');
    $clientUpdatedAt = trim($rec['updated_at'] ?? date('Y-m-d H:i:s'));

    if (!$uuid || !$studentId || !$classId || !$date) {
        $results['attendance'][] = ['local_uuid' => $uuid, 'success' => false, 'message' => 'Missing required fields'];
        continue;
    }

    if (!$canEditAttendance) {
        $results['attendance'][] = ['local_uuid' => $uuid, 'success' => false, 'message' => 'Not permitted to mark attendance'];
        continue;
    }

    // Authorization check: submitter/teacher must be assigned to this class
    if ($userRole !== 'admin') {
        if (!canTeacherMarkClassAttendance($conn, $userId, $classId, $semesterId)) {
            $results['attendance'][] = ['local_uuid' => $uuid, 'success' => false, 'message' => 'Not assigned to mark attendance for this class'];
            continue;
        }

        // Check if semester is closed
        $semCheck = dbFetchOne($conn, "SELECT status FROM semesters WHERE id = ?", "i", [$semesterId]);
        if (!$semCheck || $semCheck['status'] === 'closed') {
            $results['attendance'][] = ['local_uuid' => $uuid, 'success' => false, 'message' => 'Semester is closed'];
            continue;
        }

        // Check attendance_locked
        $attLock = dbFetchOne($conn, "SELECT attendance_locked FROM teacher_class WHERE class_id = ? AND semester_id = ? LIMIT 1", "ii", [$classId, $semesterId]);
        if ($attLock && intval($attLock['attendance_locked']) === 1) {
            $results['attendance'][] = ['local_uuid' => $uuid, 'success' => false, 'message' => 'Attendance entry is locked for this class'];
            continue;
        }
    }

    // Student enrollment validation
    $studentCheck = dbFetchOne($conn, "SELECT id FROM students WHERE id = ? AND class_id = ? AND (is_deleted = 0 OR is_deleted IS NULL)", "ii", [$studentId, $classId]);
    if (!$studentCheck) {
        $results['attendance'][] = ['local_uuid' => $uuid, 'success' => false, 'message' => 'Student not enrolled in specified class'];
        continue;
    }

    // Closed day check
    $closedCheck = dbFetchOne($conn, "SELECT id FROM attendance_days WHERE date_gregorian = ? AND is_school_day = 0 AND (class_id IS NULL OR class_id = ?) LIMIT 1", "si", [$date, $classId]);
    if ($closedCheck) {
        $results['attendance'][] = ['local_uuid' => $uuid, 'success' => false, 'message' => 'School is closed on this date'];
        continue;
    }

    // Conflict detection
    $existing = dbFetchOne($conn, "SELECT last_updated FROM attendance_records WHERE student_id = ? AND class_id = ? AND attendance_date = ? LIMIT 1", "iis", [$studentId, $classId, $date]);
    if ($existing && strtotime($existing['last_updated']) > strtotime($clientUpdatedAt)) {
        $results['attendance'][] = ['local_uuid' => $uuid, 'success' => false, 'conflict' => true, 'message' => 'Server record is newer than offline edit'];
        continue;
    }

    // Handle removal / uncheck
    if (in_array(strtolower($status), ['remove', 'uncheck', 'deleted', 'clear'])) {
        $ok = deleteAttendanceRecord($conn, $studentId, $classId, $date);
        $results['attendance'][] = ['local_uuid' => $uuid, 'success' => (bool)$ok, 'message' => $ok ? 'synced' : mysqli_error($conn)];
        continue;
    }

    // Upsert attendance
    $ok = dbExecute(
        $conn,
        "INSERT INTO attendance_records (student_id, class_id, teacher_id, attendance_date, status, marked_by, local_uuid, last_updated)
         VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
         ON DUPLICATE KEY UPDATE
            status = VALUES(status),
            marked_by = VALUES(marked_by),
            local_uuid = VALUES(local_uuid),
            last_updated = NOW()",
        "iiissis",
        [$studentId, $classId, $userId, $date, $status, $userId, $uuid]
    );

    $results['attendance'][] = ['local_uuid' => $uuid, 'success' => (bool)$ok, 'message' => $ok ? 'synced' : mysqli_error($conn)];
}

// 2. Process Marks Push
foreach (($body['marks'] ?? []) as $rec) {
    $uuid = trim($rec['local_uuid'] ?? '');
    $studentId = (int)($rec['student_id'] ?? 0);
    $classId = (int)($rec['class_id'] ?? 0);
    $targetSemesterId = (int)($rec['semester_id'] ?? $semesterId);
    $assignment = floatval($rec['assignment'] ?? 0);
    $participation = floatval($rec['participation'] ?? 0);
    $attendanceScore = floatval($rec['attendance'] ?? 0);
    $mid = floatval($rec['mid'] ?? 0);
    $final = floatval($rec['final'] ?? 0);
    $total = $assignment + $participation + $attendanceScore + $mid + $final;
    $clientUpdatedAt = trim($rec['updated_at'] ?? date('Y-m-d H:i:s'));

    if (!$uuid || !$studentId || !$classId || !$targetSemesterId) {
        $results['marks'][] = ['local_uuid' => $uuid, 'success' => false, 'message' => 'Missing required fields'];
        continue;
    }

    if (!$canEditMarks) {
        $results['marks'][] = ['local_uuid' => $uuid, 'success' => false, 'message' => 'Not permitted to enter marks'];
        continue;
    }

    // Authorization & Lock check
    if ($userRole !== 'admin') {
        $tc = dbFetchOne(
            $conn,
            "SELECT locked FROM teacher_class WHERE teacher_id = ? AND class_id = ? AND semester_id = ?",
            "iii",
            [$userId, $classId, $targetSemesterId]
        );
        if (!$tc) {
            $results['marks'][] = ['local_uuid' => $uuid, 'success' => false, 'message' => 'Not assigned to teach this class'];
            continue;
        }
        if (intval($tc['locked']) === 1) {
            $results['marks'][] = ['local_uuid' => $uuid, 'success' => false, 'message' => 'Class marks entry is locked'];
            continue;
        }

        // Check if semester is closed
        $targetSemCheck = dbFetchOne($conn, "SELECT status FROM semesters WHERE id = ?", "i", [$targetSemesterId]);
        if (!$targetSemCheck || $targetSemCheck['status'] === 'closed') {
            $results['marks'][] = ['local_uuid' => $uuid, 'success' => false, 'message' => 'Semester is closed'];
            continue;
        }
    }

    // Student enrollment validation
    $studentCheck = dbFetchOne($conn, "SELECT id FROM students WHERE id = ? AND class_id = ? AND (is_deleted = 0 OR is_deleted IS NULL)", "ii", [$studentId, $classId]);
    if (!$studentCheck) {
        $results['marks'][] = ['local_uuid' => $uuid, 'success' => false, 'message' => 'Student not enrolled in specified class'];
        continue;
    }

    // Conflict detection & safe merge
    $existing = dbFetchOne(
        $conn,
        "SELECT last_updated, assignment, participation, attendance, mid, final FROM marks WHERE student_id = ? AND class_id = ? AND semester_id = ? AND teacher_id = ? LIMIT 1",
        "iiii",
        [$studentId, $classId, $targetSemesterId, $userId]
    );
    if ($existing && !empty($existing['last_updated']) && strtotime($existing['last_updated']) > strtotime($clientUpdatedAt)) {
        $results['marks'][] = ['local_uuid' => $uuid, 'success' => false, 'conflict' => true, 'message' => 'Server record is newer than offline edit'];
        continue;
    }

    $assignment = isset($rec['assignment']) ? floatval($rec['assignment']) : ($existing ? floatval($existing['assignment']) : 0);
    $participation = isset($rec['participation']) ? floatval($rec['participation']) : ($existing ? floatval($existing['participation']) : 0);
    $attendanceScore = isset($rec['attendance']) ? floatval($rec['attendance']) : ($existing ? floatval($existing['attendance']) : 0);
    $mid = isset($rec['mid']) ? floatval($rec['mid']) : ($existing ? floatval($existing['mid']) : 0);
    $final = isset($rec['final']) ? floatval($rec['final']) : ($existing ? floatval($existing['final']) : 0);
    $total = $assignment + $participation + $attendanceScore + $mid + $final;

    // Upsert marks
    $ok = dbExecute(
        $conn,
        "INSERT INTO marks (student_id, class_id, teacher_id, semester_id, assignment, participation, attendance, mid, final, total, local_uuid, last_updated)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
         ON DUPLICATE KEY UPDATE
            assignment = VALUES(assignment),
            participation = VALUES(participation),
            attendance = VALUES(attendance),
            mid = VALUES(mid),
            final = VALUES(final),
            total = VALUES(total),
            local_uuid = VALUES(local_uuid),
            last_updated = NOW()",
        "iiiidddddds",
        [$studentId, $classId, $userId, $targetSemesterId, $assignment, $participation, $attendanceScore, $mid, $final, $total, $uuid]
    );

    $results['marks'][] = ['local_uuid' => $uuid, 'success' => (bool)$ok, 'message' => $ok ? 'synced' : mysqli_error($conn)];
}

json_out([
    'success' => true,
    'results' => $results,
    'server_time' => date('c')
]);
