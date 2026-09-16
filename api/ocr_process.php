<?php
/**
 * api/ocr_process.php
 * Handles image upload and OCR text extraction for lesson plans.
 */
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../services/ocr/OCRService.php';

header('Content-Type: application/json; charset=utf-8');

if (!isLoggedIn() || (!isTeacher() && !isAdmin())) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'ያልተፈቀደ መዳረሻ!']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit();
}

// Verify CSRF token
$csrf = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if (!verifyCsrfToken($csrf)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'የደህንነት ማረጋገጫ አልተሳካም!']);
    exit();
}

if (empty($_FILES['photo']['name']) || $_FILES['photo']['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'እባክዎ ትክክለኛ የፎቶ ፋይል ይምረጡ!']);
    exit();
}

$teacherId = (int)($_SESSION['user_id'] ?? 0);
$result = OCRService::processUploadedPhoto($_FILES['photo'], $teacherId, __DIR__ . '/../uploads/lesson_plans/');

if ($result['success']) {
    auditLog($conn, 'ocr_lesson_plan_processed', 'lesson_plans', null, 'Provider: ' . $result['provider']);
}
echo json_encode($result);

