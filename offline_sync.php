<?php
$pageTitle  = 'Offline Emergency Buffer Monitor';
$activePage = 'offline_sync';
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/session.php';
require_once __DIR__ . '/includes/rbac.php';
startSecureSession(); requireLogin(); requirePermission('offline_sync.view');

$db = getDB();

$offQ = $db->query("SELECT * FROM offline_emergency_requests ORDER BY id DESC LIMIT 100");
$offRequests = [];
while ($r = $offQ->fetch_assoc()) {
    $offRequests[] = $r;
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
        <h1 class="page-title"><i class="bi bi-wifi-off text-critical"></i> Offline Emergency Request Queue</h1>
        <p class="page-subtitle">Emergency requests captured during offline or low-connectivity</p>
    </div>
</div>

<div class="card mb-6">
    <div class="table-wrapper" style="border:none;border-radius:0;">
        <table class="table">
            <thead>
                <tr>
                    <th>Record ID</th>
                    <th>Citizen Name</th>
                    <th>Selected Emergency Services</th>
                    <th>Target Phone Numbers</th>
                    <th>GPS Coordinates</th>
                    <th>Timestamp</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($offRequests)): ?>
                <tr>
                    <td colspan="6">
                        <div class="empty-state">
                            <div class="empty-state-icon">📡</div>
                            <div class="empty-state-title">No Pending Offline SOS Packets</div>
                            <div class="empty-state-desc">All offline emergency packets have been synchronized.</div>
                        </div>
                    </td>
                </tr>
                <?php else: ?>
                <?php foreach ($offRequests as $req): ?>
                <tr>
                    <td><code class="mono"><?= htmlspecialchars($req['request_id'] ?? 'OFF-'.$req['id']) ?></code></td>
                    <td><strong><?= htmlspecialchars($req['citizen_name']) ?></strong></td>
                    <td>
                        <span class="badge badge-critical"><?= htmlspecialchars($req['selected_services'] ?? 'SOS') ?></span>
                    </td>
                    <td><?= htmlspecialchars($req['phone_numbers'] ?? '') ?></td>
                    <td>
                        <?php if (!empty($req['latitude']) && !empty($req['longitude'])): ?>
                        <code class="mono" style="font-size:11px;"><?= number_format((float)$req['latitude'], 4) ?>, <?= number_format((float)$req['longitude'], 4) ?></code>
                        <?php else: ?>
                        <span class="text-muted text-xs">Cell Tower / None</span>
                        <?php endif; ?>
                    </td>
                    <td style="font-size:12px;color:var(--text-muted);"><?= htmlspecialchars($req['created_at']) ?></td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/includes/layout_end.php'; ?>
