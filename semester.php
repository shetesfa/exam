<?php
require_once 'db.php';
requireAdmin();

$message = '';
$error = '';

// Ethiopian months in Amharic
$ethiopian_months = [
    1 => 'መስከረም', 2 => 'ጥቅምት', 3 => 'ኅዳር', 4 => 'ታኅሣሥ',
    5 => 'ጥር', 6 => 'የካቲት', 7 => 'መጋቢት', 8 => 'ሚያዝያ',
    9 => 'ግንቦት', 10 => 'ሰኔ', 11 => 'ሐምሌ', 12 => 'ነሐሴ', 13 => 'ጳጉሜን'
];

// Function to format time in 12-hour Gregorian format
function formatTime12Hour($datetime) {
    if (!$datetime) return 'N/A';
    return date('M d, Y - h:i A', strtotime($datetime));
}

// Current date in Gregorian
$current_date_gregorian = date('l, F j, Y');
$current_time_12hr = date('h:i A');
$current_datetime_gregorian = date('l, F j, Y - h:i A');

// Current academic year
$active_year = getCurrentAcademicYear($conn);
$current_ethiopian_year = $active_year ? intval($active_year['ethiopian_year']) : 2018;

// First, ensure academic year exists
$check_year = dbFetchOne($conn, "SELECT * FROM academic_years WHERE ethiopian_year = ?", "i", [$current_ethiopian_year]);
if (!$check_year) {
    dbExecute($conn, "INSERT INTO academic_years (ethiopian_year, status, start_date) VALUES (?, 'active', CURDATE())", "i", [$current_ethiopian_year]);
}

$current_year = dbFetchOne($conn, "SELECT * FROM academic_years WHERE ethiopian_year = ? LIMIT 1", "i", [$current_ethiopian_year]);

// Handle semester actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = "የደህንነት ማረጋገጫ አልተሳካም! እባክዎ እንደገና ይሞክሩ።";
    } else {
        if (isset($_POST['close_semester'])) {
            $semester_id = intval($_POST['semester_id'] ?? 0);
            
            if ($semester_id > 0) {
                $closed = dbExecute($conn, "UPDATE semesters SET status = 'closed', end_date = NOW() WHERE id = ?", "i", [$semester_id]);
                if ($closed) {
                    header("Location: semester.php?closed=1");
                    exit();
                } else {
                    $error = "ስህተት ተከስቷል!";
                }
            }
        }
        
        if (isset($_POST['open_semester'])) {
            $semester_number = intval($_POST['semester_number'] ?? 1);
            
            // Close any currently active semester to ensure only ONE semester is active at a time
            dbExecute($conn, "UPDATE semesters SET status = 'closed', end_date = NOW() WHERE status = 'active'");

            // Check if semester already exists for current year
            $check = dbFetchOne(
                $conn,
                "SELECT id FROM semesters WHERE ethiopian_year = ? AND semester_number = ?",
                "ii",
                [$current_ethiopian_year, $semester_number]
            );
            
            if ($check) {
                dbExecute($conn, "UPDATE semesters SET status = 'active', start_date = NOW(), end_date = NULL WHERE id = ?", "i", [intval($check['id'])]);
            } else {
                $semester_name = ($semester_number === 1) ? "መጀመሪያ" : "ሁለተኛ";
                $full_name = "$current_ethiopian_year ዓ.ም $semester_name ሴሚስተር";
                
                dbExecute(
                    $conn,
                    "INSERT INTO semesters (name, status, ethiopian_year, semester_number, start_date) VALUES (?, 'active', ?, ?, NOW())",
                    "sii",
                    [$full_name, $current_ethiopian_year, $semester_number]
                );
            }
            
            header("Location: semester.php?opened=1");
            exit();
        }
    }
}

// Check for URL parameters for messages
if(isset($_GET['closed']) && $_GET['closed'] == 1) {
    $message = "ሴሚስተር በተሳካ ሁኔታ ተዘግቷል!";
}
if(isset($_GET['opened']) && $_GET['opened'] == 1) {
    $message = "ሴሚስተር በተሳካ ሁኔታ ተከፍቷል!";
}

// Get ALL semesters for current Ethiopian year - DON'T force any to be active automatically
$sem1_query = "SELECT * FROM semesters WHERE ethiopian_year = $current_ethiopian_year AND semester_number = 1";
$sem1_result = mysqli_query($conn, $sem1_query);
$sem1 = mysqli_fetch_assoc($sem1_result);

$sem2_query = "SELECT * FROM semesters WHERE ethiopian_year = $current_ethiopian_year AND semester_number = 2";
$sem2_result = mysqli_query($conn, $sem2_query);
$sem2 = mysqli_fetch_assoc($sem2_result);

// If no semesters exist at all for this year, create Semester 1 as active (only for initial setup)
if(!$sem1 && !$sem2) {
    $full_name = "$current_ethiopian_year ዓ.ም መጀመሪያ ሴሚስተር";
    dbExecute(
        $conn,
        "INSERT INTO semesters (name, status, ethiopian_year, semester_number, start_date) VALUES (?, 'active', ?, 1, NOW())",
        "si",
        [$full_name, $current_ethiopian_year]
    );
    
    // Refresh the query
    $sem1_result = mysqli_query($conn, $sem1_query);
    $sem1 = mysqli_fetch_assoc($sem1_result);
}

// Get all semesters grouped by year for history
$history_query = "SELECT * FROM semesters ORDER BY ethiopian_year DESC, semester_number ASC";
$history_result = mysqli_query($conn, $history_query);

$history_by_year = [];
if($history_result) {
    while($row = mysqli_fetch_assoc($history_result)) {
        $year = $row['ethiopian_year'];
        if(!isset($history_by_year[$year])) {
            $history_by_year[$year] = [
                'semester1' => null,
                'semester2' => null
            ];
        }
        if($row['semester_number'] == 1) {
            $history_by_year[$year]['semester1'] = $row;
        } else {
            $history_by_year[$year]['semester2'] = $row;
        }
    }
}
$nav_active = 'semester';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>ሴሚስተር አስተዳደር | አጸደ ትጉሃን </title>
    <?php include 'pwa_head.php'; ?>
    <style>
        :root {
            --brown-dark: #8B4513;
            --brown-medium: #A52A2A;
            --gold-primary: #FFD700;
            --gold-dark: #DAA520;
            --gold-pale: #FFF8DC;
            --success-green: #10B981;
            --error-red: #EF4444;
            --warning-yellow: #F59E0B;
            --info-blue: #3B82F6;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', sans-serif;
        }

        body {
            background: #FAF9F6;
        }

        .header {
            background: linear-gradient(135deg, #8B4513 0%, #A52A2A 100%);
            color: white;
            padding: 20px 30px;
        }

        .header-content {
            max-width: 1400px;
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
            width: 55px;
            height: 55px;
            background: linear-gradient(135deg, var(--gold-primary), var(--gold-dark));
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 28px;
            color: var(--brown-dark);
            border: 3px solid white;
        }

        .title h1 {
            font-size: 22px;
            color: var(--gold-primary);
        }

        .title p {
            font-size: 14px;
            color: var(--gold-light);
        }

        .nav {
    background: white;
    padding: 12px 20px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    position: sticky;
    top: 0;
    z-index: 100;
}

.nav-links {
    max-width: 1400px;
    margin: 0 auto;
    display: flex;
    gap: 6px;
    flex-wrap: wrap;
    justify-content: center;
}

.nav-link {
    padding: 8px 14px;
    color: var(--brown-dark);
    text-decoration: none;
    border-radius: 25px;
    transition: all 0.3s;
    font-weight: 600;
    font-size: 12px;
    white-space: nowrap;
    border: 1px solid transparent;
}

.nav-link:hover {
    background: var(--gold-pale);
    border-color: var(--gold-primary);
}

.nav-link.active {
    background: linear-gradient(135deg, var(--gold-primary), var(--gold-dark));
    color: var(--brown-dark);
    border-color: var(--brown-dark);
    font-weight: 700;
}

        .container {
            max-width: 1400px;
            margin: 30px auto;
            padding: 0 30px;
        }

        .message {
            padding: 15px 20px;
            border-radius: 12px;
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            gap: 12px;
            animation: slideDown 0.4s ease;
        }

        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .success {
            background: #D1FAE5;
            color: var(--success-green);
            border-left: 5px solid var(--success-green);
        }

        .error {
            background: #FEE2E2;
            color: var(--error-red);
            border-left: 5px solid var(--error-red);
        }

        .current-year-card {
            background: linear-gradient(135deg, var(--gold-pale), white);
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 30px;
            border: 3px solid var(--gold-primary);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 20px;
        }

        .year-info h2 {
            color: var(--brown-dark);
            font-size: 32px;
            margin-bottom: 10px;
        }

        .year-info p {
            color: #666;
            display: flex;
            align-items: center;
            gap: 20px;
            flex-wrap: wrap;
        }

        .gregorian-date {
            background: var(--info-blue);
            color: white;
            padding: 8px 20px;
            border-radius: 30px;
            font-size: 16px;
            font-weight: bold;
            display: inline-flex;
            align-items: center;
            gap: 10px;
        }

        .status-badge {
            display: inline-block;
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }

        .badge-active {
            background: #D1FAE5;
            color: var(--success-green);
        }

        .badge-closed {
            background: #FEE2E2;
            color: var(--error-red);
        }

        .badge-warning {
            background: #FEF3C7;
            color: var(--warning-yellow);
        }

        .semester-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 25px;
            margin: 30px 0;
        }

        .semester-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            border: 2px solid var(--gold-pale);
            transition: all 0.3s;
            position: relative;
            overflow: hidden;
        }

        .semester-card:hover {
            transform: translateY(-3px);
            border-color: var(--gold-primary);
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
        }

        .semester-card::before {
            content: '📚';
            position: absolute;
            top: 10px;
            right: 10px;
            font-size: 40px;
            opacity: 0.1;
        }

        .semester-number {
            font-size: 22px;
            font-weight: bold;
            color: var(--brown-dark);
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 2px solid var(--gold-pale);
        }

        .semester-status {
            margin: 15px 0;
            padding: 10px;
            border-radius: 8px;
            text-align: center;
            font-weight: 600;
            font-size: 16px;
        }

        .btn {
            padding: 12px 25px;
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

        .btn-primary {
            background: linear-gradient(135deg, #FFD700 0%, #DAA520 100%);
            color: #8B4513;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(218,165,32,0.3);
        }

        .btn-success {
            background: #10B981;
            color: white;
        }

        .btn-success:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(16,185,129,0.3);
        }

        .btn-danger {
            background: #EF4444;
            color: white;
        }

        .btn-danger:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(239,68,68,0.3);
        }

        .history-section {
            background: white;
            border-radius: 15px;
            padding: 25px;
            margin-top: 30px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }

        .history-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid var(--gold-pale);
            flex-wrap: wrap;
            gap: 15px;
        }

        .history-header h2 {
            color: var(--brown-dark);
            font-size: 22px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .year-group {
            margin-bottom: 25px;
            border-left: 4px solid var(--gold-primary);
            padding-left: 20px;
        }

        .year-title {
            font-size: 20px;
            font-weight: bold;
            color: var(--brown-dark);
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .year-badge {
            background: var(--brown-dark);
            color: var(--gold-primary);
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 14px;
        }

        .history-row {
            display: flex;
            gap: 20px;
            margin-bottom: 12px;
            padding: 12px;
            background: #F8F9FA;
            border-radius: 10px;
            flex-wrap: wrap;
            align-items: center;
        }

        .history-label {
            min-width: 120px;
            font-weight: 600;
            color: var(--brown-medium);
            font-size: 15px;
        }

        .history-value {
            flex: 1;
            display: flex;
            align-items: center;
            gap: 15px;
            flex-wrap: wrap;
        }

        .info-box {
            background: #EFF6FF;
            border-left: 4px solid var(--info-blue);
            padding: 20px;
            border-radius: 10px;
            margin: 20px 0;
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .info-box span {
            font-size: 28px;
        }

        .info-box strong {
            color: var(--brown-dark);
            font-size: 18px;
        }

        .warning-box {
            background: #FEF3C7;
            border-left: 4px solid var(--warning-yellow);
            padding: 15px;
            border-radius: 8px;
            margin: 20px 0;
        }

        .time-display {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
            margin-top: 10px;
        }

        @media (max-width: 768px) {
            .main-container { padding: 0 12px 30px; margin: 15px auto; }
            .current-year-card {
                flex-direction: column;
                text-align: center;
                padding: 18px 14px;
                border-radius: 12px;
            }
            .year-info h2 { font-size: 24px; }
            .year-info p { justify-content: center; gap: 10px; }
            
            .semester-grid {
                grid-template-columns: 1fr;
                gap: 15px;
                margin: 20px 0;
            }
            .semester-card { padding: 18px 14px; border-radius: 12px; }
            .btn { width: 100%; justify-content: center; min-height: 44px; }
            
            .history-section { padding: 16px 12px; border-radius: 12px; margin-top: 20px; }
            .history-header h2 { font-size: 17px; }
            .history-row {
                flex-direction: column;
                gap: 8px;
                align-items: flex-start;
                padding: 10px;
            }
            .history-value { width: 100%; }
            .history-value .btn { width: 100%; }
            .year-group { padding-left: 12px; margin-bottom: 18px; }
            .info-box { flex-direction: column; align-items: flex-start; padding: 14px; gap: 10px; }
        }

        /* Direct dark-mode overrides for semester page */
        html.dark-mode body {
            background-color: #0B1120 !important;
        }
        html.dark-mode .current-year-card {
            background: linear-gradient(135deg, #1E293B, #0F172A) !important;
            border-color: #F59E0B !important;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.4) !important;
        }
        html.dark-mode .year-info h2 {
            color: #FCD34D !important;
        }
        html.dark-mode .year-info p {
            color: #CBD5E1 !important;
        }
        html.dark-mode .semester-card {
            background-color: #1E293B !important;
            border-color: #334155 !important;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.3) !important;
            color: #F1F5F9 !important;
        }
        html.dark-mode .semester-card:hover {
            border-color: #F59E0B !important;
        }
        html.dark-mode .semester-number {
            color: #FCD34D !important;
            border-bottom-color: #334155 !important;
        }
        html.dark-mode .semester-card p {
            color: #CBD5E1 !important;
        }
        html.dark-mode .semester-card p strong {
            color: #FCD34D !important;
        }
        html.dark-mode .semester-card p small {
            color: #94A3B8 !important;
        }
        html.dark-mode .semester-status.badge-active {
            background-color: #064E3B !important;
            color: #A7F3D0 !important;
            border: 1px solid #059669 !important;
        }
        html.dark-mode .semester-status.badge-closed {
            background-color: #7F1D1D !important;
            color: #FECACA !important;
            border: 1px solid #DC2626 !important;
        }
        html.dark-mode .semester-status.badge-warning {
            background-color: #78350F !important;
            color: #FDE68A !important;
            border: 1px solid #D97706 !important;
        }
        html.dark-mode .warning-box {
            background-color: #1E293B !important;
            border: 1px solid #334155 !important;
            border-left: 4px solid #F59E0B !important;
            color: #FDE68A !important;
        }
        html.dark-mode .warning-box strong {
            color: #FCD34D !important;
        }
        html.dark-mode .history-section {
            background-color: #1E293B !important;
            border: 1px solid #334155 !important;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.4) !important;
            color: #F1F5F9 !important;
        }
        html.dark-mode .history-header {
            border-bottom-color: #334155 !important;
        }
        html.dark-mode .history-header h2 {
            color: #FCD34D !important;
        }
        html.dark-mode .year-group {
            border-left-color: #F59E0B !important;
        }
        html.dark-mode .year-title {
            color: #F8FAFC !important;
        }
        html.dark-mode .year-badge {
            background: #D97706 !important;
            color: #FFFFFF !important;
        }
        html.dark-mode .history-row {
            background-color: #0F172A !important;
            border: 1px solid #334155 !important;
            color: #E2E8F0 !important;
        }
        html.dark-mode .history-label {
            color: #FCD34D !important;
        }
        html.dark-mode .history-value {
            color: #CBD5E1 !important;
        }
        html.dark-mode .info-box {
            background-color: #1E293B !important;
            border: 1px solid #334155 !important;
            border-left: 4px solid #3B82F6 !important;
            color: #BFDBFE !important;
        }
        html.dark-mode .info-box strong {
            color: #FCD34D !important;
        }
    </style>
</head>
<body>
    <?php include 'mobile_nav.php'; ?>

    <div class="main-container">
        <?php if($message): ?>
        <div class="message success">
            <span>✅</span>
            <?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?>
        </div>
        <?php endif; ?>

        <?php if($error): ?>
        <div class="message error">
            <span>⚠️</span>
            <?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?>
        </div>
        <?php endif; ?>

        <!-- Current Year Card with Gregorian Date -->
        <div class="current-year-card">
            <div class="year-info">
                <h2>2018 ዓ.ም የትምህርት ዘመን</h2>
               
                <p style="margin-top: 10px; font-size: 16px;">
                    <?php if($sem1 && $sem1['status'] == 'active'): ?>
                        <strong style="color: var(--success-green);">✅ መጀመሪያ ሴሚስተር ክፍት ነው</strong>
                    <?php elseif($sem2 && $sem2['status'] == 'active'): ?>
                        <strong style="color: var(--success-green);">✅ ሁለተኛ ሴሚስተር ክፍት ነው</strong>
                    <?php else: ?>
                        <strong style="color: var(--warning-yellow);">⚠️ ምንም ክፍት ሴሚስተር የለም</strong>
                    <?php endif; ?>
                </p>
            </div>
        </div>

        <!-- Important Note with Current Time -->
        

        <!-- Semester Controls -->
        <div class="semester-grid">
            <!-- Semester 1 Card -->
            <div class="semester-card">
                <div class="semester-number">መጀመሪያ ሴሚስተር</div>
                <?php if($sem1): ?>
                    <div class="semester-status <?php echo $sem1['status'] == 'active' ? 'badge-active' : 'badge-closed'; ?>">
                        <?php echo $sem1['status'] == 'active' ? '✅ ክፍት ነው' : '🔒 ዝግ ነው'; ?>
                    </div>
                    <p style="color: #666; font-size: 14px; margin: 10px 0;">
                        <strong><?php echo htmlspecialchars($sem1['name']); ?></strong><br>
                        <small>የተከፈተበት: <?php echo $sem1['start_date'] ? date('M d, Y - h:i A', strtotime($sem1['start_date'])) : 'N/A'; ?></small>
                        <?php if($sem1['end_date']): ?>
                            <br><small>የተዘጋበት: <?php echo date('M d, Y - h:i A', strtotime($sem1['end_date'])); ?></small>
                        <?php endif; ?>
                    </p>
                    <?php if($sem1['status'] == 'active'): ?>
                    <form method="POST" onsubmit="return confirm('እርግጠኛ ነህ ሴሚስተሩን መዝጋት ትፈልጋለህ?');">
                        <?php echo csrfField(); ?>
                        <input type="hidden" name="semester_id" value="<?php echo $sem1['id']; ?>">
                        <button type="submit" name="close_semester" class="btn btn-danger" style="width: 100%;">
                            🔒 ሴሚስተር ዝጋ
                        </button>
                    </form>
                    <?php else: ?>
                    <form method="POST">
                        <?php echo csrfField(); ?>
                        <input type="hidden" name="semester_number" value="1">
                        <button type="submit" name="open_semester" class="btn btn-primary" style="width: 100%;">
                            ➕ ሴሚስተር ክፈት
                        </button>
                    </form>
                    <?php endif; ?>
                <?php else: ?>
                    <div class="semester-status badge-warning">⏳ አልተከፈተም</div>
                    <form method="POST">
                        <?php echo csrfField(); ?>
                        <input type="hidden" name="semester_number" value="1">
                        <button type="submit" name="open_semester" class="btn btn-primary" style="width: 100%;">
                            ➕ ሴሚስተር ክፈት
                        </button>
                    </form>
                <?php endif; ?>
            </div>

            <!-- Semester 2 Card -->
            <div class="semester-card">
                <div class="semester-number">ሁለተኛ ሴሚስተር</div>
                <?php if($sem2): ?>
                    <div class="semester-status <?php echo $sem2['status'] == 'active' ? 'badge-active' : 'badge-closed'; ?>">
                        <?php echo $sem2['status'] == 'active' ? '✅ ክፍት ነው' : '🔒 ዝግ ነው'; ?>
                    </div>
                    <p style="color: #666; font-size: 14px; margin: 10px 0;">
                        <strong><?php echo htmlspecialchars($sem2['name']); ?></strong><br>
                        <small>የተከፈተበት: <?php echo $sem2['start_date'] ? date('M d, Y - h:i A', strtotime($sem2['start_date'])) : 'N/A'; ?></small>
                        <?php if($sem2['end_date']): ?>
                            <br><small>የተዘጋበት: <?php echo date('M d, Y - h:i A', strtotime($sem2['end_date'])); ?></small>
                        <?php endif; ?>
                    </p>
                    <?php if($sem2['status'] == 'active'): ?>
                    <form method="POST" onsubmit="return confirm('እርግጠኛ ነህ ሴሚስተሩን መዝጋት ትፈልጋለህ?');">
                        <?php echo csrfField(); ?>
                        <input type="hidden" name="semester_id" value="<?php echo $sem2['id']; ?>">
                        <button type="submit" name="close_semester" class="btn btn-danger" style="width: 100%;">
                            🔒 ሴሚስተር ዝጋ
                        </button>
                    </form>
                    <?php else: ?>
                    <form method="POST">
                        <?php echo csrfField(); ?>
                        <input type="hidden" name="semester_number" value="2">
                        <button type="submit" name="open_semester" class="btn btn-primary" style="width: 100%;">
                            ➕ ሴሚስተር ክፈት
                        </button>
                    </form>
                    <?php endif; ?>
                <?php else: ?>
                    <div class="semester-status badge-warning">⏳ አልተከፈተም</div>
                    <form method="POST">
                        <?php echo csrfField(); ?>
                        <input type="hidden" name="semester_number" value="2">
                        <button type="submit" name="open_semester" class="btn btn-primary" style="width: 100%;">
                            ➕ ሴሚስተር ክፈት
                        </button>
                    </form>
                <?php endif; ?>
            </div>
        </div>

        <!-- How it works -->
        <div class="warning-box">
            <p style="display: flex; align-items: center; gap: 10px;">
                <span style="font-size: 24px;">🔑</span>
                <strong>አስተዳዳሪ ብቻ ሴሚስተር መክፈት እና መዝጋት ይችላሉ።</strong>
            </p>
        </div>

        <!-- Semester History -->
        <div class="history-section">
            <div class="history-header">
                <h2><span>📜</span> የሴሚስተር ታሪክ</h2>
            </div>

            <?php if(!empty($history_by_year)): ?>
                <?php foreach($history_by_year as $year => $semesters): ?>
                <div class="year-group">
                    <div class="year-title">
                        <?php echo $year; ?> ዓ.ም
                        <?php if($year == 2018): ?>
                        <span class="year-badge">ንቁ</span>
                        <?php endif; ?>
                    </div>
                    
                    <?php if($semesters['semester1']): ?>
                    <div class="history-row">
                        <span class="history-label">መጀመሪያ ሴሚስተር:</span>
                        <div class="history-value">
                            <span class="status-badge <?php echo $semesters['semester1']['status'] == 'active' ? 'badge-active' : 'badge-closed'; ?>">
                                <?php echo $semesters['semester1']['status'] == 'active' ? 'ክፍት' : 'ዝግ'; ?>
                            </span>
                            <span>
                                🗓️ የተከፈተበት: <?php echo formatTime12Hour($semesters['semester1']['start_date']); ?>
                                <?php if($semesters['semester1']['end_date']): ?>
                                    | የተዘጋበት: <?php echo formatTime12Hour($semesters['semester1']['end_date']); ?>
                                <?php endif; ?>
                            </span>
                        </div>
                    </div>
                    <?php endif; ?>

                    <?php if($semesters['semester2']): ?>
                    <div class="history-row">
                        <span class="history-label">ሁለተኛ ሴሚስተር:</span>
                        <div class="history-value">
                            <span class="status-badge <?php echo $semesters['semester2']['status'] == 'active' ? 'badge-active' : 'badge-closed'; ?>">
                                <?php echo $semesters['semester2']['status'] == 'active' ? 'ክፍት' : 'ዝግ'; ?>
                            </span>
                            <span>
                                🗓️ የተከፈተበት: <?php echo formatTime12Hour($semesters['semester2']['start_date']); ?>
                                <?php if($semesters['semester2']['end_date']): ?>
                                    | የተዘጋበት: <?php echo formatTime12Hour($semesters['semester2']['end_date']); ?>
                                <?php endif; ?>
                            </span>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p style="text-align: center; color: #999; padding: 40px;">ምንም ሴሚስተር ታሪክ የለም</p>
            <?php endif; ?>
        </div>
    </div>

    <script>
        // Add confirmation before closing
        document.querySelectorAll('form[onsubmit]').forEach(form => {
            form.onsubmit = function() {
                return confirm('እርግጠኛ ነህ ሴሚስተሩን መዝጋት ትፈልጋለህ?');
            };
        });
    </script>
</body>
</html>
<?php mysqli_close($conn); ?>