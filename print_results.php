<?php
require_once 'db.php';
requireAdmin();

$semester_id = isset($_GET['semester_id']) ? intval($_GET['semester_id']) : 0;
$class_id = isset($_GET['class_id']) ? intval($_GET['class_id']) : 0;
$student_id = isset($_GET['student_id']) ? intval($_GET['student_id']) : 0;
$student_search = isset($_GET['student_search']) ? trim($_GET['student_search']) : '';

// If no semester specified, get current active semester
if ($semester_id <= 0) {
    $current_semester = getCurrentSemester($conn);
    $semester_id = $current_semester ? intval($current_semester['id']) : 0;
} else {
    $current_semester = dbFetchOne($conn, "SELECT * FROM semesters WHERE id = ?", "i", [$semester_id]);
}

// Get all semesters for dropdown
$all_semesters = dbQuery($conn, "SELECT * FROM semesters ORDER BY id DESC");

// Get all classes for dropdown
$classes = dbQuery($conn, "SELECT * FROM classes ORDER BY name");

// Get students for selected class (with search filter)
$students = null;
if ($class_id > 0) {
    if (!empty($student_search)) {
        $searchParam = '%' . $student_search . '%';
        $students = dbQuery($conn, "SELECT id, name FROM students WHERE class_id = ? AND name LIKE ? AND (is_deleted = 0 OR is_deleted IS NULL) ORDER BY name", "is", [$class_id, $searchParam]);
    } else {
        $students = dbQuery($conn, "SELECT id, name FROM students WHERE class_id = ? AND (is_deleted = 0 OR is_deleted IS NULL) ORDER BY name", "i", [$class_id]);
    }
}

// Get results with teacher information
$results_query = "SELECT 
                  s.id as student_id,
                  s.name as student_name, 
                  s.parent_phone,
                  c.name as class_name, 
                  c.id as class_id,
                  u.name as teacher_name,
                  u.id as teacher_id,
                  m.assignment,
                  m.participation,
                  m.attendance,
                  m.mid, 
                  m.final, 
                  m.total
                  FROM students s
                  JOIN classes c ON s.class_id = c.id
                  JOIN teacher_class tc ON c.id = tc.class_id AND tc.semester_id = " . intval($semester_id) . "
                  JOIN users u ON tc.teacher_id = u.id
                  LEFT JOIN marks m ON s.id = m.student_id 
                      AND m.semester_id = " . intval($semester_id) . "
                      AND m.teacher_id = u.id
                  WHERE (s.is_deleted = 0 OR s.is_deleted IS NULL)";
                  
if ($class_id > 0) {
    $results_query .= " AND s.class_id = " . intval($class_id);
}
if ($student_id > 0) {
    $results_query .= " AND s.id = " . intval($student_id);
}
if (!empty($student_search)) {
    // Use addcslashes to neutralise LIKE wildcards in user input before escaping
    $escaped_search = mysqli_real_escape_string($conn, addcslashes($student_search, '%_\\'));
    $results_query .= " AND s.name LIKE '%$escaped_search%'";
}
$results_query .= " ORDER BY c.id, s.name, u.name";

$results = mysqli_query($conn, $results_query);

// Calculate average per student and rank them BY CLASS
$avg_query = "SELECT 
              s.id as student_id,
              s.name as student_name, 
              c.id as class_id,
              c.name as class_name,
              COUNT(DISTINCT u.id) as teacher_count,
              AVG(m.total) as avg_total,
              SUM(m.total) as total_marks,
              COUNT(m.id) as marks_count
              FROM students s
              JOIN classes c ON s.class_id = c.id
              JOIN teacher_class tc ON c.id = tc.class_id AND tc.semester_id = " . intval($semester_id) . "
              JOIN users u ON tc.teacher_id = u.id
              LEFT JOIN marks m ON s.id = m.student_id 
                  AND m.semester_id = " . intval($semester_id) . "
                  AND m.teacher_id = u.id
              WHERE (s.is_deleted = 0 OR s.is_deleted IS NULL)";

if ($class_id > 0) {
    $avg_query .= " AND s.class_id = " . intval($class_id);
}
if ($student_id > 0) {
    $avg_query .= " AND s.id = " . intval($student_id);
}
if (!empty($student_search)) {
    $escaped_search = mysqli_real_escape_string($conn, addcslashes($student_search, '%_\\'));
    $avg_query .= " AND s.name LIKE '%$escaped_search%'";
}

$avg_query .= " GROUP BY s.id, s.name, c.id, c.name
                HAVING marks_count > 0
                ORDER BY c.id, avg_total DESC";

$avg_results = mysqli_query($conn, $avg_query);

// Calculate statistics for the class
$stats_query = "SELECT 
                COUNT(DISTINCT s.id) as total_students,
                COUNT(DISTINCT u.id) as total_teachers
                FROM students s
                JOIN classes c ON s.class_id = c.id
                LEFT JOIN teacher_class tc ON c.id = tc.class_id AND tc.semester_id = " . intval($semester_id) . "
                LEFT JOIN users u ON tc.teacher_id = u.id
                WHERE (s.is_deleted = 0 OR s.is_deleted IS NULL)";
if ($class_id > 0) {
    $stats_query .= " AND s.class_id = " . intval($class_id);
}
$stats_result = mysqli_query($conn, $stats_query);
$stats = mysqli_fetch_assoc($stats_result);
$nav_active = 'print_results';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>ውጤት ማተሚያ | አጸደ ትጉሃን</title>
    <?php include 'pwa_head.php'; ?>
    <style>
        :root {
            --brown-dark: #8B4513;
            --brown-medium: #A52A2A;
            --gold-primary: #FFD700;
            --gold-dark: #DAA520;
            --gold-pale: #FFF8DC;
            --success-green: #10B981;
            --error-red: #EF4444;
            --warning-yellow: #F59E0B;
            --info-blue: #3B82F6;
            --light-gray: #F3F4F6;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', 'Nyala', 'Abyssinica SIL', sans-serif;
        }

        body {
            background: #FAF9F6;
            padding: 15px;
        }

        .print-container {
            max-width: 1200px;
            margin: 10px auto;
        }

        /* Header */
        .header {
            background: linear-gradient(135deg, #8B4513 0%, #A52A2A 100%);
            color: white;
            padding: 15px 20px;
            border-radius: 10px 10px 0 0;
            margin-bottom: 15px;
            position: relative;
            overflow: hidden;
        }

        .header::before {
            content: '';
            position: absolute;
            top: -30px;
            right: -30px;
            width: 150px;
            height: 150px;
            background: url('images/icon.png') no-repeat center;
            background-size: contain;
            opacity: 0.1;
            transform: rotate(10deg);
        }

        .header-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 10px;
            position: relative;
            z-index: 1;
        }

        .logo-area {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .logo-img {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            border: 3px solid #FFD700;
            background: white;
            object-fit: cover;
        }

        .logo-placeholder {
            width: 60px;
            height: 60px;
            background: #FFD700;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 30px;
            color: #8B4513;
            border: 3px solid white;
        }

        .title h1 {
            font-size: 22px;
            color: #FFD700;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.3);
        }

        .title p {
            font-size: 13px;
            color: #FFF8DC;
        }

        .semester-info {
            text-align: right;
            background: rgba(0,0,0,0.2);
            padding: 8px 15px;
            border-radius: 8px;
        }

        .semester-info strong {
            color: #FFD700;
            font-size: 16px;
        }

        .semester-badge {
            display: inline-block;
            padding: 3px 10px;
            border-radius: 15px;
            font-size: 11px;
            font-weight: 600;
            margin-left: 5px;
        }

        .badge-active {
            background: #D1FAE5;
            color: #10B981;
        }

        .badge-closed {
            background: #FEE2E2;
            color: #EF4444;
        }

        /* Controls */
        .controls {
            background: white;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            align-items: stretch;
        }

        .control-group {
            flex: 1;
            min-width: 100%;
        }

        .control-group label {
            display: block;
            margin-bottom: 5px;
            color: #8B4513;
            font-weight: 600;
            font-size: 13px;
        }

        .control-group select, .control-group input {
            width: 100%;
            padding: 10px;
            border: 2px solid #E2E8F0;
            border-radius: 6px;
            font-size: 14px;
            background: white;
        }

        .control-group select:focus, .control-group input:focus {
            outline: none;
            border-color: #FFD700;
        }

        /* Search Box */
        .search-box {
            position: relative;
        }
        .search-box input {
            padding-right: 35px;
        }
        .search-icon {
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            font-size: 16px;
            color: #8B4513;
        }

        .btn-group {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }

        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-size: 13px;
        }

        .btn-primary {
            background: linear-gradient(135deg, #FFD700 0%, #DAA520 100%);
            color: #8B4513;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 3px 10px rgba(218,165,32,0.3);
        }

        .btn-print {
            background: #8B4513;
            color: #FFD700;
        }

        .btn-print:hover {
            background: #A52A2A;
            transform: translateY(-2px);
        }

        .btn-reset {
            background: #6B7280;
            color: white;
        }

        .btn-reset:hover {
            background: #4B5563;
        }

        /* Statistics */
        .stats-bar {
            background: white;
            border-radius: 8px;
            padding: 12px 15px;
            margin-bottom: 20px;
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            border-left: 4px solid #FFD700;
            font-size: 14px;
        }

        .stat-item {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .stat-label {
            color: #666;
        }

        .stat-value {
            font-weight: bold;
            color: #8B4513;
            background: #FFF8DC;
            padding: 3px 10px;
            border-radius: 20px;
        }

        /* Compact Table */
        .compact-table {
            width: 100%;
            border-collapse: collapse;
            background: white;
            border: 2px solid #FFD700;
            font-size: 12px;
            margin-bottom: 20px;
        }

        .compact-table th {
            background: #8B4513;
            color: white;
            padding: 8px 4px;
            font-weight: 600;
            text-align: center;
            border: 1px solid #DAA520;
            font-size: 11px;
        }

        .compact-table td {
            padding: 6px 4px;
            border: 1px solid #FFD700;
            text-align: center;
            vertical-align: middle;
        }

        .compact-table tr:hover {
            background: #FFF8DC;
        }

        .class-header {
            background: #FEF3C7;
            font-weight: bold;
            color: #8B4513;
        }

        .class-header td {
            padding: 10px;
            background: #FEF3C7;
            font-size: 14px;
            border-bottom: 2px solid #FFD700;
        }

        .student-name {
            font-weight: 600;
            color: #8B4513;
            text-align: left;
            padding-left: 8px;
        }

        .teacher-name {
            font-size: 11px;
            color: #A52A2A;
            font-style: italic;
        }

        .mark-cell {
            font-weight: 500;
        }

        .mark-highlight {
            background: #FFD700;
            font-weight: bold;
            border-radius: 3px;
            padding: 2px 0;
        }

        .total-cell {
            font-weight: bold;
            color: #8B4513;
            background: #FFF8DC;
        }

        .grade-badge {
            display: inline-block;
            padding: 2px 6px;
            border-radius: 12px;
            font-size: 10px;
            font-weight: 600;
        }

        .grade-excellent {
            background: #D1FAE5;
            color: #10B981;
        }

        .grade-good {
            background: #DBEAFE;
            color: #3B82F6;
        }

        .grade-satisfactory {
            background: #FEF3C7;
            color: #F59E0B;
        }

        .grade-poor {
            background: #FEE2E2;
            color: #EF4444;
        }

        .no-data {
            text-align: center;
            padding: 30px;
            color: #999;
            font-style: italic;
        }

        /* Summary Table Styles */
        .summary-section {
            margin-top: 30px;
            background: white;
            border-radius: 8px;
            padding: 15px;
            border: 2px solid #FFD700;
        }

        .summary-title {
            background: #8B4513;
            color: white;
            padding: 10px 15px;
            margin: -15px -15px 15px -15px;
            border-radius: 6px 6px 0 0;
            font-weight: bold;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .summary-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
        }

        .summary-table th {
            background: #FEF3C7;
            color: #8B4513;
            padding: 10px 8px;
            text-align: center;
            font-weight: 600;
            border: 1px solid #FFD700;
        }

        .summary-table td {
            padding: 8px;
            border: 1px solid #FFD700;
            text-align: center;
        }

        .summary-table tr:nth-child(even) {
            background: #FFF8DC;
        }

        .class-group-header {
            background: #8B4513 !important;
            color: white !important;
            font-weight: bold;
            text-align: left;
            padding: 10px 15px;
            border: 1px solid #FFD700;
        }

        .class-group-header td {
            background: #8B4513;
            color: white;
            font-size: 14px;
            padding: 8px 15px;
        }

        .rank-1 {
            background: #FFD700 !important;
            font-weight: bold;
            color: #8B4513;
        }

        .rank-2 {
            background: #E8E8E8 !important;
            font-weight: bold;
            color: #4A4A4A;
        }

        .rank-3 {
            background: #CD7F32 !important;
            font-weight: bold;
            color: white;
        }

        .avg-highlight {
            font-weight: bold;
            color: #8B4513;
        }

        @media print {
            body {
                background: white;
                padding: 0.5cm;
            }
            
            .controls, .btn, .back-btn {
                display: none !important;
            }
            
            .header {
                background: linear-gradient(135deg, #8B4513 0%, #A52A2A 100%);
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
                padding: 0.3cm;
            }
            
            .compact-table, .summary-table {
                border: 1px solid #000;
                font-size: 9pt;
            }
            
            .compact-table th, .summary-table th {
                background: #8B4513;
                color: white;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
                padding: 3px;
                font-size: 9pt;
            }
            
            @page {
                size: A4 landscape;
                margin: 1cm;
            }
        }

        @media (max-width: 768px) {
            .print-container { padding: 10px; margin: 10px auto; }
            .header-content { flex-direction: column; text-align: center; gap: 10px; }
            .logo-area { flex-direction: column; text-align: center; }
            .controls { flex-direction: column; align-items: stretch; gap: 10px; padding: 12px; }
            .control-group { width: 100%; min-width: 100%; }
            .btn-group { width: 100%; }
            .btn-group .btn { flex: 1; justify-content: center; }
            .stats-bar { flex-direction: column; gap: 8px; }
            .compact-table {
                font-size: 10px;
                min-width: 750px;
            }
            .compact-table th, 
            .compact-table td {
                padding: 4px 3px;
            }
            .summary-table {
                font-size: 11px;
                min-width: 550px;
            }
        }
    </style>
</head>
<body>
    <?php include 'mobile_nav.php'; ?>
    <div class="print-container">
        <!-- Header with Logo -->
        <div class="header">
            <div class="header-content">
                <div class="logo-area">
                    <?php 
                    $logo_path = 'images/icon.png';
                    if(file_exists($logo_path)): 
                    ?>
                        <img src="<?php echo $logo_path; ?>" alt="Logo" class="logo-img">
                    <?php else: ?>
                        <div class="logo-placeholder">⛪</div>
                    <?php endif; ?>
                    <div class="title">
                        <h1>አጸደ ትጉሃን ሰንበት ትምህርት ቤት</h1>
                        <p>Atsede Teguhan Sunday School</p>
                    </div>
                </div>
                <div class="semester-info">
                    <strong><?php echo $current_semester ? htmlspecialchars($current_semester['name']) : 'ሴሚስተር አልተመረጠም'; ?></strong><br>
                    <?php if($current_semester): ?>
                        <span class="semester-badge <?php echo $current_semester['status'] == 'active' ? 'badge-active' : 'badge-closed'; ?>">
                            <?php echo $current_semester['status'] == 'active' ? '✅ ንቁ' : '🔒 ዝግ'; ?>
                        </span>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Controls (not printed) -->
        <div class="controls">
            <div class="control-group">
                <label>📅 ሴሚስተር</label>
                <select id="semesterSelect" onchange="filterSemester()">
                    <option value="">ሁሉም</option>
                    <?php 
                    mysqli_data_seek($all_semesters, 0);
                    while($sem = mysqli_fetch_assoc($all_semesters)): 
                        $selected = $sem['id'] == $semester_id ? 'selected' : '';
                        $status_text = $sem['status'] == 'active' ? ' (ንቁ)' : ' (ዝግ)';
                    ?>
                    <option value="<?php echo $sem['id']; ?>" <?php echo $selected; ?>>
                        <?php echo htmlspecialchars($sem['name'] . $status_text); ?>
                    </option>
                    <?php endwhile; ?>
                </select>
            </div>

            <div class="control-group">
                <label>📚 ክፍል</label>
                <select id="classSelect" onchange="filterClass()">
                    <option value="">ሁሉም ክፍሎች</option>
                    <?php 
                    mysqli_data_seek($classes, 0);
                    while($class = mysqli_fetch_assoc($classes)): 
                    ?>
                    <option value="<?php echo $class['id']; ?>" <?php echo $class_id == $class['id'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($class['name']); ?>
                    </option>
                    <?php endwhile; ?>
                </select>
            </div>
            
            <!-- NEW: Student Name Search -->
            <div class="control-group search-box">
                <label>🔍 ተማሪ ፈልግ</label>
                <input type="text" id="studentSearch" placeholder="የተማሪ ስም ይፃፉ..." 
                       value="<?php echo htmlspecialchars($student_search); ?>"
                       onkeyup="searchStudent()">
                <span class="search-icon">🔍</span>
            </div>
            
            <?php if($students && mysqli_num_rows($students) > 0): ?>
            <div class="control-group">
                <label>👤 ተማሪ</label>
                <select id="studentSelect" onchange="filterStudent()">
                    <option value="">ሁሉም</option>
                    <?php 
                    mysqli_data_seek($students, 0);
                    while($student = mysqli_fetch_assoc($students)): 
                    ?>
                    <option value="<?php echo $student['id']; ?>" <?php echo $student_id == $student['id'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($student['name']); ?>
                    </option>
                    <?php endwhile; ?>
                </select>
            </div>
            <?php endif; ?>
            
            <div class="btn-group">
                <button class="btn btn-print" onclick="window.print()">
                    🖨️ አትም
                </button>
                <a href="print_results.php" class="btn btn-reset">
                    🔄 አጽዳ
                </a>
                <a href="dashboard_admin.php" class="btn btn-primary">
                    ← ዳሽቦርድ
                </a>
            </div>
        </div>

        <!-- Statistics Bar -->
        <?php if($stats && $stats['total_students'] > 0): ?>
        <div class="stats-bar">
            <div class="stat-item">
                <span class="stat-label">👥 ተማሪዎች:</span>
                <span class="stat-value"><?php echo $stats['total_students']; ?></span>
            </div>
            <div class="stat-item">
                <span class="stat-label">👨‍🏫 መምህራን:</span>
                <span class="stat-value"><?php echo $stats['total_teachers']; ?></span>
            </div>
            <?php if($class_id): ?>
            <div class="stat-item">
                <span class="stat-label">📋 ክፍል:</span>
                <span class="stat-value"><?php 
                    $class_name_query = mysqli_query($conn, "SELECT name FROM classes WHERE id = $class_id");
                    $class_name = mysqli_fetch_assoc($class_name_query);
                    echo htmlspecialchars($class_name['name'] ?? '');
                ?></span>
            </div>
            <?php endif; ?>
            <?php if(!empty($student_search)): ?>
            <div class="stat-item">
                <span class="stat-label">🔍 ፍለጋ:</span>
                <span class="stat-value">"<?php echo htmlspecialchars($student_search); ?>"</span>
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <!-- Results Table -->
        <?php 
        if($results && mysqli_num_rows($results) > 0):
            $current_class = '';
            $current_student = '';
        ?>
        <div class="table-responsive">
        <table class="compact-table">
            <thead>
                <tr>
                    <th rowspan="2">#</th>
                    <th rowspan="2">ተማሪ</th>
                    <th rowspan="2">ስልክ</th>
                    <th rowspan="2">መምህር</th>
                    <th colspan="5" style="background: #A52A2A;">ውጤቶች</th>
                    <th rowspan="2">ድምር</th>
                    <th rowspan="2">ደረጃ</th>
                </tr>
                <tr>
                    <th>የቤት ሥራ</th>
                    <th>የክፍል ተሳትፎ</th>
                    <th>የክፍል ክትትል</th>
                    <th>የአጋማሽ ፈተና</th>
                    <th>የማጠቃለያ ፈተና</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $counter = 1;
                $student_counter = 1;
                while($row = mysqli_fetch_assoc($results)): 
                    if($current_class != $row['class_name']):
                        $current_class = $row['class_name'];
                        $student_counter = 1;
                ?>
                <tr class="class-header">
                    <td colspan="12">📚 <?php echo htmlspecialchars($row['class_name']); ?></td>
                </tr>
                <?php endif; ?>
                
                <?php 
                $student_key = $row['student_name'] . $row['class_name'];
                if($current_student != $student_key):
                    $current_student = $student_key;
                    $counter = $student_counter++;
                ?>
                <tr>
                    <td><?php echo $counter; ?></td>
                    <td class="student-name"><?php echo htmlspecialchars($row['student_name']); ?></td>
                    <td><?php echo htmlspecialchars($row['parent_phone'] ?: '-'); ?></td>
                    <td>
                        <?php echo htmlspecialchars($row['teacher_name']); ?>
                        <?php if(!$row['total']): ?>
                            <span style="color:#999; font-size:10px;"> (አልተመዘገበም)</span>
                        <?php endif; ?>
                    </td>
                    <td class="mark-cell"><?php echo $row['assignment'] ? number_format($row['assignment'],1) : '-'; ?></td>
                    <td class="mark-cell"><?php echo $row['participation'] ? number_format($row['participation'],1) : '-'; ?></td>
                    <td class="mark-cell"><?php echo $row['attendance'] ? number_format($row['attendance'],1) : '-'; ?></td>
                    <td class="mark-cell"><?php echo $row['mid'] ? number_format($row['mid'],1) : '-'; ?></td>
                    <td class="mark-cell"><?php echo $row['final'] ? number_format($row['final'],1) : '-'; ?></td>
                    <td class="total-cell"><?php echo $row['total'] ? number_format($row['total'],1) : '-'; ?></td>
                    <td>
                        <?php if($row['total']): 
                            $grade = getGradeStatus($row['total']);
                            $t = floatval($row['total']);
                            if($t >= 85) $grade_class = 'grade-excellent';
                            elseif($t >= 70) $grade_class = 'grade-good';
                            elseif($t >= 50) $grade_class = 'grade-satisfactory';
                            else $grade_class = 'grade-poor';
                        ?>
                            <span class="grade-badge <?php echo $grade_class; ?>">
                                <?php echo $grade; ?>
                            </span>
                        <?php else: ?>
                            <span style="color:#999;">-</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php else: ?>
                <tr>
                    <td></td>
                    <td></td>
                    <td></td>
                    <td>
                        <?php echo htmlspecialchars($row['teacher_name']); ?>
                        <?php if(!$row['total']): ?>
                            <span style="color:#999; font-size:10px;"> (አልተመዘገበም)</span>
                        <?php endif; ?>
                    </td>
                    <td class="mark-cell"><?php echo $row['assignment'] ? number_format($row['assignment'],1) : '-'; ?></td>
                    <td class="mark-cell"><?php echo $row['participation'] ? number_format($row['participation'],1) : '-'; ?></td>
                    <td class="mark-cell"><?php echo $row['attendance'] ? number_format($row['attendance'],1) : '-'; ?></td>
                    <td class="mark-cell"><?php echo $row['mid'] ? number_format($row['mid'],1) : '-'; ?></td>
                    <td class="mark-cell"><?php echo $row['final'] ? number_format($row['final'],1) : '-'; ?></td>
                    <td class="total-cell"><?php echo $row['total'] ? number_format($row['total'],1) : '-'; ?></td>
                    <td>
                        <?php if($row['total']): 
                            $grade = getGradeStatus($row['total']);
                            $t = floatval($row['total']);
                            if($t >= 85) $grade_class = 'grade-excellent';
                            elseif($t >= 70) $grade_class = 'grade-good';
                            elseif($t >= 50) $grade_class = 'grade-satisfactory';
                            else $grade_class = 'grade-poor';
                        ?>
                            <span class="grade-badge <?php echo $grade_class; ?>">
                                <?php echo $grade; ?>
                            </span>
                        <?php else: ?>
                            <span style="color:#999;">-</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endif; ?>
                <?php endwhile; ?>
            </tbody>
        </table>
        </div>
        <?php else: ?>
        <div class="no-data">
            <span style="font-size: 48px;">📭</span>
            <h3>ምንም ውጤት አልተገኘም</h3>
            <p>እባክዎ ክፍል ወይም ተማሪ ይምረጡ</p>
        </div>
        <?php endif; ?>

        <!-- Summary Section -->
        <?php if($avg_results && mysqli_num_rows($avg_results) > 0): ?>
        <div class="summary-section">
            <div class="summary-title">
                <span>📊 የተማሪዎች አማካይ ውጤት እና ደረጃ (በክፍል)</span>
            </div>
            
            <div class="table-responsive">
            <table class="summary-table">
                <thead>
                    <tr>
                        <th>ደረጃ</th>
                        <th>ተማሪ</th>
                        <th>ክፍል</th>
                        <th>የመምህራን ብዛት</th>
                        <th>አማካይ ውጤት</th>
                        <th>ድምር ውጤት</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $current_class = '';
                    $class_rank = 1;
                    
                    mysqli_data_seek($avg_results, 0);
                    while($avg_row = mysqli_fetch_assoc($avg_results)): 
                        $avg_total = $avg_row['avg_total'] ? number_format($avg_row['avg_total'], 2) : '0.00';
                        $total_marks = $avg_row['total_marks'] ? number_format($avg_row['total_marks'], 1) : '0';
                        
                        if($current_class != $avg_row['class_name']):
                            $current_class = $avg_row['class_name'];
                            $class_rank = 1;
                    ?>
                    <tr class="class-group-header">
                        <td colspan="6">📚 ክፍል: <?php echo htmlspecialchars($avg_row['class_name']); ?></td>
                    </tr>
                    <?php endif; ?>
                    
                    <?php
                        $rank_class = '';
                        if($class_rank == 1) $rank_class = 'rank-1';
                        elseif($class_rank == 2) $rank_class = 'rank-2';
                        elseif($class_rank == 3) $rank_class = 'rank-3';
                    ?>
                    
                    <tr>
                        <td style="font-weight: bold;" class="<?php echo $rank_class; ?>">
                            <?php 
                            if($class_rank == 1) echo '🥇 1';
                            elseif($class_rank == 2) echo '🥈 2';
                            elseif($class_rank == 3) echo '🥉 3';
                            else echo $class_rank;
                            ?>
                        </td>
                        <td style="text-align: left; padding-left: 15px; font-weight: 600; color: #8B4513;">
                            <?php echo htmlspecialchars($avg_row['student_name']); ?>
                        </td>
                        <td><?php echo htmlspecialchars($avg_row['class_name']); ?></td>
                        <td>
                            <span style="background: #FFD700; padding: 3px 8px; border-radius: 12px; font-weight: bold;">
                                <?php echo $avg_row['teacher_count']; ?>
                            </span>
                        </td>
                        <td class="avg-highlight"><?php echo $avg_total; ?></td>
                        <td><?php echo $total_marks; ?></td>
                    </tr>
                    
                    <?php 
                        $class_rank++;
                    endwhile; 
                    ?>
                </tbody>
            </table>
            </div>
        </div>
        <?php endif; ?>

        <!-- Footer with signature -->
        <div style="margin-top: 30px; display: flex; justify-content: space-between; padding: 0 20px; font-size: 12px; color: #666;">
            <div>የታተመበት ቀን: <?php echo date('d/m/Y'); ?></div>
            <div>የትምህርት ክፍል ፊርማ: _________________</div>
        </div>
    </div>

    <script>
        function filterSemester() {
            const semesterId = document.getElementById('semesterSelect').value;
            const classId = document.getElementById('classSelect').value;
            const studentId = document.getElementById('studentSelect')?.value || '';
            const search = document.getElementById('studentSearch')?.value || '';
            
            let url = 'print_results.php?';
            let params = [];
            if(semesterId) params.push('semester_id=' + semesterId);
            if(classId) params.push('class_id=' + classId);
            if(studentId) params.push('student_id=' + studentId);
            if(search) params.push('student_search=' + encodeURIComponent(search));
            
            window.location.href = url + params.join('&');
        }
        
        function filterClass() {
            const semesterId = document.getElementById('semesterSelect').value;
            const classId = document.getElementById('classSelect').value;
            const search = document.getElementById('studentSearch')?.value || '';
            
            let url = 'print_results.php?';
            let params = [];
            if(semesterId) params.push('semester_id=' + semesterId);
            if(classId) params.push('class_id=' + classId);
            if(search) params.push('student_search=' + encodeURIComponent(search));
            
            window.location.href = url + params.join('&');
        }
        
        function filterStudent() {
            const semesterId = document.getElementById('semesterSelect').value;
            const classId = document.getElementById('classSelect').value;
            const studentId = document.getElementById('studentSelect').value;
            const search = document.getElementById('studentSearch')?.value || '';
            
            let url = 'print_results.php?';
            let params = [];
            if(semesterId) params.push('semester_id=' + semesterId);
            if(classId) params.push('class_id=' + classId);
            if(studentId) params.push('student_id=' + studentId);
            if(search) params.push('student_search=' + encodeURIComponent(search));
            
            window.location.href = url + params.join('&');
        }

        // NEW: Search function with debounce
        let searchTimeout;
        function searchStudent() {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(() => {
                const semesterId = document.getElementById('semesterSelect').value;
                const classId = document.getElementById('classSelect').value;
                const search = document.getElementById('studentSearch').value;
                
                let url = 'print_results.php?';
                let params = [];
                if(semesterId) params.push('semester_id=' + semesterId);
                if(classId) params.push('class_id=' + classId);
                if(search) params.push('student_search=' + encodeURIComponent(search));
                
                window.location.href = url + params.join('&');
            }, 500);
        }
        
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