<?php
require_once 'db.php';
requireLogin();

if (!isTeacher()) {
    header("Location: index.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_marks'])) {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $_SESSION['error'] = "የደህንነት ማረጋገጫ አልተሳካም! እባክዎ እንደገና ይሞክሩ።";
        header("Location: dashboard_teacher.php");
        exit();
    }
    
    $class_id = intval($_POST['class_id'] ?? 0);
    $semester_id = intval($_POST['semester_id'] ?? 0);
    $teacher_id = intval($_SESSION['user_id'] ?? 0);
    
    // IDOR Protection: verify teacher is assigned to this class
    $tc_check = dbFetchOne(
        $conn,
        "SELECT locked FROM teacher_class WHERE teacher_id = ? AND class_id = ? AND semester_id = ?",
        "iii",
        [$teacher_id, $class_id, $semester_id]
    );

    if (!$tc_check && !isAdmin()) {
        $_SESSION['error'] = "ለዚህ ክፍል ውጤት የማስገባት ፈቃድ የለዎትም!";
        header("Location: dashboard_teacher.php");
        exit();
    }
    
    if ($tc_check && intval($tc_check['locked']) === 1 && !isAdmin()) {
        $_SESSION['error'] = "ክፍሉ ተቆልፏል! ውጤት ማስተካከል አይቻልም። (Class is locked!)";
        header("Location: dashboard_teacher.php");
        exit();
    }
    
    // Check if semester is closed
    $sem_check = dbFetchOne($conn, "SELECT status FROM semesters WHERE id = ?", "i", [$semester_id]);
    if (!$sem_check || ($sem_check['status'] === 'closed' && !isAdmin())) {
        $_SESSION['error'] = "ሴሚስተሩ ተዘግቷል! ውጤት ማስተካከል አይቻልም። (Semester is closed!)";
        header("Location: dashboard_teacher.php");
        exit();
    }
    
    // Get marking scheme for this teacher, class, and semester
    $scheme = getMarkingScheme($conn, $teacher_id, $class_id, $semester_id);
    $max_assign = floatval($scheme['component1_percentage'] ?? 20);
    $max_part = floatval($scheme['component2_percentage'] ?? 20);
    $max_att = floatval($scheme['component3_percentage'] ?? 10);
    $max_mid = floatval($scheme['component4_percentage'] ?? 25);
    $max_final = floatval($scheme['component5_percentage'] ?? 25);
    
    $success_count = 0;
    $error_count = 0;
    
    // Process each student's marks
    if (isset($_POST['marks']) && is_array($_POST['marks'])) {
        foreach ($_POST['marks'] as $student_id => $marks) {
            $student_id = intval($student_id);
            // Validate student belongs to class
            $student_check = dbFetchOne(
                $conn,
                "SELECT id FROM students WHERE id = ? AND class_id = ? AND (is_deleted = 0 OR is_deleted IS NULL)",
                "ii",
                [$student_id, $class_id]
            );
            if (!$student_check) continue;
            
            $assignment = isset($marks['assignment']) && $marks['assignment'] !== '' ? floatval($marks['assignment']) : 0;
            $participation = isset($marks['participation']) && $marks['participation'] !== '' ? floatval($marks['participation']) : 0;
            $attendance = isset($marks['attendance']) && $marks['attendance'] !== '' ? floatval($marks['attendance']) : 0;
            $mid = isset($marks['mid']) && $marks['mid'] !== '' ? floatval($marks['mid']) : 0;
            $final = isset($marks['final']) && $marks['final'] !== '' ? floatval($marks['final']) : 0;
            
            // Validate ranges
            $assignment = min(max($assignment, 0), $max_assign);
            $participation = min(max($participation, 0), $max_part);
            $attendance = min(max($attendance, 0), $max_att);
            $mid = min(max($mid, 0), $max_mid);
            $final = min(max($final, 0), $max_final);
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
                    teacher_id = VALUES(teacher_id),
                    last_updated = NOW()",
                "iiiidddddd",
                [$student_id, $class_id, $teacher_id, $semester_id, $assignment, $participation, $attendance, $mid, $final, $total]
            );
            
            if ($saved) {
                $success_count++;
            } else {
                $error_count++;
            }
        }
        
        if ($error_count > 0) {
            $_SESSION['error'] = "የአንዳንድ ተማሪዎችን ውጤት በማስቀመጥ ላይ ስህተት ተከስቷል!";
        } else {
            $_SESSION['success'] = "የ{$success_count} ተማሪዎች ውጤት በትክክል ተቀምጧል!";
        }
    }
}

header("Location: dashboard_teacher.php");
exit();
?>