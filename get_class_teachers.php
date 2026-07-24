<?php
session_start();
require_once 'db.php';
requireAdmin();

$class_id = isset($_GET['class_id']) ? intval($_GET['class_id']) : 0;
$semester_id = isset($_GET['semester_id']) ? intval($_GET['semester_id']) : 0;

if(!$class_id || !$semester_id) {
    echo json_encode(['success' => false, 'message' => 'Missing parameters']);
    exit();
}

$query = "SELECT DISTINCT u.id, u.name 
          FROM teacher_class tc
          JOIN users u ON tc.teacher_id = u.id
          WHERE tc.class_id = $class_id 
          AND tc.semester_id = $semester_id
          AND u.role = 'teacher'
          ORDER BY u.name";

$result = mysqli_query($conn, $query);
$teachers = [];

while($row = mysqli_fetch_assoc($result)) {
    $teachers[] = $row;
}

echo json_encode(['success' => true, 'teachers' => $teachers]);
?>