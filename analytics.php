<?php
$pageTitle  = 'Municipal Analytics & Intelligence';
$activePage = 'analytics';
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/session.php';
require_once __DIR__ . '/includes/rbac.php';
startSecureSession(); requireLogin(); requirePermission('analytics.view');

$extraScripts = ['charts.js'];
require_once __DIR__ . '/includes/layout.php';
?>

<div class="page-header">
    <div>
        <div class="page-back-wrapper">
            <a href="javascript:history.back()" onclick="if(window.history.length > 1 && document.referrer && document.referrer.indexOf(window.location.host) !== -1){ window.history.back(); return false; } else { window.location.href='<?= BASE_URL ?>dashboard.php'; return false; }" class="btn-back">
                <i class="bi bi-arrow-left"></i> Back
            </a>
        </div>
        <h1 class="page-title"><i class="bi bi-bar-chart-fill text-primary"></i> Analytics</h1>
        <p class="page-subtitle">Incident trends, department performance, and citizen satisfaction</p>
    </div>
    <div class="page-actions">
        <select class="filter-select" id="timeRange" onchange="AnalyticsPage.load()">
            <option value="7">Last 7 Days</option>
            <option value="30" selected>Last 30 Days</option>
            <option value="90">Last 90 Days</option>
        </select>
    </div>
</div>

<div class="grid grid-cols-2 gap-6 mb-6">
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

<script>
const AnalyticsPage = {
    charts: {},
    async load() {
        const days = document.getElementById('timeRange').value;
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
