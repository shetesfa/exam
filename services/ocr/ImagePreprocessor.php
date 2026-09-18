<?php
/**
 * ImagePreprocessor.php
 * Validates, rotates according to EXIF, resizes, and optimizes images for Amharic handwriting OCR.
 */
class ImagePreprocessor {
    const MAX_FILE_SIZE = 10485760; // 10 MB
    const ALLOWED_MIMES = ['image/jpeg', 'image/png', 'image/webp'];

    /**
     * Validate an uploaded file.
     */
    public static function validate(array $file): array {
        $isUploaded = isset($file['tmp_name']) && (is_uploaded_file($file['tmp_name']) || (php_sapi_name() === 'cli' && file_exists($file['tmp_name'])));
        if (!$isUploaded) {
            return ['valid' => false, 'error' => 'ምንም ፋይል አልተጫነም!'];
        }

        if ($file['size'] > self::MAX_FILE_SIZE) {
            return ['valid' => false, 'error' => 'የፋይሉ መጠን ከ 10MB መብለጥ የለበትም!'];
        }

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        if (!in_array($mime, self::ALLOWED_MIMES)) {
            return ['valid' => false, 'error' => 'የተሳሳተ የፋይል አይነት! የሚፈቀዱት: JPG, PNG, WEBP ብቻ ናቸው።'];
        }

        $imgInfo = @getimagesize($file['tmp_name']);
        if (!$imgInfo) {
            return ['valid' => false, 'error' => 'ትክክለኛ የምስል ፋይል አይደለም!'];
        }

        return ['valid' => true, 'mime' => $mime, 'width' => $imgInfo[0], 'height' => $imgInfo[1]];
    }

    /**
     * Process image: save original and create enhanced high-contrast version for OCR.
     */
    public static function process(string $tmpPath, string $targetDir, string $baseName): array {
        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0755, true);
        }

        $origPath = rtrim($targetDir, '/\\') . DIRECTORY_SEPARATOR . 'orig_' . $baseName . '.jpg';
        $procPath = rtrim($targetDir, '/\\') . DIRECTORY_SEPARATOR . 'proc_' . $baseName . '.png';

        // 1. Load image resource
        $src = self::loadImageResource($tmpPath);
        if (!$src) {
            return ['success' => false, 'error' => 'ምስሉን መክፈት አልተቻለም!'];
        }

        // 2. Correct EXIF orientation if JPEG
        $src = self::correctOrientation($src, $tmpPath);

        // 3. Save high-quality original JPEG
        imagejpeg($src, $origPath, 92);

        // 4. Create enhanced version for OCR:
        // Resize if excessively large (keep max dimension around 2400px for sharp OCR without excessive memory)
        $w = imagesx($src);
        $h = imagesy($src);
        $maxDim = 2400;

        if ($w > $maxDim || $h > $maxDim) {
            if ($w >= $h) {
                $newW = $maxDim;
                $newH = (int)($h * ($maxDim / $w));
            } else {
                $newH = $maxDim;
                $newW = (int)($w * ($maxDim / $h));
            }
            $resized = imagecreatetruecolor($newW, $newH);
            imagecopyresampled($resized, $src, 0, 0, 0, 0, $newW, $newH, $w, $h);
            imagedestroy($src);
            $src = $resized;
        }

        // Grayscale conversion & contrast stretch for optimal OCR extraction
        imagefilter($src, IMG_FILTER_GRAYSCALE);
        imagefilter($src, IMG_FILTER_CONTRAST, -25); // enhance text edges
        imagefilter($src, IMG_FILTER_SMOOTH, 1);    // subtle noise reduction

        // Save preprocessed PNG for OCR
        imagepng($src, $procPath, 6);
        imagedestroy($src);

        return [
            'success' => true,
            'original_path' => $origPath,
            'processed_path' => $procPath
        ];
    }

    private static function loadImageResource(string $path) {
        $info = @getimagesize($path);
        if (!$info) return null;
        switch ($info[2]) {
            case IMAGETYPE_JPEG:
                return @imagecreatefromjpeg($path);
            case IMAGETYPE_PNG:
                return @imagecreatefrompng($path);
            case IMAGETYPE_WEBP:
                return function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($path) : null;
            default:
                return null;
        }
    }

    private static function correctOrientation($image, string $path) {
        if (!function_exists('exif_read_data')) return $image;
        $exif = @exif_read_data($path);
        if (!$exif || empty($exif['Orientation'])) return $image;

        $orientation = $exif['Orientation'];
        switch ($orientation) {
            case 3:
                $rotated = imagerotate($image, 180, 0);
                imagedestroy($image);
                return $rotated;
            case 6:
                $rotated = imagerotate($image, -90, 0);
                imagedestroy($image);
                return $rotated;
            case 8:
                $rotated = imagerotate($image, 90, 0);
                imagedestroy($image);
                return $rotated;
            default:
                return $image;
        }
    }
}
