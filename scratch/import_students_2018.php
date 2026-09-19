<?php
/**
 * scratch/import_students_2018.php
 * CLI-only script to import 311 students from the 2018 roster into the database.
 */
if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    die("Forbidden: CLI execution only.\n");
}

require_once __DIR__ . '/../db.php';

$jsonPath = __DIR__ . '/extracted_students_2018.json';
if (!file_exists($jsonPath)) {
    die("Error: JSON file not found at $jsonPath\n");
}

$students = json_decode(file_get_contents($jsonPath), true);
if (!is_array($students) || empty($students)) {
    die("Error: Failed to parse student records from JSON.\n");
}

echo "========================================\n";
echo "Starting Student Import (2018 Roster)\n";
echo "Total records to process: " . count($students) . "\n";
echo "========================================\n\n";

mysqli_begin_transaction($conn);

try {
    $stmtStudent = mysqli_prepare(
        $conn,
        "INSERT INTO students (name, class_id, current_grade, academic_year, parent_phone, promotion_status, student_portal_enabled, is_deleted)
         VALUES (?, ?, ?, ?, ?, ?, 1, 0)"
    );
    if (!$stmtStudent) {
        throw new Exception("Prepare student insert failed: " . mysqli_error($conn));
    }

    $stmtLogin = mysqli_prepare(
        $conn,
        "INSERT INTO student_logins (student_id, pin, first_login)
         VALUES (?, ?, 1)
         ON DUPLICATE KEY UPDATE pin = VALUES(pin), first_login = 1"
    );
    if (!$stmtLogin) {
        throw new Exception("Prepare login insert failed: " . mysqli_error($conn));
    }

    $defaultPinHash = password_hash('1234', PASSWORD_DEFAULT);

    $insertedCount = 0;
    $classCounts = [];

    foreach ($students as $st) {
        $name = trim($st['name']);
        $classId = intval($st['class_id']);
        $currentGrade = intval($st['current_grade']);
        $academicYear = intval($st['academic_year'] ?? 2018);
        $phone = trim($st['parent_phone'] ?? '');
        $promoStatus = in_array($st['promotion_status'], ['promoted', 'repeated', 'new']) ? $st['promotion_status'] : 'new';

        // Bind and insert student
        mysqli_stmt_bind_param(
            $stmtStudent,
            "siiiss",
            $name,
            $classId,
            $currentGrade,
            $academicYear,
            $phone,
            $promoStatus
        );

        if (!mysqli_stmt_execute($stmtStudent)) {
            throw new Exception("Insert failed for student '$name': " . mysqli_stmt_error($stmtStudent));
        }

        $newStudentId = mysqli_insert_id($conn);

        // Bind and insert student login
        mysqli_stmt_bind_param(
            $stmtLogin,
            "is",
            $newStudentId,
            $defaultPinHash
        );

        if (!mysqli_stmt_execute($stmtLogin)) {
            throw new Exception("Insert login failed for student ID $newStudentId: " . mysqli_stmt_error($stmtLogin));
        }

        $insertedCount++;
        $classCounts[$classId] = ($classCounts[$classId] ?? 0) + 1;
    }

    mysqli_stmt_close($stmtStudent);
    mysqli_stmt_close($stmtLogin);

    mysqli_commit($conn);

    echo "Successfully imported $insertedCount students!\n\n";
    echo "Breakdown by Class ID:\n";
    ksort($classCounts);
    foreach ($classCounts as $cid => $cnt) {
        $cRow = dbFetchOne($conn, "SELECT name FROM classes WHERE id = ?", "i", [$cid]);
        $cName = $cRow['name'] ?? "Class $cid";
        echo "  - Class $cid ($cName): $cnt students\n";
    }
    echo "\nAll students initialized with default PIN: 1234 (first_login = 1)\n";
    echo "========================================\n";
} catch (Exception $e) {
    mysqli_rollback($conn);
    echo "ERROR: Import rolled back! " . $e->getMessage() . "\n";
    exit(1);
}
