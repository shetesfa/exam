<?php
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("Expires: Sat, 26 Jul 1997 05:00:00 GMT");

require_once 'db.php';
requireLogin();

if (!isTeacher()) {
    header("Location: dashboard_admin.php");
    exit();
}

$teacher_id = intval($_SESSION['user_id'] ?? 0);
$user_name = $_SESSION['user_name'] ?? 'መምህር';

// Get teacher full info
$teacher_photo = 'images/icon.png';
$teacher_full_name = $user_name;
$teacher_username = '';
$teacher_phone = '';

$info = dbFetchOne($conn, "SELECT * FROM users WHERE id = ?", "i", [$teacher_id]);
if ($info) {
    if (!empty($info['photo'])) {
        $teacher_photo = $info['photo'];
    }
    $teacher_full_name = $info['name'];
    $teacher_username = $info['username'];
    $teacher_phone = $info['phone'] ?: '';
}

// Handle password change
$password_message = '';
$password_error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $password_error = "የደህንነት ማረጋገጫ አልተሳካም!";
    } else {
        $current_pass = $_POST['current_password'] ?? '';
        $new_pass = $_POST['new_password'] ?? '';
        $confirm_pass = $_POST['confirm_password'] ?? '';
        
        // Verify current password
        $pass_data = dbFetchOne($conn, "SELECT password FROM users WHERE id = ?", "i", [$teacher_id]);
        
        if (!$pass_data || !password_verify($current_pass, $pass_data['password'])) {
            $password_error = "የአሁኑ የይለፍ ቃል ትክክል አይደለም!";
        } elseif (strlen($new_pass) < 3) {
            $password_error = "የይለፍ ቃል ቢያንስ 3 ዲጂት መሆን አለበት!";
        } elseif ($new_pass !== $confirm_pass) {
            $password_error = "ያስገቧቸው የይለፍ ቃሎች አይመሳሰሉም!";
        } else {
            $hashed = password_hash($new_pass, PASSWORD_DEFAULT);
            dbExecute($conn, "UPDATE users SET password = ?, first_login = 0 WHERE id = ?", "si", [$hashed, $teacher_id]);
            $password_message = "የይለፍ ቃሉ በተሳካ ሁኔታ ተቀይሯል!";
        }
    }
}

// Get admin contact info
$admin1_row = dbFetchOne($conn, "SELECT setting_value FROM settings WHERE setting_key = 'admin_name_1'");
$admin1 = $admin1_row ? $admin1_row['setting_value'] : 'ዲ/ን ኪብረአብ ዘለለም';
$phone1_row = dbFetchOne($conn, "SELECT setting_value FROM settings WHERE setting_key = 'admin_phone_1'");
$phone1 = $phone1_row ? $phone1_row['setting_value'] : '0939883508';

// Get current semester
$current_semester = getCurrentSemester($conn);
$semester_id = $current_semester ? intval($current_semester['id']) : 0;

// Get classes assigned to this teacher
$teacher_classes = [];
if ($teacher_id && $semester_id) {
    $teacher_classes_query = "SELECT tc.*, c.name as class_name, c.id as class_id, 
                              COUNT(DISTINCT s.id) as student_count
                              FROM teacher_class tc
                              JOIN classes c ON tc.class_id = c.id
                              LEFT JOIN students s ON c.id = s.class_id AND (s.is_deleted = 0 OR s.is_deleted IS NULL)
                              WHERE tc.teacher_id = ? 
                              AND tc.semester_id = ?
                              GROUP BY c.id
                              ORDER BY c.name";
    $teacher_classes = dbFetchAll($conn, $teacher_classes_query, "ii", [$teacher_id, $semester_id]);
}

$error_message = '';
if (empty($teacher_classes)) {
    $error_message = "ለዚህ ሴሚስተር ምንም ክፍል አልተመደበልዎትም!";
}

$success_message = '';
$error_message_display = '';
if (isset($_SESSION['success'])) { $success_message = $_SESSION['success']; unset($_SESSION['success']); }
if (isset($_SESSION['error'])) { $error_message_display = $_SESSION['error']; unset($_SESSION['error']); }

$selected_class_id = isset($_GET['class_id']) ? intval($_GET['class_id']) : (!empty($teacher_classes) ? intval($teacher_classes[0]['class_id']) : 0);
$selected_class = null;
$students = null;
$is_locked = false;
$marking_scheme = null;

if ($selected_class_id > 0) {
    foreach ($teacher_classes as $class) {
        if (intval($class['class_id']) === $selected_class_id) {
            $selected_class = $class;
            $is_locked = (intval($class['locked']) === 1);
            break;
        }
    }
    
    if ($selected_class) {
        $scheme_row = dbFetchOne(
            $conn,
            "SELECT * FROM marking_schemes WHERE teacher_id = ? AND class_id = ? AND semester_id = ?",
            "iii",
            [$teacher_id, intval($selected_class['class_id']), $semester_id]
        );
        if ($scheme_row) {
            $marking_scheme = $scheme_row;
        } else {
            $marking_scheme = [
                'component1_name' => 'Assignment', 'component1_percentage' => 20,
                'component2_name' => 'Participation', 'component2_percentage' => 20,
                'component3_name' => 'Attendance', 'component3_percentage' => 10,
                'component4_name' => 'Mid Exam', 'component4_percentage' => 25,
                'component5_name' => 'Final Exam', 'component5_percentage' => 25
            ];
        }
        
        $marks_query = "SELECT s.*, m.id as mark_id,
                        m.assignment, m.participation, m.attendance, m.mid, m.final, m.total
                        FROM students s
                        LEFT JOIN marks m ON s.id = m.student_id 
                            AND m.semester_id = ? AND m.teacher_id = ?
                        WHERE s.class_id = ? AND (s.is_deleted = 0 OR s.is_deleted IS NULL)
                        ORDER BY s.name";
        $students = dbQuery($conn, $marks_query, "iii", [$semester_id, $teacher_id, intval($selected_class['class_id'])]);
    }
}

// Handle AJAX save
if (isset($_POST['ajax_save_marks'])) {
    header('Content-Type: application/json');
    $response = ['success' => false, 'message' => ''];
    
    if ($is_locked) {
        $response['message'] = 'locked';
        echo json_encode($response);
        exit();
    }
    
    $student_id = intval($_POST['student_id'] ?? 0);
    $field = trim($_POST['field'] ?? '');
    $value = floatval($_POST['value'] ?? 0);
    $class_id = intval($_POST['class_id'] ?? 0);
    $sem_id = intval($_POST['semester_id'] ?? 0);
    $t_id = intval($_SESSION['user_id'] ?? 0);

    // IDOR Protection: verify teacher assignment & lock status
    $tc_check = dbFetchOne(
        $conn,
        "SELECT locked FROM teacher_class WHERE teacher_id = ? AND class_id = ? AND semester_id = ?",
        "iii",
        [$t_id, $class_id, $sem_id]
    );
    if (!$tc_check && !isAdmin()) {
        $response['message'] = 'unauthorized_class';
        echo json_encode($response);
        exit();
    }
    if ($tc_check && intval($tc_check['locked']) === 1 && !isAdmin()) {
        $response['message'] = 'locked';
        echo json_encode($response);
        exit();
    }
    // Check if semester is closed
    $sem_status = dbFetchOne($conn, "SELECT status FROM semesters WHERE id = ?", "i", [$sem_id]);
    if (!$sem_status || ($sem_status['status'] === 'closed' && !isAdmin())) {
        $response['message'] = 'semester_closed';
        echo json_encode($response);
        exit();
    }

    // IDOR Protection: verify student belongs to this class
    $student_check = dbFetchOne(
        $conn,
        "SELECT id FROM students WHERE id = ? AND class_id = ? AND (is_deleted = 0 OR is_deleted IS NULL)",
        "ii",
        [$student_id, $class_id]
    );
    if (!$student_check) {
        $response['message'] = 'invalid_student';
        echo json_encode($response);
        exit();
    }
    
    $max_values = [
        'assignment' => $marking_scheme['component1_percentage'] ?? 20,
        'participation' => $marking_scheme['component2_percentage'] ?? 20,
        'attendance' => $marking_scheme['component3_percentage'] ?? 10,
        'mid' => $marking_scheme['component4_percentage'] ?? 25,
        'final' => $marking_scheme['component5_percentage'] ?? 25
    ];
    
    if (!isset($max_values[$field])) {
        $response['message'] = 'invalid field';
        echo json_encode($response);
        exit();
    }
    
    $max = $max_values[$field];
    $value = min(max($value, 0), $max);
    
    $existing = dbFetchOne(
        $conn,
        "SELECT id, assignment, participation, attendance, mid, final FROM marks 
         WHERE student_id = ? AND semester_id = ? AND teacher_id = ?",
        "iii",
        [$student_id, $sem_id, $t_id]
    );
    
    $assignment = ($field === 'assignment') ? $value : ($existing ? floatval($existing['assignment']) : 0);
    $participation = ($field === 'participation') ? $value : ($existing ? floatval($existing['participation']) : 0);
    $attendance = ($field === 'attendance') ? $value : ($existing ? floatval($existing['attendance']) : 0);
    $mid = ($field === 'mid') ? $value : ($existing ? floatval($existing['mid']) : 0);
    $final = ($field === 'final') ? $value : ($existing ? floatval($existing['final']) : 0);
    $total = $assignment + $participation + $attendance + $mid + $final;
    
    $saved = dbExecute(
        $conn,
        "INSERT INTO marks (student_id, class_id, teacher_id, semester_id, assignment, participation, attendance, mid, final, total, last_updated)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
         ON DUPLICATE KEY UPDATE 
            assignment = VALUES(assignment),
            participation = VALUES(participation),
            attendance = VALUES(attendance),
            mid = VALUES(mid),
            final = VALUES(final),
            total = VALUES(total),
            class_id = VALUES(class_id),
            last_updated = NOW()",
        "iiiidddddd",
        [$student_id, $class_id, $t_id, $sem_id, $assignment, $participation, $attendance, $mid, $final, $total]
    );
    
    echo json_encode(['success' => (bool)$saved, 'total' => $total]);
    exit();
}
// Handle marking scheme save
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_scheme'])) {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $_SESSION['error'] = "የደህንነት ማረጋገጫ አልተሳካም!";
    } else {
        $class_id = intval($_POST['class_id'] ?? 0);

        // IDOR Protection: check teacher assignment
        $tc_check = dbFetchOne(
            $conn,
            "SELECT id FROM teacher_class WHERE teacher_id = ? AND class_id = ? AND semester_id = ?",
            "iii",
            [$teacher_id, $class_id, $semester_id]
        );
        if (!$tc_check && !isAdmin()) {
            $_SESSION['error'] = "ለዚህ ክፍል የውጤት አሰጣጥ ዘዴ የመቀየር ፈቃድ የለዎትም!";
            header("Location: dashboard_teacher.php");
            exit();
        }

        $c1_perc = floatval($_POST['c1_perc'] ?? 0);
        $c2_perc = floatval($_POST['c2_perc'] ?? 0);
        $c3_perc = floatval($_POST['c3_perc'] ?? 0);
        $c4_perc = floatval($_POST['c4_perc'] ?? 0);
        $c5_perc = floatval($_POST['c5_perc'] ?? 0);
        $total = $c1_perc + $c2_perc + $c3_perc + $c4_perc + $c5_perc;
        
        if (abs($total - 100) > 0.01) {
            $_SESSION['error'] = "ጠቅላላ መቶኛ 100% መሆን አለበት! አሁን: " . $total . "%";
        } else {
            $c1_name = trim($_POST['c1_name'] ?? 'Assignment');
            $c2_name = trim($_POST['c2_name'] ?? 'Participation');
            $c3_name = trim($_POST['c3_name'] ?? 'Attendance');
            $c4_name = trim($_POST['c4_name'] ?? 'Mid Exam');
            $c5_name = trim($_POST['c5_name'] ?? 'Final Exam');
            
            $saved = dbExecute(
                $conn,
                "INSERT INTO marking_schemes (teacher_id, class_id, semester_id, 
                          component1_name, component1_percentage, component2_name, component2_percentage,
                          component3_name, component3_percentage, component4_name, component4_percentage,
                          component5_name, component5_percentage)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE
                    component1_name = VALUES(component1_name), component1_percentage = VALUES(component1_percentage),
                    component2_name = VALUES(component2_name), component2_percentage = VALUES(component2_percentage),
                    component3_name = VALUES(component3_name), component3_percentage = VALUES(component3_percentage),
                    component4_name = VALUES(component4_name), component4_percentage = VALUES(component4_percentage),
                    component5_name = VALUES(component5_name), component5_percentage = VALUES(component5_percentage)",
                "iiisdsdsdsdsd",
                [
                    $teacher_id, $class_id, $semester_id,
                    $c1_name, $c1_perc, $c2_name, $c2_perc,
                    $c3_name, $c3_perc, $c4_name, $c4_perc,
                    $c5_name, $c5_perc
                ]
            );
            
            if ($saved) {
                $_SESSION['success'] = "የውጤት አሰጣጥ ዘዴ ተቀምጧል!";
            } else {
                $_SESSION['error'] = "ስህተት ተከስቷል!";
            }
        }
    }
    header("Location: dashboard_teacher.php?class_id=$class_id");
    exit();
}
$nav_active = 'dashboard_teacher';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="theme-color" content="#8B4513">
    <title>የመምህር ዳሽቦርድ | አጸደ ትጉሃን</title>
    <?php include 'pwa_head.php'; ?>
    <style>
        :root {
            --brown-dark: #8B4513; --brown-medium: #A52A2A; --gold-primary: #FFD700;
            --gold-dark: #DAA520; --gold-pale: #FFF8DC; --bg-cream: #FAF9F6;
            --success-green: #10B981; --error-red: #EF4444; --warning-yellow: #F59E0B;
            --info-blue: #3B82F6; --purple: #8B5CF6;
        }
        * { margin: 0; padding: 0; box-sizing: border-box; -webkit-tap-highlight-color: transparent; }
        body { background: var(--bg-cream); font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; padding-bottom: 20px; }

        .app-header { background: linear-gradient(135deg, #8B4513 0%, #A52A2A 100%); padding: 12px 16px; position: sticky; top: 0; z-index: 100; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .header-content { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px; }
        
        .header-left { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }
        .logo-wrapper { display: flex; align-items: center; gap: 8px; }
        
        .teacher-photo-btn {
            width: 42px; height: 42px; border-radius: 50%; overflow: hidden;
            border: 3px solid #FFD700; background: white; cursor: pointer;
            transition: all 0.3s; flex-shrink: 0; padding: 0;
        }
        .teacher-photo-btn:hover { transform: scale(1.1); border-color: #FFF; }
        .teacher-photo-btn img { width: 100%; height: 100%; object-fit: cover; }
        
        .logo-circle { width: 42px; height: 42px; background: linear-gradient(135deg, #FFD700, #DAA520); border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 22px; color: #8B4513; border: 2px solid white; }
        .title h1 { color: #FFD700; font-size: 14px; }
        .title p { color: rgba(255,255,255,0.8); font-size: 10px; }

        .header-links { 
    display: flex; 
    gap: 8px; 
}

.header-link {
    padding: 8px 16px; 
    border-radius: 25px; 
    text-decoration: none;
    font-size: 12px; 
    font-weight: 700; 
    white-space: nowrap;
    transition: all 0.3s; 
    border: 2px solid transparent;
    letter-spacing: 0.3px;
}

.header-link.attendance-link { 
    background: #FFD700; 
    color: #8B4513; 
    border-color: #DAA520;
    box-shadow: 0 2px 8px rgba(255,215,0,0.3);
}

.header-link.attendance-link:hover { 
    background: #FFF; 
    color: #8B4513; 
    border-color: #FFD700;
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(255,215,0,0.5);
}

.header-link.scheme-link { 
    background: #10B981; 
    color: #FFFFFF; 
    border-color: #059669;
    box-shadow: 0 2px 8px rgba(16,185,129,0.3);
}

.header-link.scheme-link:hover { 
    background: #FFF; 
    color: #059669; 
    border-color: #10B981;
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(16,185,129,0.5);
}

        .user-info { display: flex; align-items: center; gap: 10px; }
        .user-name { text-align: right; background: rgba(0,0,0,0.3); padding: 5px 12px; border-radius: 20px; }
        .user-name strong { color: #FFD700; font-size: 12px; }
        .user-name span { font-size: 9px; opacity: 0.8; }
        .logout-btn { background: #8B4513; color: #FFD700; border: 1px solid #DAA520; padding: 6px 12px; border-radius: 20px; text-decoration: none; font-size: 12px; font-weight: bold; }

        .container { padding: 15px; max-width: 800px; margin: 0 auto; }

        .alert-success, .alert-error { padding: 12px 15px; border-radius: 12px; margin-bottom: 15px; font-size: 13px; display: flex; align-items: center; gap: 10px; }
        .alert-success { background: #D1FAE5; color: #065F46; border-left: 4px solid #10B981; }
        .alert-error { background: #FEE2E2; color: #991B1B; border-left: 4px solid #EF4444; }

        .classes-bar { background: white; border-radius: 16px; padding: 15px; margin-bottom: 15px; border: 2px solid #FFD700; }
        .classes-bar h3 { color: #8B4513; font-size: 14px; margin-bottom: 10px; display: flex; align-items: center; gap: 8px; }
        .class-buttons { display: flex; gap: 10px; overflow-x: auto; padding-bottom: 5px; -webkit-overflow-scrolling: touch; }
        .btn-class { background: white; color: #8B4513; border: 2px solid #DAA520; padding: 10px 18px; border-radius: 40px; font-weight: 600; text-decoration: none; white-space: nowrap; font-size: 13px; display: inline-flex; align-items: center; gap: 6px; }
        .btn-class.active { background: #FFD700; border-color: #8B4513; }
        .btn-class.locked { background: #FEE2E2; border-color: #EF4444; color: #EF4444; }
        .student-badge { background: #8B4513; color: white; padding: 2px 8px; border-radius: 20px; font-size: 10px; }

        .class-info-card { background: white; border-radius: 16px; padding: 15px; margin-bottom: 15px; border: 2px solid #FFD700; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px; }
        .class-details h2 { color: #8B4513; font-size: 18px; margin-bottom: 5px; }
        .class-details p { color: #A52A2A; font-size: 12px; display: flex; gap: 15px; flex-wrap: wrap; }
        .semester-badge { background: linear-gradient(135deg, #FFD700, #DAA520); color: #8B4513; padding: 6px 15px; border-radius: 30px; font-size: 11px; font-weight: bold; }

        .scheme-editor { background: linear-gradient(135deg, #FEF3C7, #FFF8DC); border-radius: 16px; padding: 15px; margin-bottom: 15px; border: 2px solid #FFD700; }
        .scheme-header { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px; margin-bottom: 15px; }
        .scheme-header h3 { color: #8B4513; font-size: 14px; display: flex; align-items: center; gap: 8px; }
        .total-percentage { background: #8B4513; color: #FFD700; padding: 5px 12px; border-radius: 20px; font-size: 12px; font-weight: bold; }
        .scheme-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(140px, 1fr)); gap: 10px; margin-bottom: 15px; }
        .scheme-item { background: white; padding: 10px; border-radius: 12px; }
        .scheme-item label { display: block; color: #8B4513; font-weight: 600; font-size: 11px; margin-bottom: 5px; }
        .scheme-item input { width: 100%; padding: 8px; border: 2px solid #E2E8F0; border-radius: 8px; font-size: 13px; }
        .scheme-item input:focus { outline: none; border-color: #FFD700; }
        .btn-save-scheme { background: linear-gradient(135deg, #10B981, #059669); color: white; padding: 10px 20px; border: none; border-radius: 30px; font-weight: bold; font-size: 13px; width: 100%; cursor: pointer; }

        .lock-warning { background: #FEE2E2; border: 2px solid #EF4444; border-radius: 12px; padding: 12px; margin-bottom: 15px; display: flex; align-items: center; gap: 10px; flex-wrap: wrap; font-size: 12px; }
        .admin-contact { background: white; padding: 5px 12px; border-radius: 20px; font-size: 11px; font-weight: bold; }

        .students-container { display: flex; flex-direction: column; gap: 12px; }
        .student-card { background: white; border-radius: 16px; padding: 15px; border: 2px solid #FFD700; box-shadow: 0 2px 8px rgba(0,0,0,0.04); transition: all 0.2s; }
        .student-card:active { transform: scale(0.98); }
        .student-card-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; padding-bottom: 8px; border-bottom: 1px solid #FFD700; }
        .student-name { color: #8B4513; font-weight: bold; font-size: 15px; display: flex; align-items: center; gap: 8px; }
        .student-number { background: #8B4513; color: #FFD700; width: 28px; height: 28px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 12px; font-weight: bold; flex-shrink: 0; }
        .student-phone { font-size: 10px; color: #666; margin-left: 5px; font-weight: normal; }
        .total-score { background: #FFD700; color: #8B4513; padding: 6px 14px; border-radius: 30px; font-weight: bold; font-size: 14px; white-space: nowrap; }
        .marks-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; margin-bottom: 10px; }
        .mark-item { background: #FFF8DC; border-radius: 12px; padding: 8px 10px; }
        .mark-label { font-size: 10px; color: #8B4513; font-weight: 600; margin-bottom: 4px; }
        .mark-label small { color: #A52A2A; font-size: 9px; }
        .mark-input { width: 100%; padding: 8px; border: 2px solid #DAA520; border-radius: 8px; font-size: 14px; text-align: center; font-weight: bold; background: white; color: #8B4513; }
        .mark-input:focus { outline: none; border-color: #FFD700; box-shadow: 0 0 0 3px rgba(255,215,0,0.2); }
        .mark-input:read-only { background: #E2E8F0; cursor: not-allowed; }
        .student-status { text-align: center; margin-top: 5px; }
        .status-badge { display: inline-block; padding: 4px 12px; border-radius: 20px; font-size: 10px; font-weight: 600; }
        .status-success { background: #D1FAE5; color: #10B981; }
        .status-warning { background: #FEF3C7; color: #F59E0B; }

        .empty-state { text-align: center; padding: 40px 20px; background: white; border-radius: 16px; border: 2px dashed #FFD700; }
        .empty-state span { font-size: 48px; display: block; margin-bottom: 10px; }

        .save-indicator { position: fixed; bottom: 20px; left: 50%; transform: translateX(-50%); background: #10B981; color: white; padding: 10px 20px; border-radius: 40px; font-size: 13px; display: none; align-items: center; gap: 10px; z-index: 1000; white-space: nowrap; box-shadow: 0 5px 20px rgba(0,0,0,0.2); }
        .save-indicator.show { display: flex; animation: slideUp 0.3s ease; }
        @keyframes slideUp { from { transform: translateX(-50%) translateY(20px); opacity: 0; } to { transform: translateX(-50%) translateY(0); opacity: 1; } }
        .spinner { width: 16px; height: 16px; border: 2px solid white; border-top-color: transparent; border-radius: 50%; animation: spin 1s linear infinite; }
        @keyframes spin { to { transform: rotate(360deg); } }

        /* Modal Styles */
        .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.6); z-index: 2000; justify-content: center; align-items: center; padding: 15px; }
        .modal.show { display: flex; }
        
        .profile-modal-content {
            background: white; width: 100%; max-width: 380px; border-radius: 20px;
            border: 3px solid #FFD700; text-align: center; overflow: hidden;
            animation: popIn 0.3s ease;
        }
        @keyframes popIn { from { transform: scale(0.8); opacity: 0; } to { transform: scale(1); opacity: 1; } }
        
        .profile-modal-header {
            background: linear-gradient(135deg, #8B4513, #A52A2A);
            color: white; padding: 30px 20px 20px;
        }
        .profile-photo-large {
            width: 100px; height: 100px; border-radius: 50%; border: 4px solid #FFD700;
            margin: 0 auto 12px; overflow: hidden; background: white;
        }
        .profile-photo-large img { width: 100%; height: 100%; object-fit: cover; }
        .profile-modal-name { font-size: 20px; color: #FFD700; font-weight: bold; margin-bottom: 4px; }
        .profile-modal-username { font-size: 13px; opacity: 0.8; margin-bottom: 8px; }
        .profile-modal-role { 
            display: inline-block; background: rgba(255,255,255,0.2); padding: 3px 15px;
            border-radius: 15px; font-size: 11px;
        }
        
        .profile-modal-body { padding: 20px; }
        .profile-info-row {
            display: flex; align-items: center; gap: 12px; padding: 12px 0;
            border-bottom: 1px solid #F3F4F6; text-align: left;
        }
        .profile-info-icon { font-size: 20px; width: 30px; text-align: center; }
        .profile-info-label { font-size: 10px; color: #999; text-transform: uppercase; }
        .profile-info-value { font-size: 14px; color: #8B4513; font-weight: 600; }
        
        .profile-modal-footer { padding: 15px 20px; border-top: 1px solid #F3F4F6; display: flex; gap: 8px; }
        .btn-modal {
            flex: 1; padding: 10px; border: none; border-radius: 25px;
            font-weight: 600; font-size: 12px; cursor: pointer; transition: all 0.2s;
        }
        .btn-close-modal { background: #E5E7EB; color: #666; }
        .btn-change-pass { background: #FEF3C7; color: #8B4513; }
        .btn-close-modal:hover { background: #D1D5DB; }
        .btn-change-pass:hover { background: #FDE68A; }

        /* Password Modal */
        .password-modal-content {
            background: white; width: 100%; max-width: 380px; border-radius: 20px;
            border: 3px solid #FFD700; padding: 25px; text-align: center;
            animation: popIn 0.3s ease;
        }
        .password-modal-content h3 { color: #8B4513; margin-bottom: 15px; }
        .pass-input {
            width: 100%; padding: 12px; border: 2px solid #E2E8F0; border-radius: 10px;
            font-size: 14px; margin-bottom: 10px; text-align: center;
        }
        .pass-input:focus { outline: none; border-color: #FFD700; }
        .pass-message { font-size: 12px; padding: 8px; border-radius: 8px; margin-bottom: 10px; display: none; }
        .pass-message.success { display: block; background: #D1FAE5; color: #065F46; }
        .pass-message.error { display: block; background: #FEE2E2; color: #991B1B; }
        
        .btn-save-pass {
            width: 100%; padding: 12px; background: linear-gradient(135deg, #FFD700, #DAA520);
            color: #8B4513; border: none; border-radius: 25px; font-weight: bold; font-size: 14px; cursor: pointer;
        }

        @media (max-width: 380px) { .marks-grid { grid-template-columns: 1fr; } }
        @media (min-width: 600px) { .marks-grid { grid-template-columns: repeat(5, 1fr); } }
    </style>
</head>
<body>
    <?php include 'mobile_nav.php'; ?>

    <div class="main-container">
        <?php if($password_message): ?><div class="alert-success">✅ <?php echo $password_message; ?></div><?php endif; ?>
        <?php if($success_message): ?><div class="alert-success">✅ <?php echo $success_message; ?></div><?php endif; ?>
        <?php if($error_message_display || $error_message): ?><div class="alert-error">⚠️ <?php echo $error_message_display ?: $error_message; ?></div><?php endif; ?>

        <?php if(!empty($teacher_classes) && $selected_class): ?>
            
            <div class="classes-bar">
                <h3>📚 የተመደቡባቸው ክፍሎች (<?php echo count($teacher_classes); ?>)</h3>
                <div class="class-buttons">
                    <?php foreach($teacher_classes as $class): ?>
                    <a href="?class_id=<?php echo $class['class_id']; ?>" class="btn-class <?php echo ($selected_class_id == $class['class_id']) ? 'active' : ''; ?> <?php echo $class['locked'] ? 'locked' : ''; ?>">
                        <?php echo $class['locked'] ? '🔒' : '📖'; ?> <?php echo $class['class_name']; ?>
                        <span class="student-badge"><?php echo $class['student_count']; ?></span>
                    </a>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="class-info-card">
                <div class="class-details">
                    <h2><?php echo $selected_class['class_name']; ?></h2>
                    <p>👨‍🏫 <?php echo $user_name; ?> | 👥 <?php echo $students ? mysqli_num_rows($students) : 0; ?> ተማሪዎች</p>
                </div>
                <div class="semester-badge"><?php echo $current_semester ? $current_semester['name'] : ''; ?></div>
            </div>

          <!--   <div class="scheme-editor">
                <div class="scheme-header">
                    <h3>⚙️ የውጤት አሰጣጥ ዘዴ</h3>
                    <div class="total-percentage">📊 <span id="totalPercent">100</span>%</div>
                </div>
                <form method="POST" id="schemeForm" onsubmit="return validateScheme()">
                    <input type="hidden" name="class_id" value="<?php echo $selected_class['class_id']; ?>">
                    <div class="scheme-grid">
                        <?php for($i=1; $i<=5; $i++): ?>
                        <div class="scheme-item">
                            <label><?php echo $i; ?>. <?php echo htmlspecialchars($marking_scheme["component{$i}_name"]); ?></label>
                            <input type="text" name="c<?php echo $i; ?>_name" value="<?php echo htmlspecialchars($marking_scheme["component{$i}_name"]); ?>">
                            <input type="number" name="c<?php echo $i; ?>_perc" id="c<?php echo $i; ?>_perc" value="<?php echo $marking_scheme["component{$i}_percentage"]; ?>" step="1" min="0" max="100" onchange="updateTotal()" style="margin-top:5px;">
                        </div>
                        <?php endfor; ?>
                    </div>
                    <button type="submit" name="save_scheme" class="btn-save-scheme">💾 ዘዴውን አስቀምጥ</button>
                </form>
            </div>
 -->
            <?php if($is_locked): ?>
            <div class="lock-warning">
                <span>🔒</span><strong>ክፍሉ ተቆልፏል!</strong>
                <span class="admin-contact">📞 <?php echo $admin1; ?>: <?php echo $phone1; ?></span>
            </div>
            <?php endif; ?>

            <div class="students-container">
                <?php 
                if($students && mysqli_num_rows($students) > 0):
                    $counter = 1;
                    while($student = mysqli_fetch_assoc($students)): 
                        $assignment = floatval($student['assignment'] ?? 0);
                        $participation = floatval($student['participation'] ?? 0);
                        $attendance = floatval($student['attendance'] ?? 0);
                        $mid = floatval($student['mid'] ?? 0);
                        $final = floatval($student['final'] ?? 0);
                        $total = $assignment + $participation + $attendance + $mid + $final;
                ?>
                <div class="student-card" data-student-id="<?php echo $student['id']; ?>">
                    <div class="student-card-header">
                        <div class="student-name">
                            <span class="student-number"><?php echo $counter++; ?></span>
                            <?php echo htmlspecialchars($student['name']); ?>
                            <span class="student-phone"><?php echo htmlspecialchars($student['parent_phone'] ?: ''); ?></span>
                        </div>
                        <div class="total-score" id="total-<?php echo $student['id']; ?>"><?php echo $total ? number_format($total, 1) : '0.0'; ?></div>
                    </div>
                    
                    <div class="marks-grid">
                        <?php 
                        $fields = ['assignment', 'participation', 'attendance', 'mid', 'final'];
                        foreach($fields as $idx => $field):
                            $fval = $field == 'assignment' ? $assignment : ($field == 'participation' ? $participation : ($field == 'attendance' ? $attendance : ($field == 'mid' ? $mid : $final)));
                        ?>
                        <div class="mark-item">
                            <div class="mark-label"><?php echo htmlspecialchars($marking_scheme["component".($idx+1)."_name"]); ?> <small>(<?php echo $marking_scheme["component".($idx+1)."_percentage"]; ?>%)</small></div>
                            <input type="number" step="1" min="0" max="<?php echo $marking_scheme["component".($idx+1)."_percentage"]; ?>" 
                                   value="<?php echo $fval > 0 ? $fval : ''; ?>" class="mark-input" 
                                   data-student="<?php echo $student['id']; ?>" data-field="<?php echo $field; ?>"
                                   data-max="<?php echo $marking_scheme["component".($idx+1)."_percentage"]; ?>"
                                   <?php echo $is_locked ? 'readonly' : ''; ?>>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <div class="student-status">
                        <?php if($total > 0): ?>
                            <span class="status-badge status-success">✅ ውጤት ገብቷል</span>
                        <?php else: ?>
                            <span class="status-badge status-warning">⏳ አልገባም</span>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endwhile; ?>
                <?php else: ?><div class="empty-state"><span>👥</span><p>ምንም ተማሪዎች የሉም</p></div><?php endif; ?>
            </div>

        <?php elseif(empty($teacher_classes)): ?>
        <div class="empty-state"><span>📚</span><p>ለዚህ ሴሚስተር ምንም ክፍል አልተመደበልዎትም</p></div>
        <?php endif; ?>
    </div>

    <!-- Profile Modal -->
    <div id="profileModal" class="modal">
        <div class="profile-modal-content">
            <div class="profile-modal-header">
                <div class="profile-photo-large">
                    <img src="<?php echo $teacher_photo; ?>" alt="Profile" onerror="this.src='images/icon.png'">
                </div>
                <div class="profile-modal-name"><?php echo htmlspecialchars($teacher_full_name); ?></div>
                <div class="profile-modal-username">@<?php echo htmlspecialchars($teacher_username); ?></div>
                <span class="profile-modal-role">👨‍🏫 መምህር</span>
            </div>
            <div class="profile-modal-body">
                <div class="profile-info-row">
                    <span class="profile-info-icon">👤</span>
                    <div>
                        <div class="profile-info-label">ሙሉ ስም</div>
                        <div class="profile-info-value"><?php echo htmlspecialchars($teacher_full_name); ?></div>
                    </div>
                </div>
                <div class="profile-info-row">
                    <span class="profile-info-icon">🔑</span>
                    <div>
                        <div class="profile-info-label">የተጠቃሚ ስም</div>
                        <div class="profile-info-value">@<?php echo htmlspecialchars($teacher_username); ?></div>
                    </div>
                </div>
                <div class="profile-info-row">
                    <span class="profile-info-icon">📱</span>
                    <div>
                        <div class="profile-info-label">ስልክ</div>
                        <div class="profile-info-value"><?php echo $teacher_phone ? htmlspecialchars($teacher_phone) : '---'; ?></div>
                    </div>
                </div>
            </div>
            <div class="profile-modal-footer">
                <button class="btn-modal btn-close-modal" onclick="closeProfileModal()">ዝጋ</button>
                <a href="teacher_profile.php" class="btn-modal" style="text-decoration:none; background:#8B4513; color:#FFD700; display:inline-flex; align-items:center; justify-content:center;">👤 ሙሉ መረጃ</a>
                <button class="btn-modal btn-change-pass" onclick="openPasswordModal()">🔒 የይለፍ ቃል ቀይር</button>
            </div>
        </div>
    </div>

    <!-- Password Change Modal -->
    <div id="passwordModal" class="modal">
        <div class="password-modal-content">
            <h3>🔒 የይለፍ ቃል ይቀይሩ</h3>
            <form method="POST" id="passwordForm">
                <?php echo csrfField(); ?>
                <input type="password" name="current_password" class="pass-input" placeholder="የአሁኑ የይለፍ ቃል" required>
                <input type="password" name="new_password" class="pass-input" placeholder="አዲስ የይለፍ ቃል" required minlength="3">
                <input type="password" name="confirm_password" class="pass-input" placeholder="አዲስ የይለፍ ቃል ያረጋግጡ" required minlength="3">
                <div class="pass-message <?php echo $password_error ? 'error' : ''; ?> <?php echo $password_message ? 'success' : ''; ?>" style="<?php echo ($password_error || $password_message) ? 'display:block;' : ''; ?>">
                    <?php echo $password_error ?: $password_message; ?>
                </div>
                <button type="submit" name="change_password" class="btn-save-pass">💾 አስቀምጥ</button>
            </form>
            <button class="btn-modal btn-close-modal" onclick="closePasswordModal()" style="margin-top:8px; width:100%;">ዝጋ</button>
        </div>
    </div>

    <div id="saveIndicator" class="save-indicator"><div class="spinner"></div><span>ውጤት ተቀምጧል...</span></div>

    <div id="lockModal" class="modal">
        <div class="profile-modal-content" style="max-width:320px;">
            <div class="profile-modal-header" style="padding:25px;">
                <span style="font-size:50px;">🔒</span>
                <div class="profile-modal-name" style="margin-top:10px;">ክፍሉ ተቆልፏል!</div>
                <p style="font-size:12px; opacity:0.8;">ውጤት ማስገባት አይቻልም።</p>
                <p style="font-size:12px; background:rgba(255,255,255,0.2); padding:8px; border-radius:8px; margin-top:10px;">
                    📞 <?php echo $admin1; ?>: <?php echo $phone1; ?>
                </p>
            </div>
            <div class="profile-modal-footer" style="justify-content:center;">
                <button class="btn-modal btn-close-modal" onclick="closeLockModal()" style="max-width:120px;">እሺ</button>
            </div>
        </div>
    </div>

    <script>
        const saveTimeouts = {};
        const isLocked = <?php echo $is_locked ? 'true' : 'false'; ?>;

        function updateTotal() {
            let total = 0;
            for(let i=1; i<=5; i++) total += parseFloat(document.getElementById('c'+i+'_perc')?.value) || 0;
            document.getElementById('totalPercent').textContent = total;
        }

        function validateScheme() {
            let total = 0;
            for(let i=1; i<=5; i++) total += parseFloat(document.getElementById('c'+i+'_perc')?.value) || 0;
            if(total !== 100) { alert('ማስጠንቀቂያ! ጠቅላላ መቶኛ 100% መሆን አለበት!\nአሁን: ' + total + '%'); return false; }
            return confirm('ማስጠንቀቂያ! የውጤት አሰጣጥ ዘዴ መቀየር ቀደም ሲል የገቡ ውጤቶች ላይ ተጽዕኖ ሊኖረው ይችላል። መቀጠል እርግጠኛ ነዎት?');
        }

        document.querySelectorAll('.mark-input').forEach(input => {
            input.addEventListener('input', function() {
                if(isLocked) { showLockModal(); this.value = this.defaultValue; return; }
                const studentId = this.dataset.student;
                const field = this.dataset.field;
                const max = parseFloat(this.dataset.max);
                let value = parseFloat(this.value) || 0;
                if(value > max) { this.value = max; value = max; }
                if(value < 0) { this.value = 0; value = 0; }
                updateStudentTotal(studentId);
                if(saveTimeouts[studentId]) clearTimeout(saveTimeouts[studentId]);
                document.getElementById('saveIndicator').classList.add('show');
                saveTimeouts[studentId] = setTimeout(() => saveMark(studentId, field, value), 800);
            });
        });

        function updateStudentTotal(studentId) {
            const card = document.querySelector(`.student-card[data-student-id="${studentId}"]`);
            if(!card) return;
            let total = 0;
            card.querySelectorAll('.mark-input').forEach(input => { total += parseFloat(input.value) || 0; });
            const totalSpan = document.getElementById(`total-${studentId}`);
            if(totalSpan) totalSpan.textContent = total.toFixed(1);
            const statusDiv = card.querySelector('.student-status');
            if(statusDiv) statusDiv.innerHTML = total > 0 ? '<span class="status-badge status-success">✅ ውጤት ገብቷል</span>' : '<span class="status-badge status-warning">⏳ አልገባም</span>';
        }

        function saveMark(studentId, field, value) {
            const ind = document.getElementById('saveIndicator');
            ind.classList.add('show');
            ind.querySelector('span').textContent = 'በማስቀመጥ ላይ...';

            function saveOfflineFallback() {
                const localUuid = (window.crypto && crypto.randomUUID) ? crypto.randomUUID() : 'mark_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9);
                const markObj = {
                    local_uuid: localUuid,
                    student_id: parseInt(studentId),
                    class_id: <?php echo $selected_class_id ?: 0; ?>,
                    semester_id: <?php echo $semester_id ?: 0; ?>,
                    [field]: parseFloat(value) || 0,
                    updated_at: new Date().toISOString()
                };
                OfflineDB.saveMarksLocal(markObj).then(() => {
                    ind.querySelector('span').textContent = 'ከመስመር ውጭ ተቀምጧል! 💾';
                    setTimeout(() => ind.classList.remove('show'), 1500);
                }).catch(err => {
                    ind.querySelector('span').textContent = 'ስህተት! ❌';
                    setTimeout(() => ind.classList.remove('show'), 2000);
                });
            }

            const fd = new FormData();
            fd.append('ajax_save_marks', '1'); fd.append('student_id', studentId);
            fd.append('field', field); fd.append('value', value);
            fd.append('class_id', <?php echo $selected_class_id ?: 0; ?>);
            fd.append('semester_id', <?php echo $semester_id ?: 0; ?>);
            fetch(window.location.href, { method: 'POST', body: fd })
            .then(r => r.json()).then(d => {
                if(d.success) { 
                    ind.querySelector('span').textContent = 'ውጤት ተቀምጧል! ✅'; 
                    setTimeout(() => ind.classList.remove('show'), 1500); 
                    SyncManager.fullSync();
                }
                else if(d.message === 'locked') { showLockModal(); ind.classList.remove('show'); }
                else { ind.querySelector('span').textContent = 'ስህተት! ❌'; setTimeout(() => ind.classList.remove('show'), 2000); }
            }).catch(() => { 
                saveOfflineFallback();
            });
        }

        // Profile Modal
        function openProfileModal() { document.getElementById('profileModal').classList.add('show'); }
        function closeProfileModal() { document.getElementById('profileModal').classList.remove('show'); }
        
        // Password Modal
        function openPasswordModal() { 
            closeProfileModal(); 
            document.getElementById('passwordModal').classList.add('show'); 
        }
        function closePasswordModal() { document.getElementById('passwordModal').classList.remove('show'); }

        // Lock Modal
        function showLockModal() { document.getElementById('lockModal').classList.add('show'); }
        function closeLockModal() { document.getElementById('lockModal').classList.remove('show'); }
        
        // Close modals on outside click
        window.onclick = function(e) {
            if(e.target == document.getElementById('profileModal')) closeProfileModal();
            if(e.target == document.getElementById('passwordModal')) closePasswordModal();
            if(e.target == document.getElementById('lockModal')) closeLockModal();
        };
        
        document.addEventListener('DOMContentLoaded', () => { 
            updateTotal(); 
            document.querySelectorAll('.student-card').forEach(c => { 
                const sid = c.dataset.studentId; 
                if(sid) updateStudentTotal(sid); 
            }); 
        });
    </script>
</body>
</html>
<?php mysqli_close($conn); ?>