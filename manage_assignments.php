<?php
require_once 'db.php';
requireAdmin();

$message = '';
$error = '';

$current_semester = getCurrentSemester($conn);
$semester_id = $current_semester ? intval($current_semester['id']) : 0;

// Handle Add Assignment
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = "የደህንነት ማረጋገጫ አልተሳካም! እባክዎ እንደገና ይሞክሩ።";
    } else {
        if (isset($_POST['add_assignment'])) {
            $teacher_id = intval($_POST['teacher_id'] ?? 0);
            $class_id = intval($_POST['class_id'] ?? 0);
            $subject_id = intval($_POST['subject_id'] ?? 0);
            
            if ($teacher_id > 0 && $class_id > 0 && $subject_id > 0 && $semester_id > 0) {
                // Get subject name
                $sub_row = dbFetchOne($conn, "SELECT name FROM subjects WHERE id = ?", "i", [$subject_id]);
                $subject_name = $sub_row ? $sub_row['name'] : '';
                
                if (empty($subject_name)) {
                    $error = "እባክዎ ትክክለኛ የትምህርት ዓይነት ይምረጡ!";
                } else {
                    // Check if already assigned for this class and subject
                    $exists = dbFetchOne($conn, "SELECT id FROM teacher_class WHERE teacher_id = ? AND class_id = ? AND semester_id = ? AND subject_id = ?", "iiii", [$teacher_id, $class_id, $semester_id, $subject_id]);
                    
                    if ($exists) {
                        $error = "ይህ መምህር በዚህ ክፍልና የትምህርት ዓይነት ቀድሞውኑ ተመድቧል!";
                    } else {
                        $saved = dbExecute(
                            $conn,
                            "INSERT INTO teacher_class (teacher_id, class_id, subject_id, subject_name, semester_id, locked) VALUES (?, ?, ?, ?, ?, 0)",
                            "iiisi",
                            [$teacher_id, $class_id, $subject_id, $subject_name, $semester_id]
                        );
                        if ($saved) {
                            $message = "መምህር በተሳካ ሁኔታ ለክፍል ({$subject_name}) ተመድቧል!";
                        } else {
                            $error = "ስህተት ተከስቷል!";
                        }
                    }
                }
            } else {
                $error = "እባክዎ መምህር፣ ክፍል እና የትምህርት ዓይነት በትክክል ይምረጡ! የትምህርት ዓይነት መምረጥ ግዴታ ነው።";
            }
        }
        
        if (isset($_POST['remove_assignment'])) {
            $assignment_id = intval($_POST['assignment_id'] ?? 0);
            if ($assignment_id > 0) {
                $deleted = dbExecute($conn, "DELETE FROM teacher_class WHERE id = ?", "i", [$assignment_id]);
                if ($deleted) {
                    $message = "ምደባው በተሳካ ሁኔታ ተሰርዟል!";
                } else {
                    $error = "ስህተት ተከስቷል!";
                }
            }
        }
    }
}

// Get all subjects
$all_subjects = getSubjects($conn);

// Get all teachers
$teachers_query = "SELECT * FROM users WHERE role='teacher' ORDER BY name";
$teachers = mysqli_query($conn, $teachers_query);

// Get all classes
$classes_query = "SELECT * FROM classes ORDER BY name";
$classes = mysqli_query($conn, $classes_query);

// Get all assignments for current semester with subject
$assignments_list = dbFetchAll(
    $conn,
    "SELECT tc.*, u.name as teacher_name, c.name as class_name, c.id as class_id,
            COALESCE(s.name, tc.subject_name, '') as subject_name
     FROM teacher_class tc
     JOIN users u ON tc.teacher_id = u.id
     JOIN classes c ON tc.class_id = c.id
     LEFT JOIN subjects s ON tc.subject_id = s.id
     WHERE tc.semester_id = ?
     ORDER BY c.name, u.name",
    "i",
    [$semester_id]
);

// Group assignments by class
$class_assignments = [];
foreach ($assignments_list as $assignment) {
    $class_assignments[$assignment['class_name']][] = $assignment;
}

$classes_arr = [];
mysqli_data_seek($classes, 0);
while ($c = mysqli_fetch_assoc($classes)) {
    $classes_arr[] = $c;
}

$teacher_assignments_data = [];
mysqli_data_seek($teachers, 0);
while($teacher = mysqli_fetch_assoc($teachers)) {
    $tid = intval($teacher['id']);
    $assigned_classes = [];
    $total_classes = 0;

    foreach ($assignments_list as $a) {
        if (intval($a['teacher_id']) === $tid) {
            $assigned_classes[] = [
                'name'          => $a['class_name'],
                'subject'       => $a['subject_name'] ?? '',
                'locked'        => $a['locked'],
                'assignment_id' => $a['id']
            ];
            $total_classes++;
        }
    }

    $teacher_assignments_data[] = [
        'id'      => $tid,
        'name'    => $teacher['name'],
        'phone'   => $teacher['phone'] ?: '---',
        'classes' => $assigned_classes,
        'total'   => $total_classes
    ];
}

$nav_active = 'manage_assignments';
?>
<!DOCTYPE html>
<html lang="am">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>መምህራን እና ክፍሎች ምደባ | አጸደ ትጉሃን</title>
    <?php include 'pwa_head.php'; ?>
    <style>
        :root {
            --brown-dark: #8B4513;
            --brown-medium: #A52A2A;
            --gold-primary: #FFD700;
            --gold-dark: #DAA520;
            --gold-pale: #FFF8DC;
            --bg-cream: #FAF9F6;
            --card-bg: #FFFFFF;
            --text-main: #1F2937;
            --text-muted: #6B7280;
            --border-color: #E5E7EB;
            --success: #10B981;
            --error: #EF4444;
            --info: #3B82F6;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Segoe UI', system-ui, -apple-system, sans-serif; }
        body { background: var(--bg-cream); color: var(--text-main); min-height: 100vh; }

        .main-container { max-width: 1200px; margin: 24px auto; padding: 0 16px 80px; }

        /* Page Header Card */
        .page-header-card {
            background: linear-gradient(135deg, #8B4513 0%, #A52A2A 100%);
            border-radius: 16px;
            padding: 24px 28px;
            color: white;
            margin-bottom: 24px;
            box-shadow: 0 8px 24px rgba(139, 69, 19, 0.18);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 16px;
            position: relative;
            overflow: hidden;
        }
        .page-header-card::after {
            content: '📌';
            position: absolute;
            right: 20px;
            bottom: -15px;
            font-size: 100px;
            opacity: 0.12;
            pointer-events: none;
        }
        .header-info h1 {
            font-size: 22px;
            font-weight: 800;
            color: var(--gold-primary);
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 6px;
        }
        .header-info p {
            font-size: 13.5px;
            color: rgba(255, 255, 255, 0.9);
        }
        .semester-pill {
            background: rgba(255, 215, 0, 0.2);
            border: 1px solid var(--gold-primary);
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 12.5px;
            font-weight: 700;
            color: var(--gold-primary);
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        /* Stats Row */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 16px;
            margin-bottom: 24px;
        }
        .stat-box {
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: 14px;
            padding: 18px 20px;
            display: flex;
            align-items: center;
            gap: 16px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.04);
        }
        .stat-icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            background: var(--gold-pale);
            color: var(--brown-dark);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            flex-shrink: 0;
        }
        .stat-data .stat-val {
            font-size: 24px;
            font-weight: 800;
            color: var(--brown-dark);
        }
        .stat-data .stat-lbl {
            font-size: 12px;
            color: var(--text-muted);
            font-weight: 600;
        }

        /* Alerts */
        .message {
            padding: 14px 18px;
            border-radius: 12px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
            font-weight: 600;
            font-size: 14px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }
        .message.success { background: #DCFCE7; color: #166534; border-left: 4px solid var(--success); }
        .message.error { background: #FEE2E2; color: #991B1B; border-left: 4px solid var(--error); }

        /* Card Container */
        .content-card {
            background: var(--card-bg);
            border-radius: 16px;
            padding: 24px;
            margin-bottom: 24px;
            border: 1px solid var(--border-color);
            box-shadow: 0 4px 16px rgba(0,0,0,0.05);
        }
        .content-card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 12px;
            border-bottom: 2px solid var(--gold-pale);
            flex-wrap: wrap;
            gap: 10px;
        }
        .content-card-header h2 {
            font-size: 17px;
            color: var(--brown-dark);
            display: flex;
            align-items: center;
            gap: 8px;
            font-weight: 700;
        }

        /* Forms */
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(230px, 1fr));
            gap: 16px;
            margin-bottom: 16px;
        }
        .form-group {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }
        .form-group label {
            font-size: 13px;
            font-weight: 600;
            color: var(--brown-dark);
        }
        .form-control {
            width: 100%;
            padding: 11px 14px;
            border: 1.5px solid var(--border-color);
            border-radius: 10px;
            font-size: 14px;
            outline: none;
            transition: all 0.2s ease;
            background: var(--card-bg);
            color: var(--text-main);
        }
        .form-control:focus {
            border-color: var(--gold-dark);
            box-shadow: 0 0 0 3px rgba(218, 165, 32, 0.15);
        }
        .btn-primary-action {
            background: linear-gradient(135deg, var(--gold-primary) 0%, var(--gold-dark) 100%);
            color: var(--brown-dark);
            border: none;
            padding: 12px 24px;
            border-radius: 10px;
            font-size: 14px;
            font-weight: 700;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.2s ease;
            box-shadow: 0 3px 10px rgba(218, 165, 32, 0.25);
        }
        .btn-primary-action:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(218, 165, 32, 0.35);
        }

        /* Tabs Navigation */
        .view-tabs {
            display: flex;
            gap: 8px;
            margin-bottom: 20px;
            border-bottom: 2px solid var(--border-color);
            padding-bottom: 8px;
        }
        .tab-btn {
            background: transparent;
            border: none;
            padding: 10px 18px;
            border-radius: 10px;
            font-weight: 700;
            font-size: 13.5px;
            color: var(--text-muted);
            cursor: pointer;
            transition: all 0.2s;
        }
        .tab-btn.active {
            background: var(--gold-pale);
            color: var(--brown-dark);
            box-shadow: 0 2px 6px rgba(139, 69, 19, 0.08);
        }

        /* Assignments Card Grid */
        .assignments-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
            gap: 20px;
        }
        .group-card {
            background: var(--card-bg);
            border-radius: 16px;
            border: 1.5px solid var(--border-color);
            padding: 20px;
            box-shadow: 0 4px 14px rgba(0,0,0,0.04);
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            transition: all 0.2s;
        }
        .group-card:hover {
            border-color: var(--gold-dark);
            box-shadow: 0 8px 20px rgba(139, 69, 19, 0.08);
        }
        .group-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 14px;
            padding-bottom: 8px;
            border-bottom: 1.5px solid var(--gold-pale);
        }
        .group-title {
            font-size: 16.5px;
            font-weight: 800;
            color: var(--brown-dark);
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .group-badge {
            background: var(--gold-pale);
            color: var(--brown-dark);
            padding: 3px 8px;
            border-radius: 12px;
            font-size: 11.5px;
            font-weight: 700;
        }

        /* Items List */
        .items-list {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }
        .item-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: #F9FAFB;
            border-radius: 10px;
            padding: 10px 12px;
            border: 1px solid #F3F4F6;
            font-size: 13px;
        }
        .item-info {
            display: flex;
            flex-direction: column;
            gap: 2px;
        }
        .item-primary { font-weight: 700; color: var(--text-main); }
        .item-secondary { font-size: 11.5px; color: var(--text-muted); }
        .item-chip {
            background: #EFF6FF;
            color: #1E40AF;
            padding: 2px 6px;
            border-radius: 6px;
            font-size: 11px;
            font-weight: 700;
            display: inline-block;
        }
        .item-chip.locked { background: #FEE2E2; color: #991B1B; }

        .btn-remove-asg {
            background: transparent;
            border: none;
            color: var(--error);
            font-size: 14px;
            cursor: pointer;
            padding: 4px;
            border-radius: 6px;
            transition: all 0.2s;
        }
        .btn-remove-asg:hover {
            background: #FEE2E2;
        }

        @media (max-width: 768px) {
            .main-container { padding: 0 10px 40px; margin: 12px auto; }
            .page-header-card { padding: 18px 16px; }
            .content-card { padding: 16px 14px; border-radius: 14px; }
            .assignments-grid { grid-template-columns: 1fr; }
            .btn-primary-action { width: 100%; justify-content: center; min-height: 44px; }
        }
    </style>
</head>
<body>
    <?php include 'mobile_nav.php'; ?>

    <div class="main-container">
        <!-- Header -->
        <div class="page-header-card">
            <div class="header-info">
                <h1>📌 የመምህራን እና ክፍሎች ምደባ</h1>
                <p>መምህራንን ለሚገቡባቸው ክፍሎች እና ለሚያስተምሯቸው የትምህርት ዓይነቶች ይመድቡ።</p>
            </div>
            <?php if ($current_semester): ?>
            <div class="semester-pill">
                <span>📅</span> <?php echo htmlspecialchars($current_semester['name'] ?? 'ሴሚስተር'); ?> (<?php echo htmlspecialchars($current_semester['ethiopian_year'] ?? ''); ?> ዓ.ም)
            </div>
            <?php endif; ?>
        </div>

        <?php if($message): ?>
        <div class="message success">✅ <?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?></div>
        <?php endif; ?>

        <?php if($error): ?>
        <div class="message error">⚠️ <?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
        <?php endif; ?>

        <!-- Stats -->
        <div class="stats-grid">
            <div class="stat-box">
                <div class="stat-icon">📌</div>
                <div class="stat-data">
                    <div class="stat-val"><?php echo count($assignments_list); ?></div>
                    <div class="stat-lbl">ጠቅላላ ምደባዎች</div>
                </div>
            </div>
            <div class="stat-box">
                <div class="stat-icon">🏫</div>
                <div class="stat-data">
                    <div class="stat-val"><?php echo count($classes_arr); ?></div>
                    <div class="stat-lbl">የሚገኙ ክፍሎች</div>
                </div>
            </div>
            <div class="stat-box">
                <div class="stat-icon">👨‍🏫</div>
                <div class="stat-data">
                    <div class="stat-val"><?php echo count($teacher_assignments_data); ?></div>
                    <div class="stat-lbl">የሚገኙ መምህራን</div>
                </div>
            </div>
        </div>

        <!-- Add Assignment Form -->
        <div class="content-card">
            <div class="content-card-header">
                <h2><span>➕</span> አዲስ ምደባ መዝግብ</h2>
            </div>
            <form method="POST">
                <?php echo csrfField(); ?>
                <div class="form-grid">
                    <div class="form-group">
                        <label>👨‍🏫 መምህር ይምረጡ <span style="color:var(--error);">*</span></label>
                        <select name="teacher_id" class="form-control" required>
                            <option value="">-- መምህር ምረጥ --</option>
                            <?php 
                            mysqli_data_seek($teachers, 0);
                            while($t = mysqli_fetch_assoc($teachers)): 
                            ?>
                            <option value="<?php echo $t['id']; ?>"><?php echo htmlspecialchars($t['name']); ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>🏫 ክፍል ይምረጡ <span style="color:var(--error);">*</span></label>
                        <select name="class_id" class="form-control" required>
                            <option value="">-- ክፍል ምረጥ --</option>
                            <?php foreach($classes_arr as $c): ?>
                            <option value="<?php echo $c['id']; ?>"><?php echo htmlspecialchars($c['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>📖 የትምህርት ዓይነት <span style="color:var(--error);">*</span></label>
                        <select name="subject_id" class="form-control" required>
                            <option value="">-- ትምህርት ምረጥ --</option>
                            <?php foreach($all_subjects as $s): ?>
                            <option value="<?php echo $s['id']; ?>"><?php echo htmlspecialchars($s['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <button type="submit" name="add_assignment" class="btn-primary-action">
                    ➕ ምደባ መዝግብ
                </button>
            </form>
        </div>

        <!-- Assignments Display -->
        <div class="content-card">
            <div class="content-card-header">
                <h2><span>📚</span> የተመዘገቡ ምደባዎች ዝርዝር</h2>
                <div class="view-tabs" style="margin-bottom:0; border-bottom:none;">
                    <button class="tab-btn active" id="tabClassBtn" onclick="switchTab('class')">🏫 በክፍል አደራጅ</button>
                    <button class="tab-btn" id="tabTeacherBtn" onclick="switchTab('teacher')">👨‍🏫 በመምህር አደራጅ</button>
                </div>
            </div>

            <!-- Instant Search -->
            <div style="margin-bottom: 20px;">
                <input type="text" id="asgSearch" class="form-control" placeholder="🔍 የክፍል፣ የመምህር ወይም የትምህርት ዓይነት ስም ይፈልጉ..." onkeyup="filterAssignments()">
            </div>

            <!-- View 1: Grouped by Class -->
            <div id="viewByClass" class="assignments-grid">
                <?php if(empty($class_assignments)): ?>
                <div style="grid-column: 1 / -1; text-align:center; padding:40px; color:var(--text-muted);">
                    ምንም የተመዘገበ ምደባ የለም።
                </div>
                <?php else: foreach($class_assignments as $cName => $asgs): ?>
                <div class="group-card asg-item-card" data-search="<?php echo htmlspecialchars(strtolower($cName . ' ' . implode(' ', array_column($asgs, 'teacher_name')) . ' ' . implode(' ', array_column($asgs, 'subject_name')))); ?>">
                    <div>
                        <div class="group-header">
                            <div class="group-title">
                                🏫 <?php echo htmlspecialchars($cName); ?>
                            </div>
                            <span class="group-badge"><?php echo count($asgs); ?> መምህራን</span>
                        </div>
                        <div class="items-list">
                            <?php foreach($asgs as $a): ?>
                            <div class="item-row">
                                <div class="item-info">
                                    <span class="item-primary">👨‍🏫 <?php echo htmlspecialchars($a['teacher_name']); ?></span>
                                    <span class="item-secondary">
                                        <span class="item-chip <?php echo $a['locked'] ? 'locked' : ''; ?>">
                                            <?php echo $a['locked'] ? '🔒' : '📖'; ?> <?php echo htmlspecialchars($a['subject_name'] ?: 'ትምህርት'); ?>
                                        </span>
                                    </span>
                                </div>
                                <form method="POST" onsubmit="return confirm('የ[<?php echo htmlspecialchars(addslashes($a['teacher_name'])); ?>] ምደባ ከዚህ ክፍል መሰረዝ እርግጠኛ ነዎት?')">
                                    <?php echo csrfField(); ?>
                                    <input type="hidden" name="assignment_id" value="<?php echo $a['id']; ?>">
                                    <button type="submit" name="remove_assignment" class="btn-remove-asg" title="ምደባ ሰርዝ">
                                        🗑️
                                    </button>
                                </form>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; endif; ?>
            </div>

            <!-- View 2: Grouped by Teacher -->
            <div id="viewByTeacher" class="assignments-grid" style="display:none;">
                <?php foreach($teacher_assignments_data as $td): ?>
                <div class="group-card asg-item-card" data-search="<?php echo htmlspecialchars(strtolower($td['name'] . ' ' . implode(' ', array_column($td['classes'], 'name')) . ' ' . implode(' ', array_column($td['classes'], 'subject')))); ?>">
                    <div>
                        <div class="group-header">
                            <div class="group-title">
                                👨‍🏫 <?php echo htmlspecialchars($td['name']); ?>
                            </div>
                            <span class="group-badge"><?php echo $td['total']; ?> ክፍሎች</span>
                        </div>
                        <div class="items-list">
                            <?php if(empty($td['classes'])): ?>
                            <div style="font-size:12px; color:#999; font-style:italic; padding:6px 0;">ምንም የተመደበ ክፍል የለም</div>
                            <?php else: foreach($td['classes'] as $tc): ?>
                            <div class="item-row">
                                <div class="item-info">
                                    <span class="item-primary">🏫 <?php echo htmlspecialchars($tc['name']); ?></span>
                                    <span class="item-secondary">
                                        <span class="item-chip <?php echo $tc['locked'] ? 'locked' : ''; ?>">
                                            <?php echo $tc['locked'] ? '🔒' : '📖'; ?> <?php echo htmlspecialchars($tc['subject'] ?: 'ትምህርት'); ?>
                                        </span>
                                    </span>
                                </div>
                                <form method="POST" onsubmit="return confirm('ይህን ምደባ መሰረዝ እርግጠኛ ነዎት?')">
                                    <?php echo csrfField(); ?>
                                    <input type="hidden" name="assignment_id" value="<?php echo $tc['assignment_id']; ?>">
                                    <button type="submit" name="remove_assignment" class="btn-remove-asg" title="ምደባ ሰርዝ">
                                        🗑️
                                    </button>
                                </form>
                            </div>
                            <?php endforeach; endif; ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <script>
        function switchTab(type) {
            var classView = document.getElementById('viewByClass');
            var teacherView = document.getElementById('viewByTeacher');
            var tabClassBtn = document.getElementById('tabClassBtn');
            var tabTeacherBtn = document.getElementById('tabTeacherBtn');

            if (type === 'class') {
                classView.style.display = 'grid';
                teacherView.style.display = 'none';
                tabClassBtn.classList.add('active');
                tabTeacherBtn.classList.remove('active');
            } else {
                classView.style.display = 'none';
                teacherView.style.display = 'grid';
                tabClassBtn.classList.remove('active');
                tabTeacherBtn.classList.add('active');
            }
        }

        function filterAssignments() {
            var q = document.getElementById('asgSearch').value.toLowerCase();
            var cards = document.querySelectorAll('.asg-item-card');
            cards.forEach(function(c) {
                var searchData = c.getAttribute('data-search');
                if (!q || searchData.indexOf(q) > -1) {
                    c.style.display = 'flex';
                } else {
                    c.style.display = 'none';
                }
            });
        }
    </script>
</body>
</html>
<?php mysqli_close($conn); ?>