<?php
session_start();
require_once 'db.php';
requireAdmin();

$message = '';
$error = '';

// Handle Add/Edit/Delete
if($_SERVER['REQUEST_METHOD'] == 'POST') {
    if(isset($_POST['add_teacher'])) {
        $name = mysqli_real_escape_string($conn, $_POST['name']);
        $username = mysqli_real_escape_string($conn, $_POST['username']);
        $phone = mysqli_real_escape_string($conn, $_POST['phone']);
        $password = hashPassword('123');
        
        // Check if username exists
        $check_username = mysqli_query($conn, "SELECT id FROM users WHERE username = '$username'");
        if(mysqli_num_rows($check_username) > 0) {
            $error = "ይህ የተጠቃሚ ስም ቀድሞውኑ አለ! (Username already exists!)";
        } else {
            $query = "INSERT INTO users (name, username, phone, role, password, first_login) 
                      VALUES ('$name', '$username', '$phone', 'teacher', '$password', TRUE)";
            if(mysqli_query($conn, $query)) {
                $message = "መምህር በተሳካ ሁኔታ ተመዝግቧል! የተጠቃሚ ስም: $username | የይለፍ ቃል: 123";
            } else {
                $error = "ስህተት ተከስቷል! " . mysqli_error($conn);
            }
        }
    }
    
    if(isset($_POST['edit_teacher'])) {
        $teacher_id = mysqli_real_escape_string($conn, $_POST['teacher_id']);
        $name = mysqli_real_escape_string($conn, $_POST['name']);
        $username = mysqli_real_escape_string($conn, $_POST['username']);
        $phone = mysqli_real_escape_string($conn, $_POST['phone']);
        
        // Check username uniqueness (excluding current teacher)
        $check = mysqli_query($conn, "SELECT id FROM users WHERE username = '$username' AND id != $teacher_id");
        if(mysqli_num_rows($check) > 0) {
            $error = "ይህ የተጠቃሚ ስም በሌላ ተጠቃሚ ተይዟል!";
        } else {
            $query = "UPDATE users SET name='$name', username='$username', phone='$phone' WHERE id=$teacher_id AND role='teacher'";
            if(mysqli_query($conn, $query)) {
                $message = "የመምህር መረጃ ተሻሽሏል!";
            } else {
                $error = "ስህተት ተከስቷል! " . mysqli_error($conn);
            }
        }
    }
    
    if(isset($_POST['delete_teacher'])) {
        $teacher_id = mysqli_real_escape_string($conn, $_POST['teacher_id']);
        
        mysqli_begin_transaction($conn);
        
        try {
            mysqli_query($conn, "DELETE FROM marks WHERE teacher_id = $teacher_id");
            mysqli_query($conn, "DELETE FROM teacher_class WHERE teacher_id = $teacher_id");
            mysqli_query($conn, "DELETE FROM users WHERE id = $teacher_id AND role = 'teacher'");
            
            mysqli_commit($conn);
            $message = "መምህር በተሳካ ሁኔታ ተሰርዟል!";
        } catch (Exception $e) {
            mysqli_rollback($conn);
            $error = "መምህሩን መሰረዝ አልተቻለም! " . mysqli_error($conn);
        }
    }
    
    if(isset($_POST['reset_password'])) {
        $teacher_id = mysqli_real_escape_string($conn, $_POST['teacher_id']);
        $new_password = hashPassword('123');
        
        $query = "UPDATE users SET password='$new_password', first_login=TRUE WHERE id=$teacher_id";
        if(mysqli_query($conn, $query)) {
            $message = "የይለፍ ቃል ወደ 123 ተመልሷል!";
        } else {
            $error = "ስህተት ተከስቷል! " . mysqli_error($conn);
        }
    }
}

// Get current academic year
$current_year_query = "SELECT * FROM academic_years WHERE status = 'active' LIMIT 1";
$current_year_result = mysqli_query($conn, $current_year_query);
$current_year = mysqli_fetch_assoc($current_year_result);
$current_ethiopian_year = $current_year ? $current_year['ethiopian_year'] : 2017;

// Get all teachers with username
$teachers_query = "SELECT * FROM users WHERE role='teacher' ORDER BY name";
$teachers = mysqli_query($conn, $teachers_query);

// Get all academic years for filtering
$years_query = "SELECT DISTINCT ethiopian_year FROM semesters ORDER BY ethiopian_year DESC";
$years = mysqli_query($conn, $years_query);

// Get all assignments with full details for each teacher
$all_assignments_query = "SELECT tc.*, 
                          u.name as teacher_name, 
                          c.name as class_name, 
                          s.name as semester_name,
                          s.ethiopian_year,
                          s.semester_number,
                          s.status as semester_status
                          FROM teacher_class tc
                          JOIN users u ON tc.teacher_id = u.id
                          JOIN classes c ON tc.class_id = c.id
                          JOIN semesters s ON tc.semester_id = s.id
                          ORDER BY s.ethiopian_year DESC, s.semester_number DESC, c.name";
$all_assignments = mysqli_query($conn, $all_assignments_query);

// Organize assignments by teacher and year
$teacher_history = [];
while($assignment = mysqli_fetch_assoc($all_assignments)) {
    $teacher_id = $assignment['teacher_id'];
    $year = $assignment['ethiopian_year'];
    
    if(!isset($teacher_history[$teacher_id])) {
        $teacher_history[$teacher_id] = [];
    }
    if(!isset($teacher_history[$teacher_id][$year])) {
        $teacher_history[$teacher_id][$year] = [
            'semester1' => [],
            'semester2' => []
        ];
    }
    
    $semester_key = 'semester' . $assignment['semester_number'];
    $teacher_history[$teacher_id][$year][$semester_key][] = $assignment;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/png" href="images/icon.png">
    <title>መምህራን አስተዳደር | አጸደ ትጉሃን</title>
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
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', sans-serif;
        }

        body {
            background: #FAF9F6;
        }

        .header {
            background: linear-gradient(135deg, #8B4513 0%, #A52A2A 100%);
            color: white;
            padding: 20px 30px;
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

        .message {
            padding: 15px 20px;
            border-radius: 12px;
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            gap: 12px;
            animation: slideDown 0.4s ease;
        }

        @keyframes slideDown {
            from { opacity: 0; transform: translateY(-20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .success {
            background: #D1FAE5;
            color: var(--success-green);
            border-left: 5px solid var(--success-green);
        }

        .error {
            background: #FEE2E2;
            color: var(--error-red);
            border-left: 5px solid var(--error-red);
        }

        .current-year-badge {
            background: linear-gradient(135deg, var(--gold-primary), var(--gold-dark));
            color: var(--brown-dark);
            padding: 5px 15px;
            border-radius: 20px;
            font-weight: bold;
            font-size: 14px;
            display: inline-block;
        }

        .section {
            background: white;
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 30px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }

        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid var(--gold-pale);
            flex-wrap: wrap;
            gap: 15px;
        }

        .section-header h2 {
            color: var(--brown-dark);
            font-size: 22px;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .btn {
            padding: 12px 25px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-primary {
            background: linear-gradient(135deg, #FFD700 0%, #DAA520 100%);
            color: #8B4513;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(218,165,32,0.3);
        }

        .btn-edit {
            background: #3B82F6;
            color: white;
            padding: 6px 12px;
            font-size: 12px;
        }

        .btn-delete {
            background: #EF4444;
            color: white;
            padding: 6px 12px;
            font-size: 12px;
        }

        .btn-reset {
            background: #F59E0B;
            color: white;
            padding: 6px 12px;
            font-size: 12px;
        }

        .btn-profile {
            background: #8B5CF6;
            color: white;
            padding: 6px 12px;
            font-size: 12px;
            text-decoration: none;
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }

        .form-group {
            margin-bottom: 15px;
        }

        .form-group label {
            display: block;
            margin-bottom: 5px;
            color: var(--brown-dark);
            font-weight: 600;
        }

        .form-control {
            width: 100%;
            padding: 12px;
            border: 2px solid #E2E8F0;
            border-radius: 8px;
            font-size: 16px;
        }

        .form-control:focus {
            outline: none;
            border-color: var(--gold-primary);
        }

        .add-teacher-form {
            background: var(--gold-pale);
            padding: 25px;
            border-radius: 12px;
            border: 2px dashed var(--gold-primary);
        }

        /* Teacher Cards Grid */
        .teachers-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(400px, 1fr));
            gap: 25px;
            margin-top: 20px;
        }

        .teacher-card {
            background: white;
            border-radius: 15px;
            padding: 20px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            border: 2px solid var(--gold-pale);
            transition: all 0.3s;
        }

        .teacher-card:hover {
            transform: translateY(-3px);
            border-color: var(--gold-primary);
            box-shadow: 0 10px 25px rgba(0,0,0,0.15);
        }

        .teacher-header {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 15px;
            padding-bottom: 15px;
            border-bottom: 2px solid var(--gold-pale);
        }

        .teacher-avatar {
            width: 60px;
            height: 60px;
            background: linear-gradient(135deg, var(--gold-primary), var(--gold-dark));
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            font-weight: bold;
            color: var(--brown-dark);
            border: 3px solid white;
            box-shadow: 0 3px 10px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        
        .teacher-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .teacher-title {
            flex: 1;
        }

        .teacher-name {
            font-size: 18px;
            font-weight: bold;
            color: var(--brown-dark);
            margin-bottom: 3px;
            text-decoration: none;
            cursor: pointer;
            transition: color 0.2s;
        }
        
        .teacher-name:hover {
            color: var(--gold-dark);
            text-decoration: underline;
        }

        .teacher-username {
            color: #8B5CF6;
            font-size: 12px;
            font-weight: 600;
            background: #F3F0FF;
            padding: 2px 10px;
            border-radius: 15px;
            display: inline-block;
            margin-bottom: 3px;
        }

        .teacher-phone {
            color: #666;
            font-size: 13px;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .teacher-status {
            display: inline-block;
            padding: 3px 10px;
            border-radius: 15px;
            font-size: 11px;
            font-weight: 600;
            margin-left: 10px;
        }

        .status-new {
            background: #FEF3C7;
            color: var(--warning-yellow);
        }

        .status-active {
            background: #D1FAE5;
            color: var(--success-green);
        }

        .teacher-actions {
            display: flex;
            gap: 8px;
            margin-top: 10px;
            flex-wrap: wrap;
        }

        /* Timeline Styles */
        .timeline {
            margin-top: 20px;
        }

        .year-group {
            margin-bottom: 20px;
            border-left: 3px solid var(--gold-primary);
            padding-left: 15px;
        }

        .year-header {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 10px;
            cursor: pointer;
            padding: 8px;
            background: #F8F9FA;
            border-radius: 8px;
            transition: all 0.3s;
        }

        .year-header:hover {
            background: var(--gold-pale);
        }

        .year-badge {
            background: var(--brown-dark);
            color: var(--gold-primary);
            padding: 5px 15px;
            border-radius: 20px;
            font-weight: bold;
            font-size: 14px;
        }

        .year-status {
            font-size: 12px;
            color: #666;
        }

        .toggle-icon {
            margin-left: auto;
            font-size: 18px;
            color: var(--gold-dark);
        }

        .semester-row {
            display: flex;
            gap: 20px;
            margin-bottom: 15px;
            padding: 10px;
            background: #F8F9FA;
            border-radius: 8px;
            animation: slideDown 0.3s ease;
        }

        .semester-badge {
            min-width: 100px;
            padding: 5px 10px;
            border-radius: 15px;
            font-size: 12px;
            font-weight: 600;
            text-align: center;
        }

        .semester-1 {
            background: #EFF6FF;
            color: #3B82F6;
        }

        .semester-2 {
            background: #FEF3C7;
            color: #F59E0B;
        }

        .classes-list {
            flex: 1;
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
        }

        .class-tag {
            background: white;
            border: 1px solid var(--gold-primary);
            color: var(--brown-dark);
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }

        .class-tag.locked {
            background: #FEE2E2;
            border-color: var(--error-red);
            color: var(--error-red);
        }

        .no-data {
            color: #999;
            font-style: italic;
            padding: 10px;
            text-align: center;
        }

        .empty-state {
            text-align: center;
            padding: 50px;
            color: #666;
        }

        .empty-state span {
            font-size: 50px;
            display: block;
            margin-bottom: 15px;
        }

        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 1000;
        }

        .modal-content {
            background: white;
            width: 90%;
            max-width: 500px;
            margin: 50px auto;
            padding: 30px;
            border-radius: 15px;
            border: 3px solid var(--gold-primary);
        }

        .close {
            float: right;
            font-size: 24px;
            cursor: pointer;
            color: var(--brown-dark);
        }

        .close:hover {
            color: var(--error-red);
        }

        .info-box {
            background: #EFF6FF;
            border-left: 4px solid var(--info-blue);
            padding: 15px;
            border-radius: 8px;
            margin: 20px 0;
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .info-box span {
            font-size: 24px;
        }

        @media (max-width: 768px) {
            .teachers-grid {
                grid-template-columns: 1fr;
            }
            
            .semester-row {
                flex-direction: column;
                gap: 10px;
            }
            
            .semester-badge {
                align-self: flex-start;
            }
            
            .form-grid {
                grid-template-columns: 1fr;
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
                    <p>መምህራን አስተዳደር | Teacher Management</p>
                </div>
            </div>
            <a href="dashboard_admin.php" class="btn btn-primary">← ወደ ዳሽቦርድ</a>
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
</div>
    <div class="container">
        <?php if($message): ?>
        <div class="message success">
            <span>✅</span>
            <?php echo $message; ?>
        </div>
        <?php endif; ?>

        <?php if($error): ?>
        <div class="message error">
            <span>⚠️</span>
            <?php echo $error; ?>
        </div>
        <?php endif; ?>

        <!-- Current Academic Year Info -->
        <div class="current-year-badge" style="margin-bottom: 20px;">
            ንቁ ዘመን: <?php echo $current_ethiopian_year; ?> ዓ.ም
        </div>

        <!-- Add Teacher Form -->
        <div class="section">
            <div class="section-header">
                <h2><span>➕</span> አዲስ መምህር መመዝገቢያ</h2>
            </div>
            
            <div class="add-teacher-form">
                <form method="POST">
                    <div class="form-grid">
                        <div class="form-group">
                            <label>ሙሉ ስም (Full Name) <span style="color: var(--error-red);">*</span></label>
                            <input type="text" name="name" class="form-control" required 
                                   placeholder="ሙሉ ስም ያስገቡ">
                        </div>
                        <div class="form-group">
                            <label>የተጠቃሚ ስም (Username) <span style="color: var(--error-red);">*</span></label>
                            <input type="text" name="username" class="form-control" required 
                                   placeholder="ለምሳሌ: memhir_abebe">
                        </div>
                        <div class="form-group">
                            <label>ስልክ ቁጥር (Phone)</label>
                            <input type="text" name="phone" class="form-control" 
                                   placeholder="0912345678">
                        </div>
                    </div>
                    
                    <div class="info-box">
                        <span>ℹ️</span>
                        <div>
                            <strong>የመጀመሪያ የይለፍ ቃል (Default Password):</strong> 123<br>
                            <small>መምህሩ ለመጀመሪያ ጊዜ ሲገባ ይለውጠዋል</small>
                        </div>
                    </div>
                    
                    <button type="submit" name="add_teacher" class="btn btn-primary">
                        <span>➕</span> መምህር አስመዝግብ
                    </button>
                </form>
            </div>
        </div>

        <!-- Teachers List with History -->
        <div class="section">
            <div class="section-header">
                <h2><span>👨‍🏫</span> የመምህራን ዝርዝር እና ታሪክ</h2>
                <span style="background: var(--gold-pale); padding: 5px 15px; border-radius: 20px;">
                    <?php echo mysqli_num_rows($teachers); ?> መምህራን
                </span>
            </div>
            
            <?php if($teachers && mysqli_num_rows($teachers) > 0): ?>
            <div class="teachers-grid">
                <?php while($teacher = mysqli_fetch_assoc($teachers)): 
                    $teacher_id = $teacher['id'];
                    $has_history = isset($teacher_history[$teacher_id]);
                    $teacher_photo = $teacher['photo'] ?: null;
                ?>
                <div class="teacher-card">
                    <div class="teacher-header">
                        <div class="teacher-avatar">
                            <?php if($teacher_photo): ?>
                            <img src="<?php echo htmlspecialchars($teacher_photo); ?>" alt="<?php echo htmlspecialchars($teacher['name']); ?>" onerror="this.style.display='none'; this.parentElement.innerHTML='<?php echo mb_substr($teacher['name'], 0, 1); ?>';">
                            <?php else: ?>
                            <?php echo mb_substr($teacher['name'], 0, 1); ?>
                            <?php endif; ?>
                        </div>
                        <div class="teacher-title">
                            <div>
                                <a href="teacher_profile.php?id=<?php echo $teacher['id']; ?>" class="teacher-name">
                                    <?php echo htmlspecialchars($teacher['name']); ?>
                                </a>
                                <span class="teacher-status <?php echo $teacher['first_login'] ? 'status-new' : 'status-active'; ?>">
                                    <?php echo $teacher['first_login'] ? '🆕 አዲስ' : '✅ ንቁ'; ?>
                                </span>
                            </div>
                            <div class="teacher-username">
                                @<?php echo htmlspecialchars($teacher['username']); ?>
                            </div>
                            <div class="teacher-phone">
                                <span>📱</span>
                                <?php echo htmlspecialchars($teacher['phone'] ?: 'ስልክ የለም'); ?>
                            </div>
                        </div>
                    </div>

                    <div class="teacher-actions">
                        <a href="teacher_profile.php?id=<?php echo $teacher['id']; ?>" class="btn btn-profile">
                            👤 መገለጫ
                        </a>
                        
                        <button onclick="editTeacher(<?php echo $teacher['id']; ?>, '<?php echo htmlspecialchars(addslashes($teacher['name'])); ?>', '<?php echo htmlspecialchars($teacher['username']); ?>', '<?php echo $teacher['phone']; ?>')" 
                                class="btn btn-edit">
                            ✏️ አስተካክል
                        </button>
                        
                        <form method="POST" style="display: inline;" 
                              onsubmit="return confirm('የይለፍ ቃል ወደ 123 መመለስ እርግጠኛ ነዎት?')">
                            <input type="hidden" name="teacher_id" value="<?php echo $teacher['id']; ?>">
                            <button type="submit" name="reset_password" class="btn btn-reset">
                                🔄 ይለፍ ቃል መልስ
                            </button>
                        </form>
                        
                        <?php if(!$has_history): ?>
                        <form method="POST" style="display: inline;" 
                              onsubmit="return confirm('መምህሩን መሰረዝ እርግጠኛ ነዎት?')">
                            <input type="hidden" name="teacher_id" value="<?php echo $teacher['id']; ?>">
                            <button type="submit" name="delete_teacher" class="btn btn-delete">
                                🗑️ ሰርዝ
                            </button>
                        </form>
                        <?php else: ?>
                        <button class="btn btn-delete" style="opacity: 0.5; cursor: not-allowed;" 
                                title="ይህ መምህር ታሪክ አለው መሰረዝ አይቻልም">
                            🗑️ መሰረዝ አይቻልም
                        </button>
                        <?php endif; ?>
                    </div>

                    <!-- Assignment History Timeline -->
                    <div class="timeline">
                        <?php if($has_history): ?>
                            <?php 
                            $teacher_years = $teacher_history[$teacher_id];
                            krsort($teacher_years);
                            foreach($teacher_years as $year => $semesters): 
                                $is_current = ($year == $current_ethiopian_year);
                            ?>
                            <div class="year-group">
                                <div class="year-header" onclick="toggleYear('year-<?php echo $teacher_id . '-' . $year; ?>')">
                                    <span class="year-badge"><?php echo $year; ?> ዓ.ም</span>
                                    <?php if($is_current): ?>
                                    <span class="year-status" style="color: var(--success-green);">(ንቁ)</span>
                                    <?php endif; ?>
                                    <span class="toggle-icon" id="icon-<?php echo $teacher_id . '-' . $year; ?>">▼</span>
                                </div>
                                
                                <div id="year-<?php echo $teacher_id . '-' . $year; ?>" style="display: <?php echo $is_current ? 'block' : 'none'; ?>;">
                                    <?php if(!empty($semesters['semester1'])): ?>
                                    <div class="semester-row">
                                        <div class="semester-badge semester-1">ሴሚስተር 1</div>
                                        <div class="classes-list">
                                            <?php foreach($semesters['semester1'] as $assignment): ?>
                                            <span class="class-tag <?php echo $assignment['locked'] ? 'locked' : ''; ?>">
                                                <?php if($assignment['locked']): ?>🔒 <?php endif; ?>
                                                <?php echo htmlspecialchars($assignment['class_name']); ?>
                                            </span>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                    <?php endif; ?>

                                    <?php if(!empty($semesters['semester2'])): ?>
                                    <div class="semester-row">
                                        <div class="semester-badge semester-2">ሴሚስተር 2</div>
                                        <div class="classes-list">
                                            <?php foreach($semesters['semester2'] as $assignment): ?>
                                            <span class="class-tag <?php echo $assignment['locked'] ? 'locked' : ''; ?>">
                                                <?php if($assignment['locked']): ?>🔒 <?php endif; ?>
                                                <?php echo htmlspecialchars($assignment['class_name']); ?>
                                            </span>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="no-data">
                                ይህ መምህር እስካሁን ምንም ክፍል አልተመደበም
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endwhile; ?>
            </div>
            <?php else: ?>
            <div class="empty-state">
                <span>👨‍🏫</span>
                <h3>ምንም መምህራን የሉም</h3>
                <p>እባክዎ ከላይ ባለው ቅጽ አዲስ መምህር ይመዝግቡ</p>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Edit Modal -->
    <div id="editModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeModal()">&times;</span>
            <h2 style="color: var(--brown-dark); margin-bottom: 20px;">የመምህር መረጃ አስተካክል</h2>
            
            <form method="POST" id="editForm">
                <input type="hidden" name="teacher_id" id="edit_id">
                
                <div class="form-group">
                    <label>ሙሉ ስም (Full Name) <span style="color: var(--error-red);">*</span></label>
                    <input type="text" name="name" id="edit_name" class="form-control" required>
                </div>
                
                <div class="form-group">
                    <label>የተጠቃሚ ስም (Username) <span style="color: var(--error-red);">*</span></label>
                    <input type="text" name="username" id="edit_username" class="form-control" required>
                </div>
                
                <div class="form-group">
                    <label>ስልክ ቁጥር (Phone)</label>
                    <input type="text" name="phone" id="edit_phone" class="form-control">
                </div>
                
                <button type="submit" name="edit_teacher" class="btn btn-primary" style="width: 100%;">
                    💾 አስቀምጥ
                </button>
            </form>
        </div>
    </div>

    <script>
        function editTeacher(id, name, username, phone) {
            document.getElementById('edit_id').value = id;
            document.getElementById('edit_name').value = name;
            document.getElementById('edit_username').value = username;
            document.getElementById('edit_phone').value = phone || '';
            document.getElementById('editModal').style.display = 'block';
        }

        function closeModal() {
            document.getElementById('editModal').style.display = 'none';
        }

        function toggleYear(elementId) {
            const yearDiv = document.getElementById(elementId);
            const icon = document.getElementById('icon-' + elementId.replace('year-', ''));
            
            if (yearDiv.style.display === 'none') {
                yearDiv.style.display = 'block';
                icon.innerHTML = '▼';
            } else {
                yearDiv.style.display = 'none';
                icon.innerHTML = '▶';
            }
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