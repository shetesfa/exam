<?php
session_start();
require_once 'db.php';

// Allow any logged-in student to change PIN
if (!isset($_SESSION['student_id']) || empty($_SESSION['student_id'])) {
    header("Location: student_login.php");
    exit();
}

$error = '';
$success = '';
$student_id = $_SESSION['student_id'];

// Check if this is first login
$check_query = "SELECT pin, first_login FROM student_logins WHERE student_id = $student_id";
$check_result = mysqli_query($conn, $check_query);
$student_data = mysqli_fetch_assoc($check_result);

$is_first_login = ($student_data && $student_data['first_login'] == 1) ? true : false;

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $current_pin = $_POST['current_pin'] ?? '';
    $new_pin = $_POST['new_pin'];
    $confirm_pin = $_POST['confirm_pin'];
    
    // Basic validation
    if (strlen($new_pin) < 4) {
        $error = "ፒን ቢያንስ 4 አሃዝ መሆን አለበት! (PIN must be at least 4 digits!)";
    } elseif ($new_pin != $confirm_pin) {
        $error = "ፒኖቹ አይዛመዱም! (PINs do not match!)";
    } elseif (!preg_match('/^[0-9]+$/', $new_pin)) {
        $error = "ፒን ቁጥር ብቻ መሆን አለበት! (PIN must contain only numbers!)";
    } else {
        // ONLY verify current PIN if this is NOT first login
        if (!$is_first_login) {
            if (empty($current_pin)) {
                $error = "እባክዎ የአሁኑን ፒን ያስገቡ! (Please enter your current PIN!)";
            } elseif (!password_verify($current_pin, $student_data['pin'])) {
                $error = "የአሁኑ ፒን ትክክል አይደለም! (Current PIN is incorrect!)";
            }
        }
        
        // If no error, save the new PIN
        if (empty($error)) {
            $hashed_pin = password_hash($new_pin, PASSWORD_DEFAULT);
            
            $update_query = "UPDATE student_logins SET pin = '$hashed_pin', first_login = 0, login_attempts = 0, locked_until = NULL WHERE student_id = $student_id";
            
            if (mysqli_query($conn, $update_query)) {
                $_SESSION['student_first_login'] = 0;
                $is_first_login = false;
                $success = "ፒንዎ በተሳካ ሁኔታ ተቀይሯል! (PIN changed successfully!)";
                header("refresh:2;url=dashboard_student.php");
            } else {
                $error = "ስህተት ተከስቷል! " . mysqli_error($conn);
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
    </style>
</head>
<body>
    <div class="card">
        <div class="icon">🔐</div>
        <h1><?php echo $is_first_login ? 'አዲስ ፒን ይምረጡ' : 'ፒን ይቀይሩ'; ?></h1>
        
        <div class="welcome-text">
            <strong>እንኳን ደህና መጡ <?php echo htmlspecialchars($_SESSION['student_name']); ?>!</strong><br>
            <?php if($is_first_login): ?>
            ለመጀመሪያ ጊዜ መግቢያዎ ስለሆነ አዲስ የግል ፒን መምረጥ አለብዎት።
            <?php else: ?>
            አዲስ ፒን ለመቀየር የአሁኑን እና አዲሱን ፒን ያስገቡ።
            <?php endif; ?>
        </div>

        <?php if ($error): ?><div class="error">⚠️ <?php echo $error; ?></div><?php endif; ?>
        <?php if ($success): ?><div class="success">✅ <?php echo $success; ?></div><?php endif; ?>

        <form method="POST">
            <?php if(!$is_first_login): ?>
            <div class="form-group">
                <label>የአሁኑ ፒን (Current PIN)</label>
                <input type="password" name="current_pin" class="form-control" 
                       placeholder="••••" maxlength="6" pattern="[0-9]+" required>
            </div>
            <?php else: ?>
            <div class="info-box">
                💡 <strong>ማስታወሻ:</strong> ይህ የመጀመሪያ መግቢያዎ ነው። አዲስ ሚስጥራዊ ፒን ይምረጡ።
            </div>
            <?php endif; ?>
            
            <div class="form-group">
                <label>አዲስ ፒን (New PIN)</label>
                <input type="password" name="new_pin" class="form-control" 
                       placeholder="••••" maxlength="6" pattern="[0-9]+" required>
            </div>

            <div class="form-group">
                <label>ፒን ያረጋግጡ (Confirm PIN)</label>
                <input type="password" name="confirm_pin" class="form-control" 
                       placeholder="••••" maxlength="6" pattern="[0-9]+" required>
            </div>

            <button type="submit" class="btn-save">💾 አስቀምጥ / Save PIN</button>
        </form>
        
        <a href="dashboard_student.php" class="btn-back">← ወደ ዳሽቦርድ ተመለስ</a>
    </div>
</body>
</html>
<?php mysqli_close($conn); ?>