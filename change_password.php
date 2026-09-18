<?php
require_once 'db.php';
requireLogin();

// Check if user needs to change password or is logged in
$user_id = intval($_SESSION['user_id']);
$user = dbFetchOne($conn, "SELECT id, role, first_login FROM users WHERE id = ?", "i", [$user_id]);

if (!$user) {
    header("Location: index.php");
    exit();
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = "የደህንነት ማረጋገጫ አልተሳካም! እባክዎ እንደገና ይሞክሩ።";
    } else {
        $new_password = $_POST['new_password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';
        
        if ($new_password !== $confirm_password) {
            $error = "ያስገቧቸው የይለፍ ቃሎች አይመሳሰሉም!";
        } elseif (strlen($new_password) < 4) {
            $error = "የይለፍ ቃል ቢያንስ 4 ዲጂት መሆን አለበት!";
        } else {
            $hashed_password = hashPassword($new_password);
            
            $updated = dbExecute(
                $conn,
                "UPDATE users SET password = ?, first_login = 0 WHERE id = ?",
                "si",
                [$hashed_password, $user_id]
            );
            
            if ($updated) {
                $_SESSION['first_login'] = 0;
                $success = "የይለፍ ቃል በትክክል ተቀይሯል! እንኳን ደህና መጡ!";
                
                $redirect = "dashboard_teacher.php";
                if ($user['role'] === 'admin') {
                    $redirect = "dashboard_admin.php";
                } elseif ($user['role'] === 'attendance_submitter') {
                    $redirect = "dashboard_attendance.php";
                }
                header("refresh:2;url=$redirect");
            } else {
                $error = "ስህተት ተከስቷል! እባክዎ እንደገና ይሞክሩ።";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/png" href="images\icon.png">
    <title>የይለፍ ቃል ይቀይሩ | አጸደ ትጉሃን </title>
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
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', sans-serif;
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
            max-width: 500px;
        }

        .change-card {
            background: white;
            border-radius: 20px;
            padding: 40px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.3);
            border: 3px solid var(--gold-primary);
            animation: slideIn 0.6s ease;
        }

        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(-30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .header {
            text-align: center;
            margin-bottom: 30px;
        }

        .icon {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, var(--gold-primary), var(--gold-dark));
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            border: 3px solid var(--brown-dark);
        }

        .icon span {
            font-size: 40px;
        }

        h1 {
            color: var(--brown-dark);
            font-size: 24px;
            margin-bottom: 10px;
        }

        .welcome-message {
            color: var(--brown-medium);
            font-size: 16px;
            background: var(--gold-pale);
            padding: 10px;
            border-radius: 10px;
            margin-top: 10px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        label {
            display: block;
            margin-bottom: 8px;
            color: var(--brown-dark);
            font-weight: 600;
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
            transition: all 0.3s;
        }

        .form-control:focus {
            outline: none;
            border-color: var(--gold-primary);
            box-shadow: 0 0 0 3px rgba(255,215,0,0.2);
        }

        .btn-change {
            width: 100%;
            padding: 16px;
            background: linear-gradient(135deg, #FFD700 0%, #DAA520 100%);
            color: #8B4513;
            border: 2px solid #FFD700;
            border-radius: 12px;
            font-size: 18px;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }

        .btn-change:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 25px rgba(139,69,19,0.3);
        }

        .error-message {
            background: #FEE2E2;
            color: var(--error-red);
            padding: 15px;
            border-radius: 12px;
            margin-bottom: 20px;
            border-left: 4px solid var(--error-red);
        }

        .success-message {
            background: #D1FAE5;
            color: var(--success-green);
            padding: 15px;
            border-radius: 12px;
            margin-bottom: 20px;
            border-left: 4px solid var(--success-green);
        }

        .password-hint {
            font-size: 13px;
            color: #666;
            margin-top: 5px;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        /* Dark Mode Overrides */
        html.dark-mode body,
        body.dark-mode,
        [data-theme="dark"] body {
            background: #0B1120 !important;
            color: #F1F5F9 !important;
        }
        .dark-mode .change-card,
        [data-theme="dark"] .change-card {
            background: #1E293B !important;
            border-color: #334155 !important;
            box-shadow: 0 20px 40px rgba(0,0,0,0.6) !important;
        }
        .dark-mode h1,
        [data-theme="dark"] h1 {
            color: #FCD34D !important;
        }
        .dark-mode label,
        [data-theme="dark"] label {
            color: #FCD34D !important;
        }
        .dark-mode .welcome-message,
        [data-theme="dark"] .welcome-message {
            background: #0F172A !important;
            color: #CBD5E1 !important;
            border: 1px solid #334155 !important;
        }
        .dark-mode .form-control,
        [data-theme="dark"] .form-control {
            background: #0F172A !important;
            background-color: #0F172A !important;
            border: 1.5px solid #334155 !important;
            color: #F8FAFC !important;
        }
        .dark-mode .form-control:focus,
        [data-theme="dark"] .form-control:focus {
            border-color: #F59E0B !important;
            box-shadow: 0 0 0 3px rgba(245, 158, 11, 0.25) !important;
            color: #FFFFFF !important;
        }
        .dark-mode .password-hint,
        [data-theme="dark"] .password-hint {
            color: #94A3B8 !important;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="change-card">
            <div class="header">
                <div class="icon">
                    <span>🔐</span>
                </div>
                <h1>የይለፍ ቃል ይቀይሩ</h1>
                <div class="welcome-message">
                    እንኳን ደህና መጡ ወደ አጸደ ትጉሃን ሰንበት ትምህርት ቤት!<br>
                    ለመጀመሪያ ጊዜ መግቢያዎ ነው። እባክዎ የይለፍ ቃል ይቀይሩ።
                </div>
            </div>

            <?php if($error): ?>
            <div class="error-message">
                ⚠️ <?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?>
            </div>
            <?php endif; ?>

            <?php if($success): ?>
            <div class="success-message">
                ✅ <?php echo $success; ?>
            </div>
            <?php endif; ?>

            <form method="POST" action="">
                <?php echo csrfField(); ?>
                <div class="form-group">
                    <label>አዲስ የይለፍ ቃል</label>
                    <div class="input-group">
                        <span class="input-icon">🔑</span>
                        <input type="password" name="new_password" class="form-control" 
                               placeholder="አዲስ የይለፍ ቃል" required>
                    </div>
                </div>

                <div class="form-group">
                    <label>የይለፍ ቃሉን በድጋሚ ያስገቡ</label>
                    <div class="input-group">
                        <span class="input-icon">✓</span>
                        <input type="password" name="confirm_password" class="form-control" 
                               placeholder="የይለፍ ቃል ድገም" required>
                    </div>
                </div>

                <div class="password-hint">
                    <span>💡</span> ቢያንስ 4 ዲጂት መሆን አለበት
                </div>

                <button type="submit" class="btn-change">
                    <span>🔄</span>
                    ይለፍ ቃል ይቀይሩ
                </button>
            </form>
        </div>
    </div>
</body>
</html>