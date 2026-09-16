<?php
/**
 * api/get_curriculum.php
 * Returns curriculum chapters and sub-topics for a class/grade and subject.
 */
require_once __DIR__ . '/../db.php';

header('Content-Type: application/json; charset=utf-8');

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'ያልተፈቀደ መዳረሻ!'], JSON_UNESCAPED_UNICODE);
    exit();
}

$classId = intval($_GET['class_id'] ?? 0);
$gradeLevel = intval($_GET['grade_level'] ?? 0);
$subjectRaw = trim($_GET['subject'] ?? '');

// Map class_id to grade_level if grade_level not explicitly passed
if ($gradeLevel <= 0 && $classId > 0) {
    // Direct mapping for default classes
    $map = [7 => 1, 8 => 2, 9 => 3, 10 => 4, 11 => 5, 12 => 6];
    if (isset($map[$classId])) {
        $gradeLevel = $map[$classId];
    } else {
        $cRow = dbFetchOne($conn, "SELECT name FROM classes WHERE id = ?", "i", [$classId]);
        if ($cRow) {
            $cName = $cRow['name'];
            if (preg_match('/10|11|12/u', $cName)) {
                $gradeLevel = 0;
            } elseif (preg_match('/1ኛ|፩ኛ|1\s*st/ui', $cName)) {
                $gradeLevel = 1;
            } elseif (preg_match('/2ኛ|፪ኛ|2\s*nd/ui', $cName)) {
                $gradeLevel = 2;
            } elseif (preg_match('/3ኛ|፫ኛ|3\s*rd/ui', $cName)) {
                $gradeLevel = 3;
            } elseif (preg_match('/4ኛ|፬ኛ|4\s*th/ui', $cName)) {
                $gradeLevel = 4;
            } elseif (preg_match('/5ኛ|፭ኛ|5\s*th/ui', $cName)) {
                $gradeLevel = 5;
            } elseif (preg_match('/6ኛ|፮ኛ|6\s*th/ui', $cName)) {
                $gradeLevel = 6;
            }
        }
    }
}

// Normalize subject name to match curriculum_topics
if (!function_exists('normalizeSubject')) {
    function normalizeSubject($name) {
        $name = trim($name);
        if (empty($name)) return '';
        if (preg_match('/ግእዝ/u', $name)) return 'ግእዝ';
        if (preg_match('/ታሪክ/u', $name)) return 'የቤተ ክርስቲያን ታሪክ';
        if (preg_match('/ሥነ\s*ምግባር|ስነ\s*ምግባር|ስነምግባር|ሥነምግባር|ግብረገብ/u', $name)) return 'ክርስቲያናዊ ሥነ ምግባር';
        if (preg_match('/ሥርዓተ|ስርዓተ/u', $name)) return 'ሥርዓተ ቤተ ክርስቲያን';
        if (preg_match('/ቅዱሳት|መጻሕፍት|መጻህፍት/u', $name)) return 'የቅዱሳት መጻሕፍት ጥናት';
        if (preg_match('/ሃይማኖት|እምነት/u', $name)) return 'መሠረተ ሃይማኖት';
        return $name;
    }
}

$subject = normalizeSubject($subjectRaw);

if ($gradeLevel <= 0) {
    echo json_encode([
        'success' => false,
        'error' => 'ትክክለኛ ክፍል አልተመረጠም ወይም ከ1ኛ-6ኛ ክፍል ውጭ ነው',
        'grade_level' => 0,
        'available_subjects' => [],
        'chapters' => [],
        'tree' => new stdClass()
    ], JSON_UNESCAPED_UNICODE);
    exit();
}

$subRows = dbFetchAll($conn, "SELECT DISTINCT subject_name FROM curriculum_topics WHERE grade_level = ? ORDER BY id ASC", "i", [$gradeLevel]);
$availableSubjects = array_values(array_unique(array_column($subRows, 'subject_name')));

// If no subject is chosen yet, do not dump all subjects' chapters together
if ($subject === '') {
    echo json_encode([
        'success' => true,
        'grade_level' => $gradeLevel,
        'subject' => '',
        'available_subjects' => $availableSubjects,
        'chapters' => [],
        'tree' => new stdClass()
    ], JSON_UNESCAPED_UNICODE);
    exit();
}

$query = "SELECT chapter_name, sub_topic_name FROM curriculum_topics WHERE grade_level = ? AND subject_name = ? ORDER BY sort_order ASC";
$rows = dbFetchAll($conn, $query, "is", [$gradeLevel, $subject]);

$tree = [];
$chapters = [];

foreach ($rows as $r) {
    $chap = $r['chapter_name'];
    $sub = $r['sub_topic_name'];
    if (!isset($tree[$chap])) {
        $tree[$chap] = [];
        $chapters[] = $chap;
    }
    $tree[$chap][] = $sub;
}

echo json_encode([
    'success' => true,
    'grade_level' => $gradeLevel,
    'subject' => $subject,
    'available_subjects' => $availableSubjects,
    'chapters' => $chapters,
    'tree' => $tree
], JSON_UNESCAPED_UNICODE);
