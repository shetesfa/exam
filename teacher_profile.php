<?php
session_start();
require_once 'db.php';
requireAdmin();

$message = '';
$error = '';

$teacher_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Get teacher info
$teacher_query = "SELECT u.*, 
                  (SELECT COUNT(*) FROM teacher_documents WHERE teacher_id = u.id) as doc_count
                  FROM users u 
                  WHERE u.id = $teacher_id AND u.role = 'teacher'";
$teacher_result = mysqli_query($conn, $teacher_query);
$teacher = mysqli_fetch_assoc($teacher_result);

if(!$teacher) {
    header("Location: manage_teachers.php");
    exit();
}

$teacher_name = $teacher['name'];
$teacher_phone = $teacher['phone'] ?: '---';
$teacher_username = $teacher['username'];
$teacher_photo = $teacher['photo'] ?: 'images/icon.png';

// Handle photo upload
if(isset($_POST['upload_photo']) && isset($_FILES['photo'])) {
    $file = $_FILES['photo'];
    $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    
    if($file['error'] === 0 && $file['size'] < 5242880 && in_array($ext, $allowed)) {
        $new_name = 'teacher_' . $teacher_id . '_' . time() . '.' . $ext;
        $upload_path = 'uploads/teachers/' . $new_name;
        
        if(move_uploaded_file($file['tmp_name'], $upload_path)) {
            if($teacher['photo'] && $teacher['photo'] != 'images/icon.png' && file_exists($teacher['photo'])) {
                unlink($teacher['photo']);
            }
            mysqli_query($conn, "UPDATE users SET photo = '$upload_path' WHERE id = $teacher_id");
            $message = "ፎቶ በተሳካ ሁኔታ ተሰቅሏል! (Photo uploaded successfully!)";
            $teacher['photo'] = $upload_path;
            $teacher_photo = $upload_path;
        } else {
            $error = "ፋይል መስቀል አልተቻለም! (Failed to upload file!)";
        }
    } else {
        $error = "እባክዎ ትክክለኛ የምስል ፋይል ይምረጡ (JPG, PNG, GIF - max 5MB)!";
    }
}

// Handle document upload
if(isset($_POST['upload_doc']) && isset($_FILES['document'])) {
    $file = $_FILES['document'];
    $doc_name = mysqli_real_escape_string($conn, $_POST['doc_name'] ?: $file['name']);
    $allowed = ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png', 'xls', 'xlsx', 'ppt', 'pptx', 'txt'];
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    
    if($file['error'] === 0 && $file['size'] < 20971520 && in_array($ext, $allowed)) {
        $new_name = 'doc_' . $teacher_id . '_' . time() . '.' . $ext;
        $upload_path = 'uploads/documents/' . $new_name;
        
        if(move_uploaded_file($file['tmp_name'], $upload_path)) {
            $file_type = $ext;
            mysqli_query($conn, "INSERT INTO teacher_documents (teacher_id, document_name, file_path, file_type) 
                                VALUES ($teacher_id, '$doc_name', '$upload_path', '$file_type')");
            $message = "ሰነድ በተሳካ ሁኔታ ተሰቅሏል! (Document uploaded successfully!)";
        } else {
            $error = "ሰነድ መስቀል አልተቻለም! (Failed to upload document!)";
        }
    } else {
        $error = "እባክዎ ትክክለኛ ፋይል ይምረጡ (PDF, Word, Excel, Images - max 20MB)!";
    }
}

// Handle delete document
if(isset($_POST['delete_doc'])) {
    $doc_id = intval($_POST['doc_id']);
    $doc_query = mysqli_query($conn, "SELECT file_path FROM teacher_documents WHERE id = $doc_id AND teacher_id = $teacher_id");
    $doc = mysqli_fetch_assoc($doc_query);
    
    if($doc) {
        if(file_exists($doc['file_path'])) {
            unlink($doc['file_path']);
        }
        mysqli_query($conn, "DELETE FROM teacher_documents WHERE id = $doc_id");
        $message = "ሰነድ ተሰርዟል! (Document deleted!)";
    }
}

// Get teacher documents
$docs_query = "SELECT * FROM teacher_documents WHERE teacher_id = $teacher_id ORDER BY uploaded_at DESC";
$documents = mysqli_query($conn, $docs_query);

// Get teaching history
$current_semester = getCurrentSemester($conn);
$semester_id = $current_semester ? $current_semester['id'] : 0;

$history_query = "SELECT c.name as class_name, s.name as semester_name, s.ethiopian_year, s.semester_number
                  FROM teacher_class tc
                  JOIN classes c ON tc.class_id = c.id
                  JOIN semesters s ON tc.semester_id = s.id
                  WHERE tc.teacher_id = $teacher_id
                  ORDER BY s.ethiopian_year DESC, s.semester_number DESC";
$history = mysqli_query($conn, $history_query);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/png" href="images/icon.png">
    <title>የመምህር መረጃ | <?php echo htmlspecialchars($teacher_name); ?></title>
    <style>
        :root {
            --brown-dark: #8B4513;
            --gold-primary: #FFD700;
            --gold-dark: #DAA520;
            --gold-pale: #FFF8DC;
            --success: #10B981;
            --error: #EF4444;
            --info: #3B82F6;
            --bg: #FAF9F6;
        }
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Segoe UI', sans-serif; }
        body { background: var(--bg); min-height: 100vh; }
        
        .header {
            background: linear-gradient(135deg, #8B4513, #A52A2A);
            color: white; padding: 15px 30px;
            display: flex; justify-content: space-between; align-items: center;
            flex-wrap: wrap; gap: 10px;
        }
        .logo { display: flex; align-items: center; gap: 12px; }
        .logo-icon {
            width: 45px; height: 45px; background: linear-gradient(135deg, #FFD700, #DAA520);
            border-radius: 50%; display: flex; align-items: center; justify-content: center;
            font-size: 22px; color: #8B4513; border: 2px solid white;
        }
        .btn-back { color: #8B4513; background: #FFD700; padding: 8px 16px; border-radius: 20px; text-decoration: none; font-weight: 600; font-size: 13px; }
        
        .container { max-width: 1000px; margin: 25px auto; padding: 0 20px; }
        
        .message { padding: 12px 15px; border-radius: 10px; margin-bottom: 15px; display: flex; align-items: center; gap: 10px; font-size: 14px; }
        .success { background: #D1FAE5; color: #065F46; border-left: 4px solid var(--success); }
        .error { background: #FEE2E2; color: #991B1B; border-left: 4px solid var(--error); }
        
        .profile-card {
            background: white; border-radius: 20px; overflow: hidden;
            border: 2px solid var(--gold-primary); box-shadow: 0 10px 30px rgba(0,0,0,0.08);
            margin-bottom: 25px;
        }
        
        .profile-header {
            background: linear-gradient(135deg, var(--brown-dark), #A52A2A);
            color: white; padding: 30px; text-align: center;
        }
        
        .photo-wrapper {
            width: 130px; height: 130px; margin: 0 auto 15px;
            border-radius: 50%; border: 5px solid var(--gold-primary);
            overflow: hidden; background: white; position: relative;
        }
        .photo-wrapper img { width: 100%; height: 100%; object-fit: cover; }
        
        .photo-upload-label {
            display: inline-block; cursor: pointer;
            padding: 8px 20px; background: rgba(255,255,255,0.2); color: white;
            border-radius: 20px; font-size: 12px; margin-top: 10px;
            border: 1px solid rgba(255,255,255,0.4); transition: all 0.3s;
        }
        .photo-upload-label:hover { background: rgba(255,255,255,0.3); }
        
        .profile-body { padding: 25px; }
        
        .info-grid {
            display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 15px; margin-bottom: 20px;
        }
        .info-item {
            background: #F8F9FA; padding: 15px; border-radius: 12px;
            border-left: 4px solid var(--gold-primary);
        }
        .info-label { font-size: 11px; color: #666; margin-bottom: 5px; text-transform: uppercase; }
        .info-value { font-size: 15px; color: var(--brown-dark); font-weight: 600; }
        
        .section-title {
            color: var(--brown-dark); font-size: 18px; margin: 25px 0 15px;
            padding-bottom: 10px; border-bottom: 2px solid var(--gold-pale);
            display: flex; align-items: center; gap: 10px;
        }
        
        .doc-list { display: flex; flex-direction: column; gap: 10px; }
        .doc-item {
            display: flex; align-items: center; gap: 12px; padding: 12px 15px;
            background: #F8F9FA; border-radius: 10px; cursor: pointer;
            transition: all 0.2s; border: 1px solid #E5E7EB;
        }
        .doc-item:hover { background: var(--gold-pale); border-color: var(--gold-primary); }
        .doc-icon {
            width: 40px; height: 40px; background: var(--info); color: white;
            border-radius: 10px; display: flex; align-items: center; justify-content: center;
            font-size: 18px; font-weight: bold;
        }
        .doc-icon.pdf { background: #EF4444; }
        .doc-icon.doc { background: #3B82F6; }
        .doc-icon.xls { background: #10B981; }
        .doc-icon.img { background: #8B5CF6; }
        .doc-icon.ppt { background: #F59E0B; }
        
        .doc-info { flex: 1; }
        .doc-name { font-weight: 600; color: var(--brown-dark); font-size: 14px; }
        .doc-meta { font-size: 11px; color: #666; }
        
        .doc-actions { display: flex; gap: 8px; }
        .btn-sm {
            padding: 5px 12px; border-radius: 15px; border: none; cursor: pointer;
            font-size: 11px; font-weight: 600; text-decoration: none;
        }
        .btn-view { background: var(--info); color: white; }
        .btn-delete { background: var(--error); color: white; }
        
        .upload-zone {
            border: 2px dashed #D1D5DB; border-radius: 12px; padding: 20px;
            text-align: center; transition: all 0.3s; margin-top: 10px;
        }
        .upload-zone:hover { border-color: var(--gold-primary); background: var(--gold-pale); }
        .upload-zone input { display: none; }
        .upload-zone label { cursor: pointer; color: #666; font-size: 13px; }
        
        .history-list { display: flex; flex-wrap: wrap; gap: 8px; }
        .history-tag {
            background: var(--gold-pale); color: var(--brown-dark);
            padding: 6px 14px; border-radius: 20px; font-size: 12px;
            border: 1px solid var(--gold-primary);
        }
        
        .empty { text-align: center; padding: 30px; color: #999; }
        
        @media (max-width: 600px) {
            .profile-header { padding: 20px; }
            .photo-wrapper { width: 100px; height: 100px; }
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="logo">
            <div class="logo-icon">👨‍🏫</div>
            <div>
                <h2 style="font-size:16px; color:#FFD700;">የመምህር መገለጫ</h2>
                <span style="font-size:11px; opacity:0.8;">Teacher Profile</span>
            </div>
        </div>
        <a href="manage_teachers.php" class="btn-back">← ወደ መምህራን</a>
    </div>

    <div class="container">
        <?php if($message): ?>
        <div class="message success">✅ <?php echo $message; ?></div>
        <?php endif; ?>
        <?php if($error): ?>
        <div class="message error">⚠️ <?php echo $error; ?></div>
        <?php endif; ?>

        <div class="profile-card">
            <div class="profile-header">
                <div class="photo-wrapper">
                    <img src="<?php echo $teacher_photo; ?>" alt="<?php echo htmlspecialchars($teacher_name); ?>" 
                         onerror="this.src='images/icon.png'">
                </div>
                <h2 style="margin-bottom:5px;"><?php echo htmlspecialchars($teacher_name); ?></h2>
                <p style="opacity:0.8; font-size:13px;">@<?php echo htmlspecialchars($teacher_username); ?> | 👨‍🏫 መምህር</p>
                
                <form method="POST" enctype="multipart/form-data" style="display:inline;">
                    <input type="file" name="photo" id="photoUpload" accept="image/*" onchange="this.form.submit()" style="display:none;">
                    <label for="photoUpload" class="photo-upload-label">📷 ፎቶ ቀይር / Change Photo</label>
                    <input type="hidden" name="upload_photo" value="1">
                </form>
            </div>

            <div class="profile-body">
                <div class="info-grid">
                    <div class="info-item">
                        <div class="info-label">ሙሉ ስም / Full Name</div>
                        <div class="info-value"><?php echo htmlspecialchars($teacher_name); ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">ስልክ / Phone</div>
                        <div class="info-value">📱 <?php echo htmlspecialchars($teacher_phone); ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">የተጠቃሚ ስም / Username</div>
                        <div class="info-value">@<?php echo htmlspecialchars($teacher_username); ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">ሰነዶች / Documents</div>
                        <div class="info-value">📄 <?php echo $teacher['doc_count']; ?> files</div>
                    </div>
                </div>

                <!-- Teaching History -->
                <div class="section-title">📚 የማስተማር ታሪክ / Teaching History</div>
                <div class="history-list">
                    <?php if($history && mysqli_num_rows($history) > 0): ?>
                        <?php while($h = mysqli_fetch_assoc($history)): ?>
                        <span class="history-tag">
                            <?php echo htmlspecialchars($h['class_name']); ?> 
                            (<?php echo $h['ethiopian_year']; ?> ሴም <?php echo $h['semester_number']; ?>)
                        </span>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <span class="empty">እስካሁን ምንም ክፍል አላስተማሩም</span>
                    <?php endif; ?>
                </div>

                <!-- Documents Section -->
                <div class="section-title">📄 ሰነዶች / Documents</div>
                
                <form method="POST" enctype="multipart/form-data">
                    <div class="upload-zone">
                        <input type="file" name="document" id="docUpload" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png,.xls,.xlsx,.ppt,.pptx,.txt">
                        <label for="docUpload">
                            <span style="font-size:30px; display:block; margin-bottom:8px;">📁</span>
                            ሰነድ ለመስቀል ይጫኑ<br>
                            <small>(PDF, Word, Excel, Images - Max 20MB)</small>
                        </label>
                    </div>
                    <div style="margin-top:10px; display:flex; gap:10px; flex-wrap:wrap;">
                        <input type="text" name="doc_name" placeholder="የሰነድ ስም (አማራጭ)" 
                               style="flex:1; padding:10px; border:2px solid #E2E8F0; border-radius:8px; min-width:200px;">
                        <button type="submit" name="upload_doc" 
                                style="padding:10px 20px; background:var(--info); color:white; border:none; border-radius:8px; cursor:pointer; font-weight:600;">
                            ⬆️ ስቀል / Upload
                        </button>
                    </div>
                </form>

                <div class="doc-list" style="margin-top:15px;">
                    <?php if($documents && mysqli_num_rows($documents) > 0): ?>
                        <?php while($doc = mysqli_fetch_assoc($documents)): 
                            $ext = $doc['file_type'];
                            $icon_class = 'doc';
                            $icon_text = strtoupper($ext);
                            if(in_array($ext, ['pdf'])) $icon_class = 'pdf';
                            elseif(in_array($ext, ['doc','docx'])) $icon_class = 'doc';
                            elseif(in_array($ext, ['xls','xlsx'])) $icon_class = 'xls';
                            elseif(in_array($ext, ['jpg','jpeg','png','gif'])) $icon_class = 'img';
                            elseif(in_array($ext, ['ppt','pptx'])) $icon_class = 'ppt';
                        ?>
                        <div class="doc-item">
                            <div class="doc-icon <?php echo $icon_class; ?>"><?php echo $icon_text; ?></div>
                            <div class="doc-info">
                                <div class="doc-name"><?php echo htmlspecialchars($doc['document_name']); ?></div>
                                <div class="doc-meta"><?php echo date('M d, Y', strtotime($doc['uploaded_at'])); ?> | .<?php echo $ext; ?></div>
                            </div>
                            <div class="doc-actions">
                                <a href="<?php echo $doc['file_path']; ?>" target="_blank" class="btn-sm btn-view">👁️ ክፈት</a>
                                <form method="POST" style="display:inline;" onsubmit="return confirm('ሰነዱን መሰረዝ እርግጠኛ ነዎት?')">
                                    <input type="hidden" name="doc_id" value="<?php echo $doc['id']; ?>">
                                    <button type="submit" name="delete_doc" class="btn-sm btn-delete">🗑️</button>
                                </form>
                            </div>
                        </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <div class="empty">📭 ምንም ሰነድ አልተሰቀለም</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
<?php mysqli_close($conn); ?>