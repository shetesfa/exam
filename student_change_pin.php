<?php
require_once 'db.php';
requireStudent();

$error = '';
$success = '';
$student_id = intval($_SESSION['student_id']);

// Check if this is first login
$student_data = dbFetchOne(
    $conn,
    "SELECT pin, first_login FROM student_logins WHERE student_id = ?",
    "i",
    [$student_id]
);

$is_first_login = ($student_data && $student_data['first_login'] == 1);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = "የደህንነት ማረጋገጫ አልተሳካም! እባክዎ እንደገና ይሞክሩ።";
    } else {
        $current_pin = trim($_POST['current_pin'] ?? '');
        $new_pin = trim($_POST['new_pin'] ?? '');
        $confirm_pin = trim($_POST['confirm_pin'] ?? '');
        
        // Basic validation
        if (strlen($new_pin) < 4) {
            $error = "ፒን ቢያንስ 4 አሃዝ መሆን አለበት! (PIN must be at least 4 digits!)";
        } elseif ($new_pin !== $confirm_pin) {
            $error = "ፒኖቹ አይዛመዱም! (PINs do not match!)";
        } elseif (!preg_match('/^[0-9]+$/', $new_pin)) {
            $error = "ፒን ቁጥር ብቻ መሆን አለበት! (PIN must contain only numbers!)";
        } else {
            // ONLY verify current PIN if this is NOT first login
            if (!$is_first_login) {
                if (empty($current_pin)) {
                    $error = "እባክዎ የአሁኑን ፒን ያስገቡ! (Please enter your current PIN!)";
                } elseif (!$student_data || !password_verify($current_pin, $student_data['pin'])) {
                    $error = "የአሁኑ ፒን ትክክል አይደለም! (Current PIN is incorrect!)";
                }
            }
            
            // If no error, save the new PIN
            if (empty($error)) {
                $hashed_pin = password_hash($new_pin, PASSWORD_DEFAULT);
                
                $updated = dbExecute(
                    $conn,
                    "UPDATE student_logins SET pin = ?, first_login = 0, login_attempts = 0, locked_until = NULL WHERE student_id = ?",
                    "si",
                    [$hashed_pin, $student_id]
                );
                
                if ($updated) {
                    $_SESSION['student_first_login'] = 0;
                    $is_first_login = false;
                    $success = "ፒንዎ በተሳካ ሁኔታ ተቀይሯል!";
                    header("refresh:2;url=dashboard_student.php");
                } else {
                    $error = "ስህተት ተከስቷል! እባክዎ ትንሽ ቆይተው እንደገና ይሞክሩ።";
                }
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
    <link rel="icon" type="image/png" href="images/icon.png">
    <title><?php echo $is_first_login ? 'አዲስ ፒን ይምረጡ' : 'ፒን ይቀይሩ'; ?> | Change PIN</title>
    <?php include 'pwa_head.php'; ?>
    <style>
        :root { --brown-dark: #8B4513; --brown-medium: #A52A2A; --gold-primary: #FFD700; --gold-dark: #DAA520; }
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Segoe UI', sans-serif; }
        body {
            min-height: 100vh; background: linear-gradient(135deg, #8B4513 0%, #A52A2A 50%, #DAA520 100%);
            display: flex; align-items: center; justify-content: center; padding: 20px;
        }
        .card {
            background: white; border-radius: 25px; padding: 40px; width: 100%; max-width: 450px;
            border: 3px solid var(--gold-primary); box-shadow: 0 20px 40px rgba(0,0,0,0.3); text-align: center;
        }
        .icon { font-size: 60px; margin-bottom: 20px; }
        h1 { color: var(--brown-dark); font-size: 24px; margin-bottom: 10px; }
        .welcome-text {
            background: #FFF8DC; padding: 15px; border-radius: 12px; margin-bottom: 25px;
            color: var(--brown-medium); line-height: 1.6;
        }
        .form-group { margin-bottom: 20px; text-align: left; }
        .form-group label { display: block; margin-bottom: 8px; color: var(--brown-dark); font-weight: 600; }
        .form-control {
            width: 100%; padding: 15px; border: 2px solid #E2E8F0; border-radius: 12px;
            font-size: 18px; text-align: center; letter-spacing: 5px;
        }
        .form-control:focus { outline: none; border-color: var(--gold-primary); box-shadow: 0 0 0 3px rgba(255,215,0,0.2); }
        .btn-save {
            width: 100%; padding: 16px; background: linear-gradient(135deg, #FFD700 0%, #DAA520 100%);
            color: #8B4513; border: none; border-radius: 12px; font-size: 18px; font-weight: bold;
            cursor: pointer; transition: all 0.3s;
        }
        .btn-save:hover { transform: translateY(-3px); box-shadow: 0 10px 25px rgba(139,69,19,0.3); }
        .btn-back {
            display: block; text-align: center; margin-top: 15px; color: var(--brown-dark);
            font-weight: 600; text-decoration: none;
        }
        .error { background: #FEE2E2; color: #EF4444; padding: 15px; border-radius: 12px; margin-bottom: 20px; }
        .success { background: #D1FAE5; color: #10B981; padding: 15px; border-radius: 12px; margin-bottom: 20px; }
        .info-box {
            background: #EFF6FF; padding: 12px; border-radius: 8px; margin-bottom: 20px;
            border-left: 4px solid #3B82F6; font-size: 13px; color: #1E40AF; text-align: left;
        }

        /* Dark Mode Overrides */
        html.dark-mode body,
        body.dark-mode,
        [data-theme="dark"] body {
            background: #0B1120 !important;
            color: #F1F5F9 !important;
        }
        .dark-mode .card,
        [data-theme="dark"] .card {
            background: #1E293B !important;
            border-color: #334155 !important;
            box-shadow: 0 20px 40px rgba(0,0,0,0.6) !important;
        }
        .dark-mode h1,
        [data-theme="dark"] h1 {
            color: #FCD34D !important;
        }
        .dark-mode .welcome-text,
        [data-theme="dark"] .welcome-text {
            background: #0F172A !important;
            color: #CBD5E1 !important;
            border: 1px solid #334155 !important;
        }
        .dark-mode .form-group label,
        [data-theme="dark"] .form-group label {
            color: #FCD34D !important;
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
        .dark-mode .info-box,
        [data-theme="dark"] .info-box {
            background: #1E3A8A !important;
            color: #BFDBFE !important;
            border-left-color: #3B82F6 !important;
        }
        .dark-mode .btn-back,
        [data-theme="dark"] .btn-back {
            color: #FCD34D !important;
        }
    </style>
</head>
<body>
    <div class="card">
        <div class="icon">🔐</div>
        <h1><?php echo $is_first_login ? 'አዲስ ፒን ይምረጡ' : 'ፒን ይቀይሩ'; ?></h1>
        
        <div class="welcome-text">
            <strong>እንኳን ደህና መጡ <?php echo htmlspecialchars($_SESSION['student_name'] ?? 'ተማሪ'); ?>!</strong><br>
            <?php if($is_first_login): ?>
            ለመጀመሪያ ጊዜ መግቢያዎ ስለሆነ አዲስ የግል ፒን መምረጥ አለብዎት።
            <?php else: ?>
            አዲስ ፒን ለመቀየር የአሁኑን እና አዲሱን ፒን ያስገቡ።
            <?php endif; ?>
        </div>

        <?php if ($error): ?><div class="error">⚠️ <?php echo htmlspecialchars($error); ?></div><?php endif; ?>
        <?php if ($success): ?><div class="success">✅ <?php echo htmlspecialchars($success); ?></div><?php endif; ?>

        <form method="POST">
            <?php echo csrfField(); ?>
            <?php if(!$is_first_login): ?>
            <div class="form-group">
                <label>የአሁኑ ፒን</label>
                <input type="password" name="current_pin" class="form-control" 
                       placeholder="••••" maxlength="6" pattern="[0-9]+" required>
            </div>
            <?php else: ?>
            <div class="info-box">
                💡 <strong>ማስታወሻ:</strong> ይህ የመጀመሪያ መግቢያዎ ነው። አዲስ ሚስጥራዊ ፒን ይምረጡ።
            </div>
            <?php endif; ?>
            
            <div class="form-group">
                <label>አዲስ ፒን</label>
                <input type="password" name="new_pin" class="form-control" 
                       placeholder="••••" maxlength="6" pattern="[0-9]+" required>
            </div>

            <div class="form-group">
                <label>አዲሱን ፒን በድጋሚ ያረጋግጡ</label>
                <input type="password" name="confirm_pin" class="form-control" 
                       placeholder="••••" maxlength="6" pattern="[0-9]+" required>
            </div>

            <button type="submit" class="btn-save">💾 አዲሱን ፒን አስቀምጥ</button>
        </form>
        
        <a href="dashboard_student.php" class="btn-back">← ወደ ዳሽቦርድ ተመለስ</a>
    </div>
</body>
</html>
<?php mysqli_close($conn); ?>