<?php
$pageTitle  = 'Workforce & Employees';
$activePage = 'employees';
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/session.php';
require_once __DIR__ . '/includes/rbac.php';
require_once __DIR__ . '/includes/audit.php';
startSecureSession(); requireLogin(); requirePermission('employees.view');

$db = getDB();
$admin = getSessionAdmin();
$csrfToken = generateCsrfToken();
$stationFilter = $_GET['station'] ?? '';

// Helper to check if a station belongs to the current admin's scope
function isStationAllowed(mysqli $db, string $stationId, array $admin): bool {
    if ($admin['role'] === 'super_admin') return true;
    if ($admin['role'] === 'station_admin') {
        return !empty($admin['station_id']) && $admin['station_id'] === $stationId;
    }
    $escaped = $db->real_escape_string($stationId);
    $res = $db->query("SELECT service_id FROM service_stations WHERE station_id = '$escaped' LIMIT 1");
    if ($res && $row = $res->fetch_assoc()) {
        return isAdminAllowedCategory($row['service_id'], $admin);
    }
    return false;
}

// Handle Add Employee POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_employee'])) {
    requirePermission('employees.manage');
    if (validateCsrf()) {
        $name = trim($_POST['name']);
        $role = trim($_POST['role']);
        $phone = trim($_POST['contact_number']);
        $stationId = trim($_POST['station_id']);
        $email = trim($_POST['email'] ?? '');

        if (!isStationAllowed($db, $stationId, $admin)) {
            $errorMsg = "Unauthorized: You do not have permission to assign personnel to this station/service.";
        } elseif ($name && $stationId) {
            $stmt = $db->prepare("INSERT INTO employees (station_id, name, role, contact_number, email, status) VALUES (?, ?, ?, ?, ?, 'available')");
            $stmt->bind_param('sssss', $stationId, $name, $role, $phone, $email);
            if ($stmt->execute()) {
                logAudit('add_employee', 'employee', (string)$db->insert_id, '', "name:$name station:$stationId");
                $successMsg = "Personnel added successfully.";
            } else {
                $errorMsg = "Failed to add employee: " . $db->error;
            }
        }
    }
}

// Handle Transfer POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['transfer_employee'])) {
    requirePermission('employees.manage');
    if (validateCsrf()) {
        $empId = (int)$_POST['employee_id'];
        $newStation = trim($_POST['new_station_id']);
        
        if ($empId > 0 && $newStation) {
            // Verify current employee is in admin's scope
            $checkEmp = $db->query("SELECT e.station_id, ss.service_id FROM employees e LEFT JOIN service_stations ss ON ss.station_id = e.station_id WHERE e.id = $empId LIMIT 1");
            $empRow = $checkEmp ? $checkEmp->fetch_assoc() : null;

            if (!$empRow || ($admin['role'] !== 'super_admin' && !isStationAllowed($db, $empRow['station_id'] ?? '', $admin))) {
                $errorMsg = "Unauthorized: You cannot transfer personnel outside your service jurisdiction.";
            } elseif (!isStationAllowed($db, $newStation, $admin)) {
                $errorMsg = "Unauthorized: Target station is outside your permitted service jurisdiction.";
            } else {
                $db->query("UPDATE employees SET station_id = '" . $db->real_escape_string($newStation) . "' WHERE id = $empId");
                logAudit('transfer_employee', 'employee', (string)$empId, '', "new_station:$newStation");
                $successMsg = "Employee transferred successfully.";
            }
        }
    }
}

// Fetch Employees with scope enforcement
$where = " WHERE 1=1 ";
$where .= getEmployeeScopeWhere('ss', 'e');

if ($stationFilter) {
    $where .= " AND e.station_id = '" . $db->real_escape_string($stationFilter) . "'";
}
$empRes = $db->query("SELECT e.*, ss.name AS station_name, ss.service_label, ss.service_id 
    FROM employees e 
    LEFT JOIN service_stations ss ON ss.station_id = e.station_id 
    $where ORDER BY e.id DESC");

$employees = [];
if ($empRes) {
    while ($row = $empRes->fetch_assoc()) {
        $employees[] = $row;
    }
}

// Fetch stations for select menus (strictly scoped to current admin)
$stationsList = [];
$stationScope = getStationScopeWhere('service_stations');
$stQ = $db->query("SELECT station_id, name, service_label, service_id FROM service_stations WHERE 1=1 $stationScope ORDER BY name ASC");
if ($stQ) {
    while ($s = $stQ->fetch_assoc()) {
        $stationsList[] = $s;
    }
}

$showBackButton = true;
require_once __DIR__ . '/includes/layout.php';
?>

<style>
/* ── Toolbar: Minimalist Search ──────────────────────────────────── */
.emp-toolbar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    margin-bottom: var(--space-4);
    flex-wrap: wrap;
}
.emp-search-wrapper {
    position: relative;
    flex: 1;
    max-width: 340px;
    min-width: 240px;
}
.emp-search-wrapper i {
    position: absolute;
    left: 12px;
    top: 50%;
    transform: translateY(-50%);
    color: var(--text-muted);
    font-size: 13px;
    pointer-events: none;
}
.emp-search-input {
    width: 100%;
    padding: 8px 12px 8px 34px;
    border-radius: var(--radius-md);
    border: 1px solid var(--border-light);
    background: var(--bg-surface);
    color: var(--text-primary);
    font-size: 13px;
    outline: none;
    transition: border-color var(--transition-fast), box-shadow var(--transition-fast);
}
.emp-search-input:focus {
    border-color: var(--brand-primary);
    box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.15);
}

/* ── Minimalist Table & Rows ──────────────────────────────────────── */
.emp-clickable-row {
    cursor: pointer;
    transition: background var(--transition-fast);
}
.emp-clickable-row:hover {
    background: var(--bg-surface-2);
}
.emp-officer-cell {
    display: flex;
    align-items: center;
    gap: 12px;
}
.emp-avatar {
    width: 36px;
    height: 36px;
    border-radius: var(--radius-full);
    background: var(--bg-surface-2);
    border: 1px solid var(--border-light);
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    font-size: 13px;
    color: var(--brand-primary);
    flex-shrink: 0;
    text-transform: uppercase;
}
.emp-info {
    display: flex;
    flex-direction: column;
    gap: 2px;
}
.emp-name {
    font-weight: 600;
    color: var(--text-primary);
    font-size: 13px;
}

/* ── Contextual Actions Cell & Dropdown ───────────────────────────── */
.emp-action-cell {
    position: relative;
    display: flex;
    align-items: center;
    justify-content: flex-end;
    gap: 6px;
}
.emp-dropdown-menu {
    display: none;
    position: absolute;
    right: 0;
    top: 100%;
    min-width: 175px;
    background: var(--bg-surface);
    border: 1px solid var(--border-light);
    border-radius: var(--radius-md);
    box-shadow: var(--shadow-lg);
    z-index: 100;
    padding: 4px;
    margin-top: 4px;
}
.emp-dropdown-menu.show {
    display: block;
}
.emp-dropdown-item {
    display: flex;
    align-items: center;
    gap: 8px;
    width: 100%;
    padding: 8px 12px;
    font-size: 12px;
    font-weight: 500;
    color: var(--text-primary);
    border: none;
    background: transparent;
    text-align: left;
    text-decoration: none;
    border-radius: var(--radius-sm);
    cursor: pointer;
    transition: background var(--transition-fast), color var(--transition-fast);
}
.emp-dropdown-item:hover {
    background: var(--bg-surface-2);
    color: var(--brand-primary);
}
.emp-dropdown-divider {
    height: 1px;
    background: var(--border-light);
    margin: 4px 0;
}

/* ── Accordion Drawer Row ─────────────────────────────────────────── */
.emp-drawer-row {
    background: var(--bg-surface-2) !important;
}
.emp-drawer-cell {
    padding: 16px 20px !important;
    border-top: 1px dashed var(--border-light) !important;
}
.emp-drawer-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    gap: 16px;
    align-items: center;
}
.emp-drawer-item {
    display: flex;
    flex-direction: column;
    gap: 4px;
}
.emp-drawer-label {
    font-size: 10.5px;
    text-transform: uppercase;
    font-weight: 700;
    letter-spacing: 0.5px;
    color: var(--text-muted);
}
.emp-drawer-value {
    font-size: 13px;
    font-weight: 500;
    color: var(--text-primary);
    display: flex;
    align-items: center;
    gap: 6px;
}
</style>

<div class="page-header">
    <div>
        <h1 class="page-title"><i class="bi bi-people-fill text-primary"></i> Employees & Personnel</h1>
        <p class="page-subtitle">Field officers, responders, and crew management</p>
    </div>
    <div class="page-actions">
        <?php if (hasPermission('employees.manage')): ?>
        <button class="btn btn-primary btn-sm" onclick="Modal.open('addEmpModal')">
            <i class="bi bi-person-plus-fill"></i> Add Employee
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

<!-- Minimalist Toolbar: Search & Counters -->
<div class="emp-toolbar">
    <div class="emp-search-wrapper">
        <i class="bi bi-search"></i>
        <input type="text" id="empSearchInput" class="emp-search-input" 
               placeholder="Search personnel, role, or station..." 
               onkeyup="EmployeesManager.handleSearchKeyup(event)">
    </div>
    <div class="text-xs text-muted" id="empCountLabel">
        Showing <?= count($employees) ?> personnel
    </div>
</div>

<div class="card">
    <div class="table-wrapper" style="border:none;border-radius:0;">
        <table class="table">
            <thead>
                <tr>
                    <th>Unit / Officer</th>
                    <th>Assigned Station</th>
                    <th>Status</th>
                    <th style="text-align:right;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($employees)): ?>
                <tr>
                    <td colspan="4">
                        <div class="empty-state">
                            <div class="empty-state-icon">👷</div>
                            <div class="empty-state-title">No Personnel Found</div>
                        </div>
                    </td>
                </tr>
                <?php else: ?>
                <?php foreach ($employees as $emp): 
                    $initial = mb_substr(trim($emp['name']), 0, 1) ?: 'U';
                    $status = $emp['status'] ?? 'available';
                    $statusClass = $status === 'available' ? 'success' : 'warning';
                ?>
                <tr class="emp-clickable-row" id="emp-row-<?= $emp['id'] ?>" 
                    data-search="<?= htmlspecialchars(strtolower($emp['name'] . ' ' . $emp['role'] . ' ' . ($emp['station_name'] ?? '') . ' ' . ($emp['service_label'] ?? ''))) ?>"
                    onclick="EmployeesManager.toggleRowDrawer(<?= $emp['id'] ?>, event)">
                    <td>
                        <div class="emp-officer-cell">
                            <div class="emp-avatar"><?= htmlspecialchars($initial) ?></div>
                            <div class="emp-info">
                                <span class="emp-name"><?= htmlspecialchars($emp['name']) ?></span>
                                <div>
                                    <span class="badge badge-info" style="font-size:10px;padding:2px 6px;">
                                        <?= htmlspecialchars($emp['role']) ?>
                                    </span>
                                </div>
                            </div>
                        </div>
                    </td>
                    <td>
                        <div class="font-semibold text-sm"><?= htmlspecialchars($emp['station_name'] ?? $emp['station_id']) ?></div>
                        <div class="text-xs text-muted"><?= htmlspecialchars($emp['service_label'] ?? '') ?></div>
                    </td>
                    <td>
                        <span class="badge badge-<?= $statusClass ?>">
                            <?= htmlspecialchars(strtoupper($status)) ?>
                        </span>
                    </td>
                    <td style="text-align:right;" onclick="event.stopPropagation()">
                        <div class="emp-action-cell">
                            <button type="button" class="btn btn-ghost btn-xs" 
                                    onclick="EmployeesManager.toggleRowDrawer(<?= $emp['id'] ?>, event)" 
                                    title="Toggle details" aria-label="Toggle details">
                                <i class="bi bi-chevron-down" id="drawer-icon-<?= $emp['id'] ?>"></i>
                            </button>
                            <button type="button" class="btn btn-ghost btn-xs" 
                                    onclick="EmployeesManager.toggleActionMenu(<?= $emp['id'] ?>, event)" 
                                    title="More options" aria-label="More options">
                                <i class="bi bi-three-dots"></i>
                            </button>

                            <!-- Dropdown Context Menu -->
                            <div class="emp-dropdown-menu" id="empMenu-<?= $emp['id'] ?>">
                                <?php if (hasPermission('employees.manage')): ?>
                                <button type="button" class="emp-dropdown-item" 
                                        onclick="EmployeesManager.openTransfer(<?= $emp['id'] ?>, '<?= htmlspecialchars(addslashes($emp['name'])) ?>', '<?= htmlspecialchars($emp['station_id']) ?>')">
                                    <i class="bi bi-arrow-left-right text-warning"></i> Transfer Unit
                                </button>
                                <?php endif; ?>
                                <?php if (!empty($emp['contact_number'])): ?>
                                <a href="tel:<?= htmlspecialchars($emp['contact_number']) ?>" class="emp-dropdown-item">
                                    <i class="bi bi-telephone-fill text-info"></i> Call (<?= htmlspecialchars($emp['contact_number']) ?>)
                                </a>
                                <?php endif; ?>
                                <?php if (!empty($emp['email'])): ?>
                                <a href="mailto:<?= htmlspecialchars($emp['email']) ?>" class="emp-dropdown-item">
                                    <i class="bi bi-envelope-fill text-secondary"></i> Send Email
                                </a>
                                <?php endif; ?>
                                <div class="emp-dropdown-divider"></div>
                                <button type="button" class="emp-dropdown-item" onclick="EmployeesManager.toggleRowDrawer(<?= $emp['id'] ?>, event); EmployeesManager.closeAllMenus();">
                                    <i class="bi bi-info-circle text-primary"></i> View Full Details
                                </button>
                            </div>
                        </div>
                    </td>
                </tr>

                <!-- Expandable Accordion Drawer Row -->
                <tr class="emp-drawer-row" id="drawer-row-<?= $emp['id'] ?>" style="display:none;">
                    <td colspan="4" class="emp-drawer-cell">
                        <div class="emp-drawer-grid">
                            <div class="emp-drawer-item">
                                <span class="emp-drawer-label">Employee ID</span>
                                <div class="emp-drawer-value"><code class="mono">#<?= $emp['id'] ?></code></div>
                            </div>
                            <div class="emp-drawer-item">
                                <span class="emp-drawer-label">Contact Phone</span>
                                <div class="emp-drawer-value">
                                    <?php if (!empty($emp['contact_number'])): ?>
                                    <a href="tel:<?= htmlspecialchars($emp['contact_number']) ?>" class="text-primary" style="text-decoration:none;">
                                        <i class="bi bi-telephone me-1"></i><?= htmlspecialchars($emp['contact_number']) ?>
                                    </a>
                                    <?php else: ?>
                                    <span class="text-muted">No phone registered</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="emp-drawer-item">
                                <span class="emp-drawer-label">Email Address</span>
                                <div class="emp-drawer-value">
                                    <?php if (!empty($emp['email'])): ?>
                                    <a href="mailto:<?= htmlspecialchars($emp['email']) ?>" class="text-primary" style="text-decoration:none;">
                                        <i class="bi bi-envelope me-1"></i><?= htmlspecialchars($emp['email']) ?>
                                    </a>
                                    <?php else: ?>
                                    <span class="text-muted">No email registered</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="emp-drawer-item">
                                <span class="emp-drawer-label">Department / Jurisdiction</span>
                                <div class="emp-drawer-value">
                                    <span class="badge badge-surface" style="border:1px solid var(--border-light);">
                                        <?= htmlspecialchars($emp['service_label'] ?? 'General Municipal') ?>
                                    </span>
                                </div>
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

<!-- Modal: Add Employee -->
<div class="modal-backdrop" id="addEmpModal">
    <div class="modal">
        <form method="POST" action="">
            <input type="hidden" name="<?= CSRF_TOKEN_NAME ?>" value="<?= htmlspecialchars($csrfToken) ?>">
            <input type="hidden" name="add_employee" value="1">
            <div class="modal-header">
                <span class="modal-title"><i class="bi bi-person-plus-fill text-primary"></i> Add Employee</span>
                <button type="button" class="modal-close" onclick="Modal.close('addEmpModal')"><i class="bi bi-x"></i></button>
            </div>
            <div class="modal-body">
                <div class="form-group mb-3">
                    <label class="form-label">Full Name</label>
                    <input type="text" name="name" class="form-control" placeholder="Officer Name" required>
                </div>
                <div class="form-group mb-3">
                    <label class="form-label">Role / Rank (e.g. Fire Captain, Paramedic, Driver)</label>
                    <input type="text" name="role" class="form-control" placeholder="Paramedic Officer" required>
                </div>
                <div class="form-group mb-3">
                    <label class="form-label">Assigned Station</label>
                    <select name="station_id" class="form-control" required>
                        <?php foreach ($stationsList as $st): ?>
                        <option value="<?= htmlspecialchars($st['station_id']) ?>">
                            <?= htmlspecialchars($st['name']) ?> (<?= htmlspecialchars($st['service_label']) ?>)
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="grid grid-cols-2 gap-3 mb-3">
                    <div class="form-group">
                        <label class="form-label">Contact Number</label>
                        <input type="text" name="contact_number" class="form-control" placeholder="9876543210" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Email (Optional)</label>
                        <input type="email" name="email" class="form-control" placeholder="officer@city.gov">
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-ghost" onclick="Modal.close('addEmpModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Add Personnel</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal: Transfer Employee -->
<div class="modal-backdrop" id="transferModal">
    <div class="modal">
        <form method="POST" action="">
            <input type="hidden" name="<?= CSRF_TOKEN_NAME ?>" value="<?= htmlspecialchars($csrfToken) ?>">
            <input type="hidden" name="transfer_employee" value="1">
            <input type="hidden" name="employee_id" id="tr-emp-id">
            <div class="modal-header">
                <span class="modal-title"><i class="bi bi-arrow-left-right text-warning"></i> Transfer Employee</span>
                <button type="button" class="modal-close" onclick="Modal.close('transferModal')"><i class="bi bi-x"></i></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    Transferring: <strong id="tr-emp-name"></strong>
                </div>
                <div class="form-group">
                    <label class="form-label">Target Station</label>
                    <select name="new_station_id" id="tr-station-select" class="form-control" required>
                        <?php foreach ($stationsList as $st): ?>
                        <option value="<?= htmlspecialchars($st['station_id']) ?>">
                            <?= htmlspecialchars($st['name']) ?> (<?= htmlspecialchars($st['service_label']) ?>)
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-ghost" onclick="Modal.close('transferModal')">Cancel</button>
                <button type="submit" class="btn btn-warning">Confirm Transfer</button>
            </div>
        </form>
    </div>
</div>

<script>
const EmployeesManager = {
    toggleRowDrawer(empId, event) {
        if (event) {
            // Prevent toggle if the click came from interactive elements inside the action cell (except the chevron button)
            const target = event.target;
            if (target.closest('.emp-action-cell') && !target.closest('button[title="Toggle details"]')) {
                return;
            }
        }
        const drawer = document.getElementById(`drawer-row-${empId}`);
        const icon = document.getElementById(`drawer-icon-${empId}`);
        if (!drawer) return;
        
        const isClosed = drawer.style.display === 'none' || drawer.style.display === '';
        drawer.style.display = isClosed ? 'table-row' : 'none';
        if (icon) {
            icon.className = isClosed ? 'bi bi-chevron-up' : 'bi bi-chevron-down';
        }
    },

    toggleActionMenu(empId, event) {
        if (event) {
            event.stopPropagation();
        }
        const menu = document.getElementById(`empMenu-${empId}`);
        if (!menu) return;
        
        const isShown = menu.classList.contains('show');
        this.closeAllMenus();
        if (!isShown) {
            menu.classList.add('show');
        }
    },

    closeAllMenus() {
        document.querySelectorAll('.emp-dropdown-menu.show').forEach(m => m.classList.remove('show'));
    },

    openTransfer(id, name, currentStation) {
        this.closeAllMenus();
        openTransferModal(id, name, currentStation);
    },

    handleSearchKeyup(event) {
        const query = (event.target.value || '').trim().toLowerCase();
        const rows = document.querySelectorAll('tr[id^="emp-row-"]');
        let visibleCount = 0;

        rows.forEach(row => {
            const empId = row.id.replace('emp-row-', '');
            const drawerRow = document.getElementById(`drawer-row-${empId}`);
            const text = row.getAttribute('data-search') || '';

            if (!query || text.includes(query)) {
                row.style.display = '';
                visibleCount++;
            } else {
                row.style.display = 'none';
                if (drawerRow) {
                    drawerRow.style.display = 'none';
                    const icon = document.getElementById(`drawer-icon-${empId}`);
                    if (icon) icon.className = 'bi bi-chevron-down';
                }
            }
        });

        const countLabel = document.getElementById('empCountLabel');
        if (countLabel) {
            countLabel.textContent = `Showing ${visibleCount} personnel`;
        }
    }
};

// Global menu dismiss listeners
document.addEventListener('click', (e) => {
    if (!e.target.closest('.emp-action-cell')) {
        EmployeesManager.closeAllMenus();
    }
});
window.addEventListener('scroll', () => EmployeesManager.closeAllMenus(), { passive: true });
document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') {
        EmployeesManager.closeAllMenus();
    }
});

function openTransferModal(id, name, currentStation) {
    document.getElementById('tr-emp-id').value = id;
    document.getElementById('tr-emp-name').textContent = name;
    document.getElementById('tr-station-select').value = currentStation;
    Modal.open('transferModal');
}
</script>

<?php require_once __DIR__ . '/includes/layout_end.php'; ?>
