<?php
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
$selected_eth_month = isset($_GET['m']) ? intval($_GET['m']) : intval($today_eth['month']);
$selected_eth_year = isset($_GET['y']) ? intval($_GET['y']) : intval($today_eth['year']);
$selected_class = isset($_GET['c']) ? intval($_GET['c']) : 0;

// Validate
if ($selected_eth_month < 1 || $selected_eth_month > 13) {
    $selected_eth_month = intval($today_eth['month']);
}
if ($selected_eth_year < 2000 || $selected_eth_year > 2100) {
    $selected_eth_year = intval($today_eth['year']);
}

// Get classes
$classes = dbQuery($conn, "SELECT * FROM classes ORDER BY name");

// Handle toggle day
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_day'])) {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = "የደህንነት ማረጋገጫ አልተሳካም!";
    } else {
        $date_gregorian = trim($_POST['date_gregorian'] ?? '');
        $class_id = intval($_POST['class_id'] ?? 0);
        $current_status = intval($_POST['current_status'] ?? 1);
        $new_status = ($current_status === 1) ? 0 : 1;
        
        // Get Ethiopian date info
        $eth_date = getEthiopianDateFromGregorian($date_gregorian);
        $userId = intval($_SESSION['user_id'] ?? 1);
        
        if ($class_id > 0) {
            // Per-class toggle: delete existing class entry
            dbExecute($conn, "DELETE FROM attendance_days WHERE date_gregorian = ? AND class_id = ?", "si", [$date_gregorian, $class_id]);
            
            // Check global entry
            $check_global = dbFetchOne($conn, "SELECT is_school_day FROM attendance_days WHERE date_gregorian = ? AND class_id IS NULL", "s", [$date_gregorian]);
            $global_open = true;
            if ($check_global) {
                $global_open = (intval($check_global['is_school_day']) === 1);
            }
            
            // Only insert per-class entry if it differs from global
            if ($new_status !== ($global_open ? 1 : 0)) {
                dbExecute(
                    $conn,
                    "INSERT INTO attendance_days (date_gregorian, ethiopian_year, ethiopian_month, ethiopian_day, day_of_week, class_id, is_school_day, created_by) 
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
                    "siiisiii",
                    [
                        $date_gregorian,
                        intval($eth_date['year']),
                        intval($eth_date['month']),
                        intval($eth_date['day']),
                        $eth_date['day_of_week'] ?? date('l', strtotime($date_gregorian)),
                        $class_id,
                        $new_status,
                        $userId
                    ]
                );
            }
        } else {
            // All classes toggle: remove per-class overrides and global entry
            dbExecute($conn, "DELETE FROM attendance_days WHERE date_gregorian = ?", "s", [$date_gregorian]);
            
            // Insert new global entry
            dbExecute(
                $conn,
                "INSERT INTO attendance_days (date_gregorian, ethiopian_year, ethiopian_month, ethiopian_day, day_of_week, class_id, is_school_day, created_by) 
                 VALUES (?, ?, ?, ?, ?, NULL, ?, ?)",
                "siiisii",
                [
                    $date_gregorian,
                    intval($eth_date['year']),
                    intval($eth_date['month']),
                    intval($eth_date['day']),
                    $eth_date['day_of_week'] ?? date('l', strtotime($date_gregorian)),
                    $new_status,
                    $userId
                ]
            );
        }
        
        header("Location: attendance_days_control.php?m=$selected_eth_month&y=$selected_eth_year&c=$selected_class&msg=1");
        exit();
    }
}

if (isset($_GET['msg'])) {
    $message = "ቀኑ በተሳካ ሁኔታ ተዘምኗል!";
}

// Build Ethiopian month calendar - ALL Saturday & Sunday
$days_in_month = getEthiopianDaysInMonth($selected_eth_year, $selected_eth_month);
$all_days = [];

for ($d = 1; $d <= $days_in_month; $d++) {
    $greg_str = ethiopianToGregorian($selected_eth_year, $selected_eth_month, $d);
    if (!$greg_str) continue;
    
    $dow = date('l', strtotime($greg_str));
    if ($dow === 'Saturday' || $dow === 'Sunday') {
        $all_days[] = [
            'eth_day' => $d,
            'greg_date' => $greg_str,
            'day_name' => $dow,
            'is_future' => ($greg_str > date('Y-m-d')),
            'is_today' => ($greg_str === date('Y-m-d')),
            'is_past' => ($greg_str < date('Y-m-d'))
        ];
    }
}

// Get existing attendance days status
$closed_days = [];
if (!empty($all_days)) {
    $first = $all_days[0]['greg_date'];
    $last = $all_days[count($all_days)-1]['greg_date'];
    
    // Global settings first
    $global_rows = dbFetchAll(
        $conn,
        "SELECT date_gregorian, is_school_day FROM attendance_days WHERE date_gregorian BETWEEN ? AND ? AND class_id IS NULL",
        "ss",
        [$first, $last]
    );
    foreach ($global_rows as $row) {
        $closed_days[$row['date_gregorian']] = intval($row['is_school_day']);
    }
    
    // Class override
    if ($selected_class > 0) {
        $class_rows = dbFetchAll(
            $conn,
            "SELECT date_gregorian, is_school_day FROM attendance_days WHERE date_gregorian BETWEEN ? AND ? AND class_id = ?",
            "ssi",
            [$first, $last, $selected_class]
        );
        foreach ($class_rows as $row) {
            $closed_days[$row['date_gregorian']] = intval($row['is_school_day']);
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
$nav_active = 'attendance_days_control';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>የትምህርት ቀናት መቆጣጠሪያ | Attendance Days Control</title>
    <?php include 'pwa_head.php'; ?>
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
            background: white; border-radius: 12px; padding: 16px 12px; margin-bottom: 15px;
            border: 2px solid var(--gold-primary); box-shadow: 0 5px 15px rgba(0,0,0,0.08);
        }
        
        .filter-row { display: flex; gap: 8px; flex-wrap: wrap; align-items: flex-end; margin-bottom: 20px; }
        .filter-group { flex: 1; min-width: 0; }
        .filter-group label { display: block; font-size: 11px; color: var(--brown-dark); font-weight: 600; margin-bottom: 4px; }
        .filter-group select { width: 100%; padding: 10px; border: 2px solid #E2E8F0; border-radius: 8px; font-size: 14px; }
        
        .month-nav { display: flex; justify-content: center; align-items: center; margin-bottom: 15px; gap: 8px; }
        .month-nav a {
            padding: 8px 12px; background: var(--gold-primary); color: var(--brown-dark);
            border-radius: 25px; text-decoration: none; font-weight: 600; font-size: 12px;
            cursor: pointer; transition: all 0.2s; border: 2px solid var(--gold-dark);
        }
        .month-nav a:hover { background: var(--gold-dark); color: white; transform: translateY(-2px); }
        .month-nav .btn-today { background: #F59E0B; color: white; border-color: #F59E0B; }
        .month-nav .btn-today:hover { background: #D97706; }
        .month-title { text-align: center; font-size: 15px; font-weight: bold; color: var(--brown-dark); flex: 1; }
        .month-title small { display: block; color: #666; font-size: 12px; font-weight: normal; }
        
        .days-grid { 
            display: grid; 
            grid-template-columns: repeat(2, 1fr); 
            gap: 8px; 
        }
        
        .day-card {
            padding: 12px 8px; border-radius: 12px; text-align: center; cursor: pointer;
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
        .day-num { font-size: 22px; font-weight: bold; color: var(--brown-dark); }
        .day-date { font-size: 11px; color: #666; margin-top: 3px; }
        .day-status { font-size: 11px; font-weight: 600; margin-top: 8px; padding: 4px 10px; border-radius: 15px; display: inline-block; }
        .status-open { background: #D1FAE5; color: #065F46; }
        .status-closed { background: #FEE2E2; color: #991B1B; }
        
        .legend { display: flex; gap: 8px; flex-wrap: wrap; margin-top: 15px; padding: 10px; background: #F3F4F6; border-radius: 8px; font-size: 11px; }
        .legend-item { display: flex; align-items: center; gap: 6px; }
        .legend-dot { width: 16px; height: 16px; border-radius: 4px; }
        
        .info-box { background: #EFF6FF; border-left: 4px solid #3B82F6; padding: 12px; border-radius: 8px; margin-bottom: 15px; font-size: 12px; display: flex; align-items: center; gap: 10px; }

        .stats-row { display: flex; gap: 8px; flex-wrap: wrap; margin-bottom: 15px; }
        .stat-mini { background: white; border-radius: 8px; padding: 8px 6px; border: 1px solid var(--gold-pale); text-align: center; flex: 1; min-width: 80px; }
        .stat-mini .num { font-size: 20px; font-weight: bold; color: var(--brown-dark); }
        .stat-mini .lbl { font-size: 10px; color: #666; }
        
        @media (max-width: 600px) {
            .main-container { padding: 0 10px 30px; margin: 15px auto; }
            .card { padding: 16px 12px; border-radius: 12px; margin-bottom: 15px; }
            .month-nav { flex-wrap: wrap; gap: 8px; justify-content: center; }
            .month-nav a { padding: 8px 12px; font-size: 12px; }
            .month-title { font-size: 15px; width: 100%; order: -1; margin-bottom: 5px; }
            .filter-row { flex-direction: column; gap: 8px; }
            .filter-group { width: 100%; min-width: 0; }
            .days-grid { grid-template-columns: repeat(2, 1fr); gap: 8px; }
            .day-card { padding: 12px 8px; }
            .day-num { font-size: 22px; }
            .stats-row { gap: 8px; }
            .stat-mini { padding: 8px 6px; }
            .info-box { padding: 12px; font-size: 12px; }
            .legend { gap: 8px; padding: 10px; font-size: 11px; }
        }
    </style>
</head>
<body>
    <?php include 'mobile_nav.php'; ?>

    <div class="main-container">
        <?php if($message): ?>
        <div class="message success">✅ <?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?></div>
        <?php endif; ?>

        <div class="info-box">
            <span>💡</span>
            <div>
                <strong>መመሪያ:</strong> ትምህርት የማይሰጥበትን ቀን ለመዝጋት ቀኑን ይጫኑ።<br>
                🟢 አረንጓዴ = ትምህርት አለ | 🔴 ቀይ = ትምህርት የለም<br>
                <strong>⭐ ያለፉ ቀናትም ቢሆኑ መዝጋት ወይም መክፈት ይቻላል!</strong>
            </div>
        </div>

        <!-- Filters -->
        <div class="card">
            <form method="GET" class="filter-row">
                <input type="hidden" name="m" value="<?php echo $selected_eth_month; ?>">
                <input type="hidden" name="y" value="<?php echo $selected_eth_year; ?>">
                <div class="filter-group">
                    <label>📚 ክፍል</label>
                    <select name="c" onchange="this.form.submit()">
                        <option value="0">ሁሉም ክፍሎች</option>
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
                    <?php echo csrfField(); ?>
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
                    በዚህ ወር ውስጥ ምንም የቅዳሜና እሁድ ቀናት አልተገኙም
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Legend -->
        <div class="card">
            <h3 style="color:var(--brown-dark); margin-bottom:10px;">📖 የቀለማት መፍቻ</h3>
            <div class="legend">
                <div class="legend-item">
                    <span class="legend-dot" style="background:#D1FAE5; border:2px solid var(--success);"></span> 
                    ✅ ትምህርት አለ
                </div>
                <div class="legend-item">
                    <span class="legend-dot" style="background:#FEE2E2; border:2px solid var(--error);"></span> 
                    🔒 ትምህርት የለም
                </div>
                <div class="legend-item">
                    <span class="legend-dot" style="background:#FEF3C7; border:2px solid #F59E0B;"></span> 
                    ⭐ ዛሬ
                </div>
                <div class="legend-item">
                    <span class="legend-dot" style="background:#DBEAFE; border:2px solid #3B82F6;"></span> 
                    📅 ያለፈ ቀን
                </div>
                <div class="legend-item">
                    <span class="legend-dot" style="background:#F3F0FF; border:2px solid #7C3AED;"></span> 
                    ⏰ የወደፊት ቀን
                </div>
            </div>
        </div>
    </div>

    <script>
        function confirmToggle(date, currentStatus) {
            const action = currentStatus ? 'መዝጋት' : 'መክፈት';
            const dateType = new Date(date) < new Date(new Date().toDateString()) ? 'ያለፈ ቀን' : 
                            new Date(date) > new Date(new Date().toDateString()) ? 'የወደፊት ቀን' : 'ዛሬ';
            
            return confirm(
                '⚠️ ማረጋገጫ\n\n' +
                'ቀን: ' + date + '\n' +
                'ሁኔታ: ' + dateType + '\n' +
                'የሚወሰድ እርምጃ: ' + action + '\n\n' +
                'እርግጠኛ ነዎት?'
            );
        }
    </script>
</body>
</html>
<?php mysqli_close($conn); ?>