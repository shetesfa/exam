<?php
/**
 * AmharicFieldParser.php
 * Extracts structured Ethiopian Sunday school lesson plan fields from raw Amharic OCR text.
 */
class AmharicFieldParser {
    /**
     * Map raw OCR text to structured form fields matching Sunday school paper.
     */
    public static function parse(string $rawText): array {
        $fields = [
            'subject' => '',
            'ethiopian_year' => '',
            'ethiopian_month' => '',
            'week_number' => '',
            'chapter' => '',
            'sub_topic' => '',
            'objective' => '',
            'materials' => '',
            'evaluation' => ''
        ];

        if (empty(trim($rawText))) {
            return $fields;
        }

        // Year extraction
        if (preg_match('/(20\d{2})\s*(?:ዓ\.?ም|ዓመተ\s*ምሕረት)?/u', $rawText, $ym)) {
            $fields['ethiopian_year'] = (int)$ym[1];
        }

        // Match Ethiopian month names
        $monthsRegex = '/(መስከረም|ጥቅምት|ኅዳር|ታኅሣሥ|ጥር|የካቲት|መጋቢት|ሚያዝያ|ግንቦት|ሰኔ|ሐምሌ|ነሐሴ|ጳጉሜን)/u';
        $monthMap = [
            'መስከረም'=>1, 'ጥቅምት'=>2, 'ኅዳር'=>3, 'ታኅሣሥ'=>4,
            'ጥር'=>5, 'የካቲት'=>6, 'መጋቢት'=>7, 'ሚያዝያ'=>8,
            'ግንቦት'=>9, 'ሰኔ'=>10, 'ሐምሌ'=>11, 'ነሐሴ'=>12, 'ጳጉሜን'=>13
        ];
        if (preg_match($monthsRegex, $rawText, $mm)) {
            $fields['ethiopian_month'] = $monthMap[$mm[1]] ?? '';
        }

        // Patterns for the 7 columns & header on paper
        $patterns = [
            'subject' => '/(?:ትምህርት|የትምህርቱ\s*ዓይነት|የትምህርት\s*ዓይነት)[\s:፡-]+([^\n]+)/u',
            'week_number' => '/(?:ሳምንት)[\s:፡-]+([^\n]+)/u',
            'chapter' => '/(?:ምዕራፍ|ማእራፍ)[\s:፡-]+([^\n]+)/u',
            'sub_topic' => '/(?:ንዑስ\s*ርዕስ|ንዑስ\s*ርዐስ|ርዕስ|ርዐስ)[\s:፡-]+([^\n]+)/u',
            'objective' => '/(?:የትምህርቱ\s*ዓላማ|የትምህርቱ\s*አላማ|ዓላማ|አላማ)[\s:፡-]+([^\n]+)/u',
            'materials' => '/(?:መርጃ\s*መሳሪያ|መርጃ|መሳሪያ)[\s:፡-]+([^\n]+)/u',
            'evaluation' => '/(?:ምዘና|ግምገማ)[\s:፡-]+([^\n]+)/u',
        ];

        foreach ($patterns as $key => $pattern) {
            if (preg_match($pattern, $rawText, $m)) {
                $val = trim($m[1]);
                if (!empty($val)) {
                    $fields[$key] = $val;
                }
            }
        }

        return $fields;
    }
}
