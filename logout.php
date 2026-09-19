<?php
require_once 'db.php';

// Revoke all offline tokens for the current user
$user_id = intval($_SESSION['user_id'] ?? 0);
if ($user_id > 0) {
    dbExecute($conn, "UPDATE auth_tokens SET revoked = 1 WHERE user_id = ?", "i", [$user_id]);
}

session_start();
$_SESSION = [];
session_destroy();
header("Location: index.php");
exit();