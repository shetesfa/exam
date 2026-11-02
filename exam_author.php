<?php
require_once 'db.php';
requireTeacher();
ensureExamTablesExist($conn);

$teacher_id = intval($_SESSION['user_id'] ?? 0);
$is_admin = isAdmin();

// Get current semester and active academic year
$current_semester = getCurrentSemester($conn);
$semester_id = $current_semester ? intval($current_semester['id']) : 0;
$active_year = 2018;
$year_row = dbFetchOne($conn, "SELECT ethiopian_year FROM academic_years WHERE status = 'active' LIMIT 1");
if ($year_row && !empty($year_row['ethiopian_year'])) {
    $active_year = intval($year_row['ethiopian_year']);
}

// Get teacher's classes
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
            $system_subjects[] = trim($s['name']);
        }
    }
}

// Handle Form Submissions (Create, Update, Submit, Delete, Duplicate)
$message = '';
$error = '';

$action = $_POST['action'] ?? ($_GET['action'] ?? '');
$exam_id = intval($_POST['exam_id'] ?? ($_GET['id'] ?? 0));

// CSRF check for POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
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
        $new_title = $src['title'] . " (ቅጂ)";
        $dup_exam_id = dbInsert($conn, 
            "INSERT INTO exams (teacher_id, class_id, subject_name, semester_id, ethiopian_year, exam_type, title, instructions, total_marks, duration_minutes, status) 
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'draft')",
            "iisiisssdi",
            [$teacher_id, $src['class_id'], $src['subject_name'], $src['semester_id'], $src['ethiopian_year'], $src['exam_type'], $new_title, $src['instructions'], $src['total_marks'], $src['duration_minutes']]
        );
        if ($dup_exam_id) {
            $src_questions = dbFetchAll($conn, "SELECT * FROM exam_questions WHERE exam_id = ? ORDER BY order_num ASC, id ASC", "i", [$exam_id]);
            foreach ($src_questions as $q) {
                dbExecute($conn, 
                    "INSERT INTO exam_questions (exam_id, section_title, question_type, question_text, options_json, correct_answer, marks, order_num, blank_lines) 
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)",
                    "isssssdii",
                    [$dup_exam_id, $q['section_title'], $q['question_type'], $q['question_text'], $q['options_json'], $q['correct_answer'], $q['marks'], $q['order_num'], $q['blank_lines']]
                );
            }
            $message = "ፈተናው በተሳካ ሁኔታ ተባዝቷል! አሁን ማስተካከል ይችላሉ።";
            header("Location: exam_author.php?action=edit&id=" . $dup_exam_id . "&msg=" . urlencode($message));
            exit();
        }
    }
    $action = '';
}

// 3. SAVE / SUBMIT EXAM WITH QUESTIONS (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($action === 'save' || $action === 'submit_to_admin')) {
    $class_id = intval($_POST['class_id'] ?? 0);
    $subject_name = trim($_POST['subject_name'] ?? '');
    $exam_semester_id = intval($_POST['semester_id'] ?? $semester_id);
    $ethiopian_year = intval($_POST['ethiopian_year'] ?? $active_year);
    $exam_type = trim($_POST['exam_type'] ?? 'mid');
    $title = trim($_POST['title'] ?? '');
    $instructions = trim($_POST['instructions'] ?? '');
    $duration_minutes = intval($_POST['duration_minutes'] ?? 60);
    $questions_raw = $_POST['questions'] ?? [];

    if ($class_id <= 0 || empty($subject_name)) {
        $error = "እባክዎ ክፍል እና የትምህርት ዓይነት በትክክል ይምረጡ!";
    } else {
        // Auto-generate title if left empty
        $class_info = dbFetchOne($conn, "SELECT name FROM classes WHERE id = ?", "i", [$class_id]);
        $c_name = $class_info ? $class_info['name'] : "ክፍል";
        $sem_name = ($exam_semester_id == 2) ? "2ኛ መንፈቀ ዓመት" : "1ኛ መንፈቀ ዓመት";
        $type_label = ($exam_type === 'final') ? "የማጠቃለያ ፈተና" : (($exam_type === 'quiz') ? "የክፍል ፈተና" : "የአጋማሽ ፈተና");

        if (empty($title)) {
            $title = "የ{$ethiopian_year} ዓ.ም የ{$c_name} የ{$subject_name} የ{$sem_name} {$type_label}";
        }

        $new_status = ($action === 'submit_to_admin') ? 'submitted' : 'draft';

        // Calculate total marks from questions
        $total_marks = 0.0;
        if (is_array($questions_raw)) {
            foreach ($questions_raw as $q) {
                $total_marks += floatval($q['marks'] ?? 0);
            }
        }
        if ($total_marks <= 0) {
            $total_marks = floatval($_POST['total_marks'] ?? 100);
        }

        // Insert or Update Exam
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

        // Save questions
        if (!empty($target_exam_id)) {
            // Delete existing questions and re-insert
            dbExecute($conn, "DELETE FROM exam_questions WHERE exam_id = ?", "i", [$target_exam_id]);

            if (is_array($questions_raw)) {
                $order = 1;
                foreach ($questions_raw as $q) {
                    $q_type = $q['type'] ?? 'choice';
                    $q_text = trim($q['text'] ?? '');
                    if ($q_text === '') continue; // Skip empty questions

                    $sec_title = trim($q['section_title'] ?? '');
                    $q_marks = floatval($q['marks'] ?? 1.0);
                    $q_blank_lines = intval($q['blank_lines'] ?? 3);
                    $correct = trim($q['correct_answer'] ?? '');
                    $options_json = null;

                    if ($q_type === 'choice') {
                        $opts = [];
                        if (!empty($q['choice_a'])) $opts['ሀ'] = trim($q['choice_a']);
                        if (!empty($q['choice_b'])) $opts['ለ'] = trim($q['choice_b']);
                        if (!empty($q['choice_c'])) $opts['ሐ'] = trim($q['choice_c']);
                        if (!empty($q['choice_d'])) $opts['መ'] = trim($q['choice_d']);
                        if (!empty($q['choice_e'])) $opts['ሠ'] = trim($q['choice_e']);
                        $options_json = json_encode($opts, JSON_UNESCAPED_UNICODE);
                    } elseif ($q_type === 'matching') {
                        // Left items and Right items
                        $matching_pairs = [];
                        $left_items = $q['match_left'] ?? [];
                        $right_items = $q['match_right'] ?? [];
                        if (is_array($left_items)) {
                            foreach ($left_items as $idx => $l_val) {
                                $l_trim = trim($l_val);
                                $r_trim = trim($right_items[$idx] ?? '');
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

            if ($action === 'submit_to_admin') {
                $succ_msg = "ፈተናው በተሳካ ሁኔታ ተዘጋጅቶ ለትምህርት ክፍል ተልኳል! አስተዳዳሪዎች ሲገመግሙት ማሳወቂያ ይደርስዎታል።";
            } else {
                $succ_msg = "ፈተናው በረቂቅነት (Draft) ተቀምጧል! በማንኛውም ጊዜ ማስተካከል ወይም መላክ ይችላሉ።";
            }
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
if ($is_admin) {
    $my_exams = dbFetchAll($conn, "SELECT e.*, c.name as class_name, u.name as teacher_name, 
                                  (SELECT COUNT(*) FROM exam_questions eq WHERE eq.exam_id = e.id) as question_count 
                                  FROM exams e 
                                  JOIN classes c ON e.class_id = c.id 
                                  JOIN users u ON e.teacher_id = u.id 
                                  ORDER BY e.created_at DESC");
} else {
    $my_exams = dbFetchAll($conn, "SELECT e.*, c.name as class_name, u.name as teacher_name, 
                                  (SELECT COUNT(*) FROM exam_questions eq WHERE eq.exam_id = e.id) as question_count 
                                  FROM exams e 
                                  JOIN classes c ON e.class_id = c.id 
                                  JOIN users u ON e.teacher_id = u.id 
                                  WHERE e.teacher_id = ? 
                                  ORDER BY e.created_at DESC", "i", [$teacher_id]);
}

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
            --radius: 12px;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: var(--bg-color);
            color: var(--text-main);
            margin: 0;
            padding: 0;
            padding-bottom: 90px;
        }

        .container {
            max-width: 1100px;
            margin: 0 auto;
            padding: 16px;
        }

        .header-card {
            background: linear-gradient(135deg, #1e3a8a 0%, #1e40af 100%);
            color: #ffffff;
            padding: 24px 20px;
            border-radius: var(--radius);
            box-shadow: 0 4px 14px rgba(30, 58, 138, 0.2);
            margin-bottom: 24px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 16px;
        }

        .header-title h1 {
            margin: 0;
            font-size: 1.45rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .header-title p {
            margin: 6px 0 0 0;
            font-size: 0.92rem;
            opacity: 0.9;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 10px 18px;
            font-size: 0.95rem;
            font-weight: 600;
            border-radius: 8px;
            cursor: pointer;
            border: none;
            transition: all 0.2s ease;
            text-decoration: none;
        }

        .btn-primary { background-color: #2563eb; color: #ffffff; }
        .btn-primary:hover { background-color: #1d4ed8; }
        .btn-success { background-color: #10b981; color: #ffffff; }
        .btn-success:hover { background-color: #059669; }
        .btn-secondary { background-color: #64748b; color: #ffffff; }
        .btn-secondary:hover { background-color: #475569; }
        .btn-brown { background-color: #8B4513; color: #ffffff; }
        .btn-brown:hover { background-color: #6d360e; }
        .btn-outline { background: transparent; border: 1.5px solid var(--border); color: var(--text-main); }
        .btn-outline:hover { background: #f1f5f9; }
        .btn-danger { background-color: #ef4444; color: #ffffff; }
        .btn-danger:hover { background-color: #dc2626; }
        .btn-sm { padding: 6px 12px; font-size: 0.85rem; border-radius: 6px; }

        .alert {
            padding: 14px 18px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .alert-success { background: #dcfce7; color: #166534; border: 1px solid #bbf7d0; }
        .alert-error { background: #fee2e2; color: #991b1b; border: 1px solid #fecaca; }

        .card {
            background: var(--card-bg);
            border-radius: var(--radius);
            padding: 20px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
            border: 1px solid var(--border);
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
            font-weight: 600;
            font-size: 0.92rem;
            margin-bottom: 6px;
            color: var(--text-main);
        }

        .form-control {
            width: 100%;
            padding: 10px 14px;
            border: 1.5px solid var(--border);
            border-radius: 8px;
            font-size: 0.95rem;
            box-sizing: border-box;
            background: #ffffff;
            color: var(--text-main);
            transition: border-color 0.2s;
        }

        .form-control:focus {
            outline: none;
            border-color: var(--primary-light);
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.15);
        }

        /* Question Item Styling */
        .question-box {
            background: #f8fafc;
            border: 1.5px solid #cbd5e1;
            border-radius: 10px;
            padding: 16px;
            margin-bottom: 16px;
            position: relative;
            transition: all 0.2s ease;
        }

        .question-box:hover {
            border-color: #94a3b8;
            box-shadow: 0 3px 10px rgba(0,0,0,0.04);
        }

        .q-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 12px;
            padding-bottom: 8px;
            border-bottom: 1px dashed #cbd5e1;
            flex-wrap: wrap;
            gap: 8px;
        }

        .q-badge {
            background: #1e3a8a;
            color: #ffffff;
            font-weight: 700;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 0.85rem;
        }

        .q-type-select {
            padding: 4px 10px;
            border-radius: 6px;
            border: 1px solid #94a3b8;
            font-weight: 600;
            font-size: 0.85rem;
            background: #ffffff;
        }

        .q-actions {
            display: flex;
            gap: 6px;
        }

        .options-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 10px;
            margin-top: 10px;
        }

        .option-item {
            display: flex;
            align-items: center;
            gap: 8px;
            background: #ffffff;
            padding: 6px 10px;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
        }

        .option-letter {
            font-weight: 700;
            color: #1e3a8a;
            min-width: 22px;
        }

        .badge-status {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 0.82rem;
            font-weight: 600;
        }
        .badge-draft { background: #fef3c7; color: #92400e; }
        .badge-submitted { background: #dbeafe; color: #1e40af; }
        .badge-approved { background: #dcfce7; color: #166534; }
        .badge-revision { background: #fee2e2; color: #991b1b; }

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
            font-weight: 700;
            color: #334155;
        }

        .exam-table tr:hover {
            background-color: #f8fafc;
        }

        .stats-bar {
            display: flex;
            gap: 16px;
            flex-wrap: wrap;
            margin-bottom: 16px;
            background: #eff6ff;
            padding: 12px 16px;
            border-radius: 8px;
            border-left: 4px solid #2563eb;
            font-weight: 600;
            font-size: 0.95rem;
            align-items: center;
            justify-content: space-between;
        }

        .sticky-actions {
            position: sticky;
            bottom: 10px;
            background: #ffffff;
            padding: 14px 20px;
            border-radius: var(--radius);
            box-shadow: 0 -4px 16px rgba(0, 0, 0, 0.1);
            border: 1px solid var(--border);
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 12px;
            z-index: 100;
            margin-top: 24px;
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
        <!-- Main Top Banner -->
        <div class="header-card">
            <div class="header-title">
                <h1>📝 የፈተና ዝግጅትና መላኪያ ማዕከል</h1>
                <p>መምህራን የፈተና ጥያቄዎችን በምርጫ፣ በእውነት/ሐሰት፣ በአዛምድ እና በክፍት ጥያቄ የሚያዘጋጁበት እና ለትምህርት ክፍል የሚልኩበት</p>
            </div>
            <div>
                <?php if ($editing_exam): ?>
                    <a href="exam_author.php" class="btn btn-outline" style="background:#ffffff; color:#1e3a8a;">⬅️ ወደ ፈተናዎች ዝርዝር ተመለስ</a>
                <?php else: ?>
                    <a href="exam_author.php?action=new" class="btn btn-brown">➕ አዲስ ፈተና አዘጋጅ</a>
                <?php endif; ?>
            </div>
        </div>

        <?php if (!empty($message) || !empty($_GET['msg'])): ?>
            <div class="alert alert-success">
                <span>✅</span>
                <span><?php echo htmlspecialchars($message ?: $_GET['msg']); ?></span>
            </div>
        <?php endif; ?>

        <?php if (!empty($error)): ?>
            <div class="alert alert-error">
                <span>⚠️</span>
                <span><?php echo htmlspecialchars($error); ?></span>
            </div>
        <?php endif; ?>

        <?php if ($action === 'new' || $editing_exam): ?>
            <!-- EXAM CREATOR / EDITOR FORM -->
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
                <input type="hidden" name="exam_id" value="<?php echo $is_edit ? $editing_exam['id'] : 0; ?>">
                <input type="hidden" name="action" id="formAction" value="save">

                <!-- 1. Exam Meta Info Card -->
                <div class="card">
                    <div class="card-header">
                        <h2>📋 የፈተናው አጠቃላይ መረጃ</h2>
                        <div>
                            <span class="badge-status badge-<?php echo htmlspecialchars($curr_status); ?>">
                                ሁኔታ፦ <?php 
                                    echo ($curr_status === 'draft') ? 'ረቂቅ (Draft)' : 
                                        (($curr_status === 'submitted') ? 'ለትምህርት ክፍል የተላከ' : 
                                        (($curr_status === 'approved') ? 'የጸደቀ' : 'ማስተካከያ የተጠየቀበት'));
                                ?>
                            </span>
                        </div>
                    </div>

                    <?php if ($curr_status === 'revision_requested' && !empty($editing_exam['admin_feedback'])): ?>
                        <div class="alert alert-error" style="margin-bottom: 16px;">
                            <strong>💬 የትምህርት ክፍል አስተያየት፦</strong>
                            <p style="margin:4px 0 0 0;"><?php echo nl2br(htmlspecialchars($editing_exam['admin_feedback'])); ?></p>
                        </div>
                    <?php endif; ?>

                    <div class="form-grid">
                        <div class="form-group">
                            <label for="class_id">ክፍል <span style="color:red;">*</span></label>
                            <select name="class_id" id="class_id" class="form-control" required onchange="autoFillTitle()">
                                <option value="">-- ክፍል ይምረጡ --</option>
                                <?php foreach ($my_classes as $cls): ?>
                                    <option value="<?php echo $cls['class_id']; ?>" 
                                            data-sub="<?php echo htmlspecialchars($cls['assigned_subject_name'] ?? ''); ?>"
                                            data-name="<?php echo htmlspecialchars($cls['class_name']); ?>"
                                            <?php echo ($curr_class == $cls['class_id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($cls['class_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="subject_name">የትምህርት ዓይነት <span style="color:red;">*</span></label>
                            <input type="text" name="subject_name" id="subject_name" list="subject_suggestions" class="form-control" 
                                   value="<?php echo htmlspecialchars($curr_subject); ?>" placeholder="ለምሳሌ፦ ሥርዓተ ቤተክርስቲያን" required oninput="autoFillTitle()">
                            <datalist id="subject_suggestions">
                                <?php foreach ($system_subjects as $s_name): ?>
                                    <option value="<?php echo htmlspecialchars($s_name); ?>">
                                <?php endforeach; ?>
                            </datalist>
                        </div>

                        <div class="form-group">
                            <label for="ethiopian_year">የትምህርት ዘመን (ዓ.ም)</label>
                            <input type="number" name="ethiopian_year" id="ethiopian_year" class="form-control" 
                                   value="<?php echo htmlspecialchars($curr_year); ?>" required oninput="autoFillTitle()">
                        </div>

                        <div class="form-group">
                            <label for="semester_id">መንፈቀ ዓመት (ሰሚስተር)</label>
                            <select name="semester_id" id="semester_id" class="form-control" onchange="autoFillTitle()">
                                <option value="1" <?php echo ($curr_sem == 1) ? 'selected' : ''; ?>>1ኛ መንፈቀ ዓመት</option>
                                <option value="2" <?php echo ($curr_sem == 2) ? 'selected' : ''; ?>>2ኛ መንፈቀ ዓመት</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="exam_type">የፈተና ዓይነት</label>
                            <select name="exam_type" id="exam_type" class="form-control" onchange="autoFillTitle()">
                                <option value="mid" <?php echo ($curr_type === 'mid') ? 'selected' : ''; ?>>የአጋማሽ ፈተና (Mid Exam)</option>
                                <option value="final" <?php echo ($curr_type === 'final') ? 'selected' : ''; ?>>የማጠቃለያ ፈተና (Final Exam)</option>
                                <option value="quiz" <?php echo ($curr_type === 'quiz') ? 'selected' : ''; ?>>የክፍል ፈተና (Quiz / Test)</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="duration_minutes">የሚፈጀው ጊዜ (በደቂቃ)</label>
                            <input type="number" name="duration_minutes" id="duration_minutes" class="form-control" 
                                   value="<?php echo htmlspecialchars($curr_duration); ?>" min="10" max="300">
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="title">የፈተናው ይፋዊ ሙሉ ርዕስ (ራስጌ ላይ የሚወጣ)</label>
                        <input type="text" name="title" id="title" class="form-control" 
                               value="<?php echo htmlspecialchars($curr_title); ?>" 
                               placeholder="የ2018 ዓ.ም የ8ኛ ክፍል የሥርዓተ ቤተክርስቲያን የ2ኛመንፈቀ ዓመት የአጋማሽ ፈተና">
                        <small style="color:var(--text-muted); font-size:0.83rem;">ይህ ርዕስ በፈተናው ወረቀት ራስጌ ላይ በመሃል ደምቆ ይታተማል። ከላይ ያሉትን ሲመርጡ በራሱ ይሞላል ወይም እንደፈለጉ ማስተካከል ይችላሉ።</small>
                    </div>

                    <div class="form-group" style="margin-bottom:0;">
                        <label for="instructions">ለተማሪዎች አጠቃላይ መመሪያ (Instructions)</label>
                        <textarea name="instructions" id="instructions" class="form-control" rows="2"><?php echo htmlspecialchars($curr_inst); ?></textarea>
                    </div>
                </div>

                <!-- 2. Questions Container Card -->
                <div class="card">
                    <div class="card-header">
                        <h2>✍️ የፈተና ጥያቄዎች ማዘጋጃ</h2>
                        <div style="display:flex; gap:8px; align-items:center; flex-wrap:wrap;">
                            <button type="button" class="btn btn-sm btn-outline" onclick="addQuestion('choice')">➕ ምርጫ (Choice)</button>
                            <button type="button" class="btn btn-sm btn-outline" onclick="addQuestion('true_false')">➕ እውነት/ሐሰት</button>
                            <button type="button" class="btn btn-sm btn-outline" onclick="addQuestion('matching')">➕ አዛምድ (Matching)</button>
                            <button type="button" class="btn btn-sm btn-outline" onclick="addQuestion('workout')">➕ ክፍት/አብራራ (Workout)</button>
                            <button type="button" class="btn btn-sm btn-outline" onclick="addQuestion('blank')">➕ ባዶ ቦታ ሙላ</button>
                        </div>
                    </div>

                    <!-- Live Summation & Summary -->
                    <div class="stats-bar">
                        <div>
                            <span>የጥያቄዎች ብዛት፦ <strong id="totalQuestionsCount">0</strong></span>
                            <span style="margin: 0 10px; color:#cbd5e1;">|</span>
                            <span>የነጥብ ድምር፦ <strong id="totalMarksSum" style="color:#1e3a8a; font-size:1.15rem;">0</strong> ነጥብ</span>
                        </div>
                        <div style="font-size:0.85rem; color:#475569;">
                            💡 ጥያቄዎችን በፈለጉት ቅደም ተከተል ማከል እና ነጥባቸውን ማስተካከል ይችላሉ።
                        </div>
                    </div>

                    <div id="questionsContainer">
                        <!-- Questions will be populated here via PHP or JS -->
                        <?php if (!empty($editing_questions)): ?>
                            <?php foreach ($editing_questions as $idx => $q): ?>
                                <?php 
                                    $q_num = $idx + 1;
                                    $q_type = $q['question_type'];
                                    $opts = !empty($q['options_json']) ? json_decode($q['options_json'], true) : [];
                                ?>
                                <div class="question-box" id="qbox_<?php echo $q_num; ?>" data-qnum="<?php echo $q_num; ?>">
                                    <div class="q-header">
                                        <div style="display:flex; align-items:center; gap:8px;">
                                            <span class="q-badge">ጥያቄ <?php echo $q_num; ?></span>
                                            <select name="questions[<?php echo $q_num; ?>][type]" class="q-type-select" onchange="changeQuestionType(<?php echo $q_num; ?>, this.value)">
                                                <option value="choice" <?php echo ($q_type === 'choice') ? 'selected' : ''; ?>>ምርጫ (Multiple Choice)</option>
                                                <option value="true_false" <?php echo ($q_type === 'true_false') ? 'selected' : ''; ?>>እውነት ወይም ሐሰት (True/False)</option>
                                                <option value="matching" <?php echo ($q_type === 'matching') ? 'selected' : ''; ?>>አዛምድ (Matching)</option>
                                                <option value="workout" <?php echo ($q_type === 'workout') ? 'selected' : ''; ?>>ክፍት ጥያቄ / አብራራ (Workout)</option>
                                                <option value="blank" <?php echo ($q_type === 'blank') ? 'selected' : ''; ?>>ባዶ ቦታ ሙላ (Fill in the Blank)</option>
                                            </select>
                                        </div>
                                        <div style="display:flex; align-items:center; gap:10px;">
                                            <label style="font-size:0.85rem; font-weight:600; margin:0;">ነጥብ፦</label>
                                            <input type="number" step="0.5" name="questions[<?php echo $q_num; ?>][marks]" 
                                                   value="<?php echo htmlspecialchars($q['marks']); ?>" 
                                                   class="form-control q-marks-input" style="width:70px; padding:4px 8px; font-weight:700;" oninput="updateLiveStats()">
                                            <div class="q-actions">
                                                <button type="button" class="btn btn-sm btn-outline" title="ወደ ላይ" onclick="moveQuestion(<?php echo $q_num; ?>, -1)">⬆️</button>
                                                <button type="button" class="btn btn-sm btn-outline" title="ወደ ታች" onclick="moveQuestion(<?php echo $q_num; ?>, 1)">⬇️</button>
                                                <button type="button" class="btn btn-sm btn-danger" title="ሰርዝ" onclick="removeQuestion(<?php echo $q_num; ?>)">🗑️</button>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="form-group" style="margin-bottom:8px;">
                                        <input type="text" name="questions[<?php echo $q_num; ?>][section_title]" 
                                               value="<?php echo htmlspecialchars($q['section_title'] ?? ''); ?>" 
                                               class="form-control" placeholder="የክፍል አርዕስት (አማራጭ፦ ለምሳሌ ክፍል ፩፡ ትክክለኛውን መልስ ምረጥ)">
                                    </div>

                                    <div class="form-group" style="margin-bottom:8px;">
                                        <textarea name="questions[<?php echo $q_num; ?>][text]" class="form-control" rows="2" 
                                                  placeholder="የጥያቄው ጽሑፍ..." required><?php echo htmlspecialchars($q['question_text']); ?></textarea>
                                    </div>

                                    <!-- DYNAMIC TYPE CONTAINER -->
                                    <div class="q-type-container" id="q_type_container_<?php echo $q_num; ?>">
                                        <?php if ($q_type === 'choice'): ?>
                                            <div class="options-grid">
                                                <div class="option-item">
                                                    <span class="option-letter">ሀ.</span>
                                                    <input type="text" name="questions[<?php echo $q_num; ?>][choice_a]" value="<?php echo htmlspecialchars($opts['ሀ'] ?? ''); ?>" class="form-control" placeholder="አማራጭ ሀ">
                                                </div>
                                                <div class="option-item">
                                                    <span class="option-letter">ለ.</span>
                                                    <input type="text" name="questions[<?php echo $q_num; ?>][choice_b]" value="<?php echo htmlspecialchars($opts['ለ'] ?? ''); ?>" class="form-control" placeholder="አማራጭ ለ">
                                                </div>
                                                <div class="option-item">
                                                    <span class="option-letter">ሐ.</span>
                                                    <input type="text" name="questions[<?php echo $q_num; ?>][choice_c]" value="<?php echo htmlspecialchars($opts['ሐ'] ?? ''); ?>" class="form-control" placeholder="አማራጭ ሐ">
                                                </div>
                                                <div class="option-item">
                                                    <span class="option-letter">መ.</span>
                                                    <input type="text" name="questions[<?php echo $q_num; ?>][choice_d]" value="<?php echo htmlspecialchars($opts['መ'] ?? ''); ?>" class="form-control" placeholder="አማራጭ መ">
                                                </div>
                                            </div>
                                            <div style="margin-top:10px; display:flex; align-items:center; gap:8px;">
                                                <label style="font-size:0.85rem; font-weight:600; color:#1e3a8a;">ትክክለኛ መልስ፦</label>
                                                <select name="questions[<?php echo $q_num; ?>][correct_answer]" class="form-control" style="width:130px; font-weight:700;">
                                                    <option value="">-- ይምረጡ --</option>
                                                    <option value="ሀ" <?php echo ($q['correct_answer'] === 'ሀ') ? 'selected' : ''; ?>>ሀ</option>
                                                    <option value="ለ" <?php echo ($q['correct_answer'] === 'ለ') ? 'selected' : ''; ?>>ለ</option>
                                                    <option value="ሐ" <?php echo ($q['correct_answer'] === 'ሐ') ? 'selected' : ''; ?>>ሐ</option>
                                                    <option value="መ" <?php echo ($q['correct_answer'] === 'መ') ? 'selected' : ''; ?>>መ</option>
                                                </select>
                                            </div>
                                        <?php elseif ($q_type === 'true_false'): ?>
                                            <div style="display:flex; align-items:center; gap:16px; margin-top:8px;">
                                                <label style="font-size:0.85rem; font-weight:600; color:#1e3a8a;">ትክክለኛ መልስ፦</label>
                                                <label style="cursor:pointer;"><input type="radio" name="questions[<?php echo $q_num; ?>][correct_answer]" value="እውነት" <?php echo ($q['correct_answer'] === 'እውነት') ? 'checked' : ''; ?>> እውነት</label>
                                                <label style="cursor:pointer;"><input type="radio" name="questions[<?php echo $q_num; ?>][correct_answer]" value="ሐሰት" <?php echo ($q['correct_answer'] === 'ሐሰት') ? 'checked' : ''; ?>> ሐሰት</label>
                                            </div>
                                        <?php elseif ($q_type === 'matching'): ?>
                                            <div style="margin-top:10px;">
                                                <p style="margin:0 0 6px 0; font-size:0.85rem; font-weight:600; color:#1e3a8a;">የ 'ሀ' ረድፍ እና የ 'ለ' ረድፍ ማዛመጃዎች፦</p>
                                                <?php 
                                                    $pairs = is_array($opts) ? $opts : [];
                                                    for ($p_idx = 0; $p_idx < max(4, count($pairs)); $p_idx++): 
                                                        $pair = $pairs[$p_idx] ?? ['left' => '', 'right' => ''];
                                                ?>
                                                    <div style="display:flex; gap:10px; margin-bottom:6px; align-items:center;">
                                                        <span style="font-weight:700; width:20px;"><?php echo ($p_idx + 1); ?>.</span>
                                                        <input type="text" name="questions[<?php echo $q_num; ?>][match_left][]" value="<?php echo htmlspecialchars($pair['left'] ?? ''); ?>" class="form-control" placeholder="ረድፍ 'ሀ' ንጥል">
                                                        <span style="font-weight:700;">⇄</span>
                                                        <input type="text" name="questions[<?php echo $q_num; ?>][match_right][]" value="<?php echo htmlspecialchars($pair['right'] ?? ''); ?>" class="form-control" placeholder="ረድፍ 'ለ' ንጥል">
                                                    </div>
                                                <?php endfor; ?>
                                            </div>
                                        <?php elseif ($q_type === 'workout'): ?>
                                            <div style="margin-top:10px; display:flex; gap:14px; flex-wrap:wrap;">
                                                <div style="flex:1; min-width:200px;">
                                                    <label style="font-size:0.85rem; font-weight:600; margin-bottom:4px; display:block;">ለተማሪው የሚተው የመስመር ብዛት፦</label>
                                                    <input type="number" name="questions[<?php echo $q_num; ?>][blank_lines]" value="<?php echo htmlspecialchars($q['blank_lines']); ?>" class="form-control" min="1" max="15">
                                                </div>
                                                <div style="flex:2; min-width:240px;">
                                                    <label style="font-size:0.85rem; font-weight:600; margin-bottom:4px; display:block;">የአስተማሪው መልስ ፍንጭ/ቁልፍ (ለአራሚው የሚሆን)፦</label>
                                                    <input type="text" name="questions[<?php echo $q_num; ?>][correct_answer]" value="<?php echo htmlspecialchars($q['correct_answer']); ?>" class="form-control" placeholder="የመልስ ቁልፍ...">
                                                </div>
                                            </div>
                                        <?php elseif ($q_type === 'blank'): ?>
                                            <div style="margin-top:8px;">
                                                <label style="font-size:0.85rem; font-weight:600; color:#1e3a8a;">ትክክለኛ ባዶ ቦታ መልስ፦</label>
                                                <input type="text" name="questions[<?php echo $q_num; ?>][correct_answer]" value="<?php echo htmlspecialchars($q['correct_answer']); ?>" class="form-control" placeholder="የሚሞላው ቃል ወይም ሐረግ...">
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>

                    <div style="text-align:center; padding:16px 0;">
                        <button type="button" class="btn btn-outline" style="border-style:dashed; border-width:2px; width:100%; max-width:400px;" onclick="addQuestion('choice')">
                            ➕ ተጨማሪ ጥያቄ ጨምር
                        </button>
                    </div>
                </div>

                <!-- 3. Bottom Sticky Action Bar -->
                <div class="sticky-actions">
                    <div style="display:flex; align-items:center; gap:12px;">
                        <button type="button" class="btn btn-secondary" onclick="submitExamForm('save')">
                            💾 ረቂቅ አስቀምጥ (Save Draft)
                        </button>
                        <button type="button" class="btn btn-success" onclick="submitExamForm('submit_to_admin')">
                            🚀 ወደ ትምህርት ክፍል ላክ (Submit Exam)
                        </button>
                    </div>

                    <div style="display:flex; align-items:center; gap:10px;">
                        <?php if ($is_edit): ?>
                            <a href="exam_print.php?id=<?php echo $editing_exam['id']; ?>" target="_blank" class="btn btn-brown">
                                🖨️ የፈተና ወረቀት እይ / አትም
                            </a>
                            <a href="exam_print.php?id=<?php echo $editing_exam['id']; ?>&key=1" target="_blank" class="btn btn-outline">
                                🔑 የመልስ ቁልፍ
                            </a>
                        <?php endif; ?>
                        <a href="exam_author.php" class="btn btn-outline">ተመለስ</a>
                    </div>
                </div>
            </form>

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
                                            <strong style="color:#1e3a8a;"><?php echo htmlspecialchars($ex['title']); ?></strong>
                                            <div style="font-size:0.8rem; color:var(--text-muted); margin-top:2px;">
                                                ፈታኝ፦ <?php echo htmlspecialchars($ex['teacher_name']); ?> • ጊዜ፦ <?php echo $ex['duration_minutes']; ?> ደቂቃ
                                            </div>
                                        </td>
                                        <td>
                                            <span style="font-weight:600;"><?php echo htmlspecialchars($ex['class_name']); ?></span><br>
                                            <small style="color:var(--text-muted);"><?php echo htmlspecialchars($ex['subject_name']); ?></small>
                                        </td>
                                        <td>
                                            <?php echo htmlspecialchars($ex['ethiopian_year']); ?> ዓ.ም<br>
                                            <small><?php echo ($ex['semester_id'] == 2) ? '2ኛ ሰሚስተር' : '1ኛ ሰሚስተር'; ?></small>
                                        </td>
                                        <td><span class="badge-status" style="background:#e0f2fe; color:#0369a1;"><?php echo $ex['question_count']; ?> ጥያቄዎች</span></td>
                                        <td><strong><?php echo floatval($ex['total_marks']); ?></strong></td>
                                        <td>
                                            <span class="badge-status badge-<?php echo htmlspecialchars($ex['status']); ?>">
                                                <?php 
                                                    echo ($ex['status'] === 'draft') ? 'ረቂቅ (Draft)' : 
                                                        (($ex['status'] === 'submitted') ? 'የተላከ 🚀' : 
                                                        (($ex['status'] === 'approved') ? 'የጸደቀ ✅' : 'ማስተካከያ ⚠️'));
                                                ?>
                                            </span>
                                            <?php if ($ex['status'] === 'revision_requested' && !empty($ex['admin_feedback'])): ?>
                                                <div style="font-size:0.75rem; color:#dc2626; margin-top:2px;" title="<?php echo htmlspecialchars($ex['admin_feedback']); ?>">
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

    <!-- JavaScript For Dynamic Questions Authoring -->
    <script>
        let questionCounter = <?php echo !empty($editing_questions) ? count($editing_questions) : 0; ?>;

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

        // If class is chosen and subject is empty, fill assigned subject
        document.getElementById('class_id')?.addEventListener('change', function() {
            const opt = this.options[this.selectedIndex];
            const subInput = document.getElementById('subject_name');
            if (opt && opt.getAttribute('data-sub') && subInput && !subInput.value) {
                subInput.value = opt.getAttribute('data-sub');
            }
            autoFillTitle();
        });

        function addQuestion(type = 'choice') {
            questionCounter++;
            const container = document.getElementById('questionsContainer');
            if (!container) return;

            const qBox = document.createElement('div');
            qBox.className = 'question-box';
            qBox.id = `qbox_${questionCounter}`;
            qBox.setAttribute('data-qnum', questionCounter);

            let typeOptions = `
                <option value="choice" ${type === 'choice' ? 'selected' : ''}>ምርጫ (Multiple Choice)</option>
                <option value="true_false" ${type === 'true_false' ? 'selected' : ''}>እውነት ወይም ሐሰት (True/False)</option>
                <option value="matching" ${type === 'matching' ? 'selected' : ''}>አዛምድ (Matching)</option>
                <option value="workout" ${type === 'workout' ? 'selected' : ''}>ክፍት ጥያቄ / አብራራ (Workout)</option>
                <option value="blank" ${type === 'blank' ? 'selected' : ''}>ባዶ ቦታ ሙላ (Fill in the Blank)</option>
            `;

            qBox.innerHTML = `
                <div class="q-header">
                    <div style="display:flex; align-items:center; gap:8px;">
                        <span class="q-badge">ጥያቄ ${questionCounter}</span>
                        <select name="questions[${questionCounter}][type]" class="q-type-select" onchange="changeQuestionType(${questionCounter}, this.value)">
                            ${typeOptions}
                        </select>
                    </div>
                    <div style="display:flex; align-items:center; gap:10px;">
                        <label style="font-size:0.85rem; font-weight:600; margin:0;">ነጥብ፦</label>
                        <input type="number" step="0.5" name="questions[${questionCounter}][marks]" value="1.0" 
                               class="form-control q-marks-input" style="width:70px; padding:4px 8px; font-weight:700;" oninput="updateLiveStats()">
                        <div class="q-actions">
                            <button type="button" class="btn btn-sm btn-outline" title="ወደ ላይ" onclick="moveQuestion(${questionCounter}, -1)">⬆️</button>
                            <button type="button" class="btn btn-sm btn-outline" title="ወደ ታች" onclick="moveQuestion(${questionCounter}, 1)">⬇️</button>
                            <button type="button" class="btn btn-sm btn-danger" title="ሰርዝ" onclick="removeQuestion(${questionCounter})">🗑️</button>
                        </div>
                    </div>
                </div>

                <div class="form-group" style="margin-bottom:8px;">
                    <input type="text" name="questions[${questionCounter}][section_title]" class="form-control" placeholder="የክፍል አርዕስት (አማራጭ፦ ለምሳሌ ክፍል ፩፡ ትክክለኛውን መልስ ምረጥ)">
                </div>

                <div class="form-group" style="margin-bottom:8px;">
                    <textarea name="questions[${questionCounter}][text]" class="form-control" rows="2" placeholder="የጥያቄው ጽሑፍ..." required></textarea>
                </div>

                <div class="q-type-container" id="q_type_container_${questionCounter}">
                    ${renderTypeHTML(questionCounter, type)}
                </div>
            `;

            container.appendChild(qBox);
            renumberQuestions();
            updateLiveStats();
        }

        function renderTypeHTML(num, type) {
            if (type === 'choice') {
                return `
                    <div class="options-grid">
                        <div class="option-item"><span class="option-letter">ሀ.</span><input type="text" name="questions[${num}][choice_a]" class="form-control" placeholder="አማራጭ ሀ"></div>
                        <div class="option-item"><span class="option-letter">ለ.</span><input type="text" name="questions[${num}][choice_b]" class="form-control" placeholder="አማራጭ ለ"></div>
                        <div class="option-item"><span class="option-letter">ሐ.</span><input type="text" name="questions[${num}][choice_c]" class="form-control" placeholder="አማራጭ ሐ"></div>
                        <div class="option-item"><span class="option-letter">መ.</span><input type="text" name="questions[${num}][choice_d]" class="form-control" placeholder="አማራጭ መ"></div>
                    </div>
                    <div style="margin-top:10px; display:flex; align-items:center; gap:8px;">
                        <label style="font-size:0.85rem; font-weight:600; color:#1e3a8a;">ትክክለኛ መልስ፦</label>
                        <select name="questions[${num}][correct_answer]" class="form-control" style="width:130px; font-weight:700;">
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
                        <label style="font-size:0.85rem; font-weight:600; color:#1e3a8a;">ትክክለኛ መልስ፦</label>
                        <label style="cursor:pointer;"><input type="radio" name="questions[${num}][correct_answer]" value="እውነት"> እውነት</label>
                        <label style="cursor:pointer;"><input type="radio" name="questions[${num}][correct_answer]" value="ሐሰት"> ሐሰት</label>
                    </div>
                `;
            } else if (type === 'matching') {
                let rows = '';
                for (let i = 1; i <= 4; i++) {
                    rows += `
                        <div style="display:flex; gap:10px; margin-bottom:6px; align-items:center;">
                            <span style="font-weight:700; width:20px;">${i}.</span>
                            <input type="text" name="questions[${num}][match_left][]" class="form-control" placeholder="ረድፍ 'ሀ' ንጥል">
                            <span style="font-weight:700;">⇄</span>
                            <input type="text" name="questions[${num}][match_right][]" class="form-control" placeholder="ረድፍ 'ለ' ንጥል">
                        </div>
                    `;
                }
                return `
                    <div style="margin-top:10px;">
                        <p style="margin:0 0 6px 0; font-size:0.85rem; font-weight:600; color:#1e3a8a;">የ 'ሀ' ረድፍ እና የ 'ለ' ረድፍ ማዛመጃዎች፦</p>
                        ${rows}
                    </div>
                `;
            } else if (type === 'workout') {
                return `
                    <div style="margin-top:10px; display:flex; gap:14px; flex-wrap:wrap;">
                        <div style="flex:1; min-width:200px;">
                            <label style="font-size:0.85rem; font-weight:600; margin-bottom:4px; display:block;">ለተማሪው የሚተው የመስመር ብዛት፦</label>
                            <input type="number" name="questions[${num}][blank_lines]" value="4" class="form-control" min="1" max="15">
                        </div>
                        <div style="flex:2; min-width:240px;">
                            <label style="font-size:0.85rem; font-weight:600; margin-bottom:4px; display:block;">የአስተማሪው መልስ ፍንጭ/ቁልፍ (ለአራሚው የሚሆን)፦</label>
                            <input type="text" name="questions[${num}][correct_answer]" class="form-control" placeholder="የመልስ ቁልፍ...">
                        </div>
                    </div>
                `;
            } else if (type === 'blank') {
                return `
                    <div style="margin-top:8px;">
                        <label style="font-size:0.85rem; font-weight:600; color:#1e3a8a;">ትክክለኛ ባዶ ቦታ መልስ፦</label>
                        <input type="text" name="questions[${num}][correct_answer]" class="form-control" placeholder="የሚሞላው ቃል ወይም ሐረግ...">
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
        }

        function renumberQuestions() {
            const boxes = document.querySelectorAll('.question-box');
            boxes.forEach((box, idx) => {
                const badge = box.querySelector('.q-badge');
                if (badge) badge.innerText = `ጥያቄ ${idx + 1}`;
            });
            updateLiveStats();
        }

        function updateLiveStats() {
            const boxes = document.querySelectorAll('.question-box');
            const totalCountEl = document.getElementById('totalQuestionsCount');
            const totalMarksEl = document.getElementById('totalMarksSum');

            let count = boxes.length;
            let sum = 0.0;

            const markInputs = document.querySelectorAll('.q-marks-input');
            markInputs.forEach(input => {
                const val = parseFloat(input.value) || 0;
                sum += val;
            });

            if (totalCountEl) totalCountEl.innerText = count;
            if (totalMarksEl) totalMarksEl.innerText = sum.toFixed(1);
        }

        function submitExamForm(act) {
            const actInput = document.getElementById('formAction');
            if (actInput) actInput.value = act;

            const form = document.getElementById('examForm');
            if (act === 'submit_to_admin') {
                if (confirm('እርግጠኛ ነዎት ይህ ፈተና ተጠናቆ ለትምህርት ክፍል እንዲላክ ይፈልጋሉ?')) {
                    form.submit();
                }
            } else {
                form.submit();
            }
        }

        // Initialize on load
        document.addEventListener('DOMContentLoaded', function() {
            if (questionCounter === 0 && document.getElementById('questionsContainer')) {
                // If new exam and empty, add 2 initial questions
                addQuestion('true_false');
                addQuestion('choice');
            } else {
                updateLiveStats();
            }
        });
    </script>
</body>
</html>
