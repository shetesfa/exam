<?php
require_once 'db.php';
requireAdmin();

$message = '';
$error = '';

// Handle Add User
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = "የደህንነት ማረጋገጫ አልተሳካም! እባክዎ እንደገና ይሞክሩ።";
    } else {
        if (isset($_POST['add_user'])) {
            $name = trim($_POST['name'] ?? '');
            $username = trim($_POST['username'] ?? '');
            $phone = trim($_POST['phone'] ?? '');
            $role = trim($_POST['role'] ?? 'teacher');
            $password = hashPassword('123');
            
            if (!in_array($role, ['admin', 'teacher', 'attendance_submitter'])) {
                $role = 'teacher';
            }
            
            if (!empty($name) && !empty($username)) {
                // Check if username exists
                $check = dbFetchOne($conn, "SELECT id FROM users WHERE username = ?", "s", [$username]);
                if ($check) {
                    $error = "ይህ የተጠቃሚ ስም ቀድሞውኑ አለ! (Username already exists!)";
                } else {
                    $saved = dbExecute(
                        $conn,
                        "INSERT INTO users (name, username, phone, role, password, first_login) VALUES (?, ?, ?, ?, ?, 1)",
                        "sssss",
                        [$name, $username, $phone, $role, $password]
                    );
                    if ($saved) {
                        $message = "ተጠቃሚ በትክክል ተመዝግቧል! የይለፍ ቃል: 123";
                    } else {
                        $error = "ስህተት ተከስቷል!";
                    }
                }
            } else {
                $error = "እባክዎ ስም እና የተጠቃሚ ስም ያስገቡ!";
            }
        }
        
        if (isset($_POST['edit_user'])) {
            $user_id = intval($_POST['user_id'] ?? 0);
            $name = trim($_POST['name'] ?? '');
            $username = trim($_POST['username'] ?? '');
            $phone = trim($_POST['phone'] ?? '');
            $role = trim($_POST['role'] ?? 'teacher');
            
            if (!in_array($role, ['admin', 'teacher', 'attendance_submitter'])) {
                $role = 'teacher';
            }
            
            if ($user_id > 0 && !empty($name) && !empty($username)) {
                // Check if username is taken by someone else
                $check = dbFetchOne($conn, "SELECT id FROM users WHERE username = ? AND id != ?", "si", [$username, $user_id]);
                if ($check) {
                    $error = "ይህ የተጠቃሚ ስም በሌላ ተጠቃሚ ተይዟል!";
                } else {
                    $updated = dbExecute(
                        $conn,
                        "UPDATE users SET name = ?, username = ?, phone = ?, role = ? WHERE id = ?",
                        "ssssi",
                        [$name, $username, $phone, $role, $user_id]
                    );
                    if ($updated) {
                        $message = "የተጠቃሚ መረጃ ተሻሽሏል!";
                    } else {
                        $error = "ስህተት ተከስቷል!";
                    }
                }
            }
        }
        
        if (isset($_POST['delete_user'])) {
            $user_id = intval($_POST['user_id'] ?? 0);
            
            // Don't allow deleting own account
            if ($user_id === intval($_SESSION['user_id'])) {
                $error = "የራስዎን አካውንት መሰረዝ አይችሉም!";
            } elseif ($user_id > 0) {
                // Check for active teacher class assignments
                $active_assignments = dbFetchOne($conn, "SELECT COUNT(*) as cnt FROM teacher_class WHERE teacher_id = ?", "i", [$user_id]);
                $active_marks = dbFetchOne($conn, "SELECT COUNT(*) as cnt FROM marks WHERE teacher_id = ? AND is_deleted = 0", "i", [$user_id]);
                
                if ($active_assignments && $active_assignments['cnt'] > 0) {
                    $error = "ይህ ተጠቃሚ ለክፍሎች ተምድቧል! መጀመሪያ ምደባዎቹን ያስወግዱ። (User has active class assignments!)";
                } elseif ($active_marks && $active_marks['cnt'] > 0) {
                    $error = "ይህ ተጠቃሚ ምልክቶች አስገብቷል! ሊሰረዝ አይችልም። (User has entered marks data!)";
                } else {
                    $deleted = dbExecute($conn, "DELETE FROM users WHERE id = ?", "i", [$user_id]);
                    if ($deleted) {
                        auditLog($conn, 'user_deleted', 'users', $user_id, null);
                        $message = "ተጠቃሚ ተሰርዟል!";
                    } else {
                        $error = "ስህተት ተከስቷል!";
                    }
                }
            }
        }
        
        if (isset($_POST['reset_password'])) {
            $user_id = intval($_POST['user_id'] ?? 0);
            $new_password = hashPassword('123');
            
            if ($user_id > 0) {
                $reset = dbExecute(
                    $conn,
                    "UPDATE users SET password = ?, first_login = 1 WHERE id = ?",
                    "si",
                    [$new_password, $user_id]
                );
                if ($reset) {
                    $message = "የይለፍ ቃል ወደ 123 ተመልሷል!";
                } else {
                    $error = "ስህተት ተከስቷል!";
                }
            }
        }
    }
}

// Get all users
$users_query = "SELECT * FROM users ORDER BY role, name";
$users_res = mysqli_query($conn, $users_query);
$users_list = [];
$admin_count = 0;
$teacher_count = 0;
$submitter_count = 0;

if ($users_res) {
    while ($u = mysqli_fetch_assoc($users_res)) {
        $users_list[] = $u;
        if ($u['role'] === 'admin') $admin_count++;
        elseif ($u['role'] === 'teacher') $teacher_count++;
        elseif ($u['role'] === 'attendance_submitter') $submitter_count++;
    }
}
$total_users = count($users_list);

$nav_active = 'manage_users';
?>
<!DOCTYPE html>
<html lang="am">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ተጠቃሚዎች አስተዳደር | አጸደ ትጉሃን</title>
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
            --purple: #8B5CF6;
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

        /* Direct Dark Mode Overrides for User Management Components */
        html.dark-mode table.users-table th,
        body.dark-mode table.users-table th,
        [data-theme="dark"] table.users-table th {
            background-color: #0F172A !important;
            color: #FCD34D !important;
            border-bottom: 2px solid #334155 !important;
        }

        html.dark-mode table.users-table td,
        body.dark-mode table.users-table td,
        [data-theme="dark"] table.users-table td {
            background-color: #1E293B !important;
            color: #E2E8F0 !important;
            border-bottom: 1px solid #334155 !important;
        }

        html.dark-mode table.users-table tbody tr:hover td,
        body.dark-mode table.users-table tbody tr:hover td,
        [data-theme="dark"] table.users-table tbody tr:hover td {
            background-color: #26354A !important;
        }

        html.dark-mode .user-name-text,
        body.dark-mode .user-name-text,
        [data-theme="dark"] .user-name-text {
            color: #F1F5F9 !important;
        }

        html.dark-mode .role-badge.admin,
        body.dark-mode .role-badge.admin,
        [data-theme="dark"] .role-badge.admin {
            background-color: #3B0764 !important;
            color: #D8B4FE !important;
            border: 1px solid #7E22CE !important;
        }

        html.dark-mode .role-badge.teacher,
        body.dark-mode .role-badge.teacher,
        [data-theme="dark"] .role-badge.teacher {
            background-color: #1E3A8A !important;
            color: #93C5FD !important;
            border: 1px solid #3B82F6 !important;
        }

        html.dark-mode .role-badge.submitter,
        body.dark-mode .role-badge.submitter,
        [data-theme="dark"] .role-badge.submitter {
            background-color: #064E3B !important;
            color: #6EE7B7 !important;
            border: 1px solid #059669 !important;
        }

        html.dark-mode .btn-u-edit,
        body.dark-mode .btn-u-edit,
        [data-theme="dark"] .btn-u-edit {
            background-color: #3B2A0F !important;
            color: #FCD34D !important;
            border: 1px solid #78350F !important;
        }

        html.dark-mode .btn-u-edit:hover,
        body.dark-mode .btn-u-edit:hover,
        [data-theme="dark"] .btn-u-edit:hover {
            background-color: #4D3814 !important;
        }

        html.dark-mode .btn-u-reset,
        body.dark-mode .btn-u-reset,
        [data-theme="dark"] .btn-u-reset {
            background-color: #1E1B4B !important;
            color: #A5B4FC !important;
            border: 1px solid #3730A3 !important;
        }

        html.dark-mode .btn-u-reset:hover,
        body.dark-mode .btn-u-reset:hover,
        [data-theme="dark"] .btn-u-reset:hover {
            background-color: #2D2766 !important;
        }

        html.dark-mode .btn-u-del,
        body.dark-mode .btn-u-del,
        [data-theme="dark"] .btn-u-del {
            background-color: #3B1212 !important;
            color: #FCA5A5 !important;
            border: 1px solid #991B1B !important;
        }

        html.dark-mode .btn-u-del:hover,
        body.dark-mode .btn-u-del:hover,
        [data-theme="dark"] .btn-u-del:hover {
            background-color: #501A1A !important;
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
            content: '👤';
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
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
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
            gap: 14px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.04);
        }
        .stat-icon {
            width: 46px;
            height: 46px;
            border-radius: 12px;
            background: var(--gold-pale);
            color: var(--brown-dark);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 22px;
            flex-shrink: 0;
        }
        .stat-data .stat-val {
            font-size: 22px;
            font-weight: 800;
            color: var(--brown-dark);
        }
        .stat-data .stat-lbl {
            font-size: 11.5px;
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

        /* Filters Toolbar */
        .filter-toolbar {
            display: grid;
            grid-template-columns: 1fr;
            gap: 14px;
            margin-bottom: 20px;
        }

        /* Table */
        .table-responsive {
            width: 100%;
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }
        table.users-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13.5px;
            text-align: left;
        }
        table.users-table th {
            background: #F9FAFB;
            color: var(--brown-dark);
            font-weight: 700;
            padding: 14px 16px;
            border-bottom: 2px solid var(--border-color);
            white-space: nowrap;
        }
        table.users-table td {
            padding: 14px 16px;
            border-bottom: 1px solid #F3F4F6;
            vertical-align: middle;
        }
        table.users-table tbody tr:hover {
            background: rgba(255, 215, 0, 0.03);
        }

        /* User Chip */
        .user-chip {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .user-avatar {
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
        .user-name-text {
            font-weight: 700;
            color: var(--text-main);
        }

        /* Role Badges */
        .role-badge {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 700;
            white-space: nowrap;
        }
        .role-badge.admin { background: #F3E8FF; color: #6B21A8; }
        .role-badge.teacher { background: #EFF6FF; color: #1D4ED8; }
        .role-badge.submitter { background: #ECFDF5; color: #047857; }

        /* Actions */
        .actions-group {
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .btn-u-edit {
            background: #FEF3C7;
            color: #B45309;
            border: none;
            padding: 6px 10px;
            border-radius: 8px;
            font-weight: 700;
            font-size: 12px;
            cursor: pointer;
        }
        .btn-u-edit:hover { background: #FDE68A; }

        .btn-u-reset {
            background: #E0E7FF;
            color: #3730A3;
            border: none;
            padding: 6px 10px;
            border-radius: 8px;
            font-weight: 700;
            font-size: 12px;
            cursor: pointer;
        }
        .btn-u-reset:hover { background: #C7D2FE; }

        .btn-u-del {
            background: #FEE2E2;
            color: #DC2626;
            border: none;
            padding: 6px 9px;
            border-radius: 8px;
            font-weight: 700;
            font-size: 12px;
            cursor: pointer;
        }
        .btn-u-del:hover { background: #FCA5A5; }

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

        @media (max-width: 768px) {
            .main-container { padding: 0 10px 40px; margin: 12px auto; }
            .page-header-card { padding: 18px 16px; }
            .content-card { padding: 16px 14px; border-radius: 14px; }
            .form-grid { grid-template-columns: 1fr; }
            .filter-toolbar { grid-template-columns: 1fr; }
            .btn-primary-action { width: 100%; justify-content: center; min-height: 44px; }
        }
    </style>
</head>
<body>
    <?php include 'mobile_nav.php'; ?>

    <div class="main-container">
        <!-- Header -->
        <div class="page-header-card">
            <div class="header-info">
                <h1>👥 የተጠቃሚዎች መለያ አስተዳደር</h1>
                <p>የሲስተሙን ተጠቃሚዎች (የትምህርት ክፍል፣ መምህራን እና የክፍል ጸሐፊዎች) መለያዎችን ያስተዳድሩ።</p>
            </div>
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
                <div class="stat-icon">👥</div>
                <div class="stat-data">
                    <div class="stat-val"><?php echo $total_users; ?></div>
                    <div class="stat-lbl">ጠቅላላ ተጠቃሚዎች</div>
                </div>
            </div>
            <div class="stat-box">
                <div class="stat-icon" style="background:#F3E8FF; color:#7C3AED;">👑</div>
                <div class="stat-data">
                    <div class="stat-val" style="color:#7C3AED;"><?php echo $admin_count; ?></div>
                    <div class="stat-lbl">ትምህርት ክፍል</div>
                </div>
            </div>
            <div class="stat-box">
                <div class="stat-icon" style="background:#EFF6FF; color:#2563EB;">👨‍🏫</div>
                <div class="stat-data">
                    <div class="stat-val" style="color:#2563EB;"><?php echo $teacher_count; ?></div>
                    <div class="stat-lbl">መምህራን</div>
                </div>
            </div>
            <div class="stat-box">
                <div class="stat-icon" style="background:#ECFDF5; color:#059669;">✍️</div>
                <div class="stat-data">
                    <div class="stat-val" style="color:#059669;"><?php echo $submitter_count; ?></div>
                    <div class="stat-lbl">የክፍል ጸሐፊዎች</div>
                </div>
            </div>
        </div>

        <!-- Add User Form -->
        <div class="content-card">
            <div class="content-card-header">
                <h2><span>➕</span> አዲስ ተጠቃሚ መመዝገቢያ</h2>
            </div>
            <form method="POST">
                <?php echo csrfField(); ?>
                <div class="form-grid">
                    <div class="form-group">
                        <label>ሙሉ ስም <span style="color: var(--error);">*</span></label>
                        <input type="text" name="name" class="form-control" required placeholder="የተጠቃሚው ሙሉ ስም">
                    </div>
                    <div class="form-group">
                        <label>የተጠቃሚ ስም (Username) <span style="color: var(--error);">*</span></label>
                        <input type="text" name="username" class="form-control" required placeholder="ለመግቢያ የሚሆን ስም">
                    </div>
                    <div class="form-group">
                        <label>ስልክ ቁጥር</label>
                        <input type="text" name="phone" class="form-control" placeholder="09...">
                    </div>
                    <div class="form-group">
                        <label>የስራ ድርሻ (Role) <span style="color: var(--error);">*</span></label>
                        <select name="role" class="form-control" required>
                            <option value="teacher">👨‍🏫 መምህር</option>
                            <option value="attendance_submitter">✍️ የክፍል ጸሐፊ (Attendance Submitter)</option>
                            <option value="admin">👑 ትምህርት ክፍል (Admin)</option>
                        </select>
                    </div>
                </div>
                <button type="submit" name="add_user" class="btn-primary-action">
                    ➕ ተጠቃሚ መዝግብ (ነባሪ የይለፍ ቃል: 123)
                </button>
            </form>
        </div>

        <!-- Users Directory -->
        <div class="content-card">
            <div class="content-card-header">
                <h2><span>📋</span> የተጠቃሚዎች ዝርዝር</h2>
                <span class="header-badge"><?php echo $total_users; ?> ተጠቃሚዎች</span>
            </div>

            <div class="filter-toolbar">
                <input type="text" id="userSearch" class="form-control" placeholder="🔍 የተጠቃሚ ስም፣ username ወይም ስልክ ይፈልጉ..." onkeyup="filterUserList()">
                <select id="roleFilter" class="form-control" onchange="filterUserList()">
                    <option value="">👤 ሁሉም የስራ ድርሻዎች</option>
                    <option value="admin">👑 ትምህርት ክፍል</option>
                    <option value="teacher">👨‍🏫 መምህር</option>
                    <option value="attendance_submitter">✍️ የክፍል ጸሐፊ</option>
                </select>
            </div>

            <div class="table-responsive">
                <table class="users-table">
                    <thead>
                        <tr>
                            <th>ተጠቃሚ</th>
                            <th>የተጠቃሚ ስም</th>
                            <th>የስራ ድርሻ</th>
                            <th>ስልክ ቁጥር</th>
                            <th>የተመዘገበበት</th>
                            <th>ድርጊቶች</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($users_list as $u): 
                            $uRole = $u['role'];
                            $roleLabel = ($uRole === 'admin') ? '👑 ትምህርት ክፍል' : (($uRole === 'attendance_submitter') ? '✍️ የክፍል ጸሐፊ' : '👨‍🏫 መምህር');
                            $roleClass = ($uRole === 'admin') ? 'admin' : (($uRole === 'attendance_submitter') ? 'submitter' : 'teacher');
                            $initial = mb_substr($u['name'], 0, 1, 'UTF-8');
                            $isSelf = intval($u['id']) === intval($_SESSION['user_id']);
                        ?>
                        <tr class="user-row" data-search="<?php echo htmlspecialchars(strtolower($u['name'] . ' ' . $u['username'] . ' ' . ($u['phone'] ?? ''))); ?>" data-role="<?php echo $uRole; ?>">
                            <td>
                                <div class="user-chip">
                                    <div class="user-avatar"><?php echo htmlspecialchars($initial); ?></div>
                                    <div>
                                        <div class="user-name-text"><?php echo htmlspecialchars($u['name']); ?></div>
                                        <?php if($isSelf): ?>
                                            <span style="font-size:11px; color:#10B981; font-weight:700;">(የእርስዎ አካውንት)</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <code style="background:#F3F4F6; padding:3px 8px; border-radius:6px; font-size:12.5px;">@<?php echo htmlspecialchars($u['username']); ?></code>
                            </td>
                            <td>
                                <span class="role-badge <?php echo $roleClass; ?>">
                                    <?php echo $roleLabel; ?>
                                </span>
                            </td>
                            <td>
                                <?php if (!empty($u['phone'])): ?>
                                    <a href="tel:<?php echo htmlspecialchars($u['phone']); ?>" style="color:#2563EB; text-decoration:none; font-family:monospace; font-weight:600;">
                                        📞 <?php echo htmlspecialchars($u['phone']); ?>
                                    </a>
                                <?php else: ?>
                                    <span style="color:var(--text-muted);">—</span>
                                <?php endif; ?>
                            </td>
                            <td style="font-size:12.5px; color:var(--text-muted); white-space:nowrap;">
                                <?php echo date('Y-m-d', strtotime($u['created_at'])); ?>
                            </td>
                            <td>
                                <div class="actions-group">
                                    <button onclick="editUser(<?php echo $u['id']; ?>, '<?php echo htmlspecialchars(addslashes($u['name']), ENT_QUOTES); ?>', '<?php echo htmlspecialchars(addslashes($u['username']), ENT_QUOTES); ?>', '<?php echo htmlspecialchars(addslashes($u['phone'] ?? ''), ENT_QUOTES); ?>', '<?php echo $u['role']; ?>')" 
                                            class="btn-u-edit" title="መረጃ አርትዕ">
                                        ✏️ አርትዕ
                                    </button>
                                    
                                    <form method="POST" style="display:inline;" onsubmit="return confirm('የ[<?php echo htmlspecialchars(addslashes($u['name']), ENT_QUOTES); ?>] የይለፍ ቃል ወደ 123 መመለስ እርግጠኛ ነዎት?')">
                                        <?php echo csrfField(); ?>
                                        <input type="hidden" name="user_id" value="<?php echo $u['id']; ?>">
                                        <button type="submit" name="reset_password" class="btn-u-reset" title="የይለፍ ቃል ወደ 123 መልስ">
                                            🔄 123
                                        </button>
                                    </form>

                                    <?php if (!$isSelf): ?>
                                    <form method="POST" style="display:inline;" onsubmit="return confirm('ተጠቃሚ [<?php echo htmlspecialchars(addslashes($u['name']), ENT_QUOTES); ?>] መሰረዝ እርግጠኛ ነዎት?')">
                                        <?php echo csrfField(); ?>
                                        <input type="hidden" name="user_id" value="<?php echo $u['id']; ?>">
                                        <button type="submit" name="delete_user" class="btn-u-del" title="ተጠቃሚ ሰርዝ">
                                            🗑️
                                        </button>
                                    </form>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Edit Modal -->
    <div id="editModal" class="modal-overlay">
        <div class="modal-card">
            <div class="modal-header">
                <h3>✏️ የተጠቃሚ መረጃ ማስተካከያ</h3>
                <button class="modal-close" onclick="closeModal()">&times;</button>
            </div>
            <form method="POST" id="editForm">
                <?php echo csrfField(); ?>
                <input type="hidden" name="user_id" id="edit_id">
                <div class="form-group" style="margin-bottom:14px;">
                    <label>ሙሉ ስም <span style="color:var(--error);">*</span></label>
                    <input type="text" name="name" id="edit_name" class="form-control" required>
                </div>
                <div class="form-group" style="margin-bottom:14px;">
                    <label>የተጠቃሚ ስም (Username) <span style="color:var(--error);">*</span></label>
                    <input type="text" name="username" id="edit_username" class="form-control" required>
                </div>
                <div class="form-group" style="margin-bottom:14px;">
                    <label>ስልክ ቁጥር</label>
                    <input type="text" name="phone" id="edit_phone" class="form-control">
                </div>
                <div class="form-group" style="margin-bottom:20px;">
                    <label>የስራ ድርሻ (Role) <span style="color:var(--error);">*</span></label>
                    <select name="role" id="edit_role" class="form-control" required>
                        <option value="teacher">👨‍🏫 መምህር</option>
                        <option value="attendance_submitter">✍️ የክፍል ጸሐፊ</option>
                        <option value="admin">👑 ትምህርት ክፍል (Admin)</option>
                    </select>
                </div>
                <button type="submit" name="edit_user" class="btn-primary-action" style="width:100%; justify-content:center;">
                    💾 ለውጦችን አስቀምጥ
                </button>
            </form>
        </div>
    </div>

    <script>
        function editUser(id, name, username, phone, role) {
            document.getElementById('edit_id').value = id;
            document.getElementById('edit_name').value = name;
            document.getElementById('edit_username').value = username;
            document.getElementById('edit_phone').value = phone || '';
            document.getElementById('edit_role').value = role;
            document.getElementById('editModal').style.display = 'flex';
        }

        function closeModal() {
            document.getElementById('editModal').style.display = 'none';
        }

        function filterUserList() {
            var q = document.getElementById('userSearch').value.toLowerCase();
            var r = document.getElementById('roleFilter').value;
            var rows = document.querySelectorAll('.user-row');

            rows.forEach(function(row) {
                var searchData = row.getAttribute('data-search');
                var roleData = row.getAttribute('data-role');

                var matchQ = (!q || searchData.indexOf(q) > -1);
                var matchR = (!r || roleData === r);

                if (matchQ && matchR) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
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