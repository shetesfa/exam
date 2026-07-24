<?php
session_start();
require_once 'db.php';
requireLogin();

if (!isTeacher()) {
    header("Location: index.php");
    exit();
}

$teacher_id = $_SESSION['user_id'];
$user_name = $_SESSION['user_name'];

$current_semester = getCurrentSemester($conn);
$semester_id = $current_semester ? $current_semester['id'] : 0;

$message = '';
$error = '';

// Get teacher's assigned classes
$classes = getTeacherClasses($conn, $teacher_id, $semester_id);

// Handle save scheme
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['save_scheme'])) {
    $class_id = intval($_POST['class_id']);
    $data = [
        'c1_name' => $_POST['c1_name'],
        'c1_perc' => $_POST['c1_perc'],
        'c2_name' => $_POST['c2_name'],
        'c2_perc' => $_POST['c2_perc'],
        'c3_name' => $_POST['c3_name'],
        'c3_perc' => $_POST['c3_perc'],
        'c4_name' => $_POST['c4_name'],
        'c4_perc' => $_POST['c4_perc'],
        'c5_name' => $_POST['c5_name'],
        'c5_perc' => $_POST['c5_perc']
    ];
    
    $result = saveMarkingScheme($conn, $teacher_id, $class_id, $semester_id, $data);
    if ($result['success']) {
        $message = $result['message'];
    } else {
        $error = $result['message'];
    }
}

// Get selected class scheme
$selected_class_id = isset($_GET['class_id']) ? intval($_GET['class_id']) : (!empty($classes) ? $classes[0]['class_id'] : 0);

$current_scheme = null;
if ($selected_class_id) {
    $current_scheme = getMarkingScheme($conn, $teacher_id, $selected_class_id, $semester_id);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/png" href="images/icon.png">
    <title>የውጤት አሰላልፍ ማስተካከያ | Marking Scheme</title>
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
            min-height: 100vh;
        }

        .header {
            background: linear-gradient(135deg, #8B4513 0%, #A52A2A 100%);
            color: white;
            padding: 20px 30px;
            border-bottom: 5px solid #FFD700;
        }

        .header-content {
            max-width: 1200px;
            margin: 0 auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
        }

        .logo-wrapper {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .logo-circle {
            width: 55px;
            height: 55px;
            background: linear-gradient(135deg, #FFD700 0%, #DAA520 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 28px;
            color: #8B4513;
            border: 3px solid white;
        }

        .title h1 {
            color: #FFD700;
            font-size: 20px;
        }

        .title p {
            color: rgba(255,255,255,0.9);
            font-size: 13px;
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .user-name {
            background: rgba(0,0,0,0.3);
            padding: 8px 20px;
            border-radius: 30px;
            border: 1px solid #FFD700;
        }

        .user-name strong {
            color: #FFD700;
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

        .btn-back:hover {
            background: var(--gold-dark);
            transform: translateY(-2px);
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

        .class-tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 25px;
            flex-wrap: wrap;
        }

        .class-tab {
            padding: 10px 20px;
            border: 2px solid var(--gold-dark);
            border-radius: 30px;
            background: white;
            color: var(--brown-dark);
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s;
        }

        .class-tab:hover {
            background: var(--gold-pale);
        }

        .class-tab.active {
            background: var(--gold-primary);
            border-color: var(--brown-dark);
        }

        .scheme-card {
            background: white;
            border-radius: 20px;
            padding: 30px;
            border: 2px solid var(--gold-primary);
            box-shadow: 0 5px 20px rgba(0,0,0,0.08);
        }

        .scheme-title {
            color: var(--brown-dark);
            font-size: 22px;
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 2px solid var(--gold-pale);
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .component-row {
            display: grid;
            grid-template-columns: 1fr 120px;
            gap: 15px;
            margin-bottom: 15px;
            align-items: end;
        }

        .component-label {
            font-weight: 600;
            color: var(--brown-dark);
            font-size: 14px;
        }

        .form-control {
            width: 100%;
            padding: 12px;
            border: 2px solid #E2E8F0;
            border-radius: 8px;
            font-size: 14px;
            transition: all 0.3s;
        }

        .form-control:focus {
            outline: none;
            border-color: var(--gold-primary);
            box-shadow: 0 0 0 3px rgba(255,215,0,0.2);
        }

        .percentage-input {
            text-align: center;
            font-weight: bold;
            color: var(--brown-dark);
        }

        .total-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px;
            background: var(--gold-pale);
            border-radius: 10px;
            margin: 20px 0;
            font-weight: bold;
            color: var(--brown-dark);
        }

        .total-value {
            font-size: 24px;
        }

        .total-value.valid {
            color: var(--success-green);
        }

        .total-value.invalid {
            color: var(--error-red);
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
        }

        .btn-save:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(218,165,32,0.3);
        }

        .btn-save:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        .info-box {
            background: #EFF6FF;
            border: 2px solid #3B82F6;
            border-radius: 10px;
            padding: 15px;
            margin-top: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 13px;
            color: #1E40AF;
        }

        @media (max-width: 600px) {
            .component-row {
                grid-template-columns: 1fr 80px;
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="header-content">
            <div class="logo-wrapper">
                <div class="logo-circle">⛪</div>
                <div class="title">
                    <h1>አጸደ ትጉሃን ሰንበት ትምህርት ቤት</h1>
                    <p>የውጤት አሰላልፍ ማስተካከያ | Marking Scheme</p>
                </div>
            </div>
            <div class="user-info">
                <div class="user-name">
                    <strong><?php echo htmlspecialchars($user_name); ?></strong>
                </div>
                <a href="dashboard_teacher.php" class="btn btn-back">← ወደ ዳሽቦርድ</a>
            </div>
        </div>
    </div>

    <div class="container">
        <?php if ($message): ?>
        <div class="message success">✅ <?php echo $message; ?></div>
        <?php endif; ?>

        <?php if ($error): ?>
        <div class="message error">⚠️ <?php echo $error; ?></div>
        <?php endif; ?>

        <?php if (!empty($classes)): ?>
        <div class="class-tabs">
            <?php foreach ($classes as $class): ?>
            <a href="?class_id=<?php echo $class['class_id']; ?>" 
               class="class-tab <?php echo $selected_class_id == $class['class_id'] ? 'active' : ''; ?>">
                📚 <?php echo htmlspecialchars($class['class_name']); ?>
            </a>
            <?php endforeach; ?>
        </div>

        <?php if ($selected_class_id): ?>
        <div class="scheme-card">
            <div class="scheme-title">
                <span>📊</span>
                <?php 
                    $className = '';
                    foreach($classes as $c) {
                        if($c['class_id'] == $selected_class_id) {
                            $className = $c['class_name'];
                            break;
                        }
                    }
                    echo 'የውጤት አሰላልፍ - ' . htmlspecialchars($className);
                ?>
            </div>

            <form method="POST" id="schemeForm">
                <input type="hidden" name="class_id" value="<?php echo $selected_class_id; ?>">

                <?php
                $components = [
                    1 => ['name' => $current_scheme['component1_name'] ?? 'Assignment', 'perc' => $current_scheme['component1_percentage'] ?? 20],
                    2 => ['name' => $current_scheme['component2_name'] ?? 'Participation', 'perc' => $current_scheme['component2_percentage'] ?? 20],
                    3 => ['name' => $current_scheme['component3_name'] ?? 'Attendance', 'perc' => $current_scheme['component3_percentage'] ?? 10],
                    4 => ['name' => $current_scheme['component4_name'] ?? 'Mid Exam', 'perc' => $current_scheme['component4_percentage'] ?? 25],
                    5 => ['name' => $current_scheme['component5_name'] ?? 'Final Exam', 'perc' => $current_scheme['component5_percentage'] ?? 25],
                ];

                foreach ($components as $index => $comp):
                ?>
                <div class="component-row">
                    <div>
                        <label class="component-label">ክፍል <?php echo $index; ?> ስም (Component <?php echo $index; ?> Name)</label>
                        <input type="text" name="c<?php echo $index; ?>_name" class="form-control" 
                               value="<?php echo htmlspecialchars($comp['name']); ?>" required>
                    </div>
                    <div>
                        <label class="component-label">% (Percentage)</label>
                        <input type="number" name="c<?php echo $index; ?>_perc" class="form-control percentage-input perc-input" 
                               value="<?php echo $comp['perc']; ?>" min="0" max="100" step="0.01" required>
                    </div>
                </div>
                <?php endforeach; ?>

                <div class="total-row">
                    <span>ጠቅላላ (Total):</span>
                    <span class="total-value" id="totalDisplay">100%</span>
                </div>

                <button type="submit" name="save_scheme" class="btn-save" id="saveBtn">
                    💾 አስቀምጥ / Save Scheme
                </button>
            </form>

            <div class="info-box">
                <span>ℹ️</span>
                <div>
                    <strong>ማስታወሻ (Note):</strong> የሁሉም ክፍሎች መቶኛ ድምር 100% መሆን አለበት።<br>
                    <small>The total of all component percentages must equal 100%</small>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <?php else: ?>
        <div style="text-align: center; padding: 60px; background: white; border-radius: 20px; border: 2px solid #FFD700;">
            <span style="font-size: 48px;">📚</span>
            <h3 style="color: #8B4513; margin-top: 15px;">ምንም የተመደቡ ክፍሎች የሉም</h3>
            <p style="color: #666;">No classes assigned for this semester</p>
        </div>
        <?php endif; ?>
    </div>

    <script>
        const percInputs = document.querySelectorAll('.perc-input');
        const totalDisplay = document.getElementById('totalDisplay');
        const saveBtn = document.getElementById('saveBtn');

        function updateTotal() {
            let total = 0;
            percInputs.forEach(input => {
                total += parseFloat(input.value) || 0;
            });
            
            total = Math.round(total * 100) / 100;
            totalDisplay.textContent = total.toFixed(2) + '%';
            
            if (Math.abs(total - 100) < 0.01) {
                totalDisplay.className = 'total-value valid';
                saveBtn.disabled = false;
            } else {
                totalDisplay.className = 'total-value invalid';
                saveBtn.disabled = true;
            }
        }

        percInputs.forEach(input => {
            input.addEventListener('input', updateTotal);
        });

        updateTotal();
    </script>
</body>
</html>
<?php mysqli_close($conn); ?>