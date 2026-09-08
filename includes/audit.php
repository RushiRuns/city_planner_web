<?php
// ============================================================
// City Planner Web Admin — Audit Logging
// ============================================================
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/session.php';

function logAudit(
    string $action,
    string $entityType = '',
    string $entityId = '',
    string $oldValue = '',
    string $newValue = ''
): void {
    $admin = getSessionAdmin();
    $db    = getDB();

    $adminId    = $admin ? (int)$admin['id'] : null;
    $adminEmail = $admin ? $db->real_escape_string($admin['email']) : 'system';
    $adminName  = $admin ? $db->real_escape_string($admin['name']) : 'system';
    $action     = $db->real_escape_string($action);
    $entityType = $db->real_escape_string($entityType);
    $entityId   = $db->real_escape_string($entityId);
    $oldValue   = $db->real_escape_string(substr($oldValue, 0, 4000));
    $newValue   = $db->real_escape_string(substr($newValue, 0, 4000));
    $ip         = $db->real_escape_string(getClientIp());
    $ua         = $db->real_escape_string(substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500));

    $adminIdSql = $adminId !== null ? $adminId : 'NULL';

    $db->query("INSERT INTO audit_logs
        (admin_id, admin_email, admin_name, action, entity_type, entity_id, old_value, new_value, ip_address, user_agent)
        VALUES
        ($adminIdSql, '$adminEmail', '$adminName', '$action', '$entityType', '$entityId', '$oldValue', '$newValue', '$ip', '$ua')
    ");
}

function getClientIp(): string {
    $keys = ['HTTP_X_FORWARDED_FOR', 'HTTP_CLIENT_IP', 'REMOTE_ADDR'];
    foreach ($keys as $key) {
        if (!empty($_SERVER[$key])) {
            $ip = trim(explode(',', $_SERVER[$key])[0]);
            if (filter_var($ip, FILTER_VALIDATE_IP)) return $ip;
        }
    }
    return '0.0.0.0';
}
