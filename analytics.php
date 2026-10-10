<?php
$pageTitle  = 'Municipal Analytics & Intelligence';
$activePage = 'analytics';
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/session.php';
require_once __DIR__ . '/includes/rbac.php';
startSecureSession(); requireLogin(); requirePermission('analytics.view');

$extraScripts = ['charts.js'];
$showBackButton = true;
require_once __DIR__ . '/includes/layout.php';
?>

<style>
/* ── Segmented Time Range Pills ──────────────────────────────────── */
.analytics-pill-group {
    display: inline-flex;
    align-items: center;
    background: var(--bg-surface-2, #f1f5f9);
    padding: 3px;
    border-radius: var(--radius-full, 9999px);
    border: 1px solid var(--border-light, #e2e8f0);
    gap: 2px;
}
.analytics-pill {
    padding: 5px 14px;
    border: none;
    background: transparent;
    border-radius: var(--radius-full, 9999px);
    font-size: 12px;
    font-weight: 600;
    color: var(--text-secondary, #64748b);
    cursor: pointer;
    transition: all var(--transition-fast, 0.15s ease);
}
.analytics-pill:hover {
    color: var(--text-primary, #0f172a);
}
.analytics-pill.active {
    background: var(--bg-surface, #ffffff);
    color: var(--brand-primary, #6366f1);
    box-shadow: var(--shadow-sm, 0 1px 2px rgba(0,0,0,0.05));
}
[data-theme="dark"] .analytics-pill.active {
    background: var(--bg-elevated, #262626);
    color: #ffffff;
}

/* ── Collapsible Secondary Section ────────────────────────────────── */
.analytics-toggle-wrap {
    display: flex;
    justify-content: center;
    margin: 20px 0 16px;
}
.analytics-toggle-btn {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 9px 20px;
    border-radius: var(--radius-full, 9999px);
    border: 1px solid var(--border-light, #e2e8f0);
    background: var(--bg-surface, #ffffff);
    color: var(--text-secondary, #64748b);
    font-size: 12.5px;
    font-weight: 600;
    cursor: pointer;
    box-shadow: var(--shadow-sm, 0 1px 2px rgba(0,0,0,0.05));
    transition: all var(--transition-fast, 0.15s ease);
}
.analytics-toggle-btn:hover {
    background: var(--bg-surface-2, #f8fafc);
    color: var(--brand-primary, #6366f1);
    border-color: var(--brand-primary, #6366f1);
}
.analytics-toggle-btn i {
    transition: transform var(--transition-fast, 0.2s ease);
}
</style>

<div class="page-header">
    <div>
        <h1 class="page-title"><i class="bi bi-bar-chart-fill text-primary"></i> Analytics</h1>
        <p class="page-subtitle">Incident trends, department performance, and citizen satisfaction</p>
    </div>
    <div class="page-actions">
        <div class="analytics-pill-group" role="group" aria-label="Time Range">
            <button type="button" class="analytics-pill" data-days="7" onclick="AnalyticsPage.setTimeRange(7, this)">7D</button>
            <button type="button" class="analytics-pill active" data-days="30" onclick="AnalyticsPage.setTimeRange(30, this)">30D</button>
            <button type="button" class="analytics-pill" data-days="90" onclick="AnalyticsPage.setTimeRange(90, this)">90D</button>
        </div>
    </div>
</div>

<!-- Primary Operational Charts (Always Visible) -->
<div class="grid grid-cols-2 gap-6 mb-4">
    <!-- Chart 1: Volume Over Time -->
    <div class="card">
        <div class="card-header">
            <div class="card-title"><i class="bi bi-graph-up text-primary"></i> Incident Volume & Resolution Over Time</div>
        </div>
        <div class="card-body">
            <div style="height:280px;"><canvas id="volumeChart"></canvas></div>
        </div>
    </div>

    <!-- Chart 2: Category Distribution -->
    <div class="card">
        <div class="card-header">
            <div class="card-title"><i class="bi bi-pie-chart-fill text-warning"></i> Requests by Department</div>
        </div>
        <div class="card-body">
            <div style="height:280px;"><canvas id="categoryChart"></canvas></div>
        </div>
    </div>
</div>

<!-- Collapsible Toggle Strip for Secondary Metrics -->
<div class="analytics-toggle-wrap">
    <button type="button" class="analytics-toggle-btn" id="toggleSecondaryBtn" onclick="AnalyticsPage.toggleSecondaryMetrics()">
        <i class="bi bi-chevron-down" id="secondaryChevron"></i>
        <span id="secondaryToggleLabel">Show Quality & Citizen Feedback Metrics (2 charts)</span>
    </button>
</div>

<!-- Secondary Metrics Container (On Demand) -->
<div id="secondaryChartsSection" style="display:none;">
    <div class="grid grid-cols-2 gap-6 mb-6">
        <!-- Chart 3: Response Time by Department -->
        <div class="card">
            <div class="card-header">
                <div class="card-title"><i class="bi bi-stopwatch-fill text-critical"></i> Average Resolution Time (Minutes)</div>
            </div>
            <div class="card-body">
                <div style="height:280px;"><canvas id="respTimeChart"></canvas></div>
            </div>
        </div>

        <!-- Chart 4: Citizen Rating Distribution -->
        <div class="card">
            <div class="card-header">
                <div class="card-title"><i class="bi bi-star-fill text-warning"></i> Citizen Feedback Rating Distribution</div>
            </div>
            <div class="card-body">
                <div style="height:280px;"><canvas id="ratingChart"></canvas></div>
            </div>
        </div>
    </div>
</div>

<script>
const AnalyticsPage = {
    charts: {},
    activeDays: 30,

    setTimeRange(days, btn) {
        this.activeDays = days;
        document.querySelectorAll('.analytics-pill').forEach(p => p.classList.remove('active'));
        if (btn) btn.classList.add('active');
        this.load();
    },

    toggleSecondaryMetrics() {
        const sec = document.getElementById('secondaryChartsSection');
        const chevron = document.getElementById('secondaryChevron');
        const label = document.getElementById('secondaryToggleLabel');
        if (!sec) return;

        const isHidden = sec.style.display === 'none' || sec.style.display === '';
        sec.style.display = isHidden ? 'block' : 'none';
        if (chevron) {
            chevron.className = isHidden ? 'bi bi-chevron-up' : 'bi bi-chevron-down';
        }
        if (label) {
            label.textContent = isHidden ? 'Hide Quality & Citizen Feedback Metrics' : 'Show Quality & Citizen Feedback Metrics (2 charts)';
        }

        // Trigger chart resize if opened so canvas scales properly
        if (isHidden) {
            if (this.charts.resp) this.charts.resp.resize();
            if (this.charts.ratings) this.charts.ratings.resize();
        }
    },

    async load() {
        const days = this.activeDays;
        try {
            const data = await API.get('analytics_api.php', { action: 'charts', days });
            if (!data?.success) return;
            this.renderVolume(data.daily || []);
            this.renderCategory(data.by_category || []);
            this.renderRespTime(data.avg_response || []);
            this.renderRatings(data.ratings || []);
        } catch(e) {
            Toast.error('Failed to load analytics charts');
        }
    },
    renderVolume(daily) {
        if (this.charts.volume) this.charts.volume.destroy();
        const ctx = document.getElementById('volumeChart');
        this.charts.volume = Charts.base(ctx, 'line', {
            labels: daily.map(d => d.day),
            datasets: [
                { label: 'Total Received', data: daily.map(d => d.total), borderColor: '#3B82F6', backgroundColor: 'rgba(59,130,246,0.1)', fill: true, tension: 0.3 },
                { label: 'Resolved', data: daily.map(d => d.resolved), borderColor: '#22C55E', backgroundColor: 'transparent', borderDash: [4, 4], tension: 0.3 }
            ]
        }, { aspectRatio: false });
    },
    renderCategory(cats) {
        if (this.charts.cat) this.charts.cat.destroy();
        const ctx = document.getElementById('categoryChart');
        this.charts.cat = Charts.base(ctx, 'doughnut', {
            labels: cats.map(c => c.category_name),
            datasets: [{
                data: cats.map(c => c.total),
                backgroundColor: cats.map(c => c.color || '#3B82F6')
            }]
        }, { aspectRatio: false });
    },
    renderRespTime(resp) {
        if (this.charts.resp) this.charts.resp.destroy();
        const ctx = document.getElementById('respTimeChart');
        this.charts.resp = Charts.base(ctx, 'bar', {
            labels: resp.map(r => r.name),
            datasets: [{
                label: 'Avg Minutes to Resolve',
                data: resp.map(r => r.avg_min),
                backgroundColor: '#F59E0B'
            }]
        }, { aspectRatio: false });
    },
    renderRatings(ratings) {
        if (this.charts.ratings) this.charts.ratings.destroy();
        const ctx = document.getElementById('ratingChart');
        const starLabels = ['1 Star', '2 Stars', '3 Stars', '4 Stars', '5 Stars'];
        const starCounts = [0,0,0,0,0];
        ratings.forEach(r => {
            const idx = parseInt(r.rating_stars) - 1;
            if (idx >= 0 && idx < 5) starCounts[idx] = parseInt(r.cnt);
        });
        this.charts.ratings = Charts.base(ctx, 'bar', {
            labels: starLabels,
            datasets: [{
                label: 'Feedback Count',
                data: starCounts,
                backgroundColor: ['#EF4444', '#F97316', '#F59E0B', '#3B82F6', '#22C55E']
            }]
        }, { aspectRatio: false });
    }
};

document.addEventListener('DOMContentLoaded', () => AnalyticsPage.load());
</script>

<?php require_once __DIR__ . '/includes/layout_end.php'; ?>
