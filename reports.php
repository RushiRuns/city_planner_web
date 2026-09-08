<?php
$pageTitle  = 'Reports';
$activePage = 'reports';
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/session.php';
require_once __DIR__ . '/includes/rbac.php';
startSecureSession(); requireLogin(); requirePermission('reports.view');

$admin         = getSessionAdmin();
$extraScripts  = ['charts.js'];
require_once __DIR__ . '/includes/layout.php';
?>

<style>
.simple-filter-card {
    background: var(--bg-surface);
    border: 1px solid var(--border-subtle);
    border-radius: var(--radius-xl);
    padding: 20px;
    margin-bottom: 20px;
}
.simple-kpi-bar {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 16px;
    margin-bottom: 20px;
}
.simple-kpi-item {
    background: var(--bg-surface);
    border: 1px solid var(--border-subtle);
    border-radius: var(--radius-lg);
    padding: 16px;
    text-align: center;
}
.simple-kpi-val {
    font-size: 24px;
    font-weight: 800;
    font-family: var(--font-display, Outfit, sans-serif);
    color: var(--text-primary);
}
.simple-kpi-label {
    font-size: 12px;
    color: var(--text-muted);
    font-weight: 600;
    margin-top: 2px;
}
.preview-card {
    background: var(--bg-surface);
    border: 1px solid var(--border-subtle);
    border-radius: var(--radius-xl);
    padding: 20px;
}
@media (max-width: 900px) {
    .simple-kpi-bar {
        grid-template-columns: repeat(2, 1fr);
    }
}
@media (max-width: 480px) {
    .simple-kpi-bar {
        grid-template-columns: 1fr;
    }
}
@media print {
    .sidebar, .top-header, .page-header, .simple-filter-card, .no-print {
        display: none !important;
    }
    .app-shell, .main-content, .page-content {
        margin: 0 !important;
        padding: 0 !important;
        width: 100% !important;
    }
    .preview-card {
        border: none !important;
        box-shadow: none !important;
        padding: 0 !important;
    }
}
</style>

<!-- ── Page Header ───────────────────────────────────────────── -->
<div class="page-header">
    <div>
        <div class="page-back-wrapper">
            <a href="javascript:history.back()" onclick="if(window.history.length > 1 && document.referrer && document.referrer.indexOf(window.location.host) !== -1){ window.history.back(); return false; } else { window.location.href='<?= BASE_URL ?>dashboard.php'; return false; }" class="btn-back">
                <i class="bi bi-arrow-left"></i> Back
            </a>
        </div>
        <h1 class="page-title"><i class="bi bi-file-earmark-bar-graph-fill text-primary"></i> Reports</h1>
        <p class="page-subtitle">Preview and download incident report data</p>
    </div>
</div>

<!-- ── Simple Filter & Controls ──────────────────────────────── -->
<div class="simple-filter-card no-print">
    <form id="reportForm" onsubmit="event.preventDefault(); SimpleReports.load();">
        <div class="grid grid-cols-4 gap-4 items-end">
            <!-- Department -->
            <div class="form-group mb-0">
                <label class="form-label text-xs font-semibold">Department</label>
                <select id="catFilter" class="form-control" onchange="SimpleReports.load()">
                    <option value="">All Departments</option>
                    <?php foreach (CATEGORIES as $k => $c): ?>
                    <?php if (isAdminAllowedCategory($k, $admin)): ?>
                    <option value="<?= $k ?>"><?= htmlspecialchars($c['name']) ?></option>
                    <?php endif; ?>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Status -->
            <div class="form-group mb-0">
                <label class="form-label text-xs font-semibold">Status</label>
                <select id="statusFilter" class="form-control" onchange="SimpleReports.load()">
                    <option value="">All Statuses</option>
                    <option value="resolved">Resolved Only</option>
                    <option value="inProgress">In Progress</option>
                    <option value="dispatched">Dispatched</option>
                    <option value="submitted">Submitted</option>
                </select>
            </div>

            <!-- Date Range -->
            <div class="form-group mb-0">
                <label class="form-label text-xs font-semibold">From Date</label>
                <input type="date" id="dateFrom" class="form-control" value="<?= date('Y-m-d', strtotime('-30 days')) ?>" onchange="SimpleReports.load()">
            </div>

            <div class="form-group mb-0">
                <label class="form-label text-xs font-semibold">To Date</label>
                <input type="date" id="dateTo" class="form-control" value="<?= date('Y-m-d') ?>" onchange="SimpleReports.load()">
            </div>
        </div>

        <div class="flex justify-between items-center mt-4 pt-3 border-t border-subtle">
            <div class="flex gap-2">
                <button type="button" class="btn btn-ghost btn-sm" onclick="SimpleReports.reset()">
                    <i class="bi bi-arrow-counterclockwise"></i> Reset
                </button>
            </div>
            <div class="flex gap-2">
                <button type="button" class="btn btn-surface btn-sm" onclick="window.print()">
                    <i class="bi bi-printer"></i> Print
                </button>
                <button type="button" class="btn btn-primary btn-sm" onclick="SimpleReports.download()">
                    <i class="bi bi-download"></i> Download CSV
                </button>
            </div>
        </div>
    </form>
</div>

<!-- ── Simple KPI Metrics ────────────────────────────────────── -->
<div class="simple-kpi-bar">
    <div class="simple-kpi-item">
        <div class="simple-kpi-val" id="valTotal">0</div>
        <div class="simple-kpi-label">Total Incidents</div>
    </div>
    <div class="simple-kpi-item">
        <div class="simple-kpi-val text-success" id="valResolved">0</div>
        <div class="simple-kpi-label">Resolved</div>
    </div>
    <div class="simple-kpi-item">
        <div class="simple-kpi-val" id="valActive" style="color:var(--brand-secondary);">0</div>
        <div class="simple-kpi-label">Active / In Progress</div>
    </div>
    <div class="simple-kpi-item">
        <div class="simple-kpi-val text-critical" id="valEmergency">0</div>
        <div class="simple-kpi-label">Emergency Priority</div>
    </div>
</div>

<!-- ── Simple Visual Graphs (Side by Side) ──────────────────── -->
<div class="grid grid-cols-2 gap-6 mb-6">
    <div class="card">
        <div class="card-header">
            <div class="card-title text-sm"><i class="bi bi-pie-chart-fill text-primary"></i> Incidents by Status</div>
        </div>
        <div class="card-body">
            <div style="height:200px;"><canvas id="chartStatus"></canvas></div>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <div class="card-title text-sm"><i class="bi bi-graph-up text-success"></i> Incident Trend</div>
        </div>
        <div class="card-body">
            <div style="height:200px;"><canvas id="chartTrend"></canvas></div>
        </div>
    </div>
</div>

<!-- ── Clean Data Preview Table ──────────────────────────────── -->
<div class="preview-card">
    <div class="flex justify-between items-center mb-3">
        <div style="font-weight:700;font-size:14px;color:var(--text-primary);">
            <i class="bi bi-table text-primary"></i> Data Preview
        </div>
        <span class="badge badge-surface" id="rowCount">0 rows</span>
    </div>

    <div class="table-container" style="max-height:420px;overflow-y:auto;">
        <table class="table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Citizen Name</th>
                    <th>Department</th>
                    <th>Priority</th>
                    <th>Status</th>
                    <th>Location</th>
                    <th>Date</th>
                </tr>
            </thead>
            <tbody id="tableBody">
                <tr>
                    <td colspan="7" style="text-align:center;padding:24px;" class="text-muted">Loading preview...</td>
                </tr>
            </tbody>
        </table>
    </div>
</div>

<script>
const SimpleReports = {
    chartStatus: null,
    chartTrend: null,

    async load() {
        const cat = document.getElementById('catFilter').value;
        const status = document.getElementById('statusFilter').value;
        const dateFrom = document.getElementById('dateFrom').value;
        const dateTo = document.getElementById('dateTo').value;

        const tbody = document.getElementById('tableBody');
        tbody.innerHTML = `<tr><td colspan="7" style="text-align:center;padding:20px;" class="text-muted"><div class="loading-spinner mx-auto"></div></td></tr>`;

        try {
            const data = await API.get('requests_api.php', {
                action: 'report_preview',
                category: cat,
                status: status,
                date_from: dateFrom,
                date_to: dateTo,
                limit: 50
            });

            if (!data || !data.success) {
                tbody.innerHTML = `<tr><td colspan="7" style="text-align:center;padding:20px;color:var(--color-critical);">Failed to load preview</td></tr>`;
                return;
            }

            // Update KPIs
            const s = data.summary || {};
            document.getElementById('valTotal').textContent = (s.total || 0).toLocaleString();
            document.getElementById('valResolved').textContent = (s.resolved || 0).toLocaleString();
            document.getElementById('valActive').textContent = (s.active || 0).toLocaleString();
            document.getElementById('valEmergency').textContent = (s.emergency || 0).toLocaleString();
            document.getElementById('rowCount').textContent = (data.records || []).length + ' preview rows';

            // Render Charts
            this.renderStatusChart(data.by_status || []);
            this.renderTrendChart(data.daily_trend || []);

            // Render Table
            this.renderTable(data.records || []);

        } catch (e) {
            console.error('Reports load failed:', e);
            tbody.innerHTML = `<tr><td colspan="7" style="text-align:center;padding:20px;color:var(--color-critical);">Error loading data</td></tr>`;
        }
    },

    renderStatusChart(statuses) {
        if (this.chartStatus) this.chartStatus.destroy();
        const ctx = document.getElementById('chartStatus');
        if (!ctx) return;

        const colors = {
            resolved: '#22C55E',
            inProgress: '#3B82F6',
            dispatched: '#06B6D4',
            acknowledged: '#F59E0B',
            submitted: '#94A3B8',
            cancelled: '#EF4444'
        };

        this.chartStatus = Charts.base(ctx, 'doughnut', {
            labels: statuses.map(s => s.status.toUpperCase()),
            datasets: [{
                data: statuses.map(s => s.count),
                backgroundColor: statuses.map(s => colors[s.status] || '#64748B'),
                borderWidth: 2,
                borderColor: 'transparent'
            }]
        }, { maintainAspectRatio: false });
    },

    renderTrendChart(trend) {
        if (this.chartTrend) this.chartTrend.destroy();
        const ctx = document.getElementById('chartTrend');
        if (!ctx) return;

        this.chartTrend = Charts.base(ctx, 'line', {
            labels: trend.map(t => {
                const d = new Date(t.day);
                return d.toLocaleDateString('en-IN', { month: 'short', day: 'numeric' });
            }),
            datasets: [
                {
                    label: 'Total Received',
                    data: trend.map(t => t.total),
                    borderColor: '#3B82F6',
                    backgroundColor: 'rgba(59,130,246,0.1)',
                    fill: true,
                    tension: 0.3
                },
                {
                    label: 'Resolved',
                    data: trend.map(t => t.resolved),
                    borderColor: '#22C55E',
                    backgroundColor: 'transparent',
                    tension: 0.3
                }
            ]
        }, { maintainAspectRatio: false });
    },

    renderTable(records) {
        const tbody = document.getElementById('tableBody');
        if (!records.length) {
            tbody.innerHTML = `<tr><td colspan="7" style="text-align:center;padding:24px;" class="text-muted">No records match the selected filter.</td></tr>`;
            return;
        }

        const badgeCls = {
            submitted: 'submitted',
            acknowledged: 'acknowledged',
            dispatched: 'dispatched',
            inProgress: 'inprogress',
            resolved: 'resolved',
            cancelled: 'cancelled'
        };

        tbody.innerHTML = records.map(r => `
            <tr>
                <td><code class="mono" style="font-size:11px;font-weight:700;">${this.esc(r.request_id)}</code></td>
                <td><span style="font-weight:600;">${this.esc(r.citizen_name)}</span></td>
                <td>
                    <span style="display:inline-flex;align-items:center;gap:4px;">
                        <i class="bi ${r.category_icon}" style="color:${r.category_color};"></i>
                        ${this.esc(r.category_name)}
                    </span>
                </td>
                <td><span class="badge badge-${r.urgency === 'emergency' ? 'emergency' : (r.urgency === 'high' ? 'high' : 'neutral')}">${r.urgency.toUpperCase()}</span></td>
                <td><span class="badge badge-${badgeCls[r.status] || 'neutral'}">${r.status}</span></td>
                <td style="max-width:200px;"><span class="truncate" style="display:block;font-size:12px;">${this.esc(r.location_address || '—')}</span></td>
                <td style="font-size:11px;color:var(--text-muted);white-space:nowrap;">${r.created_at}</td>
            </tr>
        `).join('');
    },

    download() {
        const cat = document.getElementById('catFilter').value;
        const status = document.getElementById('statusFilter').value;
        const dateFrom = document.getElementById('dateFrom').value;
        const dateTo = document.getElementById('dateTo').value;

        const qs = new URLSearchParams({
            action: 'export',
            category: cat,
            status: status,
            date_from: dateFrom,
            date_to: dateTo
        }).toString();

        window.open('<?= BASE_URL ?>api/requests_api.php?' + qs, '_blank');
    },

    reset() {
        document.getElementById('catFilter').value = '';
        document.getElementById('statusFilter').value = '';
        const today = new Date();
        const d30 = new Date(); d30.setDate(d30.getDate() - 30);
        document.getElementById('dateFrom').value = d30.toISOString().split('T')[0];
        document.getElementById('dateTo').value = today.toISOString().split('T')[0];
        this.load();
    },

    esc(str) {
        if (!str) return '';
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }
};

document.addEventListener('DOMContentLoaded', () => SimpleReports.load());
</script>

<?php require_once __DIR__ . '/includes/layout_end.php'; ?>
