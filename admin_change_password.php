<?php
session_start();
require_once 'db.php';
requireAdmin();

$message = '';
$error = '';

if($_SERVER['REQUEST_METHOD'] == 'POST') {
    $current = $_POST['current_password'];
    $new = $_POST['new_password'];
    $confirm = $_POST['confirm_password'];
    
    $user_id = $_SESSION['user_id'];
    $result = mysqli_query($conn, "SELECT password FROM users WHERE id = $user_id");
    $user = mysqli_fetch_assoc($result);
    
    if(!password_verify($current, $user['password'])) {
        $error = "የአሁኑ የይለፍ ቃል ትክክል አይደለም!";
    } elseif(strlen($new) < 4) {
        $error = "አዲስ የይለፍ ቃል ቢያንስ 4 ቁምፊ መሆን አለበት!";
    } elseif($new != $confirm) {
        $error = "የይለፍ ቃላት አይዛመዱም!";
    } else {
        $hashed = hashPassword($new);
        mysqli_query($conn, "UPDATE users SET password='$hashed', first_login=0 WHERE id=$user_id");
        $message = "የይለፍ ቃል በተሳካ ሁኔታ ተቀይሯል!";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/png" href="images/icon.png">
    <title>የይለፍ ቃል ቀይር | Admin</title>
    <style>
        :root { --brown-dark: #8B4513; --gold: #FFD700; --gold-dark: #DAA520; --success: #10B981; --error: #EF4444; }
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Segoe UI', sans-serif; }
        body { min-height: 100vh; background: linear-gradient(135deg, #8B4513, #A52A2A, #DAA520); display: flex; align-items: center; justify-content: center; padding: 20px; }
        .card { background: white; border-radius: 20px; padding: 40px; max-width: 450px; width: 100%; border: 3px solid var(--gold); }
        h1 { color: var(--brown-dark); text-align: center; margin-bottom: 25px; }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; color: var(--brown-dark); font-weight: 600; margin-bottom: 5px; }
        .form-control { width: 100%; padding: 12px; border: 2px solid #E2E8F0; border-radius: 8px; font-size: 14px; }
        .form-control:focus { outline: none; border-color: var(--gold); }
        .btn { width: 100%; padding: 14px; background: linear-gradient(135deg, #FFD700, #DAA520); color: #8B4513; border: none; border-radius: 10px; font-weight: bold; font-size: 16px; cursor: pointer; }
        .message { padding: 12px; border-radius: 8px; margin-bottom: 15px; }
        .success { background: #D1FAE5; color: #065F46; }
        .error { background: #FEE2E2; color: #991B1B; }
        .back-link { display: block; text-align: center; margin-top: 15px; color: var(--brown-dark); font-weight: 600; text-decoration: none; }
    </style>
</head>
<body>
    <div class="card">
        <h1>🔒 የይለፍ ቃል ይቀይሩ</h1>
        <?php if($message): ?><div class="message success">✅ <?php echo $message; ?></div><?php endif; ?>
        <?php if($error): ?><div class="message error">⚠️ <?php echo $error; ?></div><?php endif; ?>
        <form method="POST">
            <div class="form-group">
                <label>የአሁኑ የይለፍ ቃል</label>
                <input type="password" name="current_password" class="form-control" required>
            </div>
            <div class="form-group">
                <label>አዲስ የይለፍ ቃል</label>
                <input type="password" name="new_password" class="form-control" required minlength="4">
            </div>
            <div class="form-group">
                <label>አዲስ የይለፍ ቃል ያረጋግጡ</label>
                <input type="password" name="confirm_password" class="form-control" required minlength="4">
            </div>
            <button type="submit" class="btn">💾 አስቀምጥ</button>
        </form>
        <a href="dashboard_admin.php" class="back-link">← ወደ ዳሽቦርድ</a>
    </div>
</body>
</html>
<?php mysqli_close($conn); ?>