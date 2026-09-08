<?php
// ============================================================
// City Planner Web Admin — RBAC Permission System
// ============================================================
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/session.php';

// ── Role-based default permissions ───────────────────────────
const ROLE_PERMISSIONS = [
    'super_admin' => [
        'dashboard.view', 'requests.view', 'requests.create', 'requests.assign',
        'requests.dispatch', 'requests.update_status', 'requests.resolve', 'requests.cancel',
        'tracking.view', 'citizens.view', 'citizens.manage', 'employees.view',
        'employees.manage', 'stations.view', 'stations.manage', 'admins.view',
        'admins.create', 'admins.edit', 'admins.delete', 'reports.view',
        'analytics.view', 'settings.manage', 'audit_logs.view', 'system_health.view',
        'offline_sync.view', 'notifications.view', 'departments.view',
        'services.view', 'services.manage', 'services.delete',
    ],
    'dept_admin' => [
        'dashboard.view', 'requests.view', 'requests.assign', 'requests.dispatch',
        'requests.update_status', 'requests.resolve', 'tracking.view', 'citizens.view',
        'employees.view', 'employees.manage', 'stations.view', 'analytics.view',
        'reports.view', 'notifications.view', 'departments.view',
        'services.view',
    ],
    'station_admin' => [
        'dashboard.view', 'requests.view', 'requests.assign', 'requests.dispatch',
        'requests.update_status', 'requests.resolve', 'tracking.view',
        'employees.view', 'employees.manage', 'stations.view', 'analytics.view',
        'notifications.view', 'services.view',
    ],
];

function hasPermission(string $permission): bool {
    $admin = getSessionAdmin();
    if (!$admin) return false;

    $role = $admin['role'];

    // Super admin always has all permissions
    if ($role === 'super_admin') return true;

    // Check role defaults
    $defaults = ROLE_PERMISSIONS[$role] ?? [];
    if (in_array($permission, $defaults)) return true;

    // Check granular overrides from DB (loaded on login)
    $extra = $_SESSION['extra_permissions'] ?? [];
    return in_array($permission, $extra);
}

function requirePermission(string $permission): void {
    if (!hasPermission($permission)) {
        if (isAjaxRequest()) {
            jsonResponse(['success' => false, 'message' => 'Access denied.'], 403);
        }
        header('Location: ' . BASE_URL . 'dashboard.php?error=access_denied');
        exit();
    }
}

function requireLogin(): void {
    if (!isLoggedIn()) {
        if (isAjaxRequest()) {
            jsonResponse(['success' => false, 'message' => 'Authentication required.'], 401);
        }
        $redirect = urlencode($_SERVER['REQUEST_URI']);
        header('Location: ' . BASE_URL . 'index.php?redirect=' . $redirect);
        exit();
    }
}

function requireRole(string ...$roles): void {
    $admin = getSessionAdmin();
    if (!$admin || !in_array($admin['role'], $roles)) {
        if (isAjaxRequest()) {
            jsonResponse(['success' => false, 'message' => 'Insufficient privileges.'], 403);
        }
        header('Location: ' . BASE_URL . 'dashboard.php?error=access_denied');
        exit();
    }
}

function isSuperAdmin(): bool {
    $admin = getSessionAdmin();
    return $admin && $admin['role'] === 'super_admin';
}

function isAjaxRequest(): bool {
    return !empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
           strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
}

/**
 * Build SQL WHERE clause to scope requests to current admin's categories.
 * Returns '' (empty string) for super_admin (no restriction).
 */
function getCategoryScope(string $tableAlias = 'r'): string {
    $admin = getSessionAdmin();
    if (!$admin || $admin['role'] === 'super_admin') return '';

    $categories = $admin['categories'];
    if (empty($categories)) return " AND 1=0"; // No access

    $db = getDB();
    $escaped = array_map(fn($c) => "'" . $db->real_escape_string($c) . "'", $categories);
    return " AND {$tableAlias}.category_id IN (" . implode(',', $escaped) . ")";
}

/**
 * Build SQL WHERE clause for station-scoped admins.
 */
function getStationScope(string $tableAlias = 'r'): string {
    $admin = getSessionAdmin();
    if (!$admin) return " AND 1=0";
    if ($admin['role'] === 'super_admin') return '';

    if ($admin['role'] === 'station_admin' && !empty($admin['station_id'])) {
        $db  = getDB();
        $sid = $db->real_escape_string($admin['station_id']);
        return " AND {$tableAlias}.assigned_station_id = '$sid'";
    }

    return getCategoryScope($tableAlias);
}

/**
 * Resolve the Agency Key ('police', 'fire', 'medical', 'rescue', 'municipal', 'superadmin')
 * based on admin role, categories, department, or an active agency lens filter.
 */
function getAdminAgencyKey(?array $admin = null): string {
    if ($admin === null) {
        $admin = getSessionAdmin();
    }
    if (!$admin) return 'superadmin';

    // Allow Super Admin to view through an Agency Lens
    if ($admin['role'] === 'super_admin') {
        if (!empty($_GET['lens']) && isset(AGENCY_PROFILES[$_GET['lens']])) {
            return $_GET['lens'];
        }
        if (!empty($_SESSION['active_agency_lens']) && isset(AGENCY_PROFILES[$_SESSION['active_agency_lens']])) {
            return $_SESSION['active_agency_lens'];
        }
        return 'superadmin';
    }

    $cats = is_array($admin['categories']) ? $admin['categories'] : [];
    $dept = strtolower($admin['department'] ?? '');

    if (in_array('police', $cats) || strpos($dept, 'police') !== false || strpos($dept, 'law') !== false) {
        return 'police';
    }
    if (in_array('fire', $cats) || in_array('fire_bus', $cats) || strpos($dept, 'fire') !== false) {
        return 'fire';
    }
    if (in_array('ambulance', $cats) || strpos($dept, 'medic') !== false || strpos($dept, 'health') !== false || strpos($dept, 'ambulan') !== false) {
        return 'medical';
    }
    if (in_array('rescue', $cats) || in_array('tracker', $cats) || strpos($dept, 'rescue') !== false || strpos($dept, 'sar') !== false) {
        return 'rescue';
    }
    if (!empty(array_intersect($cats, ['road_management', 'water_management', 'waste_management', 'sewage_management', 'clean_management', 'citizen_issue'])) || strpos($dept, 'muncip') !== false || strpos($dept, 'civic') !== false || strpos($dept, 'citizen') !== false) {
        return 'municipal';
    }

    return 'superadmin';
}

/**
 * Return an array of all category keys the current admin is authorized to manage/view.
 * For super_admin, returns all known category keys.
 */
function getAdminCategoryList(?array $admin = null): array {
    if ($admin === null) {
        $admin = getSessionAdmin();
    }
    if (!$admin) return [];
    if ($admin['role'] === 'super_admin') {
        return array_keys(CATEGORIES);
    }
    $cats = $admin['categories'] ?? [];
    if (is_string($cats)) {
        $cats = json_decode($cats, true) ?: [];
    }
    return is_array($cats) ? array_values(array_filter($cats)) : [];
}

/**
 * Check if the admin is permitted to access a specific category or service key.
 */
function isAdminAllowedCategory(string $category, ?array $admin = null): bool {
    if ($admin === null) {
        $admin = getSessionAdmin();
    }
    if (!$admin) return false;
    if ($admin['role'] === 'super_admin') return true;

    $allowed = getAdminCategoryList($admin);
    return in_array($category, $allowed, true);
}

/**
 * SQL WHERE clause to scope employee/personnel queries to the admin's permitted services/stations.
 */
function getEmployeeScopeWhere(string $stationTableAlias = 'ss', string $empTableAlias = 'e'): string {
    $admin = getSessionAdmin();
    if (!$admin) return " AND 1=0";
    if ($admin['role'] === 'super_admin') return "";

    $db = getDB();
    if ($admin['role'] === 'station_admin' && !empty($admin['station_id'])) {
        $sid = $db->real_escape_string($admin['station_id']);
        return " AND {$empTableAlias}.station_id = '$sid'";
    }

    $cats = getAdminCategoryList($admin);
    if (empty($cats)) return " AND 1=0";

    $escaped = array_map(fn($c) => "'" . $db->real_escape_string($c) . "'", $cats);
    $inList = implode(',', $escaped);

    return " AND {$stationTableAlias}.service_id IN ($inList)";
}

/**
 * SQL WHERE clause to scope service_stations queries to the admin's permitted services/stations.
 */
function getStationScopeWhere(string $stationTableAlias = 'ss'): string {
    $admin = getSessionAdmin();
    if (!$admin) return " AND 1=0";
    if ($admin['role'] === 'super_admin') return "";

    $db = getDB();
    if ($admin['role'] === 'station_admin' && !empty($admin['station_id'])) {
        $sid = $db->real_escape_string($admin['station_id']);
        return " AND {$stationTableAlias}.station_id = '$sid'";
    }

    $cats = getAdminCategoryList($admin);
    if (empty($cats)) return " AND 1=0";

    $escaped = array_map(fn($c) => "'" . $db->real_escape_string($c) . "'", $cats);
    $inList = implode(',', $escaped);

    return " AND {$stationTableAlias}.service_id IN ($inList)";
}

/**
 * SQL WHERE clause to scope citizen_services interaction queries.
 */
function getCitizenServiceScopeWhere(string $tableAlias = 'cs'): string {
    $admin = getSessionAdmin();
    if (!$admin) return " AND 1=0";
    if ($admin['role'] === 'super_admin') return "";

    $cats = getAdminCategoryList($admin);
    if (empty($cats)) return " AND 1=0";

    $db = getDB();
    $escaped = array_map(fn($c) => "'" . $db->real_escape_string($c) . "'", $cats);
    $inList = implode(',', $escaped);

    return " AND {$tableAlias}.service_id IN ($inList)";
}

/**
 * Return the full Agency Profile definition for current admin.
 */
function getAdminAgencyProfile(?array $admin = null): array {
    $key = getAdminAgencyKey($admin);
    return AGENCY_PROFILES[$key] ?? AGENCY_PROFILES['superadmin'];
}

/**
 * Return service-scoped citizen users SQL query.
 * For Super Admin: returns all registered citizens.
 * For Service Admins (e.g. Fire Service, Police, etc.): returns only citizens who have submitted requests for the admin's permitted services.
 */
function getScopedCitizensSQL(?array $admin = null, string $search = '', int $limit = 100): string {
    if ($admin === null) {
        $admin = getSessionAdmin();
    }
    $db = getDB();
    $whereSearch = "";
    if ($search !== '') {
        $q = $db->real_escape_string($search);
        $whereSearch = " AND (u.name LIKE '%$q%' OR u.email LIKE '%$q%' OR u.phone LIKE '%$q%' OR u.city LIKE '%$q%' OR u.zone LIKE '%$q%') ";
    }

    if (!$admin || $admin['role'] === 'super_admin') {
        return "SELECT u.id, u.name, u.email, u.phone, u.city, u.zone, u.created_at, 0 AS request_count
                FROM users u
                WHERE u.role = 'citizen' $whereSearch
                ORDER BY u.id DESC LIMIT $limit";
    }

    $cats = getAdminCategoryList($admin);
    if (empty($cats)) {
        return "SELECT u.id, u.name, u.email, u.phone, u.city, u.zone, u.created_at, 0 AS request_count FROM users u WHERE 1=0";
    }

    $escaped = array_map(fn($c) => "'" . $db->real_escape_string($c) . "'", $cats);
    $inList = implode(',', $escaped);

    $tables = [];
    foreach ($cats as $cat) {
        if (isset(CATEGORY_TABLE_MAP[$cat])) {
            $tables[CATEGORY_TABLE_MAP[$cat]] = true;
        }
    }
    $tables = array_keys($tables);

    $subQueries = [];
    foreach ($tables as $tbl) {
        $subQueries[] = "SELECT citizen_name, contact_number FROM `$tbl` WHERE category_id IN ($inList) AND (contact_number != '' OR citizen_name != '')";
    }
    $subQueries[] = "SELECT citizen_name, contact_number FROM `citizen_services` WHERE service_id IN ($inList) AND (contact_number != '' OR citizen_name != '')";

    $reqUnion = implode(' UNION ALL ', $subQueries);

    return "SELECT u.id, u.name, u.email, u.phone, u.city, u.zone, u.created_at, COUNT(req.contact_number) AS request_count
            FROM users u
            INNER JOIN ($reqUnion) req 
               ON (
                   (u.phone != '' AND req.contact_number != '' AND (u.phone = req.contact_number OR req.contact_number LIKE CONCAT('%', u.phone) OR u.phone LIKE CONCAT('%', req.contact_number)))
                   OR (u.name != '' AND req.citizen_name != '' AND LOWER(TRIM(u.name)) = LOWER(TRIM(req.citizen_name)))
               )
            WHERE u.role = 'citizen' $whereSearch
            GROUP BY u.id, u.name, u.email, u.phone, u.city, u.zone, u.created_at
            ORDER BY u.id DESC LIMIT $limit";
}



