<?php
require_once 'db.php';
requireAdmin();

$message = '';
$error = '';

$current_semester = getCurrentSemester($conn);
$semester_id = $current_semester ? intval($current_semester['id']) : 0;

// Handle Add Assignment
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = "የደህንነት ማረጋገጫ አልተሳካም! እባክዎ እንደገና ይሞክሩ።";
    } else {
        if (isset($_POST['add_assignment'])) {
            $teacher_id = intval($_POST['teacher_id'] ?? 0);
            $class_id = intval($_POST['class_id'] ?? 0);
            $subject_id = intval($_POST['subject_id'] ?? 0);
            
            if ($teacher_id > 0 && $class_id > 0 && $subject_id > 0 && $semester_id > 0) {
                // Get subject name
                $sub_row = dbFetchOne($conn, "SELECT name FROM subjects WHERE id = ?", "i", [$subject_id]);
                $subject_name = $sub_row ? $sub_row['name'] : '';
                
                if (empty($subject_name)) {
                    $error = "እባክዎ ትክክለኛ የትምህርት ዓይነት ይምረጡ!";
                } else {
                    // Check if already assigned for this class and subject
                    $exists = dbFetchOne($conn, "SELECT id FROM teacher_class WHERE teacher_id = ? AND class_id = ? AND semester_id = ? AND subject_id = ?", "iiii", [$teacher_id, $class_id, $semester_id, $subject_id]);
                    
                    if ($exists) {
                        $error = "ይህ መምህር በዚህ ክፍልና የትምህርት ዓይነት ቀድሞውኑ ተመድቧል!";
                    } else {
                        $saved = dbExecute(
                            $conn,
                            "INSERT INTO teacher_class (teacher_id, class_id, subject_id, subject_name, semester_id, locked) VALUES (?, ?, ?, ?, ?, 0)",
                            "iiisi",
                            [$teacher_id, $class_id, $subject_id, $subject_name, $semester_id]
                        );
                        if ($saved) {
                            $message = "መምህር በተሳካ ሁኔታ ለክፍል ({$subject_name}) ተመድቧል!";
                        } else {
                            $error = "ስህተት ተከስቷል!";
                        }
                    }
                }
            } else {
                $error = "እባክዎ መምህር፣ ክፍል እና የትምህርት ዓይነት በትክክል ይምረጡ! የትምህርት ዓይነት መምረጥ ግዴታ ነው።";
            }
        }
        
        if (isset($_POST['remove_assignment'])) {
            $assignment_id = intval($_POST['assignment_id'] ?? 0);
            if ($assignment_id > 0) {
                $deleted = dbExecute($conn, "DELETE FROM teacher_class WHERE id = ?", "i", [$assignment_id]);
                if ($deleted) {
                    $message = "ምደባ ተሰርዟል!";
                } else {
                    $error = "ስህተት ተከስቷል!";
                }
            }
        }
    }
}

// Get all subjects
$all_subjects = getSubjects($conn);

// Get all teachers
$teachers_query = "SELECT * FROM users WHERE role='teacher' ORDER BY name";
$teachers = mysqli_query($conn, $teachers_query);

// Get all classes - this will show ALL classes even if no assignments
$classes_query = "SELECT * FROM classes ORDER BY name";
$classes = mysqli_query($conn, $classes_query);

// Get all assignments for current semester with subject
$assignments_list = dbFetchAll(
    $conn,
    "SELECT tc.*, u.name as teacher_name, c.name as class_name, c.id as class_id,
            COALESCE(s.name, tc.subject_name, '') as subject_name
     FROM teacher_class tc
     JOIN users u ON tc.teacher_id = u.id
     JOIN classes c ON tc.class_id = c.id
     LEFT JOIN subjects s ON tc.subject_id = s.id
     WHERE tc.semester_id = ?
     ORDER BY c.name, u.name",
    "i",
    [$semester_id]
);

// Group assignments by class
$class_assignments = [];
foreach ($assignments_list as $assignment) {
    $class_assignments[$assignment['class_name']][] = $assignment;
}

$classes_arr = [];
mysqli_data_seek($classes, 0);
while ($c = mysqli_fetch_assoc($classes)) {
    $classes_arr[] = $c;
}

$teacher_assignments_data = [];
mysqli_data_seek($teachers, 0);
while($teacher = mysqli_fetch_assoc($teachers)) {
    $tid = intval($teacher['id']);
    $assigned_classes = [];
    $total_classes = 0;

    foreach ($assignments_list as $a) {
        if (intval($a['teacher_id']) === $tid) {
            $assigned_classes[] = [
                'name'          => $a['class_name'],
                'subject'       => $a['subject_name'] ?? '',
                'locked'        => $a['locked'],
                'assignment_id' => $a['id']
            ];
            $total_classes++;
        }
    }

    $teacher_assignments_data[] = [
        'id'      => $tid,
        'name'    => $teacher['name'],
        'phone'   => $teacher['phone'] ?: '---',
        'classes' => $assigned_classes,
        'total'   => $total_classes
    ];
}

$nav_active = 'manage_assignments';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>ክፍል ምደባ | አጸደ ትጉሃን </title>
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
            --bg-light: #F8F9FA;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        body {
            background: var(--bg-light);
        }

        .header {
            background: linear-gradient(135deg, #8B4513 0%, #A52A2A 100%);
            color: white;
            padding: 20px 30px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
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
            box-shadow: 0 0 20px rgba(255,215,0,0.3);
        }

        .title h1 {
            font-size: 22px;
            color: var(--gold-primary);
            text-shadow: 2px 2px 4px rgba(0,0,0,0.2);
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
            box-shadow: 0 4px 10px rgba(0,0,0,0.05);
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
            border-left: 5px solid var(--success-green);
        }

        .error {
            background: #FEE2E2;
            color: var(--error-red);
            border-left: 5px solid var(--error-red);
        }

        .section {
            background: white;
            border-radius: 20px;
            padding: 25px;
            margin-bottom: 30px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.08);
            transition: all 0.3s;
        }

        .section:hover {
            box-shadow: 0 15px 40px rgba(139,69,19,0.1);
        }

        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 3px solid var(--gold-pale);
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

        .section-header h2 span {
            font-size: 28px;
        }

        .semester-info-card {
            background: linear-gradient(135deg, var(--gold-pale), white);
            border-radius: 15px;
            padding: 20px 25px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
            border: 2px solid var(--gold-primary);
            margin-bottom: 30px;
        }

        .semester-details h3 {
            color: var(--brown-dark);
            font-size: 20px;
            margin-bottom: 5px;
        }

        .semester-details p {
            color: #666;
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .total-badge {
            background: var(--brown-dark);
            color: var(--gold-primary);
            padding: 10px 20px;
            border-radius: 40px;
            font-weight: bold;
            font-size: 16px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .classes-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }

        .class-card {
            background: white;
            border-radius: 18px;
            padding: 20px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.05);
            border: 2px solid var(--gold-pale);
            transition: all 0.3s;
            position: relative;
            overflow: hidden;
        }

        .class-card::before {
            content: '📚';
            position: absolute;
            top: 10px;
            right: 10px;
            font-size: 50px;
            opacity: 0.1;
            transform: rotate(10deg);
        }

        .class-card:hover {
            transform: translateY(-5px);
            border-color: var(--gold-primary);
            box-shadow: 0 15px 30px rgba(218,165,32,0.15);
        }

        .class-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 2px solid var(--gold-pale);
            position: relative;
            z-index: 1;
        }

        .class-name {
            color: var(--brown-dark);
            font-size: 20px;
            font-weight: bold;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .teacher-count {
            background: var(--gold-primary);
            color: var(--brown-dark);
            padding: 5px 15px;
            border-radius: 25px;
            font-size: 13px;
            font-weight: bold;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .teachers-list {
            margin: 15px 0;
            max-height: 250px;
            overflow-y: auto;
            padding-right: 5px;
        }

        .teachers-list::-webkit-scrollbar {
            width: 5px;
        }

        .teachers-list::-webkit-scrollbar-track {
            background: #F1F1F1;
            border-radius: 10px;
        }

        .teachers-list::-webkit-scrollbar-thumb {
            background: var(--gold-primary);
            border-radius: 10px;
        }

        .teacher-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px 15px;
            margin: 8px 0;
            background: #F8F9FA;
            border-radius: 12px;
            border-left: 4px solid var(--gold-primary);
            transition: all 0.2s;
        }

        .teacher-item:hover {
            background: #F0F0F0;
            transform: translateX(5px);
        }

        .teacher-info {
            display: flex;
            align-items: center;
            gap: 12px;
            flex: 1;
        }

        .teacher-icon {
            font-size: 20px;
            width: 35px;
            height: 35px;
            background: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }

        .teacher-name {
            font-weight: 600;
            color: var(--brown-dark);
            font-size: 14px;
        }

        .teacher-status {
            font-size: 11px;
            padding: 3px 10px;
            border-radius: 15px;
            font-weight: 600;
        }

        .status-locked {
            background: #FEE2E2;
            color: var(--error-red);
        }

        .status-unlocked {
            background: #D1FAE5;
            color: var(--success-green);
        }

        .remove-btn {
            background: none;
            border: none;
            color: #999;
            cursor: pointer;
            font-size: 16px;
            padding: 5px 10px;
            border-radius: 8px;
            transition: all 0.2s;
        }

        .remove-btn:hover {
            background: #FEE2E2;
            color: var(--error-red);
        }

        .add-teacher-form {
            margin-top: 20px;
            padding-top: 20px;
            border-top: 2px dashed var(--gold-pale);
            display: flex;
            gap: 10px;
        }

        .add-teacher-form select {
            flex: 1;
            padding: 12px;
            border: 2px solid #E2E8F0;
            border-radius: 10px;
            font-size: 14px;
            background: white;
            cursor: pointer;
        }

        .add-teacher-form select:focus {
            outline: none;
            border-color: var(--gold-primary);
        }

        .add-teacher-form button {
            padding: 12px 25px;
            background: linear-gradient(135deg, #FFD700 0%, #DAA520 100%);
            color: var(--brown-dark);
            border: none;
            border-radius: 10px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s;
            white-space: nowrap;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .add-teacher-form button:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(218,165,32,0.3);
        }

        /* Teacher Table Styles */
        .teacher-table-container {
            border-radius: 16px;
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
            max-width: 100%;
            border: 2px solid var(--gold-pale);
            background: white;
            margin-top: 20px;
        }

        .teacher-table {
            width: 100%;
            border-collapse: collapse;
        }

        .teacher-table th {
            background: linear-gradient(135deg, var(--gold-pale), #FFE4B5);
            color: var(--brown-dark);
            font-weight: 600;
            font-size: 14px;
            padding: 16px 12px;
            text-align: left;
            border-bottom: 3px solid var(--gold-primary);
        }

        .teacher-table td {
            padding: 16px 12px;
            border-bottom: 1px solid #EDF2F7;
            vertical-align: middle;
        }

        .teacher-table tr:last-child td {
            border-bottom: none;
        }

        .teacher-table tbody tr {
            transition: all 0.2s;
        }

        .teacher-table tbody tr:hover {
            background: #FEF9E7;
        }

        .teacher-avatar {
            width: 40px;
            height: 40px;
            background: linear-gradient(135deg, var(--gold-primary), var(--gold-dark));
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            color: var(--brown-dark);
            font-size: 18px;
        }

        .teacher-info-cell {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .teacher-details {
            display: flex;
            flex-direction: column;
        }

        .teacher-name {
            font-weight: 600;
            color: var(--brown-dark);
            font-size: 15px;
        }

        .teacher-phone {
            font-size: 12px;
            color: #718096;
            display: flex;
            align-items: center;
            gap: 5px;
            margin-top: 3px;
        }

        .classes-badge-container {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            align-items: center;
        }

        .class-badge {
            background: #FEF3C7;
            color: var(--brown-dark);
            padding: 6px 14px;
            border-radius: 30px;
            font-size: 13px;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            border: 1px solid var(--gold-primary);
            transition: all 0.2s;
        }

        .class-badge:hover {
            background: var(--gold-pale);
            transform: translateY(-2px);
            box-shadow: 0 3px 8px rgba(218,165,32,0.2);
        }

        .class-badge.locked {
            background: #FEE2E2;
            border-color: var(--error-red);
            color: var(--error-red);
        }

        .badge-icon {
            width: 20px;
            height: 20px;
            background: var(--gold-primary);
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 11px;
        }

        .total-classes {
            background: var(--brown-dark);
            color: white;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            margin-left: 8px;
        }

        .no-classes {
            color: #A0AEC0;
            font-style: italic;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .no-classes span {
            font-size: 16px;
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            background: white;
            border-radius: 20px;
        }

        .empty-state span {
            font-size: 60px;
            display: block;
            margin-bottom: 20px;
        }

        .empty-state h3 {
            color: var(--brown-dark);
            margin-bottom: 10px;
            font-size: 24px;
        }

        .empty-state p {
            color: #718096;
            margin-bottom: 20px;
        }

        @media (min-width: 769px) {
            .section {
                padding: 16px 12px;
                border-radius: 14px;
            }
            .semester-info-card {
                padding: 14px;
                flex-direction: column;
                align-items: flex-start;
            }
            .classes-grid {
                grid-template-columns: 1fr;
            }
            .class-card {
                padding: 15px 12px;
            }
            .add-teacher-form {
                flex-direction: column;
            }
            .add-teacher-form button {
                width: 100%;
                justify-content: center;
            }
            .teacher-table th:nth-child(2),
            .teacher-table td:nth-child(2) {
                display: none;
            }
            .teacher-table th,
            .teacher-table td {
                padding: 10px 8px;
                font-size: 12px;
            }
            .class-badge {
                padding: 4px 10px;
                font-size: 12px;
            }
            .teacher-info-cell {
                flex-direction: column;
                align-items: flex-start;
                gap: 8px;
            }
        }

        .teacher-count.count-pale {
            background: var(--gold-pale);
            color: var(--brown-dark);
        }

        .subject-tag {
            background: #FEF3C7;
            border: 1px solid #F59E0B;
            color: #92400E;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 600;
            margin-left: 6px;
            display: inline-block;
        }

        /* Direct dark-mode overrides for page components */
        html.dark-mode body, body.dark-mode, [data-theme="dark"] body {
            background-color: #0B1120 !important;
        }
        html.dark-mode .section, body.dark-mode .section, [data-theme="dark"] .section {
            background-color: #1E293B !important;
            border: 1px solid #334155 !important;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.4) !important;
        }
        html.dark-mode .section-header {
            border-bottom-color: #334155 !important;
        }
        html.dark-mode .section-header h2 {
            color: #FCD34D !important;
        }
        html.dark-mode .semester-info-card {
            background: linear-gradient(135deg, #1E293B, #0F172A) !important;
            border-color: #F59E0B !important;
        }
        html.dark-mode .semester-details h3 {
            color: #FCD34D !important;
        }
        html.dark-mode .semester-details p {
            color: #CBD5E1 !important;
        }
        html.dark-mode .total-badge {
            background: #0F172A !important;
            color: #FCD34D !important;
            border: 1px solid #F59E0B !important;
        }
        html.dark-mode .class-card, body.dark-mode .class-card, [data-theme="dark"] .class-card {
            background-color: #1E293B !important;
            border-color: #334155 !important;
            color: #F1F5F9 !important;
        }
        html.dark-mode .class-card:hover {
            border-color: #F59E0B !important;
        }
        html.dark-mode .class-header {
            border-bottom-color: #334155 !important;
        }
        html.dark-mode .class-name {
            color: #FCD34D !important;
        }
        html.dark-mode .teacher-count,
        html.dark-mode .teacher-count.count-pale {
            background: #0F172A !important;
            color: #FCD34D !important;
            border: 1px solid #F59E0B !important;
        }
        html.dark-mode .teacher-item {
            background-color: #0F172A !important;
            border: 1px solid #334155 !important;
            border-left: 4px solid #F59E0B !important;
        }
        html.dark-mode .teacher-item:hover {
            background-color: #26354A !important;
        }
        html.dark-mode .teacher-icon {
            background-color: #1E293B !important;
            border: 1px solid #475569 !important;
            box-shadow: none !important;
        }
        html.dark-mode .teacher-name {
            color: #F8FAFC !important;
        }
        html.dark-mode .subject-tag {
            background: #78350F !important;
            border-color: #D97706 !important;
            color: #FDE68A !important;
        }
        html.dark-mode .teacher-status.status-locked {
            background-color: #7F1D1D !important;
            color: #FECACA !important;
        }
        html.dark-mode .teacher-status.status-unlocked {
            background-color: #064E3B !important;
            color: #A7F3D0 !important;
        }
        html.dark-mode .add-teacher-form {
            border-top: 2px dashed #475569 !important;
        }
        html.dark-mode .add-teacher-form select {
            background-color: #0F172A !important;
            color: #F8FAFC !important;
            border: 1.5px solid #475569 !important;
        }
        html.dark-mode .teacher-table-container, body.dark-mode .teacher-table-container, [data-theme="dark"] .teacher-table-container {
            background-color: #1E293B !important;
            border-color: #334155 !important;
        }
        html.dark-mode .teacher-table th {
            background: #0F172A !important;
            color: #FCD34D !important;
            border-bottom: 2px solid #F59E0B !important;
        }
        html.dark-mode .teacher-table td, body.dark-mode .teacher-table td, [data-theme="dark"] .teacher-table td {
            background-color: #1E293B !important;
            color: #E2E8F0 !important;
            border-bottom: 1px solid #334155 !important;
        }
        html.dark-mode .teacher-table tbody tr:hover {
            background-color: #26354A !important;
        }
        html.dark-mode .class-badge {
            background-color: #0F172A !important;
            color: #FCD34D !important;
            border-color: #F59E0B !important;
        }
        html.dark-mode .class-badge:hover {
            background-color: #334155 !important;
            color: #FFFFFF !important;
        }
        html.dark-mode .class-badge.locked {
            background-color: #3B1212 !important;
            border-color: #DC2626 !important;
            color: #FCA5A5 !important;
        }
        html.dark-mode .total-classes {
            background: #0F172A !important;
            color: #FCD34D !important;
            border: 1px solid #F59E0B !important;
        }
        html.dark-mode .empty-state {
            background-color: #1E293B !important;
            border: 1px solid #334155 !important;
            color: #F1F5F9 !important;
        }
        html.dark-mode .empty-state h3 {
            color: #FCD34D !important;
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

        <?php if($current_semester): ?>
        <!-- Semester Info -->
        <div class="semester-info-card">
            <div class="semester-details">
                <h3><?php echo htmlspecialchars($current_semester['name']); ?></h3>
                <p>
                    <span>📅 ንቁ ሴሚስተር</span>
                    <span>👨‍🏫 ጠቅላላ መምህራን: <?php echo mysqli_num_rows($teachers); ?></span>
                    <span>📚 ክፍሎች: <?php echo mysqli_num_rows($classes); ?></span>
                </p>
            </div>
            <div class="total-badge">
                <span>📋</span>
                <?php 
                $total_assignments = 0;
                foreach($class_assignments as $assignments) {
                    $total_assignments += count($assignments);
                }
                echo $total_assignments . ' ምደባዎች';
                ?>
            </div>
        </div>

        <!-- Class Cards - Shows ALL classes -->
        <div class="section">
            <div class="section-header">
                <h2>
                    <span>📚</span> 
                    ክፍሎች እና የተመደቡላቸው መምህራን
                </h2>
                <span class="teacher-count count-pale">
                    <?php echo mysqli_num_rows($classes); ?> ክፍሎች
                </span>
            </div>

            <div class="classes-grid">
                <?php 
                mysqli_data_seek($classes, 0);
                while($class = mysqli_fetch_assoc($classes)): 
                    $class_teachers = isset($class_assignments[$class['name']]) ? $class_assignments[$class['name']] : [];
                ?>
                <div class="class-card">
                    <div class="class-header">
                        <span class="class-name">
                            <span>📖</span>
                            <?php echo htmlspecialchars($class['name']); ?>
                        </span>
                        <span class="teacher-count">
                            <span>👥</span>
                            <?php echo count($class_teachers); ?> መምህራን
                        </span>
                    </div>

                    <div class="teachers-list">
                        <?php if(!empty($class_teachers)): ?>
                            <?php foreach($class_teachers as $teacher): ?>
                            <div class="teacher-item">
                                <div class="teacher-info">
                                    <span class="teacher-icon">👨‍🏫</span>
                                    <span class="teacher-name"><?php echo htmlspecialchars($teacher['teacher_name']); ?></span>
                                    <?php if(!empty($teacher['subject_name'])): ?>
                                    <span class="subject-tag">
                                        📖 <?php echo htmlspecialchars($teacher['subject_name']); ?>
                                    </span>
                                    <?php endif; ?>
                                    <?php if($teacher['locked']): ?>
                                    <span class="teacher-status status-locked">🔒 ተቆልፏል</span>
                                    <?php else: ?>
                                    <span class="teacher-status status-unlocked">🔓 ክፍት</span>
                                    <?php endif; ?>
                                </div>
                                <form method="POST" style="display: inline;" 
                                      onsubmit="return confirm('መምህሩን ከዚህ ክፍል ማስወገድ እርግጠኛ ነዎት?')">
                                    <?php echo csrfField(); ?>
                                    <input type="hidden" name="assignment_id" value="<?php echo $teacher['id']; ?>">
                                    <button type="submit" name="remove_assignment" class="remove-btn" title="አስወግድ">✕</button>
                                </form>
                            </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div style="text-align: center; padding: 20px; color: #A0AEC0;">
                                <span style="font-size: 30px; display: block; margin-bottom: 10px;">👥</span>
                                ምንም መምህራን አልተመደቡም
                            </div>
                        <?php endif; ?>
                    </div>

                    <form method="POST" class="add-teacher-form" style="display: flex; gap: 8px; flex-wrap: wrap;">
                        <?php echo csrfField(); ?>
                        <input type="hidden" name="class_id" value="<?php echo $class['id']; ?>">
                        <select name="teacher_id" style="flex: 2; min-width: 140px;" required>
                            <option value="">+ መምህር ምረጥ...</option>
                            <?php 
                            mysqli_data_seek($teachers, 0);
                            while($teacher = mysqli_fetch_assoc($teachers)): 
                            ?>
                            <option value="<?php echo $teacher['id']; ?>">
                                <?php echo htmlspecialchars($teacher['name']); ?>
                            </option>
                            <?php endwhile; ?>
                        </select>
                        <select name="subject_id" style="flex: 2; min-width: 140px;" required>
                            <option value="">* የትምህርት ዓይነት ምረጥ (ግዴታ)...</option>
                            <?php foreach($all_subjects as $sub): ?>
                                <option value="<?php echo $sub['id']; ?>">
                                    <?php echo htmlspecialchars($sub['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <button type="submit" name="add_assignment" style="padding: 10px 18px;">
                            <span>➕</span> መድብ
                        </button>
                    </form>
                </div>
                <?php endwhile; ?>
            </div>
        </div>

        <!-- Teacher Overview Table - Shows ALL teachers with their classes -->
        <div class="section">
            <div class="section-header">
                <h2>
                    <span>👨‍🏫</span> 
                    የመምህራን ምደባ ማጠቃለያ
                </h2>
                <span class="teacher-count count-pale">
                    <?php echo count($teacher_assignments_data); ?> መምህራን
                </span>
            </div>

            <div class="teacher-table-container table-responsive">
                <table class="teacher-table">
                    <thead>
                        <tr>
                            <th>መምህር</th>
                            <th>ስልክ</th>
                            <th>የተመደቡባቸው ክፍሎች</th>
                            <th style="text-align: center;">ቁጥር</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($teacher_assignments_data as $teacher): ?>
                        <tr>
                            <td>
                                <div class="teacher-info-cell">
                                    <div class="teacher-avatar">
                                        <?php echo mb_substr($teacher['name'], 0, 1); ?>
                                    </div>
                                    <div class="teacher-details">
                                        <span class="teacher-name">
                                            <?php echo htmlspecialchars($teacher['name']); ?>
                                        </span>
                                        <span class="teacher-phone">
                                            <span>📱</span>
                                            <?php echo htmlspecialchars($teacher['phone']); ?>
                                        </span>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <span style="color: #718096; font-size: 14px;">
                                    <?php echo htmlspecialchars($teacher['phone']); ?>
                                </span>
                            </td>
                            <td>
                                <div class="classes-badge-container">
                                    <?php if(!empty($teacher['classes'])): ?>
                                        <?php foreach($teacher['classes'] as $class): ?>
                                        <span class="class-badge <?php echo $class['locked'] ? 'locked' : ''; ?>">
                                            <span class="badge-icon">
                                                <?php echo $class['locked'] ? '🔒' : '📚'; ?>
                                            </span>
                                            <?php echo htmlspecialchars($class['name']); ?>
                                            <?php if(!empty($class['subject'])): ?>
                                                <span style="opacity: 0.85; font-size: 11px; margin-left: 3px;">(<?php echo htmlspecialchars($class['subject']); ?>)</span>
                                            <?php endif; ?>
                                        </span>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <span class="no-classes">
                                            <span>⏳</span>
                                            ክፍል አልተመደበም
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td style="text-align: center;">
                                <?php if($teacher['total'] > 0): ?>
                                <span class="total-classes">
                                    <?php echo $teacher['total']; ?> ክፍል
                                </span>
                                <?php else: ?>
                                <span style="color: #A0AEC0;">-</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <?php else: ?>
        <div class="empty-state">
            <span>📅</span>
            <h3>ምንም ንቁ ሴሚስተር የለም</h3>
            <p>እባክዎ መጀመሪያ ሴሚስተር ይክፈቱ</p>
            <a href="semester.php" class="nav-link" style="background: var(--gold-primary); color: var(--brown-dark); display: inline-flex; margin-top: 20px;">
                <span>➕</span> ሴሚስተር ክፈት
            </a>
        </div>
        <?php endif; ?>
    </div>
</body>
</html>
<?php mysqli_close($conn); ?>