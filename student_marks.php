<?php
session_start();
require_once 'db.php';
requireLogin();

if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['save_marks'])) {
    $class_id = mysqli_real_escape_string($conn, $_POST['class_id']);
    $semester_id = mysqli_real_escape_string($conn, $_POST['semester_id']);
    $teacher_id = $_SESSION['user_id'];
    
    // Check if semester is locked
    $lock_check = "SELECT locked FROM teacher_class 
                   WHERE teacher_id = $teacher_id 
                   AND class_id = $class_id 
                   AND semester_id = $semester_id";
    $lock_result = mysqli_query($conn, $lock_check);
    $lock_data = mysqli_fetch_assoc($lock_result);
    
    if($lock_data && $lock_data['locked'] == 1) {
        $_SESSION['error'] = "ሴሚስተር ተዘግቷል! ውጤት ማስተካከል አይቻልም። (Semester is locked!)";
        header("Location: dashboard_teacher.php");
        exit();
    }
    
    $success_count = 0;
    $error_count = 0;
    
    // Process each student's marks
    if(isset($_POST['marks']) && is_array($_POST['marks'])) {
        foreach($_POST['marks'] as $student_id => $marks) {
            $student_id = mysqli_real_escape_string($conn, $student_id);
            $assignment = isset($marks['assignment']) && $marks['assignment'] !== '' ? 
                         floatval($marks['assignment']) : 0;
            $mid = isset($marks['mid']) && $marks['mid'] !== '' ? 
                   floatval($marks['mid']) : 0;
            $final = isset($marks['final']) && $marks['final'] !== '' ? 
                     floatval($marks['final']) : 0;
            
            // Validate ranges
            $assignment = min(max($assignment, 0), 20);
            $mid = min(max($mid, 0), 30);
            $final = min(max($final, 0), 50);
            
            $total = $assignment + $mid + $final;
            
            // Check if marks exist
            $check = "SELECT id FROM marks 
                      WHERE student_id = $student_id 
                      AND semester_id = $semester_id";
            $result = mysqli_query($conn, $check);
            
            if(mysqli_num_rows($result) > 0) {
                // Update existing marks
                $query = "UPDATE marks SET 
                         assignment = $assignment,
                         mid = $mid,
                         final = $final,
                         total = $total,
                         teacher_id = $teacher_id,
                         class_id = $class_id,
                         last_updated = NOW()
                         WHERE student_id = $student_id 
                         AND semester_id = $semester_id";
            } else {
                // Insert new marks
                $query = "INSERT INTO marks 
                         (student_id, class_id, teacher_id, semester_id, assignment, mid, final, total) 
                         VALUES 
                         ($student_id, $class_id, $teacher_id, $semester_id, $assignment, $mid, $final, $total)";
            }
            
            if(mysqli_query($conn, $query)) {
                $success_count++;
            } else {
                $error_count++;
            }
        }
        
        if($error_count > 0) {
            $_SESSION['error'] = "ማስቀመጥ ላይ ስህተት ተከስቷል! (Error saving some marks!)";
        } else {
            $_SESSION['success'] = "ውጤት በተሳካ ሁኔታ ተቀምጧል! ($success_count ተማሪዎች) (Marks saved successfully!)";
        }
    }
}

header("Location: dashboard_teacher.php");
exit();
?>