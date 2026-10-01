<?php
$pageTitle  = 'Request Operations Center';
$activePage = 'requests';
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/session.php';
require_once __DIR__ . '/includes/rbac.php';
startSecureSession(); requireLogin(); requirePermission('requests.view');
$admin = getSessionAdmin();
$csrfToken = generateCsrfToken();
$extraScripts = ['requests.js'];
require_once __DIR__ . '/includes/layout.php';
?>
<!-- ── Page Header ──────────────────────────────────────────── -->
<div class="page-header">
    <div>
        <div class="page-back-wrapper">
            <a href="javascript:history.back()" onclick="if(window.history.length > 1 && document.referrer && document.referrer.indexOf(window.location.host) !== -1){ window.history.back(); return false; } else { window.location.href='<?= BASE_URL ?>dashboard.php'; return false; }" class="btn-back">
                <i class="bi bi-arrow-left"></i> Back
            </a>
        </div>
        <h1 class="page-title"><i class="bi <?= htmlspecialchars($agencyProfile['icon'] ?? 'bi-clipboard2-pulse') ?>" style="opacity:0.6;color:var(--brand-secondary);"></i> <?= htmlspecialchars($agencyProfile['incident_label'] ?? 'Request Operations') ?></h1>
        <p class="page-subtitle">Manage, assign, and resolve service requests</p>
    </div>
    <div class="page-actions">
        <a href="<?= BASE_URL ?>api/requests_api.php?action=export<?= !empty($_GET['status']) ? '&status='.urlencode($_GET['status']) : '' ?>" class="btn btn-ghost btn-sm">
            <i class="bi bi-download"></i> Export CSV
        </a>
        <button class="btn btn-ghost btn-sm" id="viewToggle" onclick="Requests.toggleView()">
            <i class="bi bi-kanban" id="viewToggleIcon"></i> Kanban View
        </button>
    </div>
</div>

<!-- ── Quick Stats Bar ───────────────────────────────────────── -->
<div class="grid grid-cols-5 gap-3 mb-6" id="quickStats">
    <div class="stat-card critical" style="padding:var(--space-4);" onclick="Requests.filterByStatus('submitted')">
        <div class="stat-icon stat-icon-primary"><i class="bi bi-inbox"></i></div>
        <div class="stat-content"><div class="stat-value" id="qs-submitted">—</div><div class="stat-label">Submitted</div></div>
    </div>
    <div class="stat-card warning" style="padding:var(--space-4);" onclick="Requests.filterByStatus('dispatched')">
        <div class="stat-icon stat-icon-rescue"><i class="bi bi-send-fill"></i></div>
        <div class="stat-content"><div class="stat-value" id="qs-dispatched">—</div><div class="stat-label">Dispatched</div></div>
    </div>
    <div class="stat-card info" style="padding:var(--space-4);" onclick="Requests.filterByStatus('inProgress')">
        <div class="stat-icon stat-icon-road"><i class="bi bi-tools"></i></div>
        <div class="stat-content"><div class="stat-value" id="qs-inprogress">—</div><div class="stat-label">In Progress</div></div>
    </div>
    <div class="stat-card success" style="padding:var(--space-4);" onclick="Requests.filterByStatus('resolved')">
        <div class="stat-icon stat-icon-success"><i class="bi bi-check-circle-fill"></i></div>
        <div class="stat-content"><div class="stat-value" id="qs-resolved">—</div><div class="stat-label">Resolved</div></div>
    </div>
    <div class="stat-card" style="--card-accent:var(--color-neutral);padding:var(--space-4);" onclick="Requests.filterByStatus('cancelled')">
        <div class="stat-icon stat-icon-neutral"><i class="bi bi-x-circle-fill"></i></div>
        <div class="stat-content"><div class="stat-value" id="qs-cancelled">—</div><div class="stat-label">Cancelled</div></div>
    </div>
</div>

<!-- ── Main Card ─────────────────────────────────────────────── -->
<div class="card">
    <!-- Filter Bar -->
    <div class="filter-bar">
        <div class="filter-group">
            <i class="bi bi-search" style="color:var(--text-muted);"></i>
            <input type="text" id="searchInput" placeholder="Search ID, citizen, location..." class="form-control"
                   style="max-width:260px;padding:7px 12px;" value="<?= htmlspecialchars($_GET['search'] ?? '') ?>">
        </div>
        <div class="filter-group">
            <span class="filter-label">Status:</span>
            <select class="filter-select" id="statusFilter">
                <option value="">All Status</option>
                <option value="submitted"   <?= ($_GET['status']??'')==='submitted'?'selected':'' ?>>Submitted</option>
                <option value="acknowledged"<?= ($_GET['status']??'')==='acknowledged'?'selected':'' ?>>Acknowledged</option>
                <option value="dispatched"  <?= ($_GET['status']??'')==='dispatched'?'selected':'' ?>>Dispatched</option>
                <option value="inProgress"  <?= ($_GET['status']??'')==='inProgress'?'selected':'' ?>>In Progress</option>
                <option value="resolved"    <?= ($_GET['status']??'')==='resolved'?'selected':'' ?>>Resolved</option>
                <option value="cancelled"   <?= ($_GET['status']??'')==='cancelled'?'selected':'' ?>>Cancelled</option>
            </select>
        </div>
        <div class="filter-group">
            <span class="filter-label">Priority:</span>
            <select class="filter-select" id="urgencyFilter">
                <option value="">All Priority</option>
                <option value="emergency">🔴 Emergency</option>
                <option value="high">🟠 High</option>
                <option value="medium">🟡 Medium</option>
                <option value="low">🟢 Low</option>
            </select>
        </div>
        <div class="filter-group">
            <span class="filter-label">Category:</span>
            <select class="filter-select" id="categoryFilter">
                <option value="">All Categories</option>
                <?php foreach (CATEGORIES as $id => $cat): ?>
                <?php if (isAdminAllowedCategory($id, $admin)): ?>
                <option value="<?= htmlspecialchars($id) ?>" <?= ($_GET['category']??'') === $id ? 'selected' : '' ?>><?= htmlspecialchars($cat['name']) ?></option>
                <?php endif; ?>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="filter-group">
            <input type="date" class="filter-select" id="dateFrom" placeholder="From" style="min-width:130px;">
            <span style="color:var(--text-muted);font-size:var(--font-size-xs);">to</span>
            <input type="date" class="filter-select" id="dateTo" placeholder="To" style="min-width:130px;">
        </div>
        <button class="btn btn-ghost btn-sm ms-auto" onclick="Requests.clearFilters()">
            <i class="bi bi-x-circle"></i> Clear
        </button>
    </div>

    <!-- Table View -->
    <div id="tableView">
        <div class="table-wrapper" style="border:none;border-radius:0;">
            <table class="table" id="requestsTable">
                <thead>
                    <tr>
                        <th class="sortable" data-col="request_id">ID <i class="bi bi-chevron-expand" style="font-size:10px;"></i></th>
                        <th class="sortable" data-col="citizen_name">Citizen</th>
                        <th>Service</th>
                        <th class="sortable" data-col="urgency">Priority</th>
                        <th class="sortable" data-col="status">Status</th>
                        <th>Location</th>
                        <th class="sortable" data-col="created_at">Time</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="requestsTableBody">
                    <tr><td colspan="8"><div class="empty-state" style="padding:var(--space-8);">
                        <div class="loading-spinner mx-auto"></div>
                        <div class="text-muted text-sm mt-4">Loading requests...</div>
                    </div></td></tr>
                </tbody>
            </table>
        </div>
        <!-- Pagination -->
        <div class="card-footer" style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;">
            <div id="paginationInfo" class="text-muted text-sm">—</div>
            <div class="pagination" id="paginationControls"></div>
        </div>
    </div>

    <!-- Kanban View (hidden by default) -->
    <div id="kanbanView" style="display:none;padding:var(--space-6);">
        <div class="grid gap-4" style="grid-template-columns:repeat(auto-fill,minmax(260px,1fr));" id="kanbanBoard">
            <?php foreach (['submitted','dispatched','inProgress','resolved'] as $col): ?>
            <div>
                <div style="font-size:var(--font-size-xs);font-weight:700;text-transform:uppercase;letter-spacing:0.08em;color:var(--text-muted);margin-bottom:12px;">
                    <?= ucfirst($col) ?>
                </div>
                <div class="kanban-col" id="kanban-<?= $col ?>" style="display:flex;flex-direction:column;gap:8px;min-height:200px;"></div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<!-- ── Status Update Modal ───────────────────────────────────── -->
<div class="modal-backdrop" id="statusModal">
    <div class="modal">
        <div class="modal-header">
            <span class="modal-title"><i class="bi bi-arrow-repeat"></i> Update Status</span>
            <button class="modal-close" onclick="Modal.close('statusModal')"><i class="bi bi-x"></i></button>
        </div>
        <div class="modal-body">
            <input type="hidden" id="sm-request-id">
            <div class="form-group mb-4">
                <label class="form-label">Request ID</label>
                <div id="sm-req-display" style="font-family:var(--font-mono);font-size:var(--font-size-sm);color:var(--text-muted);padding:8px 0;"></div>
            </div>
            <div class="form-group mb-4">
                <label class="form-label" for="sm-status">New Status</label>
                <select class="form-control" id="sm-status">
                    <option value="acknowledged">Acknowledged</option>
                    <option value="dispatched">Dispatched</option>
                    <option value="inProgress">In Progress</option>
                    <option value="resolved">Resolved</option>
                    <option value="cancelled">Cancelled</option>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label" for="sm-notes">Resolution Notes</label>
                <textarea class="form-control" id="sm-notes" rows="3" placeholder="Optional notes about this status change..."></textarea>
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-ghost" onclick="Modal.close('statusModal')">Cancel</button>
            <button class="btn btn-primary" onclick="Requests.submitStatusUpdate()">
                <i class="bi bi-check-circle"></i> Update Status
            </button>
        </div>
    </div>
</div>

<meta name="csrf-token" content="<?= htmlspecialchars($csrfToken) ?>">

<style>
.kanban-card {
    background: var(--bg-surface);
    border: 1px solid var(--border-light);
    border-radius: var(--radius-lg);
    padding: 12px;
    cursor: pointer;
    transition: all var(--transition-fast);
}
.kanban-card:hover { box-shadow: var(--shadow-md); border-color: var(--border-medium); }
.stat-card { cursor: pointer; }
.stat-card:hover { transform: translateY(-3px); }
.mx-auto { margin: 0 auto; }
</style>
<?php require_once __DIR__ . '/includes/layout_end.php'; ?>
