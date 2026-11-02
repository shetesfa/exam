<?php
require_once 'db.php';
ensureExamTablesExist($conn);

// Authentication check
if (empty($_SESSION['user_id']) && empty($_SESSION['role']) && empty($_SESSION['student_id'])) {
    header("Location: index.php");
    exit();
}

$exam_id = intval($_GET['id'] ?? 0);
if ($exam_id <= 0) {
    die("ትክክለኛ የፈተና መለያ አልተገኘም!");
}

// Fetch exam details with safe LEFT JOIN
$exam = dbFetchOne($conn, 
    "SELECT e.*, COALESCE(c.name, 'ያልታወቀ ክፍል') as class_name, COALESCE(u.name, 'ያልታወቀ መምህር') as teacher_name 
     FROM exams e 
     LEFT JOIN classes c ON e.class_id = c.id 
     LEFT JOIN users u ON e.teacher_id = u.id 
     WHERE e.id = ?", 
    "i", [$exam_id]
);

if (!$exam) {
    die("ይህ ፈተና በሲስተሙ ውስጥ አልተገኘም!");
}

// Fetch questions
$questions = dbFetchAll($conn, 
    "SELECT * FROM exam_questions WHERE exam_id = ? ORDER BY order_num ASC, id ASC", 
    "i", [$exam_id]
);

// Show Answer Key flag
$show_key = isset($_GET['key']) && $_GET['key'] == '1';

// Calculate totals safely
$total_marks = 0;
foreach ($questions as $q) {
    $total_marks += floatval($q['marks'] ?? 0);
}
if ($total_marks == 0) {
    $total_marks = floatval($exam['total_marks'] ?? 100);
}

// Clean and canonical Exam Title strictly following official user specification from image
$raw_class = (string)($exam['class_name'] ?? 'ክፍል');
// Strip any parentheses like (Grade 3), (እሁድ 6፡10-7፡00)
$clean_class = preg_replace('/\(.*?\)/u', '', $raw_class);
$clean_class = preg_replace('/\[.*?\]/u', '', $clean_class);
$clean_class = trim(preg_replace('/\s+/u', ' ', $clean_class));

$raw_subject = (string)($exam['subject_name'] ?? 'ትምህርት');
$clean_subject = preg_replace('/\(.*?\)/u', '', $raw_subject);
$clean_subject = trim(preg_replace('/\s+/u', ' ', $clean_subject));

$class_name = $clean_class;
$subject_name = $clean_subject;

$ethiopian_year = $exam['ethiopian_year'] ?? 2018;
$semester_name = (($exam['semester_id'] ?? 1) == 2) ? 'የ2ኛመንፈቀ ዓመት' : 'የ1ኛመንፈቀ ዓመት';

$exam_type_text = 'የአጋማሽ ፈተና';
if (($exam['exam_type'] ?? '') === 'final') {
    $exam_type_text = 'የማጠቃለያ ፈተና';
} elseif (($exam['exam_type'] ?? '') === 'quiz') {
    $exam_type_text = 'የክፍል ፈተና';
}

// Canonical Official Center Header (Exact match to official Church specification)
$church_prefix = "የአያት መካነ ሰማዕት ቅዱስ ጊዮርጊስ ቅድስት ኪዳነ ምሕረት ወአቡነ ሀብተማርያም ቤተ ክርስቲያን አጸደ ትጉሃን ሰንበት ትምህርት ቤት";
$official_title = "{$church_prefix} የ{$ethiopian_year} ዓ.ም የ{$clean_class} የ{$clean_subject} {$semester_name} {$exam_type_text}";

$page_title = "የፈተና ወረቀት - " . $class_name . " " . $subject_name;
?>
<!DOCTYPE html>
<html lang="am">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars((string)$page_title); ?></title>
    <link rel="icon" type="image/png" href="images/icon.png">
    <style>
        @page {
            size: A4 portrait;
            margin: 12mm 15mm 15mm 15mm;
        }

        *, *::before, *::after {
            box-sizing: border-box;
        }

        body {
            font-family: 'Times New Roman', 'Nyala', 'Abyssinica SIL', 'Segoe UI', Tahoma, serif;
            color: #000000;
            background-color: #f1f5f9;
            margin: 0;
            padding: 20px 0;
            font-size: 14pt;
            line-height: 1.5;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        /* Screen toolbar */
        .no-print-bar {
            max-width: 210mm;
            margin: 0 auto 16px auto;
            background: #ffffff;
            padding: 12px 20px;
            border-radius: 12px;
            box-shadow: 0 4px 16px rgba(0,0,0,0.08);
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 10px;
            font-family: 'Segoe UI', Tahoma, sans-serif;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 8px 16px;
            font-size: 0.92rem;
            font-weight: 700;
            border-radius: 8px;
            cursor: pointer;
            text-decoration: none;
            border: 1px solid transparent;
            transition: all 0.2s;
        }

        .btn:hover { transform: translateY(-1px); }
        .btn-print { background: #1e3a8a; color: #ffffff; }
        .btn-print:hover { background: #172554; }
        .btn-key { background: #8B4513; color: #ffffff; }
        .btn-key:hover { background: #6d360e; }
        .btn-back { background: #e2e8f0; color: #1e293b; }
        .btn-back:hover { background: #cbd5e1; }

        /* A4 Paper Sheet */
        .exam-page {
            width: 210mm;
            min-height: 297mm;
            margin: 0 auto;
            background: #ffffff;
            padding: 16mm 20mm;
            box-shadow: 0 0 16px rgba(0, 0, 0, 0.12);
            position: relative;
        }

        /* ==============================================================
           OFFICIAL EXAM HEADER (EXACT USER SPEC FROM IMAGE)
           ============================================================== */
        .official-header {
            width: 100%;
            margin-bottom: 8px;
        }

        .header-top-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            width: 100%;
            margin-bottom: 12px;
        }

        .header-amharic {
            font-size: 1.35rem;
            font-weight: 900;
            color: #000000;
            text-align: left;
            flex: 1;
            letter-spacing: 0.5px;
        }

        .header-logo-box {
            text-align: center;
            flex: 0 0 auto;
            padding: 0 14px;
        }

        .header-logo-box img {
            width: 58px;
            height: 58px;
            border-radius: 50%;
            object-fit: cover;
            display: block;
            margin: 0 auto;
        }

        .header-english {
            font-size: 1.25rem;
            font-weight: 800;
            color: #000000;
            text-align: right;
            flex: 1;
            font-family: 'Times New Roman', serif;
        }

        /* Student Info Line */
        .student-info-row {
            display: flex;
            align-items: flex-end;
            justify-content: space-between;
            width: 100%;
            font-size: 1.15rem;
            font-weight: 800;
            color: #000000;
            margin-bottom: 8px;
            padding: 0 2px;
        }

        .info-field {
            display: flex;
            align-items: flex-end;
            flex: 1;
        }

        .info-field.field-name { flex: 2.2; }
        .info-field.field-class { flex: 1.2; margin: 0 10px; }
        .info-field.field-chr-name { flex: 2.2; }

        .info-label {
            white-space: nowrap;
            margin-right: 6px;
        }

        .info-line {
            flex: 1;
            border-bottom: 1.5px solid #000000;
            height: 18px;
            margin-bottom: 2px;
        }

        /* Thick Brown Divider (#8B4513) */
        .brown-divider {
            width: 100%;
            height: 5px;
            background-color: #8B4513;
            margin-top: 4px;
            margin-bottom: 12px;
        }

        /* Official Centered Bold Title */
        .exam-main-title {
            text-align: center;
            font-size: 1.12rem;
            font-weight: 900;
            color: #000000;
            line-height: 1.45;
            margin-bottom: 14px;
            padding: 0 10px;
        }

        /* Instructions & Time Bar */
        .exam-sub-bar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            font-size: 0.95rem;
            font-weight: 800;
            border-top: 1px dashed #475569;
            border-bottom: 1px dashed #475569;
            padding: 6px 10px;
            margin-bottom: 16px;
        }

        .exam-instructions-box {
            font-size: 0.98rem;
            font-style: italic;
            margin-bottom: 16px;
            padding: 6px 10px;
            background: #fafafa;
            border-left: 3.5px solid #8B4513;
        }

        /* Questions Section */
        .section-header {
            font-size: 1.15rem;
            font-weight: 900;
            margin-top: 20px;
            margin-bottom: 10px;
            color: #000000;
            border-bottom: 1.5px solid #000000;
            padding-bottom: 4px;
            page-break-after: avoid;
        }

        .question-item {
            margin-bottom: 16px;
            page-break-inside: avoid;
        }

        .q-text-row {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            font-weight: 600;
            font-size: 1.05rem;
            margin-bottom: 6px;
        }

        .q-number {
            font-weight: 900;
            min-width: 28px;
        }

        .q-body {
            flex: 1;
            padding-right: 12px;
        }

        .q-mark-badge {
            font-weight: 800;
            white-space: nowrap;
            font-size: 0.92rem;
        }

        /* True/False Underline */
        .tf-answer-blank {
            display: inline-block;
            min-width: 80px;
            border-bottom: 1px solid #000000;
            text-align: center;
            font-weight: 800;
        }

        /* Choice options layout */
        .choice-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 6px 20px;
            margin-top: 6px;
            margin-left: 28px;
        }

        .choice-option {
            font-size: 1rem;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .choice-letter {
            font-weight: 900;
        }

        /* Matching table */
        .matching-table {
            width: 100%;
            margin: 10px 0 16px 28px;
            border-collapse: collapse;
            font-size: 1rem;
        }

        .matching-table td {
            padding: 5px 8px;
            vertical-align: middle;
        }

        .matching-left {
            width: 45%;
            font-weight: 600;
        }

        .matching-mid {
            width: 10%;
            text-align: center;
            font-weight: 800;
        }

        .matching-right {
            width: 45%;
        }

        /* Workout Blank Writing Lines */
        .workout-lines {
            margin: 8px 0 12px 28px;
        }

        .workout-line {
            width: 100%;
            border-bottom: 1px dotted #555555;
            height: 24px;
        }

        /* Answer Key Highlight */
        .key-indicator {
            display: <?php echo $show_key ? 'inline-block' : 'none'; ?>;
            background: #fef08a;
            color: #854d0e;
            padding: 2px 6px;
            border-radius: 4px;
            font-size: 0.85rem;
            font-weight: 800;
            border: 1px solid #eab308;
            margin-left: 8px;
        }

        .exam-footer-note {
            margin-top: 30px;
            text-align: center;
            font-size: 0.85rem;
            font-weight: 800;
            border-top: 1px solid #000000;
            padding-top: 8px;
            color: #333333;
        }

        @media screen and (max-width: 768px) {
            body {
                padding: 10px 6px;
                font-size: 11.5pt;
            }
            .no-print-bar {
                padding: 10px 12px;
                flex-direction: column;
                align-items: stretch;
            }
            .no-print-bar > div {
                justify-content: space-between;
                width: 100%;
            }
            .exam-page {
                width: 100% !important;
                min-height: auto !important;
                padding: 14px 12px !important;
                border-radius: 10px;
            }
            .header-top-row {
                flex-direction: column;
                text-align: center;
                gap: 8px;
            }
            .header-amharic, .header-english {
                text-align: center;
                font-size: 1.15rem;
            }
            .student-info-row {
                flex-direction: column;
                align-items: stretch;
                gap: 8px;
            }
            .info-field.field-class {
                margin: 0;
            }
            .choice-grid {
                grid-template-columns: 1fr;
                margin-left: 12px;
            }
            .matching-table {
                margin-left: 0;
                font-size: 0.95rem;
            }
            .workout-lines {
                margin-left: 10px;
            }
        }

        @media print {
            body {
                background: #ffffff;
                padding: 0;
                color: #000000;
                font-size: 13pt;
            }

            .no-print-bar {
                display: none !important;
            }

            .exam-page {
                box-shadow: none !important;
                margin: 0 !important;
                padding: 0 !important;
                width: 100% !important;
                min-height: auto !important;
            }

            .question-item {
                page-break-inside: avoid !important;
            }

            .section-header {
                page-break-after: avoid !important;
            }

            .brown-divider {
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
                background-color: #8B4513 !important;
            }

            .key-indicator {
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }
        }
    </style>
</head>
<body>

    <!-- SCREEN-ONLY CONTROL TOOLBAR -->
    <div class="no-print-bar">
        <div style="display:flex; align-items:center; gap:10px;">
            <a href="javascript:history.back()" class="btn btn-back">⬅️ ተመለስ</a>
            <span style="font-weight:700; color:#1e3a8a; font-size:1.05rem;">
                📄 የፈተና ወረቀት ማተሚያ
            </span>
            <span style="background:#e2e8f0; padding:3px 8px; border-radius:6px; font-size:0.85rem; font-weight:700;">
                <?php echo htmlspecialchars((string)($exam['class_name'] . ' - ' . $exam['subject_name'])); ?>
            </span>
        </div>

        <div style="display:flex; align-items:center; gap:8px;">
            <button onclick="window.print()" class="btn btn-print">
                🖨️ ፈተናውን አትም (Print)
            </button>
            <?php if ($show_key): ?>
                <a href="exam_print.php?id=<?php echo $exam_id; ?>" class="btn btn-back">
                    ❌ የመልስ ቁልፍ ደብቅ
                </a>
            <?php else: ?>
                <a href="exam_print.php?id=<?php echo $exam_id; ?>&key=1" class="btn btn-key">
                    🔑 የመልስ ቁልፍ አሳይ (Answer Key)
                </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- MAIN A4 EXAM SHEET -->
    <div class="exam-page">

        <!-- ==========================================
             1. OFFICIAL HEADER (EXACT USER SPEC)
             ========================================== -->
        <header class="official-header">
            <div class="header-top-row">
                <div class="header-amharic">
                    አጸደ ትጉሃን ሰንበት ትምህርት ቤት
                </div>

                <div class="header-logo-box">
                    <img src="images/icon.png" alt="አርማ" onerror="this.style.display='none'">
                </div>

                <div class="header-english">
                    Atsede Teguhan Sunday School
                </div>
            </div>

            <div class="student-info-row">
                <div class="info-field field-name">
                    <span class="info-label">ስም</span>
                    <div class="info-line"></div>
                </div>
                <div class="info-field field-class">
                    <span class="info-label">ክፍል</span>
                    <div class="info-line" style="text-align:center; font-weight:800;"></div>
                </div>
                <div class="info-field field-chr-name">
                    <span class="info-label">የክርስትና ስም</span>
                    <div class="info-line"></div>
                </div>
            </div>

            <div class="brown-divider"></div>

            <div class="exam-main-title">
                <?php echo htmlspecialchars((string)$official_title); ?>
            </div>
        </header>

        <!-- Sub Info & Instructions -->
        <div class="exam-sub-bar">
            <div>
                <span>የተፈቀደው ሰዓት፦ <strong><?php echo intval($exam['duration_minutes'] ?? 60); ?> ደቂቃ</strong></span>
            </div>
            <div>
                <span>ጠቅላላ ነጥብ፦ <strong><?php echo $total_marks; ?>%</strong></span>
            </div>
        </div>

        <?php if (!empty($exam['instructions'])): ?>
            <div class="exam-instructions-box">
                <strong>አጠቃላይ መመሪያ፦</strong> <?php echo nl2br(htmlspecialchars((string)$exam['instructions'])); ?>
            </div>
        <?php endif; ?>

        <!-- ==========================================
             2. QUESTIONS CONTENT
             ========================================== -->
        <main class="exam-body">
            <?php 
                $current_section = '';
                $q_counter = 1;
            ?>

            <?php foreach ($questions as $q): ?>
                <?php 
                    $sec = (string)($q['section_title'] ?? '');
                    if (!empty($sec) && $sec !== $current_section) {
                        $current_section = $sec;
                        echo '<div class="section-header">' . htmlspecialchars((string)$current_section) . '</div>';
                    }

                    $q_type = $q['question_type'] ?? 'choice';
                    $q_marks = floatval($q['marks'] ?? 1.0);
                    $opts = !empty($q['options_json']) ? json_decode($q['options_json'], true) : [];
                    if (!is_array($opts)) $opts = [];
                ?>

                <div class="question-item">
                    <div class="q-text-row">
                        <div class="q-number"><?php echo $q_counter; ?>.</div>
                        <div class="q-body">
                            <?php echo htmlspecialchars((string)$q['question_text']); ?>

                            <?php if ($q_type === 'true_false'): ?>
                                <span style="margin-left: 10px;">( <span class="tf-answer-blank"><?php if ($show_key && !empty($q['correct_answer'])) echo htmlspecialchars((string)$q['correct_answer']); ?></span> )</span>
                            <?php elseif ($q_type === 'blank'): ?>
                                <?php if ($show_key && !empty($q['correct_answer'])): ?>
                                    <span class="key-indicator">መልስ፦ <?php echo htmlspecialchars((string)$q['correct_answer']); ?></span>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                        <div class="q-mark-badge">(<?php echo $q_marks; ?> ነጥብ)</div>
                    </div>

                    <?php if ($q_type === 'choice' && !empty($opts)): ?>
                        <div class="choice-grid">
                            <?php foreach ($opts as $letter => $val): ?>
                                <?php if (trim((string)$val) === '') continue; ?>
                                <div class="choice-option">
                                    <span class="choice-letter"><?php echo htmlspecialchars((string)$letter); ?>.</span>
                                    <span><?php echo htmlspecialchars((string)$val); ?></span>
                                    <?php if ($show_key && ($q['correct_answer'] ?? '') === $letter): ?>
                                        <span class="key-indicator">✓ ትክክለኛ</span>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php elseif ($q_type === 'matching' && !empty($opts)): ?>
                        <table class="matching-table">
                            <?php foreach ($opts as $pair): ?>
                                <tr>
                                    <td class="matching-left">
                                        <?php echo htmlspecialchars((string)($pair['num'] ?? '')); ?>. <?php echo htmlspecialchars((string)($pair['left'] ?? '')); ?>
                                    </td>
                                    <td class="matching-mid">
                                        ( <?php echo ($show_key ? '<strong>' . htmlspecialchars((string)($pair['right'] ?? '')) . '</strong>' : '______'); ?> )
                                    </td>
                                    <td class="matching-right">
                                        <?php echo htmlspecialchars((string)($pair['right'] ?? '')); ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </table>
                    <?php elseif ($q_type === 'workout'): ?>
                        <?php 
                            $lines_count = max(2, min(15, intval($q['blank_lines'] ?? 3)));
                        ?>
                        <div class="workout-lines">
                            <?php for ($l = 0; $l < $lines_count; $l++): ?>
                                <div class="workout-line"></div>
                            <?php endfor; ?>
                        </div>
                        <?php if ($show_key && !empty($q['correct_answer'])): ?>
                            <div style="margin-left: 28px; margin-top: 4px;">
                                <span class="key-indicator">የመምህሩ የመልስ ቁልፍ፦ <?php echo htmlspecialchars((string)$q['correct_answer']); ?></span>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>

                <?php $q_counter++; ?>
            <?php endforeach; ?>
        </main>

        <footer class="exam-footer-note">
            <span>«እግዚአብሔር አምላክ ማስተዋሉን ያድላችሁ!»</span>
        </footer>

    </div>

</body>
</html>
