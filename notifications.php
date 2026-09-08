<?php
$pageTitle  = 'Notification Center';
$activePage = 'notifications';
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/session.php';
require_once __DIR__ . '/includes/rbac.php';
startSecureSession(); requireLogin(); requirePermission('notifications.view');

$db = getDB();
$admin = getSessionAdmin();
$adminId = is_numeric($admin['id']) ? (int)$admin['id'] : 0;

$notifsQ = $db->query("SELECT * FROM web_notifications WHERE admin_id = $adminId OR admin_id IS NULL ORDER BY created_at DESC LIMIT 50");
$notifs = [];
if ($notifsQ && $notifsQ instanceof mysqli_result) {
    while ($n = $notifsQ->fetch_assoc()) {
        $notifs[] = $n;
    }
}

require_once __DIR__ . '/includes/layout.php';
?>

<div class="page-header">
    <div>
        <div class="page-back-wrapper">
            <a href="javascript:history.back()" onclick="if(window.history.length > 1 && document.referrer && document.referrer.indexOf(window.location.host) !== -1){ window.history.back(); return false; } else { window.location.href='<?= BASE_URL ?>dashboard.php'; return false; }" class="btn-back">
                <i class="bi bi-arrow-left"></i> Back
            </a>
        </div>
        <h1 class="page-title"><i class="bi bi-bell-fill text-warning"></i> Notifications</h1>
        <p class="page-subtitle">Alerts, escalations, and system notifications</p>
    </div>
    <div class="page-actions">
        <button class="btn btn-surface btn-sm" onclick="markAllRead()"><i class="bi bi-check2-all"></i> Mark All as Read</button>
    </div>
</div>

<div class="card mb-6">
    <div class="card-body" style="display:flex;flex-direction:column;gap:10px;">
        <?php if (empty($notifs)): ?>
        <div class="empty-state">
            <div class="empty-state-icon">🔔</div>
            <div class="empty-state-title">No Notifications</div>
            <div class="empty-state-desc">You are all caught up. Alerts will appear here when triggered.</div>
        </div>
        <?php else: ?>
        <?php foreach ($notifs as $n): ?>
        <div class="surface-2 p-4" style="border-radius:var(--radius-lg);display:flex;align-items:flex-start;gap:14px;border-left:4px solid var(--color-<?= $n['severity'] ?: 'info' ?>);opacity:<?= $n['is_read'] ? '0.7' : '1' ?>;">
            <div style="font-size:20px;color:var(--color-<?= $n['severity'] ?: 'info' ?>);">
                <i class="bi <?= $n['severity'] === 'critical' ? 'bi-exclamation-triangle-fill' : 'bi-info-circle-fill' ?>"></i>
            </div>
            <div style="flex:1;">
                <div style="font-weight:700;font-size:15px;color:var(--text-primary);margin-bottom:2px;"><?= htmlspecialchars($n['title']) ?></div>
                <div style="font-size:13px;color:var(--text-secondary);"><?= htmlspecialchars($n['message']) ?></div>
                <div class="text-xs text-muted mt-2"><?= date('M d, Y - h:i A', strtotime($n['created_at'])) ?></div>
            </div>
            <?php if (!empty($n['entity_id'])): ?>
            <a href="<?= BASE_URL ?>request_detail.php?id=<?= urlencode($n['entity_id']) ?>" class="btn btn-surface btn-sm">
                View Incident
            </a>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<script>
async function markAllRead() {
    try {
        const res = await API.post('notifications_api.php?action=mark_all_read');
        if (res?.success) {
            Toast.success('All notifications marked as read');
            setTimeout(() => window.location.reload(), 500);
        }
    } catch(e) {
        Toast.error('Network error');
    }
}
</script>

<?php require_once __DIR__ . '/includes/layout_end.php'; ?>
