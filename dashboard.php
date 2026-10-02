<?php
// ============================================================
// City Planner Web Admin — Central Command Dashboard
// ============================================================
$pageTitle  = 'Command Dashboard';
$activePage = 'dashboard';

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/session.php';
require_once __DIR__ . '/includes/rbac.php';
require_once __DIR__ . '/includes/audit.php';

startSecureSession();
requireLogin();
requirePermission('dashboard.view');

$admin        = getSessionAdmin();
$csrfToken    = generateCsrfToken();
$isSuperAdmin = ($admin['role'] === 'super_admin');
$agencyProfile = getAdminAgencyProfile($admin);
$agencyKey    = $agencyProfile['key'];

// Pre-fetch initial data server-side scoped to admin categories
$db = getDB();
$categoryFilter = '';
$adminCats = $admin['categories'] ?? [];
if (!$isSuperAdmin && !empty($adminCats)) {
    $escaped = array_map(fn($c) => "'" . $db->real_escape_string($c) . "'", $adminCats);
    $categoryFilter = " WHERE category_id IN (" . implode(',', $escaped) . ")";
}

$union = "SELECT status, urgency, created_at, category_id FROM (" .
    implode(' UNION ALL ', array_map(fn($t) => "SELECT status, urgency, created_at, category_id FROM `$t`", ALL_REQUEST_TABLES)) .
    ") AS t $categoryFilter";

$activeR = $db->query("SELECT COUNT(*) AS total, SUM(status NOT IN ('resolved','cancelled')) AS active,
    SUM(urgency='emergency' AND status NOT IN ('resolved','cancelled')) AS critical,
    SUM(status='resolved') AS resolved,
    SUM(DATE(created_at)=CURDATE()) AS today
    FROM ($union) AS t2");
$stats = $activeR ? $activeR->fetch_assoc() : ['total'=>0,'active'=>0,'critical'=>0,'resolved'=>0,'today'=>0];

$extraScripts = ['dashboard.js', 'charts.js'];
$inlineScript = "const ADMIN_CATEGORIES = " . json_encode($admin['categories']) . "; const ACTIVE_AGENCY = '{$agencyKey}';";

require_once __DIR__ . '/includes/layout.php';
?>

<!-- ── Page Header ──────────────────────────────────────────── -->
<div class="page-header">
    <div>
        <h1 class="page-title"><i class="bi bi-grid-fill" style="opacity:0.6;color:var(--brand-secondary);"></i> Command Dashboard</h1>
        <p class="page-subtitle">Real-time overview of city operations, incidents, and units</p>
    </div>
</div>

<!-- ── Stat Cards ──────────────────────────────────────────── -->
<div class="grid grid-cols-stat gap-4 mb-6" id="statCards">
    <div class="stat-card" style="--card-accent:var(--brand-secondary);">
        <div class="stat-card-header">
            <span class="stat-label">Active <?= htmlspecialchars($agencyProfile['incident_label'] ?? 'Incidents') ?></span>
            <div class="stat-icon stat-icon-primary"><i class="bi bi-broadcast-pin"></i></div>
        </div>
        <div class="stat-value" id="sc-active"><?= number_format($stats['active']) ?></div>
    </div>

    <div class="stat-card stat-card-critical" style="--card-accent:var(--color-critical);">
        <div class="stat-card-header">
            <span class="stat-label">Critical / Emergency</span>
            <div class="stat-icon stat-icon-critical"><i class="bi bi-exclamation-triangle-fill"></i></div>
        </div>
        <div class="stat-value" id="sc-critical" style="color:var(--color-critical);"><?= number_format($stats['critical']) ?></div>
    </div>

    <div class="stat-card" style="--card-accent:var(--color-warning);">
        <div class="stat-card-header">
            <span class="stat-label">Today's Requests</span>
            <div class="stat-icon stat-icon-warning"><i class="bi bi-calendar-check-fill"></i></div>
        </div>
        <div class="stat-value" id="sc-today"><?= number_format($stats['today']) ?></div>
    </div>

    <div class="stat-card" style="--card-accent:var(--color-success);">
        <div class="stat-card-header">
            <span class="stat-label">Resolved</span>
            <div class="stat-icon stat-icon-success"><i class="bi bi-check-circle-fill"></i></div>
        </div>
        <div class="stat-value" id="sc-resolved" style="color:var(--color-success);"><?= number_format($stats['resolved']) ?></div>
    </div>
</div>

<!-- ── Live Incidents Queue (Full Width) ────────────────────────── -->
<div class="card mb-6" id="liveIncidentsCard" style="display:flex;flex-direction:column;min-height:480px;">
    <div class="card-header" style="flex-shrink:0;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;padding:14px 20px;">
        <div style="display:flex;align-items:center;gap:12px;">
            <div class="card-title" style="margin:0;">
                <i class="bi bi-activity" style="color:var(--color-critical);"></i>
                Live Incidents
            </div>
            <span class="badge badge-primary" id="liveIncidentsCount" style="font-size:11px;font-weight:700;">0 Active</span>
            <div style="display:flex;align-items:center;gap:6px;margin-left:6px;">
                <div class="dot dot-success dot-pulse"></div>
                <span style="font-size:11px;color:var(--text-muted);font-weight:600;">LIVE FEED</span>
            </div>
        </div>
        <div class="card-actions" style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
            <!-- Filter Pills -->
            <div class="incident-filters" style="display:flex;gap:6px;align-items:center;">
                <button type="button" class="incident-filter-btn active" onclick="Dashboard.setFilter('all', this)">All</button>
                <button type="button" class="incident-filter-btn" onclick="Dashboard.setFilter('critical', this)">
                    <span class="dot dot-critical" style="width:6px;height:6px;display:inline-block;margin-right:4px;"></span>Critical
                </button>
                <button type="button" class="incident-filter-btn" onclick="Dashboard.setFilter('inProgress', this)">In Progress</button>
                <button type="button" class="incident-filter-btn" onclick="Dashboard.setFilter('pending', this)">Pending</button>
            </div>
            <div style="height:18px;width:1px;background:var(--border-light);"></div>
            <a href="<?= BASE_URL ?>live_command.php" class="btn btn-ghost btn-sm" title="View Full Map Command Center">
                <i class="bi bi-map"></i> Map Center
            </a>
            <button class="btn btn-ghost btn-sm btn-icon" onclick="Dashboard.refresh()" title="Refresh Incidents">
                <i class="bi bi-arrow-clockwise"></i>
            </button>
        </div>
    </div>

    <!-- Table Column Header -->
    <div class="incident-table-header">
        <div class="col-id">Request ID</div>
        <div class="col-citizen">Citizen & Service</div>
        <div class="col-loc">Location</div>
        <div class="col-urgency">Urgency</div>
        <div class="col-status">Status</div>
        <div class="col-time">Time Elapsed</div>
        <div class="col-action" style="text-align:right;">Action</div>
    </div>

    <!-- Incidents List -->
    <div id="liveIncidentsList" style="flex:1;overflow-y:auto;max-height:460px;">
        <div class="empty-state" style="padding:var(--space-8);">
            <div class="loading-spinner mx-auto"></div>
            <div class="text-muted text-sm mt-4">Loading live incidents...</div>
        </div>
    </div>

    <div class="card-footer" style="flex-shrink:0;display:flex;justify-content:space-between;align-items:center;padding:12px 20px;">
        <span class="text-muted" style="font-size:12px;" id="liveIncidentsSummary">Live real-time operational feed</span>
        <a href="<?= BASE_URL ?>requests.php" class="btn btn-surface btn-sm">
            View All Requests <i class="bi bi-arrow-right"></i>
        </a>
    </div>
</div>

<!-- ── Department Workload + Analytics ──────────────────────────── -->
<div class="grid grid-cols-2 gap-6 mb-6">

    <!-- Department Breakdown -->
    <div class="card">
        <div class="card-header">
            <div class="card-title">
                <i class="bi bi-building-fill" style="color:var(--brand-secondary);"></i>
                Department Workload
            </div>
            <a href="<?= BASE_URL ?>departments.php" class="btn btn-ghost btn-sm">View All</a>
        </div>
        <div class="card-body no-pad">
            <div id="deptBreakdownList">
                <?php for ($i = 0; $i < 5; $i++): ?>
                <div class="skeleton" style="height:52px;margin:8px 16px;border-radius:var(--radius-lg);"></div>
                <?php endfor; ?>
            </div>
        </div>
    </div>

    <!-- 7-Day Trend Chart -->
    <div class="card">
        <div class="card-header">
            <div class="card-title">
                <i class="bi bi-graph-up" style="color:var(--color-success);"></i>
                7-Day Request Trend
            </div>
            <a href="<?= BASE_URL ?>analytics.php" class="btn btn-ghost btn-sm">Analytics</a>
        </div>
        <div class="card-body" style="padding-top:var(--space-4);">
            <canvas id="trendChart" height="200"></canvas>
        </div>
    </div>
</div>

<!-- ── Recent Activity Feed ──────────────────────────────────────── -->
<div class="card mb-6">
    <div class="card-header">
        <div class="card-title">
            <i class="bi bi-clock-history" style="color:var(--text-muted);"></i>
            Recent Request Activity
        </div>
        <div class="card-actions">
            <button class="btn btn-ghost btn-sm" onclick="Dashboard.refresh()">
                <i class="bi bi-arrow-clockwise"></i>
            </button>
            <a href="<?= BASE_URL ?>requests.php" class="btn btn-surface btn-sm">All Requests</a>
        </div>
    </div>
    <div class="table-wrapper" style="border:none;border-radius:0 0 var(--radius-xl) var(--radius-xl);">
        <table class="table" id="recentTable">
            <thead>
                <tr>
                    <th>Request ID</th>
                    <th>Citizen</th>
                    <th>Service</th>
                    <th>Priority</th>
                    <th>Status</th>
                    <th>Location</th>
                    <th>Time</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody id="recentTableBody">
                <tr><td colspan="8" style="text-align:center;padding:32px;color:var(--text-muted);">
                    <div class="loading-spinner" style="margin:0 auto;"></div>
                </td></tr>
            </tbody>
        </table>
    </div>
</div>

<style>
/* Dashboard-specific styles */
.city-pulse-metric {
    display: flex; flex-direction: column; gap: 2px; min-width: 100px;
}
.metric-val {
    font-size: var(--font-size-xl); font-weight: 800;
    color: var(--text-primary); font-variant-numeric: tabular-nums;
}
.metric-lbl {
    font-size: 10px; font-weight: 600; color: var(--text-muted);
    text-transform: uppercase; letter-spacing: 0.06em;
}
.text-critical { color: var(--color-critical); }
.text-success  { color: var(--color-success); }
.text-warning  { color: var(--color-warning); }
.mx-auto { margin: 0 auto; }

/* Filter buttons */
.incident-filter-btn {
    padding: 4px 12px;
    font-size: 11px;
    font-weight: 600;
    border-radius: var(--radius-full);
    border: 1px solid var(--border-light);
    background: transparent;
    color: var(--text-secondary);
    cursor: pointer;
    transition: all var(--transition-fast);
    display: inline-flex;
    align-items: center;
}
.incident-filter-btn:hover {
    background: var(--bg-surface-2);
    color: var(--text-primary);
}
.incident-filter-btn.active {
    background: var(--brand-secondary);
    color: #fff;
    border-color: var(--brand-secondary);
}

/* Incident Table Header */
.incident-table-header {
    display: grid;
    grid-template-columns: 120px 1.4fr 1.6fr 100px 120px 110px 70px;
    align-items: center;
    gap: 12px;
    padding: 10px 20px;
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: var(--text-muted);
    background: var(--bg-surface-2);
    border-bottom: 1px solid var(--border-light);
}

/* Incident list item (Full width grid) */
.incident-item {
    display: grid;
    grid-template-columns: 120px 1.4fr 1.6fr 100px 120px 110px 70px;
    align-items: center;
    gap: 12px;
    padding: 12px 20px;
    border-bottom: 1px solid var(--border-light);
    cursor: pointer;
    transition: background var(--transition-fast);
    text-decoration: none;
    color: inherit;
}
.incident-item:last-child {
    border-bottom: none;
}
.incident-item:hover {
    background: var(--bg-surface-2);
}
.incident-item.critical {
    border-left: 3px solid var(--color-critical);
    background: rgba(239, 68, 68, 0.03);
}
.incident-item.critical:hover {
    background: rgba(239, 68, 68, 0.07);
}
.incident-item .incident-id {
    font-size: 11px;
    color: var(--text-muted);
    font-family: var(--font-mono);
    font-weight: 700;
}
.incident-item .citizen-cat {
    display: flex;
    align-items: center;
    gap: 10px;
    min-width: 0;
}
.incident-icon {
    width: 34px;
    height: 34px;
    border-radius: var(--radius-md);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 15px;
    flex-shrink: 0;
}
.incident-item .citizen-name {
    font-size: 13px;
    font-weight: 600;
    color: var(--text-primary);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.incident-item .category-name {
    font-size: 11px;
    color: var(--text-muted);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.incident-item .incident-loc {
    font-size: 12px;
    color: var(--text-secondary);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    display: flex;
    align-items: center;
    gap: 6px;
}
.incident-item .incident-loc i {
    color: var(--text-muted);
    flex-shrink: 0;
}
.incident-item .incident-time {
    font-size: 12px;
    color: var(--text-muted);
    white-space: nowrap;
}
.incident-item .incident-action {
    text-align: right;
}

@media (max-width: 1024px) {
    .incident-table-header {
        grid-template-columns: 100px 1.2fr 1.2fr 90px 110px 90px 60px;
        padding: 8px 14px;
    }
    .incident-item {
        grid-template-columns: 100px 1.2fr 1.2fr 90px 110px 90px 60px;
        padding: 10px 14px;
    }
}
@media (max-width: 820px) {
    .incident-table-header {
        display: none !important;
    }
    .incident-item {
        display: flex;
        flex-direction: column;
        align-items: flex-start;
        gap: 8px;
        padding: 14px 16px;
    }
    .incident-item .citizen-cat { width: 100%; }
    .incident-item .incident-loc { width: 100%; }
    .incident-item .incident-action { width: 100%; text-align: left; }
}

/* Dept row */
.dept-row {
    display: flex; align-items: center; gap: 16px;
    padding: 12px 20px; border-bottom: 1px solid var(--border-light);
    cursor: pointer; transition: background var(--transition-fast);
    text-decoration: none; color: inherit;
}
.dept-row:hover { background: var(--bg-surface-2); }
.dept-row:last-child { border-bottom: none; }
.dept-bar-container {
    flex: 1; background: var(--border-light); border-radius: var(--radius-full); height: 6px; overflow: hidden;
}
.dept-bar { height: 100%; border-radius: var(--radius-full); transition: width 0.6s ease; }

/* Insight item */
.insight-item {
    display: flex; align-items: flex-start; gap: 12px;
    padding: 14px 16px; border-radius: var(--radius-lg);
    border: 1px solid; margin-bottom: 10px;
    animation: fadeIn 0.4s ease;
}
.insight-icon { font-size: 18px; flex-shrink: 0; margin-top: 1px; }
.insight-title { font-size: var(--font-size-sm); font-weight: 700; }
.insight-msg   { font-size: var(--font-size-xs); opacity: 0.75; margin-top: 2px; }

@media (max-width: 1024px) {
    #mainGrid, .dashboard-main-grid { grid-template-columns: 1fr !important; }
}
@media (max-width: 900px) {
    .grid-cols-2 { grid-template-columns: 1fr !important; }
}
</style>

<?php
$inlineScript .= <<<JS
// Initialized on load
document.addEventListener('DOMContentLoaded', () => {
    Dashboard.init();
});
JS;

require_once __DIR__ . '/includes/layout_end.php';
?>
