<?php
session_start();
require_once 'db.php';
requireAdmin();

$message = '';
$error = '';

// Handle Add/Edit/Delete
if($_SERVER['REQUEST_METHOD'] == 'POST') {
    if(isset($_POST['add_class'])) {
        $name = mysqli_real_escape_string($conn, $_POST['name']);
        $description = mysqli_real_escape_string($conn, $_POST['description']);
        
        $query = "INSERT INTO classes (name, description) VALUES ('$name', '$description')";
        if(mysqli_query($conn, $query)) {
            $message = "ክፍል በተሳካ ሁኔታ ተፈጥሯል! (Class created successfully!)";
        } else {
            $error = "ስህተት ተከስቷል! (Error: " . mysqli_error($conn) . ")";
        }
    }
    
    if(isset($_POST['edit_class'])) {
        $class_id = mysqli_real_escape_string($conn, $_POST['class_id']);
        $name = mysqli_real_escape_string($conn, $_POST['name']);
        $description = mysqli_real_escape_string($conn, $_POST['description']);
        
        $query = "UPDATE classes SET name='$name', description='$description' WHERE id=$class_id";
        if(mysqli_query($conn, $query)) {
            $message = "የክፍል መረጃ ተሻሽሏል! (Class updated successfully!)";
        } else {
            $error = "ስህተት ተከስቷል! (Error: " . mysqli_error($conn) . ")";
        }
    }
    
    if(isset($_POST['delete_class'])) {
        $class_id = mysqli_real_escape_string($conn, $_POST['class_id']);
        
        // Check if class has students
        $check_students = mysqli_query($conn, "SELECT id FROM students WHERE class_id=$class_id");
        if(mysqli_num_rows($check_students) > 0) {
            $error = "ይህ ክፍል ተማሪዎች አሉት! መጀመሪያ ተማሪዎቹን ያስተላልፉ (Class has students!)";
        } else {
            // Check if class has teacher assignments
            $check_assignments = mysqli_query($conn, "SELECT id FROM teacher_class WHERE class_id=$class_id");
            if(mysqli_num_rows($check_assignments) > 0) {
                // Delete assignments first
                mysqli_query($conn, "DELETE FROM teacher_class WHERE class_id=$class_id");
            }
            
            $query = "DELETE FROM classes WHERE id=$class_id";
            if(mysqli_query($conn, $query)) {
                $message = "ክፍል ተሰርዟል! (Class deleted successfully!)";
            } else {
                $error = "ስህተት ተከስቷል! (Error: " . mysqli_error($conn) . ")";
            }
        }
    }
}

// Get all classes with statistics
$classes_query = "SELECT c.*, 
                  COUNT(DISTINCT s.id) as student_count,
                  COUNT(DISTINCT tc.teacher_id) as teacher_count
                  FROM classes c
                  LEFT JOIN students s ON c.id = s.class_id
                  LEFT JOIN teacher_class tc ON c.id = tc.class_id
                  GROUP BY c.id
                  ORDER BY c.name";
$classes = mysqli_query($conn, $classes_query);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/png" href="images\icon.png">
    <title>ክፍሎች አስተዳደር | አጸደ ትጉሃን </title>
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
        }

        .logo-area {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .logo-icon {
            width: 50px;
            height: 50px;
            background: var(--gold-primary);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            color: var(--brown-dark);
        }

        .title h1 {
            font-size: 20px;
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
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }

        .success {
            background: #D1FAE5;
            color: var(--success-green);
            border-left: 4px solid var(--success-green);
        }

        .error {
            background: #FEE2E2;
            color: var(--error-red);
            border-left: 4px solid var(--error-red);
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
            font-size: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
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

        .btn-view {
            background: var(--gold-primary);
            color: var(--brown-dark);
            padding: 6px 12px;
            font-size: 12px;
            text-decoration: none;
            border-radius: 4px;
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
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
            transition: all 0.3s;
        }

        .form-control:focus {
            outline: none;
            border-color: var(--gold-primary);
            box-shadow: 0 0 0 3px rgba(255,215,0,0.2);
        }

        textarea.form-control {
            min-height: 100px;
            resize: vertical;
        }

        .classes-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 25px;
            margin-top: 20px;
        }

        .class-card {
            background: white;
            border-radius: 15px;
            padding: 20px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            border: 2px solid var(--gold-pale);
            transition: all 0.3s;
            position: relative;
            overflow: hidden;
        }

        .class-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.15);
            border-color: var(--gold-primary);
        }

        .class-card::before {
            content: '📚';
            position: absolute;
            top: 10px;
            right: 10px;
            font-size: 40px;
            opacity: 0.1;
        }

        .class-name {
            color: var(--brown-dark);
            font-size: 22px;
            font-weight: bold;
            margin-bottom: 10px;
            padding-right: 50px;
        }

        .class-description {
            color: #666;
            font-size: 14px;
            margin-bottom: 15px;
            line-height: 1.5;
        }

        .class-stats {
            display: flex;
            gap: 20px;
            margin: 15px 0;
            padding: 10px 0;
            border-top: 1px solid var(--gold-pale);
            border-bottom: 1px solid var(--gold-pale);
        }

        .stat-item {
            text-align: center;
            flex: 1;
        }

        .stat-value {
            font-size: 20px;
            font-weight: bold;
            color: var(--brown-dark);
        }

        .stat-label {
            font-size: 11px;
            color: #666;
            text-transform: uppercase;
        }

        .class-actions {
            display: flex;
            gap: 10px;
            justify-content: flex-end;
            margin-top: 15px;
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
            animation: fadeIn 0.3s;
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        .modal-content {
            background: white;
            width: 90%;
            max-width: 500px;
            margin: 50px auto;
            padding: 30px;
            border-radius: 15px;
            border: 3px solid var(--gold-primary);
            animation: slideDown 0.3s;
        }

        @keyframes slideDown {
            from {
                transform: translateY(-50px);
                opacity: 0;
            }
            to {
                transform: translateY(0);
                opacity: 1;
            }
        }

        .close {
            float: right;
            font-size: 24px;
            cursor: pointer;
            color: var(--brown-dark);
            transition: all 0.3s;
        }

        .close:hover {
            color: var(--error-red);
            transform: scale(1.1);
        }

        .stats-overview {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: linear-gradient(135deg, var(--gold-pale), white);
            padding: 20px;
            border-radius: 10px;
            text-align: center;
            border: 2px solid var(--gold-primary);
        }

        .stat-card .number {
            font-size: 36px;
            font-weight: bold;
            color: var(--brown-dark);
        }

        .stat-card .label {
            color: #666;
            font-size: 14px;
        }

        @media (max-width: 768px) {
            .classes-grid {
                grid-template-columns: 1fr;
            }
            
            .class-stats {
                flex-direction: column;
                gap: 10px;
            }
            
            .nav-links {
                justify-content: center;
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
                    <p>ክፍሎች አስተዳደር | Class Management</p>
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
    </div>

    <div class="container">
        <?php if($message): ?>
        <div class="message success">✅ <?php echo $message; ?></div>
        <?php endif; ?>

        <?php if($error): ?>
        <div class="message error">⚠️ <?php echo $error; ?></div>
        <?php endif; ?>

        <!-- Statistics Overview -->
        <?php
        $total_classes = mysqli_num_rows($classes);
        $total_students = 0;
        $total_teachers = 0;
        mysqli_data_seek($classes, 0);
        while($class = mysqli_fetch_assoc($classes)) {
            $total_students += $class['student_count'];
            $total_teachers += $class['teacher_count'];
        }
        ?>
        <div class="stats-overview">
            <div class="stat-card">
                <div class="number"><?php echo $total_classes; ?></div>
                <div class="label">ጠቅላላ ክፍሎች</div>
            </div>
            <div class="stat-card">
                <div class="number"><?php echo $total_students; ?></div>
                <div class="label">ጠቅላላ ተማሪዎች</div>
            </div>
            <div class="stat-card">
                <div class="number"><?php echo $total_teachers; ?></div>
                <div class="label">የተመደቡ መምህራን</div>
            </div>
        </div>

        <!-- Add Class Form -->
        <div class="section">
            <div class="section-header">
                <h2><span>➕</span> አዲስ ክፍል መፍጠሪያ</h2>
            </div>
            <form method="POST">
                <div class="form-grid">
                    <div class="form-group">
                        <label>የክፍል ስም (Class Name) <span style="color: var(--error-red);">*</span></label>
                        <input type="text" name="name" class="form-control" required 
                               placeholder="ለምሳሌ: 7ኛ ክፍል (Grade 7)">
                    </div>
                    <div class="form-group">
                        <label>መግለጫ (Description)</label>
                        <textarea name="description" class="form-control" 
                                  placeholder="ስለ ክፍሉ አጭር መግለጫ..."></textarea>
                    </div>
                </div>
                <button type="submit" name="add_class" class="btn btn-primary">
                    <span>➕</span> ክፍል ፍጠር
                </button>
            </form>
        </div>

        <!-- Classes List -->
        <div class="section">
            <div class="section-header">
                <h2><span>📚</span> የክፍሎች ዝርዝር</h2>
                <span><?php echo $total_classes; ?> ክፍሎች</span>
            </div>

            <?php if($classes && mysqli_num_rows($classes) > 0): ?>
            <div class="classes-grid">
                <?php 
                mysqli_data_seek($classes, 0);
                while($class = mysqli_fetch_assoc($classes)): 
                ?>
                <div class="class-card">
                    <div class="class-name"><?php echo htmlspecialchars($class['name']); ?></div>
                    <?php if($class['description']): ?>
                    <div class="class-description"><?php echo htmlspecialchars($class['description']); ?></div>
                    <?php endif; ?>
                    
                    <div class="class-stats">
                        <div class="stat-item">
                            <div class="stat-value"><?php echo $class['student_count']; ?></div>
                            <div class="stat-label">ተማሪዎች</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-value"><?php echo $class['teacher_count']; ?></div>
                            <div class="stat-label">መምህራን</div>
                        </div>
                    </div>

                    <div class="class-actions">
                        <a href="manage_students.php?class_id=<?php echo $class['id']; ?>" class="btn-view">
                            👥 ተማሪዎች
                        </a>
                        <button onclick="editClass(<?php echo $class['id']; ?>, '<?php echo htmlspecialchars($class['name']); ?>', '<?php echo htmlspecialchars($class['description']); ?>')" 
                                class="btn-edit">
                            ✏️ አስተካክል
                        </button>
                        <form method="POST" style="display: inline;" 
                              onsubmit="return confirm('ክፍሉን መሰረዝ እርግጠኛ ነዎት? ይህ ክዋኔ ሊቀለበስ አይችልም!')">
                            <input type="hidden" name="class_id" value="<?php echo $class['id']; ?>">
                            <button type="submit" name="delete_class" class="btn-delete">
                                🗑️ ሰርዝ
                            </button>
                        </form>
                    </div>
                </div>
                <?php endwhile; ?>
            </div>
            <?php else: ?>
            <div style="text-align: center; padding: 50px;">
                <span style="font-size: 50px; display: block; margin-bottom: 20px;">📚</span>
                <h3 style="color: #666; margin-bottom: 10px;">ምንም ክፍሎች የሉም</h3>
                <p style="color: #999;">እባክዎ ከላይ ባለው ቅጽ አዲስ ክፍል ይፍጠሩ</p>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Edit Modal -->
    <div id="editModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeModal()">&times;</span>
            <h2 style="color: var(--brown-dark); margin-bottom: 20px;">የክፍል መረጃ አስተካክል</h2>
            <form method="POST" id="editForm">
                <input type="hidden" name="class_id" id="edit_id">
                <div class="form-group">
                    <label>የክፍል ስም <span style="color: var(--error-red);">*</span></label>
                    <input type="text" name="name" id="edit_name" class="form-control" required>
                </div>
                <div class="form-group">
                    <label>መግለጫ</label>
                    <textarea name="description" id="edit_description" class="form-control" rows="3"></textarea>
                </div>
                <button type="submit" name="edit_class" class="btn btn-primary">
                    💾 አስቀምጥ
                </button>
            </form>
        </div>
    </div>

    <script>
        function editClass(id, name, description) {
            document.getElementById('edit_id').value = id;
            document.getElementById('edit_name').value = name;
            document.getElementById('edit_description').value = description || '';
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