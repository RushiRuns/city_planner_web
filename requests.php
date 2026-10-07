<?php
$pageTitle  = 'Request Operations Center';
$activePage = 'requests';
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/session.php';
require_once __DIR__ . '/includes/rbac.php';
startSecureSession(); requireLogin(); requirePermission('requests.view');
$admin = getSessionAdmin();
$csrfToken = generateCsrfToken();
$showBackButton = true;
$extraScripts = ['requests.js'];
require_once __DIR__ . '/includes/layout.php';
?>
<!-- ── Page Header ──────────────────────────────────────────── -->
<div class="page-header">
    <div>
        <h1 class="page-title"><i class="bi <?= htmlspecialchars($agencyProfile['icon'] ?? 'bi-clipboard2-pulse') ?>" style="opacity:0.6;color:var(--brand-secondary);"></i> <?= htmlspecialchars($agencyProfile['incident_label'] ?? 'Request Operations') ?></h1>
        <p class="page-subtitle">Manage, assign, and resolve service requests</p>
    </div>
    <div class="page-actions">
        <a href="<?= BASE_URL ?>api/requests_api.php?action=export<?= !empty($_GET['status']) ? '&status='.urlencode($_GET['status']) : '' ?>" class="btn btn-ghost btn-sm" id="exportBtn" onclick="Requests.exportCsv(event)">
            <i class="bi bi-download"></i> Export CSV
        </a>
        <button class="btn btn-ghost btn-sm" id="viewToggle" onclick="Requests.toggleView()">
            <i class="bi bi-kanban" id="viewToggleIcon"></i> Kanban View
        </button>
    </div>
</div>

<!-- ── Interactive Status Pills Bar ──────────────────────────── -->
<div class="status-pills-bar mb-4" id="statusPillsBar">
    <button type="button" class="status-pill <?= empty($_GET['status']) ? 'active' : '' ?>" data-status="" onclick="Requests.filterByStatus('')">
        <span class="status-pill-dot" style="background:var(--brand-primary, #6366f1);"></span>
        <span>All</span>
        <span class="status-pill-count" id="qs-all">—</span>
    </button>
    <button type="button" class="status-pill <?= ($_GET['status']??'')==='submitted' ? 'active' : '' ?>" data-status="submitted" onclick="Requests.filterByStatus('submitted')">
        <span class="status-pill-dot" style="background:var(--color-critical, #ef4444);"></span>
        <span>Submitted</span>
        <span class="status-pill-count" id="qs-submitted">—</span>
    </button>
    <button type="button" class="status-pill <?= ($_GET['status']??'')==='dispatched' ? 'active' : '' ?>" data-status="dispatched" onclick="Requests.filterByStatus('dispatched')">
        <span class="status-pill-dot" style="background:var(--color-warning, #f59e0b);"></span>
        <span>Dispatched</span>
        <span class="status-pill-count" id="qs-dispatched">—</span>
    </button>
    <button type="button" class="status-pill <?= ($_GET['status']??'')==='inProgress' ? 'active' : '' ?>" data-status="inProgress" onclick="Requests.filterByStatus('inProgress')">
        <span class="status-pill-dot" style="background:var(--color-info, #3b82f6);"></span>
        <span>In Progress</span>
        <span class="status-pill-count" id="qs-inprogress">—</span>
    </button>
    <button type="button" class="status-pill <?= ($_GET['status']??'')==='resolved' ? 'active' : '' ?>" data-status="resolved" onclick="Requests.filterByStatus('resolved')">
        <span class="status-pill-dot" style="background:var(--color-success, #10b981);"></span>
        <span>Resolved</span>
        <span class="status-pill-count" id="qs-resolved">—</span>
    </button>
    <button type="button" class="status-pill <?= ($_GET['status']??'')==='cancelled' ? 'active' : '' ?>" data-status="cancelled" onclick="Requests.filterByStatus('cancelled')">
        <span class="status-pill-dot" style="background:var(--text-muted, #64748b);"></span>
        <span>Cancelled</span>
        <span class="status-pill-count" id="qs-cancelled">—</span>
    </button>
</div>

<!-- ── Main Card ─────────────────────────────────────────────── -->
<div class="card">
    <!-- Filter Container -->
    <div class="minimal-filter-container">
        <!-- Primary Minimal Bar -->
        <div class="minimal-filter-bar">
            <div class="filter-search-wrap">
                <i class="bi bi-search filter-search-icon"></i>
                <input type="text" id="searchInput" placeholder="Search ID, citizen, location..." class="form-control minimal-search-input" value="<?= htmlspecialchars($_GET['search'] ?? '') ?>">
            </div>

            <input type="hidden" id="statusFilter" value="<?= htmlspecialchars($_GET['status'] ?? '') ?>">

            <div class="minimal-filter-actions">
                <button type="button" class="btn btn-surface btn-sm filter-drawer-toggle" id="filterDrawerToggleBtn" onclick="Requests.toggleFilterDrawer()">
                    <i class="bi bi-funnel"></i>
                    <span>Filters</span>
                    <span id="activeFilterBadge" class="filter-count-badge" style="display:none;">0</span>
                    <i class="bi bi-chevron-down filter-chevron" id="filterChevron"></i>
                </button>
                <button type="button" class="btn btn-ghost btn-sm clear-filters-btn" id="clearFiltersBtn" onclick="Requests.clearFilters()" title="Reset all filters">
                    <i class="bi bi-arrow-counterclockwise"></i> <span>Reset</span>
                </button>
            </div>
        </div>

        <!-- Collapsible Secondary Filters Drawer (Hidden by default) -->
        <div class="filter-drawer" id="filterDrawer">
            <div class="filter-drawer-inner">
                <div class="filter-drawer-grid">
                    <div class="filter-field">
                        <label class="filter-field-label" for="urgencyFilter">Priority</label>
                        <select class="filter-select" id="urgencyFilter">
                            <option value="">All Priorities</option>
                            <option value="emergency">🔴 Emergency</option>
                            <option value="high">🟠 High</option>
                            <option value="medium">🟡 Medium</option>
                            <option value="low">🟢 Low</option>
                        </select>
                    </div>

                    <div class="filter-field">
                        <label class="filter-field-label" for="categoryFilter">Category</label>
                        <select class="filter-select" id="categoryFilter">
                            <option value="">All Categories</option>
                            <?php foreach (CATEGORIES as $id => $cat): ?>
                            <?php if (isAdminAllowedCategory($id, $admin)): ?>
                            <option value="<?= htmlspecialchars($id) ?>" <?= ($_GET['category']??'') === $id ? 'selected' : '' ?>><?= htmlspecialchars($cat['name']) ?></option>
                            <?php endif; ?>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="filter-field">
                        <label class="filter-field-label">Date Range</label>
                        <div class="filter-date-group">
                            <input type="date" class="filter-select" id="dateFrom" placeholder="From">
                            <span class="filter-date-separator">to</span>
                            <input type="date" class="filter-select" id="dateTo" placeholder="To">
                        </div>
                    </div>
                </div>
            </div>
        </div>
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
