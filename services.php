<?php
// ============================================================
// City Planner Web Admin — Services Directory & Interactions Hub
// ============================================================
$pageTitle  = 'Services Directory';
$activePage = 'services';

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/session.php';
require_once __DIR__ . '/includes/rbac.php';
require_once __DIR__ . '/includes/audit.php';

startSecureSession();
requireLogin();
requirePermission('services.view');

$db            = getDB();
$admin         = getSessionAdmin();
$csrfToken     = generateCsrfToken();
$isSuperAdmin  = ($admin['role'] === 'super_admin');
$canManage     = hasPermission('services.manage');

// Handle direct POST create/update/delete if submitted via form fallback
$msgSuccess = '';
$msgError   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $canManage) {
    if (validateCsrf()) {
        if (isset($_POST['action_create_service'])) {
            $sKey  = strtolower(trim(preg_replace('/[^a-zA-Z0-9_]/', '_', $_POST['service_key'] ?? '')));
            $sName = sanitize($_POST['service_name'] ?? '');
            $sCat  = in_array($_POST['category'] ?? '', ['Emergency', 'Citizen']) ? $_POST['category'] : 'Citizen';

            if (!empty($sKey) && !empty($sName)) {
                $stmt = $db->prepare("INSERT INTO `services` (`service_key`, `service_name`, `category`) VALUES (?, ?, ?)");
                $stmt->bind_param('sss', $sKey, $sName, $sCat);
                if ($stmt->execute()) {
                    logAudit('create_service', 'services', (string)$stmt->insert_id, '', "key:$sKey name:$sName");
                    $msgSuccess = "Service \"$sName\" registered successfully.";
                } else {
                    $msgError = "Error adding service: " . $db->error;
                }
            } else {
                $msgError = "Service key and name cannot be empty.";
            }
        } elseif (isset($_POST['action_edit_service'])) {
            $sId   = (int)($_POST['service_id'] ?? 0);
            $sName = sanitize($_POST['service_name'] ?? '');
            $sCat  = in_array($_POST['category'] ?? '', ['Emergency', 'Citizen']) ? $_POST['category'] : 'Citizen';

            if ($sId > 0 && !empty($sName)) {
                $stmt = $db->prepare("UPDATE `services` SET `service_name` = ?, `category` = ? WHERE `id` = ?");
                $stmt->bind_param('ssi', $sName, $sCat, $sId);
                if ($stmt->execute()) {
                    logAudit('update_service', 'services', (string)$sId, '', "name:$sName");
                    $msgSuccess = "Service updated successfully.";
                } else {
                    $msgError = "Error updating service: " . $db->error;
                }
            }
        } elseif (isset($_POST['action_delete_service'])) {
            $sId = (int)($_POST['service_id'] ?? 0);
            if ($sId > 0) {
                $db->query("DELETE FROM `services` WHERE `id` = $sId");
                logAudit('delete_service', 'services', (string)$sId, '', '');
                $msgSuccess = "Service deleted successfully.";
            }
        } elseif (isset($_POST['action_delete_interaction'])) {
            $iId = (int)($_POST['interaction_id'] ?? 0);
            if ($iId > 0) {
                $db->query("DELETE FROM `citizen_services` WHERE `id` = $iId");
                logAudit('delete_citizen_interaction', 'citizen_services', (string)$iId, '', '');
                $msgSuccess = "Citizen interaction log deleted.";
            }
        }
    } else {
        $msgError = "Security token expired. Please try again.";
    }
}

// ── Fetch Services from DB ────────────────────────────────────
$servicesList = [];
$servRes = $db->query("SELECT * FROM `services` ORDER BY category ASC, id ASC");
if ($servRes) {
    while ($row = $servRes->fetch_assoc()) {
        $k = $row['service_key'];
        $meta = CATEGORIES[$k] ?? [];
        
        // Count stations for this service
        $stRes = $db->query("SELECT COUNT(*) AS cnt FROM `service_stations` WHERE `service_id` = '" . $db->real_escape_string($k) . "'");
        $stationCount = $stRes ? (int)$stRes->fetch_assoc()['cnt'] : 0;
        
        $row['icon']          = $meta['icon'] ?? 'bi-gear-fill';
        $row['color']         = $meta['color'] ?? ($row['category'] === 'Emergency' ? '#EF4444' : '#10B981');
        $row['station_count'] = $stationCount;
        $servicesList[] = $row;
    }
}

// ── Service Metrics ───────────────────────────────────────────
$totalServices = count($servicesList);
$emergencyCount = count(array_filter($servicesList, fn($s) => $s['category'] === 'Emergency'));
$citizenCount   = count(array_filter($servicesList, fn($s) => $s['category'] === 'Citizen'));

// ── Fetch Citizen Service Interactions (strictly scoped to admin jurisdiction) ──
$searchQ   = trim($_GET['q'] ?? '');
$filterSrv = trim($_GET['srv'] ?? '');
$filterTyp = trim($_GET['typ'] ?? '');
$activeTab = $_GET['tab'] ?? 'catalog';

$intWhere = " WHERE 1=1 ";
$intWhere .= getCitizenServiceScopeWhere('citizen_services');

if ($filterSrv) {
    if ($isSuperAdmin || isAdminAllowedCategory($filterSrv, $admin)) {
        $intWhere .= " AND `service_id` = '" . $db->real_escape_string($filterSrv) . "' ";
    } else {
        $intWhere .= " AND 1=0 ";
    }
}
if ($filterTyp === 'call') {
    $intWhere .= " AND `is_call_made` = 1 ";
} elseif ($filterTyp === 'request') {
    $intWhere .= " AND `is_request_sent` = 1 ";
}
if ($searchQ) {
    $sq = $db->real_escape_string($searchQ);
    $intWhere .= " AND (`citizen_name` LIKE '%$sq%' OR `contact_number` LIKE '%$sq%' OR `service_label` LIKE '%$sq%' OR `description` LIKE '%$sq%' OR `location_address` LIKE '%$sq%') ";
}

$csScope = getCitizenServiceScopeWhere('citizen_services');
$intStatsRes = $db->query("SELECT 
    COUNT(*) AS total_interactions,
    SUM(is_call_made = 1) AS total_calls,
    SUM(is_request_sent = 1) AS total_requests,
    SUM(image_data IS NOT NULL AND image_data != '') AS total_images
    FROM `citizen_services` WHERE 1=1 $csScope");
$intStats = $intStatsRes ? $intStatsRes->fetch_assoc() : ['total_interactions'=>0, 'total_calls'=>0, 'total_requests'=>0, 'total_images'=>0];

$interactionsQ = $db->query("SELECT * FROM `citizen_services` $intWhere ORDER BY id DESC LIMIT 100");
$interactions = [];
if ($interactionsQ) {
    while ($iRow = $interactionsQ->fetch_assoc()) {
        $interactions[] = $iRow;
    }
}

$showBackButton = true;
require_once __DIR__ . '/includes/layout.php';
?>

<!-- ── Page Header ──────────────────────────────────────────── -->
<div class="page-header">
    <div>
        <h1 class="page-title"><i class="bi bi-grid-3x3-gap-fill text-primary"></i> Services & Helplines</h1>
        <p class="page-subtitle">Service catalog and citizen interaction logs</p>
    </div>
    <div class="page-actions flex gap-2">
        <?php if ($canManage): ?>
        <button class="btn btn-primary btn-sm" onclick="Modal.open('addServiceModal')">
            <i class="bi bi-plus-circle-fill"></i> Add New Service
        </button>
        <?php endif; ?>
    </div>
</div>

<?php if (!empty($msgSuccess)): ?>
<div class="alert alert-success mb-4"><i class="bi bi-check-circle-fill me-2"></i><?= htmlspecialchars($msgSuccess) ?></div>
<?php endif; ?>
<?php if (!empty($msgError)): ?>
<div class="alert alert-critical mb-4"><i class="bi bi-exclamation-octagon-fill me-2"></i><?= htmlspecialchars($msgError) ?></div>
<?php endif; ?>

<!-- ── Navigation Tabs ──────────────────────────────────────── -->
<div class="services-nav-tabs">
    <button type="button" class="services-nav-tab <?= $activeTab === 'catalog' ? 'active' : '' ?>" id="tabBtnCatalog" onclick="switchTab('catalog')">
        <i class="bi bi-grid-fill"></i> Master Service Catalog
        <span class="tab-badge"><?= $totalServices ?></span>
    </button>
    <button type="button" class="services-nav-tab <?= $activeTab === 'interactions' ? 'active' : '' ?>" id="tabBtnInteractions" onclick="switchTab('interactions')">
        <i class="bi bi-person-lines-fill"></i> Citizen Helpline Interactions
        <span class="tab-badge"><?= (int)$intStats['total_interactions'] ?></span>
    </button>
</div>

<!-- ── TAB 1: MASTER SERVICE CATALOG ────────────────────── -->
<div id="tab-catalog" style="display:<?= $activeTab === 'catalog' ? 'block' : 'none' ?>;">
    <div class="card">
        <!-- Catalog Toolbar -->
        <div class="catalog-toolbar">
            <div class="status-pills-bar" id="catalogCategoryPills">
                <button type="button" class="status-pill active" data-cat="" onclick="filterCatalogCategory('')">
                    <span class="status-pill-dot" style="background:var(--brand-primary, #6366f1);"></span>
                    <span>All</span>
                    <span class="status-pill-count"><?= $totalServices ?></span>
                </button>
                <button type="button" class="status-pill" data-cat="Emergency" onclick="filterCatalogCategory('Emergency')">
                    <span class="status-pill-dot" style="background:var(--color-critical, #ef4444);"></span>
                    <span>Emergency</span>
                    <span class="status-pill-count"><?= $emergencyCount ?></span>
                </button>
                <button type="button" class="status-pill" data-cat="Citizen" onclick="filterCatalogCategory('Citizen')">
                    <span class="status-pill-dot" style="background:var(--color-success, #10b981);"></span>
                    <span>Citizen</span>
                    <span class="status-pill-count"><?= $citizenCount ?></span>
                </button>
            </div>
            <div class="catalog-search-wrap">
                <i class="bi bi-search filter-search-icon"></i>
                <input type="text" id="catalogSearchInput" class="form-control minimal-search-input" placeholder="Search service name or key..." oninput="filterCatalogSearch(this.value)">
            </div>
        </div>

        <div class="table-wrapper" style="border:none;border-radius:0;">
            <table class="table" id="servicesTable">
                <thead>
                    <tr>
                        <th>Service</th>
                        <th>Category</th>
                        <th>Registered Stations</th>
                        <th>Dispatch Route</th>
                        <?php if ($canManage): ?><th>Actions</th><?php endif; ?>
                    </tr>
                </thead>
                <tbody id="servicesTableBody">
                    <?php if (empty($servicesList)): ?>
                    <tr id="catalogEmptyRow">
                        <td colspan="<?= $canManage ? 5 : 4 ?>">
                            <div class="empty-state" style="padding:48px;">
                                <div class="empty-state-icon">🛠️</div>
                                <div class="empty-state-title">No Services Found</div>
                                <div class="empty-state-sub">Add services to enable departmental routing</div>
                            </div>
                        </td>
                    </tr>
                    <?php else: ?>
                    <tr id="catalogEmptyRow" style="display:none;">
                        <td colspan="<?= $canManage ? 5 : 4 ?>">
                            <div class="empty-state" style="padding:48px;">
                                <div class="empty-state-icon">🔍</div>
                                <div class="empty-state-title">No Matching Services</div>
                                <div class="empty-state-sub">Try adjusting your category filter or search keywords</div>
                            </div>
                        </td>
                    </tr>
                    <?php foreach ($servicesList as $srv): ?>
                    <tr class="service-row" data-category="<?= htmlspecialchars($srv['category']) ?>" data-search="<?= htmlspecialchars(strtolower($srv['service_name'] . ' ' . $srv['service_key'])) ?>">
                        <td>
                            <div style="display:flex;align-items:center;gap:12px;">
                                <div style="width:36px;height:36px;border-radius:var(--radius-md);background:<?= $srv['color'] ?>20;color:<?= $srv['color'] ?>;display:flex;align-items:center;justify-content:center;font-size:17px;flex-shrink:0;">
                                    <i class="bi <?= $srv['icon'] ?>"></i>
                                </div>
                                <div>
                                    <div style="font-weight:600;font-size:14px;color:var(--text-primary);"><?= htmlspecialchars($srv['service_name']) ?></div>
                                    <div style="font-size:11px;color:var(--text-muted);display:flex;align-items:center;gap:6px;margin-top:2px;">
                                        <span class="mono text-muted">#<?= $srv['id'] ?></span>
                                        <span>&bull;</span>
                                        <code class="mono" style="font-size:11px;background:var(--bg-surface-2);padding:1px 5px;border-radius:4px;border:1px solid var(--border-light);"><?= htmlspecialchars($srv['service_key']) ?></code>
                                    </div>
                                </div>
                            </div>
                        </td>
                        <td>
                            <span class="badge badge-<?= $srv['category'] === 'Emergency' ? 'critical' : 'success' ?>">
                                <?= htmlspecialchars($srv['category']) ?>
                            </span>
                        </td>
                        <td>
                            <a href="<?= BASE_URL ?>stations.php?service=<?= urlencode($srv['service_key']) ?>" class="badge badge-neutral" style="text-decoration:none;display:inline-flex;align-items:center;gap:4px;">
                                <i class="bi bi-geo-alt-fill text-muted"></i> <?= $srv['station_count'] ?> Stations
                            </a>
                        </td>
                        <td>
                            <a href="<?= BASE_URL ?>requests.php?category=<?= urlencode($srv['service_key']) ?>" class="btn btn-surface btn-sm" title="View requests for this service">
                                <i class="bi bi-inbox-fill"></i> Queue
                            </a>
                        </td>
                        <?php if ($canManage): ?>
                        <td>
                            <div class="flex gap-1">
                                <button type="button" class="btn btn-ghost btn-sm" title="Edit Service"
                                        onclick="openEditService(<?= htmlspecialchars(json_encode($srv)) ?>)">
                                    <i class="bi bi-pencil-fill"></i>
                                </button>
                                <?php if ($isSuperAdmin): ?>
                                <button type="button" class="btn btn-ghost btn-sm text-critical" title="Delete Service"
                                        onclick="confirmDeleteService(<?= $srv['id'] ?>, '<?= htmlspecialchars($srv['service_name']) ?>')">
                                    <i class="bi bi-trash-fill"></i>
                                </button>
                                <?php endif; ?>
                            </div>
                        </td>
                        <?php endif; ?>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- ── TAB 2: CITIZEN SERVICE HELPLINE INTERACTIONS ─────── -->
<div id="tab-interactions" style="display:<?= $activeTab === 'interactions' ? 'block' : 'none' ?>;">
    <div class="card">
        <!-- Minimal Filter Container -->
        <div class="minimal-filter-container">
            <form method="GET" action="" id="interactionsFilterForm">
                <input type="hidden" name="tab" value="interactions">
                <div class="minimal-filter-bar">
                    <div class="filter-search-wrap">
                        <i class="bi bi-search filter-search-icon"></i>
                        <input type="text" name="q" class="form-control minimal-search-input" placeholder="Search citizen, phone, address, notes..." value="<?= htmlspecialchars($searchQ) ?>">
                    </div>

                    <div class="minimal-filter-actions">
                        <button type="button" class="btn btn-surface btn-sm filter-drawer-toggle <?= ($filterSrv || $filterTyp) ? 'has-active-filters' : '' ?>" id="interactionsFilterBtn" onclick="toggleInteractionsDrawer()">
                            <i class="bi bi-funnel"></i>
                            <span>Filters</span>
                            <span id="interactionsFilterBadge" class="filter-count-badge" style="<?= ($filterSrv || $filterTyp) ? '' : 'display:none;' ?>"><?= (($filterSrv ? 1 : 0) + ($filterTyp ? 1 : 0)) ?></span>
                            <i class="bi bi-chevron-down filter-chevron"></i>
                        </button>
                        <button type="submit" class="btn btn-primary btn-sm">
                            <i class="bi bi-search"></i> Search
                        </button>
                        <?php if ($searchQ || $filterSrv || $filterTyp): ?>
                        <a href="<?= BASE_URL ?>services.php?tab=interactions" class="btn btn-ghost btn-sm clear-filters-btn" title="Reset all filters">
                            <i class="bi bi-arrow-counterclockwise"></i> Reset
                        </a>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Collapsible Secondary Filters Drawer -->
                <div class="filter-drawer <?= ($filterSrv || $filterTyp) ? 'open' : '' ?>" id="interactionsFilterDrawer">
                    <div class="filter-drawer-inner">
                        <div class="filter-drawer-grid">
                            <div class="filter-field">
                                <label class="filter-field-label">Target Service</label>
                                <select name="srv" class="filter-select">
                                    <option value="">All Services</option>
                                    <?php foreach ($servicesList as $s): ?>
                                    <option value="<?= $s['service_key'] ?>" <?= $filterSrv === $s['service_key'] ? 'selected' : '' ?>><?= htmlspecialchars($s['service_name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="filter-field">
                                <label class="filter-field-label">Interaction Mode</label>
                                <select name="typ" class="filter-select">
                                    <option value="">All Interaction Types</option>
                                    <option value="call" <?= $filterTyp === 'call' ? 'selected' : '' ?>>Helpline Call Made</option>
                                    <option value="request" <?= $filterTyp === 'request' ? 'selected' : '' ?>>Request Sent</option>
                                </select>
                            </div>

                            <div class="filter-field" style="align-self:flex-end;">
                                <button type="submit" class="btn btn-primary btn-sm">
                                    <i class="bi bi-funnel-fill"></i> Apply Filter
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </form>
        </div>

        <div class="table-wrapper" style="border:none;border-radius:0;">
            <table class="table" id="interactionsTable">
                <thead>
                    <tr>
                        <th>Citizen</th>
                        <th>Service & Mode</th>
                        <th>Description / Notes</th>
                        <th>Location & Media</th>
                        <th>Logged At</th>
                        <?php if ($canManage): ?><th>Actions</th><?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($interactions)): ?>
                    <tr>
                        <td colspan="<?= $canManage ? 6 : 5 ?>">
                            <div class="empty-state" style="padding:48px;">
                                <div class="empty-state-icon">📞</div>
                                <div class="empty-state-title">No Citizen Interactions Logged</div>
                                <div class="empty-state-sub">Citizen hotline calls and direct service triggers will appear here in real-time</div>
                            </div>
                        </td>
                    </tr>
                    <?php else: ?>
                    <?php foreach ($interactions as $it): 
                        $hasImage = !empty($it['image_data']);
                        $hasGeo   = !empty($it['latitude']) && !empty($it['longitude']);
                    ?>
                    <tr>
                        <td>
                            <div style="font-weight:600;font-size:13px;"><?= htmlspecialchars($it['citizen_name']) ?></div>
                            <div style="font-size:12px;margin-top:2px;">
                                <a href="tel:<?= htmlspecialchars($it['contact_number']) ?>" class="text-info" style="text-decoration:none;">
                                    <i class="bi bi-telephone-fill me-1"></i><?= htmlspecialchars($it['contact_number']) ?>
                                </a>
                            </div>
                            <div class="mono text-muted" style="font-size:11px;margin-top:2px;">#<?= $it['id'] ?></div>
                        </td>
                        <td>
                            <span class="badge badge-info" style="margin-bottom:4px;display:inline-block;"><?= htmlspecialchars($it['service_label'] ?: $it['service_id']) ?></span>
                            <div>
                                <?php if ($it['is_call_made']): ?>
                                <span class="badge badge-success" style="font-size:10px;"><i class="bi bi-telephone-outbound-fill me-1"></i> Call Placed</span>
                                <?php endif; ?>
                                <?php if ($it['is_request_sent']): ?>
                                <span class="badge badge-warning" style="font-size:10px;"><i class="bi bi-send-fill me-1"></i> Request Sent</span>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td style="max-width:260px;">
                            <div style="font-size:13px;line-height:1.4;" class="line-clamp-2" title="<?= htmlspecialchars($it['description'] ?? '') ?>">
                                <?= htmlspecialchars($it['description'] ?: 'No notes provided') ?>
                            </div>
                        </td>
                        <td style="max-width:220px;">
                            <div class="text-xs mb-2" title="<?= htmlspecialchars($it['location_address'] ?? '') ?>">
                                <i class="bi bi-geo-alt-fill text-critical me-1"></i><?= htmlspecialchars($it['location_address'] ?: 'Coordinates only') ?>
                            </div>
                            <div style="display:flex;gap:6px;flex-wrap:wrap;">
                                <?php if ($hasGeo): ?>
                                <button type="button" class="btn btn-surface btn-xs" 
                                        onclick="openMapModal(<?= (float)$it['latitude'] ?>, <?= (float)$it['longitude'] ?>, '<?= htmlspecialchars(addslashes($it['citizen_name'])) ?>', '<?= htmlspecialchars(addslashes($it['location_address'])) ?>')">
                                    <i class="bi bi-pin-map text-primary"></i> GPS Map
                                </button>
                                <?php endif; ?>
                                <?php if ($hasImage): ?>
                                <button type="button" class="btn btn-surface btn-xs" onclick="openPhotoModal('<?= htmlspecialchars($it['image_data']) ?>')">
                                    <i class="bi bi-image text-primary"></i> Photo Evidence
                                </button>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td class="text-xs">
                            <div><?= date('M d, Y', strtotime($it['created_at'])) ?></div>
                            <div class="text-muted mono"><?= date('H:i:s', strtotime($it['created_at'])) ?></div>
                        </td>
                        <?php if ($canManage): ?>
                        <td>
                            <button type="button" class="btn btn-ghost btn-sm text-critical" title="Delete record"
                                    onclick="confirmDeleteInteraction(<?= $it['id'] ?>)">
                                <i class="bi bi-trash"></i>
                            </button>
                        </td>
                        <?php endif; ?>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- ── Modal: Add New Service ───────────────────────────────── -->
<div class="modal-backdrop" id="addServiceModal">
    <div class="modal">
        <form method="POST" action="">
            <input type="hidden" name="<?= CSRF_TOKEN_NAME ?>" value="<?= htmlspecialchars($csrfToken) ?>">
            <input type="hidden" name="action_create_service" value="1">
            <div class="modal-header">
                <span class="modal-title"><i class="bi bi-plus-circle-fill text-primary"></i> Register New Service</span>
                <button type="button" class="modal-close" onclick="Modal.close('addServiceModal')"><i class="bi bi-x"></i></button>
            </div>
            <div class="modal-body">
                <div class="form-group mb-3">
                    <label class="form-label">Service Key (e.g. disaster_relief, traffic_control)</label>
                    <input type="text" name="service_key" class="form-control" placeholder="e.g. disaster_relief" required pattern="[a-zA-Z0-9_]+" title="Only alphanumeric and underscores allowed">
                    <span class="text-xs text-muted">Used by mobile application and API routers</span>
                </div>
                <div class="form-group mb-3">
                    <label class="form-label">Display Service Name</label>
                    <input type="text" name="service_name" class="form-control" placeholder="e.g. Disaster & Flood Relief" required>
                </div>
                <div class="form-group mb-3">
                    <label class="form-label">Classification Category</label>
                    <select name="category" class="form-control" required>
                        <option value="Emergency">Emergency Service (High Priority Dispatch)</option>
                        <option value="Citizen">Citizen / Municipal Service (Civic Works)</option>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-ghost" onclick="Modal.close('addServiceModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Service</button>
            </div>
        </form>
    </div>
</div>

<!-- ── Modal: Edit Service ──────────────────────────────────── -->
<div class="modal-backdrop" id="editServiceModal">
    <div class="modal">
        <form method="POST" action="">
            <input type="hidden" name="<?= CSRF_TOKEN_NAME ?>" value="<?= htmlspecialchars($csrfToken) ?>">
            <input type="hidden" name="action_edit_service" value="1">
            <input type="hidden" name="service_id" id="edit-service-id" value="">
            <div class="modal-header">
                <span class="modal-title"><i class="bi bi-pencil-square text-primary"></i> Edit Service Details</span>
                <button type="button" class="modal-close" onclick="Modal.close('editServiceModal')"><i class="bi bi-x"></i></button>
            </div>
            <div class="modal-body">
                <div class="form-group mb-3">
                    <label class="form-label">Service Key (Read-Only)</label>
                    <input type="text" id="edit-service-key" class="form-control" disabled>
                </div>
                <div class="form-group mb-3">
                    <label class="form-label">Display Service Name</label>
                    <input type="text" name="service_name" id="edit-service-name" class="form-control" required>
                </div>
                <div class="form-group mb-3">
                    <label class="form-label">Classification Category</label>
                    <select name="category" id="edit-service-category" class="form-control" required>
                        <option value="Emergency">Emergency Service</option>
                        <option value="Citizen">Citizen / Municipal Service</option>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-ghost" onclick="Modal.close('editServiceModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Update Service</button>
            </div>
        </form>
    </div>
</div>

<!-- ── Modal: Delete Confirmation ───────────────────────────── -->
<div class="modal-backdrop" id="deleteConfirmModal">
    <div class="modal modal-sm">
        <form method="POST" action="">
            <input type="hidden" name="<?= CSRF_TOKEN_NAME ?>" value="<?= htmlspecialchars($csrfToken) ?>">
            <input type="hidden" name="action_delete_service" value="1">
            <input type="hidden" name="service_id" id="del-service-id" value="">
            <div class="modal-header">
                <span class="modal-title text-critical"><i class="bi bi-exclamation-triangle-fill"></i> Delete Service</span>
                <button type="button" class="modal-close" onclick="Modal.close('deleteConfirmModal')"><i class="bi bi-x"></i></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to remove <strong id="del-service-name"></strong> from the services directory?</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-ghost" onclick="Modal.close('deleteConfirmModal')">Cancel</button>
                <button type="submit" class="btn btn-danger">Confirm Delete</button>
            </div>
        </form>
    </div>
</div>

<!-- ── Modal: Delete Interaction ────────────────────────────── -->
<div class="modal-backdrop" id="deleteIntModal">
    <div class="modal modal-sm">
        <form method="POST" action="">
            <input type="hidden" name="<?= CSRF_TOKEN_NAME ?>" value="<?= htmlspecialchars($csrfToken) ?>">
            <input type="hidden" name="action_delete_interaction" value="1">
            <input type="hidden" name="interaction_id" id="del-int-id" value="">
            <div class="modal-header">
                <span class="modal-title text-critical"><i class="bi bi-trash-fill"></i> Delete Interaction Log</span>
                <button type="button" class="modal-close" onclick="Modal.close('deleteIntModal')"><i class="bi bi-x"></i></button>
            </div>
            <div class="modal-body">
                <p>Delete this citizen interaction log entry permanently?</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-ghost" onclick="Modal.close('deleteIntModal')">Cancel</button>
                <button type="submit" class="btn btn-danger">Delete Log</button>
            </div>
        </form>
    </div>
</div>

<!-- ── Modal: Photo Preview ─────────────────────────────────── -->
<div class="modal-backdrop" id="photoModal">
    <div class="modal" style="max-width:600px;">
        <div class="modal-header">
            <span class="modal-title"><i class="bi bi-image text-primary"></i> Citizen Uploaded Evidence</span>
            <button type="button" class="modal-close" onclick="Modal.close('photoModal')"><i class="bi bi-x"></i></button>
        </div>
        <div class="modal-body" style="text-align:center;padding:10px;">
            <img id="photoModalImg" src="" alt="Citizen Evidence" style="max-width:100%;max-height:500px;border-radius:var(--radius-md);object-fit:contain;background:#000;">
        </div>
    </div>
</div>

<!-- ── Modal: Location Map ──────────────────────────────────── -->
<div class="modal-backdrop" id="mapModal">
    <div class="modal" style="max-width:700px;">
        <div class="modal-header">
            <span class="modal-title"><i class="bi bi-geo-alt-fill text-critical"></i> Incident Location Coordinates</span>
            <button type="button" class="modal-close" onclick="Modal.close('mapModal')"><i class="bi bi-x"></i></button>
        </div>
        <div class="modal-body" style="padding:0;">
            <div id="serviceModalMap" style="height:380px;width:100%;"></div>
            <div style="padding:12px;" id="mapAddressText" class="text-sm"></div>
        </div>
    </div>
</div>

<!-- Leaflet JS -->
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
let serviceMap = null;
let serviceMarker = null;

let currentCatalogCategory = '';
let currentCatalogSearch = '';

function switchTab(tab) {
    document.getElementById('tab-catalog').style.display = (tab === 'catalog') ? 'block' : 'none';
    document.getElementById('tab-interactions').style.display = (tab === 'interactions') ? 'block' : 'none';
    
    document.getElementById('tabBtnCatalog')?.classList.toggle('active', tab === 'catalog');
    document.getElementById('tabBtnInteractions')?.classList.toggle('active', tab === 'interactions');

    const url = new URL(window.location);
    url.searchParams.set('tab', tab);
    window.history.replaceState({}, '', url);
}

function filterCatalogCategory(cat) {
    currentCatalogCategory = cat;
    document.querySelectorAll('#catalogCategoryPills .status-pill').forEach(pill => {
        const pCat = pill.getAttribute('data-cat') || '';
        pill.classList.toggle('active', pCat === cat);
    });
    applyCatalogFilters();
}

function filterCatalogSearch(query) {
    currentCatalogSearch = (query || '').toLowerCase().trim();
    applyCatalogFilters();
}

function applyCatalogFilters() {
    const rows = document.querySelectorAll('#servicesTableBody .service-row');
    let visibleCount = 0;
    rows.forEach(row => {
        const rowCat = row.getAttribute('data-category') || '';
        const rowSearch = row.getAttribute('data-search') || '';
        const matchCat = !currentCatalogCategory || rowCat === currentCatalogCategory;
        const matchSearch = !currentCatalogSearch || rowSearch.includes(currentCatalogSearch);
        const visible = matchCat && matchSearch;
        row.style.display = visible ? '' : 'none';
        if (visible) visibleCount++;
    });
    const emptyRow = document.getElementById('catalogEmptyRow');
    if (emptyRow) emptyRow.style.display = (visibleCount === 0) ? '' : 'none';
}

function toggleInteractionsDrawer() {
    const drawer = document.getElementById('interactionsFilterDrawer');
    const btn = document.getElementById('interactionsFilterBtn');
    if (!drawer) return;
    const isOpen = drawer.classList.toggle('open');
    if (btn) btn.classList.toggle('open', isOpen);
}

function openEditService(srv) {
    document.getElementById('edit-service-id').value = srv.id;
    document.getElementById('edit-service-key').value = srv.service_key;
    document.getElementById('edit-service-name').value = srv.service_name;
    document.getElementById('edit-service-category').value = srv.category;
    Modal.open('editServiceModal');
}

function confirmDeleteService(id, name) {
    document.getElementById('del-service-id').value = id;
    document.getElementById('del-service-name').innerText = name;
    Modal.open('deleteConfirmModal');
}

function confirmDeleteInteraction(id) {
    document.getElementById('del-int-id').value = id;
    Modal.open('deleteIntModal');
}

function openPhotoModal(imgData) {
    let src = imgData;
    if (!src.startsWith('data:') && !src.startsWith('http') && !src.startsWith('/')) {
        src = 'data:image/jpeg;base64,' + src;
    }
    document.getElementById('photoModalImg').src = src;
    Modal.open('photoModal');
}

function openMapModal(lat, lng, name, address) {
    Modal.open('mapModal');
    document.getElementById('mapAddressText').innerHTML = `<strong>${name}</strong>: ${address} <br><code class="mono">${lat}, ${lng}</code>`;
    
    setTimeout(() => {
        if (!serviceMap) {
            serviceMap = L.map('serviceModalMap').setView([lat, lng], 15);
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '© OpenStreetMap contributors'
            }).addTo(serviceMap);
        } else {
            serviceMap.setView([lat, lng], 15);
            serviceMap.invalidateSize();
        }

        if (serviceMarker) {
            serviceMap.removeLayer(serviceMarker);
        }
        serviceMarker = L.marker([lat, lng]).addTo(serviceMap)
            .bindPopup(`<b>${name}</b><br>${address}`).openPopup();
    }, 200);
}
</script>

<?php require_once __DIR__ . '/includes/layout_end.php'; ?>
