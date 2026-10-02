<?php
// ============================================================
// City Planner Web Admin — Dashboard Stats API
// GET /api/dashboard_stats.php?action=...
// ============================================================
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/rbac.php';

header('Content-Type: application/json; charset=UTF-8');
startSecureSession();

$action = $_GET['action'] ?? 'full';

// Allow unauthenticated live_count for login page display
if ($action !== 'live_count') {
    requireLogin();
    requirePermission('dashboard.view');
}

$db = getDB();
$admin = getSessionAdmin();

// ── Build category scope filter ────────────────────────────────
$categoryFilter = '';
$adminCats = ($admin && $admin['role'] !== 'super_admin') ? getAdminCategoryList($admin) : array_keys(CATEGORIES);

$reqCats = getParam('categories');
if ($reqCats !== '') {
    $requested = array_filter(array_map('trim', explode(',', $reqCats)));
    $filteredCats = array_intersect($requested, $adminCats);
    if (empty($filteredCats)) {
        $categoryFilter = " AND 1=0";
    } else {
        $escaped = array_map(fn($c) => "'" . $db->real_escape_string($c) . "'", $filteredCats);
        $categoryFilter = " AND category_id IN (" . implode(',', $escaped) . ")";
    }
} elseif ($admin && $admin['role'] !== 'super_admin') {
    if (empty($adminCats)) {
        $categoryFilter = " AND 1=0";
    } else {
        $escaped = array_map(fn($c) => "'" . $db->real_escape_string($c) . "'", $adminCats);
        $categoryFilter = " AND category_id IN (" . implode(',', $escaped) . ")";
    }
}

// Optional urgency filter
$reqUrgency = getParam('urgency');
$urgencyFilter = '';
if ($reqUrgency === 'emergency') {
    $urgencyFilter = " AND urgency = 'emergency'";
} elseif ($reqUrgency === 'high') {
    $urgencyFilter = " AND urgency IN ('emergency', 'high')";
} elseif ($reqUrgency && in_array($reqUrgency, ['medium', 'low'])) {
    $urgencyFilter = " AND urgency = '" . $db->real_escape_string($reqUrgency) . "'";
}

// ── All-tables UNION query builder ─────────────────────────────
function buildUnionQuery(string $select, string $where = '', array $tables = null): string {
    $tables = $tables ?: ALL_REQUEST_TABLES;
    $parts  = [];
    foreach ($tables as $tbl) {
        $parts[] = "SELECT $select FROM `$tbl` WHERE 1=1 $where";
    }
    return implode(" UNION ALL ", $parts);
}

switch ($action) {

    // ── LIVE COUNT (unauthenticated — login page) ──────────────
    case 'live_count':
        $union = buildUnionQuery('1', "AND status NOT IN ('resolved','cancelled')");
        $result = $db->query("SELECT COUNT(*) AS cnt FROM ($union) AS t");
        $cnt = $result ? (int)$result->fetch_assoc()['cnt'] : 0;
        jsonResponse(['active_incidents' => $cnt]);
        break;

    // ── FULL DASHBOARD ────────────────────────────────────────
    case 'full':
    default:
        // 1. Active incidents (not resolved/cancelled)
        $activeUnion = buildUnionQuery('status, urgency', "AND status NOT IN ('resolved','cancelled') $categoryFilter");
        $r = $db->query("SELECT COUNT(*) AS total,
            SUM(urgency='emergency') AS emergency_count,
            SUM(urgency='high') AS high_count,
            SUM(status='submitted') AS submitted_count,
            SUM(status='dispatched' OR status='acknowledged') AS dispatched_count,
            SUM(status='inProgress') AS in_progress_count
            FROM ($activeUnion) AS t");
        $active = $r ? $r->fetch_assoc() : [];

        // 2. Today's stats
        $todayUnion = buildUnionQuery('status, urgency, created_at', "AND DATE(created_at) = CURDATE() $categoryFilter");
        $r2 = $db->query("SELECT COUNT(*) AS total_today,
            SUM(status IN ('resolved')) AS resolved_today,
            SUM(urgency='emergency') AS emergencies_today
            FROM ($todayUnion) AS t");
        $today = $r2 ? $r2->fetch_assoc() : [];

        // 3. Total resolved
        $resolvedUnion = buildUnionQuery('1', "AND status='resolved' $categoryFilter");
        $r3 = $db->query("SELECT COUNT(*) AS cnt FROM ($resolvedUnion) AS t");
        $totalResolved = $r3 ? (int)$r3->fetch_assoc()['cnt'] : 0;

        // 4. Avg resolution time (in hours) from dispatched_at to resolved_at for recent 30 days
        $avgUnion = buildUnionQuery(
            'TIMESTAMPDIFF(MINUTE, created_at, COALESCE(resolved_at, NOW())) AS minutes',
            "AND status='resolved' AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) AND resolved_at IS NOT NULL $categoryFilter",
            array_keys(CATEGORY_TABLE_MAP)
        );
        $r4 = $db->query("SELECT AVG(minutes) AS avg_min FROM ($avgUnion) AS t");
        $avgMin = $r4 ? round($r4->fetch_assoc()['avg_min'] ?? 0) : 0;
        $avgResponseDisplay = $avgMin > 60
            ? round($avgMin / 60, 1) . 'h'
            : $avgMin . 'm';

        // 5. Dept breakdown (active incidents per category)
        $deptUnion = buildUnionQuery('category_id, urgency', "AND status NOT IN ('resolved','cancelled') $categoryFilter");
        $r5 = $db->query("SELECT category_id, COUNT(*) AS cnt, SUM(urgency='emergency') AS emergencies
            FROM ($deptUnion) AS t GROUP BY category_id ORDER BY cnt DESC");
        $deptBreakdown = [];
        while ($row = $r5->fetch_assoc()) {
            $cat = CATEGORIES[$row['category_id']] ?? null;
            $deptBreakdown[] = [
                'category_id'   => $row['category_id'],
                'name'          => $cat ? $cat['name'] : ucfirst(str_replace('_', ' ', $row['category_id'])),
                'icon'          => $cat['icon'] ?? 'bi-question-circle',
                'color'         => $cat['color'] ?? '#94A3B8',
                'count'         => (int)$row['cnt'],
                'emergencies'   => (int)$row['emergencies'],
            ];
        }

        // 6. Live tracking (active tracking records)
        $r6 = $db->query("SELECT lt.request_id, lt.current_latitude AS lat, lt.current_longitude AS lng,
            lt.updated_at, lt.is_tracking_active,
            TIMESTAMPDIFF(MINUTE, lt.updated_at, NOW()) AS minutes_ago
            FROM live_tracking lt
            WHERE lt.is_tracking_active = 1
            ORDER BY lt.updated_at DESC LIMIT 20");
        $liveTracking = [];
        if ($r6 && $r6 instanceof mysqli_result) {
            while ($row = $r6->fetch_assoc()) $liveTracking[] = $row;
        }

        // 7. Recent incidents (last 10)
        $recentUnion = buildUnionQuery(
            "request_id, citizen_name, category_id, category_name, urgency, status, location_address, created_at, latitude, longitude",
            "AND 1=1 $categoryFilter"
        );
        $r7 = $db->query("SELECT * FROM ($recentUnion) AS t ORDER BY created_at DESC LIMIT 15");
        $recentIncidents = [];
        if ($r7 && $r7 instanceof mysqli_result) {
            while ($row = $r7->fetch_assoc()) {
                $cat = CATEGORIES[$row['category_id']] ?? null;
                $recentIncidents[] = [
                    'request_id'       => $row['request_id'],
                    'citizen_name'     => $row['citizen_name'],
                    'category_id'      => $row['category_id'],
                    'category_name'    => $row['category_name'],
                    'category_icon'    => $cat['icon'] ?? 'bi-question-circle',
                    'category_color'   => $cat['color'] ?? '#94A3B8',
                    'urgency'          => $row['urgency'],
                    'status'           => $row['status'],
                    'location_address' => $row['location_address'],
                    'latitude'         => (float)$row['latitude'],
                    'longitude'        => (float)$row['longitude'],
                    'created_at'       => $row['created_at'],
                    'time_ago'         => timeAgo($row['created_at']),
                ];
            }
        }

        // 8. Station workload
        $stScope = getStationScopeWhere('ss');
        $r8 = $db->query("SELECT ss.station_id, ss.name AS station_name, ss.service_label,
            (SELECT COUNT(*) FROM employees WHERE station_id = ss.station_id) AS employee_count,
            ss.city
            FROM service_stations ss WHERE 1=1 $stScope ORDER BY ss.service_label, ss.name LIMIT 20");
        $stations = [];
        if ($r8 && $r8 instanceof mysqli_result) {
            while ($row = $r8->fetch_assoc()) $stations[] = $row;
        }

        // 9. SLA breach check
        $slaUnion = buildUnionQuery(
            "request_id, category_id, urgency, created_at, status",
            "AND status NOT IN ('resolved','cancelled') $categoryFilter"
        );
        $r9 = $db->query("SELECT COUNT(*) AS breaches FROM ($slaUnion) AS r
            INNER JOIN sla_config s ON s.category_id = r.category_id AND s.urgency_level = r.urgency
            WHERE TIMESTAMPDIFF(MINUTE, r.created_at, NOW()) > s.response_time_minutes");
        $slaBreaches = $r9 ? (int)$r9->fetch_assoc()['breaches'] : 0;

        // 10. 7-day trend
        $trendUnion = buildUnionQuery('DATE(created_at) AS day, status', "AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) $categoryFilter");
        $r10 = $db->query("SELECT day, COUNT(*) AS total, SUM(status='resolved') AS resolved
            FROM ($trendUnion) AS t GROUP BY day ORDER BY day ASC");
        $trend = [];
        if ($r10 && $r10 instanceof mysqli_result) {
            while ($row = $r10->fetch_assoc()) $trend[] = $row;
        }

        // 11. Offline emergency queue
        $r11 = $db->query("SELECT COUNT(*) AS cnt FROM offline_emergency_requests");
        $offlineCount = $r11 ? (int)$r11->fetch_assoc()['cnt'] : 0;

        // 12. Smart insights
        $insights = generateInsights($active, $today, $slaBreaches, $avgMin, $deptBreakdown);

        jsonResponse([
            'success'         => true,
            'timestamp'       => date('Y-m-d H:i:s'),
            'active'          => $active,
            'today'           => $today,
            'total_resolved'  => $totalResolved,
            'avg_response'    => $avgResponseDisplay,
            'avg_min_raw'     => $avgMin,
            'dept_breakdown'  => $deptBreakdown,
            'live_tracking'   => $liveTracking,
            'recent_incidents'=> $recentIncidents,
            'stations'        => $stations,
            'sla_breaches'    => $slaBreaches,
            'trend'           => $trend,
            'offline_count'   => $offlineCount,
            'insights'        => $insights,
        ]);
        break;

    // ── MAP MARKERS ──────────────────────────────────────────
    case 'map_markers':
        $mapUnion = buildUnionQuery(
            "request_id, category_id, category_name, urgency, status, latitude, longitude, location_address, created_at",
            "AND latitude != 0 AND longitude != 0 AND status NOT IN ('resolved','cancelled') $categoryFilter $urgencyFilter"
        );
        $rm = $db->query("SELECT * FROM ($mapUnion) AS t ORDER BY created_at DESC LIMIT 200");
        $markers = [];
        while ($row = $rm->fetch_assoc()) {
            $cat = CATEGORIES[$row['category_id']] ?? null;
            $markers[] = [
                'id'         => $row['request_id'],
                'lat'        => (float)$row['latitude'],
                'lng'        => (float)$row['longitude'],
                'urgency'    => $row['urgency'],
                'status'     => $row['status'],
                'category'   => $row['category_name'],
                'category_id'=> $row['category_id'],
                'color'      => $cat['color'] ?? '#3B82F6',
                'address'    => $row['location_address'],
                'time'       => timeAgo($row['created_at']),
            ];
        }
        // Also add live tracking markers (scoped to admin permitted requests)
        $trackScopeUnion = buildUnionQuery("request_id", "AND status NOT IN ('resolved','cancelled') $categoryFilter");
        $rlt = $db->query("SELECT lt.* FROM live_tracking lt
            INNER JOIN ($trackScopeUnion) AS r ON r.request_id = lt.request_id
            WHERE lt.is_tracking_active=1 AND lt.current_latitude != 0 LIMIT 50");
        $trackers = [];
        if ($rlt && $rlt instanceof mysqli_result) {
            while ($row = $rlt->fetch_assoc()) {
                $trackers[] = [
                    'id'    => $row['request_id'],
                    'lat'   => (float)$row['current_latitude'],
                    'lng'   => (float)$row['current_longitude'],
                    'stale' => ((int)($row['minutes_ago'] ?? 0)) > 10,
                    'updated_at' => $row['updated_at'],
                ];
            }
        }
        // Also include station markers for map overlay
        $stScope = getStationScopeWhere('ss');
        $stQ = $db->query("SELECT ss.station_id, ss.name, ss.service_id, ss.service_label, ss.email, ss.phone, ss.city,
            ss.latitude, ss.longitude, ss.address,
            (SELECT COUNT(*) FROM employees WHERE station_id = ss.station_id) AS staff_count
            FROM service_stations ss WHERE 1=1 $stScope ORDER BY ss.service_label, ss.name ASC");
        $stationMarkers = [];
        if ($stQ && $stQ instanceof mysqli_result) {
            while ($row = $stQ->fetch_assoc()) {
                $svcMeta = CATEGORIES[$row['service_id']] ?? null;
                $stationMarkers[] = [
                    'station_id'    => $row['station_id'],
                    'name'          => $row['name'],
                    'service_id'    => $row['service_id'],
                    'service_label' => $row['service_label'],
                    'email'         => $row['email'],
                    'phone'         => $row['phone'],
                    'city'          => $row['city'],
                    'address'       => $row['address'] ?: $row['city'],
                    'lat'           => (float)$row['latitude'],
                    'lng'           => (float)$row['longitude'],
                    'staff_count'   => (int)$row['staff_count'],
                    'color'         => $svcMeta['color'] ?? '#6366F1',
                    'icon'          => $svcMeta['icon'] ?? 'bi-building-fill',
                ];
            }
        }
        jsonResponse(['success' => true, 'markers' => $markers, 'trackers' => $trackers, 'stations' => $stationMarkers]);
        break;

    // ── STATION MARKERS (dedicated endpoint) ─────────────────
    case 'station_markers':
        $stScope = getStationScopeWhere('ss');
        $search = isset($_GET['search']) ? $db->real_escape_string(trim($_GET['search'])) : '';
        $serviceFilter = isset($_GET['service']) ? $db->real_escape_string(trim($_GET['service'])) : '';
        $searchWhere = $search ? " AND (ss.name LIKE '%$search%' OR ss.city LIKE '%$search%' OR ss.station_id LIKE '%$search%' OR ss.address LIKE '%$search%')" : '';
        $svcWhere = $serviceFilter ? " AND ss.service_id = '$serviceFilter'" : '';
        $stQ = $db->query("SELECT ss.station_id, ss.name, ss.service_id, ss.service_label, ss.email, ss.phone, ss.city,
            ss.latitude, ss.longitude, ss.address,
            (SELECT COUNT(*) FROM employees WHERE station_id = ss.station_id) AS staff_count
            FROM service_stations ss WHERE 1=1 $stScope $searchWhere $svcWhere ORDER BY ss.service_label, ss.name ASC");
        $stations = [];
        if ($stQ && $stQ instanceof mysqli_result) {
            while ($row = $stQ->fetch_assoc()) {
                $svcMeta = CATEGORIES[$row['service_id']] ?? null;
                $stations[] = [
                    'station_id'    => $row['station_id'],
                    'name'          => $row['name'],
                    'service_id'    => $row['service_id'],
                    'service_label' => $row['service_label'],
                    'email'         => $row['email'],
                    'phone'         => $row['phone'],
                    'city'          => $row['city'],
                    'address'       => $row['address'] ?: $row['city'],
                    'lat'           => (float)$row['latitude'],
                    'lng'           => (float)$row['longitude'],
                    'staff_count'   => (int)$row['staff_count'],
                    'color'         => $svcMeta['color'] ?? '#6366F1',
                    'icon'          => $svcMeta['icon'] ?? 'bi-building-fill',
                ];
            }
        }
        jsonResponse(['success' => true, 'stations' => $stations]);
        break;
}

// ── Helpers ───────────────────────────────────────────────────
function timeAgo(string $datetime): string {
    $diff = time() - strtotime($datetime);
    if ($diff < 60)    return 'Just now';
    if ($diff < 3600)  return round($diff / 60) . 'm ago';
    if ($diff < 86400) return round($diff / 3600) . 'h ago';
    return round($diff / 86400) . 'd ago';
}

function generateInsights(array $active, array $today, int $slaBreaches, int $avgMin, array $deptBreakdown): array {
    $insights = [];

    $emergency = (int)($active['emergency_count'] ?? 0);
    if ($emergency > 0) {
        $insights[] = [
            'type'    => 'critical',
            'icon'    => 'bi-exclamation-triangle-fill',
            'title'   => "$emergency Emergency-Level Incident" . ($emergency > 1 ? 's' : '') . " Active",
            'message' => 'Require immediate dispatch and escalation.',
        ];
    }

    if ($slaBreaches > 0) {
        $insights[] = [
            'type'    => 'warning',
            'icon'    => 'bi-clock-fill',
            'title'   => "$slaBreaches SLA Breach" . ($slaBreaches > 1 ? 'es' : '') . " Detected",
            'message' => 'Incidents have exceeded response time thresholds.',
        ];
    }

    $inProgress = (int)($active['in_progress_count'] ?? 0);
    if ($inProgress > 5) {
        $insights[] = [
            'type'    => 'info',
            'icon'    => 'bi-activity',
            'title'   => "$inProgress Incidents In Progress",
            'message' => 'Field teams are actively engaged.',
        ];
    }

    $todayResolved = (int)($today['resolved_today'] ?? 0);
    $todayTotal    = (int)($today['total_today'] ?? 1);
    $resolveRate   = $todayTotal > 0 ? round($todayResolved / $todayTotal * 100) : 0;
    if ($resolveRate >= 80) {
        $insights[] = [
            'type'    => 'success',
            'icon'    => 'bi-check-circle-fill',
            'title'   => "Excellent Resolution Rate: $resolveRate%",
            'message' => "Today's resolution rate is performing above target.",
        ];
    }

    if (!empty($deptBreakdown)) {
        $top = $deptBreakdown[0];
        if ($top['count'] > 3) {
            $insights[] = [
                'type'    => 'info',
                'icon'    => 'bi-building',
                'title'   => "{$top['name']} Has Highest Load",
                'message' => "{$top['count']} active incidents assigned.",
            ];
        }
    }

    return array_slice($insights, 0, 5);
}
