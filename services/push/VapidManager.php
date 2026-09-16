<?php
/**
 * VapidManager.php
 * Manages VAPID (Voluntary Application Server Identification) keys for Web Push.
 */
class VapidManager {
    private static function base64UrlEncode(string $data): string {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private static function base64UrlDecode(string $data): string {
        return base64_decode(strtr($data, '-_', '+/'));
    }

    /**
     * Get or initialize VAPID keys.
     */
    public static function getKeys($conn): array {
        // 1. Check environment variables first
        $pubKey = getenv('VAPID_PUBLIC_KEY');
        $privKey = getenv('VAPID_PRIVATE_KEY');
        $subject = getenv('VAPID_SUBJECT') ?: 'mailto:admin@atsedesundayschool.org';

        if (!empty($pubKey) && !empty($privKey)) {
            return [
                'publicKey' => $pubKey,
                'privateKey' => $privKey,
                'subject' => $subject
            ];
        }

        // 2. Check settings table in database
        if ($conn) {
            $pubRow = dbFetchOne($conn, "SELECT setting_value FROM settings WHERE setting_key = 'vapid_public_key'");
            $privRow = dbFetchOne($conn, "SELECT setting_value FROM settings WHERE setting_key = 'vapid_private_key'");
            $subRow = dbFetchOne($conn, "SELECT setting_value FROM settings WHERE setting_key = 'vapid_subject'");

            if ($pubRow && $privRow && !empty($pubRow['setting_value']) && !empty($privRow['setting_value'])) {
                return [
                    'publicKey' => $pubRow['setting_value'],
                    'privateKey' => $privRow['setting_value'],
                    'subject' => $subRow['setting_value'] ?? $subject
                ];
            }
        }

        // 3. Generate new EC P-256 VAPID keypair
        $keys = self::generateVapidKeys();

        // Save generated keys into settings table for persistence
        if ($conn && !empty($keys['publicKey'])) {
            dbExecute($conn, "INSERT INTO settings (setting_key, setting_value) VALUES ('vapid_public_key', ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)", "s", [$keys['publicKey']]);
            dbExecute($conn, "INSERT INTO settings (setting_key, setting_value) VALUES ('vapid_private_key', ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)", "s", [$keys['privateKey']]);
            dbExecute($conn, "INSERT INTO settings (setting_key, setting_value) VALUES ('vapid_subject', ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)", "s", [$subject]);
        }

        return [
            'publicKey' => $keys['publicKey'],
            'privateKey' => $keys['privateKey'],
            'subject' => $subject
        ];
    }

    /**
     * Generate an EC P-256 key pair formatted for VAPID.
     */
    public static function generateVapidKeys(): array {
        $config = [];
        if (file_exists('C:/xampp/apache/conf/openssl.cnf')) {
            $config['config'] = 'C:/xampp/apache/conf/openssl.cnf';
        }

        $res = openssl_pkey_new(array_merge([
            'curve_name' => 'prime256v1',
            'private_key_type' => OPENSSL_KEYTYPE_EC,
        ], $config));

        if (!$res) {
            throw new Exception("OpenSSL EC key generation failed: " . openssl_error_string());
        }

        openssl_pkey_export($res, $privKeyPem, null, $config);
        $details = openssl_pkey_get_details($res);

        // Uncompressed EC P-256 public key is 0x04 followed by 32-byte X and 32-byte Y
        $x = $details['ec']['x'];
        $y = $details['ec']['y'];
        $rawPublicKey = "\x04" . $x . $y;

        // Raw 32-byte private key scalar
        $d = $details['ec']['d'];

        return [
            'publicKey' => self::base64UrlEncode($rawPublicKey),
            'privateKey' => self::base64UrlEncode($d),
            'privateKeyPem' => $privKeyPem
        ];
    }
}
