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

function getActionBadgeInfo($action) {
    $act = strtolower($action);
    if (strpos($act, 'login') !== false || strpos($act, 'auth') !== false) {
        return ['bg' => '#FEF3C7', 'color' => '#92400E', 'icon' => '🔑', 'label' => 'መግቢያ'];
    } elseif (strpos($act, 'delete') !== false || strpos($act, 'remove') !== false) {
        return ['bg' => '#FEE2E2', 'color' => '#991B1B', 'icon' => '🗑️', 'label' => 'ስረዛ'];
    } elseif (strpos($act, 'update') !== false || strpos($act, 'edit') !== false || strpos($act, 'change') !== false) {
        return ['bg' => '#E0F2FE', 'color' => '#075985', 'icon' => '✏️', 'label' => 'ማሻሻያ'];
    } elseif (strpos($act, 'insert') !== false || strpos($act, 'add') !== false || strpos($act, 'create') !== false) {
        return ['bg' => '#DCFCE7', 'color' => '#166534', 'icon' => '➕', 'label' => 'ምዝገባ'];
    } elseif (strpos($act, 'backup') !== false) {
        return ['bg' => '#F3E8FF', 'color' => '#6B21A8', 'icon' => '💾', 'label' => 'ምትኬ'];
    } elseif (strpos($act, 'password') !== false || strpos($act, 'pin') !== false) {
        return ['bg' => '#FFEDD5', 'color' => '#C2410C', 'icon' => '🔒', 'label' => 'የይለፍ ቃል'];
    }
    return ['bg' => '#F3F4F6', 'color' => '#4B5563', 'icon' => '⚡', 'label' => 'ክንውን'];
}
?>
<!DOCTYPE html>
<html lang="am">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>የክትትልና ቁጥጥር መዝገብ | አጸደ ትጉሃን</title>
<?php include 'pwa_head.php'; ?>
<style>
:root {
    --brown-dark: #8B4513;
    --brown-medium: #A52A2A;
    --gold-primary: #FFD700;
    --gold-dark: #DAA520;
    --gold-pale: #FFF8DC;
    --bg-cream: #FAF9F6;
    --card-bg: #FFFFFF;
    --text-main: #1F2937;
    --text-muted: #6B7280;
    --border-color: #E5E7EB;
}

* { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Segoe UI', system-ui, -apple-system, sans-serif; }
body { background: var(--bg-cream); color: var(--text-main); min-height: 100vh; }

.main-container { max-width: 1200px; margin: 24px auto; padding: 0 16px 80px; }

/* Page Header Card */
.page-header-card {
    background: linear-gradient(135deg, #8B4513 0%, #A52A2A 100%);
    border-radius: 16px;
    padding: 24px 28px;
    color: white;
    margin-bottom: 24px;
    box-shadow: 0 8px 24px rgba(139, 69, 19, 0.18);
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 16px;
    position: relative;
    overflow: hidden;
}
.page-header-card::after {
    content: '📜';
    position: absolute;
    right: 20px;
    bottom: -15px;
    font-size: 100px;
    opacity: 0.12;
    pointer-events: none;
}
.header-info h1 {
    font-size: 22px;
    font-weight: 800;
    color: var(--gold-primary);
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 6px;
}
.header-info p {
    font-size: 13.5px;
    color: rgba(255, 255, 255, 0.9);
}

/* Stats Row */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 16px;
    margin-bottom: 24px;
}
.stat-box {
    background: var(--card-bg);
    border: 1px solid var(--border-color);
    border-radius: 14px;
    padding: 16px 20px;
    display: flex;
    align-items: center;
    gap: 16px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.04);
}
.stat-icon {
    width: 48px;
    height: 48px;
    border-radius: 12px;
    background: var(--gold-pale);
    color: var(--brown-dark);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 22px;
    flex-shrink: 0;
}
.stat-data .stat-val {
    font-size: 22px;
    font-weight: 800;
    color: var(--brown-dark);
}
.stat-data .stat-lbl {
    font-size: 12px;
    color: var(--text-muted);
    font-weight: 600;
}

/* Filter Card */
.filter-card {
    background: var(--card-bg);
    border-radius: 16px;
    padding: 20px;
    margin-bottom: 24px;
    border: 1px solid var(--border-color);
    box-shadow: 0 4px 14px rgba(0,0,0,0.05);
}
.filter-card h3 {
    font-size: 15px;
    color: var(--brown-dark);
    margin-bottom: 14px;
    display: flex;
    align-items: center;
    gap: 8px;
}
.filter-form {
    display: grid;
    grid-template-columns: 1fr 1.5fr auto auto;
    gap: 12px;
    align-items: center;
}
.filter-select, .filter-input {
    width: 100%;
    padding: 11px 14px;
    border-radius: 10px;
    border: 1.5px solid #D1D5DB;
    font-size: 13.5px;
    outline: none;
    transition: all 0.2s ease;
    background: var(--card-bg);
    color: var(--text-main);
}
.filter-select:focus, .filter-input:focus {
    border-color: var(--gold-dark);
    box-shadow: 0 0 0 3px rgba(218, 165, 32, 0.15);
}
.btn-search {
    padding: 11px 22px;
    background: linear-gradient(135deg, var(--gold-primary) 0%, var(--gold-dark) 100%);
    color: var(--brown-dark);
    border: none;
    border-radius: 10px;
    font-weight: 700;
    font-size: 14px;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    transition: transform 0.2s;
}
.btn-search:hover { transform: translateY(-1px); }
.btn-clear {
    padding: 11px 16px;
    background: #F3F4F6;
    color: var(--text-muted);
    border-radius: 10px;
    text-decoration: none;
    font-size: 13px;
    font-weight: 600;
    display: inline-flex;
    align-items: center;
    gap: 4px;
}
.btn-clear:hover { background: #E5E7EB; color: var(--text-main); }

/* Table Card */
.table-card {
    background: var(--card-bg);
    border-radius: 16px;
    border: 1px solid var(--border-color);
    box-shadow: 0 4px 16px rgba(0,0,0,0.05);
    overflow: hidden;
}
.table-responsive {
    width: 100%;
    overflow-x: auto;
    -webkit-overflow-scrolling: touch;
}
table.log-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 13.5px;
    text-align: left;
}
table.log-table th {
    background: #F9FAFB;
    color: var(--brown-dark);
    font-weight: 700;
    padding: 14px 18px;
    border-bottom: 2px solid #E5E7EB;
    white-space: nowrap;
}
table.log-table td {
    padding: 14px 18px;
    border-bottom: 1px solid #F3F4F6;
    vertical-align: middle;
}
table.log-table tbody tr {
    transition: background 0.15s ease;
}
table.log-table tbody tr:hover {
    background: rgba(255, 215, 0, 0.04);
}

/* User Avatar & Name */
.user-chip {
    display: inline-flex;
    align-items: center;
    gap: 10px;
}
.user-avatar {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    background: linear-gradient(135deg, var(--gold-primary), var(--gold-dark));
    color: var(--brown-dark);
    font-weight: 800;
    font-size: 13px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}
.user-name-text {
    font-weight: 600;
    color: var(--text-main);
}

/* Action Badge */
.action-pill {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 5px 12px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 700;
    white-space: nowrap;
}
.action-raw {
    font-size: 11px;
    color: var(--text-muted);
    margin-left: 4px;
    font-family: monospace;
}

/* Details & IP */
.details-cell {
    max-width: 320px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    color: #4B5563;
}
.ip-badge {
    background: #F3F4F6;
    padding: 3px 8px;
    border-radius: 6px;
    font-family: monospace;
    font-size: 11.5px;
    color: var(--text-muted);
}
.time-text {
    font-size: 12.5px;
    color: var(--text-muted);
    white-space: nowrap;
}

/* Pagination */
.pager-container {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 16px 20px;
    background: #F9FAFB;
    border-top: 1px solid #E5E7EB;
    flex-wrap: wrap;
    gap: 12px;
}
.pager-summary {
    font-size: 13px;
    color: var(--text-muted);
}
.pager-controls {
    display: flex;
    gap: 6px;
    flex-wrap: wrap;
    align-items: center;
}
.pager-controls a, .pager-controls span {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 36px;
    height: 36px;
    padding: 0 10px;
    border-radius: 8px;
    font-size: 13px;
    font-weight: 600;
    text-decoration: none;
    transition: all 0.2s ease;
}
.pager-controls a {
    background: white;
    color: var(--text-main);
    border: 1px solid #E5E7EB;
}
.pager-controls a:hover {
    background: var(--gold-pale);
    border-color: var(--gold-dark);
    color: var(--brown-dark);
}
.pager-controls span.current {
    background: linear-gradient(135deg, var(--gold-primary), var(--gold-dark));
    color: var(--brown-dark);
    border: 1px solid transparent;
    font-weight: 800;
    box-shadow: 0 2px 6px rgba(218, 165, 32, 0.3);
}

/* Empty State */
.empty-state {
    text-align: center;
    padding: 50px 20px;
    color: var(--text-muted);
}
.empty-icon {
    font-size: 44px;
    margin-bottom: 12px;
    opacity: 0.7;
}

@media (max-width: 768px) {
    .main-container { padding: 0 10px 40px; margin: 12px auto; }
    .page-header-card { padding: 18px 16px; }
    .page-header-card h1 { font-size: 19px; }
    .filter-form { grid-template-columns: 1fr; }
    .btn-search, .btn-clear { width: 100%; justify-content: center; min-height: 42px; }
    .stats-grid { grid-template-columns: 1fr; gap: 10px; }
    .details-cell { max-width: 180px; }
    .pager-container { justify-content: center; text-align: center; }
}
</style>
</head>
<body>
<?php include 'mobile_nav.php'; ?>

<div class="main-container">
    <!-- Header -->
    <div class="page-header-card">
        <div class="header-info">
            <h1>📜 የክትትልና ቁጥጥር መዝገብ</h1>
            <p>በሲስተሙ ውስጥ የተከናወኑ ሁሉንም አስተዳደራዊ ተግባራትና ለውጦች በቀጥታ ይከታተሉ።</p>
        </div>
    </div>

    <!-- Stats -->
    <div class="stats-grid">
        <div class="stat-box">
            <div class="stat-icon">📊</div>
            <div class="stat-data">
                <div class="stat-val"><?php echo number_format($total); ?></div>
                <div class="stat-lbl">ጠቅላላ የተመዘገቡ ተግባራት</div>
            </div>
        </div>
        <div class="stat-box">
            <div class="stat-icon">👥</div>
            <div class="stat-data">
                <div class="stat-val"><?php echo count($users); ?></div>
                <div class="stat-lbl">የተመዘገቡ ተጠቃሚዎች</div>
            </div>
        </div>
        <div class="stat-box">
            <div class="stat-icon">📄</div>
            <div class="stat-data">
                <div class="stat-val"><?php echo $page; ?> / <?php echo $total_pages; ?></div>
                <div class="stat-lbl">የአሁኑ ገጽ</div>
            </div>
        </div>
    </div>

    <!-- Filters -->
    <div class="filter-card">
        <h3>🔍 መዝገብ ማጣሪያ</h3>
        <form method="GET" class="filter-form">
            <select name="user_id" class="filter-select" onchange="this.form.submit()">
                <option value="">👤 ሁሉም ተጠቃሚዎች</option>
                <?php foreach ($users as $u): ?>
                <option value="<?php echo $u['id']; ?>" <?php echo $f_user == $u['id'] ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($u['name']); ?>
                </option>
                <?php endforeach; ?>
            </select>
            
            <input type="text" name="action" class="filter-input" placeholder="የተከናወነ ተግባር ፈልግ (ለምሳሌ: login, delete...)" value="<?php echo htmlspecialchars($f_action); ?>">
            
            <button type="submit" class="btn-search">🔍 ፈልግ</button>
            <?php if ($f_user || $f_action): ?>
            <a href="audit_log_admin.php" class="btn-clear">✕ አጽዳ</a>
            <?php endif; ?>
        </form>
    </div>

    <!-- Table -->
    <div class="table-card">
        <div class="table-responsive">
            <table class="log-table">
                <thead>
                    <tr>
                        <th>ቀን እና ሰዓት</th>
                        <th>ተጠቃሚ</th>
                        <th>ተግባር</th>
                        <th>ዝርዝር መረጃ</th>
                        <th>የአይፒ አድራሻ (IP)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($logs)): ?>
                    <tr>
                        <td colspan="5">
                            <div class="empty-state">
                                <div class="empty-icon">📭</div>
                                <div>ምንም የተመዘገበ የክትትል መረጃ አልተገኘም።</div>
                            </div>
                        </td>
                    </tr>
                    <?php else: foreach ($logs as $l): 
                        $badge = getActionBadgeInfo($l['action']);
                        $uname = $l['user_name'] ?? 'ሲስተም';
                        $initial = mb_substr($uname, 0, 1, 'UTF-8');
                    ?>
                    <tr>
                        <td>
                            <div class="time-text">
                                📅 <?php echo date('Y-m-d', strtotime($l['created_at'])); ?><br>
                                <span style="font-size: 11px; opacity: 0.8;">⏰ <?php echo date('h:i:s A', strtotime($l['created_at'])); ?></span>
                            </div>
                        </td>
                        <td>
                            <div class="user-chip">
                                <div class="user-avatar"><?php echo htmlspecialchars($initial); ?></div>
                                <span class="user-name-text"><?php echo htmlspecialchars($uname); ?></span>
                            </div>
                        </td>
                        <td>
                            <span class="action-pill" style="background:<?php echo $badge['bg']; ?>; color:<?php echo $badge['color']; ?>;">
                                <?php echo $badge['icon']; ?> <?php echo htmlspecialchars($badge['label']); ?>
                            </span>
                            <span class="action-raw">(<?php echo htmlspecialchars($l['action']); ?>)</span>
                        </td>
                        <td>
                            <div class="details-cell" title="<?php echo htmlspecialchars($l['details'] ?? ''); ?>">
                                <?php echo htmlspecialchars($l['details'] ?? '—'); ?>
                            </div>
                        </td>
                        <td>
                            <span class="ip-badge"><?php echo htmlspecialchars($l['ip_address'] ?? '—'); ?></span>
                        </td>
                    </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>

        <?php if ($total_pages > 1): ?>
        <div class="pager-container">
            <div class="pager-summary">
                በአጠቃላይ <?php echo number_format($total); ?> መዝገቦች | ገጽ <?php echo $page; ?> ከ <?php echo $total_pages; ?>
            </div>
            <div class="pager-controls">
                <?php
                $qs = ($f_user ? '&user_id=' . $f_user : '') . ($f_action ? '&action=' . urlencode($f_action) : '');
                if ($page > 1): ?>
                    <a href="?page=1<?php echo $qs; ?>" title="መጀመሪያ">««</a>
                    <a href="?page=<?php echo ($page - 1) . $qs; ?>" title="ያለፈው">‹</a>
                <?php endif; ?>

                <?php
                $start_p = max(1, $page - 2);
                $end_p = min($total_pages, $page + 2);
                for ($p = $start_p; $p <= $end_p; $p++):
                    if ($p === $page): ?>
                        <span class="current"><?php echo $p; ?></span>
                    <?php else: ?>
                        <a href="?page=<?php echo $p . $qs; ?>"><?php echo $p; ?></a>
                    <?php endif;
                endfor; ?>

                <?php if ($page < $total_pages): ?>
                    <a href="?page=<?php echo ($page + 1) . $qs; ?>" title="ቀጣይ">›</a>
                    <a href="?page=<?php echo $total_pages . $qs; ?>" title="መጨረሻ">»»</a>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>
</body>
</html>
<?php mysqli_close($conn); ?>
