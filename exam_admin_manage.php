<?php
require_once 'db.php';
requireAdmin();
ensureExamTablesExist($conn);

$admin_id = intval($_SESSION['user_id'] ?? 0);
$message = '';
$error = '';

// Active Year & Semesters
$active_year = 2018;
$year_row = dbFetchOne($conn, "SELECT ethiopian_year FROM academic_years WHERE status = 'active' LIMIT 1");
if ($year_row && !empty($year_row['ethiopian_year'])) {
    $active_year = intval($year_row['ethiopian_year']);
}

// Handle Admin Actions (Approve, Request Revision, Delete)
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = "የደህንነት ማረጋገጫ አልተሳካም!";
    } else {
        $action = $_POST['action'] ?? '';
        $exam_id = intval($_POST['exam_id'] ?? 0);

        if ($exam_id > 0) {
            if ($action === 'approve') {
                dbExecute($conn, 
                    "UPDATE exams SET status = 'approved', reviewed_by = ?, reviewed_at = NOW() WHERE id = ?", 
                    "ii", [$admin_id, $exam_id]
                );
                $message = "ፈተናው በተሳካ ሁኔታ ጸድቋል! አሁን ለህትመት ዝግጁ ነው። ✅";
            } elseif ($action === 'request_revision') {
                $feedback = trim((string)($_POST['feedback'] ?? ''));
                dbExecute($conn, 
                    "UPDATE exams SET status = 'revision_requested', admin_feedback = ?, reviewed_by = ?, reviewed_at = NOW() WHERE id = ?", 
                    "sii", [$feedback, $admin_id, $exam_id]
                );
                $message = "የማስተካከያ ጥያቄው ለመምህሩ ተልኳል! 💬";
            } elseif ($action === 'revert_draft') {
                dbExecute($conn, 
                    "UPDATE exams SET status = 'draft' WHERE id = ?", 
                    "i", [$exam_id]
                );
                $message = "ፈተናው ወደ ረቂቅነት ተመልሷል!";
            } elseif ($action === 'delete') {
                dbExecute($conn, "DELETE FROM exam_questions WHERE exam_id = ?", "i", [$exam_id]);
                dbExecute($conn, "DELETE FROM exams WHERE id = ?", "i", [$exam_id]);
                $message = "ፈተናው በተሳካ ሁኔታ ተሰርዟል!";
            }
        }
    }
}

// Filters
$filter_class = intval($_GET['class_id'] ?? 0);
$filter_status = trim((string)($_GET['status'] ?? ''));
$filter_sem = intval($_GET['semester_id'] ?? 0);
$filter_year = intval($_GET['year'] ?? 0);
$search = trim((string)($_GET['search'] ?? ''));

$where = ["1=1"];
$params = [];
$types = "";

if ($filter_class > 0) {
    $where[] = "e.class_id = ?";
    $params[] = $filter_class;
    $types .= "i";
}
if (!empty($filter_status)) {
    $where[] = "e.status = ?";
    $params[] = $filter_status;
    $types .= "s";
}
if ($filter_sem > 0) {
    $where[] = "e.semester_id = ?";
    $params[] = $filter_sem;
    $types .= "i";
}
if ($filter_year > 0) {
    $where[] = "e.ethiopian_year = ?";
    $params[] = $filter_year;
    $types .= "i";
}
if (!empty($search)) {
    $where[] = "(e.title LIKE ? OR e.subject_name LIKE ? OR u.name LIKE ?)";
    $s_term = "%$search%";
    $params[] = $s_term;
    $params[] = $s_term;
    $params[] = $s_term;
    $types .= "sss";
}

$where_clause = implode(" AND ", $where);
$exams_query = "SELECT e.*, COALESCE(c.name, 'ያልታወቀ ክፍል') as class_name, COALESCE(u.name, 'ያልታወቀ መምህር') as teacher_name, 
                (SELECT COUNT(*) FROM exam_questions eq WHERE eq.exam_id = e.id) as question_count 
                FROM exams e 
                LEFT JOIN classes c ON e.class_id = c.id 
                LEFT JOIN users u ON e.teacher_id = u.id 
                WHERE $where_clause 
                ORDER BY e.created_at DESC";

$exams = !empty($params) ? dbFetchAll($conn, $exams_query, $types, $params) : dbFetchAll($conn, $exams_query);

// All classes for filter dropdown
$all_classes = dbFetchAll($conn, "SELECT id, name FROM classes ORDER BY id ASC");

// Quick Stats
$stats_counts = [
    'total' => 0,
    'submitted' => 0,
    'approved' => 0,
    'draft' => 0,
    'revision' => 0,
];
$counts_raw = dbFetchAll($conn, "SELECT status, COUNT(*) as cnt FROM exams GROUP BY status");
foreach ($counts_raw as $cr) {
    $st = (string)$cr['status'];
    $cnt = intval($cr['cnt']);
    $stats_counts['total'] += $cnt;
    if (isset($stats_counts[$st])) $stats_counts[$st] = $cnt;
    if ($st === 'revision_requested') $stats_counts['revision'] = $cnt;
}

$csrf_token = generateCsrfToken();
$nav_active = 'exam_admin_manage';
$page_title = "የትምህርት ክፍል የፈተና ግምገማና ማተሚያ";
?>
<!DOCTYPE html>
<html lang="am">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - አጸደ ትጉሃን</title>
    <link rel="icon" type="image/png" href="images/icon.png">
    <?php require_once 'pwa_head.php'; ?>
    <style>
        :root {
            --primary: #1e3a8a;
            --primary-light: #3b82f6;
            --secondary: #8B4513;
            --accent: #10b981;
            --danger: #ef4444;
            --bg-color: #f8fafc;
            --card-bg: #ffffff;
            --text-main: #1e293b;
            --text-muted: #64748b;
            --border: #e2e8f0;
            --radius: 14px;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: var(--bg-color);
            color: var(--text-main);
            margin: 0;
            padding: 0;
            padding-bottom: 90px;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 16px;
        }

        .header-card {
            background: linear-gradient(135deg, #1e3a8a 0%, #1e40af 100%);
            color: #ffffff;
            padding: 22px 20px;
            border-radius: var(--radius);
            box-shadow: 0 6px 18px rgba(30, 58, 138, 0.2);
            margin-bottom: 22px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 16px;
        }

        .header-title h1 {
            margin: 0;
            font-size: 1.45rem;
            font-weight: 800;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .header-title p {
            margin: 6px 0 0 0;
            font-size: 0.92rem;
            opacity: 0.92;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(170px, 1fr));
            gap: 14px;
            margin-bottom: 20px;
        }

        .stat-card {
            background: var(--card-bg);
            border-radius: var(--radius);
            padding: 16px;
            border: 1.5px solid var(--border);
            text-align: center;
            box-shadow: 0 2px 6px rgba(0,0,0,0.03);
            text-decoration: none;
            color: inherit;
            display: block;
            transition: all 0.2s;
        }
        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(0,0,0,0.08);
        }

        .stat-num {
            font-size: 1.8rem;
            font-weight: 900;
            margin-bottom: 4px;
        }
        .stat-label {
            font-size: 0.85rem;
            font-weight: 700;
            color: var(--text-muted);
        }

        .card {
            background: var(--card-bg);
            border-radius: var(--radius);
            padding: 22px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.04);
            border: 1.5px solid var(--border);
            margin-bottom: 24px;
        }

        .card-header {
            border-bottom: 1.5px solid var(--border);
            padding-bottom: 14px;
            margin-bottom: 18px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 10px;
        }

        .card-header h2 {
            margin: 0;
            font-size: 1.25rem;
            color: #1e3a8a;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .filter-form {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            align-items: center;
            margin-bottom: 16px;
            background: #f1f5f9;
            padding: 12px;
            border-radius: 10px;
        }

        .filter-form select, .filter-form input {
            padding: 8px 12px;
            border: 1.5px solid var(--border);
            border-radius: 8px;
            font-size: 0.9rem;
            background: #ffffff;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            padding: 8px 14px;
            font-size: 0.9rem;
            font-weight: 700;
            border-radius: 8px;
            cursor: pointer;
            border: none;
            transition: all 0.2s ease;
            text-decoration: none;
        }

        .btn:hover { transform: translateY(-1px); }
        .btn-primary { background-color: #2563eb; color: #ffffff; }
        .btn-primary:hover { background-color: #1d4ed8; }
        .btn-success { background-color: #10b981; color: #ffffff; }
        .btn-success:hover { background-color: #059669; }
        .btn-secondary { background-color: #64748b; color: #ffffff; }
        .btn-secondary:hover { background-color: #475569; }
        .btn-brown { background-color: #8B4513; color: #ffffff; }
        .btn-brown:hover { background-color: #6d360e; }
        .btn-outline { background: #ffffff; border: 1.5px solid var(--border); color: var(--text-main); }
        .btn-outline:hover { background: #f1f5f9; }
        .btn-danger { background-color: #ef4444; color: #ffffff; }
        .btn-danger:hover { background-color: #dc2626; }
        .btn-sm { padding: 5px 10px; font-size: 0.82rem; }

        .badge-status {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 0.82rem;
            font-weight: 700;
        }
        .badge-draft { background: #fef3c7; color: #92400e; }
        .badge-submitted { background: #dbeafe; color: #1e40af; }
        .badge-approved { background: #dcfce7; color: #166534; }
        .badge-revision_requested { background: #fee2e2; color: #991b1b; }

        .exam-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.92rem;
        }

        .exam-table th, .exam-table td {
            padding: 12px 14px;
            text-align: left;
            border-bottom: 1px solid var(--border);
        }

        .exam-table th {
            background-color: #f1f5f9;
            font-weight: 800;
            color: #334155;
        }

        .exam-table tr:hover {
            background-color: #f8fafc;
        }

        .alert {
            padding: 14px 18px;
            border-radius: 10px;
            margin-bottom: 20px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .alert-success { background: #dcfce7; color: #166534; border: 1.5px solid #bbf7d0; }
        .alert-error { background: #fee2e2; color: #991b1b; border: 1.5px solid #fecaca; }

        .modal-overlay {
            display: none;
            position: fixed;
            top: 0; left: 0; right: 0; bottom: 0;
            background: rgba(0,0,0,0.55);
            z-index: 9999;
            align-items: center;
            justify-content: center;
            padding: 16px;
        }
        @media (max-width: 768px) {
            .stats-grid { grid-template-columns: repeat(2, 1fr); gap: 10px; }
            .header-card { padding: 16px 14px; flex-direction: column; align-items: flex-start; }
            .header-card > div { width: 100%; }
            .filter-form { flex-direction: column; align-items: stretch; }
            .filter-form > div { width: 100%; }
            .filter-form select, .filter-form input { width: 100%; box-sizing: border-box; }
            .card { padding: 16px 12px; }
            .exam-table th, .exam-table td { padding: 10px 8px; font-size: 0.84rem; }
        }

        .modal-box {
            background: #ffffff;
            border-radius: var(--radius);
            max-width: 580px;
            width: 100%;
            padding: 24px;
            box-shadow: 0 12px 30px rgba(0,0,0,0.22);
        }

        /* ── Dark Mode Enhancements ── */
        html.dark-mode .card,
        body.dark-mode .card,
        [data-theme="dark"] .card,
        html.dark-mode .stat-card,
        body.dark-mode .stat-card,
        [data-theme="dark"] .stat-card {
            background: #1E293B !important;
            border-color: #334155 !important;
            color: #F1F5F9 !important;
        }

        html.dark-mode .card-header,
        body.dark-mode .card-header,
        [data-theme="dark"] .card-header {
            border-color: #334155 !important;
        }

        html.dark-mode .card-header h2,
        body.dark-mode .card-header h2,
        [data-theme="dark"] .card-header h2 {
            color: #FCD34D !important;
        }

        html.dark-mode .filter-form,
        body.dark-mode .filter-form,
        [data-theme="dark"] .filter-form {
            background: #0F172A !important;
            border: 1px solid #334155 !important;
        }

        html.dark-mode .filter-form select,
        html.dark-mode .filter-form input,
        body.dark-mode .filter-form select,
        body.dark-mode .filter-form input,
        [data-theme="dark"] .filter-form select,
        [data-theme="dark"] .filter-form input {
            background: #1E293B !important;
            border-color: #334155 !important;
            color: #F1F5F9 !important;
        }

        html.dark-mode .stat-label,
        body.dark-mode .stat-label,
        [data-theme="dark"] .stat-label {
            color: #94A3B8 !important;
        }

        html.dark-mode .stat-num[style*="color:#1e3a8a"],
        body.dark-mode .stat-num[style*="color:#1e3a8a"],
        [data-theme="dark"] .stat-num[style*="color:#1e3a8a"] {
            color: #93C5FD !important;
        }

        html.dark-mode .modal-box,
        body.dark-mode .modal-box,
        [data-theme="dark"] .modal-box {
            background: #1E293B !important;
            color: #F1F5F9 !important;
            border: 1px solid #334155 !important;
        }

        html.dark-mode .btn-outline,
        body.dark-mode .btn-outline,
        [data-theme="dark"] .btn-outline {
            background: #1E293B !important;
            color: #F1F5F9 !important;
            border-color: #334155 !important;
        }
        html.dark-mode .btn-outline:hover,
        body.dark-mode .btn-outline:hover,
        [data-theme="dark"] .btn-outline:hover {
            background: #334155 !important;
        }

        html.dark-mode .badge-draft { background: #78350f !important; color: #fde68a !important; }
        html.dark-mode .badge-submitted { background: #1e3a8a !important; color: #bfdbfe !important; }
        html.dark-mode .badge-approved { background: #064e3b !important; color: #a7f3d0 !important; }
        html.dark-mode .badge-revision_requested { background: #7f1d1d !important; color: #fecaca !important; }
    </style>
</head>
<body>
    <?php require_once 'mobile_nav.php'; ?>

    <div class="container">
        <!-- Top Banner -->
        <div class="header-card">
            <div class="header-title">
                <h1>📑 የፈተናዎች ግምገማ፣ ማጽደቂያና ማተሚያ</h1>
                <p>መምህራን የላኳቸውን ፈተናዎች መገምገም፣ ማጽደቅ፣ ማስተካከያ መስጠት እና በቀጥታ ማተም</p>
            </div>
            <div>
                <a href="exam_author.php?action=new" class="btn btn-brown">➕ አዲስ ፈተና አዘጋጅ</a>
            </div>
        </div>

        <?php if (!empty($message)): ?>
            <div class="alert alert-success">
                <span>✅</span>
                <span><?php echo htmlspecialchars((string)$message); ?></span>
            </div>
        <?php endif; ?>

        <?php if (!empty($error)): ?>
            <div class="alert alert-error">
                <span>⚠️</span>
                <span><?php echo htmlspecialchars((string)$error); ?></span>
            </div>
        <?php endif; ?>

        <!-- Stats Overview -->
        <div class="stats-grid">
            <a href="exam_admin_manage.php" class="stat-card">
                <div class="stat-num" style="color:#1e3a8a;"><?php echo $stats_counts['total']; ?></div>
                <div class="stat-label">ጠቅላላ ፈተናዎች</div>
            </a>
            <a href="exam_admin_manage.php?status=submitted" class="stat-card" style="border-top:3.5px solid #3b82f6;">
                <div class="stat-num" style="color:#2563eb;"><?php echo $stats_counts['submitted']; ?></div>
                <div class="stat-label">የተላኩ (ግምገማ የሚጠብቁ) 🚀</div>
            </a>
            <a href="exam_admin_manage.php?status=approved" class="stat-card" style="border-top:3.5px solid #10b981;">
                <div class="stat-num" style="color:#10b981;"><?php echo $stats_counts['approved']; ?></div>
                <div class="stat-label">የጸደቁ (ለህትመት ዝግጁ) ✅</div>
            </a>
            <a href="exam_admin_manage.php?status=draft" class="stat-card" style="border-top:3.5px solid #f59e0b;">
                <div class="stat-num" style="color:#d97706;"><?php echo $stats_counts['draft']; ?></div>
                <div class="stat-label">ረቂቆች (Draft)</div>
            </a>
            <a href="exam_admin_manage.php?status=revision_requested" class="stat-card" style="border-top:3.5px solid #ef4444;">
                <div class="stat-num" style="color:#dc2626;"><?php echo $stats_counts['revision']; ?></div>
                <div class="stat-label">ማስተካከያ የተጠየቀበት ⚠️</div>
            </a>
        </div>

        <!-- Filter Bar -->
        <div class="card">
            <form method="GET" action="exam_admin_manage.php" class="filter-form">
                <div>
                    <label style="font-size:0.82rem; font-weight:700; display:block;">ክፍል፦</label>
                    <select name="class_id">
                        <option value="">-- ሁሉም ክፍሎች --</option>
                        <?php foreach ($all_classes as $ac): ?>
                            <option value="<?php echo $ac['id']; ?>" <?php echo ($filter_class == $ac['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars((string)$ac['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label style="font-size:0.82rem; font-weight:700; display:block;">ሁኔታ፦</label>
                    <select name="status">
                        <option value="">-- ሁሉም ሁኔታዎች --</option>
                        <option value="submitted" <?php echo ($filter_status === 'submitted') ? 'selected' : ''; ?>>የተላኩ (Submitted)</option>
                        <option value="approved" <?php echo ($filter_status === 'approved') ? 'selected' : ''; ?>>የጸደቁ (Approved)</option>
                        <option value="draft" <?php echo ($filter_status === 'draft') ? 'selected' : ''; ?>>ረቂቅ (Draft)</option>
                        <option value="revision_requested" <?php echo ($filter_status === 'revision_requested') ? 'selected' : ''; ?>>ማስተካከያ (Revision)</option>
                    </select>
                </div>

                <div>
                    <label style="font-size:0.82rem; font-weight:700; display:block;">ሰሚስተር፦</label>
                    <select name="semester_id">
                        <option value="">-- ሁሉም --</option>
                        <option value="1" <?php echo ($filter_sem == 1) ? 'selected' : ''; ?>>1ኛ ሰሚስተር</option>
                        <option value="2" <?php echo ($filter_sem == 2) ? 'selected' : ''; ?>>2ኛ ሰሚስተር</option>
                    </select>
                </div>

                <div>
                    <label style="font-size:0.82rem; font-weight:700; display:block;">ፍለጋ፦</label>
                    <input type="text" name="search" value="<?php echo htmlspecialchars((string)$search); ?>" placeholder="ርዕስ፣ ትምህርት ወይም መምህር...">
                </div>

                <div style="align-self:flex-end;">
                    <button type="submit" class="btn btn-primary">🔍 አጣራ</button>
                    <a href="exam_admin_manage.php" class="btn btn-outline">ዳግም ጀምር</a>
                </div>
            </form>

            <!-- Table of Exams -->
            <?php if (empty($exams)): ?>
                <div style="text-align:center; padding:40px 20px; color:var(--text-muted);">
                    <p style="font-size:2.8rem; margin:0 0 10px 0;">📑</p>
                    <h3 style="margin:0 0 8px 0; color:var(--text-main);">ምንም ፈተና አልተገኘም</h3>
                    <p>በተመረጠው መስፈርት መሰረት የቀረበ ፈተና የለም።</p>
                </div>
            <?php else: ?>
                <div style="overflow-x:auto;">
                    <table class="exam-table">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>የፈተናው ሙሉ ርዕስ</th>
                                <th>ክፍልና ትምህርት</th>
                                <th>አዘጋጅ መምህር</th>
                                <th>ጥያቄዎች</th>
                                <th>ነጥብ</th>
                                <th>ሁኔታ</th>
                                <th style="text-align:right;">የአድሚን እርምጃዎች</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($exams as $i => $ex): ?>
                                <tr>
                                    <td><?php echo ($i + 1); ?></td>
                                    <td>
                                        <strong style="color:#1e3a8a;"><?php echo htmlspecialchars((string)$ex['title']); ?></strong>
                                        <div style="font-size:0.8rem; color:var(--text-muted); margin-top:2px;">
                                            ዓ.ም፦ <?php echo htmlspecialchars((string)($ex['ethiopian_year'] ?? '')); ?> • ጊዜ፦ <?php echo htmlspecialchars((string)($ex['duration_minutes'] ?? '60')); ?> ደቂቃ • 
                                            <?php echo (($ex['semester_id'] ?? 1) == 2) ? '2ኛ ሰሚስተር' : '1ኛ ሰሚስተር'; ?>
                                        </div>
                                    </td>
                                    <td>
                                        <span style="font-weight:700;"><?php echo htmlspecialchars((string)$ex['class_name']); ?></span><br>
                                        <small style="color:var(--text-muted);"><?php echo htmlspecialchars((string)$ex['subject_name']); ?></small>
                                    </td>
                                    <td>
                                        <span style="font-weight:700; color:#334155;"><?php echo htmlspecialchars((string)$ex['teacher_name']); ?></span>
                                    </td>
                                    <td>
                                        <span class="badge-status" style="background:#e0f2fe; color:#0369a1;">
                                            <?php echo intval($ex['question_count'] ?? 0); ?> ጥያቄዎች
                                        </span>
                                    </td>
                                    <td><strong><?php echo floatval($ex['total_marks'] ?? 0); ?></strong></td>
                                    <td>
                                        <span class="badge-status badge-<?php echo htmlspecialchars((string)$ex['status']); ?>">
                                            <?php 
                                                echo ($ex['status'] === 'draft') ? 'ረቂቅ (Draft)' : 
                                                    (($ex['status'] === 'submitted') ? 'የተላከ 🚀' : 
                                                    (($ex['status'] === 'approved') ? 'የጸደቀ ✅' : 'ማስተካከያ ⚠️'));
                                            ?>
                                        </span>
                                    </td>
                                    <td style="text-align:right; white-space:nowrap;">
                                        <!-- Print Button -->
                                        <a href="exam_print.php?id=<?php echo $ex['id']; ?>" target="_blank" class="btn btn-sm btn-brown" title="ይፋዊ ፈተናውን አትም">
                                            🖨️ አትም
                                        </a>

                                        <!-- Answer Key Print -->
                                        <a href="exam_print.php?id=<?php echo $ex['id']; ?>&key=1" target="_blank" class="btn btn-sm btn-outline" title="የመልስ ቁልፍ">
                                            🔑 ቁልፍ
                                        </a>

                                        <!-- Edit in authoring tool -->
                                        <a href="exam_author.php?action=edit&id=<?php echo $ex['id']; ?>" class="btn btn-sm btn-primary" title="አስተካክል / ጥያቄዎቹን እይ">
                                            ✏️ አርም
                                        </a>

                                        <!-- Approve / Revision Actions -->
                                        <?php if ($ex['status'] !== 'approved'): ?>
                                            <form method="POST" action="exam_admin_manage.php" style="display:inline;" onsubmit="return confirm('ይህ ፈተና ይጽደቅ?');">
                                                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                                <input type="hidden" name="exam_id" value="<?php echo $ex['id']; ?>">
                                                <input type="hidden" name="action" value="approve">
                                                <button type="submit" class="btn btn-sm btn-success" title="አጽድቅ">
                                                    ✅ አጽድቅ
                                                </button>
                                            </form>

                                            <button type="button" class="btn btn-sm btn-secondary" onclick="openRevisionModal(<?php echo $ex['id']; ?>, '<?php echo htmlspecialchars(addslashes((string)$ex['title'])); ?>')" title="ማስተካከያ ጠይቅ">
                                                💬 ማስታወሻ
                                            </button>
                                        <?php else: ?>
                                            <form method="POST" action="exam_admin_manage.php" style="display:inline;" onsubmit="return confirm('ይህ ፈተና ወደ ረቂቅነት ይመለስ?');">
                                                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                                <input type="hidden" name="exam_id" value="<?php echo $ex['id']; ?>">
                                                <input type="hidden" name="action" value="revert_draft">
                                                <button type="submit" class="btn btn-sm btn-outline" title="ወደ ረቂቅ መልስ">
                                                    ↩️
                                                </button>
                                            </form>
                                        <?php endif; ?>

                                        <!-- Delete -->
                                        <form method="POST" action="exam_admin_manage.php" style="display:inline;" onsubmit="return confirm('እርግጠኛ ነዎት ይህ ፈተና እና ሁሉም ጥያቄዎቹ ይሰረዙ?');">
                                            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                            <input type="hidden" name="exam_id" value="<?php echo $ex['id']; ?>">
                                            <input type="hidden" name="action" value="delete">
                                            <button type="submit" class="btn btn-sm btn-danger" title="ሰርዝ">
                                                🗑️
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- REVISION FEEDBACK MODAL -->
    <div id="revisionModal" class="modal-overlay" onclick="if(event.target===this) closeRevisionModal();">
        <div class="modal-box">
            <h3 style="margin-top:0; color:#1e3a8a;">💬 የማስተካከያ ማስታወሻ ለመምህሩ ይላኩ</h3>
            <p id="modalExamTitle" style="font-weight:700; font-size:0.92rem; color:var(--text-muted);"></p>
            
            <form method="POST" action="exam_admin_manage.php">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                <input type="hidden" name="action" value="request_revision">
                <input type="hidden" name="exam_id" id="modalExamId" value="0">

                <div style="margin-bottom:16px;">
                    <label style="font-weight:700; font-size:0.9rem; display:block; margin-bottom:6px;">ምን ማስተካከያ እንዲደረግ ይፈልጋሉ?</label>
                    <textarea name="feedback" id="modalFeedback" rows="4" class="form-control" style="width:100%; box-sizing:border-box; padding:10px;" placeholder="ለምሳሌ፦ እባክዎ ጥያቄ ቁጥር 3 ላይ አማራጮቹን ያስተካክሉ..." required></textarea>
                </div>

                <div style="display:flex; justify-content:flex-end; gap:10px;">
                    <button type="button" class="btn btn-outline" onclick="closeRevisionModal()">ይቅር</button>
                    <button type="submit" class="btn btn-danger">🚀 ማስታወሻውን ላክ</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openRevisionModal(id, title) {
            document.getElementById('modalExamId').value = id;
            document.getElementById('modalExamTitle').innerText = title;
            document.getElementById('modalFeedback').value = '';
            document.getElementById('revisionModal').style.display = 'flex';
        }

        function closeRevisionModal() {
            document.getElementById('revisionModal').style.display = 'none';
        }
    </script>
</body>
</html>
