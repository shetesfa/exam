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

$message = '';
$error = '';

// Get teacher's assigned classes
$classes = getTeacherClasses($conn, $teacher_id, $semester_id);

// Handle save scheme
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_scheme'])) {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = "የደህንነት ማረጋገጫ አልተሳካም!";
    } else {
        $class_id = intval($_POST['class_id'] ?? 0);
        // IDOR Protection: verify teacher is assigned to this class
        $tc_check = dbFetchOne(
            $conn,
            "SELECT id, locked FROM teacher_class WHERE teacher_id = ? AND class_id = ? AND semester_id = ?",
            "iii",
            [$teacher_id, $class_id, $semester_id]
        );
        if (!$tc_check && !isAdmin()) {
            $error = "ለዚህ ክፍል የውጤት መስፈርት የመቀየር ፈቃድ የለዎትም!";
        } elseif ($tc_check && intval($tc_check['locked']) === 1 && !isAdmin()) {
            $error = "🔒 ይህ ክፍል ተቆልፏል! የውጤት መስፈርት መቀየር አይቻልም።";
        } else {
            $data = [
                'c1_name' => trim($_POST['c1_name'] ?? 'Assignment'),
                'c1_perc' => floatval($_POST['c1_perc'] ?? 0),
                'c2_name' => trim($_POST['c2_name'] ?? 'Participation'),
                'c2_perc' => floatval($_POST['c2_perc'] ?? 0),
                'c3_name' => trim($_POST['c3_name'] ?? 'Attendance'),
                'c3_perc' => floatval($_POST['c3_perc'] ?? 0),
                'c4_name' => trim($_POST['c4_name'] ?? 'Mid Exam'),
                'c4_perc' => floatval($_POST['c4_perc'] ?? 0),
                'c5_name' => trim($_POST['c5_name'] ?? 'Final Exam'),
                'c5_perc' => floatval($_POST['c5_perc'] ?? 0)
            ];
            
            $result = saveMarkingScheme($conn, $teacher_id, $class_id, $semester_id, $data);
            if ($result['success']) {
                $message = $result['message'];
            } else {
                $error = $result['message'];
            }
        }
    }
}

// Get selected class scheme
$selected_class_id = isset($_GET['class_id']) ? intval($_GET['class_id']) : (!empty($classes) ? $classes[0]['class_id'] : 0);

// IDOR Protection: verify selected class belongs to this teacher
$class_found = false;
foreach ($classes as $c) {
    if (intval($c['class_id']) === $selected_class_id) {
        $class_found = true;
        break;
    }
}
if (!$class_found && !empty($classes)) {
    $selected_class_id = intval($classes[0]['class_id']);
}

$current_scheme = null;
if ($selected_class_id) {
    $current_scheme = getMarkingScheme($conn, $teacher_id, $selected_class_id, $semester_id);
}
$nav_active = 'teacher_marking_scheme';
?>
<!DOCTYPE html>
<html lang="am">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>የውጤት መስፈርት ማስተካከያ | አጸደ ትጉሃን</title>
    <?php include 'pwa_head.php'; ?>
    <style>
        :root {
            --brown-dark: #8B4513;
            --brown-medium: #A52A2A;
            --gold-primary: #FFD700;
            --gold-dark: #DAA520;
            --gold-pale: #FFF8DC;
            --bg-light: #FAF9F6;
            --card-bg: #FFFFFF;
            --text-main: #1F2937;
            --text-muted: #6B7280;
            --border-color: #E5E7EB;
            --success-green: #10B981;
            --error-red: #EF4444;
            --warning-amber: #F59E0B;
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


        [data-theme="dark"] {
            --bg-light: #111827;
            --card-bg: #1F2937;
            --text-main: #F9FAFB;
            --text-muted: #9CA3AF;
            --border-color: #374151;
            --gold-pale: rgba(218, 165, 32, 0.15);
        }

        body {
            background: var(--bg-light);
            color: var(--text-main);
            font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
            margin: 0;
            padding: 0;
            min-height: 100vh;
        }

        .main-container {
            max-width: 950px;
            margin: 15px auto;
            padding: 0 12px 30px;
        }

        /* Page Hero Header */
        .page-hero {
            background: var(--card-bg);
            border-radius: 18px;
            padding: 18px;
            border: 1px solid var(--border-color);
            box-shadow: 0 4px 15px rgba(139, 69, 19, 0.05);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 16px;
            margin-bottom: 25px;
            position: relative;
            overflow: hidden;
        }

        .page-hero::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--brown-dark), var(--gold-primary), var(--brown-dark));
        }

        .hero-left {
            display: flex;
            align-items: center;
            gap: 16px;
        }

        .hero-icon {
            width: 52px;
            height: 52px;
            border-radius: 14px;
            background: linear-gradient(135deg, rgba(139,69,19,0.12), rgba(218,165,32,0.22));
            color: var(--brown-dark);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 26px;
            border: 1px solid rgba(218,165,32,0.3);
            flex-shrink: 0;
        }

        [data-theme="dark"] .hero-icon {
            color: var(--gold-primary);
        }

        .hero-title {
            font-size: 19px;
            font-weight: 800;
            margin: 0 0 4px;
            color: var(--text-main);
        }

        .hero-subtitle {
            font-size: 13.5px;
            color: var(--text-muted);
            margin: 0;
        }

        /* Alert notifications */
        .message-banner {
            padding: 14px 18px;
            border-radius: 12px;
            margin-bottom: 22px;
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 14px;
            font-weight: 600;
            animation: fadeIn 0.3s ease;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-6px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .message-success {
            background: rgba(16, 185, 129, 0.1);
            color: #059669;
            border: 1px solid rgba(16, 185, 129, 0.3);
        }

        .message-error {
            background: rgba(239, 68, 68, 0.1);
            color: #DC2626;
            border: 1px solid rgba(239, 68, 68, 0.25);
        }

        /* Class tabs selector */
        .class-selector-wrap {
            margin-bottom: 24px;
        }

        .selector-label {
            font-size: 13px;
            font-weight: 700;
            color: var(--text-muted);
            margin-bottom: 10px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .class-tabs {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .class-tab {
            padding: 10px 18px;
            border-radius: 12px;
            background: var(--card-bg);
            border: 1.5px solid var(--border-color);
            color: var(--text-main);
            text-decoration: none;
            font-weight: 700;
            font-size: 13.5px;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.2s ease;
            box-shadow: 0 1px 3px rgba(0,0,0,0.03);
        }

        .class-tab:hover {
            border-color: var(--gold-dark);
            transform: translateY(-1px);
        }

        .class-tab.active {
            background: linear-gradient(135deg, var(--brown-dark), var(--brown-medium));
            color: #FFFFFF;
            border-color: var(--brown-dark);
            box-shadow: 0 3px 10px rgba(139, 69, 19, 0.25);
        }

        /* Scheme Form Card */
        .scheme-card {
            background: var(--card-bg);
            border-radius: 18px;
            padding: 18px 14px;
            border: 1px solid var(--border-color);
            box-shadow: 0 4px 16px rgba(0,0,0,0.04);
        }

        .scheme-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 22px;
            padding-bottom: 14px;
            border-bottom: 1px solid var(--border-color);
            flex-wrap: wrap;
            gap: 12px;
        }

        .scheme-heading {
            font-size: 18px;
            font-weight: 800;
            color: var(--brown-dark);
            display: flex;
            align-items: center;
            gap: 10px;
            margin: 0;
        }

        [data-theme="dark"] .scheme-heading {
            color: var(--gold-primary);
        }

        .preset-buttons {
            display: flex;
            gap: 8px;
        }

        .preset-btn {
            font-size: 11.5px;
            padding: 5px 10px;
            border-radius: 8px;
            background: var(--gold-pale);
            color: var(--brown-dark);
            border: 1px solid rgba(218,165,32,0.3);
            font-weight: 700;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        [data-theme="dark"] .preset-btn {
            color: var(--gold-primary);
        }

        .preset-btn:hover {
            background: var(--gold-primary);
            color: #000;
        }

        /* Component Rows */
        .components-list {
            display: flex;
            flex-direction: column;
            gap: 14px;
            margin-bottom: 24px;
        }

        .component-item {
            background: rgba(139, 69, 19, 0.02);
            border: 1px solid var(--border-color);
            border-radius: 14px;
            padding: 16px;
            display: grid;
            grid-template-columns: 35px 1fr 130px;
            gap: 14px;
            align-items: center;
            transition: all 0.2s ease;
        }

        [data-theme="dark"] .component-item {
            background: rgba(255, 255, 255, 0.02);
        }

        .component-item:hover {
            border-color: rgba(218, 165, 32, 0.5);
            background: rgba(255, 215, 0, 0.02);
        }

        .comp-badge {
            width: 34px;
            height: 34px;
            border-radius: 50%;
            background: var(--gold-pale);
            color: var(--brown-dark);
            font-weight: 800;
            font-size: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 1px solid rgba(218,165,32,0.3);
        }

        [data-theme="dark"] .comp-badge {
            color: var(--gold-primary);
        }

        .form-group {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }

        .form-label {
            font-size: 12px;
            font-weight: 700;
            color: var(--text-muted);
        }

        .form-input {
            width: 100%;
            padding: 10px 14px;
            border: 1.5px solid var(--border-color);
            border-radius: 10px;
            font-size: 14px;
            background: var(--card-bg);
            color: var(--text-main);
            box-sizing: border-box;
            transition: all 0.2s ease;
        }

        .form-input:focus {
            outline: none;
            border-color: var(--gold-dark);
            box-shadow: 0 0 0 3px rgba(218, 165, 32, 0.15);
        }

        .perc-input-wrap {
            position: relative;
            display: flex;
            align-items: center;
        }

        .perc-input-wrap .form-input {
            padding-right: 28px;
            font-weight: 800;
            text-align: right;
        }

        .perc-symbol {
            position: absolute;
            right: 10px;
            font-weight: 800;
            color: var(--text-muted);
            pointer-events: none;
            font-size: 13px;
        }

        /* Dynamic Progress Bar & Total Box */
        .total-summary-card {
            background: var(--card-bg);
            border: 1.5px solid var(--border-color);
            border-radius: 14px;
            padding: 18px 20px;
            margin: 24px 0;
            box-shadow: 0 2px 8px rgba(0,0,0,0.02);
        }

        .total-info-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 12px;
        }

        .total-label {
            font-weight: 700;
            font-size: 15px;
            color: var(--text-main);
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .total-val-badge {
            font-size: 22px;
            font-weight: 900;
            letter-spacing: -0.5px;
            padding: 4px 14px;
            border-radius: 10px;
            transition: all 0.3s ease;
        }

        .total-val-badge.valid {
            background: rgba(16, 185, 129, 0.12);
            color: #059669;
            border: 1px solid rgba(16, 185, 129, 0.3);
        }

        .total-val-badge.under {
            background: rgba(245, 158, 11, 0.12);
            color: #D97706;
            border: 1px solid rgba(245, 158, 11, 0.3);
        }

        .total-val-badge.over {
            background: rgba(239, 68, 68, 0.12);
            color: #DC2626;
            border: 1px solid rgba(239, 68, 68, 0.3);
        }

        /* Progress Bar Track */
        .progress-track {
            height: 10px;
            background: rgba(0,0,0,0.06);
            border-radius: 10px;
            overflow: hidden;
            position: relative;
        }

        [data-theme="dark"] .progress-track {
            background: rgba(255,255,255,0.08);
        }

        .progress-fill {
            height: 100%;
            width: 0%;
            border-radius: 10px;
            transition: width 0.3s ease, background 0.3s ease;
        }

        .progress-status-hint {
            font-size: 12.5px;
            margin-top: 8px;
            color: var(--text-muted);
            font-weight: 600;
        }

        /* Save Button */
        .btn-save {
            width: 100%;
            padding: 14px;
            background: linear-gradient(135deg, var(--brown-dark), var(--brown-medium));
            color: #FFFFFF;
            border: none;
            border-radius: 12px;
            font-size: 16px;
            font-weight: 800;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            box-shadow: 0 3px 12px rgba(139, 69, 19, 0.25);
            transition: all 0.2s ease;
        }

        .btn-save:hover:not(:disabled) {
            transform: translateY(-2px);
            filter: brightness(1.1);
        }

        .btn-save:disabled {
            opacity: 0.55;
            cursor: not-allowed;
            filter: grayscale(0.6);
            box-shadow: none;
        }

        .hint-notice {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            padding: 14px;
            border-radius: 10px;
            background: rgba(218, 165, 32, 0.08);
            border: 1px solid rgba(218, 165, 32, 0.25);
            margin-top: 20px;
            font-size: 13px;
            color: var(--text-muted);
        }

        @media (max-width: 650px) {
            .main-container { padding: 0 12px 30px; margin: 15px auto; }
            .page-hero { padding: 18px; }
            .hero-title { font-size: 19px; }
            .scheme-card { padding: 18px 14px; }
            .component-item {
                grid-template-columns: 1fr;
                gap: 10px;
                padding: 12px;
            }
            .comp-badge {
                display: none;
            }
            .preset-buttons {
                width: 100%;
                justify-content: flex-start;
            }
        }
    </style>
</head>
<body>
    <?php include 'mobile_nav.php'; ?>

    <div class="main-container">
        <!-- Hero Header -->
        <div class="page-hero">
            <div class="hero-left">
                <div class="hero-icon">⚖️</div>
                <div>
                    <h1 class="hero-title">የውጤት መስፈርት ማስተካከያ</h1>
                    <p class="hero-subtitle">ለእያንዳንዱ ክፍል የውጤት ማከፋፈያ መቶኛዎችን (ድምር 100%) ይወስኑ</p>
                </div>
            </div>
            <div>
                <a href="dashboard_teacher.php" class="class-tab" style="font-size: 13px;">
                    <span>←</span> ወደ ዳሽቦርድ ተመለስ
                </a>
            </div>
        </div>

        <?php if ($message): ?>
        <div class="message-banner message-success">
            <span>✅</span>
            <span><?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?></span>
        </div>
        <?php endif; ?>

        <?php if ($error): ?>
        <div class="message-banner message-error">
            <span>⚠️</span>
            <span><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></span>
        </div>
        <?php endif; ?>

        <?php if (!empty($classes)): ?>
        <!-- Class Tabs -->
        <div class="class-selector-wrap">
            <div class="selector-label">የሚያስተምሩትን ክፍል ይምረጡ፦</div>
            <div class="class-tabs">
                <?php foreach ($classes as $class): ?>
                <a href="?class_id=<?php echo $class['class_id']; ?>" 
                   class="class-tab <?php echo $selected_class_id == $class['class_id'] ? 'active' : ''; ?>">
                    <span>🏫</span>
                    <span><?php echo htmlspecialchars($class['class_name']); ?></span>
                </a>
                <?php endforeach; ?>
            </div>
        </div>

        <?php if ($selected_class_id): ?>
        <div class="scheme-card">
            <div class="scheme-header">
                <h2 class="scheme-heading">
                    <span>📊</span>
                    <?php 
                        $className = '';
                        foreach($classes as $c) {
                            if($c['class_id'] == $selected_class_id) {
                                $className = $c['class_name'];
                                break;
                            }
                        }
                        echo 'የውጤት መስፈርት - ' . htmlspecialchars($className);
                    ?>
                </h2>
                <div class="preset-buttons">
                    <button type="button" class="preset-btn" onclick="applyPreset([20, 20, 10, 25, 25])">
                        ⚡ መደበኛ (20/20/10/25/25)
                    </button>
                    <button type="button" class="preset-btn" onclick="applyPreset([10, 10, 10, 30, 40])">
                        ⚡ ፈተና-መር (10/10/10/30/40)
                    </button>
                </div>
            </div>

            <form method="POST" id="schemeForm">
                <?php echo csrfField(); ?>
                <input type="hidden" name="class_id" value="<?php echo $selected_class_id; ?>">

                <div class="components-list">
                    <?php
                    $components = [
                        1 => ['name' => $current_scheme['component1_name'] ?? 'Assignment', 'perc' => $current_scheme['component1_percentage'] ?? 20],
                        2 => ['name' => $current_scheme['component2_name'] ?? 'Participation', 'perc' => $current_scheme['component2_percentage'] ?? 20],
                        3 => ['name' => $current_scheme['component3_name'] ?? 'Attendance', 'perc' => $current_scheme['component3_percentage'] ?? 10],
                        4 => ['name' => $current_scheme['component4_name'] ?? 'Mid Exam', 'perc' => $current_scheme['component4_percentage'] ?? 25],
                        5 => ['name' => $current_scheme['component5_name'] ?? 'Final Exam', 'perc' => $current_scheme['component5_percentage'] ?? 25],
                    ];

                    foreach ($components as $index => $comp):
                    ?>
                    <div class="component-item">
                        <div class="comp-badge"><?php echo $index; ?></div>
                        <div class="form-group">
                            <label class="form-label">የክፍል <?php echo $index; ?> መጠሪያ ስም</label>
                            <input type="text" name="c<?php echo $index; ?>_name" id="c<?php echo $index; ?>_name" class="form-input" 
                                   value="<?php echo htmlspecialchars($comp['name']); ?>" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">ድርሻ መቶኛ</label>
                            <div class="perc-input-wrap">
                                <input type="number" name="c<?php echo $index; ?>_perc" id="c<?php echo $index; ?>_perc" 
                                       class="form-input perc-input" 
                                       value="<?php echo $comp['perc']; ?>" min="0" max="100" step="0.01" required>
                                <span class="perc-symbol">%</span>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>

                <!-- Total Summary & Dynamic Progress -->
                <div class="total-summary-card">
                    <div class="total-info-row">
                        <div class="total-label">
                            <span>📈</span>
                            <span>አጠቃላይ የመቶኛ ድምር</span>
                        </div>
                        <div class="total-val-badge" id="totalDisplay">100%</div>
                    </div>
                    <div class="progress-track">
                        <div class="progress-fill" id="progressFill"></div>
                    </div>
                    <div class="progress-status-hint" id="statusHint">
                        የሁሉም 5 ክፍሎች ድምር በትክክል 100% መሆን አለበት።
                    </div>
                </div>

                <button type="submit" name="save_scheme" class="btn-save" id="saveBtn">
                    <span>💾</span>
                    <span>የውጤት መስፈርቱን አስቀምጥ</span>
                </button>
            </form>

            <div class="hint-notice">
                <span style="font-size: 18px;">💡</span>
                <div>
                    <strong>ማስታወሻ፦</strong> የውጤት መስፈርቱ ሲቀየር ቀደም ሲል ለዚህ ክፍል የተሞሉ ውጤቶች ካሉ ባስቀመጡት አዲስ መቶኛ መሰረት ዳግም ይሰላሉ። ጠቅላላ ድምሩ በትክክል 100% ካልሞላ ማስቀመጥ አይቻልም።
                </div>
            </div>
        </div>
        <?php endif; ?>

        <?php else: ?>
        <div class="scheme-card" style="text-align: center; padding: 50px 20px;">
            <span style="font-size: 50px; display: block; margin-bottom: 15px;">📚</span>
            <h3 style="margin-bottom: 8px;">ምንም የተመደበ ክፍል የለም</h3>
            <p style="color: var(--text-muted); font-size: 14px;">ለዚህ መንፈቀ ዓመት እስካሁን የተመደበልዎት ክፍል የለም።</p>
        </div>
        <?php endif; ?>
    </div>

    <script>
        const percInputs = document.querySelectorAll('.perc-input');
        const totalDisplay = document.getElementById('totalDisplay');
        const progressFill = document.getElementById('progressFill');
        const statusHint = document.getElementById('statusHint');
        const saveBtn = document.getElementById('saveBtn');

        function updateTotal() {
            let total = 0;
            percInputs.forEach(input => {
                total += parseFloat(input.value) || 0;
            });
            
            total = Math.round(total * 100) / 100;
            totalDisplay.textContent = total.toFixed(2) + '%';
            
            // Progress bar capped at 100% for width
            const fillWidth = Math.min(100, Math.max(0, total));
            progressFill.style.width = fillWidth + '%';

            if (Math.abs(total - 100) < 0.01) {
                totalDisplay.className = 'total-val-badge valid';
                progressFill.style.background = '#10B981';
                statusHint.textContent = '✓ ድምሩ በትክክል 100% ደርሷል! አሁን ማስቀመጥ ይችላሉ።';
                statusHint.style.color = '#059669';
                saveBtn.disabled = false;
            } else if (total < 100) {
                totalDisplay.className = 'total-val-badge under';
                progressFill.style.background = '#F59E0B';
                const remaining = (100 - total).toFixed(2);
                statusHint.textContent = `⚠️ ድምሩ ገና አልሞላም! ${remaining}% ይጎድላል።`;
                statusHint.style.color = '#D97706';
                saveBtn.disabled = true;
            } else {
                totalDisplay.className = 'total-val-badge over';
                progressFill.style.background = '#EF4444';
                const excess = (total - 100).toFixed(2);
                statusHint.textContent = `⚠️ ድምሩ ከ100% በላይ ሆኗል! ${excess}% ይቀንሱ።`;
                statusHint.style.color = '#DC2626';
                saveBtn.disabled = true;
            }
        }

        function applyPreset(values) {
            if (values.length === 5) {
                for (let i = 1; i <= 5; i++) {
                    const input = document.getElementById('c' + i + '_perc');
                    if (input) {
                        input.value = values[i - 1];
                    }
                }
                updateTotal();
            }
        }

        percInputs.forEach(input => {
            input.addEventListener('input', updateTotal);
        });

        updateTotal();
    </script>
</body>
</html>
<?php mysqli_close($conn); ?>