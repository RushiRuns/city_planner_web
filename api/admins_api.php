<?php
// ============================================================
// City Planner Web Admin — Central Admin API
// High-Security CRUD & Governance Operations
// ============================================================
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/rbac.php';
require_once __DIR__ . '/../includes/audit.php';

header('Content-Type: application/json; charset=UTF-8');
startSecureSession();
requireLogin();
requireRole('super_admin');

$db           = getDB();
$currentAdmin = getSessionAdmin();
$currentId    = (int)($currentAdmin['id'] ?? 0);

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? ($method === 'POST' ? (json_decode(file_get_contents('php://input'), true)['action'] ?? 'list') : 'list');
$input  = $method === 'POST' ? (json_decode(file_get_contents('php://input'), true) ?? []) : [];

switch ($action) {

    // ── LIST ALL ADMINS (with filters & stats) ─────────────────
    case 'list':
        $roleFilter   = trim($_GET['role'] ?? '');
        $deptFilter   = trim($_GET['department'] ?? '');
        $statusFilter = isset($_GET['status']) && $_GET['status'] !== '' ? (int)$_GET['status'] : null;
        $search       = trim($_GET['search'] ?? '');

        $where = " WHERE 1=1 ";
        if ($roleFilter && in_array($roleFilter, ['super_admin', 'dept_admin', 'station_admin'])) {
            $where .= " AND a.role = '" . $db->real_escape_string($roleFilter) . "'";
        }
        if ($deptFilter) {
            $where .= " AND (a.department LIKE '%" . $db->real_escape_string($deptFilter) . "%' OR a.categories LIKE '%" . $db->real_escape_string($deptFilter) . "%')";
        }
        if ($statusFilter !== null) {
            $where .= " AND a.is_active = $statusFilter";
        }
        if ($search) {
            $q = $db->real_escape_string($search);
            $where .= " AND (a.name LIKE '%$q%' OR a.email LIKE '%$q%' OR a.phone LIKE '%$q%' OR a.station_id LIKE '%$q%' OR a.department LIKE '%$q%')";
        }

        $sql = "SELECT a.id, a.name, a.email, a.phone, a.role, a.department, a.categories, 
                       a.station_id, a.is_active, a.last_login, a.login_count, a.created_at, a.updated_at,
                       ss.name AS station_name, ss.service_label
                FROM web_admins a
                LEFT JOIN service_stations ss ON ss.station_id = a.station_id
                $where
                ORDER BY a.role = 'super_admin' DESC, a.created_at DESC";

        $r = $db->query($sql);
        $rows = [];
        if ($r && $r instanceof mysqli_result) {
            while ($row = $r->fetch_assoc()) {
                $row['categories_arr'] = is_string($row['categories']) ? (json_decode($row['categories'], true) ?: []) : [];
                $rows[] = $row;
            }
        }

        // Summary stats
        $statsQ = $db->query("SELECT 
            COUNT(*) AS total_admins,
            SUM(is_active = 1) AS active_admins,
            SUM(is_active = 0) AS suspended_admins,
            SUM(role = 'super_admin') AS super_admins,
            SUM(role = 'dept_admin') AS dept_admins,
            SUM(role = 'station_admin') AS station_admins,
            SUM(last_login >= DATE_SUB(NOW(), INTERVAL 24 HOUR)) AS active_today
            FROM web_admins");
        $stats = $statsQ ? $statsQ->fetch_assoc() : [];

        jsonResponse([
            'success' => true,
            'admins'  => $rows,
            'stats'   => $stats,
        ]);
        break;

    // ── GET SINGLE ADMIN ──────────────────────────────────────
    case 'get':
        $id = (int)($_GET['id'] ?? 0);
        if ($id <= 0) {
            jsonResponse(['success' => false, 'message' => 'Valid Admin ID required.'], 400);
        }

        $stmt = $db->prepare("SELECT a.id, a.name, a.email, a.phone, a.role, a.department, a.categories, 
                                     a.station_id, a.is_active, a.last_login, a.login_count, a.created_at, a.updated_at,
                                     ss.name AS station_name, ss.service_label
                              FROM web_admins a
                              LEFT JOIN service_stations ss ON ss.station_id = a.station_id
                              WHERE a.id = ? LIMIT 1");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $res = $stmt->get_result();
        $admin = $res ? $res->fetch_assoc() : null;

        if (!$admin) {
            jsonResponse(['success' => false, 'message' => 'Administrator not found.'], 404);
        }

        $admin['categories_arr'] = is_string($admin['categories']) ? (json_decode($admin['categories'], true) ?: []) : [];
        jsonResponse(['success' => true, 'admin' => $admin]);
        break;

    // ── CREATE NEW ADMINISTRATOR ──────────────────────────────
    case 'create':
        $name       = trim($input['name'] ?? '');
        $email      = strtolower(trim($input['email'] ?? ''));
        $phone      = trim($input['phone'] ?? '');
        $role       = in_array($input['role'] ?? '', ['super_admin', 'dept_admin', 'station_admin']) ? $input['role'] : 'dept_admin';
        $department = trim($input['department'] ?? '');
        $stationId  = trim($input['station_id'] ?? '');
        $password   = trim($input['password'] ?? '');
        $categories = is_array($input['categories'] ?? null) ? array_values(array_unique($input['categories'])) : [];

        if (!$name || !$email || !$password) {
            jsonResponse(['success' => false, 'message' => 'Full Name, Email and Password are required.'], 400);
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            jsonResponse(['success' => false, 'message' => 'Invalid email address format.'], 400);
        }

        if (strlen($password) < 6) {
            jsonResponse(['success' => false, 'message' => 'Password must be at least 6 characters.'], 400);
        }

        // Check if email already registered
        $chk = $db->prepare("SELECT id FROM web_admins WHERE email = ? LIMIT 1");
        $chk->bind_param('s', $email);
        $chk->execute();
        if ($chk->get_result()->num_rows > 0) {
            jsonResponse(['success' => false, 'message' => 'An administrator with this email already exists.'], 409);
        }

        $catsJson = json_encode($categories);
        $hash     = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);

        $ins = $db->prepare("INSERT INTO web_admins (name, email, phone, password, role, department, categories, station_id, is_active) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1)");
        $ins->bind_param('ssssssss', $name, $email, $phone, $hash, $role, $department, $catsJson, $stationId);

        if (!$ins->execute()) {
            jsonResponse(['success' => false, 'message' => 'Database error: ' . $db->error], 500);
        }

        $newId = $db->insert_id;
        logAudit('create_admin', 'web_admin', (string)$newId, '', "name:$name email:$email role:$role dept:$department");

        jsonResponse([
            'success' => true,
            'id'      => $newId,
            'message' => "Administrator \"$name\" created successfully.",
        ]);
        break;

    // ── UPDATE ADMINISTRATOR ──────────────────────────────────
    case 'update':
        $id         = (int)($input['id'] ?? 0);
        $name       = trim($input['name'] ?? '');
        $phone      = trim($input['phone'] ?? '');
        $role       = in_array($input['role'] ?? '', ['super_admin', 'dept_admin', 'station_admin']) ? $input['role'] : 'dept_admin';
        $department = trim($input['department'] ?? '');
        $stationId  = trim($input['station_id'] ?? '');
        $isActive   = isset($input['is_active']) ? (int)(bool)$input['is_active'] : 1;
        $categories = is_array($input['categories'] ?? null) ? array_values(array_unique($input['categories'])) : [];
        $password   = trim($input['password'] ?? '');

        if ($id <= 0 || !$name) {
            jsonResponse(['success' => false, 'message' => 'Admin ID and Name are required.'], 400);
        }

        // Prevent self-demotion or disabling if current admin
        if ($id === $currentId && $isActive === 0) {
            jsonResponse(['success' => false, 'message' => 'You cannot disable your own active account.'], 400);
        }

        $catsJson = json_encode($categories);

        if (!empty($password)) {
            if (strlen($password) < 6) {
                jsonResponse(['success' => false, 'message' => 'Password must be at least 6 characters.'], 400);
            }
            $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
            $stmt = $db->prepare("UPDATE web_admins SET name = ?, phone = ?, role = ?, department = ?, categories = ?, station_id = ?, is_active = ?, password = ? WHERE id = ?");
            $stmt->bind_param('ssssssisi', $name, $phone, $role, $department, $catsJson, $stationId, $isActive, $hash, $id);
        } else {
            $stmt = $db->prepare("UPDATE web_admins SET name = ?, phone = ?, role = ?, department = ?, categories = ?, station_id = ?, is_active = ? WHERE id = ?");
            $stmt->bind_param('ssssssii', $name, $phone, $role, $department, $catsJson, $stationId, $isActive, $id);
        }

        if (!$stmt->execute()) {
            jsonResponse(['success' => false, 'message' => 'Database error: ' . $db->error], 500);
        }

        logAudit('update_admin', 'web_admin', (string)$id, '', "name:$name role:$role dept:$department active:$isActive");

        jsonResponse([
            'success' => true,
            'message' => "Administrator account updated successfully.",
        ]);
        break;

    // ── RESET PASSWORD ────────────────────────────────────────
    case 'reset_password':
        $id       = (int)($input['id'] ?? 0);
        $password = trim($input['password'] ?? '');

        if ($id <= 0 || !$password) {
            jsonResponse(['success' => false, 'message' => 'Admin ID and New Password are required.'], 400);
        }

        if (strlen($password) < 6) {
            jsonResponse(['success' => false, 'message' => 'Password must be at least 6 characters.'], 400);
        }

        $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
        $stmt = $db->prepare("UPDATE web_admins SET password = ? WHERE id = ?");
        $stmt->bind_param('si', $hash, $id);

        if (!$stmt->execute()) {
            jsonResponse(['success' => false, 'message' => 'Database error: ' . $db->error], 500);
        }

        logAudit('reset_admin_password', 'web_admin', (string)$id, '', 'Password manually reset by super admin');

        jsonResponse([
            'success' => true,
            'message' => 'Password updated successfully.',
        ]);
        break;

    // ── TOGGLE ACTIVE / SUSPENDED STATUS ──────────────────────
    case 'toggle':
        $id = (int)($input['id'] ?? 0);
        if ($id <= 0) {
            jsonResponse(['success' => false, 'message' => 'Valid Admin ID required.'], 400);
        }

        if ($id === $currentId) {
            jsonResponse(['success' => false, 'message' => 'You cannot disable your own active account.'], 400);
        }

        // Fetch current status
        $q = $db->query("SELECT is_active, name, role FROM web_admins WHERE id = $id LIMIT 1");
        $row = $q ? $q->fetch_assoc() : null;
        if (!$row) {
            jsonResponse(['success' => false, 'message' => 'Admin not found.'], 404);
        }

        $newStatus = $row['is_active'] ? 0 : 1;
        $db->query("UPDATE web_admins SET is_active = $newStatus WHERE id = $id");

        logAudit('toggle_admin', 'web_admin', (string)$id, (string)$row['is_active'], (string)$newStatus);

        jsonResponse([
            'success'   => true,
            'is_active' => $newStatus,
            'message'   => 'Administrator ' . ($newStatus ? 'enabled' : 'suspended') . '.',
        ]);
        break;

    // ── DELETE ADMINISTRATOR ──────────────────────────────────
    case 'delete':
        $id = (int)($input['id'] ?? 0);
        if ($id <= 0) {
            jsonResponse(['success' => false, 'message' => 'Valid Admin ID required.'], 400);
        }

        if ($id === $currentId) {
            jsonResponse(['success' => false, 'message' => 'Security restriction: You cannot delete your own logged-in account.'], 400);
        }

        // Check if target is a super_admin and ensure at least one other active super_admin exists
        $chk = $db->query("SELECT role, name, email FROM web_admins WHERE id = $id LIMIT 1");
        $adm = $chk ? $chk->fetch_assoc() : null;
        if (!$adm) {
            jsonResponse(['success' => false, 'message' => 'Admin not found.'], 404);
        }

        if ($adm['role'] === 'super_admin') {
            $cntSuper = (int)($db->query("SELECT COUNT(*) AS cnt FROM web_admins WHERE role = 'super_admin'")->fetch_assoc()['cnt'] ?? 0);
            if ($cntSuper <= 1) {
                jsonResponse(['success' => false, 'message' => 'Cannot delete the only Super Administrator account.'], 400);
            }
        }

        $db->query("DELETE FROM web_admins WHERE id = $id");
        logAudit('delete_admin', 'web_admin', (string)$id, '', "deleted_admin:{$adm['email']}");

        jsonResponse([
            'success' => true,
            'message' => "Administrator \"{$adm['name']}\" deleted successfully.",
        ]);
        break;

    // ── FETCH RECENT AUDIT LOGS FOR AN ADMIN ──────────────────
    case 'admin_logs':
        $id = (int)($_GET['id'] ?? 0);
        if ($id <= 0) {
            jsonResponse(['success' => false, 'message' => 'Valid Admin ID required.'], 400);
        }

        $stmt = $db->prepare("SELECT id, admin_id, admin_name, action, entity_type, target_id, details, ip_address, created_at 
                              FROM audit_logs 
                              WHERE admin_id = ? 
                              ORDER BY id DESC LIMIT 50");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $res = $stmt->get_result();

        $logs = [];
        if ($res) {
            while ($log = $res->fetch_assoc()) {
                $logs[] = $log;
            }
        }

        jsonResponse([
            'success' => true,
            'logs'    => $logs,
        ]);
        break;

    default:
        jsonResponse(['success' => false, 'message' => 'Invalid action.'], 400);
}
