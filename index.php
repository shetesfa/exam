<?php
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
$is_ajax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') 
           || (isset($_POST['ajax']) && $_POST['ajax'] === '1');

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $username = trim((string)($_POST['username'] ?? ''));
    $password = (string)($_POST['password'] ?? '');
    $remember = !empty($_POST['remember']);

    if (!empty($username) && !empty($password)) {
        // Fast user lookup
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
            foreach ($users as $u) {
                if ($u['username'] === $username) {
                    $matched_user = $u;
                    break;
                }
            }
            if (!$matched_user) $matched_user = $users[0];
        }

        if ($matched_user) {
            // Check account lockout
            if (!empty($matched_user['locked_until'])) {
                if (strtotime($matched_user['locked_until']) > time()) {
                    $remaining_secs = strtotime($matched_user['locked_until']) - time();
                    $remaining_mins = max(1, ceil($remaining_secs / 60));
                    $error = "አካውንትዎ ለጊዜው ተቆልፏል! እባክዎ $remaining_mins ደቂቃ ቆይተው እንደገና ይሞክሩ።";
                } else {
                    dbExecute($conn, "UPDATE users SET login_attempts = 0, locked_until = NULL WHERE id = ?", "i", [$matched_user['id']]);
                    $matched_user['login_attempts'] = 0;
                    $matched_user['locked_until'] = null;
                }
            }

            if (empty($error)) {
                if (password_verify($password, $matched_user['password'])) {
                    // Success! Reset attempts
                    dbExecute($conn, "UPDATE users SET login_attempts = 0, locked_until = NULL WHERE id = ?", "i", [$matched_user['id']]);
                    
                    $_SESSION = [];
                    session_regenerate_id(true);

                    $_SESSION['user_id'] = (int)$matched_user['id'];
                    $_SESSION['user_name'] = trim((string)$matched_user['name']);
                    $_SESSION['username'] = trim((string)$matched_user['username']);
                    $_SESSION['role'] = $matched_user['role'];
                    $_SESSION['first_login'] = $matched_user['first_login'];
                    $_SESSION['dark_mode'] = intval($matched_user['dark_mode'] ?? 0);
                    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

                    // Create persistent Remember Me token (default or if checked)
                    $token = '';
                    if (function_exists('createRememberMeToken')) {
                        $token = createRememberMeToken($conn, $matched_user['id'], $matched_user['role']);
                    }

                    $redirect = 'dashboard_teacher.php';
                    if ($matched_user['first_login'] == 1 && $matched_user['role'] !== 'admin') {
                        $redirect = 'change_password.php';
                    } elseif ($matched_user['role'] === 'admin') {
                        $redirect = 'dashboard_admin.php';
                    } elseif ($matched_user['role'] === 'attendance_submitter') {
                        $redirect = 'dashboard_attendance.php';
                    }

                    if ($is_ajax) {
                        header('Content-Type: application/json');
                        echo json_encode([
                            'success' => true,
                            'redirect' => $redirect,
                            'token' => $token,
                            'user' => [
                                'id' => (int)$matched_user['id'],
                                'name' => trim((string)$matched_user['name']),
                                'username' => trim((string)$matched_user['username']),
                                'role' => $matched_user['role']
                            ]
                        ]);
                        exit();
                    } else {
                        header("Location: " . $redirect);
                        exit();
                    }
                } else {
                    // Wrong password
                    $attempts = intval($matched_user['login_attempts'] ?? 0) + 1;
                    $max_attempts = 10;
                    
                    if ($attempts >= $max_attempts) {
                        $lock_time = date('Y-m-d H:i:s', strtotime('+5 minutes'));
                        dbExecute($conn, "UPDATE users SET login_attempts = ?, locked_until = ? WHERE id = ?", "isi", [$attempts, $lock_time, $matched_user['id']]);
                        $error = "በጣም ብዙ ሙከራ! አካውንትዎ ለ5 ደቂቃ ተቆልፏል።";
                    } else {
                        dbExecute($conn, "UPDATE users SET login_attempts = ? WHERE id = ?", "ii", [$attempts, $matched_user['id']]);
                        $remaining = $max_attempts - $attempts;
                        $error = "የተሳሳተ የይለፍ ቃል! $remaining ሙከራዎች ቀርተዋል።";
                    }
                }
            }
        } else {
            // Check student name
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

    if ($is_ajax) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => $error]);
        exit();
    }
}

// Check student logout message
$student_msg = '';
if (isset($_GET['msg']) && $_GET['msg'] === 'student_logout') {
    $student_msg = '✅ ከተማሪ ገፅ ወጥተዋል!';
}
?>
<!DOCTYPE html>
<html lang="am">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0">
    <title>አጸደ ትጉሃን ሰንበት ትምህርት ቤት</title>
    <link rel="icon" type="image/png" href="images/icon.png">
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
            -webkit-tap-highlight-color: transparent;
        }

        body {
            min-height: 100vh;
            background: linear-gradient(135deg, #3D1603 0%, #6E2D08 45%, #92400E 80%, #B45309 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 16px 12px;
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
            padding: 28px 22px;
            box-shadow: 0 20px 48px rgba(0, 0, 0, 0.35);
            border: 2px solid var(--gold-primary);
            animation: slideUp 0.4s ease-out;
        }

        @keyframes slideUp {
            from { opacity: 0; transform: translateY(16px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .logo-section {
            text-align: center;
            margin-bottom: 22px;
        }

        .logo {
            width: 54px;
            height: 54px;
            margin: 0 auto 10px;
            background: linear-gradient(135deg, #FFD700 0%, #DAA520 100%);
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 2px solid var(--brown-dark);
            box-shadow: 0 4px 12px rgba(92, 38, 7, 0.25);
        }

        .logo img {
            width: 46px;
            height: 46px;
            border-radius: 12px;
            object-fit: cover;
            border: 1.5px solid white;
        }

        .welcome-title {
            color: var(--brown-dark);
            font-size: 20px;
            font-weight: 800;
            line-height: 1.3;
            margin-bottom: 3px;
        }

        .welcome-subtitle {
            color: var(--brown-medium);
            font-size: 13.5px;
            font-weight: 600;
            margin-bottom: 6px;
            opacity: 0.9;
        }

        .amharic {
            font-size: 12.5px;
            color: var(--brown-medium);
            display: inline-block;
            background: #FFFBEB;
            border: 1px solid #FDE68A;
            border-radius: 20px;
            padding: 4px 14px;
            font-weight: 700;
        }

        .login-form {
            margin-top: 22px;
        }

        .form-group {
            margin-bottom: 16px;
        }

        .form-group label {
            display: block;
            margin-bottom: 7px;
            color: var(--brown-dark);
            font-weight: 700;
            font-size: 13.5px;
        }

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

        .input-group input.form-control {
            width: 100% !important;
            height: 50px !important;
            padding: 12px 44px 12px 46px !important;
            border: 2px solid #E2E8F0;
            border-radius: 12px !important;
            font-size: 16px !important; /* 16px prevents iOS zoom */
            transition: all 0.2s ease !important;
            background: #FFFFFF;
            color: #1F2937;
            box-sizing: border-box !important;
        }

        .input-group input.form-control:focus {
            outline: none !important;
            border-color: #DAA520 !important;
            box-shadow: 0 0 0 3.5px rgba(255, 215, 0, 0.25) !important;
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
            font-size: 18px;
            color: #9CA3AF;
            padding: 8px;
            z-index: 5;
            line-height: 1;
        }

        .remember-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 18px;
            font-size: 13px;
            color: var(--brown-medium);
            font-weight: 600;
        }

        .remember-label {
            display: flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
            user-select: none;
        }

        .remember-label input[type="checkbox"] {
            width: 18px;
            height: 18px;
            accent-color: #8B4513;
            cursor: pointer;
        }

        .btn-login {
            width: 100%;
            height: 50px;
            background: linear-gradient(135deg, #FFD700 0%, #DAA520 100%);
            color: #5C2607;
            border: none;
            border-radius: 12px;
            font-size: 16px;
            font-weight: 800;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            transition: all 0.2s ease;
            box-shadow: 0 4px 14px rgba(218, 165, 32, 0.35);
        }

        .btn-login:hover {
            transform: translateY(-1px);
            box-shadow: 0 6px 18px rgba(218, 165, 32, 0.45);
        }

        .btn-login:active {
            transform: translateY(1px);
        }

        .btn-login:disabled {
            opacity: 0.7;
            cursor: not-allowed;
        }

        .error-message {
            background: #FEE2E2;
            border: 1.5px solid #EF4444;
            color: #991B1B;
            padding: 12px 14px;
            border-radius: 12px;
            margin-bottom: 16px;
            font-size: 13.5px;
            display: flex;
            align-items: center;
            gap: 10px;
            font-weight: 600;
            line-height: 1.4;
        }

        .success-message {
            background: #DCFCE7;
            border: 1.5px solid #10B981;
            color: #166534;
            padding: 12px 14px;
            border-radius: 12px;
            margin-bottom: 16px;
            font-size: 13.5px;
            display: flex;
            align-items: center;
            gap: 10px;
            font-weight: 600;
        }

        .student-portal-link {
            margin-top: 22px;
            text-align: center;
            padding-top: 18px;
            border-top: 1px dashed #E5E7EB;
        }

        .student-portal-link a {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            width: 100%;
            padding: 12px 16px;
            background: #FFFBEB;
            border: 1.5px solid #FDE68A;
            border-radius: 12px;
            color: #8B4513;
            text-decoration: none;
            font-size: 14.5px;
            font-weight: 700;
            transition: all 0.2s ease;
        }

        .student-portal-link a:hover {
            background: #FEF3C7;
            border-color: #F59E0B;
        }

        .developer-footer {
            margin-top: 18px;
            text-align: center;
            font-size: 11.5px;
            color: rgba(255, 255, 255, 0.75);
            line-height: 1.6;
        }

        .developer-footer a {
            color: #FFD700;
            text-decoration: none;
            font-weight: 700;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="welcome-card">
            <div class="logo-section">
                <div class="logo">
                    <img src="images/icon.png" alt="Atsede Teguhan Logo" onerror="this.onerror=null; this.src='images/icon-192.png';">
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
                <span><?php echo htmlspecialchars((string)$student_msg); ?></span>
            </div>
            <?php endif; ?>

            <div id="liveAlertBox" style="display: <?php echo !empty($error) ? 'flex' : 'none'; ?>;" class="error-message">
                <span>⚠️</span>
                <span id="liveAlertMsg"><?php echo strip_tags($error, '<a><b><strong>'); ?></span>
            </div>

            <form method="POST" action="" class="login-form" id="loginForm" autocomplete="on">
                <input type="hidden" name="ajax" value="1">
                <div class="form-group">
                    <label for="username">የተጠቃሚ ስም</label>
                    <div class="input-group">
                        <span class="input-icon">👤</span>
                        <input type="text" name="username" id="username" class="form-control" 
                               placeholder="የተጠቃሚ ስም ወይም ሙሉ ስም" required 
                               autocomplete="username" autocapitalize="none">
                    </div>
                </div>

                <div class="form-group">
                    <label for="passwordInput">የይለፍ ቃል</label>
                    <div class="input-group">
                        <span class="input-icon">🔒</span>
                        <input type="password" name="password" id="passwordInput" class="form-control" 
                               placeholder="••••••••" required 
                               autocomplete="current-password">
                        <button type="button" class="toggle-password-btn" onclick="togglePasswordVisibility()" aria-label="Toggle password visibility">
                            <span id="eyeIcon">👁️</span>
                        </button>
                    </div>
                </div>

                <div class="remember-row">
                    <label class="remember-label">
                        <input type="checkbox" name="remember" value="1" checked>
                        <span>አስታውሰኝ (በዚህ ስልክ ላይ ቆይ)</span>
                    </label>
                </div>

                <button type="submit" class="btn-login" id="loginBtn">
                    <span>🔑</span>
                    <span id="btnText">ወደ ሲስተሙ ይግቡ</span>
                </button>
            </form>

            <div class="student-portal-link">
                <a href="student_login.php">
                    🎓 የተማሪ መግቢያ / Student Portal
                </a>
            </div>
        </div>

        <div class="developer-footer">
            <span>Developed by</span>
            <span style="color:#FFD700; font-weight:700;"> Tesfa</span>
            <span> | 📞 0943854325 | </span>
            <a href="https://t.me/shetesfa" target="_blank">📩 Telegram @shetesfa</a>
        </div>
    </div>

    <!-- FAST INSTANT AJAX LOGIN & OFFLINE PWA SCRIPT -->
    <script>
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

        const loginForm = document.getElementById('loginForm');
        const loginBtn = document.getElementById('loginBtn');
        const btnText = document.getElementById('btnText');
        const alertBox = document.getElementById('liveAlertBox');
        const alertMsg = document.getElementById('liveAlertMsg');

        function showError(msg) {
            if (alertBox && alertMsg) {
                alertMsg.innerHTML = msg;
                alertBox.style.display = 'flex';
                loginBtn.disabled = false;
                btnText.innerText = 'ወደ ሲስተሙ ይግቡ';
            }
        }

        loginForm?.addEventListener('submit', async function(e) {
            e.preventDefault();
            loginBtn.disabled = true;
            btnText.innerText = 'በመግባት ላይ... ⏳';
            if (alertBox) alertBox.style.display = 'none';

            const formData = new FormData(loginForm);
            const username = formData.get('username') ? formData.get('username').trim() : '';
            const password = formData.get('password') || '';

            try {
                // Single Fast Request direct to login handler
                const res = await fetch('index.php', {
                    method: 'POST',
                    body: formData,
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                });

                if (res.ok) {
                    const data = await res.json();
                    if (data.success && data.redirect) {
                        btnText.innerText = 'ተሳክቷል! በማስገባት ላይ... ✓';
                        
                        // Cache in IndexedDB for seamless offline usage in background
                        if (typeof OfflineDB !== 'undefined' && data.token && data.user) {
                            try {
                                await OfflineDB.saveAuth(data.user, data.token, null, password);
                            } catch (_) {}
                        }
                        
                        window.location.href = data.redirect;
                        return;
                    } else {
                        showError(data.message || 'የተሳሳተ የተጠቃሚ ስም ወይም የይለፍ ቃል!');
                        return;
                    }
                } else {
                    // Fallback to offline DB check if server returned network error or unreachable
                    handleOfflineFallback(username, password);
                }
            } catch (err) {
                // Network down or connection error -> Try offline login
                handleOfflineFallback(username, password);
            }
        });

        async function handleOfflineFallback(username, password) {
            if (typeof OfflineDB !== 'undefined' && OfflineDB.offlineLogin) {
                try {
                    const offResult = await OfflineDB.offlineLogin(username, password);
                    if (offResult.success) {
                        sessionStorage.setItem('offline_user', JSON.stringify(offResult.user));
                        sessionStorage.setItem('offline_token', offResult.token);
                        btnText.innerText = 'ኦፍላይን ገብተዋል! ✓';
                        
                        if (offResult.user.role === 'teacher') {
                            window.location.href = 'dashboard_teacher.php';
                        } else if (offResult.user.role === 'attendance_submitter') {
                            window.location.href = 'dashboard_attendance.php';
                        } else {
                            window.location.href = 'dashboard_admin.php';
                        }
                        return;
                    }
                } catch (_) {}
            }
            showError('የኔትወርክ ግንኙነት የለም! እባክዎ ኢንተርኔትዎን ይፈትሹ ወይም እንደገና ይሞክሩ።');
        }
    </script>
</body>
</html>
