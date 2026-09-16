<?php
require_once 'db.php';
requireStudent();

$student_id = intval($_SESSION['student_id']);
$student_name = $_SESSION['student_name'] ?? 'ተማሪ';
$student_class_id = intval($_SESSION['student_class_id'] ?? 0);
$student_class = $_SESSION['student_class'] ?? '';

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

// Get ALL semesters
$all_semesters = dbFetchAll(
    $conn,
    "SELECT DISTINCT sem.id, sem.name, sem.status, sem.ethiopian_year, sem.semester_number
     FROM semesters sem
     WHERE sem.id IN (
         SELECT DISTINCT m.semester_id FROM marks m WHERE m.student_id = ?
         UNION
         SELECT DISTINCT tc.semester_id FROM teacher_class tc WHERE tc.class_id = ?
     )
     OR sem.status = 'active'
     ORDER BY sem.ethiopian_year DESC, sem.semester_number ASC",
    "ii",
    [$student_id, $student_class_id]
);

$selected_semester_id = isset($_GET['semester_id']) ? intval($_GET['semester_id']) : 
                        (!empty($all_semesters) ? $all_semesters[0]['id'] : 0);

$selected_semester = null;
foreach ($all_semesters as $sem) {
    if ($sem['id'] == $selected_semester_id) {
        $selected_semester = $sem;
        break;
    }
}

$base_params = "semester_id=$selected_semester_id";
$base_page = "dashboard_student.php";

// Ethiopian calendar for attendance view
$today_eth = getCurrentEthiopianDate();
$selected_eth_month = isset($_GET['m']) ? intval($_GET['m']) : $today_eth['month'];
$selected_eth_year = isset($_GET['y']) ? intval($_GET['y']) : $today_eth['year'];

if($selected_eth_month < 1 || $selected_eth_month > 13) {
    $selected_eth_month = $today_eth['month'];
}
if($selected_eth_year < 2000 || $selected_eth_year > 2100) {
    $selected_eth_year = $today_eth['year'];
}

// Build month days - ONLY SATURDAY & SUNDAY
$days_in_month = getEthiopianDaysInMonth($selected_eth_year, $selected_eth_month);
$month_days = [];
$month_start_greg = '';

// Use the accurate ethiopianToGregorian() function (handles leap years correctly)
$month_start_str = ethiopianToGregorian($selected_eth_year, $selected_eth_month, 1);
$eth_new_year = new DateTime($month_start_str);

for($d = 1; $d <= $days_in_month; $d++) {
    $day_offset = $d - 1;
    $greg_date = clone $eth_new_year;
    $greg_date->modify("+$day_offset days");
    $ds = $greg_date->format('Y-m-d');
    $dow = $greg_date->format('l');
    
    if($d == 1) $month_start_greg = $ds;
    
    if($dow != 'Saturday' && $dow != 'Sunday') {
        continue;
    }
    
    $month_days[] = [
        'eth_day' => $d,
        'greg_date' => $ds,
        'day_name' => $dow,
        'day_am' => $amharic_days[$dow] ?? substr($dow, 0, 3),
        'is_weekend' => true,
        'is_today' => $ds == date('Y-m-d'),
        'is_future' => $ds > date('Y-m-d')
    ];
}

// Get closed days
$closed_days = [];
if (!empty($month_days)) {
    $first_date = $month_days[0]['greg_date'];
    $last_date = $month_days[count($month_days)-1]['greg_date'];
    
    $cd_rows = dbFetchAll(
        $conn,
        "SELECT date_gregorian FROM attendance_days 
         WHERE date_gregorian BETWEEN ? AND ? 
         AND is_school_day = 0 
         AND (class_id IS NULL OR class_id = ?)",
        "ssi",
        [$first_date, $last_date, $student_class_id]
    );
    foreach ($cd_rows as $row) {
        $closed_days[$row['date_gregorian']] = true;
    }
}

foreach($month_days as &$day) {
    $day['is_closed'] = isset($closed_days[$day['greg_date']]);
}
unset($day);

// Navigation
$prev_m = $selected_eth_month - 1;
$prev_y = $selected_eth_year;
if($prev_m < 1) { $prev_m = 13; $prev_y--; }

// Today
$today_month = $today_eth['month'];
$today_year = $today_eth['year'];

// Get marks
$marks_query = "SELECT 
                u.name as teacher_name, u.id as teacher_id,
                COALESCE(m.assignment, 0) as assignment, COALESCE(m.participation, 0) as participation,
                COALESCE(m.attendance, 0) as attendance, COALESCE(m.mid, 0) as mid,
                COALESCE(m.final, 0) as final, COALESCE(m.total, 0) as total,
                ms.component1_name, ms.component1_percentage,
                ms.component2_name, ms.component2_percentage,
                ms.component3_name, ms.component3_percentage,
                ms.component4_name, ms.component4_percentage,
                ms.component5_name, ms.component5_percentage
                FROM teacher_class tc
                JOIN users u ON tc.teacher_id = u.id
                LEFT JOIN marks m ON m.student_id = ? AND m.teacher_id = u.id AND m.semester_id = ?
                LEFT JOIN marking_schemes ms ON ms.teacher_id = u.id AND ms.class_id = tc.class_id AND ms.semester_id = ?
                WHERE tc.class_id = ? AND tc.semester_id = ?
                ORDER BY u.name";

$marks_rows = dbFetchAll($conn, $marks_query, "iiiii", [$student_id, $selected_semester_id, $selected_semester_id, $student_class_id, $selected_semester_id]);
$all_marks = [];
$overall_total = 0;
$teacher_count = 0;

foreach ($marks_rows as $row) {
    $teacher_count++;
    $row['c1_name'] = !empty($row['component1_name']) ? $row['component1_name'] : 'Assignment';
    $row['c2_name'] = !empty($row['component2_name']) ? $row['component2_name'] : 'Participation';
    $row['c3_name'] = !empty($row['component3_name']) ? $row['component3_name'] : 'Attendance';
    $row['c4_name'] = !empty($row['component4_name']) ? $row['component4_name'] : 'Mid Exam';
    $row['c5_name'] = !empty($row['component5_name']) ? $row['component5_name'] : 'Final Exam';
    $row['c1_max'] = !empty($row['component1_percentage']) ? $row['component1_percentage'] : 20;
    $row['c2_max'] = !empty($row['component2_percentage']) ? $row['component2_percentage'] : 20;
    $row['c3_max'] = !empty($row['component3_percentage']) ? $row['component3_percentage'] : 10;
    $row['c4_max'] = !empty($row['component4_percentage']) ? $row['component4_percentage'] : 25;
    $row['c5_max'] = !empty($row['component5_percentage']) ? $row['component5_percentage'] : 25;
    $all_marks[] = $row;
    $overall_total += $row['total'];
}

$average = $teacher_count > 0 ? round($overall_total / $teacher_count, 1) : 0;

// Get student rank
$rank_row = dbFetchOne(
    $conn,
    "SELECT COUNT(*) + 1 as rank FROM (
        SELECT s.id, AVG(COALESCE(m.total, 0)) as avg_mark
        FROM students s
        JOIN teacher_class tc ON s.class_id = tc.class_id AND tc.semester_id = ?
        LEFT JOIN marks m ON s.id = m.student_id AND m.semester_id = ? AND m.teacher_id = tc.teacher_id
        WHERE s.class_id = ?
        GROUP BY s.id
        HAVING AVG(COALESCE(m.total, 0)) > ?
    ) as better_students",
    "iiid",
    [$selected_semester_id, $selected_semester_id, $student_class_id, $average]
);
$rank = $rank_row ? $rank_row['rank'] : 1;

$tot_row = dbFetchOne($conn, "SELECT COUNT(*) as total FROM students WHERE class_id = ? AND (is_deleted = 0 OR is_deleted IS NULL)", "i", [$student_class_id]);
$total_students = $tot_row ? $tot_row['total'] : 0;

// Get attendance data
$attendance_data = [];
$att_rows = dbFetchAll(
    $conn,
    "SELECT attendance_date, status FROM attendance_records 
     WHERE student_id = ? AND class_id = ? ORDER BY attendance_date",
    "ii",
    [$student_id, $student_class_id]
);
foreach ($att_rows as $att_row) {
    $attendance_data[$att_row['attendance_date']] = $att_row['status'];
}

// Get attendance summary
$att_summary = dbFetchOne(
    $conn,
    "SELECT 
        SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) as present,
        SUM(CASE WHEN status = 'absent' THEN 1 ELSE 0 END) as absent,
        SUM(CASE WHEN status = 'permission' THEN 1 ELSE 0 END) as permission,
        COUNT(*) as total_days
     FROM attendance_records WHERE student_id = ? AND class_id = ?",
    "ii",
    [$student_id, $student_class_id]
);
$attendance_summary = $att_summary ?: ['present' => 0, 'absent' => 0, 'permission' => 0, 'total_days' => 0];

// Get class average
$class_avg_row = dbFetchOne(
    $conn,
    "SELECT AVG(COALESCE(m.total, 0)) as class_avg
     FROM students s
     JOIN teacher_class tc ON s.class_id = tc.class_id AND tc.semester_id = ?
     LEFT JOIN marks m ON s.id = m.student_id AND m.semester_id = ? AND m.teacher_id = tc.teacher_id
     WHERE s.class_id = ?",
    "iii",
    [$selected_semester_id, $selected_semester_id, $student_class_id]
);
$class_average = round($class_avg_row['class_avg'] ?? 0, 1);

// Get history
$history_rows = dbFetchAll(
    $conn,
    "SELECT 
        sem.id as semester_id, sem.name as semester_name, sem.ethiopian_year,
        sem.semester_number, sem.status,
        COUNT(DISTINCT u.id) as teacher_count,
        AVG(COALESCE(m.total, 0)) as avg_total, SUM(COALESCE(m.total, 0)) as total_marks
     FROM semesters sem
     JOIN teacher_class tc ON tc.semester_id = sem.id AND tc.class_id = ?
     JOIN users u ON tc.teacher_id = u.id
     LEFT JOIN marks m ON m.student_id = ? AND m.semester_id = sem.id AND m.teacher_id = u.id
     GROUP BY sem.id, sem.name, sem.ethiopian_year, sem.semester_number, sem.status
     ORDER BY sem.ethiopian_year DESC, sem.semester_number ASC",
    "ii",
    [$student_class_id, $student_id]
);
$history_by_year = [];
foreach ($history_rows as $row) {
    $year = $row['ethiopian_year'];
    if (!isset($history_by_year[$year])) {
        $history_by_year[$year] = [];
    }
    $history_by_year[$year][] = $row;
}
?>
<!DOCTYPE html>
<html lang="am">
<head>
    <meta charset="UTF-8">
    <title>የተማሪ ዳሽቦርድ | Student Dashboard</title>
    <?php include 'pwa_head.php'; ?>
    <style>
        :root {
            --brown-dark: #8B4513; --brown-medium: #A52A2A; --gold-primary: #FFD700;
            --gold-dark: #DAA520; --gold-pale: #FFF8DC; --success-green: #10B981;
            --error-red: #EF4444; --warning-yellow: #F59E0B; --info-blue: #3B82F6;
            --bg-cream: #FAF9F6; --closed-gray: #9CA3AF;
        }
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Segoe UI', 'Nyala', sans-serif; }
        body { background: var(--bg-cream); min-height: 100vh; padding-bottom: 30px; }

        .header {
            background: linear-gradient(135deg, #8B4513, #A52A2A);
            color: white; padding: 12px 20px; display: flex; justify-content: space-between;
            align-items: center; flex-wrap: wrap; gap: 12px; position: sticky; top: 0; z-index: 100;
        }
        .student-info { display: flex; align-items: center; gap: 12px; }
        .avatar-img { width: 45px; height: 45px; border-radius: 50%; object-fit: cover; border: 2px solid var(--gold-primary); background: white; }
        .student-details h2 { font-size: 16px; color: var(--gold-primary); }
        .student-details span { font-size: 12px; opacity: 0.9; }
        .header-actions { display: flex; gap: 8px; }
        .btn-header {
            padding: 8px 16px; border-radius: 25px; text-decoration: none; font-weight: 600;
            font-size: 12px; display: inline-flex; align-items: center; gap: 5px;
        }
        .btn-logout { background: rgba(255,255,255,0.2); color: white; }
        .btn-changepin { background: var(--gold-primary); color: var(--brown-dark); }

        .container { max-width: 1000px; margin: 15px auto; padding: 0 15px; }

        .semester-tabs {
            background: white; border-radius: 12px; padding: 12px; margin-bottom: 15px;
            border: 2px solid var(--gold-primary); display: flex; gap: 8px; overflow-x: auto;
        }
        .sem-tab {
            padding: 8px 16px; border-radius: 25px; text-decoration: none; font-weight: 600;
            font-size: 12px; white-space: nowrap; border: 2px solid var(--gold-dark);
            color: var(--brown-dark); background: white;
        }
        .sem-tab.active { background: var(--gold-primary); border-color: var(--brown-dark); }
        .year-group-label {
            font-size: 10px; color: #999; padding: 8px 4px; font-weight: bold;
            display: flex; align-items: center;
        }

        .stats-grid {
            display: grid; grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
            gap: 12px; margin-bottom: 15px;
        }
        .stat-card {
            background: white; border-radius: 12px; padding: 15px; text-align: center;
            border: 2px solid var(--gold-pale); box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }
        .stat-value { font-size: 24px; font-weight: bold; color: var(--brown-dark); }
        .stat-label { font-size: 11px; color: #666; margin-top: 4px; }

        .section-card {
            background: white; border-radius: 16px; padding: 20px; margin-bottom: 20px;
            border: 1px solid #E2E8F0; box-shadow: 0 4px 12px rgba(0,0,0,0.05);
        }
        .section-title {
            color: var(--brown-dark); font-size: 18px; margin-bottom: 15px;
            padding-bottom: 10px; border-bottom: 2px solid var(--gold-pale);
            display: flex; align-items: center; gap: 8px; flex-wrap: wrap;
        }

        .table-responsive { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; min-width: 500px; font-size: 13px; }
        th { background: var(--brown-dark); color: white; padding: 10px 8px; text-align: center; border: 1px solid var(--gold-dark); font-size: 11px; }
        td { padding: 8px; text-align: center; border-bottom: 1px solid #E2E8F0; }
        tr:hover { background: var(--gold-pale); }
        .teacher-name-cell { text-align: left; font-weight: 600; color: var(--brown-dark); }
        .total-row { background: var(--gold-pale); font-weight: bold; }
        .max-hint { font-size: 9px; color: #999; display: block; }

        .grade-badge { display: inline-block; padding: 3px 10px; border-radius: 15px; font-size: 11px; font-weight: 600; }
        .grade-excellent { background: #D1FAE5; color: #059669; }
        .grade-good { background: #DBEAFE; color: #1D4ED8; }
        .grade-satisfactory { background: #FEF3C7; color: #B45309; }
        .grade-poor { background: #FEE2E2; color: #DC2626; }

        .month-nav { display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; gap: 10px; }
        .month-nav a {
            padding: 12px 20px; background: var(--gold-primary); color: var(--brown-dark);
            border-radius: 25px; text-decoration: none; font-weight: 700; font-size: 14px;
            min-width: 100px; text-align: center; border: 2px solid var(--gold-dark);
            transition: all 0.2s;
        }
        .month-nav a:hover { background: var(--gold-dark); color: white; transform: translateY(-2px); }
        .month-nav .btn-today { background: var(--warning-yellow); color: white; border-color: var(--warning-yellow); }
        .month-title { text-align: center; font-size: 20px; font-weight: bold; color: var(--brown-dark); flex: 1; line-height: 1.4; }
        .month-title .greg { font-size: 12px; color: #A52A2A; display: block; font-weight: normal; margin-top: 4px; }

        .legend { display: flex; gap: 10px; flex-wrap: wrap; margin-bottom: 12px; font-size: 11px; padding: 10px; background: #F3F4F6; border-radius: 8px; }
        .legend-item { display: flex; align-items: center; gap: 5px; }
        .legend-dot { width: 14px; height: 14px; border-radius: 3px; display: inline-block; }
        .ld-present { background: var(--success-green); } .ld-absent { background: var(--error-red); }
        .ld-permission { background: var(--warning-yellow); } .ld-closed { background: #9CA3AF; border: 2px solid #6B7280; }

        .calendar-2col {
            display: grid; grid-template-columns: 1fr 1fr; gap: 8px; margin-bottom: 15px;
        }
        .day-cell {
            padding: 12px 8px; border-radius: 10px; text-align: center; 
            background: #F0FDF4; min-height: 70px; transition: all 0.2s;
        }
        .day-cell.today { background: #FFFBEB !important; border: 2px solid var(--warning-yellow); }
        .day-cell.future { opacity: 0.5; background: #FEF2F2; }
        .day-cell.closed { background: #E5E7EB; opacity: 0.7; }
        .day-cell .day-name-text { font-size: 11px; color: #059669; font-weight: 700; margin-bottom: 4px; }
        .day-cell .day-num { font-size: 28px; font-weight: bold; color: #1a1a1a; margin: 5px 0; }
        .day-cell .greg-date { font-size: 10px; color: #666; }

        .status-dot { width: 24px; height: 24px; border-radius: 50%; display: inline-block; margin-top: 5px; }
        .status-present { background: var(--success-green); }
        .status-absent { background: var(--error-red); }
        .status-permission { background: var(--warning-yellow); }
        .status-empty { background: #F3F4F6; border: 2px solid #D1D5DB; }
        .status-closed { background: #9CA3AF; }

        .att-summary-row { display: flex; gap: 10px; margin-top: 15px; flex-wrap: wrap; }
        .att-summary-card { padding: 10px 15px; border-radius: 8px; text-align: center; flex: 1; min-width: 80px; }
        .att-summary-card .count { font-size: 20px; font-weight: bold; }
        .att-summary-card .label { font-size: 10px; }
        .att-present { background: #D1FAE5; color: #059669; }
        .att-absent { background: #FEE2E2; color: #DC2626; }
        .att-permission { background: #FEF3C7; color: #D97706; }

        .history-year-group { margin-bottom: 20px; }
        .history-year-title {
            background: var(--brown-dark); color: var(--gold-primary);
            padding: 10px 15px; border-radius: 10px 10px 0 0; font-weight: bold; font-size: 16px;
        }
        .history-card {
            background: #F8F9FA; padding: 15px; margin-bottom: 8px;
            border-left: 4px solid var(--gold-primary); border-radius: 0 8px 8px 0;
            display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;
        }
        .history-card:hover { background: var(--gold-pale); }
        .history-sem-name { font-weight: 600; color: var(--brown-dark); }
        .history-marks { text-align: right; }
        .history-avg { font-size: 22px; font-weight: bold; color: var(--brown-dark); }
        .history-status { display: inline-block; padding: 3px 10px; border-radius: 12px; font-size: 10px; font-weight: 600; margin-left: 8px; }
        .status-active { background: #D1FAE5; color: #059669; }
        .status-closed-status { background: #FEE2E2; color: #DC2626; }
        .view-btn {
            padding: 6px 14px; background: var(--gold-primary); color: var(--brown-dark);
            border-radius: 20px; text-decoration: none; font-size: 11px; font-weight: 600;
        }
        .empty-state { text-align: center; padding: 40px; color: #999; }

        @media (max-width: 768px) {
            .stats-grid { grid-template-columns: repeat(2, 1fr); }
            .header { flex-direction: column; text-align: center; }
        }
    </style>
</head>
<body>
<?php $nav_active = 'dashboard_student'; include 'mobile_nav.php'; ?>

    <div class="main-container">
        <div class="header">
            <div class="student-info">
                <img src="images/icon.png" alt="Logo" class="avatar-img" onerror="this.style.display='none'; this.insertAdjacentHTML('afterend','🎓');">
                <div class="student-details">
                    <h2><?php echo htmlspecialchars($student_name); ?></h2>
                    <span><?php echo htmlspecialchars($student_class); ?></span>
                </div>
            </div>
            <div class="header-actions">
                <a href="student_change_pin.php?<?php echo $base_params; ?>" class="btn-header btn-changepin">🔒 ፒን ቀይር</a>
                <a href="student_logout.php" class="btn-header btn-logout">🚪 ውጣ</a>
            </div>
        </div>

        <div class="container">
        <?php if (!empty($all_semesters)): ?>
        <div class="semester-tabs">
            <?php 
            $current_year = 0;
            foreach ($all_semesters as $sem): 
                if ($current_year != $sem['ethiopian_year']):
                    $current_year = $sem['ethiopian_year'];
                    echo "<span class='year-group-label'>{$sem['ethiopian_year']} ዓ.ም ▸</span>";
                endif;
            ?>
            <a href="?semester_id=<?php echo $sem['id']; ?>" class="sem-tab <?php echo $sem['id'] == $selected_semester_id ? 'active' : ''; ?>">
                <?php echo $sem['semester_number'] == 1 ? '📗 1ኛ' : '📕 2ኛ'; ?>
            </a>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <div class="stats-grid">
            <div class="stat-card"><div class="stat-value"><?php echo number_format($average, 1); ?>%</div><div class="stat-label">የእኔ አማካይ</div></div>
            <div class="stat-card"><div class="stat-value"><?php echo number_format($class_average, 1); ?>%</div><div class="stat-label">የክፍል አማካይ</div></div>
            <div class="stat-card"><div class="stat-value">#<?php echo $rank; ?>/<?php echo $total_students; ?></div><div class="stat-label">ደረጃ</div></div>
            <div class="stat-card"><div class="stat-value"><?php echo $attendance_summary['present'] ?? 0; ?></div><div class="stat-label">ቀናት ተገኝቻለሁ</div></div>
        </div>

        <!-- Marks Table -->
        <div class="section-card">
            <div class="section-title">
                📊 ውጤቶቼ
                <?php if ($selected_semester): ?><span style="font-size:14px; color:#666;">- <?php echo htmlspecialchars($selected_semester['name']); ?></span><?php endif; ?>
            </div>
            <?php if (!empty($all_marks)): ?>
            <div class="table-responsive">
                <table>
                    <thead><tr>
                        <th>መምህር</th>
                        <?php $ft = $all_marks[0]; ?>
                        <th><?php echo htmlspecialchars($ft['c1_name']); ?><br><span class="max-hint">(<?php echo $ft['c1_max']; ?>%)</span></th>
                        <th><?php echo htmlspecialchars($ft['c2_name']); ?><br><span class="max-hint">(<?php echo $ft['c2_max']; ?>%)</span></th>
                        <th><?php echo htmlspecialchars($ft['c3_name']); ?><br><span class="max-hint">(<?php echo $ft['c3_max']; ?>%)</span></th>
                        <th><?php echo htmlspecialchars($ft['c4_name']); ?><br><span class="max-hint">(<?php echo $ft['c4_max']; ?>%)</span></th>
                        <th><?php echo htmlspecialchars($ft['c5_name']); ?><br><span class="max-hint">(<?php echo $ft['c5_max']; ?>%)</span></th>
                        <th>ድምር</th><th>ደረጃ</th>
                    </tr></thead>
                    <tbody>
                        <?php foreach ($all_marks as $mark): 
                            $gc = 'grade-satisfactory';
                            if ($mark['total'] >= 85) $gc = 'grade-excellent';
                            elseif ($mark['total'] >= 70) $gc = 'grade-good';
                            elseif ($mark['total'] < 50) $gc = 'grade-poor';
                        ?>
                        <tr>
                            <td class="teacher-name-cell">👨‍🏫 <?php echo htmlspecialchars($mark['teacher_name']); ?></td>
                            <td><?php echo $mark['assignment'] > 0 ? number_format($mark['assignment'], 1) : '-'; ?></td>
                            <td><?php echo $mark['participation'] > 0 ? number_format($mark['participation'], 1) : '-'; ?></td>
                            <td><?php echo $mark['attendance'] > 0 ? number_format($mark['attendance'], 1) : '-'; ?></td>
                            <td><?php echo $mark['mid'] > 0 ? number_format($mark['mid'], 1) : '-'; ?></td>
                            <td><?php echo $mark['final'] > 0 ? number_format($mark['final'], 1) : '-'; ?></td>
                            <td><strong><?php echo number_format($mark['total'], 1); ?></strong></td>
                            <td><span class="grade-badge <?php echo $gc; ?>"><?php echo getGradeStatus($mark['total']); ?></span></td>
                        </tr>
                        <?php endforeach; ?>
                        <tr class="total-row">
                            <td><strong>አማካይ</strong></td><td colspan="5"></td>
                            <td><strong><?php echo number_format($average, 1); ?></strong></td>
                            <td><strong><?php echo getGradeStatus($average); ?></strong></td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <?php else: ?><div class="empty-state">ለዚህ ሴሚስተር ምንም ውጤት አልተመዘገበም</div><?php endif; ?>
        </div>

        <!-- Attendance Calendar -->
        <div class="section-card">
            <div class="section-title">
                📅 የአቴንዳንስ ቀን መቁጠሪያ
                <span style="font-size:12px; color:#999; margin-left:auto;">ቅዳሜ & እሁድ | 👁️ ለእይታ</span>
            </div>

            <div class="month-nav">
                <a href="<?php echo $base_page; ?>?<?php echo $base_params; ?>&m=<?php echo $prev_m; ?>&y=<?php echo $prev_y; ?>">← ቀዳሚ</a>
                <div class="month-title">
                    <?php echo $ethiopian_months[$selected_eth_month] . ' ' . $selected_eth_year; ?> ዓ.ም
                    <span class="greg">(<?php echo $month_start_greg ? date('F Y', strtotime($month_start_greg)) : ''; ?>)</span>
                </div>
                <a href="<?php echo $base_page; ?>?<?php echo $base_params; ?>&m=<?php echo $today_month; ?>&y=<?php echo $today_year; ?>" class="btn-today">📅 ዛሬ</a>
            </div>

            <div class="legend">
                <div class="legend-item"><span class="legend-dot ld-present"></span> ✅ ተገኝቻለሁ</div>
                <div class="legend-item"><span class="legend-dot ld-absent"></span> ❌ አልተገኘሁም</div>
                <div class="legend-item"><span class="legend-dot ld-permission"></span> 📝 በፈቃድ</div>
                <div class="legend-item"><span class="legend-dot ld-closed"></span> 🚫 ትምህርት የለም</div>
            </div>

            <div class="calendar-2col">
                <?php foreach ($month_days as $day):
                    $att_status = $attendance_data[$day['greg_date']] ?? null;
                    $cell_class = 'day-cell';
                    if ($day['is_today']) $cell_class .= ' today';
                    if ($day['is_future']) $cell_class .= ' future';
                    if ($day['is_closed']) $cell_class .= ' closed';
                ?>
                <div class="<?php echo $cell_class; ?>">
                    <div class="day-name-text"><?php echo $day['day_am']; ?></div>
                    <div class="day-num"><?php echo $day['eth_day']; ?></div>
                    <div class="greg-date"><?php echo date('M j', strtotime($day['greg_date'])); ?></div>
                    <?php if ($day['is_closed']): ?>
                        <span class="status-dot status-closed" title="ትምህርት የለም"></span>
                    <?php elseif (!$day['is_future']): ?>
                        <?php if ($att_status == 'present'): ?>
                            <span class="status-dot status-present" title="✅ ተገኝቻለሁ"></span>
                        <?php elseif ($att_status == 'absent'): ?>
                            <span class="status-dot status-absent" title="❌ አልተገኘሁም"></span>
                        <?php elseif ($att_status == 'permission'): ?>
                            <span class="status-dot status-permission" title="📝 በፈቃድ"></span>
                        <?php else: ?>
                            <span class="status-dot status-empty" title="አልተመዘገበም"></span>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
                <?php if(empty($month_days)): ?>
                <div style="grid-column: 1/-1; text-align:center; padding:20px; color:#999;">ምንም ቀን አልተገኘም</div>
                <?php endif; ?>
            </div>

            <div class="att-summary-row">
                <div class="att-summary-card att-present"><div class="count">✅ <?php echo $attendance_summary['present'] ?? 0; ?></div><div class="label">ተገኝቻለሁ</div></div>
                <div class="att-summary-card att-absent"><div class="count">❌ <?php echo $attendance_summary['absent'] ?? 0; ?></div><div class="label">አልተገኘሁም</div></div>
                <div class="att-summary-card att-permission"><div class="count">📝 <?php echo $attendance_summary['permission'] ?? 0; ?></div><div class="label">በፈቃድ</div></div>
            </div>
        </div>

        <!-- History -->
        <div class="section-card">
            <div class="section-title">📜 ሙሉ የውጤት ታሪክ</div>
            <?php if (!empty($history_by_year)): ?>
                <?php foreach ($history_by_year as $year => $semesters): ?>
                <div class="history-year-group">
                    <div class="history-year-title">📅 <?php echo $year; ?> ዓ.ም</div>
                    <?php foreach ($semesters as $hist): ?>
                    <div class="history-card">
                        <div>
                            <div class="history-sem-name">
                                <?php echo $hist['semester_number'] == 1 ? '📗 መጀመሪያ' : '📕 ሁለተኛ'; ?> ሴሚስተር
                                <span class="history-status <?php echo $hist['status'] == 'active' ? 'status-active' : 'status-closed-status'; ?>">
                                    <?php echo $hist['status'] == 'active' ? 'ንቁ' : 'ዝግ'; ?>
                                </span>
                            </div>
                            <div style="font-size:11px; color:#666; margin-top:4px;">👨‍🏫 <?php echo $hist['teacher_count']; ?> መምህራን</div>
                        </div>
                        <div class="history-marks">
                            <div class="history-avg"><?php echo number_format($hist['avg_total'], 1); ?>%</div>
                            <?php $hg = $hist['avg_total']; $hc = 'grade-satisfactory';
                            if ($hg >= 85) $hc = 'grade-excellent'; elseif ($hg >= 70) $hc = 'grade-good'; elseif ($hg < 50) $hc = 'grade-poor'; ?>
                            <span class="grade-badge <?php echo $hc; ?>"><?php echo getGradeStatus($hg); ?></span>
                            <br><a href="?semester_id=<?php echo $hist['semester_id']; ?>" class="view-btn">👁️ ዝርዝር</a>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endforeach; ?>
            <?php else: ?><div class="empty-state">እስካሁን ምንም የውጤት ታሪክ የለም</div><?php endif; ?>
        </div>
    </div>
</body>
</html>
<?php mysqli_close($conn); ?>