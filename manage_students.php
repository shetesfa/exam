<?php
require_once 'db.php';
requireAdmin();

$message = '';
$error = '';

// Handle Add/Edit/Delete/Reset PIN
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = "የደህንነት ማረጋገጫ አልተሳካም! እባክዎ እንደገና ይሞክሩ።";
    } else {
        if (isset($_POST['add_student'])) {
            $name = trim($_POST['name'] ?? '');
            $class_id = intval($_POST['class_id'] ?? 0);
            $parent_phone = trim($_POST['parent_phone'] ?? '');
            
            if (!empty($name) && $class_id > 0) {
                $stmt = mysqli_prepare($conn, "INSERT INTO students (name, class_id, parent_phone) VALUES (?, ?, ?)");
                if ($stmt) {
                    mysqli_stmt_bind_param($stmt, "sis", $name, $class_id, $parent_phone);
                    if (mysqli_stmt_execute($stmt)) {
                        $new_student_id = mysqli_insert_id($conn);
                        mysqli_stmt_close($stmt);
                        
                        // Default PIN
                        $default_pin = '123';
                        if (!empty($parent_phone) && strlen($parent_phone) >= 4) {
                            $default_pin = substr($parent_phone, -4);
                        }
                        $hashed_pin = password_hash($default_pin, PASSWORD_DEFAULT);
                        
                        dbExecute(
                            $conn,
                            "INSERT INTO student_logins (student_id, pin, first_login) 
                             VALUES (?, ?, 1)
                             ON DUPLICATE KEY UPDATE pin = VALUES(pin), first_login = 1",
                            "is",
                            [$new_student_id, $hashed_pin]
                        );
                        
                        $message = "ተማሪው በተሳካ ሁኔታ ተመዝግቧል! የመግቢያ ፒን: $default_pin";
                    } else {
                        $error = "ስህተት ተከስቷል! " . mysqli_stmt_error($stmt);
                        mysqli_stmt_close($stmt);
                    }
                }
            } else {
                $error = "እባክዎ የተማሪውን ስም እና ክፍል በትክክል ያስገቡ!";
            }
        }
        
        if (isset($_POST['edit_student'])) {
            $student_id = intval($_POST['student_id'] ?? 0);
            $name = trim($_POST['name'] ?? '');
            $class_id = intval($_POST['class_id'] ?? 0);
            $parent_phone = trim($_POST['parent_phone'] ?? '');
            
            if ($student_id > 0 && !empty($name) && $class_id > 0) {
                $updated = dbExecute(
                    $conn,
                    "UPDATE students SET name = ?, class_id = ?, parent_phone = ? WHERE id = ?",
                    "sisi",
                    [$name, $class_id, $parent_phone, $student_id]
                );
                if ($updated) {
                    $message = "የተማሪው መረጃ ተሻሽሏል!";
                } else {
                    $error = "ስህተት ተከስቷል!";
                }
            }
        }
        
        if (isset($_POST['delete_student'])) {
            $student_id = intval($_POST['student_id'] ?? 0);
            
            if ($student_id > 0) {
                // Soft-delete marks and attendance to preserve historical records
                dbExecute($conn, "UPDATE marks SET is_deleted = 1 WHERE student_id = ?", "i", [$student_id]);
                dbExecute($conn, "UPDATE attendance_records SET is_deleted = 1 WHERE student_id = ?", "i", [$student_id]);
                // Hard-delete login credentials (no is_deleted column)
                dbExecute($conn, "DELETE FROM student_logins WHERE student_id = ?", "i", [$student_id]);
                // Soft-delete the student
                dbExecute($conn, "UPDATE students SET is_deleted = 1 WHERE id = ?", "i", [$student_id]);
                auditLog($conn, 'student_deleted', 'students', $student_id, null);
                
                $message = "ተማሪው ተሰርዟል!";
            }
        }

        // HANDLE PIN RESET
        if (isset($_POST['reset_student_pin'])) {
            $student_id = intval($_POST['student_id'] ?? 0);
            if ($student_id > 0) {
                $new_pin = '123';
                $hashed_pin = password_hash($new_pin, PASSWORD_DEFAULT);
                
                $reset = dbExecute(
                    $conn,
                    "INSERT INTO student_logins (student_id, pin, first_login, login_attempts, locked_until) 
                     VALUES (?, ?, 1, 0, NULL)
                     ON DUPLICATE KEY UPDATE pin = VALUES(pin), first_login = 1, login_attempts = 0, locked_until = NULL",
                    "is",
                    [$student_id, $hashed_pin]
                );
                
                if ($reset) {
                    $student_name_row = dbFetchOne($conn, "SELECT name FROM students WHERE id = ?", "i", [$student_id]);
                    $student_name = $student_name_row ? $student_name_row['name'] : 'ተማሪ';
                    $message = "የ{$student_name} ፒን ወደ 123 ተመልሷል!";
                } else {
                    $error = "ስህተት ተከስቷል!";
                }
            }
        }
        
        // HANDLE TOGGLE STUDENT PORTAL ACCESS
        if (isset($_POST['toggle_portal'])) {
            $student_id = intval($_POST['student_id'] ?? 0);
            $current_status = intval($_POST['current_status'] ?? 0);
            $new_status = $current_status ? 0 : 1;
            
            if ($student_id > 0) {
                $toggled = dbExecute($conn, "UPDATE students SET student_portal_enabled = ? WHERE id = ?", "ii", [$new_status, $student_id]);
                if ($toggled) {
                    $status_text = $new_status ? 'በርቷል' : 'ጠፍቷል';
                    $message = "የተማሪ ፖርታል መዳረሻ $status_text!";
                } else {
                    $error = "ስህተት ተከስቷል!";
                }
            }
        }
    }
}

// Get all classes
$classes = mysqli_query($conn, "SELECT * FROM classes ORDER BY id ASC");

// Filters
$search_filter = isset($_GET['search']) ? trim($_GET['search']) : '';
$class_filter = isset($_GET['class_id']) ? intval($_GET['class_id']) : 0;

// Pagination
$per_page = 30;
$page = max(1, intval($_GET['page'] ?? 1));
$offset = ($page - 1) * $per_page;

// Build dynamic WHERE clause
$where_clauses = ["(s.is_deleted = 0 OR s.is_deleted IS NULL)"];
$params = [];
$types = "";

if (!empty($search_filter)) {
    $searchParam = '%' . $search_filter . '%';
    $where_clauses[] = "(s.name LIKE ? OR c.name LIKE ? OR s.parent_phone LIKE ?)";
    $params[] = $searchParam;
    $params[] = $searchParam;
    $params[] = $searchParam;
    $types .= "sss";
}

if ($class_filter > 0) {
    $where_clauses[] = "s.class_id = ?";
    $params[] = $class_filter;
    $types .= "i";
}

$where_sql = implode(" AND ", $where_clauses);

// Count
if (!empty($types)) {
    $count_row = dbFetchOne($conn, "SELECT COUNT(*) as cnt FROM students s JOIN classes c ON s.class_id = c.id WHERE $where_sql", $types, $params);
} else {
    $count_row = dbFetchOne($conn, "SELECT COUNT(*) as cnt FROM students s JOIN classes c ON s.class_id = c.id WHERE $where_sql");
}
$total_students = $count_row ? (int)$count_row['cnt'] : 0;

// Portal active count
$active_portal_row = dbFetchOne($conn, "SELECT COUNT(*) as cnt FROM students WHERE (is_deleted = 0 OR is_deleted IS NULL) AND student_portal_enabled = 1");
$total_portal_active = $active_portal_row ? (int)$active_portal_row['cnt'] : 0;

// Fetch query
$query_sql = "SELECT s.*, c.name as class_name,
                     COALESCE(sl.pin IS NOT NULL, 0) as has_pin,
                     COALESCE(sl.first_login, 1) as pin_first_login,
                     s.student_portal_enabled
              FROM students s
              JOIN classes c ON s.class_id = c.id
              LEFT JOIN student_logins sl ON s.id = sl.student_id
              WHERE $where_sql
              ORDER BY c.id, s.name
              LIMIT $per_page OFFSET $offset";

if (!empty($types)) {
    $students = dbFetchAll($conn, $query_sql, $types, $params);
} else {
    $students = dbFetchAll($conn, $query_sql);
}

$total_pages = max(1, ceil($total_students / $per_page));
$nav_active = 'manage_students';
?>
<!DOCTYPE html>
<html lang="am">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>የተማሪዎች አስተዳደር | አጸደ ትጉሃን</title>
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
            --warning: #F59E0B;
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

        .main-container { max-width: 1300px; margin: 12px auto; padding: 0 10px 40px; }

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
            content: '👥';
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

        /* Tip Alert */
        .portal-tip-card {
            background: #EFF6FF;
            border: 1.5px solid #BFDBFE;
            border-radius: 14px;
            padding: 16px 20px;
            margin-bottom: 24px;
            display: flex;
            align-items: flex-start;
            gap: 14px;
        }
        .tip-icon { font-size: 24px; }
        .tip-content { font-size: 13.5px; color: #1E40AF; line-height: 1.5; }
        .tip-content strong { color: #1E3A8A; }
        .tip-content a { color: #2563EB; font-weight: 700; text-decoration: underline; }

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

        /* Filter Row */
        .filters-toolbar {
            display: grid;
            grid-template-columns: 1fr;
            gap: 12px;
            align-items: center;
            margin-bottom: 20px;
        }
        .btn-search {
            padding: 11px 20px;
            background: linear-gradient(135deg, var(--gold-primary) 0%, var(--gold-dark) 100%);
            color: var(--brown-dark);
            border: none;
            border-radius: 10px;
            font-weight: 700;
            font-size: 14px;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        .btn-clear {
            padding: 11px 16px;
            background: #F3F4F6;
            color: var(--text-muted);
            border-radius: 10px;
            text-decoration: none;
            font-size: 13px;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }

        /* Table */
        .table-responsive {
            width: 100%;
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }
        table.student-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13.5px;
            text-align: left;
        }
        table.student-table th {
            background: #F9FAFB;
            color: var(--brown-dark);
            font-weight: 700;
            padding: 14px 16px;
            border-bottom: 2px solid var(--border-color);
            white-space: nowrap;
        }
        table.student-table td {
            padding: 14px 16px;
            border-bottom: 1px solid #F3F4F6;
            vertical-align: middle;
        }
        table.student-table tbody tr:hover {
            background: rgba(255, 215, 0, 0.03);
        }

        /* User Chip */
        .student-chip {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .student-avatar {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--gold-primary), var(--gold-dark));
            color: var(--brown-dark);
            font-weight: 800;
            font-size: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }
        .student-name-text {
            font-weight: 700;
            color: var(--text-main);
        }

        /* Badges */
        .class-badge {
            background: var(--gold-pale);
            color: var(--brown-dark);
            padding: 4px 10px;
            border-radius: 12px;
            font-weight: 700;
            font-size: 12px;
            display: inline-block;
            white-space: nowrap;
        }
        .phone-link {
            color: #2563EB;
            text-decoration: none;
            font-weight: 600;
            font-family: monospace;
            font-size: 13px;
        }

        /* Status Toggle */
        .btn-portal-status {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 700;
            border: none;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            transition: opacity 0.2s;
        }
        .btn-portal-status.active { background: #DCFCE7; color: #166534; }
        .btn-portal-status.inactive { background: #F3F4F6; color: #6B7280; }
        .btn-portal-status:hover { opacity: 0.85; }

        .pin-pill {
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 11.5px;
            font-weight: 700;
            display: inline-block;
        }
        .pin-pill.new { background: #FEF3C7; color: #92400E; }
        .pin-pill.active { background: #DCFCE7; color: #166534; }

        /* Action Buttons */
        .actions-group {
            display: flex;
            align-items: center;
            gap: 6px;
            flex-wrap: wrap;
        }
        .btn-st-edit {
            background: #FEF3C7;
            color: #B45309;
            border: none;
            padding: 6px 10px;
            border-radius: 8px;
            font-weight: 700;
            font-size: 12px;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }
        .btn-st-edit:hover { background: #FDE68A; }

        .btn-st-reset {
            background: #E0E7FF;
            color: #3730A3;
            border: none;
            padding: 6px 10px;
            border-radius: 8px;
            font-weight: 700;
            font-size: 12px;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }
        .btn-st-reset:hover { background: #C7D2FE; }

        .btn-st-del {
            background: #FEE2E2;
            color: #DC2626;
            border: none;
            padding: 6px 9px;
            border-radius: 8px;
            font-weight: 700;
            font-size: 12px;
            cursor: pointer;
        }
        .btn-st-del:hover { background: #FCA5A5; }

        /* Pagination */
        .pager-container {
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 16px 20px;
            background: #F9FAFB;
            border-top: 1px solid var(--border-color);
            flex-wrap: wrap;
            gap: 12px;
        }
        .pager-controls {
            display: flex;
            gap: 6px;
            align-items: center;
            flex-wrap: wrap;
        }
        .pager-controls a, .pager-controls span {
            min-width: 36px;
            height: 36px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 0 10px;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 600;
            text-decoration: none;
        }
        .pager-controls a {
            background: white;
            color: var(--text-main);
            border: 1px solid var(--border-color);
        }
        .pager-controls a:hover {
            background: var(--gold-pale);
            border-color: var(--gold-dark);
            color: var(--brown-dark);
        }
        .pager-controls span.current {
            background: linear-gradient(135deg, var(--gold-primary), var(--gold-dark));
            color: var(--brown-dark);
            font-weight: 800;
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
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 50px 20px;
            color: var(--text-muted);
        }
        .empty-icon {
            font-size: 48px;
            margin-bottom: 12px;
            opacity: 0.7;
        }

        @media (min-width: 769px) {
            .main-container { padding: 0 10px 40px; margin: 12px auto; }
            .page-header-card { padding: 18px 16px; }
            .content-card { padding: 16px 14px; border-radius: 14px; }
            .form-grid { grid-template-columns: 1fr; }
            .filters-toolbar { grid-template-columns: 1fr; }
            .btn-search, .btn-clear, .btn-primary-action { width: 100%; justify-content: center; min-height: 44px; }
            .actions-group { flex-wrap: wrap; }
            .pager-container { justify-content: center; text-align: center; }
        }
    </style>
</head>
<body>
    <?php include 'mobile_nav.php'; ?>

    <div class="main-container">
        <!-- Page Header -->
        <div class="page-header-card">
            <div class="header-info">
                <h1>👥 የተማሪዎች አስተዳደር</h1>
                <p>ተማሪዎችን ይመዝግቡ፣ ክፍል መድቡ፣ የተማሪ ፖርታል መዳረሻ እና የPIN ኮዶችን ያስተዳድሩ።</p>
            </div>
        </div>

        <?php if($message): ?>
        <div class="message success">✅ <?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?></div>
        <?php endif; ?>

        <?php if($error): ?>
        <div class="message error">⚠️ <?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
        <?php endif; ?>

        <!-- Statistics -->
        <div class="stats-grid">
            <div class="stat-box">
                <div class="stat-icon">👥</div>
                <div class="stat-data">
                    <div class="stat-val"><?php echo number_format($total_students); ?></div>
                    <div class="stat-lbl">ጠቅላላ ተማሪዎች</div>
                </div>
            </div>
            <div class="stat-box">
                <div class="stat-icon">🎓</div>
                <div class="stat-data">
                    <div class="stat-val"><?php echo number_format($total_portal_active); ?></div>
                    <div class="stat-lbl">ፖርታል የነቃላቸው</div>
                </div>
            </div>
            <div class="stat-box">
                <div class="stat-icon">📄</div>
                <div class="stat-data">
                    <div class="stat-val"><?php echo $page; ?> / <?php echo $total_pages; ?></div>
                    <div class="stat-lbl">የአሁኑ ገጽ</div>
                </div>
            </div>
        </div>

        <!-- Portal Tip Box -->
        <div class="portal-tip-card">
            <div class="tip-icon">💡</div>
            <div class="tip-content">
                <strong>የተማሪ ፖርታል መረጃ፦</strong> ተማሪዎች በ<a href="student_login.php" target="_blank">የተማሪ መግቢያ ገጽ</a> በኩል ሙሉ ስማቸውን እና ፒናቸውን በማስገባት ውጤታቸውን ማየት ይችላሉ። <strong>ነባሪ ፒን: 123</strong> (ወይም የወላጅ ስልክ የመጨረሻ 4 አሃዝ) ነው።
            </div>
        </div>

        <!-- Add Student Form -->
        <div class="content-card">
            <div class="content-card-header">
                <h2><span>➕</span> አዲስ ተማሪ መመዝገቢያ</h2>
            </div>
            <form method="POST">
                <?php echo csrfField(); ?>
                <div class="form-grid">
                    <div class="form-group">
                        <label>የተማሪው ሙሉ ስም <span style="color: var(--error);">*</span></label>
                        <input type="text" name="name" class="form-control" required placeholder="የተማሪ ሙሉ ስም">
                    </div>
                    <div class="form-group">
                        <label>ክፍል <span style="color: var(--error);">*</span></label>
                        <select name="class_id" class="form-control" required>
                            <option value="">-- ክፍል ይምረጡ --</option>
                            <?php 
                            mysqli_data_seek($classes, 0);
                            while($cl = mysqli_fetch_assoc($classes)): 
                            ?>
                            <option value="<?php echo $cl['id']; ?>" <?php echo $class_filter == $cl['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($cl['name']); ?>
                            </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>የወላጅ ስልክ ቁጥር</label>
                        <input type="text" name="parent_phone" class="form-control" placeholder="09...">
                    </div>
                </div>
                <button type="submit" name="add_student" class="btn-primary-action">
                    ➕ ተማሪ አስመዝግብ
                </button>
            </form>
        </div>

        <!-- Students List Section -->
        <div class="content-card">
            <div class="content-card-header">
                <h2><span>📋</span> የተማሪዎች ዝርዝር</h2>
                <span class="header-badge"><?php echo number_format($total_students); ?> ተማሪዎች</span>
            </div>

            <!-- Search & Filters Toolbar -->
            <form method="GET" class="filters-toolbar">
                <input type="text" name="search" class="form-control" 
                       placeholder="🔍 የተማሪ ስም ወይም የወላጅ ስልክ ይፈልጉ..." 
                       value="<?php echo htmlspecialchars($search_filter); ?>">
                
                <select name="class_id" class="form-control" onchange="this.form.submit()">
                    <option value="0">📚 ሁሉም ክፍሎች</option>
                    <?php 
                    mysqli_data_seek($classes, 0);
                    while($cl = mysqli_fetch_assoc($classes)): 
                    ?>
                    <option value="<?php echo $cl['id']; ?>" <?php echo $class_filter == $cl['id'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($cl['name']); ?>
                    </option>
                    <?php endwhile; ?>
                </select>

                <button type="submit" class="btn-search">🔍 ፈልግ</button>
                
                <?php if(!empty($search_filter) || $class_filter > 0): ?>
                <a href="manage_students.php" class="btn-clear">✕ አጽዳ</a>
                <?php endif; ?>
            </form>

            <!-- Table -->
            <div class="table-responsive">
                <table class="student-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>የተማሪ ስም</th>
                            <th>ክፍል</th>
                            <th>የወላጅ ስልክ</th>
                            <th>ፖርታል</th>
                            <th>የፒን ሁኔታ</th>
                            <th>የተመዘገበበት</th>
                            <th>ድርጊቶች</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $counter = $offset + 1;
                        if(!empty($students)):
                            foreach($students as $st): 
                                $initial = mb_substr($st['name'], 0, 1, 'UTF-8');
                        ?>
                        <tr>
                            <td><?php echo $counter++; ?></td>
                            <td>
                                <div class="student-chip">
                                    <div class="student-avatar"><?php echo htmlspecialchars($initial); ?></div>
                                    <span class="student-name-text"><?php echo htmlspecialchars($st['name']); ?></span>
                                </div>
                            </td>
                            <td>
                                <span class="class-badge">
                                    🏫 <?php echo htmlspecialchars($st['class_name']); ?>
                                </span>
                            </td>
                            <td>
                                <?php if (!empty($st['parent_phone'])): ?>
                                <a href="tel:<?php echo htmlspecialchars($st['parent_phone']); ?>" class="phone-link">
                                    📞 <?php echo htmlspecialchars($st['parent_phone']); ?>
                                </a>
                                <?php else: ?>
                                <span style="color: var(--text-muted);">—</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <form method="POST" style="display: inline;">
                                    <?php echo csrfField(); ?>
                                    <input type="hidden" name="student_id" value="<?php echo $st['id']; ?>">
                                    <input type="hidden" name="current_status" value="<?php echo $st['student_portal_enabled']; ?>">
                                    <button type="submit" name="toggle_portal" 
                                            class="btn-portal-status <?php echo $st['student_portal_enabled'] ? 'active' : 'inactive'; ?>"
                                            title="ሁኔታ ለመቀየር ይጫኑ">
                                        <?php echo $st['student_portal_enabled'] ? '✅ በርቷል' : '⛔ ጠፍቷል'; ?>
                                    </button>
                                </form>
                            </td>
                            <td>
                                <?php if ($st['pin_first_login']): ?>
                                <span class="pin-pill new">🆕 አዲስ (123)</span>
                                <?php else: ?>
                                <span class="pin-pill active">✅ የተቀየረ</span>
                                <?php endif; ?>
                            </td>
                            <td style="font-size: 12.5px; color: var(--text-muted); white-space: nowrap;">
                                <?php echo date('Y-m-d', strtotime($st['enrollment_date'])); ?>
                            </td>
                            <td>
                                <div class="actions-group">
                                    <button onclick="editStudent(<?php echo $st['id']; ?>, '<?php echo htmlspecialchars(addslashes($st['name']), ENT_QUOTES); ?>', <?php echo $st['class_id']; ?>, '<?php echo htmlspecialchars(addslashes($st['parent_phone'] ?? ''), ENT_QUOTES); ?>')" 
                                            class="btn-st-edit">
                                        ✏️ አርትዕ
                                    </button>
                                    
                                    <form method="POST" style="display: inline;" 
                                          onsubmit="return confirm('የ[<?php echo htmlspecialchars(addslashes($st['name']), ENT_QUOTES); ?>] ፒን ወደ 123 ማስጀመር እርግጠኛ ነዎት?')">
                                        <?php echo csrfField(); ?>
                                        <input type="hidden" name="student_id" value="<?php echo $st['id']; ?>">
                                        <button type="submit" name="reset_student_pin" class="btn-st-reset" title="ፒን ወደ 123 መልስ">
                                            🔄 ፒን
                                        </button>
                                    </form>
                                    
                                    <form method="POST" style="display: inline;" 
                                          onsubmit="return confirm('ተማሪ [<?php echo htmlspecialchars(addslashes($st['name']), ENT_QUOTES); ?>] መሰረዝ እርግጠኛ ነዎት?')">
                                        <?php echo csrfField(); ?>
                                        <input type="hidden" name="student_id" value="<?php echo $st['id']; ?>">
                                        <button type="submit" name="delete_student" class="btn-st-del" title="ተማሪ ሰርዝ">
                                            🗑️
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; else: ?>
                        <tr>
                            <td colspan="8">
                                <div class="empty-state">
                                    <div class="empty-icon">👥</div>
                                    <div style="font-weight: 700; margin-bottom: 4px;">ምንም ተማሪ አልተገኘም</div>
                                    <?php if(!empty($search_filter)): ?>
                                    <p>ለ "<?php echo htmlspecialchars($search_filter); ?>" የተገኘ ውጤት የለም።</p>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
            <div class="pager-container">
                <div style="font-size: 13px; color: var(--text-muted);">
                    በአጠቃላይ <?php echo number_format($total_students); ?> ተማሪዎች | ገጽ <?php echo $page; ?> ከ <?php echo $total_pages; ?>
                </div>
                <div class="pager-controls">
                    <?php
                    $qs = '';
                    if (!empty($search_filter)) $qs .= '&search=' . urlencode($search_filter);
                    if ($class_filter > 0) $qs .= '&class_id=' . $class_filter;

                    if ($page > 1): ?>
                        <a href="?page=1<?php echo $qs; ?>">««</a>
                        <a href="?page=<?php echo ($page - 1) . $qs; ?>">‹</a>
                    <?php endif; ?>

                    <?php
                    $start_p = max(1, $page - 2);
                    $end_p = min($total_pages, $page + 2);
                    for ($p = $start_p; $p <= $end_p; $p++):
                        if ($p === $page): ?>
                            <span class="current"><?php echo $p; ?></span>
                        <?php else: ?>
                            <a href="?page=<?php echo $p . $qs; ?>"><?php echo $p; ?></a>
                        <?php endif;
                    endfor; ?>

                    <?php if ($page < $total_pages): ?>
                        <a href="?page=<?php echo ($page + 1) . $qs; ?>">›</a>
                        <a href="?page=<?php echo $total_pages . $qs; ?>">»»</a>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Edit Student Modal -->
    <div id="editModal" class="modal-overlay">
        <div class="modal-card">
            <div class="modal-header">
                <h3>✏️ የተማሪ መረጃ ማስተካከያ</h3>
                <button class="modal-close" onclick="closeModal()">&times;</button>
            </div>
            <form method="POST" id="editForm">
                <?php echo csrfField(); ?>
                <input type="hidden" name="student_id" id="edit_id">
                <div class="form-group" style="margin-bottom: 14px;">
                    <label>የተማሪው ሙሉ ስም <span style="color: var(--error);">*</span></label>
                    <input type="text" name="name" id="edit_name" class="form-control" required>
                </div>
                <div class="form-group" style="margin-bottom: 14px;">
                    <label>ክፍል <span style="color: var(--error);">*</span></label>
                    <select name="class_id" id="edit_class" class="form-control" required>
                        <option value="">-- ክፍል ይምረጡ --</option>
                        <?php 
                        mysqli_data_seek($classes, 0);
                        while($cl = mysqli_fetch_assoc($classes)): 
                        ?>
                        <option value="<?php echo $cl['id']; ?>"><?php echo htmlspecialchars($cl['name']); ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="form-group" style="margin-bottom: 20px;">
                    <label>የወላጅ ስልክ ቁጥር</label>
                    <input type="text" name="parent_phone" id="edit_phone" class="form-control">
                </div>
                <button type="submit" name="edit_student" class="btn-primary-action" style="width: 100%; justify-content: center;">
                    💾 ለውጦችን አስቀምጥ
                </button>
            </form>
        </div>
    </div>

    <script>
        function editStudent(id, name, classId, phone) {
            document.getElementById('edit_id').value = id;
            document.getElementById('edit_name').value = name;
            document.getElementById('edit_class').value = classId;
            document.getElementById('edit_phone').value = phone || '';
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