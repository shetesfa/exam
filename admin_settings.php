<?php
session_start();
require_once 'db.php';
requireAdmin();

$message = '';
$error = '';

// Handle save settings
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['save_settings'])) {
    $settings = [
        'admin_name_1' => $_POST['admin_name_1'],
        'admin_phone_1' => $_POST['admin_phone_1'],
        'admin_name_2' => $_POST['admin_name_2'],
        'admin_phone_2' => $_POST['admin_phone_2'],
        'admin_title' => $_POST['admin_title']
    ];
    
    $success = true;
    foreach ($settings as $key => $value) {
        $value_escaped = mysqli_real_escape_string($conn, $value);
        $query = "INSERT INTO settings (setting_key, setting_value) 
                  VALUES ('$key', '$value_escaped')
                  ON DUPLICATE KEY UPDATE setting_value = '$value_escaped'";
        if (!mysqli_query($conn, $query)) {
            $success = false;
            break;
        }
    }
    
    if ($success) {
        $message = "መረጃ በተሳካ ሁኔታ ተቀምጧል! (Settings saved successfully!)";
    } else {
        $error = "ስህተት ተከስቷል! (Error occurred!)";
    }
}

// Get current settings
$current_settings = [];
$settings_result = mysqli_query($conn, "SELECT setting_key, setting_value FROM settings");
while ($row = mysqli_fetch_assoc($settings_result)) {
    $current_settings[$row['setting_key']] = $row['setting_value'];
}

// Get defaults
$admin1 = $current_settings['admin_name_1'] ?? '';
$phone1 = $current_settings['admin_phone_1'] ?? '';
$admin2 = $current_settings['admin_name_2'] ?? '';
$phone2 = $current_settings['admin_phone_2'] ?? '';
$title = $current_settings['admin_title'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/png" href="images/icon.png">
    <title>ቅንብሮች አስተዳደር | Settings</title>
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
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', sans-serif;
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
            max-width: 900px;
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
            color: rgba(255,255,255,0.9);
        }

        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-size: 14px;
        }

        .btn-back {
            background: var(--gold-primary);
            color: var(--brown-dark);
        }

        .container {
            max-width: 900px;
            margin: 30px auto;
            padding: 0 20px;
        }

        .message {
            padding: 15px 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .success {
            background: #D1FAE5;
            color: #065F46;
            border: 2px solid var(--success-green);
        }

        .error {
            background: #FEE2E2;
            color: #991B1B;
            border: 2px solid var(--error-red);
        }

        .settings-card {
            background: white;
            border-radius: 20px;
            padding: 30px;
            border: 2px solid var(--gold-primary);
            box-shadow: 0 5px 20px rgba(0,0,0,0.08);
        }

        .settings-title {
            color: var(--brown-dark);
            font-size: 22px;
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 2px solid var(--gold-pale);
            display: flex;
            align-items: center;
            gap: 10px;
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

        .form-control {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #E2E8F0;
            border-radius: 10px;
            font-size: 14px;
            transition: all 0.3s;
        }

        .form-control:focus {
            outline: none;
            border-color: var(--gold-primary);
            box-shadow: 0 0 0 3px rgba(255,215,0,0.2);
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }

        .btn-save {
            width: 100%;
            padding: 15px;
            background: linear-gradient(135deg, #FFD700 0%, #DAA520 100%);
            color: #8B4513;
            border: none;
            border-radius: 10px;
            font-size: 16px;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.3s;
            margin-top: 20px;
        }

        .btn-save:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(218,165,32,0.3);
        }

        @media (max-width: 600px) {
            .form-row {
                grid-template-columns: 1fr;
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
                    <h1>አጸደ ትጉሃን ሰንበት ትምህርት ቤት</h1>
                    <p>ቅንብሮች አስተዳደር | Settings Management</p>
                </div>
            </div>
            <a href="dashboard_admin.php" class="btn btn-back">← ወደ ዳሽቦርድ</a>
        </div>
    </div>

    <div class="container">
        <?php if ($message): ?>
        <div class="message success">✅ <?php echo $message; ?></div>
        <?php endif; ?>

        <?php if ($error): ?>
        <div class="message error">⚠️ <?php echo $error; ?></div>
        <?php endif; ?>

        <div class="settings-card">
            <div class="settings-title">
                <span>⚙️</span>
                የአስተዳዳሪ መረጃ ማስተካከያ
            </div>

            <form method="POST">
                <div class="form-group">
                    <label>የኃላፊነት ማዕረግ (Admin Title)</label>
                    <input type="text" name="admin_title" class="form-control" 
                           value="<?php echo htmlspecialchars($title); ?>" 
                           placeholder="ለምሳሌ: የአጸደ ትጉሃን ትምህርት ክፍል ኃላፊ">
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>የአስተዳዳሪ 1 ስም (Admin 1 Name)</label>
                        <input type="text" name="admin_name_1" class="form-control" 
                               value="<?php echo htmlspecialchars($admin1); ?>"
                               placeholder="ዲ/ን ኪብረአብ ዘለለም">
                    </div>
                    <div class="form-group">
                        <label>የአስተዳዳሪ 1 ስልክ (Admin 1 Phone)</label>
                        <input type="text" name="admin_phone_1" class="form-control" 
                               value="<?php echo htmlspecialchars($phone1); ?>"
                               placeholder="0939883508">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>የአስተዳዳሪ 2 ስም (Admin 2 Name)</label>
                        <input type="text" name="admin_name_2" class="form-control" 
                               value="<?php echo htmlspecialchars($admin2); ?>"
                               placeholder="ተስፋሁን ባዬ">
                    </div>
                    <div class="form-group">
                        <label>የአስተዳዳሪ 2 ስልክ (Admin 2 Phone)</label>
                        <input type="text" name="admin_phone_2" class="form-control" 
                               value="<?php echo htmlspecialchars($phone2); ?>"
                               placeholder="0943854325">
                    </div>
                </div>

                <button type="submit" name="save_settings" class="btn-save">
                    💾 አስቀምጥ / Save Settings
                </button>
            </form>
        </div>
    </div>
</body>
</html>
<?php mysqli_close($conn); ?>