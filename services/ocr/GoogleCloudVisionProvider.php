<?php
require_once __DIR__ . '/OCRProviderInterface.php';

/**
 * GoogleCloudVisionProvider.php
 * Calls Google Cloud Vision REST API with DOCUMENT_TEXT_DETECTION for superior Amharic handwriting recognition.
 */
class GoogleCloudVisionProvider implements OCRProviderInterface {
    private $apiKey;

    public function __construct(?string $apiKey = null) {
        $key = $apiKey ?: getenv('GOOGLE_VISION_API_KEY');
        if (empty($key) && !empty($GLOBALS['conn'])) {
            $row = @dbFetchOne($GLOBALS['conn'], "SELECT setting_value FROM settings WHERE setting_key = 'google_vision_api_key'");
            if ($row && !empty($row['setting_value'])) {
                $key = trim($row['setting_value']);
            }
        }
        $this->apiKey = $key;
    }

    public function isAvailable(): bool {
        return !empty($this->apiKey);
    }

    public function processImage(string $imagePath): array {
        if (!$this->isAvailable()) {
            return [
                'success' => false,
                'raw_text' => '',
                'confidence' => 0.0,
                'provider' => 'google_vision',
                'error' => 'የጉግል ቪዥን ቁልፍ (Google Vision API Key) አልተዘጋጀም።'
            ];
        }

        if (!file_exists($imagePath)) {
            return [
                'success' => false,
                'raw_text' => '',
                'confidence' => 0.0,
                'provider' => 'google_vision',
                'error' => 'የምስል ፋይሉ አልተገኘም።'
            ];
        }

        $imageData = base64_encode(file_get_contents($imagePath));

        $payload = [
            'requests' => [
                [
                    'image' => ['content' => $imageData],
                    'features' => [
                        ['type' => 'DOCUMENT_TEXT_DETECTION', 'maxResults' => 1]
                    ],
                    'imageContext' => [
                        'languageHints' => ['am']
                    ]
                ]
            ]
        ];

        $url = 'https://vision.googleapis.com/v1/images:annotate?key=' . urlencode($this->apiKey);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_TIMEOUT => 25,
            CURLOPT_SSL_VERIFYPEER => true
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr = curl_error($ch);
        curl_close($ch);

        if ($curlErr || $httpCode !== 200) {
            return [
                'success' => false,
                'raw_text' => '',
                'confidence' => 0.0,
                'provider' => 'google_vision',
                'error' => "Google Vision API ስህተት (HTTP $httpCode): " . ($curlErr ?: $response)
            ];
        }

        $data = json_decode($response, true);
        $fullText = $data['responses'][0]['fullTextAnnotation']['text'] ?? '';
        $confidence = 0.95;

        // Try extracting average confidence from blocks
        $pages = $data['responses'][0]['fullTextAnnotation']['pages'] ?? [];
        if (!empty($pages[0]['confidence'])) {
            $confidence = (float)$pages[0]['confidence'];
        }

        return [
            'success' => true,
            'raw_text' => $fullText,
            'confidence' => round($confidence, 2),
            'provider' => 'google_vision',
            'error' => null
        ];
    }
}
