<?php
require_once 'db.php';

// Migration: Add login rate limiting to users table
$conn = mysqli_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);

if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

// Check if columns already exist
$check = mysqli_query($conn, "SHOW COLUMNS FROM users LIKE 'login_attempts'");
if (mysqli_num_rows($check) > 0) {
    echo "Columns already exist. Migration already applied.\n";
    exit;
}

// Add columns
$sql = "ALTER TABLE users 
  ADD COLUMN login_attempts int(11) DEFAULT 0 AFTER can_edit_attendance,
  ADD COLUMN locked_until timestamp NULL DEFAULT NULL AFTER login_attempts";

if (mysqli_query($conn, $sql)) {
    echo "Migration applied successfully: Added login_attempts and locked_until to users table.\n";
} else {
    echo "Error applying migration: " . mysqli_error($conn) . "\n";
}

mysqli_close($conn);
