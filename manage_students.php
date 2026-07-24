<?php
session_start();
require_once 'db.php';
requireAdmin();

$message = '';
$error = '';

// Handle Add/Edit/Delete/Reset PIN
if($_SERVER['REQUEST_METHOD'] == 'POST') {
    if(isset($_POST['add_student'])) {
        $name = mysqli_real_escape_string($conn, $_POST['name']);
        $class_id = mysqli_real_escape_string($conn, $_POST['class_id']);
        $parent_phone = mysqli_real_escape_string($conn, $_POST['parent_phone']);
        
        // Insert student
        $query = "INSERT INTO students (name, class_id, parent_phone) VALUES ('$name', $class_id, '$parent_phone')";
        if(mysqli_query($conn, $query)) {
            $new_student_id = mysqli_insert_id($conn);
            
            // Create student login with default PIN (123)
            $default_pin = '123';
            if (!empty($parent_phone) && strlen($parent_phone) >= 4) {
                $default_pin = substr($parent_phone, -4);
            }
            $hashed_pin = password_hash($default_pin, PASSWORD_DEFAULT);
            
            mysqli_query($conn, "INSERT INTO student_logins (student_id, pin, first_login) 
                                VALUES ($new_student_id, '$hashed_pin', 1)
                                ON DUPLICATE KEY UPDATE pin = '$hashed_pin', first_login = 1");
            
            $message = "ተማሪ በተሳካ ሁኔታ ተመዝግቧል! የመግቢያ ፒን: $default_pin (Student added successfully! Login PIN: $default_pin)";
        } else {
            $error = "ስህተት ተከስቷል! (Error: " . mysqli_error($conn) . ")";
        }
    }
    
    if(isset($_POST['edit_student'])) {
        $student_id = mysqli_real_escape_string($conn, $_POST['student_id']);
        $name = mysqli_real_escape_string($conn, $_POST['name']);
        $class_id = mysqli_real_escape_string($conn, $_POST['class_id']);
        $parent_phone = mysqli_real_escape_string($conn, $_POST['parent_phone']);
        
        $query = "UPDATE students SET name='$name', class_id=$class_id, parent_phone='$parent_phone' WHERE id=$student_id";
        if(mysqli_query($conn, $query)) {
            $message = "የተማሪ መረጃ ተሻሽሏል! (Student updated successfully!)";
        } else {
            $error = "ስህተት ተከስቷል! (Error: " . mysqli_error($conn) . ")";
        }
    }
    
    if(isset($_POST['delete_student'])) {
        $student_id = mysqli_real_escape_string($conn, $_POST['student_id']);
        
        // Delete related records
        mysqli_query($conn, "DELETE FROM marks WHERE student_id=$student_id");
        mysqli_query($conn, "DELETE FROM attendance_records WHERE student_id=$student_id");
        mysqli_query($conn, "DELETE FROM student_logins WHERE student_id=$student_id");
        mysqli_query($conn, "DELETE FROM students WHERE id=$student_id");
        
        $message = "ተማሪ ተሰርዟል! (Student deleted successfully!)";
    }

    // HANDLE PIN RESET
    if(isset($_POST['reset_student_pin'])) {
        $student_id = intval($_POST['student_id']);
        $new_pin = '123';
        $hashed_pin = password_hash($new_pin, PASSWORD_DEFAULT);
        
        $pin_query = "INSERT INTO student_logins (student_id, pin, first_login, login_attempts, locked_until) 
                      VALUES ($student_id, '$hashed_pin', 1, 0, NULL)
                      ON DUPLICATE KEY UPDATE pin = '$hashed_pin', first_login = 1, login_attempts = 0, locked_until = NULL";
        
        if (mysqli_query($conn, $pin_query)) {
            $student_name_query = mysqli_query($conn, "SELECT name FROM students WHERE id = $student_id");
            $student_name_row = mysqli_fetch_assoc($student_name_query);
            $student_name = $student_name_row ? $student_name_row['name'] : 'ተማሪ';
            
            $message = "የ{$student_name} ፒን ወደ 123 ተመልሷል! (PIN has been reset to 123)";
        } else {
            $error = "ስህተት ተከስቷል! " . mysqli_error($conn);
        }
    }
    
    // HANDLE TOGGLE STUDENT PORTAL ACCESS
    if(isset($_POST['toggle_portal'])) {
        $student_id = intval($_POST['student_id']);
        $current_status = intval($_POST['current_status']);
        $new_status = $current_status ? 0 : 1;
        
        $toggle_query = "UPDATE students SET student_portal_enabled = $new_status WHERE id = $student_id";
        if (mysqli_query($conn, $toggle_query)) {
            $status_text = $new_status ? 'ነቅቷል (Enabled)' : 'ተሰናክሏል (Disabled)';
            $message = "የተማሪ ፖርታል መዳረሻ $status_text!";
        } else {
            $error = "ስህተት ተከስቷል! " . mysqli_error($conn);
        }
    }
}

// Get all classes
$classes_query = "SELECT * FROM classes ORDER BY name";
$classes = mysqli_query($conn, $classes_query);

// Get search filter
$search_filter = isset($_GET['search']) ? mysqli_real_escape_string($conn, $_GET['search']) : '';

// Get all students with class names and portal status - WITH SEARCH
$students_query = "SELECT s.*, c.name as class_name,
                   COALESCE(sl.pin IS NOT NULL, 0) as has_pin,
                   COALESCE(sl.first_login, 1) as pin_first_login,
                   s.student_portal_enabled
                   FROM students s
                   JOIN classes c ON s.class_id = c.id
                   LEFT JOIN student_logins sl ON s.id = sl.student_id";
if(!empty($search_filter)) {
    $students_query .= " WHERE s.name LIKE '%$search_filter%'";
}
$students_query .= " ORDER BY c.name, s.name";
$students = mysqli_query($conn, $students_query);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/png" href="images/icon.png">
    <title>ተማሪዎች አስተዳደር | አጸደ ትጉሃን</title>
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
            --purple: #8B5CF6;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Segoe UI', sans-serif; }
        body { background: #FAF9F6; }

        .header {
            background: linear-gradient(135deg, #8B4513 0%, #A52A2A 100%);
            color: white; padding: 20px 30px;
        }
        .header-content {
            max-width: 1400px; margin: 0 auto;
            display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px;
        }
        .logo-area { display: flex; align-items: center; gap: 15px; }
        .logo-img { width: 50px; height: 50px; border-radius: 50%; object-fit: cover; border: 3px solid var(--gold-primary); background: white; }
        .title h1 { font-size: 20px; color: var(--gold-primary); }
        .title p { font-size: 14px; color: rgba(255,255,255,0.8); }

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
            padding: 15px 20px; border-radius: 12px; margin-bottom: 25px;
            display: flex; align-items: center; gap: 12px; animation: slideDown 0.4s ease;
        }
        @keyframes slideDown { from { opacity: 0; transform: translateY(-20px); } to { opacity: 1; transform: translateY(0); } }
        .success { background: #D1FAE5; color: var(--success-green); border-left: 5px solid var(--success-green); }
        .error { background: #FEE2E2; color: var(--error-red); border-left: 5px solid var(--error-red); }

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

        .btn {
            padding: 12px 25px; border: none; border-radius: 8px; cursor: pointer;
            font-weight: 600; transition: all 0.3s; text-decoration: none;
            display: inline-flex; align-items: center; gap: 8px; font-size: 14px;
        }
        .btn-primary { background: linear-gradient(135deg, #FFD700 0%, #DAA520 100%); color: #8B4513; }
        .btn-primary:hover { transform: translateY(-2px); box-shadow: 0 5px 15px rgba(218,165,32,0.3); }
        .btn-edit { background: #3B82F6; color: white; padding: 6px 12px; font-size: 12px; }
        .btn-delete { background: #EF4444; color: white; padding: 6px 12px; font-size: 12px; }
        .btn-reset { background: #F59E0B; color: white; padding: 6px 12px; font-size: 12px; }
        .btn-toggle { padding: 6px 12px; font-size: 12px; color: white; }
        .btn-toggle.enable { background: var(--success-green); }
        .btn-toggle.disable { background: #6B7280; }
        .btn-search { background: var(--info-blue); color: white; padding: 12px 20px; font-size: 14px; }
        .btn-clear { background: #6B7280; color: white; padding: 12px 20px; border-radius: 8px; text-decoration: none; font-weight: 600; }

        .search-box {
            display: flex; gap: 10px; margin-bottom: 20px; max-width: 500px; flex-wrap: wrap;
        }
        .search-input {
            flex: 1; padding: 12px 15px; border: 2px solid #E2E8F0; border-radius: 8px;
            font-size: 14px; min-width: 200px;
        }
        .search-input:focus { outline: none; border-color: var(--gold-primary); }

        .form-grid {
            display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px; margin-bottom: 20px;
        }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; margin-bottom: 5px; color: var(--brown-dark); font-weight: 600; }
        .form-control {
            width: 100%; padding: 12px; border: 2px solid #E2E8F0; border-radius: 8px; font-size: 16px;
        }
        .form-control:focus { outline: none; border-color: var(--gold-primary); }

        .table-responsive { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; min-width: 1000px; }
        th { background: var(--brown-dark); color: white; padding: 15px 10px; text-align: left; font-size: 13px; border: 1px solid var(--gold-dark); }
        td { padding: 12px 10px; border-bottom: 1px solid #E2E8F0; }
        tr:hover { background: #FEF9E7; }
        .action-buttons { display: flex; gap: 6px; flex-wrap: wrap; }
        .student-name { font-weight: 600; color: var(--brown-dark); }

        .portal-status {
            display: inline-block; padding: 4px 10px; border-radius: 15px; font-size: 11px; font-weight: 600;
        }
        .status-enabled { background: #D1FAE5; color: #059669; }
        .status-disabled { background: #FEE2E2; color: #DC2626; }

        .pin-status { display: inline-block; padding: 3px 8px; border-radius: 10px; font-size: 10px; font-weight: 600; }
        .pin-new { background: #FEF3C7; color: #D97706; }
        .pin-active { background: #D1FAE5; color: #059669; }

        .info-box {
            background: #EFF6FF; border: 2px solid var(--info-blue); border-radius: 12px;
            padding: 15px 20px; margin-bottom: 20px; display: flex; align-items: center; gap: 15px;
        }
        .info-box .info-icon { font-size: 30px; }
        .info-box .info-text { font-size: 14px; color: #1E40AF; line-height: 1.6; }

        .modal {
            display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(0,0,0,0.5); z-index: 1000;
        }
        .modal-content {
            background: white; width: 90%; max-width: 500px; margin: 50px auto;
            padding: 30px; border-radius: 15px; border: 3px solid var(--gold-primary);
        }
        .close { float: right; font-size: 24px; cursor: pointer; color: var(--brown-dark); }

        .search-results-info {
            background: var(--gold-pale); padding: 10px 15px; border-radius: 8px;
            margin-bottom: 15px; display: flex; justify-content: space-between; align-items: center;
        }

        @media (max-width: 768px) {
            .form-grid { grid-template-columns: 1fr; }
            .action-buttons { flex-direction: column; }
            .search-box { max-width: 100%; }
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
                    <p>ተማሪዎች አስተዳደር | Student Management</p>
                </div>
            </div>
            <a href="dashboard_admin.php" class="btn btn-primary">← ወደ ዳሽቦርድ</a>
        </div>
    </div>

    <div class="nav-links">
        <a href="dashboard_admin.php" class="nav-link">🏠 ዳሽቦርድ</a>
        <a href="manage_classes.php" class="nav-link">📚 ክፍሎች</a>
        <a href="manage_students.php" class="nav-link active">👥 ተማሪዎች</a>
        <a href="manage_teachers.php" class="nav-link">👨‍🏫 መምህራን</a>
        <a href="manage_assignments.php" class="nav-link">📋 ክፍል ምደባ</a>
        <a href="semester.php" class="nav-link">📅 ሴሚስተር</a>
        <a href="class_locks.php" class="nav-link">🔒 ክፍል መቆለፊያ</a>
        <a href="attendance_submitter_assign.php" class="nav-link">📋 የክፍል አቴንዳንስ</a>
        <a href="attendance_days_control.php" class="nav-link">📅 የትምህርት ቀናት</a>
        <a href="attendance_controller.php" class="nav-link">📊 የአቴንዳንስ መቆጣጠሪያ</a>
        <a href="teacher_marks_viewer.php" class="nav-link">👁️ የመምህራን ውጤት</a>
        <a href="print_results.php" class="nav-link">🖨️ ውጤት ማተሚያ</a>
        <a href="manage_users.php" class="nav-link">👤 ተጠቃሚዎች</a>
    </div>

    <div class="container">
        <?php if($message): ?>
        <div class="message success">✅ <?php echo $message; ?></div>
        <?php endif; ?>

        <?php if($error): ?>
        <div class="message error">⚠️ <?php echo $error; ?></div>
        <?php endif; ?>

        <!-- Student Portal Info Box -->
        <div class="info-box">
            <div class="info-icon">🎓</div>
            <div class="info-text">
                <strong>የተማሪ ፖርታል</strong><br>
                ተማሪዎች <a href="student_login.php" target="_blank">በዚህ ሊንክ</a> ሙሉ ስማቸውን እና ፒናቸውን በመጠቀም ውጤታቸውን ማየት ይችላሉ።<br>
                <strong>ነባሪ ፒን:</strong> 123 | ተማሪዎች መጀመሪያ ሲገቡ ፒን እንዲቀይሩ ይጠየቃሉ።
            </div>
        </div>

        <!-- Add Student Form -->
        <div class="section">
            <div class="section-header">
                <h2>➕ አዲስ ተማሪ መመዝገቢያ</h2>
            </div>
            <form method="POST">
                <div class="form-grid">
                    <div class="form-group">
                        <label>የተማሪ ስም (Student Name) <span style="color: red;">*</span></label>
                        <input type="text" name="name" class="form-control" required placeholder="ሙሉ ስም">
                    </div>
                    <div class="form-group">
                        <label>ክፍል (Class) <span style="color: red;">*</span></label>
                        <select name="class_id" class="form-control" required>
                            <option value="">ክፍል ምረጥ</option>
                            <?php 
                            mysqli_data_seek($classes, 0);
                            while($class = mysqli_fetch_assoc($classes)): 
                            ?>
                            <option value="<?php echo $class['id']; ?>"><?php echo htmlspecialchars($class['name']); ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>የወላጅ ስልክ (Parent Phone)</label>
                        <input type="text" name="parent_phone" class="form-control" placeholder="ከሆነ ያስገቡ...">
                        <small style="color: #666;">ፒን ከስልክ ቁጥር የመጨረሻ 4 አሃዝ ይወሰዳል (ወይም 123)</small>
                    </div>
                </div>
                <button type="submit" name="add_student" class="btn btn-primary">
                    ➕ ተማሪ አስመዝግብ
                </button>
            </form>
        </div>

        <!-- Students List -->
        <div class="section">
            <div class="section-header">
                <h2>👥 የተማሪዎች ዝርዝር</h2>
                <span><?php echo $students ? mysqli_num_rows($students) : 0; ?> ተማሪዎች</span>
            </div>

            <!-- SEARCH BOX -->
            <form method="GET" class="search-box">
                <input type="text" name="search" class="search-input" 
                       placeholder="🔍 የተማሪ ስም ይፈልጉ... (Search student name...)" 
                       value="<?php echo htmlspecialchars($search_filter); ?>">
                <button type="submit" class="btn btn-search">🔍 ፈልግ</button>
                <?php if(!empty($search_filter)): ?>
                <a href="manage_students.php" class="btn-clear">✕ አጽዳ</a>
                <?php endif; ?>
            </form>

            <?php if(!empty($search_filter)): ?>
            <div class="search-results-info">
                <span>🔍 የፍለጋ ውጤት: "<strong><?php echo htmlspecialchars($search_filter); ?></strong>"</span>
                <span><?php echo $students ? mysqli_num_rows($students) : 0; ?> ተማሪዎች ተገኝተዋል</span>
            </div>
            <?php endif; ?>

            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>የተማሪ ስም</th>
                            <th>ክፍል</th>
                            <th>የወላጅ ስልክ</th>
                            <th>ፖርታል</th>
                            <th>ፒን ሁኔታ</th>
                            <th>የተመዘገበበት</th>
                            <th>ድርጊት</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $counter = 1;
                        if($students && mysqli_num_rows($students) > 0):
                            while($student = mysqli_fetch_assoc($students)): 
                        ?>
                        <tr>
                            <td><?php echo $counter++; ?></td>
                            <td class="student-name"><?php echo htmlspecialchars($student['name']); ?></td>
                            <td><?php echo htmlspecialchars($student['class_name']); ?></td>
                            <td><?php echo htmlspecialchars($student['parent_phone'] ?: '---'); ?></td>
                            <td>
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="student_id" value="<?php echo $student['id']; ?>">
                                    <input type="hidden" name="current_status" value="<?php echo $student['student_portal_enabled']; ?>">
                                    <button type="submit" name="toggle_portal" 
                                            class="btn-toggle <?php echo $student['student_portal_enabled'] ? 'enable' : 'disable'; ?>">
                                        <?php echo $student['student_portal_enabled'] ? '✅ Active' : '⛔ Disabled'; ?>
                                    </button>
                                </form>
                            </td>
                            <td>
                                <?php if ($student['pin_first_login']): ?>
                                <span class="pin-status pin-new">🆕 አዲስ</span>
                                <?php else: ?>
                                <span class="pin-status pin-active">✅ Active</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo date('M d, Y', strtotime($student['enrollment_date'])); ?></td>
                            <td>
                                <div class="action-buttons">
                                    <button onclick="editStudent(<?php echo $student['id']; ?>, '<?php echo htmlspecialchars(addslashes($student['name'])); ?>', <?php echo $student['class_id']; ?>, '<?php echo $student['parent_phone']; ?>')" 
                                            class="btn btn-edit">✏️ አስተካክል</button>
                                    
                                    <form method="POST" style="display: inline;" 
                                          onsubmit="return confirm('የዚህን ተማሪ ፒን ወደ 123 ማስጀመር እርግጠኛ ነዎት?')">
                                        <input type="hidden" name="student_id" value="<?php echo $student['id']; ?>">
                                        <button type="submit" name="reset_student_pin" class="btn btn-reset">
                                            🔄 Reset PIN
                                        </button>
                                    </form>
                                    
                                    <form method="POST" style="display: inline;" 
                                          onsubmit="return confirm('እርግጠኛ ነዎት ተማሪውን መሰረዝ ይፈልጋሉ?')">
                                        <input type="hidden" name="student_id" value="<?php echo $student['id']; ?>">
                                        <button type="submit" name="delete_student" class="btn btn-delete">🗑️ ሰርዝ</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                        <?php 
                            endwhile;
                        else:
                        ?>
                        <tr>
                            <td colspan="8" style="text-align: center; padding: 30px; color: #999;">
                                <span style="font-size: 40px; display: block; margin-bottom: 10px;">👥</span>
                                <?php if(!empty($search_filter)): ?>
                                    ምንም ተማሪ አልተገኘም ለ "<strong><?php echo htmlspecialchars($search_filter); ?></strong>"
                                <?php else: ?>
                                    ምንም ተማሪዎች አልተገኙም
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Edit Modal -->
    <div id="editModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeModal()">&times;</span>
            <h2 style="color: var(--brown-dark); margin-bottom: 20px;">የተማሪ መረጃ አስተካክል</h2>
            <form method="POST" id="editForm">
                <input type="hidden" name="student_id" id="edit_id">
                <div class="form-group">
                    <label>የተማሪ ስም <span style="color: red;">*</span></label>
                    <input type="text" name="name" id="edit_name" class="form-control" required>
                </div>
                <div class="form-group">
                    <label>ክፍል <span style="color: red;">*</span></label>
                    <select name="class_id" id="edit_class" class="form-control" required>
                        <option value="">ክፍል ምረጥ</option>
                        <?php 
                        mysqli_data_seek($classes, 0);
                        while($class = mysqli_fetch_assoc($classes)): 
                        ?>
                        <option value="<?php echo $class['id']; ?>"><?php echo htmlspecialchars($class['name']); ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>የወላጅ ስልክ</label>
                    <input type="text" name="parent_phone" id="edit_phone" class="form-control">
                </div>
                <button type="submit" name="edit_student" class="btn btn-primary" style="width: 100%;">
                    💾 አስቀምጥ
                </button>
            </form>
        </div>
    </div>

    <script>
        function editStudent(id, name, classId, phone) {
            document.getElementById('edit_id').value = id;
            document.getElementById('edit_name').value = name;
            document.getElementById('edit_class').value = classId;
            document.getElementById('edit_phone').value = phone || '';
            document.getElementById('editModal').style.display = 'block';
        }

        function closeModal() {
            document.getElementById('editModal').style.display = 'none';
        }

        window.onclick = function(event) {
            if (event.target == document.getElementById('editModal')) {
                closeModal();
            }
        }
    </script>
</body>
</html>
<?php mysqli_close($conn); ?>