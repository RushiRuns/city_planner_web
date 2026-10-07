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

$showBackButton = true;
require_once __DIR__ . '/includes/layout.php';
?>

<div class="page-header">
    <div>
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
    <!-- Directory Toolbar -->
    <div class="citizens-toolbar">
        <form method="GET" action="" class="citizens-search-form" id="citizenSearchForm">
            <div class="filter-search-wrap">
                <i class="bi bi-search filter-search-icon"></i>
                <input type="text" name="q" id="citizenSearchInput" class="form-control minimal-search-input" 
                       placeholder="Search citizen name, phone, email, zone..." 
                       value="<?= htmlspecialchars($search) ?>" 
                       oninput="filterCitizens(this.value)">
            </div>
            <button type="submit" class="btn btn-primary btn-sm">
                <i class="bi bi-search"></i> Search
            </button>
            <?php if ($search): ?>
            <a href="<?= BASE_URL ?>citizens.php" class="btn btn-ghost btn-sm clear-filters-btn" title="Reset server filter">
                <i class="bi bi-arrow-counterclockwise"></i> Reset
            </a>
            <?php endif; ?>
        </form>

        <div class="citizens-meta-info">
            <span class="status-pill active" id="citizenCountBadge" style="cursor:default;">
                <span class="status-pill-dot" style="background:var(--brand-primary, #6366f1);"></span>
                <span>Total</span>
                <span class="status-pill-count" id="visibleCitizenCount"><?= count($citizens) ?></span>
            </span>
            <button type="button" class="btn btn-ghost btn-sm clear-filters-btn" id="clientResetBtn" style="display:none;" onclick="resetCitizenSearch()" title="Clear live filter">
                <i class="bi bi-x-circle"></i> Clear
            </button>
        </div>
    </div>

    <div class="table-wrapper" style="border:none;border-radius:0;">
        <table class="table" id="citizensTable">
            <thead>
                <tr>
                    <th>Citizen</th>
                    <th>Phone</th>
                    <th>City / Zone</th>
                    <?php if (!$isSuperAdmin): ?>
                    <th>Service Requests</th>
                    <?php endif; ?>
                    <th>Registered At</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody id="citizensTableBody">
                <?php if (empty($citizens)): ?>
                <tr>
                    <td colspan="<?= $isSuperAdmin ? '5' : '6' ?>">
                        <div class="empty-state" style="padding:48px;">
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
                <tr id="noMatchingCitizensRow" style="display:none;">
                    <td colspan="<?= $isSuperAdmin ? '5' : '6' ?>">
                        <div class="empty-state" style="padding:48px;">
                            <div class="empty-state-icon">🔍</div>
                            <div class="empty-state-title">No Matching Citizens</div>
                            <div class="empty-state-sub">No citizens match your live search keyword</div>
                        </div>
                    </td>
                </tr>
                <?php foreach ($citizens as $c): 
                    $initial = strtoupper(mb_substr(trim($c['name'] ?? 'U'), 0, 1));
                    $searchData = strtolower(implode(' ', [
                        $c['id'],
                        $c['name'],
                        $c['email'],
                        $c['phone'],
                        $c['city'] ?? '',
                        $c['zone'] ?? ''
                    ]));
                ?>
                <tr class="citizen-row" data-search="<?= htmlspecialchars($searchData) ?>">
                    <td>
                        <div class="citizen-profile-cell">
                            <div class="citizen-avatar"><?= htmlspecialchars($initial) ?></div>
                            <div>
                                <div class="citizen-name"><?= htmlspecialchars($c['name']) ?></div>
                                <div class="citizen-subtext">
                                    <span class="mono text-muted">UID-<?= $c['id'] ?></span>
                                    <span>&bull;</span>
                                    <span><?= htmlspecialchars($c['email']) ?></span>
                                </div>
                            </div>
                        </div>
                    </td>
                    <td>
                        <a href="tel:<?= htmlspecialchars($c['phone']) ?>" class="citizen-phone-link">
                            <i class="bi bi-telephone text-muted me-1"></i><?= htmlspecialchars($c['phone']) ?>
                        </a>
                    </td>
                    <td>
                        <span><?= htmlspecialchars($c['city'] ?? 'City Wide') ?></span>
                        <?php if (!empty($c['zone'])): ?>
                        <span class="text-muted text-xs ms-1">(<?= htmlspecialchars($c['zone']) ?>)</span>
                        <?php endif; ?>
                    </td>
                    <?php if (!$isSuperAdmin): ?>
                    <td>
                        <span class="badge badge-primary">
                            <i class="bi bi-bell-fill me-1"></i><?= (int)($c['request_count'] ?? 1) ?> Request<?= (int)($c['request_count'] ?? 1) > 1 ? 's' : '' ?>
                        </span>
                    </td>
                    <?php endif; ?>
                    <td class="text-xs text-muted">
                        <?= date('M d, Y', strtotime($c['created_at'])) ?>
                    </td>
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

<script>
function filterCitizens(query) {
    const q = (query || '').toLowerCase().trim();
    const rows = document.querySelectorAll('#citizensTableBody .citizen-row');
    const resetBtn = document.getElementById('clientResetBtn');
    let visibleCount = 0;

    rows.forEach(row => {
        const text = row.getAttribute('data-search') || '';
        const match = !q || text.includes(q);
        row.style.display = match ? '' : 'none';
        if (match) visibleCount++;
    });

    const emptyRow = document.getElementById('noMatchingCitizensRow');
    if (emptyRow) emptyRow.style.display = (visibleCount === 0) ? '' : 'none';

    const countEl = document.getElementById('visibleCitizenCount');
    if (countEl) countEl.textContent = visibleCount;

    if (resetBtn) {
        resetBtn.style.display = q ? 'inline-flex' : 'none';
    }
}

function resetCitizenSearch() {
    const input = document.getElementById('citizenSearchInput');
    if (input) input.value = '';
    filterCitizens('');
}
</script>

<?php require_once __DIR__ . '/includes/layout_end.php'; ?>
