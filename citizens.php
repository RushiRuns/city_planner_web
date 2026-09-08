<?php
$pageTitle  = 'Citizen Directory';
$activePage = 'citizens';
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/session.php';
require_once __DIR__ . '/includes/rbac.php';
startSecureSession(); requireLogin(); requirePermission('citizens.view');

$db = getDB();
$admin = getSessionAdmin();
$isSuperAdmin = ($admin['role'] === 'super_admin');
$agencyProfile = getAdminAgencyProfile($admin);
$search = trim($_GET['q'] ?? '');

// Execute service-scoped citizen query
$sql = getScopedCitizensSQL($admin, $search, 100);
$citizensQ = $db->query($sql);
$citizens = [];
if ($citizensQ && $citizensQ instanceof mysqli_result) {
    while ($c = $citizensQ->fetch_assoc()) {
        $citizens[] = $c;
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
        <div class="flex items-center gap-2">
            <h1 class="page-title">
                <i class="bi bi-person-lines-fill text-info"></i> 
                <?= $isSuperAdmin ? 'Citizens & Users Directory' : htmlspecialchars($agencyProfile['short_name']) . ' Requested Users' ?>
            </h1>
            <?php if (!$isSuperAdmin): ?>
            <span class="badge" style="background:rgba(239,68,68,0.15);color:var(--brand-secondary, #EF4444);border:1px solid rgba(239,68,68,0.3);font-size:12px;padding:4px 8px;">
                <?= $agencyProfile['badge'] ?> <?= htmlspecialchars($agencyProfile['short_name']) ?> Scope
            </span>
            <?php else: ?>
            <span class="badge badge-success" style="font-size:12px;padding:4px 8px;">
                🏙️ All Services (Super Admin)
            </span>
            <?php endif; ?>
        </div>
        <p class="page-subtitle">
            <?= $isSuperAdmin 
                ? 'All registered citizen accounts across the entire smart city platform' 
                : 'Citizens who have submitted incident requests or interacted with ' . htmlspecialchars($agencyProfile['name']) ?>
        </p>
    </div>
</div>

<div class="card mb-6">
    <div class="filter-bar">
        <form method="GET" action="" style="display:flex;gap:10px;width:100%;max-width:500px;">
            <div class="form-group flex-1">
                <input type="text" name="q" class="form-control" placeholder="Search citizen name, phone, email, zone..." value="<?= htmlspecialchars($search) ?>">
            </div>
            <button type="submit" class="btn btn-primary"><i class="bi bi-search"></i> Search</button>
            <?php if ($search): ?>
            <a href="<?= BASE_URL ?>citizens.php" class="btn btn-ghost">Clear</a>
            <?php endif; ?>
        </form>
    </div>

    <div class="table-wrapper" style="border:none;border-radius:0;">
        <table class="table">
            <thead>
                <tr>
                    <th>User ID</th>
                    <th>Citizen Name</th>
                    <th>Email Address</th>
                    <th>Phone</th>
                    <th>City / Zone</th>
                    <?php if (!$isSuperAdmin): ?>
                    <th>Service Requests</th>
                    <?php endif; ?>
                    <th>Registered At</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($citizens)): ?>
                <tr>
                    <td colspan="<?= $isSuperAdmin ? '7' : '8' ?>">
                        <div class="empty-state">
                            <div class="empty-state-icon"><?= $isSuperAdmin ? '👥' : $agencyProfile['badge'] ?></div>
                            <div class="empty-state-title">
                                <?= $search 
                                    ? 'No Matching Citizens Found' 
                                    : ($isSuperAdmin ? 'No Citizens Found' : 'No Citizens Found For ' . htmlspecialchars($agencyProfile['short_name'])) ?>
                            </div>
                            <div class="text-xs text-muted mt-1">
                                <?= $isSuperAdmin 
                                    ? 'Registered citizens will appear here.' 
                                    : 'Only citizens who submit requests for ' . htmlspecialchars($agencyProfile['short_name']) . ' are listed in this view.' ?>
                            </div>
                        </div>
                    </td>
                </tr>
                <?php else: ?>
                <?php foreach ($citizens as $c): ?>
                <tr>
                    <td><code class="mono">UID-<?= $c['id'] ?></code></td>
                    <td><strong><?= htmlspecialchars($c['name']) ?></strong></td>
                    <td><?= htmlspecialchars($c['email']) ?></td>
                    <td><i class="bi bi-telephone text-muted me-1"></i><?= htmlspecialchars($c['phone']) ?></td>
                    <td><?= htmlspecialchars($c['city'] ?? 'City Wide') ?> <?= !empty($c['zone']) ? '('.htmlspecialchars($c['zone']).')' : '' ?></td>
                    <?php if (!$isSuperAdmin): ?>
                    <td>
                        <span class="badge badge-primary">
                            <i class="bi bi-bell-fill me-1"></i><?= (int)($c['request_count'] ?? 1) ?> Request<?= (int)($c['request_count'] ?? 1) > 1 ? 's' : '' ?>
                        </span>
                    </td>
                    <?php endif; ?>
                    <td><?= date('M d, Y', strtotime($c['created_at'])) ?></td>
                    <td>
                        <div class="flex gap-1">
                            <a href="<?= BASE_URL ?>requests.php?search=<?= urlencode($c['phone']) ?>" class="btn btn-surface btn-xs" title="View citizen incident requests">
                                <i class="bi bi-clock-history"></i> Requests
                            </a>
                            <a href="<?= BASE_URL ?>services.php?tab=interactions&q=<?= urlencode($c['phone']) ?>" class="btn btn-ghost btn-xs" title="View citizen helpline calls">
                                <i class="bi bi-headset"></i> Helplines
                            </a>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/includes/layout_end.php'; ?>
