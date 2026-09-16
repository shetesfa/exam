<?php
require_once 'db.php';

if (!isLoggedIn() && !isStudent()) {
    header("Location: index.php");
    exit();
}

// Notifications are keyed to a staff user_id today (targeting resolves via
// teacher_class / attendance_assignments). Students don't have a `users`
// row, so for now the student view shows only untargeted (ALL) notices.
$user_id = intval($_SESSION['user_id'] ?? 0);
$is_student = isStudent() && !$user_id;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$is_student) {
    if (verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        if (isset($_POST['mark_read'])) {
            markNotificationRead($conn, intval($_POST['notification_id'] ?? 0), $user_id);
        } elseif (isset($_POST['mark_all_read'])) {
            $unread = getNotificationsForUser($conn, $user_id, true, 200);
            foreach ($unread as $n) {
                markNotificationRead($conn, $n['id'], $user_id);
            }
        }
    }
    header("Location: notifications.php");
    exit();
}

$notifications = $is_student
    ? dbFetchAll($conn, "SELECT n.*, 0 as is_read FROM notifications n
                          LEFT JOIN notification_targets t ON t.notification_id = n.id
                          WHERE t.id IS NULL ORDER BY n.created_at DESC LIMIT 50")
    : getNotificationsForUser($conn, $user_id, false, 50);

$unread_count = 0;
foreach ($notifications as $n) if (!$n['is_read']) $unread_count++;

$nav_active = 'notifications';
?>
<!DOCTYPE html>
<html lang="am">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>ማሳወቂያዎች | አጸደ ትጉሃን</title>
<?php include 'pwa_head.php'; ?>
<style>
:root { --brown-dark:#8B4513; --gold-primary:#FFD700; --success:#10B981; --error:#EF4444; }
* { margin:0; padding:0; box-sizing:border-box; font-family:'Segoe UI',sans-serif; }
body { background:#FAF9F6; }
.main-container { max-width:700px; margin:20px auto; padding:0 15px 60px; }
.card { background:white; border-radius:14px; padding:15px; box-shadow:0 4px 12px rgba(0,0,0,0.08); }
.top-row { display:flex; justify-content:space-between; align-items:center; margin-bottom:15px; }
.top-row h2 { color:var(--brown-dark); font-size:18px; }
.btn-mark { background:none; border:1px solid var(--gold-primary); color:var(--brown-dark); padding:6px 14px; border-radius:20px; font-size:13px; cursor:pointer; font-weight:600; }
.notif { padding:14px 10px; border-bottom:1px solid #eee; display:flex; gap:10px; align-items:flex-start; }
.notif:last-child { border-bottom:none; }
.notif.unread { background:#FFF8DC; border-radius:8px; }
.notif-dot { width:8px; height:8px; border-radius:50%; background:var(--error); margin-top:6px; flex-shrink:0; }
.notif-dot.read { background:transparent; }
.notif-title { font-weight:700; color:var(--brown-dark); font-size:14px; }
.notif-msg { font-size:13px; color:#555; margin-top:2px; }
.notif-time { font-size:11px; color:#999; margin-top:4px; }
.empty { text-align:center; color:#999; padding:40px 20px; }
.priority-high { border-left:3px solid var(--error); }
@media (max-width: 500px) {
    .main-container { padding: 0 10px 40px; margin: 12px auto; }
    .card { padding: 12px 10px; }
    .top-row { flex-direction: column; align-items: flex-start; gap: 8px; }
    .top-row form { width: 100%; }
    .top-row .btn-mark { width: 100%; text-align: center; }
}
</style>
</head>
<body>
<?php include 'mobile_nav.php'; ?>
<div class="main-container">
    <!-- Push Notifications Banner -->
    <div class="card" id="push-notification-banner" style="margin-bottom:15px; border-left:4px solid var(--gold-primary);">
        <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px;">
            <div style="flex:1; min-width:240px;">
                <div style="font-weight:700; color:var(--brown-dark); font-size:15px; display:flex; align-items:center; gap:6px;">
                    <span>🔔</span> የቀጥታ የስልክና የብሮውዘር ማሳወቂያ
                </div>
                <div id="push-status-text" style="font-size:13px; color:#666; margin-top:3px;">
                    ፈተናዎችን፣ የውጤት ቀናትንና አስፈላጊ መረጃዎችን በቅጽበት በስልክዎ ላይ ያግኙ።
                </div>
            </div>
            <div id="push-actions" style="display:flex; gap:8px; align-items:center;">
                <button type="button" id="push-toggle-btn" class="btn-mark" onclick="PushNotifications.toggle()" style="background:var(--gold-primary); font-weight:700;">
                    🔔 ማሳወቂያ አብራ
                </button>
                <button type="button" id="push-test-btn" class="btn-mark" style="display:none; background:#E0F2FE; color:#0369A1; border-color:#BAE6FD;" onclick="PushNotifications.sendTestPush()" title="የሙከራ ማሳወቂያ ላክ">
                    🧪 ሞክር
                </button>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="top-row">
            <h2>🔔 ማሳወቂያዎች <?php if ($unread_count): ?><span style="color:var(--error);">(<?php echo $unread_count; ?>)</span><?php endif; ?></h2>
            <?php if (!$is_student && $unread_count > 0): ?>
            <form method="POST"><?php echo csrfField(); ?>
                <button type="submit" name="mark_all_read" class="btn-mark">ሁሉንም እንደተነበበ ምልክት አድርግ</button>
            </form>
            <?php endif; ?>
        </div>
        <?php if (empty($notifications)): ?>
            <div class="empty">📭 ምንም ማሳወቂያ የለም።</div>
        <?php else: foreach ($notifications as $n): ?>
        <div class="notif <?php echo !$n['is_read'] ? 'unread' : ''; ?> <?php echo $n['priority'] === 'high' ? 'priority-high' : ''; ?>">
            <div class="notif-dot <?php echo $n['is_read'] ? 'read' : ''; ?>"></div>
            <div style="flex:1;">
                <div class="notif-title"><?php echo htmlspecialchars($n['title']); ?></div>
                <div class="notif-msg"><?php echo htmlspecialchars($n['message']); ?></div>
                <div class="notif-time"><?php echo date('Y-m-d H:i', strtotime($n['created_at'])); ?></div>
            </div>
            <?php if (!$is_student && !$n['is_read']): ?>
            <form method="POST"><?php echo csrfField(); ?>
                <input type="hidden" name="notification_id" value="<?php echo $n['id']; ?>">
                <button type="submit" name="mark_read" class="btn-mark">✓</button>
            </form>
            <?php endif; ?>
        </div>
        <?php endforeach; endif; ?>
    </div>
</div>
</body>
</html>
<?php mysqli_close($conn); ?>
