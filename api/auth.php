<?php
/**
 * POST api/auth.php
 * Staff (admin/teacher/attendance_submitter):
 *   { "username": "...", "password": "..." }
 * Student:
 *   { "student_name": "...", "pin": "...", "is_student": true }
 *   OR { "student_id": 12, "pin": "...", "is_student": true }
 *
 * Returns a 30-day bearer token for offline operation and IndexedDB caching.
 */
require_once __DIR__ . '/api_common.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_error('POST required', 405);
}

$body = read_json_body();
$token = bin2hex(random_bytes(32));
$expiresAt = date('Y-m-d H:i:s', strtotime('+30 days'));

if (!empty($body['is_student'])) {
    // ---- Student login ----
    $name = trim($body['student_name'] ?? '');
    $studentId = intval($body['student_id'] ?? 0);
    $pin = trim($body['pin'] ?? '');

    if (($name === '' && $studentId === 0) || $pin === '') {
        json_error('Student name/ID and PIN required', 400);
    }

    $student = null;
    if ($studentId > 0) {
        $student = dbFetchOne(
            $conn,
            "SELECT s.*, sl.pin AS hashed_pin, sl.locked_until FROM students s
             LEFT JOIN student_logins sl ON s.id = sl.student_id
             WHERE s.id = ? AND s.student_portal_enabled = 1 AND (s.is_deleted = 0 OR s.is_deleted IS NULL) LIMIT 1",
            "i",
            [$studentId]
        );
    } else {
        $student = dbFetchOne(
            $conn,
            "SELECT s.*, sl.pin AS hashed_pin, sl.locked_until FROM students s
             LEFT JOIN student_logins sl ON s.id = sl.student_id
             WHERE s.name = ? AND s.student_portal_enabled = 1 AND (s.is_deleted = 0 OR s.is_deleted IS NULL) LIMIT 1",
            "s",
            [$name]
        );
    }

    if (!$student || empty($student['hashed_pin']) || !password_verify($pin, $student['hashed_pin'])) {
        json_error('የተሳሳተ የተማሪ ስም ወይም ፒን ቁጥር!', 401);
    }

    if (!empty($student['locked_until']) && strtotime($student['locked_until']) > time()) {
        json_error('አካውንትዎ ለጊዜው ተቆልፏል! እባክዎ ጥቂት ቆይተው እንደገና ይሞክሩ።', 403);
    }

    // Save token
    dbExecute(
        $conn,
        "INSERT INTO auth_tokens (user_id, token, role, expires_at) VALUES (?, ?, 'student', ?)",
        "iss",
        [$student['id'], $token, $expiresAt]
    );

    json_out([
        'success' => true,
        'token' => $token,
        'expires_at' => $expiresAt,
        'user' => [
            'id' => (int)$student['id'],
            'name' => $student['name'],
            'role' => 'student',
            'class_id' => (int)$student['class_id']
        ],
    ]);
}

// ---- Staff login: username + password ----
$username = trim($body['username'] ?? '');
$password = $body['password'] ?? '';

if ($username === '' || $password === '') {
    json_error('የተጠቃሚ ስም እና የይለፍ ቃል ያስፈልጋል!', 400);
}

$user = dbFetchOne(
    $conn,
    "SELECT * FROM users WHERE username = ? OR name = ? LIMIT 1",
    "ss",
    [$username, $username]
);

if (!$user || !password_verify($password, $user['password'])) {
    json_error('የተሳሳተ የተጠቃሚ ስም ወይም የይለፍ ቃል!', 401);
}

// Save token
dbExecute(
    $conn,
    "INSERT INTO auth_tokens (user_id, token, role, expires_at) VALUES (?, ?, ?, ?)",
    "isss",
    [$user['id'], $token, $user['role'], $expiresAt]
);

json_out([
    'success' => true,
    'token' => $token,
    'expires_at' => $expiresAt,
    'user' => [
        'id' => (int)$user['id'],
        'name' => $user['name'],
        'username' => $user['username'],
        'role' => $user['role'],
        'first_login' => (int)$user['first_login'],
        'can_edit_marks' => (int)$user['can_edit_marks'],
        'can_edit_attendance' => (int)$user['can_edit_attendance'],
    ],
]);
