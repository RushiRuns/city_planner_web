<?php
$pageTitle  = 'Incident Detail Command';
$activePage = 'requests';
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/session.php';
require_once __DIR__ . '/includes/rbac.php';
startSecureSession(); requireLogin(); requirePermission('requests.view');

$requestId = trim($_GET['id'] ?? '');
if (!$requestId) {
    header('Location: ' . BASE_URL . 'requests.php');
    exit();
}

$db = getDB();
$admin = getSessionAdmin();
$csrfToken = generateCsrfToken();

// Fetch request across all tables
$union = '';
$parts = [];
foreach (ALL_REQUEST_TABLES as $tbl) {
    $parts[] = "SELECT '$tbl' as source_table, request_id, citizen_name, contact_number, category_id, category_name, description, location_address, latitude, longitude, urgency, status, resolution_info, image_data, created_at, COALESCE(assigned_employee_id, NULL) AS assigned_employee_id, COALESCE(assigned_station_id, NULL) AS assigned_station_id, COALESCE(dispatched_at, NULL) AS dispatched_at, COALESCE(resolved_at, NULL) AS resolved_at, COALESCE(acknowledged_at, NULL) AS acknowledged_at FROM `$tbl` WHERE request_id = '" . $db->real_escape_string($requestId) . "'";
}
$union = implode(" UNION ALL ", $parts);
$res = $db->query($union);
$req = $res ? $res->fetch_assoc() : null;

if (!$req) {
    echo "<h1>Incident Not Found</h1><p><a href='requests.php'>Back to Requests</a></p>";
    exit();
}

// Strict RBAC category scope check
if (!isAdminAllowedCategory($req['category_id'], $admin)) {
    header('Location: ' . BASE_URL . 'requests.php?error=unauthorized_category');
    exit();
}

// Fetch feedback if any
$fq = $db->query("SELECT * FROM request_feedback WHERE request_id = '" . $db->real_escape_string($requestId) . "' LIMIT 1");
$feedback = $fq ? $fq->fetch_assoc() : null;

// Fetch live tracking if any
$tq = $db->query("SELECT * FROM live_tracking WHERE request_id = '" . $db->real_escape_string($requestId) . "' LIMIT 1");
$tracking = $tq ? $tq->fetch_assoc() : null;

// Fetch stations for assignment dropdown (strictly filtered to this request's service category & admin scope)
$reqCatEscaped = $db->real_escape_string($req['category_id']);
$stWhere = " WHERE (service_id = '$reqCatEscaped' OR service_id = 'emergency') " . getStationScopeWhere('service_stations');
$stationsRes = $db->query("SELECT station_id, name, service_label, city FROM service_stations $stWhere ORDER BY name ASC");
$stations = [];
if ($stationsRes) {
    while ($st = $stationsRes->fetch_assoc()) {
        $stations[] = $st;
    }
}

// Fetch employees (strictly filtered to responders of this service category)
$empScope = getEmployeeScopeWhere('ss', 'e');
$empRes = $db->query("SELECT e.id, e.station_id, e.name, e.role, e.contact_number, e.status, ss.name AS station_name 
    FROM employees e 
    INNER JOIN service_stations ss ON ss.station_id = e.station_id 
    WHERE (ss.service_id = '$reqCatEscaped' OR ss.service_id = 'emergency') $empScope 
    ORDER BY e.name ASC");
$employees = [];
if ($empRes) {
    while ($emp = $empRes->fetch_assoc()) {
        $employees[] = $emp;
    }
}

$cat = CATEGORIES[$req['category_id']] ?? [
    'name' => $req['category_name'],
    'icon' => 'bi-exclamation-triangle',
    'color' => '#3B82F6'
];

$extraScripts = ['map.js'];
require_once __DIR__ . '/includes/layout.php';
?>

<div class="page-header">
    <div>
        <div style="display:flex;align-items:center;gap:10px;margin-bottom:6px;">
            <a href="<?= BASE_URL ?>requests.php" onclick="if(window.history.length > 1 && document.referrer && document.referrer.indexOf(window.location.host) !== -1){ window.history.back(); return false; }" class="btn-back"><i class="bi bi-arrow-left"></i> Back</a>
            <span class="mono text-muted" style="font-size:14px;"><?= htmlspecialchars($req['request_id']) ?></span>
            <span class="badge badge-<?= $req['urgency'] === 'emergency' ? 'critical' : $req['urgency'] ?>">
                <?= strtoupper($req['urgency']) ?>
            </span>
            <span class="badge badge-<?= strtolower($req['status']) ?>">
                <?= htmlspecialchars($req['status']) ?>
            </span>
        </div>
        <h1 class="page-title">
            <i class="bi <?= $cat['icon'] ?>" style="color:<?= $cat['color'] ?>;"></i>
            <?= htmlspecialchars($req['category_name']) ?> — Incident Detail
        </h1>
        <p class="page-subtitle">Submitted on <?= date('D, d M Y, h:i A', strtotime($req['created_at'])) ?> &bull; Logged by <?= htmlspecialchars($req['citizen_name']) ?></p>
    </div>
    <div class="page-actions">
        <button class="btn btn-primary btn-sm" onclick="Modal.open('statusModal')">
            <i class="bi bi-arrow-repeat"></i> Update Status
        </button>
        <button class="btn btn-surface btn-sm" onclick="Modal.open('assignModal')">
            <i class="bi bi-person-check-fill"></i> Dispatch / Assign
        </button>
    </div>
</div>

<div class="grid gap-6 mb-6 request-detail-grid" id="requestDetailGrid">
    <!-- Left Column: Details, Timeline, Feedback, Evidence -->
    <div style="display:flex;flex-direction:column;gap:var(--space-6);">
        <!-- Core Details Card -->
        <div class="card">
            <div class="card-header">
                <div class="card-title"><i class="bi bi-info-circle-fill text-primary"></i> Incident Overview</div>
            </div>
            <div class="card-body">
                <div style="margin-bottom:16px;">
                    <div class="text-xs font-semibold text-muted text-uppercase mb-1">Description / Grievance</div>
                    <div style="font-size:15px;line-height:1.6;color:var(--text-primary);background:var(--bg-surface-2);padding:14px 18px;border-radius:var(--radius-lg);border:1px solid var(--border-light);">
                        <?= nl2br(htmlspecialchars($req['description'])) ?>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <div class="text-xs font-semibold text-muted text-uppercase mb-1">Citizen Contact</div>
                        <div style="font-weight:600;font-size:14px;"><?= htmlspecialchars($req['citizen_name']) ?></div>
                        <div style="font-size:13px;color:var(--text-secondary);"><i class="bi bi-telephone-fill me-2"></i><?= htmlspecialchars($req['contact_number']) ?></div>
                    </div>
                    <div>
                        <div class="text-xs font-semibold text-muted text-uppercase mb-1">Incident Location</div>
                        <div style="font-weight:600;font-size:14px;"><i class="bi bi-geo-alt-fill text-critical me-2"></i><?= htmlspecialchars($req['location_address']) ?></div>
                        <div style="font-size:12px;color:var(--text-muted);font-family:var(--font-mono);">
                            Lat: <?= number_format((float)$req['latitude'], 5) ?>, Lng: <?= number_format((float)$req['longitude'], 5) ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Timeline / Workflow Progress -->
        <div class="card">
            <div class="card-header">
                <div class="card-title"><i class="bi bi-hourglass-split text-warning"></i> Operational Timeline</div>
            </div>
            <div class="card-body">
                <div style="display:flex;flex-direction:column;gap:16px;position:relative;padding-left:24px;border-left:2px solid var(--border-medium);margin-left:12px;">
                    <!-- Step 1: Created -->
                    <div style="position:relative;">
                        <span class="dot dot-success" style="position:absolute;left:-30px;top:4px;width:12px;height:12px;"></span>
                        <div style="font-weight:700;font-size:14px;">Incident Registered</div>
                        <div class="text-xs text-muted"><?= date('M d, Y - h:i:s A', strtotime($req['created_at'])) ?></div>
                        <div style="font-size:13px;color:var(--text-secondary);margin-top:2px;">Received from citizen mobile interface into municipal database.</div>
                    </div>

                    <!-- Step 2: Acknowledged/Dispatched -->
                    <?php if (!empty($req['dispatched_at']) || in_array($req['status'], ['dispatched', 'inProgress', 'resolved'])): ?>
                    <div style="position:relative;">
                        <span class="dot dot-info" style="position:absolute;left:-30px;top:4px;width:12px;height:12px;"></span>
                        <div style="font-weight:700;font-size:14px;">Dispatched to Response Team</div>
                        <div class="text-xs text-muted"><?= !empty($req['dispatched_at']) ? date('M d, Y - h:i:s A', strtotime($req['dispatched_at'])) : 'Acknowledged & Dispatched' ?></div>
                        <div style="font-size:13px;color:var(--text-secondary);margin-top:2px;">
                            Assigned to Station: <strong><?= htmlspecialchars($req['assigned_station_id'] ?? 'Main Command') ?></strong>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Step 3: Resolved -->
                    <?php if ($req['status'] === 'resolved'): ?>
                    <div style="position:relative;">
                        <span class="dot dot-success" style="position:absolute;left:-30px;top:4px;width:12px;height:12px;"></span>
                        <div style="font-weight:700;font-size:14px;color:var(--color-success);">Incident Resolved</div>
                        <div class="text-xs text-muted"><?= !empty($req['resolved_at']) ? date('M d, Y - h:i:s A', strtotime($req['resolved_at'])) : 'Resolved' ?></div>
                        <?php if (!empty($req['resolution_info'])): ?>
                        <div style="font-size:13px;color:var(--text-secondary);margin-top:4px;background:var(--bg-surface-2);padding:8px 12px;border-radius:var(--radius-md);">
                            <strong>Resolution Action:</strong> <?= htmlspecialchars($req['resolution_info']) ?>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Citizen Feedback Section if available -->
        <?php if ($feedback): ?>
        <div class="card" style="border-left:4px solid var(--color-success);">
            <div class="card-header">
                <div class="card-title"><i class="bi bi-star-fill text-warning"></i> Citizen Rating & Feedback</div>
            </div>
            <div class="card-body">
                <div style="display:flex;align-items:center;gap:12px;margin-bottom:10px;">
                    <div style="font-size:24px;font-weight:800;color:var(--color-warning);">
                        ★ <?= htmlspecialchars($feedback['rating_stars'] ?? '5') ?>/5
                    </div>
                    <span class="badge badge-success"><?= htmlspecialchars($feedback['rating_label'] ?? 'Satisfied') ?></span>
                </div>
                <?php if (!empty($feedback['resolution_notes'])): ?>
                <div style="font-size:13px;color:var(--text-secondary);font-style:italic;">
                    "<?= htmlspecialchars($feedback['resolution_notes']) ?>"
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Attached Image Evidence if present -->
        <?php if (!empty($req['image_data'])): ?>
        <div class="card">
            <div class="card-header">
                <div class="card-title"><i class="bi bi-camera-fill text-info"></i> Citizen Uploaded Evidence</div>
            </div>
            <div class="card-body" style="text-align:center;">
                <?php 
                $imgSrc = (strpos($req['image_data'], 'data:image') === 0 || strpos($req['image_data'], 'http') === 0) 
                    ? $req['image_data'] 
                    : 'data:image/jpeg;base64,' . $req['image_data'];
                ?>
                <img src="<?= $imgSrc ?>" alt="Evidence" style="max-height:350px;margin:0 auto;border-radius:var(--radius-lg);box-shadow:var(--shadow-md);max-width:100%;object-fit:contain;" />
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- Right Column: Location Map, Live Tracking, Dispatch Controls -->
    <div style="display:flex;flex-direction:column;gap:var(--space-6);">
        <!-- GPS Map Card -->
        <div class="card">
            <div class="card-header">
                <div class="card-title"><i class="bi bi-map-fill text-primary"></i> Incident Location</div>
            </div>
            <div style="height:260px;width:100%;border-radius:0 0 var(--radius-xl) var(--radius-xl);overflow:hidden;">
                <div id="detailMap" style="height:100%;width:100%;"></div>
            </div>
        </div>

        <!-- Live Responder GPS Tracking Card -->
        <div class="card">
            <div class="card-header">
                <div class="card-title"><i class="bi bi-broadcast-pin text-critical"></i> Live Responder Tracking</div>
            </div>
            <div class="card-body">
                <?php if ($tracking && $tracking['is_tracking_active']): ?>
                <div style="display:flex;align-items:center;gap:10px;margin-bottom:12px;">
                    <span class="dot dot-success dot-pulse"></span>
                    <span style="font-weight:700;font-size:14px;color:var(--color-success);">GPS Tracking Active</span>
                </div>
                <div class="text-xs text-muted mb-2">Last coordinate ping: <?= htmlspecialchars($tracking['updated_at']) ?></div>
                <div class="surface-2 p-3" style="font-family:var(--font-mono);font-size:12px;">
                    Lat: <?= number_format((float)$tracking['current_latitude'], 5) ?><br>
                    Lng: <?= number_format((float)$tracking['current_longitude'], 5) ?>
                </div>
                <?php else: ?>
                <div class="empty-state" style="padding:var(--space-4);">
                    <div class="empty-state-icon" style="font-size:32px;">📡</div>
                    <div class="text-sm font-semibold">No Active GPS Stream</div>
                    <div class="text-xs text-muted">Field responder tracking will stream here once active.</div>
                </div>
                <?php endif; ?>
            </div>
        </div>


    </div>
</div>

<!-- ── Status Update Modal ───────────────────────────────────── -->
<div class="modal-backdrop" id="statusModal">
    <div class="modal">
        <div class="modal-header">
            <span class="modal-title"><i class="bi bi-arrow-repeat"></i> Update Status — <?= htmlspecialchars($req['request_id']) ?></span>
            <button class="modal-close" onclick="Modal.close('statusModal')"><i class="bi bi-x"></i></button>
        </div>
        <div class="modal-body">
            <div class="form-group mb-4">
                <label class="form-label" for="detail-status">Status</label>
                <select class="form-control" id="detail-status">
                    <option value="submitted" <?= $req['status'] === 'submitted' ? 'selected' : '' ?>>Submitted</option>
                    <option value="acknowledged" <?= $req['status'] === 'acknowledged' ? 'selected' : '' ?>>Acknowledged</option>
                    <option value="dispatched" <?= $req['status'] === 'dispatched' ? 'selected' : '' ?>>Dispatched</option>
                    <option value="inProgress" <?= $req['status'] === 'inProgress' ? 'selected' : '' ?>>In Progress</option>
                    <option value="resolved" <?= $req['status'] === 'resolved' ? 'selected' : '' ?>>Resolved</option>
                    <option value="cancelled" <?= $req['status'] === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label" for="detail-notes">Resolution Notes / Action Summary</label>
                <textarea class="form-control" id="detail-notes" rows="3" placeholder="Enter resolution details, unit dispatched, or inspection result..."><?= htmlspecialchars($req['resolution_info'] ?? '') ?></textarea>
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-ghost" onclick="Modal.close('statusModal')">Cancel</button>
            <button class="btn btn-primary" onclick="submitDetailStatus()">Save Changes</button>
        </div>
    </div>
</div>

<!-- ── Dispatch / Assign Modal ───────────────────────────────── -->
<div class="modal-backdrop" id="assignModal">
    <div class="modal">
        <div class="modal-header">
            <span class="modal-title"><i class="bi bi-person-check-fill"></i> Assign Dispatch Unit</span>
            <button class="modal-close" onclick="Modal.close('assignModal')"><i class="bi bi-x"></i></button>
        </div>
        <div class="modal-body">
            <div class="form-group mb-4">
                <label class="form-label" for="assign-station">Service Station</label>
                <select class="form-control" id="assign-station">
                    <option value="">Select Station...</option>
                    <?php foreach ($stations as $st): ?>
                    <option value="<?= htmlspecialchars($st['station_id']) ?>" <?= ($req['assigned_station_id'] ?? '') === $st['station_id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($st['name']) ?> (<?= htmlspecialchars($st['service_label']) ?>)
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group mb-4">
                <label class="form-label" for="assign-emp">Assigned Officer / Responder</label>
                <select class="form-control" id="assign-emp">
                    <option value="">Select Personnel...</option>
                    <?php foreach ($employees as $emp): ?>
                    <option value="<?= $emp['id'] ?>" <?= ($req['assigned_employee_id'] ?? '') == $emp['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($emp['name']) ?> — <?= htmlspecialchars($emp['role']) ?> (<?= htmlspecialchars($emp['status']) ?>)
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-ghost" onclick="Modal.close('assignModal')">Cancel</button>
            <button class="btn btn-primary" onclick="submitAssignment()">Dispatch Unit</button>
        </div>
    </div>
</div>

<meta name="csrf-token" content="<?= htmlspecialchars($csrfToken) ?>">

<script>
document.addEventListener('DOMContentLoaded', () => {
    const lat = <?= (float)$req['latitude'] ?: '18.5204' ?>;
    const lng = <?= (float)$req['longitude'] ?: '73.8567' ?>;
    
    if (document.getElementById('detailMap')) {
        const isDark = document.documentElement.getAttribute('data-theme') === 'dark';
        const map = L.map('detailMap', { center: [lat, lng], zoom: 14, attributionControl: false });
        
        const tiles = isDark 
            ? L.tileLayer('https://{s}.basemaps.cartocdn.com/dark_all/{z}/{x}/{y}{r}.png')
            : L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png');
        tiles.addTo(map);

        const markerIcon = L.divIcon({
            className: '',
            iconSize: [32, 32],
            iconAnchor: [16, 16],
            html: `<div style="width:32px;height:32px;background:var(--color-critical);border:3px solid #fff;border-radius:50%;box-shadow:0 0 10px rgba(239,68,68,0.7);display:flex;align-items:center;justify-content:center;color:#fff;font-size:14px;">📍</div>`
        });
        L.marker([lat, lng], { icon: markerIcon }).addTo(map).bindPopup("<?= htmlspecialchars($req['request_id']) ?>: <?= htmlspecialchars($req['location_address']) ?>").openPopup();
    }
});

async function submitDetailStatus() {
    const status = document.getElementById('detail-status').value;
    const notes = document.getElementById('detail-notes').value;
    try {
        const res = await API.post('requests_api.php?action=update_status', {
            request_id: '<?= htmlspecialchars($req['request_id']) ?>',
            status: status,
            resolution_info: notes
        });
        if (res?.success) {
            Toast.success('Status updated successfully');
            setTimeout(() => window.location.reload(), 800);
        } else {
            Toast.error(res?.message || 'Failed to update');
        }
    } catch(e) {
        Toast.error('Network error updating status');
    }
}

async function submitAssignment() {
    const stationId = document.getElementById('assign-station').value;
    const empId = document.getElementById('assign-emp').value;
    try {
        const res = await API.post('requests_api.php?action=assign', {
            request_id: '<?= htmlspecialchars($req['request_id']) ?>',
            station_id: stationId,
            employee_id: empId
        });
        if (res?.success) {
            Toast.success('Dispatch assignment saved');
            setTimeout(() => window.location.reload(), 800);
        } else {
            Toast.error(res?.message || 'Assignment failed');
        }
    } catch(e) {
        Toast.error('Network error during assignment');
    }
}

async function quickResolve(id) {
    if (confirm('Mark this incident as resolved?')) {
        const res = await API.post('requests_api.php?action=update_status', {
            request_id: id,
            status: 'resolved',
            resolution_info: 'Marked resolved directly from Incident Command Center.'
        });
        if (res?.success) {
            Toast.success('Incident resolved');
            setTimeout(() => window.location.reload(), 800);
        }
    }
}
</script>

<?php require_once __DIR__ . '/includes/layout_end.php'; ?>
