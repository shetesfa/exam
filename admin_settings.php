<?php
require_once 'db.php';
requireAdmin();

$message = '';
$error = '';

// Handle save settings
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_settings'])) {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = "የደህንነት ማረጋገጫ አልተሳካም! እባክዎ እንደገና ይሞክሩ።";
    } else {
        $settings = [
            'admin_name_1' => trim($_POST['admin_name_1'] ?? ''),
            'admin_phone_1' => trim($_POST['admin_phone_1'] ?? ''),
            'admin_name_2' => trim($_POST['admin_name_2'] ?? ''),
            'admin_phone_2' => trim($_POST['admin_phone_2'] ?? ''),
            'admin_title' => trim($_POST['admin_title'] ?? ''),
            'youth_can_create_plans' => isset($_POST['youth_can_create_plans']) ? '1' : '0',
            'youth_can_write_attendance' => isset($_POST['youth_can_write_attendance']) ? '1' : '0'
        ];
        
        $success = true;
        foreach ($settings as $key => $value) {
            $saved = dbExecute(
                $conn,
                "INSERT INTO settings (setting_key, setting_value) 
                 VALUES (?, ?)
                 ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)",
                "ss",
                [$key, $value]
            );
            if (!$saved) {
                $success = false;
                break;
            }
        }
        
        if ($success) {
            $message = "መረጃው በትክክል ተቀምጧል!";
        } else {
            $error = "ስህተት ተከስቷል! እባክዎ እንደገና ይሞክሩ።";
        }
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
$youth_can_create_plans = ($current_settings['youth_can_create_plans'] ?? '0') === '1';
$youth_can_write_attendance = ($current_settings['youth_can_write_attendance'] ?? '0') === '1';
$nav_active = 'admin_settings';
?>
<!DOCTYPE html>
<html lang="am">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ቅንብሮች አስተዳደር | Settings</title>
    <?php include 'pwa_head.php'; ?>
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

        .main-container {
            max-width: 850px;
            margin: 12px auto;
            padding: 0 10px 40px;
        }

        .message {
            padding: 14px 18px;
            border-radius: 10px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 14px;
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
            border-radius: 14px;
            padding: 18px 14px;
            border: 2px solid var(--gold-primary);
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
        }

        .settings-title {
            color: var(--brown-dark);
            font-size: 17px;
            margin-bottom: 16px;
            padding-bottom: 12px;
            border-bottom: 2px solid var(--gold-pale);
            display: flex;
            align-items: center;
            gap: 10px;
            font-weight: 700;
        }

        .form-group {
            margin-bottom: 18px;
        }

        .form-group label {
            display: block;
            margin-bottom: 7px;
            color: var(--brown-dark);
            font-weight: 600;
            font-size: 13.5px;
        }

        .form-control {
            width: 100%;
            padding: 12px 14px;
            border: 2px solid #E2E8F0;
            border-radius: 10px;
            font-size: 14px;
            transition: all 0.25s;
            background: white;
            color: #1F2937;
        }

        .form-control:focus {
            outline: none;
            border-color: var(--gold-primary);
            box-shadow: 0 0 0 3px rgba(255,215,0,0.2);
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr;
            gap: 0;
        }

        .btn-save {
            width: 100%;
            min-height: 48px;
            padding: 12px 20px;
            background: linear-gradient(135deg, #FFD700 0%, #DAA520 100%);
            color: #8B4513;
            border: none;
            border-radius: 10px;
            font-size: 14px;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.25s;
            margin-top: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .btn-save:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(218,165,32,0.3);
        }

        .permissions-panel {
            display: flex;
            flex-direction: column;
            gap: 12px;
            background: #FAF9F6;
            padding: 16px;
            border-radius: 12px;
            border: 1.5px solid #E2E8F0;
        }
        .permission-label {
            display: flex;
            align-items: center;
            gap: 10px;
            cursor: pointer;
            font-weight: 600;
            font-size: 14px;
            color: #1F2937;
        }
        .permission-hint {
            font-size: 12px;
            color: #6B7280;
            margin-left: 30px;
        }
        .settings-help-text {
            color: #6B7280;
        }

        @media (max-width: 600px) {
            .main-container {
                padding: 0 16px 60px;
                margin: 20px auto;
            }
            .settings-card {
                padding: 28px 24px;
                border-radius: 18px;
            }
            .settings-title {
                font-size: 20px;
                margin-bottom: 22px;
            }
            .form-row {
                grid-template-columns: 1fr 1fr;
                gap: 16px;
            }
            .btn-save {
                font-size: 15px;
            }
        }
    </style>
</head>
<body>
    <?php include 'mobile_nav.php'; ?>

    <div class="main-container">
        <?php if ($message): ?>
        <div class="message success">✅ <?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?></div>
        <?php endif; ?>

        <?php if ($error): ?>
        <div class="message error">⚠️ <?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
        <?php endif; ?>

        <div class="settings-card">
            <div class="settings-title">
                <span>⚙️</span>
                የትምህርት ክፍል ኃላፊዎች መረጃ
            </div>

            <form method="POST">
                <?php echo csrfField(); ?>
                <div class="form-group">
                    <label>የኃላፊነት ማዕረግ</label>
                    <input type="text" name="admin_title" class="form-control" 
                           value="<?php echo htmlspecialchars($title); ?>" 
                           placeholder="ለምሳሌ: የአጸደ ትጉሃን ትምህርት ክፍል ኃላፊ">
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>የትምህርት ክፍል ኃላፊ 1 ሙሉ ስም</label>
                        <input type="text" name="admin_name_1" class="form-control" 
                               value="<?php echo htmlspecialchars($admin1); ?>"
                               placeholder="ዲ/ን ኪብረአብ ዘለለም">
                    </div>
                    <div class="form-group">
                        <label>የትምህርት ክፍል ኃላፊ 1 ስልክ ቁጥር</label>
                        <input type="text" name="admin_phone_1" class="form-control" 
                               value="<?php echo htmlspecialchars($phone1); ?>"
                               placeholder="0939883508">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>የትምህርት ክፍል ኃላፊ 2 ሙሉ ስም</label>
                        <input type="text" name="admin_name_2" class="form-control" 
                               value="<?php echo htmlspecialchars($admin2); ?>"
                               placeholder="ተስፋሁን ባዬ">
                    </div>
                    <div class="form-group">
                        <label>የትምህርት ክፍል ኃላፊ 2 ስልክ ቁጥር</label>
                        <input type="text" name="admin_phone_2" class="form-control" 
                               value="<?php echo htmlspecialchars($phone2); ?>"
                               placeholder="0943854325">
                    </div>
                </div>

                <div style="margin-top:24px; padding-top:18px; border-top:2px solid var(--gold-pale);">
                    <div style="font-weight:700; color:var(--brown-dark); font-size:16px; margin-bottom:12px; display:flex; align-items:center; gap:8px;">
                        <span>🛡️</span> የወጣቶች እና የህፃናት መምህራን ፈቃዶች
                    </div>
                    <p class="settings-help-text" style="font-size:13px; margin-bottom:14px; line-height:1.5;">
                        በስርዓቱ መሠረት የህፃናት መምህራን (ከ1ኛ-6ኛ ክፍል) ሁልጊዜም አቴንዳንስ መመዝገብ እና የትምህርት ዕቅድ ማዘጋጀት ይችላሉ።<br>
                        ለወጣቶች መምህራን (ከ7ኛ-12ኛ ክፍል) የሚከተሉትን ፍቃዶች መፍቀድ ወይም መከልከል ይችላሉ፦
                    </p>

                    <div class="permissions-panel">
                        <label class="permission-label">
                            <input type="checkbox" name="youth_can_create_plans" value="1" <?php echo $youth_can_create_plans ? 'checked' : ''; ?> style="width:20px; height:20px; accent-color:var(--brown-dark);">
                            <span>📝 የወጣቶች መምህራን የትምህርት ዕቅድ ማዘጋጀት ይችላሉ</span>
                        </label>
                        <div class="permission-hint">
                            ሲጠፋ፦ የወጣቶች መምህራን በሜኑ ላይ የትምህርት ዕቅድ ማዘጋጃ አይታይላቸውም፤ አዲስ ዕቅድ ማዘጋጀትም አይችሉም።
                        </div>

                        <label class="permission-label" style="margin-top:8px;">
                            <input type="checkbox" name="youth_can_write_attendance" value="1" <?php echo $youth_can_write_attendance ? 'checked' : ''; ?> style="width:20px; height:20px; accent-color:var(--brown-dark);">
                            <span>📋 የወጣቶች መምህራን አቴንዳንስ መመዝገብ ይችላሉ</span>
                        </label>
                        <div class="permission-hint">
                            ሲጠፋ፦ የወጣቶች መምህራን የተማሪዎች አቴንዳንስ ማየት ብቻ ይችላሉ፤ መጻፍ ወይም ማረም አይችሉም።
                        </div>
                    </div>
                </div>

                <button type="submit" name="save_settings" class="btn-save">
                    💾 ቅንብሮቹን አስቀምጥ
                </button>
            </form>
        </div>
    </div>
</body>
</html>
<?php mysqli_close($conn); ?>