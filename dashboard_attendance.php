<?php
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("Expires: Sat, 26 Jul 1997 05:00:00 GMT");

require_once 'db.php';
requireLogin();

// Check if first login - force password change for attendance submitter
if (isset($_SESSION['first_login']) && $_SESSION['first_login'] == 1) {
    header("Location: change_password.php");
    exit();
}

$current_semester = getCurrentSemester($conn);
$semester_id = $current_semester ? intval($current_semester['id']) : 0;
$submitter_id = intval($_SESSION['user_id'] ?? 0);

if (!canMarkAttendance($conn, $submitter_id, $semester_id)) {
    if (isTeacher()) {
        header("Location: teacher_attendance_view.php");
        exit();
    }
    header("Location: index.php");
    exit();
}

$user_name = $_SESSION['user_name'] ?? 'የክፍል ጸሐፊ';

// Get admin contact
$admin1_row = dbFetchOne($conn, "SELECT setting_value FROM settings WHERE setting_key = 'admin_name_1'");
$admin1 = $admin1_row ? $admin1_row['setting_value'] : 'አስተዳዳሪ';
$phone1_row = dbFetchOne($conn, "SELECT setting_value FROM settings WHERE setting_key = 'admin_phone_1'");
$phone1 = $phone1_row ? $phone1_row['setting_value'] : '';

// Get assigned classes
$assigned_classes = [];
if ($submitter_id && $semester_id) {
    if (isAdmin()) {
        $classes_query = "SELECT c.id as class_id, c.name as class_name,
                          COUNT(DISTINCT s.id) as student_count
                          FROM classes c
                          LEFT JOIN students s ON c.id = s.class_id AND (s.is_deleted = 0 OR s.is_deleted IS NULL)
                          GROUP BY c.id ORDER BY c.name";
        $assigned_classes = dbFetchAll($conn, $classes_query);
    } elseif (isTeacher()) {
        $setting = dbFetchOne($conn, "SELECT setting_value FROM settings WHERE setting_key = 'youth_can_write_attendance'");
        $youth_enabled = ($setting && trim($setting['setting_value']) === '1');
        $youth_filter = $youth_enabled ? "1 = 1" : "(g.division_id = 1 OR (g.level_number > 0 AND g.level_number <= 6))";

        $classes_query = "
            SELECT c.id as class_id, c.name as class_name,
                   COUNT(DISTINCT s.id) as student_count
            FROM (
                SELECT tc.class_id
                FROM teacher_class tc
                JOIN classes c ON tc.class_id = c.id
                LEFT JOIN grades g ON c.grade_id = g.id
                WHERE tc.teacher_id = ? AND tc.semester_id = ? AND $youth_filter
                UNION
                SELECT aa.class_id
                FROM attendance_assignments aa
                WHERE aa.submitter_id = ? AND aa.semester_id = ?
            ) u_classes
            JOIN classes c ON u_classes.class_id = c.id
            LEFT JOIN students s ON c.id = s.class_id AND (s.is_deleted = 0 OR s.is_deleted IS NULL)
            GROUP BY c.id ORDER BY c.name";
        $assigned_classes = dbFetchAll($conn, $classes_query, "iiii", [$submitter_id, $semester_id, $submitter_id, $semester_id]);
    } else {
        $classes_query = "SELECT aa.*, c.name as class_name, c.id as class_id,
                          COUNT(DISTINCT s.id) as student_count
                          FROM attendance_assignments aa
                          JOIN classes c ON aa.class_id = c.id
                          LEFT JOIN students s ON c.id = s.class_id AND (s.is_deleted = 0 OR s.is_deleted IS NULL)
                          WHERE aa.submitter_id = ? AND aa.semester_id = ?
                          GROUP BY c.id ORDER BY c.name";
        $assigned_classes = dbFetchAll($conn, $classes_query, "ii", [$submitter_id, $semester_id]);
    }
}

$error_message = '';
if (empty($assigned_classes)) {
    $error_message = "ለዚህ ሴሚስተር ምንም ክፍል አልተመደበልዎትም!";
}

// Ethiopian months
$ethiopian_months = [
    1 => 'መስከረም', 2 => 'ጥቅምት', 3 => 'ኅዳር', 4 => 'ታኅሣሥ',
    5 => 'ጥር', 6 => 'የካቲት', 7 => 'መጋቢት', 8 => 'ሚያዝያ',
    9 => 'ግንቦት', 10 => 'ሰኔ', 11 => 'ሐምሌ', 12 => 'ነሐሴ', 13 => 'ጳጉሜን'
];

$amharic_days = [
    'Monday' => 'ሰኞ', 'Tuesday' => 'ማክሰኞ', 'Wednesday' => 'ረቡዕ',
    'Thursday' => 'ሐሙስ', 'Friday' => 'አርብ', 'Saturday' => 'ቅዳሜ', 'Sunday' => 'እሁድ'
];

// Get current Ethiopian date
$today_eth = getCurrentEthiopianDate();
$selected_eth_month = isset($_GET['m']) ? intval($_GET['m']) : intval($today_eth['month']);
$selected_eth_year = isset($_GET['y']) ? intval($_GET['y']) : intval($today_eth['year']);

// Validate
if ($selected_eth_month < 1 || $selected_eth_month > 13) {
    $selected_eth_month = intval($today_eth['month']);
}
if ($selected_eth_year < 2000 || $selected_eth_year > 2100) {
    $selected_eth_year = intval($today_eth['year']);
}

// Build Ethiopian month calendar - ONLY SATURDAY & SUNDAY
$days_in_eth_month = getEthiopianDaysInMonth($selected_eth_year, $selected_eth_month);
$eth_month_days = [];

for ($d = 1; $d <= $days_in_eth_month; $d++) {
    $date_str = ethiopianToGregorian($selected_eth_year, $selected_eth_month, $d);
    if (!$date_str) continue;
    
    $day_of_week = date('l', strtotime($date_str));
    if ($day_of_week !== 'Saturday' && $day_of_week !== 'Sunday') {
        continue;
    }
    
    $is_future = ($date_str > date('Y-m-d'));
    $is_today = ($date_str === date('Y-m-d'));
    $clickable = (!$is_future);
    
    $eth_month_days[] = [
        'eth_day' => $d,
        'greg_date' => $date_str,
        'day_name' => $day_of_week,
        'day_am' => $amharic_days[$day_of_week] ?? substr($day_of_week, 0, 3),
        'is_weekend' => true,
        'is_future' => $is_future,
        'is_today' => $is_today,
        'clickable' => $clickable
    ];
}

// Get selected class
$selected_class_id = isset($_GET['c']) ? intval($_GET['c']) : (!empty($assigned_classes) ? intval($assigned_classes[0]['class_id']) : 0);
$selected_class = null;
$students = [];
$teachers = [];

// Get closed days
$closed_days = [];
if ($selected_class_id > 0 && !empty($eth_month_days)) {
    $first_date = $eth_month_days[0]['greg_date'];
    $last_date = $eth_month_days[count($eth_month_days)-1]['greg_date'];
    
    $cd_rows = dbFetchAll(
        $conn,
        "SELECT date_gregorian FROM attendance_days WHERE date_gregorian BETWEEN ? AND ? AND is_school_day = 0 AND (class_id IS NULL OR class_id = ?)",
        "ssi",
        [$first_date, $last_date, $selected_class_id]
    );
    foreach ($cd_rows as $row) {
        $closed_days[$row['date_gregorian']] = true;
    }
}

foreach ($eth_month_days as &$day) {
    $day['is_closed'] = isset($closed_days[$day['greg_date']]);
    if ($day['is_closed']) {
        $day['clickable'] = false;
    }
}
unset($day);

if ($selected_class_id > 0) {
    foreach ($assigned_classes as $class) {
        if (intval($class['class_id']) === $selected_class_id) {
            $selected_class = $class;
            break;
        }
    }
    if ($selected_class) {
        $students = dbFetchAll(
            $conn,
            "SELECT * FROM students WHERE class_id = ? AND (is_deleted = 0 OR is_deleted IS NULL) ORDER BY name",
            "i",
            [$selected_class_id]
        );
        
        $teachers = dbFetchAll(
            $conn,
            "SELECT u.id, u.name FROM teacher_class tc
             JOIN users u ON tc.teacher_id = u.id
             WHERE tc.class_id = ? AND tc.semester_id = ?",
            "ii",
            [$selected_class_id, $semester_id]
        );
    }
}

// Get existing attendance
$attendance_data = [];
if (!empty($students) && !empty($teachers) && !empty($eth_month_days)) {
    $first_date = $eth_month_days[0]['greg_date'];
    $last_date = $eth_month_days[count($eth_month_days)-1]['greg_date'];
    
    $records = dbFetchAll(
        $conn,
        "SELECT student_id, attendance_date, status FROM attendance_records 
         WHERE class_id = ? AND attendance_date BETWEEN ? AND ?",
        "iss",
        [$selected_class_id, $first_date, $last_date]
    );
    foreach ($records as $r) {
        $attendance_data[$r['student_id']][$r['attendance_date']] = $r['status'];
    }
}

// AJAX save attendance
if (isset($_POST['ajax_save_attendance'])) {
    header('Content-Type: application/json');
    $student_id = intval($_POST['student_id'] ?? 0);
    $class_id = intval($_POST['class_id'] ?? 0);
    $date = trim($_POST['attendance_date'] ?? '');
    $status = trim($_POST['status'] ?? '');
    $marked_by = intval($_SESSION['user_id'] ?? 0);

    // IDOR Protection: verify user is assigned to mark attendance for this class
    if (!isAdmin()) {
        if (!canTeacherMarkClassAttendance($conn, $marked_by, $class_id, $semester_id)) {
            echo json_encode(['success' => false, 'message' => 'unauthorized_class']);
            exit();
        }
    }

    // IDOR Protection: verify student belongs to this class
    $student_check = dbFetchOne(
        $conn,
        "SELECT id FROM students WHERE id = ? AND class_id = ? AND (is_deleted = 0 OR is_deleted IS NULL)",
        "ii",
        [$student_id, $class_id]
    );
    if (!$student_check) {
        echo json_encode(['success' => false, 'message' => 'invalid_student']);
        exit();
    }

    // Validate status
    if (!in_array($status, ['present', 'absent', 'permission'])) {
        echo json_encode(['success' => false, 'message' => 'invalid_status']);
        exit();
    }
    
    // Check if semester is closed or attendance_locked for the class
    if (!isAdmin()) {
        $sem_check = dbFetchOne($conn, "SELECT status FROM semesters WHERE id = ?", "i", [$semester_id]);
        if (!$sem_check || $sem_check['status'] === 'closed') {
            echo json_encode(['success' => false, 'message' => 'semester_closed']);
            exit();
        }

        $lock_check = dbFetchOne(
            $conn,
            "SELECT attendance_locked FROM teacher_class WHERE class_id = ? AND semester_id = ? LIMIT 1",
            "ii",
            [$class_id, $semester_id]
        );
        if ($lock_check && intval($lock_check['attendance_locked']) === 1) {
            echo json_encode(['success' => false, 'message' => 'locked']);
            exit();
        }
    }
    
    $day_check = dbFetchOne(
        $conn,
        "SELECT id FROM attendance_days WHERE date_gregorian = ? AND is_school_day = 0 AND (class_id IS NULL OR class_id = ?) LIMIT 1",
        "si",
        [$date, $class_id]
    );
    if ($day_check) {
        echo json_encode(['success' => false, 'message' => 'closed']);
        exit();
    }
    
    if (strtotime($date) > strtotime(date('Y-m-d'))) {
        echo json_encode(['success' => false, 'message' => 'future']);
        exit();
    }
    
    $success = true;
    if (empty($teachers)) {
        $teachers = dbFetchAll(
            $conn,
            "SELECT u.id, u.name FROM teacher_class tc
             JOIN users u ON tc.teacher_id = u.id
             WHERE tc.class_id = ? AND tc.semester_id = ?",
            "ii",
            [$class_id, $semester_id]
        );
    }
    
    if (empty($teachers)) {
        // Direct save with teacher_id = 0 if no teacher assigned
        if (!saveAttendance($conn, $student_id, $class_id, 0, $date, $status, $marked_by)) {
            $success = false;
        }
    } else {
        foreach ($teachers as $teacher) {
            if (!saveAttendance($conn, $student_id, $class_id, intval($teacher['id']), $date, $status, $marked_by)) {
                $success = false;
            }
        }
    }
    
    echo json_encode(['success' => $success, 'status' => $status]);
    exit();
}

// FIXED: Navigation with short parameter names
$prev_m = $selected_eth_month - 1;
$prev_y = $selected_eth_year;
if($prev_m < 1) { $prev_m = 13; $prev_y--; }

$next_m = $selected_eth_month + 1;
$next_y = $selected_eth_year;
if($next_m > 13) { $next_m = 1; $next_y++; }

$today_month = $today_eth['month'];
$today_year = $today_eth['year'];

// Build query string helper
function buildUrl($m, $y, $c) {
    return "?m={$m}&y={$y}&c={$c}";
}
$nav_active = 'dashboard_attendance';
?>
<!DOCTYPE html>
<html lang="am">
<head>
    <meta charset="UTF-8">
    <meta name="theme-color" content="#8B4513">
    <title>የአቴንዳንስ ምዝገባ | አጸደ ትጉሃን</title>
    <?php include 'pwa_head.php'; ?>
    <style>
        :root {
            --primary: #8B4513; --gold: #FFD700; --gold-dark: #DAA520;
            --pale: #FFF8DC; --success: #10B981; --danger: #EF4444;
            --warning: #F59E0B; --bg: #FAF9F6; --white: #FFFFFF;
            --gray-100: #F3F4F6; --closed-gray: #9CA3AF;
        }
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Segoe UI', 'Nyala', sans-serif; }
        body { background: var(--bg); min-height: 100vh; padding-bottom: 20px; }
        
        .header {
            background: linear-gradient(135deg, #6B3410, #8B4513);
            color: white; padding: 12px 16px; position: sticky; top: 0; z-index: 100;
            box-shadow: 0 2px 10px rgba(0,0,0,0.2);
        }
        .header-top { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 8px; }
        .logo { display: flex; align-items: center; gap: 10px; }
        .logo-img { width: 42px; height: 42px; border-radius: 50%; object-fit: cover; border: 2px solid var(--gold); }
        .logo h2 { font-size: 16px; color: var(--gold); }
        .logo span { font-size: 10px; opacity: 0.8; display: block; }
        .user-badge {
            background: rgba(255,255,255,0.15); padding: 6px 14px; border-radius: 20px;
            border: 1px solid var(--gold); font-size: 12px; display: flex; align-items: center; gap: 8px;
        }
        .user-badge strong { color: var(--gold); }
        .btn-logout { color: white; text-decoration: none; background: rgba(239,68,68,0.4); padding: 6px 12px; border-radius: 20px; font-weight: 600; font-size: 12px; }

        .container { padding: 10px; max-width: 100%; }
        .alert { padding: 12px 15px; border-radius: 10px; margin-bottom: 10px; font-size: 13px; display: flex; align-items: center; gap: 8px; }
        .alert-error { background: #FEE2E2; color: #991B1B; }

        .class-bar {
            background: var(--white); border-radius: 12px; padding: 12px; margin-bottom: 10px;
            border: 2px solid var(--gold); display: flex; gap: 8px; overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }
        .class-bar::-webkit-scrollbar { display: none; }
        .class-chip {
            padding: 10px 18px; border-radius: 25px; font-size: 13px; font-weight: 600;
            text-decoration: none; white-space: nowrap; border: 2px solid var(--gold-dark);
            color: var(--primary); background: white; transition: all 0.2s;
        }
        .class-chip.active { background: var(--gold); border-color: var(--primary); font-weight: 700; }
        .class-chip .count { background: var(--primary); color: white; padding: 2px 8px; border-radius: 15px; margin-left: 5px; font-size: 10px; }

        .month-card {
            background: var(--white); border-radius: 16px; padding: 15px; margin-bottom: 10px;
            border: 2px solid var(--gold); box-shadow: 0 4px 15px rgba(139,69,19,0.08);
        }
        .month-nav { display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; gap: 10px; }
        .month-nav a {
            padding: 10px 16px; background: var(--gold); color: var(--primary);
            border-radius: 25px; text-decoration: none; font-weight: 600; font-size: 13px;
            transition: all 0.2s; min-width: 80px; text-align: center;
        }
        .month-nav a:active { transform: scale(0.95); }
        .month-nav .btn-today { background: var(--warning); color: white; }
        .month-title { text-align: center; font-size: 20px; font-weight: bold; color: var(--primary); flex: 1; }
        .month-title .greg { font-size: 12px; color: #A52A2A; display: block; font-weight: normal; }

        .legend { display: flex; gap: 12px; flex-wrap: wrap; margin-bottom: 12px; font-size: 11px; padding: 10px; background: var(--gray-100); border-radius: 8px; }
        .legend-item { display: flex; align-items: center; gap: 5px; }
        .legend-dot { width: 14px; height: 14px; border-radius: 3px; display: inline-block; }
        .ld-present { background: var(--success); } .ld-absent { background: var(--danger); }
        .ld-permission { background: var(--warning); }
        .ld-weekend { background: #D1FAE5; border: 2px solid var(--success); }
        .ld-closed { background: #9CA3AF; border: 2px solid #6B7280; }
        .ld-today { background: #FEF3C7; border: 2px solid var(--warning); }
        .ld-past { background: #DBEAFE; border: 2px solid #3B82F6; }

        .info-bar {
            background: var(--white); border-radius: 12px; padding: 12px 15px; margin-bottom: 10px;
            border: 2px solid var(--gold); display: flex; justify-content: space-between;
            flex-wrap: wrap; gap: 8px; font-size: 13px; color: var(--primary); font-weight: 600;
        }

        .table-wrapper {
            background: var(--white); border-radius: 16px; overflow: hidden;
            border: 2px solid var(--gold); box-shadow: 0 4px 15px rgba(0,0,0,0.06);
        }
        .table-header-bar {
            padding: 10px 15px; background: #FEF3C7; border-bottom: 2px solid var(--gold);
            display: flex; justify-content: space-between; font-size: 13px; font-weight: 600; color: var(--primary);
        }
        .table-scroll { overflow-x: auto; max-height: 55vh; -webkit-overflow-scrolling: touch; }
        .table-scroll table { width: 100%; border-collapse: collapse; min-width: 600px; }
        .table-scroll thead th {
            background: #5C2D0E; color: #FFD700; padding: 8px 4px;
            text-align: center; border: 1px solid var(--gold-dark);
            font-size: 10px; position: sticky; top: 0; z-index: 3; white-space: nowrap; font-weight: 700;
        }
        .table-scroll th:first-child, .table-scroll td:first-child {
            position: sticky; left: 0; background: white; z-index: 2; min-width: 32px;
        }
        .table-scroll th:nth-child(2), .table-scroll td:nth-child(2) {
            position: sticky; left: 32px; background: white; z-index: 2; min-width: 120px; text-align: left;
        }
        .table-scroll thead th:first-child, .table-scroll thead th:nth-child(2) { z-index: 4; background: #5C2D0E; }
        .table-scroll tbody td {
            padding: 6px 3px; text-align: center; border-bottom: 1px solid #E5E7EB; font-size: 10px;
        }
        .table-scroll tbody tr:hover { background: #FFF8DC; }
        .table-scroll tbody tr:hover td:first-child,
        .table-scroll tbody tr:hover td:nth-child(2) { background: #FFF8DC; }
        
        .student-name { font-weight: 600; color: var(--primary); font-size: 11px; }
        
        .day-col { min-width: 42px; }
        .day-col.weekend { background: #F0FDF4; }
        .day-col.today { background: #FFFBEB !important; }
        .day-col.future { background: #FEF2F2; opacity: 0.4; }
        .day-col.closed { background: #E5E7EB !important; opacity: 0.6; }
        .day-col.past { background: #EFF6FF; }
        .day-num { font-weight: bold; font-size: 13px; color: #1a1a1a; }
        .day-name { font-size: 9px; line-height: 1.1; color: #333; font-weight: 600; }
        .day-name.sat { color: #059669; font-weight: 700; }

        .att-cell { display: flex; gap: 2px; justify-content: center; }
        .att-dot {
            width: 16px; height: 16px; border-radius: 50%; cursor: pointer;
            border: 2px solid transparent; transition: all 0.15s; display: inline-block;
        }
        .att-dot:active { transform: scale(1.3); }
        .att-dot.present { background: #D1FAE5; border-color: var(--success); }
        .att-dot.present.active { background: var(--success); }
        .att-dot.absent { background: #FEE2E2; border-color: var(--danger); }
        .att-dot.absent.active { background: var(--danger); }
        .att-dot.permission { background: #FEF3C7; border-color: var(--warning); }
        .att-dot.permission.active { background: var(--warning); }
        .att-dot.late { background: #DBEAFE; border-color: #3B82F6; }
        .att-dot.late.active { background: #3B82F6; }
        .att-dot.excused { background: #E9D5FF; border-color: #8B5CF6; }
        .att-dot.excused.active { background: #8B5CF6; }
        .att-dot.empty { background: #F3F4F6; border-color: #D1D5DB; }
        .no-click { cursor: not-allowed; opacity: 0.3; pointer-events: none; }
        .closed-cell { background: #E5E7EB; text-align:center; padding:5px; font-size:9px; color:#6B7280; }

        .toast {
            position: fixed; bottom: 30px; left: 50%; transform: translateX(-50%);
            background: var(--success); color: white; padding: 12px 30px;
            border-radius: 30px; font-size: 14px; font-weight: 600; display: none;
            z-index: 1000; box-shadow: 0 5px 20px rgba(0,0,0,0.3);
            animation: fadeInUp 0.3s;
        }
        .toast.show { display: block; }
        @keyframes fadeInUp { from { opacity: 0; transform: translateX(-50%) translateY(10px); } to { opacity: 1; transform: translateX(-50%) translateY(0); } }
        
        .empty-state { text-align: center; padding: 50px 20px; color: #999; background: white; border-radius: 16px; }
        .empty-state .icon { font-size: 50px; display: block; margin-bottom: 15px; }

        @media (min-width: 768px) {
            .container { max-width: 1400px; margin: 15px auto; }
            .day-col { min-width: 48px; }
            .att-dot { width: 20px; height: 20px; }
            .table-scroll { max-height: 60vh; }
        }
    </style>
</head>
<body>
    <?php include 'mobile_nav.php'; ?>

    <div class="main-container">
        <?php if($error_message): ?>
            <div class="alert alert-error">⚠️ <?php echo $error_message; ?></div>
        <?php else: ?>

            <?php if(!empty($assigned_classes)): ?>
            <div class="class-bar">
                <?php foreach($assigned_classes as $class): ?>
                <a href="<?php echo buildUrl($selected_eth_month, $selected_eth_year, $class['class_id']); ?>" 
                   class="class-chip <?php echo $selected_class_id == $class['class_id'] ? 'active' : ''; ?>">
                    📖 <?php echo htmlspecialchars($class['class_name']); ?>
                    <span class="count"><?php echo $class['student_count']; ?></span>
                </a>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <div class="month-card">
                <div class="month-nav">
                    <a href="<?php echo buildUrl($prev_m, $prev_y, $selected_class_id); ?>">← ቀዳሚ</a>
                    <div class="month-title">
                        <?php echo $ethiopian_months[$selected_eth_month] . ' ' . $selected_eth_year; ?> ዓ.ም
                        <span class="greg">(ቅዳሜ & እሁድ)</span>
                    </div>
                    <div style="display:inline-flex; gap:6px; align-items:center;">
                        <a href="<?php echo buildUrl($next_m, $next_y, $selected_class_id); ?>">ቀጣይ →</a>
                        <a href="<?php echo buildUrl($today_month, $today_year, $selected_class_id); ?>" class="btn-today">📅 ዛሬ</a>
                    </div>
                </div>

                <div class="legend">
                    <div class="legend-item"><span class="legend-dot ld-weekend"></span> ቅዳሜ/እሁድ</div>
                    <div class="legend-item"><span class="legend-dot ld-today"></span> ዛሬ</div>
                    <div class="legend-item"><span class="legend-dot ld-past"></span> ያለፈ ቀን</div>
                    <div class="legend-item"><span class="legend-dot ld-present"></span> ✅ ተገኝቷል</div>
                    <div class="legend-item"><span class="legend-dot ld-absent"></span> ❌ አልተገኘም</div>
                    <div class="legend-item"><span class="legend-dot ld-permission"></span> 📝 በፈቃድ</div>
                    <div class="legend-item"><span class="legend-dot ld-closed"></span> 🚫 ትምህርት የለም</div>
                </div>
            </div>

            <?php if($selected_class && !empty($students)): ?>
            <div class="info-bar">
                <span>📖 <strong><?php echo htmlspecialchars($selected_class['class_name']); ?></strong></span>
                <span>👥 <?php echo count($students); ?> ተማሪዎች</span>
                <span>👨‍🏫 <?php echo count($teachers); ?> መምህራን</span>
                <span>📅 <?php echo count($eth_month_days); ?> ቀናት</span>
            </div>

            <div class="table-wrapper">
                <div class="table-header-bar">
                    <span>📋 ወርሃዊ የአቴንዳንስ ሰንጠረዥ (ቅዳሜ & እሁድ)</span>
                    <span style="font-size:11px;">ለሁሉም መምህራን ይመዘገባል</span>
                </div>
                <div class="table-scroll">
                    <table>
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>የተማሪ ስም</th>
                                <?php foreach($eth_month_days as $day):
                                    $colClass = 'weekend';
                                    if($day['is_today']) $colClass .= ' today';
                                    if($day['is_future']) $colClass .= ' future';
                                    if($day['is_closed']) $colClass .= ' closed';
                                    if(!$day['is_future'] && !$day['is_today']) $colClass .= ' past';
                                ?>
                                <th class="day-col <?php echo $colClass; ?>">
                                    <div class="day-name sat"><?php echo $day['day_am']; ?></div>
                                    <div class="day-num"><?php echo $day['eth_day']; ?></div>
                                    <?php if($day['is_closed']): ?><small style="font-size:7px; color:#9CA3AF;">🚫</small><?php endif; ?>
                                </th>
                                <?php endforeach; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($students as $index => $student): $sid = $student['id']; ?>
                            <tr data-student-id="<?php echo $sid; ?>">
                                <td><?php echo $index + 1; ?></td>
                                <td class="student-name"><?php echo htmlspecialchars($student['name']); ?></td>
                                <?php foreach($eth_month_days as $day):
                                    $stat = $attendance_data[$sid][$day['greg_date']] ?? null;
                                    $colClass = 'weekend';
                                    if($day['is_today']) $colClass .= ' today';
                                    if($day['is_future']) $colClass .= ' future';
                                    if($day['is_closed']) $colClass .= ' closed';
                                    if(!$day['is_future'] && !$day['is_today']) $colClass .= ' past';
                                ?>
                                <td class="day-col <?php echo $colClass; ?>" data-date="<?php echo $day['greg_date']; ?>">
                                    <?php if($day['is_closed']): ?>
                                    <div class="closed-cell">🚫<br>ዝግ</div>
                                    <?php elseif($day['clickable']): ?>
                                    <div class="att-cell">
                                        <span class="att-dot present <?php echo $stat == 'present' ? 'active' : ''; ?>"
                                              onclick="saveAtt(<?php echo $sid; ?>,'<?php echo $day['greg_date']; ?>','present',this)" title="ተገኝቷል"></span>
                                        <span class="att-dot absent <?php echo $stat == 'absent' ? 'active' : ''; ?>"
                                              onclick="saveAtt(<?php echo $sid; ?>,'<?php echo $day['greg_date']; ?>','absent',this)" title="አልተገኘም"></span>
                                        <span class="att-dot permission <?php echo $stat == 'permission' ? 'active' : ''; ?>"
                                              onclick="saveAtt(<?php echo $sid; ?>,'<?php echo $day['greg_date']; ?>','permission',this)" title="በፈቃድ"></span>
                                    </div>
                                    <?php else: ?>
                                    <div class="att-cell"><span class="att-dot empty no-click"></span></div>
                                    <?php endif; ?>
                                </td>
                                <?php endforeach; ?>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php elseif($selected_class): ?>
            <div class="empty-state"><span class="icon">👥</span><h3>ምንም ተማሪዎች የሉም</h3></div>
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <div id="toast" class="toast"></div>

    <script src="/exam/assets/js/offline-db.js"></script>
    <script src="/exam/assets/js/sync-manager.js"></script>
    <script>
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', () => {
                navigator.serviceWorker.register('/exam/sw.js').catch(() => {});
            });
        }

        const classId = <?php echo $selected_class_id ?: 0; ?>;
        
        async function saveAtt(studentId, date, status, el) {
            const cell = el.closest('td');
            cell.querySelectorAll('.att-dot').forEach(d => d.classList.remove('active'));
            el.classList.add('active');
            
            const toast = document.getElementById('toast');
            toast.textContent = '⏳ በማስቀመጥ ላይ...';
            toast.style.background = '#F59E0B';
            toast.classList.add('show');

            const localUuid = (window.crypto && crypto.randomUUID) ? crypto.randomUUID() : 'att_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9);
            const attRecord = {
                local_uuid: localUuid,
                student_id: parseInt(studentId),
                class_id: classId,
                attendance_date: date,
                status: status
            };

            // Always store to IndexedDB for seamless offline resilience
            await OfflineDB.saveAttendanceLocal(attRecord);
            
            
            
            const fd = new FormData();
            fd.append('ajax_save_attendance', '1');
            fd.append('student_id', studentId);
            fd.append('class_id', classId);
            fd.append('attendance_date', date);
            fd.append('status', status);
            
            fetch(window.location.href, { method: 'POST', body: fd })
            .then(r => r.json())
            .then(d => {
                if(d.success) {
                    toast.textContent = '✅ ተቀምጧል & ተመሳስሏል!';
                    toast.style.background = '#10B981';
                    SyncManager.pushChanges();
                } else {
                    toast.textContent = '❌ ስህተት!';
                    toast.style.background = '#EF4444';
                    cell.querySelectorAll('.att-dot').forEach(d => d.classList.remove('active'));
                }
                setTimeout(() => toast.classList.remove('show'), 2000);
            })
            .catch(() => {
                toast.textContent = '💾 ከመስመር ውጭ ተቀምጧል!';
                toast.style.background = '#3B82F6';
                setTimeout(() => toast.classList.remove('show'), 2000);
            });
        }
    </script>
</body>
</html>
<?php mysqli_close($conn); ?>