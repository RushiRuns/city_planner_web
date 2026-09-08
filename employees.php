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

require_once __DIR__ . '/includes/layout.php';
?>

<div class="page-header">
    <div>
        <div class="page-back-wrapper">
            <a href="javascript:history.back()" onclick="if(window.history.length > 1 && document.referrer && document.referrer.indexOf(window.location.host) !== -1){ window.history.back(); return false; } else { window.location.href='<?= BASE_URL ?>dashboard.php'; return false; }" class="btn-back">
                <i class="bi bi-arrow-left"></i> Back
            </a>
        </div>
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

<div class="card">
    <div class="table-wrapper" style="border:none;border-radius:0;">
        <table class="table">
            <thead>
                <tr>
                    <th>Emp ID</th>
                    <th>Officer Name</th>
                    <th>Designation / Role</th>
                    <th>Assigned Station</th>
                    <th>Contact Phone</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($employees)): ?>
                <tr>
                    <td colspan="7">
                        <div class="empty-state">
                            <div class="empty-state-icon">👷</div>
                            <div class="empty-state-title">No Personnel Found</div>
                        </div>
                    </td>
                </tr>
                <?php else: ?>
                <?php foreach ($employees as $emp): ?>
                <tr>
                    <td><code class="mono">#<?= $emp['id'] ?></code></td>
                    <td><strong><?= htmlspecialchars($emp['name']) ?></strong></td>
                    <td><span class="badge badge-info"><?= htmlspecialchars($emp['role']) ?></span></td>
                    <td>
                        <div><?= htmlspecialchars($emp['station_name'] ?? $emp['station_id']) ?></div>
                        <div class="text-xs text-muted"><?= htmlspecialchars($emp['service_label'] ?? '') ?></div>
                    </td>
                    <td><i class="bi bi-telephone text-muted me-1"></i><?= htmlspecialchars($emp['contact_number']) ?></td>
                    <td>
                        <span class="badge badge-<?= ($emp['status'] ?? 'available') === 'available' ? 'success' : 'warning' ?>">
                            <?= htmlspecialchars($emp['status'] ?? 'available') ?>
                        </span>
                    </td>
                    <td>
                        <button class="btn btn-surface btn-sm" onclick="openTransferModal(<?= $emp['id'] ?>, '<?= htmlspecialchars(addslashes($emp['name'])) ?>', '<?= htmlspecialchars($emp['station_id']) ?>')">
                            <i class="bi bi-arrow-left-right"></i> Transfer
                        </button>
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
function openTransferModal(id, name, currentStation) {
    document.getElementById('tr-emp-id').value = id;
    document.getElementById('tr-emp-name').textContent = name;
    document.getElementById('tr-station-select').value = currentStation;
    Modal.open('transferModal');
}
</script>

<?php require_once __DIR__ . '/includes/layout_end.php'; ?>
