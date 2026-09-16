<?php
/**
 * api/toggle_dark_mode.php
 * Persists Dark Mode preference per account (stored in database and session)
 * Modes: 0 = Auto (Ethiopian Day/Night), 1 = Manual Dark, 2 = Manual Light
 */
require_once __DIR__ . '/../db.php';

header('Content-Type: application/json; charset=utf-8');

$isStaff = function_exists('isLoggedIn') && isLoggedIn() && !empty($_SESSION['user_id']);
$isStudent = function_exists('isStudent') && isStudent() && !empty($_SESSION['student_id']);

$input = json_decode(file_get_contents('php://input'), true) ?? [];
$targetMode = null;

if (isset($input['mode'])) {
    $targetMode = (int)$input['mode'];
} elseif (isset($_POST['mode'])) {
    $targetMode = (int)$_POST['mode'];
} elseif (isset($input['dark_mode'])) {
    $targetMode = !empty($input['dark_mode']) ? 1 : 2;
} elseif (isset($_POST['dark_mode'])) {
    $targetMode = !empty($_POST['dark_mode']) ? 1 : 2;
}

if (!$isStaff && !$isStudent) {
    // Guest user (login screen or public page)
    if ($targetMode === null) {
        $cur = !empty($_SESSION['dark_mode']) ? (int)$_SESSION['dark_mode'] : (isEthiopianNightTime() ? 1 : 2);
        $targetMode = ($cur === 1) ? 2 : 1;
    }
    $_SESSION['dark_mode'] = $targetMode;
    echo json_encode([
        'success' => true,
        'mode' => $targetMode,
        'guest' => true,
        'message' => ($targetMode === 1) ? 'የጨለማ ገጽታ በርቷል!' : 'የብርሃን ገጽታ በርቷል!'
    ], JSON_UNESCAPED_UNICODE);
    exit();
}

$userId = $isStaff ? (int)$_SESSION['user_id'] : (int)$_SESSION['student_id'];
$userKey = ($isStaff ? 'user_' : 'student_') . $userId;

if ($targetMode === null) {
    // Toggle active state
    $currentActive = resolveUserDarkMode($conn, $userId, $isStudent);
    $targetMode = $currentActive ? 2 : 1;
}

if (!in_array($targetMode, [0, 1, 2], true)) {
    $targetMode = 1;
}

$saved = setUserDarkMode($conn, $userId, $targetMode, $isStudent);

echo json_encode([
    'success' => (bool)$saved,
    'mode' => $targetMode,
    'user_key' => $userKey,
    'is_dark_active' => ($targetMode === 1 || ($targetMode === 0 && isEthiopianNightTime())),
    'message' => ($targetMode === 1) ? 'የጨለማ ገጽታ በርቷል!' : (($targetMode === 2) ? 'የብርሃን ገጽታ በርቷል!' : 'በራስ-ሰር (በኢትዮጵያ ሰዓት) ተስተካክሏል!')
], JSON_UNESCAPED_UNICODE);
