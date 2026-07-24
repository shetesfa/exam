<?php
session_start();
require_once 'db.php';
requireAdmin();

$message = '';
$error = '';

// Handle Add User
if($_SERVER['REQUEST_METHOD'] == 'POST') {
    if(isset($_POST['add_user'])) {
        $name = mysqli_real_escape_string($conn, $_POST['name']);
        $username = mysqli_real_escape_string($conn, $_POST['username']);
        $phone = mysqli_real_escape_string($conn, $_POST['phone']);
        $role = mysqli_real_escape_string($conn, $_POST['role']);
        $password = hashPassword('123');
        
        // Check if username exists
        $check = mysqli_query($conn, "SELECT id FROM users WHERE username = '$username'");
        if(mysqli_num_rows($check) > 0) {
            $error = "ይህ የተጠቃሚ ስም ቀድሞውኑ አለ! (Username already exists!)";
        } else {
            $query = "INSERT INTO users (name, username, phone, role, password, first_login) 
                      VALUES ('$name', '$username', '$phone', '$role', '$password', 1)";
            if(mysqli_query($conn, $query)) {
                $new_id = mysqli_insert_id($conn);
                $message = "ተጠቃሚ በተሳካ ሁኔታ ተፈጥሯል! የይለፍ ቃል: 123";
            } else {
                $error = "ስህተት ተከስቷል! " . mysqli_error($conn);
            }
        }
    }
    
    if(isset($_POST['edit_user'])) {
        $user_id = mysqli_real_escape_string($conn, $_POST['user_id']);
        $name = mysqli_real_escape_string($conn, $_POST['name']);
        $username = mysqli_real_escape_string($conn, $_POST['username']);
        $phone = mysqli_real_escape_string($conn, $_POST['phone']);
        $role = mysqli_real_escape_string($conn, $_POST['role']);
        
        $query = "UPDATE users SET name='$name', username='$username', phone='$phone', role='$role' WHERE id=$user_id";
        if(mysqli_query($conn, $query)) {
            $message = "የተጠቃሚ መረጃ ተሻሽሏል!";
        } else {
            $error = "ስህተት ተከስቷል! " . mysqli_error($conn);
        }
    }
    
    if(isset($_POST['delete_user'])) {
        $user_id = mysqli_real_escape_string($conn, $_POST['user_id']);
        
        // Don't allow deleting own account
        if($user_id == $_SESSION['user_id']) {
            $error = "የራስዎን አካውንት መሰረዝ አይችሉም!";
        } else {
            $query = "DELETE FROM users WHERE id=$user_id";
            if(mysqli_query($conn, $query)) {
                $message = "ተጠቃሚ ተሰርዟል!";
            } else {
                $error = "ስህተት ተከስቷል! " . mysqli_error($conn);
            }
        }
    }
    
    if(isset($_POST['reset_password'])) {
        $user_id = mysqli_real_escape_string($conn, $_POST['user_id']);
        $new_password = hashPassword('123');
        
        $query = "UPDATE users SET password='$new_password', first_login=1 WHERE id=$user_id";
        if(mysqli_query($conn, $query)) {
            $message = "የይለፍ ቃል ወደ 123 ተመልሷል!";
        } else {
            $error = "ስህተት ተከስቷል! " . mysqli_error($conn);
        }
    }
}

// Get all users
$users_query = "SELECT * FROM users ORDER BY role, name";
$users = mysqli_query($conn, $users_query);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/png" href="images/icon.png">
    <title>ተጠቃሚዎች አስተዳደር | አጸደ ትጉሃን</title>
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
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
            animation: slideDown 0.4s ease;
        }

        @keyframes slideDown {
            from { opacity: 0; transform: translateY(-20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .success {
            background: #D1FAE5;
            color: #065F46;
            border-left: 4px solid #10B981;
        }

        .error {
            background: #FEE2E2;
            color: #991B1B;
            border-left: 4px solid #EF4444;
        }

        /* Form Section */
        .form-section {
            background: white;
            border-radius: 20px;
            padding: 25px;
            margin-bottom: 30px;
            border: 2px solid var(--gold-primary);
        }

        .form-title {
            color: var(--brown-dark);
            font-size: 20px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
            padding-bottom: 10px;
            border-bottom: 2px solid var(--gold-pale);
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }

        .form-group {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .form-group label {
            color: var(--brown-dark);
            font-weight: 600;
            font-size: 13px;
        }

        .form-group input, .form-group select {
            padding: 12px;
            border: 2px solid #E2E8F0;
            border-radius: 10px;
            font-size: 14px;
        }

        .form-group input:focus, .form-group select:focus {
            outline: none;
            border-color: var(--gold-primary);
        }

        .btn {
            padding: 12px 25px;
            border: none;
            border-radius: 10px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-size: 14px;
            text-decoration: none;
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

        .btn-reset {
            background: #F59E0B;
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

        /* Table */
        .section-card {
            background: white;
            border-radius: 20px;
            padding: 25px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.08);
            border: 1px solid #FFD700;
        }

        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #FFD700;
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

        .table-responsive {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 700px;
        }

        th {
            background: #8B4513;
            color: white;
            padding: 12px 10px;
            text-align: left;
            border: 1px solid #DAA520;
            font-size: 13px;
        }

        td {
            padding: 12px 10px;
            border-bottom: 1px solid #FFD700;
        }

        tr:hover {
            background: #FFF8DC;
        }

        .role-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 30px;
            font-size: 11px;
            font-weight: 600;
        }

        .role-admin {
            background: #8B4513;
            color: #FFD700;
        }

        .role-teacher {
            background: #3B82F6;
            color: white;
        }

        .role-attendance_submitter {
            background: #8B5CF6;
            color: white;
        }

        .action-buttons {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }
        .logo-img {
            width: 50px; height: 50px; border-radius: 50%; object-fit: cover;
            border: 3px solid var(--gold-primary); background: white;
        }
        .info-note {
            background: #EFF6FF;
            border-left: 4px solid var(--info-blue);
            padding: 15px;
            border-radius: 10px;
            margin-top: 20px;
            font-size: 13px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        @media (max-width: 768px) {
            .form-grid {
                grid-template-columns: 1fr;
            }
            
            .action-buttons {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="header-content">
            <div class="logo-area">
            <img src="images/icon.png" alt="Logo" class="logo-img" onerror="this.innerHTML='⛪'">
                <div class="title">
                    <h1>አጸደ ትጉሃን ሰንበት ትምህርት ቤት</h1>
                    <p>ተጠቃሚዎች አስተዳደር | User Management</p>
                </div>
            </div>
            <a href="dashboard_admin.php" class="nav-link" style="background: var(--gold-primary); color: var(--brown-dark);">
                ← ወደ ዳሽቦርድ
            </a>
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
        <div class="message success">✅ <?php echo $message; ?></div>
        <?php endif; ?>

        <?php if($error): ?>
        <div class="message error">⚠️ <?php echo $error; ?></div>
        <?php endif; ?>

        <!-- Add User Form -->
        <div class="form-section">
            <div class="form-title">
                <span>➕</span> አዲስ ተጠቃሚ መፍጠሪያ / Add New User
            </div>
            <form method="POST">
                <div class="form-grid">
                    <div class="form-group">
                        <label>ሙሉ ስም / Full Name</label>
                        <input type="text" name="name" required placeholder="ለምሳሌ: ዲ/ን ኪብረአብ ዘለለም">
                    </div>
                    <div class="form-group">
                        <label>የተጠቃሚ ስም / Username</label>
                        <input type="text" name="username" required placeholder="ለምሳሌ: kibreab">
                    </div>
                    <div class="form-group">
                        <label>ስልክ / Phone</label>
                        <input type="text" name="phone" placeholder="0912345678">
                    </div>
                    <div class="form-group">
                        <label>ሚና / Role</label>
                        <select name="role" required>
                            <option value="teacher">👨‍🏫 መምህር / Teacher</option>
                            <option value="attendance_submitter">📋 የክፍል ጸሐፊ / Attendance Submitter</option>
                            <option value="admin">👑 አስተዳዳሪ / Admin</option>
                        </select>
                    </div>
                </div>
                <div class="info-note">
                    <span>ℹ️</span>
                    <div>
                        <strong>የመጀመሪያ የይለፍ ቃል / Default Password:</strong> 123<br>
                        <small>ተጠቃሚው ለመጀመሪያ ጊዜ ሲገባ ይለውጠዋል / User will change on first login</small>
                    </div>
                </div>
                <button type="submit" name="add_user" class="btn btn-primary" style="margin-top: 20px;">
                    <span>➕</span> ተጠቃሚ ፍጠር / Create User
                </button>
            </form>
        </div>

        <!-- Users List -->
        <div class="section-card">
            <div class="section-header">
                <h2>
                    <span>📋</span> 
                    የተጠቃሚዎች ዝርዝር / Users List
                </h2>
                <span><?php echo mysqli_num_rows($users); ?> ተጠቃሚዎች</span>
            </div>

            <div class="table-responsive">
                <?php if($users && mysqli_num_rows($users) > 0): ?>
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>ሙሉ ስም</th>
                            <th>የተጠቃሚ ስም</th>
                            <th>ስልክ</th>
                            <th>ሚና</th>
                            <th>ሁኔታ</th>
                            <th>ድርጊት</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while($user = mysqli_fetch_assoc($users)): 
                            $role_class = '';
                            $role_text = '';
                            if($user['role'] == 'admin') {
                                $role_class = 'role-admin';
                                $role_text = '👑 አስተዳዳሪ';
                            } elseif($user['role'] == 'teacher') {
                                $role_class = 'role-teacher';
                                $role_text = '👨‍🏫 መምህር';
                            } else {
                                $role_class = 'role-attendance_submitter';
                                $role_text = '📋 የክፍል ጸሐፊ';
                            }
                        ?>
                        <tr>
                            <td><?php echo $user['id']; ?></td>
                            <td><strong><?php echo htmlspecialchars($user['name']); ?></strong></td>
                            <td><?php echo htmlspecialchars($user['username']); ?></td>
                            <td><?php echo htmlspecialchars($user['phone'] ?: '-'); ?></td>
                            <td><span class="role-badge <?php echo $role_class; ?>"><?php echo $role_text; ?></span></td>
                            <td>
                                <?php if($user['first_login']): ?>
                                    <span style="color: #F59E0B;">🆕 አዲስ</span>
                                <?php else: ?>
                                    <span style="color: #10B981;">✅ ንቁ</span>
                                <?php endif; ?>
                             </td>
                            <td class="action-buttons">
                                <button onclick="editUser(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars(addslashes($user['name'])); ?>', '<?php echo $user['username']; ?>', '<?php echo $user['phone']; ?>', '<?php echo $user['role']; ?>')" 
                                        class="btn btn-edit">
                                    ✏️ አስተካክል
                                </button>
                                <?php if($user['id'] != $_SESSION['user_id']): ?>
                                <form method="POST" style="display: inline;" onsubmit="return confirm('የይለፍ ቃል ወደ 123 መመለስ እርግጠኛ ነዎት?')">
                                    <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                    <button type="submit" name="reset_password" class="btn btn-reset">
                                        🔄 ይለፍ ቃል መልስ
                                    </button>
                                </form>
                                <form method="POST" style="display: inline;" onsubmit="return confirm('ተጠቃሚውን መሰረዝ እርግጠኛ ነዎት?')">
                                    <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                    <button type="submit" name="delete_user" class="btn btn-delete">
                                        🗑️ ሰርዝ
                                    </button>
                                </form>
                                <?php else: ?>
                                    <button class="btn btn-delete" style="opacity:0.5; cursor:not-allowed;" disabled>
                                        🗑️ አይቻልም
                                    </button>
                                <?php endif; ?>
                             </td>
                         </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
                <?php else: ?>
                <div class="no-data" style="text-align:center; padding:50px;">
                    <span style="font-size:48px;">👥</span>
                    <p>ምንም ተጠቃሚዎች የሉም</p>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Edit Modal -->
    <div id="editModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:1000; justify-content:center; align-items:center;">
        <div style="background:white; width:90%; max-width:500px; padding:30px; border-radius:20px; border:3px solid #FFD700;">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
                <h2 style="color:#8B4513;">✏️ የተጠቃሚ መረጃ አስተካክል</h2>
                <span onclick="closeModal()" style="font-size:28px; cursor:pointer;">&times;</span>
            </div>
            <form method="POST" id="editForm">
                <input type="hidden" name="user_id" id="edit_id">
                <div class="form-group" style="margin-bottom:15px;">
                    <label>ሙሉ ስም</label>
                    <input type="text" name="name" id="edit_name" class="form-control" style="width:100%; padding:12px; border:2px solid #E2E8F0; border-radius:10px;" required>
                </div>
                <div class="form-group" style="margin-bottom:15px;">
                    <label>የተጠቃሚ ስም</label>
                    <input type="text" name="username" id="edit_username" class="form-control" style="width:100%; padding:12px; border:2px solid #E2E8F0; border-radius:10px;" required>
                </div>
                <div class="form-group" style="margin-bottom:15px;">
                    <label>ስልክ</label>
                    <input type="text" name="phone" id="edit_phone" class="form-control" style="width:100%; padding:12px; border:2px solid #E2E8F0; border-radius:10px;">
                </div>
                <div class="form-group" style="margin-bottom:20px;">
                    <label>ሚና</label>
                    <select name="role" id="edit_role" class="form-control" style="width:100%; padding:12px; border:2px solid #E2E8F0; border-radius:10px;" required>
                        <option value="teacher">👨‍🏫 መምህር / Teacher</option>
                        <option value="attendance_submitter">📋 የክፍል ጸሐፊ / Attendance Submitter</option>
                        <option value="admin">👑 አስተዳዳሪ / Admin</option>
                    </select>
                </div>
                <button type="submit" name="edit_user" class="btn btn-primary" style="width:100%;">
                    💾 አስቀምጥ / Save
                </button>
            </form>
        </div>
    </div>

    <style>
        .form-control:focus {
            outline: none;
            border-color: #FFD700;
            box-shadow: 0 0 0 3px rgba(255,215,0,0.2);
        }
    </style>

    <script>
        function editUser(id, name, username, phone, role) {
            document.getElementById('edit_id').value = id;
            document.getElementById('edit_name').value = name;
            document.getElementById('edit_username').value = username;
            document.getElementById('edit_phone').value = phone || '';
            document.getElementById('edit_role').value = role;
            document.getElementById('editModal').style.display = 'flex';
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