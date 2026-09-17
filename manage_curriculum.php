<?php
/**
 * manage_curriculum.php - የመማሪያ መጻሕፍት፣ ምዕራፎች እና ንዑስ ርዕሶች ማስተዳደሪያ
 * 
 * Allows Administrators (ትምህርት ክፍል) to:
 * 1. Select Grade (1ኛ - 6ኛ ክፍል...)
 * 2. Select / Add / Edit Book (Subject)
 * 3. Add / Edit / Delete Chapters (ምዕራፍ)
 * 4. Add / Edit / Delete Sub-topics (ንዑስ ርዕስ)
 */
require_once 'db.php';
requireAdmin();

$nav_active = 'manage_curriculum';

// Handle AJAX operations
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json; charset=utf-8');
    
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        echo json_encode(['success' => false, 'message' => 'የደህንነት ማረጋገጫ አልተሳካም (CSRF)!']);
        exit();
    }

    $action = $_POST['action'];

    // 1. ADD NEW BOOK (SUBJECT)
    if ($action === 'add_subject') {
        $grade = intval($_POST['grade_level'] ?? 0);
        $subject = trim($_POST['subject_name'] ?? '');
        $chapter = trim($_POST['chapter_name'] ?? 'ምዕራፍ ፩');
        $subtopic = trim($_POST['sub_topic_name'] ?? '፩.፩ መግቢያ');

        if ($grade <= 0 || empty($subject)) {
            echo json_encode(['success' => false, 'message' => 'እባክዎ ትክክለኛ ክፍል እና የመጽሐፍ ስም ያስገቡ!']);
            exit();
        }

        // Check if subject already exists for this grade
        $exists = dbFetchOne($conn, "SELECT id FROM curriculum_topics WHERE grade_level = ? AND subject_name = ? LIMIT 1", "is", [$grade, $subject]);
        if ($exists) {
            echo json_encode(['success' => false, 'message' => 'ይህ መጽሐፍ አስቀድሞ በዚህ ክፍል ውስጥ አለ!']);
            exit();
        }

        $ins = dbExecute(
            $conn,
            "INSERT INTO curriculum_topics (grade_level, subject_name, chapter_name, sub_topic_name, sort_order) VALUES (?, ?, ?, ?, 1)",
            "isss",
            [$grade, $subject, $chapter, $subtopic]
        );

        echo json_encode(['success' => (bool)$ins, 'message' => $ins ? 'አዲሱ መጽሐፍ በተሳካ ሁኔታ ተፈጥሯል!' : 'ስህተት ተፈጥሯል!']);
        exit();
    }

    // 2. RENAME BOOK (SUBJECT)
    if ($action === 'rename_subject') {
        $grade = intval($_POST['grade_level'] ?? 0);
        $oldSubject = trim($_POST['old_subject_name'] ?? '');
        $newSubject = trim($_POST['new_subject_name'] ?? '');

        if ($grade <= 0 || empty($oldSubject) || empty($newSubject)) {
            echo json_encode(['success' => false, 'message' => 'እባክዎ አዲሱን የመጽሐፍ ስም ያስገቡ!']);
            exit();
        }

        $upd = dbExecute(
            $conn,
            "UPDATE curriculum_topics SET subject_name = ? WHERE grade_level = ? AND subject_name = ?",
            "sis",
            [$newSubject, $grade, $oldSubject]
        );

        echo json_encode(['success' => (bool)$upd, 'message' => $upd ? 'የመጽሐፉ ስም ተስተካክሏል!' : 'ማስተካከል አልተቻለም!']);
        exit();
    }

    // 3. DELETE BOOK (SUBJECT)
    if ($action === 'delete_subject') {
        $grade = intval($_POST['grade_level'] ?? 0);
        $subject = trim($_POST['subject_name'] ?? '');

        if ($grade <= 0 || empty($subject)) {
            echo json_encode(['success' => false, 'message' => 'የተሳሳተ መረጃ!']);
            exit();
        }

        $del = dbExecute(
            $conn,
            "DELETE FROM curriculum_topics WHERE grade_level = ? AND subject_name = ?",
            "is",
            [$grade, $subject]
        );

        echo json_encode(['success' => (bool)$del, 'message' => $del ? 'መጽሐፉና ምዕራፎቹ በሙሉ ተሰርዘዋል!' : 'መሰረዝ አልተቻለም!']);
        exit();
    }

    // 4. ADD CHAPTER
    if ($action === 'add_chapter') {
        $grade = intval($_POST['grade_level'] ?? 0);
        $subject = trim($_POST['subject_name'] ?? '');
        $chapter = trim($_POST['chapter_name'] ?? '');
        $subtopic = trim($_POST['sub_topic_name'] ?? '');

        if ($grade <= 0 || empty($subject) || empty($chapter)) {
            echo json_encode(['success' => false, 'message' => 'እባክዎ የምዕራፉን ስም ያስገቡ!']);
            exit();
        }

        if (empty($subtopic)) {
            $subtopic = '፩.፩ መግቢያ';
        }

        // Get max sort_order
        $maxOrderRow = dbFetchOne($conn, "SELECT MAX(sort_order) as max_ord FROM curriculum_topics WHERE grade_level = ? AND subject_name = ?", "is", [$grade, $subject]);
        $nextOrder = ($maxOrderRow && $maxOrderRow['max_ord']) ? intval($maxOrderRow['max_ord']) + 1 : 1;

        $ins = dbExecute(
            $conn,
            "INSERT INTO curriculum_topics (grade_level, subject_name, chapter_name, sub_topic_name, sort_order) VALUES (?, ?, ?, ?, ?)",
            "isssi",
            [$grade, $subject, $chapter, $subtopic, $nextOrder]
        );

        echo json_encode(['success' => (bool)$ins, 'message' => $ins ? 'አዲሱ ምዕራፍ በተሳካ ሁኔታ ተጨምሯል!' : 'ስህተት ተፈጥሯል!']);
        exit();
    }

    // 5. RENAME CHAPTER
    if ($action === 'rename_chapter') {
        $grade = intval($_POST['grade_level'] ?? 0);
        $subject = trim($_POST['subject_name'] ?? '');
        $oldChapter = trim($_POST['old_chapter_name'] ?? '');
        $newChapter = trim($_POST['new_chapter_name'] ?? '');

        if ($grade <= 0 || empty($subject) || empty($oldChapter) || empty($newChapter)) {
            echo json_encode(['success' => false, 'message' => 'እባክዎ አዲሱን የምዕራፍ ስም ያስገቡ!']);
            exit();
        }

        $upd = dbExecute(
            $conn,
            "UPDATE curriculum_topics SET chapter_name = ? WHERE grade_level = ? AND subject_name = ? AND chapter_name = ?",
            "siss",
            [$newChapter, $grade, $subject, $oldChapter]
        );

        echo json_encode(['success' => (bool)$upd, 'message' => $upd ? 'የምዕራፉ ስም ተስተካክሏል!' : 'ማስተካከል አልተቻለም!']);
        exit();
    }

    // 6. DELETE CHAPTER
    if ($action === 'delete_chapter') {
        $grade = intval($_POST['grade_level'] ?? 0);
        $subject = trim($_POST['subject_name'] ?? '');
        $chapter = trim($_POST['chapter_name'] ?? '');

        if ($grade <= 0 || empty($subject) || empty($chapter)) {
            echo json_encode(['success' => false, 'message' => 'የተሳሳተ መረጃ!']);
            exit();
        }

        $del = dbExecute(
            $conn,
            "DELETE FROM curriculum_topics WHERE grade_level = ? AND subject_name = ? AND chapter_name = ?",
            "iss",
            [$grade, $subject, $chapter]
        );

        echo json_encode(['success' => (bool)$del, 'message' => $del ? 'ምዕራፉና ንዑስ ርዕሶቹ ተሰርዘዋል!' : 'መሰረዝ አልተቻለም!']);
        exit();
    }

    // 7. ADD SUB-TOPIC
    if ($action === 'add_subtopic') {
        $grade = intval($_POST['grade_level'] ?? 0);
        $subject = trim($_POST['subject_name'] ?? '');
        $chapter = trim($_POST['chapter_name'] ?? '');
        $subtopic = trim($_POST['sub_topic_name'] ?? '');

        if ($grade <= 0 || empty($subject) || empty($chapter) || empty($subtopic)) {
            echo json_encode(['success' => false, 'message' => 'እባክዎ የንዑስ ርዕሱን ስም ያስገቡ!']);
            exit();
        }

        $maxOrderRow = dbFetchOne(
            $conn,
            "SELECT MAX(sort_order) as max_ord FROM curriculum_topics WHERE grade_level = ? AND subject_name = ? AND chapter_name = ?",
            "iss",
            [$grade, $subject, $chapter]
        );
        $nextOrder = ($maxOrderRow && $maxOrderRow['max_ord']) ? intval($maxOrderRow['max_ord']) + 1 : 1;

        $ins = dbExecute(
            $conn,
            "INSERT INTO curriculum_topics (grade_level, subject_name, chapter_name, sub_topic_name, sort_order) VALUES (?, ?, ?, ?, ?)",
            "isssi",
            [$grade, $subject, $chapter, $subtopic, $nextOrder]
        );

        echo json_encode(['success' => (bool)$ins, 'message' => $ins ? 'ንዑስ ርዕሱ በተሳካ ሁኔታ ተጨምሯል!' : 'ስህተት ተፈጥሯል!']);
        exit();
    }

    // 8. EDIT SUB-TOPIC
    if ($action === 'edit_subtopic') {
        $topicId = intval($_POST['topic_id'] ?? 0);
        $subtopic = trim($_POST['sub_topic_name'] ?? '');

        if ($topicId <= 0 || empty($subtopic)) {
            echo json_encode(['success' => false, 'message' => 'እባክዎ ትክክለኛ የንዑስ ርዕስ ስም ያስገቡ!']);
            exit();
        }

        $upd = dbExecute(
            $conn,
            "UPDATE curriculum_topics SET sub_topic_name = ? WHERE id = ?",
            "si",
            [$subtopic, $topicId]
        );

        echo json_encode(['success' => (bool)$upd, 'message' => $upd ? 'ንዑስ ርዕሱ ተስተካክሏል!' : 'ማስተካከል አልተቻለም!']);
        exit();
    }

    // 9. DELETE SUB-TOPIC
    if ($action === 'delete_subtopic') {
        $topicId = intval($_POST['topic_id'] ?? 0);

        if ($topicId <= 0) {
            echo json_encode(['success' => false, 'message' => 'የተሳሳተ መለያ!']);
            exit();
        }

        $del = dbExecute($conn, "DELETE FROM curriculum_topics WHERE id = ?", "i", [$topicId]);

        echo json_encode(['success' => (bool)$del, 'message' => $del ? 'ንዑስ ርዕሱ ተሰርዟል!' : 'መሰረዝ አልተቻለም!']);
        exit();
    }

    echo json_encode(['success' => false, 'message' => 'ያልታወቀ ትዕዛዝ!']);
    exit();
}

// -------------------------------------------------------------
// GET PAGE DATA
// -------------------------------------------------------------
$selectedGrade = isset($_GET['grade']) ? intval($_GET['grade']) : 1;
if ($selectedGrade <= 0) $selectedGrade = 1;

// Get all grades that exist or standard 1 to 6
$gradeRows = dbFetchAll($conn, "SELECT DISTINCT grade_level FROM curriculum_topics ORDER BY grade_level ASC");
$existingGrades = array_map(function($r) { return intval($r['grade_level']); }, $gradeRows);
$allGrades = array_unique(array_merge([1, 2, 3, 4, 5, 6], $existingGrades));
sort($allGrades);

// Get all books/subjects for the selected grade
$subjectStatsQuery = "
    SELECT subject_name, 
           COUNT(DISTINCT chapter_name) as total_chapters, 
           COUNT(*) as total_topics
    FROM curriculum_topics 
    WHERE grade_level = ?
    GROUP BY subject_name 
    ORDER BY MIN(id) ASC
";
$subjects = dbFetchAll($conn, $subjectStatsQuery, "i", [$selectedGrade]);

$selectedSubject = isset($_GET['subject']) ? trim($_GET['subject']) : '';
if (empty($selectedSubject) && !empty($subjects)) {
    $selectedSubject = $subjects[0]['subject_name'];
}

// Fetch Chapters and Subtopics for the active grade and subject
$chapters = [];
if (!empty($selectedSubject)) {
    $rows = dbFetchAll(
        $conn,
        "SELECT id, chapter_name, sub_topic_name, sort_order FROM curriculum_topics 
         WHERE grade_level = ? AND subject_name = ? 
         ORDER BY sort_order ASC, id ASC",
        "is",
        [$selectedGrade, $selectedSubject]
    );
    foreach ($rows as $r) {
        $cName = $r['chapter_name'];
        if (!isset($chapters[$cName])) {
            $chapters[$cName] = [];
        }
        $chapters[$cName][] = $r;
    }
}

// Icon mapper for subject names
function getSubjectIcon($name) {
    if (preg_match('/ሃይማኖት/u', $name)) return '📜';
    if (preg_match('/ሥርዓተ|ስርዓተ/u', $name)) return '⛪';
    if (preg_match('/ምግባር/u', $name)) return '🕊️';
    if (preg_match('/ቅዱሳት|መጻሕፍት/u', $name)) return '📖';
    if (preg_match('/ታሪክ/u', $name)) return '🏛️';
    if (preg_match('/ግእዝ/u', $name)) return '🔤';
    return '📚';
}
?>
<!DOCTYPE html>
<html lang="am">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>የመማሪያ መጻሕፍትና ምዕራፎች ማስተዳደሪያ | ትምህርት ክፍል</title>
    <?php include 'pwa_head.php'; ?>
    <style>
        :root {
            --brown-dark: #8B4513;
            --brown-medium: #A52A2A;
            --gold-primary: #FFD700;
            --gold-dark: #DAA520;
            --gold-pale: #FFF8DC;
            --bg-cream: #FAF9F6;
            --success-green: #10B981;
            --error-red: #EF4444;
            --card-bg: #FFFFFF;
            --text-dark: #1F2937;
            --text-muted: #64748B;
            --border-color: #E2E8F0;
        }

        .dark-mode {
            --bg-cream: #111827;
            --card-bg: #1F2937;
            --text-dark: #F9FAFB;
            --text-muted: #9CA3AF;
            --border-color: #374151;
            --gold-pale: #374151;
        }

        body {
            background: var(--bg-cream);
            color: var(--text-dark);
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            margin: 0;
            padding-bottom: 50px;
        }

        .curriculum-container {
            max-width: 1140px;
            margin: 20px auto;
            padding: 0 16px;
        }

        /* Page Hero Header */
        .page-hero {
            background: linear-gradient(135deg, #8B4513 0%, #78350F 100%);
            border-radius: 16px;
            padding: 24px;
            color: white;
            box-shadow: 0 4px 14px rgba(139,69,19,0.18);
            margin-bottom: 24px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 16px;
        }
        .page-hero h1 {
            margin: 0 0 6px 0;
            font-size: 22px;
            color: #FFD700;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .page-hero p {
            margin: 0;
            font-size: 13px;
            opacity: 0.9;
        }

        /* Flow Step Pills (Grade Tabs) */
        .grade-pill-container {
            display: flex;
            gap: 10px;
            overflow-x: auto;
            padding-bottom: 10px;
            margin-bottom: 20px;
            -webkit-overflow-scrolling: touch;
        }
        .grade-pill {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 18px;
            background: var(--card-bg);
            color: var(--text-dark);
            border: 2px solid var(--border-color);
            border-radius: 30px;
            text-decoration: none;
            font-weight: 700;
            font-size: 14px;
            white-space: nowrap;
            transition: all 0.2s ease;
            cursor: pointer;
        }
        .grade-pill:hover {
            border-color: var(--gold-dark);
            transform: translateY(-2px);
        }
        .grade-pill.active {
            background: linear-gradient(135deg, #8B4513, #A52A2A);
            color: #FFD700;
            border-color: #8B4513;
            box-shadow: 0 4px 10px rgba(139,69,19,0.25);
        }
        .grade-badge {
            background: rgba(0,0,0,0.15);
            color: inherit;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 11px;
        }
        .grade-pill.active .grade-badge {
            background: rgba(255,215,0,0.3);
            color: #FFD700;
        }

        /* Section Split Layout */
        .curriculum-layout {
            display: grid;
            grid-template-columns: 320px 1fr;
            gap: 20px;
            align-items: start;
        }
        @media (max-width: 860px) {
            .curriculum-layout {
                grid-template-columns: 1fr;
            }
        }

        /* Sidebar Books / Subjects Card */
        .books-sidebar {
            background: var(--card-bg);
            border-radius: 16px;
            border: 1px solid var(--border-color);
            box-shadow: 0 2px 8px rgba(0,0,0,0.04);
            overflow: hidden;
        }
        .sidebar-header {
            padding: 16px 18px;
            background: var(--gold-pale);
            border-bottom: 1px solid var(--border-color);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .sidebar-header h3 {
            margin: 0;
            font-size: 15px;
            color: var(--brown-dark);
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .dark-mode .sidebar-header h3 {
            color: #FFD700;
        }
        .book-list {
            padding: 10px;
            display: flex;
            flex-direction: column;
            gap: 8px;
        }
        .book-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 12px 14px;
            border-radius: 12px;
            text-decoration: none;
            color: var(--text-dark);
            border: 1px solid var(--border-color);
            background: var(--card-bg);
            transition: all 0.2s ease;
        }
        .book-item:hover {
            border-color: var(--gold-dark);
            background: rgba(255,215,0,0.05);
        }
        .book-item.active {
            background: linear-gradient(135deg, rgba(139,69,19,0.08), rgba(255,215,0,0.15));
            border: 2px solid var(--gold-dark);
            font-weight: 700;
        }
        .book-item-title {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 14px;
        }
        .book-meta {
            font-size: 11px;
            color: var(--text-muted);
            background: var(--border-color);
            padding: 2px 8px;
            border-radius: 10px;
            font-weight: normal;
        }

        /* Chapters Main Workspace */
        .chapters-workspace {
            background: var(--card-bg);
            border-radius: 16px;
            border: 1px solid var(--border-color);
            box-shadow: 0 2px 8px rgba(0,0,0,0.04);
            padding: 20px;
        }
        .workspace-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 12px;
            padding-bottom: 16px;
            border-bottom: 2px solid var(--gold-pale);
            margin-bottom: 20px;
        }
        .workspace-title h2 {
            margin: 0 0 4px 0;
            font-size: 18px;
            color: var(--brown-dark);
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .dark-mode .workspace-title h2 {
            color: #FFD700;
        }
        .workspace-title span {
            font-size: 13px;
            color: var(--text-muted);
        }
        .workspace-actions {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }

        /* Buttons */
        .btn-modern {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 700;
            cursor: pointer;
            border: none;
            transition: all 0.2s ease;
            text-decoration: none;
        }
        .btn-gold {
            background: linear-gradient(135deg, #FFD700, #DAA520);
            color: #8B4513;
        }
        .btn-gold:hover {
            filter: brightness(1.08);
            transform: translateY(-1px);
        }
        .btn-brown {
            background: linear-gradient(135deg, #8B4513, #A52A2A);
            color: white;
        }
        .btn-brown:hover {
            filter: brightness(1.1);
            transform: translateY(-1px);
        }
        .btn-outline-danger {
            background: transparent;
            color: var(--error-red);
            border: 1px solid var(--error-red);
        }
        .btn-outline-danger:hover {
            background: var(--error-red);
            color: white;
        }
        .btn-sm-action {
            padding: 4px 10px;
            font-size: 11px;
            border-radius: 12px;
            background: var(--border-color);
            color: var(--text-dark);
            cursor: pointer;
            border: none;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }
        .btn-sm-action:hover {
            background: var(--gold-primary);
            color: #8B4513;
        }

        /* Chapter Card Accordion */
        .chapter-card {
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: 14px;
            margin-bottom: 14px;
            overflow: hidden;
            transition: border-color 0.2s;
        }
        .chapter-card:hover {
            border-color: var(--gold-dark);
        }
        .chapter-header {
            padding: 14px 18px;
            background: rgba(139,69,19,0.03);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 10px;
            cursor: pointer;
            user-select: none;
        }
        .dark-mode .chapter-header {
            background: rgba(255,255,255,0.03);
        }
        .chapter-header-left {
            display: flex;
            align-items: center;
            gap: 12px;
            font-weight: 700;
            font-size: 15px;
            color: var(--brown-dark);
        }
        .dark-mode .chapter-header-left {
            color: #FCD34D;
        }
        .chapter-topic-badge {
            font-size: 11px;
            padding: 3px 10px;
            background: var(--gold-pale);
            color: var(--brown-dark);
            border-radius: 12px;
            font-weight: 600;
        }
        .chapter-header-actions {
            display: flex;
            gap: 6px;
            align-items: center;
        }
        .subtopics-container {
            padding: 14px 18px;
            border-top: 1px solid var(--border-color);
            display: flex;
            flex-direction: column;
            gap: 8px;
        }
        .subtopic-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 14px;
            background: rgba(0,0,0,0.02);
            border-radius: 8px;
            border: 1px solid var(--border-color);
            transition: all 0.15s ease;
        }
        .dark-mode .subtopic-item {
            background: rgba(255,255,255,0.02);
        }
        .subtopic-item:hover {
            border-color: var(--gold-dark);
            background: rgba(255,215,0,0.04);
        }
        .subtopic-name {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 13px;
        }
        .subtopic-bullet {
            color: var(--gold-dark);
            font-size: 12px;
        }
        .subtopic-actions {
            display: flex;
            gap: 6px;
        }

        /* Modal styling */
        .custom-modal {
            display: none;
            position: fixed;
            top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(0,0,0,0.55);
            z-index: 1000;
            justify-content: center;
            align-items: center;
            padding: 16px;
            box-sizing: border-box;
        }
        .custom-modal.show {
            display: flex;
        }
        .modal-box {
            background: var(--card-bg);
            border-radius: 16px;
            width: 100%;
            max-width: 460px;
            border: 2px solid var(--gold-primary);
            box-shadow: 0 10px 30px rgba(0,0,0,0.25);
            overflow: hidden;
            animation: modalFadeIn 0.25s ease-out;
        }
        @keyframes modalFadeIn {
            from { transform: scale(0.92); opacity: 0; }
            to { transform: scale(1); opacity: 1; }
        }
        .modal-header {
            background: linear-gradient(135deg, #8B4513, #A52A2A);
            color: #FFD700;
            padding: 14px 20px;
            font-size: 16px;
            font-weight: 700;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .modal-header button {
            background: none; border: none; color: white; font-size: 18px; cursor: pointer;
        }
        .modal-body {
            padding: 20px;
        }
        .form-group {
            margin-bottom: 14px;
        }
        .form-group label {
            display: block;
            font-size: 12px;
            font-weight: 700;
            color: var(--brown-dark);
            margin-bottom: 6px;
        }
        .dark-mode .form-group label {
            color: #FFD700;
        }
        .form-input {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            font-size: 14px;
            box-sizing: border-box;
            background: var(--card-bg);
            color: var(--text-dark);
        }
        .form-input:focus {
            outline: none;
            border-color: var(--gold-dark);
            box-shadow: 0 0 0 3px rgba(218,165,32,0.2);
        }
        .modal-footer {
            padding: 12px 20px 18px;
            display: flex;
            justify-content: flex-end;
            gap: 10px;
        }

        /* Toast notifications */
        .toast-msg {
            position: fixed;
            bottom: 24px;
            right: 24px;
            background: #10B981;
            color: white;
            padding: 12px 22px;
            border-radius: 30px;
            font-weight: 700;
            font-size: 14px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
            z-index: 2000;
            display: none;
            align-items: center;
            gap: 8px;
        }
        .toast-msg.error {
            background: #EF4444;
        }
        .toast-msg.show {
            display: flex;
            animation: toastUp 0.3s ease;
        }
        @keyframes toastUp {
            from { transform: translateY(20px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }
    </style>
</head>
<body>
    <?php include 'mobile_nav.php'; ?>

    <div class="curriculum-container">
        <!-- Hero Header -->
        <div class="page-hero">
            <div>
                <h1><span>📖</span> የመማሪያ መጻሕፍትና ምዕራፎች ማስተዳደሪያ</h1>
                <p>በየክፍሉ ያሉትን መጻሕፍት፣ ምዕራፎች (Units) እና ንዑሳን ርዕሶች (Topics) በቀጥታ ያርሙ ወይም አዳዲሶችን ያክሉ::</p>
            </div>
            <div>
                <button type="button" class="btn-modern btn-gold" onclick="openAddSubjectModal()">
                    ➕ አዲስ መጽሐፍ ጨምር
                </button>
            </div>
        </div>

        <!-- 1. Grade Selector Pills -->
        <div class="grade-pill-container">
            <?php foreach ($allGrades as $g): 
                // Count subjects for this grade
                $gCountRow = dbFetchOne($conn, "SELECT COUNT(DISTINCT subject_name) as cnt FROM curriculum_topics WHERE grade_level = ?", "i", [$g]);
                $sCount = $gCountRow ? intval($gCountRow['cnt']) : 0;
            ?>
            <a href="manage_curriculum.php?grade=<?php echo $g; ?>" 
               class="grade-pill <?php echo ($selectedGrade === $g) ? 'active' : ''; ?>">
                <span>🎓 <?php echo $g; ?>ኛ ክፍል</span>
                <span class="grade-badge"><?php echo $sCount; ?> መጻሕፍት</span>
            </a>
            <?php endforeach; ?>
        </div>

        <!-- 2. Main Layout: Books Sidebar + Chapters Workspace -->
        <div class="curriculum-layout">
            <!-- Left: Books / Subjects List -->
            <div class="books-sidebar">
                <div class="sidebar-header">
                    <h3><span>📚</span> የ<?php echo $selectedGrade; ?>ኛ ክፍል መጻሕፍት</h3>
                    <button type="button" class="btn-sm-action" onclick="openAddSubjectModal()" title="መጽሐፍ ጨምር">➕ አዲስ</button>
                </div>
                <div class="book-list">
                    <?php if (empty($subjects)): ?>
                        <div style="padding:24px; text-align:center; color:var(--text-muted); font-size:13px;">
                            በዚህ ክፍል ውስጥ የተመዘገበ መጽሐፍ የለም። «አዲስ» የሚለውን ተጭነው መጽሐፍ ያክሉ!
                        </div>
                    <?php else: ?>
                        <?php foreach ($subjects as $sb): 
                            $isActive = ($selectedSubject === $sb['subject_name']);
                        ?>
                        <a href="manage_curriculum.php?grade=<?php echo $selectedGrade; ?>&subject=<?php echo urlencode($sb['subject_name']); ?>" 
                           class="book-item <?php echo $isActive ? 'active' : ''; ?>">
                            <div class="book-item-title">
                                <span><?php echo getSubjectIcon($sb['subject_name']); ?></span>
                                <span><?php echo htmlspecialchars($sb['subject_name']); ?></span>
                            </div>
                            <div class="book-meta">
                                <?php echo $sb['total_chapters']; ?> ምዕራፍ
                            </div>
                        </a>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Right: Chapters & Topics Editor -->
            <div class="chapters-workspace">
                <?php if (empty($selectedSubject)): ?>
                    <div style="text-align:center; padding:50px 20px; color:var(--text-muted);">
                        <span style="font-size:40px; display:block; margin-bottom:12px;">📚</span>
                        <h3>እባክዎ ከግራ በኩል የሚፈልጉትን መጽሐፍ ይምረጡ</h3>
                        <p style="font-size:13px;">ወይም አዲስ መጽሐፍ ለማከል «አዲስ መጽሐፍ ጨምር» የሚለውን ይጫኑ::</p>
                    </div>
                <?php else: ?>
                    <div class="workspace-header">
                        <div class="workspace-title">
                            <h2>
                                <span><?php echo getSubjectIcon($selectedSubject); ?></span>
                                <?php echo htmlspecialchars($selectedSubject); ?>
                            </h2>
                            <span>የ<?php echo $selectedGrade; ?>ኛ ክፍል • <?php echo count($chapters); ?> ምዕራፎች ተመዝግበዋል</span>
                        </div>
                        <div class="workspace-actions">
                            <button type="button" class="btn-modern btn-gold" onclick="openAddChapterModal()">
                                ➕ አዲስ ምዕራፍ
                            </button>
                            <button type="button" class="btn-modern btn-brown" onclick="openRenameSubjectModal('<?php echo htmlspecialchars($selectedSubject, ENT_QUOTES); ?>')">
                                ✏️ የመጽሐፍ ስም ቀይር
                            </button>
                            <button type="button" class="btn-modern btn-outline-danger" onclick="deleteSubject('<?php echo htmlspecialchars($selectedSubject, ENT_QUOTES); ?>')">
                                🗑️ መጽሐፉን ሰርዝ
                            </button>
                        </div>
                    </div>

                    <!-- Chapters Accordion -->
                    <div id="chaptersContainer">
                        <?php if (empty($chapters)): ?>
                            <div style="text-align:center; padding:40px 20px; color:var(--text-muted);">
                                <span style="font-size:32px; display:block; margin-bottom:10px;">📝</span>
                                <p>በዚህ መጽሐፍ ውስጥ ምንም ምዕራፍ አልተገኘም። «አዲስ ምዕራፍ» የሚለውን ተጭነው ይጀምሩ!</p>
                            </div>
                        <?php else: 
                            $chapIndex = 0;
                            foreach ($chapters as $chapterTitle => $topicList): 
                                $chapIndex++;
                        ?>
                        <div class="chapter-card" id="chap-card-<?php echo $chapIndex; ?>">
                            <div class="chapter-header" onclick="toggleChapterAccordion('<?php echo $chapIndex; ?>')">
                                <div class="chapter-header-left">
                                    <span id="arrow-<?php echo $chapIndex; ?>">▼</span>
                                    <span><?php echo htmlspecialchars($chapterTitle); ?></span>
                                    <span class="chapter-topic-badge"><?php echo count($topicList); ?> ንዑስ ርዕሶች</span>
                                </div>
                                <div class="chapter-header-actions" onclick="event.stopPropagation()">
                                    <button type="button" class="btn-sm-action" onclick="openAddSubtopicModal('<?php echo htmlspecialchars($chapterTitle, ENT_QUOTES); ?>')">
                                        ➕ ርዕስ ጨምር
                                    </button>
                                    <button type="button" class="btn-sm-action" onclick="openRenameChapterModal('<?php echo htmlspecialchars($chapterTitle, ENT_QUOTES); ?>')">
                                        ✏️ አርም
                                    </button>
                                    <button type="button" class="btn-sm-action" style="color:var(--error-red);" onclick="deleteChapter('<?php echo htmlspecialchars($chapterTitle, ENT_QUOTES); ?>')">
                                        🗑️ ሰርዝ
                                    </button>
                                </div>
                            </div>
                            <div class="subtopics-container" id="subtopics-<?php echo $chapIndex; ?>">
                                <?php foreach ($topicList as $tp): ?>
                                <div class="subtopic-item" id="topic-row-<?php echo $tp['id']; ?>">
                                    <div class="subtopic-name">
                                        <span class="subtopic-bullet">🔹</span>
                                        <span id="topic-text-<?php echo $tp['id']; ?>"><?php echo htmlspecialchars($tp['sub_topic_name']); ?></span>
                                    </div>
                                    <div class="subtopic-actions">
                                        <button type="button" class="btn-sm-action" onclick="openEditSubtopicModal(<?php echo $tp['id']; ?>, '<?php echo htmlspecialchars($tp['sub_topic_name'], ENT_QUOTES); ?>')">
                                            ✏️ አርም
                                        </button>
                                        <button type="button" class="btn-sm-action" style="color:var(--error-red);" onclick="deleteSubtopic(<?php echo $tp['id']; ?>)">
                                            🗑️ ሰርዝ
                                        </button>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- MODAL 1: ADD NEW BOOK -->
    <div id="addSubjectModal" class="custom-modal">
        <div class="modal-box">
            <div class="modal-header">
                <span>➕ አዲስ መጽሐፍ ጨምር (ክፍል <?php echo $selectedGrade; ?>)</span>
                <button type="button" onclick="closeModal('addSubjectModal')">✕</button>
            </div>
            <form id="addSubjectForm" onsubmit="handleAddSubject(event)">
                <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                <input type="hidden" name="action" value="add_subject">
                <input type="hidden" name="grade_level" value="<?php echo $selectedGrade; ?>">
                <div class="modal-body">
                    <div class="form-group">
                        <label>የመጽሐፉ ስም (የትምህርት ዓይነት) *</label>
                        <input type="text" name="subject_name" class="form-input" required placeholder="ለምሳሌ፡ መሠረተ ሃይማኖት">
                    </div>
                    <div class="form-group">
                        <label>የመጀመሪያ ምዕራፍ ስም</label>
                        <input type="text" name="chapter_name" class="form-input" value="ምዕራፍ ፩" placeholder="ምዕራፍ ፩">
                    </div>
                    <div class="form-group">
                        <label>የመጀመሪያ ንዑስ ርዕስ</label>
                        <input type="text" name="sub_topic_name" class="form-input" value="፩.፩ መግቢያ" placeholder="፩.፩ መግቢያ">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-modern" style="background:var(--border-color);" onclick="closeModal('addSubjectModal')">ሰርዝ</button>
                    <button type="submit" class="btn-modern btn-gold">መጽሐፉን መዝግብ</button>
                </div>
            </form>
        </div>
    </div>

    <!-- MODAL 2: RENAME BOOK -->
    <div id="renameSubjectModal" class="custom-modal">
        <div class="modal-box">
            <div class="modal-header">
                <span>✏️ የመጽሐፉን ስም ቀይር</span>
                <button type="button" onclick="closeModal('renameSubjectModal')">✕</button>
            </div>
            <form id="renameSubjectForm" onsubmit="handleRenameSubject(event)">
                <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                <input type="hidden" name="action" value="rename_subject">
                <input type="hidden" name="grade_level" value="<?php echo $selectedGrade; ?>">
                <input type="hidden" name="old_subject_name" id="renameOldSubject">
                <div class="modal-body">
                    <div class="form-group">
                        <label>አዲስ የመጽሐፍ ስም *</label>
                        <input type="text" name="new_subject_name" id="renameNewSubject" class="form-input" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-modern" style="background:var(--border-color);" onclick="closeModal('renameSubjectModal')">ሰርዝ</button>
                    <button type="submit" class="btn-modern btn-gold">አስተካክል</button>
                </div>
            </form>
        </div>
    </div>

    <!-- MODAL 3: ADD CHAPTER -->
    <div id="addChapterModal" class="custom-modal">
        <div class="modal-box">
            <div class="modal-header">
                <span>➕ አዲስ ምዕራፍ ጨምር</span>
                <button type="button" onclick="closeModal('addChapterModal')">✕</button>
            </div>
            <form id="addChapterForm" onsubmit="handleAddChapter(event)">
                <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                <input type="hidden" name="action" value="add_chapter">
                <input type="hidden" name="grade_level" value="<?php echo $selectedGrade; ?>">
                <input type="hidden" name="subject_name" value="<?php echo htmlspecialchars($selectedSubject); ?>">
                <div class="modal-body">
                    <div class="form-group">
                        <label>የምዕራፉ ስም *</label>
                        <input type="text" name="chapter_name" class="form-input" required placeholder="ለምሳሌ፡ ምዕራፍ ፪ የጸሎት ምንነት">
                    </div>
                    <div class="form-group">
                        <label>የመጀመሪያ ንዑስ ርዕስ (አማራጭ)</label>
                        <input type="text" name="sub_topic_name" class="form-input" placeholder="ለምሳሌ፡ ፪.፩ የጸሎት ጥቅም">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-modern" style="background:var(--border-color);" onclick="closeModal('addChapterModal')">ሰርዝ</button>
                    <button type="submit" class="btn-modern btn-gold">ምዕራፉን መዝግብ</button>
                </div>
            </form>
        </div>
    </div>

    <!-- MODAL 4: RENAME CHAPTER -->
    <div id="renameChapterModal" class="custom-modal">
        <div class="modal-box">
            <div class="modal-header">
                <span>✏️ የምዕራፉን ስም አርም</span>
                <button type="button" onclick="closeModal('renameChapterModal')">✕</button>
            </div>
            <form id="renameChapterForm" onsubmit="handleRenameChapter(event)">
                <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                <input type="hidden" name="action" value="rename_chapter">
                <input type="hidden" name="grade_level" value="<?php echo $selectedGrade; ?>">
                <input type="hidden" name="subject_name" value="<?php echo htmlspecialchars($selectedSubject); ?>">
                <input type="hidden" name="old_chapter_name" id="renameOldChapter">
                <div class="modal-body">
                    <div class="form-group">
                        <label>የምዕራፉ ትክክለኛ ስም *</label>
                        <input type="text" name="new_chapter_name" id="renameNewChapter" class="form-input" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-modern" style="background:var(--border-color);" onclick="closeModal('renameChapterModal')">ሰርዝ</button>
                    <button type="submit" class="btn-modern btn-gold">አስተካክል</button>
                </div>
            </form>
        </div>
    </div>

    <!-- MODAL 5: ADD SUB-TOPIC -->
    <div id="addSubtopicModal" class="custom-modal">
        <div class="modal-box">
            <div class="modal-header">
                <span>➕ አዲስ ንዑስ ርዕስ ጨምር</span>
                <button type="button" onclick="closeModal('addSubtopicModal')">✕</button>
            </div>
            <form id="addSubtopicForm" onsubmit="handleAddSubtopic(event)">
                <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                <input type="hidden" name="action" value="add_subtopic">
                <input type="hidden" name="grade_level" value="<?php echo $selectedGrade; ?>">
                <input type="hidden" name="subject_name" value="<?php echo htmlspecialchars($selectedSubject); ?>">
                <input type="hidden" name="chapter_name" id="addSubtopicChapter">
                <div class="modal-body">
                    <p style="font-size:12px; color:var(--text-muted); margin-bottom:12px;" id="addSubtopicChapterLabel"></p>
                    <div class="form-group">
                        <label>ንዑስ ርዕስ *</label>
                        <input type="text" name="sub_topic_name" id="newSubtopicInput" class="form-input" required placeholder="ለምሳሌ፡ ፩.፪ የቅዱሳን አማላጅነት">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-modern" style="background:var(--border-color);" onclick="closeModal('addSubtopicModal')">ሰርዝ</button>
                    <button type="submit" class="btn-modern btn-gold">ርዕሱን ጨምር</button>
                </div>
            </form>
        </div>
    </div>

    <!-- MODAL 6: EDIT SUB-TOPIC -->
    <div id="editSubtopicModal" class="custom-modal">
        <div class="modal-box">
            <div class="modal-header">
                <span>✏️ ንዑስ ርዕስ አርም</span>
                <button type="button" onclick="closeModal('editSubtopicModal')">✕</button>
            </div>
            <form id="editSubtopicForm" onsubmit="handleEditSubtopic(event)">
                <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                <input type="hidden" name="action" value="edit_subtopic">
                <input type="hidden" name="topic_id" id="editTopicId">
                <div class="modal-body">
                    <div class="form-group">
                        <label>የንዑስ ርዕሱ ትክክለኛ ስም *</label>
                        <input type="text" name="sub_topic_name" id="editSubtopicInput" class="form-input" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-modern" style="background:var(--border-color);" onclick="closeModal('editSubtopicModal')">ሰርዝ</button>
                    <button type="submit" class="btn-modern btn-gold">አስተካክል</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Toast -->
    <div id="toast" class="toast-msg"></div>

    <script>
    // Accordion Toggle
    function toggleChapterAccordion(index) {
        const container = document.getElementById('subtopics-' + index);
        const arrow = document.getElementById('arrow-' + index);
        if (!container) return;
        if (container.style.display === 'none') {
            container.style.display = 'flex';
            if (arrow) arrow.textContent = '▼';
        } else {
            container.style.display = 'none';
            if (arrow) arrow.textContent = '▶';
        }
    }

    // Modal Helpers
    function openModal(id) {
        document.getElementById(id).classList.add('show');
    }
    function closeModal(id) {
        document.getElementById(id).classList.remove('show');
    }

    // Toast Helper
    function showToast(msg, isError = false) {
        const t = document.getElementById('toast');
        t.textContent = msg;
        t.className = 'toast-msg ' + (isError ? 'error' : '') + ' show';
        setTimeout(() => t.classList.remove('show'), 3500);
    }

    // Modal Triggers
    function openAddSubjectModal() {
        openModal('addSubjectModal');
    }
    function openRenameSubjectModal(currentName) {
        document.getElementById('renameOldSubject').value = currentName;
        document.getElementById('renameNewSubject').value = currentName;
        openModal('renameSubjectModal');
    }
    function openAddChapterModal() {
        openModal('addChapterModal');
    }
    function openRenameChapterModal(chapterName) {
        document.getElementById('renameOldChapter').value = chapterName;
        document.getElementById('renameNewChapter').value = chapterName;
        openModal('renameChapterModal');
    }
    function openAddSubtopicModal(chapterName) {
        document.getElementById('addSubtopicChapter').value = chapterName;
        document.getElementById('addSubtopicChapterLabel').textContent = 'ምዕራፍ፦ ' + chapterName;
        document.getElementById('newSubtopicInput').value = '';
        openModal('addSubtopicModal');
    }
    function openEditSubtopicModal(topicId, currentText) {
        document.getElementById('editTopicId').value = topicId;
        document.getElementById('editSubtopicInput').value = currentText;
        openModal('editSubtopicModal');
    }

    // AJAX Form Submissions
    async function submitFormAjax(formEl, modalId) {
        const fd = new FormData(formEl);
        try {
            const res = await fetch('manage_curriculum.php', { method: 'POST', body: fd });
            const data = await res.json();
            if (data.success) {
                showToast(data.message);
                closeModal(modalId);
                setTimeout(() => window.location.reload(), 700);
            } else {
                showToast(data.message, true);
            }
        } catch (e) {
            showToast('የኔትወርክ ወይም የሰርቨር ችግር አጋጥሟል!', true);
        }
    }

    function handleAddSubject(e) { e.preventDefault(); submitFormAjax(e.target, 'addSubjectModal'); }
    function handleRenameSubject(e) { e.preventDefault(); submitFormAjax(e.target, 'renameSubjectModal'); }
    function handleAddChapter(e) { e.preventDefault(); submitFormAjax(e.target, 'addChapterModal'); }
    function handleRenameChapter(e) { e.preventDefault(); submitFormAjax(e.target, 'renameChapterModal'); }
    function handleAddSubtopic(e) { e.preventDefault(); submitFormAjax(e.target, 'addSubtopicModal'); }
    function handleEditSubtopic(e) { e.preventDefault(); submitFormAjax(e.target, 'editSubtopicModal'); }

    // Direct Deletion Helpers with Confirmations
    async function deleteSubject(subjectName) {
        if (!confirm(`«${subjectName}» የተሰኘውን መጽሐፍ እና በእርሱ ስር ያሉ ምዕራፎችንና ርዕሶችን በሙሉ መሰረዝ ይፈልጋሉ? ይህ ድርጊት አይመለስም!`)) {
            return;
        }
        const fd = new FormData();
        fd.append('action', 'delete_subject');
        fd.append('grade_level', '<?php echo $selectedGrade; ?>');
        fd.append('subject_name', subjectName);
        fd.append('csrf_token', '<?php echo generateCsrfToken(); ?>');
        
        try {
            const res = await fetch('manage_curriculum.php', { method: 'POST', body: fd });
            const data = await res.json();
            if (data.success) {
                showToast(data.message);
                setTimeout(() => window.location.href = 'manage_curriculum.php?grade=<?php echo $selectedGrade; ?>', 700);
            } else {
                showToast(data.message, true);
            }
        } catch (e) {
            showToast('ስህተት ተፈጥሯል!', true);
        }
    }

    async function deleteChapter(chapterName) {
        if (!confirm(`«${chapterName}» የተሰኘውን ምዕራፍ እና በስሩ ያሉትን ንዑሳን ርዕሶች መሰረዝ ይፈልጋሉ?`)) {
            return;
        }
        const fd = new FormData();
        fd.append('action', 'delete_chapter');
        fd.append('grade_level', '<?php echo $selectedGrade; ?>');
        fd.append('subject_name', '<?php echo htmlspecialchars($selectedSubject, ENT_QUOTES); ?>');
        fd.append('chapter_name', chapterName);
        fd.append('csrf_token', '<?php echo generateCsrfToken(); ?>');

        try {
            const res = await fetch('manage_curriculum.php', { method: 'POST', body: fd });
            const data = await res.json();
            if (data.success) {
                showToast(data.message);
                setTimeout(() => window.location.reload(), 700);
            } else {
                showToast(data.message, true);
            }
        } catch (e) {
            showToast('ስህተት ተፈጥሯል!', true);
        }
    }

    async function deleteSubtopic(topicId) {
        if (!confirm('ይህንን ንዑስ ርዕስ መሰረዝ ይፈልጋሉ?')) {
            return;
        }
        const fd = new FormData();
        fd.append('action', 'delete_subtopic');
        fd.append('topic_id', topicId);
        fd.append('csrf_token', '<?php echo generateCsrfToken(); ?>');

        try {
            const res = await fetch('manage_curriculum.php', { method: 'POST', body: fd });
            const data = await res.json();
            if (data.success) {
                showToast(data.message);
                const el = document.getElementById('topic-row-' + topicId);
                if (el) {
                    el.style.opacity = '0';
                    setTimeout(() => el.remove(), 250);
                }
            } else {
                showToast(data.message, true);
            }
        } catch (e) {
            showToast('ስህተት ተፈጥሯል!', true);
        }
    }

    // Close modal on escape key
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            document.querySelectorAll('.custom-modal.show').forEach(m => m.classList.remove('show'));
        }
    });
    </script>
</body>
</html>
