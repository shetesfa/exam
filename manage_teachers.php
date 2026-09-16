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
                        $message = "መምህር በተሳካ ሁኔታ ተመዝግቧል! የተጠቃሚ ስም: $username | የይለፍ ቃል: 123";
                    } else {
                        $error = "ስህተት ተከስቷል!";
                    }
                }
            } else {
                $error = "እባክዎ ስም እና የተጠቃሚ ስም ያስገቡ!";
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
                    $message = "መምህር በተሳካ ሁኔታ ተሰርዟል!";
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
                    "ያቀረቡት የመረጃ ለውጥ ጥያቄ በአስተዳዳሪው ተቀባይነት አግኝቶ መረጃዎ ተሻሽሏል።",
                    ['user_id' => $req['teacher_id']],
                    'normal',
                    null,
                    'teacher_profile.php'
                );
                $message = "የመምህር መረጃ ለውጥ ጥያቄ በተሳካ ሁኔታ ጸድቋል!";
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

// Get all academic years for filtering
$years_query = "SELECT DISTINCT ethiopian_year FROM semesters ORDER BY ethiopian_year DESC";
$years = mysqli_query($conn, $years_query);

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
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>መምህራን አስተዳደር | አጸደ ትጉሃን</title>
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
        }

        .header-content {
            max-width: 1400px;
            margin: 0 auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
        }

        .logo-area {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .logo-icon {
            width: 55px;
            height: 55px;
            background: linear-gradient(135deg, var(--gold-primary), var(--gold-dark));
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 28px;
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
            border-radius: 12px;
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            gap: 12px;
            animation: slideDown 0.4s ease;
        }

        @keyframes slideDown {
            from { opacity: 0; transform: translateY(-20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .success {
            background: #D1FAE5;
            color: var(--success-green);
            border-left: 5px solid var(--success-green);
        }

        .error {
            background: #FEE2E2;
            color: var(--error-red);
            border-left: 5px solid var(--error-red);
        }

        .current-year-badge {
            background: linear-gradient(135deg, var(--gold-primary), var(--gold-dark));
            color: var(--brown-dark);
            padding: 5px 15px;
            border-radius: 20px;
            font-weight: bold;
            font-size: 14px;
            display: inline-block;
        }

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
            gap: 12px;
        }

        .btn {
            padding: 12px 25px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-primary {
            background: linear-gradient(135deg, #FFD700 0%, #DAA520 100%);
            color: #8B4513;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(218,165,32,0.3);
        }

        .btn-edit {
            background: #3B82F6;
            color: white;
            padding: 6px 12px;
            font-size: 12px;
        }

        .btn-delete {
            background: #EF4444;
            color: white;
            padding: 6px 12px;
            font-size: 12px;
        }

        .btn-reset {
            background: #F59E0B;
            color: white;
            padding: 6px 12px;
            font-size: 12px;
        }

        .request-review-card {
            background: white;
            border: 1.5px solid #FCD34D;
        }
        .request-review-header {
            border-bottom: 1px solid #F3F4F6;
        }
        .request-detail-box {
            background: #F9FAFB;
        }
        .request-reason-box {
            background: #FEF3C7;
            color: #92400E;
        }

        .btn-profile {
            background: #8B5CF6;
            color: white;
            padding: 6px 12px;
            font-size: 12px;
            text-decoration: none;
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }

        .form-group {
            margin-bottom: 15px;
        }

        .form-group label {
            display: block;
            margin-bottom: 5px;
            color: var(--brown-dark);
            font-weight: 600;
        }

        .form-control {
            width: 100%;
            padding: 12px;
            border: 2px solid #E2E8F0;
            border-radius: 8px;
            font-size: 16px;
        }

        .form-control:focus {
            outline: none;
            border-color: var(--gold-primary);
        }

        .add-teacher-form {
            background: var(--gold-pale);
            padding: 25px;
            border-radius: 12px;
            border: 2px dashed var(--gold-primary);
        }

        /* Teacher Cards Grid */
        .teachers-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 25px;
            margin-top: 20px;
        }

        .teacher-card {
            background: white;
            border-radius: 15px;
            padding: 20px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            border: 2px solid var(--gold-pale);
            transition: all 0.3s;
        }

        .teacher-card:hover {
            transform: translateY(-3px);
            border-color: var(--gold-primary);
            box-shadow: 0 10px 25px rgba(0,0,0,0.15);
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
        }
        
        .teacher-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .teacher-title {
            flex: 1;
        }

        .teacher-name {
            font-size: 18px;
            font-weight: bold;
            color: var(--brown-dark);
            margin-bottom: 3px;
            text-decoration: none;
            cursor: pointer;
            transition: color 0.2s;
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
            margin-bottom: 3px;
        }

        .teacher-phone {
            color: #666;
            font-size: 13px;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .teacher-status {
            display: inline-block;
            padding: 3px 10px;
            border-radius: 15px;
            font-size: 11px;
            font-weight: 600;
            margin-left: 10px;
        }

        .status-new {
            background: #FEF3C7;
            color: var(--warning-yellow);
        }

        .status-active {
            background: #D1FAE5;
            color: var(--success-green);
        }

        .teacher-actions {
            display: flex;
            gap: 8px;
            margin-top: 10px;
            flex-wrap: wrap;
        }

        /* Timeline Styles */
        .timeline {
            margin-top: 20px;
        }

        .year-group {
            margin-bottom: 20px;
            border-left: 3px solid var(--gold-primary);
            padding-left: 15px;
        }

        .year-header {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 10px;
            cursor: pointer;
            padding: 8px;
            background: #F8F9FA;
            border-radius: 8px;
            transition: all 0.3s;
        }

        .year-header:hover {
            background: var(--gold-pale);
        }

        .year-badge {
            background: var(--brown-dark);
            color: var(--gold-primary);
            padding: 5px 15px;
            border-radius: 20px;
            font-weight: bold;
            font-size: 14px;
        }

        .year-status {
            font-size: 12px;
            color: #666;
        }

        .toggle-icon {
            margin-left: auto;
            font-size: 18px;
            color: var(--gold-dark);
        }

        .semester-row {
            display: flex;
            gap: 20px;
            margin-bottom: 15px;
            padding: 10px;
            background: #F8F9FA;
            border-radius: 8px;
            animation: slideDown 0.3s ease;
        }

        .semester-badge {
            min-width: 100px;
            padding: 5px 10px;
            border-radius: 15px;
            font-size: 12px;
            font-weight: 600;
            text-align: center;
        }

        .semester-1 {
            background: #EFF6FF;
            color: #3B82F6;
        }

        .semester-2 {
            background: #FEF3C7;
            color: #F59E0B;
        }

        .classes-list {
            flex: 1;
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
        }

        .class-tag {
            background: white;
            border: 1px solid var(--gold-primary);
            color: var(--brown-dark);
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }

        .class-tag.locked {
            background: #FEE2E2;
            border-color: var(--error-red);
            color: var(--error-red);
        }

        .no-data {
            color: #999;
            font-style: italic;
            padding: 10px;
            text-align: center;
        }

        .empty-state {
            text-align: center;
            padding: 50px;
            color: #666;
        }

        .empty-state span {
            font-size: 50px;
            display: block;
            margin-bottom: 15px;
        }

        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 1000;
        }

        .modal-content {
            background: white;
            width: 90%;
            max-width: 500px;
            margin: 50px auto;
            padding: 30px;
            border-radius: 15px;
            border: 3px solid var(--gold-primary);
        }

        .close {
            float: right;
            font-size: 24px;
            cursor: pointer;
            color: var(--brown-dark);
        }

        .close:hover {
            color: var(--error-red);
        }

        .info-box {
            background: #EFF6FF;
            border-left: 4px solid var(--info-blue);
            padding: 15px;
            border-radius: 8px;
            margin: 20px 0;
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .info-box span {
            font-size: 24px;
        }

        @media (max-width: 768px) {
            .main-container { padding: 0 12px 30px; margin: 15px auto; }
            .section { padding: 16px 12px; border-radius: 12px; margin-bottom: 20px; }
            .section-header h2 { font-size: 17px; }
            .teachers-grid {
                grid-template-columns: 1fr;
                gap: 15px;
            }
            .teacher-card { padding: 15px 12px; }
            .teacher-header { gap: 10px; }
            .teacher-avatar { width: 50px; height: 50px; font-size: 20px; }
            .teacher-actions { flex-direction: column; }
            .teacher-actions .btn { width: 100%; justify-content: center; min-height: 38px; }
            
            .semester-row {
                flex-direction: column;
                gap: 10px;
            }
            
            .semester-badge {
                align-self: flex-start;
            }
            
            .form-grid {
                grid-template-columns: 1fr;
            }
            .add-teacher-form { padding: 16px 12px; }
            .modal-content { width: 95%; margin: 20px auto; padding: 20px 14px; border-radius: 12px; }
        }

        .requests-section {
            border: 2px solid #E5E7EB;
            background: white;
            margin-bottom: 25px;
        }
        .requests-section.has-pending {
            border-color: var(--warning-yellow);
            background: #FFFDF5;
        }

        .count-badge {
            background: var(--gold-pale);
            color: var(--brown-dark);
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: 600;
        }

        .reject-box {
            background: #FEF2F2;
            border: 1px solid #FECACA;
        }

        /* ── DIRECT DARK-MODE OVERRIDES FOR MANAGE_TEACHERS.PHP ── */
        html.dark-mode body {
            background-color: #0B1120 !important;
        }

        html.dark-mode .section {
            background-color: #1E293B !important;
            border: 1px solid #334155 !important;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.4) !important;
            color: #F1F5F9 !important;
        }

        html.dark-mode .requests-section {
            background-color: #1E293B !important;
            border-color: #334155 !important;
        }

        html.dark-mode .requests-section.has-pending {
            background: linear-gradient(135deg, #1E293B 0%, #261E0A 100%) !important;
            border-color: #D97706 !important;
        }

        html.dark-mode .section-header {
            border-bottom: 2px solid #334155 !important;
        }

        html.dark-mode .section-header h2 {
            color: #FCD34D !important;
        }

        html.dark-mode .count-badge {
            background: #0F172A !important;
            color: #FCD34D !important;
            border: 1px solid #F59E0B !important;
        }

        html.dark-mode .form-group label {
            color: #CBD5E1 !important;
        }

        html.dark-mode .form-control {
            background-color: #0F172A !important;
            border: 1.5px solid #475569 !important;
            color: #F8FAFC !important;
        }

        html.dark-mode .form-control:focus {
            border-color: #F59E0B !important;
            box-shadow: 0 0 0 3px rgba(245, 158, 11, 0.25) !important;
        }

        html.dark-mode .add-teacher-form {
            background-color: #0F172A !important;
            border: 2px dashed #F59E0B !important;
            color: #F1F5F9 !important;
        }

        html.dark-mode .info-box {
            background-color: #1E293B !important;
            border: 1px solid #334155 !important;
            border-left: 4px solid #3B82F6 !important;
            color: #BFDBFE !important;
        }

        html.dark-mode .info-box strong {
            color: #FCD34D !important;
        }

        html.dark-mode .teacher-card {
            background-color: #1E293B !important;
            border: 1.5px solid #334155 !important;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.3) !important;
            color: #F1F5F9 !important;
        }

        html.dark-mode .teacher-card:hover {
            border-color: #F59E0B !important;
            box-shadow: 0 10px 25px rgba(245, 158, 11, 0.2) !important;
        }

        html.dark-mode .teacher-header {
            border-bottom: 1.5px solid #334155 !important;
        }

        html.dark-mode .teacher-avatar {
            border: 2.5px solid #F59E0B !important;
            box-shadow: 0 3px 10px rgba(0, 0, 0, 0.4) !important;
        }

        html.dark-mode .teacher-name {
            color: #F8FAFC !important;
        }

        html.dark-mode .teacher-name:hover {
            color: #FCD34D !important;
        }

        html.dark-mode .teacher-username {
            background-color: #2E1065 !important;
            color: #DDD6FE !important;
            border: 1px solid #6D28D9 !important;
        }

        html.dark-mode .teacher-phone {
            color: #94A3B8 !important;
        }

        html.dark-mode .status-new {
            background-color: #78350F !important;
            color: #FDE68A !important;
            border: 1px solid #D97706 !important;
        }

        html.dark-mode .status-active {
            background-color: #064E3B !important;
            color: #A7F3D0 !important;
            border: 1px solid #059669 !important;
        }

        html.dark-mode .year-group {
            border-left: 3px solid #F59E0B !important;
        }

        html.dark-mode .year-header {
            background-color: #0F172A !important;
            border: 1px solid #334155 !important;
            color: #F1F5F9 !important;
        }

        html.dark-mode .year-header:hover {
            background-color: #26354A !important;
        }

        html.dark-mode .year-badge {
            background: #D97706 !important;
            color: #FFFFFF !important;
        }

        html.dark-mode .year-status {
            color: #94A3B8 !important;
        }

        html.dark-mode .semester-row {
            background-color: #0F172A !important;
            border: 1px solid #334155 !important;
        }

        html.dark-mode .semester-badge.semester-1 {
            background-color: #1E3A8A !important;
            color: #BFDBFE !important;
            border: 1px solid #2563EB !important;
        }

        html.dark-mode .semester-badge.semester-2 {
            background-color: #78350F !important;
            color: #FDE68A !important;
            border: 1px solid #D97706 !important;
        }

        html.dark-mode .class-tag {
            background-color: #1E293B !important;
            border: 1px solid #F59E0B !important;
            color: #FCD34D !important;
        }

        html.dark-mode .class-tag.locked {
            background-color: #3B1212 !important;
            border-color: #DC2626 !important;
            color: #FCA5A5 !important;
        }

        html.dark-mode .no-data {
            color: #64748B !important;
        }

        html.dark-mode .empty-state {
            color: #94A3B8 !important;
        }

        html.dark-mode .modal-content {
            background-color: #1E293B !important;
            border: 2px solid #F59E0B !important;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.7) !important;
            color: #F1F5F9 !important;
        }

        html.dark-mode .modal-content h2,
        html.dark-mode .modal-title {
            color: #FCD34D !important;
        }

        html.dark-mode .close {
            color: #94A3B8 !important;
        }

        html.dark-mode .close:hover {
            color: #EF4444 !important;
        }

        /* Profile Requests Review Cards */
        html.dark-mode .request-review-card {
            background-color: #0F172A !important;
            border: 1.5px solid #334155 !important;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.3) !important;
        }

        html.dark-mode .request-review-header {
            border-bottom: 1px solid #334155 !important;
        }

        html.dark-mode .request-review-header strong {
            color: #FCD34D !important;
        }

        html.dark-mode .request-detail-box {
            background-color: #1E293B !important;
            border: 1px solid #334155 !important;
            color: #E2E8F0 !important;
        }

        html.dark-mode .request-reason-box {
            background-color: #78350F !important;
            border: 1px solid #D97706 !important;
            color: #FDE68A !important;
        }

        html.dark-mode .reject-box {
            background-color: #3B1212 !important;
            border: 1px solid #7F1D1D !important;
            color: #FECACA !important;
        }

        html.dark-mode .reject-box label {
            color: #FCA5A5 !important;
        }

        html.dark-mode .reject-box input {
            background-color: #0F172A !important;
            border-color: #475569 !important;
            color: #F8FAFC !important;
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

        <!-- Current Academic Year Info -->
        <div class="current-year-badge" style="margin-bottom: 20px;">
            ንቁ ዘመን: <?php echo $current_ethiopian_year; ?> ዓ.ም
        </div>

        <!-- Profile Change Requests from Teachers -->
        <div class="section requests-section <?php echo !empty($pending_requests) ? 'has-pending' : ''; ?>" id="requests">
            <div class="section-header" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
                <h2>
                    <span>📋</span> የመምህራን የመረጃ ለውጥ ጥያቄዎች
                    <?php if (!empty($pending_requests)): ?>
                        <span style="background: #DC2626; color: white; padding: 2px 10px; border-radius: 20px; font-size: 12px; margin-left: 8px;">
                            <?php echo count($pending_requests); ?> አዲስ ጥያቄ
                        </span>
                    <?php endif; ?>
                </h2>
                <span style="font-size: 13px; color: #666;">
                    <?php echo !empty($pending_requests) ? count($pending_requests) . ' ጥያቄዎች ውሳኔ ይጠብቃሉ' : 'ምንም በመጠባበቅ ላይ ያለ ጥያቄ የለም'; ?>
                </span>
            </div>

            <?php if (!empty($pending_requests)): ?>
                <div style="display: flex; flex-direction: column; gap: 16px; margin-top: 15px;">
                    <?php foreach ($pending_requests as $req): ?>
                        <div class="request-review-card" style="border-radius: 12px; padding: 18px; box-shadow: 0 2px 8px rgba(0,0,0,0.05);">
                            <div class="request-review-header" style="display: flex; justify-content: space-between; align-items: center; padding-bottom: 10px; margin-bottom: 12px; flex-wrap: wrap; gap: 8px;">
                                <div>
                                    <strong style="color: var(--brown-dark); font-size: 15px;"><?php echo htmlspecialchars($req['current_name']); ?></strong>
                                    <span style="color: #6B7280; font-size: 12px; margin-left: 6px;">(@<?php echo htmlspecialchars($req['username']); ?>)</span>
                                </div>
                                <span style="font-size: 12px; color: #9CA3AF;">የቀረበበት ቀን፡ <?php echo date('M d, Y h:i A', strtotime($req['created_at'])); ?></span>
                            </div>

                            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 14px; margin-bottom: 14px; font-size: 13px;">
                                <div class="request-detail-box" style="padding: 10px; border-radius: 8px;">
                                    <span style="color: #6B7280; display: block; font-size: 11px; text-transform: uppercase;">ሙሉ ስም</span>
                                    <div>የነበረው፡ <strong><?php echo htmlspecialchars($req['current_name']); ?></strong></div>
                                    <div style="color: <?php echo ($req['requested_name'] && $req['requested_name'] !== $req['current_name']) ? '#D97706' : '#059669'; ?>; margin-top: 3px;">
                                        የተጠየቀው፡ <strong><?php echo htmlspecialchars($req['requested_name'] ?: $req['current_name']); ?></strong>
                                    </div>
                                </div>

                                <div class="request-detail-box" style="padding: 10px; border-radius: 8px;">
                                    <span style="color: #6B7280; display: block; font-size: 11px; text-transform: uppercase;">ስልክ ቁጥር</span>
                                    <div>የነበረው፡ <strong><?php echo htmlspecialchars($req['current_phone'] ?: '---'); ?></strong></div>
                                    <div style="color: <?php echo ($req['requested_phone'] && $req['requested_phone'] !== $req['current_phone']) ? '#D97706' : '#059669'; ?>; margin-top: 3px;">
                                        የተጠየቀው፡ <strong><?php echo htmlspecialchars($req['requested_phone'] ?: ($req['current_phone'] ?: '---')); ?></strong>
                                    </div>
                                </div>

                                <?php if ($req['requested_photo']): ?>
                                    <div class="request-detail-box" style="padding: 10px; border-radius: 8px; display: flex; align-items: center; gap: 12px;">
                                        <img src="<?php echo htmlspecialchars($req['requested_photo']); ?>" style="width: 50px; height: 50px; border-radius: 50%; object-fit: cover; border: 2px solid var(--gold-primary);" alt="New photo">
                                        <div>
                                            <span style="color: #6B7280; font-size: 11px; text-transform: uppercase; display: block;">አዲስ ፎቶ</span>
                                            <a href="<?php echo htmlspecialchars($req['requested_photo']); ?>" target="_blank" style="color: #2563EB; font-size: 12px; text-decoration: underline;">በሙሉ እይ</a>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <?php if ($req['reason']): ?>
                                <div class="request-reason-box" style="padding: 8px 12px; border-radius: 6px; font-size: 12px; margin-bottom: 14px;">
                                    <strong>ምክንያት፡</strong> <?php echo htmlspecialchars($req['reason']); ?>
                                </div>
                            <?php endif; ?>

                            <div style="display: flex; gap: 10px; flex-wrap: wrap; align-items: center; justify-content: flex-end;">
                                <form method="POST" style="display: inline;" onsubmit="return confirm('ይህን የመረጃ ለውጥ ማጽደቅ እርግጠኛ ነዎት?')">
                                    <?php echo csrfField(); ?>
                                    <input type="hidden" name="request_id" value="<?php echo $req['id']; ?>">
                                    <button type="submit" name="approve_profile_request" class="btn" style="background: #059669; color: white; padding: 8px 16px; border: none; border-radius: 8px; font-weight: 600; cursor: pointer;">
                                        ✅ ጥያቄውን አጽድቅ
                                    </button>
                                </form>

                                <button type="button" class="btn" onclick="document.getElementById('reject-box-<?php echo $req['id']; ?>').style.display='block'" style="background: #DC2626; color: white; padding: 8px 16px; border: none; border-radius: 8px; font-weight: 600; cursor: pointer;">
                                    ❌ ውድቅ አድርግ
                                </button>
                            </div>

                            <div id="reject-box-<?php echo $req['id']; ?>" class="reject-box" style="display: none; margin-top: 12px; padding: 12px; border-radius: 8px;">
                                <form method="POST">
                                    <?php echo csrfField(); ?>
                                    <input type="hidden" name="request_id" value="<?php echo $req['id']; ?>">
                                    <label style="display: block; font-size: 12px; font-weight: 600; color: #991B1B; margin-bottom: 4px;">የውድቅ ማድረጊያ ምክንያት (ለመምህሩ የሚላክ)</label>
                                    <input type="text" name="admin_notes" placeholder="ምሳሌ፡ መረጃው ትክክል አይደለም..." style="width: 100%; padding: 8px; border: 1px solid #D1D5DB; border-radius: 6px; font-size: 13px; margin-bottom: 8px;">
                                    <div style="display: flex; gap: 8px;">
                                        <button type="submit" name="reject_profile_request" class="btn" style="background: #DC2626; color: white; padding: 6px 14px; border: none; border-radius: 6px; font-size: 12px; font-weight: 600; cursor: pointer;">ውድቅ አድርግ</button>
                                        <button type="button" class="btn" onclick="document.getElementById('reject-box-<?php echo $req['id']; ?>').style.display='none'" style="background: #6B7280; color: white; padding: 6px 14px; border: none; border-radius: 6px; font-size: 12px; cursor: pointer;">ሰርዝ</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div style="padding: 15px; text-align: center; color: #9CA3AF; font-size: 13px;">
                    ✨ በአሁኑ ሰዓት ምንም ያልተመለሰ የመረጃ ለውጥ ጥያቄ የለም።
                </div>
            <?php endif; ?>
        </div>

        <!-- Add Teacher Form -->
        <div class="section">
            <div class="section-header">
                <h2><span>➕</span> አዲስ መምህር መመዝገቢያ</h2>
            </div>
            
            <div class="add-teacher-form">
                <form method="POST">
                    <?php echo csrfField(); ?>
                    <div class="form-grid">
                        <div class="form-group">
                            <label>ሙሉ ስም <span style="color: var(--error-red);">*</span></label>
                            <input type="text" name="name" class="form-control" required 
                                   placeholder="ሙሉ ስም ያስገቡ">
                        </div>
                        <div class="form-group">
                            <label>የተጠቃሚ ስም <span style="color: var(--error-red);">*</span></label>
                            <input type="text" name="username" class="form-control" required 
                                   placeholder="ለምሳሌ: memhir_abebe">
                        </div>
                        <div class="form-group">
                            <label>ስልክ ቁጥር</label>
                            <input type="text" name="phone" class="form-control" 
                                   placeholder="0912345678">
                        </div>
                    </div>
                    
                    <div class="info-box">
                        <span>ℹ️</span>
                        <div>
                            <strong>የመጀመሪያ የይለፍ ቃል:</strong> 123<br>
                            <small>መምህሩ ለመጀመሪያ ጊዜ ሲገባ ይለውጠዋል</small>
                        </div>
                    </div>
                    
                    <button type="submit" name="add_teacher" class="btn btn-primary">
                        <span>➕</span> መምህር አስመዝግብ
                    </button>
                </form>
            </div>
        </div>

        <!-- Teachers List with History -->
        <div class="section">
            <div class="section-header">
                <h2><span>👨‍🏫</span> የመምህራን ዝርዝር እና ታሪክ</h2>
                <span class="count-badge">
                    <?php echo mysqli_num_rows($teachers); ?> መምህራን
                </span>
            </div>
            
            <?php if($teachers && mysqli_num_rows($teachers) > 0): ?>
            <div class="teachers-grid">
                <?php while($teacher = mysqli_fetch_assoc($teachers)): 
                    $teacher_id = $teacher['id'];
                    $has_history = isset($teacher_history[$teacher_id]);
                    $teacher_photo = $teacher['photo'] ?: null;
                ?>
                <div class="teacher-card">
                    <div class="teacher-header">
                        <div class="teacher-avatar">
                            <?php if($teacher_photo): ?>
                            <img src="<?php echo htmlspecialchars($teacher_photo); ?>" alt="<?php echo htmlspecialchars($teacher['name']); ?>" onerror="this.style.display='none'; this.parentElement.innerHTML='<?php echo mb_substr($teacher['name'], 0, 1); ?>';">
                            <?php else: ?>
                            <?php echo mb_substr($teacher['name'], 0, 1); ?>
                            <?php endif; ?>
                        </div>
                        <div class="teacher-title">
                            <div>
                                <a href="teacher_profile.php?id=<?php echo $teacher['id']; ?>" class="teacher-name">
                                    <?php echo htmlspecialchars($teacher['name']); ?>
                                </a>
                                <span class="teacher-status <?php echo $teacher['first_login'] ? 'status-new' : 'status-active'; ?>">
                                    <?php echo $teacher['first_login'] ? '🆕 አዲስ' : '✅ ንቁ'; ?>
                                </span>
                            </div>
                            <div class="teacher-username">
                                @<?php echo htmlspecialchars($teacher['username']); ?>
                            </div>
                            <div class="teacher-phone">
                                <span>📱</span>
                                <?php echo htmlspecialchars($teacher['phone'] ?: 'ስልክ የለም'); ?>
                            </div>
                        </div>
                    </div>

                    <div class="teacher-actions">
                        <a href="teacher_profile.php?id=<?php echo $teacher['id']; ?>" class="btn btn-profile">
                            👤 መገለጫ
                        </a>
                        
                        <button onclick="editTeacher(<?php echo $teacher['id']; ?>, '<?php echo htmlspecialchars(addslashes($teacher['name'])); ?>', '<?php echo htmlspecialchars($teacher['username']); ?>', '<?php echo $teacher['phone']; ?>')" 
                                class="btn btn-edit">
                            ✏️ አስተካክል
                        </button>
                        
                        <form method="POST" style="display: inline;" 
                              onsubmit="return confirm('የይለፍ ቃል ወደ 123 መመለስ እርግጠኛ ነዎት?')">
                            <?php echo csrfField(); ?>
                            <input type="hidden" name="teacher_id" value="<?php echo $teacher['id']; ?>">
                            <button type="submit" name="reset_password" class="btn btn-reset">
                                🔄 ይለፍ ቃል መልስ
                            </button>
                        </form>
                        
                        <?php if(!$has_history): ?>
                        <form method="POST" style="display: inline;" 
                              onsubmit="return confirm('መምህሩን መሰረዝ እርግጠኛ ነዎት?')">
                            <?php echo csrfField(); ?>
                            <input type="hidden" name="teacher_id" value="<?php echo $teacher['id']; ?>">
                            <button type="submit" name="delete_teacher" class="btn btn-delete">
                                🗑️ ሰርዝ
                            </button>
                        </form>
                        <?php else: ?>
                        <button class="btn btn-delete" style="opacity: 0.5; cursor: not-allowed;" 
                                title="ይህ መምህር ታሪክ አለው መሰረዝ አይቻልም">
                            🗑️ መሰረዝ አይቻልም
                        </button>
                        <?php endif; ?>
                    </div>

                    <!-- Assignment History Timeline -->
                    <div class="timeline">
                        <?php if($has_history): ?>
                            <?php 
                            $teacher_years = $teacher_history[$teacher_id];
                            krsort($teacher_years);
                            foreach($teacher_years as $year => $semesters): 
                                $is_current = ($year == $current_ethiopian_year);
                            ?>
                            <div class="year-group">
                                <div class="year-header" onclick="toggleYear('year-<?php echo $teacher_id . '-' . $year; ?>')">
                                    <span class="year-badge"><?php echo $year; ?> ዓ.ም</span>
                                    <?php if($is_current): ?>
                                    <span class="year-status" style="color: var(--success-green);">(ንቁ)</span>
                                    <?php endif; ?>
                                    <span class="toggle-icon" id="icon-<?php echo $teacher_id . '-' . $year; ?>">▼</span>
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
                <?php endwhile; ?>
            </div>
            <?php else: ?>
            <div class="empty-state">
                <span>👨‍🏫</span>
                <h3>ምንም መምህራን የሉም</h3>
                <p>እባክዎ ከላይ ባለው ቅጽ አዲስ መምህር ይመዝግቡ</p>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Edit Modal -->
    <div id="editModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeModal()">&times;</span>
            <h2 class="modal-title" style="margin-bottom: 20px;">የመምህር መረጃ አስተካክል</h2>
            
            <form method="POST" id="editForm">
                <?php echo csrfField(); ?>
                <input type="hidden" name="teacher_id" id="edit_id">
                
                <div class="form-group">
                    <label>ሙሉ ስም <span style="color: var(--error-red);">*</span></label>
                    <input type="text" name="name" id="edit_name" class="form-control" required>
                </div>
                
                <div class="form-group">
                    <label>የተጠቃሚ ስም <span style="color: var(--error-red);">*</span></label>
                    <input type="text" name="username" id="edit_username" class="form-control" required>
                </div>
                
                <div class="form-group">
                    <label>ስልክ ቁጥር</label>
                    <input type="text" name="phone" id="edit_phone" class="form-control">
                </div>
                
                <button type="submit" name="edit_teacher" class="btn btn-primary" style="width: 100%;">
                    💾 አስቀምጥ
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
            document.getElementById('editModal').style.display = 'block';
        }

        function closeModal() {
            document.getElementById('editModal').style.display = 'none';
        }

        function toggleYear(elementId) {
            const yearDiv = document.getElementById(elementId);
            const icon = document.getElementById('icon-' + elementId.replace('year-', ''));
            
            if (yearDiv.style.display === 'none') {
                yearDiv.style.display = 'block';
                icon.innerHTML = '▼';
            } else {
                yearDiv.style.display = 'none';
                icon.innerHTML = '▶';
            }
        }

        window.onclick = function(event) {
            if (event.target == document.getElementById('editModal')) {
                closeModal();
            }
        }
    </script>
</body>
</html>
<?php mysqli_close($conn); ?>