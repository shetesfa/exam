<?php
require_once 'db.php';

// Revoke all offline tokens for the current student
$student_id = intval($_SESSION['student_id'] ?? 0);
if ($student_id > 0) {
    dbExecute($conn, "UPDATE auth_tokens SET revoked = 1 WHERE user_id = ? AND role = 'student'", "i", [$student_id]);
}

$_SESSION = [];
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}
session_destroy();
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>ውጣ | Logging out...</title>
</head>
<body>
<script>
(async function() {
    const target = (<?php echo json_encode(BASE_URL); ?>).replace(/\/$/, '') + '/student_login.php';
    
    // 1. Delete all Service Worker caches
    try {
        if ('caches' in window) {
            const keys = await caches.keys();
            await Promise.all(keys.map(k => caches.delete(k)));
        }
    } catch (e) {
        console.warn('Cache clear error:', e);
    }

    // 2. Delete IndexedDB (exam_offline_db)
    try {
        if ('indexedDB' in window) {
            await new Promise((resolve) => {
                const req = indexedDB.deleteDatabase('exam_offline_db');
                req.onsuccess = req.onerror = req.onblocked = () => resolve();
                setTimeout(resolve, 1000);
            });
        }
    } catch (e) {
        console.warn('IndexedDB clear error:', e);
    }

    // 3. Clear local & session storage
    try {
        localStorage.clear();
        sessionStorage.clear();
    } catch (e) {}

    window.location.replace(target);
})();
</script>
</body>
</html>