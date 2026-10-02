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

$admin = getSessionAdmin();
$isSuper = ($admin && $admin['role'] === 'super_admin');
?>
<style>
/* ── Live Command Center Styling ──────────────────────────── */
.live-command-container {
    position: relative;
    width: 100%;
    height: calc(100vh - var(--header-height) - 170px);
    min-height: 560px;
    border-radius: var(--radius-xl);
    overflow: hidden;
    border: 1px solid var(--border-light);
    box-shadow: var(--shadow-sm);
    background: var(--bg-surface);
}

#liveMap {
    height: 100%;
    width: 100%;
    z-index: 1;
}

/* KPI Chips Bar */
.command-kpi-bar {
    display: flex;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
    margin-top: 6px;
}
.kpi-chip {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 3px 10px;
    border-radius: var(--radius-full);
    font-size: 11px;
    font-weight: 600;
    border: 1px solid var(--border-light);
    background: var(--bg-surface-2);
    color: var(--text-secondary);
}
.kpi-chip b {
    color: var(--text-primary);
}
.kpi-chip.kpi-critical {
    background: rgba(239, 68, 68, 0.1);
    border-color: rgba(239, 68, 68, 0.25);
    color: var(--color-critical);
}
.kpi-chip.kpi-critical b {
    color: var(--color-critical);
}
.kpi-chip.kpi-responders {
    background: rgba(34, 197, 94, 0.1);
    border-color: rgba(34, 197, 94, 0.25);
    color: var(--color-success);
}
.kpi-chip.kpi-responders b {
    color: var(--color-success);
}

/* Map Toolbar */
.command-toolbar {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
    align-items: center;
    justify-content: space-between;
    margin-bottom: var(--space-4);
}
.command-toolbar-left {
    display: flex;
    gap: 6px;
    flex-wrap: wrap;
    align-items: center;
}
.command-toolbar-right {
    display: flex;
    gap: 6px;
    flex-wrap: wrap;
    align-items: center;
}

.dept-filter-btn {
    padding: 5px 12px;
    font-size: 11px;
    font-weight: 600;
    border-radius: var(--radius-full);
    border: 1px solid var(--border-light);
    background: var(--bg-surface);
    color: var(--text-secondary);
    cursor: pointer;
    transition: all var(--transition-fast);
    display: inline-flex;
    align-items: center;
    gap: 6px;
}
.dept-filter-btn:hover {
    background: var(--bg-surface-2);
    color: var(--text-primary);
    border-color: var(--border-medium);
}
.dept-filter-btn.active {
    background: var(--brand-secondary);
    color: #fff;
    border-color: var(--brand-secondary);
}

/* Floating Tactical HUD Drawer */
.command-hud-drawer {
    position: absolute;
    top: 14px;
    right: 14px;
    bottom: 14px;
    width: 350px;
    z-index: 500;
    display: flex;
    flex-direction: column;
    border-radius: var(--radius-lg);
    overflow: hidden;
    transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1), opacity 0.2s ease;
    background: rgba(15, 23, 42, 0.9);
    backdrop-filter: blur(16px);
    -webkit-backdrop-filter: blur(16px);
    border: 1px solid rgba(255, 255, 255, 0.12);
    box-shadow: 0 16px 36px -4px rgba(0, 0, 0, 0.45);
    color: #f8fafc;
}

[data-theme="light"] .command-hud-drawer {
    background: rgba(255, 255, 255, 0.94);
    border: 1px solid var(--border-medium);
    box-shadow: 0 12px 30px rgba(0, 0, 0, 0.15);
    color: var(--text-primary);
}

.command-hud-drawer.collapsed {
    transform: translateX(calc(100% + 20px));
    pointer-events: none;
    opacity: 0;
}

.hud-reopen-pill {
    position: absolute;
    top: 14px;
    right: 14px;
    z-index: 490;
    padding: 6px 14px;
    border-radius: var(--radius-full);
    font-size: 11px;
    font-weight: 700;
    cursor: pointer;
    box-shadow: var(--shadow-md);
    background: var(--bg-surface);
    color: var(--text-primary);
    border: 1px solid var(--border-medium);
    display: none;
    align-items: center;
    gap: 6px;
    transition: all var(--transition-fast);
}
.hud-reopen-pill:hover {
    background: var(--bg-elevated);
    transform: translateY(-1px);
}
.hud-reopen-pill.visible {
    display: inline-flex;
}

/* HUD Tabs */
.hud-tab-bar {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    background: rgba(0, 0, 0, 0.2);
    border-bottom: 1px solid var(--border-light);
    padding: 4px;
    gap: 4px;
    flex-shrink: 0;
}
[data-theme="light"] .hud-tab-bar {
    background: var(--bg-surface-2);
}

.hud-tab-btn {
    background: transparent;
    border: none;
    padding: 7px 4px;
    font-size: 11px;
    font-weight: 700;
    color: var(--text-muted);
    border-radius: var(--radius-md);
    cursor: pointer;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 2px;
    transition: all var(--transition-fast);
}
.hud-tab-btn:hover {
    color: var(--text-primary);
    background: rgba(255, 255, 255, 0.05);
}
.hud-tab-btn.active {
    background: var(--brand-secondary);
    color: #fff;
}
.hud-tab-badge {
    font-size: 10px;
    opacity: 0.85;
}

/* HUD Search & Controls */
.hud-search-box {
    padding: 10px 12px;
    border-bottom: 1px solid var(--border-light);
    display: flex;
    align-items: center;
    gap: 8px;
    background: rgba(0, 0, 0, 0.1);
    flex-shrink: 0;
}
[data-theme="light"] .hud-search-box {
    background: var(--bg-surface);
}

.hud-search-input {
    flex: 1;
    background: transparent;
    border: none;
    outline: none;
    font-size: 12px;
    color: inherit;
}
.hud-search-input::placeholder {
    color: var(--text-muted);
}

/* HUD Item Cards */
.hud-item-list {
    flex: 1;
    overflow-y: auto;
    padding: 8px;
    display: flex;
    flex-direction: column;
    gap: 6px;
}

.hud-card {
    padding: 10px 12px;
    border-radius: var(--radius-md);
    border: 1px solid var(--border-light);
    background: rgba(255, 255, 255, 0.03);
    cursor: pointer;
    transition: all var(--transition-fast);
    display: flex;
    flex-direction: column;
    gap: 4px;
    text-decoration: none;
    color: inherit;
}
[data-theme="light"] .hud-card {
    background: var(--bg-surface);
    border-color: var(--border-light);
}
.hud-card:hover {
    background: rgba(255, 255, 255, 0.08);
    transform: translateY(-1px);
    border-color: var(--brand-secondary);
}
[data-theme="light"] .hud-card:hover {
    background: var(--bg-surface-2);
}

.hud-card.critical {
    border-left: 3px solid var(--color-critical);
}
.hud-card.live-unit {
    border-left: 3px solid var(--color-success);
}
.hud-card.stale-unit {
    border-left: 3px solid var(--text-muted);
}
.hud-card.station {
    border-left: 3px solid var(--brand-secondary);
}

.hud-card-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
}
.hud-card-title {
    font-size: 12px;
    font-weight: 700;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.hud-card-sub {
    font-size: 11px;
    color: var(--text-muted);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    display: flex;
    align-items: center;
    gap: 4px;
}

/* HUD Footer */
.hud-footer {
    padding: 8px 12px;
    border-top: 1px solid var(--border-light);
    display: flex;
    align-items: center;
    justify-content: space-between;
    font-size: 10px;
    color: var(--text-muted);
    background: rgba(0, 0, 0, 0.15);
    flex-shrink: 0;
}
[data-theme="light"] .hud-footer {
    background: var(--bg-surface-2);
}

@media (max-width: 900px) {
    .command-hud-drawer {
        width: 300px;
    }
}
@media (max-width: 640px) {
    .command-hud-drawer {
        width: calc(100% - 24px);
        top: auto;
        bottom: 12px;
        left: 12px;
        height: 280px;
    }
}
</style>

<div class="page-header mb-4">
    <div>
        <div class="page-back-wrapper">
            <a href="javascript:history.back()" onclick="if(window.history.length > 1 && document.referrer && document.referrer.indexOf(window.location.host) !== -1){ window.history.back(); return false; } else { window.location.href='<?= BASE_URL ?>dashboard.php'; return false; }" class="btn-back">
                <i class="bi bi-arrow-left"></i> Back
            </a>
        </div>
        <h1 class="page-title"><i class="bi bi-broadcast-pin" style="color:var(--color-critical);"></i> Live Command Center</h1>
        <div class="command-kpi-bar">
            <span class="kpi-chip kpi-critical"><i class="bi bi-exclamation-triangle-fill"></i> <b id="kpiCritical">0</b> Emergency</span>
            <span class="kpi-chip kpi-responders"><i class="bi bi-broadcast"></i> <b id="kpiTrackers">0</b> Active Units</span>
            <span class="kpi-chip"><i class="bi bi-activity"></i> <b id="kpiIncidents">0</b> Incidents</span>
            <span class="kpi-chip"><i class="bi bi-building"></i> <b id="kpiStations">0</b> Stations</span>
        </div>
    </div>
    <div class="page-actions">
        <div class="header-live-indicator">
            <span class="dot dot-success dot-pulse"></span> LIVE &bull; 15s Cycle
        </div>
    </div>
</div>

<!-- Map Controls Toolbar -->
<div class="command-toolbar">
    <div class="command-toolbar-left">
        <button class="dept-filter-btn active" onclick="LiveMap.filterByDept('', this)" id="filter-all">
            <i class="bi bi-grid-fill"></i> <?= $isSuper ? 'All Departments' : 'My Service Scope' ?>
        </button>
        <?php foreach (DEPARTMENTS as $deptKey => $dept): 
            $deptAllowedCats = array_filter($dept['categories'], fn($c) => isAdminAllowedCategory($c, $admin));
            if (empty($deptAllowedCats) && !$isSuper) continue;
        ?>
        <button class="dept-filter-btn" onclick="LiveMap.filterByDept('<?= implode(",", $deptAllowedCats) ?>', this)"
                style="--dept-tint:<?= $dept['color'] ?>;">
            <span class="dot" style="background:<?= $dept['color'] ?>;width:7px;height:7px;"></span>
            <?= htmlspecialchars($dept['name']) ?>
        </button>
        <?php endforeach; ?>
        
        <select class="filter-select" id="mapUrgencyFilter" onchange="LiveMap.refresh()" style="padding:4px 10px;font-size:11px;height:30px;">
            <option value="">All Urgencies</option>
            <option value="emergency">🔴 Emergency Only</option>
            <option value="high">🟠 High+</option>
            <option value="medium">🟡 Medium</option>
            <option value="low">🟢 Low</option>
        </select>
    </div>

    <div class="command-toolbar-right">
        <button class="btn btn-surface btn-sm" id="btnToggleSatellite" onclick="LiveMap.toggleSatellite()" title="Toggle Satellite Imagery">
            <i class="bi bi-globe-americas"></i> Satellite
        </button>
        <button class="btn btn-surface btn-sm" id="btnLiveToggleStations" onclick="LiveMap.toggleStations()" title="Show/Hide Service Stations Layer">
            <i class="bi bi-building-fill"></i> Stations Layer
        </button>
        <button class="btn btn-surface btn-sm" onclick="MapView.recenter()" title="Recenter City Center">
            <i class="bi bi-crosshair"></i> Recenter
        </button>
        <button class="btn btn-surface btn-sm" id="btnTogglePanel" onclick="LiveMap.togglePanel()" title="Toggle Tactical Drawer">
            <i class="bi bi-layout-sidebar-reverse"></i> Panel
        </button>
    </div>
</div>

<!-- Map Container & Tactical HUD Drawer -->
<div class="live-command-container">
    <div id="liveMap"></div>

    <!-- Reopen pill when drawer is collapsed -->
    <div class="hud-reopen-pill" id="hudReopenPill" onclick="LiveMap.togglePanel()">
        <i class="bi bi-layout-sidebar-inset"></i> Open Command Panel
    </div>

    <!-- Floating Tactical Drawer -->
    <div class="command-hud-drawer" id="commandDrawer">
        <!-- 3 Tab Bar -->
        <div class="hud-tab-bar">
            <button type="button" class="hud-tab-btn active" id="tabBtnTrackers" onclick="LiveMap.switchTab('trackers')">
                <span>📡 Units</span>
                <span class="hud-tab-badge" id="tabCountTrackers">0</span>
            </button>
            <button type="button" class="hud-tab-btn" id="tabBtnIncidents" onclick="LiveMap.switchTab('incidents')">
                <span>🚨 Incidents</span>
                <span class="hud-tab-badge" id="tabCountIncidents">0</span>
            </button>
            <button type="button" class="hud-tab-btn" id="tabBtnStations" onclick="LiveMap.switchTab('stations')">
                <span>🏢 Stations</span>
                <span class="hud-tab-badge" id="tabCountStations">0</span>
            </button>
        </div>

        <!-- Search Bar -->
        <div class="hud-search-box">
            <i class="bi bi-search" style="font-size:12px;color:var(--text-muted);"></i>
            <input type="text" class="hud-search-input" id="panelSearchInput" placeholder="Filter current list...">
            <button type="button" class="btn btn-ghost btn-xs" onclick="LiveMap.togglePanel()" title="Collapse Panel">
                <i class="bi bi-chevron-right"></i>
            </button>
        </div>

        <!-- Scrollable Cards -->
        <div class="hud-item-list" id="panelItemList">
            <div class="text-muted text-xs" style="text-align:center;padding:24px;">Loading data...</div>
        </div>

        <!-- Footer -->
        <div class="hud-footer">
            <span id="panelStatusText">Syncing telemetry...</span>
            <button type="button" class="btn btn-ghost btn-xs" onclick="LiveMap.refresh()" title="Refresh Telemetry">
                <i class="bi bi-arrow-clockwise"></i> Sync
            </button>
        </div>
    </div>
</div>

<meta name="csrf-token" content="<?= htmlspecialchars($csrfToken) ?>">

<script>
const LiveMap = {
    refreshTimer: null,
    currentFilter: "",
    activeTab: "trackers", // 'trackers', 'incidents', 'stations'
    searchQuery: "",
    isPanelOpen: true,
    data: {
        markers: [],
        trackers: [],
        stations: []
    },

    init() {
        MapView.init("liveMap", { zoom: 12, autoRefresh: false });
        this.bindEvents();
        this.startRefresh();
    },

    bindEvents() {
        const input = document.getElementById('panelSearchInput');
        if (input) {
            input.addEventListener('input', (e) => {
                this.searchQuery = e.target.value.toLowerCase().trim();
                this.renderActiveTab();
            });
        }
    },

    startRefresh() {
        this.refresh();
        if (this.refreshTimer) clearInterval(this.refreshTimer);
        this.refreshTimer = setInterval(() => this.refresh(), 15000);
    },

    async refresh() {
        try {
            const params = { action: "map_markers" };
            if (this.currentFilter) params.categories = this.currentFilter;
            
            const urg = document.getElementById('mapUrgencyFilter');
            if (urg && urg.value) params.urgency = urg.value;

            const res = await API.get("dashboard_stats.php", params);
            if (!res || !res.success) return;

            this.data.markers  = res.markers  || [];
            this.data.trackers = res.trackers || [];
            this.data.stations = res.stations || [];

            // Update Map layers
            MapView.renderMarkers(this.data.markers, this.data.trackers);
            if (this.data.stations.length) {
                MapView.stationData = this.data.stations;
                if (MapView.stationsVisible) {
                    MapView.renderStations(this.data.stations);
                }
            }

            // Update KPI chips
            const emergCount = this.data.markers.filter(m => m.urgency === 'emergency').length;
            this.setEl('kpiCritical', emergCount);
            this.setEl('kpiTrackers', this.data.trackers.length);
            this.setEl('kpiIncidents', this.data.markers.length);
            this.setEl('kpiStations', this.data.stations.length);

            // Update tab badges
            this.setEl('tabCountTrackers', this.data.trackers.length);
            this.setEl('tabCountIncidents', this.data.markers.length);
            this.setEl('tabCountStations', this.data.stations.length);

            this.renderActiveTab();

            const status = document.getElementById('panelStatusText');
            if (status) status.textContent = `Updated ${new Date().toLocaleTimeString('en-IN')}`;

        } catch (err) {
            console.error('Live command refresh failed:', err);
        }
    },

    switchTab(tab) {
        this.activeTab = tab;
        ['trackers', 'incidents', 'stations'].forEach(t => {
            const btn = document.getElementById(`tabBtn${t.charAt(0).toUpperCase() + t.slice(1)}`);
            if (btn) btn.classList.toggle('active', t === tab);
        });
        this.renderActiveTab();
    },

    renderActiveTab() {
        const container = document.getElementById('panelItemList');
        if (!container) return;

        const q = this.searchQuery;

        if (this.activeTab === 'trackers') {
            const list = this.data.trackers.filter(t => !q || t.id.toLowerCase().includes(q));
            if (!list.length) {
                container.innerHTML = `<div class="text-muted text-xs" style="text-align:center;padding:24px;">
                    ${q ? 'No matching responders found' : 'No active responders online'}
                </div>`;
                return;
            }

            container.innerHTML = list.map(t => `
            <div class="hud-card ${t.stale ? 'stale-unit' : 'live-unit'}" onclick="LiveMap.focusTracker('${escHtml(t.id)}')">
                <div class="hud-card-header">
                    <span class="hud-card-title"><code class="mono" style="font-size:11px;">${escHtml(t.id)}</code></span>
                    <span class="badge ${t.stale ? 'badge-neutral' : 'badge-success'}">${t.stale ? 'Stale' : 'Live'}</span>
                </div>
                <div class="hud-card-sub">
                    <span class="dot ${t.stale ? '' : 'dot-success dot-pulse'}" style="width:6px;height:6px;"></span>
                    <span>${t.stale ? 'Signal inactive > 10m' : 'Transmitting GPS telemetry'}</span>
                </div>
            </div>`).join('');

        } else if (this.activeTab === 'incidents') {
            const list = this.data.markers.filter(m => !q || 
                m.id.toLowerCase().includes(q) || 
                (m.category && m.category.toLowerCase().includes(q)) ||
                (m.address && m.address.toLowerCase().includes(q))
            );

            if (!list.length) {
                container.innerHTML = `<div class="text-muted text-xs" style="text-align:center;padding:24px;">
                    ${q ? 'No matching incidents found' : 'No active incidents on map'}
                </div>`;
                return;
            }

            container.innerHTML = list.map(m => {
                const isEmerg = m.urgency === 'emergency';
                return `
                <div class="hud-card ${isEmerg ? 'critical' : ''}" onclick="LiveMap.focusIncident('${escHtml(m.id)}')">
                    <div class="hud-card-header">
                        <span class="hud-card-title">${escHtml(m.category || m.id)}</span>
                        <span class="badge badge-${isEmerg ? 'critical' : (m.urgency === 'high' ? 'warning' : 'neutral')}">${m.urgency}</span>
                    </div>
                    <div class="hud-card-sub">
                        <code class="mono" style="font-size:10px;">${escHtml(m.id)}</code>
                        <span>&bull;</span>
                        <span>${escHtml(m.status)}</span>
                    </div>
                    <div class="hud-card-sub" title="${escHtml(m.address || '')}">
                        <i class="bi bi-geo-alt"></i> ${escHtml(m.address || 'Address on map')}
                    </div>
                </div>`;
            }).join('');

        } else if (this.activeTab === 'stations') {
            const list = this.data.stations.filter(s => !q || 
                s.name.toLowerCase().includes(q) || 
                (s.service_label && s.service_label.toLowerCase().includes(q)) ||
                (s.address && s.address.toLowerCase().includes(q))
            );

            if (!list.length) {
                container.innerHTML = `<div class="text-muted text-xs" style="text-align:center;padding:24px;">
                    ${q ? 'No matching stations found' : 'No stations loaded'}
                </div>`;
                return;
            }

            container.innerHTML = list.map(s => `
            <div class="hud-card station" onclick="LiveMap.focusStation('${escHtml(s.station_id)}')">
                <div class="hud-card-header">
                    <span class="hud-card-title">${escHtml(s.name)}</span>
                    <span class="badge badge-primary">${escHtml(s.service_label)}</span>
                </div>
                <div class="hud-card-sub" title="${escHtml(s.address || '')}">
                    <i class="bi bi-geo-alt"></i> ${escHtml(s.address || s.city || 'Station location')}
                </div>
                <div class="hud-card-sub">
                    <i class="bi bi-people"></i> ${s.staff_count} personnel assigned
                </div>
            </div>`).join('');
        }
    },

    focusTracker(id) {
        MapView.focusTracker(id);
    },

    focusIncident(id) {
        MapView.focusIncident(id);
    },

    focusStation(id) {
        MapView.focusStation(id);
    },

    filterByDept(cats, btn) {
        this.currentFilter = cats;
        document.querySelectorAll('.dept-filter-btn').forEach(b => b.classList.remove('active'));
        if (btn) btn.classList.add('active');
        this.refresh();
    },

    toggleStations() {
        const v = MapView.toggleStations();
        const btn = document.getElementById('btnLiveToggleStations');
        if (btn) btn.classList.toggle('active', v);
    },

    toggleSatellite() {
        const isSat = MapView.toggleSatellite();
        const btn = document.getElementById('btnToggleSatellite');
        if (btn) {
            btn.classList.toggle('active', isSat);
            btn.innerHTML = isSat ? '<i class="bi bi-map"></i> Street Map' : '<i class="bi bi-globe-americas"></i> Satellite';
        }
    },

    togglePanel() {
        this.isPanelOpen = !this.isPanelOpen;
        const drawer = document.getElementById('commandDrawer');
        const pill = document.getElementById('hudReopenPill');
        if (drawer) drawer.classList.toggle('collapsed', !this.isPanelOpen);
        if (pill) pill.classList.toggle('visible', !this.isPanelOpen);
    },

    setEl(id, val) {
        const el = document.getElementById(id);
        if (el) el.textContent = val;
    }
};

document.addEventListener("DOMContentLoaded", () => LiveMap.init());
</script>

<?php require_once __DIR__ . '/includes/layout_end.php'; ?>
