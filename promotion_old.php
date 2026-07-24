<?php
session_start();
require_once 'db.php';
requireAdmin();

$message = '';
$error = '';

// Helper function for grade status
if (!function_exists('getGradeStatus')) {
    function getGradeStatus($total) {
        if($total >= 90) return 'እጅግ በጣም ጥሩ';
        if($total >= 80) return 'በጣም ጥሩ';
        if($total >= 70) return 'ጥሩ';
        if($total >= 60) return 'አጥጋቢ';
        if($total >= 50) return 'ደካማ';
        return 'ውድቅ';
    }
}

// DEBUG: Check what classes are in database
$debug_classes = "SELECT * FROM classes ORDER BY id";
$debug_result = mysqli_query($conn, $debug_classes);
$all_classes = [];
while($row = mysqli_fetch_assoc($debug_result)) {
    $all_classes[] = $row;
}

// If you have duplicates, this will show them
$duplicate_check = "SELECT name, COUNT(*) as count FROM classes GROUP BY name HAVING COUNT(*) > 1";
$duplicate_result = mysqli_query($conn, $duplicate_check);
$duplicates = [];
while($row = mysqli_fetch_assoc($duplicate_result)) {
    $duplicates[] = $row;
}

// Get all semesters for history dropdown
$all_semesters_query = "SELECT * FROM semesters ORDER BY ethiopian_year DESC, semester_number DESC";
$all_semesters = mysqli_query($conn, $all_semesters_query);

// Get selected semester from URL
$selected_semester_id = isset($_GET['semester_id']) ? (int)$_GET['semester_id'] : 0;

// If no semester selected, get the most recent one
if($selected_semester_id == 0) {
    $latest_semester_query = "SELECT * FROM semesters ORDER BY id DESC LIMIT 1";
    $latest_semester_result = mysqli_query($conn, $latest_semester_query);
    $latest_semester = mysqli_fetch_assoc($latest_semester_result);
    $selected_semester_id = $latest_semester ? $latest_semester['id'] : 0;
}

// Get selected semester details
$semester_info_query = "SELECT * FROM semesters WHERE id = $selected_semester_id";
$semester_info_result = mysqli_query($conn, $semester_info_query);
$current_semester = mysqli_fetch_assoc($semester_info_result);

// Get selected class filter
$selected_class = isset($_GET['class_id']) ? (int)$_GET['class_id'] : 0;

// FIXED: Get unique classes by using GROUP BY instead of DISTINCT
$classes_list_query = "SELECT id, name FROM classes GROUP BY name ORDER BY id";
$classes_list = mysqli_query($conn, $classes_list_query);

// Also check which classes have students
$classes_with_students_query = "SELECT DISTINCT c.id, c.name 
                               FROM classes c 
                               JOIN students s ON c.id = s.class_id 
                               ORDER BY c.id";
$classes_with_students = mysqli_query($conn, $classes_with_students_query);

// Build the main query to get ALL data
$main_query = "SELECT 
               c.id as class_id,
               c.name as class_name,
               s.id as student_id,
               s.name as student_name,
               s.parent_phone,
               u.id as teacher_id,
               u.name as teacher_name,
               COALESCE(m.total, 0) as total
               FROM students s
               JOIN classes c ON s.class_id = c.id
               LEFT JOIN teacher_class tc ON c.id = tc.class_id AND tc.semester_id = $selected_semester_id
               LEFT JOIN users u ON tc.teacher_id = u.id
               LEFT JOIN marks m ON s.id = m.student_id 
                   AND m.teacher_id = u.id 
                   AND m.semester_id = $selected_semester_id";

if($selected_class > 0) {
    $main_query .= " WHERE c.id = $selected_class";
}

$main_query .= " ORDER BY c.id, s.name, u.name";

$results = mysqli_query($conn, $main_query);

// Process data into structured format
$class_data = [];
$all_teachers = [];

while($row = mysqli_fetch_assoc($results)) {
    $class_id = $row['class_id'];
    $student_id = $row['student_id'];
    $teacher_id = $row['teacher_id'];
    
    // Initialize class array
    if(!isset($class_data[$class_id])) {
        $class_data[$class_id] = [
            'class_name' => $row['class_name'],
            'students' => [],
            'teachers' => []
        ];
    }
    
    // Store teacher for this class
    if($teacher_id && !isset($class_data[$class_id]['teachers'][$teacher_id])) {
        $class_data[$class_id]['teachers'][$teacher_id] = $row['teacher_name'];
        $all_teachers[$teacher_id] = $row['teacher_name'];
    }
    
    // Initialize student array
    if($student_id && !isset($class_data[$class_id]['students'][$student_id])) {
        $class_data[$class_id]['students'][$student_id] = [
            'name' => $row['student_name'],
            'phone' => $row['parent_phone'],
            'teachers' => [],
            'total_score' => 0
        ];
    }
    
    // Store teacher mark
    if($student_id && $teacher_id) {
        $class_data[$class_id]['students'][$student_id]['teachers'][$teacher_id] = $row['total'];
        // Add to total score
        $class_data[$class_id]['students'][$student_id]['total_score'] += $row['total'];
    }
}

// Calculate averages and rankings for each class
foreach($class_data as $class_id => &$class) {
    $students_with_avg = [];
    $teacher_count = count($class['teachers']); // Get number of teachers in this class
    
    foreach($class['students'] as $student_id => &$student) {
        // FIXED: AVERAGE = TOTAL SCORE ÷ NUMBER OF TEACHERS
        $student['average'] = $teacher_count > 0 
            ? round($student['total_score'] / $teacher_count, 1) 
            : 0;
        
        $student['grade'] = getGradeStatus($student['average']);
        
        // Store for ranking
        $students_with_avg[] = [
            'id' => $student_id,
            'avg' => $student['average']
        ];
    }
    
    // Sort by average for ranking (highest first)
    usort($students_with_avg, function($a, $b) {
        if($b['avg'] == $a['avg']) return 0;
        return ($b['avg'] > $a['avg']) ? 1 : -1;
    });
    
    // Assign ranks starting from 1
    $rank = 1;
    foreach($students_with_avg as $index => $ranked) {
        $class['students'][$ranked['id']]['rank'] = $rank;
        
        // Assign icons for top 3
        if($rank == 1) {
            $class['students'][$ranked['id']]['rank_icon'] = '🥇';
        } elseif($rank == 2) {
            $class['students'][$ranked['id']]['rank_icon'] = '🥈';
        } elseif($rank == 3) {
            $class['students'][$ranked['id']]['rank_icon'] = '🥉';
        } else {
            $class['students'][$ranked['id']]['rank_icon'] = '';
        }
        
        $rank++;
    }
    
    // Calculate class average (average of all student averages)
    $class_total_avg = 0;
    $class_count = count($class['students']);
    foreach($class['students'] as $student) {
        $class_total_avg += $student['average'];
    }
    $class['class_average'] = $class_count > 0 ? round($class_total_avg / $class_count, 1) : 0;
    
    // Calculate per-teacher averages
    foreach($class['teachers'] as $teacher_id => $teacher_name) {
        $teacher_total = 0;
        $teacher_count = 0;
        foreach($class['students'] as $student) {
            if(isset($student['teachers'][$teacher_id])) {
                $teacher_total += $student['teachers'][$teacher_id];
                $teacher_count++;
            }
        }
        $class['teacher_averages'][$teacher_id] = $teacher_count > 0 ? round($teacher_total / $teacher_count, 1) : 0;
    }
}

// Handle Excel Export
if(isset($_GET['export']) && $_GET['export'] == 'excel') {
    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment; filename="student_ranking_' . date('Y-m-d') . '.xls"');
    
    echo '<html><head><meta charset="UTF-8"></head><body>';
    echo '<table border="1">';
    echo '<tr><th colspan="' . (5 + count($all_teachers)) . '" style="background:#8B4513; color:#FFD700; font-size:16px;">';
    echo 'አጸደ ትጉሃን ሰንበት ትምህርት ቤት - የተማሪ ደረጃ ዝርዝር</th></tr>';
    echo '<tr><th colspan="' . (5 + count($all_teachers)) . '">';
    echo ($current_semester ? htmlspecialchars($current_semester['name']) : 'ሴሚስተር') . '</th></tr>';
    
    foreach($class_data as $class_id => $class) {
        // Sort students by rank
        $students = $class['students'];
        uasort($students, function($a, $b) {
            return $a['rank'] <=> $b['rank'];
        });
        
        echo '<tr><th colspan="' . (5 + count($class['teachers'])) . '" style="background:#FEF3C7;">';
        echo '📚 ' . $class['class_name'] . '</th></tr>';
        
        // Headers
        echo '<tr>';
        echo '<th>ደረጃ</th>';
        echo '<th>ተማሪ</th>';
        echo '<th>ስልክ</th>';
        foreach($class['teachers'] as $tid => $tname) {
            echo '<th style="background:#A52A2A; color:white;">' . htmlspecialchars($tname) . '</th>';
        }
        echo '<th>ጠቅላላ ውጤት</th>';
        echo '<th>አማካይ</th>';
        echo '<th>ደረጃ</th>';
        echo '</tr>';
        
        // Student rows
        foreach($students as $student_id => $student) {
            echo '<tr>';
            echo '<td>' . $student['rank'] . ' ' . $student['rank_icon'] . '</td>';
            echo '<td>' . htmlspecialchars($student['name']) . '</td>';
            echo '<td>' . ($student['phone'] ?: '-') . '</td>';
            
            foreach($class['teachers'] as $tid => $tname) {
                if(isset($student['teachers'][$tid]) && $student['teachers'][$tid] > 0) {
                    echo '<td>' . number_format($student['teachers'][$tid], 1) . '</td>';
                } else {
                    echo '<td>-</td>';
                }
            }
            
            echo '<td><strong>' . number_format($student['total_score'], 1) . '</strong></td>';
            echo '<td><strong>' . number_format($student['average'], 1) . '</strong></td>';
            echo '<td>' . $student['grade'] . '</td>';
            echo '</tr>';
        }
        
        // Class average row
        echo '<tr style="background:#FFF8DC; font-weight:bold;">';
        echo '<td colspan="3">📊 የክፍል አማካይ</td>';
        
        foreach($class['teachers'] as $tid => $tname) {
            echo '<td>' . number_format($class['teacher_averages'][$tid], 1) . '</td>';
        }
        
        $class_total_sum = 0;
        foreach($class['students'] as $student) {
            $class_total_sum += $student['total_score'];
        }
        $class_total_avg = count($class['students']) > 0 ? round($class_total_sum / count($class['students']), 1) : 0;
        
        echo '<td>' . number_format($class_total_avg, 1) . '</td>';
        echo '<td><strong>' . number_format($class['class_average'], 1) . '</strong></td>';
        echo '<td></td>';
        echo '</tr>';
    }
    
    echo '</table></body></html>';
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/png" href="images/icon.png">
    <title>የተማሪ ደረጃ ሰንጠረዥ | አጸደ ትጉሃን</title>
    <style>
        :root {
            --brown-dark: #8B4513;
            --brown-medium: #A52A2A;
            --gold-primary: #FFD700;
            --gold-dark: #DAA520;
            --gold-pale: #FFF8DC;
            --success-green: #10B981;
            --error-red: #EF4444;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', 'Nyala', sans-serif;
        }

        body {
            background: #f8f5f0;
            padding: 20px;
        }

        .header {
            background: linear-gradient(135deg, #8B4513, #A52A2A);
            color: white;
            padding: 20px 30px;
            border-radius: 15px 15px 0 0;
            border-bottom: 5px solid var(--gold-primary);
        }

        .header-content {
            max-width: 1400px;
            margin: 0 auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 20px;
        }

        .logo-area {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .logo-icon {
            width: 70px;
            height: 70px;
            background: linear-gradient(135deg, var(--gold-primary), var(--gold-dark));
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 36px;
            color: var(--brown-dark);
            border: 4px solid white;
        }

        .title h1 {
            font-size: 24px;
            color: var(--gold-primary);
        }

        .title p {
            font-size: 14px;
            color: #fff8dc;
        }

        .nav {
            background: white;
            padding: 15px 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            border-bottom: 3px solid var(--gold-primary);
        }

        .nav-links {
            max-width: 1400px;
            margin: 0 auto;
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
        }

        .nav-link {
            padding: 10px 20px;
            color: var(--brown-dark);
            text-decoration: none;
            border-radius: 30px;
            font-weight: 600;
            background: #f8f5f0;
        }

        .nav-link:hover {
            background: var(--gold-pale);
        }

        .nav-link.active {
            background: linear-gradient(135deg, var(--gold-primary), var(--gold-dark));
            color: var(--brown-dark);
        }

        .container {
            max-width: 1400px;
            margin: 30px auto;
            padding: 0 20px;
        }

        .debug-section {
            background: #f0f0f0;
            border: 2px solid #ff0000;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 30px;
            font-family: monospace;
            font-size: 14px;
        }

        .debug-section h3 {
            color: #8B4513;
            margin-bottom: 15px;
        }

        .debug-section pre {
            background: white;
            padding: 15px;
            border-radius: 5px;
            overflow-x: auto;
        }

        .filter-section {
            background: white;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 30px;
            display: flex;
            gap: 20px;
            flex-wrap: wrap;
            align-items: flex-end;
            border: 2px solid var(--gold-pale);
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
        }

        .filter-group {
            flex: 1;
            min-width: 250px;
        }

        .filter-group label {
            display: block;
            margin-bottom: 8px;
            color: var(--brown-dark);
            font-weight: 600;
        }

        .filter-group select {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #E2E8F0;
            border-radius: 10px;
            font-size: 14px;
            background: white;
        }

        .filter-group select:focus {
            outline: none;
            border-color: var(--gold-primary);
        }

        .btn {
            padding: 12px 25px;
            border: none;
            border-radius: 10px;
            cursor: pointer;
            font-weight: 600;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s;
            font-size: 14px;
        }

        .btn-primary {
            background: linear-gradient(135deg, #FFD700, #DAA520);
            color: #8B4513;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(218,165,32,0.3);
        }

        .btn-excel {
            background: linear-gradient(135deg, #10B981, #059669);
            color: white;
        }

        .btn-excel:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(16,185,129,0.3);
        }

        .btn-danger {
            background: linear-gradient(135deg, #EF4444, #DC2626);
            color: white;
        }

        .semester-info {
            background: linear-gradient(135deg, var(--gold-pale), white);
            border-radius: 15px;
            padding: 15px 25px;
            margin-bottom: 30px;
            border-left: 6px solid var(--brown-dark);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
        }

        .semester-badge {
            background: var(--brown-dark);
            color: var(--gold-primary);
            padding: 8px 20px;
            border-radius: 30px;
            font-weight: bold;
        }

        .class-card {
            background: white;
            border-radius: 20px;
            margin-bottom: 40px;
            overflow: hidden;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            border: 2px solid var(--gold-pale);
        }

        .class-header {
            background: linear-gradient(135deg, var(--brown-dark), var(--brown-medium));
            color: var(--gold-primary);
            padding: 20px;
            font-size: 22px;
            font-weight: bold;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
        }

        .class-stats {
            font-size: 16px;
            background: rgba(255,255,255,0.2);
            padding: 8px 20px;
            border-radius: 30px;
        }

        .table-wrapper {
            overflow-x: auto;
            padding: 20px;
        }

        .ranking-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
            min-width: 900px;
        }

        .ranking-table th {
            background: var(--gold-pale);
            color: var(--brown-dark);
            padding: 12px 8px;
            text-align: center;
            border: 1px solid var(--gold-primary);
            white-space: nowrap;
        }

        .ranking-table td {
            padding: 10px 8px;
            border: 1px solid #E2E8F0;
            text-align: center;
        }

        .ranking-table tr:hover {
            background: #FFF8DC;
        }

        .teacher-header {
            background: #A52A2A !important;
            color: white !important;
        }

        .student-name {
            font-weight: 600;
            color: var(--brown-dark);
            text-align: left !important;
            padding-left: 15px !important;
        }

        .rank-1 {
            background: #FFD700;
            font-weight: bold;
            border-radius: 20px;
            padding: 3px 10px;
            display: inline-block;
        }

        .rank-2 {
            background: #C0C0C0;
            font-weight: bold;
            border-radius: 20px;
            padding: 3px 10px;
            display: inline-block;
        }

        .rank-3 {
            background: #CD7F32;
            color: white;
            font-weight: bold;
            border-radius: 20px;
            padding: 3px 10px;
            display: inline-block;
        }

        .rank-number {
            font-weight: bold;
            display: inline-block;
            padding: 3px 8px;
        }

        .total-score {
            font-weight: bold;
            color: var(--brown-dark);
            background: var(--gold-pale);
            padding: 3px 8px;
            border-radius: 15px;
        }

        .average-score {
            font-weight: bold;
            color: var(--success-green);
            font-size: 14px;
            padding: 3px 8px;
            border-radius: 15px;
            background: #D1FAE5;
        }

        .average-row {
            background: #FEF3C7;
            font-weight: bold;
        }

        .average-row td {
            background: #FEF3C7;
            border-top: 2px solid var(--gold-primary);
        }

        .grade-badge {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
        }

        .grade-excellent {
            background: #D1FAE5;
            color: #065F46;
        }

        .grade-good {
            background: #DBEAFE;
            color: #1E40AF;
        }

        .grade-satisfactory {
            background: #FEF3C7;
            color: #92400E;
        }

        .grade-poor {
            background: #FEE2E2;
            color: #991B1B;
        }

        .no-data {
            text-align: center;
            padding: 60px;
            color: #999;
            background: white;
            border-radius: 20px;
            border: 2px dashed var(--gold-primary);
        }

        .fix-buttons {
            display: flex;
            gap: 10px;
            margin-top: 15px;
        }

        @media print {
            .header, .nav, .filter-section, .btn, .debug-section, .fix-buttons {
                display: none !important;
            }
            
            .class-card {
                break-inside: avoid;
                border: 1px solid #000;
            }
            
            .ranking-table th {
                background: #ccc !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="header-content">
            <div class="logo-area">
                <div class="logo-icon">⛪</div>
                <div class="title">
                    <h1>አጸደ ተጉሃን ሰንበት ትምህርት ቤት</h1>
                    <p>የተማሪ ደረጃ ሰንጠረዥ | Student Ranking System</p>
                </div>
            </div>
            <a href="dashboard_admin.php" class="btn btn-primary">← ዳሽቦርድ</a>
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
            <a href="promotion.php" class="nav-link active">📈 ደረጃ ሰንጠረዥ</a>
            <a href="print_results.php" class="nav-link">🖨️ ማተሚያ</a>
        </div>
    </div>

    <div class="container">
        <!-- DEBUG SECTION - Shows what's in your database -->
        <div class="debug-section">
            <h3>🔍 የውሂብ ጎታ መፈተሻ (Database Debug)</h3>
            
            <h4>ሁሉም ክፍሎች (All Classes):</h4>
            <pre>
<?php 
foreach($all_classes as $c) {
    echo "ID: " . $c['id'] . " - Name: " . $c['name'] . "\n";
}
?>
            </pre>

            <?php if(!empty($duplicates)): ?>
            <h4 style="color: red;">⚠️ የተደጋገሙ ክፍሎች (Duplicate Classes):</h4>
            <pre style="color: red;">
<?php
foreach($duplicates as $d) {
    echo "ክፍል: " . $d['name'] . " - ተደጋግሞ " . $d['count'] . " ጊዜ\n";
}
?>
            </pre>
            <?php endif; ?>

            <h4>ተማሪዎች ያሏቸው ክፍሎች (Classes with Students):</h4>
            <pre>
<?php 
mysqli_data_seek($classes_with_students, 0);
while($c = mysqli_fetch_assoc($classes_with_students)) {
    echo "ID: " . $c['id'] . " - Name: " . $c['name'] . "\n";
}
?>
            </pre>

            <div class="fix-buttons">
                <form method="POST" action="manage_classes.php" style="display: inline;">
                    <button type="submit" class="btn btn-danger" onclick="return confirm('ወደ ክፍል ማኔጅመንት ይሂዱ እና የተደጋገሙ ክፍሎችን ያስወግዱ?')">
                        🛠️ ወደ ክፍል ማኔጅመንት ሂድ
                    </button>
                </form>
                
                <form method="POST" action="fix_classes.php" style="display: inline;" onsubmit="return confirm('ይህ የተደጋገሙ ክፍሎችን ያስተካክላል። እርግጠኛ ነዎት?')">
                    <button type="submit" class="btn btn-primary" name="fix_duplicates">
                        🔧 የተደጋገሙ ክፍሎችን አስተካክል
                    </button>
                </form>
            </div>
        </div>

        <!-- Filter Section -->
        <div class="filter-section">
            <div class="filter-group">
                <label>📅 ሴሚስተር ምረጥ</label>
                <select onchange="window.location.href='?semester_id='+this.value<?php echo $selected_class ? '+&class_id='.$selected_class : ''; ?>">
                    <option value="">ሴሚስተር ምረጥ</option>
                    <?php 
                    mysqli_data_seek($all_semesters, 0);
                    while($sem = mysqli_fetch_assoc($all_semesters)): 
                        $selected = $sem['id'] == $selected_semester_id ? 'selected' : '';
                        $status_text = $sem['status'] == 'active' ? ' (ንቁ)' : ' (ዝግ)';
                    ?>
                    <option value="<?php echo $sem['id']; ?>" <?php echo $selected; ?>>
                        <?php echo htmlspecialchars($sem['name'] . $status_text); ?>
                    </option>
                    <?php endwhile; ?>
                </select>
            </div>
            
            <div class="filter-group">
                <label>📚 ክፍል ምረጥ</label>
                <select onchange="window.location.href='?semester_id=<?php echo $selected_semester_id; ?>&class_id='+this.value">
                    <option value="0">ሁሉም ክፍሎች</option>
                    <?php 
                    mysqli_data_seek($classes_list, 0);
                    while($class = mysqli_fetch_assoc($classes_list)): 
                        $selected = ($selected_class == $class['id']) ? 'selected' : '';
                    ?>
                    <option value="<?php echo $class['id']; ?>" <?php echo $selected; ?>>
                        <?php echo htmlspecialchars($class['name']); ?>
                    </option>
                    <?php endwhile; ?>
                </select>
            </div>
            
            <div>
                <a href="?export=excel&semester_id=<?php echo $selected_semester_id; ?><?php echo $selected_class ? '&class_id='.$selected_class : ''; ?>" class="btn btn-excel">📊 Excel አውርድ</a>
                <button onclick="window.print()" class="btn btn-primary">🖨️ አትም</button>
            </div>
        </div>

        <!-- Semester Info -->
        <?php if($current_semester): ?>
        <div class="semester-info">
            <div>
                <strong>📅 <?php echo htmlspecialchars($current_semester['name']); ?></strong>
                <span style="margin-left: 15px; color: #666;">
                    <?php if($current_semester['end_date']): ?>
                    የተዘጋበት: <?php echo date('M d, Y', strtotime($current_semester['end_date'])); ?>
                    <?php endif; ?>
                </span>
            </div>
            <span class="semester-badge">
                <?php echo $current_semester['status'] == 'active' ? '✅ ክፍት' : '🔒 ዝግ'; ?>
            </span>
        </div>
        <?php endif; ?>

        <!-- Ranking Tables -->
        <?php if(!empty($class_data)): ?>
            <?php foreach($class_data as $class_id => $class): 
                // Sort students by rank
                $students = $class['students'];
                uasort($students, function($a, $b) {
                    return $a['rank'] <=> $b['rank'];
                });
            ?>
            <div class="class-card">
                <div class="class-header">
                    <span>📚 <?php echo htmlspecialchars($class['class_name']); ?></span>
                    <span class="class-stats">
                        👥 <?php echo count($students); ?> ተማሪዎች | 
                        👨‍🏫 <?php echo count($class['teachers']); ?> መምህራን |
                        📊 አማካይ: <?php echo number_format($class['class_average'], 1); ?>
                    </span>
                </div>
                
                <div class="table-wrapper">
                    <table class="ranking-table">
                        <thead>
                            <tr>
                                <th>ደረጃ</th>
                                <th>ተማሪ</th>
                                <th>ስልክ</th>
                                <?php foreach($class['teachers'] as $tid => $tname): ?>
                                <th class="teacher-header"><?php echo htmlspecialchars($tname); ?></th>
                                <?php endforeach; ?>
                                <th>ጠቅላላ ውጤት</th>
                                <th>አማካይ</th>
                                <th>ደረጃ</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($students as $student_id => $student): 
                                // Determine grade class
                                $grade_class = 'grade-satisfactory';
                                if($student['average'] >= 80) $grade_class = 'grade-excellent';
                                elseif($student['average'] >= 70) $grade_class = 'grade-good';
                                elseif($student['average'] >= 50) $grade_class = 'grade-satisfactory';
                                else $grade_class = 'grade-poor';
                            ?>
                            <tr>
                                <td>
                                    <?php if($student['rank'] == 1): ?>
                                    <span class="rank-1">🥇 1</span>
                                    <?php elseif($student['rank'] == 2): ?>
                                    <span class="rank-2">🥈 2</span>
                                    <?php elseif($student['rank'] == 3): ?>
                                    <span class="rank-3">🥉 3</span>
                                    <?php else: ?>
                                    <span class="rank-number">#<?php echo $student['rank']; ?></span>
                                    <?php endif; ?>
                                </td>
                                <td class="student-name"><?php echo htmlspecialchars($student['name']); ?></td>
                                <td><?php echo $student['phone'] ?: '-'; ?></td>
                                
                                <?php foreach($class['teachers'] as $tid => $tname): ?>
                                    <?php if(isset($student['teachers'][$tid]) && $student['teachers'][$tid] > 0): ?>
                                    <td><?php echo number_format($student['teachers'][$tid], 1); ?></td>
                                    <?php else: ?>
                                    <td style="color: #999;">-</td>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                                
                                <td class="total-score"><?php echo number_format($student['total_score'], 1); ?></td>
                                <td class="average-score"><?php echo number_format($student['average'], 1); ?></td>
                                <td><span class="grade-badge <?php echo $grade_class; ?>"><?php echo $student['grade']; ?></span></td>
                            </tr>
                            <?php endforeach; ?>

                            <!-- Class Average Row -->
                            <tr class="average-row">
                                <td colspan="3" style="text-align: left;">📊 የክፍል አማካይ</td>
                                
                                <?php foreach($class['teachers'] as $tid => $tname): ?>
                                    <td><?php echo number_format($class['teacher_averages'][$tid], 1); ?></td>
                                <?php endforeach; ?>
                                
                                <?php
                                $class_total_sum = 0;
                                foreach($class['students'] as $student) {
                                    $class_total_sum += $student['total_score'];
                                }
                                $class_total_avg = count($class['students']) > 0 ? round($class_total_sum / count($class['students']), 1) : 0;
                                ?>
                                <td><?php echo number_format($class_total_avg, 1); ?></td>
                                <td><strong><?php echo number_format($class['class_average'], 1); ?></strong></td>
                                <td></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="no-data">
                <span style="font-size: 48px;">📭</span>
                <h3>ለዚህ ሴሚስተር ምንም ውሂብ የለም</h3>
                <p>እባክዎ ሌላ ሴሚስተር ይምረጡ</p>
            </div>
        <?php endif; ?>
    </div>

    <script>
        // Print shortcut (Ctrl+P)
        document.addEventListener('keydown', function(e) {
            if(e.ctrlKey && e.key === 'p') {
                e.preventDefault();
                window.print();
            }
        });
    </script>
</body>
</html>
<?php mysqli_close($conn); ?>