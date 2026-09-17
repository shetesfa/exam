<?php
require_once 'db.php';
requireAdmin();

$message = '';
$error = '';

// ============================================
// GET CURRENT ACADEMIC YEAR & SEMESTER
// ============================================
$current_year = dbFetchOne($conn, "SELECT * FROM academic_years WHERE status = 'active' LIMIT 1");

if (!$current_year) {
    $error = "ምንም ንቁ የትምህርት ዘመን የለም! እባክዎ መጀመሪያ የትምህርት ዘመን ያስጀምሩ።";
    $current_ethiopian_year = 2018;
    $promotion_done = 0;
    $semester_id = 0;
} else {
    $current_ethiopian_year = intval($current_year['ethiopian_year']);
    $promotion_done = intval($current_year['promotion_done']);
    
    $current_semester = getCurrentSemester($conn);
    $semester_id = $current_semester ? intval($current_semester['id']) : 0;
}

// ============================================
// GET ALL CLASSES ORDERED BY GRADE LEVEL
// ============================================
$classes_list = dbFetchAll(
    $conn,
    "SELECT c.id, c.name, c.grade_id, g.level_number, g.division_id, d.code as div_code, d.name_am as div_name
     FROM classes c
     LEFT JOIN grades g ON c.grade_id = g.id
     LEFT JOIN divisions d ON g.division_id = d.id
     ORDER BY COALESCE(g.level_number, 999) ASC, c.id ASC"
);

// Map level_number to class_id
$level_to_class = [];
foreach ($classes_list as $c) {
    if (!empty($c['level_number']) && !isset($level_to_class[$c['level_number']])) {
        $level_to_class[$c['level_number']] = $c;
    }
}

// Build class progression map (current_class_id => next_class_id)
$class_progression = [];
foreach ($classes_list as $i => $current_class) {
    $lvl = !empty($current_class['level_number']) ? (int)$current_class['level_number'] : null;
    $next_class = null;
    
    if ($lvl !== null && $lvl < 12) {
        $next_lvl = $lvl + 1;
        $next_class = $level_to_class[$next_lvl] ?? null;
    }
    
    // Fallback if no grade level mapped
    if (!$next_class && $lvl === null && isset($classes_list[$i + 1])) {
        $next_class = $classes_list[$i + 1];
    }
    
    if ($next_class) {
        $class_progression[$current_class['id']] = [
            'current_name' => $current_class['name'],
            'next_id' => $next_class['id'],
            'next_name' => $next_class['name'],
            'division' => $current_class['div_name'] ?? 'ያልተመደበ'
        ];
    } else {
        $class_progression[$current_class['id']] = [
            'current_name' => $current_class['name'],
            'next_id' => null,
            'next_name' => ($lvl !== null && $lvl >= 12) ? 'ምሩቅ' : 'የመጨረሻ ክፍል (ዝውትር)',
            'division' => $current_class['div_name'] ?? 'ያልተመደበ'
        ];
    }
}

// ============================================
// GET STUDENT STATISTICS (Current Snapshot)
// ============================================
$preview_data = [];
$total_students = 0;
$students_with_marks = 0;

if (!$error && $semester_id > 0) {
    // Get all active students with their average marks
    $student_avg_query = "SELECT 
                            s.id AS student_id,
                            s.name AS student_name,
                            s.class_id,
                            c.name AS class_name,
                            COUNT(DISTINCT m.teacher_id) AS teacher_count,
                            COUNT(m.id) AS marks_count,
                            COALESCE(AVG(m.total), 0) AS average_mark
                          FROM students s
                          JOIN classes c ON s.class_id = c.id
                          LEFT JOIN teacher_class tc ON c.id = tc.class_id AND tc.semester_id = ?
                          LEFT JOIN marks m ON s.id = m.student_id 
                              AND m.teacher_id = tc.teacher_id 
                              AND m.semester_id = ?
                          WHERE (s.is_deleted = 0 OR s.is_deleted IS NULL)
                          GROUP BY s.id, s.name, s.class_id, c.name
                          ORDER BY c.id, s.name";

    $preview_data = dbFetchAll($conn, $student_avg_query, "ii", [$semester_id, $semester_id]);
    $total_students = count($preview_data);
    foreach ($preview_data as $row) {
        if ($row['marks_count'] > 0) {
            $students_with_marks++;
        }
    }
}

// ============================================
// HANDLE PROMOTION EXECUTION
// ============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['execute_promotion'])) {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = "የደህንነት ማረጋገጫ አልተሳካም! እባክዎ እንደገና ይሞክሩ።";
    } elseif ($promotion_done) {
        $error = "⚠️ ደረጃ ማሳደግ ቀድሞውኑ ተከናውኗል! ለዚህ የትምህርት ዘመን እንደገና ማሳደግ አይቻልም።";
    } elseif ($semester_id == 0) {
        $error = "⚠️ ምንም ንቁ ሴሚስተር የለም።";
    } elseif (empty($preview_data)) {
        $error = "⚠️ ምንም ተማሪዎች አልተገኙም።";
    } else {
        $pass_mark = floatval($_POST['pass_mark'] ?? 50);
        
        if ($pass_mark < 0 || $pass_mark > 100) {
            $error = "⚠️ እባክዎ ትክክለኛ ማለፊያ ውጤት ያስገቡ (0-100)";
        } else {
            // START TRANSACTION
            mysqli_begin_transaction($conn);
            
            try {
                $promoted_count = 0;
                $repeated_count = 0;
                $last_class_count = 0;
                $no_marks_count = 0;
                $error_count = 0;
                
                foreach ($preview_data as $student) {
                    $student_id = intval($student['student_id']);
                    $current_class_id = intval($student['class_id']);
                    $average = floatval($student['average_mark']);
                    $has_marks = ($student['marks_count'] > 0);
                    
                    // Check if this class has a next class
                    $progression = $class_progression[$current_class_id] ?? null;
                    
                    if (!$progression) {
                        $error_count++;
                        continue;
                    }
                    
                    $next_class_id = $progression['next_id'];
                    $is_last_class = ($next_class_id === null);
                    
                    // Determine promotion status
                    if ($is_last_class) {
                        $status = 'promoted';
                        $to_class_id = $current_class_id;
                        $last_class_count++;
                    } elseif (!$has_marks) {
                        $status = 'repeated';
                        $to_class_id = $current_class_id;
                        $no_marks_count++;
                    } elseif ($average >= $pass_mark) {
                        $status = 'promoted';
                        $to_class_id = intval($next_class_id);
                        $promoted_count++;
                    } else {
                        $status = 'repeated';
                        $to_class_id = $current_class_id;
                        $repeated_count++;
                    }
                    
                    // Update student record
                    $update_success = dbExecute(
                        $conn,
                        "UPDATE students SET 
                            class_id = ?,
                            promotion_status = ?,
                            academic_year = ?
                         WHERE id = ?",
                        "isii",
                        [$to_class_id, $status, $current_ethiopian_year, $student_id]
                    );
                    
                    if (!$update_success) {
                        throw new Exception("Failed to update student ID: $student_id");
                    }
                    
                    // Insert promotion history
                    $history_success = dbExecute(
                        $conn,
                        "INSERT INTO promotion_history 
                            (student_id, from_class_id, to_class_id, from_academic_year, 
                             to_academic_year, total_marks, status)
                         VALUES 
                            (?, ?, ?, ?, ?, ?, ?)",
                        "iiiiids",
                        [
                            $student_id,
                            $current_class_id,
                            $to_class_id,
                            $current_ethiopian_year,
                            $current_ethiopian_year + 1,
                            $average,
                            $status
                        ]
                    );
                    
                    if (!$history_success) {
                        throw new Exception("Failed to insert promotion history for student ID: $student_id");
                    }
                }
                
                // Mark academic year as promotion done
                dbExecute($conn, "UPDATE academic_years SET promotion_done = 1 WHERE id = ?", "i", [intval($current_year['id'])]);
                
                // COMMIT TRANSACTION
                mysqli_commit($conn);
                
                // Refresh page
                header("Location: promotion.php?promoted=1");
                exit();
                
            } catch (Exception $e) {
                mysqli_rollback($conn);
                $error = "ደረጃ ማሳደግ አልተሳካም! " . $e->getMessage();
            }
        }
    }
}

// Check for success redirect
if (isset($_GET['promoted']) && $_GET['promoted'] == 1) {
    $message = "✅ ደረጃ ማሳደግ በተሳካ ሁኔታ ተጠናቋል! ሁሉም ተማሪዎች ተሸጋግረዋል።";
}

// ============================================
// GET PROMOTION HISTORY FOR DISPLAY
// ============================================
$history_data = [];
$history_query = "SELECT ph.*, 
                    s.name AS student_name,
                    fc.name AS from_class_name,
                    tc.name AS to_class_name
                  FROM promotion_history ph
                  JOIN students s ON ph.student_id = s.id
                  JOIN classes fc ON ph.from_class_id = fc.id
                  JOIN classes tc ON ph.to_class_id = tc.id
                  WHERE ph.from_academic_year = ?
                  ORDER BY ph.promoted_at DESC
                  LIMIT 50";
$history_data = dbFetchAll($conn, $history_query, "i", [$current_ethiopian_year]);
$nav_active = 'promotion';
?>
<!DOCTYPE html>
<html lang="am">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ደረጃ ማሳደግ | አጸደ ትጉሃን</title>
    <?php include 'pwa_head.php'; ?>
    <style>
        :root {
            --brown-dark: #8B4513;
            --brown-medium: #A52A2A;
            --gold-primary: #FFD700;
            --gold-dark: #DAA520;
            --gold-pale: #FFF8DC;
            --bg-light: #FAF9F6;
            --card-bg: #FFFFFF;
            --text-main: #1F2937;
            --text-muted: #6B7280;
            --border-color: #E5E7EB;
            --success-green: #10B981;
            --error-red: #EF4444;
            --warning-amber: #F59E0B;
            --info-blue: #3B82F6;
        }

        [data-theme="dark"] {
            --bg-light: #111827;
            --card-bg: #1F2937;
            --text-main: #F9FAFB;
            --text-muted: #9CA3AF;
            --border-color: #374151;
            --gold-pale: rgba(218, 165, 32, 0.15);
        }

        body {
            background: var(--bg-light);
            color: var(--text-main);
            font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
            margin: 0;
            padding: 0;
            min-height: 100vh;
        }

        .main-container {
            max-width: 1300px;
            margin: 25px auto;
            padding: 0 20px 50px;
        }

        /* Page Hero Header */
        .page-hero {
            background: var(--card-bg);
            border-radius: 18px;
            padding: 24px 28px;
            border: 1px solid var(--border-color);
            box-shadow: 0 4px 15px rgba(139, 69, 19, 0.05);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 16px;
            margin-bottom: 25px;
            position: relative;
            overflow: hidden;
        }

        .page-hero::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--brown-dark), var(--gold-primary), var(--brown-dark));
        }

        .hero-left {
            display: flex;
            align-items: center;
            gap: 16px;
        }

        .hero-icon {
            width: 52px;
            height: 52px;
            border-radius: 14px;
            background: linear-gradient(135deg, rgba(139,69,19,0.12), rgba(218,165,32,0.22));
            color: var(--brown-dark);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 26px;
            border: 1px solid rgba(218,165,32,0.3);
            flex-shrink: 0;
        }

        [data-theme="dark"] .hero-icon {
            color: var(--gold-primary);
        }

        .hero-title {
            font-size: 22px;
            font-weight: 800;
            margin: 0 0 4px;
            color: var(--text-main);
        }

        .hero-subtitle {
            font-size: 13.5px;
            color: var(--text-muted);
            margin: 0;
        }

        /* Message banners */
        .msg-box {
            padding: 14px 18px;
            border-radius: 12px;
            margin-bottom: 22px;
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 14px;
            font-weight: 600;
            animation: fadeIn 0.3s ease;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-5px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .msg-success {
            background: rgba(16, 185, 129, 0.1);
            color: #059669;
            border: 1px solid rgba(16, 185, 129, 0.3);
        }

        .msg-error {
            background: rgba(239, 68, 68, 0.1);
            color: #DC2626;
            border: 1px solid rgba(239, 68, 68, 0.25);
        }

        .lock-card {
            background: rgba(245, 158, 11, 0.1);
            border: 1.5px solid rgba(245, 158, 11, 0.3);
            border-radius: 16px;
            padding: 22px 24px;
            display: flex;
            align-items: center;
            gap: 18px;
            margin-bottom: 25px;
        }

        .lock-card .icon {
            font-size: 38px;
            flex-shrink: 0;
        }

        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 16px;
            margin-bottom: 25px;
        }

        .stat-card {
            background: var(--card-bg);
            border-radius: 14px;
            padding: 18px 20px;
            border: 1px solid var(--border-color);
            display: flex;
            align-items: center;
            gap: 16px;
            box-shadow: 0 2px 6px rgba(0,0,0,0.02);
        }

        .stat-icon {
            width: 44px;
            height: 44px;
            border-radius: 12px;
            background: var(--gold-pale);
            color: var(--brown-dark);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            flex-shrink: 0;
        }

        [data-theme="dark"] .stat-icon {
            color: var(--gold-primary);
        }

        .stat-num {
            font-size: 22px;
            font-weight: 800;
            color: var(--text-main);
            line-height: 1.2;
        }

        .stat-label {
            font-size: 12.5px;
            color: var(--text-muted);
            margin-top: 3px;
        }

        /* Church Card */
        .church-card {
            background: var(--card-bg);
            border-radius: 16px;
            padding: 24px;
            border: 1px solid var(--border-color);
            margin-bottom: 25px;
            box-shadow: 0 3px 12px rgba(0,0,0,0.03);
        }

        .card-header-clean {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 14px;
            border-bottom: 1px solid var(--border-color);
            flex-wrap: wrap;
            gap: 12px;
        }

        .card-heading {
            font-size: 18px;
            font-weight: 800;
            color: var(--brown-dark);
            display: flex;
            align-items: center;
            gap: 10px;
            margin: 0;
        }

        [data-theme="dark"] .card-heading {
            color: var(--gold-primary);
        }

        /* Promotion Execution Box */
        .promotion-box {
            background: rgba(255, 215, 0, 0.05);
            border: 1.5px dashed var(--gold-dark);
            border-radius: 16px;
            padding: 24px;
            margin-bottom: 20px;
        }

        .promo-controls {
            display: flex;
            align-items: flex-end;
            gap: 20px;
            flex-wrap: wrap;
        }

        .passmark-input-wrap {
            flex: 1;
            min-width: 240px;
        }

        .input-label {
            display: block;
            margin-bottom: 8px;
            font-size: 13.5px;
            font-weight: 700;
            color: var(--text-main);
        }

        .passmark-input {
            width: 140px;
            padding: 12px 16px;
            border: 2px solid var(--border-color);
            border-radius: 12px;
            font-size: 20px;
            font-weight: 800;
            text-align: center;
            background: var(--card-bg);
            color: var(--text-main);
            outline: none;
            transition: all 0.2s ease;
        }

        .passmark-input:focus {
            border-color: var(--gold-dark);
            box-shadow: 0 0 0 3px rgba(218, 165, 32, 0.2);
        }

        .presets-row {
            display: flex;
            gap: 8px;
            margin-top: 10px;
            flex-wrap: wrap;
        }

        .preset-chip {
            padding: 6px 14px;
            border-radius: 20px;
            background: var(--card-bg);
            border: 1.5px solid var(--border-color);
            color: var(--text-main);
            font-size: 12.5px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .preset-chip:hover {
            border-color: var(--gold-dark);
        }

        .preset-chip.active {
            background: var(--gold-primary);
            color: #8B4513;
            border-color: var(--brown-dark);
        }

        .btn-promote {
            padding: 14px 28px;
            background: linear-gradient(135deg, #10B981, #059669);
            color: #FFFFFF;
            border: none;
            border-radius: 12px;
            font-size: 16px;
            font-weight: 800;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            box-shadow: 0 3px 12px rgba(16, 185, 129, 0.3);
            transition: all 0.2s ease;
        }

        .btn-promote:hover {
            transform: translateY(-2px);
            filter: brightness(1.08);
            box-shadow: 0 5px 18px rgba(16, 185, 129, 0.4);
        }

        /* Rules summary banner */
        .rules-guide {
            background: rgba(59, 130, 246, 0.06);
            border-left: 4px solid var(--info-blue);
            border-radius: 8px;
            padding: 14px 18px;
            font-size: 13.5px;
            color: var(--text-main);
            margin-top: 16px;
        }

        .rules-list {
            margin: 8px 0 0 16px;
            padding: 0;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 6px;
        }

        /* Tables */
        .table-responsive {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
            border-radius: 12px;
            border: 1px solid var(--border-color);
        }

        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
        }

        th {
            background: rgba(139, 69, 19, 0.05);
            color: var(--brown-dark);
            padding: 12px 14px;
            text-align: left;
            border-bottom: 1px solid var(--border-color);
            font-weight: 700;
            white-space: nowrap;
        }

        [data-theme="dark"] th {
            background: rgba(218, 165, 32, 0.1);
            color: var(--gold-primary);
        }

        td {
            padding: 10px 14px;
            border-bottom: 1px solid var(--border-color);
            color: var(--text-main);
        }

        tr:last-child td {
            border-bottom: none;
        }

        tr:hover td {
            background: rgba(255, 215, 0, 0.03);
        }

        /* Badges */
        .badge {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 11.5px;
            font-weight: 700;
        }

        .badge-promoted { background: rgba(16, 185, 129, 0.12); color: #059669; border: 1px solid rgba(16, 185, 129, 0.3); }
        .badge-repeated { background: rgba(239, 68, 68, 0.1); color: #DC2626; border: 1px solid rgba(239, 68, 68, 0.25); }
        .badge-last { background: rgba(59, 130, 246, 0.1); color: #2563EB; border: 1px solid rgba(59, 130, 246, 0.25); }
        .badge-nomarks { background: rgba(107, 114, 128, 0.1); color: #6B7280; border: 1px solid rgba(107, 114, 128, 0.25); }

        /* Simulation live counters */
        .sim-summary {
            display: flex;
            gap: 14px;
            flex-wrap: wrap;
            margin-bottom: 15px;
            padding: 12px 16px;
            background: var(--card-bg);
            border-radius: 12px;
            border: 1px solid var(--border-color);
        }

        .sim-item {
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 13px;
            font-weight: 700;
        }

        @media (max-width: 768px) {
            .main-container { padding: 0 12px 30px; margin: 15px auto; }
            .page-hero { padding: 18px; }
            .hero-title { font-size: 19px; }
            .church-card { padding: 16px 12px; }
            .promo-controls { flex-direction: column; align-items: stretch; }
            .passmark-input { width: 100%; }
            .btn-promote { width: 100%; justify-content: center; }
            .stats-grid { grid-template-columns: repeat(2, 1fr); gap: 10px; }
            th, td { padding: 8px 10px; font-size: 12px; }
        }
    </style>
</head>
<body>
    <?php include 'mobile_nav.php'; ?>

    <div class="main-container">
        <!-- Hero Header -->
        <div class="page-hero">
            <div class="hero-left">
                <div class="hero-icon">🚀</div>
                <div>
                    <h1 class="hero-title">የተማሪዎች ደረጃ ማሳደግ (Student Promotion)</h1>
                    <p class="hero-subtitle">የትምህርት ዘመን ማብቂያ የተማሪዎችን ውጤት መዝኖ ወደ ቀጣይ ክፍል ማሸጋገሪያ</p>
                </div>
            </div>
            <div>
                <a href="semester.php" class="preset-chip" style="text-decoration: none; padding: 8px 16px;">
                    <span>📅</span> የሴሚስተር አስተዳደር
                </a>
            </div>
        </div>

        <!-- Messages -->
        <?php if ($message): ?>
        <div class="msg-box msg-success"><?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
        <div class="msg-box msg-error"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
        <?php endif; ?>

        <?php if (!$current_year): ?>
        <!-- No active academic year -->
        <div class="church-card" style="text-align: center; padding: 50px 20px;">
            <span style="font-size: 55px; display: block; margin-bottom: 12px;">📅</span>
            <h2 style="color: var(--brown-dark); margin: 0 0 8px;">ምንም ንቁ የትምህርት ዘመን የለም</h2>
            <p style="color: var(--text-muted); font-size: 14px;">ደረጃ ከማሳደግዎ በፊት እባክዎ መጀመሪያ ንቁ የትምህርት ዘመን ያስጀምሩ።</p>
            <a href="semester.php" class="btn-promote" style="text-decoration: none; margin-top: 15px;">
                📅 ወደ ሴሚስተር አስተዳደር ሂድ
            </a>
        </div>
        <?php elseif ($promotion_done): ?>
        <!-- Promotion already done -->
        <div class="lock-card">
            <span class="icon">🔒</span>
            <div>
                <strong style="color: var(--text-main); font-size: 17px;">ለዚህ የትምህርት ዘመን ደረጃ ማሳደግ ቀድሞውኑ ተከናውኗል!</strong>
                <p style="color: var(--text-muted); margin: 5px 0 0; font-size: 13.5px;">
                    ለ <?php echo $current_ethiopian_year; ?> ዓ.ም የትምህርት ዘመን ደረጃ ማሳደግ ተጠናቋል። ለሚቀጥለው ዓመት አዲስ የትምህርት ዘመን ሲጀመር እንደገና ማሳደግ ይቻላል።
                </p>
            </div>
        </div>
        <?php else: ?>

        <!-- Statistics Grid -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon">👥</div>
                <div>
                    <div class="stat-num"><?php echo $total_students; ?></div>
                    <div class="stat-label">ጠቅላላ ተማሪዎች</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">📝</div>
                <div>
                    <div class="stat-num"><?php echo $students_with_marks; ?></div>
                    <div class="stat-label">ውጤት ያላቸው ተማሪዎች</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">🏫</div>
                <div>
                    <div class="stat-num"><?php echo count($classes_list); ?></div>
                    <div class="stat-label">የክፍሎች ብዛት</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">📅</div>
                <div>
                    <div class="stat-num" style="color: var(--success-green);"><?php echo $current_ethiopian_year; ?></div>
                    <div class="stat-label">ንቁ የትምህርት ዘመን (ዓ.ም)</div>
                </div>
            </div>
        </div>

        <!-- Class Progression Map -->
        <div class="church-card">
            <div class="card-header-clean">
                <h2 class="card-heading">
                    <span>🗺️</span> የክፍል ሽግግር ካርታ (Progression Map)
                </h2>
                <span style="font-size: 12.5px; color: var(--text-muted);">ክፍሎች ደረጃቸውን ጠብቀው የሚሸጋገሩበት ቅደም ተከተል</span>
            </div>
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th style="width: 45%;">አሁን ያለው ክፍል</th>
                            <th style="text-align: center; width: 10%;">ሽግግር</th>
                            <th style="width: 45%;">ቀጣይ ክፍል</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($class_progression as $current_id => $prog): ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($prog['current_name']); ?></strong> <span style="font-size: 11px; color: var(--text-muted);">(<?php echo htmlspecialchars($prog['division']); ?>)</span></td>
                            <td style="text-align: center; color: var(--gold-dark); font-weight: 800;">➔</td>
                            <td>
                                <?php if ($prog['next_id']): ?>
                                    <span style="color: var(--success-green); font-weight: 700;">
                                        <?php echo htmlspecialchars($prog['next_name']); ?>
                                    </span>
                                <?php else: ?>
                                    <span class="badge badge-last">🎓 <?php echo htmlspecialchars($prog['next_name']); ?></span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Promotion Wizard & Action Form -->
        <div class="church-card">
            <div class="card-header-clean">
                <h2 class="card-heading">
                    <span>🎯</span> የተማሪዎች ደረጃ ማሳደጊያ ማስፈጸሚያ
                </h2>
            </div>

            <div class="promotion-box">
                <form method="POST" onsubmit="return confirmPromotion()">
                    <?php echo csrfField(); ?>
                    <div class="promo-controls">
                        <div class="passmark-input-wrap">
                            <label class="input-label">📊 ማለፊያ ውጤት (Pass Mark %)</label>
                            <input type="number" name="pass_mark" id="pass_mark" class="passmark-input" 
                                   value="50" min="0" max="100" step="1" required>
                            <div class="presets-row">
                                <button type="button" class="preset-chip" onclick="setPassMark(40)">40%</button>
                                <button type="button" class="preset-chip active" onclick="setPassMark(50)">50% (መደበኛ)</button>
                                <button type="button" class="preset-chip" onclick="setPassMark(60)">60%</button>
                                <button type="button" class="preset-chip" onclick="setPassMark(70)">70%</button>
                                <button type="button" class="preset-chip" onclick="setPassMark(80)">80%</button>
                            </div>
                        </div>
                        <div>
                            <button type="submit" name="execute_promotion" class="btn-promote" id="promoteBtn">
                                <span>🚀</span>
                                <span>የተማሪዎችን ደረጃ አሁን አሳድግ</span>
                            </button>
                        </div>
                    </div>
                </form>
            </div>

            <div class="rules-guide">
                <strong>📋 የተማሪዎች ሽግግር መመዘኛ ደንቦች፦</strong>
                <ul class="rules-list">
                    <li>አማካይ ≥ ማለፊያ ውጤት → <span class="badge badge-promoted">ያልፋል</span></li>
                    <li>አማካይ < ማለፊያ ውጤት → <span class="badge badge-repeated">ይደግማል</span></li>
                    <li>ምንም ውጤት የሌለው → <span class="badge badge-nomarks">ይደግማል</span></li>
                    <li>የመጨረሻ ክፍል → <span class="badge badge-last">ተመርቋል</span></li>
                </ul>
            </div>
        </div>

        <!-- Live Simulation Preview Table -->
        <div class="church-card">
            <div class="card-header-clean">
                <h2 class="card-heading">
                    <span>👁️</span> የተማሪዎች ቅድመ እይታ (Simulation Preview)
                </h2>
                <div style="font-size: 13px; font-weight: 700; color: var(--gold-dark);">
                    የተመረጠ ማለፊያ ውጤት፦ <span id="previewPassMark">50</span>%
                </div>
            </div>

            <!-- Dynamic live counters -->
            <div class="sim-summary">
                <div class="sim-item" style="color: #059669;">
                    <span>✅ የሚያልፉ፦</span>
                    <span id="simPassCount">0</span>
                </div>
                <div class="sim-item" style="color: #DC2626;">
                    <span>❌ የሚደግሙ፦</span>
                    <span id="simFailCount">0</span>
                </div>
                <div class="sim-item" style="color: #2563EB;">
                    <span>🎓 ተመራቂዎች፦</span>
                    <span id="simGradCount">0</span>
                </div>
                <div class="sim-item" style="color: #6B7280;">
                    <span>⚠️ ውጤት የሌላቸው፦</span>
                    <span id="simNoMarksCount">0</span>
                </div>
            </div>
            
            <div class="table-responsive">
                <table id="previewTable">
                    <thead>
                        <tr>
                            <th style="width: 50px;">#</th>
                            <th>የተማሪ ስም</th>
                            <th>አሁን ያለበት ክፍል</th>
                            <th style="text-align: right;">አማካይ ውጤት</th>
                            <th style="text-align: center;">የመምህራን ብዛት</th>
                            <th style="text-align: center;">ውጤት አለ?</th>
                            <th>ቀጣይ ክፍል</th>
                            <th style="text-align: center;">ውሳኔ (ውጤት)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($preview_data as $index => $student): 
                            $avg = floatval($student['average_mark']);
                            $has_marks = ($student['marks_count'] > 0);
                            $prog = $class_progression[$student['class_id']] ?? null;
                            $is_last = $prog && $prog['next_id'] === null;
                            
                            $pass_preview = 50;
                            if ($is_last) {
                                $decision = 'ተመርቋል 🎓';
                                $badge_class = 'badge-last';
                            } elseif (!$has_marks) {
                                $decision = 'ይደግማል ⚠️';
                                $badge_class = 'badge-nomarks';
                            } elseif ($avg >= $pass_preview) {
                                $decision = 'ያልፋል ✅';
                                $badge_class = 'badge-promoted';
                            } else {
                                $decision = 'ይደግማል ❌';
                                $badge_class = 'badge-repeated';
                            }
                        ?>
                        <tr class="student-row" 
                            data-avg="<?php echo $avg; ?>" 
                            data-hasmarks="<?php echo $has_marks ? '1' : '0'; ?>" 
                            data-islast="<?php echo $is_last ? '1' : '0'; ?>">
                            <td><?php echo $index + 1; ?></td>
                            <td><strong><?php echo htmlspecialchars($student['student_name']); ?></strong></td>
                            <td><?php echo htmlspecialchars($student['class_name']); ?></td>
                            <td style="text-align: right;"><strong><?php echo number_format($avg, 1); ?></strong></td>
                            <td style="text-align: center;"><?php echo $student['teacher_count']; ?></td>
                            <td style="text-align: center;"><?php echo $has_marks ? '✅' : '❌'; ?></td>
                            <td><?php echo $prog ? htmlspecialchars($prog['next_name']) : '?'; ?></td>
                            <td style="text-align: center;"><span class="badge <?php echo $badge_class; ?>"><?php echo $decision; ?></span></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <?php endif; ?>

        <!-- Promotion History Table -->
        <?php if (!empty($history_data)): ?>
        <div class="church-card">
            <div class="card-header-clean">
                <h2 class="card-heading">
                    <span>📜</span> የቅርብ ጊዜ የደረጃ ማሳደግ ታሪክ (የመጨረሻዎቹ 50)
                </h2>
            </div>
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>የተማሪ ስም</th>
                            <th>ከነበረበት ክፍል</th>
                            <th>ወደ ተሸጋገረበት ክፍል</th>
                            <th style="text-align: right;">አማካይ ውጤት</th>
                            <th style="text-align: center;">ሁኔታ</th>
                            <th>የተከናወነበት ቀን</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($history_data as $hist): ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($hist['student_name']); ?></strong></td>
                            <td><?php echo htmlspecialchars($hist['from_class_name']); ?></td>
                            <td><?php echo htmlspecialchars($hist['to_class_name']); ?></td>
                            <td style="text-align: right;"><strong><?php echo number_format($hist['total_marks'], 1); ?></strong></td>
                            <td style="text-align: center;">
                                <span class="badge <?php echo $hist['status'] == 'promoted' ? 'badge-promoted' : 'badge-repeated'; ?>">
                                    <?php echo $hist['status'] == 'promoted' ? 'ያለፈ' : 'የደገመ'; ?>
                                </span>
                            </td>
                            <td><?php echo date('M d, Y h:i A', strtotime($hist['promoted_at'])); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <script>
        function setPassMark(value) {
            document.getElementById('pass_mark').value = value;
            document.getElementById('previewPassMark').textContent = value;
            
            document.querySelectorAll('.preset-chip').forEach(btn => btn.classList.remove('active'));
            if (window.event && window.event.target) {
                window.event.target.classList.add('active');
            }
            
            updatePreview(value);
        }
        
        function updatePreview(passMark) {
            const rows = document.querySelectorAll('.student-row');
            let passCount = 0;
            let failCount = 0;
            let gradCount = 0;
            let noMarksCount = 0;

            rows.forEach(row => {
                const avg = parseFloat(row.dataset.avg);
                const hasMarks = row.dataset.hasmarks === '1';
                const isLast = row.dataset.islast === '1';
                const decisionCell = row.querySelector('td:last-child');
                
                let decision, badgeClass;
                if (isLast) {
                    decision = 'ተመርቋል 🎓';
                    badgeClass = 'badge-last';
                    gradCount++;
                } else if (!hasMarks) {
                    decision = 'ይደግማል ⚠️';
                    badgeClass = 'badge-nomarks';
                    noMarksCount++;
                    failCount++;
                } else if (avg >= passMark) {
                    decision = 'ያልፋል ✅';
                    badgeClass = 'badge-promoted';
                    passCount++;
                } else {
                    decision = 'ይደግማል ❌';
                    badgeClass = 'badge-repeated';
                    failCount++;
                }
                
                decisionCell.innerHTML = '<span class="badge ' + badgeClass + '">' + decision + '</span>';
            });

            // Update live simulation counters
            const passEl = document.getElementById('simPassCount');
            const failEl = document.getElementById('simFailCount');
            const gradEl = document.getElementById('simGradCount');
            const noMarksEl = document.getElementById('simNoMarksCount');

            if (passEl) passEl.textContent = passCount;
            if (failEl) failEl.textContent = failCount;
            if (gradEl) gradEl.textContent = gradCount;
            if (noMarksEl) noMarksEl.textContent = noMarksCount;
        }
        
        function confirmPromotion() {
            const passMark = document.getElementById('pass_mark').value;
            return confirm('⚠️ ጥብቅ ማስጠንቀቂያ!\n\n' +
                'ይህ እርምጃ ሁሉንም ተማሪዎች በ ' + passMark + '% ማለፊያ ውጤት መሰረት ደረጃ ያሳድጋል!\n\n' +
                'ይህ ከተፈጸመ በኋላ ወደ ኋላ መመለስ አይቻልም!\n\n' +
                'እርግጠኛ ነዎት? ለመቀጠል እሺ (OK) ይበሉ።');
        }
        
        document.addEventListener('DOMContentLoaded', function() {
            const passInput = document.getElementById('pass_mark');
            if (passInput) {
                passInput.addEventListener('input', function() {
                    const val = parseFloat(this.value) || 0;
                    document.getElementById('previewPassMark').textContent = val;
                    updatePreview(val);
                    
                    document.querySelectorAll('.preset-chip').forEach(btn => btn.classList.remove('active'));
                    const matchingPreset = Array.from(document.querySelectorAll('.preset-chip')).find(b => b.textContent.includes(val + '%'));
                    if (matchingPreset) matchingPreset.classList.add('active');
                });
                
                // Initial calculation
                updatePreview(parseFloat(passInput.value) || 50);
            }
        });
    </script>
</body>
</html>
<?php mysqli_close($conn); ?>