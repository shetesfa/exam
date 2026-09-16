<?php
require_once __DIR__ . '/OCRProviderInterface.php';
require_once __DIR__ . '/ImagePreprocessor.php';
require_once __DIR__ . '/AmharicFieldParser.php';
require_once __DIR__ . '/GoogleCloudVisionProvider.php';
require_once __DIR__ . '/LocalTesseractProvider.php';
require_once __DIR__ . '/OCRSpaceProvider.php';
require_once __DIR__ . '/FallbackAmharicProvider.php';

/**
 * OCRService.php
 * Unified OCR Service for Ethiopian Sunday School lesson plans.
 */
class OCRService {
    /**
     * Get active OCR provider according to environment configuration.
     */
    public static function getProvider(): OCRProviderInterface {
        $preferred = strtolower(getenv('OCR_PROVIDER') ?: 'auto');

        if ($preferred === 'google_vision' || $preferred === 'auto') {
            $g = new GoogleCloudVisionProvider();
            if ($g->isAvailable()) return $g;
        }

        if ($preferred === 'tesseract' || $preferred === 'auto') {
            $t = new LocalTesseractProvider();
            if ($t->isAvailable()) return $t;
        }

        if ($preferred === 'ocr_space' || $preferred === 'auto') {
            $s = new OCRSpaceProvider();
            if ($s->isAvailable()) return $s;
        }

        // Default to graceful fallback
        return new FallbackAmharicProvider();
    }

    /**
     * Process an uploaded lesson plan image file and extract structured Amharic fields.
     */
    public static function processUploadedPhoto(array $file, int $teacherId, string $uploadDir = 'uploads/lesson_plans/'): array {
        // 1. Validate uploaded image
        $val = ImagePreprocessor::validate($file);
        if (!$val['valid']) {
            return [
                'success' => false,
                'error' => $val['error']
            ];
        }

        // 2. Preprocess & Save original and OCR-optimized images
        $baseName = 'plan_' . $teacherId . '_' . time() . '_' . bin2hex(random_bytes(4));
        $prep = ImagePreprocessor::process($file['tmp_name'], $uploadDir, $baseName);
        if (!$prep['success']) {
            return [
                'success' => false,
                'error' => $prep['error']
            ];
        }

        $origRelativePath = str_replace('\\', '/', $prep['original_path']);
        $procRelativePath = str_replace('\\', '/', $prep['processed_path']);

        // Strip absolute directory prefix if present for database storage
        if (strpos($origRelativePath, 'uploads/') !== false) {
            $origRelativePath = substr($origRelativePath, strpos($origRelativePath, 'uploads/'));
        }
        if (strpos($procRelativePath, 'uploads/') !== false) {
            $procRelativePath = substr($procRelativePath, strpos($procRelativePath, 'uploads/'));
        }

        // 3. Execute OCR on processed image
        $provider = self::getProvider();
        $ocrResult = $provider->processImage($prep['processed_path']);

        if (!$ocrResult['success'] && empty($ocrResult['raw_text'])) {
            return [
                'success' => false,
                'original_photo' => $origRelativePath,
                'processed_photo' => $procRelativePath,
                'error' => $ocrResult['error'] ?: 'ከምስሉ ላይ ጽሑፉን ማንበብ አልተቻለም።'
            ];
        }

        // 4. Parse raw Amharic text into structured lesson plan fields
        $fields = AmharicFieldParser::parse($ocrResult['raw_text']);

        return [
            'success' => true,
            'original_photo' => $origRelativePath,
            'processed_photo' => $procRelativePath,
            'raw_text' => $ocrResult['raw_text'],
            'fields' => $fields,
            'confidence' => $ocrResult['confidence'],
            'provider' => $ocrResult['provider'],
            'error' => null
        ];
    }
}
