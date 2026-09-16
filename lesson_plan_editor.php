<?php
require_once 'db.php';
requireTeacher();

$teacher_id = intval($_SESSION['user_id'] ?? 0);
$current_semester = getCurrentSemester($conn);
$semester_id = $current_semester ? intval($current_semester['id']) : 0;

if (!isAdmin() && function_exists('teacherCanCreatePlan') && !teacherCanCreatePlan($conn, $teacher_id, $semester_id)) {
    header("Location: dashboard_teacher.php");
    exit();
}
$my_classes = getTeacherClasses($conn, $teacher_id, $semester_id);
if (empty($my_classes) && isAdmin()) {
    $my_classes = dbFetchAll($conn, "SELECT c.id as class_id, c.id, c.name as class_name, '' as assigned_subject_name FROM classes c WHERE c.id BETWEEN 7 AND 12 ORDER BY c.id ASC");
}

// Group teacher classes and map any assigned subjects
$teacher_classes_map = [];
foreach ($my_classes as $c) {
    $cid = intval($c['class_id'] ?? $c['id']);
    if (!isset($teacher_classes_map[$cid])) {
        $teacher_classes_map[$cid] = [
            'class_id' => $cid,
            'class_name' => $c['class_name'] ?? $c['name'],
            'assigned_subjects' => []
        ];
    }
    if (!empty($c['assigned_subject_name']) && trim($c['assigned_subject_name']) !== '') {
        $subName = trim($c['assigned_subject_name']);
        if (!in_array($subName, $teacher_classes_map[$cid]['assigned_subjects'])) {
            $teacher_classes_map[$cid]['assigned_subjects'][] = $subName;
        }
    }
}

// Fetch authentic curriculum subjects by grade level (Grades 1-6)
$curriculumSubjectsByGrade = [];
$curriculumRows = dbFetchAll($conn, "SELECT DISTINCT grade_level, subject_name FROM curriculum_topics ORDER BY grade_level ASC, id ASC");
foreach ($curriculumRows as $cr) {
    $curriculumSubjectsByGrade[intval($cr['grade_level'])][] = $cr['subject_name'];
}

// Fetch all system subjects from subjects table
$allSubjectsList = getSubjects($conn);
$allSystemSubjects = [];
if (is_array($allSubjectsList)) {
    foreach ($allSubjectsList as $ss) {
        if (!empty($ss['name'])) {
            $allSystemSubjects[] = trim($ss['name']);
        }
    }
}

$can_create_plan = function_exists('teacherCanCreatePlan') ? teacherCanCreatePlan($conn, $teacher_id, $semester_id) : true;
if (isAdmin()) $can_create_plan = true;

$message = '';
$error = '';
$editing_plan = null;

$ethiopianMonths = [
    1 => 'መስከረም', 2 => 'ጥቅምት', 3 => 'ኅዳር', 4 => 'ታኅሣሥ',
    5 => 'ጥር', 6 => 'የካቲት', 7 => 'መጋቢት', 8 => 'ሚያዝያ',
    9 => 'ግንቦት', 10 => 'ሰኔ', 11 => 'ሐምሌ', 12 => 'ነሐሴ', 13 => 'ጳጉሜን'
];

function savePlanVersionSnapshot($conn, $planId, $changedBy, $reason = 'edit') {
    $current = dbFetchOne($conn, "SELECT * FROM lesson_plans WHERE id = ?", "i", [$planId]);
    if (!$current) return;
    dbExecute(
        $conn,
        "INSERT INTO lesson_plan_versions (lesson_plan_id, version, snapshot_json, changed_by, change_reason) VALUES (?, ?, ?, ?, ?)",
        "iisis",
        [$planId, $current['version'], json_encode($current, JSON_UNESCAPED_UNICODE), $changedBy, $reason]
    );
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = "የደህንነት ማረጋገጫ አልተሳካም!";
    } elseif (isset($_POST['save_plan'])) {
        if (!$can_create_plan) {
            $error = "ይቅርታ፤ የወጣቶች መምህራን የትምህርት ዕቅድ የማዘጋጀት ፈቃድ አልተሰጣቸውም። አስተዳዳሪውን ያነጋግሩ።";
        } else {
            $plan_id = intval($_POST['plan_id'] ?? 0);
            $class_id = intval($_POST['class_id'] ?? 0);
            $submitStatus = 'submitted';

        $fields = [
            'subject' => trim($_POST['subject'] ?? ''),
            'ethiopian_year' => intval($_POST['eth_year'] ?? 2019) ?: 2019,
            'ethiopian_month' => intval($_POST['eth_month'] ?? 0),
            'week_number' => trim($_POST['week_number'] ?? ''),
            'chapter' => trim($_POST['chapter'] ?? ''),
            'sub_topic' => trim($_POST['sub_topic'] ?? ''),
            'objective' => trim($_POST['objective'] ?? ''),
            'materials' => trim($_POST['materials'] ?? ''),
            'evaluation' => trim($_POST['evaluation'] ?? ''),
        ];

        // If subject is empty, fallback from teacher class assignment
        if ($fields['subject'] === '' && $class_id > 0) {
            $tcRow = dbFetchOne($conn, "SELECT COALESCE(s.name, tc.subject_name) as sub_name FROM teacher_class tc LEFT JOIN subjects s ON tc.subject_id = s.id WHERE tc.teacher_id = ? AND tc.class_id = ? AND tc.semester_id = ?", "iii", [$teacher_id, $class_id, $semester_id]);
            if ($tcRow && !empty($tcRow['sub_name'])) {
                $fields['subject'] = trim($tcRow['sub_name']);
            }
        }

        // Maintain canonical topic column for backward-compat and search
        $topic = trim($fields['chapter'] . ($fields['sub_topic'] !== '' ? ($fields['chapter'] !== '' ? ' - ' : '') . $fields['sub_topic'] : ''));
        if ($topic === '') {
            $topic = $fields['sub_topic'] ?: ($fields['chapter'] ?: 'የትምህርት ዕቅድ');
        }

        if (!$class_id || !$fields['subject'] || (!$fields['sub_topic'] && !$fields['chapter']) || !$fields['ethiopian_year'] || !$fields['ethiopian_month']) {
            $error = "እባክዎ ክፍል፣ የትምህርት ዓይነት፣ ወር እና ምዕራፍ/ንዑስ ርዕስ በትክክል ያስገቡ!";
        } else {
            $owns = dbFetchOne($conn, "SELECT id FROM teacher_class WHERE teacher_id = ? AND class_id = ? AND semester_id = ?", "iii", [$teacher_id, $class_id, $semester_id]);
            if (!$owns && !isAdmin()) {
                $error = "ወደዚህ ክፍል የመድረስ ፍቃድ የለዎትም!";
            } else {
                $photoPath = null;
                if (!empty($_FILES['paper_photo']['name']) && $_FILES['paper_photo']['error'] === 0) {
                    $ext = strtolower(pathinfo($_FILES['paper_photo']['name'], PATHINFO_EXTENSION));
                    $isRealImage = @getimagesize($_FILES['paper_photo']['tmp_name']) !== false;
                    if (in_array($ext, ['jpg','jpeg','png','webp']) && $isRealImage && $_FILES['paper_photo']['size'] < 10485760) {
                        $safeName = 'plan_' . $teacher_id . '_' . time() . '.' . $ext;
                        if (move_uploaded_file($_FILES['paper_photo']['tmp_name'], 'uploads/lesson_plans/' . $safeName)) {
                            $photoPath = 'uploads/lesson_plans/' . $safeName;
                        }
                    }
                }

                if (!$photoPath && !empty($_POST['uploaded_paper_photo_path'])) {
                    $cand = trim($_POST['uploaded_paper_photo_path']);
                    if (strpos($cand, 'uploads/lesson_plans/') === 0 && file_exists($cand)) {
                        $photoPath = $cand;
                    }
                }

                if ($plan_id > 0) {
                    $lockRow = dbFetchOne($conn, "SELECT plan_locked FROM teacher_class WHERE teacher_id = ? AND class_id = ? AND semester_id = ?", "iii", [$teacher_id, $class_id, $semester_id]);
                    if ($lockRow && $lockRow['plan_locked']) {
                        $error = "ይህ ክፍል ለዕቅድ አርትዖት ተቆልፏል! አስተዳዳሪውን ያነጋግሩ።";
                    } else {
                        $ownerCheck = dbFetchOne($conn, "SELECT id, paper_photo_path FROM lesson_plans WHERE id = ? AND teacher_id = ?", "ii", [$plan_id, $teacher_id]);
                        if (!$ownerCheck) {
                            $error = "ይህን ዕቅድ የማረም ፍቃድ የለዎትም!";
                        } else {
                            if (!$photoPath) {
                                $photoPath = $ownerCheck['paper_photo_path'];
                            }
                            savePlanVersionSnapshot($conn, $plan_id, $teacher_id, 'teacher_edit');
                            $sql = "UPDATE lesson_plans SET class_id=?, subject=?, ethiopian_year=?, ethiopian_month=?, week_number=?, chapter=?, sub_topic=?, topic=?, objective=?, materials=?, evaluation=?, status=?, version=version+1" . ($photoPath ? ", paper_photo_path=?" : "") . " WHERE id=?";
                            $types = "isisssssssss" . ($photoPath ? "s" : "") . "i";
                            $params = [$class_id, $fields['subject'], $fields['ethiopian_year'], $fields['ethiopian_month'], $fields['week_number'], $fields['chapter'], $fields['sub_topic'], $topic, $fields['objective'], $fields['materials'], $fields['evaluation'], $submitStatus];
                            if ($photoPath) $params[] = $photoPath;
                            $params[] = $plan_id;
                            dbExecute($conn, $sql, $types, $params);
                            auditLog($conn, 'lesson_plan_updated', 'lesson_plans', $plan_id);
                            $message = "ዕቅድ በተሳካ ሁኔታ ተሻሽሏል!";
                        }
                    }
                } else {
                    dbExecute(
                        $conn,
                        "INSERT INTO lesson_plans (teacher_id, class_id, semester_id, subject, ethiopian_year, ethiopian_month, ethiopian_day, week_number, chapter, sub_topic, topic, objective, materials, evaluation, paper_photo_path, status)
                         VALUES (?, ?, ?, ?, ?, ?, 1, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
                        "iiisissssssssss",
                        [$teacher_id, $class_id, $semester_id, $fields['subject'], $fields['ethiopian_year'], $fields['ethiopian_month'], $fields['week_number'], $fields['chapter'], $fields['sub_topic'], $topic, $fields['objective'], $fields['materials'], $fields['evaluation'], $photoPath, $submitStatus]
                    );
                    $newId = mysqli_insert_id($conn);
                    auditLog($conn, 'lesson_plan_created', 'lesson_plans', $newId);
                    $message = "ዕቅድ በተሳካ ሁኔታ ተፈጥሯል!";
                }
            }
        }
    }
}
}

if (isset($_GET['edit'])) {
    $editing_plan = dbFetchOne($conn, "SELECT * FROM lesson_plans WHERE id = ? AND teacher_id = ?", "ii", [intval($_GET['edit']), $teacher_id]);
}

$my_plans = dbFetchAll(
    $conn,
    "SELECT lp.*, c.name as class_name FROM lesson_plans lp JOIN classes c ON lp.class_id = c.id
     WHERE lp.teacher_id = ? AND lp.is_deleted = 0 ORDER BY lp.updated_at DESC LIMIT 50",
    "i",
    [$teacher_id]
);

$status_labels = [
    'submitted' => '📄 የተቀመጠ ዕቅድ',
    'reviewed' => '💬 አስተያየት ተሰጥቷል',
    'draft' => '📄 የተቀመጠ ዕቅድ',
    'approved' => '📄 የተቀመጠ ዕቅድ',
    'needs_correction' => '💬 አስተያየት ተሰጥቷል'
];
$nav_active = 'lesson_plan_editor';
$todayEth = getCurrentEthiopianDate();
?>
<!DOCTYPE html>
<html lang="am">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>የትምህርት ዕቅድ | አጸደ ትጉሃን</title>
<?php include 'pwa_head.php'; ?>
<style>
:root { 
    --brown-dark:#8B4513; 
    --brown-medium:#A52A2A;
    --gold-primary:#FFD700; 
    --gold-pale:#FFF8DC; 
    --success:#10B981; 
    --error:#EF4444; 
    --warn:#F59E0B; 
    --blue:#2563EB; 
}
* { margin:0; padding:0; box-sizing:border-box; font-family:'Segoe UI',sans-serif; }
body { background:#FAF9F6; color:#333; }
.main-container { max-width:1150px; margin:20px auto; padding:0 15px 60px; }
.card { background:white; border-radius:14px; padding:20px; margin-bottom:20px; box-shadow:0 4px 12px rgba(0,0,0,0.08); }
.message { padding:12px 15px; border-radius:8px; margin-bottom:15px; }
.success { background:#D1FAE5; color:var(--success); font-weight:600; }
.error { background:#FEE2E2; color:var(--error); font-weight:600; }
.warning-card { background:#FEF3C7; border-left:5px solid var(--warn); }
.warning-card h3 { color:#92400E; margin-bottom:8px; }
.warning-card p { color:#78350F; font-size:14px; line-height:1.6; }
.plan-date-panel { background:#FFF8DC; border:1px solid #FDE68A; }

/* OCR Capture Box */
.ocr-banner { background:linear-gradient(135deg, #FFF8DC, #FEF3C7); border:2px dashed #D97706; border-radius:12px; padding:16px; margin-bottom:20px; }
.ocr-btn-group { display:flex; gap:12px; flex-wrap:wrap; margin-top:10px; }
.btn-ocr { background:#D97706; color:white; border:none; padding:10px 18px; border-radius:8px; font-weight:700; cursor:pointer; font-size:14px; display:inline-flex; align-items:center; gap:6px; }
.btn-ocr:hover { background:#B45309; }
.btn-ocr-secondary { background:white; color:#8B4513; border:1px solid #D97706; }

/* Workspace split */
.workspace-grid { display:grid; grid-template-columns:300px 1fr; gap:20px; }
@media (max-width: 860px) {
    .workspace-grid { grid-template-columns:1fr; }
}

.photo-preview-card { background:#F8FAFC; border:1px solid #E2E8F0; border-radius:10px; padding:12px; text-align:center; position:sticky; top:15px; }
.photo-preview-card img { max-width:100%; max-height:420px; object-fit:contain; border-radius:6px; box-shadow:0 2px 8px rgba(0,0,0,0.1); }
.confidence-pill { display:inline-block; font-size:12px; font-weight:700; padding:4px 10px; border-radius:12px; margin-top:8px; background:#E0F2FE; color:var(--blue); }

.form-grid-3 { display:grid; grid-template-columns:repeat(auto-fit, minmax(180px, 1fr)); gap:12px; margin-bottom:12px; }
.form-grid-2 { display:grid; grid-template-columns:repeat(auto-fit, minmax(200px, 1fr)); gap:12px; margin-bottom:12px; }

label { display:block; font-size:13px; font-weight:700; color:var(--brown-dark); margin-bottom:5px; }
input, select, textarea { width:100%; padding:10px 12px; border:1px solid #CBD5E1; border-radius:8px; font-size:14px; font-family:inherit; }
input:focus, select:focus, textarea:focus { border-color:var(--brown-dark); outline:none; box-shadow:0 0 0 2px rgba(139,69,19,0.15); }
textarea { resize:vertical; }

.btn { background:var(--gold-primary); color:var(--brown-dark); border:none; padding:11px 24px; border-radius:8px; font-weight:700; cursor:pointer; font-size:15px; }
.btn:hover { opacity:0.92; }
.btn-outline { background:white; border:1px solid var(--gold-primary); color:var(--brown-dark); }
.btn-row { display:flex; gap:12px; margin-top:18px; flex-wrap:wrap; align-items:center; }

/* Paper Style Table for Lesson Plans */
.table-responsive { width:100%; overflow-x:auto; margin-top:10px; border-radius:8px; }
.paper-table { width:100%; border-collapse:collapse; background:white; font-size:13px; min-width:850px; }
.paper-table th { 
    background:var(--brown-dark); 
    color:white; 
    font-weight:700; 
    padding:10px 8px; 
    text-align:center; 
    border:1px solid #78350F;
    white-space:nowrap;
}
.paper-table td { 
    padding:10px 8px; 
    border:1px solid #E2E8F0; 
    vertical-align:top; 
}
.paper-table tbody tr:hover { background:#FFFDF7; }

.status-badge { display:inline-block; font-size:11px; font-weight:700; padding:3px 8px; border-radius:12px; background:var(--gold-pale); color:var(--brown-dark); }
.btn-action-edit { display:inline-block; padding:5px 12px; background:#F3F4F6; color:var(--brown-dark); border-radius:6px; text-decoration:none; font-weight:700; border:1px solid #D1D5DB; font-size:12px; }
.btn-action-edit:hover { background:var(--gold-primary); }
.feedback-box { background:#FEF3C7; border-radius:6px; padding:6px 8px; margin-top:6px; font-size:12px; color:#92400E; }
.loading-box { text-align:center; padding:15px; font-weight:600; color:#B45309; }
.spinner { display:inline-block; width:20px; height:20px; border:3px solid #FEF3C7; border-top:3px solid #B45309; border-radius:50%; animation:spin 1s linear infinite; vertical-align:middle; margin-right:8px; }
@keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }

/* Mobile Cards for Lesson Plans */
.plans-mobile-cards { display:none; }

@media (max-width: 768px) {
    .main-container { padding: 0 10px 40px; margin: 10px auto; }
    .card { padding: 16px 12px; border-radius: 12px; }
    
    /* OCR banner touch optimization */
    .ocr-banner { padding: 14px 10px; }
    .ocr-btn-group { flex-direction: column; gap: 8px; }
    .btn-ocr { width: 100%; justify-content: center; padding: 12px 16px; font-size: 15px; min-height: 46px; }
    
    /* Form fields mobile layout */
    .form-grid-2, .form-grid-3 { grid-template-columns: 1fr; gap: 10px; }
    .form-fields-container input, 
    .form-fields-container select, 
    .form-fields-container textarea { font-size: 15px; min-height: 44px; }
    .btn-row { flex-direction: column; }
    .btn-row .btn { width: 100%; justify-content: center; min-height: 48px; }
    
    /* Photo preview collapsible on mobile */
    .photo-preview-card { position: static; max-height: none; margin-bottom: 15px; padding: 10px; }
    .photo-preview-card img { max-height: 220px; }
    
    /* Hide wide table on mobile, show card list */
    .table-responsive { display: none !important; }
    .plans-mobile-cards { display: flex; flex-direction: column; gap: 12px; }
    
    .plan-card-mobile {
        background: #FFFFFF;
        border: 1.5px solid #E2E8F0;
        border-radius: 10px;
        padding: 14px;
        box-shadow: 0 2px 6px rgba(0,0,0,0.04);
        display: flex;
        flex-direction: column;
        gap: 8px;
    }
    .plan-card-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        border-bottom: 1px solid #F1F5F9;
        padding-bottom: 8px;
        gap: 8px;
        flex-wrap: wrap;
    }
    .plan-card-class {
        font-weight: 700;
        color: var(--brown-dark);
        font-size: 14px;
    }
    .plan-card-meta {
        font-size: 12px;
        color: #64748B;
        margin-top: 2px;
    }
    .plan-card-body {
        display: flex;
        flex-direction: column;
        gap: 6px;
        font-size: 13px;
        line-height: 1.4;
    }
    .plan-card-topic {
        font-size: 14px;
        font-weight: 700;
        color: #1E293B;
    }
    .plan-card-detail-row {
        display: flex;
        gap: 6px;
    }
    .plan-card-detail-label {
        font-weight: 600;
        color: var(--brown-medium);
        min-width: 65px;
        flex-shrink: 0;
    }
    .plan-card-actions {
        display: flex;
        gap: 8px;
        margin-top: 6px;
        padding-top: 8px;
        border-top: 1px dashed #E2E8F0;
    }
    .plan-card-actions .btn-action-edit {
        flex: 1;
        text-align: center;
        padding: 10px;
        font-size: 14px;
        border-radius: 8px;
    }
}

.subject-input-field {
    width: 100%;
    background: #F1F5F9;
    border: 1.5px solid #CBD5E1;
    border-radius: 8px;
    padding: 10px 80px 10px 12px;
    font-size: 14px;
    font-weight: 700;
    color: #1E293B;
    cursor: not-allowed;
}
.subject-lock-badge {
    position: absolute;
    right: 10px;
    top: 50%;
    transform: translateY(-50%);
    font-size: 11px;
    font-weight: 700;
    color: #475569;
    background: #E2E8F0;
    padding: 3px 8px;
    border-radius: 6px;
    pointer-events: none;
}
.subject-unlock-btn {
    background: #F8FAFC;
    border: 1px solid #CBD5E1;
    border-radius: 8px;
    padding: 10px 12px;
    cursor: pointer;
    font-size: 13px;
    font-weight: 700;
    color: var(--brown-dark);
}
.subject-input-field.unlocked {
    background: #FFFFFF !important;
    cursor: text !important;
    color: #1E293B !important;
}
.btn-photo-view {
    background: #FFF8DC;
    border: 1px solid #F59E0B;
    color: #92400E;
}

/* ── COMPREHENSIVE DARK MODE OVERRIDES (Removes all white backgrounds) ── */
html.dark-mode,
html.dark-mode body,
body.dark-mode,
.dark-mode,
.dark-mode body,
html[data-theme="dark"],
html[data-theme="dark"] body,
body[data-theme="dark"],
[data-theme="dark"] body,
[data-theme="dark"] {
    background-color: #0B1120 !important;
    background: #0B1120 !important;
    color: #F1F5F9 !important;
}

.dark-mode .main-container,
[data-theme="dark"] .main-container {
    background: transparent !important;
}

.dark-mode .card,
body.dark-mode .card,
html.dark-mode .card,
[data-theme="dark"] .card {
    background-color: #1E293B !important;
    background: #1E293B !important;
    border: 1px solid #334155 !important;
    color: #F1F5F9 !important;
    box-shadow: 0 4px 16px rgba(0,0,0,0.4) !important;
}

.dark-mode .card h2,
body.dark-mode .card h2,
[data-theme="dark"] .card h2,
.dark-mode h1, .dark-mode h2, .dark-mode h3, .dark-mode h4,
[data-theme="dark"] h1, [data-theme="dark"] h2, [data-theme="dark"] h3, [data-theme="dark"] h4 {
    color: #FCD34D !important;
}

.dark-mode label,
body.dark-mode label,
[data-theme="dark"] label {
    color: #FCD34D !important;
}

.dark-mode input,
.dark-mode select,
.dark-mode textarea,
body.dark-mode input,
body.dark-mode select,
body.dark-mode textarea,
[data-theme="dark"] input,
[data-theme="dark"] select,
[data-theme="dark"] textarea {
    background-color: #0F172A !important;
    background: #0F172A !important;
    color: #F1F5F9 !important;
    border: 1.5px solid #334155 !important;
}

.dark-mode select option,
body.dark-mode select option,
[data-theme="dark"] select option {
    background-color: #0F172A !important;
    background: #0F172A !important;
    color: #F1F5F9 !important;
}

.dark-mode input:focus,
.dark-mode select:focus,
.dark-mode textarea:focus,
body.dark-mode input:focus,
body.dark-mode select:focus,
body.dark-mode textarea:focus,
[data-theme="dark"] input:focus,
[data-theme="dark"] select:focus,
[data-theme="dark"] textarea:focus {
    border-color: #F59E0B !important;
    box-shadow: 0 0 0 2px rgba(245,158,11,0.25) !important;
}

/* Chrome/Safari Autofill override */
.dark-mode input:-webkit-autofill,
.dark-mode input:-webkit-autofill:hover,
.dark-mode input:-webkit-autofill:focus,
.dark-mode textarea:-webkit-autofill,
.dark-mode select:-webkit-autofill,
[data-theme="dark"] input:-webkit-autofill,
[data-theme="dark"] input:-webkit-autofill:hover,
[data-theme="dark"] input:-webkit-autofill:focus,
[data-theme="dark"] textarea:-webkit-autofill,
[data-theme="dark"] select:-webkit-autofill {
    -webkit-text-fill-color: #F1F5F9 !important;
    -webkit-box-shadow: 0 0 0px 1000px #0F172A inset !important;
    box-shadow: 0 0 0px 1000px #0F172A inset !important;
    transition: background-color 5000s ease-in-out 0s;
}

.dark-mode .subject-input-field,
[data-theme="dark"] .subject-input-field,
.dark-mode #field_subject,
[data-theme="dark"] #field_subject {
    background-color: #0F172A !important;
    background: #0F172A !important;
    color: #FCD34D !important;
    border-color: #334155 !important;
}

.dark-mode .subject-lock-badge,
[data-theme="dark"] .subject-lock-badge,
.dark-mode #subjectLockBadge,
[data-theme="dark"] #subjectLockBadge {
    background-color: #1E293B !important;
    background: #1E293B !important;
    color: #CBD5E1 !important;
    border: 1px solid #334155 !important;
}

.dark-mode .subject-unlock-btn,
[data-theme="dark"] .subject-unlock-btn {
    background-color: #0F172A !important;
    background: #0F172A !important;
    border-color: #334155 !important;
    color: #FCD34D !important;
}

.dark-mode #subjectHelpText,
.dark-mode #curriculumStatus,
[data-theme="dark"] #subjectHelpText,
[data-theme="dark"] #curriculumStatus {
    color: #94A3B8 !important;
}

.dark-mode #toggleCustomBtn,
[data-theme="dark"] #toggleCustomBtn {
    color: #FCD34D !important;
}

.dark-mode .plan-date-panel,
body.dark-mode .plan-date-panel,
[data-theme="dark"] .plan-date-panel {
    background-color: #0F172A !important;
    background: #0F172A !important;
    border: 1px solid #334155 !important;
}

.dark-mode .ocr-banner,
body.dark-mode .ocr-banner,
[data-theme="dark"] .ocr-banner {
    background: linear-gradient(135deg, #1E293B, #0F172A) !important;
    border: 2px dashed #F59E0B !important;
}

.dark-mode .ocr-banner h3,
[data-theme="dark"] .ocr-banner h3 {
    color: #FCD34D !important;
}

.dark-mode .ocr-banner p,
[data-theme="dark"] .ocr-banner p {
    color: #CBD5E1 !important;
}

.dark-mode .btn-ocr-secondary,
body.dark-mode .btn-ocr-secondary,
[data-theme="dark"] .btn-ocr-secondary {
    background-color: #0F172A !important;
    background: #0F172A !important;
    color: #FCD34D !important;
    border: 1.5px solid #F59E0B !important;
}

.dark-mode .photo-preview-card,
body.dark-mode .photo-preview-card,
[data-theme="dark"] .photo-preview-card {
    background-color: #0F172A !important;
    background: #0F172A !important;
    border: 1px solid #334155 !important;
    color: #E2E8F0 !important;
}

.dark-mode .photo-preview-card h4,
[data-theme="dark"] .photo-preview-card h4 {
    color: #CBD5E1 !important;
}

.dark-mode .photo-preview-card img,
[data-theme="dark"] .photo-preview-card img {
    background-color: #0F172A !important;
    border: 1px solid #334155 !important;
}

.dark-mode .confidence-pill,
[data-theme="dark"] .confidence-pill {
    background-color: #1E3A8A !important;
    color: #93C5FD !important;
}

.dark-mode .btn-outline,
body.dark-mode .btn-outline,
[data-theme="dark"] .btn-outline {
    background-color: #0F172A !important;
    background: #0F172A !important;
    color: #FCD34D !important;
    border: 1.5px solid #F59E0B !important;
}

.dark-mode .table-responsive,
[data-theme="dark"] .table-responsive {
    border-color: #334155 !important;
}

.dark-mode .paper-table,
body.dark-mode .paper-table,
[data-theme="dark"] .paper-table {
    background-color: #1E293B !important;
    background: #1E293B !important;
    color: #F1F5F9 !important;
}

.dark-mode .paper-table th,
body.dark-mode .paper-table th,
[data-theme="dark"] .paper-table th {
    background-color: #0F172A !important;
    background: #0F172A !important;
    color: #FCD34D !important;
    border: 1px solid #334155 !important;
}

.dark-mode .paper-table td,
body.dark-mode .paper-table td,
[data-theme="dark"] .paper-table td {
    background-color: #1E293B !important;
    background: #1E293B !important;
    color: #E2E8F0 !important;
    border: 1px solid #334155 !important;
}

.dark-mode .paper-table td strong,
[data-theme="dark"] .paper-table td strong {
    color: #FCD34D !important;
}

.dark-mode .paper-table td div,
[data-theme="dark"] .paper-table td div {
    color: #94A3B8 !important;
}

.dark-mode .paper-table tbody tr:hover td,
body.dark-mode .paper-table tbody tr:hover td,
[data-theme="dark"] .paper-table tbody tr:hover td {
    background-color: #233045 !important;
    background: #233045 !important;
}

.dark-mode .status-badge,
[data-theme="dark"] .status-badge {
    background-color: #0F172A !important;
    background: #0F172A !important;
    color: #FCD34D !important;
    border: 1px solid #D97706 !important;
}

.dark-mode .btn-action-edit,
[data-theme="dark"] .btn-action-edit {
    background-color: #0F172A !important;
    background: #0F172A !important;
    color: #FCD34D !important;
    border: 1px solid #334155 !important;
}

.dark-mode .btn-action-edit:hover,
[data-theme="dark"] .btn-action-edit:hover {
    background-color: #D97706 !important;
    background: #D97706 !important;
    color: #FFFFFF !important;
}

.dark-mode .btn-photo-view,
[data-theme="dark"] .btn-photo-view {
    background-color: #0F172A !important;
    background: #0F172A !important;
    border-color: #F59E0B !important;
    color: #FCD34D !important;
}

.dark-mode .feedback-box,
[data-theme="dark"] .feedback-box {
    background-color: #3B2A10 !important;
    background: #3B2A10 !important;
    border: 1px solid #D97706 !important;
    color: #FDE68A !important;
}

.dark-mode .plan-card-mobile,
body.dark-mode .plan-card-mobile,
[data-theme="dark"] .plan-card-mobile {
    background-color: #1E293B !important;
    background: #1E293B !important;
    border-color: #334155 !important;
    color: #F1F5F9 !important;
    box-shadow: 0 2px 8px rgba(0,0,0,0.3) !important;
}

.dark-mode .plan-card-mobile.active-edit,
[data-theme="dark"] .plan-card-mobile.active-edit {
    background-color: #233045 !important;
    background: #233045 !important;
    border-color: #F59E0B !important;
}

.dark-mode .plan-card-header,
[data-theme="dark"] .plan-card-header {
    border-bottom-color: #334155 !important;
}

.dark-mode .plan-card-class,
[data-theme="dark"] .plan-card-class {
    color: #FCD34D !important;
}

.dark-mode .plan-card-meta,
[data-theme="dark"] .plan-card-meta {
    color: #94A3B8 !important;
}

.dark-mode .plan-card-topic,
[data-theme="dark"] .plan-card-topic {
    color: #FFFFFF !important;
}

.dark-mode .plan-card-detail-label,
[data-theme="dark"] .plan-card-detail-label {
    color: #FBBF24 !important;
}

.dark-mode .plan-card-detail-row span,
[data-theme="dark"] .plan-card-detail-row span {
    color: #CBD5E1 !important;
}

.dark-mode .plan-card-actions,
[data-theme="dark"] .plan-card-actions {
    border-top-color: #334155 !important;
}

.dark-mode .warning-card,
body.dark-mode .warning-card,
[data-theme="dark"] .warning-card {
    background-color: #3B2A10 !important;
    background: #3B2A10 !important;
    border-left-color: #F59E0B !important;
}

.dark-mode .warning-card h3,
[data-theme="dark"] .warning-card h3 {
    color: #FCD34D !important;
}

.dark-mode .warning-card p,
[data-theme="dark"] .warning-card p {
    color: #FDE68A !important;
}

.dark-mode .message.success,
[data-theme="dark"] .message.success {
    background-color: #064E3B !important;
    background: #064E3B !important;
    color: #6EE7B7 !important;
    border: 1px solid #059669 !important;
}

.dark-mode .message.error,
[data-theme="dark"] .message.error {
    background-color: #7F1D1D !important;
    background: #7F1D1D !important;
    color: #FCA5A5 !important;
    border: 1px solid #DC2626 !important;
}

.dark-mode .loading-box,
[data-theme="dark"] .loading-box {
    color: #FCD34D !important;
}

.dark-mode .spinner,
[data-theme="dark"] .spinner {
    border: 3px solid #334155 !important;
    border-top: 3px solid #F59E0B !important;
}
</style>
</head>
<body>
<?php include 'mobile_nav.php'; ?>
<div class="main-container">
    <?php if ($message): ?><div class="message success">✅ <?php echo htmlspecialchars($message); ?></div><?php endif; ?>
    <?php if ($error): ?><div class="message error">⚠️ <?php echo htmlspecialchars($error); ?></div><?php endif; ?>

    <?php if (!$can_create_plan): ?>
    <div class="card warning-card" style="padding:20px;">
        <h3>⚠️ የትምህርት ዕቅድ ማዘጋጀት አልተፈቀደም</h3>
        <p>
            በአሁኑ ደንብ መሠረት የወጣቶች (ከ7ኛ-12ኛ ክፍል) መምህራን የትምህርት ዕቅድ ማዘጋጀት አይጠበቅባቸውም። ማስተካከያ ወይም ፍቃድ ለማግኘት እባክዎ የስርዓት አስተዳዳሪውን ያነጋግሩ።
        </p>
    </div>
    <?php else: ?>
    <div class="card">
        <h2 style="color:var(--brown-dark); margin-bottom:12px;">
            <?php echo $editing_plan ? '✏️ የትምህርት ዕቅድ ማስተካከያ' : '➕ አዲስ የትምህርት ዕቅድ ማዘጋጃ'; ?>
        </h2>

        <!-- Image Reference Upload Section -->
        <div class="ocr-banner">
            <h3 style="color:#92400E; font-size:16px; margin-bottom:4px;">📷 የተዘጋጀ የዕቅድ ወረቀት ፎቶ</h3>
            <p style="font-size:13px; color:#78350F;">
                የተጻፈበትን የወረቀት ሰንጠረዥ ፎቶ እዚህ በማንሳት ወይም በማስገባት በግራ በኩል እንደ ማጣቀሻ እያዩ በቀላሉ ከታች ባለው ሰንጠረዥ መሙላት ይችላሉ።
            </p>
            <div class="ocr-btn-group">
                <input type="file" id="ocrCamera" accept="image/*" capture="environment" style="display:none;" onchange="handleOcrUpload(this)">
                <input type="file" id="ocrGallery" accept="image/*" style="display:none;" onchange="handleOcrUpload(this)">
                
                <button type="button" class="btn-ocr" onclick="document.getElementById('ocrCamera').click()">
                    📷 በካሜራ አንሳ
                </button>
                <button type="button" class="btn-ocr btn-ocr-secondary" onclick="document.getElementById('ocrGallery').click()">
                    📁 ከማህደር ምረጥ
                </button>
            </div>
            
            <div id="ocrLoading" class="loading-box" style="display:none;">
                <span class="spinner"></span> ፎቶው በመጫን ላይ ነው... እባክዎ ጥቂት ይጠብቁ
            </div>
            <div id="ocrStatusMsg" style="margin-top:10px; font-size:13px; display:none;"></div>
        </div>

        <form method="POST" enctype="multipart/form-data" id="planForm">
            <?php echo csrfField(); ?>
            <input type="hidden" name="plan_id" value="<?php echo $editing_plan['id'] ?? 0; ?>">
            <input type="hidden" name="uploaded_paper_photo_path" id="uploadedPhotoPath" value="<?php echo htmlspecialchars($editing_plan['paper_photo_path'] ?? ''); ?>">

            <div class="workspace-grid">
                <!-- Left: Photo Preview Reference -->
                <div class="photo-preview-card" id="photoCard" style="<?php echo empty($editing_plan['paper_photo_path']) ? 'display:none;' : ''; ?>">
                    <h4 style="font-size:13px; color:#555; margin-bottom:8px;">የዕቅዱ ፎቶ ማጣቀሻ</h4>
                    <img id="photoPreview" src="<?php echo htmlspecialchars($editing_plan['paper_photo_path'] ?? ''); ?>" alt="የዕቅድ ፎቶ">
                    <div id="confidencePill" class="confidence-pill" style="display:none;"></div>
                </div>

                <!-- Right: Form Fields matching Paper Header & 7 Columns -->
                <div class="form-fields-container">
                    <!-- Paper Top Header: Class & Subject (Dynamic selector linked to curriculum) -->
                    <?php
                    $selected_class_id = $editing_plan['class_id'] ?? (array_key_first($teacher_classes_map) ?? ($my_classes[0]['class_id'] ?? ($my_classes[0]['id'] ?? 0)));
                    $saved_subject = $editing_plan['subject'] ?? ($teacher_classes_map[$selected_class_id]['assigned_subjects'][0] ?? ($my_classes[0]['assigned_subject_name'] ?? ''));
                    ?>
                    <div class="form-grid-2">
                        <div>
                            <label for="field_class_id">ክፍል *</label>
                            <select name="class_id" id="field_class_id" required onchange="onClassChange()">
                                <?php foreach ($teacher_classes_map as $cid => $cData): 
                                    $assignedSub = $cData['assigned_subjects'][0] ?? '';
                                ?>
                                <option value="<?php echo $cid; ?>" 
                                        data-subject="<?php echo htmlspecialchars($assignedSub); ?>" 
                                        <?php echo ($selected_class_id == $cid) ? 'selected' : ''; ?>>
                                    <?php 
                                    $label = $cData['class_name'];
                                    if (!empty($assignedSub)) {
                                        $label .= ' (' . $assignedSub . ')';
                                    }
                                    echo htmlspecialchars($label); 
                                    ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label for="field_subject">የትምህርት ዓይነት *</label>
                            <div style="position:relative; display:flex; align-items:center; gap:6px;">
                                <div style="position:relative; flex:1;">
                                    <input type="text" 
                                           name="subject" 
                                           id="field_subject" 
                                           class="subject-input-field"
                                           value="<?php echo htmlspecialchars($saved_subject); ?>" 
                                           readonly 
                                           required
                                           oninput="onSubjectInputChange(this.value)"
                                           placeholder="የተመደበ የትምህርት ዓይነት...">
                                    <span id="subjectLockBadge" class="subject-lock-badge">
                                        🔒 ቋሚ
                                    </span>
                                </div>
                                <?php if (isAdmin()): ?>
                                <button type="button" class="subject-unlock-btn" onclick="unlockSubjectInput()" title="የትምህርት ዓይነት ቀይር (ለአስተዳዳሪ ብቻ)">
                                    ✏️
                                </button>
                                <?php endif; ?>
                            </div>
                            <small id="subjectHelpText" style="font-size:11px; color:#64748B; margin-top:4px; display:block;">
                                🔒 የተመደበ የትምህርት ዓይነት (በምዕራፍ የሚታዩት የዚህ ትምህርት ምዕራፎች ብቻ ናቸው)
                            </small>
                        </div>
                    </div>
                    <!-- Hidden Ethiopian Year (Default: 2019 ዓ.ም) -->
                    <input type="hidden" name="eth_year" id="field_eth_year" value="<?php echo htmlspecialchars($editing_plan['ethiopian_year'] ?? 2019); ?>">

                    <!-- The 7 Paper Columns -->
                    <div class="form-grid-2 plan-date-panel" style="padding:12px; border-radius:8px; margin-bottom:12px;">
                        <div>
                            <label for="field_eth_month">ወር *</label>
                            <select name="eth_month" id="field_eth_month" required>
                                <?php foreach ($ethiopianMonths as $num => $mName): ?>
                                <option value="<?php echo $num; ?>" <?php echo (($editing_plan['ethiopian_month'] ?? $todayEth['month']) == $num) ? 'selected' : ''; ?>>
                                    <?php echo $num . ' - ' . $mName; ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label for="field_week_number">ሳምንት *</label>
                            <input type="text" name="week_number" id="field_week_number" placeholder="ምሳሌ፡ 1ኛ ሳምንት" value="<?php echo htmlspecialchars($editing_plan['week_number'] ?? ''); ?>" required list="weeksList">
                            <datalist id="weeksList">
                                <option value="1ኛ ሳምንት">
                                <option value="2ኛ ሳምንት">
                                <option value="3ኛ ሳምንት">
                                <option value="4ኛ ሳምንት">
                                <option value="5ኛ ሳምንት">
                            </datalist>
                        </div>
                    </div>

                    <!-- Curriculum Cascading Dropdowns: ምዕራፍ & ንዑስ ርዕስ -->
                    <div class="form-grid-2">
                        <div>
                            <label for="select_chapter">ምዕራፍ *</label>
                            <select name="chapter" id="select_chapter" required onchange="onChapterSelect(this.value)">
                                <option value="">-- ምዕራፍ በመጫን ላይ... --</option>
                            </select>
                            <div id="chapterCustomWrap" style="display:none; margin-top:6px;">
                                <input type="text" id="custom_chapter" placeholder="ምዕራፍ በእጅ ይጻፉ..." oninput="onCustomChapterInput(this.value)">
                            </div>
                        </div>
                        <div>
                            <label for="select_sub_topic">ንዑስ ርዕስ *</label>
                            <select name="sub_topic" id="select_sub_topic" required onchange="onSubTopicSelect(this.value)">
                                <option value="">-- መጀመሪያ ምዕራፍ ይምረጡ --</option>
                            </select>
                            <div id="subTopicCustomWrap" style="display:none; margin-top:6px;">
                                <input type="text" id="custom_sub_topic" placeholder="ንዑስ ርዕስ በእጅ ይጻፉ..." oninput="onCustomSubTopicInput(this.value)">
                            </div>
                        </div>
                    </div>
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-top:-4px; margin-bottom:12px; flex-wrap:wrap; gap:8px;">
                        <small id="curriculumStatus" style="font-size:12px; color:#64748B; font-weight:600;">📚 ከመማሪያ መጽሐፉ ማውጫ በቀጥታ የተዘጋጀ</small>
                        <button type="button" onclick="toggleCustomInputs()" id="toggleCustomBtn" style="background:none; border:none; color:#8B4513; font-size:12px; font-weight:700; cursor:pointer; text-decoration:underline;">
                            ✏️ ርዕስ በእጅ ለመጻፍ
                        </button>
                    </div>

                    <div style="margin-top:10px;">
                        <label>የትምህርቱ ዓላማ</label>
                        <textarea name="objective" id="field_objective" rows="2" placeholder="የትምህርቱ ዋና ዓላማ..."><?php echo htmlspecialchars($editing_plan['objective'] ?? ''); ?></textarea>
                    </div>

                    <div style="margin-top:10px;">
                        <label>የማስተማሪያ መርጃ መሣሪያዎች</label>
                        <textarea name="materials" id="field_materials" rows="2" placeholder="መጽሐፍ ቅዱስ፣ ሥዕላት፣ ቻርት..."><?php echo htmlspecialchars($editing_plan['materials'] ?? ''); ?></textarea>
                    </div>

                    <div style="margin-top:10px;">
                        <label>የተማሪዎች ምዘናና ክትትል</label>
                        <textarea name="evaluation" id="field_evaluation" rows="2" placeholder="የቃል ጥያቄ፣ የክፍል ሥራ፣ ምዘና..."><?php echo htmlspecialchars($editing_plan['evaluation'] ?? ''); ?></textarea>
                    </div>

                    <div class="btn-row">
                        <button type="submit" name="save_plan" class="btn" style="background:#8B4513; color:white; font-size:15px; padding:12px 28px;">
                            💾 የትምህርት ዕቅድ አስቀምጥ
                        </button>
                        <?php if ($editing_plan): ?>
                            <a href="lesson_plan_editor.php" class="btn btn-outline" style="text-decoration:none; display:inline-flex; align-items:center;">✖️ ተመለስ</a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </form>
    </div>
    <?php endif; ?>

    <!-- Lesson plans table matching the paper headers -->
    <div class="card">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:12px; flex-wrap:wrap; gap:10px;">
            <h2 style="color:var(--brown-dark);">📋 የዕቅዶች ዝርዝር</h2>
            <span style="font-size:13px; color:#666; font-weight:600;"><?php echo count($my_plans); ?> ዕቅዶች</span>
        </div>

        <?php if (empty($my_plans)): ?>
            <p style="color:#999; text-align:center; padding:30px;">ምንም ዕቅድ አልተገኘም። ከላይ ያለውን ፎርም በመጠቀም አዲስ ዕቅድ ያስገቡ።</p>
        <?php else: ?>
        <div class="table-responsive">
            <table class="paper-table">
                <thead>
                    <tr>
                        <th style="width:90px;">ወር</th>
                        <th style="width:95px;">ሳምንት</th>
                        <th style="width:110px;">ምዕራፍ</th>
                        <th>ንዑስ ርዕስ</th>
                        <th>የትምህርቱ ዓላማ</th>
                        <th>መርጃ መሳሪያ</th>
                        <th>ምዘና</th>
                        <th style="width:110px;">ሁኔታ</th>
                        <th style="width:80px;">ተግባር</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($my_plans as $p): ?>
                    <tr>
                        <td style="text-align:center;"><strong><?php echo $ethiopianMonths[$p['ethiopian_month']] ?? $p['ethiopian_month']; ?></strong></td>
                        <td style="text-align:center;"><?php echo htmlspecialchars($p['week_number'] ?: '—'); ?></td>
                        <td><?php echo htmlspecialchars($p['chapter'] ?: '—'); ?></td>
                        <td>
                            <strong><?php echo htmlspecialchars($p['sub_topic'] ?: $p['topic']); ?></strong>
                            <div style="font-size:11px; color:#666; margin-top:3px;">
                                <?php echo htmlspecialchars($p['class_name']); ?>
                                <?php if ($p['subject']): ?> · <?php echo htmlspecialchars($p['subject']); ?><?php endif; ?>
                                · <?php echo $p['ethiopian_year']; ?> ዓ.ም
                            </div>
                            <?php if (!empty($p['admin_feedback'])): ?>
                                <div class="feedback-box">
                                    <strong>አስተያየት:</strong> <?php echo htmlspecialchars($p['admin_feedback']); ?>
                                </div>
                            <?php endif; ?>
                        </td>
                        <td><?php echo nl2br(htmlspecialchars($p['objective'] ?: '—')); ?></td>
                        <td><?php echo nl2br(htmlspecialchars($p['materials'] ?: '—')); ?></td>
                        <td><?php echo nl2br(htmlspecialchars($p['evaluation'] ?: '—')); ?></td>
                        <td style="text-align:center;">
                            <span class="status-badge"><?php echo $status_labels[$p['status']] ?? $p['status']; ?></span>
                        </td>
                        <td style="text-align:center;">
                            <a href="?edit=<?php echo $p['id']; ?>" class="btn-action-edit">✏️ አርትዕ</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Mobile View Cards (< 768px) -->
        <div class="plans-mobile-cards">
            <?php foreach ($my_plans as $p): ?>
            <div class="plan-card-mobile <?php echo (isset($editing_plan) && $editing_plan && $editing_plan['id'] == $p['id']) ? 'active-edit' : ''; ?>">
                <div class="plan-card-header">
                    <div>
                        <div class="plan-card-class">📖 <?php echo htmlspecialchars($p['class_name']); ?></div>
                        <div class="plan-card-meta">
                            📅 <?php echo $ethiopianMonths[$p['ethiopian_month']] ?? $p['ethiopian_month']; ?> · <?php echo htmlspecialchars($p['week_number'] ?: 'ሳምንት አልተጠቀሰም'); ?>
                            <?php if ($p['subject']): ?> · <strong><?php echo htmlspecialchars($p['subject']); ?></strong><?php endif; ?>
                        </div>
                    </div>
                    <span class="status-badge"><?php echo $status_labels[$p['status']] ?? $p['status']; ?></span>
                </div>
                <div class="plan-card-body">
                    <?php if ($p['chapter']): ?>
                        <div class="plan-card-detail-row">
                            <span class="plan-card-detail-label">ምዕራፍ፦</span>
                            <span><?php echo htmlspecialchars($p['chapter']); ?></span>
                        </div>
                    <?php endif; ?>
                    <div class="plan-card-detail-row">
                        <span class="plan-card-detail-label">ርዕስ፦</span>
                        <strong class="plan-card-topic"><?php echo htmlspecialchars($p['sub_topic'] ?: $p['topic']); ?></strong>
                    </div>
                    <?php if ($p['objective']): ?>
                        <div class="plan-card-detail-row">
                            <span class="plan-card-detail-label">ዓላማ፦</span>
                            <span style="color:#475569;"><?php echo htmlspecialchars($p['objective']); ?></span>
                        </div>
                    <?php endif; ?>
                    <?php if (!empty($p['admin_feedback'])): ?>
                        <div class="feedback-box">
                            <strong>💬 አስተያየት:</strong> <?php echo htmlspecialchars($p['admin_feedback']); ?>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="plan-card-actions">
                    <a href="?edit=<?php echo $p['id']; ?>" class="btn-action-edit">✏️ አርትዕ</a>
                    <?php if (!empty($p['paper_photo_path']) && file_exists($p['paper_photo_path'])): ?>
                        <a href="<?php echo htmlspecialchars($p['paper_photo_path']); ?>" target="_blank" class="btn-action-edit btn-photo-view">📷 ፎቶ እይ</a>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<script>
async function handleOcrUpload(input) {
    if (!input.files || !input.files[0]) return;
    const file = input.files[0];

    // Show photo preview immediately
    const reader = new FileReader();
    reader.onload = (e) => {
        document.getElementById('photoPreview').src = e.target.result;
        document.getElementById('photoCard').style.display = 'block';
    };
    reader.readAsDataURL(file);

    // Show loading spinner
    const loading = document.getElementById('ocrLoading');
    const statusMsg = document.getElementById('ocrStatusMsg');
    loading.style.display = 'block';
    statusMsg.style.display = 'none';

    const csrfToken = document.querySelector('input[name="csrf_token"]').value;
    const fd = new FormData();
    fd.append('photo', file);
    fd.append('csrf_token', csrfToken);

    try {
        const res = await fetch('api/ocr_process.php', {
            method: 'POST',
            body: fd
        });
        const data = await res.json();
        loading.style.display = 'none';

        if (data.processed_photo) {
            document.getElementById('photoPreview').src = data.processed_photo;
            document.getElementById('photoCard').style.display = 'block';
            document.getElementById('uploadedPhotoPath').value = data.processed_photo;
        } else if (data.original_photo) {
            document.getElementById('photoPreview').src = data.original_photo;
            document.getElementById('photoCard').style.display = 'block';
            document.getElementById('uploadedPhotoPath').value = data.original_photo;
        }

        if (data.success && data.fields && Object.keys(data.fields).length > 0) {
            statusMsg.style.display = 'block';
            statusMsg.className = 'message success';
            statusMsg.innerHTML = `✅ ጽሑፉ ተነቧል! እባክዎ ከታች የተሞሉትን መረጃዎች ትክክለኛነት ያረጋግጡ።`;

            const f = data.fields;
            if (f.ethiopian_month) document.getElementById('field_eth_month').value = f.ethiopian_month;
            if (f.week_number) document.getElementById('field_week_number').value = f.week_number;
            if (f.objective) document.getElementById('field_objective').value = f.objective;
            if (f.materials) document.getElementById('field_materials').value = f.materials;
            if (f.evaluation) document.getElementById('field_evaluation').value = f.evaluation;
            if (f.subject) {
                const subInput = document.getElementById('field_subject');
                if (subInput && !subInput.value.trim()) {
                    subInput.value = f.subject;
                    currentEditingSubject = f.subject;
                    loadCurriculum();
                }
            }
            if (f.chapter || f.sub_topic) {
                applyOcrTopics(f.chapter || '', f.sub_topic || '');
            }
        } else {
            statusMsg.style.display = 'block';
            statusMsg.className = 'message error';
            statusMsg.innerHTML = `⚠️ ፎቶው ላይ ያለውን ጽሑፍ በቀጥታ መለየት አልተቻለም፤ እባክዎ በግራ በኩል የሚታየውን ፎቶ እያዩ ከታች ባሉት ክፍት ቦታዎች ላይ በቀላሉ ይሙሉ::`;
        }
    } catch (err) {
        loading.style.display = 'none';
        statusMsg.style.display = 'block';
        statusMsg.className = 'message error';
        statusMsg.innerHTML = `⚠️ የግንኙነት ችግር አጋጥሟል፤ እባክዎ በድጋሚ ይሞክሩ።`;
    }
}

// Curriculum & Form Management
const teacherClassesMap = <?php echo json_encode($teacher_classes_map, JSON_UNESCAPED_UNICODE); ?>;
const curriculumSubjectsByGrade = <?php echo json_encode($curriculumSubjectsByGrade, JSON_UNESCAPED_UNICODE); ?>;
const allSystemSubjects = <?php echo json_encode($allSystemSubjects, JSON_UNESCAPED_UNICODE); ?>;
let currentEditingSubject = <?php echo json_encode($saved_subject, JSON_UNESCAPED_UNICODE); ?>;
let currentEditingChapter = <?php echo json_encode($editing_plan['chapter'] ?? '', JSON_UNESCAPED_UNICODE); ?>;
let currentEditingSubTopic = <?php echo json_encode($editing_plan['sub_topic'] ?? ($editing_plan['topic'] ?? ''), JSON_UNESCAPED_UNICODE); ?>;
let curriculumTree = {};
let isCustomMode = false;
let isCustomSubjectMode = false;

function getGradeLevel(classId, className) {
    const cid = parseInt(classId, 10);
    const defaultMap = { 7: 1, 8: 2, 9: 3, 10: 4, 11: 5, 12: 6 };
    if (defaultMap[cid]) return defaultMap[cid];
    if (!className) return 0;
    if (/10|11|12/.test(className)) return 0;
    if (/1ኛ|፩ኛ|1\s*st/i.test(className)) return 1;
    if (/2ኛ|፪ኛ|2\s*nd/i.test(className)) return 2;
    if (/3ኛ|፫ኛ|3\s*rd/i.test(className)) return 3;
    if (/4ኛ|፬ኛ|4\s*th/i.test(className)) return 4;
    if (/5ኛ|፭ኛ|5\s*th/i.test(className)) return 5;
    if (/6ኛ|፮ኛ|6\s*th/i.test(className)) return 6;
    return 0;
}

function getSelectedSubject() {
    const subInput = document.getElementById('field_subject');
    return subInput ? subInput.value.trim() : '';
}

function onSubjectInputChange(val) {
    currentEditingSubject = val.trim();
    loadCurriculum();
}

function unlockSubjectInput() {
    const subInput = document.getElementById('field_subject');
    const badge = document.getElementById('subjectLockBadge');
    if (!subInput) return;
    if (subInput.hasAttribute('readonly')) {
        subInput.removeAttribute('readonly');
        subInput.classList.add('unlocked');
        if (badge) badge.style.display = 'none';
        subInput.focus();
    } else {
        subInput.setAttribute('readonly', 'readonly');
        subInput.classList.remove('unlocked');
        if (badge) badge.style.display = 'inline-block';
    }
}

function onClassChange() {
    const classSelect = document.getElementById('field_class_id');
    const subjectInput = document.getElementById('field_subject');
    if (!classSelect || !subjectInput) return;

    const opt = classSelect.options[classSelect.selectedIndex];
    const assignedSub = opt ? (opt.getAttribute('data-subject') || '') : '';

    if (assignedSub) {
        subjectInput.value = assignedSub;
        currentEditingSubject = assignedSub;
    }
    currentEditingChapter = '';
    currentEditingSubTopic = '';

    loadCurriculum();
}

async function loadCurriculum() {
    const classSelect = document.getElementById('field_class_id');
    const chapterSelect = document.getElementById('select_chapter');
    const subTopicSelect = document.getElementById('select_sub_topic');
    const statusEl = document.getElementById('curriculumStatus');

    if (!classSelect || !chapterSelect) return;
    const classId = classSelect.value;
    const subject = getSelectedSubject();

    if (!classId) return;

    if (!subject) {
        chapterSelect.innerHTML = '<option value="">-- መጀመሪያ የትምህርት ዓይነት ይምረጡ --</option>';
        subTopicSelect.innerHTML = '<option value="">-- መጀመሪያ ምዕራፍ ይምረጡ --</option>';
        statusEl.innerHTML = 'ℹ️ እባክዎ መጀመሪያ የትምህርት ዓይነት ይምረጡ::';
        curriculumTree = {};
        return;
    }

    statusEl.innerHTML = '⏳ የመጽሐፍ ማውጫ በመጫን ላይ...';
    chapterSelect.innerHTML = '<option value="">-- ምዕራፍ በመጫን ላይ... --</option>';
    subTopicSelect.innerHTML = '<option value="">-- መጀመሪያ ምዕራፍ ይምረጡ --</option>';

    try {
        const res = await fetch(`api/get_curriculum.php?class_id=${encodeURIComponent(classId)}&subject=${encodeURIComponent(subject)}`);
        const data = await res.json();

        if (data.success && data.chapters && data.chapters.length > 0) {
            curriculumTree = data.tree || {};
            statusEl.innerHTML = `📚 የ${data.grade_level}ኛ ክፍል ${data.subject} መማሪያ መጽሐፍ ማውጫ (${data.chapters.length} ምዕራፎች)`;
            
            // If in custom mode, switch back to dropdowns
            if (isCustomMode) {
                toggleCustomInputs();
            }

            chapterSelect.innerHTML = '<option value="">-- ምዕራፍ ይምረጡ --</option>';
            let foundSavedChapter = false;

            data.chapters.forEach(chap => {
                const opt = document.createElement('option');
                opt.value = chap;
                opt.textContent = chap;
                if (currentEditingChapter && (currentEditingChapter.trim() === chap.trim())) {
                    opt.selected = true;
                    foundSavedChapter = true;
                }
                chapterSelect.appendChild(opt);
            });

            // If editing a plan with a chapter not in standard list, append it
            if (currentEditingChapter && !foundSavedChapter) {
                const customOpt = document.createElement('option');
                customOpt.value = currentEditingChapter;
                customOpt.textContent = currentEditingChapter + ' (የተመዘገበ)';
                customOpt.selected = true;
                chapterSelect.appendChild(customOpt);
            }

            // Populate subtopics based on current selected chapter
            populateSubTopics(chapterSelect.value);

        } else {
            curriculumTree = {};
            statusEl.innerHTML = `ℹ️ ለ${subject} የተዘጋጀ የመጽሐፍ ማውጫ አልተገኘም፤ እባክዎ በእጅ ይጻፉ::`;
            if (!isCustomMode) {
                toggleCustomInputs();
            }
        }
    } catch (e) {
        console.error('Curriculum load error:', e);
        statusEl.innerHTML = '⚠️ ማውጫ መጫን አልተቻለም፤ በእጅ መጻፍ ይችላሉ::';
        if (!isCustomMode) {
            toggleCustomInputs();
        }
    }
}

function populateSubTopics(selectedChapter) {
    const subTopicSelect = document.getElementById('select_sub_topic');
    subTopicSelect.innerHTML = '';

    if (!selectedChapter || !curriculumTree[selectedChapter]) {
        if (selectedChapter && currentEditingSubTopic) {
            const opt = document.createElement('option');
            opt.value = currentEditingSubTopic;
            opt.textContent = currentEditingSubTopic;
            opt.selected = true;
            subTopicSelect.appendChild(opt);
            return;
        }
        subTopicSelect.innerHTML = '<option value="">-- መጀመሪያ ምዕራፍ ይምረጡ --</option>';
        return;
    }

    const subTopics = curriculumTree[selectedChapter];
    subTopicSelect.innerHTML = '<option value="">-- ንዑስ ርዕስ ይምረጡ --</option>';
    let foundSavedSub = false;

    subTopics.forEach(st => {
        const opt = document.createElement('option');
        opt.value = st;
        opt.textContent = st;
        if (currentEditingSubTopic && (currentEditingSubTopic.trim() === st.trim())) {
            opt.selected = true;
            foundSavedSub = true;
        }
        subTopicSelect.appendChild(opt);
    });

    if (currentEditingSubTopic && !foundSavedSub) {
        const customOpt = document.createElement('option');
        customOpt.value = currentEditingSubTopic;
        customOpt.textContent = currentEditingSubTopic + ' (የተመዘገበ)';
        customOpt.selected = true;
        subTopicSelect.appendChild(customOpt);
    }
}

function onChapterSelect(val) {
    currentEditingChapter = val;
    currentEditingSubTopic = '';
    populateSubTopics(val);
}

function onSubTopicSelect(val) {
    currentEditingSubTopic = val;
}

function toggleCustomInputs() {
    isCustomMode = !isCustomMode;
    const chapSelect = document.getElementById('select_chapter');
    const subSelect = document.getElementById('select_sub_topic');
    const chapCustomWrap = document.getElementById('chapterCustomWrap');
    const subCustomWrap = document.getElementById('subTopicCustomWrap');
    const customChapInput = document.getElementById('custom_chapter');
    const customSubInput = document.getElementById('custom_sub_topic');
    const toggleBtn = document.getElementById('toggleCustomBtn');

    if (isCustomMode) {
        chapSelect.style.display = 'none';
        chapSelect.removeAttribute('name');
        chapSelect.removeAttribute('required');

        subSelect.style.display = 'none';
        subSelect.removeAttribute('name');
        subSelect.removeAttribute('required');

        chapCustomWrap.style.display = 'block';
        customChapInput.setAttribute('name', 'chapter');
        customChapInput.value = chapSelect.value || customChapInput.value || currentEditingChapter;

        subCustomWrap.style.display = 'block';
        customSubInput.setAttribute('name', 'sub_topic');
        customSubInput.setAttribute('required', 'required');
        customSubInput.value = subSelect.value || customSubInput.value || currentEditingSubTopic;

        toggleBtn.textContent = '📚 ከመጽሐፉ ማውጫ ለመምረጥ ተመለስ';
    } else {
        chapCustomWrap.style.display = 'none';
        customChapInput.removeAttribute('name');

        subCustomWrap.style.display = 'none';
        customSubInput.removeAttribute('name');
        customSubInput.removeAttribute('required');

        chapSelect.style.display = 'block';
        chapSelect.setAttribute('name', 'chapter');
        chapSelect.setAttribute('required', 'required');

        subSelect.style.display = 'block';
        subSelect.setAttribute('name', 'sub_topic');
        subSelect.setAttribute('required', 'required');

        toggleBtn.textContent = '✏️ ርዕስ በእጅ ለመጻፍ';
    }
}

function onCustomChapterInput(val) {
    currentEditingChapter = val;
}

function onCustomSubTopicInput(val) {
    currentEditingSubTopic = val;
}

function applyOcrTopics(ocrChap, ocrSub) {
    const chapSelect = document.getElementById('select_chapter');
    let matchedChap = false;

    if (chapSelect && !isCustomMode) {
        for (let i = 0; i < chapSelect.options.length; i++) {
            const optVal = chapSelect.options[i].value;
            if (optVal && (optVal.includes(ocrChap) || ocrChap.includes(optVal))) {
                chapSelect.selectedIndex = i;
                onChapterSelect(optVal);
                matchedChap = true;
                break;
            }
        }
    }

    if (matchedChap && ocrSub) {
        const subSelect = document.getElementById('select_sub_topic');
        let matchedSub = false;
        for (let j = 0; j < subSelect.options.length; j++) {
            const subVal = subSelect.options[j].value;
            if (subVal && (subVal.includes(ocrSub) || ocrSub.includes(subVal))) {
                subSelect.selectedIndex = j;
                onSubTopicSelect(subVal);
                matchedSub = true;
                break;
            }
        }
        if (!matchedSub) {
            const newOpt = document.createElement('option');
            newOpt.value = ocrSub;
            newOpt.textContent = ocrSub;
            newOpt.selected = true;
            subSelect.appendChild(newOpt);
            onSubTopicSelect(ocrSub);
        }
    } else if (ocrChap || ocrSub) {
        if (!isCustomMode) {
            toggleCustomInputs();
        }
        if (ocrChap) document.getElementById('custom_chapter').value = ocrChap;
        if (ocrSub) document.getElementById('custom_sub_topic').value = ocrSub;
    }
}

document.addEventListener('DOMContentLoaded', function() {
    loadCurriculum();
});
</script>
</body>
</html>
<?php mysqli_close($conn); ?>
