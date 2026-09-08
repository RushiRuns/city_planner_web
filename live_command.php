<?php
$pageTitle  = 'Live Command Center';
$activePage = 'live_command';
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/session.php';
require_once __DIR__ . '/includes/rbac.php';
startSecureSession(); requireLogin(); requirePermission('tracking.view');
$csrfToken = generateCsrfToken();
$extraScripts = ['map.js'];
require_once __DIR__ . '/includes/layout.php';
?>
<style>
.live-map-wrapper { height: calc(100vh - var(--header-height) - 180px); min-height: 500px; }
#liveMap { height: 100%; width: 100%; border-radius: var(--radius-xl); }
.map-controls { display: flex; gap: 8px; flex-wrap: wrap; align-items: center; margin-bottom: var(--space-4); }
.tracking-panel { position: fixed; right: 20px; top: calc(var(--header-height) + 80px); width: 300px; z-index: 500; max-height: 60vh; overflow-y: auto; }
@media (max-width: 1100px) { .tracking-panel { display: none; } }
</style>

<div class="page-header">
    <div>
        <div class="page-back-wrapper">
            <a href="javascript:history.back()" onclick="if(window.history.length > 1 && document.referrer && document.referrer.indexOf(window.location.host) !== -1){ window.history.back(); return false; } else { window.location.href='<?= BASE_URL ?>dashboard.php'; return false; }" class="btn-back">
                <i class="bi bi-arrow-left"></i> Back
            </a>
        </div>
        <h1 class="page-title"><i class="bi bi-broadcast-pin" style="color:var(--color-critical);opacity:0.7;"></i> Live Command Center</h1>
        <p class="page-subtitle">Real-time incident map &bull; <span id="activeTrackCount" style="color:var(--color-success);font-weight:700;">0</span> active trackers</p>
    </div>
    <div class="page-actions">
        <div class="header-live-indicator">
            <span class="dot dot-success dot-pulse"></span> LIVE — Auto-refresh 15s
        </div>
    </div>
</div>

<?php 
$admin = getSessionAdmin();
$isSuper = ($admin && $admin['role'] === 'super_admin');
?>
<!-- Map Controls -->
<div class="map-controls">
    <button class="btn btn-surface btn-sm" onclick="LiveMap.filterByDept('')" id="filter-all" style="border-color:var(--brand-secondary);color:var(--brand-secondary);">
        <?= $isSuper ? 'All Departments' : 'My Service Scope' ?>
    </button>
    <?php foreach (DEPARTMENTS as $deptKey => $dept): 
        $deptAllowedCats = array_filter($dept['categories'], fn($c) => isAdminAllowedCategory($c, $admin));
        if (empty($deptAllowedCats) && !$isSuper) continue;
    ?>
    <button class="btn btn-surface btn-sm" onclick="LiveMap.filterByDept('<?= implode(",", $deptAllowedCats) ?>')"
            style="border-color:<?= $dept['color'] ?>;color:<?= $dept['color'] ?>;">
        <i class="bi <?= $dept['icon'] ?>"></i> <?= $dept['name'] ?>
    </button>
    <?php endforeach; ?>
    <button class="btn-map-toggle ms-auto" id="btnLiveToggleStations" onclick="const v = MapView.toggleStations(); this.classList.toggle('active', v);" title="Show/Hide Service Stations">
        <i class="bi bi-building-fill"></i> Stations Layer
    </button>
    <select class="filter-select" id="mapUrgencyFilter" onchange="LiveMap.refresh()">
        <option value="">All Urgencies</option>
        <option value="emergency">🔴 Emergency Only</option>
        <option value="high">🟠 High+</option>
    </select>
</div>

<div class="card" style="overflow:visible;">
    <div class="live-map-wrapper" style="padding:var(--space-4);">
        <div id="liveMap"></div>
    </div>
</div>

<!-- Floating Live Tracking Panel -->
<div class="tracking-panel card" style="padding:var(--space-4);" id="trackingPanel">
    <div style="font-weight:700;font-size:var(--font-size-sm);margin-bottom:12px;display:flex;align-items:center;gap:8px;">
        <span class="dot dot-success dot-pulse"></span>
        Live Responders
    </div>
    <div id="trackerList">
        <div class="text-muted text-xs" style="text-align:center;padding:16px;">Loading trackers...</div>
    </div>
</div>

<meta name="csrf-token" content="<?= htmlspecialchars($csrfToken) ?>">

<script>
const LiveMap = {
    refreshTimer: null,
    currentFilter: "",
    init() {
        MapView.init("liveMap", { zoom: 11, autoRefresh: false });
        this.startRefresh();
    },
    startRefresh() {
        this.refresh();
        this.refreshTimer = setInterval(() => this.refresh(), 15000);
    },
    async refresh() {
        try {
            const params = { action: "map_markers" };
            if (this.currentFilter) params.categories = this.currentFilter;
            const data = await API.get("dashboard_stats.php", params);
            if (!data?.success) return;
            MapView.renderMarkers(data.markers || [], data.trackers || []);
            this.updateTrackerPanel(data.trackers || []);
            document.getElementById("activeTrackCount").textContent = data.trackers?.length || 0;
        } catch(e) {}
    },
    filterByDept(cats) {
        this.currentFilter = cats;
        this.refresh();
    },
    updateTrackerPanel(trackers) {
        const el = document.getElementById("trackerList");
        if (!el) return;
        if (!trackers.length) { el.innerHTML = `<div class="text-muted text-xs" style="text-align:center;padding:16px;">No active trackers</div>`; return; }
        el.innerHTML = trackers.map(t => `
        <div style="display:flex;align-items:center;gap:8px;padding:8px;border-radius:var(--radius-md);border:1px solid var(--border-light);margin-bottom:6px;">
            <span style="font-size:18px;">${t.stale ? "⚠️" : "📡"}</span>
            <div style="flex:1;">
                <div style="font-size:11px;font-weight:600;font-family:var(--font-mono);">${t.id}</div>
                <div style="font-size:10px;color:var(--text-muted);">${t.stale ? "Stale data" : "Live tracking"}</div>
            </div>
            <span class="dot ${t.stale ? "" : "dot-success dot-pulse"}"></span>
        </div>`).join("");
    }
};
document.addEventListener("DOMContentLoaded", () => LiveMap.init());
</script>

<?php require_once __DIR__ . '/includes/layout_end.php'; ?>
