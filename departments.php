<?php
$pageTitle  = 'Departments Command';
$activePage = 'departments';
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/session.php';
require_once __DIR__ . '/includes/rbac.php';
startSecureSession(); requireLogin(); requirePermission('departments.view');

$db = getDB();
$admin = getSessionAdmin();
$selectedCat = $_GET['cat'] ?? '';

// Build category metrics
$categoryStats = [];
$dbServices = getDbServices();
foreach ($dbServices as $catId => $catInfo) {
    if ($admin['role'] !== 'super_admin' && !empty($admin['categories']) && !in_array($catId, $admin['categories'])) {
        continue;
    }
    $table = $catInfo['table'] ?? 'general_emergency_requests';
    
    // Check if table exists in DB before querying
    $stats = ['total'=>0, 'active'=>0, 'critical'=>0, 'resolved'=>0];
    try {
        $cntRes = $db->query("SELECT 
            COUNT(*) AS total,
            SUM(status NOT IN ('resolved','cancelled')) AS active,
            SUM(urgency = 'emergency' AND status NOT IN ('resolved','cancelled')) AS critical,
            SUM(status = 'resolved') AS resolved
            FROM `$table` WHERE category_id = '$catId'");
        if ($cntRes) $stats = $cntRes->fetch_assoc();
    } catch (\Throwable $e) {}
    
    // Station count for this service
    $stationsCount = 0;
    try {
        $stCnt = $db->query("SELECT COUNT(*) AS cnt FROM service_stations WHERE service_id = '$catId'");
        if ($stCnt) $stationsCount = (int)$stCnt->fetch_assoc()['cnt'];
    } catch (\Throwable $e) {}

    $categoryStats[$catId] = [
        'info' => $catInfo,
        'stats' => $stats,
        'stations' => $stationsCount,
    ];
}

$extraScripts = [];
require_once __DIR__ . '/includes/layout.php';
?>

<div class="page-header">
    <div>
        <div class="page-back-wrapper">
            <a href="javascript:history.back()" onclick="if(window.history.length > 1 && document.referrer && document.referrer.indexOf(window.location.host) !== -1){ window.history.back(); return false; } else { window.location.href='<?= BASE_URL ?>dashboard.php'; return false; }" class="btn-back">
                <i class="bi bi-arrow-left"></i> Back
            </a>
        </div>
        <h1 class="page-title"><i class="bi bi-building-fill" style="color:var(--brand-secondary);"></i> Departments</h1>
        <p class="page-subtitle">Department performance and active request overview</p>
    </div>
    <div class="page-actions flex gap-2">
        <a href="<?= BASE_URL ?>services.php" class="btn btn-primary btn-sm"><i class="bi bi-grid-3x3-gap-fill"></i> Manage Services</a>
        <a href="<?= BASE_URL ?>requests.php" class="btn btn-surface btn-sm"><i class="bi bi-list-task"></i> View All Requests</a>
    </div>
</div>

<div class="grid grid-cols-auto gap-4 mb-6">
    <?php foreach ($categoryStats as $catId => $data): 
        $info = $data['info'];
        $stats = $data['stats'];
    ?>
    <div class="card" style="border-top: 4px solid <?= $info['color'] ?>;">
        <div class="card-body" style="padding:var(--space-5);">
            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:12px;">
                <div style="width:40px;height:40px;border-radius:var(--radius-lg);background:<?= $info['color'] ?>20;color:<?= $info['color'] ?>;display:flex;align-items:center;justify-content:center;font-size:18px;">
                    <i class="bi <?= $info['icon'] ?>"></i>
                </div>
                <?php if (!empty($stats['critical']) && $stats['critical'] > 0): ?>
                <span class="badge badge-critical dot-pulse"><i class="bi bi-exclamation-circle"></i> <?= $stats['critical'] ?> SOS</span>
                <?php endif; ?>
            </div>
            <h3 style="font-size:16px;font-weight:700;margin-bottom:4px;color:var(--text-primary);"><?= htmlspecialchars($info['service_name'] ?? $info['name']) ?></h3>
            <div class="text-xs text-muted mb-4"><?= $data['stations'] ?> Active Stations registered</div>
            
            <div style="display:flex;justify-content:space-between;padding:10px;background:var(--bg-surface-2);border-radius:var(--radius-md);margin-bottom:14px;">
                <div>
                    <div style="font-size:18px;font-weight:800;color:<?= $stats['active'] > 0 ? 'var(--color-critical)' : 'var(--text-primary)' ?>;"><?= (int)$stats['active'] ?></div>
                    <div class="text-xs text-muted">Active</div>
                </div>
                <div>
                    <div style="font-size:18px;font-weight:800;color:var(--color-success);"><?= (int)$stats['resolved'] ?></div>
                    <div class="text-xs text-muted">Resolved</div>
                </div>
                <div>
                    <div style="font-size:18px;font-weight:800;color:var(--text-secondary);"><?= (int)$stats['total'] ?></div>
                    <div class="text-xs text-muted">Total</div>
                </div>
            </div>

            <div style="display:flex;gap:6px;">
                <a href="<?= BASE_URL ?>requests.php?category=<?= urlencode($catId) ?>" class="btn btn-surface btn-sm w-full" style="justify-content:center;">
                    <i class="bi bi-clipboard-data"></i> Cases
                </a>
                <a href="<?= BASE_URL ?>stations.php?service=<?= urlencode($catId) ?>" class="btn btn-ghost btn-sm" title="Stations">
                    <i class="bi bi-geo-alt"></i>
                </a>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<?php require_once __DIR__ . '/includes/layout_end.php'; ?>
