<?php
session_start();
require_once 'db.php';
requireAdmin();

$message = '';
$error = '';

// ============================================
// GET CURRENT ACADEMIC YEAR & SEMESTER
// ============================================
$current_year_query = "SELECT * FROM academic_years WHERE status = 'active' LIMIT 1";
$current_year_result = mysqli_query($conn, $current_year_query);
$current_year = mysqli_fetch_assoc($current_year_result);

if (!$current_year) {
    $error = "ምንም ንቁ የትምህርት ዘመን የለም! እባክዎ መጀመሪያ የትምህርት ዘመን ያስጀምሩ።";
} else {
    $current_ethiopian_year = $current_year['ethiopian_year'];
    $promotion_done = $current_year['promotion_done'];
    
    $current_semester = getCurrentSemester($conn);
    $semester_id = $current_semester ? $current_semester['id'] : 0;
}

// ============================================
// GET ALL CLASSES ORDERED BY GRADE LEVEL
// ============================================
$classes_list = [];
$classes_query = "SELECT id, name FROM classes ORDER BY id ASC";
$classes_result = mysqli_query($conn, $classes_query);
while ($row = mysqli_fetch_assoc($classes_result)) {
    $classes_list[] = $row;
}

// Build class progression map (current_class_id => next_class_id)
$class_progression = [];
$class_count = count($classes_list);
for ($i = 0; $i < $class_count; $i++) {
    $current_class = $classes_list[$i];
    if ($i + 1 < $class_count) {
        $next_class = $classes_list[$i + 1];
        $class_progression[$current_class['id']] = [
            'current_name' => $current_class['name'],
            'next_id' => $next_class['id'],
            'next_name' => $next_class['name']
        ];
    } else {
        // Last class - no promotion possible
        $class_progression[$current_class['id']] = [
            'current_name' => $current_class['name'],
            'next_id' => null, // No next class
            'next_name' => 'የመጨረሻ ክፍል (ዝውትር)'
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
    // Get all students with their average marks
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
                          LEFT JOIN teacher_class tc ON c.id = tc.class_id AND tc.semester_id = $semester_id
                          LEFT JOIN marks m ON s.id = m.student_id 
                              AND m.teacher_id = tc.teacher_id 
                              AND m.semester_id = $semester_id
                          GROUP BY s.id, s.name, s.class_id, c.name
                          ORDER BY c.name, s.name";

    $student_avg_result = mysqli_query($conn, $student_avg_query);
    
    if ($student_avg_result) {
        while ($row = mysqli_fetch_assoc($student_avg_result)) {
            $preview_data[] = $row;
            $total_students++;
            if ($row['marks_count'] > 0) {
                $students_with_marks++;
            }
        }
    }
}

// ============================================
// HANDLE PROMOTION EXECUTION
// ============================================
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['execute_promotion'])) {
    
    // Security checks
    if ($promotion_done) {
        $error = "⚠️ ደረጃ ማሳደግ ቀድሞውኑ ተከናውኗል! ለዚህ የትምህርት ዘመን እንደገና ማሳደግ አይቻልም።";
    } elseif ($semester_id == 0) {
        $error = "⚠️ ምንም ንቁ ሴሚስተር የለም።";
    } elseif (empty($preview_data)) {
        $error = "⚠️ ምንም ተማሪዎች አልተገኙም።";
    } else {
        
        $pass_mark = floatval($_POST['pass_mark']);
        
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
                    $student_id = $student['student_id'];
                    $current_class_id = $student['class_id'];
                    $student_name = $student['student_name'];
                    $average = $student['average_mark'];
                    $has_marks = ($student['marks_count'] > 0);
                    
                    // Check if this class has a next class
                    $progression = $class_progression[$current_class_id] ?? null;
                    
                    if (!$progression) {
                        // Class not found in progression map - skip
                        $error_count++;
                        continue;
                    }
                    
                    $next_class_id = $progression['next_id'];
                    $is_last_class = ($next_class_id === null);
                    
                    // Determine promotion status
                    if ($is_last_class) {
                        // Student is in the final class - cannot promote further
                        $status = 'promoted'; // Mark as completed
                        $to_class_id = $current_class_id; // Stay in same class
                        $last_class_count++;
                    } elseif (!$has_marks) {
                        // No marks - student repeats
                        $status = 'repeated';
                        $to_class_id = $current_class_id;
                        $no_marks_count++;
                    } elseif ($average >= $pass_mark) {
                        // Student passed - promote to next class
                        $status = 'promoted';
                        $to_class_id = $next_class_id;
                        $promoted_count++;
                    } else {
                        // Student failed - repeat same class
                        $status = 'repeated';
                        $to_class_id = $current_class_id;
                        $repeated_count++;
                    }
                    
                    // Update student record
                    $update_query = "UPDATE students SET 
                                        class_id = $to_class_id,
                                        promotion_status = '$status',
                                        current_grade = (SELECT CAST(SUBSTRING_INDEX(name, ' ', 1) AS UNSIGNED) FROM classes WHERE id = $to_class_id),
                                        academic_year = $current_ethiopian_year
                                     WHERE id = $student_id";
                    
                    if (!mysqli_query($conn, $update_query)) {
                        throw new Exception("Failed to update student ID: $student_id");
                    }
                    
                    // Insert promotion history
                    $history_query = "INSERT INTO promotion_history 
                                        (student_id, from_class_id, to_class_id, from_academic_year, 
                                         to_academic_year, total_marks, status)
                                      VALUES 
                                        ($student_id, $current_class_id, $to_class_id, 
                                         $current_ethiopian_year, $current_ethiopian_year + 1, 
                                         $average, '$status')";
                    
                    if (!mysqli_query($conn, $history_query)) {
                        throw new Exception("Failed to insert promotion history for student ID: $student_id");
                    }
                }
                
                // Mark academic year as promotion done
                $update_year_query = "UPDATE academic_years SET promotion_done = 1 WHERE id = {$current_year['id']}";
                if (!mysqli_query($conn, $update_year_query)) {
                    throw new Exception("Failed to update academic year status");
                }
                
                // COMMIT TRANSACTION
                mysqli_commit($conn);
                
                // Success message
                $message = "✅ ደረጃ ማሳደግ በተሳካ ሁኔታ ተጠናቋል!<br>";
                $message .= "📊 ያለፉ (Promoted): <strong>$promoted_count</strong> | ";
                $message .= "🔄 የደገሙ (Repeated): <strong>$repeated_count</strong> | ";
                $message .= "📭 ውጤት የሌላቸው (No Marks): <strong>$no_marks_count</strong>";
                if ($last_class_count > 0) {
                    $message .= " | 🎓 የመጨረሻ ክፍል: <strong>$last_class_count</strong>";
                }
                if ($error_count > 0) {
                    $message .= "<br>⚠️ <strong>$error_count</strong> ተማሪዎች ላይ ችግር ነበር (የክፍል መረጃ አልተገኘም)";
                }
                
                // Refresh the page to show updated data
                header("Location: promotion.php?promoted=1");
                exit();
                
            } catch (Exception $e) {
                // ROLLBACK ON ERROR
                mysqli_rollback($conn);
                $error = "❌ ስህተት ተከስቷል! ሁሉም ለውጦች ተመልሰዋል።<br>ስህተት: " . $e->getMessage();
            }
        }
    }
}

// Check for success redirect
if (isset($_GET['promoted']) && $_GET['promoted'] == 1) {
    $message = "✅ ደረጃ ማሳደግ በተሳካ ሁኔታ ተጠናቋል! ሁሉም ተማሪዎች ተሻሽለዋል።";
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
                  WHERE ph.from_academic_year = $current_ethiopian_year
                  ORDER BY ph.promoted_at DESC
                  LIMIT 50";
$history_result = mysqli_query($conn, $history_query);
if ($history_result) {
    while ($row = mysqli_fetch_assoc($history_result)) {
        $history_data[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/png" href="images/icon.png">
    <title>ደረጃ ማሳደግ | Student Promotion</title>
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
            --warning-yellow: #F59E0B;
            --info-blue: #3B82F6;
            --purple: #8B5CF6;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Segoe UI', 'Nyala', sans-serif; }
        body { background: var(--bg-cream); }

        .header {
            background: linear-gradient(135deg, #8B4513 0%, #A52A2A 100%);
            color: white; padding: 20px 30px;
        }
        .header-content {
            max-width: 1400px; margin: 0 auto;
            display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px;
        }
        .logo-area { display: flex; align-items: center; gap: 15px; }
        .logo-icon {
            width: 55px; height: 55px; background: linear-gradient(135deg, var(--gold-primary), var(--gold-dark));
            border-radius: 50%; display: flex; align-items: center; justify-content: center;
            font-size: 28px; color: var(--brown-dark); border: 3px solid white;
        }
        .title h1 { font-size: 22px; color: var(--gold-primary); }
        .title p { font-size: 14px; color: var(--gold-light); }

        .nav {
            background: white; padding: 15px 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1); position: sticky; top: 0; z-index: 100;
        }
        .nav-links { max-width: 1400px; margin: 0 auto; display: flex; gap: 10px; flex-wrap: wrap; }
        .nav-link {
            padding: 10px 18px; color: var(--brown-dark); text-decoration: none;
            border-radius: 8px; font-weight: 600; font-size: 13px;
        }
        .nav-link:hover, .nav-link.active { background: var(--gold-pale); color: var(--gold-dark); }

        .container { max-width: 1400px; margin: 30px auto; padding: 0 30px; }

        .message-box {
            padding: 15px 20px; border-radius: 12px; margin-bottom: 20px;
            display: flex; align-items: center; gap: 10px; font-size: 14px;
        }
        .success { background: #D1FAE5; color: #065F46; border: 2px solid var(--success-green); }
        .error { background: #FEE2E2; color: #991B1B; border: 2px solid var(--error-red); }
        .warning { background: #FEF3C7; color: #92400E; border: 2px solid var(--warning-yellow); }

        .card {
            background: white; border-radius: 20px; padding: 25px; margin-bottom: 25px;
            border: 2px solid var(--gold-primary); box-shadow: 0 5px 20px rgba(0,0,0,0.08);
        }
        .card-title {
            color: var(--brown-dark); font-size: 20px; margin-bottom: 20px;
            padding-bottom: 10px; border-bottom: 2px solid var(--gold-pale);
            display: flex; align-items: center; gap: 10px;
        }

        .stats-grid {
            display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 15px;
            margin-bottom: 25px;
        }
        .stat-card {
            background: linear-gradient(135deg, var(--gold-pale), white);
            border-radius: 15px; padding: 20px; text-align: center;
            border: 2px solid var(--gold-primary);
        }
        .stat-value { font-size: 32px; font-weight: bold; color: var(--brown-dark); }
        .stat-label { font-size: 12px; color: #666; margin-top: 5px; }

        .promotion-form {
            background: linear-gradient(135deg, #FEF3C7, #FFF8DC);
            border-radius: 15px; padding: 25px; border: 2px dashed var(--gold-primary);
            margin-bottom: 25px;
        }
        .form-row { display: flex; gap: 15px; align-items: flex-end; flex-wrap: wrap; }
        .form-group { flex: 1; min-width: 200px; }
        .form-group label { display: block; margin-bottom: 8px; color: var(--brown-dark); font-weight: 600; font-size: 14px; }
        .form-control {
            width: 100%; padding: 14px; border: 2px solid #E2E8F0; border-radius: 10px;
            font-size: 18px; text-align: center; font-weight: bold;
        }
        .form-control:focus { outline: none; border-color: var(--gold-primary); }

        .btn {
            padding: 14px 30px; border: none; border-radius: 10px; cursor: pointer;
            font-weight: 600; font-size: 16px; transition: all 0.3s; display: inline-flex; align-items: center; gap: 8px;
        }
        .btn-promote {
            background: linear-gradient(135deg, #10B981, #059669); color: white; font-size: 18px;
        }
        .btn-promote:hover { transform: translateY(-2px); box-shadow: 0 5px 20px rgba(16,185,129,0.3); }
        .btn-promote:disabled { background: #9CA3AF; cursor: not-allowed; transform: none; }
        .btn-back { background: var(--gold-primary); color: var(--brown-dark); }

        .pass-mark-preset {
            display: flex; gap: 8px; margin-top: 8px; flex-wrap: wrap;
        }
        .preset-btn {
            padding: 6px 16px; border: 2px solid var(--gold-dark); border-radius: 20px;
            background: white; cursor: pointer; font-weight: 600; font-size: 12px;
            transition: all 0.2s;
        }
        .preset-btn:hover, .preset-btn.active { background: var(--gold-primary); border-color: var(--brown-dark); }

        .preview-table { width: 100%; border-collapse: collapse; font-size: 13px; }
        .preview-table th { background: var(--brown-dark); color: white; padding: 10px; border: 1px solid var(--gold-dark); }
        .preview-table td { padding: 8px 10px; border-bottom: 1px solid #E5E7EB; text-align: center; }
        .preview-table tr:hover { background: #FFF8DC; }
        .student-name-cell { text-align: left; font-weight: 600; color: var(--brown-dark); }

        .badge {
            display: inline-block; padding: 4px 12px; border-radius: 20px; font-size: 11px; font-weight: 600;
        }
        .badge-promoted { background: #D1FAE5; color: #059669; }
        .badge-repeated { background: #FEE2E2; color: #DC2626; }
        .badge-last { background: #DBEAFE; color: #1D4ED8; }
        .badge-nomarks { background: #F3F4F6; color: #6B7280; }

        .lock-warning {
            display: flex; align-items: center; gap: 15px; padding: 20px;
            background: #FEF3C7; border: 2px solid var(--warning-yellow); border-radius: 12px;
            margin-bottom: 20px;
        }
        .lock-warning .icon { font-size: 40px; }

        .history-table { width: 100%; border-collapse: collapse; font-size: 13px; }
        .history-table th { background: var(--gold-pale); color: var(--brown-dark); padding: 10px; }
        .history-table td { padding: 8px; border-bottom: 1px solid #E5E7EB; }

        @media (max-width: 768px) {
            .form-row { flex-direction: column; }
            .preview-table { font-size: 11px; }
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="header-content">
            <div class="logo-area">
                <div class="logo-icon">📈</div>
                <div class="title">
                    <h1>አጸደ ትጉሃን ሰንበት ትምህርት ቤት</h1>
                    <p>ደረጃ ማሳደግ | Student Promotion System</p>
                </div>
            </div>
            <a href="dashboard_admin.php" class="btn btn-back">← ወደ ዳሽቦርድ</a>
        </div>
    </div>

    <div class="nav">
        <div class="nav-links">
            <a href="dashboard_admin.php" class="nav-link">🏠 ዳሽቦርድ</a>
            <a href="manage_classes.php" class="nav-link">📚 ክፍሎች</a>
            <a href="manage_students.php" class="nav-link">👥 ተማሪዎች</a>
            <a href="manage_teachers.php" class="nav-link">👨‍🏫 መምህራን</a>
            <a href="manage_assignments.php" class="nav-link">📋 ክፍል ምደባ</a>
            <a href="semester.php" class="nav-link">📅 ሴሚስተር</a>
            <a href="class_locks.php" class="nav-link">🔒 ክፍል መቆለፊያ</a>
            <a href="promotion.php" class="nav-link active">📈 ደረጃ ማሳደግ</a>
            <a href="attendance_controller.php" class="nav-link">📊 የመገኘት</a>
            <a href="print_results.php" class="nav-link">🖨️ ውጤት ማተሚያ</a>
        </div>
    </div>

    <div class="container">
        <!-- Messages -->
        <?php if ($message): ?>
        <div class="message-box success"><?php echo $message; ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
        <div class="message-box error"><?php echo $error; ?></div>
        <?php endif; ?>

        <?php if (!$current_year): ?>
        <!-- No active academic year -->
        <div class="card">
            <div style="text-align:center; padding:40px;">
                <span style="font-size:60px;">📅</span>
                <h2 style="color:var(--brown-dark); margin-top:15px;">ምንም ንቁ የትምህርት ዘመን የለም</h2>
                <p style="color:#666;">እባክዎ መጀመሪያ የትምህርት ዘመን ያስጀምሩ</p>
                <a href="semester.php" class="btn" style="background:var(--gold-primary); color:var(--brown-dark); margin-top:15px;">
                    📅 ወደ ሴሚስተር አስተዳደር
                </a>
            </div>
        </div>
        <?php elseif ($promotion_done): ?>
        <!-- Promotion already done -->
        <div class="lock-warning">
            <span class="icon">🔒</span>
            <div>
                <strong style="color:var(--brown-dark); font-size:18px;">ደረጃ ማሳደግ ቀድሞውኑ ተከናውኗል!</strong>
                <p style="color:#666; margin-top:5px;">
                    ለ <?php echo $current_ethiopian_year; ?> ዓ.ም የትምህርት ዘመን ደረጃ ማሳደግ ተጠናቋል። 
                    እንደገና ማሳደግ አይቻልም።
                </p>
            </div>
        </div>
        <?php else: ?>

        <!-- Statistics -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-value"><?php echo $total_students; ?></div>
                <div class="stat-label">ጠቅላላ ተማሪዎች</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?php echo $students_with_marks; ?></div>
                <div class="stat-label">ውጤት ያላቸው</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?php echo count($classes_list); ?></div>
                <div class="stat-label">ክፍሎች</div>
            </div>
            <div class="stat-card">
                <div class="stat-value" style="color:var(--success-green);"><?php echo $current_ethiopian_year; ?></div>
                <div class="stat-label">የትምህርት ዘመን</div>
            </div>
        </div>

        <!-- Class Progression Map -->
        <div class="card">
            <div class="card-title"><span>🗺️</span> የክፍል ሽግግር ካርታ (Class Progression Map)</div>
            <table class="preview-table">
                <thead>
                    <tr>
                        <th>አሁን ያለው ክፍል</th>
                        <th>➡️</th>
                        <th>ቀጣይ ክፍል</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($class_progression as $current_id => $prog): ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars($prog['current_name']); ?></strong></td>
                        <td>→</td>
                        <td>
                            <?php if ($prog['next_id']): ?>
                            <span style="color:var(--success-green); font-weight:600;">
                                <?php echo htmlspecialchars($prog['next_name']); ?>
                            </span>
                            <?php else: ?>
                            <span class="badge badge-last">🎓 የመጨረሻ ክፍል</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Promotion Action Card -->
        <div class="card">
            <div class="card-title"><span>🎯</span> ደረጃ ማሳደግ (Execute Promotion)</div>

            <div class="promotion-form">
                <form method="POST" onsubmit="return confirmPromotion()">
                    <div class="form-row">
                        <div class="form-group">
                            <label>📊 ማለፊያ ውጤት (Pass Mark %)</label>
                            <input type="number" name="pass_mark" id="pass_mark" class="form-control" 
                                   value="50" min="0" max="100" step="1" required>
                            <div class="pass-mark-preset">
                                <button type="button" class="preset-btn" onclick="setPassMark(40)">40%</button>
                                <button type="button" class="preset-btn active" onclick="setPassMark(50)">50%</button>
                                <button type="button" class="preset-btn" onclick="setPassMark(60)">60%</button>
                                <button type="button" class="preset-btn" onclick="setPassMark(70)">70%</button>
                                <button type="button" class="preset-btn" onclick="setPassMark(80)">80%</button>
                            </div>
                        </div>
                        <div style="display:flex; align-items:flex-end;">
                            <button type="submit" name="execute_promotion" class="btn btn-promote" id="promoteBtn">
                                🚀 ደረጃ አሳድግ / Promote Students
                            </button>
                        </div>
                    </div>
                </form>
            </div>

            <!-- Rules -->
            <div style="background:#EFF6FF; border-left:4px solid var(--info-blue); padding:15px; border-radius:8px; margin-top:15px;">
                <strong>📋 የማሳደግ ህጎች (Promotion Rules):</strong>
                <ul style="margin-top:8px; margin-left:20px; color:#1E40AF; font-size:13px; line-height:1.8;">
                    <li>አማካይ ≥ ማለፊያ ውጤት → <span class="badge badge-promoted">ወደ ቀጣይ ክፍል ያልፋል</span></li>
                    <li>አማካይ < ማለፊያ ውጤት → <span class="badge badge-repeated">ክፍል ይደግማል</span></li>
                    <li>ውጤት የሌላቸው → <span class="badge badge-nomarks">ክፍል ይደግማሉ</span></li>
                    <li>የመጨረሻ ክፍል → <span class="badge badge-last">ማለፍ አይችሉም (ተመርቀዋል)</span></li>
                </ul>
            </div>
        </div>

        <!-- Preview Table -->
        <div class="card">
            <div class="card-title">
                <span>👁️</span> የተማሪዎች ቅድመ እይታ (Preview - Pass Mark: <span id="previewPassMark">50</span>%)
            </div>
            
            <div style="overflow-x:auto;">
                <table class="preview-table" id="previewTable">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>ተማሪ</th>
                            <th>አሁን ያለው ክፍል</th>
                            <th>አማካይ</th>
                            <th>የመምህራን ብዛት</th>
                            <th>ውጤት አለ?</th>
                            <th>ቀጣይ ክፍል</th>
                            <th>ውሳኔ</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($preview_data as $index => $student): 
                            $avg = $student['average_mark'];
                            $has_marks = ($student['marks_count'] > 0);
                            $prog = $class_progression[$student['class_id']] ?? null;
                            $is_last = $prog && $prog['next_id'] === null;
                            
                            // Preview with default 50% pass mark
                            $pass_preview = 50;
                            if ($is_last) {
                                $decision = 'መጨረሻ';
                                $badge_class = 'badge-last';
                            } elseif (!$has_marks) {
                                $decision = 'ይደግማል';
                                $badge_class = 'badge-nomarks';
                            } elseif ($avg >= $pass_preview) {
                                $decision = 'ያልፋል ✅';
                                $badge_class = 'badge-promoted';
                            } else {
                                $decision = 'ይደግማል ❌';
                                $badge_class = 'badge-repeated';
                            }
                        ?>
                        <tr class="student-row" data-avg="<?php echo $avg; ?>" data-hasmarks="<?php echo $has_marks ? '1' : '0'; ?>" data-islast="<?php echo $is_last ? '1' : '0'; ?>">
                            <td><?php echo $index + 1; ?></td>
                            <td class="student-name-cell"><?php echo htmlspecialchars($student['student_name']); ?></td>
                            <td><?php echo htmlspecialchars($student['class_name']); ?></td>
                            <td><strong><?php echo number_format($avg, 1); ?></strong></td>
                            <td><?php echo $student['teacher_count']; ?></td>
                            <td><?php echo $has_marks ? '✅' : '❌'; ?></td>
                            <td><?php echo $prog ? htmlspecialchars($prog['next_name']) : '?'; ?></td>
                            <td><span class="badge <?php echo $badge_class; ?>"><?php echo $decision; ?></span></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <?php endif; ?>

        <!-- Promotion History -->
        <?php if (!empty($history_data)): ?>
        <div class="card">
            <div class="card-title"><span>📜</span> የደረጃ ማሳደግ ታሪክ (Last 50)</div>
            <div style="overflow-x:auto;">
                <table class="history-table">
                    <thead>
                        <tr>
                            <th>ተማሪ</th>
                            <th>ከ (From)</th>
                            <th>ወደ (To)</th>
                            <th>አማካይ</th>
                            <th>ሁኔታ</th>
                            <th>ቀን</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($history_data as $hist): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($hist['student_name']); ?></td>
                            <td><?php echo htmlspecialchars($hist['from_class_name']); ?></td>
                            <td><?php echo htmlspecialchars($hist['to_class_name']); ?></td>
                            <td><?php echo number_format($hist['total_marks'], 1); ?></td>
                            <td>
                                <span class="badge <?php echo $hist['status'] == 'promoted' ? 'badge-promoted' : 'badge-repeated'; ?>">
                                    <?php echo $hist['status'] == 'promoted' ? 'ያለፈ' : 'የደገመ'; ?>
                                </span>
                            </td>
                            <td><?php echo date('M d, Y', strtotime($hist['promoted_at'])); ?></td>
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
            
            // Update preset buttons
            document.querySelectorAll('.preset-btn').forEach(btn => btn.classList.remove('active'));
            event.target.classList.add('active');
            
            // Update preview table
            updatePreview(value);
        }
        
        function updatePreview(passMark) {
            const rows = document.querySelectorAll('.student-row');
            rows.forEach(row => {
                const avg = parseFloat(row.dataset.avg);
                const hasMarks = row.dataset.hasmarks === '1';
                const isLast = row.dataset.islast === '1';
                const decisionCell = row.querySelector('td:last-child');
                
                let decision, badgeClass;
                if (isLast) {
                    decision = 'መጨረሻ';
                    badgeClass = 'badge-last';
                } else if (!hasMarks) {
                    decision = 'ይደግማል';
                    badgeClass = 'badge-nomarks';
                } else if (avg >= passMark) {
                    decision = 'ያልፋል ✅';
                    badgeClass = 'badge-promoted';
                } else {
                    decision = 'ይደግማል ❌';
                    badgeClass = 'badge-repeated';
                }
                
                decisionCell.innerHTML = '<span class="badge ' + badgeClass + '">' + decision + '</span>';
            });
        }
        
        function confirmPromotion() {
            const passMark = document.getElementById('pass_mark').value;
            return confirm('⚠️ ማስጠንቀቂያ!\n\n' +
                'ይህ እርምጃ ሁሉንም ተማሪዎች በ' + passMark + '% ማለፊያ ውጤት ደረጃ ያሳድጋል!\n\n' +
                'ይህን አንዴ ካደረጉ በኋላ መመለስ አይቻልም!\n\n' +
                'እርግጠኛ ነዎት?');
        }
        
        // Initialize preset
        document.addEventListener('DOMContentLoaded', function() {
            const passInput = document.getElementById('pass_mark');
            passInput.addEventListener('input', function() {
                document.getElementById('previewPassMark').textContent = this.value;
                updatePreview(parseFloat(this.value) || 0);
                
                // Update preset active state
                document.querySelectorAll('.preset-btn').forEach(btn => btn.classList.remove('active'));
                const matchingPreset = document.querySelector('.preset-btn[onclick*="' + this.value + '"]');
                if (matchingPreset) matchingPreset.classList.add('active');
            });
        });
    </script>
</body>
</html>
<?php mysqli_close($conn); ?>