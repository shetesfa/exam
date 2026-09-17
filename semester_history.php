<?php
require_once 'db.php';
requireAdmin();

$semester_id = isset($_SESSION['view_semester_id']) ? intval($_SESSION['view_semester_id']) : (isset($_GET['id']) ? intval($_GET['id']) : 0);

if (!$semester_id) {
    header("Location: semester.php");
    exit();
}

// Get semester info
$semester = dbFetchOne($conn, "SELECT * FROM semesters WHERE id = ?", "i", [$semester_id]);

if (!$semester) {
    header("Location: semester.php");
    exit();
}

// Get teacher assignments for this semester
$assignments = dbQuery(
    $conn,
    "SELECT tc.*, u.name as teacher_name, c.name as class_name
     FROM teacher_class tc
     JOIN users u ON tc.teacher_id = u.id
     JOIN classes c ON tc.class_id = c.id
     WHERE tc.semester_id = ?
     ORDER BY c.name",
    "i",
    [$semester_id]
);

$assignments_list = [];
if ($assignments) {
    while ($row = mysqli_fetch_assoc($assignments)) {
        $assignments_list[] = $row;
    }
}

// Get marks for this semester
$marks_list = dbFetchAll(
    $conn,
    "SELECT s.name as student_name, c.name as class_name,
            m.assignment, m.mid, m.final, m.total
     FROM marks m
     JOIN students s ON m.student_id = s.id
     JOIN classes c ON m.class_id = c.id
     WHERE m.semester_id = ?
     ORDER BY c.name, s.name",
    "i",
    [$semester_id]
);

// Group marks by class
$class_marks = [];
foreach ($marks_list as $mark) {
    $class_marks[$mark['class_name']][] = $mark;
}

$nav_active = 'semester';
?>
<!DOCTYPE html>
<html lang="am">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ሴሚስተር ታሪክ - <?php echo htmlspecialchars($semester['name']); ?> | አጸደ ትጉሃን</title>
    <?php include 'pwa_head.php'; ?>
    <style>
        :root {
            --brown-dark: #8B4513;
            --brown-medium: #A52A2A;
            --gold-primary: #FFD700;
            --gold-dark: #DAA520;
            --gold-pale: #FFF8DC;
            --bg-light: #FAF9F6;
            --card-bg: #FFFFFF;
            --text-main: #1F2937;
            --text-muted: #6B7280;
            --border-color: #E5E7EB;
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


        [data-theme="dark"] {
            --bg-light: #111827;
            --card-bg: #1F2937;
            --text-main: #F9FAFB;
            --text-muted: #9CA3AF;
            --border-color: #374151;
            --gold-pale: rgba(218, 165, 32, 0.15);
        }

        body {
            background: var(--bg-light);
            color: var(--text-main);
            font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
            margin: 0;
            padding: 0;
            min-height: 100vh;
        }

        .main-container {
            max-width: 1300px;
            margin: 15px auto;
            padding: 0 12px 30px;
        }

        .back-nav {
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 12px;
        }

        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: var(--brown-dark);
            text-decoration: none;
            font-weight: 600;
            font-size: 14px;
            padding: 8px 16px;
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: 10px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
            transition: all 0.2s ease;
        }

        [data-theme="dark"] .back-link {
            color: var(--gold-primary);
        }

        .back-link:hover {
            border-color: var(--gold-dark);
            transform: translateX(-3px);
        }

        /* Semester Hero Banner */
        .semester-banner {
            background: var(--card-bg);
            border-radius: 18px;
            padding: 28px;
            border: 1px solid var(--border-color);
            box-shadow: 0 4px 15px rgba(139, 69, 19, 0.06);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 20px;
            margin-bottom: 25px;
            position: relative;
            overflow: hidden;
        }

        .semester-banner::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--brown-dark), var(--gold-primary), var(--brown-dark));
        }

        .banner-left {
            display: flex;
            align-items: center;
            gap: 18px;
        }

        .banner-icon {
            width: 58px;
            height: 58px;
            border-radius: 16px;
            background: linear-gradient(135deg, rgba(139,69,19,0.12), rgba(218,165,32,0.2));
            color: var(--brown-dark);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 28px;
            border: 1px solid rgba(218,165,32,0.3);
            flex-shrink: 0;
        }

        [data-theme="dark"] .banner-icon {
            color: var(--gold-primary);
        }

        .banner-title {
            font-size: 24px;
            font-weight: 800;
            color: var(--text-main);
            margin: 0 0 6px;
        }

        .banner-subtitle {
            display: flex;
            align-items: center;
            gap: 15px;
            font-size: 13.5px;
            color: var(--text-muted);
            flex-wrap: wrap;
        }

        .status-pill {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 700;
            letter-spacing: 0.3px;
        }

        .status-active {
            background: rgba(16, 185, 129, 0.12);
            color: #059669;
            border: 1px solid rgba(16, 185, 129, 0.3);
        }

        .status-closed {
            background: rgba(239, 68, 68, 0.1);
            color: #DC2626;
            border: 1px solid rgba(239, 68, 68, 0.25);
        }

        .banner-actions {
            display: flex;
            gap: 10px;
            align-items: center;
        }

        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 16px;
            margin-bottom: 25px;
        }

        .stat-card {
            background: var(--card-bg);
            border-radius: 14px;
            padding: 18px 20px;
            border: 1px solid var(--border-color);
            display: flex;
            align-items: center;
            gap: 16px;
            box-shadow: 0 2px 6px rgba(0,0,0,0.03);
        }

        .stat-icon {
            width: 46px;
            height: 46px;
            border-radius: 12px;
            background: var(--gold-pale);
            color: var(--brown-dark);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 22px;
            flex-shrink: 0;
        }

        [data-theme="dark"] .stat-icon {
            color: var(--gold-primary);
        }

        .stat-num {
            font-size: 22px;
            font-weight: 800;
            color: var(--text-main);
            line-height: 1.2;
        }

        .stat-label {
            font-size: 12.5px;
            color: var(--text-muted);
            margin-top: 3px;
            font-weight: 500;
        }

        /* Buttons */
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 18px;
            border-radius: 10px;
            font-weight: 700;
            font-size: 13.5px;
            text-decoration: none;
            cursor: pointer;
            border: none;
            transition: all 0.2s ease;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--brown-dark), var(--brown-medium));
            color: #ffffff;
            box-shadow: 0 2px 8px rgba(139, 69, 19, 0.25);
        }

        .btn-primary:hover {
            filter: brightness(1.1);
            transform: translateY(-1px);
        }

        .btn-gold {
            background: linear-gradient(135deg, var(--gold-primary), var(--gold-dark));
            color: #8B4513;
            font-weight: 800;
            box-shadow: 0 2px 8px rgba(218, 165, 32, 0.3);
        }

        .btn-gold:hover {
            filter: brightness(1.05);
            transform: translateY(-1px);
        }

        /* Section Cards */
        .section-card {
            background: var(--card-bg);
            border-radius: 16px;
            padding: 24px;
            border: 1px solid var(--border-color);
            margin-bottom: 25px;
            box-shadow: 0 3px 12px rgba(0,0,0,0.03);
        }

        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 18px;
            padding-bottom: 12px;
            border-bottom: 1px solid var(--border-color);
        }

        .section-title {
            font-size: 17px;
            font-weight: 800;
            color: var(--brown-dark);
            display: flex;
            align-items: center;
            gap: 10px;
            margin: 0;
        }

        [data-theme="dark"] .section-title {
            color: var(--gold-primary);
        }

        .badge-count {
            background: var(--gold-pale);
            color: var(--brown-dark);
            padding: 3px 10px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 700;
        }

        [data-theme="dark"] .badge-count {
            color: var(--gold-primary);
        }

        /* Tables */
        .table-responsive {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
            border-radius: 12px;
            border: 1px solid var(--border-color);
        }

        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13.5px;
        }

        th {
            background: rgba(139, 69, 19, 0.05);
            color: var(--brown-dark);
            font-weight: 700;
            padding: 12px 16px;
            text-align: left;
            border-bottom: 1px solid var(--border-color);
            white-space: nowrap;
        }

        [data-theme="dark"] th {
            background: rgba(218, 165, 32, 0.1);
            color: var(--gold-primary);
        }

        td {
            padding: 12px 16px;
            border-bottom: 1px solid var(--border-color);
            color: var(--text-main);
        }

        tr:last-child td {
            border-bottom: none;
        }

        tr:hover td {
            background: rgba(255, 215, 0, 0.04);
        }

        .class-heading {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin: 22px 0 10px;
            padding: 10px 14px;
            background: rgba(139, 69, 19, 0.04);
            border-radius: 10px;
            border-left: 4px solid var(--gold-dark);
        }

        .class-heading-title {
            font-size: 15px;
            font-weight: 700;
            color: var(--brown-dark);
            margin: 0;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        [data-theme="dark"] .class-heading-title {
            color: var(--gold-primary);
        }

        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: var(--text-muted);
        }

        .empty-icon {
            font-size: 40px;
            display: block;
            margin-bottom: 12px;
            opacity: 0.7;
        }

        @media (min-width: 769px) {
            .main-container { padding: 0 12px 30px; margin: 15px auto; }
            .semester-banner {
                padding: 18px;
                flex-direction: column;
                align-items: flex-start;
                gap: 16px;
            }
            .banner-actions {
                width: 100%;
            }
            .banner-actions .btn {
                flex: 1;
                justify-content: center;
            }
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 10px;
            }
            .stat-card {
                padding: 12px 14px;
            }
            .section-card {
                padding: 16px 12px;
                border-radius: 12px;
            }
            th, td {
                padding: 9px 10px;
                font-size: 12.5px;
            }
        }
    </style>
</head>
<body>
    <?php include 'mobile_nav.php'; ?>

    <div class="main-container">
        <!-- Back Navigation -->
        <div class="back-nav">
            <a href="semester.php" class="back-link">
                <span>←</span> ወደ ሴሚስተር አስተዳደር ተመለስ
            </a>
            <div class="banner-actions">
                <a href="print_results.php?semester_id=<?php echo $semester_id; ?>" class="btn btn-gold" target="_blank">
                    <span>🖨️</span> ውጤት አትም
                </a>
            </div>
        </div>

        <!-- Semester Banner -->
        <div class="semester-banner">
            <div class="banner-left">
                <div class="banner-icon">📜</div>
                <div>
                    <h1 class="banner-title"><?php echo htmlspecialchars($semester['name']); ?></h1>
                    <div class="banner-subtitle">
                        <span>📅 <?php echo date('M d, Y', strtotime($semester['start_date'])); ?></span>
                        <?php if($semester['end_date']): ?>
                        <span>— <?php echo date('M d, Y', strtotime($semester['end_date'])); ?></span>
                        <?php endif; ?>
                        <span>•</span>
                        <?php if($semester['status'] == 'active'): ?>
                            <span class="status-pill status-active">● ንቁ ሴሚስተር</span>
                        <?php else: ?>
                            <span class="status-pill status-closed">● ዝግ ሴሚስተር</span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Quick Stats -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon">👨‍🏫</div>
                <div>
                    <div class="stat-num"><?php echo count($assignments_list); ?></div>
                    <div class="stat-label">የተመደቡ መምህራን</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">🏫</div>
                <div>
                    <div class="stat-num"><?php echo count($class_marks); ?></div>
                    <div class="stat-label">የተመዘገቡ ክፍሎች</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">📊</div>
                <div>
                    <div class="stat-num"><?php echo count($marks_list); ?></div>
                    <div class="stat-label">የተማሪዎች ውጤት ብዛት</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">⚙️</div>
                <div>
                    <div class="stat-num"><?php echo $semester['status'] == 'active' ? 'ክፍት' : 'ዝግ'; ?></div>
                    <div class="stat-label">የሴሚስተር ሁኔታ</div>
                </div>
            </div>
        </div>

        <!-- Teacher Assignments Section -->
        <div class="section-card">
            <div class="section-header">
                <h2 class="section-title">
                    <span>👨‍🏫</span> የመምህራን ምደባ
                    <span class="badge-count"><?php echo count($assignments_list); ?></span>
                </h2>
            </div>
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th style="width: 60px;">#</th>
                            <th>ክፍል</th>
                            <th>መምህር</th>
                            <th>የመቆለፍ ሁኔታ</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(!empty($assignments_list)): ?>
                            <?php $assign_idx = 1; foreach($assignments_list as $assignment): ?>
                            <tr>
                                <td><?php echo $assign_idx++; ?></td>
                                <td><strong><?php echo htmlspecialchars($assignment['class_name']); ?></strong></td>
                                <td><?php echo htmlspecialchars($assignment['teacher_name']); ?></td>
                                <td>
                                    <?php if($assignment['locked']): ?>
                                        <span style="color: #EF4444; font-weight: 700; display: inline-flex; align-items: center; gap: 4px;">
                                            🔒 ተቆልፏል
                                        </span>
                                    <?php else: ?>
                                        <span style="color: #10B981; font-weight: 700; display: inline-flex; align-items: center; gap: 4px;">
                                            🔓 ክፍት
                                        </span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="4" class="empty-state">
                                    <span class="empty-icon">👨‍🏫</span>
                                    ለዚህ ሴሚስተር የተመደበ መምህር የለም
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Student Marks Section -->
        <div class="section-card">
            <div class="section-header">
                <h2 class="section-title">
                    <span>📊</span> የተማሪዎች ውጤት
                    <span class="badge-count"><?php echo count($marks_list); ?> ተማሪዎች</span>
                </h2>
            </div>

            <?php if(!empty($class_marks)): ?>
                <?php foreach($class_marks as $class_name => $marks): ?>
                <div class="class-heading">
                    <h3 class="class-heading-title">
                        <span>🏫</span> <?php echo htmlspecialchars($class_name); ?>
                    </h3>
                    <span class="badge-count"><?php echo count($marks); ?> ተማሪዎች</span>
                </div>
                <div class="table-responsive" style="margin-bottom: 20px;">
                    <table>
                        <thead>
                            <tr>
                                <th style="width: 50px;">#</th>
                                <th>የተማሪ ስም</th>
                                <th style="text-align: right;">የቤት ስራ</th>
                                <th style="text-align: right;">የመካከለኛ</th>
                                <th style="text-align: right;">የመጨረሻ</th>
                                <th style="text-align: right;">አጠቃላይ ድምር</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $counter = 1;
                            foreach($marks as $mark): 
                            ?>
                            <tr>
                                <td><?php echo $counter++; ?></td>
                                <td><strong><?php echo htmlspecialchars($mark['student_name']); ?></strong></td>
                                <td style="text-align: right;"><?php echo formatMark($mark['assignment']); ?></td>
                                <td style="text-align: right;"><?php echo formatMark($mark['mid']); ?></td>
                                <td style="text-align: right;"><?php echo formatMark($mark['final']); ?></td>
                                <td style="text-align: right;"><strong style="color: var(--brown-dark);"><?php echo formatMark($mark['total']); ?></strong></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="empty-state">
                    <span class="empty-icon">📭</span>
                    <p style="font-weight: 600; margin-bottom: 4px;">ለዚህ ሴሚስተር የተመዘገበ ምንም ውጤት የለም</p>
                    <span style="font-size: 13px;">መምህራን ውጤት ሲሞሉ እዚህ ዝርዝሩ ይታያል።</span>
                </div>
            <?php endif; ?>
        </div>

        <div style="text-align: center; margin: 30px 0;">
            <a href="print_results.php?semester_id=<?php echo $semester_id; ?>" class="btn btn-gold" style="padding: 12px 28px; font-size: 15px;" target="_blank">
                <span>🖨️</span> የዚህን ሴሚስተር ውጤት በሙሉ አትም
            </a>
        </div>
    </div>
</body>
</html>
<?php mysqli_close($conn); ?>