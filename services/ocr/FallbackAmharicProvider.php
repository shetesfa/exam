<?php
require_once __DIR__ . '/OCRProviderInterface.php';

/**
 * FallbackAmharicProvider.php
 * Graceful fallback when external cloud credentials or local Tesseract binary
 * are not installed, providing structured editable template text so the workflow
 * continues seamlessly and teachers can edit every field.
 */
class FallbackAmharicProvider implements OCRProviderInterface {
    public function isAvailable(): bool {
        return true;
    }

    public function processImage(string $imagePath): array {
        return [
            'success' => false,
            'raw_text' => '',
            'confidence' => 0,
            'provider' => 'none',
            'error' => 'ጽሑፉን ከፎቶው ላይ ማንበብ አልተቻለም። እባክዎ ፎቶውን እያዩ መረጃዎቹን በቀጥታ በሰንጠረዡ ውስጥ ይሙሉ!'
        ];
    }
}

