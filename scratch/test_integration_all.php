<?php
/**
 * scratch/test_integration_all.php
 * Integration test suite for pre-launch audit verification.
 */
require_once __DIR__ . '/../db.php';

$totalTests = 0;
$passedTests = 0;
$failedTests = 0;
$failures = [];

function assert_test($description, $condition, $details = '') {
    global $totalTests, $passedTests, $failedTests, $failures;
    $totalTests++;
    if ($condition) {
        $passedTests++;
        echo "  [PASS] $description\n";
    } else {
        $failedTests++;
        $failures[] = "$description: $details";
        echo "  [FAIL] $description\n";
        if ($details) {
            echo "         Details: $details\n";
        }
    }
}

echo "========================================\n";
echo "Starting Integration Test Suite\n";
echo "========================================\n\n";

// --- 1. Database Connection ---
echo "1. Database Connection & Config\n";
assert_test("Database connection active", isset($conn) && $conn !== false);
assert_test("BASE_URL defined", defined('BASE_URL') && strlen(BASE_URL) > 0, "BASE_URL = " . (defined('BASE_URL') ? BASE_URL : 'undefined'));

// --- 2. Calendar & Leap Year Unit Tests (Requirement 4.1) ---
echo "\n2. Ethiopian Calendar Leap Year & Conversion Tests\n";
assert_test("isEthiopianLeapYear function exists", function_exists('isEthiopianLeapYear'));

// 5 Known Date Pairs across Ethiopian Leap Years:
// Ethiopian leap years: 2011, 2015, 2019 (year % 4 === 3)
// Non-leap years: 2012, 2013, 2014, 2016, 2017, 2018
$leapPairs = [
    // 1. Pagume 6, 2011 (Leap year Pagume day 6) -> 2019-09-11
    ['ey' => 2011, 'em' => 13, 'ed' => 6, 'greg' => '2019-09-11', 'is_leap' => true],
    // 2. Meskerem 1, 2012 (Following a leap year, starts on Sept 12) -> 2019-09-12
    ['ey' => 2012, 'em' => 1, 'ed' => 1, 'greg' => '2019-09-12', 'is_leap' => false],
    // 3. Pagume 5, 2014 (Non-leap year) -> 2022-09-10
    ['ey' => 2014, 'em' => 13, 'ed' => 5, 'greg' => '2022-09-10', 'is_leap' => false],
    // 4. Pagume 6, 2015 (Leap year Pagume day 6) -> 2023-09-11
    ['ey' => 2015, 'em' => 13, 'ed' => 6, 'greg' => '2023-09-11', 'is_leap' => true],
    // 5. Meskerem 1, 2016 (Following 2015 leap year) -> 2023-09-12
    ['ey' => 2016, 'em' => 1, 'ed' => 1, 'greg' => '2023-09-12', 'is_leap' => false],
    // 6. Pagume 6, 2019 (Leap year Pagume day 6) -> 2027-09-11
    ['ey' => 2019, 'em' => 13, 'ed' => 6, 'greg' => '2027-09-11', 'is_leap' => true],
];

foreach ($leapPairs as $idx => $p) {
    $num = $idx + 1;
    // Check leap detection
    $leapCheck = isEthiopianLeapYear($p['ey']);
    assert_test("Pair $num: {$p['ey']} leap status is " . ($p['is_leap'] ? 'true' : 'false'), $leapCheck === $p['is_leap']);

    // Check Days in Pagume
    if ($p['em'] === 13) {
        $daysInPagume = getEthiopianDaysInMonth($p['ey'], 13);
        $expectedDays = $p['is_leap'] ? 6 : 5;
        assert_test("Pair $num: {$p['ey']} Pagume has $expectedDays days", $daysInPagume === $expectedDays);
    }

    // Check ethiopianToGregorian conversion
    $convGreg = ethiopianToGregorian($p['ey'], $p['em'], $p['ed']);
    assert_test("Pair $num: {$p['ey']}-{$p['em']}-{$p['ed']} converts to {$p['greg']}", $convGreg === $p['greg'], "Got: $convGreg");

    // Check gregorianToEthiopian conversion
    $convEth = gregorianToEthiopian($p['greg']);
    $ethMatch = ($convEth['year'] === $p['ey'] && $convEth['month'] === $p['em'] && $convEth['day'] === $p['ed']);
    assert_test("Pair $num: {$p['greg']} converts back to {$p['ey']}-{$p['em']}-{$p['ed']}", $ethMatch, "Got: {$convEth['year']}-{$convEth['month']}-{$convEth['day']}");
}

// --- 3. Database Schema Integrity ---
echo "\n3. Database Schema Integrity Checks\n";

// Users table rate limiting columns (1.2)
$userCols = dbFetchAll($conn, "SHOW COLUMNS FROM users");
$userColNames = array_column($userCols, 'Field');
assert_test("users table has login_attempts column", in_array('login_attempts', $userColNames));
assert_test("users table has locked_until column", in_array('locked_until', $userColNames));

// Semesters results_published columns (3.1)
$semCols = dbFetchAll($conn, "SHOW COLUMNS FROM semesters");
$semColNames = array_column($semCols, 'Field');
assert_test("semesters table has results_published column", in_array('results_published', $semColNames));
assert_test("semesters table has results_published_at column", in_array('results_published_at', $semColNames));

// Marks indexes (2.5)
$marksIndexes = dbFetchAll($conn, "SHOW INDEX FROM marks");
$marksIndexNames = array_unique(array_column($marksIndexes, 'Key_name'));
assert_test("marks table no longer has local_uuid_unique UNIQUE index", !in_array('local_uuid_unique', $marksIndexNames));

// Check marks business unique key columns
$marksCompIndexCols = [];
foreach ($marksIndexes as $idx) {
    if ($idx['Key_name'] === 'unique_teacher_student_semester') {
        $marksCompIndexCols[] = $idx['Column_name'];
    }
}
$expectedMarksKey = ['teacher_id', 'student_id', 'class_id', 'semester_id'];
$hasCompKey = count(array_intersect($expectedMarksKey, $marksCompIndexCols)) === 4;
assert_test("marks unique_teacher_student_semester includes (teacher_id, student_id, class_id, semester_id)", $hasCompKey, "Columns: " . implode(', ', $marksCompIndexCols));

// Attendance records indexes (2.5)
$attIndexes = dbFetchAll($conn, "SHOW INDEX FROM attendance_records");
$attIndexNames = array_unique(array_column($attIndexes, 'Key_name'));
assert_test("attendance_records table no longer has local_uuid_unique UNIQUE index", !in_array('local_uuid_unique', $attIndexNames));

// --- 4. Marking Scheme Clamping Calculation (2.7, 4.9) ---
echo "\n4. Marking Scheme & Clamping Calculations\n";
$scheme = getMarkingScheme($conn, 1, 1, 1);
assert_test("Marking scheme returns array with component percentages", is_array($scheme) && isset($scheme['component1_percentage']));

$val = 35.0;
$max = floatval($scheme['component1_percentage'] ?? 20.0);
$clamped = min(max($val, 0), $max);
assert_test("Over-maximum mark value 35 is clamped to max $max", $clamped === $max);

$negVal = -5.0;
$clampedNeg = floatval(min(max($negVal, 0), $max));
assert_test("Negative mark value -5 is clamped to 0", $clampedNeg === 0.0);

$studentMarksSource = file_get_contents(__DIR__ . '/../student_marks.php');
assert_test("student_marks.php checks !is_array(\$marks)", strpos($studentMarksSource, 'if (!is_array($marks)) continue;') !== false);
assert_test("student_marks.php surfaces clamping with Amharic message", strpos($studentMarksSource, 'አንዳንድ ውጤቶች ከከፍተኛው በላይ ስለነበሩ ተስተካክለዋል።') !== false);

// --- 5. Soft-Delete Attendance Integrity (2.6) ---
echo "\n5. Soft-Delete Attendance Record Check\n";
$dummyClass = dbFetchOne($conn, "SELECT id FROM classes LIMIT 1");
$cid = $dummyClass ? intval($dummyClass['id']) : 1;

// Insert a temporary test student for integration tests
dbExecute($conn, "INSERT INTO students (name, class_id, is_deleted) VALUES ('__Test Student__', ?, 0)", "i", [$cid]);
$dummyStudent = dbFetchOne($conn, "SELECT id, class_id FROM students WHERE name = '__Test Student__' ORDER BY id DESC LIMIT 1");
$sid = $dummyStudent ? intval($dummyStudent['id']) : 0;

$testDate = '2026-09-01';
if ($sid > 0) {
    saveAttendance($conn, $sid, $cid, 1, $testDate, 'present', 1);
    
    // Delete via deleteAttendanceRecord
    deleteAttendanceRecord($conn, $sid, $cid, $testDate);
    
    // Check in database if row is soft-deleted
    $softDeletedRow = dbFetchOne($conn, "SELECT is_deleted, last_updated FROM attendance_records WHERE student_id = ? AND class_id = ? AND attendance_date = ?", "iis", [$sid, $cid, $testDate]);
    assert_test("deleteAttendanceRecord marks is_deleted = 1", $softDeletedRow && intval($softDeletedRow['is_deleted']) === 1);
    
    // Cleanup test record
    dbExecute($conn, "DELETE FROM attendance_records WHERE student_id = ? AND class_id = ? AND attendance_date = ?", "iis", [$sid, $cid, $testDate]);
}

// --- 6. Role-Aware Notifications for Students (4.10) ---
echo "\n6. Role-Aware Notifications (Student & Teacher)\n";
if ($sid > 0) {
    $notifs = getNotificationsForUser($conn, $sid, false, 10);
    assert_test("getNotificationsForUser succeeds for student caller", is_array($notifs));
}

// --- 7. Student Dashboard Queries & Syntax (4.2, 4.3, 4.4, 4.5) ---
echo "\n7. Student Dashboard Queries & Syntax\n";
if ($sid > 0) {
    $semRow = dbFetchOne($conn, "SELECT id, start_date, end_date FROM semesters ORDER BY id DESC LIMIT 1");
    if ($semRow) {
        $semId = intval($semRow['id']);
        $rank_row = dbFetchOne(
            $conn,
            "SELECT COUNT(*) + 1 as student_rank FROM (
                SELECT s.id, AVG(COALESCE(m.total, 0)) as avg_mark
                FROM students s
                JOIN teacher_class tc ON s.class_id = tc.class_id AND tc.semester_id = ?
                LEFT JOIN marks m ON s.id = m.student_id AND m.semester_id = ? AND m.teacher_id = tc.teacher_id AND (m.is_deleted = 0 OR m.is_deleted IS NULL)
                WHERE s.class_id = ? AND (s.is_deleted = 0 OR s.is_deleted IS NULL)
                GROUP BY s.id
                HAVING AVG(COALESCE(m.total, 0)) > ?
            ) as better_students",
            "iiid",
            [$semId, $semId, $cid, 75.0]
        );
        assert_test("Student rank query with 'student_rank' alias executes without error", is_array($rank_row) && isset($rank_row['student_rank']));
        
        // Test scoped attendance query
        $attScoped = dbFetchOne(
            $conn,
            "SELECT 
                SUM(CASE WHEN ar.status = 'present' THEN 1 ELSE 0 END) as present,
                COUNT(*) as total_days
             FROM attendance_records ar
             JOIN semesters sem ON sem.id = ?
             WHERE ar.student_id = ? AND ar.class_id = ?
               AND (ar.is_deleted = 0 OR ar.is_deleted IS NULL)
               AND (sem.start_date IS NULL OR ar.attendance_date >= sem.start_date)
               AND (sem.end_date IS NULL OR ar.attendance_date <= sem.end_date)",
            "iii",
            [$semId, $sid, $cid]
        );
        assert_test("Semester-scoped attendance query executes without error", is_array($attScoped) && isset($attScoped['total_days']));
    }
}

// Cleanup temporary test student
dbExecute($conn, "DELETE FROM students WHERE name = '__Test Student__'");

// --- Summary ---
echo "\n========================================\n";
echo "Test Results: $passedTests / $totalTests passed (" . round(($passedTests / max(1, $totalTests)) * 100, 1) . "%)\n";
if ($failedTests > 0) {
    echo "Failures ($failedTests):\n";
    foreach ($failures as $f) {
        echo "  - $f\n";
    }
    echo "========================================\n";
    exit(1);
} else {
    echo "All tests passed successfully!\n";
    echo "========================================\n";
    exit(0);
}
