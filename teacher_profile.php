<?php
require_once 'db.php';
requireLogin();

if (!isAdmin() && !isTeacher()) {
    header("Location: index.php");
    exit();
}

$message = '';
$error = '';

$is_admin = isAdmin();
$logged_user_id = intval($_SESSION['user_id'] ?? 0);

// Determine teacher ID
if (isTeacher()) {
    $teacher_id = $logged_user_id;
} else {
    $teacher_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
}

if ($teacher_id <= 0) {
    header("Location: " . ($is_admin ? "manage_teachers.php" : "dashboard_teacher.php"));
    exit();
}

// Get teacher info
$teacher_query = "SELECT u.*, 
                  (SELECT COUNT(*) FROM teacher_documents WHERE teacher_id = u.id) as doc_count
                  FROM users u 
                  WHERE u.id = ? AND u.role = 'teacher'";
$teacher = dbFetchOne($conn, $teacher_query, "i", [$teacher_id]);

if (!$teacher) {
    header("Location: " . ($is_admin ? "manage_teachers.php" : "dashboard_teacher.php"));
    exit();
}

$teacher_name = $teacher['name'];
$teacher_phone = $teacher['phone'] ?: '---';
$teacher_username = $teacher['username'];
$teacher_photo = $teacher['photo'] ?: 'images/icon.png';

// Ensure upload directories exist
if (!is_dir('uploads/teachers')) {
    @mkdir('uploads/teachers', 0777, true);
}
if (!is_dir('uploads/documents')) {
    @mkdir('uploads/documents', 0777, true);
}
if (!is_dir('uploads/profile_requests')) {
    @mkdir('uploads/profile_requests', 0777, true);
}

// Helper for localized upload error message
function getTeacherUploadErrorMessage($errorCode) {
    switch ($errorCode) {
        case UPLOAD_ERR_INI_SIZE:
        case UPLOAD_ERR_FORM_SIZE:
            return "የተመረጠው ፎቶ መጠን በጣም ትልቅ ነው (ከ 25MB በታች መሆን አለበት)!";
        case UPLOAD_ERR_PARTIAL:
            return "ፎቶው በከፊል ብቻ ነው የተጫነው፤ እባክዎ ደግመው ይሞክሩ።";
        case UPLOAD_ERR_NO_FILE:
            return "ምንም ፎቶ አልተመረጠም!";
        case UPLOAD_ERR_NO_TMP_DIR:
            return "ጊዜያዊ ማከማቻ አቃፊ አልተገኘም (Missing temporary folder)!";
        case UPLOAD_ERR_CANT_WRITE:
            return "ፎቶውን መጻፍ አልተቻለም (Failed to write file to disk)!";
        case UPLOAD_ERR_EXTENSION:
            return "የፋይል ጭነቱ በሰርቨር ቅጥያ ተቋርጧል (File upload stopped by extension)!";
        default:
            return "ያልታወቀ የምስል ጭነት ስህተት ተከስቷል (ስህተት ኮድ: " . intval($errorCode) . ")!";
    }
}

// Handle Direct Profile Photo Upload (Admin OR Teacher for their own profile)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile_photo'])) {
    if (!$is_admin && $logged_user_id !== $teacher_id) {
        $error = "ይህን ፎቶ የመቀየር ፈቃድ የለዎትም!";
    } elseif (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = "የደህንነት ማረጋገጫ አልተሳካም!";
    } else {
        if (!isset($_FILES['profile_photo']) || $_FILES['profile_photo']['error'] === UPLOAD_ERR_NO_FILE) {
            $error = "እባክዎ የሚጫን ፎቶ ይምረጡ!";
        } elseif ($_FILES['profile_photo']['error'] !== UPLOAD_ERR_OK) {
            $error = getTeacherUploadErrorMessage($_FILES['profile_photo']['error']);
        } else {
            $file = $_FILES['profile_photo'];
            $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $isRealImage = @getimagesize($file['tmp_name']) !== false;

            if (!$isRealImage || !in_array($ext, $allowed)) {
                $error = "እባክዎ ትክክለኛ የምስል ፋይል ይምረጡ (JPG, PNG, WebP)!";
            } elseif ($file['size'] > 26214400) { // 25MB
                $error = "የፎቶው መጠን ከ 25MB መብለጥ የለበትም!";
            } else {
                $new_name = 'teacher_' . $teacher_id . '_' . time() . '_' . rand(100, 999) . '.' . $ext;
                $target_path = 'uploads/teachers/' . $new_name;

                if (move_uploaded_file($file['tmp_name'], $target_path)) {
                    $updated = dbExecute($conn, "UPDATE users SET photo = ? WHERE id = ?", "si", [$target_path, $teacher_id]);
                    if ($updated) {
                        $teacher['photo'] = $target_path;
                        $teacher_photo = $target_path;
                        if ($logged_user_id === $teacher_id) {
                            $_SESSION['photo'] = $target_path;
                        }
                        $message = "የመገለጫ ፎቶው በትክክል ተቀይሯል!";
                    } else {
                        $error = "የዳታቤዝ መረጃ ማሻሻል አልተቻለም!";
                    }
                } else {
                    $error = "ፎቶውን በ uploads/teachers ማከማቻ ማስቀመጥ አልተቻለም! የአቃፊ ፈቃድን ያረጋግጡ።";
                }
            }
        }
    }
}

// Handle Profile Change Request from Teacher (Teachers submit request to Admin)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_change_request'])) {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = "የደህንነት ማረጋገጫ አልተሳካም!";
    } else {
        $req_name = trim($_POST['requested_name'] ?? '');
        $req_phone = trim($_POST['requested_phone'] ?? '');
        $req_reason = trim($_POST['change_reason'] ?? '');
        $req_photo_path = null;

        // Check if photo is uploaded
        if (!empty($_FILES['requested_photo']['name'])) {
            if ($_FILES['requested_photo']['error'] !== UPLOAD_ERR_OK) {
                $error = getTeacherUploadErrorMessage($_FILES['requested_photo']['error']);
            } else {
                $file = $_FILES['requested_photo'];
                $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
                $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                $isRealImage = @getimagesize($file['tmp_name']) !== false;

                if ($isRealImage && $file['size'] <= 26214400 && in_array($ext, $allowed)) {
                    $new_name = 'req_teacher_' . $teacher_id . '_' . time() . '_' . rand(100, 999) . '.' . $ext;
                    $target_path = 'uploads/profile_requests/' . $new_name;
                    if (move_uploaded_file($file['tmp_name'], $target_path)) {
                        $req_photo_path = $target_path;
                    } else {
                        $error = "ፎቶ መጫን አልተቻለም! የአቃፊ ፈቃድ ያረጋግጡ።";
                    }
                } else {
                    $error = "እባክዎ ትክክለኛ የምስል ፋይል ይምረጡ (JPG, PNG, WebP - ከ 25MB በታች)!";
                }
            }
        }

        // Only proceed if no error and at least one change is requested
        if (empty($error)) {
            $hasChange = ($req_name !== '' && $req_name !== $teacher['name']) ||
                          ($req_phone !== '' && $req_phone !== $teacher['phone']) ||
                          ($req_photo_path !== null);

            if (!$hasChange && empty($req_reason)) {
                $error = "ምንም የተቀየረ መረጃ አላስገቡም!";
            } else {
                $saved = dbExecute(
                    $conn,
                    "INSERT INTO profile_change_requests (teacher_id, requested_name, requested_phone, requested_photo, reason, status) 
                     VALUES (?, ?, ?, ?, ?, 'pending')",
                    "issss",
                    [$teacher_id, $req_name ?: $teacher['name'], $req_phone ?: $teacher['phone'], $req_photo_path, $req_reason]
                );

                if ($saved) {
                    // Send real notification to admin
                    $admin_users = dbFetchAll($conn, "SELECT id FROM users WHERE role = 'admin'");
                    $admin_targets = [];
                    foreach ($admin_users as $adm) {
                        $admin_targets[] = ['user_id' => intval($adm['id'])];
                    }
                    createNotification(
                        $conn,
                        "📋 የመረጃ ለውጥ ጥያቄ ቀርቧል",
                        "መምህር {$teacher_name} የመረጃ ለውጥ ጥያቄ አቅርበዋል። እባክዎ በመምህራን አስተዳደር ገጽ ይገምግሙ።",
                        $admin_targets,
                        'normal',
                        null,
                        'manage_teachers.php#requests'
                    );

                    $message = "የመረጃ ለውጥ ጥያቄዎ በትክክል ለትምህርት ክፍል ተልኳል! ትምህርት ክፍሉ ሲያጸድቀው መረጃዎ በራስ-ሰር ይሻሻላል።";
                } else {
                    $error = "ጥያቄውን መላክ አልተቻለም! እባክዎ እንደገና ይሞክሩ።";
                }
            }
        }
    }
}

// Handle Approve Request by Admin
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['approve_profile_request']) && $is_admin) {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = "የደህንነት ማረጋገጫ አልተሳካም!";
    } else {
        $req_id = intval($_POST['request_id'] ?? 0);
        $req = dbFetchOne($conn, "SELECT r.*, u.name as current_name FROM profile_change_requests r JOIN users u ON r.teacher_id = u.id WHERE r.id = ? AND r.status = 'pending'", "i", [$req_id]);
        if ($req) {
            $upName = $req['requested_name'] ?: $req['current_name'];
            $upPhone = $req['requested_phone'];
            $upPhoto = $req['requested_photo'];
            
            if ($upPhoto) {
                dbExecute($conn, "UPDATE users SET name = ?, phone = ?, photo = ? WHERE id = ?", "sssi", [$upName, $upPhone, $upPhoto, $req['teacher_id']]);
                $teacher['photo'] = $upPhoto;
                $teacher_photo = $upPhoto;
            } else {
                dbExecute($conn, "UPDATE users SET name = ?, phone = ? WHERE id = ?", "ssi", [$upName, $upPhone, $req['teacher_id']]);
            }
            
            dbExecute($conn, "UPDATE profile_change_requests SET status = 'approved', reviewed_by = ?, reviewed_at = NOW() WHERE id = ?", "ii", [$_SESSION['user_id'], $req_id]);
            
            createNotification(
                $conn,
                "✅ የመረጃ ለውጥ ጥያቄዎ ጸድቋል",
                "ያቀረቡት የመረጃ ለውጥ ጥያቄ በትምህርት ክፍሉ ተቀባይነት አግኝቶ መረጃዎ ተሻሽሏል።",
                [['user_id' => $req['teacher_id']]],
                'normal',
                null,
                'teacher_profile.php'
            );
            $teacher['name'] = $upName;
            $teacher['phone'] = $upPhone;
            $teacher_name = $upName;
            $teacher_phone = $upPhone ?: '---';
            $message = "የመምህር መረጃ ለውጥ ጥያቄ በትክክል ጸድቋል!";
        }
    }
}

// Handle Reject Request by Admin
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reject_profile_request']) && $is_admin) {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = "የደህንነት ማረጋገጫ አልተሳካም!";
    } else {
        $req_id = intval($_POST['request_id'] ?? 0);
        $notes = trim($_POST['admin_notes'] ?? '');
        $req = dbFetchOne($conn, "SELECT * FROM profile_change_requests WHERE id = ? AND status = 'pending'", "i", [$req_id]);
        if ($req) {
            dbExecute($conn, "UPDATE profile_change_requests SET status = 'rejected', admin_notes = ?, reviewed_by = ?, reviewed_at = NOW() WHERE id = ?", "sii", [$notes, $_SESSION['user_id'], $req_id]);
            
            createNotification(
                $conn,
                "⚠️ የመረጃ ለውጥ ጥያቄዎ ውድቅ ተደርጓል",
                "ያቀረቡት የመረጃ ለውጥ ጥያቄ ተቀባይነት አላገኘም። " . ($notes ? "ማብራሪያ: $notes" : ""),
                [['user_id' => $req['teacher_id']]],
                'normal',
                null,
                'teacher_profile.php'
            );
            $message = "የመረጃ ለውጥ ጥያቄው ውድቅ ተደርጓል።";
        }
    }
}

// Handle Admin Directly Updating Teacher Info (Admin only)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['admin_direct_update']) && $is_admin) {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = "የደህንነት ማረጋገጫ አልተሳካም!";
    } else {
        $adm_name = trim($_POST['name'] ?? '');
        $adm_phone = trim($_POST['phone'] ?? '');
        $adm_status = trim($_POST['admin_status'] ?? 'active');
        $adm_password = trim($_POST['admin_new_password'] ?? '');

        if ($adm_name) {
            dbExecute($conn, "UPDATE users SET name = ?, phone = ?, status = ? WHERE id = ?", "sssi", [$adm_name, $adm_phone, $adm_status, $teacher_id]);
            $teacher['name'] = $adm_name;
            $teacher['phone'] = $adm_phone;
            $teacher['status'] = $adm_status;
            $teacher_name = $adm_name;
            $teacher_phone = $adm_phone ?: '---';

            // Check if photo is uploaded directly
            if (!empty($_FILES['admin_photo']['name'])) {
                if ($_FILES['admin_photo']['error'] !== UPLOAD_ERR_OK) {
                    $error = getTeacherUploadErrorMessage($_FILES['admin_photo']['error']);
                } else {
                    $file = $_FILES['admin_photo'];
                    $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
                    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                    $isRealImage = @getimagesize($file['tmp_name']) !== false;

                    if ($isRealImage && $file['size'] <= 26214400 && in_array($ext, $allowed)) {
                        $new_name = 'teacher_' . $teacher_id . '_' . time() . '_' . rand(100, 999) . '.' . $ext;
                        $target_path = 'uploads/teachers/' . $new_name;
                        if (move_uploaded_file($file['tmp_name'], $target_path)) {
                            dbExecute($conn, "UPDATE users SET photo = ? WHERE id = ?", "si", [$target_path, $teacher_id]);
                            $teacher['photo'] = $target_path;
                            $teacher_photo = $target_path;
                        } else {
                            $error = "ፎቶውን ማስቀመጥ አልተቻለም! እባክዎ የ uploads/teachers አቃፊ ፈቃድን ያረጋግጡ።";
                        }
                    } else {
                        $error = "እባክዎ ትክክለኛ የምስል ፋይል ይምረጡ (JPG, PNG, WebP - ከ 25MB በታች)!";
                    }
                }
            }

            // Check if new password is provided
            if ($adm_password !== '') {
                $hashed_pwd = password_hash($adm_password, PASSWORD_DEFAULT);
                dbExecute($conn, "UPDATE users SET password = ? WHERE id = ?", "si", [$hashed_pwd, $teacher_id]);
            }

            if (empty($error)) {
                $message = "የመምህር መረጃ በቀጥታ ተሻሽሏል!";
            }
        } else {
            $error = "እባክዎ የመምህሩን ሙሉ ስም ያስገቡ!";
        }
    }
}

// Handle document upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_doc']) && isset($_FILES['document'])) {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = "የደህንነት ማረጋገጫ አልተሳካም!";
    } else {
        $file = $_FILES['document'];
        $doc_name = trim($_POST['doc_name'] ?? '') ?: $file['name'];
        $allowed = ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png', 'xls', 'xlsx', 'ppt', 'pptx', 'txt'];
        $allowedMimes = [
            'application/pdf', 'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'image/jpeg', 'image/png',
            'application/vnd.ms-excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'application/vnd.ms-powerpoint',
            'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            'text/plain',
        ];
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $realMime = $file['error'] === 0 ? @mime_content_type($file['tmp_name']) : false;

        if ($file['error'] === 0 && $realMime && in_array($realMime, $allowedMimes) && $file['size'] < 20971520 && in_array($ext, $allowed)) {
            $new_name = 'doc_' . $teacher_id . '_' . time() . '.' . $ext;
            $upload_path = 'uploads/documents/' . $new_name;
            
            if (move_uploaded_file($file['tmp_name'], $upload_path)) {
                $file_type = $ext;
                dbExecute(
                    $conn,
                    "INSERT INTO teacher_documents (teacher_id, document_name, file_path, file_type, uploaded_at) 
                     VALUES (?, ?, ?, ?, NOW())",
                    "isss",
                    [$teacher_id, $doc_name, $upload_path, $file_type]
                );
                $message = "ሰነዱ በትክክል ተጭኗል!";
            } else {
                $error = "ሰነድ መጫን አልተቻለም!";
            }
        } else {
            $error = "እባክዎ ትክክለኛ ፋይል ይምረጡ (PDF, Word, Excel, Images - max 20MB)!";
        }
    }
}

// Handle delete document
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_doc'])) {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = "የደህንነት ማረጋገጫ አልተሳካም!";
    } else {
        $doc_id = intval($_POST['doc_id'] ?? 0);
        $doc = dbFetchOne($conn, "SELECT file_path FROM teacher_documents WHERE id = ? AND teacher_id = ?", "ii", [$doc_id, $teacher_id]);
        if ($doc) {
            if (file_exists($doc['file_path'])) {
                @unlink($doc['file_path']);
            }
            dbExecute($conn, "DELETE FROM teacher_documents WHERE id = ?", "i", [$doc_id]);
            $message = "ሰነድ ተሰርዟል!";
        }
    }
}

// Get teacher documents
$documents = dbFetchAll($conn, "SELECT * FROM teacher_documents WHERE teacher_id = ? ORDER BY uploaded_at DESC", "i", [$teacher_id]);

// Get current semester assignments with subjects
$current_semester = getCurrentSemester($conn);
$semester_id = $current_semester ? intval($current_semester['id']) : 0;
$current_assignments = getTeacherClasses($conn, $teacher_id, $semester_id);

// Get teaching history
$history_query = "SELECT c.name as class_name, COALESCE(s.name, tc.subject_name, 'ያልተገለጸ') as subject_name, sem.name as semester_name, sem.ethiopian_year, sem.semester_number
                  FROM teacher_class tc
                  JOIN classes c ON tc.class_id = c.id
                  JOIN semesters sem ON tc.semester_id = sem.id
                  LEFT JOIN subjects s ON tc.subject_id = s.id
                  WHERE tc.teacher_id = ?
                  ORDER BY sem.ethiopian_year DESC, sem.semester_number DESC";
$history = dbFetchAll($conn, $history_query, "i", [$teacher_id]);

// Get profile change requests for this teacher
$pending_request = dbFetchOne(
    $conn,
    "SELECT * FROM profile_change_requests WHERE teacher_id = ? AND status = 'pending' ORDER BY created_at DESC LIMIT 1",
    "i",
    [$teacher_id]
);
$past_requests = dbFetchAll(
    $conn,
    "SELECT * FROM profile_change_requests WHERE teacher_id = ? AND status != 'pending' ORDER BY created_at DESC LIMIT 5",
    "i",
    [$teacher_id]
);

$nav_active = $is_admin ? 'manage_teachers' : 'teacher_profile';
?>
<!DOCTYPE html>
<html lang="am">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>የመምህር መረጃ | <?php echo htmlspecialchars($teacher_name); ?></title>
    <?php include 'pwa_head.php'; ?>
    <style>
        :root {
            --brown-dark: #8B4513;
            --brown-medium: #A52A2A;
            --gold-primary: #FFD700;
            --gold-dark: #DAA520;
            --gold-pale: #FFF8DC;
            --success: #10B981;
            --error: #EF4444;
            --info: #3B82F6;
            --warning: #F59E0B;
            --bg: #FAF9F6;
            --card-shadow: 0 10px 25px rgba(139, 69, 19, 0.08);
        }
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Segoe UI', system-ui, sans-serif; }
        body { background: var(--bg); min-height: 100vh; color: #333; }

        .container { max-width: 960px; margin: 20px auto; padding: 0 16px 80px; }

        /* Navigation Buttons */
        .top-nav-bar {
            display: flex; justify-content: space-between; align-items: stretch;
            margin-bottom: 20px; flex-wrap: wrap; gap: 12px;
        }
        .btn-action {
            display: inline-flex; align-items: center; gap: 8px;
            padding: 9px 18px; border-radius: 25px; font-size: 13px;
            font-weight: 600; text-decoration: none; transition: all 0.3s ease;
            cursor: pointer; border: none;
        }
        .btn-gold { background: var(--gold-primary); color: var(--brown-dark); }
        .btn-gold:hover { background: var(--gold-dark); transform: translateY(-2px); }
        .btn-outline { background: white; border: 1.5px solid var(--gold-dark); color: var(--brown-dark); }
        .btn-outline:hover { background: var(--gold-pale); }

        /* Alerts */
        .alert {
            padding: 14px 18px; border-radius: 12px; margin-bottom: 20px;
            display: flex; align-items: center; gap: 10px; font-size: 14px;
            box-shadow: 0 3px 10px rgba(0,0,0,0.04);
        }
        .alert-success { background: #ECFDF5; color: #065F46; border-left: 5px solid var(--success); }
        .alert-error { background: #FEF2F2; color: #991B1B; border-left: 5px solid var(--error); }

        /* Hero Profile Card */
        .hero-profile-card {
            background: linear-gradient(135deg, #78350F 0%, #92400E 50%, #B45309 100%);
            border-radius: 20px; color: white; padding: 30px;
            box-shadow: var(--card-shadow); margin-bottom: 24px;
            border: 2px solid var(--gold-primary); position: relative; overflow: hidden;
        }
        .hero-profile-card::after {
            content: "⛪"; position: absolute; right: -15px; bottom: -20px;
            font-size: 140px; opacity: 0.08; pointer-events: none;
        }
        .hero-content {
            display: flex; align-items: center; gap: 24px; flex-wrap: wrap;
        }
        .avatar-box {
            position: relative; width: 110px; height: 110px;
            border-radius: 50%; border: 4px solid var(--gold-primary);
            box-shadow: 0 4px 15px rgba(0,0,0,0.3);
            background: white; flex-shrink: 0;
        }
        .avatar-box img { width: 100%; height: 100%; object-fit: cover; border-radius: 50%; display: block; }
        .avatar-edit-btn {
            position: absolute; bottom: 0; right: 0;
            width: 34px; height: 34px; border-radius: 50%;
            background: var(--brown-dark); color: #FFD700;
            border: 2px solid #FFD700; display: flex; align-items: center;
            justify-content: center; font-size: 15px; cursor: pointer;
            box-shadow: 0 3px 8px rgba(0,0,0,0.4); transition: all 0.2s ease;
            z-index: 10;
        }
        .avatar-edit-btn:hover { transform: scale(1.1); background: #5C2404; color: #FFF; }
        .teacher-meta h1 { font-size: 24px; font-weight: 700; color: #FEF3C7; margin-bottom: 6px; }
        .teacher-meta .badge {
            display: inline-block; background: rgba(254, 243, 199, 0.2);
            border: 1px solid rgba(254, 243, 199, 0.4); color: #FFFBEB;
            padding: 4px 12px; border-radius: 20px; font-size: 12px;
            font-weight: 600; margin-bottom: 8px;
        }
        .teacher-meta .sub-info { font-size: 13px; opacity: 0.9; }

        /* Stats Row */
        .stats-grid {
            display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 14px; margin-bottom: 24px;
        }
        .stat-card {
            background: white; border-radius: 14px; padding: 18px;
            border: 1px solid #E5E7EB; box-shadow: 0 2px 8px rgba(0,0,0,0.04);
            display: flex; align-items: center; gap: 14px;
        }
        .stat-icon {
            width: 46px; height: 46px; border-radius: 12px;
            background: var(--gold-pale); color: var(--brown-dark);
            display: flex; align-items: center; justify-content: center;
            font-size: 22px; flex-shrink: 0;
        }
        .stat-data .label { font-size: 11px; color: #6B7280; text-transform: uppercase; font-weight: 600; }
        .stat-data .value { font-size: 18px; font-weight: 700; color: var(--brown-dark); margin-top: 2px; }

        /* Content Section Cards */
        .card {
            background: white; border-radius: 16px; padding: 24px;
            border: 1px solid #E5E7EB; box-shadow: var(--card-shadow);
            margin-bottom: 24px;
        }
        .card-header {
            display: flex; justify-content: space-between; align-items: center;
            padding-bottom: 14px; border-bottom: 2px solid var(--gold-pale);
            margin-bottom: 18px;
        }
        .card-title {
            font-size: 17px; font-weight: 700; color: var(--brown-dark);
            display: flex; align-items: center; gap: 8px;
        }

        /* Detail List */
        .detail-row {
            display: flex; justify-content: space-between; align-items: center;
            padding: 12px 0; border-bottom: 1px solid #F3F4F6; font-size: 14px;
        }
        .detail-row:last-child { border-bottom: none; }
        .detail-label { color: #6B7280; font-weight: 500; }
        .detail-val { color: var(--brown-dark); font-weight: 700; }

        /* Badges */
        .chip {
            display: inline-flex; align-items: center; gap: 6px;
            padding: 6px 14px; border-radius: 20px; font-size: 12px;
            font-weight: 600; background: var(--gold-pale); color: var(--brown-dark);
            border: 1px solid var(--gold-primary); margin: 3px;
        }

        /* Pending Request Notice Banner (Teacher View) */
        .request-banner {
            background: #FFFBEB; border: 1.5px solid var(--warning);
            border-radius: 14px; padding: 18px; margin-bottom: 20px;
            color: #78350F;
        }
        .request-banner-title {
            font-weight: 700; color: #92400E; font-size: 15px;
            display: flex; align-items: center; gap: 8px; margin-bottom: 8px;
        }

        /* Admin Review Pending Card */
        .pending-review-card {
            border: 2px solid #F59E0B;
            background: #FFFBEB;
        }
        .pending-review-card .card-header {
            border-color: #FDE68A;
        }
        .pending-review-card .card-title {
            color: #92400E;
        }

        /* Admin Direct Edit Card */
        .admin-edit-card {
            border: 2px solid #0284C7;
            background: #F0F9FF;
        }
        .admin-edit-card .card-header {
            border-color: #BAE6FD;
        }
        .admin-edit-card .card-title {
            color: #0369A1;
        }

        /* Admin Reject Box */
        .admin-reject-box {
            display: none;
            margin-top: 12px;
            padding: 14px;
            background: #FFF1F2;
            border-radius: 10px;
            border: 1px solid #FECACA;
        }

        /* Past Requests History Cards */
        .req-history-card {
            padding: 12px 16px;
            border-radius: 10px;
            margin-bottom: 10px;
            font-size: 13px;
            transition: all 0.2s ease;
        }
        .req-history-card.approved {
            background: #ECFDF5;
            border: 1px solid #A7F3D0;
            color: #065F46;
        }
        .req-history-card.rejected {
            background: #FEF2F2;
            border: 1px solid #FECACA;
            color: #991B1B;
        }
        .req-history-card .req-date {
            color: #6B7280;
            font-size: 11.5px;
        }
        .req-history-card .req-details {
            margin-top: 6px;
            color: #374151;
            line-height: 1.6;
        }

        /* Rejection Admin Note / Feedback */
        .req-admin-note {
            margin-top: 8px;
            padding: 10px 14px;
            border-radius: 8px;
            background: #FEE2E2;
            border-left: 4px solid #DC2626;
            color: #7F1D1D;
            font-size: 13px;
            line-height: 1.5;
        }
        .req-admin-note strong {
            color: #991B1B;
            display: inline-block;
            margin-bottom: 2px;
        }

        /* Form Inputs */
        .form-group { margin-bottom: 16px; }
        .form-label { display: block; font-size: 13px; font-weight: 600; color: var(--brown-dark); margin-bottom: 6px; }
        .form-control {
            width: 100%; padding: 11px 14px; border: 1.5px solid #D1D5DB;
            border-radius: 10px; font-size: 14px; transition: all 0.2s;
            background: #FFFFFF; color: #1F2937;
        }
        .form-control:focus { outline: none; border-color: var(--gold-dark); box-shadow: 0 0 0 3px rgba(218,165,32,0.15); }
        .form-text { font-size: 12px; color: #6B7280; margin-top: 4px; }

        .btn-submit {
            background: linear-gradient(135deg, var(--brown-dark) 0%, var(--brown-medium) 100%);
            color: white; border: none; padding: 12px 24px; border-radius: 10px;
            font-size: 14px; font-weight: 700; cursor: pointer; transition: all 0.3s;
            display: inline-flex; align-items: center; gap: 8px;
        }
        .btn-submit:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(139,69,19,0.25); }

        /* Documents */
        .doc-item {
            display: flex; align-items: center; gap: 12px; padding: 12px 14px;
            background: #F9FAFB; border-radius: 10px; border: 1px solid #E5E7EB;
            margin-bottom: 10px; transition: all 0.2s;
        }
        .doc-item:hover { background: var(--gold-pale); border-color: var(--gold-primary); }

        @media (max-width: 600px) {
            .hero-content { flex-direction: column; text-align: center; }
            .top-nav-bar { flex-direction: column; align-items: stretch; }
            .btn-action { justify-content: center; }
        }

        /* ============================================================
           GLOBAL HIGH-CONTRAST DARK MODE SUPPORT
           Matches html.dark-mode, html[data-theme="dark"], and body.dark-mode
           ============================================================ */
        html.dark-mode body,
        html[data-theme="dark"] body,
        body.dark-mode {
            background-color: #0B1120 !important;
            color: #E2E8F0 !important;
        }

        html.dark-mode .card,
        html[data-theme="dark"] .card,
        body.dark-mode .card,
        html.dark-mode .stat-card,
        html[data-theme="dark"] .stat-card,
        body.dark-mode .stat-card {
            background-color: #1E293B !important;
            border-color: #334155 !important;
            color: #E2E8F0 !important;
            box-shadow: 0 4px 14px rgba(0, 0, 0, 0.4) !important;
        }

        html.dark-mode .card-header,
        html[data-theme="dark"] .card-header,
        body.dark-mode .card-header {
            border-color: #334155 !important;
        }

        html.dark-mode .card-title,
        html[data-theme="dark"] .card-title,
        body.dark-mode .card-title {
            color: #FCD34D !important;
        }

        html.dark-mode .detail-row,
        html[data-theme="dark"] .detail-row,
        body.dark-mode .detail-row {
            border-color: #334155 !important;
        }

        html.dark-mode .detail-label,
        html[data-theme="dark"] .detail-label,
        body.dark-mode .detail-label {
            color: #94A3B8 !important;
        }

        html.dark-mode .detail-val,
        html[data-theme="dark"] .detail-val,
        body.dark-mode .detail-val {
            color: #F8FAFC !important;
        }

        html.dark-mode .form-label,
        html[data-theme="dark"] .form-label,
        body.dark-mode .form-label {
            color: #F1F5F9 !important;
        }

        html.dark-mode .form-control,
        html[data-theme="dark"] .form-control,
        body.dark-mode .form-control {
            background-color: #0F172A !important;
            border-color: #475569 !important;
            color: #F8FAFC !important;
        }

        html.dark-mode .form-control:focus,
        html[data-theme="dark"] .form-control:focus,
        body.dark-mode .form-control:focus {
            border-color: #F59E0B !important;
            box-shadow: 0 0 0 3px rgba(245, 158, 11, 0.25) !important;
        }

        html.dark-mode .form-text,
        html[data-theme="dark"] .form-text,
        body.dark-mode .form-text {
            color: #94A3B8 !important;
        }

        html.dark-mode .chip,
        html[data-theme="dark"] .chip,
        body.dark-mode .chip {
            background-color: #1E293B !important;
            border-color: #F59E0B !important;
            color: #FCD34D !important;
        }

        html.dark-mode .doc-item,
        html[data-theme="dark"] .doc-item,
        body.dark-mode .doc-item {
            background-color: #0F172A !important;
            border-color: #334155 !important;
            color: #F1F5F9 !important;
        }

        html.dark-mode .doc-item:hover,
        html[data-theme="dark"] .doc-item:hover,
        body.dark-mode .doc-item:hover {
            background-color: #1E293B !important;
            border-color: #F59E0B !important;
        }

        html.dark-mode .doc-item div,
        html[data-theme="dark"] .doc-item div,
        body.dark-mode .doc-item div {
            color: #E2E8F0 !important;
        }

        html.dark-mode .btn-outline,
        html[data-theme="dark"] .btn-outline,
        body.dark-mode .btn-outline {
            background: transparent !important;
            color: #FCD34D !important;
            border-color: #FCD34D !important;
        }

        html.dark-mode .stat-data .value,
        html[data-theme="dark"] .stat-data .value,
        body.dark-mode .stat-data .value {
            color: #FCD34D !important;
        }

        html.dark-mode .stat-data .label,
        html[data-theme="dark"] .stat-data .label,
        body.dark-mode .stat-data .label {
            color: #94A3B8 !important;
        }

        html.dark-mode .stat-icon,
        html[data-theme="dark"] .stat-icon,
        body.dark-mode .stat-icon {
            background-color: #334155 !important;
            color: #FCD34D !important;
        }

        html.dark-mode .top-nav-bar span,
        html[data-theme="dark"] .top-nav-bar span,
        body.dark-mode .top-nav-bar span {
            color: #FCD34D !important;
        }

        /* Admin Panels Dark Mode */
        html.dark-mode .admin-edit-card,
        html[data-theme="dark"] .admin-edit-card,
        body.dark-mode .admin-edit-card {
            background: #0B1E36 !important;
            border-color: #0284C7 !important;
        }
        html.dark-mode .admin-edit-card .card-header,
        html[data-theme="dark"] .admin-edit-card .card-header,
        body.dark-mode .admin-edit-card .card-header {
            border-color: #0369A1 !important;
        }
        html.dark-mode .admin-edit-card .card-title,
        html[data-theme="dark"] .admin-edit-card .card-title,
        body.dark-mode .admin-edit-card .card-title {
            color: #7DD3FC !important;
        }
        html.dark-mode .admin-edit-card p,
        html[data-theme="dark"] .admin-edit-card p,
        body.dark-mode .admin-edit-card p {
            color: #BAE6FD !important;
        }

        html.dark-mode .pending-review-card,
        html[data-theme="dark"] .pending-review-card,
        body.dark-mode .pending-review-card {
            background: #241A06 !important;
            border-color: #D97706 !important;
        }
        html.dark-mode .pending-review-card .card-header,
        html[data-theme="dark"] .pending-review-card .card-header,
        body.dark-mode .pending-review-card .card-header {
            border-color: #92400E !important;
        }
        html.dark-mode .pending-review-card .card-title,
        html[data-theme="dark"] .pending-review-card .card-title,
        body.dark-mode .pending-review-card .card-title {
            color: #FCD34D !important;
        }
        html.dark-mode .pending-review-card .pending-meta,
        html[data-theme="dark"] .pending-review-card .pending-meta,
        body.dark-mode .pending-review-card .pending-meta {
            color: #FEF3C7 !important;
        }

        html.dark-mode .admin-reject-box,
        html[data-theme="dark"] .admin-reject-box,
        body.dark-mode .admin-reject-box {
            background: #2A0E11 !important;
            border-color: #991B1B !important;
            color: #FECACA !important;
        }

        html.dark-mode .request-banner,
        html[data-theme="dark"] .request-banner,
        body.dark-mode .request-banner {
            background: #241A06 !important;
            border-color: #D97706 !important;
            color: #FEF3C7 !important;
        }
        html.dark-mode .request-banner .request-banner-title,
        html[data-theme="dark"] .request-banner .request-banner-title,
        body.dark-mode .request-banner .request-banner-title {
            color: #FCD34D !important;
        }
        html.dark-mode .request-banner-body,
        html[data-theme="dark"] .request-banner-body,
        body.dark-mode .request-banner-body {
            color: #FEF3C7 !important;
        }

        /* History Cards in Dark Mode */
        html.dark-mode .req-history-card.approved,
        html[data-theme="dark"] .req-history-card.approved,
        body.dark-mode .req-history-card.approved {
            background: #064E3B !important;
            border-color: #059669 !important;
            color: #A7F3D0 !important;
        }
        html.dark-mode .req-history-card.rejected,
        html[data-theme="dark"] .req-history-card.rejected,
        body.dark-mode .req-history-card.rejected {
            background: #3B0D0D !important;
            border-color: #DC2626 !important;
            color: #FCA5A5 !important;
        }
        html.dark-mode .req-history-card .req-date,
        html[data-theme="dark"] .req-history-card .req-date,
        body.dark-mode .req-history-card .req-date {
            color: #94A3B8 !important;
        }
        html.dark-mode .req-history-card .req-details,
        html[data-theme="dark"] .req-history-card .req-details,
        body.dark-mode .req-history-card .req-details {
            color: #F1F5F9 !important;
        }

        /* Reject Note in Dark Mode - High Contrast Readability! */
        html.dark-mode .req-admin-note,
        html[data-theme="dark"] .req-admin-note,
        body.dark-mode .req-admin-note {
            background: #1C0505 !important;
            border-left-color: #EF4444 !important;
            color: #FEE2E2 !important;
        }
        html.dark-mode .req-admin-note strong,
        html[data-theme="dark"] .req-admin-note strong,
        body.dark-mode .req-admin-note strong {
            color: #FCA5A5 !important;
        }

        /* Alerts in Dark Mode */
        html.dark-mode .alert-success,
        html[data-theme="dark"] .alert-success,
        body.dark-mode .alert-success {
            background: #064E3B !important;
            color: #A7F3D0 !important;
            border-left-color: #10B981 !important;
        }
        html.dark-mode .alert-error,
        html[data-theme="dark"] .alert-error,
        body.dark-mode .alert-error {
            background: #3B0D0D !important;
            color: #FCA5A5 !important;
            border-left-color: #EF4444 !important;
        }
    </style>
</head>
<body>
    <?php include 'mobile_nav.php'; ?>

    <div class="container">
        <!-- Top Navigation -->
        <div class="top-nav-bar">
            <?php if ($is_admin): ?>
                <div style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap;">
                    <a href="manage_teachers.php" class="btn-action btn-gold">
                        ⬅️ ወደ መምህራን አስተዳደር
                    </a>
                    <span style="font-size: 13px; font-weight: 600; color: #78350F;">
                        / መምህር፡ <?php echo htmlspecialchars($teacher_name); ?>
                    </span>
                </div>
                <div style="display: flex; gap: 8px; flex-wrap: wrap;">
                    <a href="manage_assignments.php" class="btn-action btn-outline">
                        📋 የክፍል ምደባዎች
                    </a>
                    <a href="#admin-edit-panel" class="btn-action" style="background: #0284C7; color: white;">
                        ⚡ መረጃ አሻሽል
                    </a>
                </div>
            <?php else: ?>
                <a href="dashboard_teacher.php" class="btn-action btn-gold">
                    🏠 ወደ ዳሽቦርድ
                </a>
                <a href="change_password.php" class="btn-action btn-outline">
                    🔑 የይለፍ ቃል ቀይር
                </a>
            <?php endif; ?>
        </div>

        <?php if ($message): ?>
            <div class="alert alert-success">✅ <?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert alert-error">⚠️ <?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
        <?php endif; ?>

        <!-- Modern Hero Profile Header -->
        <div class="hero-profile-card">
            <div class="hero-content">
                <div class="avatar-box">
                    <img src="<?php echo htmlspecialchars($teacher_photo); ?>" alt="<?php echo htmlspecialchars($teacher_name); ?>" id="currentAvatarImg" onerror="this.src='images/icon.png'">
                    <?php if ($is_admin || $logged_user_id === $teacher_id): ?>
                        <form id="avatarUploadForm" method="POST" enctype="multipart/form-data" style="display:none;">
                            <?php echo csrfField(); ?>
                            <input type="hidden" name="update_profile_photo" value="1">
                            <input type="file" name="profile_photo" id="avatarFileInput" accept="image/jpeg,image/png,image/webp,image/gif" onchange="previewAndSubmitAvatar(this);">
                        </form>
                        <button type="button" class="avatar-edit-btn" onclick="document.getElementById('avatarFileInput').click();" title="የመገለጫ ፎቶ ቀይር (Change Photo)">
                            📷
                        </button>
                    <?php endif; ?>
                </div>
                <div class="teacher-meta">
                    <div class="badge"><?php echo $is_admin ? '🛡️ የመምህራን አስተዳደር (የትምህርት ክፍል እይታ)' : '⛪ አጸደ ትጉሃን መምህር'; ?></div>
                    <h1><?php echo htmlspecialchars($teacher_name); ?></h1>
                    <div class="sub-info">
                        <span>👤 @<?php echo htmlspecialchars($teacher_username); ?></span> &bull; 
                        <span>📱 <?php echo htmlspecialchars($teacher_phone); ?></span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Quick Stats -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon">📚</div>
                <div class="stat-data">
                    <div class="label">የተመደቡ ክፍሎች (ዘንድሮ)</div>
                    <div class="value"><?php echo count($current_assignments); ?> ክፍሎች</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">📄</div>
                <div class="stat-data">
                    <div class="label">የተጫኑ ሰነዶች</div>
                    <div class="value"><?php echo count($documents); ?> ፋይሎች</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">🎓</div>
                <div class="stat-data">
                    <div class="label">ያስተማሩባቸው ሴሚስተሮች</div>
                    <div class="value"><?php echo count($history); ?> ጊዜ</div>
                </div>
            </div>
        </div>

        <!-- Profile Information Card -->
        <div class="card">
            <div class="card-header">
                <div class="card-title"><span>👤</span> የመምህር መረጃ</div>
                <span style="font-size: 12px; color: #6B7280;">ሚና፡ <strong>መምህር</strong></span>
            </div>
            <div class="detail-row">
                <span class="detail-label">ሙሉ ስም</span>
                <span class="detail-val"><?php echo htmlspecialchars($teacher_name); ?></span>
            </div>
            <div class="detail-row">
                <span class="detail-label">የተጠቃሚ ስም</span>
                <span class="detail-val">@<?php echo htmlspecialchars($teacher_username); ?></span>
            </div>
            <div class="detail-row">
                <span class="detail-label">ስልክ ቁጥር</span>
                <span class="detail-val"><?php echo htmlspecialchars($teacher_phone); ?></span>
            </div>
            <div class="detail-row">
                <span class="detail-label">የተመደቡባቸው ክፍሎችና የትምህርት ዓይነቶች</span>
                <div style="text-align: right;">
                    <?php if (!empty($current_assignments)): ?>
                        <?php foreach ($current_assignments as $ca): ?>
                            <span class="chip">
                                📖 <?php echo htmlspecialchars($ca['class_name']); ?>
                                <?php if (!empty($ca['assigned_subject_name'])): ?>
                                    - <strong style="color:var(--brown-dark);"><?php echo htmlspecialchars($ca['assigned_subject_name']); ?></strong>
                                <?php endif; ?>
                            </span>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <span style="color:#9CA3AF; font-size:13px;">ለዚህ ሴሚስተር የተመደበ ክፍል የለም</span>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <?php if ($is_admin): ?>
            <!-- ADMIN VIEW: PROFILE CHANGE REQUEST REVIEW (IF PENDING) -->
            <?php if ($pending_request): ?>
                <div class="card pending-review-card">
                    <div class="card-header">
                        <div class="card-title"><span>⏳</span> በመጠባበቅ ላይ ያለ የመረጃ ለውጥ ጥያቄ</div>
                        <span class="chip" style="font-weight:700;">የትምህርት ክፍል ውሳኔ ይፈልጋል</span>
                    </div>
                    <div class="pending-meta" style="font-size: 14px; line-height: 1.8; margin-bottom: 16px;">
                        <div><strong>የቀረበበት ቀን፡</strong> <?php echo date('M d, Y h:i A', strtotime($pending_request['created_at'])); ?></div>
                        <?php if ($pending_request['requested_name'] && $pending_request['requested_name'] !== $teacher['name']): ?>
                            <div><strong>የተጠየቀ አዲስ ስም፡</strong> <span style="font-weight:700;"><?php echo htmlspecialchars($pending_request['requested_name']); ?></span> (የነበረው፡ <?php echo htmlspecialchars($teacher['name']); ?>)</div>
                        <?php endif; ?>
                        <?php if ($pending_request['requested_phone'] && $pending_request['requested_phone'] !== $teacher['phone']): ?>
                            <div><strong>የተጠየቀ አዲስ ስልክ፡</strong> <span style="font-weight:700;"><?php echo htmlspecialchars($pending_request['requested_phone']); ?></span> (የነበረው፡ <?php echo htmlspecialchars($teacher['phone'] ?? '---'); ?>)</div>
                        <?php endif; ?>
                        <?php if ($pending_request['requested_photo']): ?>
                            <div style="margin-top: 8px;">
                                <strong>የተጠየቀ አዲስ ፎቶ፡</strong><br>
                                <a href="<?php echo htmlspecialchars($pending_request['requested_photo']); ?>" target="_blank" style="display:inline-block; margin-top:4px;">
                                    <img src="<?php echo htmlspecialchars($pending_request['requested_photo']); ?>" style="width:75px; height:75px; border-radius:10px; object-fit:cover; border:2px solid #B45309;" alt="Requested Photo">
                                </a>
                            </div>
                        <?php endif; ?>
                        <?php if ($pending_request['reason']): ?>
                            <div style="margin-top: 4px;"><strong>የቀረበበት ምክንያት፡</strong> <?php echo htmlspecialchars($pending_request['reason']); ?></div>
                        <?php endif; ?>
                    </div>

                    <div style="display: flex; gap: 12px; flex-wrap: wrap; border-top: 1px dashed rgba(217, 119, 6, 0.4); padding-top: 14px;">
                        <form method="POST" style="display: inline;">
                            <?php echo csrfField(); ?>
                            <input type="hidden" name="request_id" value="<?php echo $pending_request['id']; ?>">
                            <button type="submit" name="approve_profile_request" class="btn-action" style="background: #10B981; color: white; border: none;" onclick="return confirm('ይህን የመረጃ ለውጥ ጥያቄ ማጽደቅ ይፈልጋሉ?')">
                                ✅ ጥያቄውን አጽድቅ (Approve)
                            </button>
                        </form>
                        <button type="button" class="btn-action" style="background: #EF4444; color: white; border: none;" onclick="var rf=document.getElementById('reject-box-admin'); rf.style.display=(rf.style.display==='none'?'block':'none');">
                            ❌ ውድቅ አድርግ (Reject)
                        </button>
                    </div>
                    <div id="reject-box-admin" class="admin-reject-box">
                        <form method="POST">
                            <?php echo csrfField(); ?>
                            <input type="hidden" name="request_id" value="<?php echo $pending_request['id']; ?>">
                            <label class="form-label" style="font-size:12.5px; font-weight:600;">ውድቅ የተደረገበት ምክንያት (ለመምህሩ የሚላክ)፦</label>
                            <input type="text" name="admin_notes" class="form-control" placeholder="ምክንያት ይጻፉ..." style="margin-bottom:10px;" required>
                            <button type="submit" name="reject_profile_request" class="btn-action" style="background:#DC2626; color:white; border:none; padding:8px 16px;">
                                ውድቅ አድርግ
                            </button>
                        </form>
                    </div>
                </div>
            <?php endif; ?>

            <!-- ADMIN DIRECT EDIT PANEL -->
            <div class="card admin-edit-card" id="admin-edit-panel">
                <div class="card-header">
                    <div class="card-title"><span>⚡</span> የትምህርት ክፍል ቀጥታ ማስተካከያ (Admin Controls)</div>
                    <span style="font-size: 12px; font-weight: 600;">ትምህርት ክፍል ብቻ</span>
                </div>
                <p style="font-size: 13px; margin-bottom: 16px;">
                    የትምህርት ክፍል ኃላፊ እንደመሆንዎ መጠን የመምህሩን ስም፣ ስልክ ቁጥር፣ ፎቶ፣ የይለፍ ቃል እና የአካውንት ሁኔታ በቀጥታ ማስተካከል ይችላሉ።
                </p>
                <form method="POST" enctype="multipart/form-data">
                    <?php echo csrfField(); ?>
                    <input type="hidden" name="admin_direct_update" value="1">
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 16px; margin-bottom: 16px;">
                        <div class="form-group">
                            <label class="form-label">ሙሉ ስም</label>
                            <input type="text" name="name" class="form-control" value="<?php echo htmlspecialchars($teacher['name']); ?>" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">ስልክ ቁጥር</label>
                            <input type="text" name="phone" class="form-control" value="<?php echo htmlspecialchars($teacher['phone'] ?? ''); ?>">
                        </div>
                        <div class="form-group">
                            <label class="form-label">የመምህር አዲስ ፎቶ ይጫኑ (Upload)</label>
                            <input type="file" name="admin_photo" class="form-control" accept="image/*">
                            <div class="form-text">ፎቶ መቀየር ካልፈለጉ ባዶ ይተዉት። (እስከ 25MB)</div>
                        </div>
                        <div class="form-group">
                            <label class="form-label">የይለፍ ቃል ቀይር (Reset Password)</label>
                            <input type="password" name="admin_new_password" class="form-control" placeholder="አዲስ የይለፍ ቃል ያስገቡ..." autocomplete="new-password">
                            <div class="form-text">የይለፍ ቃል መቀየር ካልፈለጉ ባዶ ይተዉት።</div>
                        </div>
                        <div class="form-group">
                            <label class="form-label">የአካውንት ሁኔታ (Status)</label>
                            <select name="admin_status" class="form-control">
                                <option value="active" <?php echo ($teacher['status'] ?? 'active') === 'active' ? 'selected' : ''; ?>>🟢 ንቁ (Active)</option>
                                <option value="inactive" <?php echo ($teacher['status'] ?? '') === 'inactive' ? 'selected' : ''; ?>>🔴 የታገደ (Inactive)</option>
                            </select>
                        </div>
                    </div>
                    <button type="submit" class="btn-submit" style="background: linear-gradient(135deg, #0284C7 0%, #0369A1 100%);">
                        💾 የመምህር መረጃን በቀጥታ አስቀምጥ
                    </button>
                </form>
            </div>

            <!-- Past Requests History for Admin -->
            <?php if (!empty($past_requests)): ?>
                <div class="card">
                    <div class="card-header">
                        <div class="card-title"><span>📜</span> የቀደሙ የመረጃ ለውጥ ጥያቄዎች ታሪክ</div>
                        <span style="font-size: 12px; color: #6B7280;"><?php echo count($past_requests); ?> ጥያቄዎች</span>
                    </div>
                    <?php foreach ($past_requests as $pr): ?>
                        <div class="req-history-card <?php echo $pr['status'] === 'approved' ? 'approved' : 'rejected'; ?>">
                            <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 6px;">
                                <strong><?php echo $pr['status'] === 'approved' ? '✅ የጸደቀ' : '❌ ውድቅ የተደረገ'; ?></strong>
                                <span class="req-date"><?php echo date('M d, Y h:i A', strtotime($pr['created_at'])); ?></span>
                            </div>
                            <div class="req-details">
                                <?php if ($pr['requested_name']): ?><div><strong>የተጠየቀ ስም፡</strong> <?php echo htmlspecialchars($pr['requested_name']); ?></div><?php endif; ?>
                                <?php if ($pr['requested_phone']): ?><div><strong>የተጠየቀ ስልክ፡</strong> <?php echo htmlspecialchars($pr['requested_phone']); ?></div><?php endif; ?>
                                <?php if ($pr['admin_notes']): ?>
                                    <div class="req-admin-note">
                                        <strong>📌 ውድቅ የተደረገበት ምክንያት (የትምህርት ክፍል ማስታወሻ)፦</strong><br>
                                        <span><?php echo nl2br(htmlspecialchars($pr['admin_notes'])); ?></span>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

        <?php else: ?>
            <!-- TEACHER VIEW: SELF PROFILE CHANGE REQUEST SECTION -->
            <div class="card" id="change-request-section">
                <div class="card-header">
                    <div class="card-title"><span>📝</span> የመረጃ ለውጥ መጠየቂያ</div>
                    <span style="font-size: 12px; color: #B45309; font-weight:600;">🔒 በቀጥታ አይቀየርም፤ ለትምህርት ክፍል ጥያቄ ይላካል</span>
                </div>

                <p style="font-size: 13px; color: #4B5563; margin-bottom: 16px; line-height: 1.5;">
                    የመምህራን መረጃ በትምህርት ክፍል ቁጥጥር ስር ስለሆነ፤ ስም፣ ስልክ ቁጥር ወይም ፎቶ መቀየር ከፈለጉ እባክዎ ከታች ያለውን ቅጽ ሞልተው ጥያቄ ይላኩ። ትምህርት ክፍሉ ሲያጸድቀው መረጃዎ በራስ-ሰር ይሻሻላል።
                </p>

                <?php if ($pending_request): ?>
                    <!-- Active Pending Request Alert for Teacher -->
                    <div class="request-banner">
                        <div class="request-banner-title">
                            <span>⏳</span> በመጠባበቅ ላይ ያለ የመረጃ ለውጥ ጥያቄ
                        </div>
                        <div class="request-banner-body" style="font-size: 13px; line-height: 1.6;">
                            <strong>የቀረበበት ቀን፡</strong> <?php echo date('M d, Y h:i A', strtotime($pending_request['created_at'])); ?><br>
                            <?php if ($pending_request['requested_name'] && $pending_request['requested_name'] !== $teacher['name']): ?>
                                <strong>የተጠየቀ አዲስ ስም፡</strong> <?php echo htmlspecialchars($pending_request['requested_name']); ?><br>
                            <?php endif; ?>
                            <?php if ($pending_request['requested_phone'] && $pending_request['requested_phone'] !== $teacher['phone']): ?>
                                <strong>የተጠየቀ አዲስ ስልክ፡</strong> <?php echo htmlspecialchars($pending_request['requested_phone']); ?><br>
                            <?php endif; ?>
                            <?php if ($pending_request['requested_photo']): ?>
                                <strong>አዲስ ፎቶ፡</strong> <a href="<?php echo htmlspecialchars($pending_request['requested_photo']); ?>" target="_blank" style="color:#B45309; text-decoration:underline;">የተጫነውን ፎቶ ይመልከቱ</a><br>
                            <?php endif; ?>
                            <?php if ($pending_request['reason']): ?>
                                <strong>ምክንያት፡</strong> <?php echo htmlspecialchars($pending_request['reason']); ?><br>
                            <?php endif; ?>
                        </div>
                        <div style="margin-top: 10px; font-size: 12px; font-style: italic; opacity: 0.9;">
                            🔔 ጥያቄዎ ለትምህርት ክፍል ተልኳል፤ ትምህርት ክፍሉ ውሳኔ ሲሰጥበት በስልክዎ/ብሮውዘርዎ ማሳወቂያ ይደርስዎታል።
                        </div>
                    </div>
                <?php else: ?>
                    <!-- Submission Form for Teacher -->
                    <form method="POST" enctype="multipart/form-data">
                        <?php echo csrfField(); ?>
                        <input type="hidden" name="submit_change_request" value="1">

                        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 16px;">
                            <div class="form-group">
                                <label class="form-label">የሚቀየር አዲስ ሙሉ ስም (ካለ)</label>
                                <input type="text" name="requested_name" class="form-control" value="<?php echo htmlspecialchars($teacher['name']); ?>" placeholder="አዲስ ሙሉ ስም ያስገቡ">
                                <div class="form-text">ስምዎን መቀየር ካልፈለጉ እንዳለ ይተዉት።</div>
                            </div>

                            <div class="form-group">
                                <label class="form-label">የሚቀየር አዲስ ስልክ ቁጥር (ካለ)</label>
                                <input type="text" name="requested_phone" class="form-control" value="<?php echo htmlspecialchars($teacher['phone'] ?? ''); ?>" placeholder="09xxxxxxxx">
                                <div class="form-text">ስልክዎን መቀየር ካልፈለጉ እንዳለ ይተዉት።</div>
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="form-label">አዲስ የመገለጫ ፎቶ (ካለ)</label>
                            <input type="file" name="requested_photo" accept="image/*" class="form-control">
                            <div class="form-text">JPG, PNG, GIF, WebP እስከ 25MB መሆን አለበት።</div>
                        </div>

                        <div class="form-group">
                            <label class="form-label">የለውጡ ምክንያት / ማብራሪያ (አማራጭ)</label>
                            <textarea name="change_reason" class="form-control" rows="2" placeholder="ለምሳሌ፡ ስልክ ቁጥር ስለቀየርኩ..."></textarea>
                        </div>

                        <button type="submit" class="btn-submit">
                            <span>📤</span> የመረጃ ለውጥ ጥያቄውን ላክ
                        </button>
                    </form>
                <?php endif; ?>

                <!-- Past Requests History for Teacher -->
                <?php if (!empty($past_requests)): ?>
                    <div style="margin-top: 24px; padding-top: 16px; border-top: 1px dashed rgba(229, 231, 235, 0.5);">
                        <div style="font-size: 13px; font-weight: 700; margin-bottom: 10px;">የቀደሙ ጥያቄዎች ታሪክ፦</div>
                        <?php foreach ($past_requests as $pr): ?>
                            <div class="req-history-card <?php echo $pr['status'] === 'approved' ? 'approved' : 'rejected'; ?>">
                                <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 6px;">
                                    <strong><?php echo $pr['status'] === 'approved' ? '✅ የጸደቀ' : '❌ ውድቅ የተደረገ'; ?></strong>
                                    <span class="req-date"><?php echo date('M d, Y', strtotime($pr['created_at'])); ?></span>
                                </div>
                                <div class="req-details">
                                    <?php if ($pr['requested_name'] && $pr['requested_name'] !== $teacher['name']): ?>
                                        <div><strong>የተጠየቀ ስም፡</strong> <?php echo htmlspecialchars($pr['requested_name']); ?></div>
                                    <?php endif; ?>
                                    <?php if ($pr['requested_phone'] && $pr['requested_phone'] !== $teacher['phone']): ?>
                                        <div><strong>የተጠየቀ ስልክ፡</strong> <?php echo htmlspecialchars($pr['requested_phone']); ?></div>
                                    <?php endif; ?>
                                    <?php if ($pr['admin_notes']): ?>
                                        <div class="req-admin-note">
                                            <strong>📌 ውድቅ የተደረገበት ምክንያት (የትምህርት ክፍል ማስታወሻ)፦</strong><br>
                                            <span><?php echo nl2br(htmlspecialchars($pr['admin_notes'])); ?></span>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <!-- Documents Section -->
        <div class="card">
            <div class="card-header">
                <div class="card-title"><span>📄</span> የትምህርት ሰነዶችና መርጃዎች</div>
                <span style="font-size: 12px; color: #6B7280;"><?php echo count($documents); ?> ፋይሎች</span>
            </div>

            <!-- Document Upload Form -->
            <form method="POST" enctype="multipart/form-data" style="margin-bottom: 20px;">
                <?php echo csrfField(); ?>
                <div style="display: flex; gap: 10px; flex-wrap: wrap; align-items: flex-end;">
                    <div style="flex: 2; min-width: 200px;">
                        <label class="form-label">ሰነድ ይምረጡ</label>
                        <input type="file" name="document" class="form-control" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png,.xls,.xlsx,.ppt,.pptx,.txt" required>
                    </div>
                    <div style="flex: 2; min-width: 180px;">
                        <label class="form-label">የሰነዱ ስም (አማራጭ)</label>
                        <input type="text" name="doc_name" class="form-control" placeholder="ምሳሌ፡ የክፍል መመሪያ">
                    </div>
                    <button type="submit" name="upload_doc" class="btn-submit" style="height: 44px;">
                        ⬆️ ሰነዱን ጫን (Upload)
                    </button>
                </div>
            </form>

            <!-- Document List -->
            <?php if (!empty($documents)): ?>
                <?php foreach ($documents as $doc): ?>
                    <div class="doc-item">
                        <span style="font-size: 24px;">📄</span>
                        <div style="flex: 1;">
                            <div style="font-weight: 700; font-size: 14px; color: var(--brown-dark);">
                                <?php echo htmlspecialchars($doc['document_name']); ?>
                            </div>
                            <div style="font-size: 12px; color: #6B7280;">
                                <?php echo date('M d, Y', strtotime($doc['uploaded_at'])); ?> &bull; .<?php echo htmlspecialchars($doc['file_type']); ?>
                            </div>
                        </div>
                        <div style="display: flex; gap: 8px;">
                            <a href="<?php echo htmlspecialchars($doc['file_path']); ?>" target="_blank" class="btn-action btn-gold" style="padding: 5px 12px; font-size: 12px;">
                                👁️ እይ
                            </a>
                            <form method="POST" onsubmit="return confirm('ሰነዱን መሰረዝ እርግጠኛ ነዎት?')" style="display: inline;">
                                <?php echo csrfField(); ?>
                                <input type="hidden" name="doc_id" value="<?php echo $doc['id']; ?>">
                                <button type="submit" name="delete_doc" style="background: none; border: none; cursor: pointer; padding: 6px; font-size: 16px;" title="ሰርዝ">
                                    🗑️
                                </button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div style="text-align: center; padding: 24px; color: #9CA3AF; font-size: 13px;">
                    📭 እስካሁን የተጫነ ሰነድ የለም።
                </div>
            <?php endif; ?>
        </div>

        <!-- Teaching History Section -->
        <div class="card">
            <div class="card-header">
                <div class="card-title"><span>📜</span> የማስተማር ታሪክ</div>
            </div>
            <?php if (!empty($history)): ?>
                <div style="display: flex; flex-wrap: wrap; gap: 8px;">
                    <?php foreach ($history as $h): ?>
                        <span class="chip">
                            🏫 <?php echo htmlspecialchars($h['class_name']); ?> 
                            (<?php echo htmlspecialchars($h['subject_name']); ?> - <?php echo $h['ethiopian_year']; ?> ዓ.ም ሴም <?php echo $h['semester_number']; ?>)
                        </span>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div style="text-align: center; padding: 20px; color: #9CA3AF; font-size: 13px;">
                    እስካሁን ምንም የማስተማር ታሪክ አልተመዘገበም።
                </div>
            <?php endif; ?>
        </div>
    </div>
    <script>
    function previewAndSubmitAvatar(input) {
        if (!input.files || !input.files[0]) return;
        var file = input.files[0];
        
        // 25MB check
        if (file.size > 26214400) {
            alert('የተመረጠው ፎቶ መጠን ከ 25MB ያንሳል! እባክዎ አነስ ያለ ፎቶ ይምረጡ።');
            input.value = '';
            return;
        }

        // Preview immediate change
        var reader = new FileReader();
        reader.onload = function(e) {
            var img = document.getElementById('currentAvatarImg');
            if (img) img.src = e.target.result;
        };
        reader.readAsDataURL(file);

        if (confirm('የተመረጠውን ፎቶ እንደ መገለጫ ፎቶ አሁን ማስቀመጥ ይፈልጋሉ?')) {
            document.getElementById('avatarUploadForm').submit();
        } else {
            input.value = '';
        }
    }
    </script>
</body>
</html>
<?php mysqli_close($conn); ?>
