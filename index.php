<?php
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
require_once 'db.php';

// Redirect if already logged in
if (isLoggedIn()) {
    if (isAdmin()) {
        header("Location: dashboard_admin.php");
        exit();
    } elseif (isTeacher()) {
        header("Location: dashboard_teacher.php");
        exit();
    } elseif (isAttendanceSubmitter()) {
        header("Location: dashboard_attendance.php");
        exit();
    }
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $submitted_token = $_POST['csrf_token'] ?? '';
    $csrf_valid = verifyCsrfToken($submitted_token);

    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    // If CSRF token check failed (e.g. stale tab, bfcache, or browser session cookie timing),
    // verify whether valid credentials were submitted. If credentials are correct, allow login!
    if (!$csrf_valid && !empty($username) && !empty($password)) {
        $candidate = dbFetchOne($conn, "SELECT id, password FROM users WHERE username = ? OR name = ?", "ss", [$username, $username]);
        if ($candidate && password_verify($password, $candidate['password'])) {
            $csrf_valid = true;
        }
    }

    if (!$csrf_valid) {
        $error = "የደህንነት ማረጋገጫ አልተሳካም! እባክዎ እንደገና ይሞክሩ።";
    } else {
        if (!empty($username) && !empty($password)) {
            // Find user by username or name
            $users = dbFetchAll(
                $conn, 
                "SELECT * FROM users WHERE username = ? OR name = ?", 
                "ss", 
                [$username, $username]
            );

            $matched_user = null;

            if (count($users) === 1) {
                $matched_user = $users[0];
            } elseif (count($users) > 1) {
                // Exact username match priority
                foreach ($users as $u) {
                    if ($u['username'] === $username) {
                        $matched_user = $u;
                        break;
                    }
                }
            }

            if ($matched_user && password_verify($password, $matched_user['password'])) {
                session_regenerate_id(true);

                $_SESSION['user_id'] = (int)$matched_user['id'];
                $_SESSION['user_name'] = trim($matched_user['name']);
                $_SESSION['username'] = trim($matched_user['username']);
                $_SESSION['role'] = $matched_user['role'];
                $_SESSION['first_login'] = $matched_user['first_login'];
                $_SESSION['dark_mode'] = intval($matched_user['dark_mode'] ?? 0);

                if ($matched_user['first_login'] == 1 && $matched_user['role'] === 'teacher') {
                    header("Location: change_password.php");
                    exit();
                } elseif ($matched_user['role'] === 'admin') {
                    header("Location: dashboard_admin.php");
                    exit();
                } elseif ($matched_user['role'] === 'attendance_submitter') {
                    header("Location: dashboard_attendance.php");
                    exit();
                } else {
                    header("Location: dashboard_teacher.php");
                    exit();
                }
            } else {
                // Check if user entered a student's name
                $student_check = dbFetchOne($conn, "SELECT id FROM students WHERE name = ? AND (is_deleted = 0 OR is_deleted IS NULL)", "s", [$username]);
                if ($student_check) {
                    $error = "ይህ የተማሪ ስም ነው። እባክዎ <a href='student_login.php' style='color:#8B4513;font-weight:bold;text-decoration:underline;'>በተማሪዎች መግቢያ</a> ይግቡ!";
                } else {
                    $error = "የተሳሳተ የተጠቃሚ ስም ወይም የይለፍ ቃል!";
                }
            }
        } else {
            $error = "እባክዎ የተጠቃሚ ስም እና የይለፍ ቃል ያስገቡ!";
        }
    }
}

// Check if redirected from student portal logout
$student_msg = '';
if (isset($_GET['msg']) && $_GET['msg'] === 'student_logout') {
    $student_msg = '✅ ከተማሪ ገፅ ወጥተዋል!';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
    <title>አጸደ ትጉሃን ሰንበት ትምህርት ቤት</title>
    <?php include 'pwa_head.php'; ?>
    <style>
        :root {
            --brown-dark: #5C2607;
            --brown-medium: #78350F;
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
            background: linear-gradient(135deg, #3D1603 0%, #6E2D08 45%, #92400E 80%, #B45309 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 14px 10px;
            color: #1F2937;
        }

        .container {
            width: 100%;
            max-width: 440px;
            margin: 0 auto;
        }

        .welcome-card {
            background: #FFFFFF;
            border-radius: 24px;
            padding: 26px 20px;
            box-shadow: 0 24px 50px rgba(0, 0, 0, 0.38);
            border: 2.5px solid var(--gold-primary);
            animation: slideUp 0.6s cubic-bezier(0.16, 1, 0.3, 1);
        }

        @keyframes slideUp {
            from { opacity: 0; transform: translateY(24px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .logo-section {
            text-align: center;
            margin-bottom: 24px;
        }

        .logo {
            width: 96px;
            height: 96px;
            margin: 0 auto 14px;
            background: linear-gradient(135deg, #FFD700 0%, #DAA520 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 3.5px solid var(--brown-dark);
            box-shadow: 0 8px 20px rgba(92, 38, 7, 0.28);
        }

        .logo img {
            width: 76px;
            height: 76px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid white;
        }

        .logo-icon {
            font-size: 42px;
            color: var(--brown-dark);
            background: white;
            width: 78px;
            height: 78px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .welcome-title {
            color: var(--brown-dark);
            font-size: 21px;
            font-weight: 800;
            line-height: 1.3;
            margin-bottom: 3px;
            letter-spacing: -0.2px;
        }

        .welcome-subtitle {
            color: var(--brown-medium);
            font-size: 13.5px;
            font-weight: 600;
            margin-bottom: 6px;
            opacity: 0.9;
        }

        .amharic {
            font-size: 13px;
            color: var(--brown-medium);
            display: inline-block;
            background: #FFFBEB;
            border: 1px solid #FDE68A;
            border-radius: 20px;
            padding: 4px 14px;
            font-weight: 600;
        }

        .login-form {
            margin-top: 24px;
        }

        .form-group {
            margin-bottom: 18px;
        }

        .form-group label {
            display: block;
            margin-bottom: 7px;
            color: var(--brown-dark);
            font-weight: 700;
            font-size: 13.5px;
        }

        /* High specificity to prevent global mobile.css from overriding input padding */
        .input-group {
            position: relative;
            display: flex;
            align-items: center;
            width: 100%;
        }

        .input-icon {
            position: absolute;
            left: 14px;
            top: 50%;
            transform: translateY(-50%);
            color: #78350F;
            font-size: 18px;
            z-index: 5;
            pointer-events: none;
            line-height: 1;
        }

        .input-group input.form-control,
        .input-group input[type="text"],
        .input-group input[type="password"] {
            width: 100% !important;
            height: 48px !important;
            padding: 12px 42px 12px 46px !important;
            border: 2px solid #E2E8F0;
            border-radius: 12px !important;
            font-size: 15px !important;
            transition: all 0.25s ease !important;
            background: #FFFFFF;
            color: #1F2937;
            box-sizing: border-box !important;
            appearance: none !important;
        }

        .input-group input.form-control:focus,
        .input-group input[type="text"]:focus,
        .input-group input[type="password"]:focus {
            outline: none !important;
            border-color: #DAA520 !important;
            box-shadow: 0 0 0 3.5px rgba(255, 215, 0, 0.28) !important;
            background: #FFFDF9;
        }

        .toggle-password-btn {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            cursor: pointer;
            font-size: 16px;
            color: #9CA3AF;
            padding: 4px;
            z-index: 5;
            line-height: 1;
            transition: transform 0.2s;
        }

        .toggle-password-btn:hover {
            color: var(--brown-dark);
            transform: translateY(-50%) scale(1.1);
        }

        .btn-login {
            width: 100%;
            height: 48px;
            padding: 0 20px;
            background: linear-gradient(135deg, #FFD700 0%, #DAA520 100%);
            color: #4A1A05;
            border: none;
            border-radius: 12px;
            font-size: 16px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.25s ease;
            box-shadow: 0 5px 16px rgba(139, 69, 19, 0.25);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            margin-top: 10px;
        }

        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 22px rgba(139, 69, 19, 0.35);
            background: linear-gradient(135deg, #FFE033 0%, #E5B229 100%);
        }

        .btn-login:active {
            transform: translateY(0);
            box-shadow: 0 3px 8px rgba(139, 69, 19, 0.2);
        }

        .error-message {
            background: #FEF2F2;
            color: #991B1B;
            padding: 12px 16px;
            border-radius: 12px;
            margin-bottom: 18px;
            border-left: 4px solid var(--error-red);
            font-size: 13.5px;
            display: flex;
            align-items: center;
            gap: 8px;
            animation: shake 0.4s ease;
            line-height: 1.4;
        }

        .success-message {
            background: #ECFDF5;
            color: #065F46;
            padding: 12px 16px;
            border-radius: 12px;
            margin-bottom: 18px;
            border-left: 4px solid var(--success-green);
            font-size: 13.5px;
            display: flex;
            align-items: center;
            gap: 8px;
            line-height: 1.4;
        }

        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            10%, 30%, 50%, 70%, 90% { transform: translateX(-5px); }
            20%, 40%, 60%, 80% { transform: translateX(5px); }
        }

        .student-portal-link {
            text-align: center;
            margin-top: 22px;
            padding-top: 18px;
            border-top: 1px solid #E5E7EB;
        }

        .student-portal-link a {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            width: 100%;
            padding: 12px 20px;
            background: linear-gradient(135deg, #10B981, #059669);
            color: white;
            border-radius: 12px;
            text-decoration: none;
            font-weight: 700;
            font-size: 14.5px;
            transition: all 0.25s ease;
            box-shadow: 0 4px 14px rgba(16, 185, 129, 0.25);
        }

        .student-portal-link a:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(16, 185, 129, 0.35);
            color: white;
        }

        .student-portal-link .small-text {
            font-size: 12px;
            color: #6B7280;
            display: block;
            margin-top: 8px;
        }

        /* Developer Footer */
        .developer-footer {
            text-align: center;
            padding: 11px 16px;
            margin-top: 16px;
            background: rgba(0, 0, 0, 0.25);
            border-radius: 12px;
            border: 1px solid rgba(255, 215, 0, 0.25);
            backdrop-filter: blur(8px);
            -webkit-backdrop-filter: blur(8px);
            display: flex;
            align-items: center;
            justify-content: center;
            flex-wrap: wrap;
            gap: 6px;
            font-size: 12px;
        }
        .developer-footer .dev-text { color: rgba(255, 255, 255, 0.85); }
        .developer-footer .dev-name { color: #FFD700; font-weight: 700; }
        .developer-footer .dev-divider { color: rgba(255, 255, 255, 0.3); margin: 0 2px; }
        .developer-footer .dev-phone { color: #F3F4F6; font-weight: 600; }
        .developer-footer .dev-telegram { color: #93C5FD; text-decoration: none; font-weight: 600; transition: color 0.2s; }
        .developer-footer .dev-telegram:hover { color: #FFD700; text-decoration: underline; }

        @media (min-width: 481px) {
            body { padding: 20px; }
            .welcome-card { padding: 40px 35px; border-radius: 28px; }
            .logo { width: 100px; height: 100px; margin-bottom: 16px; }
            .logo img { width: 80px; height: 80px; }
            .welcome-title { font-size: 22px; }
            .welcome-subtitle { font-size: 14px; }
            .amharic { font-size: 13px; padding: 4px 14px; }
            .btn-login { height: 48px; font-size: 16px; }
            .student-portal-link a { padding: 12px 20px; font-size: 14.5px; }
            .developer-footer { font-size: 12px; padding: 11px 16px; }
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
                <?php echo strip_tags($error, '<a><b><strong>'); ?>
            </div>
            <?php endif; ?>

            <form method="POST" action="" class="login-form" autocomplete="off">
                <?php echo csrfField(); ?>
                <div class="form-group">
                    <label>የተጠቃሚ ስም</label>
                    <div class="input-group">
                        <span class="input-icon">👤</span>
                        <input type="text" name="username" class="form-control" 
                               placeholder="የተጠቃሚ ስም ወይም ሙሉ ስም" required 
                               autocomplete="off">
                    </div>
                </div>

                <div class="form-group">
                    <label>የይለፍ ቃል</label>
                    <div class="input-group">
                        <span class="input-icon">🔒</span>
                        <input type="password" name="password" id="passwordInput" class="form-control" 
                               placeholder="••••••••" required 
                               autocomplete="off">
                        <button type="button" class="toggle-password-btn" onclick="togglePasswordVisibility()" aria-label="Toggle password visibility">
                            <span id="eyeIcon">👁️</span>
                        </button>
                    </div>
                </div>

                <button type="submit" class="btn-login">
                    <span>🔑</span>
                    ወደ ሲስተሙ ይግቡ
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
            <span class="dev-phone">📞 0943854325</span>
            <span class="dev-divider">|</span>
            <a href="https://t.me/shetesfa" target="_blank" class="dev-telegram">📩 Telegram @shetesfa</a>
        </div>
    </div>
    <script>
        if (window.history.replaceState) {
            window.history.replaceState(null, null, window.location.href);
        }

        function togglePasswordVisibility() {
            const pwd = document.getElementById('passwordInput');
            const icon = document.getElementById('eyeIcon');
            if (!pwd) return;
            if (pwd.type === 'password') {
                pwd.type = 'text';
                if (icon) icon.textContent = '🙈';
            } else {
                pwd.type = 'password';
                if (icon) icon.textContent = '👁️';
            }
        }

        // Login and token caching handler (works on local XAMPP and offline PWA)
        const loginForm = document.querySelector('.login-form');
        if (loginForm) {
            let isSubmitting = false;
            loginForm.addEventListener('submit', async function(e) {
                if (isSubmitting) return; // Allow programmatic submit to proceed
                e.preventDefault();

                const usernameInput = loginForm.querySelector('input[name="username"]');
                const passwordInput = loginForm.querySelector('input[name="password"]');
                const username = usernameInput ? usernameInput.value.trim() : '';
                const password = passwordInput ? passwordInput.value : '';

                // Check if local XAMPP or remote server is reachable
                let isServerUp = false;
                try {
                    const controller = new AbortController();
                    const timer = setTimeout(() => controller.abort(), 1200);
                    const ping = await fetch('api/ping.php?t=' + Date.now(), { method: 'GET', cache: 'no-store', signal: controller.signal });
                    clearTimeout(timer);
                    isServerUp = ping.ok;
                } catch (_) {
                    isServerUp = false;
                }

                if (isServerUp) {
                    // Server is alive (e.g. XAMPP localhost or online server)
                    // Cache credentials in background for future PWA offline usage
                    try {
                        const authRes = await fetch('api/auth.php', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({ username, password })
                        });
                        const authData = await authRes.json();
                        if (authData.success && authData.token) {
                            await OfflineDB.saveAuth(authData.user, authData.token, authData.expires_at, password);
                        }
                    } catch (_) {}

                    // Submit form normally to PHP to start session & log in
                    isSubmitting = true;
                    loginForm.submit();
                } else {
                    // Server is truly unreachable (Apache is stopped or device is completely disconnected)
                    const result = await OfflineDB.offlineLogin(username, password);
                    if (result.success) {
                        sessionStorage.setItem('offline_user', JSON.stringify(result.user));
                        sessionStorage.setItem('offline_token', result.token);
                        
                        // Redirect to appropriate dashboard based on role
                        if (result.user.role === 'teacher') {
                            window.location.href = 'teacher_attendance_view.php';
                        } else if (result.user.role === 'attendance_submitter') {
                            window.location.href = 'dashboard_attendance.php';
                        } else if (result.user.role === 'student') {
                            window.location.href = 'dashboard_student.php';
                        } else {
                            window.location.href = 'dashboard_admin.php';
                        }
                    } else {
                        let errDiv = document.querySelector('.error-message');
                        if (!errDiv) {
                            errDiv = document.createElement('div');
                            errDiv.className = 'error-message';
                            loginForm.parentNode.insertBefore(errDiv, loginForm);
                        }
                        errDiv.innerHTML = `<span>⚠️</span> ${result.message || 'Offline መግባት አልተቻለም!'}`;
                    }
                }
            });
        }
    </script>
</body>
</html>
<?php mysqli_close($conn); ?>