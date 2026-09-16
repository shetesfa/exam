<?php
require_once 'db.php';
requireAdmin();

$f_user = intval($_GET['user_id'] ?? 0);
$f_action = trim($_GET['action'] ?? '');

$where = "WHERE 1=1";
$params = []; $types = "";
if ($f_user) { $where .= " AND a.user_id = ?"; $types .= "i"; $params[] = $f_user; }
if ($f_action) { $where .= " AND a.action LIKE ?"; $types .= "s"; $params[] = '%' . $f_action . '%'; }

$per_page = 50;
$page = max(1, intval($_GET['page'] ?? 1));
$offset = ($page - 1) * $per_page;

$count_row = dbFetchOne($conn, "SELECT COUNT(*) as cnt FROM audit_log a $where", $types, $params);
$total = $count_row ? (int)$count_row['cnt'] : 0;

$logs = dbFetchAll(
    $conn,
    "SELECT a.*, u.name as user_name FROM audit_log a LEFT JOIN users u ON a.user_id = u.id
     $where ORDER BY a.created_at DESC LIMIT $per_page OFFSET $offset",
    $types,
    $params
);
$total_pages = max(1, ceil($total / $per_page));

$users = dbFetchAll($conn, "SELECT id, name FROM users ORDER BY name");
$nav_active = 'audit_log_admin';
?>
<!DOCTYPE html>
<html lang="am">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>የክትትል መዝገብ | አጸደ ትጉሃን</title>
<?php include 'pwa_head.php'; ?>
<style>
:root { --brown-dark:#8B4513; --gold-primary:#FFD700; }
* { margin:0; padding:0; box-sizing:border-box; font-family:'Segoe UI',sans-serif; }
body { background:#FAF9F6; }
.main-container { max-width:1000px; margin:20px auto; padding:0 15px 60px; }
.card { background:white; border-radius:14px; padding:18px; margin-bottom:16px; box-shadow:0 4px 12px rgba(0,0,0,0.08); }
.filters { display:flex; gap:10px; flex-wrap:wrap; }
.filters select, .filters input { padding:8px; border-radius:8px; border:1px solid #ddd; }
table { width:100%; border-collapse:collapse; font-size:13px; }
th, td { padding:8px; text-align:left; border-bottom:1px solid #eee; }
th { color:var(--brown-dark); }
.pager { display:flex; justify-content:center; gap:6px; padding:15px; flex-wrap:wrap; }
.pager a, .pager span { padding:6px 12px; border-radius:8px; text-decoration:none; }
.pager span { background:var(--gold-primary); color:var(--brown-dark); font-weight:700; }
.pager a { background:#F3F4F6; color:#555; }
@media (max-width: 600px) {
    .main-container { padding: 0 10px 40px; margin: 12px auto; }
    .card { padding: 14px 12px; }
    .filters { flex-direction: column; }
    .filters select, .filters input, .filters button { width: 100%; min-height: 42px; font-size: 14px; }
    table { font-size: 11px; min-width: 500px; }
    th, td { padding: 6px 4px; }
}
</style>
</head>
<body>
<?php include 'mobile_nav.php'; ?>
<div class="main-container">
    <div class="card">
        <h2 style="color:var(--brown-dark); margin-bottom:12px;">📜 የክትትልና ቁጥጥር መዝገብ</h2>
        <form method="GET" class="filters">
            <select name="user_id" onchange="this.form.submit()">
                <option value="">ሁሉም ተጠቃሚዎች</option>
                <?php foreach ($users as $u): ?>
                <option value="<?php echo $u['id']; ?>" <?php echo $f_user == $u['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($u['name']); ?></option>
                <?php endforeach; ?>
            </select>
            <input type="text" name="action" placeholder="የተከናወነ ተግባር ፈልግ..." value="<?php echo htmlspecialchars($f_action); ?>">
            <button type="submit" style="padding:8px 16px; border-radius:8px; border:none; background:var(--gold-primary); font-weight:700; cursor:pointer;">🔍</button>
        </form>
    </div>

    <div class="card">
        <div style="overflow-x:auto;">
        <table>
            <thead><tr><th>ጊዜ</th><th>ተጠቃሚ</th><th>ተግባር</th><th>ዝርዝር መረጃ</th><th>የአይፒ አድራሻ</th></tr></thead>
            <tbody>
                <?php if (empty($logs)): ?>
                <tr><td colspan="5" style="text-align:center; color:#999; padding:30px;">ምንም መዝገብ አልተገኘም።</td></tr>
                <?php else: foreach ($logs as $l): ?>
                <tr>
                    <td><?php echo date('Y-m-d H:i', strtotime($l['created_at'])); ?></td>
                    <td><?php echo htmlspecialchars($l['user_name'] ?? 'ሲስተም'); ?></td>
                    <td><?php echo htmlspecialchars($l['action']); ?></td>
                    <td><?php echo htmlspecialchars($l['details'] ?? '—'); ?></td>
                    <td><?php echo htmlspecialchars($l['ip_address'] ?? '—'); ?></td>
                </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
        </div>
        <?php if ($total_pages > 1): ?>
        <div class="pager">
            <?php
            $qs = ($f_user ? '&user_id=' . $f_user : '') . ($f_action ? '&action=' . urlencode($f_action) : '');
            for ($p = 1; $p <= $total_pages; $p++):
                if ($p === $page): ?>
                    <span><?php echo $p; ?></span>
                <?php else: ?>
                    <a href="?page=<?php echo $p . $qs; ?>"><?php echo $p; ?></a>
                <?php endif;
            endfor; ?>
        </div>
        <?php endif; ?>
    </div>
</div>
</body>
</html>
<?php mysqli_close($conn); ?>
