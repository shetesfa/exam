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

// Current academic year
$active_year = getCurrentAcademicYear($conn);
$current_ethiopian_year = $active_year ? intval($active_year['ethiopian_year']) : 2018;

// Ensure academic year exists
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

// Get ALL semesters for current Ethiopian year
$sem1_query = "SELECT * FROM semesters WHERE ethiopian_year = $current_ethiopian_year AND semester_number = 1";
$sem1_result = mysqli_query($conn, $sem1_query);
$sem1 = mysqli_fetch_assoc($sem1_result);

$sem2_query = "SELECT * FROM semesters WHERE ethiopian_year = $current_ethiopian_year AND semester_number = 2";
$sem2_result = mysqli_query($conn, $sem2_query);
$sem2 = mysqli_fetch_assoc($sem2_result);

if(!$sem1 && !$sem2) {
    $full_name = "$current_ethiopian_year ዓ.ም መጀመሪያ ሴሚስተር";
    dbExecute(
        $conn,
        "INSERT INTO semesters (name, status, ethiopian_year, semester_number, start_date) VALUES (?, 'active', ?, 1, NOW())",
        "si",
        [$full_name, $current_ethiopian_year]
    );
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
<html lang="am">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ሴሚስተር አስተዳደር | አጸደ ትጉሃን</title>
    <?php include 'pwa_head.php'; ?>
    <style>
        :root {
            --brown-dark: #8B4513;
            --brown-medium: #A52A2A;
            --gold-primary: #FFD700;
            --gold-dark: #DAA520;
            --gold-pale: #FFF8DC;
            --bg-cream: #FAF9F6;
            --card-bg: #FFFFFF;
            --text-main: #1F2937;
            --text-muted: #6B7280;
            --border-color: #E5E7EB;
            --success: #10B981;
            --error: #EF4444;
            --warning: #F59E0B;
        }

        html.dark-mode,
        html[data-theme="dark"],
        body.dark-mode {
            --bg-cream: #0B1120 !important;
            --bg-light: #0B1120 !important;
            --card-bg: #1E293B !important;
            --card-bg-subtle: #162032 !important;
            --text-main: #F1F5F9 !important;
            --text-muted: #94A3B8 !important;
            --border-color: #334155 !important;
            --border-subtle: #243247 !important;
            --gold-pale: rgba(245, 158, 11, 0.15) !important;
        }

        html.dark-mode body,
        body.dark-mode,
        html[data-theme="dark"] body {
            background-color: #0B1120 !important;
            background: #0B1120 !important;
            color: #F1F5F9 !important;
        }

        html.dark-mode .content-card,
        html.dark-mode .stat-box,
        html.dark-mode .stat-card,
        html.dark-mode .church-card,
        html.dark-mode .class-card,
        html.dark-mode .student-card,
        html.dark-mode .teacher-card,
        html.dark-mode .user-card,
        html.dark-mode .scheme-card,
        html.dark-mode .promotion-box,
        html.dark-mode .sim-summary,
        html.dark-mode .page-hero,
        html.dark-mode .backup-item,
        html.dark-mode .history-card,
        html.dark-mode .semester-banner,
        html.dark-mode .total-summary-card,
        html.dark-mode .table-wrapper,
        html.dark-mode .modal-card,
        html.dark-mode .modal-content,
        body.dark-mode .content-card,
        body.dark-mode .stat-box,
        body.dark-mode .stat-card,
        body.dark-mode .church-card,
        body.dark-mode .class-card,
        body.dark-mode .student-card,
        body.dark-mode .teacher-card,
        body.dark-mode .user-card,
        body.dark-mode .scheme-card,
        body.dark-mode .promotion-box,
        body.dark-mode .sim-summary,
        body.dark-mode .page-hero,
        body.dark-mode .backup-item,
        body.dark-mode .history-card,
        body.dark-mode .semester-banner,
        body.dark-mode .total-summary-card,
        body.dark-mode .table-wrapper,
        body.dark-mode .modal-card,
        body.dark-mode .modal-content,
        [data-theme="dark"] .content-card,
        [data-theme="dark"] .stat-box,
        [data-theme="dark"] .stat-card,
        [data-theme="dark"] .church-card,
        [data-theme="dark"] .class-card,
        [data-theme="dark"] .student-card,
        [data-theme="dark"] .teacher-card,
        [data-theme="dark"] .user-card,
        [data-theme="dark"] .scheme-card,
        [data-theme="dark"] .promotion-box,
        [data-theme="dark"] .sim-summary,
        [data-theme="dark"] .page-hero,
        [data-theme="dark"] .backup-item,
        [data-theme="dark"] .history-card,
        [data-theme="dark"] .semester-banner,
        [data-theme="dark"] .total-summary-card,
        [data-theme="dark"] .table-wrapper,
        [data-theme="dark"] .modal-card,
        [data-theme="dark"] .modal-content {
            background-color: #1E293B !important;
            background: #1E293B !important;
            color: #F1F5F9 !important;
            border-color: #334155 !important;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.4) !important;
        }

        html.dark-mode .form-input,
        html.dark-mode .filter-input,
        html.dark-mode .form-control,
        html.dark-mode .search-input,
        html.dark-mode .passmark-input,
        html.dark-mode .filter-select,
        html.dark-mode select,
        body.dark-mode .form-input,
        body.dark-mode .filter-input,
        body.dark-mode .form-control,
        body.dark-mode .search-input,
        body.dark-mode .passmark-input,
        body.dark-mode .filter-select,
        body.dark-mode select,
        [data-theme="dark"] .form-input,
        [data-theme="dark"] .filter-input,
        [data-theme="dark"] .form-control,
        [data-theme="dark"] .search-input,
        [data-theme="dark"] .passmark-input,
        [data-theme="dark"] .filter-select,
        [data-theme="dark"] select {
            background-color: #0F172A !important;
            background: #0F172A !important;
            color: #F8FAFC !important;
            border-color: #475569 !important;
        }

        html.dark-mode th,
        body.dark-mode th,
        [data-theme="dark"] th {
            background-color: #0F172A !important;
            background: #0F172A !important;
            color: #FCD34D !important;
            border-color: #334155 !important;
        }

        html.dark-mode td,
        body.dark-mode td,
        [data-theme="dark"] td {
            background-color: #1E293B !important;
            color: #E2E8F0 !important;
            border-color: #334155 !important;
        }

        html.dark-mode tr:hover td,
        body.dark-mode tr:hover td,
        [data-theme="dark"] tr:hover td {
            background-color: #26354A !important;
        }

        html.dark-mode .stat-val,
        html.dark-mode .stat-value,
        html.dark-mode .stat-num,
        body.dark-mode .stat-val,
        body.dark-mode .stat-value,
        body.dark-mode .stat-num,
        [data-theme="dark"] .stat-val,
        [data-theme="dark"] .stat-value,
        [data-theme="dark"] .stat-num {
            color: #FCD34D !important;
        }

        html.dark-mode .stat-lbl,
        html.dark-mode .stat-label,
        body.dark-mode .stat-lbl,
        body.dark-mode .stat-label,
        [data-theme="dark"] .stat-lbl,
        [data-theme="dark"] .stat-label {
            color: #94A3B8 !important;
        }


        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Segoe UI', system-ui, -apple-system, sans-serif; }
        body { background: var(--bg-cream); color: var(--text-main); min-height: 100vh; }

        .main-container { max-width: 1100px; margin: 12px auto; padding: 0 10px 40px; }

        /* Page Header Card */
        .page-header-card {
            background: linear-gradient(135deg, #8B4513 0%, #A52A2A 100%);
            border-radius: 16px;
            padding: 18px 16px;
            color: white;
            margin-bottom: 24px;
            box-shadow: 0 8px 24px rgba(139, 69, 19, 0.18);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 16px;
            position: relative;
            overflow: hidden;
        }
        .page-header-card::after {
            content: '📅';
            position: absolute;
            right: 20px;
            bottom: -15px;
            font-size: 100px;
            opacity: 0.12;
            pointer-events: none;
        }
        .header-info h1 {
            font-size: 22px;
            font-weight: 800;
            color: var(--gold-primary);
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 6px;
        }
        .header-info p {
            font-size: 13.5px;
            color: rgba(255, 255, 255, 0.9);
        }
        .year-pill {
            background: rgba(255, 215, 0, 0.2);
            border: 1px solid var(--gold-primary);
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 13.5px;
            font-weight: 800;
            color: var(--gold-primary);
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        /* Alerts */
        .message {
            padding: 14px 18px;
            border-radius: 12px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
            font-weight: 600;
            font-size: 14px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }
        .message.success { background: #DCFCE7; color: #166534; border-left: 4px solid var(--success); }
        .message.error { background: #FEE2E2; color: #991B1B; border-left: 4px solid var(--error); }

        /* Semesters 2-Card Layout */
        .semesters-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 14px;
            margin-bottom: 28px;
        }
        .sem-card {
            background: var(--card-bg);
            border-radius: 18px;
            border: 2px solid var(--border-color);
            padding: 24px;
            box-shadow: 0 4px 16px rgba(0,0,0,0.05);
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            position: relative;
            transition: all 0.25s;
        }
        .sem-card.active {
            border-color: var(--gold-dark);
            box-shadow: 0 10px 24px rgba(218, 165, 32, 0.15);
        }
        .sem-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 16px;
            padding-bottom: 12px;
            border-bottom: 1.5px solid var(--gold-pale);
        }
        .sem-title {
            font-size: 18px;
            font-weight: 800;
            color: var(--brown-dark);
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .status-pill {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 700;
        }
        .status-pill.active { background: #DCFCE7; color: #166534; }
        .status-pill.closed { background: #FEE2E2; color: #991B1B; }
        .status-pill.pending { background: #F3F4F6; color: #6B7280; }

        .sem-dates {
            font-size: 13px;
            color: var(--text-muted);
            margin-bottom: 20px;
            line-height: 1.6;
        }
        .sem-dates div { display: flex; align-items: center; gap: 6px; }

        .sem-actions {
            display: flex;
            gap: 10px;
            align-items: center;
            flex-wrap: wrap;
        }
        .btn-open-sem {
            flex: 1;
            background: linear-gradient(135deg, var(--gold-primary), var(--gold-dark));
            color: var(--brown-dark);
            border: none;
            padding: 11px 18px;
            border-radius: 10px;
            font-weight: 700;
            font-size: 13.5px;
            cursor: pointer;
            text-align: center;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
        }
        .btn-close-sem {
            flex: 1;
            background: #FEE2E2;
            color: #DC2626;
            border: none;
            padding: 11px 18px;
            border-radius: 10px;
            font-weight: 700;
            font-size: 13.5px;
            cursor: pointer;
            text-align: center;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
        }
        .btn-history-link {
            background: #F3F4F6;
            color: var(--text-main);
            padding: 11px 16px;
            border-radius: 10px;
            font-size: 13px;
            font-weight: 600;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }
        .btn-history-link:hover { background: #E5E7EB; }

        /* History Table Card */
        .content-card {
            background: var(--card-bg);
            border-radius: 14px;
            padding: 16px 14px;
            border: 1px solid var(--border-color);
            box-shadow: 0 4px 16px rgba(0,0,0,0.05);
        }
        .content-card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 12px;
            border-bottom: 2px solid var(--gold-pale);
        }
        .content-card-header h2 {
            font-size: 17px;
            color: var(--brown-dark);
            display: flex;
            align-items: center;
            gap: 8px;
            font-weight: 700;
        }
        .table-responsive {
            width: 100%;
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }
        table.history-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13.5px;
            text-align: left;
        }
        table.history-table th {
            background: #F9FAFB;
            color: var(--brown-dark);
            font-weight: 700;
            padding: 14px 16px;
            border-bottom: 2px solid var(--border-color);
            white-space: nowrap;
        }
        table.history-table td {
            padding: 14px 16px;
            border-bottom: 1px solid #F3F4F6;
            vertical-align: middle;
        }
        table.history-table tbody tr:hover {
            background: rgba(255, 215, 0, 0.03);
        }

        @media (max-width: 768px) {
            .main-container { padding: 0 10px 40px; margin: 12px auto; }
            .page-header-card { padding: 18px 16px; }
            .semesters-grid { grid-template-columns: 1fr; gap: 14px; }
            .content-card { padding: 16px 14px; border-radius: 14px; }
            .sem-actions { flex-direction: column; }
            .btn-open-sem, .btn-close-sem, .btn-history-link { width: 100%; justify-content: center; }
        }
    </style>
</head>
<body>
    <?php include 'mobile_nav.php'; ?>

    <div class="main-container">
        <!-- Header -->
        <div class="page-header-card">
            <div class="header-info">
                <h1>📅 የትምህርት ዘመን እና ሴሚስተር አስተዳደር</h1>
                <p>ሴሚስተሮችን ይክፈቱ፣ ይዝጉ እና ያለፉ ዓመታት የውጤት መዝገቦችን በታሪክ ይገምግሙ።</p>
            </div>
            <div class="year-pill">
                <span>⛪</span> <?php echo $current_ethiopian_year; ?> ዓ.ም (አሁን ያለው ዓመት)
            </div>
        </div>

        <?php if($message): ?>
        <div class="message success">✅ <?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?></div>
        <?php endif; ?>

        <?php if($error): ?>
        <div class="message error">⚠️ <?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
        <?php endif; ?>

        <!-- Semesters Grid for Current Year -->
        <div class="semesters-grid">
            <!-- Semester 1 -->
            <?php 
                $s1_active = $sem1 && $sem1['status'] === 'active';
                $s1_closed = $sem1 && $sem1['status'] === 'closed';
            ?>
            <div class="sem-card <?php echo $s1_active ? 'active' : ''; ?>">
                <div>
                    <div class="sem-header">
                        <div class="sem-title">
                            📖 1ኛ ሴሚስተር
                        </div>
                        <?php if($s1_active): ?>
                            <span class="status-pill active">🟢 ንቁ ሴሚስተር</span>
                        <?php elseif($s1_closed): ?>
                            <span class="status-pill closed">🔴 የተዘጋ</span>
                        <?php else: ?>
                            <span class="status-pill pending">⚪ ያልተጀመረ</span>
                        <?php endif; ?>
                    </div>

                    <div class="sem-dates">
                        <div>📅 የተጀመረበት፦ <?php echo $sem1 && $sem1['start_date'] ? date('Y-m-d', strtotime($sem1['start_date'])) : 'አልተጀመረም'; ?></div>
                        <div>🏁 የተዘጋበት፦ <?php echo $sem1 && $sem1['end_date'] ? date('Y-m-d', strtotime($sem1['end_date'])) : 'አልተዘጋም'; ?></div>
                    </div>
                </div>

                <div class="sem-actions">
                    <?php if($s1_active): ?>
                        <form method="POST" style="flex:1;" onsubmit="return confirm('1ኛ ሴሚስተርን መዝጋት እርግጠኛ ነዎት? መምህራን ውጤት ማስተካከል አይችሉም!')">
                            <?php echo csrfField(); ?>
                            <input type="hidden" name="semester_id" value="<?php echo $sem1['id']; ?>">
                            <button type="submit" name="close_semester" class="btn-close-sem">
                                🔒 ሴሚስተሩን ዝጋ
                            </button>
                        </form>
                    <?php else: ?>
                        <form method="POST" style="flex:1;" onsubmit="return confirm('1ኛ ሴሚስተርን መክፈት እርግጠኛ ነዎት? ሌላ ንቁ ሴሚስተር ካለ ይዘጋል!')">
                            <?php echo csrfField(); ?>
                            <input type="hidden" name="semester_number" value="1">
                            <button type="submit" name="open_semester" class="btn-open-sem">
                                🔓 1ኛ ሴሚስተር ክፈት
                            </button>
                        </form>
                    <?php endif; ?>

                    <?php if($sem1): ?>
                        <a href="semester_history.php?id=<?php echo $sem1['id']; ?>" class="btn-history-link">
                            📊 ዝርዝር
                        </a>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Semester 2 -->
            <?php 
                $s2_active = $sem2 && $sem2['status'] === 'active';
                $s2_closed = $sem2 && $sem2['status'] === 'closed';
            ?>
            <div class="sem-card <?php echo $s2_active ? 'active' : ''; ?>">
                <div>
                    <div class="sem-header">
                        <div class="sem-title">
                            📖 2ኛ ሴሚስተር
                        </div>
                        <?php if($s2_active): ?>
                            <span class="status-pill active">🟢 ንቁ ሴሚስተር</span>
                        <?php elseif($s2_closed): ?>
                            <span class="status-pill closed">🔴 የተዘጋ</span>
                        <?php else: ?>
                            <span class="status-pill pending">⚪ ያልተጀመረ</span>
                        <?php endif; ?>
                    </div>

                    <div class="sem-dates">
                        <div>📅 የተጀመረበት፦ <?php echo $sem2 && $sem2['start_date'] ? date('Y-m-d', strtotime($sem2['start_date'])) : 'አልተጀመረም'; ?></div>
                        <div>🏁 የተዘጋበት፦ <?php echo $sem2 && $sem2['end_date'] ? date('Y-m-d', strtotime($sem2['end_date'])) : 'አልተዘጋም'; ?></div>
                    </div>
                </div>

                <div class="sem-actions">
                    <?php if($s2_active): ?>
                        <form method="POST" style="flex:1;" onsubmit="return confirm('2ኛ ሴሚስተርን መዝጋት እርግጠኛ ነዎት? መምህራን ውጤት ማስተካከል አይችሉም!')">
                            <?php echo csrfField(); ?>
                            <input type="hidden" name="semester_id" value="<?php echo $sem2['id']; ?>">
                            <button type="submit" name="close_semester" class="btn-close-sem">
                                🔒 ሴሚስተሩን ዝጋ
                            </button>
                        </form>
                    <?php else: ?>
                        <form method="POST" style="flex:1;" onsubmit="return confirm('2ኛ ሴሚስተርን መክፈት እርግጠኛ ነዎት? ሌላ ንቁ ሴሚስተር ካለ ይዘጋል!')">
                            <?php echo csrfField(); ?>
                            <input type="hidden" name="semester_number" value="2">
                            <button type="submit" name="open_semester" class="btn-open-sem">
                                🔓 2ኛ ሴሚስተር ክፈት
                            </button>
                        </form>
                    <?php endif; ?>

                    <?php if($sem2): ?>
                        <a href="semester_history.php?id=<?php echo $sem2['id']; ?>" class="btn-history-link">
                            📊 ዝርዝር
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- History Table -->
        <div class="content-card">
            <div class="content-card-header">
                <h2><span>📜</span> የዓመታት ሴሚስተር ታሪክ</h2>
            </div>

            <div class="table-responsive">
                <table class="history-table">
                    <thead>
                        <tr>
                            <th>የትምህርት ዘመን</th>
                            <th>1ኛ ሴሚስተር</th>
                            <th>2ኛ ሴሚስተር</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($history_by_year as $yr => $sems): ?>
                        <tr>
                            <td>
                                <strong>📅 <?php echo $yr; ?> ዓ.ም</strong>
                                <?php if($yr == $current_ethiopian_year): ?>
                                    <span style="font-size:11px; background:var(--gold-pale); color:var(--brown-dark); padding:2px 8px; border-radius:10px; font-weight:700; margin-left:6px;">አሁን</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if($sems['semester1']): 
                                    $st = $sems['semester1']['status'];
                                ?>
                                    <span class="status-pill <?php echo $st==='active'?'active':'closed'; ?>">
                                        <?php echo $st==='active'?'🟢 ንቁ':'🔴 የተዘጋ'; ?>
                                    </span>
                                    <a href="semester_history.php?id=<?php echo $sems['semester1']['id']; ?>" style="font-size:12px; margin-left:8px; color:#2563EB; font-weight:600;">
                                        ዝርዝር ይመልከቱ
                                    </a>
                                <?php else: ?>
                                    <span style="color:#999;">—</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if($sems['semester2']): 
                                    $st2 = $sems['semester2']['status'];
                                ?>
                                    <span class="status-pill <?php echo $st2==='active'?'active':'closed'; ?>">
                                        <?php echo $st2==='active'?'🟢 ንቁ':'🔴 የተዘጋ'; ?>
                                    </span>
                                    <a href="semester_history.php?id=<?php echo $sems['semester2']['id']; ?>" style="font-size:12px; margin-left:8px; color:#2563EB; font-weight:600;">
                                        ዝርዝር ይመልከቱ
                                    </a>
                                <?php else: ?>
                                    <span style="color:#999;">—</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</body>
</html>
<?php mysqli_close($conn); ?>