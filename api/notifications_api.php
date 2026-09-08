<?php
// ── notifications_api.php ──────────────────────────────────
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/rbac.php';
header('Content-Type: application/json; charset=UTF-8');
startSecureSession(); requireLogin();

$db      = getDB();
$admin   = getSessionAdmin();
$adminId = is_numeric($admin['id']) ? (int)$admin['id'] : 0;
$action  = $_GET['action'] ?? 'list';
$input   = json_decode(file_get_contents('php://input'), true) ?? [];

switch ($action) {
    case 'count':
        $cnt = 0;
        $latest = null;
        if ($adminId > 0) {
            $r = $db->query("SELECT COUNT(*) AS cnt FROM web_notifications WHERE (admin_id=$adminId OR admin_id IS NULL) AND is_read=0");
            if ($r) $cnt = (int)$r->fetch_assoc()['cnt'];
            $ln = $db->query("SELECT * FROM web_notifications WHERE (admin_id=$adminId OR admin_id IS NULL) AND is_read=0 ORDER BY created_at DESC LIMIT 1");
            if ($ln) $latest = $ln->fetch_assoc();
        }
        jsonResponse(['success' => true, 'count' => $cnt, 'latest' => $latest]);
        break;
    case 'list':
        $rows = [];
        if ($adminId > 0) {
            $r = $db->query("SELECT * FROM web_notifications WHERE (admin_id=$adminId OR admin_id IS NULL) ORDER BY created_at DESC LIMIT 50");
            while ($row = $r->fetch_assoc()) $rows[] = $row;
        }
        jsonResponse(['success' => true, 'notifications' => $rows]);
        break;
    case 'mark_read':
        $nid = (int)($input['id'] ?? 0);
        if ($nid > 0) $db->query("UPDATE web_notifications SET is_read=1 WHERE id=$nid");
        jsonResponse(['success' => true]);
        break;
    case 'mark_all_read':
        if ($adminId > 0) $db->query("UPDATE web_notifications SET is_read=1 WHERE admin_id=$adminId OR admin_id IS NULL");
        jsonResponse(['success' => true]);
        break;
    default:
        jsonResponse(['success' => false], 400);
}
