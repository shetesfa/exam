<?php
require_once 'db.php';
requireTeacher();
ensureExamTablesExist($conn);

$teacher_id = intval($_SESSION['user_id'] ?? 0);
$is_admin = isAdmin();

// Current semester and academic year
$current_semester = getCurrentSemester($conn);
$semester_id = $current_semester ? intval($current_semester['id']) : 0;
$active_year = 2018;
$year_row = dbFetchOne($conn, "SELECT ethiopian_year FROM academic_years WHERE status = 'active' LIMIT 1");
if ($year_row && !empty($year_row['ethiopian_year'])) {
    $active_year = intval($year_row['ethiopian_year']);
}

// Get teacher's classes with safe fallback
$my_classes = [];
if ($is_admin) {
    $my_classes = dbFetchAll($conn, "SELECT id as class_id, name as class_name, '' as assigned_subject_name FROM classes ORDER BY id ASC");
} else {
    $my_classes = getTeacherClasses($conn, $teacher_id, $semester_id);
    if (empty($my_classes)) {
        $my_classes = dbFetchAll($conn, "SELECT tc.class_id, c.name as class_name, tc.subject_name as assigned_subject_name 
                                         FROM teacher_class tc 
                                         JOIN classes c ON tc.class_id = c.id 
                                         WHERE tc.teacher_id = ? 
                                         ORDER BY c.id ASC", "i", [$teacher_id]);
    }
}

// Available system subjects
$allSubjectsList = getSubjects($conn);
$system_subjects = [];
if (is_array($allSubjectsList)) {
    foreach ($allSubjectsList as $s) {
        if (!empty($s['name'])) {
            $system_subjects[] = trim((string)$s['name']);
        }
    }
}

$message = '';
$error = '';
$action = $_POST['action'] ?? ($_GET['action'] ?? '');
$exam_id = intval($_POST['exam_id'] ?? ($_GET['id'] ?? 0));

// CSRF check on POST
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = "የደህንነት ማረጋገጫ አልተሳካም! እባክዎ ገጹን አድሰው እንደገና ይሞክሩ።";
        $action = '';
    }
}

// 1. DELETE EXAM
if ($action === 'delete' && $exam_id > 0) {
    $check = dbFetchOne($conn, "SELECT * FROM exams WHERE id = ?", "i", [$exam_id]);
    if ($check && ($is_admin || ($check['teacher_id'] == $teacher_id && $check['status'] !== 'approved'))) {
        dbExecute($conn, "DELETE FROM exam_questions WHERE exam_id = ?", "i", [$exam_id]);
        dbExecute($conn, "DELETE FROM exams WHERE id = ?", "i", [$exam_id]);
        $message = "ፈተናው በተሳካ ሁኔታ ተሰርዟል!";
    } else {
        $error = "ፈተናውን የመሰረዝ ፈቃድ የለዎትም ወይም የጸደቀ ፈተና ሊሰረዝ አይችልም።";
    }
    $action = '';
    $exam_id = 0;
}

// 2. DUPLICATE EXAM
if ($action === 'duplicate' && $exam_id > 0) {
    $src = dbFetchOne($conn, "SELECT * FROM exams WHERE id = ?", "i", [$exam_id]);
    if ($src && ($is_admin || $src['teacher_id'] == $teacher_id)) {
        $new_title = ($src['title'] ?? 'ፈተና') . " (ቅጂ)";
        $dup_exam_id = dbInsert($conn, 
            "INSERT INTO exams (teacher_id, class_id, subject_name, semester_id, ethiopian_year, exam_type, title, instructions, total_marks, duration_minutes, status) 
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'draft')",
            "iisiisssdi",
            [$teacher_id, $src['class_id'], $src['subject_name'], $src['semester_id'], $src['ethiopian_year'], $src['exam_type'], $new_title, $src['instructions'] ?? '', $src['total_marks'] ?? 100, $src['duration_minutes'] ?? 60]
        );
        if ($dup_exam_id) {
            $src_questions = dbFetchAll($conn, "SELECT * FROM exam_questions WHERE exam_id = ? ORDER BY order_num ASC, id ASC", "i", [$exam_id]);
            foreach ($src_questions as $q) {
                dbExecute($conn, 
                    "INSERT INTO exam_questions (exam_id, section_title, question_type, question_text, options_json, correct_answer, marks, order_num, blank_lines) 
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)",
                    "isssssdii",
                    [$dup_exam_id, $q['section_title'] ?? '', $q['question_type'] ?? 'choice', $q['question_text'] ?? '', $q['options_json'] ?? null, $q['correct_answer'] ?? '', $q['marks'] ?? 1.0, $q['order_num'] ?? 1, $q['blank_lines'] ?? 3]
                );
            }
            $message = "ፈተናው በተሳካ ሁኔታ ተባዝቷል! አሁን ማስተካከል ይችላሉ።";
            header("Location: exam_author.php?action=edit&id=" . $dup_exam_id . "&msg=" . urlencode($message));
            exit();
        }
    }
    $action = '';
}

// 3. SAVE / SUBMIT EXAM
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && ($action === 'save' || $action === 'submit_to_admin')) {
    $class_id = intval($_POST['class_id'] ?? 0);
    $subject_name = trim((string)($_POST['subject_name'] ?? ''));
    $exam_semester_id = intval($_POST['semester_id'] ?? $semester_id);
    $ethiopian_year = intval($_POST['ethiopian_year'] ?? $active_year);
    $exam_type = trim((string)($_POST['exam_type'] ?? 'mid'));
    $title = trim((string)($_POST['title'] ?? ''));
    $instructions = trim((string)($_POST['instructions'] ?? ''));
    $duration_minutes = max(10, intval($_POST['duration_minutes'] ?? 60));
    $questions_raw = $_POST['questions'] ?? [];

    if ($class_id <= 0 || empty($subject_name)) {
        $error = "እባክዎ ክፍል እና የትምህርት ዓይነት በትክክል ይምረጡ!";
    } else {
        $class_info = dbFetchOne($conn, "SELECT name FROM classes WHERE id = ?", "i", [$class_id]);
        $c_name = $class_info ? $class_info['name'] : "ክፍል";
        $sem_name = ($exam_semester_id == 2) ? "2ኛ መንፈቀ ዓመት" : "1ኛ መንፈቀ ዓመት";
        $type_label = ($exam_type === 'final') ? "የማጠቃለያ ፈተና" : (($exam_type === 'quiz') ? "የክፍል ፈተና" : "የአጋማሽ ፈተና");

        if (empty($title)) {
            $title = "የ{$ethiopian_year} ዓ.ም የ{$c_name} የ{$subject_name} የ{$sem_name} {$type_label}";
        }

        $new_status = ($action === 'submit_to_admin') ? 'submitted' : 'draft';

        $total_marks = 0.0;
        if (is_array($questions_raw)) {
            foreach ($questions_raw as $q) {
                $total_marks += floatval($q['marks'] ?? 0);
            }
        }
        if ($total_marks <= 0) {
            $total_marks = floatval($_POST['total_marks'] ?? 100);
        }

        $target_exam_id = 0;
        if ($exam_id > 0) {
            $existing = dbFetchOne($conn, "SELECT * FROM exams WHERE id = ?", "i", [$exam_id]);
            if (!$existing || (!$is_admin && $existing['teacher_id'] != $teacher_id && $existing['status'] === 'approved')) {
                $error = "ፈተናውን ለማስተካከል ፈቃድ የለዎትም።";
            } else {
                dbExecute($conn, 
                    "UPDATE exams SET class_id = ?, subject_name = ?, semester_id = ?, ethiopian_year = ?, exam_type = ?, title = ?, instructions = ?, total_marks = ?, duration_minutes = ?, status = ? 
                     WHERE id = ?",
                    "isiisssdisi",
                    [$class_id, $subject_name, $exam_semester_id, $ethiopian_year, $exam_type, $title, $instructions, $total_marks, $duration_minutes, $new_status, $exam_id]
                );
                $target_exam_id = $exam_id;
            }
        } else {
            $target_exam_id = dbInsert($conn, 
                "INSERT INTO exams (teacher_id, class_id, subject_name, semester_id, ethiopian_year, exam_type, title, instructions, total_marks, duration_minutes, status) 
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
                "iisiisssdis",
                [$teacher_id, $class_id, $subject_name, $exam_semester_id, $ethiopian_year, $exam_type, $title, $instructions, $total_marks, $duration_minutes, $new_status]
            );
        }

        if (!empty($target_exam_id)) {
            dbExecute($conn, "DELETE FROM exam_questions WHERE exam_id = ?", "i", [$target_exam_id]);

            if (is_array($questions_raw)) {
                $order = 1;
                foreach ($questions_raw as $q) {
                    $q_type = (string)($q['type'] ?? 'choice');
                    $q_text = trim((string)($q['text'] ?? ''));
                    if ($q_text === '') continue;

                    $sec_title = trim((string)($q['section_title'] ?? ''));
                    $q_marks = max(0.25, floatval($q['marks'] ?? 1.0));
                    $q_blank_lines = max(1, min(20, intval($q['blank_lines'] ?? 3)));
                    $correct = trim((string)($q['correct_answer'] ?? ''));
                    $options_json = null;

                    if ($q_type === 'choice') {
                        $opts = [];
                        if (!empty($q['choice_a'])) $opts['ሀ'] = trim((string)$q['choice_a']);
                        if (!empty($q['choice_b'])) $opts['ለ'] = trim((string)$q['choice_b']);
                        if (!empty($q['choice_c'])) $opts['ሐ'] = trim((string)$q['choice_c']);
                        if (!empty($q['choice_d'])) $opts['መ'] = trim((string)$q['choice_d']);
                        if (!empty($q['choice_e'])) $opts['ሠ'] = trim((string)$q['choice_e']);
                        $options_json = json_encode($opts, JSON_UNESCAPED_UNICODE);
                    } elseif ($q_type === 'matching') {
                        $matching_pairs = [];
                        $left_items = $q['match_left'] ?? [];
                        $right_items = $q['match_right'] ?? [];
                        if (is_array($left_items)) {
                            foreach ($left_items as $idx => $l_val) {
                                $l_trim = trim((string)$l_val);
                                $r_trim = trim((string)($right_items[$idx] ?? ''));
                                if ($l_trim !== '' || $r_trim !== '') {
                                    $matching_pairs[] = [
                                        'num' => $idx + 1,
                                        'left' => $l_trim,
                                        'right' => $r_trim
                                    ];
                                }
                            }
                        }
                        $options_json = json_encode($matching_pairs, JSON_UNESCAPED_UNICODE);
                    }

                    dbExecute($conn, 
                        "INSERT INTO exam_questions (exam_id, section_title, question_type, question_text, options_json, correct_answer, marks, order_num, blank_lines) 
                         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)",
                        "isssssdii",
                        [$target_exam_id, $sec_title, $q_type, $q_text, $options_json, $correct, $q_marks, $order, $q_blank_lines]
                    );
                    $order++;
                }
            }

            $succ_msg = ($action === 'submit_to_admin') 
                ? "ፈተናው በተሳካ ሁኔታ ተዘጋጅቶ ለትምህርት ክፍል ተልኳል! ✅"
                : "ፈተናው በረቂቅነት (Draft) ተቀምጧል! 💾";
            header("Location: exam_author.php?msg=" . urlencode($succ_msg));
            exit();
        }
    }
}

// Fetch single exam for editing
$editing_exam = null;
$editing_questions = [];
if (($action === 'edit' || $action === 'view') && $exam_id > 0) {
    $editing_exam = dbFetchOne($conn, "SELECT * FROM exams WHERE id = ?", "i", [$exam_id]);
    if ($editing_exam) {
        $editing_questions = dbFetchAll($conn, "SELECT * FROM exam_questions WHERE exam_id = ? ORDER BY order_num ASC, id ASC", "i", [$exam_id]);
    }
}

// Fetch list of exams authored by this teacher (or all if admin)
$my_exams_query = "SELECT e.*, COALESCE(c.name, 'ያልታወቀ ክፍል') as class_name, COALESCE(u.name, 'ያልታወቀ መምህር') as teacher_name, 
                   (SELECT COUNT(*) FROM exam_questions eq WHERE eq.exam_id = e.id) as question_count 
                   FROM exams e 
                   LEFT JOIN classes c ON e.class_id = c.id 
                   LEFT JOIN users u ON e.teacher_id = u.id " .
                   ($is_admin ? "" : "WHERE e.teacher_id = ? ") .
                   "ORDER BY e.created_at DESC";

$my_exams = $is_admin 
    ? dbFetchAll($conn, $my_exams_query) 
    : dbFetchAll($conn, $my_exams_query, "i", [$teacher_id]);

$csrf_token = generateCsrfToken();
$nav_active = 'exam_author';
$page_title = "የፈተና ዝግጅትና መላኪያ ማዕከል";
?>
<!DOCTYPE html>
<html lang="am">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - አጸደ ትጉሃን</title>
    <link rel="icon" type="image/png" href="images/icon.png">
    <?php require_once 'pwa_head.php'; ?>
    <style>
        :root {
            --primary: #1e3a8a;
            --primary-light: #3b82f6;
            --secondary: #8B4513;
            --accent: #10b981;
            --danger: #ef4444;
            --bg-color: #f8fafc;
            --card-bg: #ffffff;
            --text-main: #1e293b;
            --text-muted: #64748b;
            --border: #e2e8f0;
            --radius: 14px;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: var(--bg-color);
            color: var(--text-main);
            margin: 0;
            padding: 0;
            padding-bottom: 100px;
        }

        .container {
            max-width: 1100px;
            margin: 0 auto;
            padding: 16px;
        }

        /* Offline Sync & Backup Alert */
        .offline-status-pill {
            display: none;
            background: #fef08a;
            color: #854d0e;
            border: 1.5px solid #eab308;
            border-radius: 30px;
            padding: 8px 16px;
            font-size: 0.85rem;
            font-weight: 700;
            margin-bottom: 14px;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            box-shadow: 0 2px 8px rgba(234, 179, 8, 0.2);
        }

        .header-card {
            background: linear-gradient(135deg, #1e3a8a 0%, #1e40af 100%);
            color: #ffffff;
            padding: 22px 20px;
            border-radius: var(--radius);
            box-shadow: 0 6px 18px rgba(30, 58, 138, 0.22);
            margin-bottom: 22px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 16px;
        }

        .header-title h1 {
            margin: 0;
            font-size: 1.45rem;
            font-weight: 800;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .header-title p {
            margin: 6px 0 0 0;
            font-size: 0.92rem;
            opacity: 0.92;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 10px 18px;
            font-size: 0.92rem;
            font-weight: 700;
            border-radius: 10px;
            cursor: pointer;
            border: none;
            transition: all 0.2s ease;
            text-decoration: none;
        }

        .btn:hover { transform: translateY(-1px); }
        .btn-primary { background-color: #2563eb; color: #ffffff; }
        .btn-primary:hover { background-color: #1d4ed8; }
        .btn-success { background-color: #10b981; color: #ffffff; }
        .btn-success:hover { background-color: #059669; }
        .btn-secondary { background-color: #64748b; color: #ffffff; }
        .btn-secondary:hover { background-color: #475569; }
        .btn-brown { background-color: #8B4513; color: #ffffff; }
        .btn-brown:hover { background-color: #6d360e; }
        .btn-outline { background: #ffffff; border: 1.5px solid var(--border); color: var(--text-main); }
        .btn-outline:hover { background: #f1f5f9; border-color: #cbd5e1; }
        .btn-danger { background-color: #ef4444; color: #ffffff; }
        .btn-danger:hover { background-color: #dc2626; }
        .btn-sm { padding: 6px 12px; font-size: 0.83rem; border-radius: 8px; }

        .alert {
            padding: 14px 18px;
            border-radius: 10px;
            margin-bottom: 20px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .alert-success { background: #dcfce7; color: #166534; border: 1.5px solid #bbf7d0; }
        .alert-error { background: #fee2e2; color: #991b1b; border: 1.5px solid #fecaca; }

        .card {
            background: var(--card-bg);
            border-radius: var(--radius);
            padding: 22px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.04);
            border: 1.5px solid var(--border);
            margin-bottom: 24px;
        }

        .card-header {
            border-bottom: 1.5px solid var(--border);
            padding-bottom: 14px;
            margin-bottom: 18px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 10px;
        }

        .card-header h2 {
            margin: 0;
            font-size: 1.25rem;
            color: #1e3a8a;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 16px;
        }

        .form-group {
            margin-bottom: 16px;
        }

        .form-group label {
            display: block;
            font-weight: 700;
            font-size: 0.9rem;
            margin-bottom: 6px;
            color: var(--text-main);
        }

        .form-control {
            width: 100%;
            padding: 10px 14px;
            border: 1.5px solid var(--border);
            border-radius: 10px;
            font-size: 0.95rem;
            box-sizing: border-box;
            background: #ffffff;
            color: var(--text-main);
            transition: all 0.2s;
        }

        .form-control:focus {
            outline: none;
            border-color: var(--primary-light);
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.18);
        }

        /* Quick Type Buttons Bar */
        .type-picker-bar {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            margin-bottom: 18px;
            padding: 10px;
            background: #f1f5f9;
            border-radius: 12px;
        }

        .btn-type-pill {
            background: #ffffff;
            border: 1.5px solid #cbd5e1;
            padding: 7px 14px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 700;
            color: var(--text-main);
            cursor: pointer;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .btn-type-pill:hover {
            border-color: #2563eb;
            color: #2563eb;
            background: #eff6ff;
        }

        /* Modern Question Box */
        .question-box {
            background: #ffffff;
            border: 1.5px solid #cbd5e1;
            border-radius: 12px;
            padding: 18px;
            margin-bottom: 18px;
            position: relative;
            box-shadow: 0 2px 6px rgba(0,0,0,0.02);
            transition: all 0.2s ease;
        }

        .question-box:hover {
            border-color: #94a3b8;
            box-shadow: 0 4px 14px rgba(0,0,0,0.06);
        }

        .q-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 12px;
            padding-bottom: 10px;
            border-bottom: 1px dashed #cbd5e1;
            flex-wrap: wrap;
            gap: 8px;
        }

        .q-badge {
            background: #1e3a8a;
            color: #ffffff;
            font-weight: 800;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.85rem;
        }

        .q-type-select {
            padding: 6px 10px;
            border-radius: 8px;
            border: 1.5px solid #cbd5e1;
            font-weight: 700;
            font-size: 0.85rem;
            background: #ffffff;
            color: #1e3a8a;
        }

        .options-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(210px, 1fr));
            gap: 10px;
            margin-top: 10px;
        }

        .option-item {
            display: flex;
            align-items: center;
            gap: 8px;
            background: #f8fafc;
            padding: 6px 10px;
            border: 1.5px solid #e2e8f0;
            border-radius: 8px;
        }

        .option-letter {
            font-weight: 800;
            color: #1e3a8a;
            min-width: 24px;
        }

        .badge-status {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.82rem;
            font-weight: 700;
        }
        .badge-draft { background: #fef3c7; color: #92400e; }
        .badge-submitted { background: #dbeafe; color: #1e40af; }
        .badge-approved { background: #dcfce7; color: #166534; }
        .badge-revision_requested { background: #fee2e2; color: #991b1b; }

        .exam-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.92rem;
        }

        .exam-table th, .exam-table td {
            padding: 12px 14px;
            text-align: left;
            border-bottom: 1px solid var(--border);
        }

        .exam-table th {
            background-color: #f1f5f9;
            font-weight: 800;
            color: #334155;
        }

        .exam-table tr:hover {
            background-color: #f8fafc;
        }

        /* Stats & Marks live meter */
        .stats-bar {
            display: flex;
            gap: 16px;
            flex-wrap: wrap;
            margin-bottom: 18px;
            background: #eff6ff;
            padding: 14px 18px;
            border-radius: 10px;
            border-left: 5px solid #2563eb;
            font-weight: 700;
            font-size: 0.95rem;
            align-items: center;
            justify-content: space-between;
        }

        .sticky-actions {
            position: sticky;
            bottom: 12px;
            background: #ffffff;
            padding: 14px 20px;
            border-radius: var(--radius);
            box-shadow: 0 -4px 20px rgba(0, 0, 0, 0.12);
            border: 1.5px solid var(--border);
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 12px;
            z-index: 100;
            margin-top: 24px;
        }

        /* Live Header Preview Modal */
        .modal-overlay {
            display: none;
            position: fixed;
            top: 0; left: 0; right: 0; bottom: 0;
            background: rgba(0,0,0,0.6);
            z-index: 9999;
            align-items: center;
            justify-content: center;
            padding: 16px;
        }

        .modal-box {
            background: #ffffff;
            border-radius: 14px;
            max-width: 750px;
            width: 100%;
            max-height: 90vh;
            overflow-y: auto;
            padding: 24px;
            box-shadow: 0 12px 30px rgba(0,0,0,0.25);
        }

        @media (max-width: 768px) {
            .form-grid { grid-template-columns: 1fr; }
            .sticky-actions { flex-direction: column; align-items: stretch; }
            .sticky-actions .btn { width: 100%; }
        }
    </style>
</head>
<body>
    <?php require_once 'mobile_nav.php'; ?>

    <div class="container">
        <!-- OFFLINE STATUS NOTIFICATION PILL -->
        <div id="offlinePill" class="offline-status-pill">
            <span style="display:flex; align-items:center; gap:8px;">
                <span>📴</span>
                <span><strong>ኦፍላይን ሁነታ ላይ ነዎት፦</strong> ያዘጋጁት ፈተና እና ጥያቄዎች በስልክዎ/ኮምፒውተርዎ ላይ በደህንነት እየተቀመጠ ነው።</span>
            </span>
            <button type="button" class="btn btn-sm btn-outline" style="background:#ffffff; color:#854d0e; padding:3px 10px;" onclick="exportExamOfflineJSON()">
                💾 ወደ ፋይል አውርድ
            </button>
        </div>

        <!-- RESTORE AUTO-SAVED DRAFT BANNER -->
        <div id="restoreBanner" class="alert alert-success" style="display:none; justify-content:space-between; flex-wrap:wrap; gap:10px;">
            <div style="display:flex; align-items:center; gap:8px;">
                <span>💡</span>
                <span>ያልተላከ የቀደመ ረቂቅ ፈተና በአሳሽዎ ውስጥ ተገኝቷል!</span>
            </div>
            <div style="display:flex; gap:8px;">
                <button type="button" class="btn btn-sm btn-success" onclick="restoreAutoSavedDraft()">መልስ (Restore)</button>
                <button type="button" class="btn btn-sm btn-outline" onclick="discardAutoSavedDraft()">አጽዳ (Discard)</button>
            </div>
        </div>

        <!-- Main Top Banner -->
        <div class="header-card">
            <div class="header-title">
                <h1>📝 የፈተና ዝግጅትና መላኪያ ማዕከል</h1>
                <p>መምህራን የፈተና ጥያቄዎችን በምርጫ፣ በእውነት/ሐሰት፣ በአዛምድ እና በክፍት ጥያቄ በቀላሉ የሚያዘጋጁበት እና ለትምህርት ክፍል የሚልኩበት</p>
            </div>
            <div style="display:flex; gap:8px; flex-wrap:wrap;">
                <?php if ($editing_exam || $action === 'new'): ?>
                    <a href="exam_author.php" class="btn btn-outline" style="background:#ffffff; color:#1e3a8a;">⬅️ ወደ ፈተናዎች ዝርዝር</a>
                    <button type="button" class="btn btn-outline" style="background:#ffffff; color:#1e3a8a;" onclick="openPreviewModal()">
                        👁️ የራስጌ ቅድመ-ዕይታ
                    </button>
                <?php else: ?>
                    <button type="button" class="btn btn-outline" style="background:#ffffff; color:#1e3a8a;" onclick="document.getElementById('importFileInput').click()">
                        📂 ፈተና ጫን (Import)
                    </button>
                    <input type="file" id="importFileInput" accept=".json" style="display:none;" onchange="importExamJSON(event)">
                    <a href="exam_author.php?action=new" class="btn btn-brown">➕ አዲስ ፈተና አዘጋጅ</a>
                <?php endif; ?>
            </div>
        </div>

        <?php if (!empty($message) || !empty($_GET['msg'])): ?>
            <div class="alert alert-success">
                <span>✅</span>
                <span><?php echo htmlspecialchars((string)($message ?: $_GET['msg'])); ?></span>
            </div>
        <?php endif; ?>

        <?php if (!empty($error)): ?>
            <div class="alert alert-error">
                <span>⚠️</span>
                <span><?php echo htmlspecialchars((string)$error); ?></span>
            </div>
        <?php endif; ?>

        <?php if ($action === 'new' || $editing_exam): ?>
            <!-- EXAM AUTHORING INTERACTIVE FORM -->
            <?php 
                $is_edit = !empty($editing_exam);
                $curr_title = $editing_exam['title'] ?? '';
                $curr_class = $editing_exam['class_id'] ?? ($_GET['class_id'] ?? ($my_classes[0]['class_id'] ?? 0));
                $curr_subject = $editing_exam['subject_name'] ?? ($my_classes[0]['assigned_subject_name'] ?? '');
                $curr_year = $editing_exam['ethiopian_year'] ?? $active_year;
                $curr_sem = $editing_exam['semester_id'] ?? $semester_id;
                $curr_type = $editing_exam['exam_type'] ?? 'mid';
                $curr_inst = $editing_exam['instructions'] ?? 'ሁሉንም ጥያቄዎች በትክክል አንብበህ/ሽ መልስ/ሽ።';
                $curr_duration = $editing_exam['duration_minutes'] ?? 60;
                $curr_total = $editing_exam['total_marks'] ?? 100;
                $curr_status = $editing_exam['status'] ?? 'draft';
            ?>

            <form method="POST" action="exam_author.php" id="examForm">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                <input type="hidden" name="exam_id" id="exam_id" value="<?php echo $is_edit ? $editing_exam['id'] : 0; ?>">
                <input type="hidden" name="action" id="formAction" value="save">

                <!-- 1. Exam Meta Info Card -->
                <div class="card">
                    <div class="card-header">
                        <h2>📋 የፈተናው አጠቃላይ መረጃ</h2>
                        <div style="display:flex; align-items:center; gap:8px;">
                            <span class="badge-status badge-<?php echo htmlspecialchars((string)$curr_status); ?>">
                                ሁኔታ፦ <?php 
                                    echo ($curr_status === 'draft') ? 'ረቂቅ (Draft)' : 
                                        (($curr_status === 'submitted') ? 'ለትምህርት ክፍል የተላከ' : 
                                        (($curr_status === 'approved') ? 'የጸደቀ ✅' : 'ማስተካከያ የተጠየቀበት ⚠️'));
                                ?>
                            </span>
                            <span id="autoSaveIndicator" style="font-size:0.8rem; color:#10b981; font-weight:700;">
                                ✓ ራስ-ሰር ተቀምጧል
                            </span>
                        </div>
                    </div>

                    <?php if ($curr_status === 'revision_requested' && !empty($editing_exam['admin_feedback'])): ?>
                        <div class="alert alert-error" style="margin-bottom: 16px;">
                            <strong>💬 የትምህርት ክፍል አስተያየት፦</strong>
                            <p style="margin:4px 0 0 0;"><?php echo nl2br(htmlspecialchars((string)$editing_exam['admin_feedback'])); ?></p>
                        </div>
                    <?php endif; ?>

                    <div class="form-grid">
                        <div class="form-group">
                            <label for="class_id">ክፍል <span style="color:red;">*</span></label>
                            <select name="class_id" id="class_id" class="form-control" required onchange="autoFillTitle(); triggerAutoSave();">
                                <option value="">-- ክፍል ይምረጡ --</option>
                                <?php foreach ($my_classes as $cls): ?>
                                    <option value="<?php echo $cls['class_id']; ?>" 
                                            data-sub="<?php echo htmlspecialchars((string)($cls['assigned_subject_name'] ?? '')); ?>"
                                            data-name="<?php echo htmlspecialchars((string)$cls['class_name']); ?>"
                                            <?php echo ($curr_class == $cls['class_id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars((string)$cls['class_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="subject_name">የትምህርት ዓይነት <span style="color:red;">*</span></label>
                            <input type="text" name="subject_name" id="subject_name" list="subject_suggestions" class="form-control" 
                                   value="<?php echo htmlspecialchars((string)$curr_subject); ?>" placeholder="ለምሳሌ፦ ሥርዓተ ቤተክርስቲያን" required oninput="autoFillTitle(); triggerAutoSave();">
                            <datalist id="subject_suggestions">
                                <?php foreach ($system_subjects as $s_name): ?>
                                    <option value="<?php echo htmlspecialchars((string)$s_name); ?>">
                                <?php endforeach; ?>
                            </datalist>
                        </div>

                        <div class="form-group">
                            <label for="ethiopian_year">የትምህርት ዘመን (ዓ.ም)</label>
                            <input type="number" name="ethiopian_year" id="ethiopian_year" class="form-control" 
                                   value="<?php echo htmlspecialchars((string)$curr_year); ?>" required oninput="autoFillTitle(); triggerAutoSave();">
                        </div>

                        <div class="form-group">
                            <label for="semester_id">መንፈቀ ዓመት (ሰሚስተር)</label>
                            <select name="semester_id" id="semester_id" class="form-control" onchange="autoFillTitle(); triggerAutoSave();">
                                <option value="1" <?php echo ($curr_sem == 1) ? 'selected' : ''; ?>>1ኛ መንፈቀ ዓመት</option>
                                <option value="2" <?php echo ($curr_sem == 2) ? 'selected' : ''; ?>>2ኛ መንፈቀ ዓመት</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="exam_type">የፈተና ዓይነት</label>
                            <select name="exam_type" id="exam_type" class="form-control" onchange="autoFillTitle(); triggerAutoSave();">
                                <option value="mid" <?php echo ($curr_type === 'mid') ? 'selected' : ''; ?>>የአጋማሽ ፈተና (Mid Exam)</option>
                                <option value="final" <?php echo ($curr_type === 'final') ? 'selected' : ''; ?>>የማጠቃለያ ፈተና (Final Exam)</option>
                                <option value="quiz" <?php echo ($curr_type === 'quiz') ? 'selected' : ''; ?>>የክፍል ፈተና (Quiz / Test)</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="duration_minutes">የሚፈጀው ጊዜ (በደቂቃ)</label>
                            <input type="number" name="duration_minutes" id="duration_minutes" class="form-control" 
                                   value="<?php echo htmlspecialchars((string)$curr_duration); ?>" min="10" max="300" oninput="triggerAutoSave();">
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="title">የፈተናው ይፋዊ ሙሉ ርዕስ (ራስጌ ላይ የሚወጣ)</label>
                        <input type="text" name="title" id="title" class="form-control" 
                               value="<?php echo htmlspecialchars((string)$curr_title); ?>" 
                               placeholder="የ2018 ዓ.ም የ8ኛ ክፍል የሥርዓተ ቤተክርስቲያን የ2ኛመንፈቀ ዓመት የአጋማሽ ፈተና" oninput="triggerAutoSave();">
                        <small style="color:var(--text-muted); font-size:0.83rem;">ይህ ርዕስ በፈተናው ወረቀት ራስጌ ላይ በመሃል ደምቆ ይታተማል። ከላይ ያሉትን ሲመርጡ በራሱ ይሞላል ወይም እንደፈለጉ ማስተካከል ይችላሉ።</small>
                    </div>

                    <div class="form-group" style="margin-bottom:0;">
                        <label for="instructions">ለተማሪዎች አጠቃላይ መመሪያ (Instructions)</label>
                        <textarea name="instructions" id="instructions" class="form-control" rows="2" oninput="triggerAutoSave();"><?php echo htmlspecialchars((string)$curr_inst); ?></textarea>
                    </div>
                </div>

                <!-- 2. Questions Container Card -->
                <div class="card">
                    <div class="card-header">
                        <h2>✍️ የፈተና ጥያቄዎች ማዘጋጃ</h2>
                        <div style="display:flex; gap:6px; align-items:center; flex-wrap:wrap;">
                            <button type="button" class="btn btn-sm btn-outline" onclick="exportExamOfflineJSON()" title="ወደ JSON አውርድ">
                                💾 ኦፍላይን ፋይል አውርድ
                            </button>
                            <button type="button" class="btn btn-sm btn-brown" onclick="openPreviewModal()">
                                👁️ ራስጌውን እይ
                            </button>
                        </div>
                    </div>

                    <!-- Quick Type Buttons Bar -->
                    <div class="type-picker-bar">
                        <span style="font-weight:700; font-size:0.85rem; color:#475569; align-self:center; margin-right:4px;">ጥያቄ ጨምር፦</span>
                        <button type="button" class="btn-type-pill" onclick="addQuestion('choice')">🔘 ምርጫ (Choice)</button>
                        <button type="button" class="btn-type-pill" onclick="addQuestion('true_false')">⚖️ እውነት ወይም ሐሰት</button>
                        <button type="button" class="btn-type-pill" onclick="addQuestion('matching')">🔄 አዛምድ (Matching)</button>
                        <button type="button" class="btn-type-pill" onclick="addQuestion('workout')">✍️ ክፍት ጥያቄ (Workout)</button>
                        <button type="button" class="btn-type-pill" onclick="addQuestion('blank')">🔲 ባዶ ቦታ ሙላ</button>
                    </div>

                    <!-- Live Summation & Summary -->
                    <div class="stats-bar" id="statsBar">
                        <div>
                            <span>የጥያቄዎች ብዛት፦ <strong id="totalQuestionsCount">0</strong></span>
                            <span style="margin: 0 10px; color:#cbd5e1;">|</span>
                            <span>የነጥብ ድምር፦ <strong id="totalMarksSum" style="font-size:1.25rem;">0</strong> ነጥብ</span>
                        </div>
                        <div id="marksStatusText" style="font-size:0.85rem; font-weight:700;">
                            <!-- Dynamically updated -->
                        </div>
                    </div>

                    <div id="questionsContainer">
                        <?php if (!empty($editing_questions)): ?>
                            <?php foreach ($editing_questions as $idx => $q): ?>
                                <?php 
                                    $q_num = $idx + 1;
                                    $q_type = $q['question_type'] ?? 'choice';
                                    $opts = !empty($q['options_json']) ? json_decode($q['options_json'], true) : [];
                                    if (!is_array($opts)) $opts = [];
                                ?>
                                <div class="question-box" id="qbox_<?php echo $q_num; ?>" data-qnum="<?php echo $q_num; ?>">
                                    <div class="q-header">
                                        <div style="display:flex; align-items:center; gap:8px;">
                                            <span class="q-badge">ጥያቄ <?php echo $q_num; ?></span>
                                            <select name="questions[<?php echo $q_num; ?>][type]" class="q-type-select" onchange="changeQuestionType(<?php echo $q_num; ?>, this.value); triggerAutoSave();">
                                                <option value="choice" <?php echo ($q_type === 'choice') ? 'selected' : ''; ?>>🔘 ምርጫ (Choice)</option>
                                                <option value="true_false" <?php echo ($q_type === 'true_false') ? 'selected' : ''; ?>>⚖️ እውነት ወይም ሐሰት</option>
                                                <option value="matching" <?php echo ($q_type === 'matching') ? 'selected' : ''; ?>>🔄 አዛምድ (Matching)</option>
                                                <option value="workout" <?php echo ($q_type === 'workout') ? 'selected' : ''; ?>>✍️ ክፍት ጥያቄ (Workout)</option>
                                                <option value="blank" <?php echo ($q_type === 'blank') ? 'selected' : ''; ?>>🔲 ባዶ ቦታ ሙላ</option>
                                            </select>
                                        </div>
                                        <div style="display:flex; align-items:center; gap:10px;">
                                            <label style="font-size:0.85rem; font-weight:700; margin:0;">ነጥብ፦</label>
                                            <input type="number" step="0.5" name="questions[<?php echo $q_num; ?>][marks]" 
                                                   value="<?php echo htmlspecialchars((string)$q['marks']); ?>" 
                                                   class="form-control q-marks-input" style="width:75px; padding:4px 8px; font-weight:800;" oninput="updateLiveStats(); triggerAutoSave();">
                                            <div class="q-actions">
                                                <button type="button" class="btn btn-sm btn-outline" title="ወደ ላይ" onclick="moveQuestion(<?php echo $q_num; ?>, -1)">⬆️</button>
                                                <button type="button" class="btn btn-sm btn-outline" title="ወደ ታች" onclick="moveQuestion(<?php echo $q_num; ?>, 1)">⬇️</button>
                                                <button type="button" class="btn btn-sm btn-danger" title="ሰርዝ" onclick="removeQuestion(<?php echo $q_num; ?>)">🗑️</button>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="form-group" style="margin-bottom:8px;">
                                        <input type="text" name="questions[<?php echo $q_num; ?>][section_title]" 
                                               value="<?php echo htmlspecialchars((string)($q['section_title'] ?? '')); ?>" 
                                               class="form-control" placeholder="የክፍል አርዕስት (አማራጭ፦ ለምሳሌ ክፍል ፩፡ ትክክለኛውን መልስ ምረጥ)" oninput="triggerAutoSave();">
                                    </div>

                                    <div class="form-group" style="margin-bottom:8px;">
                                        <textarea name="questions[<?php echo $q_num; ?>][text]" class="form-control" rows="2" 
                                                  placeholder="የጥያቄው ጽሑፍ..." required oninput="triggerAutoSave();"><?php echo htmlspecialchars((string)$q['question_text']); ?></textarea>
                                    </div>

                                    <!-- DYNAMIC TYPE CONTAINER -->
                                    <div class="q-type-container" id="q_type_container_<?php echo $q_num; ?>">
                                        <?php if ($q_type === 'choice'): ?>
                                            <div class="options-grid">
                                                <div class="option-item"><span class="option-letter">ሀ.</span><input type="text" name="questions[<?php echo $q_num; ?>][choice_a]" value="<?php echo htmlspecialchars((string)($opts['ሀ'] ?? '')); ?>" class="form-control" placeholder="አማራጭ ሀ" oninput="triggerAutoSave();"></div>
                                                <div class="option-item"><span class="option-letter">ለ.</span><input type="text" name="questions[<?php echo $q_num; ?>][choice_b]" value="<?php echo htmlspecialchars((string)($opts['ለ'] ?? '')); ?>" class="form-control" placeholder="አማራጭ ለ" oninput="triggerAutoSave();"></div>
                                                <div class="option-item"><span class="option-letter">ሐ.</span><input type="text" name="questions[<?php echo $q_num; ?>][choice_c]" value="<?php echo htmlspecialchars((string)($opts['ሐ'] ?? '')); ?>" class="form-control" placeholder="አማራጭ ሐ" oninput="triggerAutoSave();"></div>
                                                <div class="option-item"><span class="option-letter">መ.</span><input type="text" name="questions[<?php echo $q_num; ?>][choice_d]" value="<?php echo htmlspecialchars((string)($opts['መ'] ?? '')); ?>" class="form-control" placeholder="አማራጭ መ" oninput="triggerAutoSave();"></div>
                                            </div>
                                            <div style="margin-top:10px; display:flex; align-items:center; gap:8px;">
                                                <label style="font-size:0.85rem; font-weight:700; color:#1e3a8a;">ትክክለኛ መልስ፦</label>
                                                <select name="questions[<?php echo $q_num; ?>][correct_answer]" class="form-control" style="width:130px; font-weight:800;" onchange="triggerAutoSave();">
                                                    <option value="">-- ይምረጡ --</option>
                                                    <option value="ሀ" <?php echo (($q['correct_answer'] ?? '') === 'ሀ') ? 'selected' : ''; ?>>ሀ</option>
                                                    <option value="ለ" <?php echo (($q['correct_answer'] ?? '') === 'ለ') ? 'selected' : ''; ?>>ለ</option>
                                                    <option value="ሐ" <?php echo (($q['correct_answer'] ?? '') === 'ሐ') ? 'selected' : ''; ?>>ሐ</option>
                                                    <option value="መ" <?php echo (($q['correct_answer'] ?? '') === 'መ') ? 'selected' : ''; ?>>መ</option>
                                                </select>
                                            </div>
                                        <?php elseif ($q_type === 'true_false'): ?>
                                            <div style="display:flex; align-items:center; gap:16px; margin-top:8px;">
                                                <label style="font-size:0.85rem; font-weight:700; color:#1e3a8a;">ትክክለኛ መልስ፦</label>
                                                <label style="cursor:pointer;"><input type="radio" name="questions[<?php echo $q_num; ?>][correct_answer]" value="እውነት" <?php echo (($q['correct_answer'] ?? '') === 'እውነት') ? 'checked' : ''; ?> onchange="triggerAutoSave();"> እውነት</label>
                                                <label style="cursor:pointer;"><input type="radio" name="questions[<?php echo $q_num; ?>][correct_answer]" value="ሐሰት" <?php echo (($q['correct_answer'] ?? '') === 'ሐሰት') ? 'checked' : ''; ?> onchange="triggerAutoSave();"> ሐሰት</label>
                                            </div>
                                        <?php elseif ($q_type === 'matching'): ?>
                                            <div style="margin-top:10px;">
                                                <p style="margin:0 0 6px 0; font-size:0.85rem; font-weight:700; color:#1e3a8a;">የ 'ሀ' ረድፍ እና የ 'ለ' ረድፍ ማዛመጃዎች፦</p>
                                                <?php 
                                                    $pairs = is_array($opts) ? $opts : [];
                                                    for ($p_idx = 0; $p_idx < max(4, count($pairs)); $p_idx++): 
                                                        $pair = $pairs[$p_idx] ?? ['left' => '', 'right' => ''];
                                                ?>
                                                    <div style="display:flex; gap:10px; margin-bottom:6px; align-items:center;">
                                                        <span style="font-weight:800; width:20px;"><?php echo ($p_idx + 1); ?>.</span>
                                                        <input type="text" name="questions[<?php echo $q_num; ?>][match_left][]" value="<?php echo htmlspecialchars((string)($pair['left'] ?? '')); ?>" class="form-control" placeholder="ረድፍ 'ሀ' ንጥል" oninput="triggerAutoSave();">
                                                        <span style="font-weight:800;">⇄</span>
                                                        <input type="text" name="questions[<?php echo $q_num; ?>][match_right][]" value="<?php echo htmlspecialchars((string)($pair['right'] ?? '')); ?>" class="form-control" placeholder="ረድፍ 'ለ' ንጥል" oninput="triggerAutoSave();">
                                                    </div>
                                                <?php endfor; ?>
                                            </div>
                                        <?php elseif ($q_type === 'workout'): ?>
                                            <div style="margin-top:10px; display:flex; gap:14px; flex-wrap:wrap;">
                                                <div style="flex:1; min-width:200px;">
                                                    <label style="font-size:0.85rem; font-weight:700; margin-bottom:4px; display:block;">ለተማሪው የሚተው የመስመር ብዛት፦</label>
                                                    <input type="number" name="questions[<?php echo $q_num; ?>][blank_lines]" value="<?php echo htmlspecialchars((string)($q['blank_lines'] ?? 3)); ?>" class="form-control" min="1" max="15" oninput="triggerAutoSave();">
                                                </div>
                                                <div style="flex:2; min-width:240px;">
                                                    <label style="font-size:0.85rem; font-weight:700; margin-bottom:4px; display:block;">የአስተማሪው መልስ ፍንጭ/ቁልፍ (ለአራሚው የሚሆን)፦</label>
                                                    <input type="text" name="questions[<?php echo $q_num; ?>][correct_answer]" value="<?php echo htmlspecialchars((string)($q['correct_answer'] ?? '')); ?>" class="form-control" placeholder="የመልስ ቁልፍ..." oninput="triggerAutoSave();">
                                                </div>
                                            </div>
                                        <?php elseif ($q_type === 'blank'): ?>
                                            <div style="margin-top:8px;">
                                                <label style="font-size:0.85rem; font-weight:700; color:#1e3a8a;">ትክክለኛ ባዶ ቦታ መልስ፦</label>
                                                <input type="text" name="questions[<?php echo $q_num; ?>][correct_answer]" value="<?php echo htmlspecialchars((string)($q['correct_answer'] ?? '')); ?>" class="form-control" placeholder="የሚሞላው ቃል ወይም ሐረግ..." oninput="triggerAutoSave();">
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>

                    <div style="text-align:center; padding:16px 0;">
                        <button type="button" class="btn btn-outline" style="border-style:dashed; border-width:2px; width:100%; max-width:400px; font-weight:700;" onclick="addQuestion('choice')">
                            ➕ ተጨማሪ ጥያቄ ጨምር
                        </button>
                    </div>
                </div>

                <!-- 3. Bottom Sticky Action Bar -->
                <div class="sticky-actions">
                    <div style="display:flex; align-items:center; gap:10px; flex-wrap:wrap;">
                        <button type="button" class="btn btn-secondary" onclick="submitExamForm('save')">
                            💾 ረቂቅ አስቀምጥ (Save Draft)
                        </button>
                        <button type="button" class="btn btn-success" onclick="submitExamForm('submit_to_admin')">
                            🚀 ወደ ትምህርት ክፍል ላክ (Submit)
                        </button>
                    </div>

                    <div style="display:flex; align-items:center; gap:8px; flex-wrap:wrap;">
                        <button type="button" class="btn btn-outline" onclick="openPreviewModal()">
                            👁️ ራስጌውን እይ
                        </button>
                        <?php if ($is_edit): ?>
                            <a href="exam_print.php?id=<?php echo $editing_exam['id']; ?>" target="_blank" class="btn btn-brown">
                                🖨️ አትም
                            </a>
                            <a href="exam_print.php?id=<?php echo $editing_exam['id']; ?>&key=1" target="_blank" class="btn btn-outline">
                                🔑 ቁልፍ
                            </a>
                        <?php endif; ?>
                        <a href="exam_author.php" class="btn btn-outline">ተመለስ</a>
                    </div>
                </div>
            </form>

            <!-- LIVE HEADER PREVIEW MODAL -->
            <div id="previewModal" class="modal-overlay" onclick="if(event.target===this) closePreviewModal();">
                <div class="modal-box">
                    <div style="display:flex; justify-content:space-between; align-items:center; border-bottom:1px solid #e2e8f0; padding-bottom:10px; margin-bottom:14px;">
                        <h3 style="margin:0; color:#1e3a8a;">👁️ የፈተና ወረቀት ይፋዊ ራስጌ ቅድመ-ዕይታ</h3>
                        <button type="button" class="btn btn-sm btn-outline" onclick="closePreviewModal()">✖</button>
                    </div>

                    <div style="border:1px solid #cbd5e1; padding:20px; border-radius:10px; background:#ffffff;">
                        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:10px;">
                            <div style="font-size:1.15rem; font-weight:900;">አጸደ ትጉሃን ሰንበት ትምህርት ቤት</div>
                            <div style="text-align:center;">
                                <img src="images/icon.png" alt="አርማ" style="width:48px; height:48px; border-radius:50%;" onerror="this.style.display='none'">
                            </div>
                            <div style="font-size:1.05rem; font-weight:800; font-family:'Times New Roman', serif;">Atsede Teguhan Sunday School</div>
                        </div>

                        <div style="display:flex; justify-content:space-between; font-weight:700; margin-bottom:8px; font-size:0.95rem;">
                            <span>ስም _____________________________</span>
                            <span>ክፍል ______________</span>
                            <span>የክርስትና ስም ________________________</span>
                        </div>

                        <div style="width:100%; height:4.5px; background-color:#8B4513; margin-bottom:12px;"></div>

                        <div id="modalPreviewTitle" style="text-align:center; font-weight:900; font-size:1.02rem; line-height:1.45;">
                            <!-- Populated dynamically -->
                        </div>
                    </div>

                    <div style="text-align:right; margin-top:16px;">
                        <button type="button" class="btn btn-primary" onclick="closePreviewModal()">ገባኝ (ተመለስ)</button>
                    </div>
                </div>
            </div>

        <?php else: ?>
            <!-- LIST OF CREATED EXAMS TABLE -->
            <div class="card">
                <div class="card-header">
                    <h2>📚 የተዘጋጁ ፈተናዎች ዝርዝር</h2>
                    <a href="exam_author.php?action=new" class="btn btn-brown btn-sm">➕ አዲስ ፈተና አዘጋጅ</a>
                </div>

                <?php if (empty($my_exams)): ?>
                    <div style="text-align:center; padding:40px 20px; color:var(--text-muted);">
                        <p style="font-size:2.8rem; margin:0 0 10px 0;">📝</p>
                        <h3 style="margin:0 0 8px 0; color:var(--text-main);">እስካሁን ያዘጋጁት ፈተና የለም</h3>
                        <p style="margin:0 0 20px 0;">«አዲስ ፈተና አዘጋጅ» የሚለውን ቁልፍ በመጫን ለክፍልዎ ፈተና ያዘጋጁ።</p>
                        <a href="exam_author.php?action=new" class="btn btn-brown">አዲስ ፈተና አዘጋጅ</a>
                    </div>
                <?php else: ?>
                    <div style="overflow-x:auto;">
                        <table class="exam-table">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>የፈተናው ርዕስ</th>
                                    <th>ክፍልና ትምህርት</th>
                                    <th>ዓ.ም / ሰሚስተር</th>
                                    <th>ጥያቄዎች</th>
                                    <th>ነጥብ</th>
                                    <th>ሁኔታ</th>
                                    <th style="text-align:right;">ተግባራት</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($my_exams as $i => $ex): ?>
                                    <tr>
                                        <td><?php echo ($i + 1); ?></td>
                                        <td>
                                            <strong style="color:#1e3a8a;"><?php echo htmlspecialchars((string)$ex['title']); ?></strong>
                                            <div style="font-size:0.8rem; color:var(--text-muted); margin-top:2px;">
                                                ፈታኝ፦ <?php echo htmlspecialchars((string)$ex['teacher_name']); ?> • ጊዜ፦ <?php echo $ex['duration_minutes']; ?> ደቂቃ
                                            </div>
                                        </td>
                                        <td>
                                            <span style="font-weight:700;"><?php echo htmlspecialchars((string)$ex['class_name']); ?></span><br>
                                            <small style="color:var(--text-muted);"><?php echo htmlspecialchars((string)$ex['subject_name']); ?></small>
                                        </td>
                                        <td>
                                            <?php echo htmlspecialchars((string)$ex['ethiopian_year']); ?> ዓ.ም<br>
                                            <small><?php echo ($ex['semester_id'] == 2) ? '2ኛ ሰሚስተር' : '1ኛ ሰሚስተር'; ?></small>
                                        </td>
                                        <td><span class="badge-status" style="background:#e0f2fe; color:#0369a1;"><?php echo $ex['question_count']; ?> ጥያቄዎች</span></td>
                                        <td><strong><?php echo floatval($ex['total_marks']); ?></strong></td>
                                        <td>
                                            <span class="badge-status badge-<?php echo htmlspecialchars((string)$ex['status']); ?>">
                                                <?php 
                                                    echo ($ex['status'] === 'draft') ? 'ረቂቅ (Draft)' : 
                                                        (($ex['status'] === 'submitted') ? 'የተላከ 🚀' : 
                                                        (($ex['status'] === 'approved') ? 'የጸደቀ ✅' : 'ማስተካከያ ⚠️'));
                                                ?>
                                            </span>
                                            <?php if ($ex['status'] === 'revision_requested' && !empty($ex['admin_feedback'])): ?>
                                                <div style="font-size:0.75rem; color:#dc2626; margin-top:2px;" title="<?php echo htmlspecialchars((string)$ex['admin_feedback']); ?>">
                                                    ⚠️ ማስታወሻ አለ
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                        <td style="text-align:right; white-space:nowrap;">
                                            <a href="exam_print.php?id=<?php echo $ex['id']; ?>" target="_blank" class="btn btn-sm btn-brown" title="ይፋዊ ፈተናውን አትም">
                                                🖨️ አትም
                                            </a>
                                            <a href="exam_author.php?action=edit&id=<?php echo $ex['id']; ?>" class="btn btn-sm btn-primary" title="አስተካክል">
                                                ✏️ አርም
                                            </a>
                                            <a href="exam_author.php?action=duplicate&id=<?php echo $ex['id']; ?>" class="btn btn-sm btn-outline" title="አባዛ (ቅጂ ውሰድ)">
                                                📑 አባዛ
                                            </a>
                                            <?php if ($ex['status'] !== 'approved' || $is_admin): ?>
                                                <a href="exam_author.php?action=delete&id=<?php echo $ex['id']; ?>" class="btn btn-sm btn-danger" 
                                                   onclick="return confirm('እርግጠኛ ነዎት ይህ ፈተና ይሰረዝ?');" title="ሰርዝ">
                                                    🗑️
                                                </a>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- JavaScript For Dynamic Questions Authoring, Offline Auto-Save & JSON Export/Import -->
    <script>
        let questionCounter = <?php echo !empty($editing_questions) ? count($editing_questions) : 0; ?>;
        const AUTOSAVE_STORAGE_KEY = 'ats_exam_autosave_draft';
        let autoSaveTimer = null;

        // Auto title generation
        function autoFillTitle() {
            const classSelect = document.getElementById('class_id');
            const subjectInput = document.getElementById('subject_name');
            const yearInput = document.getElementById('ethiopian_year');
            const semSelect = document.getElementById('semester_id');
            const typeSelect = document.getElementById('exam_type');
            const titleInput = document.getElementById('title');

            if (!classSelect || !titleInput) return;

            const selectedClassOption = classSelect.options[classSelect.selectedIndex];
            const className = selectedClassOption ? selectedClassOption.getAttribute('data-name') || '' : '';
            const subName = subjectInput ? subjectInput.value.trim() : '';
            const year = yearInput ? yearInput.value.trim() : '2018';
            const sem = semSelect && semSelect.value === '2' ? '2ኛመንፈቀ ዓመት' : '1ኛመንፈቀ ዓመት';
            
            let examTypeTxt = 'የአጋማሽ ፈተና';
            if (typeSelect && typeSelect.value === 'final') examTypeTxt = 'የማጠቃለያ ፈተና';
            else if (typeSelect && typeSelect.value === 'quiz') examTypeTxt = 'የክፍል ፈተና';

            if (className && subName) {
                titleInput.value = `የ${year} ዓ.ም የ${className} የ${subName} የ${sem} ${examTypeTxt}`;
            }
        }

        // Live stats & coloring
        function updateLiveStats() {
            const boxes = document.querySelectorAll('.question-box');
            const totalCountEl = document.getElementById('totalQuestionsCount');
            const totalMarksEl = document.getElementById('totalMarksSum');
            const statusTextEl = document.getElementById('marksStatusText');
            const statsBar = document.getElementById('statsBar');

            let count = boxes.length;
            let sum = 0.0;

            const markInputs = document.querySelectorAll('.q-marks-input');
            markInputs.forEach(input => {
                const val = parseFloat(input.value) || 0;
                sum += val;
            });

            if (totalCountEl) totalCountEl.innerText = count;
            if (totalMarksEl) {
                totalMarksEl.innerText = sum.toFixed(1);
                if (sum === 100) {
                    totalMarksEl.style.color = '#10b981';
                    if (statsBar) statsBar.style.borderLeftColor = '#10b981';
                    if (statusTextEl) statusTextEl.innerHTML = '<span style="color:#10b981;">✅ ልክ 100% ተሞልቷል!</span>';
                } else if (sum > 100) {
                    totalMarksEl.style.color = '#ef4444';
                    if (statsBar) statsBar.style.borderLeftColor = '#ef4444';
                    if (statusTextEl) statusTextEl.innerHTML = `<span style="color:#ef4444;">⚠️ ከ100% በላይ ሆኗል (${(sum-100).toFixed(1)} ነጥብ ብልጫ)</span>`;
                } else {
                    totalMarksEl.style.color = '#1e3a8a';
                    if (statsBar) statsBar.style.borderLeftColor = '#2563eb';
                    if (statusTextEl) statusTextEl.innerHTML = `<span style="color:#64748b;">(ቀሪ ${(100-sum).toFixed(1)} ነጥብ ወደ 100% ለመድረስ)</span>`;
                }
            }
        }

        function triggerAutoSave() {
            const indicator = document.getElementById('autoSaveIndicator');
            if (indicator) indicator.innerText = '⏳ በማስቀመጥ ላይ...';

            clearTimeout(autoSaveTimer);
            autoSaveTimer = setTimeout(() => {
                saveDraftToLocalStorage();
                if (indicator) indicator.innerText = '✓ ራስ-ሰር ተቀምጧል';
            }, 1000);
        }

        function saveDraftToLocalStorage() {
            const form = document.getElementById('examForm');
            if (!form) return;

            const draftData = {
                savedAt: new Date().toISOString(),
                class_id: document.getElementById('class_id')?.value || '',
                subject_name: document.getElementById('subject_name')?.value || '',
                ethiopian_year: document.getElementById('ethiopian_year')?.value || '',
                semester_id: document.getElementById('semester_id')?.value || '',
                exam_type: document.getElementById('exam_type')?.value || '',
                title: document.getElementById('title')?.value || '',
                instructions: document.getElementById('instructions')?.value || '',
                duration_minutes: document.getElementById('duration_minutes')?.value || '',
                questions: []
            };

            const boxes = document.querySelectorAll('.question-box');
            boxes.forEach((box, idx) => {
                const qNum = idx + 1;
                const type = box.querySelector('.q-type-select')?.value || 'choice';
                const marks = box.querySelector('.q-marks-input')?.value || 1.0;
                const section = box.querySelector(`input[name="questions[${qNum}][section_title]"]`)?.value || '';
                const text = box.querySelector(`textarea[name="questions[${qNum}][text]"]`)?.value || '';

                let qObj = { type, marks, section, text };

                if (type === 'choice') {
                    qObj.choice_a = box.querySelector(`input[name="questions[${qNum}][choice_a]"]`)?.value || '';
                    qObj.choice_b = box.querySelector(`input[name="questions[${qNum}][choice_b]"]`)?.value || '';
                    qObj.choice_c = box.querySelector(`input[name="questions[${qNum}][choice_c]"]`)?.value || '';
                    qObj.choice_d = box.querySelector(`input[name="questions[${qNum}][choice_d]"]`)?.value || '';
                    qObj.correct = box.querySelector(`select[name="questions[${qNum}][correct_answer]"]`)?.value || '';
                } else if (type === 'true_false') {
                    const checkedRadio = box.querySelector(`input[name="questions[${qNum}][correct_answer]"]:checked`);
                    qObj.correct = checkedRadio ? checkedRadio.value : '';
                } else if (type === 'matching') {
                    qObj.match_left = Array.from(box.querySelectorAll(`input[name="questions[${qNum}][match_left][]"]`)).map(i => i.value);
                    qObj.match_right = Array.from(box.querySelectorAll(`input[name="questions[${qNum}][match_right][]"]`)).map(i => i.value);
                } else if (type === 'workout') {
                    qObj.blank_lines = box.querySelector(`input[name="questions[${qNum}][blank_lines]"]`)?.value || 3;
                    qObj.correct = box.querySelector(`input[name="questions[${qNum}][correct_answer]"]`)?.value || '';
                } else if (type === 'blank') {
                    qObj.correct = box.querySelector(`input[name="questions[${qNum}][correct_answer]"]`)?.value || '';
                }

                draftData.questions.push(qObj);
            });

            try {
                localStorage.setItem(AUTOSAVE_STORAGE_KEY, JSON.stringify(draftData));
                if (typeof OfflineDB !== 'undefined' && OfflineDB.saveOfflineExam) {
                    OfflineDB.saveOfflineExam(draftData).catch(() => {});
                }
            } catch (e) {
                console.warn('LocalStorage save failed:', e);
            }
        }

        async function checkOfflineRestore() {
            const examId = parseInt(document.getElementById('exam_id')?.value || '0', 10);
            if (examId > 0) return; // Don't prompt restore if already editing existing exam

            try {
                const raw = localStorage.getItem(AUTOSAVE_STORAGE_KEY);
                if (raw) {
                    const data = JSON.parse(raw);
                    if (data && data.questions && data.questions.length > 0) {
                        const banner = document.getElementById('restoreBanner');
                        if (banner) banner.style.display = 'flex';
                        return;
                    }
                }
                if (typeof OfflineDB !== 'undefined' && OfflineDB.getOfflineExams) {
                    const idbExams = await OfflineDB.getOfflineExams();
                    if (idbExams && idbExams.length > 0) {
                        const latest = idbExams[idbExams.length - 1];
                        if (latest && latest.questions && latest.questions.length > 0) {
                            localStorage.setItem(AUTOSAVE_STORAGE_KEY, JSON.stringify(latest));
                            const banner = document.getElementById('restoreBanner');
                            if (banner) banner.style.display = 'flex';
                        }
                    }
                }
            } catch (e) {}
        }

        function restoreAutoSavedDraft() {
            try {
                const raw = localStorage.getItem(AUTOSAVE_STORAGE_KEY);
                if (!raw) return;
                const data = JSON.parse(raw);

                if (data.class_id) document.getElementById('class_id').value = data.class_id;
                if (data.subject_name) document.getElementById('subject_name').value = data.subject_name;
                if (data.ethiopian_year) document.getElementById('ethiopian_year').value = data.ethiopian_year;
                if (data.semester_id) document.getElementById('semester_id').value = data.semester_id;
                if (data.exam_type) document.getElementById('exam_type').value = data.exam_type;
                if (data.title) document.getElementById('title').value = data.title;
                if (data.instructions) document.getElementById('instructions').value = data.instructions;
                if (data.duration_minutes) document.getElementById('duration_minutes').value = data.duration_minutes;

                const container = document.getElementById('questionsContainer');
                if (container && data.questions && data.questions.length > 0) {
                    container.innerHTML = '';
                    questionCounter = 0;

                    data.questions.forEach(q => {
                        addQuestion(q.type);
                        const currBox = document.getElementById(`qbox_${questionCounter}`);
                        if (currBox) {
                            if (currBox.querySelector('.q-marks-input')) currBox.querySelector('.q-marks-input').value = q.marks || 1.0;
                            if (currBox.querySelector(`input[name="questions[${questionCounter}][section_title]"]`)) {
                                currBox.querySelector(`input[name="questions[${questionCounter}][section_title]"]`).value = q.section || '';
                            }
                            if (currBox.querySelector(`textarea[name="questions[${questionCounter}][text]"]`)) {
                                currBox.querySelector(`textarea[name="questions[${questionCounter}][text]"]`).value = q.text || '';
                            }

                            if (q.type === 'choice') {
                                if (q.choice_a) currBox.querySelector(`input[name="questions[${questionCounter}][choice_a]"]`).value = q.choice_a;
                                if (q.choice_b) currBox.querySelector(`input[name="questions[${questionCounter}][choice_b]"]`).value = q.choice_b;
                                if (q.choice_c) currBox.querySelector(`input[name="questions[${questionCounter}][choice_c]"]`).value = q.choice_c;
                                if (q.choice_d) currBox.querySelector(`input[name="questions[${questionCounter}][choice_d]"]`).value = q.choice_d;
                                if (q.correct) currBox.querySelector(`select[name="questions[${questionCounter}][correct_answer]"]`).value = q.correct;
                            } else if (q.type === 'true_false') {
                                const radio = currBox.querySelector(`input[name="questions[${questionCounter}][correct_answer]"][value="${q.correct}"]`);
                                if (radio) radio.checked = true;
                            } else if (q.type === 'matching' && q.match_left) {
                                const leftInputs = currBox.querySelectorAll(`input[name="questions[${questionCounter}][match_left][]"]`);
                                const rightInputs = currBox.querySelectorAll(`input[name="questions[${questionCounter}][match_right][]"]`);
                                q.match_left.forEach((lv, lidx) => {
                                    if (leftInputs[lidx]) leftInputs[lidx].value = lv;
                                });
                                (q.match_right || []).forEach((rv, ridx) => {
                                    if (rightInputs[ridx]) rightInputs[ridx].value = rv;
                                });
                            } else if (q.type === 'workout') {
                                if (q.blank_lines) currBox.querySelector(`input[name="questions[${questionCounter}][blank_lines]"]`).value = q.blank_lines;
                                if (q.correct) currBox.querySelector(`input[name="questions[${questionCounter}][correct_answer]"]`).value = q.correct;
                            } else if (q.type === 'blank') {
                                if (q.correct) currBox.querySelector(`input[name="questions[${questionCounter}][correct_answer]"]`).value = q.correct;
                            }
                        }
                    });
                }

                updateLiveStats();
                document.getElementById('restoreBanner').style.display = 'none';
                alert('ረቂቁ በተሳካ ሁኔታ ተመልሷል!');
            } catch (e) {
                alert('ረቂቁን መመለስ አልተቻለም፦ ' + e.message);
            }
        }

        function discardAutoSavedDraft() {
            localStorage.removeItem(AUTOSAVE_STORAGE_KEY);
            const banner = document.getElementById('restoreBanner');
            if (banner) banner.style.display = 'none';
        }

        // Export Exam Offline as JSON
        function exportExamOfflineJSON() {
            saveDraftToLocalStorage();
            const raw = localStorage.getItem(AUTOSAVE_STORAGE_KEY);
            if (!raw) {
                alert('የሚወርድ የፈተና መረጃ የለም!');
                return;
            }
            const blob = new Blob([raw], { type: 'application/json' });
            const title = document.getElementById('title')?.value || 'exam';
            const cleanFilename = title.replace(/[^\w\u1200-\u137F]/gi, '_') + '.json';

            const link = document.createElement('a');
            link.href = URL.createObjectURL(blob);
            link.download = cleanFilename;
            link.click();
        }

        // Import Exam from JSON
        function importExamJSON(event) {
            const file = event.target.files[0];
            if (!file) return;

            const reader = new FileReader();
            reader.onload = function(e) {
                try {
                    const data = JSON.parse(e.target.result);
                    localStorage.setItem(AUTOSAVE_STORAGE_KEY, JSON.stringify(data));
                    window.location.href = 'exam_author.php?action=new&restored=1';
                } catch (err) {
                    alert('የ JSON ፋይሉ ትክክል አይደለም፦ ' + err.message);
                }
            };
            reader.readAsText(file);
        }

        // Live Header Preview Modal
        function openPreviewModal() {
            const modal = document.getElementById('previewModal');
            const previewTitle = document.getElementById('modalPreviewTitle');
            const titleInput = document.getElementById('title');

            if (modal && previewTitle) {
                let fullTitle = titleInput ? titleInput.value.trim() : '';
                if (!fullTitle) {
                    autoFillTitle();
                    fullTitle = titleInput ? titleInput.value.trim() : '';
                }
                if (fullTitle.indexOf('አጸደ ትጉሃን') === -1) {
                    fullTitle = 'የአያት መካነ ሰማዕት ቅዱስ ጊዮርጊስ ቅድስት ኪዳነ ምሕረት ወአቡነ ሀብተማርያም ቤተ ክርስቲያን አጸደ ትጉሃን ሰንበት ትምህርት ቤት ' + fullTitle;
                }
                previewTitle.innerText = fullTitle;
                modal.style.display = 'flex';
            }
        }

        function closePreviewModal() {
            const modal = document.getElementById('previewModal');
            if (modal) modal.style.display = 'none';
        }

        // Network Online/Offline Handler
        function updateOnlineStatus() {
            const pill = document.getElementById('offlinePill');
            if (!pill) return;
            if (!navigator.onLine) {
                pill.style.display = 'flex';
            } else {
                pill.style.display = 'none';
            }
        }
        window.addEventListener('online', updateOnlineStatus);
        window.addEventListener('offline', updateOnlineStatus);

        function addQuestion(type = 'choice') {
            questionCounter++;
            const container = document.getElementById('questionsContainer');
            if (!container) return;

            const qBox = document.createElement('div');
            qBox.className = 'question-box';
            qBox.id = `qbox_${questionCounter}`;
            qBox.setAttribute('data-qnum', questionCounter);

            let typeOptions = `
                <option value="choice" ${type === 'choice' ? 'selected' : ''}>🔘 ምርጫ (Choice)</option>
                <option value="true_false" ${type === 'true_false' ? 'selected' : ''}>⚖️ እውነት ወይም ሐሰት</option>
                <option value="matching" ${type === 'matching' ? 'selected' : ''}>🔄 አዛምድ (Matching)</option>
                <option value="workout" ${type === 'workout' ? 'selected' : ''}>✍️ ክፍት ጥያቄ (Workout)</option>
                <option value="blank" ${type === 'blank' ? 'selected' : ''}>🔲 ባዶ ቦታ ሙላ</option>
            `;

            qBox.innerHTML = `
                <div class="q-header">
                    <div style="display:flex; align-items:center; gap:8px;">
                        <span class="q-badge">ጥያቄ ${questionCounter}</span>
                        <select name="questions[${questionCounter}][type]" class="q-type-select" onchange="changeQuestionType(${questionCounter}, this.value); triggerAutoSave();">
                            ${typeOptions}
                        </select>
                    </div>
                    <div style="display:flex; align-items:center; gap:10px;">
                        <label style="font-size:0.85rem; font-weight:700; margin:0;">ነጥብ፦</label>
                        <input type="number" step="0.5" name="questions[${questionCounter}][marks]" value="1.0" 
                               class="form-control q-marks-input" style="width:75px; padding:4px 8px; font-weight:800;" oninput="updateLiveStats(); triggerAutoSave();">
                        <div class="q-actions">
                            <button type="button" class="btn btn-sm btn-outline" title="ወደ ላይ" onclick="moveQuestion(${questionCounter}, -1)">⬆️</button>
                            <button type="button" class="btn btn-sm btn-outline" title="ወደ ታች" onclick="moveQuestion(${questionCounter}, 1)">⬇️</button>
                            <button type="button" class="btn btn-sm btn-danger" title="ሰርዝ" onclick="removeQuestion(${questionCounter})">🗑️</button>
                        </div>
                    </div>
                </div>

                <div class="form-group" style="margin-bottom:8px;">
                    <input type="text" name="questions[${questionCounter}][section_title]" class="form-control" placeholder="የክፍል አርዕስት (አማራጭ፦ ለምሳሌ ክፍል ፩፡ ትክክለኛውን መልስ ምረጥ)" oninput="triggerAutoSave();">
                </div>

                <div class="form-group" style="margin-bottom:8px;">
                    <textarea name="questions[${questionCounter}][text]" class="form-control" rows="2" placeholder="የጥያቄው ጽሑፍ..." required oninput="triggerAutoSave();"></textarea>
                </div>

                <div class="q-type-container" id="q_type_container_${questionCounter}">
                    ${renderTypeHTML(questionCounter, type)}
                </div>
            `;

            container.appendChild(qBox);
            renumberQuestions();
            updateLiveStats();
            triggerAutoSave();
        }

        function renderTypeHTML(num, type) {
            if (type === 'choice') {
                return `
                    <div class="options-grid">
                        <div class="option-item"><span class="option-letter">ሀ.</span><input type="text" name="questions[${num}][choice_a]" class="form-control" placeholder="አማራጭ ሀ" oninput="triggerAutoSave();"></div>
                        <div class="option-item"><span class="option-letter">ለ.</span><input type="text" name="questions[${num}][choice_b]" class="form-control" placeholder="አማራጭ ለ" oninput="triggerAutoSave();"></div>
                        <div class="option-item"><span class="option-letter">ሐ.</span><input type="text" name="questions[${num}][choice_c]" class="form-control" placeholder="አማራጭ ሐ" oninput="triggerAutoSave();"></div>
                        <div class="option-item"><span class="option-letter">መ.</span><input type="text" name="questions[${num}][choice_d]" class="form-control" placeholder="አማራጭ መ" oninput="triggerAutoSave();"></div>
                    </div>
                    <div style="margin-top:10px; display:flex; align-items:center; gap:8px;">
                        <label style="font-size:0.85rem; font-weight:700; color:#1e3a8a;">ትክክለኛ መልስ፦</label>
                        <select name="questions[${num}][correct_answer]" class="form-control" style="width:130px; font-weight:800;" onchange="triggerAutoSave();">
                            <option value="">-- ይምረጡ --</option>
                            <option value="ሀ">ሀ</option>
                            <option value="ለ">ለ</option>
                            <option value="ሐ">ሐ</option>
                            <option value="መ">መ</option>
                        </select>
                    </div>
                `;
            } else if (type === 'true_false') {
                return `
                    <div style="display:flex; align-items:center; gap:16px; margin-top:8px;">
                        <label style="font-size:0.85rem; font-weight:700; color:#1e3a8a;">ትክክለኛ መልስ፦</label>
                        <label style="cursor:pointer;"><input type="radio" name="questions[${num}][correct_answer]" value="እውነት" onchange="triggerAutoSave();"> እውነት</label>
                        <label style="cursor:pointer;"><input type="radio" name="questions[${num}][correct_answer]" value="ሐሰት" onchange="triggerAutoSave();"> ሐሰት</label>
                    </div>
                `;
            } else if (type === 'matching') {
                let rows = '';
                for (let i = 1; i <= 4; i++) {
                    rows += `
                        <div style="display:flex; gap:10px; margin-bottom:6px; align-items:center;">
                            <span style="font-weight:800; width:20px;">${i}.</span>
                            <input type="text" name="questions[${num}][match_left][]" class="form-control" placeholder="ረድፍ 'ሀ' ንጥል" oninput="triggerAutoSave();">
                            <span style="font-weight:800;">⇄</span>
                            <input type="text" name="questions[${num}][match_right][]" class="form-control" placeholder="ረድፍ 'ለ' ንጥል" oninput="triggerAutoSave();">
                        </div>
                    `;
                }
                return `
                    <div style="margin-top:10px;">
                        <p style="margin:0 0 6px 0; font-size:0.85rem; font-weight:700; color:#1e3a8a;">የ 'ሀ' ረድፍ እና የ 'ለ' ረድፍ ማዛመጃዎች፦</p>
                        ${rows}
                    </div>
                `;
            } else if (type === 'workout') {
                return `
                    <div style="margin-top:10px; display:flex; gap:14px; flex-wrap:wrap;">
                        <div style="flex:1; min-width:200px;">
                            <label style="font-size:0.85rem; font-weight:700; margin-bottom:4px; display:block;">ለተማሪው የሚተው የመስመር ብዛት፦</label>
                            <input type="number" name="questions[${num}][blank_lines]" value="4" class="form-control" min="1" max="15" oninput="triggerAutoSave();">
                        </div>
                        <div style="flex:2; min-width:240px;">
                            <label style="font-size:0.85rem; font-weight:700; margin-bottom:4px; display:block;">የአስተማሪው መልስ ፍንጭ/ቁልፍ (ለአራሚው የሚሆን)፦</label>
                            <input type="text" name="questions[${num}][correct_answer]" class="form-control" placeholder="የመልስ ቁልፍ..." oninput="triggerAutoSave();">
                        </div>
                    </div>
                `;
            } else if (type === 'blank') {
                return `
                    <div style="margin-top:8px;">
                        <label style="font-size:0.85rem; font-weight:700; color:#1e3a8a;">ትክክለኛ ባዶ ቦታ መልስ፦</label>
                        <input type="text" name="questions[${num}][correct_answer]" class="form-control" placeholder="የሚሞላው ቃል ወይም ሐረግ..." oninput="triggerAutoSave();">
                    </div>
                `;
            }
            return '';
        }

        function changeQuestionType(num, newType) {
            const container = document.getElementById(`q_type_container_${num}`);
            if (container) {
                container.innerHTML = renderTypeHTML(num, newType);
            }
        }

        function removeQuestion(num) {
            const box = document.getElementById(`qbox_${num}`);
            if (box && confirm('ይህ ጥያቄ ይሰረዝ?')) {
                box.remove();
                renumberQuestions();
                updateLiveStats();
                triggerAutoSave();
            }
        }

        function moveQuestion(num, direction) {
            const box = document.getElementById(`qbox_${num}`);
            if (!box) return;
            if (direction === -1 && box.previousElementSibling) {
                box.parentNode.insertBefore(box, box.previousElementSibling);
            } else if (direction === 1 && box.nextElementSibling) {
                box.parentNode.insertBefore(box.nextElementSibling, box);
            }
            renumberQuestions();
            triggerAutoSave();
        }

        function renumberQuestions() {
            const boxes = document.querySelectorAll('.question-box');
            boxes.forEach((box, idx) => {
                const badge = box.querySelector('.q-badge');
                if (badge) badge.innerText = `ጥያቄ ${idx + 1}`;
            });
            updateLiveStats();
        }

        function submitExamForm(act) {
            const actInput = document.getElementById('formAction');
            if (actInput) actInput.value = act;

            const form = document.getElementById('examForm');
            if (act === 'submit_to_admin') {
                if (confirm('እርግጠኛ ነዎት ይህ ፈተና ተጠናቆ ለትምህርት ክፍል እንዲላክ ይፈልጋሉ?')) {
                    localStorage.removeItem(AUTOSAVE_STORAGE_KEY);
                    form.submit();
                }
            } else {
                localStorage.removeItem(AUTOSAVE_STORAGE_KEY);
                form.submit();
            }
        }

        // Initialize on load
        document.addEventListener('DOMContentLoaded', function() {
            updateOnlineStatus();

            const urlParams = new URLSearchParams(window.location.search);
            if (urlParams.get('restored') === '1') {
                restoreAutoSavedDraft();
            } else {
                checkOfflineRestore();
            }

            if (questionCounter === 0 && document.getElementById('questionsContainer')) {
                addQuestion('true_false');
                addQuestion('choice');
            } else {
                updateLiveStats();
            }
        });
    </script>
</body>
</html>
