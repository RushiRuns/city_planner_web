<?php
$pageTitle  = 'Service Stations Management';
$activePage = 'stations';
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/session.php';
require_once __DIR__ . '/includes/rbac.php';
require_once __DIR__ . '/includes/audit.php';
startSecureSession(); requireLogin(); requirePermission('stations.view');

$db = getDB();
$admin = getSessionAdmin();
$csrfToken = generateCsrfToken();
$serviceFilter = $_GET['service'] ?? '';

// Handle Create Station POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_station'])) {
    requireRole('super_admin');
    if (validateCsrf()) {
        $stnId = trim($_POST['station_id']);
        $serviceId = trim($_POST['service_id']);
        $serviceLabel = trim($_POST['service_label']);
        $name = trim($_POST['name']);
        $email = trim($_POST['email']);
        $phone = trim($_POST['phone']);
        $city = trim($_POST['city']);
        $address = trim($_POST['address'] ?? '');
        $lat = floatval($_POST['latitude'] ?? 0);
        $lng = floatval($_POST['longitude'] ?? 0);

        if ($stnId && $name && $email) {
            $stmt = $db->prepare("INSERT INTO service_stations (station_id, service_id, service_label, name, email, phone, city, latitude, longitude, address) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param('sssssssdds', $stnId, $serviceId, $serviceLabel, $name, $email, $phone, $city, $lat, $lng, $address);
            if ($stmt->execute()) {
                logAudit('create_station', 'station', $stnId, '', "name:$name service:$serviceLabel lat:$lat lng:$lng");
                $successMsg = "Station created successfully.";
            } else {
                $errorMsg = "Error creating station: " . $db->error;
            }
        }
    }
}

// Fetch Stations with scope enforcement
$where = " WHERE 1=1 ";
$where .= getStationScopeWhere('ss');
if ($serviceFilter) {
    if ($admin['role'] === 'super_admin' || isAdminAllowedCategory($serviceFilter, $admin)) {
        $where .= " AND ss.service_id = '" . $db->real_escape_string($serviceFilter) . "'";
    } else {
        $where .= " AND 1=0";
    }
}
$stationsQ = $db->query("SELECT ss.*, 
    (SELECT COUNT(*) FROM employees WHERE station_id = ss.station_id) AS staff_count
    FROM service_stations ss $where ORDER BY ss.service_label, ss.name ASC");

$stations = [];
$stationsJson = [];
if ($stationsQ) {
    while ($st = $stationsQ->fetch_assoc()) {
        $stations[] = $st;
        $svcMeta = CATEGORIES[$st['service_id']] ?? null;
        $stationsJson[] = [
            'station_id'    => $st['station_id'],
            'name'          => $st['name'],
            'service_id'    => $st['service_id'],
            'service_label' => $st['service_label'],
            'email'         => $st['email'],
            'phone'         => $st['phone'],
            'city'          => $st['city'],
            'address'       => $st['address'] ?: $st['city'],
            'lat'           => (float)$st['latitude'],
            'lng'           => (float)$st['longitude'],
            'staff_count'   => (int)$st['staff_count'],
            'color'         => $svcMeta['color'] ?? '#6366F1',
            'icon'          => $svcMeta['icon'] ?? 'bi-building-fill',
        ];
    }
}

$showBackButton = true;
$extraScripts = ['map.js'];
require_once __DIR__ . '/includes/layout.php';
?>

<style>
/* ── Service Stations Styling ────────────────────────────────── */
.station-map-card.collapsed {
    display: none;
}
.station-toolbar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    flex-wrap: wrap;
    margin-bottom: 16px;
    padding: 12px 16px;
    background: var(--bg-surface-1);
    border: 1px solid var(--border-subtle);
    border-radius: var(--radius-lg);
}
.station-search-box {
    position: relative;
    flex: 1;
    min-width: 220px;
    max-width: 380px;
}
.station-search-box i {
    position: absolute;
    left: 12px;
    top: 50%;
    transform: translateY(-50%);
    color: var(--text-muted);
    font-size: 13px;
    pointer-events: none;
}
.station-search-input {
    width: 100%;
    padding: 7px 12px 7px 34px;
    border-radius: var(--radius-md);
    border: 1px solid var(--border-medium);
    background: var(--bg-surface-2);
    color: var(--text-primary);
    font-size: 13px;
    outline: none;
    transition: all var(--transition-fast);
}
.station-search-input:focus {
    border-color: var(--brand-secondary);
    background: var(--bg-surface);
}

/* ── Contextual Row Menu (•••) ───────────────────────────────── */
.station-actions-cell {
    position: relative;
    display: flex;
    align-items: center;
    justify-content: flex-end;
    gap: 6px;
}
.station-dropdown-menu {
    position: absolute;
    right: 0;
    top: 100%;
    margin-top: 6px;
    background: var(--bg-surface, #ffffff);
    background-color: var(--bg-surface, #ffffff);
    border: 1px solid var(--border-medium, #d0d0d0);
    border-radius: var(--radius-md, 6px);
    box-shadow: 0 10px 25px rgba(0, 0, 0, 0.15), 0 4px 6px rgba(0, 0, 0, 0.08);
    min-width: 185px;
    z-index: 1000;
    padding: 6px;
    display: none;
    text-align: left;
    opacity: 1 !important;
}
[data-theme="dark"] .station-dropdown-menu {
    background: var(--bg-surface-2, #2e2e2e);
    background-color: var(--bg-surface-2, #2e2e2e);
    border: 1px solid var(--border-strong, #4a4a4a);
    box-shadow: 0 10px 25px rgba(0, 0, 0, 0.5), 0 4px 6px rgba(0, 0, 0, 0.3);
}
.station-dropdown-menu.show {
    display: block;
}
.station-dropdown-item {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 8px 12px;
    border-radius: var(--radius-sm, 4px);
    font-size: 12px;
    font-weight: 500;
    color: var(--text-primary);
    text-decoration: none;
    cursor: pointer;
    background: transparent;
    border: none;
    width: 100%;
    text-align: left;
    transition: background 0.15s ease, color 0.15s ease;
}
.station-dropdown-item:hover {
    background: var(--bg-surface-2, #f5f5f5);
    color: var(--brand-primary);
}
[data-theme="dark"] .station-dropdown-item:hover {
    background: var(--bg-elevated, #383838);
    color: #ffffff;
}
.station-row.has-menu-open {
    position: relative;
    z-index: 100 !important;
}
</style>

<!-- ── Page Header ──────────────────────────────────────────── -->
<div class="page-header">
    <div>
        <h1 class="page-title"><i class="bi bi-geo-alt-fill text-critical"></i> Service Stations</h1>
        <p class="page-subtitle">Municipal, emergency & tactical command facilities across the city</p>
    </div>
    <div class="page-actions" style="display:flex;align-items:center;gap:8px;">
        <button class="btn btn-surface btn-sm" id="btnToggleMapCard" onclick="StationMapManager.toggleMapCard()" title="Collapse or expand map view">
            <i class="bi bi-map" id="btnToggleMapIcon"></i> <span id="btnToggleMapText">Hide Map</span>
        </button>
        <?php if ($admin['role'] === 'super_admin'): ?>
        <button class="btn btn-primary btn-sm" onclick="Modal.open('newStationModal')">
            <i class="bi bi-plus-circle-fill"></i> Add New Station
        </button>
        <?php endif; ?>
    </div>
</div>

<?php if (!empty($successMsg)): ?>
<div class="alert alert-success mb-4"><?= htmlspecialchars($successMsg) ?></div>
<?php endif; ?>
<?php if (!empty($errorMsg)): ?>
<div class="alert alert-critical mb-4"><?= htmlspecialchars($errorMsg) ?></div>
<?php endif; ?>

<!-- ── Interactive Station Map Card (Collapsible) ───────────── -->
<div class="station-map-card" id="stationMapContainer">
    <div class="station-map-header">
        <div style="display:flex;align-items:center;gap:10px;">
            <div style="display:flex;align-items:center;gap:6px;font-weight:700;font-size:var(--font-size-base);color:var(--text-primary);">
                <i class="bi bi-pin-map-fill" style="color:var(--brand-secondary);"></i>
                <span>Station Map Overview</span>
            </div>
            <button class="btn btn-ghost btn-xs" id="btnFitMap" onclick="StationMapManager.fitBounds()" title="Center all stations">
                <i class="bi bi-crosshair"></i> Center
            </button>
        </div>
        <div class="station-map-controls">
            <!-- Map Toggle Layer -->
            <button class="btn-map-toggle active" id="btnToggleStationLayer" onclick="StationMapManager.toggleLayer()" title="Show/Hide station markers">
                <i class="bi bi-layers-fill"></i> Stations Layer
            </button>
        </div>
    </div>
    <div class="station-map-wrapper">
        <div id="stationMap"></div>
    </div>
</div>

<!-- ── Unified Filter Toolbar ───────────────────────────────── -->
<div class="station-toolbar">
    <div class="station-search-box">
        <i class="bi bi-search"></i>
        <input type="text" id="stationSearchInput" class="station-search-input" 
               placeholder="Search station, area, ID..." oninput="StationMapManager.handleSearch(this.value)">
    </div>
    
    <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
        <select id="stationServiceFilter" class="filter-select" onchange="StationMapManager.handleFilter(this.value)">
            <option value="">All Services</option>
            <?php 
            $allServices = getDbServices();
            foreach ($allServices as $k => $s): 
                if ($admin['role'] !== 'super_admin' && !isAdminAllowedCategory($k, $admin)) continue;
            ?>
            <option value="<?= htmlspecialchars($k) ?>" <?= $serviceFilter === $k ? 'selected' : '' ?>><?= htmlspecialchars($s['service_name']) ?></option>
            <?php endforeach; ?>
        </select>
        <span class="badge badge-info" id="stationCountBadge"><?= count($stations) ?> Stations</span>
    </div>
</div>

<!-- ── Stations Directory Table (Streamlined) ───────────────── -->
<div class="card">
    <div class="table-wrapper" style="border:none;border-radius:0;overflow:visible;">
        <table class="table" id="stationsTable">
            <thead>
                <tr>
                    <th style="min-width:220px;">Station</th>
                    <th>Service Category</th>
                    <th>Location & Contact</th>
                    <th>Staff</th>
                    <th style="text-align:right;min-width:120px;">Actions</th>
                </tr>
            </thead>
            <tbody id="stationsTableBody">
                <?php if (empty($stations)): ?>
                <tr>
                    <td colspan="5">
                        <div class="empty-state">
                            <div class="empty-state-icon">🏢</div>
                            <div class="empty-state-title">No Stations Found</div>
                        </div>
                    </td>
                </tr>
                <?php else: ?>
                <?php foreach ($stations as $st): 
                    $hasGeo = ($st['latitude'] != 0 && $st['longitude'] != 0);
                ?>
                <tr class="station-row" 
                    id="stationRow-<?= htmlspecialchars($st['station_id']) ?>"
                    data-station-id="<?= htmlspecialchars($st['station_id']) ?>" 
                    data-service="<?= htmlspecialchars($st['service_id']) ?>"
                    data-name="<?= htmlspecialchars(strtolower($st['name'])) ?>"
                    data-city="<?= htmlspecialchars(strtolower($st['city'])) ?>"
                    data-lat="<?= (float)$st['latitude'] ?>"
                    data-lng="<?= (float)$st['longitude'] ?>">
                    <!-- Column 1: Station ID + Name -->
                    <td>
                        <div style="display:flex;flex-direction:column;gap:2px;">
                            <div style="display:flex;align-items:center;gap:6px;">
                                <code class="mono" style="font-size:11px;"><?= htmlspecialchars($st['station_id']) ?></code>
                                <?php if ($hasGeo): ?>
                                <span title="Geolocated" style="color:var(--color-success);font-size:12px;"><i class="bi bi-geo-fill"></i></span>
                                <?php else: ?>
                                <span title="Coordinates not set" style="color:var(--text-muted);font-size:12px;"><i class="bi bi-geo"></i></span>
                                <?php endif; ?>
                            </div>
                            <strong style="color:var(--text-primary);font-size:13px;"><?= htmlspecialchars($st['name']) ?></strong>
                        </div>
                    </td>

                    <!-- Column 2: Service Category -->
                    <td><span class="badge badge-info" style="font-size:11px;"><?= htmlspecialchars($st['service_label']) ?></span></td>

                    <!-- Column 3: Location & Contact -->
                    <td>
                        <div style="font-size:12px;font-weight:600;color:var(--text-primary);">
                            <i class="bi bi-pin-map-fill text-muted me-1"></i><?= htmlspecialchars($st['city']) ?>
                        </div>
                        <?php if (!empty($st['address'])): ?>
                        <div class="text-xs text-muted" style="margin-top:1px;"><?= htmlspecialchars($st['address']) ?></div>
                        <?php endif; ?>
                        <?php if (!empty($st['phone'])): ?>
                        <div class="text-xs text-muted" style="margin-top:2px;">
                            <i class="bi bi-telephone text-muted me-1"></i><?= htmlspecialchars($st['phone']) ?>
                        </div>
                        <?php endif; ?>
                    </td>

                    <!-- Column 4: Staff -->
                    <td><span class="badge badge-success" style="font-size:11px;"><?= (int)$st['staff_count'] ?> Staff</span></td>

                    <!-- Column 5: Actions & Context Menu (•••) -->
                    <td style="text-align:right;">
                        <div class="station-actions-cell">
                            <a href="<?= BASE_URL ?>employees.php?station=<?= urlencode($st['station_id']) ?>" class="btn btn-surface btn-xs" title="View Staff">
                                <i class="bi bi-people-fill"></i> Staff
                            </a>
                            <button type="button" class="btn btn-ghost btn-xs" 
                                    onclick="StationMapManager.toggleRowMenu('<?= htmlspecialchars($st['station_id']) ?>', event)" title="More options">
                                <i class="bi bi-three-dots"></i>
                            </button>

                            <!-- Context Dropdown Menu -->
                            <div class="station-dropdown-menu" id="stationMenu-<?= htmlspecialchars($st['station_id']) ?>">
                                <?php if ($hasGeo): ?>
                                <button type="button" class="station-dropdown-item" 
                                        onclick="StationMapManager.focusStation('<?= htmlspecialchars($st['station_id']) ?>', <?= (float)$st['latitude'] ?>, <?= (float)$st['longitude'] ?>)">
                                    <i class="bi bi-geo-alt-fill text-primary"></i> Focus on Map
                                </button>
                                <a href="https://www.google.com/maps/dir/?api=1&destination=<?= (float)$st['latitude'] ?>,<?= (float)$st['longitude'] ?>" 
                                   target="_blank" rel="noopener" class="station-dropdown-item">
                                    <i class="bi bi-cursor-fill text-success"></i> Get Directions
                                </a>
                                <?php endif; ?>
                                <?php if (!empty($st['phone'])): ?>
                                <a href="tel:<?= htmlspecialchars($st['phone']) ?>" class="station-dropdown-item">
                                    <i class="bi bi-telephone-fill text-info"></i> Call Station
                                </a>
                                <?php endif; ?>
                                <?php if (!empty($st['email'])): ?>
                                <a href="mailto:<?= htmlspecialchars($st['email']) ?>" class="station-dropdown-item">
                                    <i class="bi bi-envelope-fill text-secondary"></i> Email Station
                                </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal: New Station -->
<div class="modal-backdrop" id="newStationModal">
    <div class="modal" style="max-width:560px;">
        <form method="POST" action="">
            <input type="hidden" name="<?= CSRF_TOKEN_NAME ?>" value="<?= htmlspecialchars($csrfToken) ?>">
            <input type="hidden" name="create_station" value="1">
            <div class="modal-header">
                <span class="modal-title"><i class="bi bi-plus-circle-fill text-primary"></i> Register Service Station</span>
                <button type="button" class="modal-close" onclick="Modal.close('newStationModal')"><i class="bi bi-x"></i></button>
            </div>
            <div class="modal-body">
                <div class="form-group mb-3">
                    <label class="form-label">Station ID (e.g. STN-FIRE-01)</label>
                    <input type="text" name="station_id" class="form-control" placeholder="STN-FIRE-01" required>
                </div>
                <div class="form-group mb-3">
                    <label class="form-label">Station Name</label>
                    <input type="text" name="name" class="form-control" placeholder="Central Fire Station" required>
                </div>
                <div class="grid grid-cols-2 gap-3 mb-3">
                    <div class="form-group">
                        <label class="form-label">Service Key</label>
                        <select name="service_id" class="form-control" required onchange="document.getElementById('service_label_input').value = this.options[this.selectedIndex].text;">
                            <?php 
                            $firstLabel = '';
                            foreach ($allServices as $k => $c): 
                                if (empty($firstLabel)) $firstLabel = $c['service_name'];
                            ?>
                            <option value="<?= $k ?>"><?= htmlspecialchars($c['service_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Service Label</label>
                        <input type="text" id="service_label_input" name="service_label" class="form-control" value="<?= htmlspecialchars($firstLabel ?: 'Service Station') ?>" required>
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-3 mb-3">
                    <div class="form-group">
                        <label class="form-label">Email</label>
                        <input type="email" name="email" class="form-control" placeholder="station@city.gov" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Phone</label>
                        <input type="text" name="phone" class="form-control" placeholder="101" required>
                    </div>
                </div>
                <div class="form-group mb-3">
                    <label class="form-label">City / Area Zone</label>
                    <input type="text" name="city" class="form-control" placeholder="Central Pune, Zone 1" required>
                </div>
                <div class="form-group mb-3">
                    <label class="form-label">Street Address</label>
                    <input type="text" name="address" class="form-control" placeholder="e.g. 102 Shivaji Nagar, Station Road">
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div class="form-group">
                        <label class="form-label">Latitude</label>
                        <input type="number" step="any" name="latitude" class="form-control" placeholder="18.5204">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Longitude</label>
                        <input type="number" step="any" name="longitude" class="form-control" placeholder="73.8567">
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-ghost" onclick="Modal.close('newStationModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Create Station</button>
            </div>
        </form>
    </div>
</div>

<script>
const INITIAL_STATIONS = <?= json_encode($stationsJson) ?>;

const StationMapManager = {
    allStations: INITIAL_STATIONS,
    filteredStations: INITIAL_STATIONS,
    markerMap: {},

    init() {
        // Init MapView specifically for stationMap
        MapView.init('stationMap', { zoom: 12, autoRefresh: false });
        MapView.toggleStations(true);
        this.renderStationsOnMap(this.allStations);
        this.fitBounds();
        this.initMapPersistence();
        this.initClickOutside();
    },

    initMapPersistence() {
        const saved = localStorage.getItem('stations_map_visible');
        const container = document.getElementById('stationMapContainer');
        const text = document.getElementById('btnToggleMapText');
        const icon = document.getElementById('btnToggleMapIcon');

        // Visible by default; collapse only if explicitly 'false'
        if (saved === 'false' && container) {
            container.classList.add('collapsed');
            if (text) text.textContent = 'Show Map';
            if (icon) icon.className = 'bi bi-map-fill';
        } else if (text) {
            text.textContent = 'Hide Map';
            if (icon) icon.className = 'bi bi-map';
        }
    },

    toggleMapCard() {
        const container = document.getElementById('stationMapContainer');
        const text = document.getElementById('btnToggleMapText');
        const icon = document.getElementById('btnToggleMapIcon');
        if (!container) return;

        const isCurrentlyCollapsed = container.classList.contains('collapsed');
        if (isCurrentlyCollapsed) {
            container.classList.remove('collapsed');
            localStorage.setItem('stations_map_visible', 'true');
            if (text) text.textContent = 'Hide Map';
            if (icon) icon.className = 'bi bi-map';
            setTimeout(() => {
                if (MapView.map) MapView.map.invalidateSize();
                this.fitBounds();
            }, 100);
        } else {
            container.classList.add('collapsed');
            localStorage.setItem('stations_map_visible', 'false');
            if (text) text.textContent = 'Show Map';
            if (icon) icon.className = 'bi bi-map-fill';
        }
    },

    initClickOutside() {
        document.addEventListener('click', (e) => {
            if (!e.target.closest('.station-actions-cell')) {
                this.closeAllMenus();
            }
        });
    },

    closeAllMenus() {
        document.querySelectorAll('.station-dropdown-menu.show').forEach(m => m.classList.remove('show'));
        document.querySelectorAll('.station-row.has-menu-open').forEach(r => r.classList.remove('has-menu-open'));
    },

    toggleRowMenu(stationId, e) {
        if (e) e.stopPropagation();
        const menu = document.getElementById('stationMenu-' + stationId);
        if (!menu) return;
        const row = document.getElementById('stationRow-' + stationId);
        const isOpen = menu.classList.contains('show');
        this.closeAllMenus();
        if (!isOpen) {
            menu.classList.add('show');
            if (row) row.classList.add('has-menu-open');
        }
    },

    renderStationsOnMap(stations) {
        this.markerMap = {};
        MapView.stationLayer.clearLayers();

        const isDark = document.documentElement.getAttribute('data-theme') === 'dark';
        const popupText = isDark ? '#F1F5F9' : '#0F172A';
        const popupMuted = isDark ? '#94A3B8' : '#64748B';
        const popupBorder = isDark ? '#1E293B' : '#E2E8F0';

        stations.forEach(stn => {
            if (!stn.lat || !stn.lng || (stn.lat === 0 && stn.lng === 0)) return;

            const color = stn.color || '#6366F1';
            const stationIcon = L.divIcon({
                className: 'station-marker-wrapper',
                iconSize:  [36, 42],
                iconAnchor:[18, 42],
                popupAnchor: [0, -44],
                html: `
                <div class="station-map-marker" style="--stn-color:${color};">
                    <div class="station-map-marker-pin">
                        <i class="bi bi-building-fill"></i>
                    </div>
                    <div class="station-map-marker-shadow"></div>
                </div>`,
            });

            const marker = L.marker([stn.lat, stn.lng], { icon: stationIcon });
            marker.bindPopup(`
                <div class="station-popup" style="font-family:Inter,sans-serif;min-width:250px;max-width:300px;">
                    <div style="display:flex;align-items:center;gap:10px;margin-bottom:10px;">
                        <div style="width:38px;height:38px;border-radius:10px;background:${color}18;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                            <i class="bi bi-building-fill" style="font-size:18px;color:${color};"></i>
                        </div>
                        <div style="min-width:0;">
                            <div style="font-weight:800;font-size:14px;color:${popupText};line-height:1.2;">${escHtml(stn.name)}</div>
                            <span style="display:inline-block;background:${color}18;color:${color};padding:2px 8px;border-radius:999px;font-size:10px;font-weight:700;margin-top:3px;">${escHtml(stn.service_label)}</span>
                        </div>
                    </div>
                    <div style="display:flex;flex-direction:column;gap:6px;margin-bottom:12px;padding:10px;background:${isDark ? '#1E293B' : '#F8FAFC'};border-radius:8px;border:1px solid ${popupBorder};">
                        ${stn.address ? `<div style="display:flex;align-items:flex-start;gap:8px;">
                            <i class="bi bi-geo-alt-fill" style="color:${popupMuted};font-size:13px;margin-top:1px;flex-shrink:0;"></i>
                            <span style="font-size:12px;color:${popupText};line-height:1.3;">${escHtml(stn.address)}</span>
                        </div>` : ''}
                        ${stn.phone ? `<div style="display:flex;align-items:center;gap:8px;">
                            <i class="bi bi-telephone-fill" style="color:${popupMuted};font-size:12px;flex-shrink:0;"></i>
                            <a href="tel:${escHtml(stn.phone)}" style="font-size:12px;color:${color};font-weight:600;text-decoration:none;">${escHtml(stn.phone)}</a>
                        </div>` : ''}
                        ${stn.email ? `<div style="display:flex;align-items:center;gap:8px;">
                            <i class="bi bi-envelope-fill" style="color:${popupMuted};font-size:12px;flex-shrink:0;"></i>
                            <a href="mailto:${escHtml(stn.email)}" style="font-size:12px;color:${color};font-weight:600;text-decoration:none;overflow:hidden;text-overflow:ellipsis;">${escHtml(stn.email)}</a>
                        </div>` : ''}
                        <div style="display:flex;align-items:center;gap:8px;">
                            <i class="bi bi-people-fill" style="color:${popupMuted};font-size:12px;flex-shrink:0;"></i>
                            <span style="font-size:12px;color:${popupText};font-weight:600;">${stn.staff_count} Staff Member${stn.staff_count !== 1 ? 's' : ''}</span>
                        </div>
                    </div>
                    <div style="display:flex;gap:8px;">
                        <a href="https://www.google.com/maps/dir/?api=1&destination=${stn.lat},${stn.lng}" target="_blank" rel="noopener"
                           style="flex:1;display:flex;align-items:center;justify-content:center;gap:6px;background:${color};color:#fff;padding:8px 12px;border-radius:8px;font-size:12px;font-weight:700;text-decoration:none;">
                            <i class="bi bi-cursor-fill" style="font-size:13px;"></i> Get Directions
                        </a>
                        <a href="${window.BASE_URL || ''}employees.php?station=${encodeURIComponent(stn.station_id)}" 
                           style="display:flex;align-items:center;justify-content:center;gap:4px;background:${isDark ? '#1E293B' : '#F1F5F9'};color:${popupText};padding:8px 12px;border-radius:8px;font-size:12px;font-weight:600;text-decoration:none;border:1px solid ${popupBorder};">
                            <i class="bi bi-people-fill" style="font-size:12px;"></i> Staff
                        </a>
                    </div>
                </div>
            `, { maxWidth: 320 });

            MapView.stationLayer.addLayer(marker);
            this.markerMap[stn.station_id] = marker;
        });

        // Update badge count
        const badge = document.getElementById('stationCountBadge');
        if (badge) badge.textContent = `${stations.length} Station${stations.length !== 1 ? 's' : ''}`;
    },

    handleSearch(query) {
        const q = query.trim().toLowerCase();
        const svcFilter = document.getElementById('stationServiceFilter')?.value || '';

        this.applyFilter(q, svcFilter);
    },

    handleFilter(svc) {
        const q = (document.getElementById('stationSearchInput')?.value || '').trim().toLowerCase();
        this.applyFilter(q, svc);
    },

    applyFilter(query, svc) {
        this.filteredStations = this.allStations.filter(s => {
            const matchSvc = !svc || s.service_id === svc;
            const matchQ = !query || 
                s.name.toLowerCase().includes(query) ||
                s.station_id.toLowerCase().includes(query) ||
                s.city.toLowerCase().includes(query) ||
                (s.address && s.address.toLowerCase().includes(query));
            return matchSvc && matchQ;
        });

        this.renderStationsOnMap(this.filteredStations);
        this.filterTableRows(query, svc);

        if (this.filteredStations.length > 0) {
            this.fitBounds();
        }
    },

    filterTableRows(query, svc) {
        document.querySelectorAll('#stationsTableBody .station-row').forEach(row => {
            const rowSvc = row.getAttribute('data-service') || '';
            const rowName = row.getAttribute('data-name') || '';
            const rowCity = row.getAttribute('data-city') || '';
            const rowId = (row.getAttribute('data-station-id') || '').toLowerCase();

            const matchSvc = !svc || rowSvc === svc;
            const matchQ = !query || 
                rowName.includes(query) || 
                rowCity.includes(query) || 
                rowId.includes(query);

            row.style.display = (matchSvc && matchQ) ? '' : 'none';
        });
    },

    toggleLayer() {
        const btn = document.getElementById('btnToggleStationLayer');
        const visible = MapView.toggleStations();
        if (btn) {
            btn.classList.toggle('active', visible);
        }
    },

    fitBounds() {
        MapView.fitToStations();
    },

    focusStation(stationId, lat, lng) {
        if (!lat || !lng || (lat === 0 && lng === 0)) {
            Toast.warning('Coordinates not set for this station.');
            return;
        }

        this.closeAllMenus();

        // Auto-expand map if currently collapsed
        const container = document.getElementById('stationMapContainer');
        if (container && container.classList.contains('collapsed')) {
            this.toggleMapCard();
        }

        // Scroll to map smoothly
        container?.scrollIntoView({ behavior: 'smooth' });

        if (MapView.map) {
            MapView.map.flyTo([lat, lng], 15, { duration: 1 });
            setTimeout(() => {
                const marker = this.markerMap[stationId];
                if (marker) marker.openPopup();
            }, 1000);
        }
    }
};

document.addEventListener('DOMContentLoaded', () => {
    StationMapManager.init();
});
</script>

<?php require_once __DIR__ . '/includes/layout_end.php'; ?>
