<?php
session_start();
require_once 'db.php';
requireAdmin();

$message = '';
$error = '';

// Ethiopian months
$ethiopian_months = [
    1 => 'መስከረም', 2 => 'ጥቅምት', 3 => 'ኅዳር', 4 => 'ታኅሣሥ',
    5 => 'ጥር', 6 => 'የካቲት', 7 => 'መጋቢት', 8 => 'ሚያዝያ',
    9 => 'ግንቦት', 10 => 'ሰኔ', 11 => 'ሐምሌ', 12 => 'ነሐሴ', 13 => 'ጳጉሜን'
];

// Get current Ethiopian date
$today_eth = getCurrentEthiopianDate();
$selected_eth_month = isset($_GET['m']) ? intval($_GET['m']) : $today_eth['month'];
$selected_eth_year = isset($_GET['y']) ? intval($_GET['y']) : $today_eth['year'];
$selected_class = isset($_GET['c']) ? intval($_GET['c']) : 0;

// Validate
if($selected_eth_month < 1 || $selected_eth_month > 13) {
    $selected_eth_month = $today_eth['month'];
}
if($selected_eth_year < 2000 || $selected_eth_year > 2100) {
    $selected_eth_year = $today_eth['year'];
}

// Get classes
$classes_query = "SELECT * FROM classes ORDER BY name";
$classes = mysqli_query($conn, $classes_query);

// Handle toggle day - ALLOW PAST AND FUTURE DAYS
if(isset($_POST['toggle_day'])) {
    $date_gregorian = mysqli_real_escape_string($conn, $_POST['date_gregorian']);
    $class_id = intval($_POST['class_id']);
    $current_status = intval($_POST['current_status']);
    $new_status = $current_status ? 0 : 1;
    
    // Get Ethiopian date info
    $eth_date = getEthiopianDateFromGregorian($date_gregorian);
    
    if($class_id > 0) {
        // Per-class toggle
        mysqli_query($conn, "DELETE FROM attendance_days WHERE date_gregorian = '$date_gregorian' AND class_id = $class_id");
        
        // Check global entry
        $check_global = mysqli_query($conn, "SELECT is_school_day FROM attendance_days WHERE date_gregorian = '$date_gregorian' AND class_id IS NULL");
        $global_open = true;
        if(mysqli_num_rows($check_global) > 0) {
            $global_row = mysqli_fetch_assoc($check_global);
            $global_open = ($global_row['is_school_day'] == 1);
        }
        
        // Only insert per-class entry if it differs from global
        if($new_status != ($global_open ? 1 : 0)) {
            mysqli_query($conn, "INSERT INTO attendance_days (date_gregorian, ethiopian_year, ethiopian_month, ethiopian_day, day_of_week, class_id, is_school_day, created_by) 
                                VALUES ('$date_gregorian', {$eth_date['year']}, {$eth_date['month']}, {$eth_date['day']}, '{$eth_date['day_of_week']}', $class_id, $new_status, {$_SESSION['user_id']})");
        }
    } else {
        // All classes toggle - remove per-class overrides first
        mysqli_query($conn, "DELETE FROM attendance_days WHERE date_gregorian = '$date_gregorian' AND class_id IS NOT NULL");
        
        // Delete existing global entry
        mysqli_query($conn, "DELETE FROM attendance_days WHERE date_gregorian = '$date_gregorian' AND class_id IS NULL");
        
        // Insert new global entry
        mysqli_query($conn, "INSERT INTO attendance_days (date_gregorian, ethiopian_year, ethiopian_month, ethiopian_day, day_of_week, class_id, is_school_day, created_by) 
                            VALUES ('$date_gregorian', {$eth_date['year']}, {$eth_date['month']}, {$eth_date['day']}, '{$eth_date['day_of_week']}', NULL, $new_status, {$_SESSION['user_id']})");
    }
    
    $status_text = $new_status ? 'ክፍት (Open)' : 'ዝግ (Closed)';
    $message = "ቀን ተዘምኗል! $date_gregorian → $status_text";
    
    // Redirect to prevent form resubmission
    header("Location: attendance_days_control.php?m=$selected_eth_month&y=$selected_eth_year&c=$selected_class&msg=1");
    exit();
}

if(isset($_GET['msg'])) {
    $message = "✅ ቀን በተሳካ ሁኔታ ተዘምኗል! (Day updated successfully!)";
}

// Build Ethiopian month calendar - ALL Saturday & Sunday (past, present, future)
$days_in_month = getEthiopianDaysInMonth($selected_eth_year, $selected_eth_month);
$all_days = [];

$base_year = $selected_eth_year + 7;
$eth_new_year = new DateTime("$base_year-09-11");
if($base_year % 4 == 3) $eth_new_year = new DateTime("$base_year-09-12");

$month_offset = ($selected_eth_month - 1) * 30;

for($d = 1; $d <= $days_in_month; $d++) {
    $greg_date = clone $eth_new_year;
    $greg_date->modify('+' . ($month_offset + $d - 1) . ' days');
    $ds = $greg_date->format('Y-m-d');
    $dow = $greg_date->format('l');
    
    // Only Saturday and Sunday
    if($dow == 'Saturday' || $dow == 'Sunday') {
        $all_days[] = [
            'eth_day' => $d,
            'greg_date' => $ds,
            'day_name' => $dow,
            'is_future' => $ds > date('Y-m-d'),
            'is_today' => $ds == date('Y-m-d'),
            'is_past' => $ds < date('Y-m-d')
        ];
    }
}

// Get existing attendance days status
$closed_days = [];
if(!empty($all_days)) {
    $first = $all_days[0]['greg_date'];
    $last = $all_days[count($all_days)-1]['greg_date'];
    
    if($selected_class > 0) {
        // Global settings first
        $days_query = "SELECT date_gregorian, is_school_day FROM attendance_days 
                       WHERE date_gregorian BETWEEN '$first' AND '$last' 
                       AND class_id IS NULL";
        $days_result = mysqli_query($conn, $days_query);
        if($days_result) {
            while($row = mysqli_fetch_assoc($days_result)) {
                $closed_days[$row['date_gregorian']] = $row['is_school_day'];
            }
        }
        
        // Class override
        $days_query2 = "SELECT date_gregorian, is_school_day FROM attendance_days 
                        WHERE date_gregorian BETWEEN '$first' AND '$last' 
                        AND class_id = $selected_class";
        $days_result2 = mysqli_query($conn, $days_query2);
        if($days_result2) {
            while($row = mysqli_fetch_assoc($days_result2)) {
                $closed_days[$row['date_gregorian']] = $row['is_school_day'];
            }
        }
    } else {
        // All classes - only global
        $days_query = "SELECT date_gregorian, is_school_day FROM attendance_days 
                       WHERE date_gregorian BETWEEN '$first' AND '$last' 
                       AND class_id IS NULL";
        $days_result = mysqli_query($conn, $days_query);
        if($days_result) {
            while($row = mysqli_fetch_assoc($days_result)) {
                $closed_days[$row['date_gregorian']] = $row['is_school_day'];
            }
        }
    }
}

// Navigation
$prev_m = $selected_eth_month - 1; $prev_y = $selected_eth_year;
if($prev_m < 1) { $prev_m = 13; $prev_y--; }
$next_m = $selected_eth_month + 1; $next_y = $selected_eth_year;
if($next_m > 13) { $next_m = 1; $next_y++; }

// Today
$today_month = $today_eth['month'];
$today_year = $today_eth['year'];

// Helper function for URLs
function buildDayUrl($m, $y, $c) {
    return "attendance_days_control.php?m={$m}&y={$y}&c={$c}";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/png" href="images/icon.png">
    <title>የትምህርት ቀናት መቆጣጠሪያ | Attendance Days Control</title>
    <style>
        :root {
            --brown-dark: #8B4513; --gold-primary: #FFD700; --gold-dark: #DAA520;
            --gold-pale: #FFF8DC; --success: #10B981; --error: #EF4444; --bg: #FAF9F6;
        }
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Segoe UI', sans-serif; }
        body { background: var(--bg); min-height: 100vh; }
        
        .header {
            background: linear-gradient(135deg, #8B4513, #A52A2A);
            color: white; padding: 15px 25px;
            display: flex; justify-content: space-between; align-items: center;
            flex-wrap: wrap; gap: 10px;
        }
        .logo { display: flex; align-items: center; gap: 12px; }
        .logo-img { width: 45px; height: 45px; border-radius: 50%; object-fit: cover; border: 2px solid var(--gold-primary); background: white; }
        .logo h2 { font-size: 16px; color: #FFD700; }
        .logo span { font-size: 11px; opacity: 0.8; display: block; }
        .btn-back { color: #8B4513; background: #FFD700; padding: 8px 16px; border-radius: 20px; text-decoration: none; font-weight: 600; font-size: 13px; }

        .nav {
            background: white; padding: 12px 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1); position: sticky; top: 0; z-index: 100;
        }
        .nav-links {
            max-width: 1400px; margin: 0 auto; display: flex; gap: 6px;
            flex-wrap: wrap; justify-content: center;
        }
        .nav-link {
            padding: 8px 14px; color: var(--brown-dark); text-decoration: none;
            border-radius: 25px; transition: all 0.3s; font-weight: 600;
            font-size: 12px; white-space: nowrap; border: 1px solid transparent;
        }
        .nav-link:hover { background: var(--gold-pale); border-color: var(--gold-primary); }
        .nav-link.active { background: linear-gradient(135deg, var(--gold-primary), var(--gold-dark)); color: var(--brown-dark); border-color: var(--brown-dark); font-weight: 700; }
        
        .container { max-width: 1000px; margin: 20px auto; padding: 0 15px; }
        
        .message { padding: 12px 15px; border-radius: 10px; margin-bottom: 15px; display: flex; align-items: center; gap: 10px; font-size: 14px; }
        .success { background: #D1FAE5; color: #065F46; border-left: 4px solid var(--success); }
        
        .card {
            background: white; border-radius: 15px; padding: 20px; margin-bottom: 20px;
            border: 2px solid var(--gold-primary); box-shadow: 0 5px 15px rgba(0,0,0,0.08);
        }
        
        .filter-row { display: flex; gap: 10px; flex-wrap: wrap; align-items: flex-end; margin-bottom: 20px; }
        .filter-group { flex: 1; min-width: 150px; }
        .filter-group label { display: block; font-size: 11px; color: var(--brown-dark); font-weight: 600; margin-bottom: 4px; }
        .filter-group select { width: 100%; padding: 10px; border: 2px solid #E2E8F0; border-radius: 8px; font-size: 14px; }
        
        .month-nav { display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; gap: 10px; }
        .month-nav a {
            padding: 10px 16px; background: var(--gold-primary); color: var(--brown-dark);
            border-radius: 25px; text-decoration: none; font-weight: 600; font-size: 13px;
            cursor: pointer; transition: all 0.2s; border: 2px solid var(--gold-dark);
        }
        .month-nav a:hover { background: var(--gold-dark); color: white; transform: translateY(-2px); }
        .month-nav .btn-today { background: #F59E0B; color: white; border-color: #F59E0B; }
        .month-nav .btn-today:hover { background: #D97706; }
        .month-title { text-align: center; font-size: 18px; font-weight: bold; color: var(--brown-dark); flex: 1; }
        .month-title small { display: block; color: #666; font-size: 12px; font-weight: normal; }
        
        .days-grid { 
            display: grid; 
            grid-template-columns: repeat(auto-fill, minmax(130px, 1fr)); 
            gap: 10px; 
        }
        
        .day-card {
            padding: 15px 10px; border-radius: 12px; text-align: center; cursor: pointer;
            transition: all 0.3s; border: 2px solid #E2E7EF; background: white;
            font-family: inherit; font-size: inherit; position: relative; overflow: hidden;
        }
        .day-card:hover { transform: translateY(-3px); box-shadow: 0 5px 15px rgba(0,0,0,0.15); }
        .day-card.open { border-color: var(--success); background: #D1FAE5; }
        .day-card.closed { border-color: var(--error); background: #FEE2E2; }
        .day-card.today { border-color: #F59E0B; box-shadow: 0 0 0 3px rgba(245,158,11,0.3); }
        .day-card.past { opacity: 0.85; }
        
        .day-badge {
            position: absolute; top: 5px; right: 5px; font-size: 10px;
            padding: 2px 6px; border-radius: 10px; font-weight: 600;
        }
        .badge-past { background: #DBEAFE; color: #1D4ED8; }
        .badge-today { background: #FEF3C7; color: #D97706; }
        .badge-future { background: #F3F0FF; color: #7C3AED; }
        
        .day-name { font-size: 12px; font-weight: 600; color: #666; margin-bottom: 5px; }
        .day-num { font-size: 28px; font-weight: bold; color: var(--brown-dark); }
        .day-date { font-size: 11px; color: #666; margin-top: 3px; }
        .day-status { font-size: 11px; font-weight: 600; margin-top: 8px; padding: 4px 10px; border-radius: 15px; display: inline-block; }
        .status-open { background: #D1FAE5; color: #065F46; }
        .status-closed { background: #FEE2E2; color: #991B1B; }
        
        .legend { display: flex; gap: 15px; flex-wrap: wrap; margin-top: 15px; padding: 12px; background: #F3F4F6; border-radius: 8px; font-size: 12px; }
        .legend-item { display: flex; align-items: center; gap: 6px; }
        .legend-dot { width: 16px; height: 16px; border-radius: 4px; }
        
        .info-box { background: #EFF6FF; border-left: 4px solid #3B82F6; padding: 15px; border-radius: 8px; margin-bottom: 15px; font-size: 13px; display: flex; align-items: center; gap: 10px; }

        .stats-row { display: flex; gap: 15px; flex-wrap: wrap; margin-bottom: 15px; }
        .stat-mini { background: white; border-radius: 8px; padding: 10px 15px; border: 1px solid var(--gold-pale); text-align: center; flex: 1; min-width: 80px; }
        .stat-mini .num { font-size: 20px; font-weight: bold; color: var(--brown-dark); }
        .stat-mini .lbl { font-size: 10px; color: #666; }
        
        @media (max-width: 600px) {
            .days-grid { grid-template-columns: repeat(2, 1fr); }
            .day-num { font-size: 22px; }
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="logo">
            <img src="images/icon.png" alt="Logo" class="logo-img" onerror="this.style.display='none'; this.insertAdjacentHTML('afterend','📅');">
            <div>
                <h2>የትምህርት ቀናት መቆጣጠሪያ</h2>
                <span>Attendance Days Control</span>
            </div>
        </div>
        <a href="dashboard_admin.php" class="btn-back">← ዳሽቦርድ</a>
    </div>

    <div class="nav-links">
        <a href="dashboard_admin.php" class="nav-link active">🏠 ዳሽቦርድ</a>
        <a href="manage_classes.php" class="nav-link">📚 ክፍሎች</a>
        <a href="manage_students.php" class="nav-link">👥 ተማሪዎች</a>
        <a href="manage_teachers.php" class="nav-link">👨‍🏫 መምህራን</a>
        <a href="manage_assignments.php" class="nav-link">📋 ክፍል ምደባ</a>
        <a href="semester.php" class="nav-link">📅 ሴሚስተር</a>
        <a href="class_locks.php" class="nav-link">🔒 ክፍል መቆለፊያ</a>
        <a href="attendance_submitter_assign.php" class="nav-link">📋 የክፍል አቴንዳንስ አባላት</a>
        <a href="attendance_days_control.php" class="nav-link">📅 የትምህርት ቀናት</a>   
        <a href="attendance_controller.php" class="nav-link">📊 የአቴንዳንስ መቆጣጠሪያ</a>
        <a href="teacher_marks_viewer.php" class="nav-link">👁️ የመምህራን ውጤት</a>
        <a href="print_results.php" class="nav-link">🖨️ ውጤት ማተሚያ</a>
        <a href="manage_users.php" class="nav-link">👤 ተጠቃሚዎች</a>
    </div>

    <div class="container">
        <?php if($message): ?>
        <div class="message success">✅ <?php echo $message; ?></div>
        <?php endif; ?>

        <div class="info-box">
            <span>💡</span>
            <div>
                <strong>መመሪያ:</strong> ትምህርት የማይሰጥበትን ቀን ለመዝጋት ቀኑን ይጫኑ።<br>
                🟢 አረንጓዴ = ትምህርት አለ (Open) | 🔴 ቀይ = ትምህርት የለም (Closed)<br>
                <strong>⭐ ያለፉ ቀናትም መዝጋት ይቻላል! Past days CAN be closed!</strong>
            </div>
        </div>

        <!-- Filters -->
        <div class="card">
            <form method="GET" class="filter-row">
                <input type="hidden" name="m" value="<?php echo $selected_eth_month; ?>">
                <input type="hidden" name="y" value="<?php echo $selected_eth_year; ?>">
                <div class="filter-group">
                    <label>📚 ክፍል (Class)</label>
                    <select name="c" onchange="this.form.submit()">
                        <option value="0">ሁሉም ክፍሎች (All Classes)</option>
                        <?php 
                        mysqli_data_seek($classes, 0);
                        while($cl = mysqli_fetch_assoc($classes)): 
                        ?>
                        <option value="<?php echo $cl['id']; ?>" <?php echo $selected_class == $cl['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($cl['name']); ?>
                        </option>
                        <?php endwhile; ?>
                    </select>
                </div>
            </form>

            <!-- Month Navigation -->
            <div class="month-nav">
                <a href="<?php echo buildDayUrl($prev_m, $prev_y, $selected_class); ?>">← ቀዳሚ</a>
                <div class="month-title">
                    <?php echo $ethiopian_months[$selected_eth_month] . ' ' . $selected_eth_year; ?> ዓ.ም
                    <small>ቅዳሜ እና እሁድ (Saturday & Sunday)</small>
                </div>
                <a href="<?php echo buildDayUrl($today_month, $today_year, $selected_class); ?>" class="btn-today">📅 ዛሬ</a>
            </div>

            <!-- Stats -->
            <?php
            $open_count = 0; $closed_count = 0; $past_count = 0; $future_count = 0;
            foreach($all_days as $day) {
                $is_open = isset($closed_days[$day['greg_date']]) ? $closed_days[$day['greg_date']] : 1;
                if($is_open) $open_count++; else $closed_count++;
                if($day['is_past']) $past_count++;
                if($day['is_future']) $future_count++;
            }
            ?>
            <div class="stats-row">
                <div class="stat-mini"><div class="num"><?php echo count($all_days); ?></div><div class="lbl">ጠቅላላ ቀናት</div></div>
                <div class="stat-mini"><div class="num" style="color:var(--success);"><?php echo $open_count; ?></div><div class="lbl">✅ ክፍት</div></div>
                <div class="stat-mini"><div class="num" style="color:var(--error);"><?php echo $closed_count; ?></div><div class="lbl">🔒 ዝግ</div></div>
                <div class="stat-mini"><div class="num" style="color:#3B82F6;"><?php echo $past_count; ?></div><div class="lbl">📅 ያለፉ</div></div>
                <div class="stat-mini"><div class="num" style="color:#7C3AED;"><?php echo $future_count; ?></div><div class="lbl">⏳ የወደፊት</div></div>
            </div>

            <!-- Days Grid -->
            <div class="days-grid">
                <?php 
                $amharic_days = ['Saturday' => 'ቅዳሜ', 'Sunday' => 'እሁድ'];
                foreach($all_days as $day): 
                    $is_open = isset($closed_days[$day['greg_date']]) ? $closed_days[$day['greg_date']] : 1;
                    $status_class = $is_open ? 'open' : 'closed';
                    $status_text = $is_open ? '✅ ትምህርት አለ' : '🔒 ትምህርት የለም';
                    $card_class = 'day-card ' . $status_class;
                    if($day['is_today']) $card_class .= ' today';
                    if($day['is_past']) $card_class .= ' past';
                    
                    $badge = '';
                    if($day['is_today']) $badge = '<span class="day-badge badge-today">ዛሬ</span>';
                    elseif($day['is_past']) $badge = '<span class="day-badge badge-past">ያለፈ</span>';
                    else $badge = '<span class="day-badge badge-future">የወደፊት</span>';
                ?>
                <form method="POST" style="display:inline;">
                    <input type="hidden" name="date_gregorian" value="<?php echo $day['greg_date']; ?>">
                    <input type="hidden" name="class_id" value="<?php echo $selected_class; ?>">
                    <input type="hidden" name="current_status" value="<?php echo $is_open; ?>">
                    <button type="submit" name="toggle_day" class="<?php echo $card_class; ?>" style="width:100%;"
                            onclick="return confirmToggle('<?php echo $day['greg_date']; ?>', <?php echo $is_open; ?>)">
                        <?php echo $badge; ?>
                        <div class="day-name"><?php echo $amharic_days[$day['day_name']] ?? $day['day_name']; ?></div>
                        <div class="day-num"><?php echo $day['eth_day']; ?></div>
                        <div class="day-date"><?php echo $day['greg_date']; ?></div>
                        <div class="day-status <?php echo $is_open ? 'status-open' : 'status-closed'; ?>">
                            <?php echo $status_text; ?>
                        </div>
                    </button>
                </form>
                <?php endforeach; ?>
                
                <?php if(empty($all_days)): ?>
                <div style="grid-column: 1/-1; text-align:center; padding: 30px; color: #999;">
                    ምንም ቀናት አልተገኙም (No Saturday/Sunday in this month)
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Legend -->
        <div class="card">
            <h3 style="color:var(--brown-dark); margin-bottom:10px;">📖 መፍቻ / Legend</h3>
            <div class="legend">
                <div class="legend-item">
                    <span class="legend-dot" style="background:#D1FAE5; border:2px solid var(--success);"></span> 
                    ✅ ትምህርት አለ (Open)
                </div>
                <div class="legend-item">
                    <span class="legend-dot" style="background:#FEE2E2; border:2px solid var(--error);"></span> 
                    🔒 ትምህርት የለም (Closed)
                </div>
                <div class="legend-item">
                    <span class="legend-dot" style="background:#FEF3C7; border:2px solid #F59E0B;"></span> 
                    ⭐ ዛሬ (Today)
                </div>
                <div class="legend-item">
                    <span class="legend-dot" style="background:#DBEAFE; border:2px solid #3B82F6;"></span> 
                    📅 ያለፈ ቀን (Past - Clickable)
                </div>
                <div class="legend-item">
                    <span class="legend-dot" style="background:#F3F0FF; border:2px solid #7C3AED;"></span> 
                    ⏰ የወደፊት (Future - Clickable)
                </div>
            </div>
        </div>
    </div>

    <script>
        function confirmToggle(date, currentStatus) {
            const action = currentStatus ? 'መዝጋት (CLOSE)' : 'መክፈት (OPEN)';
            const dateType = new Date(date) < new Date(new Date().toDateString()) ? 'ያለፈ ቀን' : 
                            new Date(date) > new Date(new Date().toDateString()) ? 'የወደፊት ቀን' : 'ዛሬ';
            
            return confirm(
                '⚠️ ማረጋገጫ / Confirmation\n\n' +
                'ቀን: ' + date + '\n' +
                'አይነት: ' + dateType + '\n' +
                'ድርጊት: ' + action + '\n\n' +
                'እርግጠኛ ነዎት? (Are you sure?)'
            );
        }
    </script>
</body>
</html>
<?php mysqli_close($conn); ?>