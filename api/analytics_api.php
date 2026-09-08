<?php
// ============================================================
// City Planner — Analytics API
// ============================================================
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/rbac.php';
header('Content-Type: application/json; charset=UTF-8');
startSecureSession(); requireLogin(); requirePermission('analytics.view');

$db    = getDB();
$admin = getSessionAdmin();
$action= $_GET['action'] ?? 'charts';
$days  = max(7, min(90, (int)($_GET['days'] ?? 30)));

$scope = '';
if ($admin['role'] !== 'super_admin' && !empty($admin['categories'])) {
    $cats  = array_map(fn($c) => "'" . $db->real_escape_string($c) . "'", $admin['categories']);
    $scope = " AND category_id IN (" . implode(',', $cats) . ")";
}

function analyticsUnion(string $fields, string $where = ''): string {
    global $scope;
    $parts = [];
    foreach (ALL_REQUEST_TABLES as $tbl) {
        $parts[] = "SELECT $fields FROM `$tbl` WHERE 1=1 $scope $where";
    }
    return implode(" UNION ALL ", $parts);
}

switch ($action) {
    case 'charts':
        // 1. Requests per day
        $dailyUnion = analyticsUnion("DATE(created_at) AS day, status, urgency, category_id",
            " AND created_at >= DATE_SUB(NOW(), INTERVAL $days DAY)");
        $r1 = $db->query("SELECT day, COUNT(*) AS total, SUM(status='resolved') AS resolved, SUM(status NOT IN ('resolved','cancelled')) AS active
            FROM ($dailyUnion) AS t GROUP BY day ORDER BY day ASC");
        $daily = [];
        while ($r = $r1->fetch_assoc()) $daily[] = $r;

        // 2. By category
        $catUnion = analyticsUnion("category_id, category_name, urgency",
            " AND created_at >= DATE_SUB(NOW(), INTERVAL $days DAY)");
        $r2 = $db->query("SELECT category_id, category_name, COUNT(*) AS total FROM ($catUnion) AS t GROUP BY category_id, category_name ORDER BY total DESC");
        $byCategory = [];
        while ($r = $r2->fetch_assoc()) {
            $cat = CATEGORIES[$r['category_id']] ?? null;
            $r['color'] = $cat['color'] ?? '#3B82F6';
            $r['icon']  = $cat['icon'] ?? 'bi-question';
            $byCategory[] = $r;
        }

        // 3. By urgency
        $urgUnion = analyticsUnion("urgency", " AND created_at >= DATE_SUB(NOW(), INTERVAL $days DAY)");
        $r3 = $db->query("SELECT urgency, COUNT(*) AS total FROM ($urgUnion) AS t GROUP BY urgency");
        $byUrgency = [];
        while ($r = $r3->fetch_assoc()) $byUrgency[] = $r;

        // 4. Resolution rate by dept
        $rateUnion = analyticsUnion("category_id, status",
            " AND created_at >= DATE_SUB(NOW(), INTERVAL $days DAY)");
        $r4 = $db->query("SELECT category_id, COUNT(*) AS total, SUM(status='resolved') AS resolved
            FROM ($rateUnion) AS t GROUP BY category_id");
        $resRate = [];
        while ($r = $r4->fetch_assoc()) {
            $rate = $r['total'] > 0 ? round($r['resolved'] / $r['total'] * 100, 1) : 0;
            $cat  = CATEGORIES[$r['category_id']] ?? null;
            $resRate[] = ['category_id' => $r['category_id'], 'name' => $cat['name'] ?? $r['category_id'], 'rate' => $rate, 'total' => (int)$r['total']];
        }

        // 5. Avg response time by dept
        $avgUnion = analyticsUnion(
            "category_id, TIMESTAMPDIFF(MINUTE, created_at, COALESCE(resolved_at, NOW())) AS mins",
            " AND created_at >= DATE_SUB(NOW(), INTERVAL $days DAY) AND resolved_at IS NOT NULL"
        );
        $r5 = $db->query("SELECT category_id, AVG(mins) AS avg_min FROM ($avgUnion) AS t GROUP BY category_id");
        $avgResp = [];
        while ($r = $r5->fetch_assoc()) {
            $cat = CATEGORIES[$r['category_id']] ?? null;
            $avgResp[] = ['name' => $cat['name'] ?? $r['category_id'], 'avg_min' => round($r['avg_min'])];
        }

        // 6. Rating distribution
        $r6 = $db->query("SELECT rating_stars, COUNT(*) AS cnt FROM request_feedback WHERE rating_stars IS NOT NULL GROUP BY rating_stars ORDER BY rating_stars");
        $ratings = [];
        while ($r = $r6->fetch_assoc()) $ratings[] = $r;

        // 7. Emergency vs Municipal split
        $splitUnion = analyticsUnion("category_id", " AND created_at >= DATE_SUB(NOW(), INTERVAL $days DAY)");
        $emergencyCats = "'fire','fire_bus','ambulance','rescue','police','tracker','emergency'";
        $r7 = $db->query("SELECT SUM(category_id IN ($emergencyCats)) AS emergency, SUM(category_id NOT IN ($emergencyCats)) AS municipal FROM ($splitUnion) AS t");
        $split = ($r7 && $r7 instanceof mysqli_result) ? $r7->fetch_assoc() : ['emergency' => 0, 'municipal' => 0];

        jsonResponse(['success' => true, 'period_days' => $days, 'daily' => $daily, 'by_category' => $byCategory,
            'by_urgency' => $byUrgency, 'resolution_rate' => $resRate, 'avg_response' => $avgResp,
            'ratings' => $ratings, 'split' => $split]);
        break;

    default:
        jsonResponse(['success' => false, 'message' => 'Unknown action.'], 400);
}
