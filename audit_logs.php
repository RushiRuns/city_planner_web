<?php
$pageTitle  = 'Security & Audit Logs';
$activePage = 'audit_logs';
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/session.php';
require_once __DIR__ . '/includes/rbac.php';
startSecureSession(); requireLogin(); requirePermission('audit_logs.view');

$db = getDB();
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 30;
$offset = ($page - 1) * $perPage;

$total = 0;
$cntQ = $db->query("SELECT COUNT(*) AS cnt FROM audit_logs");
if ($cntQ && $cntQ instanceof mysqli_result) {
    $total = (int)$cntQ->fetch_assoc()['cnt'];
}

$logsQ = $db->query("SELECT * FROM audit_logs ORDER BY created_at DESC LIMIT $perPage OFFSET $offset");
$logs = [];
if ($logsQ && $logsQ instanceof mysqli_result) {
    while ($l = $logsQ->fetch_assoc()) {
        $logs[] = $l;
    }
}

$pagination = getPagination($total, $page, $perPage);

require_once __DIR__ . '/includes/layout.php';
?>

<div class="page-header">
    <div>
        <div class="page-back-wrapper">
            <a href="javascript:history.back()" onclick="if(window.history.length > 1 && document.referrer && document.referrer.indexOf(window.location.host) !== -1){ window.history.back(); return false; } else { window.location.href='<?= BASE_URL ?>dashboard.php'; return false; }" class="btn-back">
                <i class="bi bi-arrow-left"></i> Back
            </a>
        </div>
        <h1 class="page-title"><i class="bi bi-journal-text text-warning"></i> Audit Logs</h1>
        <p class="page-subtitle">Chronological record of administrator actions and system events</p>
    </div>
</div>

<div class="card mb-6">
    <div class="table-wrapper" style="border:none;border-radius:0;">
        <table class="table">
            <thead>
                <tr>
                    <th>Timestamp</th>
                    <th>Operator</th>
                    <th>Action</th>
                    <th>Entity Type</th>
                    <th>Target ID</th>
                    <th>Context / Details</th>
                    <th>IP Address</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($logs)): ?>
                <tr>
                    <td colspan="7">
                        <div class="empty-state">
                            <div class="empty-state-icon">🛡️</div>
                            <div class="empty-state-title">No Audit Logs Yet</div>
                        </div>
                    </td>
                </tr>
                <?php else: ?>
                <?php foreach ($logs as $log): ?>
                <tr>
                    <td style="white-space:nowrap;font-size:12px;color:var(--text-muted);">
                        <?= date('M d, Y H:i:s', strtotime($log['created_at'])) ?>
                    </td>
                    <td>
                        <div style="font-weight:700;"><?= htmlspecialchars($log['admin_name'] ?: 'System') ?></div>
                        <div class="text-xs text-muted"><?= htmlspecialchars($log['admin_email']) ?></div>
                    </td>
                    <td>
                        <span class="badge badge-<?= strpos($log['action'], 'login') !== false ? 'info' : (strpos($log['action'], 'create') !== false ? 'success' : 'warning') ?>">
                            <?= htmlspecialchars($log['action']) ?>
                        </span>
                    </td>
                    <td><code class="mono"><?= htmlspecialchars($log['entity_type']) ?></code></td>
                    <td><code class="mono"><?= htmlspecialchars($log['entity_id']) ?></code></td>
                    <td style="max-width:280px;font-size:12px;color:var(--text-secondary);">
                        <?= htmlspecialchars($log['new_value'] ?: $log['old_value']) ?>
                    </td>
                    <td><code class="mono" style="font-size:11px;"><?= htmlspecialchars($log['ip_address']) ?></code></td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Pagination -->
    <?php if ($pagination['total_pages'] > 1): ?>
    <div class="card-footer" style="display:flex;justify-content:space-between;align-items:center;">
        <div class="text-xs text-muted">Showing page <?= $pagination['current'] ?> of <?= $pagination['total_pages'] ?> (<?= $pagination['total'] ?> total events)</div>
        <div class="pagination">
            <?php if ($pagination['has_prev']): ?>
            <a href="?page=<?= $pagination['current'] - 1 ?>" class="page-btn"><i class="bi bi-chevron-left"></i></a>
            <?php endif; ?>
            <span class="page-btn active"><?= $pagination['current'] ?></span>
            <?php if ($pagination['has_next']): ?>
            <a href="?page=<?= $pagination['current'] + 1 ?>" class="page-btn"><i class="bi bi-chevron-right"></i></a>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/includes/layout_end.php'; ?>
