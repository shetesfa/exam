<?php
require_once 'db.php';
requireAdmin();

$message = '';
$error = '';

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && verifyCsrfToken($_POST['csrf_token'] ?? '')) {
    if (isset($_POST['review_action'])) {
        $plan_id = intval($_POST['plan_id'] ?? 0);
        $feedback = trim($_POST['feedback'] ?? '');
        $newStatus = 'reviewed';

        if ($plan_id > 0) {
            dbExecute($conn, "UPDATE lesson_plans SET status = ?, admin_feedback = ? WHERE id = ?", "ssi", [$newStatus, $feedback, $plan_id]);
            auditLog($conn, "lesson_plan_feedback", 'lesson_plans', $plan_id, $feedback);

            // Notify teacher
            $plan_row = dbFetchOne($conn, "SELECT teacher_id, sub_topic, topic FROM lesson_plans WHERE id = ?", "i", [$plan_id]);
            if ($plan_row && function_exists('createNotification')) {
                $pTopic = $plan_row['sub_topic'] ?: ($plan_row['topic'] ?: 'የትምህርት ዕቅድ');
                $notifTitle = "💬 ከትምህርት ክፍል የተሰጠ አስተያየት";
                $notifMsg = "ለዕቅድ '{$pTopic}' ትምህርት ክፍል አስተያየት ሰጥቷል። " . ($feedback ? "አስተያየት: $feedback" : "እባክዎ ዕቅድዎን ይመልከቱ።");
                createNotification($conn, $notifTitle, $notifMsg, ['users' => [intval($plan_row['teacher_id'])]], 'normal', null, 'lesson_plan_editor.php?edit=' . $plan_id);
            }

            $message = "አስተያየትዎ ለመምህሩ በትክክል ተልኳል!";
        }
    } elseif (isset($_POST['send_broadcast_comment'])) {
        $broadcast_msg = trim($_POST['broadcast_message'] ?? '');
        $target_audience = trim($_POST['target_audience'] ?? 'all');
        if (!empty($broadcast_msg)) {
            $recipients = [];
            $audience_label = "ለሁሉም መምህራን";
            if ($target_audience === 'all') {
                $rows = dbFetchAll($conn, "SELECT id FROM users WHERE role = 'teacher'");
                $recipients = array_column($rows, 'id');
                $title = "📢 አጠቃላይ የትምህርት ዕቅድ ማስታወሻ";
            } elseif ($target_audience === 'children') {
                $rows = dbFetchAll($conn, "SELECT DISTINCT tc.teacher_id as id FROM teacher_class tc JOIN classes c ON tc.class_id = c.id LEFT JOIN grades g ON c.grade_id = g.id WHERE g.division_id = 1 OR g.level_number <= 6");
                $recipients = array_column($rows, 'id');
                $title = "📢 የህፃናት ክፍል መምህራን የዕቅድ ማስታወሻ";
                $audience_label = "የህፃናት ክፍል መምህራን";
            } elseif ($target_audience === 'youth') {
                $rows = dbFetchAll($conn, "SELECT DISTINCT tc.teacher_id as id FROM teacher_class tc JOIN classes c ON tc.class_id = c.id LEFT JOIN grades g ON c.grade_id = g.id WHERE g.division_id = 2 OR g.level_number >= 7");
                $recipients = array_column($rows, 'id');
                $title = "📢 የወጣቶች ክፍል መምህራን ማስታወሻ";
                $audience_label = "የወጣቶች ክፍል መምህራን";
            } else {
                $cid = intval($target_audience);
                $cRow = dbFetchOne($conn, "SELECT name FROM classes WHERE id = ?", "i", [$cid]);
                $cName = $cRow ? $cRow['name'] : 'ክፍል';
                $rows = dbFetchAll($conn, "SELECT DISTINCT teacher_id as id FROM teacher_class WHERE class_id = ?", "i", [$cid]);
                $recipients = array_column($rows, 'id');
                $title = "📢 ለ{$cName} መምህራን የተሰጠ ማስታወሻ";
                $audience_label = "የ{$cName} መምህራን";
            }

            if (!empty($recipients)) {
                createNotification($conn, $title, $broadcast_msg, ['users' => $recipients], 'high', null, 'lesson_plan_editor.php');
                auditLog($conn, 'lesson_plan_broadcast', 'notifications', 0, "Sent to $audience_label: $broadcast_msg");
                $message = "ማስታወሻው ለ{$audience_label} (" . count($recipients) . " መምህራን) በስኬት ተሰራጭቷል!";
            } else {
                $error = "የተመረጡ መምህራን አልተገኙም!";
            }
        } else {
            $error = "እባክዎ የማስታወሻውን መልዕክት ያስገቡ!";
        }
    }
}

// Build class-to-teachers mapping for dependent dropdown
$tc_mapping_query = "
    SELECT DISTINCT tc.class_id, u.id as teacher_id, u.name as teacher_name
    FROM teacher_class tc
    JOIN users u ON tc.teacher_id = u.id
    WHERE u.role = 'teacher'
    UNION
    SELECT DISTINCT lp.class_id, u.id as teacher_id, u.name as teacher_name
    FROM lesson_plans lp
    JOIN users u ON lp.teacher_id = u.id
    WHERE lp.is_deleted = 0 AND u.role = 'teacher'
    ORDER BY teacher_name
";
$tc_mapping_rows = dbFetchAll($conn, $tc_mapping_query);
$class_teachers_map = [];
foreach ($tc_mapping_rows as $row) {
    $cid = intval($row['class_id']);
    if (!isset($class_teachers_map[$cid])) {
        $class_teachers_map[$cid] = [];
    }
    $class_teachers_map[$cid][] = [
        'id' => intval($row['teacher_id']),
        'name' => $row['teacher_name']
    ];
}

$all_teachers = dbFetchAll($conn, "SELECT id, name FROM users WHERE role = 'teacher' ORDER BY name");
$classes = dbFetchAll($conn, "SELECT * FROM classes ORDER BY name");

// Filters from GET
$f_class = intval($_GET['class_id'] ?? 0);
$f_teacher = intval($_GET['teacher_id'] ?? 0);
$f_status = trim($_GET['status'] ?? '');
$f_month = intval($_GET['month'] ?? 0);
$f_search = trim($_GET['search'] ?? '');

// Filter available teachers for dropdown based on selected class
if ($f_class > 0 && isset($class_teachers_map[$f_class])) {
    $available_teachers = $class_teachers_map[$f_class];
    $teacher_ids_in_class = array_column($available_teachers, 'id');
    if ($f_teacher > 0 && !in_array($f_teacher, $teacher_ids_in_class)) {
        $f_teacher = 0;
    }
} elseif ($f_class > 0) {
    $available_teachers = [];
    $f_teacher = 0;
} else {
    $available_teachers = $all_teachers;
}

// Build query
$where = "WHERE lp.is_deleted = 0";
$params = [];
$types = "";

if ($f_class > 0) {
    $where .= " AND lp.class_id = ?";
    $types .= "i";
    $params[] = $f_class;
}
if ($f_teacher > 0) {
    $where .= " AND lp.teacher_id = ?";
    $types .= "i";
    $params[] = $f_teacher;
}
if ($f_status !== '') {
    $where .= " AND lp.status = ?";
    $types .= "s";
    $params[] = $f_status;
}
if ($f_month > 0) {
    $where .= " AND lp.ethiopian_month = ?";
    $types .= "i";
    $params[] = $f_month;
}
if ($f_search !== '') {
    $where .= " AND (lp.topic LIKE ? OR lp.sub_topic LIKE ? OR lp.chapter LIKE ? OR lp.objective LIKE ? OR u.name LIKE ? OR c.name LIKE ?)";
    $types .= "ssssss";
    $searchTerm = "%" . $f_search . "%";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
}

$plans = dbFetchAll(
    $conn,
    "SELECT lp.*, c.name as class_name, u.name as teacher_name, u.phone as teacher_phone
     FROM lesson_plans lp 
     JOIN classes c ON lp.class_id = c.id 
     JOIN users u ON lp.teacher_id = u.id
     $where 
     ORDER BY lp.updated_at DESC, lp.id DESC 
     LIMIT 250",
    $types,
    $params
);

// KPI Stats
$stats = dbFetchOne($conn, "
    SELECT 
        COUNT(*) as total_plans,
        SUM(CASE WHEN lp.status = 'submitted' THEN 1 ELSE 0 END) as submitted_count,
        SUM(CASE WHEN lp.status = 'reviewed' OR (lp.admin_feedback IS NOT NULL AND lp.admin_feedback != '') THEN 1 ELSE 0 END) as reviewed_count,
        SUM(CASE WHEN g.division_id = 1 OR g.level_number <= 6 THEN 1 ELSE 0 END) as children_count,
        SUM(CASE WHEN g.division_id = 2 OR g.level_number >= 7 THEN 1 ELSE 0 END) as youth_count
    FROM lesson_plans lp
    JOIN classes c ON lp.class_id = c.id
    LEFT JOIN grades g ON c.grade_id = g.id
    WHERE lp.is_deleted = 0
") ?: [
    'total_plans' => 0, 'submitted_count' => 0, 'reviewed_count' => 0,
    'children_count' => 0, 'youth_count' => 0
];

$status_labels = [
    'submitted' => '📄 የተቀመጠ ዕቅድ',
    'reviewed' => '💬 አስተያየት ተሰጥቷል'
];

$ethiopian_months = [
    1 => 'መስከረም', 2 => 'ጥቅምት', 3 => 'ኅዳር', 4 => 'ታኅሣሥ',
    5 => 'ጥር', 6 => 'የካቲት', 7 => 'መጋቢት', 8 => 'ሚያዝያ',
    9 => 'ግንቦት', 10 => 'ሰኔ', 11 => 'ሐምሌ', 12 => 'ነሐሴ', 13 => 'ጳጉሜን'
];

$nav_active = 'lesson_plan_admin_review';
?>
<!DOCTYPE html>
<html lang="am">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>የትምህርት ዕቅድ ግምገማ | አጸደ ትጉሃን</title>
    <?php include 'pwa_head.php'; ?>
    <style>
        :root {
            --brown-dark: #8B4513;
            --brown-medium: #A52A2A;
            --gold-primary: #FFD700;
            --gold-dark: #DAA520;
            --gold-pale: #FFF8DC;
            --bg-cream: #FAF9F6;
            --success: #10B981;
            --error: #EF4444;
            --warning: #F59E0B;
            --info: #3B82F6;
        }

        * { margin:0; padding:0; box-sizing:border-box; font-family:'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        body { background: var(--bg-cream); color: #333; min-height: 100vh; }
        .main-container { max-width: 1400px; margin: 12px auto; padding: 0 10px 40px; }

        /* KPI Cards */
        .kpi-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
            margin-bottom: 22px;
        }
        .kpi-card {
            background: white;
            border-radius: 14px;
            padding: 16px 18px;
            border: 1px solid #E5E7EB;
            box-shadow: 0 4px 12px rgba(0,0,0,0.04);
            display: flex;
            align-items: center;
            gap: 14px;
            text-decoration: none;
            color: inherit;
            transition: all 0.25s ease;
        }
        .kpi-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 18px rgba(0,0,0,0.08);
            border-color: var(--gold-dark);
        }
        .kpi-icon {
            width: 44px;
            height: 44px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 22px;
        }
        .kpi-title { font-size: 12px; color: #6B7280; font-weight: 600; text-transform: uppercase; }
        .kpi-num { font-size: 24px; font-weight: 800; color: var(--brown-dark); line-height: 1.2; }
        .guidance-card { background: linear-gradient(135deg, #FFFDF7, #FFF8DC); }

        /* Filter Section */
        .filter-panel {
            background: white;
            border-radius: 16px;
            padding: 16px;
            margin-bottom: 24px;
            border: 2px solid var(--gold-primary);
            box-shadow: 0 6px 20px rgba(0,0,0,0.05);
        }
        .filter-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 16px;
            flex-wrap: wrap;
            gap: 10px;
        }
        .filter-title {
            font-size: 17px;
            font-weight: 700;
            color: var(--brown-dark);
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .filter-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 14px;
            align-items: end;
        }
        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }
        .filter-group label {
            font-size: 12px;
            font-weight: 700;
            color: var(--brown-dark);
        }
        .filter-control {
            padding: 9px 12px;
            border: 1.5px solid #D1D5DB;
            border-radius: 10px;
            font-size: 13px;
            background: white;
            color: #333;
            transition: border-color 0.2s;
            outline: none;
            width: 100%;
        }
        .filter-control:focus {
            border-color: var(--gold-dark);
            box-shadow: 0 0 0 3px rgba(218,165,32,0.15);
        }
        .filter-actions {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }

        /* Buttons */
        .btn {
            padding: 9px 16px;
            border-radius: 10px;
            border: none;
            font-size: 13px;
            font-weight: 700;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            transition: all 0.2s;
            line-height: 1.2;
        }
        .btn-filter { background: var(--brown-dark); color: var(--gold-primary); }
        .btn-filter:hover { background: #68340d; }
        .btn-reset { background: #E5E7EB; color: #374151; }
        .btn-reset:hover { background: #D1D5DB; }
        .btn-print { background: var(--gold-pale); border: 1.5px solid var(--gold-dark); color: var(--brown-dark); }
        .btn-print:hover { background: #FFE899; }
        .btn-excel { background: #D1FAE5; border: 1.5px solid var(--success); color: #065F46; }
        .btn-excel:hover { background: #A7F3D0; }
        .btn-sm { padding: 5px 10px; font-size: 11px; border-radius: 6px; }
        .btn-review { background: #EFF6FF; border: 1.5px solid var(--info); color: #1D4ED8; }
        .btn-review:hover { background: #DBEAFE; }
        .btn-approve { background: var(--success); color: white; }
        .btn-approve:hover { background: #059669; }
        .btn-reject { background: var(--error); color: white; }
        .btn-reject:hover { background: #DC2626; }

        /* Action Top Bar */
        .table-toolbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 14px;
            flex-wrap: wrap;
            gap: 10px;
        }
        .result-count {
            font-size: 14px;
            font-weight: 700;
            color: var(--brown-dark);
        }

        /* Table Card */
        .table-card {
            background: white;
            border-radius: 16px;
            border: 1px solid #E5E7EB;
            box-shadow: 0 6px 24px rgba(0,0,0,0.06);
            overflow: hidden;
        }
        .table-responsive {
            overflow-x: auto;
            width: 100%;
        }
        table.plans-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
            text-align: left;
            min-width: 1050px;
        }
        table.plans-table th {
            background: linear-gradient(135deg, #8B4513, #A52A2A);
            color: white;
            padding: 13px 12px;
            font-weight: 700;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.3px;
            border-bottom: 3px solid var(--gold-primary);
            white-space: nowrap;
        }
        table.plans-table td {
            padding: 12px 12px;
            border-bottom: 1px solid #F3F4F6;
            vertical-align: middle;
            color: #374151;
        }
        table.plans-table tbody tr:hover {
            background: #FFFDF5;
        }
        table.plans-table tbody tr:last-child td {
            border-bottom: none;
        }

        /* Badges */
        .badge {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 700;
            white-space: nowrap;
        }
        .badge-class {
            background: var(--gold-pale);
            color: var(--brown-dark);
            border: 1px solid var(--gold-primary);
        }
        .badge-submitted { background: #FEF3C7; color: #92400E; border: 1px solid #F59E0B; }
        .badge-approved { background: #D1FAE5; color: #065F46; border: 1px solid #10B981; }
        .badge-needs_correction { background: #FEE2E2; color: #991B1B; border: 1px solid #EF4444; }
        .badge-reviewed { background: #DBEAFE; color: #1E40AF; border: 1px solid #3B82F6; }
        .badge-draft { background: #F3F4F6; color: #4B5563; border: 1px solid #9CA3AF; }

        /* Cell content styling */
        .teacher-cell { font-weight: 700; color: var(--brown-dark); }
        .topic-cell { font-weight: 700; color: #111827; }
        .text-truncate-2 {
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
            max-width: 260px;
            font-size: 12px;
            color: #4B5563;
            line-height: 1.35;
        }

        /* Thumbnail */
        .thumb-link {
            display: inline-block;
            position: relative;
            cursor: pointer;
        }
        .thumb-img {
            width: 44px;
            height: 44px;
            border-radius: 8px;
            object-fit: cover;
            border: 1.5px solid #D1D5DB;
            transition: transform 0.2s;
        }
        .thumb-img:hover { transform: scale(1.1); border-color: var(--gold-dark); }

        /* Modal styling */
        .modal-overlay {
            position: fixed;
            top: 0; left: 0; right: 0; bottom: 0;
            background: rgba(0,0,0,0.6);
            backdrop-filter: blur(3px);
            display: none;
            justify-content: center;
            align-items: center;
            z-index: 9999;
            padding: 15px;
        }
        .modal-overlay.show { display: flex; animation: fadeIn 0.25s ease; }
        .modal-box {
            background: white;
            border-radius: 18px;
            max-width: 850px;
            width: 100%;
            max-height: 90vh;
            overflow-y: auto;
            border: 2px solid var(--gold-primary);
            box-shadow: 0 15px 40px rgba(0,0,0,0.25);
            padding: 24px 28px;
            position: relative;
        }
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-bottom: 14px;
            border-bottom: 2px solid var(--gold-pale);
            margin-bottom: 18px;
        }
        .modal-title { font-size: 18px; color: var(--brown-dark); font-weight: 800; display: flex; align-items: center; gap: 8px; }
        .modal-close {
            background: #F3F4F6; border: none; width: 32px; height: 32px; border-radius: 50%;
            font-size: 16px; font-weight: bold; cursor: pointer; color: #4B5563;
        }
        .modal-close:hover { background: #E5E7EB; color: #111; }
        
        .plan-grid-spec {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 12px;
            background: #FAF9F6;
            padding: 16px;
            border-radius: 12px;
            border: 1px solid #E5E7EB;
            margin-bottom: 18px;
        }
        .spec-item { display: flex; flex-direction: column; gap: 3px; }
        .spec-label { font-size: 11px; font-weight: 700; color: #6B7280; text-transform: uppercase; }
        .spec-value { font-size: 14px; font-weight: 600; color: var(--brown-dark); }
        .spec-full { grid-column: 1 / -1; }

        .feedback-textarea {
            width: 100%;
            padding: 12px;
            border: 1.5px solid #D1D5DB;
            border-radius: 10px;
            font-size: 13px;
            outline: none;
            resize: vertical;
            min-height: 80px;
            margin-top: 6px;
        }
        .feedback-textarea:focus {
            border-color: var(--gold-dark);
            box-shadow: 0 0 0 3px rgba(218,165,32,0.15);
        }

        @media (max-width: 768px) {
            .main-container { padding: 0 10px 40px; margin: 12px auto; }
            .filter-panel { padding: 16px; }
            .filter-grid { grid-template-columns: 1fr; }
            .kpi-grid { grid-template-columns: 1fr 1fr; }
        }

        @media print {
            .no-print, .site-header, .mobile-nav-bar, .filter-panel, .kpi-grid, .btn-print, .btn-excel, .table-toolbar .btn, th:last-child, td:last-child {
                display: none !important;
            }
            body, .main-container { background: white !important; padding: 0 10px 40px; margin: 12px auto; max-width: 100% !important; }
            .table-card { border: none !important; box-shadow: none !important; }
            table.plans-table { min-width: 100% !important; font-size: 10px !important; }
            table.plans-table th { background: #eee !important; color: black !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            .print-only-header { display: block !important; text-align: center; margin-bottom: 20px; }
        }
        .print-only-header { display: none; }
    </style>
</head>
<body class="no-print">
    <?php include 'mobile_nav.php'; ?>

    <div class="main-container">
        <!-- Print Header -->
        <div class="print-only-header">
            <h2 style="color:var(--brown-dark); margin-bottom:4px;">አጸደ ትጉሃን ሰንበት ትምህርት ቤት</h2>
            <h4 style="margin-bottom:4px;">የትምህርት ዕቅድ ግምገማ ሪፖርት</h4>
            <p style="font-size:12px; color:#555;">የታተመበት ቀን፡ <?php echo date('Y-m-d H:i'); ?></p>
        </div>

        <!-- Alert messages -->
        <?php if ($message): ?>
            <div style="background:#D1FAE5; color:#065F46; padding:12px 18px; border-radius:12px; margin-bottom:18px; border-left:5px solid var(--success); font-weight:600; display:flex; align-items:center; gap:8px;">
                <span>✅</span> <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div style="background:#FEE2E2; color:#991B1B; padding:12px 18px; border-radius:12px; margin-bottom:18px; border-left:5px solid var(--error); font-weight:600; display:flex; align-items:center; gap:8px;">
                <span>⚠️</span> <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <!-- KPI Summary Cards -->
        <div class="kpi-grid no-print">
            <a href="lesson_plan_admin_review.php" class="kpi-card">
                <div class="kpi-icon" style="background:#FEF3C7; color:var(--brown-dark);">📚</div>
                <div>
                    <div class="kpi-title">ጠቅላላ ዕቅዶች</div>
                    <div class="kpi-num"><?php echo $stats['total_plans'] ?? 0; ?></div>
                </div>
            </a>
            <a href="lesson_plan_admin_review.php?status=submitted" class="kpi-card" style="border-left:4px solid var(--warning);">
                <div class="kpi-icon" style="background:#FEF3C7; color:#B45309;">📄</div>
                <div>
                    <div class="kpi-title">የተቀመጡ ዕቅዶች</div>
                    <div class="kpi-num" style="color:#B45309;"><?php echo $stats['submitted_count'] ?? 0; ?></div>
                </div>
            </a>
            <a href="lesson_plan_admin_review.php?status=reviewed" class="kpi-card" style="border-left:4px solid var(--info);">
                <div class="kpi-icon" style="background:#DBEAFE; color:#1D4ED8;">💬</div>
                <div>
                    <div class="kpi-title">አስተያየት የተሰጠባቸው</div>
                    <div class="kpi-num" style="color:#1D4ED8;"><?php echo $stats['reviewed_count'] ?? 0; ?></div>
                </div>
            </a>
            <div class="kpi-card" style="border-left:4px solid var(--success);">
                <div class="kpi-icon" style="background:#D1FAE5; color:#047857;">🧒</div>
                <div>
                    <div class="kpi-title">የህፃናት ክፍሎች (1-6)</div>
                    <div class="kpi-num" style="color:#047857;"><?php echo $stats['children_count'] ?? 0; ?></div>
                </div>
            </div>
            <div class="kpi-card" style="border-left:4px solid #8B4513;">
                <div class="kpi-icon" style="background:#FFF8DC; color:#8B4513;">🧑</div>
                <div>
                    <div class="kpi-title">የወጣቶች ክፍሎች (7-12)</div>
                    <div class="kpi-num" style="color:#8B4513;"><?php echo $stats['youth_count'] ?? 0; ?></div>
                </div>
            </div>
        </div>

        <!-- Broadcast Announcement / Guidance Card -->
        <div class="card no-print guidance-card" style="border:2px solid var(--gold-primary); border-radius:16px; padding:20px 24px; margin-bottom:24px; box-shadow:0 4px 16px rgba(0,0,0,0.05);">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:12px; flex-wrap:wrap; gap:10px;">
                <h3 style="color:var(--brown-dark); font-size:16px; display:flex; align-items:center; gap:8px;">
                    📢 ለመምህራን አጠቃላይ ማስታወሻና መመሪያ ማስተላለፊያ
                </h3>
                <span style="font-size:12px; color:#92400E; background:#FEF3C7; padding:4px 10px; border-radius:12px; font-weight:600;">
                    🔔 የቀጥታ ማሳወቂያ
                </span>
            </div>
            <p style="font-size:13px; color:#555; margin-bottom:14px;">
                ለሁሉም ወይም ለተወሰኑ መምህራን ስለ ትምህርት ዕቅድ አዘገጃጀት፣ የማስተማሪያ መርጃዎች ወይም አጠቃላይ መመሪያዎችን በአንድ ጊዜ ማስታወሻ መላክ ይችላሉ።
            </p>
            <form method="POST" onsubmit="return confirm('ይህንን ማስታወሻ ለተመረጡት መምህራን ማሰራጨት ይፈልጋሉ?')">
                <?php echo csrfField(); ?>
                <div style="display:grid; grid-template-columns:260px 1fr auto; gap:12px; align-items:start;">
                    <div>
                        <label style="display:block; font-size:12px; font-weight:700; color:var(--brown-dark); margin-bottom:5px;">ተደራሽ መምህራን *</label>
                        <select name="target_audience" class="filter-control" style="font-weight:600;">
                            <option value="all">👥 ለሁሉም መምህራን</option>
                            <option value="children">🧒 ለህፃናት ክፍል መምህራን (ከ1ኛ-6ኛ ክፍል)</option>
                            <option value="youth">🧑 ለወጣቶች ክፍል መምህራን (ከ7ኛ-12ኛ ክፍል)</option>
                            <optgroup label="-- ለተወሰነ ክፍል ብቻ --">
                                <?php foreach ($classes as $c): ?>
                                    <option value="<?php echo $c['id']; ?>"><?php echo htmlspecialchars($c['name']); ?></option>
                                <?php endforeach; ?>
                            </optgroup>
                        </select>
                    </div>
                    <div>
                        <label style="display:block; font-size:12px; font-weight:700; color:var(--brown-dark); margin-bottom:5px;">የማስታወሻው ዝርዝር መልዕክት *</label>
                        <textarea name="broadcast_message" rows="2" class="filter-control" required placeholder="ምሳሌ፦ መምህራን የትምህርት ዕቅድ ሲያዘጋጁ ተገቢውን መርጃ መሳሪያና የተማሪዎች ምዘና በጥንቃቄ እንዲያካትቱ እናሳስባለን..."></textarea>
                    </div>
                    <div style="padding-top:22px;">
                        <button type="submit" name="send_broadcast_comment" class="btn" style="background:#8B4513; color:white; padding:10px 20px; white-space:nowrap;">
                            📢 ማስታወሻውን አስተላልፍ
                        </button>
                    </div>
                </div>
            </form>
        </div>

        <!-- Advanced Filter Panel -->
        <div class="filter-panel no-print">
            <form method="GET" id="filterForm">
                <div class="filter-header">
                    <div class="filter-title">
                        <span>🔍</span> የዕቅድ መፈለጊያ እና ማጣሪያ
                    </div>
                    <div class="filter-actions">
                        <button type="submit" class="btn btn-filter">🔍 ፈልግ</button>
                        <a href="lesson_plan_admin_review.php" class="btn btn-reset">🔄 ሁሉንም አሳይ</a>
                    </div>
                </div>

                <div class="filter-grid">
                    <!-- Class Filter (Triggers Dynamic Teacher Filtering!) -->
                    <div class="filter-group">
                        <label for="classFilter">📚 ክፍል</label>
                        <select name="class_id" id="classFilter" class="filter-control" onchange="onClassFilterChange(this)">
                            <option value="">-- ሁሉም ክፍሎች --</option>
                            <?php foreach ($classes as $c): ?>
                                <option value="<?php echo $c['id']; ?>" <?php echo $f_class == $c['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($c['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Teacher Filter (Dynamically filtered by class!) -->
                    <div class="filter-group">
                        <label for="teacherFilter">
                            👨‍🏫 መምህር 
                            <span id="teacherFilterSub" style="font-weight:normal; font-size:11px; color:#777;">
                                <?php echo $f_class ? '(የተመረጠው ክፍል መምህራን)' : ''; ?>
                            </span>
                        </label>
                        <select name="teacher_id" id="teacherFilter" class="filter-control" onchange="document.getElementById('filterForm').submit()">
                            <option value="">
                                <?php echo $f_class ? '-- ሁሉም መምህራን (የዚህ ክፍል ብቻ) --' : '-- ሁሉም መምህራን --'; ?>
                            </option>
                            <?php foreach ($available_teachers as $t): ?>
                                <option value="<?php echo $t['id']; ?>" <?php echo $f_teacher == $t['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($t['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Status Filter -->
                    <div class="filter-group">
                        <label for="statusFilter">📌 ሁኔታ</label>
                        <select name="status" id="statusFilter" class="filter-control" onchange="document.getElementById('filterForm').submit()">
                            <option value="">-- ሁሉም ሁኔታዎች --</option>
                            <?php foreach ($status_labels as $k => $v): ?>
                                <option value="<?php echo $k; ?>" <?php echo $f_status === $k ? 'selected' : ''; ?>>
                                    <?php echo $v; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Month Filter -->
                    <div class="filter-group">
                        <label for="monthFilter">📅 ወር</label>
                        <select name="month" id="monthFilter" class="filter-control" onchange="document.getElementById('filterForm').submit()">
                            <option value="">-- ሁሉም ወራት --</option>
                            <?php foreach ($ethiopian_months as $m_num => $m_name): ?>
                                <option value="<?php echo $m_num; ?>" <?php echo $f_month == $m_num ? 'selected' : ''; ?>>
                                    <?php echo $m_name; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Search Input -->
                    <div class="filter-group">
                        <label for="searchInput">🔎 ፈጣን ፍለጋ</label>
                        <input type="text" name="search" id="searchInput" class="filter-control" 
                               value="<?php echo htmlspecialchars($f_search); ?>" 
                               placeholder="በርዕስ፣ በዓላማ ወይም በመምህር ስም ፈልግ...">
                    </div>
                </div>
            </form>
        </div>

        <!-- Table Toolbar -->
        <div class="table-toolbar">
            <div class="result-count">
                📋 የተገኙ የትምህርት ዕቅዶች፡ <strong><?php echo count($plans); ?></strong>
                <?php if ($f_class): ?>
                    <span style="font-size:12px; color:#6B7280; font-weight:normal;">(በተመረጠው ክፍል)</span>
                <?php endif; ?>
            </div>
            <div style="display:flex; gap:10px;" class="no-print">
                <button type="button" onclick="window.print()" class="btn btn-print">
                    🖨️ ሰንጠረዡን አትም
                </button>
                <button type="button" onclick="exportPlansToExcel()" class="btn btn-excel">
                    📊 ኤክሴል አውርድ
                </button>
            </div>
        </div>

        <!-- Plans Data Table -->
        <div class="table-card">
            <?php if (empty($plans)): ?>
                <div style="text-align:center; padding:50px 20px; color:#6B7280;">
                    <div style="font-size:48px; margin-bottom:12px;">📭</div>
                    <h3 style="color:var(--brown-dark); margin-bottom:6px;">ምንም ዕቅድ አልተገኘም</h3>
                    <p style="font-size:13px;">በተመረጡት ማጣሪያዎች መሠረት ምንም የትምህርት ዕቅድ አልተመዘገበም።</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="plans-table" id="plansTable">
                        <thead>
                            <tr>
                                <th style="width:40px;">#</th>
                                <th>ክፍልና መምህር</th>
                                <th>ወቅት / ሳምንት</th>
                                <th>ምዕራፍና ንዑስ ርዕስ</th>
                                <th>የትምህርቱ ዓላማ</th>
                                <th>መርጃ መሳሪያና ምዘና</th>
                                <th style="text-align:center;">ወረቀት</th>
                                <th style="text-align:center;">ሁኔታ</th>
                                <th class="no-print" style="text-align:center;">እርምጃ / ግምገማ</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $badgeClasses = [
                                'submitted' => 'badge-submitted',
                                'approved' => 'badge-approved',
                                'needs_correction' => 'badge-needs_correction',
                                'reviewed' => 'badge-reviewed',
                                'draft' => 'badge-draft'
                            ];

                            foreach ($plans as $idx => $p): 
                                $monthName = getEthiopianMonthName($p['ethiopian_month']);
                                $topicTitle = $p['sub_topic'] ?: ($p['topic'] ?: 'የትምህርት ዕቅድ');
                                $statusBadgeClass = $badgeClasses[$p['status']] ?? 'badge-draft';
                                $statusLabelText = $status_labels[$p['status']] ?? $p['status'];
                            ?>
                            <tr id="plan-row-<?php echo $p['id']; ?>">
                                <td style="font-weight:700; color:#9CA3AF;"><?php echo $idx + 1; ?></td>
                                <td>
                                    <span class="badge badge-class" style="margin-bottom:4px;">
                                        📚 <?php echo htmlspecialchars($p['class_name']); ?>
                                    </span>
                                    <div class="teacher-cell">
                                        👨‍🏫 <?php echo htmlspecialchars($p['teacher_name']); ?>
                                    </div>
                                    <?php if ($p['subject']): ?>
                                        <div style="font-size:11px; color:#6B7280;">📖 <?php echo htmlspecialchars($p['subject']); ?></div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div style="font-weight:700; color:var(--brown-dark);">
                                        📅 <?php echo $monthName . ' ' . $p['ethiopian_year']; ?> ዓ.ም
                                    </div>
                                    <div style="font-size:12px; color:#4B5563;">
                                        <?php echo htmlspecialchars($p['week_number'] ?: '—'); ?>
                                    </div>
                                </td>
                                <td>
                                    <?php if ($p['chapter']): ?>
                                        <div style="font-size:11px; font-weight:700; color:var(--brown-dark); margin-bottom:2px;">
                                            📖 <?php echo htmlspecialchars($p['chapter']); ?>
                                        </div>
                                    <?php endif; ?>
                                    <div class="topic-cell">
                                        <?php echo htmlspecialchars($topicTitle); ?>
                                    </div>
                                </td>
                                <td>
                                    <div class="text-truncate-2" title="<?php echo htmlspecialchars($p['objective'] ?: ''); ?>">
                                        <?php echo htmlspecialchars($p['objective'] ?: '—'); ?>
                                    </div>
                                </td>
                                <td>
                                    <div style="font-size:11px; margin-bottom:3px;">
                                        <strong style="color:var(--brown-dark);">መርጃ፡</strong> 
                                        <?php echo htmlspecialchars(mb_strimwidth($p['materials'] ?: '—', 0, 45, '...')); ?>
                                    </div>
                                    <div style="font-size:11px;">
                                        <strong style="color:var(--brown-dark);">ምዘና፡</strong> 
                                        <?php echo htmlspecialchars(mb_strimwidth($p['evaluation'] ?: '—', 0, 45, '...')); ?>
                                    </div>
                                </td>
                                <td style="text-align:center;">
                                    <?php if (!empty($p['paper_photo_path'])): ?>
                                        <a href="<?php echo htmlspecialchars($p['paper_photo_path']); ?>" target="_blank" class="thumb-link" title="የወረቀት ፎቶ ክፈት">
                                            <img src="<?php echo htmlspecialchars($p['paper_photo_path']); ?>" class="thumb-img" alt="Paper Plan">
                                        </a>
                                    <?php else: ?>
                                        <span style="color:#D1D5DB;">—</span>
                                    <?php endif; ?>
                                </td>
                                <td style="text-align:center;">
                                    <span class="badge <?php echo $statusBadgeClass; ?>">
                                        <?php echo $statusLabelText; ?>
                                    </span>
                                </td>
                                <td class="no-print" style="text-align:center; white-space:nowrap;">
                                    <div style="display:inline-flex; gap:5px; align-items:center;">
                                        <button type="button" class="btn btn-review btn-sm" onclick='openPlanReviewModal(<?php echo json_encode($p, JSON_UNESCAPED_UNICODE); ?>)'>
                                            💬 አስተያየት ስጥ / ተመልከት
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Review / Detail Modal Dialog -->
    <div id="reviewModal" class="modal-overlay">
        <div class="modal-box">
            <div class="modal-header">
                <div class="modal-title">
                    <span>💬</span>
                    <span id="modalTopicTitle">የትምህርት ዕቅድ መመልከቻ እና አስተያየት መስጫ</span>
                </div>
                <button type="button" class="modal-close" onclick="closePlanReviewModal()">&times;</button>
            </div>

            <!-- Full Plan Details View -->
            <div class="plan-grid-spec" id="modalPlanDetails">
                <div class="spec-item">
                    <span class="spec-label">ክፍል</span>
                    <span class="spec-value" id="mClass">—</span>
                </div>
                <div class="spec-item">
                    <span class="spec-label">መምህር</span>
                    <span class="spec-value" id="mTeacher">—</span>
                </div>
                <div class="spec-item">
                    <span class="spec-label">የትምህርት ዓይነት</span>
                    <span class="spec-value" id="mSubject">—</span>
                </div>
                <div class="spec-item">
                    <span class="spec-label">ወር እና ዓመተ ምሕረት</span>
                    <span class="spec-value" id="mMonthYear">—</span>
                </div>
                <div class="spec-item">
                    <span class="spec-label">ሳምንት</span>
                    <span class="spec-value" id="mWeek">—</span>
                </div>
                <div class="spec-item">
                    <span class="spec-label">ምዕራፍ</span>
                    <span class="spec-value" id="mChapter">—</span>
                </div>
                <div class="spec-item spec-full">
                    <span class="spec-label">ንዑስ ርዕስ</span>
                    <span class="spec-value" id="mSubTopic" style="font-size:15px; color:#111;">—</span>
                </div>
                <div class="spec-item spec-full">
                    <span class="spec-label">🎯 የትምህርቱ ዓላማ</span>
                    <p id="mObjective" style="font-size:13px; color:#374151; white-space:pre-wrap; margin-top:2px;">—</p>
                </div>
                <div class="spec-item spec-full">
                    <span class="spec-label">🛠️ የማስተማሪያ መርጃ መሣሪያዎች</span>
                    <p id="mMaterials" style="font-size:13px; color:#374151; white-space:pre-wrap; margin-top:2px;">—</p>
                </div>
                <div class="spec-item spec-full">
                    <span class="spec-label">📊 የተማሪዎች ምዘና</span>
                    <p id="mEvaluation" style="font-size:13px; color:#374151; white-space:pre-wrap; margin-top:2px;">—</p>
                </div>
                <div class="spec-item spec-full" id="mPaperBox" style="display:none;">
                    <span class="spec-label">📷 የተያያዘ የዕቅድ ወረቀት ፎቶ</span>
                    <div style="margin-top:6px;">
                        <a id="mPaperLink" href="" target="_blank">
                            <img id="mPaperImg" src="" style="max-width:100%; max-height:220px; border-radius:8px; border:1px solid #ddd; object-fit:contain;" alt="Paper Plan">
                        </a>
                    </div>
                </div>
            </div>

            <!-- Review Feedback & Action Form -->
            <form method="POST" id="modalReviewForm">
                <?php echo csrfField(); ?>
                <input type="hidden" name="plan_id" id="modalPlanId" value="0">

                <div style="margin-bottom: 16px;">
                    <label style="font-weight:700; font-size:13px; color:var(--brown-dark);">
                        💬 ለመምህሩ የሚሰጥ ገንቢ አስተያየት ወይም ማስተካከያ:
                    </label>
                    <textarea name="feedback" id="modalFeedbackText" class="feedback-textarea" placeholder="አስተያየት ካለዎት እዚህ ይጻፉ... (ለምሳሌ፦ 'በጣም ጥሩ ነው' ወይም 'የመርጃ መሳሪያው ቢጨመርበት')"></textarea>
                </div>

                <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px;">
                    <div style="display:flex; gap:8px; flex-wrap:wrap;">
                        <button type="submit" name="review_action" value="feedback" class="btn" style="background:#8B4513; color:white; padding:10px 22px;">
                            💬 አስተያየቱን ለመምህሩ ላክ
                        </button>
                    </div>
                    <button type="button" class="btn btn-reset" onclick="closePlanReviewModal()">✕ ዝጋ</button>
                </div>
            </form>
        </div>
    </div>

    <!-- JavaScript for Dynamic Cascading Filter and Modal -->
    <script>
        // Class-to-Teachers map injected from backend
        const classTeachersMap = <?php echo json_encode($class_teachers_map, JSON_UNESCAPED_UNICODE); ?>;
        const allTeachers = <?php echo json_encode($all_teachers, JSON_UNESCAPED_UNICODE); ?>;
        const currentTeacherId = <?php echo $f_teacher; ?>;
        const ethiopianMonthsMap = <?php echo json_encode($ethiopian_months, JSON_UNESCAPED_UNICODE); ?>;

        // When Grade / Class filter changes:
        function onClassFilterChange(selectEl) {
            const classId = selectEl.value;
            const teacherSelect = document.getElementById('teacherFilter');
            const teacherSubLabel = document.getElementById('teacherFilterSub');

            // Reset teacher options
            teacherSelect.innerHTML = '';
            
            let teachersToShow = [];
            if (!classId) {
                teachersToShow = allTeachers;
                teacherSubLabel.textContent = '';
                teacherSelect.innerHTML = '<option value="">-- ሁሉም መምህራን --</option>';
            } else if (classTeachersMap[classId] && classTeachersMap[classId].length > 0) {
                teachersToShow = classTeachersMap[classId];
                teacherSubLabel.textContent = '(የተመረጠው ክፍል መምህራን ብቻ)';
                teacherSelect.innerHTML = '<option value="">-- ሁሉም መምህራን (የዚህ ክፍል ብቻ) --</option>';
            } else {
                teacherSubLabel.textContent = '(ለዚህ ክፍል መምህር አልተመደበም)';
                teacherSelect.innerHTML = '<option value="">-- ለዚህ ክፍል ምንም መምህር የለም --</option>';
            }

            teachersToShow.forEach(t => {
                const opt = document.createElement('option');
                opt.value = t.id;
                opt.textContent = t.name;
                teacherSelect.appendChild(opt);
            });

            // Automatically submit form to load that grade's lesson plans!
            selectEl.form.submit();
        }

        // Open Plan Review Modal
        function openPlanReviewModal(plan) {
            document.getElementById('modalPlanId').value = plan.id;
            const topic = plan.sub_topic || plan.topic || 'የትምህርት ዕቅድ';
            document.getElementById('modalTopicTitle').textContent = topic + ' - ' + (plan.class_name || '');
            document.getElementById('mClass').textContent = plan.class_name || '—';
            document.getElementById('mTeacher').textContent = plan.teacher_name || '—';
            document.getElementById('mSubject').textContent = plan.subject || '—';
            
            const monthName = ethiopianMonthsMap[plan.ethiopian_month] || plan.ethiopian_month;
            document.getElementById('mMonthYear').textContent = monthName + ' ' + (plan.ethiopian_year || '') + ' ዓ.ም';
            document.getElementById('mWeek').textContent = plan.week_number || '—';
            document.getElementById('mChapter').textContent = plan.chapter || '—';
            document.getElementById('mSubTopic').textContent = plan.sub_topic || plan.topic || '—';
            document.getElementById('mObjective').textContent = plan.objective || '—';
            document.getElementById('mMaterials').textContent = plan.materials || '—';
            document.getElementById('mEvaluation').textContent = plan.evaluation || '—';
            document.getElementById('modalFeedbackText').value = plan.admin_feedback || '';

            const paperBox = document.getElementById('mPaperBox');
            if (plan.paper_photo_path) {
                document.getElementById('mPaperLink').href = plan.paper_photo_path;
                document.getElementById('mPaperImg').src = plan.paper_photo_path;
                paperBox.style.display = 'block';
            } else {
                paperBox.style.display = 'none';
            }

            document.getElementById('reviewModal').classList.add('show');
        }

        function closePlanReviewModal() {
            document.getElementById('reviewModal').classList.remove('show');
        }

        // Close modal on click outside box
        window.addEventListener('click', function(e) {
            const modal = document.getElementById('reviewModal');
            if (e.target === modal) {
                closePlanReviewModal();
            }
        });

        // Export table to Excel (.xls)
        function exportPlansToExcel() {
            const table = document.getElementById('plansTable');
            if (!table) return;

            let excelContent = `
                <html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40">
                <head>
                    <meta charset="UTF-8">
                    <style>
                        body { font-family:'Segoe UI', sans-serif; }
                        table { border-collapse: collapse; width: 100%; }
                        th { background: #8B4513; color: white; padding: 8px; border: 1px solid #ccc; }
                        td { padding: 8px; border: 1px solid #ccc; }
                    </style>
                </head>
                <body>
                    <h2>አጸደ ትጉሃን ሰንበት ትምህርት ቤት - የትምህርት ዕቅድ ግምገማ ሪፖርት</h2>
                    <table>
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>ክፍል</th>
                                <th>መምህር</th>
                                <th>ወቅት</th>
                                <th>ሳምንት</th>
                                <th>ምዕራፍ</th>
                                <th>ንዑስ ርዕስ</th>
                                <th>የትምህርቱ ዓላማ</th>
                                <th>መርጃ መሳሪያ</th>
                                <th>ምዘና</th>
                                <th>ሁኔታ</th>
                            </tr>
                        </thead>
                        <tbody>
            `;

            const rows = table.querySelectorAll('tbody tr');
            rows.forEach((row, idx) => {
                const cells = row.querySelectorAll('td');
                if (cells.length < 8) return;
                excelContent += '<tr>';
                excelContent += `<td>${idx + 1}</td>`;
                excelContent += `<td>${cells[1].querySelector('.badge-class') ? cells[1].querySelector('.badge-class').innerText.replace('📚', '').trim() : ''}</td>`;
                excelContent += `<td>${cells[1].querySelector('.teacher-cell') ? cells[1].querySelector('.teacher-cell').innerText.replace('👨‍🏫', '').trim() : ''}</td>`;
                excelContent += `<td>${cells[2].innerText.replace(/\n/g, ' ').trim()}</td>`;
                excelContent += `<td>${cells[2].querySelector('div:last-child') ? cells[2].querySelector('div:last-child').innerText.trim() : ''}</td>`;
                excelContent += `<td>${cells[3].querySelector('div:first-child') ? cells[3].querySelector('div:first-child').innerText.trim() : ''}</td>`;
                excelContent += `<td>${cells[3].querySelector('.topic-cell') ? cells[3].querySelector('.topic-cell').innerText.trim() : ''}</td>`;
                excelContent += `<td>${cells[4].innerText.trim()}</td>`;
                excelContent += `<td>${cells[5].innerText.replace(/\n/g, ' ').trim()}</td>`;
                excelContent += `<td>${cells[5].innerText.replace(/\n/g, ' ').trim()}</td>`;
                excelContent += `<td>${cells[7].innerText.trim()}</td>`;
                excelContent += '</tr>';
            });

            excelContent += `
                        </tbody>
                    </table>
                </body>
                </html>
            `;

            const blob = new Blob([excelContent], { type: 'application/vnd.ms-excel' });
            const link = document.createElement('a');
            link.href = URL.createObjectURL(blob);
            link.download = `lesson_plans_review_${Date.now()}.xls`;
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
            URL.revokeObjectURL(link.href);
        }
    </script>
</body>
</html>
<?php mysqli_close($conn); ?>
