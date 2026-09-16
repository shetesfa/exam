<?php
require_once 'db.php';
requireLogin();

if (!isTeacher()) {
    header("Location: index.php");
    exit();
}

$teacher_id = intval($_SESSION['user_id'] ?? 0);
$user_name = $_SESSION['user_name'] ?? '';

$current_semester = getCurrentSemester($conn);
$semester_id = $current_semester ? intval($current_semester['id']) : 0;

$classes = getTeacherClasses($conn, $teacher_id, $semester_id);

// If teacher only teaches children classes (Grades 1-6), seamlessly redirect to children attendance page
$hasYouthClasses = false;
foreach ($classes as $c) {
    $cid = intval($c['class_id'] ?? 0);
    if ($cid >= 1 && $cid <= 6) {
        $hasYouthClasses = true;
        break;
    }
}
if (!$hasYouthClasses && !empty($classes) && !isAdmin()) {
    $firstCid = intval($classes[0]['class_id'] ?? 0);
    header("Location: dashboard_attendance.php" . ($firstCid ? "?c={$firstCid}" : ""));
    exit();
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

$today_eth = getCurrentEthiopianDate();
$selected_eth_month = isset($_GET['m']) ? intval($_GET['m']) : intval($today_eth['month']);
$selected_eth_year = isset($_GET['y']) ? intval($_GET['y']) : intval($today_eth['year']);
$selected_class_id = isset($_GET['c']) ? intval($_GET['c']) : (!empty($classes) ? intval($classes[0]['class_id']) : 0);

// IDOR Protection: ensure selected class is assigned to this teacher
$class_valid = false;
foreach ($classes as $c) {
    if (intval($c['class_id']) === $selected_class_id) {
        $class_valid = true;
        break;
    }
}
if (!$class_valid && !isAdmin()) {
    $selected_class_id = !empty($classes) ? intval($classes[0]['class_id']) : 0;
}

$can_record = function_exists('canTeacherMarkClassAttendance') ? canTeacherMarkClassAttendance($conn, $teacher_id, $selected_class_id, $semester_id) : false;

// Validate
if ($selected_eth_month < 1 || $selected_eth_month > 13) {
    $selected_eth_month = intval($today_eth['month']);
}
if ($selected_eth_year < 2000 || $selected_eth_year > 2100) {
    $selected_eth_year = intval($today_eth['year']);
}

// Build Ethiopian month calendar
$days_in_month = getEthiopianDaysInMonth($selected_eth_year, $selected_eth_month);
$month_days = [];
$month_start_greg = '';

for ($d = 1; $d <= $days_in_month; $d++) {
    $greg_str = ethiopianToGregorian($selected_eth_year, $selected_eth_month, $d);
    if (!$greg_str) continue;
    
    $dow = date('l', strtotime($greg_str));
    if (empty($month_start_greg)) $month_start_greg = $greg_str;
    
    if ($dow !== 'Saturday' && $dow !== 'Sunday') {
        continue;
    }
    
    $month_days[] = [
        'eth_day' => $d,
        'greg_date' => $greg_str,
        'day_name' => $dow,
        'day_am' => $amharic_days[$dow] ?? substr($dow, 0, 3),
        'is_weekend' => true,
        'is_today' => ($greg_str === date('Y-m-d')),
        'is_future' => ($greg_str > date('Y-m-d'))
    ];
}

// Get closed days
$closed_days = [];
if ($selected_class_id > 0 && !empty($month_days)) {
    $first_date = $month_days[0]['greg_date'];
    $last_date = $month_days[count($month_days)-1]['greg_date'];
    
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

foreach ($month_days as &$day) {
    $day['is_closed'] = isset($closed_days[$day['greg_date']]);
}
unset($day);

// Get students and attendance - ALL past days
$students = [];
$attendance_data = [];
if ($selected_class_id > 0 && !empty($month_days)) {
    $students = dbFetchAll(
        $conn,
        "SELECT * FROM students WHERE class_id = ? AND (is_deleted = 0 OR is_deleted IS NULL) ORDER BY name",
        "i",
        [$selected_class_id]
    );
    
    if (!empty($students)) {
        $first_date = $month_days[0]['greg_date'];
        $last_date = $month_days[count($month_days)-1]['greg_date'];
        
        $rec_query = "SELECT ar.student_id, ar.attendance_date, ar.status, u.name as submitter 
                      FROM attendance_records ar
                      LEFT JOIN users u ON ar.marked_by = u.id
                      WHERE ar.class_id = ? AND ar.attendance_date BETWEEN ? AND ?";
        $records = dbFetchAll($conn, $rec_query, "iss", [$selected_class_id, $first_date, $last_date]);
        foreach ($records as $r) {
            $attendance_data[$r['student_id']][$r['attendance_date']] = $r;
        }
    }
}

// Navigation with short params
$prev_m = $selected_eth_month - 1; 
$prev_y = $selected_eth_year;
if ($prev_m < 1) { $prev_m = 13; $prev_y--; }

$next_m = $selected_eth_month + 1;
$next_y = $selected_eth_year;
if ($next_m > 13) { $next_m = 1; $next_y++; }

$today_month = intval($today_eth['month']);
$today_year = intval($today_eth['year']);

function buildTUrl($m, $y, $c) {
    return "teacher_attendance_view.php?m={$m}&y={$y}&c={$c}";
}

$display_greg_date = !empty($month_days) ? $month_days[0]['greg_date'] : $month_start_greg;
$nav_active = 'teacher_attendance_view';
?>
<!DOCTYPE html>
<html lang="am">
<head>
    <meta charset="UTF-8">
    <meta name="theme-color" content="#8B4513">
    <title>የአቴንዳንስ እይታ | አጸደ ትጉሃን</title>
    <?php include 'pwa_head.php'; ?>
    <style>
        :root { --primary: #8B4513; --gold: #FFD700; --gold-dark: #DAA520; --pale: #FFF8DC; --success: #10B981; --danger: #EF4444; --warning: #F59E0B; --bg: #FAF9F6; --white: #FFFFFF; --gray-100: #F3F4F6; }
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Segoe UI', 'Nyala', sans-serif; }
        body { background: var(--bg); min-height: 100vh; padding-bottom: 20px; }
        
        .header { background: linear-gradient(135deg, #6B3410, #8B4513); color: white; padding: 12px 16px; position: sticky; top: 0; z-index: 100; }
        .header-top { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 8px; }
        .logo { display: flex; align-items: center; gap: 10px; }
        .logo-img { width: 42px; height: 42px; border-radius: 50%; object-fit: cover; border: 2px solid var(--gold); }
        .logo h2 { font-size: 16px; color: var(--gold); }
        .logo span { font-size: 10px; opacity: 0.8; display: block; }
        .user-badge { background: rgba(255,255,255,0.15); padding: 6px 14px; border-radius: 20px; border: 1px solid var(--gold); font-size: 12px; display: flex; align-items: center; gap: 8px; }
        .user-badge strong { color: var(--gold); }
        .btn-back { color: var(--primary); background: var(--gold); padding: 6px 14px; border-radius: 20px; text-decoration: none; font-size: 12px; font-weight: 600; }

        .container { padding: 10px; max-width: 100%; }
        
        .class-bar { background: var(--white); border-radius: 12px; padding: 10px; margin-bottom: 10px; border: 2px solid var(--gold); display: flex; gap: 8px; overflow-x: auto; -webkit-overflow-scrolling: touch; }
        .class-bar::-webkit-scrollbar { display: none; }
        .class-chip { padding: 10px 18px; border-radius: 25px; font-size: 13px; font-weight: 600; text-decoration: none; white-space: nowrap; border: 2px solid var(--gold-dark); color: var(--primary); background: white; transition: all 0.2s; }
        .class-chip.active { background: var(--gold); border-color: var(--primary); }
        .class-chip:hover { background: var(--pale); }
        .class-chip .count { background: var(--primary); color: white; padding: 2px 8px; border-radius: 15px; margin-left: 5px; font-size: 10px; }

        .month-card { background: var(--white); border-radius: 16px; padding: 15px; margin-bottom: 10px; border: 2px solid var(--gold); }
        .month-nav { display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; gap: 10px; }
        .month-nav a { 
            padding: 12px 20px; background: var(--gold); color: var(--primary); 
            border-radius: 25px; text-decoration: none; font-weight: 700; font-size: 14px; 
            min-width: 100px; text-align: center; transition: all 0.2s; 
            display: inline-block; cursor: pointer; border: 2px solid var(--gold-dark);
        }
        .month-nav a:hover { background: var(--gold-dark); color: white; transform: translateY(-2px); }
        .month-nav .btn-today { background: var(--warning); color: white; border-color: var(--warning); }
        .month-title { text-align: center; font-size: 20px; font-weight: bold; color: var(--primary); flex: 1; line-height: 1.4; }
        .month-title .greg { font-size: 12px; color: #A52A2A; display: block; font-weight: normal; margin-top: 4px; }

        .legend { display: flex; gap: 10px; flex-wrap: wrap; margin-bottom: 10px; font-size: 11px; padding: 10px; background: #F3F4F6; border-radius: 8px; }
        .legend-item { display: flex; align-items: center; gap: 5px; }
        .legend-dot { width: 14px; height: 14px; border-radius: 3px; display: inline-block; }
        .ld-present { background: var(--success); } .ld-absent { background: var(--danger); }
        .ld-permission { background: var(--warning); } .ld-closed { background: #9CA3AF; border: 2px solid #6B7280; }
        .ld-past { background: #DBEAFE; border: 2px solid #3B82F6; }

        .info-bar { background: var(--white); border-radius: 12px; padding: 12px 15px; margin-bottom: 10px; border: 2px solid var(--gold); display: flex; justify-content: space-between; flex-wrap: wrap; gap: 8px; font-size: 13px; color: var(--primary); font-weight: 600; }

        .table-wrapper { background: var(--white); border-radius: 16px; overflow: hidden; border: 2px solid var(--gold); }
        .table-header-bar { padding: 10px 15px; background: #FEF3C7; border-bottom: 2px solid var(--gold); display: flex; justify-content: space-between; font-size: 13px; font-weight: 600; color: var(--primary); flex-wrap: wrap; gap: 8px; }
        .table-scroll { overflow-x: auto; max-height: 55vh; -webkit-overflow-scrolling: touch; }
        .table-scroll table { width: 100%; border-collapse: collapse; min-width: 600px; }
        .table-scroll thead th { background: #5C2D0E; color: #FFD700; padding: 10px 6px; text-align: center; border: 1px solid var(--gold-dark); font-size: 11px; position: sticky; top: 0; z-index: 3; white-space: nowrap; font-weight: 700; }
        .table-scroll th:first-child, .table-scroll td:first-child { position: sticky; left: 0; background: white; z-index: 2; min-width: 32px; }
        .table-scroll th:nth-child(2), .table-scroll td:nth-child(2) { position: sticky; left: 32px; background: white; z-index: 2; min-width: 130px; text-align: left; }
        .table-scroll thead th:first-child, .table-scroll thead th:nth-child(2) { z-index: 4; background: #5C2D0E; }
        .table-scroll tbody td { padding: 6px 4px; text-align: center; border-bottom: 1px solid #E5E7EB; font-size: 10px; }
        .table-scroll tbody tr:hover { background: #FFF8DC; }
        .table-scroll tbody tr:hover td:first-child, .table-scroll tbody tr:hover td:nth-child(2) { background: #FFF8DC; }
        .student-name { font-weight: 600; color: var(--primary); font-size: 11px; }
        .day-col { min-width: 52px; }
        .day-col.weekend { background: #F0FDF4; }
        .day-col.today { background: #FFFBEB !important; border: 2px solid #F59E0B !important; }
        .day-col.closed { background: #E5E7EB !important; opacity: 0.6; }
        .day-col.past { background: #EFF6FF; }
        .day-num { font-weight: bold; font-size: 14px; color: #1a1a1a; }
        .day-name { font-size: 9px; color: #333; font-weight: 600; }
        .day-name.sat, .day-name.sun { color: #059669; font-weight: 700; font-size: 10px; }

        .status-dot { width: 16px; height: 16px; border-radius: 50%; display: inline-block; }
        .status-present { background: var(--success); }
        .status-absent { background: var(--danger); }
        .status-permission { background: var(--warning); }
        .status-closed { background: #9CA3AF; }
        .status-empty { background: #F3F4F6; border: 1px solid #D1D5DB; }
        .submitted-by { font-size: 7px; color: #666; display: block; margin-top: 1px; }
        
        .empty-state { text-align: center; padding: 50px 20px; color: #999; background: white; border-radius: 16px; }
        .empty-state .icon { font-size: 50px; display: block; margin-bottom: 15px; }

        @media (min-width: 768px) { .container { max-width: 1400px; margin: 15px auto; } .day-col { min-width: 60px; } .status-dot { width: 18px; height: 18px; } }
    </style>
</head>
<body>
    <?php include 'mobile_nav.php'; ?>

    <div class="main-container">
        <?php if(!empty($classes)): ?>
        <div class="class-bar">
            <?php foreach($classes as $class): ?>
            <a href="<?php echo buildTUrl($selected_eth_month, $selected_eth_year, $class['class_id']); ?>" 
               class="class-chip <?php echo $selected_class_id == $class['class_id'] ? 'active' : ''; ?>">
                📖 <?php echo htmlspecialchars($class['class_name']); ?>
                <span class="count"><?php echo $class['student_count']; ?></span>
            </a>
            <?php endforeach; ?>
        </div>

        <div class="month-card">
            <div class="month-nav">
                <a href="<?php echo buildTUrl($prev_m, $prev_y, $selected_class_id); ?>">← ቀዳሚ</a>
                <div class="month-title">
                    <?php echo $ethiopian_months[$selected_eth_month] . ' ' . $selected_eth_year; ?> ዓ.ም
                    <span class="greg">(ቅዳሜ & እሁድ<?php echo $display_greg_date ? ' - ' . date('F Y', strtotime($display_greg_date)) : ''; ?>)</span>
                </div>
                <div style="display:inline-flex; gap:6px; align-items:center;">
                    <a href="<?php echo buildTUrl($next_m, $next_y, $selected_class_id); ?>">ቀጣይ →</a>
                    <a href="<?php echo buildTUrl($today_month, $today_year, $selected_class_id); ?>" class="btn-today">📅 ዛሬ</a>
                </div>
            </div>
            <div class="legend">
                <div class="legend-item"><span class="legend-dot ld-present"></span> ✅ ተገኝቷል</div>
                <div class="legend-item"><span class="legend-dot ld-absent"></span> ❌ አልተገኘም</div>
                <div class="legend-item"><span class="legend-dot ld-permission"></span> 📝 በፈቃድ</div>
                <div class="legend-item"><span class="legend-dot ld-past"></span> 🔹 ያለፈ ቀን</div>
                <div class="legend-item"><span class="legend-dot ld-closed"></span> 🚫 ትምህርት የለም</div>
            </div>
        </div>

        <?php if($selected_class_id && !empty($students)): ?>
        <div class="info-bar" style="align-items: center;">
            <div>
                <span>📖 <strong><?php 
                    $className = '';
                    foreach($classes as $c) {
                        if($c['class_id'] == $selected_class_id) { $className = $c['class_name']; break; }
                    }
                    echo htmlspecialchars($className);
                ?></strong></span> &bull;
                <span>👥 <?php echo count($students); ?> ተማሪዎች</span> &bull;
                <span>📅 <?php echo count($month_days); ?> ቀናት</span>
            </div>
            <?php if ($can_record): ?>
                <a href="dashboard_attendance.php?c=<?php echo $selected_class_id; ?>" class="btn-action" style="background: linear-gradient(135deg, #10B981 0%, #059669 100%); color: white; padding: 7px 16px; border-radius: 20px; text-decoration: none; font-size: 13px; font-weight: 700; display: inline-flex; align-items: center; gap: 6px; box-shadow: 0 3px 8px rgba(16,185,129,0.3); border: none;">
                    ✍️ አቴንዳንስ መዝግብ
                </a>
            <?php endif; ?>
        </div>

        <div class="table-wrapper">
            <div class="table-header-bar" style="align-items: center;">
                <span>📋 ወርሃዊ የአቴንዳንስ ሪፖርት (ቅዳሜ & እሁድ)</span>
                <?php if ($can_record): ?>
                    <a href="dashboard_attendance.php?c=<?php echo $selected_class_id; ?>" style="color: #065F46; background: #D1FAE5; padding: 4px 12px; border-radius: 15px; text-decoration: none; font-size: 11px; font-weight: 700; display: inline-flex; align-items: center; gap: 4px; border: 1px solid #10B981;">
                        ✍️ አቴንዳንስ መመዝገቢያ ገጽ ክፈት
                    </a>
                <?php else: ?>
                    <span style="font-size:11px;">👁️ ተመልካች ብቻ</span>
                <?php endif; ?>
            </div>
            <div class="table-scroll">
                <table>
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>የተማሪ ስም</th>
                            <?php foreach($month_days as $day): 
                                $colClass = 'weekend';
                                if($day['is_today']) $colClass .= ' today';
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
                        <tr>
                            <td><?php echo $index + 1; ?></td>
                            <td class="student-name"><?php echo htmlspecialchars($student['name']); ?></td>
                            <?php foreach($month_days as $day): 
                                $att = $attendance_data[$sid][$day['greg_date']] ?? null;
                                $colClass = 'weekend';
                                if($day['is_today']) $colClass .= ' today';
                                if($day['is_closed']) $colClass .= ' closed';
                                if(!$day['is_future'] && !$day['is_today']) $colClass .= ' past';
                            ?>
                            <td class="day-col <?php echo $colClass; ?>">
                                <?php if($day['is_closed']): ?>
                                    <span class="status-dot status-closed" title="ትምህርት የለም"></span>
                                <?php elseif($day['is_future']): ?>
                                    <span class="status-dot status-empty" title="የወደፊት"></span>
                                <?php elseif($att): ?>
                                    <span class="status-dot <?php echo 'status-' . $att['status']; ?>" title="<?php echo $att['status']; ?>"></span>
                                    <span class="submitted-by"><?php echo htmlspecialchars(mb_substr($att['submitter'] ?? '', 0, 8)); ?></span>
                                <?php else: ?>
                                    <span class="status-dot status-empty" title="አልተመዘገበም"></span>
                                <?php endif; ?>
                            </td>
                            <?php endforeach; ?>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php elseif($selected_class_id): ?>
        <div class="empty-state"><span class="icon">👥</span><h3>ምንም ተማሪዎች የሉም</h3></div>
        <?php endif; ?>
        <?php else: ?>
        <div class="empty-state"><span class="icon">📚</span><h3>ምንም የተመደቡ ክፍሎች የሉም</h3></div>
        <?php endif; ?>
    </div>
    <script src="exam-main/assets/js/offline-db.js"></script>
    <script src="exam-main/assets/js/sync-manager.js"></script>
    <script>
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', () => {
                navigator.serviceWorker.register('/exam/sw.js').catch(() => {});
            });
        }
        // Auto sync and background check
        SyncManager.fullSync();
    </script>
</body>
</html>
<?php mysqli_close($conn); ?>