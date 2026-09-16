<?php
/**
 * OCRProviderInterface.php
 * Contract for all Amharic OCR providers.
 */
interface OCRProviderInterface {
    /**
     * Process an image and return extracted text and confidence.
     *
     * @param string $imagePath Absolute path to the preprocessed image file.
     * @return array [
     *    'success' => bool,
     *    'raw_text' => string,
     *    'confidence' => float, // 0.0 - 1.0
     *    'provider' => string,
     *    'error' => ?string
     * ]
     */
    public function processImage(string $imagePath): array;

    /**
     * Check if this provider is available/configured in the current environment.
     */
    public function isAvailable(): bool;
}
