<?php
require_once 'db.php';
requireAdmin();

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = "የደህንነት ማረጋገጫ አልተሳካም! እባክዎ እንደገና ይሞክሩ።";
    } else {
        $current = $_POST['current_password'] ?? '';
        $new = $_POST['new_password'] ?? '';
        $confirm = $_POST['confirm_password'] ?? '';
        
        $user_id = intval($_SESSION['user_id']);
        $user = dbFetchOne($conn, "SELECT password FROM users WHERE id = ?", "i", [$user_id]);
        
        if (!$user || !password_verify($current, $user['password'])) {
            $error = "የአሁኑ የይለፍ ቃል ትክክል አይደለም!";
        } elseif (strlen($new) < 4) {
            $error = "አዲስ የይለፍ ቃል ቢያንስ 4 ዲጂት መሆን አለበት!";
        } elseif ($new !== $confirm) {
            $error = "ያስገቧቸው የይለፍ ቃሎች አይመሳሰሉም!";
        } else {
            $hashed = hashPassword($new);
            dbExecute($conn, "UPDATE users SET password = ?, first_login = 0 WHERE id = ?", "si", [$hashed, $user_id]);
            $message = "የይለፍ ቃል በተሳካ ሁኔታ ተቀይሯል!";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="am">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>የይለፍ ቃል ቀይር | ትምህርት ክፍል</title>
    <?php include 'pwa_head.php'; ?>
    <style>
        :root { --brown-dark: #8B4513; --gold: #FFD700; --gold-dark: #DAA520; --success: #10B981; --error: #EF4444; }
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Segoe UI', sans-serif; }
        body { min-height: 100vh; background: linear-gradient(135deg, #8B4513, #A52A2A, #DAA520); display: flex; align-items: center; justify-content: center; padding: 16px; }
        .card { background: white; border-radius: 20px; padding: 36px 30px; max-width: 440px; width: 100%; border: 2.5px solid var(--gold); box-shadow: 0 15px 35px rgba(0,0,0,0.25); }
        h1 { color: var(--brown-dark); text-align: center; margin-bottom: 22px; font-size: 20px; }
        .form-group { margin-bottom: 16px; }
        .form-group label { display: block; color: var(--brown-dark); font-weight: 600; margin-bottom: 6px; font-size: 13.5px; }
        .form-control { width: 100%; height: 46px; padding: 10px 14px; border: 2px solid #E2E8F0; border-radius: 10px; font-size: 14px; }
        .form-control:focus { outline: none; border-color: var(--gold); box-shadow: 0 0 0 3px rgba(255,215,0,0.2); }
        .btn { width: 100%; height: 48px; background: linear-gradient(135deg, #FFD700, #DAA520); color: #8B4513; border: none; border-radius: 10px; font-weight: bold; font-size: 15px; cursor: pointer; transition: transform 0.2s, box-shadow 0.2s; }
        .btn:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(218,165,32,0.3); }
        .message { padding: 12px 14px; border-radius: 8px; margin-bottom: 15px; font-size: 13.5px; }
        .success { background: #D1FAE5; color: #065F46; border: 1px solid var(--success); }
        .error { background: #FEE2E2; color: #991B1B; border: 1px solid var(--error); }
        .back-link { display: block; text-align: center; margin-top: 16px; color: var(--brown-dark); font-weight: 600; text-decoration: none; font-size: 13.5px; }
        .back-link:hover { text-decoration: underline; }

        @media (max-width: 480px) {
            body { padding: 12px 8px; }
            .card { padding: 24px 16px; border-radius: 16px; }
            h1 { font-size: 18px; margin-bottom: 16px; }
            .form-control { height: 44px; font-size: 13.5px; }
            .btn { height: 46px; font-size: 14px; }
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
            box-shadow: 0 15px 35px rgba(0,0,0,0.5) !important;
        }
        .dark-mode h1,
        [data-theme="dark"] h1 {
            color: #FCD34D !important;
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
        .dark-mode .back-link,
        [data-theme="dark"] .back-link {
            color: #FCD34D !important;
        }
    </style>
</head>
<body>
    <div class="card">
        <h1>🔒 የይለፍ ቃል ይቀይሩ</h1>
        <?php if($message): ?><div class="message success">✅ <?php echo htmlspecialchars($message); ?></div><?php endif; ?>
        <?php if($error): ?><div class="message error">⚠️ <?php echo htmlspecialchars($error); ?></div><?php endif; ?>
        <form method="POST">
            <?php echo csrfField(); ?>
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