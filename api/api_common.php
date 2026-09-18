<?php
/**
 * api/api_common.php
 * Shared helpers and authentication for all /exam/api/*.php endpoints.
 */
if (file_exists(__DIR__ . '/../db.php')) {
    require_once __DIR__ . '/../db.php';
} elseif (file_exists(__DIR__ . '/../../db.php')) {
    require_once __DIR__ . '/../../db.php';
} else {
    require_once 'db.php';
}

header('Content-Type: application/json; charset=utf-8');

function json_out($data, $code = 200) {
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit();
}

function json_error($message, $code = 400) {
    json_out(['success' => false, 'message' => $message], $code);
}

/**
 * Resolves authentication from either:
 * 1. Bearer token in Authorization header (offline sync / API clients)
 * 2. Active PHP session (logged-in browser session)
 */
function auth_from_token($conn) {
    // 1. Check Bearer Authorization header or token query/body parameter
    $headers = function_exists('getallheaders') ? getallheaders() : [];
    $authHeader = $headers['Authorization'] ?? $headers['authorization'] 
        ?? $_SERVER['HTTP_AUTHORIZATION'] 
        ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] 
        ?? '';

    $extractedToken = '';
    if (preg_match('/Bearer\s+(.+)/i', $authHeader, $m)) {
        $extractedToken = trim($m[1]);
    } elseif (!empty($_GET['token'])) {
        $extractedToken = trim($_GET['token']);
    } elseif (!empty($_POST['token'])) {
        $extractedToken = trim($_POST['token']);
    }

    if (!empty($extractedToken)) {
        $token = mysqli_real_escape_string($conn, $extractedToken);
        $q = "SELECT * FROM auth_tokens WHERE token = '$token' AND revoked = 0 AND expires_at > NOW() LIMIT 1";
        $res = mysqli_query($conn, $q);
        if ($res && mysqli_num_rows($res) > 0) {
            $tokenRow = mysqli_fetch_assoc($res);
            $userId = (int)$tokenRow['user_id'];
            $role = $tokenRow['role'];

            if ($role === 'student') {
                $q2 = "SELECT id, name, class_id FROM students WHERE id = $userId LIMIT 1";
            } else {
                $q2 = "SELECT id, name, username, role FROM users WHERE id = $userId LIMIT 1";
            }
            $res2 = mysqli_query($conn, $q2);
            if ($res2 && mysqli_num_rows($res2) > 0) {
                $user = mysqli_fetch_assoc($res2);
                $user['role'] = $role;
                return $user;
            }
        }
    }

    // 2. Seamless fallback: Active PHP Session (web users calling sync while logged in)
    if (function_exists('isLoggedIn') && isLoggedIn()) {
        $userId = (int)($_SESSION['user_id'] ?? 0);
        $role = $_SESSION['role'] ?? '';
        if ($userId > 0) {
            $res = mysqli_query($conn, "SELECT id, name, username, role FROM users WHERE id = $userId LIMIT 1");
            if ($res && mysqli_num_rows($res) > 0) {
                $user = mysqli_fetch_assoc($res);
                $user['role'] = $role ?: $user['role'];
                return $user;
            }
        }
    } elseif (function_exists('isStudent') && isStudent()) {
        $studentId = (int)($_SESSION['student_id'] ?? 0);
        if ($studentId > 0) {
            $res = mysqli_query($conn, "SELECT id, name, class_id FROM students WHERE id = $studentId LIMIT 1");
            if ($res && mysqli_num_rows($res) > 0) {
                $user = mysqli_fetch_assoc($res);
                $user['role'] = 'student';
                return $user;
            }
        }
    }

    json_error('Invalid or expired token / unauthorized', 401);
}

function authenticateApiRequest($conn) {
    return auth_from_token($conn);
}

function read_json_body() {
    $raw = file_get_contents('php://input');
    if (empty($raw)) return [];
    $data = json_decode($raw, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        json_error('Invalid JSON body', 400);
    }
    return $data ?? [];
}
