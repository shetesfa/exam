<?php
require_once __DIR__ . '/OCRProviderInterface.php';

/**
 * OCRSpaceProvider.php
 * Calls OCR.Space REST API with language=amh.
 */
class OCRSpaceProvider implements OCRProviderInterface {
    private $apiKey;

    public function __construct(?string $apiKey = null) {
        $this->apiKey = $apiKey ?: (getenv('OCR_SPACE_API_KEY') ?: 'helloworld'); // 'helloworld' is OCR.Space public demo key
    }

    public function isAvailable(): bool {
        return !empty($this->apiKey);
    }

    public function processImage(string $imagePath): array {
        if (!file_exists($imagePath)) {
            return [
                'success' => false,
                'raw_text' => '',
                'confidence' => 0.0,
                'provider' => 'ocr_space',
                'error' => 'Image file not found.'
            ];
        }

        $cfile = new CURLFile($imagePath, 'image/png', 'photo.png');
        $postData = [
            'apikey' => $this->apiKey,
            'language' => 'amh',
            'isOverlayRequired' => 'false',
            'file' => $cfile,
            'OCREngine' => '2' // Engine 2 is optimized for special scripts
        ];

        $ch = curl_init('https://api.ocr.space/parse/image');
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $postData,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
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
                'provider' => 'ocr_space',
                'error' => "OCR.Space ስህተት (HTTP $httpCode): " . ($curlErr ?: $response)
            ];
        }

        $data = json_decode($response, true);
        if (isset($data['IsErroredOnProcessing']) && $data['IsErroredOnProcessing']) {
            $err = $data['ErrorMessage'][0] ?? 'Unknown OCR error';
            return [
                'success' => false,
                'raw_text' => '',
                'confidence' => 0.0,
                'provider' => 'ocr_space',
                'error' => "OCR Error: " . $err
            ];
        }

        $parsedText = '';
        if (!empty($data['ParsedResults'])) {
            foreach ($data['ParsedResults'] as $res) {
                $parsedText .= $res['ParsedText'] . "\n";
            }
        }

        return [
            'success' => true,
            'raw_text' => trim($parsedText),
            'confidence' => 0.88,
            'provider' => 'ocr_space',
            'error' => null
        ];
    }
}
