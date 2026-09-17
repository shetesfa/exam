<?php
require_once 'db.php';
requireAdmin();

$message = '';
$error = '';

// Handle Add/Edit/Delete
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = "የደህንነት ማረጋገጫ አልተሳካም! እባክዎ እንደገና ይሞክሩ።";
    } else {
        if (isset($_POST['add_class'])) {
            $name = trim($_POST['name'] ?? '');
            $description = trim($_POST['description'] ?? '');
            $grade_id = intval($_POST['grade_id'] ?? 0) ?: null;
            
            if (!empty($name)) {
                $saved = dbExecute($conn, "INSERT INTO classes (name, description, grade_id) VALUES (?, ?, ?)", "ssi", [$name, $description, $grade_id]);
                if ($saved) {
                    $message = "ክፍሉ በተሳካ ሁኔታ ተፈጥሯል!";
                } else {
                    $error = "ስህተት ተከስቷል!";
                }
            } else {
                $error = "እባክዎ የክፍል ስም ያስገቡ!";
            }
        }
        
        if (isset($_POST['edit_class'])) {
            $class_id = intval($_POST['class_id'] ?? 0);
            $name = trim($_POST['name'] ?? '');
            $description = trim($_POST['description'] ?? '');
            $grade_id = intval($_POST['grade_id'] ?? 0) ?: null;
            
            if ($class_id > 0 && !empty($name)) {
                $updated = dbExecute($conn, "UPDATE classes SET name = ?, description = ?, grade_id = ? WHERE id = ?", "ssii", [$name, $description, $grade_id, $class_id]);
                if ($updated) {
                    $message = "የክፍል መረጃው ተሻሽሏል!";
                } else {
                    $error = "ስህተት ተከስቷል!";
                }
            }
        }
        
        if (isset($_POST['delete_class'])) {
            $class_id = intval($_POST['class_id'] ?? 0);
            
            if ($class_id > 0) {
                // Check if class has students
                $students_count = dbFetchOne($conn, "SELECT COUNT(*) as cnt FROM students WHERE class_id = ? AND (is_deleted = 0 OR is_deleted IS NULL)", "i", [$class_id]);
                if ($students_count && $students_count['cnt'] > 0) {
                    $error = "ይህ ክፍል ተማሪዎች አሉት! መጀመሪያ ተማሪዎቹን ወደ ሌላ ክፍል ያስተላልፉ።";
                } else {
                    // Delete assignments first
                    dbExecute($conn, "DELETE FROM teacher_class WHERE class_id = ?", "i", [$class_id]);
                    $deleted = dbExecute($conn, "DELETE FROM classes WHERE id = ?", "i", [$class_id]);
                    if ($deleted) {
                        $message = "ክፍሉ ተሰርዟል!";
                    } else {
                        $error = "ስህተት ተከስቷል!";
                    }
                }
            }
        }
    }
}

// Get all classes with statistics + their grade/division (if assigned)
$classes_query = "SELECT c.*, 
                  g.name_am AS grade_name, g.level_number,
                  d.code AS division_code, d.name_am AS division_name,
                  COUNT(DISTINCT s.id) as student_count,
                  COUNT(DISTINCT tc.teacher_id) as teacher_count
                  FROM classes c
                  LEFT JOIN grades g ON c.grade_id = g.id
                  LEFT JOIN divisions d ON g.division_id = d.id
                  LEFT JOIN students s ON c.id = s.class_id
                  LEFT JOIN teacher_class tc ON c.id = tc.class_id
                  GROUP BY c.id
                  ORDER BY d.sort_order, g.level_number, c.name";
$classes = mysqli_query($conn, $classes_query);

// Grade picker options, grouped by division (for the add/edit forms)
$grades_by_division = [];
$grades_res = mysqli_query($conn, "SELECT g.id, g.name_am, g.level_number, d.name_am AS division_name, d.sort_order
                                    FROM grades g JOIN divisions d ON g.division_id = d.id
                                    ORDER BY d.sort_order, g.level_number");
if ($grades_res) {
    while ($g = mysqli_fetch_assoc($grades_res)) {
        $grades_by_division[$g['division_name']][] = $g;
    }
}

$nav_active = 'manage_classes';
?>
<!DOCTYPE html>
<html lang="am">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>የክፍሎች አስተዳደር | አጸደ ትጉሃን</title>
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
            --info: #3B82F6;
        }

        html.dark-mode,
        html[data-theme="dark"],
        body.dark-mode {
            --bg-cream: #0B1120 !important;
            --bg-light: #0B1120 !important;
            --card-bg: #1E293B !important;
            --card-bg-subtle: #162032 !important;
            --text-main: #F1F5F9 !important;
            --text-muted: #94A3B8 !important;
            --border-color: #334155 !important;
            --border-subtle: #243247 !important;
            --gold-pale: rgba(245, 158, 11, 0.15) !important;
        }

        html.dark-mode body,
        body.dark-mode,
        html[data-theme="dark"] body {
            background-color: #0B1120 !important;
            background: #0B1120 !important;
            color: #F1F5F9 !important;
        }

        html.dark-mode .content-card,
        html.dark-mode .stat-box,
        html.dark-mode .stat-card,
        html.dark-mode .church-card,
        html.dark-mode .class-card,
        html.dark-mode .student-card,
        html.dark-mode .teacher-card,
        html.dark-mode .user-card,
        html.dark-mode .scheme-card,
        html.dark-mode .promotion-box,
        html.dark-mode .sim-summary,
        html.dark-mode .page-hero,
        html.dark-mode .backup-item,
        html.dark-mode .history-card,
        html.dark-mode .semester-banner,
        html.dark-mode .total-summary-card,
        html.dark-mode .table-wrapper,
        html.dark-mode .modal-card,
        html.dark-mode .modal-content,
        body.dark-mode .content-card,
        body.dark-mode .stat-box,
        body.dark-mode .stat-card,
        body.dark-mode .church-card,
        body.dark-mode .class-card,
        body.dark-mode .student-card,
        body.dark-mode .teacher-card,
        body.dark-mode .user-card,
        body.dark-mode .scheme-card,
        body.dark-mode .promotion-box,
        body.dark-mode .sim-summary,
        body.dark-mode .page-hero,
        body.dark-mode .backup-item,
        body.dark-mode .history-card,
        body.dark-mode .semester-banner,
        body.dark-mode .total-summary-card,
        body.dark-mode .table-wrapper,
        body.dark-mode .modal-card,
        body.dark-mode .modal-content,
        [data-theme="dark"] .content-card,
        [data-theme="dark"] .stat-box,
        [data-theme="dark"] .stat-card,
        [data-theme="dark"] .church-card,
        [data-theme="dark"] .class-card,
        [data-theme="dark"] .student-card,
        [data-theme="dark"] .teacher-card,
        [data-theme="dark"] .user-card,
        [data-theme="dark"] .scheme-card,
        [data-theme="dark"] .promotion-box,
        [data-theme="dark"] .sim-summary,
        [data-theme="dark"] .page-hero,
        [data-theme="dark"] .backup-item,
        [data-theme="dark"] .history-card,
        [data-theme="dark"] .semester-banner,
        [data-theme="dark"] .total-summary-card,
        [data-theme="dark"] .table-wrapper,
        [data-theme="dark"] .modal-card,
        [data-theme="dark"] .modal-content {
            background-color: #1E293B !important;
            background: #1E293B !important;
            color: #F1F5F9 !important;
            border-color: #334155 !important;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.4) !important;
        }

        html.dark-mode .form-input,
        html.dark-mode .filter-input,
        html.dark-mode .form-control,
        html.dark-mode .search-input,
        html.dark-mode .passmark-input,
        html.dark-mode .filter-select,
        html.dark-mode select,
        body.dark-mode .form-input,
        body.dark-mode .filter-input,
        body.dark-mode .form-control,
        body.dark-mode .search-input,
        body.dark-mode .passmark-input,
        body.dark-mode .filter-select,
        body.dark-mode select,
        [data-theme="dark"] .form-input,
        [data-theme="dark"] .filter-input,
        [data-theme="dark"] .form-control,
        [data-theme="dark"] .search-input,
        [data-theme="dark"] .passmark-input,
        [data-theme="dark"] .filter-select,
        [data-theme="dark"] select {
            background-color: #0F172A !important;
            background: #0F172A !important;
            color: #F8FAFC !important;
            border-color: #475569 !important;
        }

        html.dark-mode th,
        body.dark-mode th,
        [data-theme="dark"] th {
            background-color: #0F172A !important;
            background: #0F172A !important;
            color: #FCD34D !important;
            border-color: #334155 !important;
        }

        html.dark-mode td,
        body.dark-mode td,
        [data-theme="dark"] td {
            background-color: #1E293B !important;
            color: #E2E8F0 !important;
            border-color: #334155 !important;
        }

        html.dark-mode tr:hover td,
        body.dark-mode tr:hover td,
        [data-theme="dark"] tr:hover td {
            background-color: #26354A !important;
        }

        html.dark-mode .stat-val,
        html.dark-mode .stat-value,
        html.dark-mode .stat-num,
        body.dark-mode .stat-val,
        body.dark-mode .stat-value,
        body.dark-mode .stat-num,
        [data-theme="dark"] .stat-val,
        [data-theme="dark"] .stat-value,
        [data-theme="dark"] .stat-num {
            color: #FCD34D !important;
        }

        html.dark-mode .stat-lbl,
        html.dark-mode .stat-label,
        body.dark-mode .stat-lbl,
        body.dark-mode .stat-label,
        [data-theme="dark"] .stat-lbl,
        [data-theme="dark"] .stat-label {
            color: #94A3B8 !important;
        }


        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Segoe UI', system-ui, -apple-system, sans-serif; }
        body { background: var(--bg-cream); color: var(--text-main); min-height: 100vh; }

        .main-container { max-width: 1200px; margin: 12px auto; padding: 0 10px 40px; }

        /* Page Header Card */
        .page-header-card {
            background: linear-gradient(135deg, #8B4513 0%, #A52A2A 100%);
            border-radius: 16px;
            padding: 18px 16px;
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
            content: '🏫';
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
            border-radius: 14px;
            padding: 16px 14px;
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
        .header-badge {
            background: var(--gold-pale);
            color: var(--brown-dark);
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 700;
        }

        /* Forms */
        .form-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 16px;
            margin-bottom: 16px;
        }
        .form-group {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }
        .form-group.full-width {
            grid-column: 1 / -1;
        }
        .form-group label {
            font-size: 13px;
            font-weight: 600;
            color: var(--brown-dark);
        }
        .form-control {
            width: 100%;
            padding: 11px 14px;
            border: 1.5px solid var(--border-color);
            border-radius: 10px;
            font-size: 14px;
            outline: none;
            transition: all 0.2s ease;
            background: var(--card-bg);
            color: var(--text-main);
        }
        .form-control:focus {
            border-color: var(--gold-dark);
            box-shadow: 0 0 0 3px rgba(218, 165, 32, 0.15);
        }
        textarea.form-control {
            min-height: 70px;
            resize: vertical;
        }
        .btn-primary-action {
            background: linear-gradient(135deg, var(--gold-primary) 0%, var(--gold-dark) 100%);
            color: var(--brown-dark);
            border: none;
            padding: 12px 24px;
            border-radius: 10px;
            font-size: 14px;
            font-weight: 700;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.2s ease;
            box-shadow: 0 3px 10px rgba(218, 165, 32, 0.25);
        }
        .btn-primary-action:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(218, 165, 32, 0.35);
        }

        /* Class Cards Grid */
        .classes-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 14px;
        }
        .class-card {
            background: var(--card-bg);
            border-radius: 16px;
            border: 1.5px solid var(--border-color);
            padding: 20px;
            box-shadow: 0 4px 14px rgba(0,0,0,0.04);
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            transition: all 0.25s ease;
            position: relative;
        }
        .class-card:hover {
            transform: translateY(-4px);
            border-color: var(--gold-dark);
            box-shadow: 0 10px 24px rgba(139, 69, 19, 0.1);
        }
        .class-card-top {
            margin-bottom: 14px;
        }
        .grade-tag {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            font-size: 11.5px;
            font-weight: 700;
            padding: 4px 10px;
            border-radius: 20px;
            margin-bottom: 10px;
        }
        .grade-tag.children { background: #DBEAFE; color: #1D4ED8; }
        .grade-tag.youth { background: #FEF3C7; color: #92400E; }
        .grade-tag.unassigned { background: #F3F4F6; color: #6B7280; }

        .class-title {
            font-size: 17px;
            font-weight: 800;
            color: var(--brown-dark);
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 6px;
        }
        .class-desc {
            font-size: 13px;
            color: var(--text-muted);
            line-height: 1.4;
            min-height: 36px;
        }

        /* Stats Inside Class Card */
        .class-metrics {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
            background: #F9FAFB;
            padding: 12px;
            border-radius: 10px;
            margin-bottom: 16px;
            border: 1px solid #F3F4F6;
        }
        .class-metric-item {
            text-align: center;
        }
        .class-metric-val {
            font-size: 18px;
            font-weight: 800;
            color: var(--brown-dark);
        }
        .class-metric-lbl {
            font-size: 11px;
            color: var(--text-muted);
            font-weight: 600;
            text-transform: uppercase;
        }

        /* Class Card Action Buttons */
        .class-actions-bar {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
        }
        .btn-view-st {
            flex: 1.5;
            background: #EFF6FF;
            color: #2563EB;
            padding: 8px 12px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 700;
            font-size: 12.5px;
            text-align: center;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 5px;
            transition: all 0.2s;
        }
        .btn-view-st:hover { background: #DBEAFE; }

        .btn-edit-cls {
            background: #FEF3C7;
            color: #B45309;
            border: none;
            padding: 8px 12px;
            border-radius: 8px;
            font-weight: 700;
            font-size: 12.5px;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            transition: all 0.2s;
        }
        .btn-edit-cls:hover { background: #FDE68A; }

        .btn-del-cls {
            background: #FEE2E2;
            color: #DC2626;
            border: none;
            padding: 8px 10px;
            border-radius: 8px;
            font-weight: 700;
            font-size: 12.5px;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            transition: all 0.2s;
        }
        .btn-del-cls:hover { background: #FCA5A5; }

        /* Modal */
        .modal-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            backdrop-filter: blur(4px);
            z-index: 1000;
            align-items: center;
            justify-content: center;
            padding: 16px;
        }
        .modal-card {
            background: var(--card-bg);
            border-radius: 18px;
            padding: 26px;
            width: 100%;
            max-width: 500px;
            border: 2px solid var(--gold-primary);
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.2);
            animation: modalPop 0.25s ease-out;
            position: relative;
        }
        @keyframes modalPop {
            from { transform: scale(0.92); opacity: 0; }
            to { transform: scale(1); opacity: 1; }
        }
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 18px;
            padding-bottom: 10px;
            border-bottom: 2px solid var(--gold-pale);
        }
        .modal-header h3 {
            color: var(--brown-dark);
            font-size: 18px;
            font-weight: 700;
        }
        .modal-close {
            background: none;
            border: none;
            font-size: 22px;
            cursor: pointer;
            color: var(--text-muted);
            transition: color 0.2s;
        }
        .modal-close:hover { color: var(--error); }

        /* Empty State */
        .empty-classes {
            text-align: center;
            padding: 50px 20px;
            color: var(--text-muted);
        }
        .empty-classes-icon {
            font-size: 50px;
            margin-bottom: 12px;
            opacity: 0.7;
        }

        @media (min-width: 769px) {
            .main-container { padding: 0 10px 40px; margin: 12px auto; }
            .page-header-card { padding: 18px 16px; }
            .content-card { padding: 16px 14px; border-radius: 14px; }
            .form-grid { grid-template-columns: 1fr; }
            .btn-primary-action { width: 100%; justify-content: center; min-height: 44px; }
            .classes-grid { grid-template-columns: 1fr; gap: 14px; }
            .class-actions-bar { flex-direction: row; }
            .btn-view-st { flex: 1.5; }
        }
    </style>
</head>
<body>
    <?php include 'mobile_nav.php'; ?>

    <div class="main-container">
        <!-- Page Header -->
        <div class="page-header-card">
            <div class="header-info">
                <h1>🏫 የክፍሎች አስተዳደር</h1>
                <p>ክፍሎችን ይፍጠሩ፣ ከስርዓተ-ትምህርት ደረጃዎች ጋር ያገናኙ እና የተማሪ/መምህር ምደባዎችን ይከታተሉ።</p>
            </div>
        </div>

        <?php if($message): ?>
        <div class="message success">✅ <?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?></div>
        <?php endif; ?>

        <?php if($error): ?>
        <div class="message error">⚠️ <?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
        <?php endif; ?>

        <!-- Statistics -->
        <?php
        $total_classes = mysqli_num_rows($classes);
        $total_students = 0;
        $total_teachers = 0;
        mysqli_data_seek($classes, 0);
        while($class = mysqli_fetch_assoc($classes)) {
            $total_students += $class['student_count'];
            $total_teachers += $class['teacher_count'];
        }
        ?>
        <div class="stats-grid">
            <div class="stat-box">
                <div class="stat-icon">🏫</div>
                <div class="stat-data">
                    <div class="stat-val"><?php echo $total_classes; ?></div>
                    <div class="stat-lbl">ጠቅላላ ክፍሎች</div>
                </div>
            </div>
            <div class="stat-box">
                <div class="stat-icon">👥</div>
                <div class="stat-data">
                    <div class="stat-val"><?php echo number_format($total_students); ?></div>
                    <div class="stat-lbl">ጠቅላላ ተማሪዎች</div>
                </div>
            </div>
            <div class="stat-box">
                <div class="stat-icon">👨‍🏫</div>
                <div class="stat-data">
                    <div class="stat-val"><?php echo $total_teachers; ?></div>
                    <div class="stat-lbl">የተመደቡ መምህራን</div>
                </div>
            </div>
        </div>

        <!-- Add Class Form -->
        <div class="content-card">
            <div class="content-card-header">
                <h2><span>➕</span> አዲስ ክፍል መፍጠሪያ</h2>
            </div>
            <form method="POST">
                <?php echo csrfField(); ?>
                <div class="form-grid">
                    <div class="form-group">
                        <label>የክፍል ስም <span style="color: var(--error);">*</span></label>
                        <input type="text" name="name" class="form-control" required placeholder="ለምሳሌ: 7ኛ ክፍል 'ሀ'">
                    </div>
                    <div class="form-group">
                        <label>የትምህርት ደረጃ / ክፍል</label>
                        <select name="grade_id" class="form-control">
                            <option value="">-- ያልተመደበ --</option>
                            <?php foreach ($grades_by_division as $divName => $gradeList): ?>
                                <optgroup label="<?php echo htmlspecialchars($divName); ?>">
                                    <?php foreach ($gradeList as $g): ?>
                                        <option value="<?php echo $g['id']; ?>"><?php echo htmlspecialchars($g['name_am']); ?></option>
                                    <?php endforeach; ?>
                                </optgroup>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group full-width">
                        <label>መግለጫ (አማራጭ)</label>
                        <textarea name="description" class="form-control" placeholder="ስለ ክፍሉ አጭር መግለጫ ካለ ያስገቡ..."></textarea>
                    </div>
                </div>
                <button type="submit" name="add_class" class="btn-primary-action">
                    ➕ አዲስ ክፍል ፍጠር
                </button>
            </form>
        </div>

        <!-- Classes List -->
        <div class="content-card">
            <div class="content-card-header">
                <h2><span>📚</span> የነባር ክፍሎች ዝርዝር</h2>
                <span class="header-badge"><?php echo $total_classes; ?> ክፍሎች</span>
            </div>

            <?php if($classes && mysqli_num_rows($classes) > 0): ?>
            <div class="classes-grid">
                <?php 
                mysqli_data_seek($classes, 0);
                while($class = mysqli_fetch_assoc($classes)): 
                    $divClass = ($class['division_code'] === 'CHILDREN') ? 'children' : (($class['division_code'] === 'YOUTH') ? 'youth' : 'unassigned');
                ?>
                <div class="class-card">
                    <div class="class-card-top">
                        <?php if($class['grade_name']): ?>
                        <div class="grade-tag <?php echo $divClass; ?>">
                            🏷️ <?php echo htmlspecialchars($class['division_name'] . ' · ' . $class['grade_name']); ?>
                        </div>
                        <?php else: ?>
                        <div class="grade-tag unassigned">
                            ⚠️ ደረጃ ያልተመደበ
                        </div>
                        <?php endif; ?>

                        <div class="class-title">
                            🏫 <?php echo htmlspecialchars($class['name']); ?>
                        </div>
                        <div class="class-desc">
                            <?php echo htmlspecialchars($class['description'] ?: 'ምንም መግለጫ አልተሰጠም።'); ?>
                        </div>
                    </div>

                    <div>
                        <div class="class-metrics">
                            <div class="class-metric-item">
                                <div class="class-metric-val"><?php echo $class['student_count']; ?></div>
                                <div class="class-metric-lbl">ተማሪዎች</div>
                            </div>
                            <div class="class-metric-item">
                                <div class="class-metric-val"><?php echo $class['teacher_count']; ?></div>
                                <div class="class-metric-lbl">መምህራን</div>
                            </div>
                        </div>

                        <div class="class-actions-bar">
                            <a href="manage_students.php?class_id=<?php echo $class['id']; ?>" class="btn-view-st">
                                👥 ተማሪዎች
                            </a>
                            <button onclick="editClass(<?php echo $class['id']; ?>, '<?php echo htmlspecialchars(addslashes($class['name']), ENT_QUOTES); ?>', '<?php echo htmlspecialchars(addslashes($class['description'] ?? ''), ENT_QUOTES); ?>', <?php echo $class['grade_id'] ? (int)$class['grade_id'] : 'null'; ?>)" 
                                    class="btn-edit-cls">
                                ✏️ አርትዕ
                            </button>
                            <form method="POST" style="display: inline;" 
                                  onsubmit="return confirm('እርግጠኛ ነዎት ክፍል [<?php echo htmlspecialchars(addslashes($class['name']), ENT_QUOTES); ?>] መሰረዝ ይፈልጋሉ? ይህ ክዋኔ ሊቀለበስ አይችልም!')">
                                <?php echo csrfField(); ?>
                                <input type="hidden" name="class_id" value="<?php echo $class['id']; ?>">
                                <button type="submit" name="delete_class" class="btn-del-cls" title="ሰርዝ">
                                    🗑️
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
                <?php endwhile; ?>
            </div>
            <?php else: ?>
            <div class="empty-classes">
                <div class="empty-classes-icon">🏫</div>
                <h3 style="color: var(--brown-dark); margin-bottom: 8px;">ምንም ክፍሎች አልተገኙም</h3>
                <p>እባክዎ ከላይ ያለውን ቅጽ በመጠቀም አዲስ ክፍል ይመዝግቡ።</p>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Edit Modal -->
    <div id="editModal" class="modal-overlay">
        <div class="modal-card">
            <div class="modal-header">
                <h3>✏️ የክፍል መረጃ ማስተካከያ</h3>
                <button class="modal-close" onclick="closeModal()">&times;</button>
            </div>
            <form method="POST" id="editForm">
                <?php echo csrfField(); ?>
                <input type="hidden" name="class_id" id="edit_id">
                <div class="form-group" style="margin-bottom: 14px;">
                    <label>የክፍል ስም <span style="color: var(--error);">*</span></label>
                    <input type="text" name="name" id="edit_name" class="form-control" required>
                </div>
                <div class="form-group" style="margin-bottom: 14px;">
                    <label>የትምህርት ደረጃ / ክፍል</label>
                    <select name="grade_id" id="edit_grade_id" class="form-control">
                        <option value="">-- ያልተመደበ --</option>
                        <?php foreach ($grades_by_division as $divName => $gradeList): ?>
                            <optgroup label="<?php echo htmlspecialchars($divName); ?>">
                                <?php foreach ($gradeList as $g): ?>
                                    <option value="<?php echo $g['id']; ?>"><?php echo htmlspecialchars($g['name_am']); ?></option>
                                <?php endforeach; ?>
                            </optgroup>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group" style="margin-bottom: 18px;">
                    <label>መግለጫ</label>
                    <textarea name="description" id="edit_description" class="form-control" rows="3"></textarea>
                </div>
                <button type="submit" name="edit_class" class="btn-primary-action" style="width: 100%; justify-content: center;">
                    💾 ለውጦችን አስቀምጥ
                </button>
            </form>
        </div>
    </div>

    <script>
        function editClass(id, name, description, gradeId) {
            document.getElementById('edit_id').value = id;
            document.getElementById('edit_name').value = name;
            document.getElementById('edit_description').value = description || '';
            document.getElementById('edit_grade_id').value = gradeId || '';
            document.getElementById('editModal').style.display = 'flex';
        }

        function closeModal() {
            document.getElementById('editModal').style.display = 'none';
        }

        window.onclick = function(event) {
            var modal = document.getElementById('editModal');
            if (event.target === modal) {
                closeModal();
            }
        }
    </script>
</body>
</html>
<?php mysqli_close($conn); ?>