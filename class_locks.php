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
        
        // METHOD 3: Lock by Specific Combination (1 teacher + 1 class)
        if (isset($_POST['lock_specific'])) {
            $assignment_id = intval($_POST['assignment_id'] ?? 0);
            $current_lock = intval($_POST['current_lock'] ?? 0);
            
            $new_lock = $current_lock ? 0 : 1;
            $action_text = $new_lock ? 'ተቆልፏል' : 'ተከፍቷል';
            
            if ($assignment_id > 0) {
                $updated = dbExecute($conn, "UPDATE teacher_class SET locked = ? WHERE id = ?", "ii", [$new_lock, $assignment_id]);
                
                if ($updated) {
                    $info = dbFetchOne(
                        $conn,
                        "SELECT u.name as teacher_name, c.name as class_name 
                         FROM teacher_class tc
                         JOIN users u ON tc.teacher_id = u.id
                         JOIN classes c ON tc.class_id = c.id
                         WHERE tc.id = ?",
                        "i",
                        [$assignment_id]
                    );
                    $tName = $info ? $info['teacher_name'] : 'መምህር';
                    $cName = $info ? $info['class_name'] : 'ክፍል';
                    $message = "መምህር $tName በ$cName ክፍል $action_text!";
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
        // METHOD 5: Toggle a single lock type (marks / attendance / plan)
        // for one assignment - added so attendance and lesson-plan locking
        // can be controlled independently of the marks lock, per-class.
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
                    $message = "ሁኔታ ተቀይሯል!";
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
                      s.name as semester_name
                      FROM teacher_class tc
                      JOIN users u ON tc.teacher_id = u.id
                      JOIN classes c ON tc.class_id = c.id
                      JOIN semesters s ON tc.semester_id = s.id
                      WHERE tc.semester_id = $semester_id
                      ORDER BY c.name, u.name";
$assignments = mysqli_query($conn, $assignments_query);

// Get lock statistics
$stats_query = "SELECT 
                COUNT(*) as total_assignments,
                SUM(CASE WHEN locked = 1 THEN 1 ELSE 0 END) as locked_assignments,
                COUNT(DISTINCT teacher_id) as total_teachers,
                COUNT(DISTINCT class_id) as total_classes
                FROM teacher_class 
                WHERE semester_id = $semester_id";
$stats_result = mysqli_query($conn, $stats_query);
$stats = mysqli_fetch_assoc($stats_result);

$locked_count = $stats['locked_assignments'] ?? 0;
$total_count = $stats['total_assignments'] ?? 0;
$unlocked_count = $total_count - $locked_count;
$nav_active = 'class_locks';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>ክፍል መቆለፊያ አስተዳደር | አጸደ ትጉሃን</title>
    <?php include 'pwa_head.php'; ?>
    <style>
        :root {
            --brown-dark: #8B4513;
            --brown-medium: #A52A2A;
            --gold-primary: #FFD700;
            --gold-dark: #DAA520;
            --gold-pale: #FFF8DC;
            --success-green: #10B981;
            --error-red: #EF4444;
            --warning-yellow: #F59E0B;
            --info-blue: #3B82F6;
            --purple: #8B5CF6;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', sans-serif;
        }

        body {
            background: #FAF9F6;
        }

        .header {
            background: linear-gradient(135deg, #8B4513 0%, #A52A2A 100%);
            color: white;
            padding: 20px 30px;
            position: relative;
            overflow: hidden;
        }

        .header::before {
            content: '⛪';
            position: absolute;
            right: 30px;
            bottom: -20px;
            font-size: 150px;
            opacity: 0.1;
            transform: rotate(15deg);
        }

        .header-content {
            max-width: 1400px;
            margin: 0 auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 20px;
            position: relative;
            z-index: 1;
        }

        .logo-area {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .logo-icon {
            width: 60px;
            height: 60px;
            background: linear-gradient(135deg, var(--gold-primary), var(--gold-dark));
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 30px;
            color: var(--brown-dark);
            border: 3px solid white;
        }

        .title h1 {
            font-size: 22px;
            color: var(--gold-primary);
        }

        .title p {
            font-size: 14px;
            color: var(--gold-light);
        }

        .admin-contact {
            background: rgba(0,0,0,0.2);
            padding: 10px 20px;
            border-radius: 40px;
            border: 2px solid var(--gold-primary);
            font-size: 14px;
        }

        .admin-contact span {
            color: var(--gold-primary);
            margin: 0 10px;
        }

        .nav {
    background: white;
    padding: 12px 20px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    position: sticky;
    top: 0;
    z-index: 100;
}

.nav-links {
    max-width: 1400px;
    margin: 0 auto;
    display: flex;
    gap: 6px;
    flex-wrap: wrap;
    justify-content: center;
}

.nav-link {
    padding: 8px 14px;
    color: var(--brown-dark);
    text-decoration: none;
    border-radius: 25px;
    transition: all 0.3s;
    font-weight: 600;
    font-size: 12px;
    white-space: nowrap;
    border: 1px solid transparent;
}

.nav-link:hover {
    background: var(--gold-pale);
    border-color: var(--gold-primary);
}

.nav-link.active {
    background: linear-gradient(135deg, var(--gold-primary), var(--gold-dark));
    color: var(--brown-dark);
    border-color: var(--brown-dark);
    font-weight: 700;
}
        .container {
            max-width: 1400px;
            margin: 30px auto;
            padding: 0 30px;
        }

        .message {
            padding: 15px 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
            animation: slideDown 0.4s ease;
        }

        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .success {
            background: #D1FAE5;
            color: var(--success-green);
            border-left: 4px solid var(--success-green);
        }

        .error {
            background: #FEE2E2;
            color: var(--error-red);
            border-left: 4px solid var(--error-red);
        }

        /* Stats Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            text-align: center;
            border-left: 4px solid var(--gold-primary);
        }

        .stat-value {
            font-size: 28px;
            font-weight: bold;
            color: var(--brown-dark);
            margin: 10px 0;
        }

        .stat-label {
            color: #666;
            font-size: 14px;
        }

        /* Lock Method Cards */
        .methods-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 25px;
            margin-bottom: 30px;
        }

        .method-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
            border: 2px solid var(--gold-pale);
            transition: all 0.3s;
            position: relative;
            overflow: hidden;
        }

        .method-card:hover {
            transform: translateY(-5px);
            border-color: var(--gold-primary);
        }

        .method-card::before {
            position: absolute;
            top: 10px;
            right: 10px;
            font-size: 40px;
            opacity: 0.1;
        }

        .method-card.method1::before { content: '📚'; }
        .method-card.method2::before { content: '👨‍🏫'; }
        .method-card.method3::before { content: '🔗'; }
        .method-card.method4::before { content: '🌐'; }

        .method-title {
            color: var(--brown-dark);
            font-size: 20px;
            font-weight: bold;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 2px solid var(--gold-pale);
        }

        .method-desc {
            color: #666;
            font-size: 14px;
            margin-bottom: 20px;
            line-height: 1.5;
        }

        .method-form {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }

        .form-group {
            margin-bottom: 10px;
        }

        .form-group label {
            display: block;
            margin-bottom: 5px;
            color: var(--brown-dark);
            font-weight: 600;
            font-size: 14px;
        }

        .form-control {
            width: 100%;
            padding: 12px;
            border: 2px solid #E2E8F0;
            border-radius: 8px;
            font-size: 14px;
            transition: all 0.3s;
        }

        .form-control:focus {
            outline: none;
            border-color: var(--gold-primary);
            box-shadow: 0 0 0 3px rgba(255,215,0,0.2);
        }

        .btn-group {
            display: flex;
            gap: 10px;
            margin-top: 10px;
        }

        .btn {
            padding: 12px 20px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            font-size: 14px;
            flex: 1;
        }

        .btn-lock {
            background: #EF4444;
            color: white;
        }

        .btn-lock:hover {
            background: #DC2626;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(239,68,68,0.3);
        }

        .btn-unlock {
            background: #10B981;
            color: white;
        }

        .btn-unlock:hover {
            background: #059669;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(16,185,129,0.3);
        }

        .btn-warning {
            background: #F59E0B;
            color: white;
        }

        .btn-info {
            background: #3B82F6;
            color: white;
        }

        .btn-sm {
            padding: 6px 12px;
            font-size: 12px;
        }

        /* Assignments Table */
        .section {
            background: white;
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 30px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }

        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid var(--gold-pale);
            flex-wrap: wrap;
            gap: 15px;
        }

        .section-header h2 {
            color: var(--brown-dark);
            font-size: 22px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .table-responsive {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        .table-responsive table {
            min-width: 650px;
        }

        th {
            background: var(--gold-pale);
            color: var(--brown-dark);
            padding: 15px;
            text-align: left;
            font-weight: 600;
        }

        td {
            padding: 12px 15px;
            border-bottom: 1px solid #E2E8F0;
        }

        tr:hover {
            background: #FEF9E7;
        }

        .lock-badge {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }

        .badge-locked {
            background: #FEE2E2;
            color: var(--error-red);
        }

        .badge-unlocked {
            background: #D1FAE5;
            color: var(--success-green);
        }

        .toggle-btn {
            background: none;
            border: 2px solid;
            padding: 6px 15px;
            border-radius: 20px;
            cursor: pointer;
            font-weight: 600;
            font-size: 12px;
            transition: all 0.3s;
        }

        .toggle-btn.locked {
            background: #FEE2E2;
            border-color: var(--error-red);
            color: var(--error-red);
        }

        .toggle-btn.locked:hover {
            background: #FECACA;
        }

        .toggle-btn.unlocked {
            background: #D1FAE5;
            border-color: var(--success-green);
            color: var(--success-green);
        }

        .toggle-btn.unlocked:hover {
            background: #A7F3D0;
        }

        .info-box {
            background: #EFF6FF;
            border-left: 4px solid var(--info-blue);
            padding: 20px;
            border-radius: 10px;
            margin: 20px 0;
            display: flex;
            align-items: center;
            gap: 15px;
            flex-wrap: wrap;
        }

        .method-badge {
            background: var(--purple);
            color: white;
            padding: 3px 10px;
            border-radius: 15px;
            font-size: 11px;
            margin-left: 10px;
        }

        @media (max-width: 768px) {
            .main-container { padding: 0 12px 30px; margin: 15px auto; }
            .stats-grid { grid-template-columns: repeat(2, 1fr); gap: 10px; margin-bottom: 20px; }
            .stat-card { padding: 12px 10px; border-radius: 10px; }
            .stat-value { font-size: 22px; margin: 6px 0; }
            .stat-label { font-size: 12px; }
            .methods-grid {
                grid-template-columns: 1fr;
                gap: 15px;
            }
            .method-card { padding: 16px 12px; border-radius: 12px; }
            .method-title { font-size: 16px; }
            .method-badge { display: inline-block; margin-top: 5px; margin-left: 0; }
            .btn-group {
                flex-direction: column;
                gap: 8px;
            }
            .btn { width: 100%; min-height: 44px; }
            .section { padding: 16px 12px; border-radius: 12px; margin-bottom: 20px; }
            .section-header h2 { font-size: 17px; }
            .info-box { flex-direction: column; align-items: flex-start; padding: 14px; gap: 10px; }
            .header-content {
                flex-direction: column;
                text-align: center;
            }
        }
        /* Dark Mode Overrides for Class Locks */
        html.dark-mode body,
        body.dark-mode,
        [data-theme="dark"] body {
            background: #0B1120 !important;
            background-color: #0B1120 !important;
            color: #F1F5F9 !important;
        }
        html.dark-mode .stat-card,
        body.dark-mode .stat-card,
        [data-theme="dark"] .stat-card {
            background: #1E293B !important;
            background-color: #1E293B !important;
            border: 1px solid #334155 !important;
            color: #F1F5F9 !important;
            box-shadow: 0 2px 10px rgba(0,0,0,0.4) !important;
        }
        html.dark-mode .stat-card div:first-child,
        body.dark-mode .stat-card div:first-child,
        [data-theme="dark"] .stat-card div:first-child {
            color: #CBD5E1 !important;
        }
        html.dark-mode .stat-value,
        body.dark-mode .stat-value,
        [data-theme="dark"] .stat-value {
            color: #FCD34D !important;
        }
        html.dark-mode .method-card,
        body.dark-mode .method-card,
        [data-theme="dark"] .method-card {
            background: #1E293B !important;
            background-color: #1E293B !important;
            border: 1px solid #334155 !important;
            color: #F1F5F9 !important;
            box-shadow: 0 4px 15px rgba(0,0,0,0.4) !important;
        }
        html.dark-mode .method-title,
        body.dark-mode .method-title,
        [data-theme="dark"] .method-title {
            color: #FCD34D !important;
            border-bottom-color: #334155 !important;
        }
        html.dark-mode .method-desc,
        body.dark-mode .method-desc,
        [data-theme="dark"] .method-desc {
            color: #94A3B8 !important;
        }
        html.dark-mode .form-group label,
        body.dark-mode .form-group label,
        [data-theme="dark"] .form-group label {
            color: #CBD5E1 !important;
        }
        html.dark-mode .form-control,
        body.dark-mode .form-control,
        [data-theme="dark"] .form-control {
            background: #0F172A !important;
            background-color: #0F172A !important;
            color: #F8FAFC !important;
            border-color: #475569 !important;
        }
        html.dark-mode .section,
        body.dark-mode .section,
        [data-theme="dark"] .section {
            background: #1E293B !important;
            background-color: #1E293B !important;
            border: 1px solid #334155 !important;
            box-shadow: 0 5px 20px rgba(0,0,0,0.4) !important;
        }
        html.dark-mode .section-header h2,
        body.dark-mode .section-header h2,
        [data-theme="dark"] .section-header h2 {
            color: #FCD34D !important;
        }
        html.dark-mode .table-responsive table,
        body.dark-mode .table-responsive table,
        [data-theme="dark"] .table-responsive table {
            background: #1E293B !important;
            border-color: #334155 !important;
        }
        html.dark-mode th,
        body.dark-mode th,
        [data-theme="dark"] th {
            background: #0F172A !important;
            background-color: #0F172A !important;
            color: #FCD34D !important;
            border-bottom: 2px solid #F59E0B !important;
        }
        html.dark-mode td,
        body.dark-mode td,
        [data-theme="dark"] td {
            background: #1E293B !important;
            background-color: #1E293B !important;
            color: #E2E8F0 !important;
            border-bottom: 1px solid #334155 !important;
        }
        html.dark-mode tr:hover td,
        body.dark-mode tr:hover td,
        [data-theme="dark"] tr:hover td {
            background: #26354A !important;
            background-color: #26354A !important;
        }
        html.dark-mode .info-box,
        body.dark-mode .info-box,
        [data-theme="dark"] .info-box {
            background: #0F172A !important;
            background-color: #0F172A !important;
            border-left-color: #3B82F6 !important;
            color: #93C5FD !important;
        }
    </style>
</head>
<body>
    <?php include 'mobile_nav.php'; ?>

    <div class="main-container">
        <?php if($message): ?>
        <div class="message success">
            <span>✅</span>
            <?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?>
        </div>
        <?php endif; ?>

        <?php if($error): ?>
        <div class="message error">
            <span>⚠️</span>
            <?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?>
        </div>
        <?php endif; ?>


        <!-- Statistics -->
        <div class="stats-grid">
            <div class="stat-card">
                <div>📊 ጠቅላላ ምደባዎች</div>
                <div class="stat-value"><?php echo $total_count; ?></div>
            </div>
            <div class="stat-card">
                <div>🔒 የተቆለፉ</div>
                <div class="stat-value" style="color: var(--error-red);"><?php echo $locked_count; ?></div>
            </div>
            <div class="stat-card">
                <div>🔓 ያልተቆለፉ</div>
                <div class="stat-value" style="color: var(--success-green);"><?php echo $unlocked_count; ?></div>
            </div>
            <div class="stat-card">
                <div>👥 መምህራን</div>
                <div class="stat-value"><?php echo $stats['total_teachers'] ?? 0; ?></div>
            </div>
        </div>

        <!-- 4 Locking Methods -->
        <div class="methods-grid">
            <!-- METHOD 1: Lock by Class -->
            <div class="method-card method1">
                <div class="method-title">
                    📚 በክፍል መቆለፍ
                    <span class="method-badge">1 ክፍል - ሁሉም መምህራን</span>
                </div>
                <div class="method-desc">
                    አንድ ክፍል ይምረጡ እና በዚያ ክፍል ውስጥ ያሉ ሁሉም መምህራን ይቆለፋሉ።
                </div>
                <form method="POST" class="method-form">
                    <?php echo csrfField(); ?>
                    <div class="form-group">
                        <label>ክፍል ምረጥ</label>
                        <select name="class_id" class="form-control" required>
                            <option value="">ክፍል ምረጥ...</option>
                            <?php 
                            mysqli_data_seek($classes, 0);
                            while($class = mysqli_fetch_assoc($classes)): 
                            ?>
                            <option value="<?php echo $class['id']; ?>"><?php echo htmlspecialchars($class['name']); ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="btn-group">
                        <button type="submit" name="lock_by_class" value="lock" class="btn btn-lock">
                            🔒 ቍልፍ
                        </button>
                        <button type="submit" name="lock_by_class" value="unlock" class="btn btn-unlock">
                            🔓 ክፈት
                        </button>
                    </div>
                    <input type="hidden" name="action" value="lock">
                </form>
            </div>

            <!-- METHOD 2: Lock by Teacher -->
            <div class="method-card method2">
                <div class="method-title">
                    👨‍🏫 በመምህር መቆለፍ
                    <span class="method-badge">1 መምህር - ሁሉም ክፍሎች</span>
                </div>
                <div class="method-desc">
                    አንድ መምህር ይምረጡ እና ያ መምህር የሚያስተምራቸው ሁሉም ክፍሎች ይቆለፋሉ።
                </div>
                <form method="POST" class="method-form">
                    <?php echo csrfField(); ?>
                    <div class="form-group">
                        <label>መምህር ምረጥ</label>
                        <select name="teacher_id" class="form-control" required>
                            <option value="">መምህር ምረጥ...</option>
                            <?php 
                            mysqli_data_seek($teachers, 0);
                            while($teacher = mysqli_fetch_assoc($teachers)): 
                            ?>
                            <option value="<?php echo $teacher['id']; ?>"><?php echo htmlspecialchars($teacher['name']); ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="btn-group">
                        <button type="submit" name="lock_by_teacher" value="lock" class="btn btn-lock">
                            🔒 ቍልፍ
                        </button>
                        <button type="submit" name="lock_by_teacher" value="unlock" class="btn btn-unlock">
                            🔓 ክፈት
                        </button>
                    </div>
                    <input type="hidden" name="action" value="lock">
                </form>
            </div>

            <!-- METHOD 3: Lock Specific Combination -->
            <div class="method-card method3">
                <div class="method-title">
                    🔗 የተወሰነ መቆለፍ
                    <span class="method-badge">1 መምህር + 1 ክፍል</span>
                </div>
                <div class="method-desc">
                    ከታች ካለው ሠንጠረዥ አንድ የተወሰነ መምህር እና ክፍል ይምረጡ።
                </div>
                <div class="info-box" style="margin: 0;">
                    <span>👇</span>
                    <small>ከታች ባለው ሠንጠረዥ ውስጥ ቀጥታ መቆለፍ ይችላሉ</small>
                </div>
            </div>

            <!-- METHOD 4: Lock All -->
            <div class="method-card method4">
                <div class="method-title">
                    🌐 ሁሉንም መቆለፍ
                    <span class="method-badge">ሁሉም መምህራን + ክፍሎች</span>
                </div>
                <div class="method-desc">
                    በአንድ ጊዜ ሁሉንም መምህራን እና ክፍሎች ይቍለፉ ወይም ይክፈቱ።
                </div>
                <form method="POST" class="method-form" onsubmit="return confirm('እርግጠኛ ነዎት? ይህ ሁሉንም ምደባዎች ይቀይራል!')">
                    <?php echo csrfField(); ?>
                    <div class="btn-group">
                        <button type="submit" name="lock_all" value="lock" class="btn btn-lock">
                            🔒 ሁሉንም ቍልፍ
                        </button>
                        <button type="submit" name="lock_all" value="unlock" class="btn btn-unlock">
                            🔓 ሁሉንም ክፈት
                        </button>
                    </div>
                    <input type="hidden" name="action" value="lock">
                </form>
            </div>
        </div>

        <!-- All Assignments Table -->
        <div class="section">
            <div class="section-header">
                <h2><span>📋</span> ሁሉም የመምህራን ክፍል ምደባዎች</h2>
                <span><?php echo $total_count; ?> ምደባዎች</span>
            </div>

            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>ክፍል</th>
                            <th>መምህር</th>
                            <th>ሴሚስተር</th>
                            <th>የውጤት መቆለፊያ</th>
                            <th>የአቴንዳንስ መቆለፊያ</th>
                            <th>የዕቅድ መቆለፊያ</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if($assignments && mysqli_num_rows($assignments) > 0): ?>
                            <?php while($row = mysqli_fetch_assoc($assignments)): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($row['class_name']); ?></strong></td>
                                <td><?php echo htmlspecialchars($row['teacher_name']); ?></td>
                                <td><?php echo htmlspecialchars($row['semester_name']); ?></td>
                                <?php foreach (['marks' => 'locked', 'attendance' => 'attendance_locked', 'plan' => 'plan_locked'] as $type => $col): ?>
                                <td>
                                    <form method="POST" style="display:inline;">
                                        <?php echo csrfField(); ?>
                                        <input type="hidden" name="assignment_id" value="<?php echo $row['id']; ?>">
                                        <input type="hidden" name="lock_type" value="<?php echo $type; ?>">
                                        <button type="submit" name="toggle_lock_type"
                                                class="toggle-btn <?php echo !empty($row[$col]) ? 'locked' : 'unlocked'; ?>">
                                            <?php echo !empty($row[$col]) ? '🔒' : '🔓'; ?>
                                        </button>
                                    </form>
                                </td>
                                <?php endforeach; ?>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" style="text-align: center; padding: 50px;">
                                    <span style="font-size: 40px;">📭</span>
                                    <p>ምንም ምደባዎች አልተገኙም</p>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Lock Information -->
        <div class="info-box">
            <span>🔒</span>
            <div>
                <strong>የመቆለፊያ ማብራሪያ:</strong><br>
                • <strong>በክፍል መቆለፍ</strong> - አንድ ክፍል ሲቆለፍ በዚያ ክፍል ውስጥ ያሉ ሁሉም መምህራን ውጤት ማስገባት አይችሉም።<br>
                    • <strong>በመምህር መቆለፍ</strong> - አንድ መምህር ሲቆለፍ ያ መምህር በማንኛውም ክፍል ውጤት ማስገባት አይችልም።<br>
                    • <strong>የተወሰነ መቆለፍ</strong> - አንድ መምህር በአንድ ክፍል ብቻ እንዲቆለፍ ማድረግ።<br>
                    • <strong>ሁሉንም መቆለፍ</strong> - ሁሉም መምህራን በሁሉም ክፍሎች ውጤት ማስገባት አይችሉም።
            </div>
        </div>
    </div>

    <script>
        // Handle form submissions for lock_by_class and lock_by_teacher
        document.querySelectorAll('form.method-form').forEach(form => {
            form.addEventListener('submit', function(e) {
                const submitter = e.submitter;
                if(submitter) {
                    const action = submitter.value;
                    const actionText = action === 'lock' ? 'መቆለፍ' : 'መክፈት';
                    
                    let confirmMessage = '';
                    if(this.querySelector('select[name="class_id"]')) {
                        const className = this.querySelector('select[name="class_id"] option:checked').text;
                        confirmMessage = `በ${className} ክፍል ውስጥ ያሉትን ሁሉም መምህራን ${actionText} እርግጠኛ ነዎት?`;
                    } else if(this.querySelector('select[name="teacher_id"]')) {
                        const teacherName = this.querySelector('select[name="teacher_id"] option:checked').text;
                        confirmMessage = `${teacherName} የሚያስተምራቸውን ሁሉም ክፍሎች ${actionText} እርግጠኛ ነዎት?`;
                    } else if(this.querySelector('input[name="lock_all"]')) {
                        confirmMessage = `ሁሉንም ምደባዎች ${actionText} እርግጠኛ ነዎት?`;
                    }
                    
                    if(!confirm(confirmMessage)) {
                        e.preventDefault();
                        return;
                    }
                    
                    // Add the action to a hidden input
                    const actionInput = document.createElement('input');
                    actionInput.type = 'hidden';
                    actionInput.name = 'action';
                    actionInput.value = action;
                    this.appendChild(actionInput);
                }
            });
        });
    </script>
</body>
</html>
<?php mysqli_close($conn); ?>