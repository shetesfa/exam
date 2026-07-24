<?php
session_start();
require_once 'db.php';
requireAdmin();

$current_semester = getCurrentSemester($conn);
$semester_id = $current_semester ? $current_semester['id'] : 0;

// Get statistics with proper error handling
$stats_query = "SELECT 
    (SELECT COUNT(*) FROM users WHERE role = 'teacher') as total_teachers,
    (SELECT COUNT(*) FROM classes) as total_classes,
    (SELECT COUNT(*) FROM students) as total_students";
    
if($semester_id > 0) {
    $stats_query .= ", (SELECT COUNT(*) FROM teacher_class WHERE semester_id = $semester_id) as assigned_classes";
} else {
    $stats_query .= ", 0 as assigned_classes";
}

$stats_result = mysqli_query($conn, $stats_query);
$stats = $stats_result ? mysqli_fetch_assoc($stats_result) : ['total_teachers' => 0, 'total_classes' => 0, 'total_students' => 0, 'assigned_classes' => 0];

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
                  LEFT JOIN students s ON c.id = s.class_id
                  LEFT JOIN teacher_class tc ON c.id = tc.class_id " . 
                  ($semester_id > 0 ? " AND tc.semester_id = $semester_id" : "") . "
                  LEFT JOIN users u ON tc.teacher_id = u.id
                  GROUP BY c.id";
$classes = mysqli_query($conn, $classes_query);

// Get recent marks
$recent_marks = null;
if($semester_id > 0) {
    $marks_query = "SELECT s.name as student_name, c.name as class_name, 
                    m.assignment, m.participation, m.attendance, m.mid, m.final, m.total
                    FROM marks m
                    JOIN students s ON m.student_id = s.id
                    JOIN classes c ON m.class_id = c.id
                    WHERE m.semester_id = $semester_id
                    ORDER BY m.last_updated DESC LIMIT 10";
    $recent_marks = mysqli_query($conn, $marks_query);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/png" href="images/icon.png">
    <title>የአስተዳዳሪ ዳሽቦርድ | አጸደ ትጉሃን</title>
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
            background: #FEF3C7; color: #D97706; padding: 20px; border-radius: 12px;
            margin-bottom: 30px; display: flex; align-items: center; gap: 15px; border-left: 5px solid #D97706;
        }
        .warning-message span { font-size: 24px; }

        .stats-grid {
            display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 25px; margin-bottom: 40px;
        }
        .stat-card {
            background: white; border-radius: 15px; padding: 25px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1); border-left: 5px solid var(--gold-primary);
            transition: transform 0.3s;
        }
        .stat-card:hover { transform: translateY(-5px); }
        .stat-icon { font-size: 30px; color: var(--gold-dark); margin-bottom: 15px; }
        .stat-value { font-size: 32px; font-weight: bold; color: var(--brown-dark); margin-bottom: 5px; }
        .stat-label { color: #666; font-size: 14px; }

        .section {
            background: white; border-radius: 15px; padding: 25px; margin-bottom: 30px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        .section-header {
            display: flex; justify-content: space-between; align-items: center;
            margin-bottom: 20px; padding-bottom: 15px; border-bottom: 2px solid var(--gold-pale);
            flex-wrap: wrap; gap: 15px;
        }
        .section-header h2 { color: var(--brown-dark); font-size: 20px; display: flex; align-items: center; gap: 10px; }

        .semester-badge {
            background: linear-gradient(135deg, var(--gold-primary), var(--gold-dark));
            color: var(--brown-dark); padding: 8px 15px; border-radius: 20px; font-weight: bold; font-size: 14px;
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
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="header-content">
            <div class="logo-area">
                <img src="images/icon.png" alt="Logo" class="logo-img" onerror="this.style.display='none'; this.insertAdjacentHTML('afterend','<div class=logo-img style=background:gold;display:flex;align-items:center;justify-content:center;font-size:24px;color:#8B4513;>⛪</div>');">
                <div class="title">
                    <h1>አጸደ ትጉሃን ሰንበት ትምህርት ቤት</h1>
                    <p>የአስተዳዳሪ ዳሽቦርድ | Admin Dashboard</p>
                </div>
            </div>
            <div class="user-info">
                <div class="user-name">
                    <strong><?php echo htmlspecialchars($_SESSION['user_name'] ?? 'አስተዳዳሪ'); ?></strong><br>
                    <span style="font-size: 12px;">አስተዳዳሪ (Admin)</span>
                </div>
                <a href="admin_change_password.php" class="btn btn-password">🔒 የይለፍ ቃል ቀይር</a>
               
                <a href="logout.php" class="btn btn-logout">🚪 ውጣ</a>
            </div>
        </div>
    </div>

    <div class="nav-links">
        <a href="dashboard_admin.php" class="nav-link active">🏠 ዳሽቦርድ</a>
        <a href="manage_classes.php" class="nav-link">📚 ክፍሎች</a>
        <a href="manage_students.php" class="nav-link">👥 ተማሪዎች</a>
        <a href="manage_teachers.php" class="nav-link">👨‍🏫 መምህራን</a>
        <a href="manage_assignments.php" class="nav-link">📋 ክፍል ምደባ</a>
        <a href="semester.php" class="nav-link">📅 ሴሚስተር</a>
        <a href="class_locks.php" class="nav-link">🔒 ክፍል መቆለፊያ</a>
        <a href="attendance_submitter_assign.php" class="nav-link">📋 የክፍል አቴንዳንስ አባላት</a>
        <a href="attendance_days_control.php" class="nav-link">📅 የትምህርት ቀናት</a>   
        <a href="attendance_controller.php" class="nav-link">📊 የአቴንዳንስ መቆጣጠሪያ</a>
        <a href="teacher_marks_viewer.php" class="nav-link">👁️ የመምህራን ውጤት</a>
        <a href="print_results.php" class="nav-link">🖨️ ውጤት ማተሚያ</a>
        <a href="manage_users.php" class="nav-link">👤 ተጠቃሚዎች</a>
        <a href="admin_settings.php" class="nav-link">⚙️ ቅንብሮች</a>
    </div>

    <div class="container">
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
                <div class="stat-label">መምህራን (Teachers)</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">📚</div>
                <div class="stat-value"><?php echo $stats['total_classes']; ?></div>
                <div class="stat-label">ክፍሎች (Classes)</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">👧👦</div>
                <div class="stat-value"><?php echo $stats['total_students']; ?></div>
                <div class="stat-label">ተማሪዎች (Students)</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">✓</div>
                <div class="stat-value"><?php echo $stats['assigned_classes']; ?></div>
                <div class="stat-label">የተመደቡ ክፍሎች</div>
            </div>
        </div>

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
                            <th>ስም (Name)</th>
                            <th>ስልክ (Phone)</th>
                            <th>የተመደበላቸው ክፍል (Assigned Class)</th>
                            <th>ሁኔታ (Status)</th>
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
                            <th>ክፍል (Class)</th>
                            <th>የተማሪዎች ቁጥር</th>
                            <th>ኃላፊ መምህር</th>
                            <th>ድርጊት (Action)</th>
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
                            <th>ተማሪ (Student)</th>
                            <th>ክፍል (Class)</th>
                            <th>Assignment</th>
                            <th>Mid</th>
                            <th>Final</th>
                            <th>Total</th>
                            <th>ደረጃ (Grade)</th>
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