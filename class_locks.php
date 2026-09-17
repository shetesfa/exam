<?php
require_once 'db.php';
requireAdmin();

$message = '';
$error = '';

// Get admin contact info
$admin1_row = dbFetchOne($conn, "SELECT setting_value FROM settings WHERE setting_key = 'admin_name_1'");
$admin1 = $admin1_row ? $admin1_row['setting_value'] : 'ዲ/ን ኪብረአብ ዘለለም';
$phone1_row = dbFetchOne($conn, "SELECT setting_value FROM settings WHERE setting_key = 'admin_phone_1'");
$phone1 = $phone1_row ? $phone1_row['setting_value'] : '0939883508';
$admin2_row = dbFetchOne($conn, "SELECT setting_value FROM settings WHERE setting_key = 'admin_name_2'");
$admin2 = $admin2_row ? $admin2_row['setting_value'] : 'ተስፋሁን ባዬ';
$phone2_row = dbFetchOne($conn, "SELECT setting_value FROM settings WHERE setting_key = 'admin_phone_2'");
$phone2 = $phone2_row ? $phone2_row['setting_value'] : '0943854325';
$title_row = dbFetchOne($conn, "SELECT setting_value FROM settings WHERE setting_key = 'admin_title'");
$title = $title_row ? $title_row['setting_value'] : 'የአጸደ ትጉሃን ትምህርት ክፍል ኃላፊ';

// Get current academic year and semester
$current_year = dbFetchOne($conn, "SELECT * FROM academic_years WHERE status = 'active' LIMIT 1");
$current_ethiopian_year = $current_year ? intval($current_year['ethiopian_year']) : 2018;

$current_semester = getCurrentSemester($conn);
$semester_id = $current_semester ? intval($current_semester['id']) : 0;

// Handle different locking methods
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = "የደህንነት ማረጋገጫ አልተሳካም! እባክዎ እንደገና ይሞክሩ።";
    } else {
        // METHOD 1: Lock by Class (affects all teachers of that class)
        if (isset($_POST['lock_by_class'])) {
            $class_id = intval($_POST['class_id'] ?? 0);
            $action = ($_POST['lock_by_class'] === 'unlock' || ($_POST['action'] ?? '') === 'unlock') ? 'unlock' : 'lock';
            
            $lock_value = ($action === 'lock') ? 1 : 0;
            $action_text = ($action === 'lock') ? 'ተቆልፈዋል' : 'ተከፍተዋል';
            
            if ($class_id > 0 && $semester_id > 0) {
                $updated = dbExecute(
                    $conn,
                    "UPDATE teacher_class SET locked = ? WHERE class_id = ? AND semester_id = ?",
                    "iii",
                    [$lock_value, $class_id, $semester_id]
                );
                
                if ($updated) {
                    $message = "በተመረጠው ክፍል ውስጥ ያሉ መምህራን $action_text!";
                } else {
                    $error = "ስህተት ተከስቷል!";
                }
            }
        }
        
        // METHOD 2: Lock by Teacher (affects all classes of that teacher)
        if (isset($_POST['lock_by_teacher'])) {
            $teacher_id = intval($_POST['teacher_id'] ?? 0);
            $action = ($_POST['lock_by_teacher'] === 'unlock' || ($_POST['action'] ?? '') === 'unlock') ? 'unlock' : 'lock';
            
            $lock_value = ($action === 'lock') ? 1 : 0;
            $action_text = ($action === 'lock') ? 'ተቆልፈዋል' : 'ተከፍተዋል';
            
            if ($teacher_id > 0 && $semester_id > 0) {
                $updated = dbExecute(
                    $conn,
                    "UPDATE teacher_class SET locked = ? WHERE teacher_id = ? AND semester_id = ?",
                    "iii",
                    [$lock_value, $teacher_id, $semester_id]
                );
                
                if ($updated) {
                    $message = "የመምህሩ ክፍሎች $action_text!";
                } else {
                    $error = "ስህተት ተከስቷል!";
                }
            }
        }
        
        // METHOD 3: Lock Specific (toggle marks lock)
        if (isset($_POST['lock_specific'])) {
            $assignment_id = intval($_POST['assignment_id'] ?? 0);
            $current_lock = intval($_POST['current_lock'] ?? 0);
            
            $new_lock = $current_lock ? 0 : 1;
            $action_text = $new_lock ? 'ተቆልፏል' : 'ተከፍቷል';
            
            if ($assignment_id > 0) {
                $updated = dbExecute($conn, "UPDATE teacher_class SET locked = ? WHERE id = ?", "ii", [$new_lock, $assignment_id]);
                
                if ($updated) {
                    $message = "የውጤት ማስገቢያ ሁኔታ $action_text!";
                } else {
                    $error = "ስህተት ተከስቷል!";
                }
            }
        }
        
        // METHOD 4: Lock All (all teachers, all classes)
        if (isset($_POST['lock_all'])) {
            $action = ($_POST['lock_all'] === 'unlock' || ($_POST['action'] ?? '') === 'unlock') ? 'unlock' : 'lock';
            $lock_value = ($action === 'lock') ? 1 : 0;
            $action_text = ($action === 'lock') ? 'ተቆልፈዋል' : 'ተከፍተዋል';
            
            if ($semester_id > 0) {
                $updated = dbExecute($conn, "UPDATE teacher_class SET locked = ? WHERE semester_id = ?", "ii", [$lock_value, $semester_id]);
                if ($updated) {
                    $message = "ሁሉም ክፍሎች $action_text!";
                } else {
                    $error = "ስህተት ተከስቷል!";
                }
            }
        }

        // METHOD 5: Toggle single lock type (marks / attendance / plan)
        if (isset($_POST['toggle_lock_type'])) {
            $assignment_id = intval($_POST['assignment_id'] ?? 0);
            $lock_type = $_POST['lock_type'] ?? '';
            $column = ['marks' => 'locked', 'attendance' => 'attendance_locked', 'plan' => 'plan_locked'][$lock_type] ?? null;

            if ($assignment_id > 0 && $column) {
                $current = dbFetchOne($conn, "SELECT `$column` AS val FROM teacher_class WHERE id = ?", "i", [$assignment_id]);
                $new_val = ($current && $current['val']) ? 0 : 1;
                $updated = dbExecute($conn, "UPDATE teacher_class SET `$column` = ? WHERE id = ?", "ii", [$new_val, $assignment_id]);
                if ($updated) {
                    auditLog($conn, $new_val ? "{$lock_type}_locked" : "{$lock_type}_unlocked", 'teacher_class', $assignment_id);
                    $message = "የመቆለፊያ ሁኔታ ተቀይሯል!";
                } else {
                    $error = "ስህተት ተከስቷል!";
                }
            }
        }
    }
}

// Get all classes for dropdown
$classes_query = "SELECT * FROM classes ORDER BY name";
$classes = mysqli_query($conn, $classes_query);

// Get all teachers for dropdown
$teachers_query = "SELECT * FROM users WHERE role = 'teacher' ORDER BY name";
$teachers = mysqli_query($conn, $teachers_query);

// Get all teacher-class assignments with lock status
$assignments_query = "SELECT tc.*, 
                      u.name as teacher_name, 
                      c.name as class_name,
                      s.name as semester_name,
                      COALESCE(sub.name, tc.subject_name, '') as subject_name
                      FROM teacher_class tc
                      JOIN users u ON tc.teacher_id = u.id
                      JOIN classes c ON tc.class_id = c.id
                      JOIN semesters s ON tc.semester_id = s.id
                      LEFT JOIN subjects sub ON tc.subject_id = sub.id
                      WHERE tc.semester_id = $semester_id
                      ORDER BY c.name, u.name";
$assignments = mysqli_query($conn, $assignments_query);
$assignments_list = [];
if ($assignments) {
    while ($row = mysqli_fetch_assoc($assignments)) {
        $assignments_list[] = $row;
    }
}

// Stats
$total_count = count($assignments_list);
$locked_count = 0;
foreach ($assignments_list as $a) {
    if ($a['locked'] == 1) $locked_count++;
}
$unlocked_count = $total_count - $locked_count;

$nav_active = 'class_locks';
?>
<!DOCTYPE html>
<html lang="am">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ክፍል መቆለፊያ አስተዳደር | አጸደ ትጉሃን</title>
    <?php include 'pwa_head.php'; ?>
    <style>
        :root {
            --brown-dark: #8B4513;
            --brown-medium: #A52A2A;
            --gold-primary: #FFD700;
            --gold-dark: #DAA520;
            --gold-pale: #FFF8DC;
            --bg-cream: #FAF9F6;
            --card-bg: #FFFFFF;
            --text-main: #1F2937;
            --text-muted: #6B7280;
            --border-color: #E5E7EB;
            --success: #10B981;
            --error: #EF4444;
            --warning: #F59E0B;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Segoe UI', system-ui, -apple-system, sans-serif; }
        body { background: var(--bg-cream); color: var(--text-main); min-height: 100vh; }

        .main-container { max-width: 1200px; margin: 24px auto; padding: 0 16px 80px; }

        /* Page Header Card */
        .page-header-card {
            background: linear-gradient(135deg, #8B4513 0%, #A52A2A 100%);
            border-radius: 16px;
            padding: 24px 28px;
            color: white;
            margin-bottom: 24px;
            box-shadow: 0 8px 24px rgba(139, 69, 19, 0.18);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 16px;
            position: relative;
            overflow: hidden;
        }
        .page-header-card::after {
            content: '🔒';
            position: absolute;
            right: 20px;
            bottom: -15px;
            font-size: 100px;
            opacity: 0.12;
            pointer-events: none;
        }
        .header-info h1 {
            font-size: 22px;
            font-weight: 800;
            color: var(--gold-primary);
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 6px;
        }
        .header-info p {
            font-size: 13.5px;
            color: rgba(255, 255, 255, 0.9);
        }
        .semester-pill {
            background: rgba(255, 215, 0, 0.2);
            border: 1px solid var(--gold-primary);
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 12.5px;
            font-weight: 700;
            color: var(--gold-primary);
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        /* Stats Row */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 16px;
            margin-bottom: 24px;
        }
        .stat-box {
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: 14px;
            padding: 18px 20px;
            display: flex;
            align-items: center;
            gap: 16px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.04);
        }
        .stat-icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            background: var(--gold-pale);
            color: var(--brown-dark);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            flex-shrink: 0;
        }
        .stat-data .stat-val {
            font-size: 24px;
            font-weight: 800;
            color: var(--brown-dark);
        }
        .stat-data .stat-lbl {
            font-size: 12px;
            color: var(--text-muted);
            font-weight: 600;
        }

        /* Alerts */
        .message {
            padding: 14px 18px;
            border-radius: 12px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
            font-weight: 600;
            font-size: 14px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }
        .message.success { background: #DCFCE7; color: #166534; border-left: 4px solid var(--success); }
        .message.error { background: #FEE2E2; color: #991B1B; border-left: 4px solid var(--error); }

        /* Card Container */
        .content-card {
            background: var(--card-bg);
            border-radius: 16px;
            padding: 24px;
            margin-bottom: 24px;
            border: 1px solid var(--border-color);
            box-shadow: 0 4px 16px rgba(0,0,0,0.05);
        }
        .content-card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 12px;
            border-bottom: 2px solid var(--gold-pale);
            flex-wrap: wrap;
            gap: 10px;
        }
        .content-card-header h2 {
            font-size: 17px;
            color: var(--brown-dark);
            display: flex;
            align-items: center;
            gap: 8px;
            font-weight: 700;
        }

        /* Global 1-Click Bar */
        .global-bar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 14px;
            background: #FEF3C7;
            border: 1.5px solid #FCD34D;
            border-radius: 14px;
            padding: 18px 22px;
            margin-bottom: 24px;
        }
        .global-bar-text h3 {
            color: #92400E;
            font-size: 16px;
            margin-bottom: 4px;
            font-weight: 800;
        }
        .global-bar-text p {
            font-size: 13px;
            color: #78350F;
        }
        .global-buttons {
            display: flex;
            gap: 10px;
        }
        .btn-lock-all {
            background: #EF4444;
            color: white;
            border: none;
            padding: 10px 18px;
            border-radius: 10px;
            font-weight: 700;
            font-size: 13.5px;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: all 0.2s;
        }
        .btn-unlock-all {
            background: #10B981;
            color: white;
            border: none;
            padding: 10px 18px;
            border-radius: 10px;
            font-weight: 700;
            font-size: 13.5px;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: all 0.2s;
        }

        /* Batch Grid */
        .batch-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 24px;
        }
        .batch-card {
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: 14px;
            padding: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.04);
        }
        .batch-card h3 {
            font-size: 15px;
            color: var(--brown-dark);
            margin-bottom: 12px;
            display: flex;
            align-items: center;
            gap: 6px;
            font-weight: 700;
        }
        .batch-form {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }
        .batch-buttons {
            display: flex;
            gap: 8px;
        }
        .btn-batch-lock {
            flex: 1;
            background: #FEE2E2;
            color: #DC2626;
            border: none;
            padding: 9px 14px;
            border-radius: 8px;
            font-weight: 700;
            font-size: 13px;
            cursor: pointer;
            transition: all 0.2s;
        }
        .btn-batch-unlock {
            flex: 1;
            background: #DCFCE7;
            color: #166534;
            border: none;
            padding: 9px 14px;
            border-radius: 8px;
            font-weight: 700;
            font-size: 13px;
            cursor: pointer;
            transition: all 0.2s;
        }

        /* Table */
        .table-responsive {
            width: 100%;
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }
        table.lock-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13.5px;
            text-align: left;
        }
        table.lock-table th {
            background: #F9FAFB;
            color: var(--brown-dark);
            font-weight: 700;
            padding: 14px 16px;
            border-bottom: 2px solid var(--border-color);
            white-space: nowrap;
        }
        table.lock-table td {
            padding: 14px 16px;
            border-bottom: 1px solid #F3F4F6;
            vertical-align: middle;
        }
        table.lock-table tbody tr:hover {
            background: rgba(255, 215, 0, 0.03);
        }

        /* Toggle Switches */
        .btn-toggle-switch {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 700;
            border: none;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            transition: all 0.2s;
        }
        .btn-toggle-switch.locked {
            background: #FEE2E2;
            color: #DC2626;
            border: 1px solid #FECACA;
        }
        .btn-toggle-switch.unlocked {
            background: #DCFCE7;
            color: #166534;
            border: 1px solid #BBF7D0;
        }
        .btn-toggle-switch:hover {
            opacity: 0.85;
            transform: scale(1.02);
        }

        @media (max-width: 768px) {
            .main-container { padding: 0 10px 40px; margin: 12px auto; }
            .page-header-card { padding: 18px 16px; }
            .content-card { padding: 16px 14px; border-radius: 14px; }
            .batch-grid { grid-template-columns: 1fr; }
            .global-bar { flex-direction: column; align-items: stretch; text-align: center; }
            .global-buttons { justify-content: center; }
        }
    </style>
</head>
<body>
    <?php include 'mobile_nav.php'; ?>

    <div class="main-container">
        <!-- Header -->
        <div class="page-header-card">
            <div class="header-info">
                <h1>🔒 የክፍል እና ውጤት መቆለፊያ አስተዳደር</h1>
                <p>የውጤት ማስገቢያ፣ የትምህርት ዕቅድ እና የተማሪዎች ክትትል መቆጣጠሪያዎችን ይቆልፉ ወይም ይክፈቱ።</p>
            </div>
            <?php if ($current_semester): ?>
            <div class="semester-pill">
                <span>📅</span> <?php echo htmlspecialchars($current_semester['name'] ?? 'ሴሚስተር'); ?> (<?php echo htmlspecialchars($current_semester['ethiopian_year'] ?? ''); ?> ዓ.ም)
            </div>
            <?php endif; ?>
        </div>

        <?php if($message): ?>
        <div class="message success">✅ <?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?></div>
        <?php endif; ?>

        <?php if($error): ?>
        <div class="message error">⚠️ <?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
        <?php endif; ?>

        <!-- Stats -->
        <div class="stats-grid">
            <div class="stat-box">
                <div class="stat-icon">📋</div>
                <div class="stat-data">
                    <div class="stat-val"><?php echo $total_count; ?></div>
                    <div class="stat-lbl">ጠቅላላ ምደባዎች</div>
                </div>
            </div>
            <div class="stat-box">
                <div class="stat-icon" style="background:#FEE2E2; color:#DC2626;">🔒</div>
                <div class="stat-data">
                    <div class="stat-val" style="color:#DC2626;"><?php echo $locked_count; ?></div>
                    <div class="stat-lbl">የተቆለፉ ክፍሎች</div>
                </div>
            </div>
            <div class="stat-box">
                <div class="stat-icon" style="background:#DCFCE7; color:#166534;">🔓</div>
                <div class="stat-data">
                    <div class="stat-val" style="color:#166534;"><?php echo $unlocked_count; ?></div>
                    <div class="stat-lbl">ክፍት የሆኑ ክፍሎች</div>
                </div>
            </div>
        </div>

        <!-- Global 1-Click Bar -->
        <div class="global-bar">
            <div class="global-bar-text">
                <h3>⚡ ፈጣን አጠቃላይ ቁጥጥር (Global Control)</h3>
                <p>በዚህ ሴሚስተር ያሉ ሁሉንም ክፍሎች በአንድ ጊዜ መቆለፍ ወይም መክፈት ይችላሉ።</p>
            </div>
            <div class="global-buttons">
                <form method="POST" style="display:inline;" onsubmit="return confirm('እርግጠኛ ነዎት ሁሉንም ክፍሎች መቆለፍ ይፈልጋሉ? መምህራን ውጤት ማስተካከል አይችሉም!')">
                    <?php echo csrfField(); ?>
                    <input type="hidden" name="lock_all" value="lock">
                    <button type="submit" class="btn-lock-all">
                        🔒 ሁሉንም ቆልፍ
                    </button>
                </form>
                <form method="POST" style="display:inline;" onsubmit="return confirm('እርግጠኛ ነዎት ሁሉንም ክፍሎች መክፈት ይፈልጋሉ?')">
                    <?php echo csrfField(); ?>
                    <input type="hidden" name="lock_all" value="unlock">
                    <button type="submit" class="btn-unlock-all">
                        🔓 ሁሉንም ክፈት
                    </button>
                </form>
            </div>
        </div>

        <!-- Batch Controls -->
        <div class="batch-grid">
            <!-- By Class -->
            <div class="batch-card">
                <h3>🏫 በክፍል ደረጃ መቆጣጠሪያ</h3>
                <form method="POST" class="batch-form">
                    <?php echo csrfField(); ?>
                    <select name="class_id" class="form-control" required style="width:100%; padding:10px; border-radius:8px; border:1.5px solid var(--border-color);">
                        <option value="">-- ክፍል ይምረጡ --</option>
                        <?php 
                        mysqli_data_seek($classes, 0);
                        while($c = mysqli_fetch_assoc($classes)): 
                        ?>
                        <option value="<?php echo $c['id']; ?>"><?php echo htmlspecialchars($c['name']); ?></option>
                        <?php endwhile; ?>
                    </select>
                    <div class="batch-buttons">
                        <button type="submit" name="lock_by_class" value="lock" class="btn-batch-lock">🔒 ክፍሉን ቆልፍ</button>
                        <button type="submit" name="lock_by_class" value="unlock" class="btn-batch-unlock">🔓 ክፍሉን ክፈት</button>
                    </div>
                </form>
            </div>

            <!-- By Teacher -->
            <div class="batch-card">
                <h3>👨‍🏫 በመምህር ደረጃ መቆጣጠሪያ</h3>
                <form method="POST" class="batch-form">
                    <?php echo csrfField(); ?>
                    <select name="teacher_id" class="form-control" required style="width:100%; padding:10px; border-radius:8px; border:1.5px solid var(--border-color);">
                        <option value="">-- መምህር ይምረጡ --</option>
                        <?php 
                        mysqli_data_seek($teachers, 0);
                        while($t = mysqli_fetch_assoc($teachers)): 
                        ?>
                        <option value="<?php echo $t['id']; ?>"><?php echo htmlspecialchars($t['name']); ?></option>
                        <?php endwhile; ?>
                    </select>
                    <div class="batch-buttons">
                        <button type="submit" name="lock_by_teacher" value="lock" class="btn-batch-lock">🔒 የመምህሩን ቆልፍ</button>
                        <button type="submit" name="lock_by_teacher" value="unlock" class="btn-batch-unlock">🔓 የመምህሩን ክፈት</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Detailed Assignments Table -->
        <div class="content-card">
            <div class="content-card-header">
                <h2><span>📋</span> የእያንዳንዱ ክፍል ዝርዝር ቁጥጥር</h2>
                <span class="header-badge"><?php echo $total_count; ?> ምደባዎች</span>
            </div>

            <!-- Search -->
            <div style="margin-bottom: 20px;">
                <input type="text" id="lockSearch" class="form-control" style="width:100%; padding:11px 14px; border-radius:10px; border:1.5px solid var(--border-color);" placeholder="🔍 የክፍል ወይም የመምህር ስም ይፈልጉ..." onkeyup="filterLocks()">
            </div>

            <div class="table-responsive">
                <table class="lock-table">
                    <thead>
                        <tr>
                            <th>ክፍል</th>
                            <th>መምህር</th>
                            <th>የትምህርት ዓይነት</th>
                            <th>የውጤት መቆለፊያ</th>
                            <th>የትምህርት ዕቅድ</th>
                            <th>የተማሪ ክትትል</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($assignments_list)): ?>
                        <tr>
                            <td colspan="6" style="text-align:center; padding:40px; color:var(--text-muted);">
                                ምንም የተመደበ ክፍል አልተገኘም።
                            </td>
                        </tr>
                        <?php else: foreach($assignments_list as $row): 
                            $isMarksLocked = intval($row['locked'] ?? 0) === 1;
                            $isPlanLocked = intval($row['plan_locked'] ?? 0) === 1;
                            $isAttLocked = intval($row['attendance_locked'] ?? 0) === 1;
                        ?>
                        <tr class="lock-row" data-search="<?php echo htmlspecialchars(strtolower($row['class_name'] . ' ' . $row['teacher_name'] . ' ' . $row['subject_name'])); ?>">
                            <td>
                                <strong>🏫 <?php echo htmlspecialchars($row['class_name']); ?></strong>
                            </td>
                            <td>
                                <span>👨‍🏫 <?php echo htmlspecialchars($row['teacher_name']); ?></span>
                            </td>
                            <td>
                                <span style="background:#F3F4F6; padding:3px 8px; border-radius:6px; font-size:12px;">
                                    📖 <?php echo htmlspecialchars($row['subject_name'] ?: 'ትምህርት'); ?>
                                </span>
                            </td>
                            <!-- Marks Lock -->
                            <td>
                                <form method="POST" style="display:inline;">
                                    <?php echo csrfField(); ?>
                                    <input type="hidden" name="assignment_id" value="<?php echo $row['id']; ?>">
                                    <input type="hidden" name="current_lock" value="<?php echo $row['locked']; ?>">
                                    <button type="submit" name="lock_specific" class="btn-toggle-switch <?php echo $isMarksLocked ? 'locked' : 'unlocked'; ?>">
                                        <?php echo $isMarksLocked ? '🔒 ተቆልፏል' : '🔓 ክፍት ነው'; ?>
                                    </button>
                                </form>
                            </td>
                            <!-- Plan Lock -->
                            <td>
                                <form method="POST" style="display:inline;">
                                    <?php echo csrfField(); ?>
                                    <input type="hidden" name="assignment_id" value="<?php echo $row['id']; ?>">
                                    <input type="hidden" name="lock_type" value="plan">
                                    <button type="submit" name="toggle_lock_type" class="btn-toggle-switch <?php echo $isPlanLocked ? 'locked' : 'unlocked'; ?>">
                                        <?php echo $isPlanLocked ? '🔒 ተቆልፏል' : '🔓 ክፍት ነው'; ?>
                                    </button>
                                </form>
                            </td>
                            <!-- Attendance Lock -->
                            <td>
                                <form method="POST" style="display:inline;">
                                    <?php echo csrfField(); ?>
                                    <input type="hidden" name="assignment_id" value="<?php echo $row['id']; ?>">
                                    <input type="hidden" name="lock_type" value="attendance">
                                    <button type="submit" name="toggle_lock_type" class="btn-toggle-switch <?php echo $isAttLocked ? 'locked' : 'unlocked'; ?>">
                                        <?php echo $isAttLocked ? '🔒 ተቆልፏል' : '🔓 ክፍት ነው'; ?>
                                    </button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script>
        function filterLocks() {
            var q = document.getElementById('lockSearch').value.toLowerCase();
            var rows = document.querySelectorAll('.lock-row');
            rows.forEach(function(row) {
                var searchData = row.getAttribute('data-search');
                if (!q || searchData.indexOf(q) > -1) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        }
    </script>
</body>
</html>
<?php mysqli_close($conn); ?>