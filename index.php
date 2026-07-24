<?php
// ===== Dynamic session name based on role (for multi-tab login) =====
if (isset($_GET['role'])) {
    session_name($_GET['role'] . '_session');
}
session_start();

require_once 'db.php';

// Redirect if already logged in
if (isset($_SESSION['user_id']) && !empty($_SESSION['user_id'])) {
    if ($_SESSION['role'] == 'admin') {
        header("Location: dashboard_admin.php?role=admin");
        exit();
    } elseif ($_SESSION['role'] == 'teacher') {
        header("Location: dashboard_teacher.php?role=teacher");
        exit();
    } elseif ($_SESSION['role'] == 'attendance_submitter') {
        header("Location: dashboard_attendance.php?role=attendance_submitter");
        exit();
    }
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = mysqli_real_escape_string($conn, $_POST['username']);
    $password = $_POST['password'];

    // Login with username OR name (backward compatible)
    $query = "SELECT * FROM users WHERE username = '$username' OR name = '$username'";
    $result = mysqli_query($conn, $query);

    if (!$result) {
        $error = "የውሂብ ጎታ ስህተት! (Database error!)";
    } elseif (mysqli_num_rows($result) == 1) {
        $user = mysqli_fetch_assoc($result);

        if (password_verify($password, $user['password'])) {
            // Regenerate session ID for security
            session_regenerate_id(true);

            // Set session values
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_name'] = $user['name'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['first_login'] = $user['first_login'];

            // Redirect based on role
            if ($user['first_login'] == 1 && $user['role'] == 'teacher') {
                header("Location: change_password.php?role=teacher");
                exit();
            } elseif ($user['role'] == 'admin') {
                header("Location: dashboard_admin.php?role=admin");
                exit();
            } elseif ($user['role'] == 'attendance_submitter') {
                header("Location: dashboard_attendance.php?role=attendance_submitter");
                exit();
            } else {
                header("Location: dashboard_teacher.php?role=teacher");
                exit();
            }
        } else {
            $error = "የተሳሳተ የይለፍ ቃል! (Incorrect password!)";
        }
    } elseif (mysqli_num_rows($result) > 1) {
        // Multiple users with same name, try exact username match
        $query = "SELECT * FROM users WHERE username = '$username'";
        $result = mysqli_query($conn, $query);
        
        if (mysqli_num_rows($result) == 1) {
            $user = mysqli_fetch_assoc($result);
            if (password_verify($password, $user['password'])) {
                session_regenerate_id(true);
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_name'] = $user['name'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['first_login'] = $user['first_login'];

                if ($user['first_login'] == 1 && $user['role'] == 'teacher') {
                    header("Location: change_password.php?role=teacher");
                } elseif ($user['role'] == 'admin') {
                    header("Location: dashboard_admin.php?role=admin");
                } elseif ($user['role'] == 'attendance_submitter') {
                    header("Location: dashboard_attendance.php?role=attendance_submitter");
                } else {
                    header("Location: dashboard_teacher.php?role=teacher");
                }
                exit();
            }
        }
        $error = "እባክዎ የተጠቃሚ ስም ይጠቀሙ! (Please use username!)";
    } else {
        $error = "የተጠቃሚ ስም ወይም ስም አልተገኘም! (Username or name not found!)";
    }
}

// Check if redirected from student portal logout
$student_msg = '';
if (isset($_GET['msg']) && $_GET['msg'] == 'student_logout') {
    $student_msg = '✅ ከተማሪ ገፅ ወጥተዋል!';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
    <link rel="icon" type="image/png" href="images/icon.png">
    <title>አጸደ ትጉሃን ሰንበት ትምህርት ቤት</title>
    <style>
        :root {
            --brown-dark: #8B4513;
            --brown-medium: #A52A2A;
            --gold-primary: #FFD700;
            --gold-dark: #DAA520;
            --gold-pale: #FFF8DC;
            --error-red: #EF4444;
            --success-green: #10B981;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        body {
            min-height: 100vh;
            background: linear-gradient(135deg, #8B4513 0%, #A52A2A 50%, #DAA520 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .container {
            width: 100%;
            max-width: 450px;
        }

        .welcome-card {
            background: rgba(255, 255, 255, 0.98);
            border-radius: 20px;
            padding: 40px 30px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.3);
            border: 3px solid var(--gold-primary);
            animation: slideUp 0.8s ease;
        }

        @keyframes slideUp {
            from { opacity: 0; transform: translateY(30px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .logo-section {
            text-align: center;
            margin-bottom: 30px;
        }

        .logo {
            width: 120px;
            height: 120px;
            margin: 0 auto 20px;
            background: linear-gradient(135deg, var(--gold-primary) 0%, var(--gold-dark) 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 4px solid var(--brown-dark);
            box-shadow: 0 10px 20px rgba(139, 69, 19, 0.3);
        }

        .logo img {
            width: 90px;
            height: 90px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid white;
        }

        .logo-icon {
            font-size: 50px;
            color: var(--brown-dark);
            background: white;
            width: 100px;
            height: 100px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .welcome-title {
            color: var(--brown-dark);
            font-size: 24px;
            font-weight: bold;
            margin-bottom: 10px;
        }

        .welcome-subtitle {
            color: var(--brown-medium);
            font-size: 18px;
            margin-bottom: 5px;
        }

        .amharic {
            font-size: 20px;
            color: var(--brown-medium);
            margin-top: 10px;
            border-top: 2px solid var(--gold-primary);
            padding-top: 15px;
        }

        .login-form {
            margin-top: 30px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: var(--brown-dark);
            font-weight: 600;
            font-size: 14px;
        }

        .input-group {
            position: relative;
        }

        .input-icon {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--gold-dark);
            font-size: 18px;
        }

        .form-control {
            width: 100%;
            padding: 15px 15px 15px 45px;
            border: 2px solid #E2E8F0;
            border-radius: 12px;
            font-size: 16px;
            transition: all 0.3s ease;
            background: white;
        }

        .form-control:focus {
            outline: none;
            border-color: var(--gold-primary);
            box-shadow: 0 0 0 3px rgba(255, 215, 0, 0.2);
        }

        .btn-login {
            width: 100%;
            padding: 16px;
            background: linear-gradient(135deg, #FFD700 0%, #DAA520 100%);
            color: #8B4513;
            border: 2px solid #FFD700;
            border-radius: 12px;
            font-size: 18px;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 5px 15px rgba(139, 69, 19, 0.2);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }

        .btn-login:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 25px rgba(139, 69, 19, 0.3);
            border-color: var(--brown-dark);
        }

        .error-message {
            background: #FEE2E2;
            color: var(--error-red);
            padding: 15px;
            border-radius: 12px;
            margin-bottom: 20px;
            border-left: 4px solid var(--error-red);
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 10px;
            animation: shake 0.5s ease;
        }

        .success-message {
            background: #D1FAE5;
            color: var(--success-green);
            padding: 15px;
            border-radius: 12px;
            margin-bottom: 20px;
            border-left: 4px solid var(--success-green);
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            10%, 30%, 50%, 70%, 90% { transform: translateX(-5px); }
            20%, 40%, 60%, 80% { transform: translateX(5px); }
        }

        .student-portal-link {
            text-align: center;
            margin-top: 20px;
            padding-top: 20px;
            border-top: 2px solid var(--gold-pale);
        }

        .student-portal-link a {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            padding: 15px 30px;
            background: linear-gradient(135deg, #10B981, #059669);
            color: white;
            border-radius: 12px;
            text-decoration: none;
            font-weight: 600;
            font-size: 16px;
            transition: all 0.3s;
        }

        .student-portal-link a:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 25px rgba(16, 185, 129, 0.3);
        }

        .student-portal-link .small-text {
            font-size: 12px;
            opacity: 0.8;
            display: block;
            margin-top: 8px;
        }

        /* Developer Footer */
        .developer-footer {
            text-align: center;
            padding: 15px 20px;
            margin-top: 20px;
            background: rgba(255, 255, 255, 0.15);
            border-radius: 12px;
            border: 1px solid rgba(255, 215, 0, 0.3);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
        }
        .developer-footer .dev-text {
            color: rgba(255, 255, 255, 0.9);
            font-size: 12px;
            letter-spacing: 0.5px;
        }
        .developer-footer .dev-name {
            color: #FFD700;
            font-weight: 700;
            font-size: 13px;
        }
        .developer-footer .dev-phone {
            color: #E2E8F0;
            font-weight: 600;
        }
        .developer-footer .dev-telegram {
            color: #93C5FD;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s;
        }
        .developer-footer .dev-telegram:hover {
            color: #FFD700;
            text-decoration: underline;
        }
        .developer-footer .dev-divider {
            color: rgba(255, 255, 255, 0.3);
            margin: 0 10px;
        }
        .developer-footer .dev-icon {
            font-size: 14px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="welcome-card">
            <div class="logo-section">
                <div class="logo">
                    <?php
                    if(file_exists('images/icon.png')) {
                        echo '<img src="images/icon.png" alt="Atsede Teguhan Logo">';
                    } else {
                        echo '<div class="logo-icon">⛪</div>';
                    }
                    ?>
                </div>
                <div class="welcome-title">
                    አጸደ ትጉሃን ሰንበት ትምህርት ቤት
                </div>
                <div class="welcome-subtitle">
                    Atsede Teguhan Sunday School
                </div>
                <div class="amharic">
                    የውጤት መመዝገብያ እና መረጃ
                </div>
            </div>

            <?php if($student_msg): ?>
            <div class="success-message">
                <span>✅</span>
                <?php echo $student_msg; ?>
            </div>
            <?php endif; ?>

            <?php if($error): ?>
            <div class="error-message">
                <span>⚠️</span>
                <?php echo $error; ?>
            </div>
            <?php endif; ?>

            <form method="POST" action="" class="login-form" autocomplete="off">
                <div class="form-group">
                    <label>የተጠቃሚ ስም (Username)</label>
                    <div class="input-group">
                        <span class="input-icon">👤</span>
                        <input type="text" name="username" class="form-control" 
                               placeholder="Username ወይም ሙሉ ስም" required 
                               autocomplete="off">
                    </div>
                </div>

                <div class="form-group">
                    <label>የይለፍ ቃል (Password)</label>
                    <div class="input-group">
                        <span class="input-icon">🔒</span>
                        <input type="password" name="password" class="form-control" 
                               placeholder="••••••••" required 
                               autocomplete="off">
                    </div>
                </div>

                <button type="submit" class="btn-login">
                    <span>🔑</span>
                    ግባ / Login
                </button>
            </form>

            <!-- STUDENT PORTAL LINK -->
            <div class="student-portal-link">
                <a href="student_login.php">
                    🎓 የተማሪ መግቢያ / Student Portal
                </a>
                <span class="small-text">ተማሪዎች ውጤትዎን ለማየት እዚህ ይግቡ</span>
            </div>
        </div>

        <!-- Developer Footer -->
        <div class="developer-footer">
            <span class="dev-text">Developed by</span>
            <span class="dev-name"> Tesfa</span>
            <span class="dev-divider">|</span>
            <span class="dev-icon">📞</span>
            <span class="dev-phone">0943854325</span>
            <span class="dev-divider">|</span>
            <span class="dev-icon">📩</span>
            <a href="https://t.me/shetesfa" target="_blank" class="dev-telegram">Telegram @shetesfa</a>
        </div>
    </div>

    <script>
        if (window.history.replaceState) {
            window.history.replaceState(null, null, window.location.href);
        }
    </script>
</body>
</html>
<?php mysqli_close($conn); ?>