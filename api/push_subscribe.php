<?php
/**
 * api/push_subscribe.php
 * Handles browser Web Push subscription registration and VAPID key distribution.
 */
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../services/push/VapidManager.php';
require_once __DIR__ . '/../services/push/WebPushService.php';

header('Content-Type: application/json; charset=utf-8');

$action = $_GET['action'] ?? $_POST['action'] ?? '';

// Allow reading VAPID public key without login (needed during PWA init)
if ($action === 'vapid_public_key') {
    try {
        $keys = VapidManager::getKeys($conn);
        echo json_encode([
            'success' => true,
            'publicKey' => $keys['publicKey']
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit();
}

// All mutation actions require user login
if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'እባክዎ መጀመሪያ ይግቡ!']);
    exit();
}

$userId = (int)$_SESSION['user_id'];
$rawInput = file_get_contents('php://input');
$body = json_decode($rawInput, true) ?: $_POST;

if ($action === 'subscribe') {
    $endpoint = trim($body['endpoint'] ?? '');
    $p256dh = trim($body['p256dh'] ?? ($body['keys']['p256dh'] ?? ''));
    $auth = trim($body['auth'] ?? ($body['keys']['auth'] ?? ''));
    $deviceName = trim($body['device_name'] ?? ($_SERVER['HTTP_USER_AGENT'] ?? 'Web Browser'));

    if (empty($endpoint) || empty($p256dh) || empty($auth)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Missing subscription parameters']);
        exit();
    }

    $endpointHash = hash('sha256', $endpoint);

    // Upsert subscription
    $stmt = mysqli_prepare($conn, "INSERT INTO push_subscriptions 
              (user_id, endpoint, endpoint_hash, p256dh, auth, device_name, updated_at)
              VALUES (?, ?, ?, ?, ?, ?, NOW())
              ON DUPLICATE KEY UPDATE
                user_id = VALUES(user_id),
                p256dh = VALUES(p256dh),
                auth = VALUES(auth),
                device_name = VALUES(device_name),
                updated_at = NOW()");

    if (!$stmt) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => mysqli_error($conn)]);
        exit();
    }

    mysqli_stmt_bind_param($stmt, "isssss", $userId, $endpoint, $endpointHash, $p256dh, $auth, $deviceName);
    $ok = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    if ($ok) {
        auditLog($conn, 'push_subscription_registered', 'push_subscriptions', null, "Device: $deviceName");
        echo json_encode(['success' => true, 'message' => 'የቀጥታ የስልክ ማሳወቂያ በርቷል!']);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Failed to save subscription']);
    }
    exit();
}

if ($action === 'unsubscribe') {
    $endpoint = trim($body['endpoint'] ?? '');
    if (!empty($endpoint)) {
        $endpointHash = hash('sha256', $endpoint);
        dbExecute($conn, "DELETE FROM push_subscriptions WHERE endpoint_hash = ? AND user_id = ?", "si", [$endpointHash, $userId]);
    }
    echo json_encode(['success' => true, 'message' => 'ማሳወቂያ ጠፍቷል']);
    exit();
}

if ($action === 'test_push') {
    $res = WebPushService::sendToUser($conn, $userId, [
        'title' => 'አጸደ ትጉሃን የፈተሻ ማሳወቂያ',
        'body' => 'የስልክ ማሳወቂያዎ በትክክል እየሰራ ነው!',
        'url' => '/exam/notifications.php'
    ]);
    echo json_encode(['success' => true, 'result' => $res]);
    exit();
}

http_response_code(400);
echo json_encode(['success' => false, 'error' => 'Invalid action']);
