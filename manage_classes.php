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
                    $message = "ክፍሉ በትክክል ተመዝግቧል!";
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

// Get current academic year
$current_year_query = "SELECT * FROM academic_years WHERE status = 'active' LIMIT 1";
$current_year_result = mysqli_query($conn, $current_year_query);
$current_year = mysqli_fetch_assoc($current_year_result);
$current_ethiopian_year = $current_year ? $current_year['ethiopian_year'] : 2017;

// Get all classes with statistics + their grade/division (if assigned)
$classes_query = "SELECT c.*, 
                  g.name_am AS grade_name, g.level_number,
                  d.code AS division_code, d.name_am AS division_name,
                  COUNT(DISTINCT s.id) as student_count,
                  COUNT(DISTINCT tc.teacher_id) as teacher_count
                  FROM classes c
                  LEFT JOIN grades g ON c.grade_id = g.id
                  LEFT JOIN divisions d ON g.division_id = d.id
                  LEFT JOIN students s ON c.id = s.class_id AND (s.is_deleted = 0 OR s.is_deleted IS NULL)
                  LEFT JOIN teacher_class tc ON c.id = tc.class_id
                  GROUP BY c.id
                  ORDER BY d.sort_order, g.level_number, c.name";
$classes = mysqli_query($conn, $classes_query);

// Get all teacher assignments organized by class and year
$all_class_teachers_query = "SELECT tc.*, 
                              u.id as teacher_id,
                              u.name as teacher_name, 
                              u.photo as teacher_photo,
                              s.name as semester_name,
                              s.ethiopian_year,
                              s.semester_number,
                              s.status as semester_status
                              FROM teacher_class tc
                              JOIN users u ON tc.teacher_id = u.id
                              JOIN semesters s ON tc.semester_id = s.id
                              ORDER BY s.ethiopian_year DESC, s.semester_number DESC, u.name";
$all_class_teachers = mysqli_query($conn, $all_class_teachers_query);
$class_teacher_history = [];
if ($all_class_teachers) {
    while($asg = mysqli_fetch_assoc($all_class_teachers)) {
        $c_id = $asg['class_id'];
        $yr = $asg['ethiopian_year'];
        if (!isset($class_teacher_history[$c_id])) {
            $class_teacher_history[$c_id] = [];
        }
        if (!isset($class_teacher_history[$c_id][$yr])) {
            $class_teacher_history[$c_id][$yr] = [
                'semester1' => [],
                'semester2' => []
            ];
        }
        $sem_key = 'semester' . $asg['semester_number'];
        $class_teacher_history[$c_id][$yr][$sem_key][] = $asg;
    }
}

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

        /* Direct Dark Mode Overrides for Class Components */
        html.dark-mode .class-card,
        body.dark-mode .class-card,
        [data-theme="dark"] .class-card {
            background-color: #1E293B !important;
            border-color: #334155 !important;
            color: #F1F5F9 !important;
        }

        html.dark-mode .class-card:hover,
        body.dark-mode .class-card:hover,
        [data-theme="dark"] .class-card:hover {
            border-color: #F59E0B !important;
        }

        html.dark-mode .class-header,
        body.dark-mode .class-header,
        [data-theme="dark"] .class-header {
            border-bottom-color: #334155 !important;
        }

        html.dark-mode .class-name,
        body.dark-mode .class-name,
        [data-theme="dark"] .class-name {
            color: #FCD34D !important;
        }

        html.dark-mode .class-desc,
        body.dark-mode .class-desc,
        [data-theme="dark"] .class-desc {
            background: #162032 !important;
            color: #94A3B8 !important;
            border-left-color: #F59E0B !important;
        }

        html.dark-mode .class-metrics,
        body.dark-mode .class-metrics,
        [data-theme="dark"] .class-metrics {
            background-color: #162032 !important;
            border-color: #334155 !important;
        }

        html.dark-mode .class-metric-val,
        body.dark-mode .class-metric-val,
        [data-theme="dark"] .class-metric-val {
            color: #FCD34D !important;
        }

        html.dark-mode .class-metric-lbl,
        body.dark-mode .class-metric-lbl,
        [data-theme="dark"] .class-metric-lbl {
            color: #94A3B8 !important;
        }

        html.dark-mode .class-meta-info,
        body.dark-mode .class-meta-info,
        [data-theme="dark"] .class-meta-info {
            color: #94A3B8 !important;
        }

        html.dark-mode .class-division-badge.children,
        body.dark-mode .class-division-badge.children,
        [data-theme="dark"] .class-division-badge.children {
            background-color: #1E3A8A !important;
            color: #93C5FD !important;
            border: 1px solid #3B82F6 !important;
        }

        html.dark-mode .class-division-badge.youth,
        body.dark-mode .class-division-badge.youth,
        [data-theme="dark"] .class-division-badge.youth {
            background-color: #3B2A0F !important;
            color: #FCD34D !important;
            border: 1px solid #78350F !important;
        }

        html.dark-mode .class-division-badge.unassigned,
        body.dark-mode .class-division-badge.unassigned,
        [data-theme="dark"] .class-division-badge.unassigned {
            background-color: #0F172A !important;
            color: #94A3B8 !important;
            border: 1px solid #334155 !important;
        }

        html.dark-mode .year-header,
        body.dark-mode .year-header,
        [data-theme="dark"] .year-header {
            background: #162032 !important;
            color: #F1F5F9 !important;
        }

        html.dark-mode .year-header:hover,
        body.dark-mode .year-header:hover,
        [data-theme="dark"] .year-header:hover {
            background: #243247 !important;
        }

        html.dark-mode .semester-row,
        body.dark-mode .semester-row,
        [data-theme="dark"] .semester-row {
            background: #162032 !important;
            color: #CBD5E1 !important;
        }

        html.dark-mode .teacher-tag,
        body.dark-mode .teacher-tag,
        [data-theme="dark"] .teacher-tag {
            background-color: #0F172A !important;
            border-color: #F59E0B !important;
            color: #FCD34D !important;
        }

        html.dark-mode .teacher-tag.locked,
        body.dark-mode .teacher-tag.locked,
        [data-theme="dark"] .teacher-tag.locked {
            background-color: rgba(239, 68, 68, 0.2) !important;
            border-color: #EF4444 !important;
            color: #FCA5A5 !important;
        }

        html.dark-mode .class-actions,
        body.dark-mode .class-actions,
        [data-theme="dark"] .class-actions {
            border-top-color: #334155 !important;
        }

        html.dark-mode .content-card-header h2,
        body.dark-mode .content-card-header h2,
        [data-theme="dark"] .content-card-header h2 {
            color: #FCD34D !important;
        }

        html.dark-mode .content-card-header,
        body.dark-mode .content-card-header,
        [data-theme="dark"] .content-card-header {
            border-bottom-color: #334155 !important;
        }

        html.dark-mode .form-group label,
        body.dark-mode .form-group label,
        [data-theme="dark"] .form-group label {
            color: #CBD5E1 !important;
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
            grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
            gap: 16px;
            margin-bottom: 20px;
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

        /* Class Cards Grid (Matching Teachers Grid) */
        .classes-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(380px, 1fr));
            gap: 25px;
            margin-top: 20px;
        }

        .class-card {
            background: white;
            border-radius: 15px;
            padding: 20px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            border: 2px solid var(--gold-pale);
            transition: all 0.3s ease;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }

        .class-card:hover {
            transform: translateY(-3px);
            border-color: var(--gold-primary);
            box-shadow: 0 10px 25px rgba(0,0,0,0.12);
        }

        .class-header {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 15px;
            padding-bottom: 15px;
            border-bottom: 2px solid var(--gold-pale);
        }

        .class-avatar {
            width: 60px;
            height: 60px;
            background: linear-gradient(135deg, var(--gold-primary), var(--gold-dark));
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 26px;
            font-weight: bold;
            color: var(--brown-dark);
            border: 3px solid white;
            box-shadow: 0 3px 10px rgba(0,0,0,0.1);
            overflow: hidden;
            flex-shrink: 0;
        }

        .class-title-area {
            flex: 1;
            min-width: 0;
        }

        .class-name {
            font-size: 18px;
            font-weight: bold;
            color: var(--brown-dark);
            margin-bottom: 4px;
            display: block;
            line-height: 1.3;
        }

        .class-division-badge {
            font-size: 12px;
            font-weight: 600;
            padding: 2px 10px;
            border-radius: 15px;
            display: inline-block;
            margin-bottom: 4px;
        }
        .class-division-badge.children {
            background: #EFF6FF;
            color: #2563EB;
            border: 1px solid #BFDBFE;
        }
        .class-division-badge.youth {
            background: #FEF3C7;
            color: #D97706;
            border: 1px solid #FDE68A;
        }
        .class-division-badge.unassigned {
            background: #F3F4F6;
            color: #6B7280;
            border: 1px solid #E5E7EB;
        }

        .class-meta-info {
            color: #666;
            font-size: 13px;
            display: flex;
            align-items: center;
            gap: 8px;
            margin-top: 2px;
        }

        .class-desc {
            font-size: 13px;
            color: var(--text-muted);
            line-height: 1.5;
            margin-bottom: 14px;
            padding: 8px 12px;
            background: #FAF9F6;
            border-radius: 8px;
            border-left: 3px solid var(--gold-dark);
        }

        /* Metrics inside Class Card */
        .class-metrics {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
            background: #F8F9FA;
            padding: 12px;
            border-radius: 10px;
            margin-bottom: 14px;
            border: 1px solid rgba(0,0,0,0.04);
        }
        .class-metric-item {
            text-align: center;
        }
        .class-metric-val {
            font-size: 20px;
            font-weight: 800;
            color: var(--brown-dark);
        }
        .class-metric-lbl {
            font-size: 11px;
            color: var(--text-muted);
            font-weight: 600;
        }

        /* Action Buttons */
        .class-actions {
            display: flex;
            gap: 8px;
            margin-top: 15px;
            flex-wrap: wrap;
            padding-top: 12px;
            border-top: 1px dashed rgba(0,0,0,0.08);
        }

        .btn-students {
            background: #8B5CF6;
            color: white !important;
            padding: 7px 12px;
            font-size: 12px;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            transition: all 0.2s;
        }
        .btn-students:hover {
            background: #7C3AED;
            transform: translateY(-1px);
        }

        .btn-edit {
            background: #3B82F6;
            color: white;
            padding: 7px 12px;
            font-size: 12px;
            border-radius: 8px;
            border: none;
            cursor: pointer;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            transition: all 0.2s;
        }
        .btn-edit:hover {
            background: #2563EB;
            transform: translateY(-1px);
        }

        .btn-delete {
            background: #EF4444;
            color: white;
            padding: 7px 12px;
            font-size: 12px;
            border-radius: 8px;
            border: none;
            cursor: pointer;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            transition: all 0.2s;
        }
        .btn-delete:hover:not(:disabled) {
            background: #DC2626;
            transform: translateY(-1px);
        }

        /* Timeline Styles from Teachers Design */
        .timeline {
            margin-top: 14px;
        }

        .year-group {
            margin-bottom: 12px;
            border-left: 3px solid var(--gold-primary);
            padding-left: 12px;
        }

        .year-header {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 6px;
            cursor: pointer;
            padding: 6px 10px;
            background: #F8F9FA;
            border-radius: 8px;
            transition: all 0.2s;
            user-select: none;
        }

        .year-header:hover {
            background: var(--gold-pale);
        }

        .year-badge {
            background: var(--brown-dark);
            color: var(--gold-primary);
            padding: 3px 10px;
            border-radius: 20px;
            font-weight: bold;
            font-size: 12px;
        }

        .year-status {
            font-size: 11px;
            color: #059669;
            font-weight: 700;
        }

        .toggle-icon {
            margin-left: auto;
            font-size: 13px;
            color: var(--gold-dark);
        }

        .semester-row {
            display: flex;
            gap: 10px;
            margin-bottom: 8px;
            padding: 6px 10px;
            background: #F8F9FA;
            border-radius: 8px;
            align-items: center;
        }

        .semester-badge {
            min-width: 80px;
            padding: 3px 8px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 700;
            text-align: center;
        }

        .semester-1 {
            background: #EFF6FF;
            color: #2563EB;
            border: 1px solid #BFDBFE;
        }

        .semester-2 {
            background: #FEF3C7;
            color: #D97706;
            border: 1px solid #FDE68A;
        }

        .teachers-list {
            flex: 1;
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
        }

        .teacher-tag {
            background: white;
            border: 1px solid var(--gold-primary);
            color: var(--brown-dark);
            padding: 3px 10px;
            border-radius: 16px;
            font-size: 11.5px;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            text-decoration: none;
            transition: all 0.2s;
        }

        .teacher-tag:hover {
            background: var(--gold-pale);
            border-color: var(--gold-dark);
        }

        .teacher-tag.locked {
            background: #FEE2E2;
            border-color: #EF4444;
            color: #DC2626;
        }

        .no-data {
            color: #9CA3AF;
            font-style: italic;
            padding: 6px 8px;
            font-size: 12px;
        }

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

        @media (max-width: 768px) {
            .main-container { padding: 0 10px 40px; margin: 12px auto; }
            .page-header-card { padding: 18px 16px; }
            .content-card { padding: 16px 14px; border-radius: 14px; }
            .form-grid { grid-template-columns: 1fr; }
            .btn-primary-action { width: 100%; justify-content: center; min-height: 44px; }
            .classes-grid { grid-template-columns: 1fr; }
            .class-actions { flex-direction: row; }
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
                <p>ክፍሎችን ይመዝግቡ፣ ከስርዓተ-ትምህርት ደረጃዎች ጋር ያገናኙ እና የተማሪ/መምህር ምደባዎችን ይከታተሉ።</p>
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
                    ➕ አዲስ ክፍል መዝግብ
                </button>
            </form>
        </div>

        <!-- Classes List -->
        <div class="content-card">
            <div class="content-card-header">
                <h2><span>📚</span> የነባር ክፍሎች ዝርዝር</h2>
                <span class="header-badge"><?php echo $total_classes; ?> ክፍሎች</span>
            </div>

            <!-- Instant Search Box -->
            <div style="margin-bottom: 20px;">
                <input type="text" id="classSearch" class="form-control" placeholder="🔍 የክፍል ስም፣ ደረጃ ወይም መግለጫ ይፈልጉ..." onkeyup="filterClasses()">
            </div>

            <?php if($classes && mysqli_num_rows($classes) > 0): ?>
            <div class="classes-grid" id="classesListContainer">
                <?php 
                mysqli_data_seek($classes, 0);
                while($class = mysqli_fetch_assoc($classes)): 
                    $class_id = $class['id'];
                    $class_name = $class['name'];
                    $class_desc = $class['description'];
                    $student_count = intval($class['student_count']);
                    $teacher_count = intval($class['teacher_count']);
                    $grade_name = $class['grade_name'];
                    $division_name = $class['division_name'];
                    $divClass = ($class['division_code'] === 'CHILDREN') ? 'children' : (($class['division_code'] === 'YOUTH') ? 'youth' : 'unassigned');
                    $class_teachers = $class_teacher_history[$class_id] ?? [];
                    $has_history = !empty($class_teachers);
                    $search_text = strtolower($class_name . ' ' . ($grade_name ?? '') . ' ' . ($division_name ?? '') . ' ' . ($class_desc ?? ''));
                ?>
                <div class="class-card" data-search="<?php echo htmlspecialchars($search_text, ENT_QUOTES, 'UTF-8'); ?>">
                    <div>
                        <div class="class-header">
                            <div class="class-avatar">
                                🏫
                            </div>
                            <div class="class-title-area">
                                <div>
                                    <span class="class-name">
                                        <?php echo htmlspecialchars($class_name); ?>
                                    </span>
                                </div>
                                <div>
                                    <?php if($grade_name): ?>
                                    <span class="class-division-badge <?php echo $divClass; ?>">
                                        🏷️ <?php echo htmlspecialchars($division_name . ' · ' . $grade_name); ?>
                                    </span>
                                    <?php else: ?>
                                    <span class="class-division-badge unassigned">
                                        ⚠️ ደረጃ ያልተመደበ
                                    </span>
                                    <?php endif; ?>
                                </div>
                                <div class="class-meta-info">
                                    <span>👥 <?php echo $student_count; ?> ተማሪዎች</span>
                                    <span>•</span>
                                    <span>👨‍🏫 <?php echo $teacher_count; ?> መምህራን</span>
                                </div>
                            </div>
                        </div>

                        <?php if(!empty($class_desc)): ?>
                        <div class="class-desc">
                            <?php echo htmlspecialchars($class_desc); ?>
                        </div>
                        <?php endif; ?>

                        <div class="class-metrics">
                            <div class="class-metric-item">
                                <div class="class-metric-val"><?php echo $student_count; ?></div>
                                <div class="class-metric-lbl">ተማሪዎች</div>
                            </div>
                            <div class="class-metric-item">
                                <div class="class-metric-val"><?php echo $teacher_count; ?></div>
                                <div class="class-metric-lbl">መምህራን</div>
                            </div>
                        </div>

                        <div class="class-actions">
                            <a href="manage_students.php?class_id=<?php echo $class_id; ?>" class="btn-students">
                                👥 ተማሪዎች (<?php echo $student_count; ?>)
                            </a>
                            <button type="button" onclick="editClass(<?php echo $class_id; ?>, '<?php echo htmlspecialchars(addslashes($class_name), ENT_QUOTES); ?>', '<?php echo htmlspecialchars(addslashes($class_desc ?? ''), ENT_QUOTES); ?>', <?php echo $class['grade_id'] ? (int)$class['grade_id'] : 'null'; ?>)" 
                                    class="btn-edit">
                                ✏️ አስተካክል
                            </button>
                            <?php if($student_count == 0): ?>
                            <form method="POST" style="display: inline;" 
                                  onsubmit="return confirm('እርግጠኛ ነዎት ክፍል [<?php echo htmlspecialchars(addslashes($class_name), ENT_QUOTES); ?>] መሰረዝ ይፈልጋሉ? ይህ ክዋኔ ሊቀለበስ አይችልም!')">
                                <?php echo csrfField(); ?>
                                <input type="hidden" name="class_id" value="<?php echo $class_id; ?>">
                                <button type="submit" name="delete_class" class="btn-delete">
                                    🗑️ ሰርዝ
                                </button>
                            </form>
                            <?php else: ?>
                            <button type="button" class="btn-delete" style="opacity: 0.55; cursor: not-allowed;" 
                                    title="ይህ ክፍል ተማሪዎች ስላሉት መሰረዝ አይቻልም! መጀመሪያ ተማሪዎቹን ያዛውሩ።">
                                🗑️ መሰረዝ አይቻልም
                            </button>
                            <?php endif; ?>
                        </div>

                        <!-- Assigned Teachers Timeline -->
                        <div class="timeline">
                            <?php if($has_history): ?>
                                <?php 
                                krsort($class_teachers);
                                foreach($class_teachers as $year => $semesters): 
                                    $is_current = ($year == $current_ethiopian_year);
                                ?>
                                <div class="year-group">
                                    <div class="year-header" onclick="toggleYear('year-<?php echo $class_id . '-' . $year; ?>')">
                                        <span class="year-badge"><?php echo $year; ?> ዓ.ም</span>
                                        <?php if($is_current): ?>
                                        <span class="year-status" style="color: var(--success); font-weight:700;">(ንቁ)</span>
                                        <?php endif; ?>
                                        <span class="toggle-icon" id="icon-<?php echo $class_id . '-' . $year; ?>"><?php echo $is_current ? '▼' : '▶'; ?></span>
                                    </div>
                                    
                                    <div id="year-<?php echo $class_id . '-' . $year; ?>" style="display: <?php echo $is_current ? 'block' : 'none'; ?>;">
                                        <?php if(!empty($semesters['semester1'])): ?>
                                        <div class="semester-row">
                                            <div class="semester-badge semester-1">ሴሚስተር 1</div>
                                            <div class="teachers-list">
                                                <?php foreach($semesters['semester1'] as $assignment): ?>
                                                <a href="teacher_profile.php?id=<?php echo $assignment['teacher_id']; ?>" class="teacher-tag <?php echo $assignment['locked'] ? 'locked' : ''; ?>" title="የመምህሩን መገለጫ ይመልከቱ">
                                                    <?php if($assignment['locked']): ?>🔒 <?php endif; ?>
                                                    👨‍🏫 <?php echo htmlspecialchars($assignment['teacher_name']); ?>
                                                </a>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>
                                        <?php endif; ?>

                                        <?php if(!empty($semesters['semester2'])): ?>
                                        <div class="semester-row">
                                            <div class="semester-badge semester-2">ሴሚስተር 2</div>
                                            <div class="teachers-list">
                                                <?php foreach($semesters['semester2'] as $assignment): ?>
                                                <a href="teacher_profile.php?id=<?php echo $assignment['teacher_id']; ?>" class="teacher-tag <?php echo $assignment['locked'] ? 'locked' : ''; ?>" title="የመምህሩን መገለጫ ይመልከቱ">
                                                    <?php if($assignment['locked']): ?>🔒 <?php endif; ?>
                                                    👨‍🏫 <?php echo htmlspecialchars($assignment['teacher_name']); ?>
                                                </a>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="no-data">
                                    ይህ ክፍል እስካሁን ምንም መምህር አልተመደበለትም
                                </div>
                            <?php endif; ?>
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

        function toggleYear(id) {
            var el = document.getElementById(id);
            var icon = document.getElementById(id.replace('year-', 'icon-'));
            if (!el) return;
            if (el.style.display === 'none' || el.style.display === '') {
                el.style.display = 'block';
                if (icon) icon.innerText = '▼';
            } else {
                el.style.display = 'none';
                if (icon) icon.innerText = '▶';
            }
        }

        function filterClasses() {
            var q = document.getElementById('classSearch').value.toLowerCase();
            var cards = document.querySelectorAll('.class-card');
            cards.forEach(function(card) {
                var searchData = card.getAttribute('data-search') || '';
                if (!q || searchData.indexOf(q) > -1) {
                    card.style.display = 'flex';
                } else {
                    card.style.display = 'none';
                }
            });
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