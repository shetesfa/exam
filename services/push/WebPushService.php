<?php
require_once __DIR__ . '/VapidManager.php';
require_once __DIR__ . '/WebPushEncryption.php';

/**
 * WebPushService.php
 * Sends standard Web Push notifications to subscribed devices.
 */
class WebPushService {
    /**
     * Send a web push notification to a specific subscription record.
     *
     * @param mysqli $conn
     * @param array $sub [id, endpoint, p256dh, auth, user_id]
     * @param array $data [title, body, url, icon, tag]
     * @return array [success => bool, http_code => int, error => ?string]
     */
    public static function sendNotification($conn, array $sub, array $data): array {
        try {
            $vapidKeys = VapidManager::getKeys($conn);
            $payload = json_encode($data, JSON_UNESCAPED_UNICODE);

            $encrypted = WebPushEncryption::encrypt($payload, $sub['p256dh'], $sub['auth']);
            $vapidHeaders = WebPushEncryption::createVapidHeaders($sub['endpoint'], $vapidKeys);

            $httpHeaders = [];
            foreach (array_merge($encrypted['headers'], $vapidHeaders) as $k => $v) {
                $httpHeaders[] = "$k: $v";
            }

            $ch = curl_init($sub['endpoint']);
            curl_setopt_array($ch, [
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => $encrypted['body'],
                CURLOPT_HTTPHEADER => $httpHeaders,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 15,
                CURLOPT_SSL_VERIFYPEER => true
            ]);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);

            if ($curlError) {
                return ['success' => false, 'http_code' => $httpCode, 'error' => $curlError];
            }

            // 200, 201, 202 are success codes
            if ($httpCode >= 200 && $httpCode < 300) {
                if ($conn && !empty($sub['id'])) {
                    @dbExecute($conn, "UPDATE push_subscriptions SET last_used_at = NOW() WHERE id = ?", "i", [$sub['id']]);
                }
                return ['success' => true, 'http_code' => $httpCode, 'error' => null];
            }

            // 404 Not Found or 410 Gone means the subscription is no longer valid
            if ($httpCode === 404 || $httpCode === 410) {
                if ($conn && !empty($sub['id'])) {
                    @dbExecute($conn, "DELETE FROM push_subscriptions WHERE id = ?", "i", [$sub['id']]);
                }
                return ['success' => false, 'http_code' => $httpCode, 'expired' => true, 'error' => 'Subscription expired or unregistered.'];
            }

            return ['success' => false, 'http_code' => $httpCode, 'error' => "Push service responded with HTTP $httpCode: $response"];

        } catch (Exception $e) {
            return ['success' => false, 'http_code' => 0, 'error' => $e->getMessage()];
        }
    }

    /**
     * Send push notification to all devices belonging to a given user.
     */
    public static function sendToUser($conn, int $userId, array $data): array {
        if (!$conn || $userId <= 0) return ['sent' => 0, 'failed' => 0];

        $subs = dbFetchAll(
            $conn,
            "SELECT id, endpoint, p256dh, auth, user_id FROM push_subscriptions WHERE user_id = ?",
            "i",
            [$userId]
        );

        $sent = 0;
        $failed = 0;
        foreach ($subs as $sub) {
            $res = self::sendNotification($conn, $sub, $data);
            if ($res['success']) $sent++;
            else $failed++;
        }

        return ['sent' => $sent, 'failed' => $failed, 'total' => count($subs)];
    }

    /**
     * Send push notification to users targeted by division, grade, class, or user_id.
     */
    public static function sendToTarget($conn, array $targets, array $data): array {
        if (!$conn) return ['sent' => 0, 'failed' => 0];

        $userIds = [];
        if (empty($targets)) {
            // Everyone: all users who have subscriptions
            $rows = dbFetchAll($conn, "SELECT DISTINCT user_id FROM push_subscriptions");
            foreach ($rows as $r) $userIds[] = (int)$r['user_id'];
        } else {
            foreach ($targets as $t) {
                if (!empty($t['user_id'])) {
                    $userIds[] = (int)$t['user_id'];
                }
                if (!empty($t['class_id'])) {
                    // Teachers of this class
                    $tRows = dbFetchAll($conn, "SELECT DISTINCT teacher_id FROM teacher_class WHERE class_id = ?", "i", [$t['class_id']]);
                    foreach ($tRows as $tr) $userIds[] = (int)$tr['teacher_id'];
                    // Submitters of this class
                    $sRows = dbFetchAll($conn, "SELECT DISTINCT submitter_id FROM attendance_assignments WHERE class_id = ?", "i", [$t['class_id']]);
                    foreach ($sRows as $sr) $userIds[] = (int)$sr['submitter_id'];
                }
            }
        }

        $userIds = array_unique(array_filter($userIds));
        $totalSent = 0;
        $totalFailed = 0;

        foreach ($userIds as $uid) {
            $res = self::sendToUser($conn, $uid, $data);
            $totalSent += $res['sent'];
            $totalFailed += $res['failed'];
        }

        return ['sent' => $totalSent, 'failed' => $totalFailed, 'users' => count($userIds)];
    }
}
