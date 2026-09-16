<?php
require_once 'db.php';
requireAdmin();

$current_semester = getCurrentSemester($conn);
$semester_id = $current_semester ? intval($current_semester['id']) : 0;

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

// Get today's Ethiopian date
$today_eth = getCurrentEthiopianDate();

// Selected Ethiopian month/year
$selected_eth_month = isset($_GET['eth_month']) ? intval($_GET['eth_month']) : intval($today_eth['month']);
$selected_eth_year = isset($_GET['eth_year']) ? intval($_GET['eth_year']) : intval($today_eth['year']);

if ($selected_eth_month < 1 || $selected_eth_month > 13) $selected_eth_month = intval($today_eth['month']);
if ($selected_eth_year < 2000 || $selected_eth_year > 2100) $selected_eth_year = intval($today_eth['year']);

// Filters
$filter_class = isset($_GET['class_id']) ? intval($_GET['class_id']) : 0;
$filter_teacher = isset($_GET['teacher_id']) ? intval($_GET['teacher_id']) : 0;

// Build Ethiopian month days - ONLY SATURDAY & SUNDAY
$days_in_month = getEthiopianDaysInMonth($selected_eth_year, $selected_eth_month);
$month_days = [];
$month_start_greg = '';
$month_end_greg = '';

for ($d = 1; $d <= $days_in_month; $d++) {
    $greg_str = ethiopianToGregorian($selected_eth_year, $selected_eth_month, $d);
    if (!$greg_str) continue;
    
    $dow = date('l', strtotime($greg_str));
    if ($dow !== 'Saturday' && $dow !== 'Sunday') {
        continue;
    }
    
    if (empty($month_start_greg)) $month_start_greg = $greg_str;
    $month_end_greg = $greg_str;
    
    $month_days[] = [
        'eth_day' => $d,
        'greg_date' => $greg_str,
        'day_name' => $dow,
        'day_am' => $amharic_days[$dow] ?? substr($dow, 0, 3),
        'is_weekend' => true,
        'is_future' => ($greg_str > date('Y-m-d')),
        'is_today' => ($greg_str === date('Y-m-d'))
    ];
}

// Get closed days (no school days)
$closed_days_global = [];
$closed_days_class = [];
if (!empty($month_days)) {
    $first_date = $month_days[0]['greg_date'];
    $last_date = $month_days[count($month_days)-1]['greg_date'];
    
    // Global closed days
    $cd_rows = dbFetchAll(
        $conn,
        "SELECT date_gregorian FROM attendance_days WHERE date_gregorian BETWEEN ? AND ? AND is_school_day = 0 AND class_id IS NULL",
        "ss",
        [$first_date, $last_date]
    );
    foreach ($cd_rows as $row) {
        $closed_days_global[$row['date_gregorian']] = true;
    }
    
    // Per-class closed days
    if ($filter_class > 0) {
        $cd_rows2 = dbFetchAll(
            $conn,
            "SELECT date_gregorian FROM attendance_days WHERE date_gregorian BETWEEN ? AND ? AND is_school_day = 0 AND class_id = ?",
            "ssi",
            [$first_date, $last_date, $filter_class]
        );
        foreach ($cd_rows2 as $row) {
            $closed_days_class[$row['date_gregorian']] = true;
        }
    }
}

// Mark closed days in month_days
foreach ($month_days as &$day) {
    $day['is_closed'] = isset($closed_days_global[$day['greg_date']]) || isset($closed_days_class[$day['greg_date']]);
}
unset($day);

// Get classes, teachers for filters - classes now grouped by division/grade (if assigned)
$classes = dbQuery($conn, "SELECT c.*, g.name_am AS grade_name, d.name_am AS division_name, d.sort_order, g.level_number
                            FROM classes c
                            LEFT JOIN grades g ON c.grade_id = g.id
                            LEFT JOIN divisions d ON g.division_id = d.id
                            ORDER BY d.sort_order, g.level_number, c.name");
$teachers = dbQuery($conn, "SELECT * FROM users WHERE role = 'teacher' ORDER BY name");

// Get students and attendance for selected class
$students = [];
$attendance_data = [];
if ($filter_class > 0 && !empty($month_days)) {
    $students = dbFetchAll(
        $conn,
        "SELECT * FROM students WHERE class_id = ? AND (is_deleted = 0 OR is_deleted IS NULL) ORDER BY name",
        "i",
        [$filter_class]
    );
    
    if (!empty($students)) {
        $student_ids = array_column($students, 'id');
        $first_date = $month_days[0]['greg_date'];
        $last_date = $month_days[count($month_days)-1]['greg_date'];
        
        $rec_query = "SELECT student_id, attendance_date, status FROM attendance_records 
                      WHERE class_id = ? AND attendance_date BETWEEN ? AND ?";
        $params = [$filter_class, $first_date, $last_date];
        $types = "iss";
        
        if ($filter_teacher > 0) {
            $rec_query .= " AND teacher_id = ?";
            $params[] = $filter_teacher;
            $types .= "i";
        }
        
        $records = dbFetchAll($conn, $rec_query, $types, $params);
        foreach ($records as $r) {
            $attendance_data[$r['student_id']][$r['attendance_date']] = $r['status'];
        }
    }
}

// Statistics
$stats = ['total' => 0, 'present' => 0, 'absent' => 0, 'permission' => 0, 'late' => 0, 'excused' => 0];
if ($month_start_greg && $month_end_greg) {
    $stats_query = "SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) as present,
        SUM(CASE WHEN status = 'absent' THEN 1 ELSE 0 END) as absent,
        SUM(CASE WHEN status = 'permission' THEN 1 ELSE 0 END) as permission,
        SUM(CASE WHEN status = 'late' THEN 1 ELSE 0 END) as late,
        SUM(CASE WHEN status = 'excused' THEN 1 ELSE 0 END) as excused
        FROM attendance_records 
        WHERE attendance_date BETWEEN ? AND ?";
    $sparams = [$month_start_greg, $month_end_greg];
    $stypes = "ss";
    
    if ($filter_class > 0) {
        $stats_query .= " AND class_id = ?";
        $sparams[] = $filter_class;
        $stypes .= "i";
    }
    $srow = dbFetchOne($conn, $stats_query, $stypes, $sparams);
    if ($srow) {
        $stats = $srow;
    }
}

// Navigation
$prev_m = $selected_eth_month - 1; $prev_y = $selected_eth_year;
if($prev_m < 1) { $prev_m = 13; $prev_y--; }
$next_m = $selected_eth_month + 1; $next_y = $selected_eth_year;
if($next_m > 13) { $next_m = 1; $next_y++; }

$query_params = '';
if($filter_class) $query_params .= "&class_id=$filter_class";
if($filter_teacher) $query_params .= "&teacher_id=$filter_teacher";

// --------------- EXCEL EXPORT HANDLER ---------------
if (isset($_GET['export']) && $_GET['export'] === 'excel' && $filter_class) {
    $filename = "Attendance_Report_" . $ethiopian_months[$selected_eth_month] . "_" . $selected_eth_year . ".xls";
    
    header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: max-age=0');
    
    echo '<?xml version="1.0" encoding="UTF-8"?>';
    echo '<?mso-application progid="Excel.Sheet"?>';
    ?>
    <Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet"
     xmlns:o="urn:schemas-microsoft-com:office:office"
     xmlns:x="urn:schemas-microsoft-com:office:excel"
     xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet"
     xmlns:html="http://www.w3.org/TR/REC-html40">
     <Styles>
      <Style ss:ID="header">
       <Alignment ss:Horizontal="Center" ss:Vertical="Center" ss:WrapText="1"/>
       <Borders><Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1"/></Borders>
       <Font ss:FontName="Nyala" ss:Size="11" ss:Bold="1" ss:Color="#FFFFFF"/>
       <Interior ss:Color="#8B4513" ss:Pattern="Solid"/>
      </Style>
      <Style ss:ID="present"><Font ss:Color="#10B981"/><Interior ss:Color="#D1FAE5" ss:Pattern="Solid"/></Style>
      <Style ss:ID="absent"><Font ss:Color="#EF4444"/><Interior ss:Color="#FEE2E2" ss:Pattern="Solid"/></Style>
      <Style ss:ID="permission"><Font ss:Color="#F59E0B"/><Interior ss:Color="#FEF3C7" ss:Pattern="Solid"/></Style>
      <Style ss:ID="closed"><Font ss:Color="#6B7280"/><Interior ss:Color="#E5E7EB" ss:Pattern="Solid"/></Style>
     </Styles>
     <Worksheet ss:Name="Attendance Report">
      <Table>
       <?php
       $visibleDays = [];
       foreach($month_days as $day) { if(!$day['is_closed']) $visibleDays[] = $day; }
       $totalCols = 2 + count($visibleDays);
       
       echo '<Row><Cell ss:MergeAcross="' . ($totalCols - 1) . '" ss:StyleID="header"><Data ss:Type="String">የአቴንዳንስ ሪፖርት - ' . $ethiopian_months[$selected_eth_month] . ' ' . $selected_eth_year . '</Data></Cell></Row>';
       echo '<Row><Cell ss:StyleID="header"><Data ss:Type="String">ተ.ቁ</Data></Cell><Cell ss:StyleID="header"><Data ss:Type="String">ስም</Data></Cell>';
       foreach($visibleDays as $day) {
           echo '<Cell ss:StyleID="header"><Data ss:Type="String">' . $day['day_am'] . ' ' . $day['eth_day'] . '</Data></Cell>';
       }
       echo '</Row>';
       
       foreach($students as $index => $student) {
           $sid = $student['id'];
           echo '<Row><Cell><Data ss:Type="Number">' . ($index + 1) . '</Data></Cell><Cell><Data ss:Type="String">' . htmlspecialchars($student['name']) . '</Data></Cell>';
           foreach($visibleDays as $day) {
               $stat = $attendance_data[$sid][$day['greg_date']] ?? null;
               $style = 'present'; $value = '✓';
               if($stat == 'absent') { $style = 'absent'; $value = '✗'; }
               elseif($stat == 'permission') { $style = 'permission'; $value = '◉'; }
               elseif(!$stat && $day['is_closed']) { $style = 'closed'; $value = '🚫'; }
               elseif(!$stat) { $value = '-'; }
               echo '<Cell ss:StyleID="' . $style . '"><Data ss:Type="String">' . $value . '</Data></Cell>';
           }
           echo '</Row>';
       }
       ?>
      </Table>
     </Worksheet>
    </Workbook>
    <?php
    exit;
}
$nav_active = 'attendance_controller';
?>
<!DOCTYPE html>
<html lang="am">
<head>
    <meta charset="UTF-8">
    <title>የአቴንዳንስ መቆጣጠሪያ | አጸደ ትጉሃን</title>
    <?php include 'pwa_head.php'; ?>
    <style>
        :root { --primary: #8B4513; --gold: #FFD700; --gold-dark: #DAA520; --pale: #FFF8DC; --success: #10B981; --danger: #EF4444; --warning: #F59E0B; --bg: #FAF9F6; --white: #FFFFFF; --gray-100: #F3F4F6; --gray-300: #D1D5DB; --closed-color: #9CA3AF; }
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Segoe UI', 'Nyala', sans-serif; }
        body { background: var(--bg); min-height: 100vh; padding-bottom: 20px; }
        
        .header { background: linear-gradient(135deg, #6B3410, #8B4513); color: white; padding: 12px 16px; position: sticky; top: 0; z-index: 100; }
        .header-top { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 8px; }
        .logo { display: flex; align-items: center; gap: 10px; }
        .logo-icon { width: 42px; height: 42px; background: linear-gradient(135deg, var(--gold), var(--gold-dark)); border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 22px; color: #5C2D0E; border: 2px solid white; }
        .logo h2 { font-size: 16px; color: var(--gold); }
        .logo span { font-size: 10px; opacity: 0.8; display: block; }
        .btn-back { color: var(--primary); background: var(--gold); padding: 6px 14px; border-radius: 20px; text-decoration: none; font-size: 12px; font-weight: 600; }
        .btn-excel { background: #217346; color: white; padding: 8px 18px; border-radius: 20px; text-decoration: none; font-size: 13px; font-weight: 600; display: inline-flex; align-items: center; gap: 6px; }

        .container { padding: 10px; max-width: 100%; }
        
        .filter-card { background: var(--white); border-radius: 12px; padding: 15px; margin-bottom: 10px; border: 2px solid var(--gold); }
        .filter-row { display: flex; gap: 10px; flex-wrap: wrap; align-items: flex-end; }
        .filter-group { flex: 1; min-width: 120px; }
        .filter-group label { display: block; font-size: 11px; color: var(--primary); font-weight: 600; margin-bottom: 4px; }
        .filter-group select { width: 100%; padding: 10px; border: 2px solid #E2E8F0; border-radius: 8px; font-size: 13px; }
        .btn-filter { padding: 10px 16px; background: var(--gold); color: var(--primary); border: none; border-radius: 8px; font-weight: 600; font-size: 13px; cursor: pointer; text-decoration: none; display: inline-block; }

        .stats-row { display: grid; grid-template-columns: repeat(4, 1fr); gap: 8px; margin-bottom: 10px; }
        .stat-card { background: var(--white); border-radius: 10px; padding: 12px 8px; text-align: center; border-left: 3px solid var(--gold); }
        .stat-val { font-size: 20px; font-weight: bold; color: var(--primary); }
        .stat-lbl { font-size: 9px; color: #666; }

        .month-card { background: var(--white); border-radius: 16px; padding: 15px; margin-bottom: 10px; border: 2px solid var(--gold); }
        .month-nav { display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; gap: 10px; }
        .month-nav a { padding: 10px 16px; background: var(--gold); color: var(--primary); border-radius: 25px; text-decoration: none; font-weight: 600; font-size: 13px; min-width: 80px; text-align: center; }
        .month-title { text-align: center; font-size: 20px; font-weight: bold; color: var(--primary); flex: 1; }
        .month-title .greg { font-size: 12px; color: #A52A2A; display: block; font-weight: normal; }

        .legend { display: flex; gap: 10px; flex-wrap: wrap; margin-bottom: 10px; font-size: 11px; padding: 10px; background: #F3F4F6; border-radius: 8px; }
        .legend-item { display: flex; align-items: center; gap: 5px; }
        .legend-dot { width: 14px; height: 14px; border-radius: 3px; display: inline-block; }
        .ld-present { background: var(--success); } .ld-absent { background: var(--danger); }
        .ld-permission { background: var(--warning); } .ld-closed { background: #9CA3AF; border: 1px solid #6B7280; }
        .ld-weekend { background: #D1FAE5; border: 2px solid var(--success); }

        .table-wrapper { background: var(--white); border-radius: 16px; overflow: hidden; border: 2px solid var(--gold); }
        .table-header-bar { padding: 10px 15px; background: #FEF3C7; border-bottom: 2px solid var(--gold); display: flex; justify-content: space-between; align-items: center; font-size: 13px; font-weight: 600; color: var(--primary); flex-wrap: wrap; gap: 8px; }
        .table-scroll { overflow-x: auto; max-height: 55vh; -webkit-overflow-scrolling: touch; }
        .table-scroll table { width: 100%; border-collapse: collapse; min-width: 600px; }
        .table-scroll thead th { background: var(--primary); color: white; padding: 8px 4px; text-align: center; border: 1px solid var(--gold-dark); font-size: 10px; position: sticky; top: 0; z-index: 3; white-space: nowrap; }
        .table-scroll th:first-child, .table-scroll td:first-child { position: sticky; left: 0; background: white; z-index: 2; min-width: 32px; }
        .table-scroll th:nth-child(2), .table-scroll td:nth-child(2) { position: sticky; left: 32px; background: white; z-index: 2; min-width: 120px; text-align: left; }
        .table-scroll thead th:first-child, .table-scroll thead th:nth-child(2) { z-index: 4; background: var(--primary); }
        .table-scroll tbody td { padding: 6px 3px; text-align: center; border-bottom: 1px solid #E5E7EB; font-size: 10px; }
        .table-scroll tbody tr:hover { background: #FFF8DC; }
        .table-scroll tbody tr:hover td:first-child, .table-scroll tbody tr:hover td:nth-child(2) { background: #FFF8DC; }
        .student-name { font-weight: 600; color: var(--primary); font-size: 11px; }
        .day-col { min-width: 42px; }
        .day-col.weekend { background: #F0FDF4; }
        .day-col.today { background: #FFFBEB !important; }
        .day-col.future { background: #FEF2F2; opacity: 0.4; }
        .day-col.closed { background: #E5E7EB !important; opacity: 0.6; }
        .day-num { font-weight: bold; font-size: 12px; }
        .day-name { font-size: 8px; }
        .day-name.sat, .day-name.sun { color: #059669; }
        .day-name.wd { color: #9CA3AF; }
        .logo-img {
            width: 50px; height: 50px; border-radius: 50%; object-fit: cover;
            border: 3px solid var(--gold-primary); background: white;
        }
        .status-dot { width: 14px; height: 14px; border-radius: 50%; display: inline-block; }
        .status-present { background: var(--success); }
        .status-absent { background: var(--danger); }
        .status-permission { background: var(--warning); }
        .status-closed { background: #9CA3AF; }
        
        .empty-state { text-align: center; padding: 50px 20px; color: #999; background: white; border-radius: 16px; }

        @media (min-width: 768px) { .container { max-width: 1400px; margin: 15px auto; } .day-col { min-width: 48px; } .stats-row { grid-template-columns: repeat(4, 1fr); } }
        @media (max-width: 768px) {
            .stats-row { grid-template-columns: repeat(2, 1fr); gap: 6px; }
            .stat-card { padding: 8px 6px; }
            .stat-val { font-size: 18px; }
            .month-card { padding: 12px 10px; }
            .month-title { font-size: 16px; }
            .month-nav a { padding: 8px 10px; font-size: 11px; min-width: 60px; }
            .filter-card { padding: 10px; }
            .btn-excel { font-size: 11px; padding: 6px 12px; }
        }
        @media (max-width: 480px) {
            .table-scroll th:nth-child(2), .table-scroll td:nth-child(2) { min-width: 90px; font-size: 9.5px; }
            .month-nav { gap: 4px; }
            .month-title { font-size: 14px; }
            .month-nav a { padding: 6px 8px; font-size: 10px; min-width: 50px; }
        }
    </style>
</head>
<body>
    <?php include 'mobile_nav.php'; ?>

    <div class="main-container">
        <!-- Filters -->
        <div class="filter-card">
            <form method="GET" class="filter-row">
                <input type="hidden" name="eth_month" value="<?php echo $selected_eth_month; ?>">
                <input type="hidden" name="eth_year" value="<?php echo $selected_eth_year; ?>">
                <div class="filter-group">
                    <label>📚 ክፍል</label>
                    <select name="class_id" onchange="this.form.submit()">
                        <option value="">ሁሉም</option>
                        <?php
                        mysqli_data_seek($classes, 0);
                        $grouped = [];
                        while($c = mysqli_fetch_assoc($classes)) {
                            $grouped[$c['division_name'] ?: 'ያልተመደበ']['sort'] = $c['sort_order'] ?? 99;
                            $grouped[$c['division_name'] ?: 'ያልተመደበ']['classes'][] = $c;
                        }
                        uasort($grouped, fn($a, $b) => $a['sort'] <=> $b['sort']);
                        foreach ($grouped as $divName => $g): ?>
                        <optgroup label="<?php echo htmlspecialchars($divName); ?>">
                            <?php foreach ($g['classes'] as $c): ?>
                            <option value="<?php echo $c['id']; ?>" <?php echo $filter_class == $c['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($c['name']); ?></option>
                            <?php endforeach; ?>
                        </optgroup>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="filter-group">
                    <label>👨‍🏫 መምህር</label>
                    <select name="teacher_id" onchange="this.form.submit()">
                        <option value="">ሁሉም</option>
                        <?php mysqli_data_seek($teachers, 0); while($t = mysqli_fetch_assoc($teachers)): ?>
                        <option value="<?php echo $t['id']; ?>" <?php echo $filter_teacher == $t['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($t['name']); ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <a href="attendance_controller.php" class="btn-filter">🔄 አጽዳ</a>
            </form>
        </div>

        <!-- Statistics -->
        <?php
        $stat_total = (int)($stats['total'] ?? 0);
        $pct = function($n) use ($stat_total) { return $stat_total > 0 ? round(($n / $stat_total) * 100) : 0; };
        ?>
        <div class="stats-row">
            <div class="stat-card"><div class="stat-val"><?php echo $stat_total; ?></div><div class="stat-lbl">ጠቅላላ</div></div>
            <div class="stat-card"><div class="stat-val" style="color:#10B981;"><?php echo $stats['present'] ?? 0; ?><?php if($stat_total): ?><span style="font-size:12px;color:#6B7280;"> (<?php echo $pct($stats['present'] ?? 0); ?>%)</span><?php endif; ?></div><div class="stat-lbl">✅ ተገኝቷል</div></div>
            <div class="stat-card"><div class="stat-val" style="color:#EF4444;"><?php echo $stats['absent'] ?? 0; ?><?php if($stat_total): ?><span style="font-size:12px;color:#6B7280;"> (<?php echo $pct($stats['absent'] ?? 0); ?>%)</span><?php endif; ?></div><div class="stat-lbl">❌ አልተገኘም</div></div>
            <div class="stat-card"><div class="stat-val" style="color:#F59E0B;"><?php echo $stats['permission'] ?? 0; ?><?php if($stat_total): ?><span style="font-size:12px;color:#6B7280;"> (<?php echo $pct($stats['permission'] ?? 0); ?>%)</span><?php endif; ?></div><div class="stat-lbl">📝 በፈቃድ</div></div>
        </div>

        <!-- Month Navigation -->
        <div class="month-card">
            <div class="month-nav">
                <a href="?eth_month=<?php echo $prev_m; ?>&eth_year=<?php echo $prev_y; ?><?php echo $query_params; ?>">← ቀዳሚ</a>
                <div class="month-title"><?php echo $ethiopian_months[$selected_eth_month] . ' ' . $selected_eth_year; ?> ዓ.ም<span class="greg">(<?php echo $month_start_greg ? date('F Y', strtotime($month_start_greg)) : ''; ?>)</span></div>
                <a href="?eth_month=<?php echo $next_m; ?>&eth_year=<?php echo $next_y; ?><?php echo $query_params; ?>">ቀጣይ →</a>
            </div>
            <div class="legend">
                <div class="legend-item"><span class="legend-dot ld-weekend"></span> ቅዳሜ/እሁድ</div>
                <div class="legend-item"><span class="legend-dot ld-present"></span> ✅ ተገኝቷል</div>
                <div class="legend-item"><span class="legend-dot ld-absent"></span> ❌ አልተገኘም</div>
                <div class="legend-item"><span class="legend-dot ld-permission"></span> 📝 በፈቃድ</div>
                <div class="legend-item"><span class="legend-dot" style="background:#3B82F6;"></span> ⏰ ዘግይቷል</div>
                <div class="legend-item"><span class="legend-dot" style="background:#8B5CF6;"></span> 📄 በምክንያት</div>
                <div class="legend-item"><span class="legend-dot ld-closed"></span> 🚫 ትምህርት የለም</div>
            </div>
        </div>

        <!-- Attendance Table -->
        <?php if($filter_class && !empty($students)): ?>
        <div class="table-wrapper">
            <div class="table-header-bar">
                <span>📋 ወርሃዊ የአቴንዳንስ ሰንጠረዥ (ቅዳሜ & እሁድ)</span>
                <span style="font-size:11px;">👥 <?php echo count($students); ?> ተማሪዎች | 📅 <?php echo count($month_days); ?> ቀናት</span>
            </div>
            <div class="table-scroll table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>የተማሪ ስም</th>
                            <?php foreach($month_days as $day): 
                                $colClass = 'weekend';
                                if($day['is_today']) $colClass .= ' today';
                                if($day['is_future']) $colClass .= ' future';
                                if($day['is_closed']) $colClass .= ' closed';
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
                        <tr>
                            <td><?php echo $index + 1; ?></td>
                            <td class="student-name"><?php echo htmlspecialchars($student['name']); ?></td>
                            <?php foreach($month_days as $day): 
                                $colClass = 'weekend';
                                if($day['is_today']) $colClass .= ' today';
                                if($day['is_future']) $colClass .= ' future';
                                if($day['is_closed']) $colClass .= ' closed';
                                
                                $stat = $attendance_data[$sid][$day['greg_date']] ?? null;
                            ?>
                            <td class="day-col <?php echo $colClass; ?>">
                                <?php if($day['is_closed']): ?>
                                    <span class="status-dot status-closed" title="ትምህርት የለም"></span>
                                <?php elseif($stat == 'present'): ?>
                                    <span class="status-dot status-present" title="✅ ተገኝቷል"></span>
                                <?php elseif($stat == 'absent'): ?>
                                    <span class="status-dot status-absent" title="❌ አልተገኘም"></span>
                                <?php elseif($stat == 'permission'): ?>
                                    <span class="status-dot status-permission" title="📝 በፈቃድ"></span>
                                <?php elseif($stat == 'late'): ?>
                                    <span class="status-dot" style="background:#3B82F6;" title="⏰ ዘግይቷል"></span>
                                <?php elseif($stat == 'excused'): ?>
                                    <span class="status-dot" style="background:#8B5CF6;" title="📄 በምክንያት"></span>
                                <?php elseif(!$day['is_future']): ?>
                                    <span class="status-dot" style="background:#F3F4F6; border:1px solid #D1D5DB;"></span>
                                <?php endif; ?>
                            </td>
                            <?php endforeach; ?>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php else: ?>
        <div class="empty-state"><span class="icon">📊</span><h3>እባክዎ ክፍል ይምረጡ</h3><p>የአቴንዳንስ ሪፖርት ለማየት መጀመሪያ ክፍል መምረጥ ያስፈልጋል</p></div>
        <?php endif; ?>
    </div>
</body>
</html>
<?php mysqli_close($conn); ?>