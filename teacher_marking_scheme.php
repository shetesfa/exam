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
            // Check if student marks already exist for this class & teacher & semester
            $marks_check = dbFetchOne(
                $conn,
                "SELECT COUNT(*) AS cnt FROM marks WHERE class_id = ? AND teacher_id = ? AND semester_id = ? AND is_deleted = 0",
                "iii",
                [$class_id, $teacher_id, $semester_id]
            );
            $has_marks_count = intval($marks_check['cnt'] ?? 0);
            if ($has_marks_count > 0 && !isAdmin()) {
                $error = "🔒 ለዚህ ክፍል አስቀድሞ የ{$has_marks_count} ተማሪዎች ውጤት ተመዝግቧል! የተማሪዎች ውጤት ከተመዘገበ በኋላ የውጤት መስፈርትን መቀየር አይቻልም (ውጤቱን ስለሚያዛባ)። መስፈርቱን ለመቀየር መጀመሪያ የተመዘገቡትን ውጤቶች መሰረዝ ወይም አስተዳዳሪውን (Admin) ማነጋገር አለብዎት።";
            } else {
                $c1_name = getChurchComponentName(trim($_POST['c1_name'] ?? 'የቤት ሥራ'), 'የቤት ሥራ');
            $c1_perc = floatval($_POST['c1_perc'] ?? 0);
            $c2_name = getChurchComponentName(trim($_POST['c2_name'] ?? 'የክፍል ተሳትፎ'), 'የክፍል ተሳትፎ');
            $c2_perc = floatval($_POST['c2_perc'] ?? 0);
            $c3_name = getChurchComponentName(trim($_POST['c3_name'] ?? 'የክፍል ክትትል'), 'የክፍል ክትትል');
            $c3_perc = floatval($_POST['c3_perc'] ?? 0);
            $c4_name = getChurchComponentName(trim($_POST['c4_name'] ?? 'የአጋማሽ ፈተና'), 'የአጋማሽ ፈተና');
            $c4_perc = floatval($_POST['c4_perc'] ?? 0);
            $c5_name = getChurchComponentName(trim($_POST['c5_name'] ?? 'የማጠቃለያ ፈተና'), 'የማጠቃለያ ፈተና');
            $c5_perc = floatval($_POST['c5_perc'] ?? 0);

            // Active flags (if inactive, set percentage to 0 and name to '-' if blank)
            $c1_active = isset($_POST['c1_active']) ? intval($_POST['c1_active']) : ($c1_perc > 0 ? 1 : 0);
            $c2_active = isset($_POST['c2_active']) ? intval($_POST['c2_active']) : ($c2_perc > 0 ? 1 : 0);
            $c3_active = isset($_POST['c3_active']) ? intval($_POST['c3_active']) : ($c3_perc > 0 ? 1 : 0);
            $c4_active = isset($_POST['c4_active']) ? intval($_POST['c4_active']) : ($c4_perc > 0 ? 1 : 0);
            $c5_active = isset($_POST['c5_active']) ? intval($_POST['c5_active']) : ($c5_perc > 0 ? 1 : 0);

            if (!$c1_active) { $c1_perc = 0; if (empty($c1_name)) $c1_name = '-'; }
            if (!$c2_active) { $c2_perc = 0; if (empty($c2_name)) $c2_name = '-'; }
            if (!$c3_active) { $c3_perc = 0; if (empty($c3_name)) $c3_name = '-'; }
            if (!$c4_active) { $c4_perc = 0; if (empty($c4_name)) $c4_name = '-'; }
            if (!$c5_active) { $c5_perc = 0; if (empty($c5_name)) $c5_name = '-'; }

            $active_count = ($c1_active ? 1 : 0) + ($c2_active ? 1 : 0) + ($c3_active ? 1 : 0) + ($c4_active ? 1 : 0) + ($c5_active ? 1 : 0);
            $total = $c1_perc + $c2_perc + $c3_perc + $c4_perc + $c5_perc;

            if ($active_count < 2) {
                $error = "ቢያንስ 2 ወይም ከዚያ በላይ መስፈርቶች መመረጥ አለባቸው (ለምሳሌ፦ 3 ወይም 4 ወይም 5 መስፈርቶች)!";
            } elseif (abs($total - 100) > 0.01) {
                $error = "የንቁ መስፈርቶች ጠቅላላ ድምር በትክክል 100% መሆን አለበት! (አሁን የተመረጡት {$active_count} መስፈርቶች ድምር: {$total}% ነው)";
            } else {
                $data = [
                    'c1_name' => $c1_name, 'c1_perc' => $c1_perc,
                    'c2_name' => $c2_name, 'c2_perc' => $c2_perc,
                    'c3_name' => $c3_name, 'c3_perc' => $c3_perc,
                    'c4_name' => $c4_name, 'c4_perc' => $c4_perc,
                    'c5_name' => $c5_name, 'c5_perc' => $c5_perc
                ];
                
                $result = saveMarkingScheme($conn, $teacher_id, $class_id, $semester_id, $data);
                if ($result['success']) {
                    $message = "የክፍሉ የውጤት መስፈርት ({$active_count} ንቁ መስፈርቶች፣ ድምር 100%) በትክክል ተቀምጧል!";
                } else {
                    $error = $result['message'];
                }
            }
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
$class_has_marks = 0;
if ($selected_class_id) {
    $current_scheme = getMarkingScheme($conn, $teacher_id, $selected_class_id, $semester_id);
    $marks_stat = dbFetchOne(
        $conn,
        "SELECT COUNT(*) AS cnt FROM marks WHERE class_id = ? AND teacher_id = ? AND semester_id = ? AND is_deleted = 0",
        "iii",
        [$selected_class_id, $teacher_id, $semester_id]
    );
    $class_has_marks = intval($marks_stat['cnt'] ?? 0);
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
            flex-wrap: wrap;
        }

        .preset-btn {
            font-size: 11.5px;
            padding: 6px 12px;
            border-radius: 8px;
            background: var(--gold-pale);
            color: var(--brown-dark);
            border: 1px solid rgba(218,165,32,0.3);
            font-weight: 700;
            cursor: pointer;
            transition: all 0.2s ease;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }

        [data-theme="dark"] .preset-btn {
            color: var(--gold-primary);
        }

        .preset-btn:hover {
            background: var(--gold-primary);
            color: #000;
            transform: translateY(-1px);
        }

        /* Criteria Count Selector (3, 4, or 5) */
        .criteria-count-selector {
            background: rgba(139, 69, 19, 0.03);
            border: 1.5px solid var(--border-color);
            border-radius: 16px;
            padding: 14px 16px;
            margin-bottom: 20px;
        }

        [data-theme="dark"] .criteria-count-selector {
            background: #0F172A;
            border-color: #334155;
        }

        .criteria-count-title {
            font-size: 13.5px;
            font-weight: 800;
            color: var(--text-main);
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 8px;
        }

        .criteria-help-badge {
            font-size: 11px;
            font-weight: 700;
            background: var(--gold-pale);
            color: var(--brown-dark);
            padding: 3px 10px;
            border-radius: 20px;
            border: 1px solid rgba(218,165,32,0.3);
        }

        [data-theme="dark"] .criteria-help-badge {
            color: var(--gold-primary);
        }

        .count-buttons-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 10px;
        }

        .count-btn {
            padding: 12px 10px;
            border-radius: 12px;
            border: 2px solid var(--border-color);
            background: var(--card-bg);
            color: var(--text-main);
            cursor: pointer;
            text-align: center;
            transition: all 0.2s ease;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 4px;
        }

        .count-btn:hover {
            border-color: var(--gold-dark);
            transform: translateY(-2px);
        }

        .count-btn.active {
            background: linear-gradient(135deg, var(--brown-dark), var(--brown-medium));
            color: #FFFFFF;
            border-color: var(--brown-dark);
            box-shadow: 0 4px 14px rgba(139, 69, 19, 0.25);
        }

        [data-theme="dark"] .count-btn.active {
            background: linear-gradient(135deg, #B45309, #78350F);
            border-color: #D97706;
            box-shadow: 0 4px 14px rgba(217, 119, 6, 0.35);
        }

        .count-badge-lg {
            font-size: 18px;
            font-weight: 900;
            letter-spacing: -0.5px;
        }

        .count-label-sub {
            font-size: 11px;
            opacity: 0.85;
            font-weight: 700;
        }

        /* Preset Collections Section */
        .preset-section {
            background: rgba(218, 165, 32, 0.05);
            border: 1px solid rgba(218, 165, 32, 0.25);
            border-radius: 14px;
            padding: 12px 14px;
            margin-bottom: 22px;
        }

        .preset-section-header {
            font-size: 12px;
            font-weight: 800;
            color: var(--brown-dark);
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        [data-theme="dark"] .preset-section-header {
            color: var(--gold-primary);
        }

        .preset-groups-container {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .preset-group-row {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
        }

        .preset-group-tag {
            font-size: 11px;
            font-weight: 800;
            color: var(--text-muted);
            min-width: 90px;
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
            border: 1.5px solid var(--border-color);
            border-radius: 14px;
            padding: 16px;
            display: grid;
            grid-template-columns: 38px 1fr 140px auto;
            gap: 14px;
            align-items: center;
            transition: all 0.25s ease;
        }

        [data-theme="dark"] .component-item {
            background: rgba(255, 255, 255, 0.02);
        }

        .component-item:hover {
            border-color: rgba(218, 165, 32, 0.5);
            background: rgba(255, 215, 0, 0.02);
        }

        .component-item.is-inactive {
            opacity: 0.45;
            background: rgba(0, 0, 0, 0.02);
            border-style: dashed;
            border-color: #94A3B8;
        }

        [data-theme="dark"] .component-item.is-inactive {
            background: rgba(15, 23, 42, 0.5);
            border-color: #475569;
        }

        .component-item.is-inactive .form-input {
            background: rgba(0, 0, 0, 0.04);
            cursor: not-allowed;
        }

        [data-theme="dark"] .component-item.is-inactive .form-input {
            background: #0B1120;
        }

        .btn-row-toggle {
            padding: 8px 12px;
            border-radius: 9px;
            font-size: 11.5px;
            font-weight: 700;
            cursor: pointer;
            border: 1px solid transparent;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            transition: all 0.2s ease;
            white-space: nowrap;
        }

        .btn-row-toggle.deactivate {
            background: rgba(239, 68, 68, 0.08);
            color: #DC2626;
            border-color: rgba(239, 68, 68, 0.3);
        }

        .btn-row-toggle.deactivate:hover {
            background: #EF4444;
            color: #FFFFFF;
        }

        .btn-row-toggle.activate {
            background: rgba(16, 185, 129, 0.08);
            color: #059669;
            border-color: rgba(16, 185, 129, 0.35);
        }

        .btn-row-toggle.activate:hover {
            background: #10B981;
            color: #FFFFFF;
        }

        .comp-status-badge {
            display: inline-block;
            font-size: 10.5px;
            font-weight: 800;
            padding: 2px 8px;
            border-radius: 6px;
            margin-top: 4px;
        }

        .comp-status-badge.active {
            background: rgba(16, 185, 129, 0.12);
            color: #059669;
        }

        .comp-status-badge.inactive {
            background: rgba(148, 163, 184, 0.15);
            color: #64748B;
        }

        [data-theme="dark"] .comp-status-badge.inactive {
            color: #94A3B8;
        }

        /* Total Summary Card & Dynamic Progress */
        .total-summary-card {
            background: linear-gradient(135deg, rgba(218, 165, 32, 0.08) 0%, rgba(139, 69, 19, 0.03) 100%);
            border: 1.5px solid rgba(218, 165, 32, 0.35);
            border-radius: 18px;
            padding: 20px 22px;
            margin-top: 24px;
            margin-bottom: 20px;
            box-shadow: 0 4px 20px rgba(139, 69, 19, 0.06);
            transition: all 0.3s ease;
        }

        [data-theme="dark"] .total-summary-card {
            background: linear-gradient(135deg, rgba(30, 41, 59, 0.85) 0%, rgba(15, 23, 42, 0.85) 100%) !important;
            border-color: rgba(218, 165, 32, 0.3) !important;
            box-shadow: 0 6px 24px rgba(0, 0, 0, 0.35) !important;
        }

        .total-info-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 12px;
            margin-bottom: 14px;
        }

        .total-label {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 15px;
            font-weight: 800;
            color: var(--brown-dark);
        }

        [data-theme="dark"] .total-label {
            color: var(--gold-primary);
        }

        .active-count-tag {
            background: rgba(218, 165, 32, 0.16);
            color: #92400E;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 11.5px;
            font-weight: 800;
            border: 1px solid rgba(218, 165, 32, 0.35);
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }

        [data-theme="dark"] .active-count-tag {
            background: rgba(245, 158, 11, 0.2);
            color: #FCD34D;
            border-color: rgba(245, 158, 11, 0.4);
        }

        .total-val-badge {
            font-size: 20px;
            font-weight: 900;
            padding: 7px 18px;
            border-radius: 12px;
            letter-spacing: -0.5px;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 90px;
        }

        .total-val-badge.valid {
            background: linear-gradient(135deg, #10B981, #059669);
            color: #FFFFFF;
            box-shadow: 0 4px 16px rgba(16, 185, 129, 0.4);
            animation: pulse-green 2.5s infinite;
        }

        .total-val-badge.under {
            background: linear-gradient(135deg, #F59E0B, #D97706);
            color: #FFFFFF;
            box-shadow: 0 4px 16px rgba(245, 158, 11, 0.35);
        }

        .total-val-badge.over {
            background: linear-gradient(135deg, #EF4444, #DC2626);
            color: #FFFFFF;
            box-shadow: 0 4px 16px rgba(239, 68, 68, 0.35);
        }

        @keyframes pulse-green {
            0% { box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.4); }
            70% { box-shadow: 0 0 0 10px rgba(16, 185, 129, 0); }
            100% { box-shadow: 0 0 0 0 rgba(16, 185, 129, 0); }
        }

        .progress-track {
            width: 100%;
            height: 12px;
            background: rgba(0, 0, 0, 0.07);
            border-radius: 20px;
            overflow: hidden;
            margin-bottom: 12px;
            box-shadow: inset 0 1px 3px rgba(0, 0, 0, 0.1);
        }

        [data-theme="dark"] .progress-track {
            background: rgba(255, 255, 255, 0.08);
            box-shadow: inset 0 1px 3px rgba(0, 0, 0, 0.4);
        }

        .progress-fill {
            height: 100%;
            width: 0%;
            border-radius: 20px;
            background: #10B981;
            transition: width 0.4s cubic-bezier(0.4, 0, 0.2, 1), background 0.3s ease;
        }

        .progress-status-hint {
            font-size: 13px;
            font-weight: 700;
            line-height: 1.5;
            display: flex;
            align-items: center;
            gap: 6px;
            transition: color 0.3s ease;
        }

        /* Save Scheme Button */
        .btn-save {
            width: 100%;
            padding: 16px 28px;
            border: none;
            border-radius: 16px;
            font-size: 16px;
            font-weight: 800;
            font-family: inherit;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
            background: linear-gradient(135deg, #10B981 0%, #059669 100%);
            color: #FFFFFF;
            box-shadow: 0 6px 20px rgba(16, 185, 129, 0.35);
            letter-spacing: 0.3px;
        }

        .btn-save:hover:not(:disabled) {
            transform: translateY(-2px);
            box-shadow: 0 10px 28px rgba(16, 185, 129, 0.48);
            background: linear-gradient(135deg, #059669 0%, #047857 100%);
        }

        .btn-save:active:not(:disabled) {
            transform: translateY(1px);
            box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
        }

        .btn-save:disabled {
            background: #94A3B8 !important;
            color: #E2E8F0 !important;
            cursor: not-allowed !important;
            box-shadow: none !important;
            opacity: 0.65;
            transform: none !important;
        }

        [data-theme="dark"] .btn-save:disabled {
            background: #475569 !important;
            color: #94A3B8 !important;
        }

        /* Tip / Notice Box */
        .hint-notice {
            margin-top: 24px;
            padding: 16px 20px;
            background: rgba(218, 165, 32, 0.08);
            border: 1.5px solid rgba(218, 165, 32, 0.3);
            border-radius: 16px;
            font-size: 13px;
            line-height: 1.65;
            color: var(--brown-dark);
            display: flex;
            align-items: flex-start;
            gap: 14px;
            box-shadow: 0 2px 10px rgba(218, 165, 32, 0.06);
        }

        [data-theme="dark"] .hint-notice {
            background: rgba(30, 41, 59, 0.7);
            border-color: rgba(218, 165, 32, 0.25);
            color: #E2E8F0;
        }

        .hint-notice strong {
            color: var(--brown-dark);
        }

        [data-theme="dark"] .hint-notice strong {
            color: var(--gold-primary);
        }

        /* Mobile responsiveness for bottom section */
        @media (max-width: 600px) {
            .total-summary-card {
                padding: 16px 14px;
                border-radius: 14px;
                margin-top: 18px;
            }

            .total-info-row {
                flex-direction: column;
                align-items: flex-start;
                gap: 8px;
            }

            .total-val-badge {
                align-self: flex-start;
                font-size: 17px;
                padding: 5px 14px;
            }

            .btn-save {
                padding: 14px 18px;
                font-size: 15px;
                border-radius: 13px;
            }

            .hint-notice {
                padding: 12px 14px;
                font-size: 12px;
                gap: 10px;
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
            </div>

            <?php if ($class_has_marks > 0): ?>
            <div style="background: rgba(245, 158, 11, 0.12); border: 1.5px solid #F59E0B; border-radius: 14px; padding: 14px 18px; margin-bottom: 20px; display: flex; align-items: center; gap: 12px;">
                <span style="font-size: 24px;">🔒</span>
                <div>
                    <div style="font-weight: 800; color: #B45309; font-size: 14px;">
                        ውጤት የተመዘገበበት ክፍል (የ<?php echo $class_has_marks; ?> ተማሪዎች ውጤት አስቀድሞ ተመዝግቧል)
                    </div>
                    <div style="font-size: 13px; color: var(--text-muted); margin-top: 2px;">
                        የተማሪዎች ውጤት ከተመዘገበ በኋላ የውጤት መስፈርቱን መቀየር አይፈቀድም (የነባር ተማሪዎችን ውጤት እንዳያዛባ)። <?php echo isAdmin() ? '<strong style="color:#059669;">(እንደ ዋና አስተዳዳሪ መቀየር ይችላሉ፤ ነገር ግን ነባር ውጤቶችን በጥንቃቄ ይፈትሹ)</strong>' : 'መስፈርቱን ለመቀየር መጀመሪያ የተመዘገቡትን ውጤቶች መሰረዝ ወይም አስተዳዳሪውን ማነጋገር ያስፈልጋል።'; ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <?php
            $components = [
                1 => ['name' => getChurchComponentName($current_scheme['component1_name'] ?? '', 'የቤት ሥራ'), 'perc' => floatval($current_scheme['component1_percentage'] ?? 20)],
                2 => ['name' => getChurchComponentName($current_scheme['component2_name'] ?? '', 'የክፍል ተሳትፎ'), 'perc' => floatval($current_scheme['component2_percentage'] ?? 20)],
                3 => ['name' => getChurchComponentName($current_scheme['component3_name'] ?? '', 'የክፍል ክትትል'), 'perc' => floatval($current_scheme['component3_percentage'] ?? 10)],
                4 => ['name' => getChurchComponentName($current_scheme['component4_name'] ?? '', 'የአጋማሽ ፈተና'), 'perc' => floatval($current_scheme['component4_percentage'] ?? 25)],
                5 => ['name' => getChurchComponentName($current_scheme['component5_name'] ?? '', 'የማጠቃለያ ፈተና'), 'perc' => floatval($current_scheme['component5_percentage'] ?? 25)],
            ];

            // Determine active items from existing saved scheme
            $non_zero_indices = [];
            foreach ($components as $idx => $comp) {
                if ($comp['perc'] > 0) {
                    $non_zero_indices[] = $idx;
                }
            }

            if (empty($non_zero_indices)) {
                $active_indices = [1, 2, 3, 4, 5];
                $initial_mode = 5;
            } else {
                $active_indices = $non_zero_indices;
                $initial_mode = count($non_zero_indices);
                if ($initial_mode < 3) $initial_mode = 3;
                if ($initial_mode > 5) $initial_mode = 5;
            }
            ?>

            <!-- Criteria Count Selector -->
            <div class="criteria-count-selector">
                <div class="criteria-count-title">
                    <span>⚙️ የመስፈርቶች ብዛት ይምረጡ፦</span>
                    <span class="criteria-help-badge">ድምሩ 100% እስከተሟላ ድረስ 3፣ 4 ወይም 5 መጠቀም ይችላሉ</span>
                </div>
                <div class="count-buttons-grid">
                    <button type="button" class="count-btn <?php echo $initial_mode == 3 ? 'active' : ''; ?>" id="countBtn-3" onclick="setCriteriaCount(3)">
                        <span class="count-badge-lg">3 መስፈርቶች</span>
                        <span class="count-label-sub">ድምር 100%</span>
                    </button>
                    <button type="button" class="count-btn <?php echo $initial_mode == 4 ? 'active' : ''; ?>" id="countBtn-4" onclick="setCriteriaCount(4)">
                        <span class="count-badge-lg">4 መስፈርቶች</span>
                        <span class="count-label-sub">ድምር 100%</span>
                    </button>
                    <button type="button" class="count-btn <?php echo $initial_mode == 5 ? 'active' : ''; ?>" id="countBtn-5" onclick="setCriteriaCount(5)">
                        <span class="count-badge-lg">5 መስፈርቶች</span>
                        <span class="count-label-sub">መደበኛ (100%)</span>
                    </button>
                </div>
            </div>

            <!-- Presets Section -->
            <div class="preset-section">
                <div class="preset-section-header">
                    <span>⚡</span>
                    <span>ዝግጁ የውጤት ማከፋፈያ አማራጮች (በመጫን በቀላሉ ይምረጡ)፦</span>
                </div>
                <div class="preset-groups-container">
                    <!-- 3 criteria presets -->
                    <div class="preset-group-row" id="presets-row-3" style="<?php echo $initial_mode == 3 ? '' : 'display:none;'; ?>">
                        <span class="preset-group-tag">🎯 3 መስፈርቶች፦</span>
                        <button type="button" class="preset-btn" onclick="applyPresetDetailed(3, [
                            {name: 'የቤት ሥራ', perc: 20},
                            {name: 'የአጋማሽ ፈተና', perc: 30},
                            {name: 'የማጠቃለያ ፈተና', perc: 50}
                        ])">
                            ⚡ 20% / 30% / 50%
                        </button>
                        <button type="button" class="preset-btn" onclick="applyPresetDetailed(3, [
                            {name: 'የክፍል ተሳትፎ', perc: 25},
                            {name: 'የአጋማሽ ፈተና', perc: 25},
                            {name: 'የማጠቃለያ ፈተና', perc: 50}
                        ])">
                            ⚡ 25% / 25% / 50%
                        </button>
                        <button type="button" class="preset-btn" onclick="applyPresetDetailed(3, [
                            {name: 'የቤት ሥራና ተሳትፎ', perc: 30},
                            {name: 'የአጋማሽ ፈተና', perc: 30},
                            {name: 'የማጠቃለያ ፈተና', perc: 40}
                        ])">
                            ⚡ 30% / 30% / 40%
                        </button>
                    </div>

                    <!-- 4 criteria presets -->
                    <div class="preset-group-row" id="presets-row-4" style="<?php echo $initial_mode == 4 ? '' : 'display:none;'; ?>">
                        <span class="preset-group-tag">🎯 4 መስፈርቶች፦</span>
                        <button type="button" class="preset-btn" onclick="applyPresetDetailed(4, [
                            {name: 'የቤት ሥራ', perc: 20},
                            {name: 'የክፍል ክትትልና ተሳትፎ', perc: 10},
                            {name: 'የአጋማሽ ፈተና', perc: 30},
                            {name: 'የማጠቃለያ ፈተና', perc: 40}
                        ])">
                            ⚡ 20% / 10% / 30% / 40%
                        </button>
                        <button type="button" class="preset-btn" onclick="applyPresetDetailed(4, [
                            {name: 'የቤት ሥራ', perc: 15},
                            {name: 'የክፍል ተሳትፎ', perc: 15},
                            {name: 'የአጋማሽ ፈተና', perc: 30},
                            {name: 'የማጠቃለያ ፈተና', perc: 40}
                        ])">
                            ⚡ 15% / 15% / 30% / 40%
                        </button>
                        <button type="button" class="preset-btn" onclick="applyPresetDetailed(4, [
                            {name: 'የቤት ሥራ', perc: 25},
                            {name: 'የክፍል ተሳትፎ', perc: 25},
                            {name: 'የአጋማሽ ፈተና', perc: 25},
                            {name: 'የማጠቃለያ ፈተና', perc: 25}
                        ])">
                            ⚡ እኩል 25% / 25% / 25% / 25%
                        </button>
                    </div>

                    <!-- 5 criteria presets -->
                    <div class="preset-group-row" id="presets-row-5" style="<?php echo $initial_mode == 5 ? '' : 'display:none;'; ?>">
                        <span class="preset-group-tag">🎯 5 መስፈርቶች፦</span>
                        <button type="button" class="preset-btn" onclick="applyPresetDetailed(5, [
                            {name: 'የቤት ሥራ', perc: 20},
                            {name: 'የክፍል ተሳትፎ', perc: 20},
                            {name: 'የክፍል ክትትል', perc: 10},
                            {name: 'የአጋማሽ ፈተና', perc: 25},
                            {name: 'የማጠቃለያ ፈተና', perc: 25}
                        ])">
                            ⚡ መደበኛ (20/20/10/25/25)
                        </button>
                        <button type="button" class="preset-btn" onclick="applyPresetDetailed(5, [
                            {name: 'የቤት ሥራ', perc: 10},
                            {name: 'የክፍል ተሳትፎ', perc: 10},
                            {name: 'የክፍል ክትትል', perc: 10},
                            {name: 'የአጋማሽ ፈተና', perc: 30},
                            {name: 'የማጠቃለያ ፈተና', perc: 40}
                        ])">
                            ⚡ ፈተና-መር (10/10/10/30/40)
                        </button>
                    </div>
                </div>
            </div>

            <form method="POST" id="schemeForm" onsubmit="return validateBeforeSubmit()">
                <?php echo csrfField(); ?>
                <input type="hidden" name="class_id" value="<?php echo $selected_class_id; ?>">

                <div class="components-list">
                    <?php foreach ($components as $index => $comp): 
                        $isActive = in_array($index, $active_indices);
                    ?>
                    <div class="component-item <?php echo $isActive ? '' : 'is-inactive'; ?>" id="comp-item-<?php echo $index; ?>" data-index="<?php echo $index; ?>">
                        <div class="comp-badge" id="comp-badge-<?php echo $index; ?>"><?php echo $index; ?></div>
                        
                        <div class="form-group" style="flex: 2;">
                            <div style="display: flex; justify-content: space-between; align-items: center;">
                                <label class="form-label" for="c<?php echo $index; ?>_name">የመስፈርት <?php echo $index; ?> መጠሪያ ስም</label>
                                <span class="comp-status-badge <?php echo $isActive ? 'active' : 'inactive'; ?>" id="status-badge-<?php echo $index; ?>">
                                    <?php echo $isActive ? '🟢 ንቁ' : '⚪ አልተካተተም (0%)'; ?>
                                </span>
                            </div>
                            <input type="text" name="c<?php echo $index; ?>_name" id="c<?php echo $index; ?>_name" 
                                   class="form-input comp-name-input" 
                                   value="<?php echo htmlspecialchars($comp['name']); ?>" 
                                   placeholder="የመስፈርቱ ስም (ለምሳሌ፦ አጋማሽ ፈተና)"
                                   <?php echo $isActive ? 'required' : ''; ?>>
                        </div>
                        
                        <div class="form-group" style="flex: 1; max-width: 145px;">
                            <label class="form-label" for="c<?php echo $index; ?>_perc">ድርሻ መቶኛ</label>
                            <div class="perc-input-wrap">
                                <input type="number" name="c<?php echo $index; ?>_perc" id="c<?php echo $index; ?>_perc" 
                                       class="form-input perc-input" 
                                       value="<?php echo $comp['perc']; ?>" min="0" max="100" step="0.01" 
                                       <?php echo $isActive ? 'required' : ''; ?>>
                                <span class="perc-symbol">%</span>
                            </div>
                        </div>

                        <input type="hidden" name="c<?php echo $index; ?>_active" id="c<?php echo $index; ?>_active" value="<?php echo $isActive ? '1' : '0'; ?>">

                        <div class="comp-actions">
                            <?php if ($isActive): ?>
                            <button type="button" class="btn-row-toggle deactivate" id="btn-toggle-<?php echo $index; ?>" onclick="toggleComponent(<?php echo $index; ?>)">
                                <span>❌</span> <span>አሰናብት</span>
                            </button>
                            <?php else: ?>
                            <button type="button" class="btn-row-toggle activate" id="btn-toggle-<?php echo $index; ?>" onclick="toggleComponent(<?php echo $index; ?>)">
                                <span>➕</span> <span>አካትት</span>
                            </button>
                            <?php endif; ?>
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
                            <span class="active-count-tag" id="activeCountTag">🎯 <?php echo $initial_mode; ?> መስፈርቶች ተመርጠዋል</span>
                        </div>
                        <div class="total-val-badge" id="totalDisplay">100%</div>
                    </div>
                    <div class="progress-track">
                        <div class="progress-fill" id="progressFill"></div>
                    </div>
                    <div class="progress-status-hint" id="statusHint">
                        የተመረጡት መስፈርቶች ድምር በትክክል 100% መሆን አለበት።
                    </div>
                </div>

                <button type="submit" name="save_scheme" class="btn-save" id="saveBtn" <?php echo ($class_has_marks > 0 && !isAdmin()) ? 'disabled' : ''; ?>>
                    <span><?php echo ($class_has_marks > 0 && !isAdmin()) ? '🔒' : '💾'; ?></span>
                    <span><?php echo ($class_has_marks > 0 && !isAdmin()) ? 'ውጤት ስለተመዘገበ መስፈርቱ ተቆልፏል' : 'የውጤት መስፈርቱን አስቀምጥ'; ?></span>
                </button>
            </form>

            <div class="hint-notice">
                <span style="font-size: 18px;">💡</span>
                <div>
                    <strong>ጠቃሚ ማስታወሻ፦</strong> 3፣ 4 ወይም 5 መስፈርቶችን መርጠው መጠቀም ይችላሉ። ዋናው መስፈርት የተመረጡት ንቁ ክፍሎች ድምር <strong>በትክክል 100%</strong> መሆኑ ብቻ ነው። ያልተካተቱ መስፈርቶች (0%) በተማሪዎች የውጤት መመዝገቢያ ላይ አይታዩም።
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
        const totalDisplay = document.getElementById('totalDisplay');
        const progressFill = document.getElementById('progressFill');
        const statusHint = document.getElementById('statusHint');
        const saveBtn = document.getElementById('saveBtn');
        const activeCountTag = document.getElementById('activeCountTag');

        // Check active state of a component (1 to 5)
        function isComponentActive(idx) {
            const activeInput = document.getElementById('c' + idx + '_active');
            return activeInput && activeInput.value === '1';
        }

        // Set active state for component
        function setComponentActiveState(idx, active, defaultVal = null, defaultName = null) {
            const item = document.getElementById('comp-item-' + idx);
            const activeInput = document.getElementById('c' + idx + '_active');
            const percInput = document.getElementById('c' + idx + '_perc');
            const nameInput = document.getElementById('c' + idx + '_name');
            const badge = document.getElementById('status-badge-' + idx);
            const btn = document.getElementById('btn-toggle-' + idx);

            if (!item) return;

            if (active) {
                activeInput.value = '1';
                item.classList.remove('is-inactive');
                percInput.disabled = false;
                percInput.required = true;
                nameInput.disabled = false;
                nameInput.required = true;

                if (defaultVal !== null) {
                    percInput.value = defaultVal;
                } else if (parseFloat(percInput.value) <= 0) {
                    percInput.value = 10;
                }

                const churchFallbackNames = {1: 'የቤት ሥራ', 2: 'የክፍል ተሳትፎ', 3: 'የክፍል ክትትል', 4: 'የአጋማሽ ፈተና', 5: 'የማጠቃለያ ፈተና'};
                const legacyMap = {'assignment': 'የቤት ሥራ', 'participation': 'የክፍል ተሳትፎ', 'attendance': 'የክፍል ክትትል', 'mid exam': 'የአጋማሽ ፈተና', 'final exam': 'የማጠቃለያ ፈተና'};

                if (defaultName !== null && defaultName.trim() !== '') {
                    nameInput.value = defaultName;
                } else if (nameInput.value === '-' || nameInput.value.trim() === '' || legacyMap[nameInput.value.trim().toLowerCase()]) {
                    nameInput.value = legacyMap[nameInput.value.trim().toLowerCase()] || churchFallbackNames[idx] || ('መስፈርት ' + idx);
                }

                badge.className = 'comp-status-badge active';
                badge.textContent = '🟢 ንቁ';
                btn.className = 'btn-row-toggle deactivate';
                btn.innerHTML = '<span>❌</span> <span>አሰናብት</span>';
            } else {
                activeInput.value = '0';
                item.classList.add('is-inactive');
                percInput.value = 0;
                percInput.required = false;
                nameInput.required = false;

                badge.className = 'comp-status-badge inactive';
                badge.textContent = '⚪ አልተካተተም (0%)';
                btn.className = 'btn-row-toggle activate';
                btn.innerHTML = '<span>➕</span> <span>አካትት</span>';
            }
        }

        // Toggle individual component
        function toggleComponent(idx) {
            const currentlyActive = isComponentActive(idx);
            
            // Prevent deactivating if only 2 would remain
            let currentActiveCount = 0;
            for (let i = 1; i <= 5; i++) {
                if (isComponentActive(i)) currentActiveCount++;
            }
            if (currentlyActive && currentActiveCount <= 2) {
                alert('ቢያንስ 2 መስፈርቶች መኖር አለባቸው!');
                return;
            }

            setComponentActiveState(idx, !currentlyActive);
            highlightMatchingCountButton();
            updateTotal();
        }

        // Set criteria count mode (3, 4, or 5)
        function setCriteriaCount(count) {
            // Update buttons styling
            [3, 4, 5].forEach(c => {
                const btn = document.getElementById('countBtn-' + c);
                if (btn) btn.classList.toggle('active', c === count);

                const presetRow = document.getElementById('presets-row-' + c);
                if (presetRow) presetRow.style.display = (c === count ? 'flex' : 'none');
            });

            if (count === 3) {
                // Keep 1, 2, 3 active; deactivate 4, 5
                setComponentActiveState(1, true);
                setComponentActiveState(2, true);
                setComponentActiveState(3, true);
                setComponentActiveState(4, false);
                setComponentActiveState(5, false);

                // If current sum of 1..3 is not 100, suggest standard 3-criteria split
                const currentSum = (parseFloat(document.getElementById('c1_perc').value) || 0) +
                                   (parseFloat(document.getElementById('c2_perc').value) || 0) +
                                   (parseFloat(document.getElementById('c3_perc').value) || 0);
                if (Math.abs(currentSum - 100) > 0.01) {
                    document.getElementById('c1_perc').value = 20;
                    document.getElementById('c2_perc').value = 30;
                    document.getElementById('c3_perc').value = 50;
                }
            } else if (count === 4) {
                // Keep 1, 2, 3, 4 active; deactivate 5
                setComponentActiveState(1, true);
                setComponentActiveState(2, true);
                setComponentActiveState(3, true);
                setComponentActiveState(4, true);
                setComponentActiveState(5, false);

                const currentSum = (parseFloat(document.getElementById('c1_perc').value) || 0) +
                                   (parseFloat(document.getElementById('c2_perc').value) || 0) +
                                   (parseFloat(document.getElementById('c3_perc').value) || 0) +
                                   (parseFloat(document.getElementById('c4_perc').value) || 0);
                if (Math.abs(currentSum - 100) > 0.01) {
                    document.getElementById('c1_perc').value = 20;
                    document.getElementById('c2_perc').value = 10;
                    document.getElementById('c3_perc').value = 30;
                    document.getElementById('c4_perc').value = 40;
                }
            } else if (count === 5) {
                // All 1..5 active
                setComponentActiveState(1, true);
                setComponentActiveState(2, true);
                setComponentActiveState(3, true);
                setComponentActiveState(4, true);
                setComponentActiveState(5, true);

                const currentSum = (parseFloat(document.getElementById('c1_perc').value) || 0) +
                                   (parseFloat(document.getElementById('c2_perc').value) || 0) +
                                   (parseFloat(document.getElementById('c3_perc').value) || 0) +
                                   (parseFloat(document.getElementById('c4_perc').value) || 0) +
                                   (parseFloat(document.getElementById('c5_perc').value) || 0);
                if (Math.abs(currentSum - 100) > 0.01) {
                    document.getElementById('c1_perc').value = 20;
                    document.getElementById('c2_perc').value = 20;
                    document.getElementById('c3_perc').value = 10;
                    document.getElementById('c4_perc').value = 25;
                    document.getElementById('c5_perc').value = 25;
                }
            }

            updateTotal();
        }

        // Apply a detailed preset with custom names & percentages
        function applyPresetDetailed(count, items) {
            setCriteriaCount(count);

            for (let i = 1; i <= 5; i++) {
                if (i <= count && items[i - 1]) {
                    setComponentActiveState(i, true, items[i - 1].perc, items[i - 1].name);
                } else {
                    setComponentActiveState(i, false);
                }
            }

            updateTotal();
        }

        // Highlight the count button matching active components
        function highlightMatchingCountButton() {
            let activeCount = 0;
            for (let i = 1; i <= 5; i++) {
                if (isComponentActive(i)) activeCount++;
            }

            [3, 4, 5].forEach(c => {
                const btn = document.getElementById('countBtn-' + c);
                if (btn) btn.classList.toggle('active', c === activeCount);

                const presetRow = document.getElementById('presets-row-' + c);
                if (presetRow) presetRow.style.display = (c === activeCount ? 'flex' : 'none');
            });
        }

        // Recalculate total sum and validate
        function updateTotal() {
            let total = 0;
            let activeCount = 0;

            for (let i = 1; i <= 5; i++) {
                if (isComponentActive(i)) {
                    activeCount++;
                    const percInput = document.getElementById('c' + i + '_perc');
                    total += parseFloat(percInput.value) || 0;
                }
            }
            
            total = Math.round(total * 100) / 100;
            totalDisplay.textContent = total.toFixed(2) + '%';

            if (activeCountTag) {
                activeCountTag.textContent = `🎯 ${activeCount} መስፈርቶች ተመርጠዋል`;
            }
            
            const fillWidth = Math.min(100, Math.max(0, total));
            progressFill.style.width = fillWidth + '%';

            if (Math.abs(total - 100) < 0.01) {
                totalDisplay.className = 'total-val-badge valid';
                progressFill.style.background = '#10B981';
                statusHint.textContent = `✓ ድምሩ በትክክል 100% ደርሷል (${activeCount} መስፈርቶች)! አሁን ማስቀመጥ ይችላሉ።`;
                statusHint.style.color = '#059669';
                saveBtn.disabled = false;
            } else if (total < 100) {
                totalDisplay.className = 'total-val-badge under';
                progressFill.style.background = '#F59E0B';
                const remaining = (100 - total).toFixed(2);
                statusHint.textContent = `⚠️ ድምሩ ገና አልሞላም! ${remaining}% ይጎድላል (${activeCount} መስፈርቶች)። ጠቅላላ 100% መሆን አለበት።`;
                statusHint.style.color = '#D97706';
                saveBtn.disabled = true;
            } else {
                totalDisplay.className = 'total-val-badge over';
                progressFill.style.background = '#EF4444';
                const excess = (total - 100).toFixed(2);
                statusHint.textContent = `⚠️ ድምሩ ከ100% በላይ ሆኗል! ${excess}% ይቀንሱ (${activeCount} መስፈርቶች)። ጠቅላላ 100% መሆን አለበት።`;
                statusHint.style.color = '#DC2626';
                saveBtn.disabled = true;
            }
        }

        function validateBeforeSubmit() {
            let total = 0;
            let activeCount = 0;
            for (let i = 1; i <= 5; i++) {
                if (isComponentActive(i)) {
                    activeCount++;
                    const percInput = document.getElementById('c' + i + '_perc');
                    total += parseFloat(percInput.value) || 0;
                }
            }

            total = Math.round(total * 100) / 100;

            if (activeCount < 2) {
                alert('እባክዎ ቢያንስ 2 መስፈርቶችን ይምረጡ!');
                return false;
            }

            if (Math.abs(total - 100) > 0.01) {
                alert(`ጠቅላላ የመቶኛ ድምር በትክክል 100% መሆን አለበት! አሁን: ${total}% ነው`);
                return false;
            }

            return true;
        }

        // Attach change listeners to all percentage inputs
        for (let i = 1; i <= 5; i++) {
            const percInput = document.getElementById('c' + i + '_perc');
            if (percInput) {
                percInput.addEventListener('input', updateTotal);
            }
        }

        // Initial calculation
        updateTotal();
    </script>
</body>
</html>
<?php mysqli_close($conn); ?>