<?php
require_once 'db.php';

// Migration: Remove UNIQUE constraint on local_uuid (Phase 2.5)
$conn = mysqli_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);

if (!$conn) {
    die("Database connection failed. Please check your configuration.");
}

// Drop UNIQUE constraint on attendance_records.local_uuid
$check1 = mysqli_query($conn, "SHOW INDEX FROM attendance_records WHERE Key_name = 'local_uuid_unique'");
if (mysqli_num_rows($check1) > 0) {
    $sql1 = "ALTER TABLE attendance_records DROP INDEX local_uuid_unique";
    if (mysqli_query($conn, $sql1)) {
        echo "Migration applied: Dropped UNIQUE index on attendance_records.local_uuid\n";
    } else {
        echo "Error dropping index on attendance_records: " . mysqli_error($conn) . "\n";
    }
} else {
    echo "Index local_uuid_unique does not exist on attendance_records (already migrated)\n";
}

// Drop UNIQUE constraint on marks.local_uuid
$check2 = mysqli_query($conn, "SHOW INDEX FROM marks WHERE Key_name = 'local_uuid_unique'");
if (mysqli_num_rows($check2) > 0) {
    $sql2 = "ALTER TABLE marks DROP INDEX local_uuid_unique";
    if (mysqli_query($conn, $sql2)) {
        echo "Migration applied: Dropped UNIQUE index on marks.local_uuid\n";
    } else {
        echo "Error dropping index on marks: " . mysqli_error($conn) . "\n";
    }
} else {
    echo "Index local_uuid_unique does not exist on marks (already migrated)\n";
}
// Update unique constraint on marks to (teacher_id, student_id, class_id, semester_id)
$check3 = mysqli_query($conn, "SHOW INDEX FROM marks WHERE Key_name = 'unique_teacher_student_semester'");
$cols = [];
while ($row = mysqli_fetch_assoc($check3)) {
    $cols[] = $row['Column_name'];
}
if (count($cols) !== 4 || !in_array('class_id', $cols)) {
    mysqli_query($conn, "ALTER TABLE marks ADD INDEX idx_teacher (teacher_id)");
    mysqli_query($conn, "ALTER TABLE marks DROP INDEX unique_teacher_student_semester");
    if (mysqli_query($conn, "ALTER TABLE marks ADD UNIQUE KEY unique_teacher_student_semester (teacher_id, student_id, class_id, semester_id)")) {
        echo "Migration applied: Updated marks unique_teacher_student_semester to include class_id\n";
    } else {
        echo "Error updating unique index on marks: " . mysqli_error($conn) . "\n";
    }
    mysqli_query($conn, "ALTER TABLE marks DROP INDEX idx_teacher");
} else {
    echo "Index unique_teacher_student_semester already includes class_id (already migrated)\n";
}

mysqli_close($conn);
