<?php
session_start();
require_once 'db.php';
requireLogin();

if(!isTeacher()) {
    header("Location: index.php");
    exit();
}

$teacher_id = $_SESSION['user_id'];
$user_name = $_SESSION['user_name'];

$current_semester = getCurrentSemester($conn);
$semester_id = $current_semester ? $current_semester['id'] : 0;

$classes = getTeacherClasses($conn, $teacher_id, $semester_id);

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
// FIXED: Use 'm' and 'y' as parameter names
$selected_eth_month = isset($_GET['m']) ? intval($_GET['m']) : $today_eth['month'];
$selected_eth_year = isset($_GET['y']) ? intval($_GET['y']) : $today_eth['year'];
$selected_class_id = isset($_GET['c']) ? intval($_GET['c']) : (!empty($classes) ? $classes[0]['class_id'] : 0);

// Validate
if($selected_eth_month < 1 || $selected_eth_month > 13) {
    $selected_eth_month = $today_eth['month'];
}
if($selected_eth_year < 2000 || $selected_eth_year > 2100) {
    $selected_eth_year = $today_eth['year'];
}

// Build Ethiopian month calendar
$days_in_month = getEthiopianDaysInMonth($selected_eth_year, $selected_eth_month);
$month_days = [];

$greg_year = $selected_eth_year + 7;
$eth_new_year = new DateTime("$greg_year-09-11");
if($greg_year % 4 == 3) {
    $eth_new_year = new DateTime("$greg_year-09-12");
}

$month_offset = ($selected_eth_month - 1) * 30;
$month_start_greg = '';

for($d = 1; $d <= $days_in_month; $d++) {
    $day_offset = $month_offset + ($d - 1);
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
if($selected_class_id && !empty($month_days)) {
    $first_date = $month_days[0]['greg_date'];
    $last_date = $month_days[count($month_days)-1]['greg_date'];
    
    $cd_query = "SELECT date_gregorian FROM attendance_days WHERE date_gregorian BETWEEN '$first_date' AND '$last_date' AND is_school_day = 0 AND (class_id IS NULL OR class_id = $selected_class_id)";
    $cd_result = mysqli_query($conn, $cd_query);
    if($cd_result) {
        while($row = mysqli_fetch_assoc($cd_result)) {
            $closed_days[$row['date_gregorian']] = true;
        }
    }
}

foreach($month_days as &$day) {
    $day['is_closed'] = isset($closed_days[$day['greg_date']]);
}
unset($day);

// Get students and attendance - ALL past days
$students = [];
$attendance_data = [];
if($selected_class_id) {
    $sq = "SELECT * FROM students WHERE class_id = $selected_class_id ORDER BY name";
    $sr = mysqli_query($conn, $sq);
    while($s = mysqli_fetch_assoc($sr)) { $students[] = $s; }
    
    foreach($students as $st) {
        foreach($month_days as $day) {
            if(!$day['is_future']) {
                $aq = "SELECT ar.status, u.name as submitter FROM attendance_records ar
                       LEFT JOIN users u ON ar.marked_by = u.id
                       WHERE ar.student_id = {$st['id']} AND ar.class_id = $selected_class_id
                       AND ar.attendance_date = '{$day['greg_date']}' LIMIT 1";
                $ar = mysqli_query($conn, $aq);
                if($ar && mysqli_num_rows($ar) > 0) {
                    $att = mysqli_fetch_assoc($ar);
                    $attendance_data[$st['id']][$day['greg_date']] = $att;
                }
            }
        }
    }
}

// Navigation with short params
$prev_m = $selected_eth_month - 1; 
$prev_y = $selected_eth_year;
if($prev_m < 1) { $prev_m = 13; $prev_y--; }

$today_month = $today_eth['month'];
$today_year = $today_eth['year'];

function buildTUrl($m, $y, $c) {
    return "teacher_attendance_view.php?m={$m}&y={$y}&c={$c}";
}

$display_greg_date = !empty($month_days) ? $month_days[0]['greg_date'] : $month_start_greg;
?>
<!DOCTYPE html>
<html lang="am">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <link rel="icon" type="image/png" href="images/icon.png">
    <title>የመገኘት እይታ | አጸደ ትጉሃን</title>
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
    <div class="header">
        <div class="header-top">
            <div class="logo">
                <img src="images/icon.png" alt="Logo" class="logo-img" onerror="this.style.display='none'; this.insertAdjacentHTML('afterend','⛪');">
                <div><h2>አጸደ ትጉሃን</h2><span>የመገኘት እይታ (Read Only)</span></div>
            </div>
            <div class="user-badge">
                <span>👨‍🏫</span><strong><?php echo htmlspecialchars(mb_substr($user_name, 0, 15)); ?></strong>
                <a href="dashboard_teacher.php" class="btn-back">← ዳሽቦርድ</a>
            </div>
        </div>
    </div>

    <div class="container">
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
                <a href="<?php echo buildTUrl($today_month, $today_year, $selected_class_id); ?>" class="btn-today">📅 ዛሬ</a>
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
        <div class="info-bar">
            <span>📖 <strong><?php 
                $className = '';
                foreach($classes as $c) {
                    if($c['class_id'] == $selected_class_id) { $className = $c['class_name']; break; }
                }
                echo htmlspecialchars($className);
            ?></strong></span>
            <span>👥 <?php echo count($students); ?> ተማሪዎች</span>
            <span>📅 <?php echo count($month_days); ?> ቀናት</span>
        </div>

        <div class="table-wrapper">
            <div class="table-header-bar">
                <span>📋 ወርሃዊ የመገኘት ሪፖርት (ቅዳሜ & እሁድ)</span>
                <span style="font-size:11px;">👁️ ተመልካች ብቻ</span>
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
</body>
</html>
<?php mysqli_close($conn); ?>