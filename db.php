<?php
// Set Ethiopian Timezone (Africa/Addis_Ababa - EAT UTC+3)
date_default_timezone_set('Africa/Addis_Ababa');

// ============================================
// DATABASE CONFIGURATION
// ============================================
defined('DB_HOST') or define('DB_HOST', getenv('DB_HOST') ?: '127.0.0.1');
defined('DB_USER') or define('DB_USER', getenv('DB_USER') ?: 'root');
defined('DB_PASS') or define('DB_PASS', getenv('DB_PASS') !== false ? getenv('DB_PASS') : '');  
defined('DB_NAME') or define('DB_NAME', getenv('DB_NAME') ?: 'atsede_sunday_school'); 

// Gate for internal maintenance/dev tools (fix.php, debug_students.php, etc).
// Defaults OFF. To use a tool locally: set APP_DEBUG=1 as an environment
// variable before starting PHP, or export APP_DEBUG=1 in your shell/XAMPP
// config. Never enable this on a production/live server.
defined('APP_DEBUG') or define('APP_DEBUG', getenv('APP_DEBUG') === '1');

// OpenSSL configuration fallback for Windows / XAMPP environments
if (!getenv('OPENSSL_CONF') && file_exists('C:/xampp/apache/conf/openssl.cnf')) {
    putenv('OPENSSL_CONF=C:/xampp/apache/conf/openssl.cnf');
}

// Error handling: XAMPP's default php.ini often has display_errors On, which
// would leak file paths, SQL, and stack traces to end users on a live site.
// APP_DEBUG=1 (local dev) keeps full errors visible; otherwise show a single
// friendly Amharic message and log the real error to the PHP error log.
if (APP_DEBUG) {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
    error_reporting(E_ALL);
    set_exception_handler(function ($e) {
        error_log('Uncaught exception: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
        http_response_code(500);
        echo "እባክዎ እንደገና ይሞክሩ። ችግሩ ከቀጠለ አስተዳዳሪውን ያነጋግሩ።";
        exit;
    });
    register_shutdown_function(function () {
        $err = error_get_last();
        if ($err && in_array($err['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
            error_log('Fatal error: ' . $err['message'] . ' in ' . $err['file'] . ':' . $err['line']);
            if (!headers_sent()) http_response_code(500);
            echo "እባክዎ እንደገና ይሞክሩ። ችግሩ ከቀጠለ አስተዳዳሪውን ያነጋግሩ። (Something went wrong - please try again.)";
        }
    });
}

// Create connection
$conn = mysqli_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);

if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

mysqli_set_charset($conn, "utf8mb4");

// Start unified session if not started
if (session_status() === PHP_SESSION_NONE) {
    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'domain' => '',
        'secure' => $secure,
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    session_start();

    // Session inactivity timeout (4 hours)
    $maxIdleTime = 14400;
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > $maxIdleTime)) {
        $_SESSION = [];
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params["path"], $params["domain"],
                $params["secure"], $params["httponly"]
            );
        }
        session_destroy();
        session_start();
    }
    $_SESSION['last_activity'] = time();
}

// ============================================
// CSRF PROTECTION FUNCTIONS
// ============================================

function generateCsrfToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verifyCsrfToken($token) {
    if (empty($token)) {
        return false;
    }
    if (!empty($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token)) {
        return true;
    }
    if (!empty($_SESSION['csrf_token_prev']) && hash_equals($_SESSION['csrf_token_prev'], $token)) {
        return true;
    }
    return false;
}

function csrfField() {
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(generateCsrfToken()) . '">';
}

// ============================================
// PREPARED STATEMENTS HELPER FUNCTIONS
// ============================================

function dbQuery($conn, $sql, $types = "", $params = []) {
    if (empty($params)) {
        return mysqli_query($conn, $sql);
    }
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) {
        return false;
    }
    if (!empty($types) && !empty($params)) {
        mysqli_stmt_bind_param($stmt, $types, ...$params);
    }
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    mysqli_stmt_close($stmt);
    return $result;
}

function dbFetchAll($conn, $sql, $types = "", $params = []) {
    $result = dbQuery($conn, $sql, $types, $params);
    if (!$result) return [];
    $rows = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $rows[] = $row;
    }
    return $rows;
}

function dbFetchOne($conn, $sql, $types = "", $params = []) {
    $result = dbQuery($conn, $sql, $types, $params);
    if (!$result || mysqli_num_rows($result) === 0) return null;
    return mysqli_fetch_assoc($result);
}

function dbExecute($conn, $sql, $types = "", $params = []) {
    if (empty($params)) {
        return mysqli_query($conn, $sql);
    }
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) return false;
    if (!empty($types) && !empty($params)) {
        mysqli_stmt_bind_param($stmt, $types, ...$params);
    }
    $success = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    return $success;
}

// ============================================
// PASSWORD & AUTHENTICATION FUNCTIONS
// ============================================

function hashPassword($password) {
    return password_hash($password, PASSWORD_DEFAULT);
}

function verifyPassword($password, $hash) {
    return password_verify($password, $hash);
}

function isLoggedIn() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

function isAdmin() {
    return (isset($_SESSION['role']) && $_SESSION['role'] === 'admin');
}

function isTeacher() {
    return (isset($_SESSION['role']) && $_SESSION['role'] === 'teacher');
}

function isAttendanceSubmitter() {
    return (isset($_SESSION['role']) && $_SESSION['role'] === 'attendance_submitter');
}

/**
 * True if the logged-in user is allowed to mark attendance for at least one
 * class this semester: either admin, the attendance_submitter role, or a
 * teacher who teaches Children (Grade 1-6), or has an explicit assignment row
 * in attendance_assignments, or youth write attendance is enabled in settings.
 */
function canMarkAttendance($conn, $userId, $semesterId = null) {
    if (isAdmin()) return true;
    if (isAttendanceSubmitter()) return true;
    $userId = intval($userId);
    if (!$userId) return false;
    
    // Check role in DB if session is not active
    $userRow = dbFetchOne($conn, "SELECT role FROM users WHERE id = ?", "i", [$userId]);
    if (!$userRow) return false;
    if ($userRow['role'] === 'admin' || $userRow['role'] === 'attendance_submitter') return true;
    if ($userRow['role'] !== 'teacher') return false;

    return teacherCanWriteAttendance($conn, $userId, $semesterId);
}

/**
 * Check if a specific user/teacher is permitted to record attendance for a specific class
 */
function canTeacherMarkClassAttendance($conn, $userId, $classId, $semesterId = null) {
    $userId = intval($userId);
    $classId = intval($classId);
    $semesterId = intval($semesterId);
    if (!$userId || !$classId) return false;
    if (isAdmin()) return true;

    // Check if user is admin in DB
    $userRow = dbFetchOne($conn, "SELECT role FROM users WHERE id = ?", "i", [$userId]);
    if ($userRow && $userRow['role'] === 'admin') return true;
    
    // Check if explicitly assigned in attendance_assignments
    if ($semesterId) {
        $aa = dbFetchOne(
            $conn,
            "SELECT id FROM attendance_assignments WHERE submitter_id = ? AND class_id = ? AND semester_id = ?",
            "iii",
            [$userId, $classId, $semesterId]
        );
        if ($aa) return true;
    } else {
        $aa = dbFetchOne(
            $conn,
            "SELECT id FROM attendance_assignments WHERE submitter_id = ? AND class_id = ?",
            "ii",
            [$userId, $classId]
        );
        if ($aa) return true;
    }
    
    // Dedicated attendance submitter role cannot write classes not assigned in attendance_assignments
    if (isAttendanceSubmitter()) return false;
    
    // Check teacher assignment in teacher_class
    $tcSql = "SELECT tc.id, g.division_id, g.level_number 
              FROM teacher_class tc
              JOIN classes c ON tc.class_id = c.id
              LEFT JOIN grades g ON c.grade_id = g.id
              WHERE tc.teacher_id = ? AND tc.class_id = ?";
    $params = [$userId, $classId];
    $types = "ii";
    if ($semesterId) {
        $tcSql .= " AND tc.semester_id = ?";
        $params[] = $semesterId;
        $types .= "i";
    }
    $tc = dbFetchOne($conn, $tcSql, $types, $params);
    if (!$tc) return false;
    
    // Children division (division_id = 1 or level <= 6) can always record attendance
    if (intval($tc['division_id']) === 1 || (intval($tc['level_number']) > 0 && intval($tc['level_number']) <= 6)) {
        return true;
    }
    
    // Youth division can write if admin enabled it
    $setting = dbFetchOne($conn, "SELECT setting_value FROM settings WHERE setting_key = 'youth_can_write_attendance'");
    return ($setting && trim($setting['setting_value']) === '1');
}

function isStudent() {
    return (isset($_SESSION['student_id']) && !empty($_SESSION['student_id']));
}

function requireLogin() {
    if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
        header("Location: index.php");
        exit();
    }
}

function requireAdmin() {
    requireLogin();
    if (!isAdmin()) {
        header("Location: dashboard_teacher.php");
        exit();
    }
}

function requireTeacher() {
    requireLogin();
    if (!isTeacher() && !isAdmin()) {
        header("Location: dashboard_admin.php");
        exit();
    }
}

function requireAttendanceSubmitter() {
    requireLogin();
    if (!isAttendanceSubmitter() && !isAdmin()) {
        header("Location: index.php");
        exit();
    }
}

function requireStudent() {
    if (!isStudent()) {
        header("Location: student_login.php");
        exit();
    }
}

// ============================================
// MARKING SCHEME FUNCTIONS
// ============================================

function getMarkingScheme($conn, $teacher_id, $class_id, $semester_id) {
    $teacher_id = intval($teacher_id);
    $class_id = intval($class_id);
    $semester_id = intval($semester_id);
    
    $row = dbFetchOne(
        $conn, 
        "SELECT * FROM marking_schemes WHERE teacher_id = ? AND class_id = ? AND semester_id = ?",
        "iii", 
        [$teacher_id, $class_id, $semester_id]
    );
    
    if ($row) {
        return $row;
    }
    
    // Return default scheme
    return [
        'component1_name' => 'Assignment',
        'component1_percentage' => 20,
        'component2_name' => 'Participation',
        'component2_percentage' => 20,
        'component3_name' => 'Attendance',
        'component3_percentage' => 10,
        'component4_name' => 'Mid Exam',
        'component4_percentage' => 25,
        'component5_name' => 'Final Exam',
        'component5_percentage' => 25
    ];
}

function saveMarkingScheme($conn, $teacher_id, $class_id, $semester_id, $data) {
    $teacher_id = intval($teacher_id);
    $class_id = intval($class_id);
    $semester_id = intval($semester_id);
    
    $c1_name = trim($data['c1_name'] ?? 'Assignment');
    $c1_perc = floatval($data['c1_perc'] ?? 0);
    $c2_name = trim($data['c2_name'] ?? 'Participation');
    $c2_perc = floatval($data['c2_perc'] ?? 0);
    $c3_name = trim($data['c3_name'] ?? 'Attendance');
    $c3_perc = floatval($data['c3_perc'] ?? 0);
    $c4_name = trim($data['c4_name'] ?? 'Mid Exam');
    $c4_perc = floatval($data['c4_perc'] ?? 0);
    $c5_name = trim($data['c5_name'] ?? 'Final Exam');
    $c5_perc = floatval($data['c5_perc'] ?? 0);
    
    $total = $c1_perc + $c2_perc + $c3_perc + $c4_perc + $c5_perc;
    if (abs($total - 100) > 0.01) {
        return ['success' => false, 'message' => "Total must be 100% (Current: {$total}%)"];
    }
    
    $stmt = mysqli_prepare($conn, "INSERT INTO marking_schemes 
              (teacher_id, class_id, semester_id, 
               component1_name, component1_percentage,
               component2_name, component2_percentage,
               component3_name, component3_percentage,
               component4_name, component4_percentage,
               component5_name, component5_percentage)
              VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
              ON DUPLICATE KEY UPDATE
              component1_name = VALUES(component1_name),
              component1_percentage = VALUES(component1_percentage),
              component2_name = VALUES(component2_name),
              component2_percentage = VALUES(component2_percentage),
              component3_name = VALUES(component3_name),
              component3_percentage = VALUES(component3_percentage),
              component4_name = VALUES(component4_name),
              component4_percentage = VALUES(component4_percentage),
              component5_name = VALUES(component5_name),
              component5_percentage = VALUES(component5_percentage)");
              
    if (!$stmt) {
        return ['success' => false, 'message' => 'Query error: ' . mysqli_error($conn)];
    }
    
    mysqli_stmt_bind_param($stmt, "iiisdsdsdsdsd", 
        $teacher_id, $class_id, $semester_id,
        $c1_name, $c1_perc,
        $c2_name, $c2_perc,
        $c3_name, $c3_perc,
        $c4_name, $c4_perc,
        $c5_name, $c5_perc
    );
    
    if (mysqli_stmt_execute($stmt)) {
        mysqli_stmt_close($stmt);
        return ['success' => true, 'message' => 'Marking scheme saved!'];
    }
    
    $err = mysqli_stmt_error($stmt);
    mysqli_stmt_close($stmt);
    return ['success' => false, 'message' => 'Error: ' . $err];
}

// ============================================
// ETHIOPIAN CALENDAR FUNCTIONS
// ============================================

function ethiopianToGregorian($ethYear, $ethMonth, $ethDay) {
    $ethYear = intval($ethYear);
    $ethMonth = intval($ethMonth);
    $ethDay = intval($ethDay);
    $gregYear = $ethYear + 7;
    $newYearDay = (($ethYear - 1) % 4 === 3) ? 12 : 11;
    $startDate = new DateTime("$gregYear-09-$newYearDay");
    
    $daysOffset = ($ethMonth - 1) * 30 + ($ethDay - 1);
    $startDate->modify("+$daysOffset days");
    return $startDate->format('Y-m-d');
}

function gregorianToEthiopian($gregorianDate = null) {
    if (!$gregorianDate) {
        $gregorianDate = date('Y-m-d');
    }
    $date = new DateTime($gregorianDate);
    $gy = (int)$date->format('Y');
    
    $prevEthLeap = (($gy - 8) % 4 === 3);
    $nyDay = $prevEthLeap ? 12 : 11;
    $nyDate = new DateTime("$gy-09-$nyDay");
    
    if ($date >= $nyDate) {
        $ey = $gy - 7;
        $diff = $nyDate->diff($date)->days;
    } else {
        $ey = $gy - 8;
        $prevPrevEthLeap = (($gy - 9) % 4 === 3);
        $prevNyDay = $prevPrevEthLeap ? 12 : 11;
        $prevNyDate = new DateTime(($gy - 1) . "-09-$prevNyDay");
        $diff = $prevNyDate->diff($date)->days;
    }
    
    $em = (int)intdiv($diff, 30) + 1;
    $ed = (int)($diff % 30) + 1;
    
    if ($em > 13) {
        $em = 13;
    }
    
    $ethiopianMonths = [
        1 => 'መስከረም', 2 => 'ጥቅምት', 3 => 'ኅዳር', 4 => 'ታኅሣሥ',
        5 => 'ጥር', 6 => 'የካቲት', 7 => 'መጋቢት', 8 => 'ሚያዝያ',
        9 => 'ግንቦት', 10 => 'ሰኔ', 11 => 'ሐምሌ', 12 => 'ነሐሴ', 13 => 'ጳጉሜን'
    ];
    
    return [
        'year' => (int)$ey,
        'month' => (int)$em,
        'day' => (int)$ed,
        'month_name' => $ethiopianMonths[$em] ?? 'ያልታወቀ',
        'day_of_week' => $date->format('l'),
        'formatted' => ($ethiopianMonths[$em] ?? '') . ' ' . $ed . ' ቀን ' . $ey . ' ዓ.ም',
        'full' => ($ethiopianMonths[$em] ?? '') . ' ' . $ed . ' ቀን ' . $ey . ' ዓ.ም'
    ];
}

function getCurrentEthiopianDate() {
    return gregorianToEthiopian(date('Y-m-d'));
}

function getEthiopianDateFromGregorian($gregorian_date) {
    return gregorianToEthiopian($gregorian_date);
}

function convertToEthiopianTime($time24) {
    if (!$time24) return 'N/A';
    $time = date("H:i", strtotime($time24));
    $parts = explode(':', $time);
    $hour = intval($parts[0] ?? 0);
    $minute = $parts[1] ?? '00';
    $ethiopian_hour = $hour % 12;
    if ($ethiopian_hour == 0) $ethiopian_hour = 12;
    $period = ($hour < 12) ? 'ጠዋት' : 'ማታ';
    return $ethiopian_hour . ':' . $minute . ' ' . $period;
}

function getEthiopianDaysInMonth($ethiopianYear, $ethiopianMonth) {
    if ($ethiopianMonth == 13) {
        // Pagume: 6 days in leap year (ethiopianYear % 4 == 3), 5 days otherwise
        return ($ethiopianYear % 4 === 3) ? 6 : 5;
    }
    return 30;
}

function getEthiopianMonthName($monthNumber) {
    $months = [
        1 => 'መስከረም', 2 => 'ጥቅምት', 3 => 'ኅዳር', 4 => 'ታኅሣሥ',
        5 => 'ጥር', 6 => 'የካቲት', 7 => 'መጋቢት', 8 => 'ሚያዝያ',
        9 => 'ግንቦት', 10 => 'ሰኔ', 11 => 'ሐምሌ', 12 => 'ነሐሴ', 13 => 'ጳጉሜን'
    ];
    return $months[$monthNumber] ?? 'ያልታወቀ';
}

// ============================================
// ATTENDANCE FUNCTIONS
// ============================================

function getAttendanceDaysForMonth($conn, $ethiopian_year, $ethiopian_month) {
    $ethiopian_year = intval($ethiopian_year);
    $ethiopian_month = intval($ethiopian_month);
    
    $rows = dbFetchAll(
        $conn,
        "SELECT * FROM attendance_days WHERE ethiopian_year = ? AND ethiopian_month = ? ORDER BY date_gregorian",
        "ii",
        [$ethiopian_year, $ethiopian_month]
    );
    
    $days = [];
    foreach ($rows as $row) {
        $days[$row['date_gregorian']] = $row;
    }
    return $days;
}

function markAttendanceDay($conn, $date, $is_school_day, $reason = null, $admin_id = 0) {
    $gregorian_date = date('Y-m-d', strtotime($date));
    $is_school = $is_school_day ? 1 : 0;
    $admin_id = intval($admin_id);
    
    $eth_date = getEthiopianDateFromGregorian($gregorian_date);
    
    $stmt = mysqli_prepare($conn, "INSERT INTO attendance_days 
              (date_gregorian, ethiopian_year, ethiopian_month, ethiopian_day, day_of_week, is_school_day, reason, created_by)
              VALUES (?, ?, ?, ?, ?, ?, ?, ?)
              ON DUPLICATE KEY UPDATE
              is_school_day = VALUES(is_school_day),
              reason = VALUES(reason),
              created_by = VALUES(created_by)");
              
    if (!$stmt) return false;
    
    mysqli_stmt_bind_param(
        $stmt, 
        "siiisisi", 
        $gregorian_date, 
        $eth_date['year'], 
        $eth_date['month'], 
        $eth_date['day'], 
        $eth_date['day_of_week'], 
        $is_school, 
        $reason, 
        $admin_id
    );
    
    $res = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    return $res;
}

function getStudentAttendance($conn, $student_id, $class_id, $start_date = null, $end_date = null) {
    $student_id = intval($student_id);
    $class_id = intval($class_id);
    
    $query = "SELECT * FROM attendance_records WHERE student_id = ? AND class_id = ?";
    $params = [$student_id, $class_id];
    $types = "ii";
    
    if ($start_date) {
        $query .= " AND attendance_date >= ?";
        $params[] = $start_date;
        $types .= "s";
    }
    if ($end_date) {
        $query .= " AND attendance_date <= ?";
        $params[] = $end_date;
        $types .= "s";
    }
    $query .= " ORDER BY attendance_date DESC";
    
    $rows = dbFetchAll($conn, $query, $types, $params);
    $attendance = [];
    foreach ($rows as $row) {
        $attendance[$row['attendance_date']] = $row;
    }
    return $attendance;
}

function saveAttendance($conn, $student_id, $class_id, $teacher_id, $date, $status, $marked_by) {
    $student_id = intval($student_id);
    $class_id = intval($class_id);
    $teacher_id = intval($teacher_id);
    $marked_by = intval($marked_by);
    $date = date('Y-m-d', strtotime($date));
    $status = in_array(strtolower($status), ['present', 'absent', 'permission', 'late', 'excused']) ? strtolower($status) : 'absent';
    
    $stmt = mysqli_prepare($conn, "INSERT INTO attendance_records 
              (student_id, class_id, teacher_id, attendance_date, status, marked_by)
              VALUES (?, ?, ?, ?, ?, ?)
              ON DUPLICATE KEY UPDATE
              status = VALUES(status),
              marked_by = VALUES(marked_by),
              last_updated = CURRENT_TIMESTAMP");
              
    if (!$stmt) return false;
    
    mysqli_stmt_bind_param($stmt, "iiissi", $student_id, $class_id, $teacher_id, $date, $status, $marked_by);
    $res = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    return $res;
}

function getNextSchoolDays($conn, $limit = 10) {
    $limit = intval($limit);
    return dbFetchAll(
        $conn,
        "SELECT * FROM attendance_days 
         WHERE date_gregorian >= CURDATE() 
         AND day_of_week IN ('Saturday', 'Sunday')
         AND is_school_day = 1
         ORDER BY date_gregorian ASC 
         LIMIT ?",
        "i",
        [$limit]
    );
}

// ============================================
// SEMESTER & ACADEMIC YEAR FUNCTIONS
// ============================================

function getCurrentSemester($conn) {
    return dbFetchOne($conn, "SELECT * FROM semesters WHERE status = 'active' LIMIT 1");
}

function getCurrentAcademicYear($conn) {
    return dbFetchOne($conn, "SELECT * FROM academic_years WHERE status = 'active' LIMIT 1");
}

function getTeacherClasses($conn, $teacher_id, $semester_id) {
    $teacher_id = intval($teacher_id);
    $semester_id = intval($semester_id);
    if (!$teacher_id || !$semester_id) return [];
    
    return dbFetchAll(
        $conn,
        "SELECT tc.*, c.name as class_name, c.id as class_id,
                COALESCE(s.name, tc.subject_name, '') as assigned_subject_name,
                s.id as assigned_subject_id,
                COUNT(DISTINCT st.id) as student_count
         FROM teacher_class tc
         JOIN classes c ON tc.class_id = c.id
         LEFT JOIN subjects s ON tc.subject_id = s.id
         LEFT JOIN students st ON c.id = st.class_id AND (st.is_deleted = 0 OR st.is_deleted IS NULL)
         WHERE tc.teacher_id = ? 
         AND tc.semester_id = ?
         GROUP BY c.id, tc.id
         ORDER BY c.name",
        "ii",
        [$teacher_id, $semester_id]
    );
}

// ============================================
// UTILITY FUNCTIONS
// ============================================

function formatMark($mark) {
    if ($mark === null || $mark === '') return '0.0';
    return number_format((float)$mark, 1, '.', '');
}

function getGradeStatus($total) {
    $total = floatval($total);
    if ($total >= 85) return 'እጅግ በጣም ጥሩ';
    if ($total >= 70) return 'ጥሩ';
    if ($total >= 50) return 'አጥጋቢ';
    return 'ማሻሻያ የሚሻ';
}

function getGradeColor($total) {
    $total = floatval($total);
    if ($total >= 85) return '#10B981';
    if ($total >= 70) return '#3B82F6';
    if ($total >= 50) return '#F59E0B';
    return '#EF4444';
}

function sanitize($conn, $data) {
    return mysqli_real_escape_string($conn, trim($data));
}

/**
 * Record an administrative/security-relevant action. Fails silently if the
 * audit_log table doesn't exist yet (migration 004 not applied) so this can
 * be dropped into pages before the migration runs without breaking them.
 */
function auditLog($conn, $action, $entityType = null, $entityId = null, $details = null) {
    $userId = $_SESSION['user_id'] ?? null;
    $ip = $_SERVER['REMOTE_ADDR'] ?? null;
    @dbExecute(
        $conn,
        "INSERT INTO audit_log (user_id, action, entity_type, entity_id, details, ip_address) VALUES (?, ?, ?, ?, ?, ?)",
        "ississ",
        [$userId, $action, $entityType, $entityId, $details, $ip]
    );
}

/**
 * Create a notification, optionally targeted. $targets is an array of
 * associative arrays, each with any of: division_id, grade_id, class_id,
 * user_id. An empty $targets array means "everyone".
 */
function createNotification($conn, $title, $message, $targets = [], $priority = 'normal', $relatedEventId = null, $relatedPage = null) {
    $createdBy = $_SESSION['user_id'] ?? null;
    dbExecute(
        $conn,
        "INSERT INTO notifications (title, message, related_event_id, related_page, priority, created_by) VALUES (?, ?, ?, ?, ?, ?)",
        "ssissi",
        [$title, $message, $relatedEventId, $relatedPage, $priority, $createdBy]
    );
    $notifId = mysqli_insert_id($conn);

    // Normalize targets
    $normTargets = [];
    if (!empty($targets)) {
        if (isset($targets['users']) && is_array($targets['users'])) {
            foreach ($targets['users'] as $uid) {
                $normTargets[] = ['user_id' => intval($uid)];
            }
        } elseif (isset($targets['user_id'])) {
            $normTargets[] = ['user_id' => intval($targets['user_id'])];
        } elseif (isset($targets['class_id'])) {
            $normTargets[] = ['class_id' => intval($targets['class_id'])];
        } elseif (isset($targets['division_id'])) {
            $normTargets[] = ['division_id' => intval($targets['division_id'])];
        } else {
            foreach ($targets as $t) {
                if (is_array($t)) {
                    $normTargets[] = [
                        'division_id' => $t['division_id'] ?? null,
                        'grade_id' => $t['grade_id'] ?? null,
                        'class_id' => $t['class_id'] ?? null,
                        'user_id' => $t['user_id'] ?? null
                    ];
                }
            }
        }
    }

    foreach ($normTargets as $t) {
        dbExecute(
            $conn,
            "INSERT INTO notification_targets (notification_id, division_id, grade_id, class_id, user_id) VALUES (?, ?, ?, ?, ?)",
            "iiiii",
            [$notifId, $t['division_id'] ?? null, $t['grade_id'] ?? null, $t['class_id'] ?? null, $t['user_id'] ?? null]
        );
    }

    // Automatically trigger Web Push to ensure 100% real notification delivery
    $pushFile = __DIR__ . '/services/push/WebPushService.php';
    if (file_exists($pushFile)) {
        require_once $pushFile;
        if (class_exists('WebPushService')) {
            $pushData = [
                'title' => $title,
                'body' => $message,
                'url' => $relatedPage ?: '/exam/notifications.php'
            ];
            if (!empty($normTargets)) {
                foreach ($normTargets as $t) {
                    if (!empty($t['user_id'])) {
                        @WebPushService::sendToUser($conn, intval($t['user_id']), $pushData);
                    } else {
                        @WebPushService::sendToTarget($conn, $t, $pushData);
                    }
                }
            } else {
                @WebPushService::sendToTarget($conn, [], $pushData);
            }
        }
    }

    return $notifId;
}

/**
 * Notifications relevant to the logged-in user: untargeted (everyone) ones,
 * plus any explicitly targeted at their user id, class(es), grade, or
 * division. Simple resolution - checked against the user's current
 * assignments, not a live "your class changed division" edge case.
 */
function getNotificationsForUser($conn, $userId, $unreadOnly = false, $limit = 50) {
    $limit = (int)$limit;
    $sql = "SELECT DISTINCT n.*, (r.id IS NOT NULL) AS is_read
            FROM notifications n
            LEFT JOIN notification_reads r ON r.notification_id = n.id AND r.user_id = ?
            LEFT JOIN notification_targets t ON t.notification_id = n.id
            WHERE (
                t.id IS NULL
                OR t.user_id = ?
                OR t.class_id IN (SELECT class_id FROM teacher_class WHERE teacher_id = ?)
                OR t.class_id IN (SELECT class_id FROM attendance_assignments WHERE submitter_id = ?)
            )";
    if ($unreadOnly) $sql .= " AND r.id IS NULL";
    $sql .= " ORDER BY n.created_at DESC LIMIT $limit";
    return dbFetchAll($conn, $sql, "iiii", [$userId, $userId, $userId, $userId]);
}

function markNotificationRead($conn, $notificationId, $userId) {
    return dbExecute(
        $conn,
        "INSERT INTO notification_reads (notification_id, user_id) VALUES (?, ?) ON DUPLICATE KEY UPDATE read_at = NOW()",
        "ii",
        [$notificationId, $userId]
    );
}

function getUnreadNotificationCount($conn, $userId) {
    $rows = getNotificationsForUser($conn, $userId, true, 200);
    return count($rows);
}

/**
 * Generates or retrieves a valid 30-day offline token for IndexedDB client storage
 */
function getOrCreateOfflineToken($conn, $userId, $role) {
    $userId = intval($userId);
    if (!$userId) return null;

    $existing = dbFetchOne(
        $conn,
        "SELECT token FROM auth_tokens WHERE user_id = ? AND role = ? AND revoked = 0 AND expires_at > NOW() ORDER BY expires_at DESC LIMIT 1",
        "is",
        [$userId, $role]
    );
    if ($existing && !empty($existing['token'])) {
        return $existing['token'];
    }

    $token = bin2hex(random_bytes(32));
    $expiresAt = date('Y-m-d H:i:s', strtotime('+30 days'));
    $ok = dbExecute(
        $conn,
        "INSERT INTO auth_tokens (user_id, token, role, expires_at) VALUES (?, ?, ?, ?)",
        "isss",
        [$userId, $token, $role, $expiresAt]
    );
    return $ok ? $token : null;
}

/**
 * Get all subjects
 */
function getSubjects($conn = null) {
    if (!$conn) $conn = $GLOBALS['conn'];
    return dbFetchAll($conn, "SELECT * FROM subjects ORDER BY sort_order, name");
}

/**
 * Check if a teacher can create/edit lesson plans
 * Children teachers (Grade 1-6) can create plans.
 * Youth teachers (Grade 7-12) can only create plans if enabled in admin settings or overridden.
 */
function teacherCanCreatePlan($conn, $teacherId, $semesterId = null) {
    $teacherId = intval($teacherId);
    if (!$teacherId) return false;
    
    // Check if teacher teaches any Children class (grades 1-6, division_id = 1)
    $hasChildren = dbFetchOne(
        $conn,
        "SELECT COUNT(*) as cnt FROM teacher_class tc
         JOIN classes c ON tc.class_id = c.id
         JOIN grades g ON c.grade_id = g.id
         WHERE tc.teacher_id = ? AND g.division_id = 1",
        "i",
        [$teacherId]
    );
    if ($hasChildren && intval($hasChildren['cnt']) > 0) {
        return true;
    }
    
    // If only youth classes: check global setting
    $setting = dbFetchOne($conn, "SELECT setting_value FROM settings WHERE setting_key = 'youth_can_create_plans'");
    $youthAllowed = ($setting && trim($setting['setting_value']) === '1');
    if ($youthAllowed) return true;
    
    // Per-teacher check override
    $override = dbFetchOne($conn, "SELECT setting_value FROM settings WHERE setting_key = CONCAT('teacher_can_plan_', ?)", "i", [$teacherId]);
    if ($override && trim($override['setting_value']) === '1') {
        return true;
    }
    
    return false;
}

/**
 * Check if a teacher can take/write attendance
 * Children teachers (Grade 1-6) can write attendance.
 * Youth teachers only view submitted attendance unless enabled in admin settings.
 */
function teacherCanWriteAttendance($conn, $teacherId, $semesterId = null) {
    $teacherId = intval($teacherId);
    if (!$teacherId) return false;
    
    if (isAdmin() || isAttendanceSubmitter()) return true;
    
    if ($semesterId) {
        $assigned = dbFetchOne(
            $conn,
            "SELECT COUNT(*) as cnt FROM attendance_assignments WHERE submitter_id = ? AND semester_id = ?",
            "ii",
            [$teacherId, $semesterId]
        );
        if ($assigned && intval($assigned['cnt']) > 0) return true;
    }
    
    $tcSql = "SELECT COUNT(*) as cnt FROM teacher_class tc
              JOIN classes c ON tc.class_id = c.id
              LEFT JOIN grades g ON c.grade_id = g.id
              WHERE tc.teacher_id = ? AND (g.division_id = 1 OR (g.level_number > 0 AND g.level_number <= 6))";
    $params = [$teacherId];
    $types = "i";
    if ($semesterId) {
        $tcSql .= " AND tc.semester_id = ?";
        $params[] = $semesterId;
        $types .= "i";
    }
    $hasChildren = dbFetchOne($conn, $tcSql, $types, $params);
    if ($hasChildren && intval($hasChildren['cnt']) > 0) {
        return true;
    }
    
    $setting = dbFetchOne($conn, "SELECT setting_value FROM settings WHERE setting_key = 'youth_can_write_attendance'");
    return ($setting && trim($setting['setting_value']) === '1');
}

/**
 * Seed official 2019 Academic Calendar from PDF
 */
function seedOfficialCalendar2019($conn = null) {
    if (!$conn) $conn = $GLOBALS['conn'];
    $events = [
        [
            'title' => 'የተማሪዎች ምዝገባ ጊዜ',
            'desc' => 'የምዝገባ ጊዜ ከጳጉሜ 1/2018 ዓ.ም - ጥቅምት 1/2019 ዓ.ም ብቻ ይሆናል፡፡',
            'type' => 'announcement', 'priority' => 'normal',
            'ey' => 2019, 'em' => 1, 'ed' => 1
        ],
        [
            'title' => 'የትምህርት መጀመሪያ ቀን እና አጠቃላይ ገለጻ',
            'desc' => 'ለተመዘገቡ ተማሪዎች አጠቃላይ የትምህርት ካላንደርን እና የትምህርቱን ሥርዓት በተመለከተ ገለጻ የሚሰጥበት እና ትምህርት የሚጀመርበት ቀን፡፡',
            'type' => 'teaching', 'priority' => 'normal',
            'ey' => 2019, 'em' => 1, 'ed' => 24
        ],
        [
            'title' => 'የመጀመሪያ የክፍል ምዘና (Mid Exam - 20%)',
            'desc' => 'የመጀመሪያ የክፍል ምዘና ከ20% ህዳር 26-27/2019 ዓ.ም (ሳምንት ፬ ቅዳሜ እና እሁድ)፡፡',
            'type' => 'exam', 'priority' => 'high',
            'ey' => 2019, 'em' => 3, 'ed' => 26
        ],
        [
            'title' => 'የ1ኛ መንፈቀ ዓመት የትምህርት ማጠናቀቂያ ቀን',
            'desc' => 'የመጀመሪያ መንፈቅ ዓመት የትምህርት ማጠናቀቂያ ጊዜ የካቲት 13-14/2019 ዓ.ም ይሆናል፡፡',
            'type' => 'important', 'priority' => 'high',
            'ey' => 2019, 'em' => 6, 'ed' => 13
        ],
        [
            'title' => 'የ1ኛ መንፈቀ ዓመት ማጠቃለያ ምዘና (Final Exam - 30%)',
            'desc' => 'የመጀመሪያ መንፈቅ ዓመት ማጠቃለያ ምዘና ከ30% የሚሰጥበት ጊዜ የካቲት 20-21/2019 ዓ.ም ይሆናል፡፡',
            'type' => 'exam', 'priority' => 'high',
            'ey' => 2019, 'em' => 6, 'ed' => 20
        ],
        [
            'title' => 'የፈተና ወረቀት እና ከ100% ውጤት ማሳወቂያ ቀን',
            'desc' => 'ለተማሪዎች የፈተና ወረቀት እና ከ100% ውጤት የሚሰጥበት ቀን የካቲት 27-28/2019 ዓ.ም፡፡',
            'type' => 'assessment', 'priority' => 'high',
            'ey' => 2019, 'em' => 6, 'ed' => 27
        ],
        [
            'title' => 'የውጤት ማስተላለፊያ ወረቀት ማስረከቢያ የመጨረሻ ቀን',
            'desc' => 'መምህራን የመጀመሪያ መንፈቅ ዓመት ከ100% ውጤት ለተማሪዎች አሳይተው አጠናቀው በውጤት ማስተላለፊያ ወረቀት እስከ መጋቢት 4 እና 5/2019 ዓ.ም ገቢ ማድረግ ይጠበቅባቸዋል፡፡',
            'type' => 'important', 'priority' => 'high',
            'ey' => 2019, 'em' => 7, 'ed' => 4
        ]
    ];
    
    $inserted = 0;
    foreach ($events as $ev) {
        $chk = dbFetchOne($conn, "SELECT id FROM calendar_events WHERE title = ? AND ethiopian_year = ? AND is_deleted = 0", "si", [$ev['title'], $ev['ey']]);
        if (!$chk) {
            $gdate = ethiopianToGregorian($ev['ey'], $ev['em'], $ev['ed']);
            dbExecute(
                $conn,
                "INSERT INTO calendar_events (title, description, event_type, event_date, ethiopian_year, ethiopian_month, ethiopian_day, priority, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1)",
                "ssssiiis",
                [$ev['title'], $ev['desc'], $ev['type'], $gdate, $ev['ey'], $ev['em'], $ev['ed'], $ev['priority']]
            );
            $inserted++;
        }
    }
    return $inserted;
}

// ============================================
// PER-ACCOUNT DARK MODE FUNCTIONS (ETHIOPIAN DAY/NIGHT AWARE)
// ============================================

function getEthiopianCurrentHour() {
    $dt = new DateTime('now', new DateTimeZone('Africa/Addis_Ababa'));
    return (int)$dt->format('H');
}

function isEthiopianNightTime() {
    $hour = getEthiopianCurrentHour();
    // Night is from 18:00 (6:00 PM) to 06:00 (6:00 AM) Ethiopian time
    return ($hour >= 18 || $hour < 6);
}

function getUserDarkMode($conn, $userId, $isStudent = false) {
    $userId = intval($userId);
    if (!$userId) return 0;
    if ($isStudent) {
        $row = dbFetchOne($conn, "SELECT dark_mode FROM student_logins WHERE student_id = ?", "i", [$userId]);
    } else {
        $row = dbFetchOne($conn, "SELECT dark_mode FROM users WHERE id = ?", "i", [$userId]);
    }
    return intval($row['dark_mode'] ?? 0);
}

function resolveUserDarkMode($conn, $userId, $isStudent = false) {
    $raw = getUserDarkMode($conn, $userId, $isStudent);
    // 1 = Manual Dark
    if ($raw === 1) return 1;
    // 2 = Manual Light
    if ($raw === 2) return 0;
    // 0 = Auto by Ethiopian Time (Night = 1, Day = 0)
    return isEthiopianNightTime() ? 1 : 0;
}

function setUserDarkMode($conn, $userId, $mode, $isStudent = false) {
    $userId = intval($userId);
    if (!$userId) return false;
    $val = intval($mode); // 0 = Auto, 1 = Dark, 2 = Light
    if ($isStudent) {
        dbExecute($conn, "UPDATE student_logins SET dark_mode = ? WHERE student_id = ?", "ii", [$val, $userId]);
    } else {
        dbExecute($conn, "UPDATE users SET dark_mode = ? WHERE id = ?", "ii", [$val, $userId]);
    }
    $_SESSION['dark_mode_preference'] = $val;
    $_SESSION['dark_mode'] = $val;
    return true;
}
?>