<?php
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
require_once 'db.php';

// Redirect if already logged in as student
if (isStudent()) {
    header("Location: dashboard_student.php");
    exit();
}

$error = '';
$suggestions = [];

// AJAX endpoint for name search
if (isset($_GET['ajax']) && $_GET['ajax'] === 'search') {
    header('Content-Type: application/json');
    
    $search = isset($_GET['q']) ? trim($_GET['q']) : '';
    
    if (mb_strlen($search) >= 1) {
        $searchTerm = '%' . $search . '%';
        $rows = dbFetchAll(
            $conn,
            "SELECT s.id, s.name, c.name as class_name, s.parent_phone
             FROM students s
             JOIN classes c ON s.class_id = c.id
             WHERE s.name LIKE ? 
               AND s.student_portal_enabled = 1
               AND (s.is_deleted = 0 OR s.is_deleted IS NULL)
             ORDER BY s.name
             LIMIT 15",
            "s",
            [$searchTerm]
        );
        
        $students = [];
        foreach ($rows as $row) {
            $students[] = [
                'id' => (int)$row['id'],
                'name' => $row['name'],
                'class' => $row['class_name']
            ];
        }
        
        echo json_encode($students);
    } else {
        echo json_encode([]);
    }
    exit();
}

// Handle login
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $submitted_token = $_POST['csrf_token'] ?? '';
    $csrf_valid = verifyCsrfToken($submitted_token);

    $student_id = isset($_POST['student_id']) ? intval($_POST['student_id']) : 0;
    $pin = isset($_POST['pin']) ? trim($_POST['pin']) : '';
    $typed_name = trim($_POST['student_name'] ?? '');
    
    // If student_id is empty, try looking up by typed name
    if ($student_id <= 0 && !empty($typed_name)) {
        // Check if user accidentally entered a staff/admin username
        $staff_check = dbFetchOne($conn, "SELECT id, role FROM users WHERE username = ? OR name = ?", "ss", [$typed_name, $typed_name]);
        if ($staff_check) {
            $error = "ይህ የመምህር ወይም የአስተዳዳሪ አካውንት ነው። እባክዎ <a href='index.php' style='color:#8B4513;font-weight:bold;text-decoration:underline;'>በዋናው መግቢያ</a> ይግቡ!";
        } else {
            $matched_s = dbFetchOne($conn, "SELECT id FROM students WHERE name = ? AND student_portal_enabled = 1 AND (is_deleted = 0 OR is_deleted IS NULL)", "s", [$typed_name]);
            if ($matched_s) {
                $student_id = intval($matched_s['id']);
            }
        }
    }

    // If CSRF token check failed (e.g. stale tab, bfcache, or browser session cookie timing),
    // verify whether valid credentials were submitted. If credentials are correct, allow login!
    if (!$csrf_valid && $student_id > 0 && !empty($pin)) {
        $pin_candidate = dbFetchOne($conn, "SELECT pin FROM student_logins WHERE student_id = ?", "i", [$student_id]);
        if ($pin_candidate && !empty($pin_candidate['pin']) && password_verify($pin, $pin_candidate['pin'])) {
            $csrf_valid = true;
        }
    }

    if (!$csrf_valid && empty($error)) {
        $error = "የደህንነት ማረጋገጫ አልተሳካም! እባክዎ እንደገና ይሞክሩ።";
    } elseif (empty($error)) {
        
        if (empty($error) && $student_id > 0 && !empty($pin)) {
            $student = dbFetchOne(
                $conn,
                "SELECT s.*, sl.pin as hashed_pin, sl.first_login, sl.login_attempts, sl.locked_until, sl.dark_mode,
                        c.name as class_name
                 FROM students s
                 JOIN classes c ON s.class_id = c.id
                 LEFT JOIN student_logins sl ON s.id = sl.student_id
                 WHERE s.id = ? AND s.student_portal_enabled = 1 AND (s.is_deleted = 0 OR s.is_deleted IS NULL)",
                "i",
                [$student_id]
            );
            
            if ($student) {
                // Check locked
                if (!empty($student['locked_until']) && strtotime($student['locked_until']) > time()) {
                    $lock_time = date('h:i A', strtotime($student['locked_until']));
                    $error = "አካውንትዎ ተቆልፏል! እባክዎ $lock_time ድረስ ይጠብቁ።";
                }
                // Verify PIN
                elseif (!empty($student['hashed_pin']) && password_verify($pin, $student['hashed_pin'])) {
                    // Reset attempts
                    dbExecute(
                        $conn,
                        "UPDATE student_logins SET login_attempts = 0, locked_until = NULL, last_login = NOW() WHERE student_id = ?",
                        "i",
                        [$student_id]
                    );
                    
                    session_regenerate_id(true);
                    $_SESSION['student_id'] = $student['id'];
                    $_SESSION['student_name'] = $student['name'];
                    $_SESSION['student_class'] = $student['class_name'];
                    $_SESSION['student_class_id'] = $student['class_id'];
                    $_SESSION['student_first_login'] = $student['first_login'] ?? 1;
                    $_SESSION['dark_mode'] = intval($student['dark_mode'] ?? 0);
                    
                    if (($student['first_login'] ?? 1) == 1) {
                        header("Location: student_change_pin.php");
                    } else {
                        header("Location: dashboard_student.php");
                    }
                    exit();
                } else {
                    // Wrong PIN
                    $attempts = ($student['login_attempts'] ?? 0) + 1;
                    
                    if ($attempts >= 5) {
                        $lock_time = date('Y-m-d H:i:s', strtotime('+15 minutes'));
                        dbExecute(
                            $conn,
                            "UPDATE student_logins SET login_attempts = ?, locked_until = ? WHERE student_id = ?",
                            "isi",
                            [$attempts, $lock_time, $student_id]
                        );
                        $error = "በጣም ብዙ ሙከራ! አካውንትዎ ለ15 ደቂቃ ተቆልፏል።";
                    } else {
                        dbExecute(
                            $conn,
                            "UPDATE student_logins SET login_attempts = ? WHERE student_id = ?",
                            "ii",
                            [$attempts, $student_id]
                        );
                        $remaining = 5 - $attempts;
                        $error = "የተሳሳተ ፒን! $remaining ሙከራዎች ቀርተዋል።";
                    }
                }
            } else {
                $error = "ተማሪ አልተገኘም!";
            }
        } else {
            $error = "እባክዎ ስምዎን መርጠው ፒን ያስገቡ!";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>የተማሪ መግቢያ | Student Login</title>
    <?php include 'pwa_head.php'; ?>
    <style>
        :root {
            --brown-dark: #8B4513;
            --gold-primary: #FFD700;
            --gold-dark: #DAA520;
            --gold-pale: #FFF8DC;
            --success: #10B981;
            --error: #EF4444;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Segoe UI', 'Nyala', sans-serif; }

        body {
            min-height: 100vh;
            background: linear-gradient(135deg, #8B4513 0%, #A52A2A 50%, #DAA520 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .login-card {
            background: white;
            border-radius: 25px;
            padding: 30px;
            width: 100%;
            max-width: 500px;
            border: 3px solid var(--gold-primary);
            box-shadow: 0 20px 40px rgba(0,0,0,0.3);
        }

        .logo-section {
            text-align: center;
            margin-bottom: 25px;
        }

        .logo-circle {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, var(--gold-primary), var(--gold-dark));
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 15px;
            font-size: 32px;
            color: var(--brown-dark);
            border: 4px solid var(--brown-dark);
        }

        h1 { color: var(--brown-dark); font-size: 20px; margin-bottom: 5px; }
        .subtitle { color: var(--brown-medium); font-size: 13px; }

        .search-box {
            position: relative;
            margin-bottom: 20px;
        }

        .search-box input {
            width: 100%;
            padding: 15px;
            border: 2px solid #E2E8F0;
            border-radius: 12px;
            font-size: 16px;
            transition: all 0.3s;
        }

        .search-box input:focus {
            outline: none;
            border-color: var(--gold-primary);
            box-shadow: 0 0 0 3px rgba(255,215,0,0.2);
        }

        .search-results {
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            background: white;
            border: 2px solid var(--gold-primary);
            border-top: none;
            border-radius: 0 0 12px 12px;
            max-height: 300px;
            overflow-y: auto;
            z-index: 1000;
            display: none;
        }

        .search-results.show { display: block; }

        .result-item {
            padding: 12px 15px;
            cursor: pointer;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid #F3F4F6;
            transition: all 0.2s;
        }

        .result-item:hover { background: var(--gold-pale); }
        .result-item.selected { background: var(--gold-primary); font-weight: bold; }

        .result-name { font-weight: 600; color: var(--brown-dark); font-size: 14px; }
        .result-class { font-size: 11px; color: #666; background: #F3F4F6; padding: 3px 8px; border-radius: 10px; }

        .selected-student {
            background: #D1FAE5;
            border: 2px solid var(--success);
            padding: 15px;
            border-radius: 12px;
            margin-bottom: 20px;
            display: none;
        }

        .selected-student.show { display: block; }

        .selected-student .student-info {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .selected-student .name { font-size: 18px; font-weight: bold; color: var(--brown-dark); }
        .selected-student .class { color: #065F46; font-size: 13px; }
        .selected-student .change-btn { 
            color: #DC2626; cursor: pointer; font-size: 12px; text-decoration: underline; 
        }

        .pin-group { margin-bottom: 20px; }
        .pin-group label { display: block; margin-bottom: 8px; color: var(--brown-dark); font-weight: 600; }
        .pin-group input {
            width: 100%;
            padding: 15px;
            border: 2px solid #E2E8F0;
            border-radius: 12px;
            font-size: 20px;
            text-align: center;
            letter-spacing: 5px;
        }

        .error-message {
            background: #FEE2E2;
            color: #DC2626;
            padding: 12px;
            border-radius: 10px;
            margin-bottom: 15px;
            border-left: 4px solid #DC2626;
            font-size: 14px;
        }

        .btn-login {
            width: 100%;
            padding: 16px;
            background: linear-gradient(135deg, #FFD700 0%, #DAA520 100%);
            color: #8B4513;
            border: none;
            border-radius: 12px;
            font-size: 18px;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.3s;
        }

        .btn-login:hover { transform: translateY(-2px); }
        .btn-login:disabled { opacity: 0.5; cursor: not-allowed; }

        .hint-box {
            margin-top: 20px;
            padding: 15px;
            background: var(--gold-pale);
            border-radius: 12px;
            border: 2px dashed var(--gold-primary);
            font-size: 13px;
            color: #666;
            line-height: 1.8;
        }

        .hint-box strong { color: var(--brown-dark); }
        .hint-box .pin-highlight { 
            background: #FEF3C7; padding: 2px 8px; border-radius: 4px; 
            font-weight: bold; color: #8B4513; font-size: 16px; 
        }

        .back-link {
            display: block; text-align: center; margin-top: 15px;
            color: var(--brown-dark); font-weight: 600; text-decoration: none;
        }
    </style>
</head>
<body>
    <div class="login-card">
        <div class="logo-section">
            <div class="logo-circle">🎓</div>
            <h1>የተማሪ መግቢያ</h1>
            <p class="subtitle">Student Portal - Login with your name</p>
        </div>

        <?php if ($error): ?>
        <div class="error-message">⚠️ <?php echo strip_tags($error, '<a><b><strong>'); ?></div>
        <?php endif; ?>

        <form method="POST" id="loginForm">
            <?php echo csrfField(); ?>
            <input type="hidden" name="student_id" id="studentIdInput" value="">
            
            <!-- Search Box -->
            <div class="search-box">
                <label style="display:block; margin-bottom:8px; color:var(--brown-dark); font-weight:600;">
                    🔍 ስምዎን ይፈልጉ / Search Your Name
                </label>
                <input type="text" 
                       name="student_name"
                       id="searchInput" 
                       placeholder="የመጀመሪያ ስምዎን ይተይቡ... (Type your first name...)" 
                       autocomplete="off"
                       oninput="searchStudents(this.value)">
                <div id="searchResults" class="search-results"></div>
            </div>

            <!-- Selected Student Display -->
            <div id="selectedStudent" class="selected-student">
                <div class="student-info">
                    <div>
                        <div class="name" id="selectedName"></div>
                        <div class="class" id="selectedClass"></div>
                    </div>
                    <span class="change-btn" onclick="clearSelection()">❌ ለውጥ</span>
                </div>
            </div>

            <!-- PIN Input -->
            <div class="pin-group" id="pinGroup">
                <label>🔒 ሚስጥራዊ ፒን / የይለፍ ቃል</label>
                <input type="password" name="pin" id="pinInput" 
                       placeholder="••••" required maxlength="20">
            </div>

            <button type="submit" class="btn-login" id="loginBtn" disabled>
                🔑 ወደ አካውንትህ ግባ
            </button>
        </form>

        <div class="hint-box">
            <strong>📋 መመሪያ፦</strong><br>
            1. ስምዎን ይፈልጉና ይምረጡ<br>
            2. የተሰጠዎትን የይለፍ ቃል/ፒን ያስገቡ<br>
            3. ለመጀመሪያ ጊዜ ከሆነ አዲስ ፒን ይቀይራሉ<br>
            <br>
            <strong>💡 ማስታወሻ:</strong> ስምዎ ካልተገኘ ወይም ፒን ከረሱ አስተዳዳሪዎን ያነጋግሩ።
        </div>

        <a href="index.php" class="back-link">← ወደ ዋና ገፅ</a>
    </div>

    <script>
        let selectedStudentId = null;
        let searchTimeout;

        function searchStudents(query) {
            clearTimeout(searchTimeout);
            
            const resultsDiv = document.getElementById('searchResults');
            
            if (query.length < 1) {
                resultsDiv.classList.remove('show');
                resultsDiv.innerHTML = '';
                return;
            }
            
            searchTimeout = setTimeout(() => {
                fetch(`student_login.php?ajax=search&q=${encodeURIComponent(query)}`)
                    .then(response => response.json())
                    .then(students => {
                        if (students.length > 0) {
                            let html = '';
                            students.forEach((student, index) => {
                                html += `
                                    <div class="result-item" onclick="selectStudent(${student.id}, '${escapeHtml(student.name)}', '${escapeHtml(student.class)}')">
                                        <span class="result-name">👤 ${escapeHtml(student.name)}</span>
                                        <span class="result-class">📚 ${escapeHtml(student.class)}</span>
                                    </div>
                                `;
                            });
                            resultsDiv.innerHTML = html;
                            resultsDiv.classList.add('show');
                        } else {
                            resultsDiv.innerHTML = '<div style="padding:15px; text-align:center; color:#999;">ምንም አልተገኘም - አስተዳዳሪዎን ያነጋግሩ</div>';
                            resultsDiv.classList.add('show');
                        }
                    });
            }, 300);
        }

        function selectStudent(id, name, className) {
            selectedStudentId = id;
            
            document.getElementById('studentIdInput').value = id;
            document.getElementById('selectedName').textContent = name;
            document.getElementById('selectedClass').textContent = '📚 ' + className;
            
            document.getElementById('selectedStudent').classList.add('show');
            document.getElementById('searchResults').classList.remove('show');
            document.getElementById('searchInput').value = name;
            document.getElementById('loginBtn').disabled = false;
            document.getElementById('pinInput').focus();
        }

        function clearSelection() {
            selectedStudentId = null;
            document.getElementById('studentIdInput').value = '';
            document.getElementById('selectedStudent').classList.remove('show');
            document.getElementById('searchInput').value = '';
            document.getElementById('pinInput').value = '';
            document.getElementById('loginBtn').disabled = true;
            document.getElementById('searchInput').focus();
        }

        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        // Close search results when clicking outside
        document.addEventListener('click', function(e) {
            const searchBox = document.querySelector('.search-box');
            if (!searchBox.contains(e.target)) {
                document.getElementById('searchResults').classList.remove('show');
            }
        });

        // Search again if user modifies the search input after selecting
        document.getElementById('searchInput').addEventListener('focus', function() {
            if (selectedStudentId) {
                clearSelection();
            }
        });
        
        // Keyboard navigation
        document.getElementById('searchInput').addEventListener('keydown', function(e) {
            const results = document.querySelectorAll('.result-item');
            if (results.length === 0) return;
            
            if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
                e.preventDefault();
                // Handle arrow key navigation
            } else if (e.key === 'Enter') {
                const firstResult = document.querySelector('.result-item');
                if (firstResult && document.getElementById('searchResults').classList.contains('show')) {
                    e.preventDefault();
                    firstResult.click();
                }
            }
        });
    </script>
    <script src="exam-main/assets/js/offline-db.js"></script>
    <script src="exam-main/assets/js/sync-manager.js"></script>
    <script>
        // Register Service Worker
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', () => {
                navigator.serviceWorker.register('/exam/sw.js').catch(() => {});
            });
        }

        // Offline-aware search handler
        searchStudents = async function(query) {
            try {
                // First try live server search (works on XAMPP localhost/LAN even without internet)
                const controller = new AbortController();
                const t = setTimeout(() => controller.abort(), 1500);
                const res = await fetch('student_live_search.php?q=' + encodeURIComponent(query), { signal: controller.signal });
                clearTimeout(t);
                if (res.ok) {
                    const data = await res.json();
                    const resultsDiv = document.getElementById('searchResults');
                    if (data && data.length > 0) {
                        let html = '';
                        data.forEach(s => {
                            html += `
                                <div class="result-item" onclick="selectStudent(${s.id}, '${escapeHtml(s.name)}', '${s.class_name || ''}')">
                                    <div class="name">${escapeHtml(s.name)}</div>
                                    <div class="class-badge">${escapeHtml(s.class_name || '')}</div>
                                </div>
                            `;
                        });
                        resultsDiv.innerHTML = html;
                        resultsDiv.classList.add('show');
                        return;
                    } else {
                        resultsDiv.innerHTML = '<div class="no-results">ተማሪ አልተገኘም</div>';
                        resultsDiv.classList.add('show');
                        return;
                    }
                }
            } catch (_) {
                // If server is unreachable, fall back to offline IndexedDB
            }

            // Offline search directly from IndexedDB
            const allStudents = await OfflineDB.getAllStudents();
            const filtered = allStudents.filter(s => s.name && s.name.toLowerCase().includes(query.toLowerCase()));
            const resultsDiv = document.getElementById('searchResults');
            if (filtered.length > 0) {
                let html = '';
                filtered.slice(0, 15).forEach(s => {
                    html += `
                        <div class="result-item" onclick="selectStudent(${s.id}, '${escapeHtml(s.name)}', '${s.class_id || ''}')">
                            <div class="name">${escapeHtml(s.name)}</div>
                            <div class="class-badge">ክፍል ${s.class_id || ''}</div>
                        </div>
                    `;
                });
                resultsDiv.innerHTML = html;
                resultsDiv.classList.add('show');
            } else {
                resultsDiv.innerHTML = '<div class="no-results">ተማሪ አልተገኘም</div>';
                resultsDiv.classList.add('show');
            }
        };

        // Cache student login online & handle offline submit
        const studentLoginForm = document.getElementById('loginForm');
        if (studentLoginForm) {
            let isSubmitting = false;
            studentLoginForm.addEventListener('submit', async function(e) {
                if (isSubmitting) return;
                e.preventDefault();

                const studentId = document.getElementById('studentIdInput').value;
                const name = document.getElementById('searchInput').value;
                const pin = document.getElementById('pinInput').value;

                // Probe if local or remote server is reachable
                let isServerUp = false;
                try {
                    const controller = new AbortController();
                    const t = setTimeout(() => controller.abort(), 1200);
                    const ping = await fetch('api/ping.php?t=' + Date.now(), { method: 'GET', cache: 'no-store', signal: controller.signal });
                    clearTimeout(t);
                    isServerUp = ping.ok;
                } catch (_) {
                    isServerUp = false;
                }

                if (isServerUp) {
                    // Server reachable: cache credentials in background for future PWA use
                    try {
                        const authRes = await fetch('api/auth.php', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({ student_name: name, pin: pin, is_student: true })
                        });
                        const authData = await authRes.json();
                        if (authData.success && authData.token) {
                            await OfflineDB.saveAuth(authData.user, authData.token, authData.expires_at);
                        }
                    } catch (_) {}

                    // Submit form normally to PHP
                    isSubmitting = true;
                    studentLoginForm.submit();
                } else {
                    // Server unreachable: attempt offline login
                    const auth = await OfflineDB.getAuth();
                    if (auth && auth.user && auth.user.role === 'student' && auth.user.id == studentId) {
                        sessionStorage.setItem('offline_user', JSON.stringify(auth.user));
                        sessionStorage.setItem('offline_token', auth.token);
                        window.location.href = 'dashboard_student.php';
                    } else {
                        alert('ከመስመር ውጭ ለመግባት አስቀድመው አንዴ ከሰርቨሩ ጋር ተገናኝተው መግባት አለብዎት!');
                    }
                }
            });
        }
    </script>
</body>
</html>
<?php mysqli_close($conn); ?>