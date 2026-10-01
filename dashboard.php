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

$extraScripts = ['dashboard.js', 'map.js', 'charts.js'];
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

<!-- ── Main Grid: Map + Live Incidents ────────────────────────── -->
<div class="grid gap-6 mb-6 dashboard-main-grid" id="mainGrid">

    <!-- Live Incident Map -->
    <div class="card" style="display:flex;flex-direction:column;height:520px;">
        <div class="card-header" style="flex-shrink:0;">
            <div class="card-title">
                <i class="bi bi-map-fill" style="color:var(--brand-secondary);"></i>
                Live Incident Map
            </div>
            <div class="card-actions">
                <button class="btn btn-ghost btn-sm" id="btnDashToggleStations" onclick="const v = MapView.toggleStations(); this.classList.toggle('active', v);" title="Toggle Service Stations on map">
                    <i class="bi bi-building-fill"></i> Stations
                </button>
                <span class="badge badge-success" id="mapMarkerCount">0 active</span>
                <a href="<?= BASE_URL ?>live_command.php" class="btn btn-ghost btn-sm">
                    <i class="bi bi-fullscreen"></i> Full Map
                </a>
            </div>
        </div>
        <div style="flex:1;border-radius:0 0 var(--radius-xl) var(--radius-xl);overflow:hidden;">
            <div id="dashMap" style="height:100%;width:100%;"></div>
        </div>
    </div>

    <!-- Live Incidents Panel -->
    <div class="card" style="display:flex;flex-direction:column;height:520px;">
        <div class="card-header" style="flex-shrink:0;">
            <div class="card-title">
                <i class="bi bi-activity" style="color:var(--color-critical);"></i>
                Live Incidents
            </div>
            <div class="card-actions">
                <div class="dot dot-success dot-pulse"></div>
                <span style="font-size:11px;color:var(--text-muted);font-weight:600;">AUTO-REFRESH</span>
            </div>
        </div>
        <div id="liveIncidentsList" style="flex:1;overflow-y:auto;padding:8px;">
            <!-- Populated by JS -->
            <div class="empty-state" style="padding:var(--space-8);">
                <div class="loading-spinner mx-auto"></div>
                <div class="text-muted text-sm mt-4">Loading incidents...</div>
            </div>
        </div>
        <div class="card-footer" style="flex-shrink:0;text-align:center;">
            <a href="<?= BASE_URL ?>requests.php" class="btn btn-surface btn-sm w-full">
                View All Requests <i class="bi bi-arrow-right"></i>
            </a>
        </div>
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

/* Incident list item */
.incident-item {
    display: flex; align-items: flex-start; gap: 12px;
    padding: 12px 16px; border-radius: var(--radius-lg);
    border: 1px solid transparent;
    cursor: pointer; transition: all var(--transition-fast);
    margin-bottom: 4px;
    text-decoration: none; color: inherit;
}
.incident-item:hover { background: var(--bg-surface-2); border-color: var(--border-light); }
.incident-icon {
    width: 36px; height: 36px; border-radius: var(--radius-md);
    display: flex; align-items: center; justify-content: center;
    font-size: 15px; flex-shrink: 0;
}
.incident-info { flex: 1; overflow: hidden; }
.incident-id   { font-size: 11px; color: var(--text-muted); font-family: var(--font-mono); }
.incident-name { font-size: var(--font-size-sm); font-weight: 600; color: var(--text-primary); }
.incident-loc  { font-size: 11px; color: var(--text-muted); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.incident-meta { display: flex; flex-direction: column; align-items: flex-end; gap: 4px; flex-shrink: 0; }
.incident-time { font-size: 11px; color: var(--text-muted); }

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
