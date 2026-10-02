/* ============================================================
   CITY PLANNER — DASHBOARD JAVASCRIPT
   Live polling, incident feed, dept breakdown, insights
   ============================================================ */
'use strict';

const Dashboard = {
    refreshInterval: 30000, // 30 seconds
    timer: null,
    trendChart: null,
    maxItems: 15,
    activeIncidentFilter: 'all',
    rawIncidents: [],

    async init() {
        this.initChart();
        await this.refresh();
        this.startAutoRefresh();
    },

    startAutoRefresh() {
        if (this.timer) clearInterval(this.timer);
        this.timer = setInterval(() => this.refresh(), this.refreshInterval);
    },

    async refresh() {
        const btn = document.getElementById('refreshBtn');
        if (btn) { btn.innerHTML = '<i class="bi bi-arrow-clockwise" style="animation:spin 0.6s linear infinite;"></i> Refreshing'; }

        try {
            const data = await API.get('dashboard_stats.php', { action: 'full' });
            if (!data || !data.success) return;

            this.updateStatCards(data);
            this.updateCityPulse(data);
            this.updateIncidentsList(data.recent_incidents || []);
            this.updateDeptBreakdown(data.dept_breakdown || []);
            this.updateInsights(data.insights || []);
            this.updateRecentTable(data.recent_incidents || []);
            this.updateTrendChart(data.trend || []);
            this.updateLastUpdated();

        } catch (err) {
            console.error('Dashboard refresh failed:', err);
            Toast.error('Failed to refresh dashboard data.');
        } finally {
            if (btn) { btn.innerHTML = '<i class="bi bi-arrow-clockwise"></i> Refresh'; }
        }
    },

    updateStatCards(data) {
        const a = data.active || {};
        const t = data.today || {};

        this.setEl('sc-active',       a.total || 0);
        this.setEl('sc-critical',     a.emergency_count || 0);
        this.setEl('sc-today',        t.total_today || 0);
        this.setEl('sc-resolved',     data.total_resolved || 0);
        this.setEl('sc-avg-resp',     data.avg_response || '—');

        const inProgress = parseInt(a.in_progress_count || 0);
        const dispatched = parseInt(a.dispatched_count || 0);
        this.setEl('sc-active-delta',
            `<span style="color:var(--color-warning)"><i class="bi bi-circle-fill" style="font-size:7px;"></i> ${inProgress} in progress &bull; ${dispatched} dispatched</span>`
        );
        this.setEl('sc-sla-badge',
            `<i class="bi bi-clock"></i> SLA breach: <span style="color:${data.sla_breaches > 0 ? 'var(--color-critical)' : 'var(--color-success)'};">${data.sla_breaches || 0}</span>`
        );

        const todayResolved = parseInt(t.resolved_today || 0);
        const todayTotal    = parseInt(t.total_today || 1);
        const rate          = todayTotal > 0 ? Math.round(todayResolved / todayTotal * 100) : 0;
        this.setEl('sc-today-delta',
            `<span style="color:var(--color-success)"><i class="bi bi-check-circle"></i> ${rate}% resolved today</span>`
        );
        this.setEl('sc-resolved-delta', 'All time performance');
    },

    updateCityPulse(data) {
        const a = data.active || {};
        const t = data.today || {};
        this.setEl('pulseActive',   a.total || 0);
        this.setEl('pulseCritical', a.emergency_count || 0);
        this.setEl('pulseToday',    t.total_today || 0);
        this.setEl('pulseResolved', data.total_resolved || 0);
        this.setEl('pulseAvgResp',  data.avg_response || '—');
        const slaEl = document.getElementById('pulseSLA');
        if (slaEl) {
            slaEl.textContent = data.sla_breaches || 0;
            slaEl.style.color = data.sla_breaches > 0 ? 'var(--color-critical)' : 'var(--color-success)';
        }
    },

    setFilter(filter, btn) {
        this.activeIncidentFilter = filter;
        document.querySelectorAll('.incident-filter-btn').forEach(b => b.classList.remove('active'));
        if (btn) btn.classList.add('active');
        this.renderIncidents();
    },

    renderIncidents() {
        const container = document.getElementById('liveIncidentsList');
        if (!container) return;

        let incidents = this.rawIncidents || [];

        // Apply selected tab filter
        if (this.activeIncidentFilter === 'critical') {
            incidents = incidents.filter(i => (i.urgency === 'emergency' || i.urgency === 'critical'));
        } else if (this.activeIncidentFilter === 'inProgress') {
            incidents = incidents.filter(i => (i.status === 'inProgress' || i.status === 'dispatched'));
        } else if (this.activeIncidentFilter === 'pending') {
            incidents = incidents.filter(i => (i.status === 'submitted' || i.status === 'acknowledged'));
        }

        const countEl = document.getElementById('liveIncidentsCount');
        if (countEl) {
            countEl.textContent = `${this.rawIncidents.length} Active`;
        }

        const summaryEl = document.getElementById('liveIncidentsSummary');
        if (summaryEl) {
            summaryEl.textContent = `Showing ${Math.min(incidents.length, this.maxItems)} of ${this.rawIncidents.length} live incidents`;
        }

        if (!incidents.length) {
            container.innerHTML = `<div class="empty-state" style="padding:var(--space-8);">
                <div class="empty-state-icon">✅</div>
                <div class="empty-state-title">${this.activeIncidentFilter === 'all' ? 'No Active Incidents' : 'No Matching Incidents'}</div>
                <div class="empty-state-desc">${this.activeIncidentFilter === 'all' ? 'All systems operating normally.' : 'No incidents match the selected filter.'}</div>
            </div>`;
            return;
        }

        const base = (typeof window !== 'undefined' && window.BASE_URL) || '';

        container.innerHTML = incidents.slice(0, this.maxItems).map(inc => {
            const isCritical = (inc.urgency === 'emergency' || inc.urgency === 'critical');
            const urgencyClass = isCritical ? 'critical' : (inc.urgency || '');
            const urgencyLabel = isCritical ? 'Critical' : (inc.urgency ? inc.urgency.charAt(0).toUpperCase() + inc.urgency.slice(1) : 'Normal');
            const urgencyBadgeClass = isCritical ? 'badge-critical' : (inc.urgency === 'high' ? 'badge-warning' : (inc.urgency === 'medium' ? 'badge-info' : 'badge-neutral'));
            const statusBadge  = this.statusBadge(inc.status);

            return `
            <div class="incident-item ${urgencyClass}">
                <div class="incident-id"><code class="mono" style="font-size:11px;">${escHtml(inc.request_id)}</code></div>
                <div class="citizen-cat">
                    <div class="incident-icon" style="background:${inc.category_color}20;color:${inc.category_color};">
                        <i class="bi ${inc.category_icon}"></i>
                    </div>
                    <div style="min-width:0;">
                        <div class="citizen-name" title="${escHtml(inc.citizen_name)}">${escHtml(inc.citizen_name)}</div>
                        <div class="category-name">${escHtml(inc.category_name)}</div>
                    </div>
                </div>
                <div class="incident-loc" title="${escHtml(inc.location_address)}">
                    <i class="bi bi-geo-alt"></i>
                    <span>${escHtml(inc.location_address)}</span>
                </div>
                <div>
                    <span class="badge ${urgencyBadgeClass}">${urgencyLabel}</span>
                </div>
                <div>${statusBadge}</div>
                <div class="incident-time">${escHtml(inc.time_ago || '')}</div>
                <div class="incident-action">
                    <a href="${base}request_detail.php?id=${encodeURIComponent(inc.request_id)}"
                       class="btn btn-ghost btn-sm"
                       title="View Details"
                       style="padding:4px 8px;font-size:12px;">
                        <i class="bi bi-eye"></i>
                    </a>
                </div>
            </div>`;
        }).join('');
    },

    updateIncidentsList(incidents) {
        this.rawIncidents = incidents || [];
        this.renderIncidents();
    },

    updateDeptBreakdown(depts) {
        const container = document.getElementById('deptBreakdownList');
        if (!container || !depts.length) return;

        const maxCount = Math.max(...depts.map(d => d.count), 1);
        container.innerHTML = depts.slice(0, 8).map(dept => {
            const pct = Math.round(dept.count / maxCount * 100);
            const base = (typeof window !== 'undefined' && window.BASE_URL) || '';
            return `
            <a href="${base}departments.php?cat=${dept.category_id}" class="dept-row">
                <div style="width:34px;height:34px;border-radius:var(--radius-md);background:${dept.color}20;color:${dept.color};display:flex;align-items:center;justify-content:center;font-size:14px;flex-shrink:0;">
                    <i class="bi ${dept.icon}"></i>
                </div>
                <div style="flex:1;overflow:hidden;">
                    <div style="font-size:var(--font-size-sm);font-weight:600;color:var(--text-primary);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">${escHtml(dept.name)}</div>
                    <div class="dept-bar-container mt-1">
                        <div class="dept-bar" style="width:${pct}%;background:${dept.color};"></div>
                    </div>
                </div>
                <div style="text-align:right;flex-shrink:0;min-width:60px;">
                    <span style="font-size:var(--font-size-lg);font-weight:800;color:var(--text-primary);">${dept.count}</span>
                    ${dept.emergencies > 0 ? `<div style="font-size:10px;color:var(--color-critical);">${dept.emergencies} critical</div>` : ''}
                </div>
            </a>`;
        }).join('');
    },

    updateInsights(insights) {
        const container = document.getElementById('insightsPanel');
        if (!container) return;

        if (!insights.length) {
            container.innerHTML = `<div class="empty-state" style="padding:var(--space-6);">
                <div class="empty-state-icon">💡</div>
                <div class="empty-state-title">No Critical Insights</div>
                <div class="empty-state-desc">City operations are running smoothly.</div>
            </div>`;
            return;
        }

        const typeStyles = {
            critical: { bg: 'var(--color-critical-bg)', border: 'var(--color-critical-border)', icon: 'var(--color-critical)', text: 'var(--text-primary)' },
            warning:  { bg: 'var(--color-warning-bg)',  border: 'var(--color-warning-border)',  icon: 'var(--color-warning)',  text: 'var(--text-primary)' },
            info:     { bg: 'var(--color-info-bg)',     border: 'var(--color-info-border)',     icon: 'var(--color-info)',     text: 'var(--text-primary)' },
            success:  { bg: 'var(--color-success-bg)',  border: 'var(--color-success-border)',  icon: 'var(--color-success)',  text: 'var(--text-primary)' },
        };

        container.innerHTML = insights.map(ins => {
            const s = typeStyles[ins.type] || typeStyles.info;
            return `
            <div class="insight-item" style="background:${s.bg};border-color:${s.border};color:${s.text};">
                <i class="bi ${ins.icon} insight-icon" style="color:${s.icon};"></i>
                <div>
                    <div class="insight-title">${ins.title}</div>
                    <div class="insight-msg">${ins.message}</div>
                </div>
            </div>`;
        }).join('');
    },

    updateRecentTable(incidents) {
        const tbody = document.getElementById('recentTableBody');
        if (!tbody) return;

        if (!incidents.length) {
            tbody.innerHTML = `<tr><td colspan="8"><div class="empty-state" style="padding:var(--space-8);">
                <div class="empty-state-icon">📋</div>
                <div class="empty-state-title">No Recent Requests</div>
            </div></td></tr>`;
            return;
        }

        tbody.innerHTML = incidents.map(inc => {
            const urgencyBadge = `<span class="badge badge-${inc.urgency}">${inc.urgency.toUpperCase()}</span>`;
            const statusBadge  = this.statusBadge(inc.status);
            return `
            <tr class="${inc.urgency === 'emergency' ? 'row-critical' : (inc.urgency === 'high' ? 'row-warning' : '')}">
                <td><code class="mono" style="font-size:11px;">${inc.request_id}</code></td>
                <td><span style="font-weight:600;">${escHtml(inc.citizen_name)}</span></td>
                <td>
                    <span style="display:flex;align-items:center;gap:6px;">
                        <i class="bi ${inc.category_icon}" style="color:${inc.category_color};font-size:14px;"></i>
                        ${escHtml(inc.category_name)}
                    </span>
                </td>
                <td>${urgencyBadge}</td>
                <td>${statusBadge}</td>
                <td style="max-width:200px;"><span class="truncate" style="display:block;">${escHtml(inc.location_address)}</span></td>
                <td style="white-space:nowrap;color:var(--text-muted);font-size:var(--font-size-xs);">${inc.time_ago}</td>
                <td>
                    <a href="${(typeof window !== 'undefined' && window.BASE_URL) || ''}request_detail.php?id=${encodeURIComponent(inc.request_id)}"
                       class="btn btn-surface btn-sm">
                        <i class="bi bi-eye"></i>
                    </a>
                </td>
            </tr>`;
        }).join('');
    },

    initChart() {
        const canvas = document.getElementById('trendChart');
        if (!canvas) return;

        const getVar = (name, fallback) => {
            if (typeof window !== 'undefined' && window.getComputedStyle) {
                const v = getComputedStyle(document.documentElement).getPropertyValue(name).trim();
                if (v) return v;
            }
            return fallback;
        };

        const isDark = document.documentElement.getAttribute('data-theme') === 'dark';
        const gridColor = isDark ? 'rgba(255,255,255,0.06)' : 'rgba(0,0,0,0.05)';
        const textColor = getVar('--text-secondary', isDark ? '#64748B' : '#94A3B8');
        const primaryColor = getVar('--brand-secondary', '#3B82F6');
        const successColor = getVar('--color-success', '#22C55E');

        this.trendChart = new Chart(canvas, {
            type: 'bar',
            data: {
                labels: [],
                datasets: [
                    {
                        label: 'Total Requests',
                        data: [],
                        backgroundColor: 'rgba(59,130,246,0.3)',
                        borderColor: primaryColor,
                        borderWidth: 2,
                        borderRadius: 4,
                    },
                    {
                        label: 'Resolved',
                        data: [],
                        backgroundColor: 'rgba(34,197,94,0.4)',
                        borderColor: successColor,
                        borderWidth: 2,
                        borderRadius: 4,
                        type: 'line',
                        fill: false,
                        tension: 0.4,
                        pointRadius: 3,
                    },
                ],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        labels: { color: textColor, font: { family: 'Inter', size: 11 } },
                    },
                    tooltip: {
                        backgroundColor: getVar('--bg-surface', isDark ? '#1E293B' : '#fff'),
                        titleColor: getVar('--text-primary', isDark ? '#F1F5F9' : '#0F172A'),
                        bodyColor: textColor,
                        borderColor: getVar('--border-medium', isDark ? 'rgba(255,255,255,0.1)' : '#E2E8F0'),
                        borderWidth: 1,
                    },
                },
                scales: {
                    x: { grid: { color: gridColor }, ticks: { color: textColor, font: { size: 11 } } },
                    y: { grid: { color: gridColor }, ticks: { color: textColor, font: { size: 11 } } },
                },
            },
        });
    },

    updateTrendChart(trend) {
        if (!this.trendChart || !trend.length) return;
        const labels = trend.map(d => {
            const date = new Date(d.day);
            return date.toLocaleDateString('en-IN', { month: 'short', day: 'numeric' });
        });
        this.trendChart.data.labels                  = labels;
        this.trendChart.data.datasets[0].data        = trend.map(d => parseInt(d.total));
        this.trendChart.data.datasets[1].data        = trend.map(d => parseInt(d.resolved || 0));
        this.trendChart.update('active');
    },

    updateLastUpdated() {
        const el = document.getElementById('lastUpdated');
        if (el) el.textContent = 'Updated ' + new Date().toLocaleTimeString('en-IN');
    },

    // ── Helpers ─────────────────────────────────────────────
    statusBadge(status) {
        const map = {
            submitted:    'submitted',
            acknowledged: 'acknowledged',
            dispatched:   'dispatched',
            inProgress:   'inprogress',
            resolved:     'resolved',
            cancelled:    'cancelled',
        };
        const cls = map[status] || 'neutral';
        return `<span class="badge badge-${cls}">${status}</span>`;
    },

    setEl(id, html) {
        const el = document.getElementById(id);
        if (el) el.innerHTML = html;
    },
};

function escHtml(str) {
    if (!str) return '';
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
}
