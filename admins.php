<?php
// ============================================================
// City Planner Web Admin — Central Administrator Control Center
// ============================================================
$pageTitle  = 'Administrator Management Center';
$activePage = 'admins';

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/session.php';
require_once __DIR__ . '/includes/rbac.php';
require_once __DIR__ . '/includes/audit.php';

startSecureSession();
requireLogin();
requireRole('super_admin');

$db           = getDB();
$admin        = getSessionAdmin();
$csrfToken    = generateCsrfToken();
$currentAdmId = (int)$admin['id'];

// Fetch all service stations for assignment select options
$stationsList = [];
$stRes = $db->query("SELECT station_id, service_id, service_label, name, city FROM service_stations ORDER BY service_label, name ASC");
if ($stRes) {
    while ($s = $stRes->fetch_assoc()) {
        $stationsList[] = $s;
    }
}

// Fetch all database services
$dbServices = getDbServices();

$showBackButton = true;
require_once __DIR__ . '/includes/layout.php';
?>

<style>

.admin-avatar {
    width: 38px; height: 38px;
    border-radius: var(--radius-lg);
    display: flex; align-items: center; justify-content: center;
    font-weight: 800; font-size: 14px;
    color: #FFFFFF; flex-shrink: 0;
    box-shadow: var(--shadow-sm);
}
.category-chip-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
    gap: 8px;
    max-height: 220px;
    overflow-y: auto;
    padding: 10px;
    background: var(--bg-surface-2);
    border-radius: var(--radius-lg);
    border: 1px solid var(--border-subtle);
}
.category-chip-label {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 6px 10px;
    background: var(--bg-surface-1);
    border: 1px solid var(--border-subtle);
    border-radius: var(--radius-md);
    font-size: 12px;
    cursor: pointer;
    transition: background 0.15s ease, border-color 0.15s ease;
    user-select: none;
}
.category-chip-label:hover {
    border-color: var(--brand-primary);
    background: var(--bg-surface-3);
}
.category-chip-label input[type="checkbox"] {
    accent-color: var(--brand-primary);
}
.preset-btn-bar {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
    margin-bottom: 10px;
}
.preset-btn {
    font-size: 11px;
    padding: 4px 10px;
    border-radius: var(--radius-full);
    background: var(--bg-surface-2);
    border: 1px solid var(--border-subtle);
    color: var(--text-secondary);
    cursor: pointer;
    transition: all 0.15s ease;
}
.preset-btn:hover {
    background: var(--brand-primary);
    color: #ffffff;
    border-color: var(--brand-primary);
}
.audit-log-item {
    display: flex;
    align-items: flex-start;
    gap: 12px;
    padding: 12px;
    border-bottom: 1px solid var(--border-subtle);
}
.audit-log-item:last-child {
    border-bottom: none;
}

/* ── Minimalist KPI Bar & Chips ────────────────────────────────── */
.admin-kpi-bar {
    display: flex;
    align-items: center;
    gap: 10px;
    flex-wrap: wrap;
    margin-bottom: 16px;
    transition: opacity 0.2s ease, max-height 0.25s ease;
}
.admin-kpi-bar.collapsed {
    display: none;
}
.admin-kpi-chip {
    display: inline-flex;
    align-items: center;
    gap: 7px;
    padding: 6px 14px;
    border-radius: var(--radius-full);
    font-size: 12px;
    font-weight: 500;
    border: 1px solid var(--border-subtle);
    background: var(--bg-surface-1);
    color: var(--text-secondary);
}
.admin-kpi-chip b {
    color: var(--text-primary);
    font-weight: 700;
}
.admin-kpi-chip .kpi-dot {
    width: 8px;
    height: 8px;
    border-radius: 50%;
}

/* ── Minimalist Toolbar & Collapsible Filter Tray ─────────────── */
.admin-toolbar-primary {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 12px 16px;
    border-bottom: 1px solid var(--border-subtle);
}
.admin-search-wrapper {
    position: relative;
    flex: 1;
}
.admin-search-wrapper i {
    position: absolute;
    left: 12px;
    top: 50%;
    transform: translateY(-50%);
    color: var(--text-muted);
}
.admin-search-input {
    width: 100%;
    padding: 8px 12px 8px 36px;
    border-radius: var(--radius-md);
    border: 1px solid var(--border-subtle);
    background: var(--bg-surface-2);
    color: var(--text-primary);
    font-size: 13px;
    outline: none;
    transition: border-color 0.15s ease;
}
.admin-search-input:focus {
    border-color: var(--brand-primary);
}
.admin-filter-tray {
    display: none;
    padding: 12px 16px;
    background: var(--bg-surface-2);
    border-bottom: 1px solid var(--border-subtle);
    gap: 12px;
    flex-wrap: wrap;
    align-items: center;
}
.admin-filter-tray.expanded {
    display: flex;
}

/* ── Contextual Dropdown Menu (•••) ──────────────────────────── */
.admin-action-cell {
    position: relative;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    justify-content: flex-end;
}
.admin-dropdown-menu {
    position: absolute;
    right: 0;
    top: 100%;
    margin-top: 6px;
    background: var(--bg-surface-1);
    border: 1px solid var(--border-subtle);
    border-radius: var(--radius-md);
    box-shadow: var(--shadow-lg);
    min-width: 190px;
    z-index: 100;
    padding: 4px;
    display: none;
    text-align: left;
}
.admin-dropdown-menu.show {
    display: block;
}
.admin-dropdown-item {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 7px 12px;
    border-radius: var(--radius-sm);
    font-size: 12px;
    color: var(--text-secondary);
    cursor: pointer;
    background: transparent;
    border: none;
    width: 100%;
    text-align: left;
    transition: background 0.15s ease, color 0.15s ease;
}
.admin-dropdown-item:hover {
    background: var(--bg-surface-2);
    color: var(--text-primary);
}
.admin-dropdown-item.text-critical:hover {
    background: rgba(239, 68, 68, 0.1);
    color: var(--color-critical);
}
.admin-dropdown-divider {
    height: 1px;
    background: var(--border-subtle);
    margin: 4px 0;
}

/* ── Collapsible Row Detail Drawer ────────────────────────────── */
.admin-row-drawer td {
    padding: 0 !important;
    background: var(--bg-surface-2) !important;
    border-bottom: 1px solid var(--border-subtle);
}
.admin-drawer-content {
    padding: 14px 20px;
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
    gap: 16px;
}
.admin-drawer-card {
    background: var(--bg-surface-1);
    border: 1px solid var(--border-subtle);
    border-radius: var(--radius-md);
    padding: 12px 14px;
}
.admin-drawer-title {
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    color: var(--text-muted);
    margin-bottom: 8px;
    display: flex;
    align-items: center;
    gap: 6px;
}
</style>

<!-- ── Page Header ──────────────────────────────────────────── -->
<div class="page-header">
    <div>
        <h1 class="page-title"><i class="bi bi-shield-lock-fill text-warning"></i> Administrator Management</h1>
        <p class="page-subtitle">Manage administrative accounts, role delegation, and service jurisdictions</p>
    </div>
    <div class="page-actions" style="display:flex;align-items:center;gap:8px;">
        <button class="btn btn-ghost btn-sm" id="toggleKpiBtn" onclick="AdminsManager.toggleKpiBar()" title="Toggle metrics bar">
            <i class="bi bi-bar-chart-line" id="toggleKpiIcon"></i> <span id="toggleKpiText">Stats</span>
        </button>
        <button class="btn btn-primary btn-sm" onclick="AdminsManager.openCreateModal()">
            <i class="bi bi-person-plus-fill"></i> Create Administrator
        </button>
    </div>
</div>

<!-- ── Minimalist KPI Metric Chips Bar ──────────────────────── -->
<div class="admin-kpi-bar" id="adminMetrics">
    <div class="admin-kpi-chip">
        <span class="kpi-dot" style="background:var(--brand-primary);"></span>
        <span>Total Admins:</span>
        <b id="kpi-total">—</b>
    </div>
    <div class="admin-kpi-chip">
        <span class="kpi-dot" style="background:var(--dept-ambulance);"></span>
        <span>Super Admins:</span>
        <b id="kpi-super">—</b>
    </div>
    <div class="admin-kpi-chip">
        <span class="kpi-dot" style="background:var(--brand-secondary);"></span>
        <span>Dispatchers:</span>
        <b id="kpi-dept">—</b>
    </div>
    <div class="admin-kpi-chip">
        <span class="kpi-dot" style="background:var(--color-success);"></span>
        <span>Station Admins:</span>
        <b id="kpi-station">—</b>
    </div>
    <div class="admin-kpi-chip">
        <span class="kpi-dot" style="background:var(--color-warning);"></span>
        <span>Active (24h):</span>
        <b id="kpi-active-today">—</b>
    </div>
</div>

<!-- ── Minimalist Filter & Search Card ──────────────────────── -->
<div class="card mb-6" style="overflow:visible;">
    <!-- Primary Quick Search Toolbar -->
    <div class="admin-toolbar-primary">
        <div class="admin-search-wrapper">
            <i class="bi bi-search"></i>
            <input type="text" id="adminSearchInput" placeholder="Search by name, email, phone, department, or station..." 
                   class="admin-search-input" onkeyup="AdminsManager.handleSearchKeyup(event)">
        </div>
        <button class="btn btn-surface btn-sm" id="filterToggleBtn" onclick="AdminsManager.toggleFilterTray()" title="Toggle filters">
            <i class="bi bi-funnel"></i> Filters
            <span class="badge badge-primary" id="activeFilterBadge" style="display:none;font-size:10px;padding:1px 6px;margin-left:4px;">0</span>
        </button>
        <button class="btn btn-ghost btn-sm" id="clearFiltersBtn" onclick="AdminsManager.resetFilters()" title="Clear all filters">
            <i class="bi bi-x-circle"></i> Clear
        </button>
    </div>

    <!-- Collapsible Advanced Filter Tray -->
    <div class="admin-filter-tray" id="adminFilterTray">
        <div class="filter-group">
            <span class="filter-label" style="font-size:12px;font-weight:600;color:var(--text-secondary);">Role:</span>
            <select class="filter-select" id="adminRoleFilter" onchange="AdminsManager.filter()">
                <option value="">All Roles</option>
                <option value="super_admin">👑 Super Administrator</option>
                <option value="dept_admin">🏢 Department Admin</option>
                <option value="station_admin">📍 Station Admin</option>
            </select>
        </div>
        <div class="filter-group">
            <span class="filter-label" style="font-size:12px;font-weight:600;color:var(--text-secondary);">Department:</span>
            <select class="filter-select" id="adminDeptFilter" onchange="AdminsManager.filter()">
                <option value="">All Departments</option>
                <option value="Emergency Services">Emergency Services</option>
                <option value="Municipal Services">Municipal Services</option>
                <option value="Police">Police Command</option>
                <option value="Fire">Fire & Rescue</option>
                <option value="EMS">EMS Operations</option>
                <option value="Central">Central HQ</option>
            </select>
        </div>
        <div class="filter-group">
            <span class="filter-label" style="font-size:12px;font-weight:600;color:var(--text-secondary);">Status:</span>
            <select class="filter-select" id="adminStatusFilter" onchange="AdminsManager.filter()">
                <option value="">All Statuses</option>
                <option value="1">🟢 Active</option>
                <option value="0">🔴 Suspended</option>
            </select>
        </div>
    </div>

    <!-- ── Administrator Directory Table ─────────────────────── -->
    <div class="table-wrapper" style="border:none;border-radius:0;overflow:visible;">
        <table class="table" id="adminsTable">
            <thead>
                <tr>
                    <th style="min-width:240px;">Administrator</th>
                    <th>Role</th>
                    <th>Jurisdiction / Categories</th>
                    <th>Status</th>
                    <th style="text-align:right;min-width:140px;">Actions</th>
                </tr>
            </thead>
            <tbody id="adminsTableBody">
                <tr>
                    <td colspan="5" style="text-align:center;padding:40px;">
                        <div class="spinner spinner-primary" style="margin:0 auto 12px;"></div>
                        <div class="text-muted">Loading administrator registry...</div>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</div>

<!-- ══════════════════════════════════════════════════════════════ -->
<!-- MODAL: CREATE ADMINISTRATOR                                    -->
<!-- ══════════════════════════════════════════════════════════════ -->
<div class="modal-backdrop" id="createAdminModal">
    <div class="modal modal-lg">
        <form onsubmit="AdminsManager.handleCreate(event)">
            <input type="hidden" name="<?= CSRF_TOKEN_NAME ?>" value="<?= htmlspecialchars($csrfToken) ?>">
            <div class="modal-header">
                <span class="modal-title"><i class="bi bi-shield-plus text-primary"></i> Register New Administrator</span>
                <button type="button" class="modal-close" onclick="Modal.close('createAdminModal')"><i class="bi bi-x"></i></button>
            </div>
            <div class="modal-body">
                <div class="grid grid-cols-2 gap-4 mb-3">
                    <div class="form-group">
                        <label class="form-label">Full Name <span class="text-critical">*</span></label>
                        <input type="text" id="cr-name" class="form-control" placeholder="e.g. Captain Sarah Connor" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Email Address <span class="text-critical">*</span></label>
                        <input type="email" id="cr-email" class="form-control" placeholder="dispatcher@city.gov" required>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-4 mb-3">
                    <div class="form-group">
                        <label class="form-label">Contact Phone</label>
                        <input type="text" id="cr-phone" class="form-control" placeholder="+91 98765 43210">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Role Classification <span class="text-critical">*</span></label>
                        <select id="cr-role" class="form-control" required onchange="AdminsManager.handleRoleChange('create', this.value)">
                            <option value="dept_admin">Department / Service Admin</option>
                            <option value="station_admin">Station Commander / Field Admin</option>
                            <option value="super_admin">Central Super Administrator (Full Multi-Agency Access)</option>
                        </select>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-4 mb-3">
                    <div class="form-group">
                        <label class="form-label">Department / Agency Name</label>
                        <input type="text" id="cr-dept" class="form-control" placeholder="e.g. Police Tactical Command / Emergency Services">
                    </div>
                    <div class="form-group" id="cr-station-group">
                        <label class="form-label">Assigned Station (Optional for Dept Admins)</label>
                        <select id="cr-station" class="form-control">
                            <option value="">-- No Specific Station (Department Wide) --</option>
                            <?php foreach ($stationsList as $st): ?>
                            <option value="<?= htmlspecialchars($st['station_id']) ?>" data-service="<?= htmlspecialchars($st['service_id']) ?>">
                                <?= htmlspecialchars($st['name']) ?> (<?= htmlspecialchars($st['service_label']) ?> &bull; <?= htmlspecialchars($st['city']) ?>)
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="form-group mb-3">
                    <label class="form-label">Initial Password <span class="text-critical">*</span></label>
                    <div style="display:flex;gap:8px;">
                        <input type="password" id="cr-pass" class="form-control" placeholder="Minimum 6 characters" required>
                        <button type="button" class="btn btn-surface btn-sm" onclick="AdminsManager.generatePassword('cr-pass')">
                            <i class="bi bi-key-fill"></i> Generate
                        </button>
                    </div>
                </div>

                <div class="form-group mb-2" id="cr-category-group">
                    <label class="form-label" style="display:flex;justify-content:space-between;align-items:center;">
                        <span>Permitted Service Jurisdictions & Categories</span>
                        <span class="text-xs text-muted">Select services this admin can view/manage</span>
                    </label>

                    <!-- Quick Preset Buttons -->
                    <div class="preset-btn-bar">
                        <span style="font-size:11px;font-weight:700;color:var(--text-muted);align-self:center;">Presets:</span>
                        <button type="button" class="preset-btn" onclick="AdminsManager.applyPreset('create', 'all_emergency')">🚨 All Emergency</button>
                        <button type="button" class="preset-btn" onclick="AdminsManager.applyPreset('create', 'all_municipal')">🏛️ All Municipal</button>
                        <button type="button" class="preset-btn" onclick="AdminsManager.applyPreset('create', 'police')">🚔 Police</button>
                        <button type="button" class="preset-btn" onclick="AdminsManager.applyPreset('create', 'fire')">🚒 Fire & Rescue</button>
                        <button type="button" class="preset-btn" onclick="AdminsManager.applyPreset('create', 'medical')">🚑 EMS</button>
                        <button type="button" class="preset-btn" onclick="AdminsManager.applyPreset('create', 'all')">✅ Select All</button>
                        <button type="button" class="preset-btn" onclick="AdminsManager.applyPreset('create', 'none')">❌ Clear All</button>
                    </div>

                    <div class="category-chip-grid" id="cr-cat-container">
                        <?php foreach ($dbServices as $sKey => $sVal): ?>
                        <label class="category-chip-label">
                            <input type="checkbox" name="cr_categories[]" value="<?= htmlspecialchars($sKey) ?>" class="cr-cat-box">
                            <i class="bi <?= htmlspecialchars($sVal['icon']) ?>" style="color:<?= htmlspecialchars($sVal['color']) ?>;"></i>
                            <span style="font-weight:600;"><?= htmlspecialchars($sVal['service_name']) ?></span>
                        </label>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-ghost" onclick="Modal.close('createAdminModal')">Cancel</button>
                <button type="submit" class="btn btn-primary" id="cr-submit-btn">
                    <i class="bi bi-check-circle-fill"></i> Create Administrator Account
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ══════════════════════════════════════════════════════════════ -->
<!-- MODAL: EDIT ADMINISTRATOR                                      -->
<!-- ══════════════════════════════════════════════════════════════ -->
<div class="modal-backdrop" id="editAdminModal">
    <div class="modal modal-lg">
        <form onsubmit="AdminsManager.handleUpdate(event)">
            <input type="hidden" name="<?= CSRF_TOKEN_NAME ?>" value="<?= htmlspecialchars($csrfToken) ?>">
            <input type="hidden" id="ed-id">
            <div class="modal-header">
                <span class="modal-title"><i class="bi bi-pencil-square text-warning"></i> Edit Administrator Account</span>
                <button type="button" class="modal-close" onclick="Modal.close('editAdminModal')"><i class="bi bi-x"></i></button>
            </div>
            <div class="modal-body">
                <div class="grid grid-cols-2 gap-4 mb-3">
                    <div class="form-group">
                        <label class="form-label">Full Name <span class="text-critical">*</span></label>
                        <input type="text" id="ed-name" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Email Address (Read Only)</label>
                        <input type="email" id="ed-email" class="form-control" readonly style="background:var(--bg-surface-2);opacity:0.8;">
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-4 mb-3">
                    <div class="form-group">
                        <label class="form-label">Contact Phone</label>
                        <input type="text" id="ed-phone" class="form-control">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Role Classification <span class="text-critical">*</span></label>
                        <select id="ed-role" class="form-control" required onchange="AdminsManager.handleRoleChange('edit', this.value)">
                            <option value="dept_admin">Department / Service Admin</option>
                            <option value="station_admin">Station Commander / Field Admin</option>
                            <option value="super_admin">Central Super Administrator (Full Multi-Agency Access)</option>
                        </select>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-4 mb-3">
                    <div class="form-group">
                        <label class="form-label">Department / Agency Name</label>
                        <input type="text" id="ed-dept" class="form-control">
                    </div>
                    <div class="form-group" id="ed-station-group">
                        <label class="form-label">Assigned Station</label>
                        <select id="ed-station" class="form-control">
                            <option value="">-- No Specific Station (Department Wide) --</option>
                            <?php foreach ($stationsList as $st): ?>
                            <option value="<?= htmlspecialchars($st['station_id']) ?>" data-service="<?= htmlspecialchars($st['service_id']) ?>">
                                <?= htmlspecialchars($st['name']) ?> (<?= htmlspecialchars($st['service_label']) ?> &bull; <?= htmlspecialchars($st['city']) ?>)
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-4 mb-3">
                    <div class="form-group">
                        <label class="form-label">Account Status</label>
                        <select id="ed-status" class="form-control">
                            <option value="1">🟢 Active / Enabled</option>
                            <option value="0">🔴 Suspended / Disabled</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">New Password (Leave blank to keep unchanged)</label>
                        <div style="display:flex;gap:8px;">
                            <input type="password" id="ed-pass" class="form-control" placeholder="••••••••">
                            <button type="button" class="btn btn-surface btn-sm" onclick="AdminsManager.generatePassword('ed-pass')">
                                <i class="bi bi-key-fill"></i>
                            </button>
                        </div>
                    </div>
                </div>

                <div class="form-group mb-2" id="ed-category-group">
                    <label class="form-label" style="display:flex;justify-content:space-between;align-items:center;">
                        <span>Permitted Service Jurisdictions & Categories</span>
                        <span class="text-xs text-muted">Select services this admin can view/manage</span>
                    </label>

                    <!-- Quick Preset Buttons -->
                    <div class="preset-btn-bar">
                        <span style="font-size:11px;font-weight:700;color:var(--text-muted);align-self:center;">Presets:</span>
                        <button type="button" class="preset-btn" onclick="AdminsManager.applyPreset('edit', 'all_emergency')">🚨 All Emergency</button>
                        <button type="button" class="preset-btn" onclick="AdminsManager.applyPreset('edit', 'all_municipal')">🏛️ All Municipal</button>
                        <button type="button" class="preset-btn" onclick="AdminsManager.applyPreset('edit', 'police')">🚔 Police</button>
                        <button type="button" class="preset-btn" onclick="AdminsManager.applyPreset('edit', 'fire')">🚒 Fire & Rescue</button>
                        <button type="button" class="preset-btn" onclick="AdminsManager.applyPreset('edit', 'medical')">🚑 EMS</button>
                        <button type="button" class="preset-btn" onclick="AdminsManager.applyPreset('edit', 'all')">✅ Select All</button>
                        <button type="button" class="preset-btn" onclick="AdminsManager.applyPreset('edit', 'none')">❌ Clear All</button>
                    </div>

                    <div class="category-chip-grid" id="ed-cat-container">
                        <?php foreach ($dbServices as $sKey => $sVal): ?>
                        <label class="category-chip-label">
                            <input type="checkbox" name="ed_categories[]" value="<?= htmlspecialchars($sKey) ?>" class="ed-cat-box">
                            <i class="bi <?= htmlspecialchars($sVal['icon']) ?>" style="color:<?= htmlspecialchars($sVal['color']) ?>;"></i>
                            <span style="font-weight:600;"><?= htmlspecialchars($sVal['service_name']) ?></span>
                        </label>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-ghost" onclick="Modal.close('editAdminModal')">Cancel</button>
                <button type="submit" class="btn btn-primary" id="ed-submit-btn">
                    <i class="bi bi-save-fill"></i> Save Changes
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ══════════════════════════════════════════════════════════════ -->
<!-- MODAL: RESET PASSWORD                                          -->
<!-- ══════════════════════════════════════════════════════════════ -->
<div class="modal-backdrop" id="resetPassModal">
    <div class="modal">
        <form onsubmit="AdminsManager.handleResetPassword(event)">
            <input type="hidden" id="rp-id">
            <div class="modal-header">
                <span class="modal-title"><i class="bi bi-key-fill text-warning"></i> Reset Administrator Password</span>
                <button type="button" class="modal-close" onclick="Modal.close('resetPassModal')"><i class="bi bi-x"></i></button>
            </div>
            <div class="modal-body">
                <div class="mb-3 p-3 surface-2 rounded">
                    <div style="font-size:13px;color:var(--text-muted);">Resetting password for:</div>
                    <div style="font-weight:800;font-size:15px;" id="rp-name-display">—</div>
                    <div class="text-xs text-muted" id="rp-email-display">—</div>
                </div>

                <div class="form-group mb-3">
                    <label class="form-label">New Password <span class="text-critical">*</span></label>
                    <div style="display:flex;gap:8px;">
                        <input type="text" id="rp-pass" class="form-control" placeholder="Enter new secure password" required minlength="6">
                        <button type="button" class="btn btn-surface btn-sm" onclick="AdminsManager.generatePassword('rp-pass')">
                            <i class="bi bi-shuffle"></i> Generate
                        </button>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-ghost" onclick="Modal.close('resetPassModal')">Cancel</button>
                <button type="submit" class="btn btn-warning">Confirm Password Reset</button>
            </div>
        </form>
    </div>
</div>

<!-- ══════════════════════════════════════════════════════════════ -->
<!-- MODAL: AUDIT TRAIL                                             -->
<!-- ══════════════════════════════════════════════════════════════ -->
<div class="modal-backdrop" id="auditTrailModal">
    <div class="modal modal-lg">
        <div class="modal-header">
            <span class="modal-title"><i class="bi bi-journal-text text-info"></i> Administrator Audit Trail</span>
            <button type="button" class="modal-close" onclick="Modal.close('auditTrailModal')"><i class="bi bi-x"></i></button>
        </div>
        <div class="modal-body" style="max-height:60vh;overflow-y:auto;padding:0;">
            <div class="p-4 surface-2" style="border-bottom:1px solid var(--border-subtle);display:flex;justify-content:space-between;align-items:center;">
                <div>
                    <strong id="at-admin-name" style="font-size:15px;">—</strong>
                    <div class="text-xs text-muted" id="at-admin-email">—</div>
                </div>
                <span class="badge badge-info" id="at-admin-role">—</span>
            </div>
            <div id="auditLogContainer">
                <div class="p-6 text-center text-muted">Loading audit records...</div>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-ghost" onclick="Modal.close('auditTrailModal')">Close</button>
        </div>
    </div>
</div>

<!-- ══════════════════════════════════════════════════════════════ -->
<!-- MODAL: DELETE CONFIRMATION                                     -->
<!-- ══════════════════════════════════════════════════════════════ -->
<div class="modal-backdrop" id="deleteAdminModal">
    <div class="modal">
        <div class="modal-header">
            <span class="modal-title text-critical"><i class="bi bi-exclamation-triangle-fill"></i> Delete Administrator</span>
            <button type="button" class="modal-close" onclick="Modal.close('deleteAdminModal')"><i class="bi bi-x"></i></button>
        </div>
        <div class="modal-body">
            <p>Are you sure you want to permanently delete the administrator account for <strong id="del-admin-name">—</strong> (<code id="del-admin-email" class="mono"></code>)?</p>
            <div class="alert alert-critical mt-3 text-xs">
                <i class="bi bi-shield-exclamation me-1"></i> This action cannot be undone. All access privileges for this officer will be permanently revoked immediately.
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-ghost" onclick="Modal.close('deleteAdminModal')">Cancel</button>
            <button type="button" class="btn btn-critical" id="del-confirm-btn" onclick="AdminsManager.confirmDelete()">
                <i class="bi bi-trash3-fill"></i> Delete Account
            </button>
        </div>
    </div>
</div>

<script>
const currentUserId = <?= $currentAdmId ?>;

const CATEGORY_MAP = <?= json_encode(array_map(fn($s) => [
    'name'  => $s['service_name'],
    'icon'  => $s['icon'],
    'color' => $s['color'],
    'cat'   => $s['category']
], $dbServices)) ?>;

const PRESETS = {
    'all_emergency': ['fire', 'fire_bus', 'ambulance', 'rescue', 'police', 'tracker', 'emergency'],
    'all_municipal': ['road_management', 'water_management', 'waste_management', 'sewage_management', 'clean_management', 'citizen_issue'],
    'police': ['police'],
    'fire': ['fire', 'fire_bus', 'rescue'],
    'medical': ['ambulance']
};

const AdminsManager = {
    allAdmins: [],
    deleteTargetId: null,
    searchDebounceTimer: null,
    activeMenuId: null,
    openDrawerIds: new Set(),

    async init() {
        this.initKpiBar();
        this.initClickOutsideListener();
        await this.load();
    },

    initKpiBar() {
        const saved = localStorage.getItem('admin_kpi_visible');
        const kpiBar = document.getElementById('adminMetrics');
        const icon = document.getElementById('toggleKpiIcon');
        const text = document.getElementById('toggleKpiText');
        if (saved === 'false' && kpiBar) {
            kpiBar.classList.add('collapsed');
            if (icon) icon.className = 'bi bi-bar-chart';
            if (text) text.textContent = 'Show Stats';
        } else if (text) {
            text.textContent = 'Hide Stats';
        }
    },

    toggleKpiBar() {
        const kpiBar = document.getElementById('adminMetrics');
        const icon = document.getElementById('toggleKpiIcon');
        const text = document.getElementById('toggleKpiText');
        if (!kpiBar) return;
        const isCurrentlyCollapsed = kpiBar.classList.contains('collapsed');
        if (isCurrentlyCollapsed) {
            kpiBar.classList.remove('collapsed');
            localStorage.setItem('admin_kpi_visible', 'true');
            if (icon) icon.className = 'bi bi-bar-chart-line';
            if (text) text.textContent = 'Hide Stats';
        } else {
            kpiBar.classList.add('collapsed');
            localStorage.setItem('admin_kpi_visible', 'false');
            if (icon) icon.className = 'bi bi-bar-chart';
            if (text) text.textContent = 'Show Stats';
        }
    },

    toggleFilterTray() {
        const tray = document.getElementById('adminFilterTray');
        const btn = document.getElementById('filterToggleBtn');
        if (!tray) return;
        const isOpen = tray.classList.toggle('expanded');
        if (btn) {
            if (isOpen) {
                btn.classList.add('btn-primary');
                btn.classList.remove('btn-surface');
            } else {
                btn.classList.remove('btn-primary');
                btn.classList.add('btn-surface');
            }
        }
    },

    handleSearchKeyup(e) {
        clearTimeout(this.searchDebounceTimer);
        this.searchDebounceTimer = setTimeout(() => {
            this.filter();
        }, 180);
    },

    initClickOutsideListener() {
        document.addEventListener('click', (e) => {
            if (!e.target.closest('.admin-action-cell')) {
                this.closeAllActionMenus();
            }
        });
    },

    closeAllActionMenus() {
        document.querySelectorAll('.admin-dropdown-menu.show').forEach(el => el.classList.remove('show'));
        this.activeMenuId = null;
    },

    toggleActionMenu(adminId, e) {
        if (e) e.stopPropagation();
        const menu = document.getElementById(`action-menu-${adminId}`);
        if (!menu) return;
        const isShowing = menu.classList.contains('show');
        this.closeAllActionMenus();
        if (!isShowing) {
            menu.classList.add('show');
            this.activeMenuId = adminId;
        }
    },

    toggleRowDrawer(adminId) {
        const drawer = document.getElementById(`drawer-row-${adminId}`);
        const icon = document.getElementById(`drawer-icon-${adminId}`);
        if (!drawer) return;
        if (drawer.style.display === 'none' || !drawer.style.display) {
            drawer.style.display = 'table-row';
            this.openDrawerIds.add(adminId);
            if (icon) icon.className = 'bi bi-chevron-up';
        } else {
            drawer.style.display = 'none';
            this.openDrawerIds.delete(adminId);
            if (icon) icon.className = 'bi bi-chevron-down';
        }
    },

    async load() {
        try {
            const res = await API.get('admins_api.php', { action: 'list' });
            if (res?.success) {
                this.allAdmins = res.admins || [];
                this.renderStats(res.stats || {});
                this.renderTable(this.allAdmins);
            } else {
                Toast.error(res?.message || 'Error loading administrators');
            }
        } catch(e) {
            Toast.error('Network error loading administrators');
        }
    },

    renderStats(stats) {
        const elTotal = document.getElementById('kpi-total');
        const elSuper = document.getElementById('kpi-super');
        const elDept = document.getElementById('kpi-dept');
        const elStation = document.getElementById('kpi-station');
        const elActive = document.getElementById('kpi-active-today');
        if (elTotal) elTotal.textContent = stats.total_admins || 0;
        if (elSuper) elSuper.textContent = stats.super_admins || 0;
        if (elDept) elDept.textContent = stats.dept_admins || 0;
        if (elStation) elStation.textContent = stats.station_admins || 0;
        if (elActive) elActive.textContent = stats.active_today || 0;
    },

    renderTable(admins) {
        const tbody = document.getElementById('adminsTableBody');
        this.closeAllActionMenus();
        if (!admins || admins.length === 0) {
            tbody.innerHTML = `
                <tr>
                    <td colspan="5">
                        <div class="empty-state" style="padding:40px 20px;">
                            <div class="empty-state-icon">🛡️</div>
                            <div class="empty-state-title">No Administrators Found</div>
                            <div class="empty-state-desc">Try clearing your filters or create a new administrator account.</div>
                        </div>
                    </td>
                </tr>
            `;
            return;
        }

        tbody.innerHTML = admins.map(a => {
            const initials = a.name.split(' ').map(n => n[0]).join('').substring(0,2).toUpperCase();
            const isMe = (parseInt(a.id) === currentUserId);
            
            // Gradient based on role
            let gradient = 'linear-gradient(135deg, #4338CA 0%, #6366F1 100%)';
            let roleBadgeClass = 'badge-primary';
            let roleLabel = 'Super Admin';
            if (a.role === 'dept_admin') {
                gradient = 'linear-gradient(135deg, #1D4ED8 0%, #3B82F6 100%)';
                roleBadgeClass = 'badge-info';
                roleLabel = 'Department Admin';
            } else if (a.role === 'station_admin') {
                gradient = 'linear-gradient(135deg, #047857 0%, #10B981 100%)';
                roleBadgeClass = 'badge-success';
                roleLabel = 'Station Commander';
            }

            // Categories HTML
            let catHtml = '';
            if (a.role === 'super_admin') {
                catHtml = '<span class="badge badge-success" style="font-size:10px;"><i class="bi bi-globe me-1"></i> FULL MULTI-AGENCY ACCESS</span>';
            } else if (a.categories_arr && a.categories_arr.length > 0) {
                const chips = a.categories_arr.slice(0, 2).map(ck => {
                    const meta = CATEGORY_MAP[ck] || { name: ck, icon: 'bi-tag', color: '#64748B' };
                    return `<span class="badge badge-neutral" style="font-size:10px;padding:2px 6px;">
                        <i class="bi ${meta.icon}" style="color:${meta.color};"></i> ${meta.name}
                    </span>`;
                }).join(' ');
                const extra = a.categories_arr.length > 2 ? `<span class="badge badge-neutral" style="font-size:9px;">+${a.categories_arr.length - 2}</span>` : '';
                catHtml = `<div style="display:flex;flex-wrap:wrap;gap:3px;align-items:center;">${chips} ${extra}</div>`;
            } else {
                catHtml = '<span class="text-xs text-muted">None Assigned</span>';
            }

            // Station info
            let stationDisplay = 'No station assigned';
            if (a.station_id) {
                stationDisplay = `${escapeHtml(a.station_id)} ${a.station_name ? '— ' + escapeHtml(a.station_name) : ''}`;
            }

            // Last active
            const lastActiveStr = a.last_login ? formatDate(a.last_login) : 'Never logged in';
            const isDrawerOpen = this.openDrawerIds.has(a.id);

            return `
                <tr id="admin-row-${a.id}">
                    <!-- Combined Administrator + Email Column -->
                    <td>
                        <div style="display:flex;align-items:center;gap:10px;">
                            <div class="admin-avatar" style="background:${gradient};">${initials}</div>
                            <div>
                                <div style="font-weight:700;display:flex;align-items:center;gap:6px;">
                                    ${escapeHtml(a.name)}
                                    ${isMe ? '<span class="badge badge-primary" style="font-size:9px;padding:1px 5px;">YOU</span>' : ''}
                                </div>
                                <div style="font-size:12px;color:var(--text-secondary);font-family:monospace;margin-top:2px;">
                                    ${escapeHtml(a.email)}
                                </div>
                                <div class="text-xs text-muted" style="margin-top:1px;">
                                    <i class="bi bi-telephone me-1"></i>${escapeHtml(a.phone || 'No phone')}
                                </div>
                            </div>
                        </div>
                    </td>

                    <!-- Role Column -->
                    <td>
                        <span class="badge ${roleBadgeClass}" style="font-size:11px;">${roleLabel}</span>
                    </td>

                    <!-- Jurisdiction / Department Column -->
                    <td>
                        <div style="font-weight:600;font-size:12px;margin-bottom:3px;color:var(--text-secondary);">${escapeHtml(a.department || 'Central Operations')}</div>
                        ${catHtml}
                    </td>

                    <!-- Status Column -->
                    <td>
                        <button class="badge badge-${a.is_active == 1 ? 'success' : 'critical'}" 
                                style="cursor:${isMe ? 'default' : 'pointer'};border:none;"
                                onclick="${isMe ? '' : `AdminsManager.toggleStatus(${a.id})`}"
                                title="${isMe ? 'Your own account' : 'Click to toggle status'}">
                            <span class="dot dot-${a.is_active == 1 ? 'success' : 'critical'} me-1"></span>
                            ${a.is_active == 1 ? 'Active' : 'Suspended'}
                        </button>
                    </td>

                    <!-- Actions Column: Edit, Drawer Toggle, Context Menu (•••) -->
                    <td style="text-align:right;">
                        <div class="admin-action-cell">
                            <button class="btn btn-surface btn-xs" onclick="AdminsManager.openEditModal(${a.id})" title="Edit Administrator">
                                <i class="bi bi-pencil-fill"></i> Edit
                            </button>
                            <button class="btn btn-ghost btn-xs" onclick="AdminsManager.toggleRowDrawer(${a.id})" title="View Details">
                                <i class="bi ${isDrawerOpen ? 'bi-chevron-up' : 'bi-chevron-down'}" id="drawer-icon-${a.id}"></i>
                            </button>
                            <button class="btn btn-ghost btn-xs" onclick="AdminsManager.toggleActionMenu(${a.id}, event)" title="More options">
                                <i class="bi bi-three-dots"></i>
                            </button>

                            <!-- Contextual Dropdown Menu -->
                            <div class="admin-dropdown-menu" id="action-menu-${a.id}">
                                <button class="admin-dropdown-item" onclick="AdminsManager.openResetPasswordModal(${a.id}, '${escapeHtml(a.name)}', '${escapeHtml(a.email)}')">
                                    <i class="bi bi-key-fill text-warning"></i> Reset Password
                                </button>
                                <button class="admin-dropdown-item" onclick="AdminsManager.openEditModal(${a.id})">
                                    <i class="bi bi-shield-check text-primary"></i> Edit Jurisdictions
                                </button>
                                <button class="admin-dropdown-item" onclick="AdminsManager.openAuditTrail(${a.id}, '${escapeHtml(a.name)}', '${escapeHtml(a.email)}', '${roleLabel}')">
                                    <i class="bi bi-journal-text"></i> View Audit Trail
                                </button>
                                ${!isMe ? `
                                <div class="admin-dropdown-divider"></div>
                                <button class="admin-dropdown-item" onclick="AdminsManager.toggleStatus(${a.id})">
                                    <i class="bi ${a.is_active == 1 ? 'bi-slash-circle text-warning' : 'bi-check-circle text-success'}"></i> 
                                    ${a.is_active == 1 ? 'Suspend Account' : 'Activate Account'}
                                </button>
                                <button class="admin-dropdown-item text-critical" onclick="AdminsManager.openDeleteModal(${a.id}, '${escapeHtml(a.name)}', '${escapeHtml(a.email)}')">
                                    <i class="bi bi-trash3-fill"></i> Delete Account
                                </button>
                                ` : ''}
                            </div>
                        </div>
                    </td>
                </tr>

                <!-- Collapsible Row Detail Drawer -->
                <tr id="drawer-row-${a.id}" class="admin-row-drawer" style="display:${isDrawerOpen ? 'table-row' : 'none'};">
                    <td colspan="5">
                        <div class="admin-drawer-content">
                            <div class="admin-drawer-card">
                                <div class="admin-drawer-title"><i class="bi bi-geo-alt-fill text-primary"></i> Station Assignment</div>
                                <div style="font-size:13px;font-weight:600;margin-bottom:2px;">${stationDisplay}</div>
                                <div class="text-xs text-muted">${a.department ? escapeHtml(a.department) : 'No specific division'}</div>
                            </div>
                            <div class="admin-drawer-card">
                                <div class="admin-drawer-title"><i class="bi bi-clock-history text-warning"></i> Login & Activity</div>
                                <div style="font-size:13px;font-weight:600;margin-bottom:2px;">Last active: ${lastActiveStr}</div>
                                <div class="text-xs text-muted">Total recorded logins: <b>${a.login_count || 0}</b></div>
                            </div>
                        </div>
                    </td>
                </tr>
            `;
        }).join('');
    },

    filter() {
        const q = (document.getElementById('adminSearchInput').value || '').trim().toLowerCase();
        const role = document.getElementById('adminRoleFilter').value;
        const dept = (document.getElementById('adminDeptFilter').value || '').toLowerCase();
        const status = document.getElementById('adminStatusFilter').value;

        // Calculate active filters count
        let activeCount = 0;
        if (role) activeCount++;
        if (dept) activeCount++;
        if (status !== '') activeCount++;

        const badge = document.getElementById('activeFilterBadge');
        if (badge) {
            if (activeCount > 0) {
                badge.textContent = activeCount;
                badge.style.display = 'inline-block';
            } else {
                badge.style.display = 'none';
            }
        }

        const filtered = this.allAdmins.filter(a => {
            if (role && a.role !== role) return false;
            if (status !== '' && String(a.is_active) !== status) return false;
            if (dept) {
                const d = (a.department || '').toLowerCase();
                const cats = (a.categories || '').toLowerCase();
                if (!d.includes(dept) && !cats.includes(dept)) return false;
            }
            if (q) {
                const hay = `${a.name} ${a.email} ${a.phone} ${a.department} ${a.station_id} ${a.station_name}`.toLowerCase();
                if (!hay.includes(q)) return false;
            }
            return true;
        });

        this.renderTable(filtered);
    },

    resetFilters() {
        document.getElementById('adminSearchInput').value = '';
        document.getElementById('adminRoleFilter').value = '';
        document.getElementById('adminDeptFilter').value = '';
        document.getElementById('adminStatusFilter').value = '';

        const badge = document.getElementById('activeFilterBadge');
        if (badge) badge.style.display = 'none';

        const tray = document.getElementById('adminFilterTray');
        const btn = document.getElementById('filterToggleBtn');
        if (tray) tray.classList.remove('expanded');
        if (btn) {
            btn.classList.remove('btn-primary');
            btn.classList.add('btn-surface');
        }

        this.renderTable(this.allAdmins);
    },

    // ── CREATE ADMIN ──────────────────────────────────────────
    openCreateModal() {
        document.getElementById('cr-name').value = '';
        document.getElementById('cr-email').value = '';
        document.getElementById('cr-phone').value = '';
        document.getElementById('cr-role').value = 'dept_admin';
        document.getElementById('cr-dept').value = '';
        document.getElementById('cr-station').value = '';
        document.getElementById('cr-pass').value = '';
        this.applyPreset('create', 'none');
        this.handleRoleChange('create', 'dept_admin');
        Modal.open('createAdminModal');
    },

    async handleCreate(e) {
        e.preventDefault();
        const name = document.getElementById('cr-name').value.trim();
        const email = document.getElementById('cr-email').value.trim();
        const phone = document.getElementById('cr-phone').value.trim();
        const role = document.getElementById('cr-role').value;
        const department = document.getElementById('cr-dept').value.trim();
        const station_id = document.getElementById('cr-station').value.trim();
        const password = document.getElementById('cr-pass').value.trim();

        const categories = [];
        if (role !== 'super_admin') {
            document.querySelectorAll('#cr-cat-container .cr-cat-box:checked').forEach(cb => {
                categories.push(cb.value);
            });
        }

        const btn = document.getElementById('cr-submit-btn');
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner spinner-sm"></span> Creating...';

        try {
            const res = await API.post('admins_api.php?action=create', {
                name, email, phone, role, department, station_id, password, categories
            });
            if (res?.success) {
                Toast.success(res.message || 'Admin created successfully');
                Modal.close('createAdminModal');
                await this.load();
            } else {
                Toast.error(res?.message || 'Failed to create administrator');
            }
        } catch(err) {
            Toast.error('Network error during administrator creation');
        } finally {
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-check-circle-fill"></i> Create Administrator Account';
        }
    },

    // ── EDIT ADMIN ────────────────────────────────────────────
    async openEditModal(id) {
        try {
            const res = await API.get('admins_api.php', { action: 'get', id });
            if (!res?.success || !res.admin) {
                Toast.error(res?.message || 'Error fetching administrator details');
                return;
            }
            const a = res.admin;
            document.getElementById('ed-id').value = a.id;
            document.getElementById('ed-name').value = a.name;
            document.getElementById('ed-email').value = a.email;
            document.getElementById('ed-phone').value = a.phone || '';
            document.getElementById('ed-role').value = a.role;
            document.getElementById('ed-dept').value = a.department || '';
            document.getElementById('ed-station').value = a.station_id || '';
            document.getElementById('ed-status').value = a.is_active;
            document.getElementById('ed-pass').value = '';

            // Check checkboxes
            const cats = a.categories_arr || [];
            document.querySelectorAll('#ed-cat-container .ed-cat-box').forEach(cb => {
                cb.checked = cats.includes(cb.value);
            });

            this.handleRoleChange('edit', a.role);
            Modal.open('editAdminModal');
        } catch(e) {
            Toast.error('Network error fetching admin details');
        }
    },

    async handleUpdate(e) {
        e.preventDefault();
        const id = parseInt(document.getElementById('ed-id').value);
        const name = document.getElementById('ed-name').value.trim();
        const phone = document.getElementById('ed-phone').value.trim();
        const role = document.getElementById('ed-role').value;
        const department = document.getElementById('ed-dept').value.trim();
        const station_id = document.getElementById('ed-station').value.trim();
        const is_active = parseInt(document.getElementById('ed-status').value);
        const password = document.getElementById('ed-pass').value.trim();

        const categories = [];
        if (role !== 'super_admin') {
            document.querySelectorAll('#ed-cat-container .ed-cat-box:checked').forEach(cb => {
                categories.push(cb.value);
            });
        }

        const btn = document.getElementById('ed-submit-btn');
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner spinner-sm"></span> Saving...';

        try {
            const res = await API.post('admins_api.php?action=update', {
                id, name, phone, role, department, station_id, is_active, password, categories
            });
            if (res?.success) {
                Toast.success(res.message || 'Admin updated successfully');
                Modal.close('editAdminModal');
                await this.load();
            } else {
                Toast.error(res?.message || 'Failed to update administrator');
            }
        } catch(err) {
            Toast.error('Network error during administrator update');
        } finally {
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-save-fill"></i> Save Changes';
        }
    },

    // ── RESET PASSWORD ────────────────────────────────────────
    openResetPasswordModal(id, name, email) {
        document.getElementById('rp-id').value = id;
        document.getElementById('rp-name-display').textContent = name;
        document.getElementById('rp-email-display').textContent = email;
        document.getElementById('rp-pass').value = '';
        Modal.open('resetPassModal');
    },

    async handleResetPassword(e) {
        e.preventDefault();
        const id = parseInt(document.getElementById('rp-id').value);
        const password = document.getElementById('rp-pass').value.trim();

        try {
            const res = await API.post('admins_api.php?action=reset_password', { id, password });
            if (res?.success) {
                Toast.success(res.message || 'Password reset successfully');
                Modal.close('resetPassModal');
            } else {
                Toast.error(res?.message || 'Failed to reset password');
            }
        } catch(err) {
            Toast.error('Network error resetting password');
        }
    },

    // ── TOGGLE STATUS ─────────────────────────────────────────
    async toggleStatus(id) {
        try {
            const res = await API.post('admins_api.php?action=toggle', { id });
            if (res?.success) {
                Toast.success(res.message || 'Administrator status updated');
                await this.load();
            } else {
                Toast.error(res?.message || 'Error updating status');
            }
        } catch(err) {
            Toast.error('Network error updating status');
        }
    },

    // ── DELETE ADMIN ──────────────────────────────────────────
    openDeleteModal(id, name, email) {
        this.deleteTargetId = id;
        document.getElementById('del-admin-name').textContent = name;
        document.getElementById('del-admin-email').textContent = email;
        Modal.open('deleteAdminModal');
    },

    async confirmDelete() {
        if (!this.deleteTargetId) return;
        const btn = document.getElementById('del-confirm-btn');
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner spinner-sm"></span> Deleting...';

        try {
            const res = await API.post('admins_api.php?action=delete', { id: this.deleteTargetId });
            if (res?.success) {
                Toast.success(res.message || 'Administrator deleted');
                Modal.close('deleteAdminModal');
                await this.load();
            } else {
                Toast.error(res?.message || 'Failed to delete administrator');
            }
        } catch(err) {
            Toast.error('Network error during deletion');
        } finally {
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-trash3-fill"></i> Delete Account';
            this.deleteTargetId = null;
        }
    },

    // ── AUDIT TRAIL ───────────────────────────────────────────
    async openAuditTrail(id, name, email, role) {
        document.getElementById('at-admin-name').textContent = name;
        document.getElementById('at-admin-email').textContent = email;
        document.getElementById('at-admin-role').textContent = role;
        
        const container = document.getElementById('auditLogContainer');
        container.innerHTML = '<div class="p-6 text-center text-muted"><span class="spinner spinner-primary me-2"></span> Loading audit records...</div>';
        Modal.open('auditTrailModal');

        try {
            const res = await API.get('admins_api.php', { action: 'admin_logs', id });
            if (res?.success) {
                const logs = res.logs || [];
                if (logs.length === 0) {
                    container.innerHTML = '<div class="p-8 text-center text-muted"><div style="font-size:28px;margin-bottom:8px;">📜</div>No audit logs recorded for this administrator yet.</div>';
                    return;
                }

                container.innerHTML = logs.map(l => `
                    <div class="audit-log-item">
                        <div style="width:32px;height:32px;border-radius:var(--radius-md);background:rgba(99,102,241,0.1);color:var(--brand-primary);display:flex;align-items:center;justify-content:center;font-size:14px;flex-shrink:0;">
                            <i class="bi bi-activity"></i>
                        </div>
                        <div style="flex:1;">
                            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:2px;">
                                <span style="font-weight:700;font-size:13px;color:var(--text-primary);">${escapeHtml(l.action)}</span>
                                <span class="text-xs text-muted">${formatDate(l.created_at)}</span>
                            </div>
                            <div class="text-xs text-muted mb-1">
                                Entity: <code class="mono">${escapeHtml(l.entity_type)}</code> ${l.target_id ? `&bull; Target: <code class="mono">#${escapeHtml(l.target_id)}</code>` : ''}
                            </div>
                            ${l.details ? `<div class="p-2 surface-1 rounded text-xs mono" style="word-break:break-all;">${escapeHtml(l.details)}</div>` : ''}
                        </div>
                    </div>
                `).join('');
            } else {
                container.innerHTML = `<div class="p-6 text-center text-critical">${escapeHtml(res?.message || 'Error fetching audit logs')}</div>`;
            }
        } catch(e) {
            container.innerHTML = '<div class="p-6 text-center text-critical">Network error fetching audit logs</div>';
        }
    },

    // ── HELPERS ───────────────────────────────────────────────
    handleRoleChange(mode, role) {
        const catGroup = document.getElementById(`${mode === 'create' ? 'cr' : 'ed'}-category-group`);
        const stGroup  = document.getElementById(`${mode === 'create' ? 'cr' : 'ed'}-station-group`);
        
        if (role === 'super_admin') {
            catGroup.style.display = 'none';
            stGroup.style.display = 'none';
        } else {
            catGroup.style.display = 'block';
            stGroup.style.display = 'block';
        }
    },

    applyPreset(mode, presetKey) {
        const prefix = (mode === 'create') ? 'cr' : 'ed';
        const boxes = document.querySelectorAll(`#${prefix}-cat-container .${prefix}-cat-box`);

        if (presetKey === 'all') {
            boxes.forEach(b => b.checked = true);
        } else if (presetKey === 'none') {
            boxes.forEach(b => b.checked = false);
        } else if (PRESETS[presetKey]) {
            const list = PRESETS[presetKey];
            boxes.forEach(b => b.checked = list.includes(b.value));
        }
    },

    generatePassword(targetInputId) {
        const chars = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789!@#$%&*';
        let pass = '';
        for (let i = 0; i < 10; i++) {
            pass += chars.charAt(Math.floor(Math.random() * chars.length));
        }
        const input = document.getElementById(targetInputId);
        input.value = pass;
        input.type = 'text'; // Show generated password temporarily
        Toast.info('Strong password generated');
    }
};

function escapeHtml(str) {
    if (!str) return '';
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

function formatDate(dateStr) {
    if (!dateStr) return '';
    const d = new Date(dateStr.replace(' ', 'T'));
    if (isNaN(d)) return dateStr;
    return d.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric', hour: '2-digit', minute: '2-digit' });
}

document.addEventListener('DOMContentLoaded', () => AdminsManager.init());
</script>

<?php require_once __DIR__ . '/includes/layout_end.php'; ?>
