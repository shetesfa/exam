<?php
require_once __DIR__ . '/VapidManager.php';

/**
 * WebPushEncryption.php
 * Implements RFC 8291 (Message Encryption for Web Push) and RFC 8292 (VAPID).
 */
class WebPushEncryption {
    public static function base64UrlEncode(string $data): string {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    public static function base64UrlDecode(string $data): string {
        return base64_decode(strtr($data, '-_', '+/'));
    }

    /**
     * Convert DER ECDSA signature to IEEE P1363 (64 bytes raw R||S)
     */
    public static function derToP1363(string $der): string {
        $pos = 0;
        if (ord($der[$pos++]) !== 0x30) return '';
        $len = ord($der[$pos++]);
        if ($len & 0x80) {
            $n = $len & 0x7f;
            $pos += $n;
        }

        // R
        if (ord($der[$pos++]) !== 0x02) return '';
        $rLen = ord($der[$pos++]);
        $r = substr($der, $pos, $rLen);
        $pos += $rLen;
        $r = ltrim($r, "\x00");
        $r = str_pad($r, 32, "\x00", STR_PAD_LEFT);

        // S
        if (ord($der[$pos++]) !== 0x02) return '';
        $sLen = ord($der[$pos++]);
        $s = substr($der, $pos, $sLen);
        $s = ltrim($s, "\x00");
        $s = str_pad($s, 32, "\x00", STR_PAD_LEFT);

        return $r . $s;
    }

    /**
     * Generate VAPID Authorization header (RFC 8292).
     */
    public static function createVapidHeaders(string $endpoint, array $vapidKeys): array {
        $urlParts = parse_url($endpoint);
        $origin = $urlParts['scheme'] . '://' . $urlParts['host'] . (isset($urlParts['port']) ? ':' . $urlParts['port'] : '');

        $header = self::base64UrlEncode(json_encode(['typ' => 'JWT', 'alg' => 'ES256']));
        $payload = self::base64UrlEncode(json_encode([
            'aud' => $origin,
            'exp' => time() + 43200, // 12 hours
            'sub' => $vapidKeys['subject']
        ]));

        $tokenData = "$header.$payload";

        // Load private key
        $privKeyResource = openssl_pkey_get_private($vapidKeys['privateKeyPem'] ?? '');
        if (!$privKeyResource && !empty($vapidKeys['privateKey'])) {
            // Reconstruct private key PEM from raw scalar
            $rawD = self::base64UrlDecode($vapidKeys['privateKey']);
            $rawPub = self::base64UrlDecode($vapidKeys['publicKey']);
            $der = hex2bin('30770201010420') . $rawD . hex2bin('a00a06082a8648ce3d030107a144034200') . $rawPub;
            $pem = "-----BEGIN EC PRIVATE KEY-----\n" . chunk_split(base64_encode($der), 64, "\n") . "-----END EC PRIVATE KEY-----\n";
            $privKeyResource = openssl_pkey_get_private($pem);
        }

        $signatureDer = '';
        openssl_sign($tokenData, $signatureDer, $privKeyResource, OPENSSL_ALGO_SHA256);
        $signatureP1363 = self::derToP1363($signatureDer);

        $jwt = "$tokenData." . self::base64UrlEncode($signatureP1363);

        return [
            'Authorization' => 'vapid t=' . $jwt . ', k=' . $vapidKeys['publicKey']
        ];
    }

    /**
     * Encrypt message payload according to RFC 8291 (aes128gcm).
     */
    public static function encrypt(string $payload, string $p256dhBase64, string $authBase64): array {
        $clientPubRaw = self::base64UrlDecode($p256dhBase64);
        $authSecret = self::base64UrlDecode($authBase64);

        if (strlen($clientPubRaw) !== 65 || strlen($authSecret) < 16) {
            throw new Exception("Invalid client subscription parameters.");
        }

        $config = [];
        if (file_exists('C:/xampp/apache/conf/openssl.cnf')) {
            $config['config'] = 'C:/xampp/apache/conf/openssl.cnf';
        }

        // 1. Generate ephemeral sender EC P-256 keypair
        $senderKey = openssl_pkey_new(array_merge([
            'curve_name' => 'prime256v1',
            'private_key_type' => OPENSSL_KEYTYPE_EC,
        ], $config));
        $senderDetails = openssl_pkey_get_details($senderKey);
        $senderPubRaw = "\x04" . $senderDetails['ec']['x'] . $senderDetails['ec']['y'];

        // 2. Convert client raw 65-byte public key to OpenSSL key resource
        $clientPubDer = hex2bin('3059301306072a8648ce3d020106082a8648ce3d030107034200') . $clientPubRaw;
        $clientPubPem = "-----BEGIN PUBLIC KEY-----\n" . chunk_split(base64_encode($clientPubDer), 64, "\n") . "-----END PUBLIC KEY-----\n";
        $clientPubResource = openssl_pkey_get_public($clientPubPem);

        if (!$clientPubResource) {
            throw new Exception("Failed to parse client public key.");
        }

        // 3. Derive ECDH shared secret
        $sharedSecret = openssl_pkey_derive($clientPubResource, $senderKey);
        if (!$sharedSecret) {
            throw new Exception("ECDH key derivation failed.");
        }

        // 4. Derive encryption keys via HKDF (RFC 8291)
        $salt = random_bytes(16);
        $authInfo = "WebPush: info\x00" . $clientPubRaw . $senderPubRaw;
        $ikm = hash_hkdf('sha256', $sharedSecret, 32, $authInfo, $authSecret);

        $cekInfo = "Content-Encoding: aes128gcm\x00";
        $cek = hash_hkdf('sha256', $ikm, 16, $cekInfo, $salt);

        $nonceInfo = "Content-Encoding: nonce\x00";
        $nonce = hash_hkdf('sha256', $ikm, 12, $nonceInfo, $salt);

        // 5. Encrypt with AES-128-GCM
        $paddedPlaintext = $payload . "\x02";
        $tag = '';
        $ciphertext = openssl_encrypt($paddedPlaintext, 'aes-128-gcm', $cek, OPENSSL_RAW_DATA, $nonce, $tag);

        $recordSize = 4096;
        $encryptedBody = $salt . pack('N', $recordSize) . chr(65) . $senderPubRaw . $ciphertext . $tag;

        return [
            'body' => $encryptedBody,
            'headers' => [
                'Content-Type' => 'application/octet-stream',
                'Content-Encoding' => 'aes128gcm',
                'TTL' => '86400',
                'Urgency' => 'high'
            ]
        ];
    }
}
