<?php
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');  
define('DB_NAME', 'atsede_sunday_school'); 

// Create connection
$conn = mysqli_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);

if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

mysqli_set_charset($conn, "utf8mb4");
// Start session if not started
if (session_status() == PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'domain' => '',
        'secure' => false,
        'httponly' => true,
        'samesite' => 'Strict'
    ]);
    session_start();
}


// ============================================
// PASSWORD FUNCTIONS
// ============================================


function hashPassword($password) {
    return password_hash($password, PASSWORD_DEFAULT);
}

function verifyPassword($password, $hash) {
    return password_verify($password, $hash);
}

// ============================================
// AUTHENTICATION FUNCTIONS
// ============================================

function isLoggedIn() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

function isAdmin() {
    return (isset($_SESSION['role']) && $_SESSION['role'] == 'admin');
}

function isTeacher() {
    return (isset($_SESSION['role']) && $_SESSION['role'] == 'teacher');
}

function isAttendanceSubmitter() {
    return (isset($_SESSION['role']) && $_SESSION['role'] == 'attendance_submitter');
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
    if (!isTeacher()) {
        header("Location: dashboard_admin.php");
        exit();
    }
}

function requireAttendanceSubmitter() {
    requireLogin();
    if (!isAttendanceSubmitter()) {
        header("Location: index.php");
        exit();
    }
}

// ============================================
// MARKING SCHEME FUNCTIONS
// ============================================

function getMarkingScheme($conn, $teacher_id, $class_id, $semester_id) {
    $query = "SELECT * FROM marking_schemes 
              WHERE teacher_id = $teacher_id 
              AND class_id = $class_id 
              AND semester_id = $semester_id";
    $result = mysqli_query($conn, $query);
    if($result && mysqli_num_rows($result) > 0) {
        return mysqli_fetch_assoc($result);
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
    $c1_name = mysqli_real_escape_string($conn, $data['c1_name']);
    $c1_perc = floatval($data['c1_perc']);
    $c2_name = mysqli_real_escape_string($conn, $data['c2_name']);
    $c2_perc = floatval($data['c2_perc']);
    $c3_name = mysqli_real_escape_string($conn, $data['c3_name']);
    $c3_perc = floatval($data['c3_perc']);
    $c4_name = mysqli_real_escape_string($conn, $data['c4_name']);
    $c4_perc = floatval($data['c4_perc']);
    $c5_name = mysqli_real_escape_string($conn, $data['c5_name']);
    $c5_perc = floatval($data['c5_perc']);
    
    $total = $c1_perc + $c2_perc + $c3_perc + $c4_perc + $c5_perc;
    if(abs($total - 100) > 0.01) {
        return ['success' => false, 'message' => "Total must be 100% (Current: {$total}%)"];
    }
    
    $query = "INSERT INTO marking_schemes 
              (teacher_id, class_id, semester_id, 
               component1_name, component1_percentage,
               component2_name, component2_percentage,
               component3_name, component3_percentage,
               component4_name, component4_percentage,
               component5_name, component5_percentage)
              VALUES ($teacher_id, $class_id, $semester_id,
                      '$c1_name', $c1_perc,
                      '$c2_name', $c2_perc,
                      '$c3_name', $c3_perc,
                      '$c4_name', $c4_perc,
                      '$c5_name', $c5_perc)
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
              component5_percentage = VALUES(component5_percentage)";
    
    if(mysqli_query($conn, $query)) {
        return ['success' => true, 'message' => 'Marking scheme saved!'];
    }
    return ['success' => false, 'message' => 'Error: ' . mysqli_error($conn)];
}

// ============================================
// ETHIOPIAN CALENDAR FUNCTIONS (Complete)
// ============================================

function gregorianToEthiopian($gregorianDate) {
    $date = new DateTime($gregorianDate);
    $gregYear = (int)$date->format('Y');
    $gregMonth = (int)$date->format('n');
    $gregDay = (int)$date->format('j');
    
    // Ethiopian year is approximately 7-8 years behind Gregorian
    $ethYear = $gregYear - 7;
    
    // If date is before Ethiopian New Year (September 11 or 12), subtract 1 year
    if ($gregMonth < 9 || ($gregMonth == 9 && $gregDay < 11)) {
        $ethYear--;
    }
    
    // Determine Ethiopian New Year in Gregorian
    $ethNewYear = new DateTime("$gregYear-09-11");
    // Leap year: September 12
    if ($gregYear % 4 == 3) {
        $ethNewYear = new DateTime("$gregYear-09-12");
    }
    
    $daysDiff = $date->diff($ethNewYear)->days;
    
    // If date is before September 11/12, use previous year's new year
    if ($date < $ethNewYear) {
        $prevYear = $gregYear - 1;
        $ethNewYear = new DateTime($prevYear . "-09-11");
        if ($prevYear % 4 == 3) {
            $ethNewYear = new DateTime($prevYear . "-09-12");
        }
        $daysDiff = $date->diff($ethNewYear)->days;
    }
    
    $ethMonth = floor($daysDiff / 30) + 1;
    $ethDay = ($daysDiff % 30) + 1;
    
    if ($ethMonth > 13) {
        $ethMonth = 13;
        $ethDay = min($ethDay, 6);
    }
    
    $ethiopianMonths = [
        1 => 'መስከረም', 2 => 'ጥቅምት', 3 => 'ኅዳር', 4 => 'ታኅሣሥ',
        5 => 'ጥር', 6 => 'የካቲት', 7 => 'መጋቢት', 8 => 'ሚያዝያ',
        9 => 'ግንቦት', 10 => 'ሰኔ', 11 => 'ሐምሌ', 12 => 'ነሐሴ', 13 => 'ጳጉሜን'
    ];
    
    $dayOfWeek = $date->format('l');
    
    return [
        'year' => $ethYear,
        'month' => $ethMonth,
        'day' => $ethDay,
        'month_name' => $ethiopianMonths[$ethMonth] ?? 'ያልታወቀ',
        'day_of_week' => $dayOfWeek,
        'full' => $ethiopianMonths[$ethMonth] . ' ' . $ethDay . ' ቀን ' . $ethYear . ' ዓ.ም'
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
    list($hour, $minute) = explode(':', $time);
    $hour = intval($hour);
    $ethiopian_hour = $hour % 12;
    if ($ethiopian_hour == 0) $ethiopian_hour = 12;
    $period = ($hour < 12) ? 'ጠዋት' : 'ማታ';
    return $ethiopian_hour . ':' . $minute . ' ' . $period;
}

function getEthiopianDaysInMonth($ethiopianYear, $ethiopianMonth) {
    if ($ethiopianMonth == 13) {
        // Pagume: 6 days in leap year, 5 days in regular year
        return ($ethiopianYear % 4 == 3) ? 6 : 5;
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
    $query = "SELECT * FROM attendance_days 
              WHERE ethiopian_year = $ethiopian_year 
              AND ethiopian_month = $ethiopian_month
              ORDER BY date_gregorian";
    $result = mysqli_query($conn, $query);
    $days = [];
    while($row = mysqli_fetch_assoc($result)) {
        $days[$row['date_gregorian']] = $row;
    }
    return $days;
}

function markAttendanceDay($conn, $date, $is_school_day, $reason = null, $admin_id) {
    $gregorian_date = mysqli_real_escape_string($conn, $date);
    $is_school = $is_school_day ? 1 : 0;
    $reason_escaped = $reason ? mysqli_real_escape_string($conn, $reason) : null;
    
    // Get Ethiopian date info
    $eth_date = getEthiopianDateFromGregorian($gregorian_date);
    
    $query = "INSERT INTO attendance_days 
              (date_gregorian, ethiopian_year, ethiopian_month, ethiopian_day, day_of_week, is_school_day, reason, created_by)
              VALUES ('$gregorian_date', {$eth_date['year']}, {$eth_date['month']}, {$eth_date['day']}, '{$eth_date['day_of_week']}', $is_school, '$reason_escaped', $admin_id)
              ON DUPLICATE KEY UPDATE
              is_school_day = $is_school,
              reason = '$reason_escaped',
              created_by = $admin_id";
    
    return mysqli_query($conn, $query);
}

function getStudentAttendance($conn, $student_id, $class_id, $start_date = null, $end_date = null) {
    $query = "SELECT * FROM attendance_records 
              WHERE student_id = $student_id AND class_id = $class_id";
    if($start_date) {
        $query .= " AND attendance_date >= '$start_date'";
    }
    if($end_date) {
        $query .= " AND attendance_date <= '$end_date'";
    }
    $query .= " ORDER BY attendance_date DESC";
    
    $result = mysqli_query($conn, $query);
    $attendance = [];
    while($row = mysqli_fetch_assoc($result)) {
        $attendance[$row['attendance_date']] = $row;
    }
    return $attendance;
}

function saveAttendance($conn, $student_id, $class_id, $teacher_id, $date, $status, $marked_by) {
    $date_escaped = mysqli_real_escape_string($conn, $date);
    $status_escaped = mysqli_real_escape_string($conn, $status);
    
    $query = "INSERT INTO attendance_records 
              (student_id, class_id, teacher_id, attendance_date, status, marked_by)
              VALUES ($student_id, $class_id, $teacher_id, '$date_escaped', '$status_escaped', $marked_by)
              ON DUPLICATE KEY UPDATE
              status = '$status_escaped',
              marked_by = $marked_by,
              last_updated = CURRENT_TIMESTAMP";
    
    return mysqli_query($conn, $query);
}

function getNextSchoolDays($conn, $limit = 10) {
    $query = "SELECT * FROM attendance_days 
              WHERE date_gregorian >= CURDATE() 
              AND day_of_week IN ('Saturday', 'Sunday')
              AND is_school_day = 1
              ORDER BY date_gregorian ASC 
              LIMIT $limit";
    $result = mysqli_query($conn, $query);
    $days = [];
    while($row = mysqli_fetch_assoc($result)) {
        $days[] = $row;
    }
    return $days;
}

// ============================================
// SEMESTER FUNCTIONS
// ============================================

function getCurrentSemester($conn) {
    $query = "SELECT * FROM semesters WHERE status = 'active' LIMIT 1";
    $result = mysqli_query($conn, $query);
    if($result && mysqli_num_rows($result) > 0) {
        return mysqli_fetch_assoc($result);
    }
    return null;
}

function getTeacherClasses($conn, $teacher_id, $semester_id) {
    if(!$teacher_id || !$semester_id) return [];
    
    $query = "SELECT tc.*, c.name as class_name, c.id as class_id,
              COUNT(DISTINCT s.id) as student_count
              FROM teacher_class tc
              JOIN classes c ON tc.class_id = c.id
              LEFT JOIN students s ON c.id = s.class_id
              WHERE tc.teacher_id = $teacher_id 
              AND tc.semester_id = $semester_id
              GROUP BY c.id
              ORDER BY c.name";
    $result = mysqli_query($conn, $query);
    
    $classes = [];
    if($result && mysqli_num_rows($result) > 0) {
        while($row = mysqli_fetch_assoc($result)) {
            $classes[] = $row;
        }
    }
    return $classes;
}

// ============================================
// UTILITY FUNCTIONS
// ============================================

function formatMark($mark) {
    if($mark === null || $mark === '') return '0.0';
    return number_format((float)$mark, 1, '.', '');
}

function getGradeStatus($total) {
    if($total >= 85) return 'እጅግ በጣም ጥሩ (Excellent)';
    if($total >= 70) return 'ጥሩ (Good)';
    if($total >= 50) return 'አጥጋቢ (Satisfactory)';
    return 'ደካማ (Needs Improvement)';
}

function getGradeColor($total) {
    if($total >= 85) return '#10B981';
    if($total >= 70) return '#3B82F6';
    if($total >= 50) return '#F59E0B';
    return '#EF4444';
}

function sanitize($conn, $data) {
    return mysqli_real_escape_string($conn, trim($data));
}
?>