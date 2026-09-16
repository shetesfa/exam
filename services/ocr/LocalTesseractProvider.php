<?php
require_once __DIR__ . '/OCRProviderInterface.php';

/**
 * LocalTesseractProvider.php
 * Calls local Tesseract OCR binary with Amharic language model (amh+eng).
 */
class LocalTesseractProvider implements OCRProviderInterface {
    private $binaryPath;

    public function __construct(?string $binaryPath = null) {
        $this->binaryPath = $binaryPath ?: (getenv('TESSERACT_PATH') ?: 'tesseract');
    }

    public function isAvailable(): bool {
        // Test if tesseract executable runs
        $output = [];
        $returnVar = -1;
        @exec(escapeshellcmd($this->binaryPath) . ' --version 2>&1', $output, $returnVar);
        return ($returnVar === 0);
    }

    public function processImage(string $imagePath): array {
        if (!file_exists($imagePath)) {
            return [
                'success' => false,
                'raw_text' => '',
                'confidence' => 0.0,
                'provider' => 'tesseract',
                'error' => 'የምስል ፋይሉ አልተገኘም።'
            ];
        }

        // Run Tesseract with Amharic + English language models
        $cmd = escapeshellcmd($this->binaryPath) . ' ' . escapeshellarg($imagePath) . ' stdout -l amh+eng --psm 6 2>&1';
        $output = [];
        $returnVar = -1;
        @exec($cmd, $output, $returnVar);

        $text = trim(implode("\n", $output));

        if ($returnVar !== 0 && empty($text)) {
            return [
                'success' => false,
                'raw_text' => '',
                'confidence' => 0.0,
                'provider' => 'tesseract',
                'error' => 'የጽሑፍ ንባብ (Tesseract OCR) አገልግሎት አልተሳካም።'
            ];
        }

        return [
            'success' => true,
            'raw_text' => $text,
            'confidence' => 0.85,
            'provider' => 'tesseract',
            'error' => null
        ];
    }
}
