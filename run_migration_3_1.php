<?php
require_once 'db.php';

// Migration: Add results_published and results_published_at to semesters (Phase 3.1)
$conn = mysqli_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);

if (!$conn) {
    die("Database connection failed. Please check your configuration.");
}

// 1. Check if results_published column exists
$check1 = mysqli_query($conn, "SHOW COLUMNS FROM semesters LIKE 'results_published'");
if (mysqli_num_rows($check1) === 0) {
    $sql1 = "ALTER TABLE semesters ADD COLUMN results_published tinyint(1) NOT NULL DEFAULT 0 AFTER status";
    if (mysqli_query($conn, $sql1)) {
        echo "Migration applied: Added results_published column to semesters table\n";
    } else {
        echo "Error adding results_published column: " . mysqli_error($conn) . "\n";
    }
} else {
    echo "Column results_published already exists in semesters table\n";
}

// 2. Check if results_published_at column exists
$check2 = mysqli_query($conn, "SHOW COLUMNS FROM semesters LIKE 'results_published_at'");
if (mysqli_num_rows($check2) === 0) {
    $sql2 = "ALTER TABLE semesters ADD COLUMN results_published_at datetime NULL AFTER results_published";
    if (mysqli_query($conn, $sql2)) {
        echo "Migration applied: Added results_published_at column to semesters table\n";
    } else {
        echo "Error adding results_published_at column: " . mysqli_error($conn) . "\n";
    }
} else {
    echo "Column results_published_at already exists in semesters table\n";
}

mysqli_close($conn);
