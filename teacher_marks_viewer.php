<?php
require_once 'db.php';
requireLogin();
if (!isAdmin() && !isTeacher()) {
    header("Location: index.php");
    exit();
}

$user_id = intval($_SESSION['user_id'] ?? 0);
$user_name = $_SESSION['user_name'] ?? '';
$is_admin = isAdmin();

if (empty($user_name) && $user_id) {
    $u_row = dbFetchOne($conn, "SELECT name FROM users WHERE id = ?", "i", [$user_id]);
    $user_name = $u_row ? $u_row['name'] : 'Teacher';
}

$current_semester = getCurrentSemester($conn);
$semester_id = $current_semester ? intval($current_semester['id']) : 0;

// Get classes based on role
if ($is_admin) {
    $classes_query = "SELECT * FROM classes ORDER BY name";
    $classes = mysqli_query($conn, $classes_query);
    $teachers_query = "SELECT * FROM users WHERE role = 'teacher' ORDER BY name";
    $teachers = mysqli_query($conn, $teachers_query);
    $teacher_classes = [];
    $selected_class_id = 0;
} else {
    $teacher_classes = getTeacherClasses($conn, $user_id, $semester_id);
    $classes = null;
    $teachers = null;
    $selected_class_id = 0;
    if (!empty($teacher_classes)) {
        $requested_class_id = isset($_GET['class_id']) ? intval($_GET['class_id']) : 0;
        foreach ($teacher_classes as $tc) {
            if (intval($tc['class_id']) === $requested_class_id) {
                $selected_class_id = $requested_class_id;
                break;
            }
        }
        if (!$selected_class_id) {
            $selected_class_id = intval($teacher_classes[0]['class_id']);
        }
    }
}

// Handle AJAX request for teachers
if (isset($_GET['ajax']) && $_GET['ajax'] === 'get_teachers') {
    header('Content-Type: application/json');
    
    $class_id = intval($_GET['class_id'] ?? 0);
    
    if (!$class_id) {
        echo json_encode(['success' => false, 'message' => 'Missing class_id']);
        exit();
    }
    
    if (!$is_admin) {
        // IDOR Protection: teacher can only see themselves and only for assigned classes
        $assigned = dbFetchOne($conn, "SELECT id FROM teacher_class WHERE teacher_id = ? AND class_id = ? AND semester_id = ?", "iii", [$user_id, $class_id, $semester_id]);
        if (!$assigned) {
            echo json_encode(['success' => false, 'message' => 'Unauthorized class']);
            exit();
        }
        $teacher_info = dbFetchOne($conn, "SELECT id, name FROM users WHERE id = ?", "i", [$user_id]);
        echo json_encode(['success' => true, 'teachers' => [$teacher_info]]);
        exit();
    }

    // Get teachers assigned to this class
    $query = "SELECT DISTINCT u.id, u.name 
              FROM teacher_class tc
              JOIN users u ON tc.teacher_id = u.id
              WHERE tc.class_id = ? 
              AND tc.semester_id = ?
              AND u.role = 'teacher'
              ORDER BY u.name";
    
    $teachers_list = dbFetchAll($conn, $query, "ii", [$class_id, $semester_id]);
    
    echo json_encode(['success' => true, 'teachers' => $teachers_list]);
    exit();
}

// Handle AJAX request for marks
if (isset($_GET['ajax']) && $_GET['ajax'] === 'get_marks') {
    header('Content-Type: application/json');
    
    $teacher_id = intval($_GET['teacher_id'] ?? 0);
    $class_id = intval($_GET['class_id'] ?? 0);
    
    if (!$teacher_id || !$class_id) {
        echo json_encode(['success' => false, 'message' => 'Missing parameters']);
        exit();
    }

    // IDOR Protection
    if (!$is_admin) {
        if ($teacher_id !== $user_id) {
            echo json_encode(['success' => false, 'message' => 'Unauthorized teacher']);
            exit();
        }
        $assigned = dbFetchOne($conn, "SELECT id FROM teacher_class WHERE teacher_id = ? AND class_id = ? AND semester_id = ?", "iii", [$user_id, $class_id, $semester_id]);
        if (!$assigned) {
            echo json_encode(['success' => false, 'message' => 'Unauthorized class']);
            exit();
        }
    }
    
    // Get marking scheme for this teacher and class
    $scheme_row = dbFetchOne(
        $conn,
        "SELECT * FROM marking_schemes WHERE teacher_id = ? AND class_id = ? AND semester_id = ?",
        "iii",
        [$teacher_id, $class_id, $semester_id]
    );
    
    if ($scheme_row) {
        $scheme = $scheme_row;
    } else {
        // Default scheme
        $scheme = [
            'component1_name' => 'Assignment',
            'component1_percentage' => 20,
            'component2_name' => 'Participation',
            'component2_percentage' => 20,
            'component3_name' => 'Attendance',
            'component3_percentage' => 10,
            'component4_name' => 'Mid Exam',
            'component4_percentage' => 25,
            'component5_name' => 'Final Exam',
            'component5_percentage' => 25
        ];
    }
    
    // Get students with marks
    $marks_query = "SELECT s.id, s.name, s.parent_phone,
                    COALESCE(m.assignment, 0) as assignment,
                    COALESCE(m.participation, 0) as participation,
                    COALESCE(m.attendance, 0) as attendance,
                    COALESCE(m.mid, 0) as mid,
                    COALESCE(m.final, 0) as final,
                    COALESCE(m.total, 0) as total
                    FROM students s
                    LEFT JOIN marks m ON s.id = m.student_id 
                        AND m.teacher_id = ? 
                        AND m.semester_id = ?
                    WHERE s.class_id = ? AND (s.is_deleted = 0 OR s.is_deleted IS NULL)
                    ORDER BY s.name";
    
    $students = dbFetchAll($conn, $marks_query, "iii", [$teacher_id, $semester_id, $class_id]);
    
    // Get teacher name
    $teacher_info = dbFetchOne($conn, "SELECT name FROM users WHERE id = ?", "i", [$teacher_id]) ?: ['name' => 'Unknown'];
    
    // Get class name
    $class_info = dbFetchOne($conn, "SELECT name FROM classes WHERE id = ?", "i", [$class_id]) ?: ['name' => 'Unknown'];
    
    echo json_encode([
        'success' => true,
        'scheme' => $scheme,
        'students' => $students,
        'teacher_name' => $teacher_info['name'],
        'class_name' => $class_info['name'],
        'semester_name' => $current_semester ? $current_semester['name'] : ''
    ]);
    exit();
}
$nav_active = 'teacher_marks_viewer';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>የመምህራን ውጤት ማያ ገጽ | አጸደ ትጉሃን</title>
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
            --info-blue: #3B82F6;
            --purple: #8B5CF6;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        body {
            background: var(--bg-cream);
        }

        .header {
            background: linear-gradient(135deg, #8B4513 0%, #A52A2A 100%);
            color: white;
            padding: 20px 30px;
        }
        .logo-img {
            width: 50px; height: 50px; border-radius: 50%; object-fit: cover;
            border: 3px solid var(--gold-primary); background: white;
        }
        .header-content {
            max-width: 1400px;
            margin: 0 auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
        }

        .logo-area {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .logo-icon {
            width: 55px;
            height: 55px;
            background: linear-gradient(135deg, var(--gold-primary), var(--gold-dark));
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 28px;
            color: var(--brown-dark);
            border: 3px solid white;
        }

        .title h1 {
            font-size: 22px;
            color: var(--gold-primary);
        }

        .title p {
            font-size: 14px;
            color: var(--gold-light);
        }

        .nav {
    background: white;
    padding: 12px 20px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    position: sticky;
    top: 0;
    z-index: 100;
}

.nav-links {
    max-width: 1400px;
    margin: 0 auto;
    display: flex;
    gap: 6px;
    flex-wrap: wrap;
    justify-content: center;
}

.nav-link {
    padding: 8px 14px;
    color: var(--brown-dark);
    text-decoration: none;
    border-radius: 25px;
    transition: all 0.3s;
    font-weight: 600;
    font-size: 12px;
    white-space: nowrap;
    border: 1px solid transparent;
}

.nav-link:hover {
    background: var(--gold-pale);
    border-color: var(--gold-primary);
}

.nav-link.active {
    background: linear-gradient(135deg, var(--gold-primary), var(--gold-dark));
    color: var(--brown-dark);
    border-color: var(--brown-dark);
    font-weight: 700;
}

        .container {
            max-width: 1400px;
            margin: 30px auto;
            padding: 0 30px;
        }

        /* Filter Section */
        .filter-section {
            background: white;
            border-radius: 20px;
            padding: 25px;
            margin-bottom: 30px;
            border: 2px solid var(--gold-primary);
            box-shadow: 0 5px 20px rgba(0,0,0,0.08);
        }

        .class-direct-card {
            background: white;
            border: 2px solid var(--gold-primary);
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
        }

        .filter-title {
            color: var(--brown-dark);
            font-size: 20px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .filter-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 20px;
            margin-bottom: 20px;
        }

        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .filter-group label {
            color: var(--brown-dark);
            font-weight: 600;
            font-size: 14px;
        }

        .filter-group select {
            padding: 12px 15px;
            border: 2px solid #E2E8F0;
            border-radius: 12px;
            font-size: 14px;
            background: white;
            cursor: pointer;
            transition: all 0.3s;
        }

        .filter-group select:focus {
            outline: none;
            border-color: var(--gold-primary);
            box-shadow: 0 0 0 3px rgba(255,215,0,0.2);
        }

        /* Class Tabs for Teachers */
        .class-tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }

        .class-tab {
            padding: 10px 22px;
            border: 2px solid var(--gold-dark);
            border-radius: 30px;
            background: white;
            color: var(--brown-dark);
            font-weight: 600;
            font-size: 14px;
            cursor: pointer;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .class-tab:hover {
            background: var(--gold-pale);
            transform: translateY(-2px);
        }

        .class-tab.active {
            background: linear-gradient(135deg, var(--gold-primary), var(--gold-dark));
            border-color: var(--brown-dark);
            color: var(--brown-dark);
            box-shadow: 0 4px 12px rgba(218,165,32,0.3);
        }

        /* Teachers Grid */
        .teachers-grid {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            margin-bottom: 25px;
        }

        .teacher-btn {
            padding: 12px 24px;
            background: white;
            border: 2px solid var(--gold-dark);
            border-radius: 40px;
            font-weight: 600;
            color: var(--brown-dark);
            cursor: pointer;
            transition: all 0.3s;
            font-size: 14px;
        }

        .teacher-btn:hover {
            background: var(--gold-pale);
            transform: translateY(-2px);
        }

        .teacher-btn.active {
            background: linear-gradient(135deg, var(--gold-primary), var(--gold-dark));
            border-color: var(--brown-dark);
            color: var(--brown-dark);
            box-shadow: 0 5px 15px rgba(218,165,32,0.3);
        }

        .teacher-btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        /* Results Card */
        .results-card {
            background: white;
            border-radius: 20px;
            padding: 25px;
            border: 2px solid var(--gold-primary);
            box-shadow: 0 5px 20px rgba(0,0,0,0.08);
            display: none;
        }

        .results-card.show {
            display: block;
            animation: fadeIn 0.4s ease;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .results-header {
            display: flex;
            flex-direction: column;
            text-align: center;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 3px solid var(--gold-primary);
            flex-wrap: wrap;
            gap: 15px;
        }

        .results-header h2 {
            color: var(--brown-dark);
            font-size: 22px;
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
        }

        .semester-badge {
            background: var(--purple);
            color: white;
            padding: 6px 15px;
            border-radius: 30px;
            font-size: 12px;
        }

        .action-buttons {
            display: flex;
            gap: 10px;
        }

        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 10px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-size: 13px;
        }

        .btn-print {
            background: #8B4513;
            color: #FFD700;
        }

        .btn-excel {
            background: #10B981;
            color: white;
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }

        /* Table */
        .table-responsive {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 800px;
        }

        th {
            background: #8B4513;
            color: white;
            padding: 14px 10px;
            text-align: center;
            border: 1px solid #DAA520;
            font-size: 13px;
            font-weight: 600;
        }

        td {
            padding: 12px 8px;
            text-align: center;
            border-bottom: 1px solid #FFD700;
        }

        tr:hover {
            background: #FFF8DC;
        }

        .student-name {
            color: var(--brown-dark);
            font-weight: 600;
            text-align: left;
            background: #FFF8DC;
        }

        .total-cell {
            font-weight: bold;
            color: var(--brown-dark);
            background: #FFD700;
            border-radius: 20px;
        }

        .loading {
            text-align: center;
            padding: 60px;
            color: #666;
        }

        .loading-spinner {
            width: 50px;
            height: 50px;
            border: 4px solid var(--gold-pale);
            border-top-color: var(--gold-primary);
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin: 0 auto 15px;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        .no-data {
            text-align: center;
            padding: 60px;
            color: #999;
        }

        .no-data span {
            font-size: 48px;
            display: block;
            margin-bottom: 15px;
        }

        .error-message {
            background: #FEE2E2;
            color: #EF4444;
            padding: 20px;
            border-radius: 10px;
            text-align: center;
            margin: 20px 0;
        }

        .summary-bar {
            margin-top: 20px;
            padding: 15px;
            background: var(--gold-pale);
            border-radius: 12px;
            display: flex;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 15px;
        }

        @media (max-width: 768px) {
            .filter-grid {
                grid-template-columns: 1fr;
            }
            
            .teachers-grid {
                justify-content: flex-start;
            }
            
            .results-header {
                flex-direction: row;
                text-align: left;
            }
        }

        @media print {
            .header, .nav, .filter-section, .btn, .teachers-grid {
                display: none !important;
            }
            
            .results-card {
                display: block !important;
                border: none;
                padding: 0;
                box-shadow: none;
            }
            
            th {
                background: #ddd !important;
                color: black !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
        }
    </style>
</head>
<body>
    <?php include 'mobile_nav.php'; ?>
    <div class="main-container">
        <?php if ($is_admin): ?>
        <!-- Filter Section for Admin -->
        <div class="filter-section">
            <div class="filter-title">
                <span>🔍</span> የማጣሪያ መምረጫ / Filter Selection
            </div>
            <div class="filter-grid">
                <div class="filter-group">
                    <label>📚 ክፍል ምረጥ / Select Class</label>
                    <select id="classSelect" onchange="onClassChange()">
                        <option value="">-- ክፍል ምረጥ --</option>
                        <?php 
                        if ($classes) {
                            mysqli_data_seek($classes, 0);
                            while($class = mysqli_fetch_assoc($classes)): 
                        ?>
                        <option value="<?php echo $class['id']; ?>"><?php echo htmlspecialchars($class['name']); ?></option>
                        <?php 
                            endwhile;
                        } 
                        ?>
                    </select>
                </div>
            </div>
        </div>

        <!-- Teachers Grid (dynamic for Admin) -->
        <div id="teachersGrid" class="teachers-grid" style="display: none;">
            <!-- Teachers buttons will be loaded here -->
        </div>
        <?php else: ?>
        <!-- Teacher Direct View: No selecting self needed! -->
        <?php if (empty($teacher_classes)): ?>
            <div class="no-data class-direct-card" style="border-radius: 20px; padding: 40px; text-align: center; margin-bottom: 25px;">
                <span style="font-size: 48px; display: block; margin-bottom: 12px;">📚</span>
                <h3 style="color: var(--brown-dark); margin-bottom: 8px;">ለዚህ ሴሚስተር የተመደቡበት ክፍል የለም</h3>
                <p style="color: #666; font-size: 14px;">እባክዎ ከአስተዳዳሪው ጋር ይገናኙ። (No assigned classes found for this semester.)</p>
            </div>
        <?php elseif (count($teacher_classes) === 1): ?>
            <div class="class-direct-card" style="border-radius: 16px; padding: 18px 25px; margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
                <div style="display: flex; align-items: center; gap: 12px;">
                    <span style="font-size: 32px;">📚</span>
                    <div>
                        <h3 style="color: var(--brown-dark); margin: 0; font-size: 20px; font-weight: 700;">
                            <?php echo htmlspecialchars($teacher_classes[0]['class_name']); ?>
                        </h3>
                        <p style="color: #666; margin: 4px 0 0 0; font-size: 13px;">
                            👨‍🏫 መምህር፡ <strong><?php echo htmlspecialchars($user_name); ?></strong> &nbsp;|&nbsp; 
                            👥 ተማሪዎች፡ <strong><?php echo $teacher_classes[0]['student_count']; ?></strong>
                        </p>
                    </div>
                </div>
                <span class="semester-badge"><?php echo htmlspecialchars($current_semester['name'] ?? ''); ?></span>
            </div>
        <?php else: ?>
            <div class="class-direct-card" style="border-radius: 16px; padding: 18px 25px; margin-bottom: 20px;">
                <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px; margin-bottom: 12px;">
                    <div style="font-size: 14px; color: var(--brown-dark); font-weight: 700;">
                        👨‍🏫 መምህር፡ <?php echo htmlspecialchars($user_name); ?> | 📚 ክፍል ይምረጡ፦
                    </div>
                    <span class="semester-badge"><?php echo htmlspecialchars($current_semester['name'] ?? ''); ?></span>
                </div>
                <div class="class-tabs" style="margin-bottom: 0;">
                    <?php foreach ($teacher_classes as $tc): ?>
                    <button type="button" 
                            class="class-tab <?php echo $selected_class_id == $tc['class_id'] ? 'active' : ''; ?>" 
                            onclick="selectTeacherClass(this, <?php echo $tc['class_id']; ?>)">
                        📚 <?php echo htmlspecialchars($tc['class_name']); ?> 
                        <small style="opacity: 0.85;">(<?php echo $tc['student_count']; ?> ተማሪዎች)</small>
                    </button>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
        <?php endif; ?>

        <!-- Results Card -->
        <div id="resultsCard" class="results-card">
            <div class="results-header">
                <h2>
                    <span>📊</span>
                    <span id="resultsTitle">ውጤት ሰንጠረዥ / Marks Table</span>
                    <span id="teacherNameSpan" style="font-size: 16px; color: #A52A2A;"></span>
                </h2>
                <div class="action-buttons">
                    <button class="btn btn-print" onclick="window.print()">
                        🖨️ አትም / Print
                    </button>
                    <button class="btn btn-excel" id="exportExcelBtn" onclick="exportToExcel()">
                        📊 Excel አውርድ / Export
                    </button>
                </div>
            </div>
            <div id="marksTableContainer">
                <div class="loading">
                    <div class="loading-spinner"></div>
                    <p>እባክዎ ክፍል ይምረጡ እና መምህር ይጫኑ</p>
                </div>
            </div>
        </div>
    </div>

    <script>
        let currentMarksData = null;
        let currentScheme = null;
        let currentTeacherName = '';
        let currentClassName = '';
        let currentClassId = null;
        let currentTeacherId = null;

        const isAdmin = <?php echo $is_admin ? 'true' : 'false'; ?>;
        const loggedInTeacherId = <?php echo $user_id; ?>;
        const loggedInTeacherName = <?php echo json_encode($user_name, JSON_UNESCAPED_UNICODE); ?>;

        function selectTeacherClass(btn, classId) {
            document.querySelectorAll('.class-tab').forEach(b => b.classList.remove('active'));
            if (btn) btn.classList.add('active');
            loadMarks(loggedInTeacherId, loggedInTeacherName, classId);
        }

        function onClassChange() {
            const classSelect = document.getElementById('classSelect');
            const classId = classSelect ? classSelect.value : null;
            const teachersGrid = document.getElementById('teachersGrid');
            const resultsCard = document.getElementById('resultsCard');
            
            if (!classId) {
                if (teachersGrid) teachersGrid.style.display = 'none';
                if (resultsCard) resultsCard.classList.remove('show');
                document.getElementById('marksTableContainer').innerHTML = `
                    <div class="loading">
                        <div class="loading-spinner"></div>
                        <p>እባክዎ ክፍል ይምረጡ እና መምህር ይጫኑ</p>
                    </div>
                `;
                return;
            }
            
            // Show loading
            if (teachersGrid) {
                teachersGrid.style.display = 'flex';
                teachersGrid.innerHTML = '<div style="width:100%; text-align:center; padding:20px;"><div class="loading-spinner"></div><p>መምህራንን በማግኘት ላይ...</p></div>';
            }
            if (resultsCard) resultsCard.classList.remove('show');
            
            // Fetch teachers for this class
            fetch(`teacher_marks_viewer.php?ajax=get_teachers&class_id=${classId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.teachers.length > 0) {
                        renderTeacherButtons(data.teachers, classId);
                    } else {
                        if (teachersGrid) teachersGrid.innerHTML = '<div class="no-data" style="width:100%;"><span>👨‍🏫</span><p>ለዚህ ክፍል ምንም መምህራን አልተመደቡም</p></div>';
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    if (teachersGrid) teachersGrid.innerHTML = '<div class="error-message" style="width:100%;">❌ ስህተት ተከስቷል! እባክዎ እንደገና ይሞክሩ።</div>';
                });
        }

        function renderTeacherButtons(teachers, classId) {
            const teachersGrid = document.getElementById('teachersGrid');
            if (!teachersGrid) return;
            teachersGrid.innerHTML = '';
            
            teachers.forEach((teacher, idx) => {
                const btn = document.createElement('button');
                btn.className = 'teacher-btn';
                btn.innerHTML = `👨‍🏫 ${escapeHtml(teacher.name)}`;
                btn.onclick = function() {
                    document.querySelectorAll('.teacher-btn').forEach(b => b.classList.remove('active'));
                    btn.classList.add('active');
                    loadMarks(teacher.id, teacher.name, classId);
                };
                teachersGrid.appendChild(btn);

                // If only 1 teacher in this class, auto-select!
                if (teachers.length === 1 && idx === 0) {
                    btn.classList.add('active');
                    loadMarks(teacher.id, teacher.name, classId);
                }
            });
        }

        function loadMarks(teacherId, teacherName, classId) {
            if (!classId) {
                const sel = document.getElementById('classSelect');
                classId = sel ? sel.value : null;
            }
            if (!classId) return;

            const resultsCard = document.getElementById('resultsCard');
            currentTeacherName = teacherName;
            currentTeacherId = teacherId;
            currentClassId = classId;
            
            if (resultsCard) resultsCard.classList.add('show');
            const teacherNameSpan = document.getElementById('teacherNameSpan');
            if (teacherNameSpan) teacherNameSpan.innerHTML = ` - ${escapeHtml(teacherName)}`;
            
            document.getElementById('marksTableContainer').innerHTML = `
                <div class="loading">
                    <div class="loading-spinner"></div>
                    <p>ውጤቶችን በማምጣት ላይ...</p>
                </div>
            `;
            
            fetch(`teacher_marks_viewer.php?ajax=get_marks&teacher_id=${teacherId}&class_id=${classId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        currentMarksData = data.students;
                        currentScheme = data.scheme;
                        currentClassName = data.class_name;
                        currentTeacherName = data.teacher_name;
                        const resultsTitle = document.getElementById('resultsTitle');
                        if (resultsTitle) resultsTitle.innerHTML = `ውጤት ሰንጠረዥ - ${escapeHtml(data.class_name)}`;
                        if (teacherNameSpan) teacherNameSpan.innerHTML = ` (${escapeHtml(data.teacher_name)})`;
                        renderMarksTable(data);
                    } else {
                        document.getElementById('marksTableContainer').innerHTML = `
                            <div class="error-message">
                                <span style="font-size:40px;">❌</span>
                                <p>ውጤቶችን ማምጣት አልተቻለም</p>
                                <small>${escapeHtml(data.message || 'Unknown error')}</small>
                            </div>
                        `;
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    document.getElementById('marksTableContainer').innerHTML = `
                        <div class="error-message">
                            <span style="font-size:40px;">❌</span>
                            <p>ስህተት ተከስቷል</p>
                            <small>Please check console for details</small>
                        </div>
                    `;
                });
        }

        // Auto-load marks on page load for teachers
        document.addEventListener('DOMContentLoaded', function() {
            <?php if (!$is_admin && !empty($teacher_classes) && $selected_class_id): ?>
                loadMarks(<?php echo $user_id; ?>, <?php echo json_encode($user_name, JSON_UNESCAPED_UNICODE); ?>, <?php echo $selected_class_id; ?>);
            <?php endif; ?>
        });

        function renderMarksTable(data) {
            const scheme = data.scheme;
            const students = data.students;
            
            if (!students || students.length === 0) {
                document.getElementById('marksTableContainer').innerHTML = `
                    <div class="no-data">
                        <span>👥</span>
                        <p>ለዚህ ክፍል ምንም ተማሪዎች የሉም</p>
                    </div>
                `;
                return;
            }
            
            let html = `
                <div class="table-responsive">
                    <table id="marksTable">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>የተማሪ ስም</th>
                                <th>ስልክ</th>
                                <th>${escapeHtml(scheme.component1_name)}<br><small>(${scheme.component1_percentage}%)</small></th>
                                <th>${escapeHtml(scheme.component2_name)}<br><small>(${scheme.component2_percentage}%)</small></th>
                                <th>${escapeHtml(scheme.component3_name)}<br><small>(${scheme.component3_percentage}%)</small></th>
                                <th>${escapeHtml(scheme.component4_name)}<br><small>(${scheme.component4_percentage}%)</small></th>
                                <th>${escapeHtml(scheme.component5_name)}<br><small>(${scheme.component5_percentage}%)</small></th>
                                <th>ጠቅላላ ድምር</th>
                            </tr>
                        </thead>
                        <tbody>
            `;
            
            students.forEach((student, index) => {
                html += `
                    <tr>
                        <td>${index + 1}</td>
                        <td class="student-name">${escapeHtml(student.name)}</td>
                        <td>${student.parent_phone || '-'}</td>
                        <td>${parseFloat(student.assignment).toFixed(1)}</td>
                        <td>${parseFloat(student.participation).toFixed(1)}</td>
                        <td>${parseFloat(student.attendance).toFixed(1)}</td>
                        <td>${parseFloat(student.mid).toFixed(1)}</td>
                        <td>${parseFloat(student.final).toFixed(1)}</td>
                        <td class="total-cell"><strong>${parseFloat(student.total).toFixed(1)}</strong></td>
                    </tr>
                `;
            });
            
            // Calculate average
            let totals = students.map(s => parseFloat(s.total));
            let classAverage = totals.reduce((a, b) => a + b, 0) / totals.length;
            
            html += `
                        </tbody>
                    </table>
                </div>
                <div class="summary-bar">
                    <div><strong>📊 የክፍል አማካይ / Class Average:</strong> ${classAverage.toFixed(1)}</div>
                    <div><strong>👥 ጠቅላላ ተማሪዎች / Total Students:</strong> ${students.length}</div>
                </div>
            `;
            
            document.getElementById('marksTableContainer').innerHTML = html;
        }

        function exportToExcel() {
            if (!currentMarksData || !currentScheme) {
                alert('እባክዎ መጀመሪያ መምህር እና ክፍል ይምረጡ');
                return;
            }
            
            let excelContent = `
                <html>
                <head>
                    <meta charset="UTF-8">
                    <title>Teacher Marks Report</title>
                    <style>
                        body { font-family: 'Segoe UI', sans-serif; margin: 20px; }
                        .header { text-align: center; margin-bottom: 30px; }
                        h1 { color: #8B4513; }
                        table { border-collapse: collapse; width: 100%; }
                        th { background: #8B4513; color: white; padding: 10px; border: 1px solid #DAA520; }
                        td { padding: 8px; border: 1px solid #DAA520; }
                    </style>
                </head>
                <body>
                    <div class="header">
                        <h1>አጸደ ትጉሃን ሰንበት ትምህርት ቤት</h1>
                        <p>የመምህር ውጤት ሪፖርት</p>
                        <p><strong>መምህር:</strong> ${escapeHtml(currentTeacherName)} | <strong>ክፍል:</strong> ${escapeHtml(currentClassName)}</p>
                        <p>የታተመበት ቀን: ${new Date().toLocaleDateString()}</p>
                    </div>
                    <table>
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>የተማሪ ስም</th>
                                <th>ስልክ</th>
                                <th>${escapeHtml(currentScheme.component1_name)}</th>
                                <th>${escapeHtml(currentScheme.component2_name)}</th>
                                <th>${escapeHtml(currentScheme.component3_name)}</th>
                                <th>${escapeHtml(currentScheme.component4_name)}</th>
                                <th>${escapeHtml(currentScheme.component5_name)}</th>
                                <th>ድምር</th>
                            </tr>
                        </thead>
                        <tbody>
            `;
            
            currentMarksData.forEach((student, index) => {
                excelContent += `
                    <tr>
                        <td>${index + 1}</td>
                        <td>${escapeHtml(student.name)}</td>
                        <td>${student.parent_phone || '-'}</td>
                        <td>${parseFloat(student.assignment).toFixed(1)}</td>
                        <td>${parseFloat(student.participation).toFixed(1)}</td>
                        <td>${parseFloat(student.attendance).toFixed(1)}</td>
                        <td>${parseFloat(student.mid).toFixed(1)}</td>
                        <td>${parseFloat(student.final).toFixed(1)}</td>
                        <td><strong>${parseFloat(student.total).toFixed(1)}</strong></td>
                    </tr>
                `;
            });
            
            excelContent += `
                        </tbody>
                    </table>
                </body>
                </html>
            `;
            
            const blob = new Blob([excelContent], { type: 'application/vnd.ms-excel' });
            const link = document.createElement('a');
            link.href = URL.createObjectURL(blob);
            link.download = `teacher_marks_${Date.now()}.xls`;
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
            URL.revokeObjectURL(link.href);
        }

        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
    </script>
</body>
</html>
<?php mysqli_close($conn); ?>