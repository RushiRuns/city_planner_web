<?php
// ── system_api.php ──────────────────────────────────────────
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/rbac.php';
header('Content-Type: application/json; charset=UTF-8');
startSecureSession(); requireLogin();

$action = $_GET['action'] ?? 'health';
$db     = getDB();

switch ($action) {
    case 'health':
        $health = [];
        // DB connectivity
        $r = $db->query("SELECT 1 AS ok");
        $health['database'] = ['status' => $r ? 'ok' : 'error', 'label' => 'MySQL Database', 'detail' => $r ? 'Connected' : $db->error];

        // Table counts
        $total = 0;
        foreach (ALL_REQUEST_TABLES as $tbl) {
            $tr = $db->query("SELECT COUNT(*) AS cnt FROM `$tbl`");
            if ($tr) $total += (int)$tr->fetch_assoc()['cnt'];
        }
        $health['requests'] = ['status' => 'ok', 'label' => 'Total Requests', 'detail' => number_format($total) . ' records across 14 tables'];

        // Offline queue
        $oq = $db->query("SELECT COUNT(*) AS cnt FROM offline_emergency_requests");
        $offCount = $oq ? (int)$oq->fetch_assoc()['cnt'] : 0;
        $health['offline_queue'] = ['status' => $offCount > 0 ? 'warning' : 'ok', 'label' => 'Offline SOS Queue', 'detail' => "$offCount pending records"];

        // Live tracking
        $lt = $db->query("SELECT COUNT(*) AS cnt FROM live_tracking WHERE is_tracking_active=1");
        $ltCount = $lt ? (int)$lt->fetch_assoc()['cnt'] : 0;
        $health['live_tracking'] = ['status' => 'ok', 'label' => 'Live Tracking', 'detail' => "$ltCount active trackers"];

        // Web admins
        $wa = $db->query("SELECT COUNT(*) AS cnt FROM web_admins WHERE is_active=1");
        $waCount = $wa ? (int)$wa->fetch_assoc()['cnt'] : 0;
        $health['web_admins'] = ['status' => 'ok', 'label' => 'Active Web Admins', 'detail' => "$waCount admins"];

        // Server info
        $health['server'] = ['status' => 'ok', 'label' => 'PHP Server', 'detail' => 'PHP ' . PHP_VERSION . ' — ' . date('Y-m-d H:i:s')];

        jsonResponse(['success' => true, 'health' => $health]);
        break;

    case 'save_settings':
        requireRole('super_admin');
        $input = json_decode(file_get_contents('php://input'), true) ?? [];
        $admin = getSessionAdmin();
        $adminId = is_numeric($admin['id']) ? (int)$admin['id'] : 0;
        foreach ($input['settings'] ?? [] as $key => $val) {
            $k = $db->real_escape_string($key);
            $v = $db->real_escape_string($val);
            $db->query("INSERT INTO system_settings (setting_key, setting_value, updated_by) VALUES ('$k','$v',$adminId)
                ON DUPLICATE KEY UPDATE setting_value='$v', updated_by=$adminId");
        }
        jsonResponse(['success' => true, 'message' => 'Settings saved.']);
        break;

    case 'settings':
        $r = $db->query("SELECT setting_key, setting_value FROM system_settings");
        $settings = [];
        while ($row = $r->fetch_assoc()) $settings[$row['setting_key']] = $row['setting_value'];
        jsonResponse(['success' => true, 'settings' => $settings]);
        break;

    default:
        jsonResponse(['success' => false], 400);
}
