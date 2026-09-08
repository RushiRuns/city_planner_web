<?php
// ============================================================
// City Planner Web Admin — Services API
// GET  ?action=list|get|interactions|stats
// POST ?action=create|update|delete|delete_interaction
// ============================================================
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/rbac.php';
require_once __DIR__ . '/../includes/audit.php';

header('Content-Type: application/json; charset=UTF-8');
startSecureSession();
requireLogin();

$db     = getDB();
$admin  = getSessionAdmin();
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? ($method === 'POST' ? (json_decode(file_get_contents('php://input'), true)['action'] ?? 'list') : 'list');
$input  = $method === 'POST' ? (json_decode(file_get_contents('php://input'), true) ?? []) : [];

switch ($action) {

    // ── LIST ALL SERVICES FROM DB ────────────────────────────
    case 'list':
    case 'list_services':
        requirePermission('services.view');
        $services = [];
        $res = $db->query("SELECT * FROM `services` ORDER BY category ASC, id ASC");
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $k = $row['service_key'];
                $meta = CATEGORIES[$k] ?? [];
                
                // Get active station count
                $stRes = $db->query("SELECT COUNT(*) AS cnt FROM `service_stations` WHERE `service_id` = '" . $db->real_escape_string($k) . "'");
                $stationCount = $stRes ? (int)$stRes->fetch_assoc()['cnt'] : 0;
                
                $row['icon']          = $meta['icon'] ?? 'bi-gear-fill';
                $row['color']         = $meta['color'] ?? '#6366F1';
                $row['table']         = $meta['table'] ?? ($k . '_requests');
                $row['urgency']       = $meta['urgency'] ?? ($row['category'] === 'Emergency' ? 'high' : 'medium');
                $row['station_count'] = $stationCount;
                $services[] = $row;
            }
        }
        jsonResponse(['success' => true, 'services' => $services, 'total' => count($services)]);
        break;

    // ── CREATE NEW SERVICE IN DB ─────────────────────────────
    case 'create':
        requirePermission('services.manage');
        $serviceKey  = strtolower(trim(preg_replace('/[^a-zA-Z0-9_]/', '_', $input['service_key'] ?? '')));
        $serviceName = sanitize($input['service_name'] ?? '');
        $category    = in_array($input['category'] ?? '', ['Emergency', 'Citizen']) ? $input['category'] : 'Citizen';

        if (empty($serviceKey) || empty($serviceName)) {
            jsonResponse(['success' => false, 'message' => 'Service key and service name are required.'], 422);
        }

        // Check if key already exists
        $chk = $db->query("SELECT id FROM `services` WHERE `service_key` = '" . $db->real_escape_string($serviceKey) . "' LIMIT 1");
        if ($chk && $chk->num_rows > 0) {
            jsonResponse(['success' => false, 'message' => 'Service key already exists.'], 409);
        }

        $stmt = $db->prepare("INSERT INTO `services` (`service_key`, `service_name`, `category`) VALUES (?, ?, ?)");
        $stmt->bind_param('sss', $serviceKey, $serviceName, $category);
        if ($stmt->execute()) {
            $newId = $stmt->insert_id;
            logAudit('create_service', 'services', (string)$newId, '', "key:$serviceKey name:$serviceName category:$category");
            jsonResponse([
                'success' => true,
                'message' => 'Service created successfully.',
                'service' => [
                    'id'           => $newId,
                    'service_key'  => $serviceKey,
                    'service_name' => $serviceName,
                    'category'     => $category,
                ]
            ]);
        } else {
            jsonResponse(['success' => false, 'message' => 'Database error creating service: ' . $db->error], 500);
        }
        break;

    // ── UPDATE SERVICE ───────────────────────────────────────
    case 'update':
        requirePermission('services.manage');
        $id          = (int)($input['id'] ?? 0);
        $serviceName = sanitize($input['service_name'] ?? '');
        $category    = in_array($input['category'] ?? '', ['Emergency', 'Citizen']) ? $input['category'] : 'Citizen';

        if ($id <= 0 || empty($serviceName)) {
            jsonResponse(['success' => false, 'message' => 'Valid Service ID and Name are required.'], 422);
        }

        $stmt = $db->prepare("UPDATE `services` SET `service_name` = ?, `category` = ? WHERE `id` = ?");
        $stmt->bind_param('ssi', $serviceName, $category, $id);
        if ($stmt->execute()) {
            logAudit('update_service', 'services', (string)$id, '', "name:$serviceName category:$category");
            jsonResponse(['success' => true, 'message' => 'Service updated successfully.']);
        } else {
            jsonResponse(['success' => false, 'message' => 'Error updating service: ' . $db->error], 500);
        }
        break;

    // ── DELETE SERVICE ───────────────────────────────────────
    case 'delete':
        requirePermission('services.manage');
        $id = (int)($input['id'] ?? 0);
        if ($id <= 0) {
            jsonResponse(['success' => false, 'message' => 'Invalid service ID.'], 422);
        }

        $res = $db->query("SELECT `service_key`, `service_name` FROM `services` WHERE `id` = $id LIMIT 1");
        $service = $res ? $res->fetch_assoc() : null;
        if (!$service) {
            jsonResponse(['success' => false, 'message' => 'Service not found.'], 404);
        }

        $db->query("DELETE FROM `services` WHERE `id` = $id");
        logAudit('delete_service', 'services', (string)$id, json_encode($service), '');
        jsonResponse(['success' => true, 'message' => 'Service removed successfully.']);
        break;

    // ── CITIZEN SERVICE HELPLINE INTERACTIONS ────────────────
    case 'interactions':
        requirePermission('services.view');
        $page    = max(1, (int)($_GET['page'] ?? 1));
        $perPage = min(100, max(10, (int)($_GET['per_page'] ?? 20)));
        $offset  = ($page - 1) * $perPage;

        $where = " WHERE 1=1 ";
        if (!empty($_GET['service_id'])) {
            $sid = $db->real_escape_string($_GET['service_id']);
            $where .= " AND `service_id` = '$sid' ";
        }
        if (!empty($_GET['type'])) {
            if ($_GET['type'] === 'call') {
                $where .= " AND `is_call_made` = 1 ";
            } elseif ($_GET['type'] === 'request') {
                $where .= " AND `is_request_sent` = 1 ";
            }
        }
        if (!empty($_GET['search'])) {
            $q = $db->real_escape_string(trim($_GET['search']));
            $where .= " AND (`citizen_name` LIKE '%$q%' OR `contact_number` LIKE '%$q%' OR `service_label` LIKE '%$q%' OR `description` LIKE '%$q%' OR `location_address` LIKE '%$q%') ";
        }

        $totalRes = $db->query("SELECT COUNT(*) AS cnt FROM `citizen_services` $where");
        $total = $totalRes ? (int)$totalRes->fetch_assoc()['cnt'] : 0;

        $dataRes = $db->query("SELECT * FROM `citizen_services` $where ORDER BY id DESC LIMIT $perPage OFFSET $offset");
        $interactions = [];
        if ($dataRes) {
            while ($row = $dataRes->fetch_assoc()) {
                // remove raw heavy base64 from list for speed if not requested
                $interactions[] = $row;
            }
        }

        jsonResponse([
            'success'      => true,
            'total'        => $total,
            'page'         => $page,
            'per_page'     => $perPage,
            'interactions' => $interactions,
        ]);
        break;

    // ── DELETE CITIZEN INTERACTION ───────────────────────────
    case 'delete_interaction':
        requirePermission('services.manage');
        $id = (int)($input['id'] ?? 0);
        if ($id <= 0) {
            jsonResponse(['success' => false, 'message' => 'Invalid interaction ID.'], 422);
        }

        $db->query("DELETE FROM `citizen_services` WHERE `id` = $id");
        logAudit('delete_citizen_service', 'citizen_services', (string)$id, '', '');
        jsonResponse(['success' => true, 'message' => 'Interaction log removed.']);
        break;

    default:
        jsonResponse(['success' => false, 'message' => 'Unknown action.'], 400);
        break;
}
