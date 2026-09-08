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
</style>

<!-- ── Page Header ──────────────────────────────────────────── -->
<div class="page-header">
    <div>
        <div class="page-back-wrapper">
            <a href="<?= BASE_URL ?>dashboard.php" class="btn-back">
                <i class="bi bi-arrow-left"></i> Dashboard
            </a>
        </div>
        <h1 class="page-title"><i class="bi bi-shield-lock-fill text-warning"></i> Administrator Management</h1>
        <p class="page-subtitle">Manage administrative accounts, role delegation, and service jurisdictions</p>
    </div>
    <div class="page-actions">
        <button class="btn btn-primary btn-sm" onclick="AdminsManager.openCreateModal()">
            <i class="bi bi-person-plus-fill"></i> Create Administrator
        </button>
    </div>
</div>

<!-- ── KPI Metric Cards ─────────────────────────────────────── -->
<div class="grid grid-cols-5 gap-4 mb-6" id="adminMetrics">
    <div class="stat-card" style="--card-accent: #6366F1;">
        <div class="stat-card-header">
            <span class="stat-label">Total Admins</span>
            <div class="stat-icon" style="background: rgba(99,102,241,0.12); color: #6366F1;"><i class="bi bi-people-fill"></i></div>
        </div>
        <div class="stat-value" id="kpi-total">—</div>
    </div>
    <div class="stat-card" style="--card-accent: #EC4899;">
        <div class="stat-card-header">
            <span class="stat-label">Super Admins</span>
            <div class="stat-icon" style="background: rgba(236,72,153,0.12); color: #EC4899;"><i class="bi bi-shield-fill-check"></i></div>
        </div>
        <div class="stat-value" id="kpi-super">—</div>
    </div>
    <div class="stat-card" style="--card-accent: #3B82F6;">
        <div class="stat-card-header">
            <span class="stat-label">Service Dispatchers</span>
            <div class="stat-icon" style="background: rgba(59,130,246,0.12); color: #3B82F6;"><i class="bi bi-building-fill-gear"></i></div>
        </div>
        <div class="stat-value" id="kpi-dept">—</div>
    </div>
    <div class="stat-card" style="--card-accent: #10B981;">
        <div class="stat-card-header">
            <span class="stat-label">Station Admins</span>
            <div class="stat-icon" style="background: rgba(16,185,129,0.12); color: #10B981;"><i class="bi bi-geo-alt-fill"></i></div>
        </div>
        <div class="stat-value" id="kpi-station">—</div>
    </div>
    <div class="stat-card" style="--card-accent: #F59E0B;">
        <div class="stat-card-header">
            <span class="stat-label">Active (24h)</span>
            <div class="stat-icon" style="background: rgba(245,158,11,0.12); color: #F59E0B;"><i class="bi bi-broadcast"></i></div>
        </div>
        <div class="stat-value" id="kpi-active-today">—</div>
    </div>
</div>

<!-- ── Filter & Search Toolbar ──────────────────────────────── -->
<div class="card mb-6">
    <div class="filter-bar">
        <div class="filter-group flex-1">
            <i class="bi bi-search" style="color:var(--text-muted);"></i>
            <input type="text" id="adminSearchInput" placeholder="Search by name, email, phone, department, or station..." 
                   class="form-control" style="padding:7px 12px;" onkeyup="AdminsManager.filter()">
        </div>
        <div class="filter-group">
            <span class="filter-label">Role:</span>
            <select class="filter-select" id="adminRoleFilter" onchange="AdminsManager.filter()">
                <option value="">All Roles</option>
                <option value="super_admin">👑 Super Administrator</option>
                <option value="dept_admin">🏢 Department Admin</option>
                <option value="station_admin">📍 Station Admin</option>
            </select>
        </div>
        <div class="filter-group">
            <span class="filter-label">Department:</span>
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
            <span class="filter-label">Status:</span>
            <select class="filter-select" id="adminStatusFilter" onchange="AdminsManager.filter()">
                <option value="">All Statuses</option>
                <option value="1">🟢 Active</option>
                <option value="0">🔴 Suspended</option>
            </select>
        </div>
        <button class="btn btn-ghost btn-sm" onclick="AdminsManager.resetFilters()">
            <i class="bi bi-x-circle"></i> Clear
        </button>
    </div>

    <!-- ── Administrator Directory Table ─────────────────────── -->
    <div class="table-wrapper" style="border:none;border-radius:0;">
        <table class="table" id="adminsTable">
            <thead>
                <tr>
                    <th>Administrator</th>
                    <th>Email Address</th>
                    <th>Role</th>
                    <th>Jurisdiction / Categories</th>
                    <th>Station</th>
                    <th>Status</th>
                    <th>Activity</th>
                    <th style="text-align:right;">Actions</th>
                </tr>
            </thead>
            <tbody id="adminsTableBody">
                <tr>
                    <td colspan="8" style="text-align:center;padding:40px;">
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

    async init() {
        await this.load();
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
        document.getElementById('kpi-total').textContent = stats.total_admins || 0;
        document.getElementById('kpi-super').textContent = stats.super_admins || 0;
        document.getElementById('kpi-dept').textContent = stats.dept_admins || 0;
        document.getElementById('kpi-station').textContent = stats.station_admins || 0;
        document.getElementById('kpi-active-today').textContent = stats.active_today || 0;
    },

    renderTable(admins) {
        const tbody = document.getElementById('adminsTableBody');
        if (!admins || admins.length === 0) {
            tbody.innerHTML = `
                <tr>
                    <td colspan="8">
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
                const chips = a.categories_arr.slice(0, 3).map(ck => {
                    const meta = CATEGORY_MAP[ck] || { name: ck, icon: 'bi-tag', color: '#64748B' };
                    return `<span class="badge badge-neutral" style="font-size:10px;padding:2px 6px;">
                        <i class="bi ${meta.icon}" style="color:${meta.color};"></i> ${meta.name}
                    </span>`;
                }).join(' ');
                const extra = a.categories_arr.length > 3 ? `<span class="badge badge-neutral" style="font-size:9px;">+${a.categories_arr.length - 3} more</span>` : '';
                catHtml = `<div style="display:flex;flex-wrap:wrap;gap:3px;align-items:center;">${chips} ${extra}</div>`;
            } else {
                catHtml = '<span class="text-xs text-muted">None Assigned</span>';
            }

            // Station info
            let stationHtml = '<span class="text-xs text-muted">—</span>';
            if (a.station_id) {
                stationHtml = `
                    <div><span class="badge badge-neutral mono" style="font-size:10px;">${escapeHtml(a.station_id)}</span></div>
                    <div class="text-xs text-muted" style="max-width:140px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">
                        ${escapeHtml(a.station_name || '')}
                    </div>
                `;
            }

            // Last active
            const lastActiveStr = a.last_login ? formatDate(a.last_login) : 'Never';

            return `
                <tr>
                    <td>
                        <div style="display:flex;align-items:center;gap:10px;">
                            <div class="admin-avatar" style="background:${gradient};">${initials}</div>
                            <div>
                                <div style="font-weight:700;display:flex;align-items:center;gap:6px;">
                                    ${escapeHtml(a.name)}
                                    ${isMe ? '<span class="badge badge-primary" style="font-size:9px;padding:1px 5px;">YOU</span>' : ''}
                                </div>
                                <div class="text-xs text-muted"><i class="bi bi-telephone me-1"></i>${escapeHtml(a.phone || 'No phone')}</div>
                            </div>
                        </div>
                    </td>
                    <td>
                        <code class="mono" style="font-size:12px;">${escapeHtml(a.email)}</code>
                    </td>
                    <td>
                        <span class="badge ${roleBadgeClass}" style="font-size:11px;">${roleLabel}</span>
                    </td>
                    <td>
                        <div style="font-weight:600;font-size:12px;margin-bottom:3px;color:var(--text-secondary);">${escapeHtml(a.department || 'Central Operations')}</div>
                        ${catHtml}
                    </td>
                    <td>
                        ${stationHtml}
                    </td>
                    <td>
                        <button class="badge badge-${a.is_active == 1 ? 'success' : 'critical'}" 
                                style="cursor:${isMe ? 'default' : 'pointer'};border:none;"
                                onclick="${isMe ? '' : `AdminsManager.toggleStatus(${a.id})`}"
                                title="${isMe ? 'Your own account' : 'Click to toggle status'}">
                            <span class="dot dot-${a.is_active == 1 ? 'success' : 'critical'} me-1"></span>
                            ${a.is_active == 1 ? 'Active' : 'Suspended'}
                        </button>
                    </td>
                    <td>
                        <div style="font-size:12px;font-weight:600;">${lastActiveStr}</div>
                        <div class="text-xs text-muted">${a.login_count || 0} logins</div>
                    </td>
                    <td style="text-align:right;">
                        <div style="display:inline-flex;gap:4px;">
                            <button class="btn btn-surface btn-xs" onclick="AdminsManager.openEditModal(${a.id})" title="Edit Administrator">
                                <i class="bi bi-pencil-fill"></i>
                            </button>
                            <button class="btn btn-surface btn-xs" onclick="AdminsManager.openResetPasswordModal(${a.id}, '${escapeHtml(a.name)}', '${escapeHtml(a.email)}')" title="Reset Password">
                                <i class="bi bi-key-fill"></i>
                            </button>
                            <button class="btn btn-surface btn-xs" onclick="AdminsManager.openAuditTrail(${a.id}, '${escapeHtml(a.name)}', '${escapeHtml(a.email)}', '${roleLabel}')" title="Audit Trail">
                                <i class="bi bi-journal-text"></i>
                            </button>
                            ${!isMe ? `
                            <button class="btn btn-ghost btn-xs text-critical" onclick="AdminsManager.openDeleteModal(${a.id}, '${escapeHtml(a.name)}', '${escapeHtml(a.email)}')" title="Delete Account">
                                <i class="bi bi-trash3-fill"></i>
                            </button>
                            ` : ''}
                        </div>
                    </td>
                </tr>
            `;
        }).join('');
    },

    filter() {
        const q = (document.getElementById('adminSearchInput').value || '').toLowerCase();
        const role = document.getElementById('adminRoleFilter').value;
        const dept = (document.getElementById('adminDeptFilter').value || '').toLowerCase();
        const status = document.getElementById('adminStatusFilter').value;

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
