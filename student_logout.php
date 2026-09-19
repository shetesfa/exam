<?php
require_once 'db.php';

// Revoke all offline tokens for the current student
$student_id = intval($_SESSION['student_id'] ?? 0);
if ($student_id > 0) {
    dbExecute($conn, "UPDATE auth_tokens SET revoked = 1 WHERE user_id = ? AND role = 'student'", "i", [$student_id]);
}

session_start();
$_SESSION = [];
session_destroy();
header("Location: student_login.php");
exit();