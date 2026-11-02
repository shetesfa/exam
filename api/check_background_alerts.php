<?php
/**
 * api/check_background_alerts.php
 * Background Notification Checker for Service Worker (Periodic Sync / Background Polling)
 * Allows PWA to query for pending alerts without keeping any tabs open.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../db.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, no-store, must-revalidate');

if (!isset($conn) || !$conn) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database connection unavailable']);
    exit();
}

// 1. Authenticate via active session OR 60-day Remember-Me Cookie
$userId = $_SESSION['user_id'] ?? null;
if (!$userId && function_exists('checkRememberMeCookie')) {
    $remembered = @checkRememberMeCookie($conn);
    if ($remembered && !empty($remembered['user_id'])) {
        $userId = (int)$remembered['user_id'];
    }
}

$sinceId = isset($_GET['since_id']) ? (int)$_GET['since_id'] : 0;

// Also trigger lightweight automated alerts check in case cron is delayed
if (function_exists('checkAutomatedSchoolAlerts')) {
    @checkAutomatedSchoolAlerts($conn);
}

// 2. Fetch notifications
// If user is authenticated, fetch their targeted notifications.
// Otherwise, fetch general public/school-wide notifications.
$notifications = [];

if ($userId) {
    $notifRows = getNotificationsForUser($conn, $userId, false, 15);
    foreach ($notifRows as $n) {
        if ((int)$n['id'] > $sinceId) {
            $notifications[] = [
                'id' => (int)$n['id'],
                'title' => $n['title'],
                'body' => $n['message'],
                'url' => (!empty($n['related_page']) && strpos($n['related_page'], '/') === 0) 
                            ? $n['related_page'] 
                            : '/exam/notifications.php',
                'priority' => $n['priority'],
                'created_at' => $n['created_at'],
                'is_read' => !empty($n['read_at'])
            ];
        }
    }
} else {
    // Untargeted (school-wide) notifications only
    $rows = dbFetchAll(
        $conn,
        "SELECT n.id, n.title, n.message, n.related_page, n.priority, n.created_at
         FROM notifications n
         LEFT JOIN notification_targets nt ON n.id = nt.notification_id
         WHERE nt.id IS NULL AND n.id > ?
         ORDER BY n.id DESC LIMIT 10",
        "i",
        [$sinceId]
    );

    foreach ($rows as $n) {
        $notifications[] = [
            'id' => (int)$n['id'],
            'title' => $n['title'],
            'body' => $n['message'],
            'url' => (!empty($n['related_page']) && strpos($n['related_page'], '/') === 0) 
                        ? $n['related_page'] 
                        : '/exam/notifications.php',
            'priority' => $n['priority'],
            'created_at' => $n['created_at'],
            'is_read' => false
        ];
    }
}

echo json_encode([
    'success' => true,
    'user_id' => $userId,
    'count' => count($notifications),
    'notifications' => $notifications,
    'server_time' => date('Y-m-d H:i:s')
], JSON_UNESCAPED_UNICODE);
