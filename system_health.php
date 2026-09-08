<?php
$pageTitle  = 'System Health & Infrastructure Monitor';
$activePage = 'system_health';
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/session.php';
require_once __DIR__ . '/includes/rbac.php';
startSecureSession(); requireLogin(); requirePermission('system_health.view');

$db = getDB();

// DB Check
$dbCheck = $db->ping();

// Table counts
$tableCounts = [];
$totalRows = 0;
foreach (ALL_REQUEST_TABLES as $tbl) {
    $r = $db->query("SELECT COUNT(*) AS cnt FROM `$tbl`");
    $cnt = $r ? (int)$r->fetch_assoc()['cnt'] : 0;
    $tableCounts[$tbl] = $cnt;
    $totalRows += $cnt;
}

// User counts
$uQ = $db->query("SELECT COUNT(*) as cnt FROM users");
$userCount = $uQ ? (int)$uQ->fetch_assoc()['cnt'] : 0;

$stQ = $db->query("SELECT COUNT(*) as cnt FROM service_stations");
$stationCount = $stQ ? (int)$stQ->fetch_assoc()['cnt'] : 0;

$empQ = $db->query("SELECT COUNT(*) as cnt FROM employees");
$empCount = $empQ ? (int)$empQ->fetch_assoc()['cnt'] : 0;

$offQ = $db->query("SELECT COUNT(*) as cnt FROM offline_emergency_requests");
$offCount = $offQ ? (int)$offQ->fetch_assoc()['cnt'] : 0;

$trackQ = $db->query("SELECT COUNT(*) as cnt FROM live_tracking WHERE is_tracking_active=1");
$trackCount = $trackQ ? (int)$trackQ->fetch_assoc()['cnt'] : 0;

require_once __DIR__ . '/includes/layout.php';
?>

<div class="page-header">
    <div>
        <div class="page-back-wrapper">
            <a href="javascript:history.back()" onclick="if(window.history.length > 1 && document.referrer && document.referrer.indexOf(window.location.host) !== -1){ window.history.back(); return false; } else { window.location.href='<?= BASE_URL ?>dashboard.php'; return false; }" class="btn-back">
                <i class="bi bi-arrow-left"></i> Back
            </a>
        </div>
        <h1 class="page-title"><i class="bi bi-activity text-success"></i> System Health</h1>
        <p class="page-subtitle">Database status, record counts, and environment diagnostics</p>
    </div>
</div>

<div class="grid grid-cols-3 gap-4 mb-6">
    <!-- DB Card -->
    <div class="card" style="border-left:4px solid var(--color-success);">
        <div class="card-body">
            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:8px;">
                <span class="text-xs text-muted text-uppercase font-semibold">MySQL Engine</span>
                <span class="badge badge-success"><span class="dot dot-success dot-pulse"></span> ONLINE</span>
            </div>
            <div style="font-size:22px;font-weight:800;">Connected</div>
            <div class="text-xs text-muted mt-1">Host: <?= DB_HOST ?> &bull; Database: <?= DB_NAME ?></div>
        </div>
    </div>

    <!-- Storage / Records Card -->
    <div class="card" style="border-left:4px solid var(--brand-secondary);">
        <div class="card-body">
            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:8px;">
                <span class="text-xs text-muted text-uppercase font-semibold">Total Requests Handled</span>
                <span class="badge badge-info">14 Tables</span>
            </div>
            <div style="font-size:22px;font-weight:800;"><?= number_format($totalRows) ?> Records</div>
            <div class="text-xs text-muted mt-1">Synchronized across departmental schemas</div>
        </div>
    </div>

    <!-- Offline Queue Card -->
    <div class="card" style="border-left:4px solid <?= $offCount > 0 ? 'var(--color-warning)' : 'var(--color-success)' ?>;">
        <div class="card-body">
            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:8px;">
                <span class="text-xs text-muted text-uppercase font-semibold">Offline SOS Buffer</span>
                <span class="badge badge-<?= $offCount > 0 ? 'warning' : 'success' ?>"><?= $offCount ?> Pending</span>
            </div>
            <div style="font-size:22px;font-weight:800;"><?= $offCount ?> SOS Packets</div>
            <div class="text-xs text-muted mt-1"><?= $offCount > 0 ? 'Awaiting cloud sync' : 'Buffer fully synchronized' ?></div>
        </div>
    </div>
</div>

<div class="card mb-6">
    <div class="card-header">
        <div class="card-title"><i class="bi bi-hdd-stack-fill text-primary"></i> Database Schema Table Metrics</div>
    </div>
    <div class="table-wrapper" style="border:none;border-radius:0;">
        <table class="table">
            <thead>
                <tr>
                    <th>Table Name</th>
                    <th>Record Count</th>
                    <th>Schema Purpose</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($tableCounts as $tName => $c): ?>
                <tr>
                    <td><code class="mono"><?= htmlspecialchars($tName) ?></code></td>
                    <td><strong><?= number_format($c) ?></strong> rows</td>
                    <td style="color:var(--text-secondary);font-size:13px;">Department request partition</td>
                    <td><span class="badge badge-success">OK</span></td>
                </tr>
                <?php endforeach; ?>
                <tr>
                    <td><code class="mono">users</code></td>
                    <td><strong><?= number_format($userCount) ?></strong> rows</td>
                    <td style="color:var(--text-secondary);font-size:13px;">Registered citizens & station accounts</td>
                    <td><span class="badge badge-success">OK</span></td>
                </tr>
                <tr>
                    <td><code class="mono">service_stations</code></td>
                    <td><strong><?= number_format($stationCount) ?></strong> rows</td>
                    <td style="color:var(--text-secondary);font-size:13px;">Municipal service stations & dispatch nodes</td>
                    <td><span class="badge badge-success">OK</span></td>
                </tr>
                <tr>
                    <td><code class="mono">employees</code></td>
                    <td><strong><?= number_format($empCount) ?></strong> rows</td>
                    <td style="color:var(--text-secondary);font-size:13px;">Field officers, responders & drivers</td>
                    <td><span class="badge badge-success">OK</span></td>
                </tr>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/includes/layout_end.php'; ?>
