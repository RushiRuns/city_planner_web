<?php
// ============================================================
// City Planner Web Admin — Requests API
// GET  ?action=list|single|export
// POST ?action=assign|update_status|resolve|cancel
// ============================================================
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/rbac.php';
require_once __DIR__ . '/../includes/audit.php';

header('Content-Type: application/json; charset=UTF-8');
startSecureSession();
requireLogin();
requirePermission('requests.view');

$db     = getDB();
$admin  = getSessionAdmin();
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? ($method === 'POST' ? (json_decode(file_get_contents('php://input'), true)['action'] ?? 'list') : 'list');
$input  = $method === 'POST' ? (json_decode(file_get_contents('php://input'), true) ?? []) : [];

// ── Category scope ─────────────────────────────────────────────
function getScopeWhere(): string {
    global $admin, $db;
    if ($admin['role'] === 'super_admin') return '';
    $cats = getAdminCategoryList($admin);
    if (empty($cats)) return " AND 1=0";
    $escaped = array_map(fn($c) => "'" . $db->real_escape_string($c) . "'", $cats);
    return " AND category_id IN (" . implode(',', $escaped) . ")";
}

// ── Build UNION ALL across all request tables ─────────────────
function buildRequestUnion(string $extraWhere = ''): string {
    global $db;
    $scope  = getScopeWhere();
    $fields = "request_id, citizen_name, contact_number, category_id, category_name,
               description, location_address, latitude, longitude, urgency, status,
               resolution_info, image_data, created_at,
               assigned_employee_id, assigned_station_id, dispatched_at, resolved_at";
    $parts = [];
    foreach (ALL_REQUEST_TABLES as $tbl) {
        $parts[] = "SELECT $fields FROM `$tbl` WHERE 1=1 $scope $extraWhere";
    }
    return implode(" UNION ALL ", $parts);
}

switch ($action) {

    // ── LIST ──────────────────────────────────────────────────
    case 'list':
        $page    = max(1, (int)($_GET['page'] ?? 1));
        $perPage = min(100, max(1, (int)($_GET['per_page'] ?? 20)));
        $offset  = ($page - 1) * $perPage;

        // Filters
        $where = '';
        if (!empty($_GET['status'])) {
            $s     = $db->real_escape_string($_GET['status']);
            $where .= " AND status = '$s'";
        }
        if (!empty($_GET['urgency'])) {
            $u     = $db->real_escape_string($_GET['urgency']);
            $where .= " AND urgency = '$u'";
        }
        if (!empty($_GET['category'])) {
            $c     = $db->real_escape_string($_GET['category']);
            $where .= " AND category_id = '$c'";
        }
        if (!empty($_GET['search'])) {
            $q     = $db->real_escape_string(trim($_GET['search']));
            $where .= " AND (citizen_name LIKE '%$q%' OR request_id LIKE '%$q%' OR location_address LIKE '%$q%' OR contact_number LIKE '%$q%')";
        }
        if (!empty($_GET['date_from'])) {
            $df    = $db->real_escape_string($_GET['date_from']);
            $where .= " AND created_at >= '$df 00:00:00'";
        }
        if (!empty($_GET['date_to'])) {
            $dt    = $db->real_escape_string($_GET['date_to']);
            $where .= " AND created_at <= '$dt 23:59:59'";
        }

        $sortCol = in_array($_GET['sort'] ?? '', ['created_at','urgency','status','citizen_name','request_id']) ? $db->real_escape_string($_GET['sort']) : 'created_at';
        $sortDir = strtoupper($_GET['dir'] ?? 'DESC') === 'ASC' ? 'ASC' : 'DESC';

        $union = buildRequestUnion($where);
        $total = 0;
        $cntQ = $db->query("SELECT COUNT(*) AS cnt FROM ($union) AS t");
        if ($cntQ && $cntQ instanceof mysqli_result) {
            $total = (int)$cntQ->fetch_assoc()['cnt'];
        }

        $data = $db->query("SELECT * FROM ($union) AS t ORDER BY $sortCol $sortDir LIMIT $perPage OFFSET $offset");
        $rows = [];
        if ($data && $data instanceof mysqli_result) {
            while ($r = $data->fetch_assoc()) {
                $cat = CATEGORIES[$r['category_id']] ?? null;
                // Get feedback if resolved
                $feedback = null;
                if ($r['status'] === 'resolved') {
                    $fq = $db->query("SELECT rating_stars, rating_label FROM request_feedback WHERE request_id='" . $db->real_escape_string($r['request_id']) . "' LIMIT 1");
                    if ($fq && $fq instanceof mysqli_result) $feedback = $fq->fetch_assoc();
                }
                $rows[] = [
                    'request_id'       => $r['request_id'],
                    'citizen_name'     => $r['citizen_name'],
                    'contact_number'   => $r['contact_number'],
                    'category_id'      => $r['category_id'],
                    'category_name'    => $r['category_name'],
                    'category_icon'    => $cat['icon'] ?? 'bi-question-circle',
                    'category_color'   => $cat['color'] ?? '#3B82F6',
                    'description'      => $r['description'],
                    'location_address' => $r['location_address'],
                    'latitude'         => (float)$r['latitude'],
                    'longitude'        => (float)$r['longitude'],
                    'urgency'          => $r['urgency'],
                    'status'           => $r['status'],
                    'resolution_info'  => $r['resolution_info'],
                    'has_image'        => !empty($r['image_data']),
                    'created_at'       => $r['created_at'],
                    'time_ago'         => timeAgo($r['created_at']),
                    'assigned_station' => $r['assigned_station_id'],
                    'assigned_employee'=> $r['assigned_employee_id'],
                    'dispatched_at'    => $r['dispatched_at'],
                    'resolved_at'      => $r['resolved_at'],
                    'feedback'         => $feedback,
                ];
            }
        }

        jsonResponse([
            'success'    => true,
            'data'       => $rows,
            'pagination' => getPagination($total, $page, $perPage),
        ]);
        break;

    // ── SINGLE REQUEST ────────────────────────────────────────
    case 'single':
        $rid = $db->real_escape_string($_GET['id'] ?? '');
        if (!$rid) jsonResponse(['success' => false, 'message' => 'Request ID required.'], 400);

        $union = buildRequestUnion(" AND request_id = '$rid'");
        $sQ = $db->query("SELECT * FROM ($union) AS t LIMIT 1");
        $r = ($sQ && $sQ instanceof mysqli_result) ? $sQ->fetch_assoc() : null;
        if (!$r) jsonResponse(['success' => false, 'message' => 'Request not found.'], 404);

        // Feedback
        $feedback = null;
        $fq = $db->query("SELECT * FROM request_feedback WHERE request_id='$rid' LIMIT 1");
        if ($fq && $fq instanceof mysqli_result) $feedback = $fq->fetch_assoc();

        // Live tracking
        $tracking = null;
        $tq = $db->query("SELECT * FROM live_tracking WHERE request_id='$rid' LIMIT 1");
        if ($tq && $tq instanceof mysqli_result) $tracking = $tq->fetch_assoc();

        // Station info
        $station = null;
        if (!empty($r['assigned_station_id'])) {
            $sid = $db->real_escape_string($r['assigned_station_id']);
            $sq  = $db->query("SELECT * FROM service_stations WHERE station_id='$sid' LIMIT 1");
            if ($sq && $sq instanceof mysqli_result) $station = $sq->fetch_assoc();
        }

        $cat = CATEGORIES[$r['category_id']] ?? null;
        jsonResponse([
            'success'  => true,
            'request'  => array_merge($r, [
                'category_icon'  => $cat['icon'] ?? 'bi-question-circle',
                'category_color' => $cat['color'] ?? '#3B82F6',
                'has_image'      => !empty($r['image_data']),
                'time_ago'       => timeAgo($r['created_at']),
            ]),
            'feedback' => $feedback,
            'tracking' => $tracking,
            'station'  => $station,
        ]);
        break;

    // ── UPDATE STATUS ─────────────────────────────────────────
    case 'update_status':
        requirePermission('requests.update_status');
        if (!validateCsrf() && !isAjaxRequest()) jsonResponse(['success' => false, 'message' => 'CSRF validation failed.'], 403);

        $rid       = $db->real_escape_string($input['request_id'] ?? '');
        $newStatus = $db->real_escape_string($input['status'] ?? '');
        $notes     = $db->real_escape_string($input['notes'] ?? '');
        $validStatuses = ['submitted','acknowledged','dispatched','inProgress','resolved','cancelled'];

        if (!$rid || !in_array($newStatus, $validStatuses)) {
            jsonResponse(['success' => false, 'message' => 'Invalid request ID or status.'], 400);
        }

        // Find which table this request is in
        $table = findRequestTable($rid, $db);
        if (!$table) jsonResponse(['success' => false, 'message' => 'Request not found.'], 404);

        // Verify admin has jurisdiction over this incident's category
        $chk = $db->query("SELECT category_id, status FROM `$table` WHERE request_id='$rid' LIMIT 1");
        $chkRow = $chk ? $chk->fetch_assoc() : null;
        if (!$chkRow || !isAdminAllowedCategory($chkRow['category_id'], $admin)) {
            jsonResponse(['success' => false, 'message' => 'Unauthorized: Incident is outside your service jurisdiction.'], 403);
        }

        $oldStatus = $chkRow['status'] ?? '';

        // Timestamp columns based on status
        $extraSet = '';
        if ($newStatus === 'dispatched')  $extraSet = ", dispatched_at = NOW()";
        if ($newStatus === 'resolved')    $extraSet = ", resolved_at = NOW()";
        if ($newStatus === 'acknowledged') $extraSet = ", acknowledged_at = NOW()";

        // Resolution info
        $resolutionSet = '';
        if (!empty($input['resolution_info'])) {
            $ri = $db->real_escape_string($input['resolution_info']);
            $resolutionSet = ", resolution_info = '$ri'";
        }

        $db->query("UPDATE `$table` SET status='$newStatus' $extraSet $resolutionSet WHERE request_id='$rid'");

        if ($db->affected_rows === 0 && $oldStatus === $newStatus) {
            jsonResponse(['success' => true, 'message' => "Status already '$newStatus'."]);
        } elseif ($db->affected_rows === 0) {
            jsonResponse(['success' => false, 'message' => 'Update failed or no change.'], 500);
        }

        // Also forward to existing City_planner PHP endpoint for Flutter sync
        $legacyUrl = 'http://localhost' . LEGACY_API_URL . 'update_request_status.php';
        $payload   = json_encode(['request_id' => $rid, 'status' => $newStatus, 'resolution_info' => $input['resolution_info'] ?? '']);
        @file_get_contents($legacyUrl, false, stream_context_create([
            'http' => ['method' => 'POST', 'header' => 'Content-Type: application/json', 'content' => $payload, 'timeout' => 2],
        ]));

        logAudit('status_update', 'request', $rid, $oldStatus, $newStatus);

        jsonResponse(['success' => true, 'message' => "Status updated to '$newStatus'."]);
        break;

    // ── ASSIGN ────────────────────────────────────────────────
    case 'assign':
        requirePermission('requests.assign');
        $rid    = $db->real_escape_string($input['request_id'] ?? '');
        $empId  = (int)($input['employee_id'] ?? 0);
        $stnId  = $db->real_escape_string($input['station_id'] ?? '');

        $table = findRequestTable($rid, $db);
        if (!$table) jsonResponse(['success' => false, 'message' => 'Request not found.'], 404);

        // Verify admin has jurisdiction over this incident's category
        $chk = $db->query("SELECT category_id FROM `$table` WHERE request_id='$rid' LIMIT 1");
        $chkRow = $chk ? $chk->fetch_assoc() : null;
        if (!$chkRow || !isAdminAllowedCategory($chkRow['category_id'], $admin)) {
            jsonResponse(['success' => false, 'message' => 'Unauthorized: Incident is outside your service jurisdiction.'], 403);
        }

        $db->query("UPDATE `$table` SET assigned_employee_id=$empId, assigned_station_id='$stnId', assigned_at=NOW() WHERE request_id='$rid'");

        logAudit('assign_employee', 'request', $rid, '', "employee:$empId station:$stnId");
        jsonResponse(['success' => true, 'message' => 'Assignment saved.']);
        break;

    // ── REPORT PREVIEW ─────────────────────────────────────────
    case 'report_preview':
        requirePermission('reports.view');
        $where = '';
        if (!empty($_GET['status'])) {
            $s = $db->real_escape_string($_GET['status']);
            $where .= " AND status = '$s'";
        }
        if (!empty($_GET['urgency'])) {
            $u = $db->real_escape_string($_GET['urgency']);
            $where .= " AND urgency = '$u'";
        }
        if (!empty($_GET['category'])) {
            $c = $db->real_escape_string($_GET['category']);
            $where .= " AND category_id = '$c'";
        }
        if (!empty($_GET['search'])) {
            $q = $db->real_escape_string(trim($_GET['search']));
            $where .= " AND (citizen_name LIKE '%$q%' OR request_id LIKE '%$q%' OR location_address LIKE '%$q%' OR contact_number LIKE '%$q%')";
        }
        if (!empty($_GET['date_from'])) {
            $df = $db->real_escape_string($_GET['date_from']);
            $where .= " AND created_at >= '$df 00:00:00'";
        }
        if (!empty($_GET['date_to'])) {
            $dt = $db->real_escape_string($_GET['date_to']);
            $where .= " AND created_at <= '$dt 23:59:59'";
        }

        $union = buildRequestUnion($where);

        // 1. Summary KPIs
        $kpiQ = $db->query("SELECT 
            COUNT(*) AS total,
            SUM(status='resolved') AS resolved,
            SUM(status NOT IN ('resolved','cancelled')) AS active,
            SUM(urgency='emergency') AS emergency,
            SUM(urgency='high') AS high,
            SUM(status='dispatched') AS dispatched,
            SUM(status='inProgress') AS in_progress,
            AVG(CASE WHEN status='resolved' AND resolved_at IS NOT NULL AND resolved_at >= created_at THEN TIMESTAMPDIFF(MINUTE, created_at, resolved_at) ELSE NULL END) AS avg_res_min
            FROM ($union) AS t");
        
        $kpi = $kpiQ ? $kpiQ->fetch_assoc() : [];
        $total = (int)($kpi['total'] ?? 0);
        $resolved = (int)($kpi['resolved'] ?? 0);
        $active = (int)($kpi['active'] ?? 0);
        $emergency = (int)($kpi['emergency'] ?? 0);
        $resRate = $total > 0 ? round(($resolved / $total) * 100, 1) : 0;
        $avgMin = !empty($kpi['avg_res_min']) ? round((float)$kpi['avg_res_min']) : 0;
        $avgTimeDisplay = $avgMin > 60 ? round($avgMin / 60, 1) . ' hrs' : ($avgMin > 0 ? $avgMin . ' mins' : 'N/A');

        // 2. Status Breakdown
        $statusQ = $db->query("SELECT status, COUNT(*) AS cnt FROM ($union) AS t GROUP BY status ORDER BY cnt DESC");
        $byStatus = [];
        if ($statusQ && $statusQ instanceof mysqli_result) {
            while ($sr = $statusQ->fetch_assoc()) {
                $cnt = (int)$sr['cnt'];
                $pct = $total > 0 ? round(($cnt / $total) * 100, 1) : 0;
                $byStatus[] = [
                    'status' => $sr['status'],
                    'count'  => $cnt,
                    'pct'    => $pct,
                ];
            }
        }

        // 3. Category Breakdown
        $catQ = $db->query("SELECT category_id, category_name, COUNT(*) AS cnt FROM ($union) AS t GROUP BY category_id, category_name ORDER BY cnt DESC LIMIT 15");
        $byCategory = [];
        if ($catQ && $catQ instanceof mysqli_result) {
            while ($cr = $catQ->fetch_assoc()) {
                $catMeta = CATEGORIES[$cr['category_id']] ?? null;
                $byCategory[] = [
                    'category_id'   => $cr['category_id'],
                    'category_name' => $cr['category_name'] ?: ($catMeta['name'] ?? $cr['category_id']),
                    'count'         => (int)$cr['cnt'],
                    'color'         => $catMeta['color'] ?? '#3B82F6',
                    'icon'          => $catMeta['icon'] ?? 'bi-tag-fill',
                ];
            }
        }

        // 4. Urgency Breakdown
        $urgQ = $db->query("SELECT urgency, COUNT(*) AS cnt FROM ($union) AS t GROUP BY urgency");
        $byUrgency = [];
        if ($urgQ && $urgQ instanceof mysqli_result) {
            while ($ur = $urgQ->fetch_assoc()) {
                $byUrgency[] = [
                    'urgency' => $ur['urgency'],
                    'count'   => (int)$ur['cnt'],
                ];
            }
        }

        // 5. Daily Trend
        $trendQ = $db->query("SELECT DATE(created_at) AS day, COUNT(*) AS total, SUM(status='resolved') AS resolved FROM ($union) AS t GROUP BY DATE(created_at) ORDER BY day ASC LIMIT 60");
        $dailyTrend = [];
        if ($trendQ && $trendQ instanceof mysqli_result) {
            while ($tr = $trendQ->fetch_assoc()) {
                $dailyTrend[] = [
                    'day'      => $tr['day'],
                    'total'    => (int)$tr['total'],
                    'resolved' => (int)$tr['resolved'],
                ];
            }
        }

        // 6. Preview Records (up to 100)
        $previewLimit = min(200, max(10, (int)($_GET['limit'] ?? 100)));
        $recQ = $db->query("SELECT * FROM ($union) AS t ORDER BY created_at DESC LIMIT $previewLimit");
        $records = [];
        if ($recQ && $recQ instanceof mysqli_result) {
            while ($r = $recQ->fetch_assoc()) {
                $cat = CATEGORIES[$r['category_id']] ?? null;
                $records[] = [
                    'request_id'       => $r['request_id'],
                    'citizen_name'     => $r['citizen_name'],
                    'contact_number'   => $r['contact_number'],
                    'category_id'      => $r['category_id'],
                    'category_name'    => $r['category_name'],
                    'category_icon'    => $cat['icon'] ?? 'bi-question-circle',
                    'category_color'   => $cat['color'] ?? '#3B82F6',
                    'urgency'          => $r['urgency'],
                    'status'           => $r['status'],
                    'location_address' => $r['location_address'],
                    'created_at'       => $r['created_at'],
                    'resolved_at'      => $r['resolved_at'],
                    'time_ago'         => timeAgo($r['created_at']),
                    'has_image'        => !empty($r['image_data']),
                ];
            }
        }

        jsonResponse([
            'success' => true,
            'summary' => [
                'total'               => $total,
                'resolved'            => $resolved,
                'active'              => $active,
                'emergency'           => $emergency,
                'resolution_rate'     => $resRate,
                'avg_resolution_min'  => $avgMin,
                'avg_resolution_text' => $avgTimeDisplay,
            ],
            'by_status'    => $byStatus,
            'by_category'  => $byCategory,
            'by_urgency'   => $byUrgency,
            'daily_trend'  => $dailyTrend,
            'records'      => $records,
            'count_shown'  => count($records),
        ]);
        break;

    // ── EXPORT CSV ────────────────────────────────────────────
    case 'export':
        requirePermission('reports.view');
        header('Content-Type: text/csv; charset=UTF-8');
        $catLabel = !empty($_GET['category']) ? $_GET['category'] . '_' : '';
        $urgLabel = !empty($_GET['urgency']) ? $_GET['urgency'] . '_' : '';
        $filename = 'city_planner_report_' . $catLabel . $urgLabel . date('Ymd_His') . '.csv';
        header('Content-Disposition: attachment; filename="' . $filename . '"');

        $where = '';
        if (!empty($_GET['status']))    $where .= " AND status='" . $db->real_escape_string($_GET['status']) . "'";
        if (!empty($_GET['urgency']))   $where .= " AND urgency='" . $db->real_escape_string($_GET['urgency']) . "'";
        if (!empty($_GET['category']))  $where .= " AND category_id='" . $db->real_escape_string($_GET['category']) . "'";
        if (!empty($_GET['search'])) {
            $q = $db->real_escape_string(trim($_GET['search']));
            $where .= " AND (citizen_name LIKE '%$q%' OR request_id LIKE '%$q%' OR location_address LIKE '%$q%' OR contact_number LIKE '%$q%')";
        }
        if (!empty($_GET['date_from'])) $where .= " AND created_at>='" . $db->real_escape_string($_GET['date_from']) . " 00:00:00'";
        if (!empty($_GET['date_to']))   $where .= " AND created_at<='" . $db->real_escape_string($_GET['date_to']) . " 23:59:59'";

        $union = buildRequestUnion($where);
        $rows  = $db->query("SELECT * FROM ($union) AS t ORDER BY created_at DESC LIMIT 10000");

        $out = fopen('php://output', 'w');
        // UTF-8 BOM for Excel
        fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF));
        fputcsv($out, ['Request ID','Citizen Name','Contact Number','Department / Category','Urgency','Status','Location Address','Created At','Resolved At','Resolution Info']);
        if ($rows && $rows instanceof mysqli_result) {
            while ($r = $rows->fetch_assoc()) {
                fputcsv($out, [
                    $r['request_id'],
                    $r['citizen_name'],
                    $r['contact_number'],
                    $r['category_name'],
                    strtoupper($r['urgency']),
                    ucfirst($r['status']),
                    $r['location_address'],
                    $r['created_at'],
                    $r['resolved_at'] ?? '',
                    $r['resolution_info'] ?? ''
                ]);
            }
        }
        fclose($out);
        exit();

    default:
        jsonResponse(['success' => false, 'message' => 'Unknown action.'], 400);
}

// ── Helpers ──────────────────────────────────────────────────
function findRequestTable(string $rid, mysqli $db): ?string {
    foreach (ALL_REQUEST_TABLES as $tbl) {
        $r = $db->query("SELECT 1 FROM `$tbl` WHERE request_id='" . $db->real_escape_string($rid) . "' LIMIT 1");
        if ($r && $r->num_rows > 0) return $tbl;
    }
    return null;
}

function timeAgo(string $datetime): string {
    $diff = time() - strtotime($datetime);
    if ($diff < 60)    return 'Just now';
    if ($diff < 3600)  return round($diff / 60) . 'm ago';
    if ($diff < 86400) return round($diff / 3600) . 'h ago';
    return round($diff / 86400) . 'd ago';
}
