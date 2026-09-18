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
        if (isset($_POST['add_teacher'])) {
            $name = trim($_POST['name'] ?? '');
            $username = trim($_POST['username'] ?? '');
            $phone = trim($_POST['phone'] ?? '');
            $password = hashPassword('123');
            
            if (!empty($name) && !empty($username)) {
                // Check if username exists
                $check_username = dbFetchOne($conn, "SELECT id FROM users WHERE username = ?", "s", [$username]);
                if ($check_username) {
                    $error = "ይህ የተጠቃሚ ስም ቀድሞውኑ አለ! (Username already exists!)";
                } else {
                    $saved = dbExecute(
                        $conn,
                        "INSERT INTO users (name, username, phone, role, password, first_login) 
                         VALUES (?, ?, ?, 'teacher', ?, 1)",
                        "ssss",
                        [$name, $username, $phone, $password]
                    );
                    if ($saved) {
                        $message = "መምህር በትክክል ተመዝግቧል! የተጠቃሚ ስም: $username | የይለፍ ቃል: 123";
                    } else {
                        $error = "ስህተት ተከስቷል!";
                    }
                }
            } else {
                $error = "እባክዎ ስም እና የተጠቃሚ ስም ያስገቡ!";
            }
        }

        if (isset($_POST['import_excel_teachers'])) {
            $uploaded_path = null;
            if (!empty($_FILES['excel_file']['tmp_name']) && is_uploaded_file($_FILES['excel_file']['tmp_name'])) {
                $ext = strtolower(pathinfo($_FILES['excel_file']['name'], PATHINFO_EXTENSION));
                if (in_array($ext, ['xlsx', 'xls', 'csv'])) {
                    $target = __DIR__ . '/uploads/teachers_import_' . time() . '.' . $ext;
                    if (move_uploaded_file($_FILES['excel_file']['tmp_name'], $target)) {
                        $uploaded_path = $target;
                    }
                }
            } elseif (file_exists(__DIR__ . '/መምህራን.xlsx')) {
                $uploaded_path = __DIR__ . '/መምህራን.xlsx';
            }
            
            if ($uploaded_path) {
                $pyScript = escapeshellarg('C:/Users/Tesfa/.gemini/antigravity/brain/2fa187f5-ad49-481e-ac72-bde341e622cf/scratch/import_teachers.py');
                $filePathArg = escapeshellarg($uploaded_path);
                $cmd = "python $pyScript $filePathArg";
                $output = shell_exec($cmd);
                $message = "የመምህራን መረጃ ከ Excel ፋይል በትክክል ተጭኗል!";
            } else {
                $error = "እባክዎ ትክክለኛ የ Excel (.xlsx) ፋይል ይምረጡ!";
            }
        }
        
        if (isset($_POST['edit_teacher'])) {
            $teacher_id = intval($_POST['teacher_id'] ?? 0);
            $name = trim($_POST['name'] ?? '');
            $username = trim($_POST['username'] ?? '');
            $phone = trim($_POST['phone'] ?? '');
            
            if ($teacher_id > 0 && !empty($name) && !empty($username)) {
                // Check username uniqueness (excluding current teacher)
                $check = dbFetchOne($conn, "SELECT id FROM users WHERE username = ? AND id != ?", "si", [$username, $teacher_id]);
                if ($check) {
                    $error = "ይህ የተጠቃሚ ስም በሌላ ተጠቃሚ ተይዟል!";
                } else {
                    $updated = dbExecute(
                        $conn,
                        "UPDATE users SET name = ?, username = ?, phone = ? WHERE id = ? AND role = 'teacher'",
                        "sssi",
                        [$name, $username, $phone, $teacher_id]
                    );
                    if ($updated) {
                        $message = "የመምህር መረጃ ተሻሽሏል!";
                    } else {
                        $error = "ስህተት ተከስቷል!";
                    }
                }
            }
        }
        
        if (isset($_POST['delete_teacher'])) {
            $teacher_id = intval($_POST['teacher_id'] ?? 0);
            
            if ($teacher_id > 0) {
                mysqli_begin_transaction($conn);
                try {
                    dbExecute($conn, "DELETE FROM marks WHERE teacher_id = ?", "i", [$teacher_id]);
                    dbExecute($conn, "DELETE FROM teacher_class WHERE teacher_id = ?", "i", [$teacher_id]);
                    dbExecute($conn, "DELETE FROM users WHERE id = ? AND role = 'teacher'", "i", [$teacher_id]);
                    
                    mysqli_commit($conn);
                    $message = "መምህር በትክክል ተሰርዟል!";
                } catch (Exception $e) {
                    mysqli_rollback($conn);
                    $error = "መምህሩን መሰረዝ አልተቻለም!";
                }
            }
        }
        
        if (isset($_POST['reset_password'])) {
            $teacher_id = intval($_POST['teacher_id'] ?? 0);
            $new_password = hashPassword('123');
            
            if ($teacher_id > 0) {
                $reset = dbExecute(
                    $conn,
                    "UPDATE users SET password = ?, first_login = 1 WHERE id = ?",
                    "si",
                    [$new_password, $teacher_id]
                );
                if ($reset) {
                    $message = "የይለፍ ቃል ወደ 123 ተመልሷል!";
                } else {
                    $error = "ስህተት ተከስቷል!";
                }
            }
        }

        if (isset($_POST['approve_profile_request'])) {
            $req_id = intval($_POST['request_id'] ?? 0);
            $req = dbFetchOne($conn, "SELECT r.*, u.name as current_name FROM profile_change_requests r JOIN users u ON r.teacher_id = u.id WHERE r.id = ? AND r.status = 'pending'", "i", [$req_id]);
            if ($req) {
                $upName = $req['requested_name'] ?: $req['current_name'];
                $upPhone = $req['requested_phone'];
                $upPhoto = $req['requested_photo'];
                
                if ($upPhoto) {
                    dbExecute($conn, "UPDATE users SET name = ?, phone = ?, photo = ? WHERE id = ?", "sssi", [$upName, $upPhone, $upPhoto, $req['teacher_id']]);
                } else {
                    dbExecute($conn, "UPDATE users SET name = ?, phone = ? WHERE id = ?", "ssi", [$upName, $upPhone, $req['teacher_id']]);
                }
                
                dbExecute($conn, "UPDATE profile_change_requests SET status = 'approved', reviewed_by = ?, reviewed_at = NOW() WHERE id = ?", "ii", [$_SESSION['user_id'], $req_id]);
                
                createNotification(
                    $conn,
                    "✅ የመረጃ ለውጥ ጥያቄዎ ጸድቋል",
                    "ያቀረቡት የመረጃ ለውጥ ጥያቄ በትምህርት ክፍሉ ተቀባይነት አግኝቶ መረጃዎ ተሻሽሏል።",
                    ['user_id' => $req['teacher_id']],
                    'normal',
                    null,
                    'teacher_profile.php'
                );
                $message = "የመምህር መረጃ ለውጥ ጥያቄ በትክክል ጸድቋል!";
            }
        }
        
        if (isset($_POST['reject_profile_request'])) {
            $req_id = intval($_POST['request_id'] ?? 0);
            $notes = trim($_POST['admin_notes'] ?? '');
            $req = dbFetchOne($conn, "SELECT * FROM profile_change_requests WHERE id = ? AND status = 'pending'", "i", [$req_id]);
            if ($req) {
                dbExecute($conn, "UPDATE profile_change_requests SET status = 'rejected', admin_notes = ?, reviewed_by = ?, reviewed_at = NOW() WHERE id = ?", "sii", [$notes, $_SESSION['user_id'], $req_id]);
                
                createNotification(
                    $conn,
                    "⚠️ የመረጃ ለውጥ ጥያቄዎ ውድቅ ተደርጓል",
                    "ያቀረቡት የመረጃ ለውጥ ጥያቄ ተቀባይነት አላገኘም። " . ($notes ? "ማብራሪያ: $notes" : ""),
                    ['user_id' => $req['teacher_id']],
                    'normal',
                    null,
                    'teacher_profile.php'
                );
                $message = "የመረጃ ለውጥ ጥያቄው ውድቅ ተደርጓል፤ ለመምህሩ ማሳወቂያ ተልኳል!";
            }
        }
    }
}

// Get current academic year
$current_year_query = "SELECT * FROM academic_years WHERE status = 'active' LIMIT 1";
$current_year_result = mysqli_query($conn, $current_year_query);
$current_year = mysqli_fetch_assoc($current_year_result);
$current_ethiopian_year = $current_year ? $current_year['ethiopian_year'] : 2017;

// Get all teachers with username
$teachers_query = "SELECT * FROM users WHERE role='teacher' ORDER BY name";
$teachers = mysqli_query($conn, $teachers_query);
$teachers_count = mysqli_num_rows($teachers);

// Get all assignments with full details for each teacher
$all_assignments_query = "SELECT tc.*, 
                          u.name as teacher_name, 
                          c.name as class_name, 
                          s.name as semester_name,
                          s.ethiopian_year,
                          s.semester_number,
                          s.status as semester_status
                          FROM teacher_class tc
                          JOIN users u ON tc.teacher_id = u.id
                          JOIN classes c ON tc.class_id = c.id
                          JOIN semesters s ON tc.semester_id = s.id
                          ORDER BY s.ethiopian_year DESC, s.semester_number DESC, c.name";
$all_assignments = mysqli_query($conn, $all_assignments_query);
$total_assignments_count = mysqli_num_rows($all_assignments);

// Organize assignments by teacher and year
$teacher_history = [];
while($assignment = mysqli_fetch_assoc($all_assignments)) {
    $teacher_id = $assignment['teacher_id'];
    $year = $assignment['ethiopian_year'];
    
    if(!isset($teacher_history[$teacher_id])) {
        $teacher_history[$teacher_id] = [];
    }
    if(!isset($teacher_history[$teacher_id][$year])) {
        $teacher_history[$teacher_id][$year] = [
            'semester1' => [],
            'semester2' => []
        ];
    }
    
    $semester_key = 'semester' . $assignment['semester_number'];
    $teacher_history[$teacher_id][$year][$semester_key][] = $assignment;
}

// Get pending profile change requests
$pending_requests = dbFetchAll(
    $conn,
    "SELECT r.*, u.name as current_name, u.phone as current_phone, u.photo as current_photo, u.username
     FROM profile_change_requests r
     JOIN users u ON r.teacher_id = u.id
     WHERE r.status = 'pending'
     ORDER BY r.created_at ASC"
);

$nav_active = 'manage_teachers';
?>
<!DOCTYPE html>
<html lang="am">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>መምህራን አስተዳደር | አጸደ ትጉሃን</title>
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

        /* Direct Dark Mode Overrides for Teacher Components */
        html.dark-mode .teacher-card,
        body.dark-mode .teacher-card,
        [data-theme="dark"] .teacher-card {
            background-color: #1E293B !important;
            border-color: #334155 !important;
            color: #F1F5F9 !important;
        }

        html.dark-mode .teacher-card:hover,
        body.dark-mode .teacher-card:hover,
        [data-theme="dark"] .teacher-card:hover {
            border-color: #F59E0B !important;
        }

        html.dark-mode .teacher-meta-head h3,
        body.dark-mode .teacher-meta-head h3,
        [data-theme="dark"] .teacher-meta-head h3 {
            color: #FCD34D !important;
        }

        html.dark-mode .teacher-history-box,
        body.dark-mode .teacher-history-box,
        [data-theme="dark"] .teacher-history-box {
            background-color: #162032 !important;
            border: 1px solid #334155 !important;
            color: #CBD5E1 !important;
        }

        html.dark-mode .history-toggle-title,
        body.dark-mode .history-toggle-title,
        [data-theme="dark"] .history-toggle-title {
            color: #FCD34D !important;
        }

        html.dark-mode .history-content,
        body.dark-mode .history-content,
        [data-theme="dark"] .history-content {
            border-top: 1px dashed #334155 !important;
        }

        html.dark-mode .history-content strong,
        body.dark-mode .history-content strong,
        [data-theme="dark"] .history-content strong {
            color: #FCD34D !important;
        }

        html.dark-mode .class-chip,
        body.dark-mode .class-chip,
        [data-theme="dark"] .class-chip {
            background-color: #0F172A !important;
            border: 1px solid #334155 !important;
            color: #F1F5F9 !important;
        }

        html.dark-mode .class-chip.locked,
        body.dark-mode .class-chip.locked,
        [data-theme="dark"] .class-chip.locked {
            background-color: #3B1212 !important;
            border-color: #DC2626 !important;
            color: #FCA5A5 !important;
        }

        html.dark-mode .teacher-user-tag,
        body.dark-mode .teacher-user-tag,
        [data-theme="dark"] .teacher-user-tag {
            background-color: #0F172A !important;
            border: 1px solid #334155 !important;
            color: #94A3B8 !important;
        }

        html.dark-mode .teacher-phone,
        body.dark-mode .teacher-phone,
        [data-theme="dark"] .teacher-phone {
            color: #60A5FA !important;
        }

        html.dark-mode .requests-card,
        body.dark-mode .requests-card,
        [data-theme="dark"] .requests-card {
            background-color: #1E293B !important;
            border: 2px solid #F59E0B !important;
        }

        html.dark-mode .requests-card-header,
        body.dark-mode .requests-card-header,
        [data-theme="dark"] .requests-card-header {
            color: #FCD34D !important;
        }

        html.dark-mode .req-item,
        body.dark-mode .req-item,
        [data-theme="dark"] .req-item {
            background-color: #0F172A !important;
            border: 1px solid #334155 !important;
            color: #F1F5F9 !important;
        }

        html.dark-mode .req-info,
        body.dark-mode .req-info,
        [data-theme="dark"] .req-info {
            color: #CBD5E1 !important;
        }

        html.dark-mode .req-info strong,
        body.dark-mode .req-info strong,
        [data-theme="dark"] .req-info strong {
            color: #F8FAFC !important;
        }

        html.dark-mode .req-info code,
        body.dark-mode .req-info code,
        [data-theme="dark"] .req-info code {
            background-color: #1E293B !important;
            color: #FCD34D !important;
            border: 1px solid #334155 !important;
        }

        html.dark-mode .req-info small,
        body.dark-mode .req-info small,
        [data-theme="dark"] .req-info small {
            color: #94A3B8 !important;
        }

        html.dark-mode .btn-profile-link,
        body.dark-mode .btn-profile-link,
        [data-theme="dark"] .btn-profile-link {
            background-color: #243247 !important;
            color: #FCD34D !important;
            border: 1px solid #334155 !important;
        }

        html.dark-mode .btn-profile-link:hover,
        body.dark-mode .btn-profile-link:hover,
        [data-theme="dark"] .btn-profile-link:hover {
            background-color: #334155 !important;
            color: #FFFFFF !important;
        }

        html.dark-mode .btn-t-edit,
        body.dark-mode .btn-t-edit,
        [data-theme="dark"] .btn-t-edit {
            background-color: #3B2A0F !important;
            color: #FCD34D !important;
            border: 1px solid #78350F !important;
        }

        html.dark-mode .btn-t-edit:hover,
        body.dark-mode .btn-t-edit:hover,
        [data-theme="dark"] .btn-t-edit:hover {
            background-color: #4D3814 !important;
        }

        html.dark-mode .btn-t-reset,
        body.dark-mode .btn-t-reset,
        [data-theme="dark"] .btn-t-reset {
            background-color: #1E1B4B !important;
            color: #A5B4FC !important;
            border: 1px solid #3730A3 !important;
        }

        html.dark-mode .btn-t-reset:hover,
        body.dark-mode .btn-t-reset:hover,
        [data-theme="dark"] .btn-t-reset:hover {
            background-color: #2D2766 !important;
        }

        html.dark-mode .btn-t-del,
        body.dark-mode .btn-t-del,
        [data-theme="dark"] .btn-t-del {
            background-color: #3B1212 !important;
            color: #FCA5A5 !important;
            border: 1px solid #991B1B !important;
        }

        html.dark-mode .btn-t-del:hover,
        body.dark-mode .btn-t-del:hover,
        [data-theme="dark"] .btn-t-del:hover {
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
            content: '👨‍🏫';
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

        /* Pending Requests Box */
        .requests-card {
            background: #FEF3C7;
            border: 2px solid #F59E0B;
            border-radius: 16px;
            padding: 20px;
            margin-bottom: 24px;
        }
        .requests-card-header {
            display: flex;
            align-items: center;
            gap: 10px;
            color: #92400E;
            font-size: 16px;
            font-weight: 800;
            margin-bottom: 14px;
        }
        .req-item {
            background: white;
            border-radius: 12px;
            padding: 16px;
            margin-bottom: 12px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 14px;
            border: 1px solid #FDE68A;
        }
        .req-item:last-child { margin-bottom: 0; }
        .req-info { font-size: 13.5px; color: #374151; }
        .req-info strong { color: #1F2937; }
        .req-actions { display: flex; gap: 8px; }

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
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
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

        /* Teacher Cards Grid (from Hosted Code) */
        .teachers-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(380px, 1fr));
            gap: 25px;
            margin-top: 20px;
        }

        .teacher-card {
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

        .teacher-card:hover {
            transform: translateY(-3px);
            border-color: var(--gold-primary);
            box-shadow: 0 10px 25px rgba(0,0,0,0.12);
        }

        .teacher-header {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 15px;
            padding-bottom: 15px;
            border-bottom: 2px solid var(--gold-pale);
        }

        .teacher-avatar {
            width: 60px;
            height: 60px;
            background: linear-gradient(135deg, var(--gold-primary), var(--gold-dark));
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            font-weight: bold;
            color: var(--brown-dark);
            border: 3px solid white;
            box-shadow: 0 3px 10px rgba(0,0,0,0.1);
            overflow: hidden;
            flex-shrink: 0;
        }
        
        .teacher-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .teacher-title {
            flex: 1;
            min-width: 0;
        }

        .teacher-name {
            font-size: 18px;
            font-weight: bold;
            color: var(--brown-dark);
            margin-bottom: 3px;
            text-decoration: none;
            cursor: pointer;
            transition: color 0.2s;
            display: inline-block;
        }
        
        .teacher-name:hover {
            color: var(--gold-dark);
            text-decoration: underline;
        }

        .teacher-username {
            color: #8B5CF6;
            font-size: 12px;
            font-weight: 600;
            background: #F3F0FF;
            padding: 2px 10px;
            border-radius: 15px;
            display: inline-block;
            margin-bottom: 4px;
        }

        .teacher-phone {
            color: #666;
            font-size: 13px;
            display: flex;
            align-items: center;
            gap: 5px;
            text-decoration: none;
        }

        .teacher-status {
            display: inline-block;
            padding: 3px 10px;
            border-radius: 15px;
            font-size: 11px;
            font-weight: 600;
            margin-left: 8px;
            vertical-align: middle;
        }

        .status-new {
            background: #FEF3C7;
            color: #D97706;
        }

        .status-active {
            background: #D1FAE5;
            color: #059669;
        }

        .teacher-actions {
            display: flex;
            gap: 8px;
            margin-top: 15px;
            flex-wrap: wrap;
            padding-top: 12px;
            border-top: 1px dashed rgba(0,0,0,0.08);
        }

        .btn-profile {
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
        .btn-profile:hover {
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

        .btn-reset {
            background: #F59E0B;
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
        .btn-reset:hover {
            background: #D97706;
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

        /* Timeline Styles from Hosted Code */
        .timeline {
            margin-top: 15px;
        }

        .year-group {
            margin-bottom: 15px;
            border-left: 3px solid var(--gold-primary);
            padding-left: 12px;
        }

        .year-header {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 8px;
            cursor: pointer;
            padding: 8px 10px;
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
            padding: 4px 12px;
            border-radius: 20px;
            font-weight: bold;
            font-size: 13px;
        }

        .year-status {
            font-size: 12px;
            color: #059669;
            font-weight: 700;
        }

        .toggle-icon {
            margin-left: auto;
            font-size: 14px;
            color: var(--gold-dark);
        }

        .semester-row {
            display: flex;
            gap: 12px;
            margin-bottom: 10px;
            padding: 8px 10px;
            background: #F8F9FA;
            border-radius: 8px;
            align-items: center;
        }

        .semester-badge {
            min-width: 85px;
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 11.5px;
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

        .classes-list {
            flex: 1;
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
        }

        .class-tag {
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
        }

        .class-tag.locked {
            background: #FEE2E2;
            border-color: #EF4444;
            color: #DC2626;
        }

        .no-data {
            color: #9CA3AF;
            font-style: italic;
            padding: 8px;
            font-size: 12.5px;
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

        @media (max-width: 768px) {
            .main-container { padding: 0 10px 40px; margin: 12px auto; }
            .page-header-card { padding: 18px 16px; }
            .content-card { padding: 16px 14px; border-radius: 14px; }
            .teachers-grid { grid-template-columns: 1fr; }
            .btn-primary-action { width: 100%; justify-content: center; min-height: 44px; }
        }
    </style>
</head>
<body>
    <?php include 'mobile_nav.php'; ?>

    <div class="main-container">
        <!-- Page Header -->
        <div class="page-header-card">
            <div class="header-info">
                <h1>👨‍🏫 የመምህራን አስተዳደር</h1>
                <p>አዳዲስ መምህራንን ይመዝግቡ፣ የተመደቡባቸውን ክፍሎች ይከታተሉ እና የይለፍ ቃሎችን ያስተዳድሩ።</p>
            </div>
        </div>

        <?php if($message): ?>
        <div class="message success">✅ <?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?></div>
        <?php endif; ?>

        <?php if($error): ?>
        <div class="message error">⚠️ <?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
        <?php endif; ?>

        <!-- Pending Profile Requests Section (If Any) -->
        <?php if (!empty($pending_requests)): ?>
        <div class="requests-card">
            <div class="requests-card-header">
                <span>🔔</span> ያልጸደቁ የመረጃ ለውጥ ጥያቄዎች (<?php echo count($pending_requests); ?>)
            </div>
            <?php foreach ($pending_requests as $req): ?>
            <div class="req-item">
                <div class="req-info">
                    <strong><?php echo htmlspecialchars($req['current_name']); ?></strong> (@<?php echo htmlspecialchars($req['username']); ?>)<br>
                    <?php if ($req['requested_name']): ?>
                        ስም መቀየር፦ <code><?php echo htmlspecialchars($req['requested_name']); ?></code><br>
                    <?php endif; ?>
                    <?php if ($req['requested_phone']): ?>
                        ስልክ መቀየር፦ <code><?php echo htmlspecialchars($req['requested_phone']); ?></code><br>
                    <?php endif; ?>
                    <?php if ($req['requested_photo']): ?>
                        ፎቶ መቀየር፦ <a href="<?php echo htmlspecialchars($req['requested_photo']); ?>" target="_blank">ፎቶውን ይመልከቱ</a><br>
                    <?php endif; ?>
                    <small style="color:#666;">የቀረበበት ቀን፦ <?php echo date('Y-m-d H:i', strtotime($req['created_at'])); ?></small>
                </div>
                <div class="req-actions">
                    <form method="POST" style="display:inline;">
                        <?php echo csrfField(); ?>
                        <input type="hidden" name="request_id" value="<?php echo $req['id']; ?>">
                        <button type="submit" name="approve_profile_request" class="btn-primary-action" style="padding:8px 14px; font-size:12.5px; background:#10B981; color:white;">
                            ✅ አጽድቅ
                        </button>
                    </form>
                    <button onclick="openRejectModal(<?php echo $req['id']; ?>, '<?php echo htmlspecialchars(addslashes($req['current_name'])); ?>')" 
                            style="padding:8px 14px; font-size:12.5px; background:#EF4444; color:white; border:none; border-radius:10px; font-weight:700; cursor:pointer;">
                        ❌ ውድቅ አድርግ
                    </button>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <!-- Stats -->
        <div class="stats-grid">
            <div class="stat-box">
                <div class="stat-icon">👨‍🏫</div>
                <div class="stat-data">
                    <div class="stat-val"><?php echo $teachers_count; ?></div>
                    <div class="stat-lbl">ጠቅላላ መምህራን</div>
                </div>
            </div>
            <div class="stat-box">
                <div class="stat-icon">📚</div>
                <div class="stat-data">
                    <div class="stat-val"><?php echo $total_assignments_count; ?></div>
                    <div class="stat-lbl">የክፍል ምደባዎች</div>
                </div>
            </div>
            <div class="stat-box">
                <div class="stat-icon">🔔</div>
                <div class="stat-data">
                    <div class="stat-val"><?php echo count($pending_requests); ?></div>
                    <div class="stat-lbl">የመረጃ ለውጥ ጥያቄዎች</div>
                </div>
            </div>
        </div>

        <!-- Add Teacher Form -->
        <div class="content-card">
            <div class="content-card-header">
                <h2><span>➕</span> አዲስ መምህር መመዝገቢያ</h2>
            </div>
            <form method="POST">
                <?php echo csrfField(); ?>
                <div class="form-grid">
                    <div class="form-group">
                        <label>ሙሉ ስም <span style="color: var(--error);">*</span></label>
                        <input type="text" name="name" class="form-control" required placeholder="የመምህር ሙሉ ስም">
                    </div>
                    <div class="form-group">
                        <label>የተጠቃሚ ስም (Username) <span style="color: var(--error);">*</span></label>
                        <input type="text" name="username" class="form-control" required placeholder="ለመግቢያ የሚያገለግል ስም">
                    </div>
                    <div class="form-group">
                        <label>ስልክ ቁጥር</label>
                        <input type="text" name="phone" class="form-control" placeholder="09...">
                    </div>
                </div>
                <button type="submit" name="add_teacher" class="btn-primary-action">
                    ➕ መምህር መዝግብ (ነባሪ የይለፍ ቃል: 123)
                </button>
            </form>
        </div>

        <!-- Excel Import Card -->
        <div class="content-card" style="border: 1.5px dashed var(--gold-dark);">
            <div class="content-card-header">
                <h2><span>📊</span> ከመምህራን Excel ፋይል መረጃ መጫኛ (Import Excel)</h2>
                <?php if (file_exists(__DIR__ . '/መምህራን.xlsx')): ?>
                <span class="header-badge" style="background: #DCFCE7; color: #166534;">✅ መምህራን.xlsx ዝግጁ ነው</span>
                <?php endif; ?>
            </div>
            <p style="font-size: 13px; color: var(--text-muted); margin-bottom: 15px;">
                የመምህራን ዝርዝር ያለበትን <code>መምህራን.xlsx</code> ፋይል በቀጥታ መጫን ወይም አዲስ Excel (.xlsx) ፋይል መርጠው በአንድ ጊዜ መምህራንን መመዝገብ ይችላሉ።
            </p>
            <form method="POST" enctype="multipart/form-data" style="display: flex; gap: 12px; align-items: center; flex-wrap: wrap;">
                <?php echo csrfField(); ?>
                <input type="file" name="excel_file" accept=".xlsx,.xls,.csv" class="form-control" style="max-width: 320px;">
                <button type="submit" name="import_excel_teachers" class="btn-primary-action" style="background: #10B981; border: none;">
                    📥 Excel ፋይል አስመጣ (Import)
                </button>
            </form>
        </div>

        <!-- Teachers Grid List -->
        <div class="content-card">
            <div class="content-card-header">
                <h2><span>📋</span> የመምህራን ማውጫ ዝርዝር</h2>
                <span class="header-badge"><?php echo $teachers_count; ?> መምህራን</span>
            </div>

            <!-- Instant Search Box -->
            <div style="margin-bottom: 20px;">
                <input type="text" id="teacherSearch" class="form-control" placeholder="🔍 የመምህር ስም፣ ስልክ ወይም username ይፈልጉ..." onkeyup="filterTeachers()">
            </div>

            <?php if($teachers && mysqli_num_rows($teachers) > 0): ?>
            <div class="teachers-grid" id="teachersListContainer">
                <?php 
                mysqli_data_seek($teachers, 0);
                while($teacher = mysqli_fetch_assoc($teachers)): 
                    $teacher_id = $teacher['id'];
                    $teacher_name = $teacher['name'];
                    $teacher_username = $teacher['username'];
                    $teacher_phone = $teacher['phone'];
                    $teacher_photo = $teacher['photo'] ?: null;
                    $teacher_years = $teacher_history[$teacher_id] ?? [];
                    $has_history = !empty($teacher_years);
                ?>
                <div class="teacher-card" data-search="<?php echo htmlspecialchars(strtolower($teacher_name . ' ' . $teacher_username . ' ' . ($teacher_phone ?? ''))); ?>">
                    <div>
                        <div class="teacher-header">
                            <div class="teacher-avatar">
                                <?php if($teacher_photo && file_exists($teacher_photo)): ?>
                                <img src="<?php echo htmlspecialchars($teacher_photo); ?>" alt="<?php echo htmlspecialchars($teacher_name); ?>" onerror="this.style.display='none'; this.parentElement.innerHTML='<?php echo mb_substr($teacher_name, 0, 1, 'UTF-8'); ?>';">
                                <?php else: ?>
                                <?php echo mb_substr($teacher_name, 0, 1, 'UTF-8'); ?>
                                <?php endif; ?>
                            </div>
                            <div class="teacher-title">
                                <div>
                                    <a href="teacher_profile.php?id=<?php echo $teacher_id; ?>" class="teacher-name">
                                        <?php echo htmlspecialchars($teacher_name); ?>
                                    </a>
                                    <span class="teacher-status <?php echo $teacher['first_login'] ? 'status-new' : 'status-active'; ?>">
                                        <?php echo $teacher['first_login'] ? '🆕 አዲስ' : '✅ ንቁ'; ?>
                                    </span>
                                </div>
                                <div class="teacher-username">
                                    @<?php echo htmlspecialchars($teacher_username); ?>
                                </div>
                                <div class="teacher-phone">
                                    <span>📱</span>
                                    <?php echo htmlspecialchars($teacher_phone ?: 'ስልክ የለም'); ?>
                                </div>
                            </div>
                        </div>

                        <div class="teacher-actions">
                            <a href="teacher_profile.php?id=<?php echo $teacher_id; ?>" class="btn btn-profile">
                                👤 መገለጫ
                            </a>
                            
                            <button type="button" onclick="editTeacher(<?php echo $teacher_id; ?>, '<?php echo htmlspecialchars(addslashes($teacher_name), ENT_QUOTES); ?>', '<?php echo htmlspecialchars(addslashes($teacher_username), ENT_QUOTES); ?>', '<?php echo htmlspecialchars(addslashes($teacher_phone ?? ''), ENT_QUOTES); ?>')" 
                                    class="btn btn-edit">
                                ✏️ አስተካክል
                            </button>
                            
                            <form method="POST" style="display: inline;" 
                                  onsubmit="return confirm('የ[<?php echo htmlspecialchars(addslashes($teacher_name), ENT_QUOTES); ?>] የይለፍ ቃል ወደ 123 መመለስ እርግጠኛ ነዎት?')">
                                <?php echo csrfField(); ?>
                                <input type="hidden" name="teacher_id" value="<?php echo $teacher_id; ?>">
                                <button type="submit" name="reset_password" class="btn btn-reset">
                                    🔄 ይለፍ ቃል መልስ
                                </button>
                            </form>
                            
                            <?php if(!$has_history): ?>
                            <form method="POST" style="display: inline;" 
                                  onsubmit="return confirm('መምህር [<?php echo htmlspecialchars(addslashes($teacher_name), ENT_QUOTES); ?>] መሰረዝ ይፈልጋሉ? የተያያዙ ውጤቶችና ምደባዎች ይሰረዛሉ!')">
                                <?php echo csrfField(); ?>
                                <input type="hidden" name="teacher_id" value="<?php echo $teacher_id; ?>">
                                <button type="submit" name="delete_teacher" class="btn btn-delete">
                                    🗑️ ሰርዝ
                                </button>
                            </form>
                            <?php else: ?>
                            <button type="button" class="btn btn-delete" style="opacity: 0.55; cursor: not-allowed;" 
                                    title="ይህ መምህር ታሪክ አለው መሰረዝ አይቻልም">
                                🗑️ መሰረዝ አይቻልም
                            </button>
                            <?php endif; ?>
                        </div>

                        <!-- Assignment History Timeline -->
                        <div class="timeline">
                            <?php if($has_history): ?>
                                <?php 
                                krsort($teacher_years);
                                foreach($teacher_years as $year => $semesters): 
                                    $is_current = ($year == $current_ethiopian_year);
                                ?>
                                <div class="year-group">
                                    <div class="year-header" onclick="toggleYear('year-<?php echo $teacher_id . '-' . $year; ?>')">
                                        <span class="year-badge"><?php echo $year; ?> ዓ.ም</span>
                                        <?php if($is_current): ?>
                                        <span class="year-status" style="color: var(--success-green); font-weight:700;">(ንቁ)</span>
                                        <?php endif; ?>
                                        <span class="toggle-icon" id="icon-<?php echo $teacher_id . '-' . $year; ?>"><?php echo $is_current ? '▼' : '▶'; ?></span>
                                    </div>
                                    
                                    <div id="year-<?php echo $teacher_id . '-' . $year; ?>" style="display: <?php echo $is_current ? 'block' : 'none'; ?>;">
                                        <?php if(!empty($semesters['semester1'])): ?>
                                        <div class="semester-row">
                                            <div class="semester-badge semester-1">ሴሚስተር 1</div>
                                            <div class="classes-list">
                                                <?php foreach($semesters['semester1'] as $assignment): ?>
                                                <span class="class-tag <?php echo $assignment['locked'] ? 'locked' : ''; ?>">
                                                    <?php if($assignment['locked']): ?>🔒 <?php endif; ?>
                                                    <?php echo htmlspecialchars($assignment['class_name']); ?>
                                                </span>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>
                                        <?php endif; ?>

                                        <?php if(!empty($semesters['semester2'])): ?>
                                        <div class="semester-row">
                                            <div class="semester-badge semester-2">ሴሚስተር 2</div>
                                            <div class="classes-list">
                                                <?php foreach($semesters['semester2'] as $assignment): ?>
                                                <span class="class-tag <?php echo $assignment['locked'] ? 'locked' : ''; ?>">
                                                    <?php if($assignment['locked']): ?>🔒 <?php endif; ?>
                                                    <?php echo htmlspecialchars($assignment['class_name']); ?>
                                                </span>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="no-data">
                                    ይህ መምህር እስካሁን ምንም ክፍል አልተመደበም
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endwhile; ?>
            </div>
            <?php else: ?>
            <div style="text-align:center; padding:50px 20px; color:var(--text-muted);">
                <div style="font-size:48px; margin-bottom:12px;">👨‍🏫</div>
                <div style="font-weight:700;">ምንም መምህራን አልተመዘገቡም</div>
                <p>እባክዎ ከላይ ያለውን ቅጽ በመጠቀም አዲስ መምህር ይመዝግቡ።</p>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Edit Teacher Modal -->
    <div id="editModal" class="modal-overlay">
        <div class="modal-card">
            <div class="modal-header">
                <h3>✏️ የመምህር መረጃ ማስተካከያ</h3>
                <button class="modal-close" onclick="closeModal()">&times;</button>
            </div>
            <form method="POST" id="editForm">
                <?php echo csrfField(); ?>
                <input type="hidden" name="teacher_id" id="edit_id">
                <div class="form-group" style="margin-bottom: 14px;">
                    <label>ሙሉ ስም <span style="color: var(--error);">*</span></label>
                    <input type="text" name="name" id="edit_name" class="form-control" required>
                </div>
                <div class="form-group" style="margin-bottom: 14px;">
                    <label>የተጠቃሚ ስም (Username) <span style="color: var(--error);">*</span></label>
                    <input type="text" name="username" id="edit_username" class="form-control" required>
                </div>
                <div class="form-group" style="margin-bottom: 20px;">
                    <label>ስልክ ቁጥር</label>
                    <input type="text" name="phone" id="edit_phone" class="form-control">
                </div>
                <button type="submit" name="edit_teacher" class="btn-primary-action" style="width: 100%; justify-content: center;">
                    💾 ለውጦችን አስቀምጥ
                </button>
            </form>
        </div>
    </div>

    <!-- Reject Profile Modal -->
    <div id="rejectModal" class="modal-overlay">
        <div class="modal-card">
            <div class="modal-header">
                <h3>❌ ጥያቄ ውድቅ ማድረጊያ</h3>
                <button class="modal-close" onclick="closeRejectModal()">&times;</button>
            </div>
            <form method="POST">
                <?php echo csrfField(); ?>
                <input type="hidden" name="request_id" id="reject_req_id">
                <p id="reject_teacher_name" style="margin-bottom: 12px; font-weight: 600; color: var(--brown-dark);"></p>
                <div class="form-group" style="margin-bottom: 16px;">
                    <label>ውድቅ የተደረገበት ምክንያት / ማብራሪያ፦</label>
                    <textarea name="admin_notes" class="form-control" rows="3" placeholder="ለምሳሌ፦ ስልክ ቁጥሩ የተሳሳተ ነው..."></textarea>
                </div>
                <button type="submit" name="reject_profile_request" style="width: 100%; padding: 12px; border: none; border-radius: 10px; background: var(--error); color: white; font-weight: 700; cursor: pointer;">
                    ❌ ጥያቄውን ውድቅ አድርግ
                </button>
            </form>
        </div>
    </div>

    <script>
        function editTeacher(id, name, username, phone) {
            document.getElementById('edit_id').value = id;
            document.getElementById('edit_name').value = name;
            document.getElementById('edit_username').value = username;
            document.getElementById('edit_phone').value = phone || '';
            document.getElementById('editModal').style.display = 'flex';
        }

        function closeModal() {
            document.getElementById('editModal').style.display = 'none';
        }

        function openRejectModal(id, name) {
            document.getElementById('reject_req_id').value = id;
            document.getElementById('reject_teacher_name').innerText = "ለመምህር " + name + " የተላከ ማብራሪያ";
            document.getElementById('rejectModal').style.display = 'flex';
        }

        function closeRejectModal() {
            document.getElementById('rejectModal').style.display = 'none';
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

        function toggleHistory(teacherId) {
            var el = document.getElementById('historyDetails-' + teacherId);
            var icon = document.getElementById('toggleIcon-' + teacherId);
            if (!el) return;
            if (el.style.display === 'none' || el.style.display === '') {
                el.style.display = 'block';
                if (icon) icon.innerText = '▲';
            } else {
                el.style.display = 'none';
                if (icon) icon.innerText = '▼';
            }
        }

        function filterTeachers() {
            var q = document.getElementById('teacherSearch').value.toLowerCase();
            var cards = document.querySelectorAll('.teacher-card');
            cards.forEach(function(card) {
                var searchData = card.getAttribute('data-search');
                if (!q || searchData.indexOf(q) > -1) {
                    card.style.display = 'flex';
                } else {
                    card.style.display = 'none';
                }
            });
        }

        window.onclick = function(event) {
            var editM = document.getElementById('editModal');
            var rejM = document.getElementById('rejectModal');
            if (event.target === editM) closeModal();
            if (event.target === rejM) closeRejectModal();
        }
    </script>
</body>
</html>
<?php mysqli_close($conn); ?>