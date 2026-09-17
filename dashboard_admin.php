<?php
require_once 'db.php';
requireAdmin();

$current_semester = getCurrentSemester($conn);
$semester_id = $current_semester ? intval($current_semester['id']) : 0;

// Get statistics with proper error handling
$stats_query = "SELECT 
    (SELECT COUNT(*) FROM users WHERE role = 'teacher') as total_teachers,
    (SELECT COUNT(*) FROM classes) as total_classes,
    (SELECT COUNT(*) FROM students WHERE (is_deleted = 0 OR is_deleted IS NULL)) as total_students";
    
if ($semester_id > 0) {
    $stats_query .= ", (SELECT COUNT(*) FROM teacher_class WHERE semester_id = $semester_id) as assigned_classes";
} else {
    $stats_query .= ", 0 as assigned_classes";
}

$stats_result = mysqli_query($conn, $stats_query);
$stats = $stats_result ? mysqli_fetch_assoc($stats_result) : ['total_teachers' => 0, 'total_classes' => 0, 'total_students' => 0, 'assigned_classes' => 0];

// Division split + pending-work counters (safe no-ops if migrations 002/004/005 aren't applied yet)
$children_students = 0; $youth_students = 0; $pending_plans = 0; $next_exam = null;
$divisionsExist = @mysqli_query($conn, "SHOW TABLES LIKE 'divisions'");
if ($divisionsExist && mysqli_num_rows($divisionsExist) > 0) {
    $row = dbFetchOne($conn, "SELECT COUNT(*) as cnt FROM students s JOIN classes c ON s.class_id = c.id JOIN grades g ON c.grade_id = g.id JOIN divisions d ON g.division_id = d.id WHERE d.code = 'CHILDREN' AND (s.is_deleted = 0 OR s.is_deleted IS NULL)");
    $children_students = $row ? (int)$row['cnt'] : 0;
    $row = dbFetchOne($conn, "SELECT COUNT(*) as cnt FROM students s JOIN classes c ON s.class_id = c.id JOIN grades g ON c.grade_id = g.id JOIN divisions d ON g.division_id = d.id WHERE d.code = 'YOUTH' AND (s.is_deleted = 0 OR s.is_deleted IS NULL)");
    $youth_students = $row ? (int)$row['cnt'] : 0;

    $row = dbFetchOne($conn, "SELECT id, title, event_date FROM calendar_events WHERE event_type = 'exam' AND is_deleted = 0 AND event_date >= CURDATE() ORDER BY event_date ASC LIMIT 1");
    $next_exam = $row;
}
$plansExist = @mysqli_query($conn, "SHOW TABLES LIKE 'lesson_plans'");
if ($plansExist && mysqli_num_rows($plansExist) > 0) {
    $row = dbFetchOne($conn, "SELECT COUNT(*) as cnt FROM lesson_plans WHERE status = 'submitted' AND is_deleted = 0");
    $pending_plans = $row ? (int)$row['cnt'] : 0;
}

// Get all teachers with their assigned classes
$teachers_query = "SELECT u.*, 
                   IFNULL(GROUP_CONCAT(CONCAT(c.name, ' (', s.name, ')') SEPARATOR '<br>'), 'አልተመደበለትም') as classes
                   FROM users u
                   LEFT JOIN teacher_class tc ON u.id = tc.teacher_id " . 
                   ($semester_id > 0 ? " AND tc.semester_id = $semester_id" : "") . "
                   LEFT JOIN classes c ON tc.class_id = c.id
                   LEFT JOIN semesters s ON tc.semester_id = s.id
                   WHERE u.role = 'teacher'
                   GROUP BY u.id";
$teachers = mysqli_query($conn, $teachers_query);

// Get classes with student counts
$classes_query = "SELECT c.*, COUNT(s.id) as student_count,
                  tc.teacher_id, u.name as teacher_name
                  FROM classes c
                  LEFT JOIN students s ON c.id = s.class_id AND (s.is_deleted = 0 OR s.is_deleted IS NULL)
                  LEFT JOIN teacher_class tc ON c.id = tc.class_id " . 
                  ($semester_id > 0 ? " AND tc.semester_id = $semester_id" : "") . "
                  LEFT JOIN users u ON tc.teacher_id = u.id
                  GROUP BY c.id";
$classes = mysqli_query($conn, $classes_query);

// Get recent marks
$recent_marks = null;
if ($semester_id > 0) {
    $marks_query = "SELECT s.name as student_name, c.name as class_name, 
                    m.assignment, m.participation, m.attendance, m.mid, m.final, m.total
                    FROM marks m
                    JOIN students s ON m.student_id = s.id
                    JOIN classes c ON m.class_id = c.id
                    WHERE m.semester_id = ? AND (s.is_deleted = 0 OR s.is_deleted IS NULL)
                    ORDER BY m.last_updated DESC LIMIT 10";
    $recent_marks = dbQuery($conn, $marks_query, "i", [$semester_id]);
}

$nav_active = 'dashboard_admin';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>የትምህርት ክፍል ዳሽቦርድ | አጸደ ትጉሃን</title>
    <?php include 'pwa_head.php'; ?>
    <style>
        :root {
            --brown-dark: #8B4513;
            --brown-medium: #A52A2A;
            --brown-light: #CD853F;
            --gold-primary: #FFD700;
            --gold-dark: #DAA520;
            --gold-light: #FBBF24;
            --gold-pale: #FFF8DC;
            --bg-cream: #FAF9F6;
            --success-green: #10B981;
            --error-red: #EF4444;
            --info-blue: #3B82F6;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Segoe UI', sans-serif; }
        body { background: var(--bg-cream); }

        .header {
            background: linear-gradient(135deg, #8B4513 0%, #A52A2A 100%);
            color: white; padding: 20px 30px; box-shadow: 0 4px 15px rgba(0,0,0,0.2);
        }
        .header-content {
            max-width: 1400px; margin: 0 auto;
            display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px;
        }
        .logo-area { display: flex; align-items: center; gap: 15px; }
        .logo-img {
            width: 50px; height: 50px; border-radius: 50%; object-fit: cover;
            border: 3px solid var(--gold-primary); background: white;
        }
        .title h1 { font-size: 20px; color: var(--gold-primary); }
        .title p { font-size: 14px; color: var(--gold-light); }

        .user-info { display: flex; align-items: center; gap: 15px; }
        .user-name {
            text-align: right; background: rgba(0,0,0,0.2); padding: 10px 20px; border-radius: 10px;
        }
        .user-name strong { color: var(--gold-primary); }

        .btn {
            padding: 10px 20px; border: none; border-radius: 8px; cursor: pointer;
            font-weight: 600; transition: all 0.3s; text-decoration: none;
            display: inline-flex; align-items: center; gap: 8px; font-size: 14px;
        }
        .btn-logout { background: var(--gold-primary); color: var(--brown-dark); }
        .btn-logout:hover { background: var(--gold-dark); transform: translateY(-2px); }
        .btn-password { background: var(--success-green); color: white; margin-right: 5px; }
        .btn-password:hover { background: #059669; transform: translateY(-2px); }
        .btn-settings { background: var(--info-blue); color: white; margin-right: 5px; }
        .btn-settings:hover { background: #2563EB; transform: translateY(-2px); }

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

        .container { max-width: 1400px; margin: 30px auto; padding: 0 30px; }

        .warning-message {
            background: #FEF3C7; color: #D97706; padding: 14px; border-radius: 12px;
            margin-bottom: 30px; display: flex; align-items: center; gap: 10px; border-left: 5px solid #D97706;
        }
        .warning-message span { font-size: 24px; }

        .stats-grid {
            display: grid; grid-template-columns: 1fr 1fr;
            gap: 8px; margin-bottom: 24px;
        }
        .stat-card {
            background: white; border-radius: 12px; padding: 12px 8px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1); border-left: 5px solid var(--gold-primary);
            transition: transform 0.3s;
        }
        .stat-card:hover { transform: translateY(-5px); }
        .stat-icon { font-size: 24px; color: var(--gold-dark); margin-bottom: 8px; }
        .stat-value { font-size: 20px; font-weight: bold; color: var(--brown-dark); margin-bottom: 5px; }
        .stat-label { color: #666; font-size: 11px; }

        .section {
            background: white; border-radius: 12px; padding: 16px 12px; margin-bottom: 20px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        .section-header {
            display: flex; justify-content: space-between; align-items: center;
            margin-bottom: 20px; padding-bottom: 15px; border-bottom: 2px solid var(--gold-pale);
            flex-wrap: wrap; gap: 15px;
        }
        .section-header h2 { color: var(--brown-dark); font-size: 17px; display: flex; align-items: center; gap: 10px; }

        .semester-badge {
            background: linear-gradient(135deg, var(--gold-primary), var(--gold-dark));
            color: var(--brown-dark); padding: 8px 15px; border-radius: 20px; font-weight: bold; font-size: 14px;
        }

        .exam-notice-banner {
            background: #FFF8DC;
            border: 1.5px solid #FCD34D;
        }

        .table-responsive { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; }
        th { background: var(--gold-pale); color: var(--brown-dark); padding: 15px; text-align: left; font-weight: 600; }
        td { padding: 12px 15px; border-bottom: 1px solid #E2E8F0; }
        tr:hover { background: #FEF9E7; }
        .teacher-class { color: var(--brown-medium); font-weight: 500; }

        .btn-primary {
            padding: 10px 20px; background: linear-gradient(135deg, #FFD700 0%, #DAA520 100%);
            color: #8B4513; border: none; border-radius: 8px; cursor: pointer; font-weight: 600;
            transition: all 0.3s; text-decoration: none; display: inline-flex; align-items: center; gap: 8px; font-size: 14px;
        }
        .btn-primary:hover { transform: translateY(-2px); box-shadow: 0 5px 15px rgba(139,69,19,0.3); }

        .marks-table { font-size: 14px; }
        .marks-table .total { font-weight: bold; color: var(--brown-dark); }
        .grade-badge { padding: 5px 10px; border-radius: 15px; font-size: 12px; font-weight: 600; }

        @media (max-width: 768px) {
            .header-content { flex-direction: column; text-align: center; }
            .user-info { flex-direction: column; }
            .nav-links { justify-content: center; }
            .main-container { padding: 0 12px 30px; margin: 15px auto; }
            .section { padding: 16px 12px; border-radius: 12px; margin-bottom: 20px; }
            .section-header h2 { font-size: 17px; }
            .stats-grid { grid-template-columns: 1fr 1fr; gap: 8px; margin-bottom: 24px; }
            .stat-card { padding: 12px 8px; border-radius: 12px; }
            .stat-icon { font-size: 24px; margin-bottom: 8px; }
            .stat-value { font-size: 20px; }
            .stat-label { font-size: 11px; }
            .table-responsive table { min-width: 560px; }
            th, td { padding: 10px 8px; font-size: 13px; }
            .warning-message { padding: 14px; gap: 10px; font-size: 13px; }
        }
        @media (max-width: 420px) {
            .stats-grid { grid-template-columns: 1fr 1fr; gap: 8px; }
            .stat-card { padding: 12px 8px; }
            .stat-value { font-size: 20px; }
            .stat-label { font-size: 11px; }
        }
    </style>
</head>
<body>
    <?php include 'mobile_nav.php'; ?>

    <div class="main-container">
        <?php if(!$current_semester): ?>
        <div class="warning-message">
            <span>⚠️</span>
            <div>
                <strong>ምንም active ሴሚስተር የለም!</strong><br>
                እባክዎ መጀመሪያ ሴሚስተር ይክፈቱ።
                <a href="semester.php" class="btn-primary" style="margin-top: 10px; display: inline-block;">📅 ሴሚስተር ክፈት</a>
            </div>
        </div>
        <?php endif; ?>

        <!-- Stats Cards -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon">👨‍🏫</div>
                <div class="stat-value"><?php echo $stats['total_teachers']; ?></div>
                <div class="stat-label">መምህራን</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">📚</div>
                <div class="stat-value"><?php echo $stats['total_classes']; ?></div>
                <div class="stat-label">ክፍሎች</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">👧👦</div>
                <div class="stat-value"><?php echo $stats['total_students']; ?></div>
                <div class="stat-label">ተማሪዎች</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">✓</div>
                <div class="stat-value"><?php echo $stats['assigned_classes']; ?></div>
                <div class="stat-label">የተመደቡ ክፍሎች</div>
            </div>
            <?php if ($divisionsExist && mysqli_num_rows($divisionsExist) > 0): ?>
            <div class="stat-card">
                <div class="stat-icon">👧</div>
                <div class="stat-value"><?php echo $children_students; ?></div>
                <div class="stat-label">የህፃናት ተማሪዎች</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">👦</div>
                <div class="stat-value"><?php echo $youth_students; ?></div>
                <div class="stat-label">የወጣቶች ተማሪዎች</div>
            </div>
            <?php endif; ?>
            <?php if ($plansExist && mysqli_num_rows($plansExist) > 0): ?>
            <div class="stat-card">
                <div class="stat-icon">📝</div>
                <div class="stat-value"><?php echo $pending_plans; ?></div>
                <div class="stat-label">ያልታዩ የትምህርት ዕቅዶች</div>
            </div>
            <?php endif; ?>
        </div>

        <?php if ($next_exam): ?>
        <div class="section exam-notice-banner" style="border-radius:14px; padding:15px 20px; margin-bottom:20px;">
            <strong>📝 ቀጣይ ፈተና:</strong> <?php echo htmlspecialchars($next_exam['title']); ?>
            — <?php $d = (new DateTime('today'))->diff(new DateTime($next_exam['event_date']))->days; ?>
            በ<?php echo $d; ?> ቀን ውስጥ
        </div>
        <?php endif; ?>

        <!-- Teachers Section -->
        <div class="section">
            <div class="section-header">
                <h2><span>👨‍🏫</span> መምህራን እና የተመደቡላቸው ክፍሎች</h2>
                <?php if($current_semester): ?>
                <div class="semester-badge">
                    <?php echo htmlspecialchars($current_semester['name']); ?>
                </div>
                <?php endif; ?>
            </div>
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>ስም</th>
                            <th>ስልክ ቁጥር</th>
                            <th>የተመደበላቸው ክፍል</th>
                            <th>ሁኔታ</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if($teachers && mysqli_num_rows($teachers) > 0): ?>
                            <?php while($teacher = mysqli_fetch_assoc($teachers)): ?>
                            <tr>
                                <td class="teacher-class"><?php echo htmlspecialchars($teacher['name']); ?></td>
                                <td><?php echo htmlspecialchars($teacher['phone'] ?: '---'); ?></td>
                                <td><?php echo $teacher['classes']; ?></td>
                                <td>
                                    <?php if($teacher['classes'] != 'አልተመደበለትም'): ?>
                                    <span style="color: var(--success-green);">✅ ተመድቧል</span>
                                    <?php else: ?>
                                    <span style="color: var(--error-red);">⏳ አልተመደበለትም</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="4" style="text-align: center; padding: 30px; color: #666;">
                                    ምንም መምህራን አልተገኙም
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Classes Section -->
        <div class="section">
            <div class="section-header">
                <h2><span>📚</span> ክፍሎች እና ተማሪዎች</h2>
                <div class="action-buttons">
                    <a href="print_results.php" class="btn-primary">🖨️ ሁሉንም አትም</a>
                </div>
            </div>
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>ክፍል</th>
                            <th>የተማሪዎች ቁጥር</th>
                            <th>ኃላፊ መምህር</th>
                            <th>ተግባር</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if($classes && mysqli_num_rows($classes) > 0): ?>
                            <?php while($class = mysqli_fetch_assoc($classes)): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($class['name']); ?></strong></td>
                                <td><?php echo $class['student_count']; ?> ተማሪዎች</td>
                                <td><?php echo htmlspecialchars($class['teacher_name'] ?: 'አልተመደበም'); ?></td>
                                <td>
                                    <a href="print_results.php?class_id=<?php echo $class['id']; ?>" 
                                       class="btn-primary" style="padding: 5px 10px; font-size: 12px;">
                                       🖨️ አትም
                                    </a>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="4" style="text-align: center; padding: 30px; color: #666;">
                                    ምንም ክፍሎች አልተገኙም
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Recent Marks Section -->
        <?php if($current_semester && $recent_marks && mysqli_num_rows($recent_marks) > 0): ?>
        <div class="section">
            <div class="section-header">
                <h2><span>📊</span> የቅርብ ጊዜ ውጤቶች</h2>
            </div>
            <div class="table-responsive">
                <table class="marks-table">
                    <thead>
                        <tr>
                            <th>ተማሪ</th>
                            <th>ክፍል</th>
                            <th>የቤት ሥራ / ተግባር</th>
                            <th>የአጋማሽ ፈተና</th>
                            <th>የማጠቃለያ ፈተና</th>
                            <th>ድምር</th>
                            <th>ደረጃ</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while($mark = mysqli_fetch_assoc($recent_marks)): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($mark['student_name']); ?></td>
                            <td><?php echo htmlspecialchars($mark['class_name']); ?></td>
                            <td><?php echo formatMark($mark['assignment']); ?></td>
                            <td><?php echo formatMark($mark['mid']); ?></td>
                            <td><?php echo formatMark($mark['final']); ?></td>
                            <td class="total"><?php echo formatMark($mark['total']); ?></td>
                            <td>
                                <span class="grade-badge" style="background: <?php echo getGradeColor($mark['total']); ?>20; color: <?php echo getGradeColor($mark['total']); ?>;">
                                    <?php echo getGradeStatus($mark['total']); ?>
                                </span>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php elseif($current_semester): ?>
        <div class="section">
            <div style="text-align: center; padding: 30px; color: #666;">
                <span style="font-size: 40px; display: block; margin-bottom: 10px;">📊</span>
                እስካሁን ምንም ውጤት አልገባም
            </div>
        </div>
        <?php endif; ?>
    </div>
</body>
</html>
<?php mysqli_close($conn); ?>