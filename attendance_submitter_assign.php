<?php
require_once 'db.php';
requireAdmin();

$message = '';
$error = '';

$current_semester = getCurrentSemester($conn);
$semester_id = $current_semester ? intval($current_semester['id']) : 0;

// Handle assignment
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = "የደህንነት ማረጋገጫ አልተሳካም! እባክዎ እንደገና ይሞክሩ።";
    } else {
        if (isset($_POST['assign'])) {
            $submitter_id = intval($_POST['submitter_id'] ?? 0);
            $class_id = intval($_POST['class_id'] ?? 0);
            
            if ($submitter_id > 0 && $class_id > 0 && $semester_id > 0) {
                // Check if this submitter is already assigned to this class in this semester
                $check = dbFetchOne(
                    $conn,
                    "SELECT id FROM attendance_assignments WHERE submitter_id = ? AND class_id = ? AND semester_id = ?",
                    "iii",
                    [$submitter_id, $class_id, $semester_id]
                );
                
                if ($check) {
                    $error = "ይህ የክፍል ጸሐፊ በዚህ ክፍል ቀድሞውኑ ተመድቧል!";
                } else {
                    $inserted = dbExecute(
                        $conn,
                        "INSERT INTO attendance_assignments (submitter_id, class_id, semester_id) VALUES (?, ?, ?)",
                        "iii",
                        [$submitter_id, $class_id, $semester_id]
                    );
                    
                    if ($inserted) {
                        $message = "የክፍል ጸሐፊው በተሳካ ሁኔታ ተመድቧል!";
                    } else {
                        $error = "ስህተት ተከስቷል!";
                    }
                }
            }
        }
        
        if (isset($_POST['remove'])) {
            $assignment_id = intval($_POST['assignment_id'] ?? 0);
            if ($assignment_id > 0) {
                dbExecute($conn, "DELETE FROM attendance_assignments WHERE id = ?", "i", [$assignment_id]);
                $message = "ምደባው ተሰርዟል!";
            }
        }
        
        // ADD NEW SUBMITTER
        if (isset($_POST['add_submitter'])) {
            $name = trim($_POST['submitter_name'] ?? '');
            $username = trim($_POST['submitter_username'] ?? '');
            $phone = trim($_POST['submitter_phone'] ?? '');
            $password = password_hash('123', PASSWORD_DEFAULT);
            
            if (empty($name) || empty($username)) {
                $error = "እባክዎ ሙሉ ስም እና የተጠቃሚ ስም ያስገቡ!";
            } else {
                // Check if username exists
                $check = dbFetchOne($conn, "SELECT id FROM users WHERE username = ?", "s", [$username]);
                if ($check) {
                    $error = "ይህ የተጠቃሚ ስም ቀድሞውኑ አለ! እባክዎ ሌላ የተጠቃሚ ስም ይምረጡ።";
                } else {
                    $created = dbExecute(
                        $conn,
                        "INSERT INTO users (name, username, phone, role, password, first_login, can_edit_marks, can_edit_attendance) 
                         VALUES (?, ?, ?, 'attendance_submitter', ?, 1, 0, 1)",
                        "ssss",
                        [$name, $username, $phone, $password]
                    );
                    if ($created) {
                        $message = "አዲስ የክፍል ጸሐፊ በተሳካ ሁኔታ ተመዝግቧል! የመጀመሪያ የይለፍ ቃል: 123";
                    } else {
                        $error = "ስህተት ተከስቷል!";
                    }
                }
            }
        }
    }
}

// Get submitters - now includes teachers, since a teacher can also be
// granted attendance-marking rights via attendance_assignments (this is
// how Children division teachers get it, reusing this exact mechanism).
$submitters_query = "SELECT * FROM users WHERE role IN ('attendance_submitter', 'teacher') ORDER BY role, name";
$submitters = mysqli_query($conn, $submitters_query);

// Get classes
$classes_query = "SELECT * FROM classes ORDER BY name";
$classes = mysqli_query($conn, $classes_query);

// Get current assignments with full details
$assignments_query = "SELECT aa.*, u.name as submitter_name, u.phone as submitter_phone,
                      c.name as class_name, c.id as class_id,
                      (SELECT COUNT(*) FROM students s WHERE s.class_id = c.id) as student_count
                      FROM attendance_assignments aa
                      JOIN users u ON aa.submitter_id = u.id
                      JOIN classes c ON aa.class_id = c.id
                      WHERE aa.semester_id = $semester_id
                      ORDER BY c.name";
$assignments = mysqli_query($conn, $assignments_query);

// Group assignments by class for better display
$class_assignments = [];
if($assignments) {
    while($row = mysqli_fetch_assoc($assignments)) {
        $class_assignments[$row['class_id']][] = $row;
    }
}

// Get unassigned classes
$assigned_class_ids = array_keys($class_assignments);
$unassigned_classes = [];
mysqli_data_seek($classes, 0);
while($class = mysqli_fetch_assoc($classes)) {
    if(!in_array($class['id'], $assigned_class_ids)) {
        $unassigned_classes[] = $class;
    }
}

// Get all submitters with their assignments
$submitter_assignments = [];
$submitter_detail_query = "SELECT u.id as submitter_id, u.name as submitter_name, u.phone, u.role,
                           c.id as class_id, c.name as class_name,
                           aa.id as assignment_id,
                           (SELECT COUNT(*) FROM students s WHERE s.class_id = c.id) as student_count
                           FROM users u
                           LEFT JOIN attendance_assignments aa ON u.id = aa.submitter_id AND aa.semester_id = $semester_id
                           LEFT JOIN classes c ON aa.class_id = c.id
                           WHERE u.role IN ('attendance_submitter', 'teacher')
                           ORDER BY u.role, u.name, c.name";
$submitter_detail = mysqli_query($conn, $submitter_detail_query);
if($submitter_detail) {
    while($row = mysqli_fetch_assoc($submitter_detail)) {
        $submitter_assignments[$row['submitter_id']]['name'] = $row['submitter_name'];
        $submitter_assignments[$row['submitter_id']]['phone'] = $row['phone'];
        $submitter_assignments[$row['submitter_id']]['role'] = $row['role'];
        if($row['class_id']) {
            $submitter_assignments[$row['submitter_id']]['classes'][] = [
                'assignment_id' => $row['assignment_id'],
                'class_id' => $row['class_id'],
                'class_name' => $row['class_name'],
                'student_count' => $row['student_count']
            ];
        } else {
            if(!isset($submitter_assignments[$row['submitter_id']]['classes'])) {
                $submitter_assignments[$row['submitter_id']]['classes'] = [];
            }
        }
    }
}
$nav_active = 'attendance_submitter_assign';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>የክፍል ጸሐፊ ምደባ | Attendance Submitter Assignment</title>
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
            --error-red: #EF4444;
            --warning-yellow: #F59E0B;
            --info-blue: #3B82F6;
            --purple: #8B5CF6;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Segoe UI', sans-serif; }
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

        .message {
            padding: 15px 20px; border-radius: 12px; margin-bottom: 20px;
            display: flex; align-items: center; gap: 10px;
        }
        .success { background: #D1FAE5; color: #065F46; border: 2px solid var(--success-green); }
        .error { background: #FEE2E2; color: #991B1B; border: 2px solid var(--error-red); }

        .card {
            background: white; border-radius: 20px; padding: 25px; margin-bottom: 25px;
            border: 2px solid var(--gold-primary); box-shadow: 0 5px 20px rgba(0,0,0,0.08);
        }
        .card-title {
            color: var(--brown-dark); font-size: 20px; margin-bottom: 20px;
            padding-bottom: 10px; border-bottom: 2px solid var(--gold-pale);
            display: flex; align-items: center; gap: 10px;
        }

        .btn {
            padding: 10px 20px; border: none; border-radius: 8px; cursor: pointer;
            font-weight: 600; text-decoration: none; display: inline-flex; align-items: center;
            gap: 8px; font-size: 14px; transition: all 0.3s;
        }
        .btn-back { background: var(--gold-primary); color: var(--brown-dark); }
        .btn-assign { background: linear-gradient(135deg, #FFD700 0%, #DAA520 100%); color: #8B4513; }
        .btn-remove { background: var(--error-red); color: white; padding: 6px 12px; font-size: 12px; }
        .btn-add { background: var(--purple); color: white; }
        .btn-primary { background: linear-gradient(135deg, #FFD700 0%, #DAA520 100%); color: #8B4513; }

        .form-row { display: flex; gap: 15px; align-items: flex-end; flex-wrap: wrap; }
        .form-group { flex: 1; min-width: 200px; }
        .form-group label { display: block; margin-bottom: 5px; color: var(--brown-dark); font-weight: 600; font-size: 13px; }
        .form-group select, .form-group input {
            width: 100%; padding: 12px; border: 2px solid #E2E8F0; border-radius: 8px; font-size: 14px;
        }
        .form-group select:focus, .form-group input:focus {
            outline: none; border-color: var(--gold-primary);
        }

        .table-responsive { overflow-x: auto; -webkit-overflow-scrolling: touch; }
        table { width: 100%; border-collapse: collapse; }
        .table-responsive table { min-width: 550px; }
        th { background: var(--brown-dark); color: white; padding: 12px; text-align: left; border: 1px solid var(--gold-dark); }
        td { padding: 10px 12px; border-bottom: 1px solid #E2E8F0; }

        .class-grid {
            display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 20px;
        }
        .class-card-item {
            background: #F8F9FA; border-radius: 12px; padding: 15px;
            border: 2px solid var(--gold-pale); transition: all 0.3s;
        }
        .class-card-item:hover { border-color: var(--gold-primary); transform: translateY(-2px); }
        .class-card-item h3 { color: var(--brown-dark); font-size: 16px; margin-bottom: 10px; }
        .class-card-item .info { font-size: 12px; color: #666; margin-bottom: 8px; }
        .submitter-tag {
            display: inline-block; background: var(--gold-pale); padding: 4px 12px;
            border-radius: 20px; font-size: 12px; margin: 3px;
        }
        .no-assignment { color: #999; font-style: italic; font-size: 13px; padding: 10px 0; }

        .add-submitter-form {
            background: #F8F9FA; border: 2px dashed var(--purple); border-radius: 12px;
            padding: 20px; margin-top: 20px;
        }
        .add-submitter-form h4 { color: var(--purple); margin-bottom: 15px; }

        @media (max-width: 768px) {
            .main-container { padding: 0 12px 30px; margin: 15px auto; }
            .card { padding: 16px 12px; border-radius: 12px; margin-bottom: 18px; }
            .card-title { font-size: 17px; }
            .class-grid { grid-template-columns: 1fr; gap: 12px; }
            .form-row { flex-direction: column; gap: 10px; }
            .form-group { width: 100%; min-width: 0; }
            .btn { width: 100%; justify-content: center; min-height: 44px; }
            .add-submitter-form { padding: 15px 10px; border-radius: 10px; }
        }
    </style>
</head>
<body>
    <?php include 'mobile_nav.php'; ?>

    <div class="main-container">
        <?php if ($message): ?>
        <div class="message success">✅ <?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
        <div class="message error">⚠️ <?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
        <?php endif; ?>

        <?php if(!$current_semester): ?>
        <div class="message error">
            <span>⚠️</span>
            ምንም ንቁ ሴሚስተር የለም። እባክዎ መጀመሪያ ሴሚስተር ይክፈቱ።
            <a href="semester.php" class="btn btn-primary" style="margin-left: auto;">📅 ሴሚስተር ክፈት</a>
        </div>
        <?php else: ?>

        <!-- SECTION 1: Quick Assign -->
        <div class="card">
            <div class="card-title">
                <span>➕</span> አዲስ ምደባ / New Assignment
            </div>
            <form method="POST">
                <?php echo csrfField(); ?>
                <div class="form-row">
                    <div class="form-group">
                        <label>👤 የክፍል ጸሐፊ</label>
                        <select name="submitter_id" required>
                            <option value="">ምረጥ...</option>
                            <?php 
                            mysqli_data_seek($submitters, 0);
                            while ($submitter = mysqli_fetch_assoc($submitters)): 
                            ?>
                            <option value="<?php echo $submitter['id']; ?>">
                                <?php echo htmlspecialchars($submitter['name']); ?>
                                (<?php echo $submitter['role'] === 'teacher' ? 'መምህር' : 'ጸሐፊ'; ?>)
                            </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>📚 ክፍል</label>
                        <select name="class_id" required>
                            <option value="">ምረጥ...</option>
                            <?php 
                            mysqli_data_seek($classes, 0);
                            while ($class = mysqli_fetch_assoc($classes)): 
                            ?>
                            <option value="<?php echo $class['id']; ?>">
                                <?php echo htmlspecialchars($class['name']); ?>
                            </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <button type="submit" name="assign" class="btn btn-assign">
                        ➕ መድብ / Assign
                    </button>
                </div>
            </form>
        </div>

        <!-- SECTION 2: Current Assignments by Class -->
        <div class="card">
            <div class="card-title">
                <span>📋</span> አሁን ያሉ ምደባዎች በክፍል / Current Assignments by Class
            </div>
            
            <?php if(!empty($class_assignments)): ?>
            <div class="class-grid">
                <?php foreach($class_assignments as $c_id => $assignments_list): 
                    $class_info = null;
                    mysqli_data_seek($classes, 0);
                    while($c = mysqli_fetch_assoc($classes)) {
                        if($c['id'] == $c_id) { $class_info = $c; break; }
                    }
                ?>
                <div class="class-card-item">
                    <h3>📖 <?php echo htmlspecialchars($class_info['name'] ?? 'ክፍል ' . $c_id); ?></h3>
                    <div class="info">
                        👥 <?php echo $assignments_list[0]['student_count'] ?? 0; ?> ተማሪዎች
                    </div>
                    <?php foreach($assignments_list as $assignment): ?>
                    <div>
                        <span class="submitter-tag">
                            👤 <?php echo htmlspecialchars($assignment['submitter_name']); ?>
                            <form method="POST" style="display: inline; margin-left: 8px;" onsubmit="return confirm('ምደባውን ማስወገድ እርግጠኛ ነዎት?')">
                                <?php echo csrfField(); ?>
                                <input type="hidden" name="assignment_id" value="<?php echo $assignment['id']; ?>">
                                <button type="submit" name="remove" style="background:none; border:none; color:var(--error-red); cursor:pointer; font-size:14px;">✕</button>
                            </form>
                        </span>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
            <div class="no-assignment" style="text-align:center; padding:30px;">
                <span style="font-size:40px;">📭</span>
                <p>ምንም ምደባዎች የሉም</p>
            </div>
            <?php endif; ?>
        </div>

        <!-- SECTION 3: Submitter Overview Table -->
        <div class="card">
            <div class="card-title">
                <span>👥</span> የክፍል ጸሐፊዎች አጠቃላይ እይታ / Submitter Overview
            </div>
            
            <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>የክፍል ጸሐፊ</th>
                        <th>ስልክ</th>
                        <th>የተመደቡባቸው ክፍሎች</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(!empty($submitter_assignments)): ?>
                        <?php foreach($submitter_assignments as $s_id => $s_data): ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($s_data['name']); ?></strong>
                                <span style="font-size:11px;color:#999;">(<?php echo $s_data['role'] === 'teacher' ? 'መምህር' : 'ጸሐፊ'; ?>)</span>
                            </td>
                            <td><?php echo htmlspecialchars($s_data['phone'] ?? '---'); ?></td>
                            <td>
                                <?php if(!empty($s_data['classes'])): ?>
                                    <?php foreach($s_data['classes'] as $sc): ?>
                                    <span class="submitter-tag">
                                        📖 <?php echo htmlspecialchars($sc['class_name']); ?> 
                                        (<?php echo $sc['student_count']; ?> ተማሪዎች)
                                    </span>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <span style="color:#999;">ምንም ክፍል አልተመደበም</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="3" style="text-align:center; padding:30px;">ምንም የክፍል ጸሐፊዎች የሉም</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
            </div>
        </div>

        <!-- SECTION 4: Add New Submitter -->
        <div class="card">
            <div class="card-title">
                <span>➕</span> አዲስ የክፍል ጸሐፊ መመዝገቢያ
            </div>
            
            <div class="add-submitter-form">
                <h4>🆕 አዲስ የክፍል ጸሐፊ መረጃ</h4>
                <form method="POST">
                    <?php echo csrfField(); ?>
                    <div class="form-row">
                        <div class="form-group">
                            <label>ሙሉ ስም *</label>
                            <input type="text" name="submitter_name" required placeholder="ለምሳሌ: የክፍል ጸሐፊ አበራ">
                        </div>
                        <div class="form-group">
                            <label>የተጠቃሚ ስም *</label>
                            <input type="text" name="submitter_username" required placeholder="ለምሳሌ: submitter1">
                        </div>
                        <div class="form-group">
                            <label>ስልክ ቁጥር</label>
                            <input type="text" name="submitter_phone" placeholder="0912345678">
                        </div>
                    </div>
                    <button type="submit" name="add_submitter" class="btn btn-add" style="margin-top:15px;">
                        ➕ አዲስ የክፍል ጸሐፊ ፍጠር
                    </button>
                    <small style="display:block; margin-top:8px; color:#666;">
                        💡 ነባሪ የይለፍ ቃል: <strong>123</strong> (ተጠቃሚው መጀመሪያ ሲገባ ይቀይረዋል)
                    </small>
                </form>
            </div>
        </div>

        <?php endif; ?>
    </div>
</body>
</html>
<?php mysqli_close($conn); ?>