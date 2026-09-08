"use strict";
const Requests = {
    currentPage: 1, perPage: 20, currentView: "table", sortCol: "created_at", sortDir: "DESC",
    init() { this.bindFilters(); this.loadStats(); this.load(); },
    getFilters() {
        return {
            search: document.getElementById("searchInput")?.value.trim() || "",
            status: document.getElementById("statusFilter")?.value || "",
            urgency: document.getElementById("urgencyFilter")?.value || "",
            category: document.getElementById("categoryFilter")?.value || "",
            date_from: document.getElementById("dateFrom")?.value || "",
            date_to: document.getElementById("dateTo")?.value || "",
            page: this.currentPage, per_page: this.perPage,
            sort: this.sortCol, dir: this.sortDir,
        };
    },
    async load() {
        const tbody = document.getElementById("requestsTableBody");
        if (tbody) tbody.innerHTML = `<tr><td colspan="8"><div style="text-align:center;padding:40px;"><div class="loading-spinner mx-auto"></div></div></td></tr>`;
        try {
            const data = await API.get("requests_api.php", { action: "list", ...this.getFilters() });
            if (!data || !data.success) return;
            if (this.currentView === "table") this.renderTable(data.data, data.pagination);
            else this.renderKanban(data.data);
        } catch(e) { Toast.error("Failed to load requests."); }
    },
    async loadStats() {
        try {
            const statuses = ["submitted","dispatched","inProgress","resolved","cancelled"];
            await Promise.all(statuses.map(async s => {
                const d = await API.get("requests_api.php", { action: "list", status: s, per_page: 1 });
                if (d?.pagination) { const el = document.getElementById(`qs-${s.toLowerCase()}`); if (el) el.textContent = d.pagination.total; }
            }));
        } catch(e) {}
    },
    renderTable(rows, pg) {
        const tbody = document.getElementById("requestsTableBody");
        if (!tbody) return;
        if (!rows.length) { tbody.innerHTML = `<tr><td colspan="8"><div class="empty-state" style="padding:60px;"><div class="empty-state-icon">📭</div><div class="empty-state-title">No Requests Found</div><div class="empty-state-desc">Adjust your filters or check back later.</div></div></td></tr>`; this.renderPagination(pg); return; }
        const urgBadge = u => `<span class="badge badge-${u==="emergency"?"critical":u}">${u.toUpperCase()}</span>`;
        const stsBadge = s => { const m={submitted:"submitted",acknowledged:"acknowledged",dispatched:"dispatched",inProgress:"inprogress",resolved:"resolved",cancelled:"cancelled"}; return `<span class="badge badge-${m[s]||"neutral"}">${s}</span>`; };
        tbody.innerHTML = rows.map(r => `
        <tr class="${r.urgency==="emergency"?"row-critical":r.urgency==="high"?"row-warning":""}">
            <td><a href="request_detail.php?id=${encodeURIComponent(r.request_id)}" class="mono" style="font-size:11px;color:var(--brand-secondary);">${r.request_id}</a></td>
            <td><div style="font-weight:600;">${esc(r.citizen_name)}</div><div style="font-size:11px;color:var(--text-muted);">${esc(r.contact_number)}</div></td>
            <td><span style="display:flex;align-items:center;gap:6px;"><i class="bi ${r.category_icon}" style="color:${r.category_color};font-size:14px;"></i>${esc(r.category_name)}</span></td>
            <td>${urgBadge(r.urgency)}</td>
            <td>${stsBadge(r.status)}</td>
            <td style="max-width:180px;"><span class="truncate" style="display:block;font-size:12px;">${esc(r.location_address)}</span></td>
            <td style="font-size:11px;color:var(--text-muted);white-space:nowrap;">${r.time_ago}</td>
            <td>
                <div style="display:flex;gap:4px;">
                    <a href="request_detail.php?id=${encodeURIComponent(r.request_id)}" class="btn btn-surface btn-sm" title="View"><i class="bi bi-eye"></i></a>
                    <button class="btn btn-primary btn-sm" onclick="Requests.openStatusModal('${r.request_id}')" title="Update Status"><i class="bi bi-arrow-repeat"></i></button>
                </div>
            </td>
        </tr>`).join("");
        this.renderPagination(pg);
    },
    renderKanban(rows) {
        ["submitted","dispatched","inProgress","resolved"].forEach(col => {
            const el = document.getElementById("kanban-" + col);
            if (!el) return;
            const items = rows.filter(r => r.status === col);
            if (!items.length) { el.innerHTML = `<div style="text-align:center;padding:24px;color:var(--text-muted);font-size:12px;border:2px dashed var(--border-light);border-radius:var(--radius-lg);">No items</div>`; return; }
            el.innerHTML = items.map(r => `
            <div class="kanban-card priority-${r.urgency==="emergency"?"emergency":r.urgency}" onclick="window.location='request_detail.php?id=${encodeURIComponent(r.request_id)}'">
                <div style="font-size:10px;font-family:var(--font-mono);color:var(--text-muted);margin-bottom:4px;">${r.request_id}</div>
                <div style="font-weight:600;font-size:13px;margin-bottom:4px;">${esc(r.citizen_name)}</div>
                <div style="font-size:11px;color:var(--text-secondary);margin-bottom:8px;"><i class="bi ${r.category_icon}" style="color:${r.category_color};"></i> ${esc(r.category_name)}</div>
                <div style="font-size:10px;color:var(--text-muted);margin-bottom:8px;">${esc(r.location_address.substring(0,60))}...</div>
                <div style="display:flex;justify-content:space-between;align-items:center;">
                    <span class="badge badge-${r.urgency==="emergency"?"critical":r.urgency}" style="font-size:9px;">${r.urgency.toUpperCase()}</span>
                    <span style="font-size:10px;color:var(--text-muted);">${r.time_ago}</span>
                </div>
            </div>`).join("");
        });
    },
    renderPagination(pg) {
        if (!pg) return;
        document.getElementById("paginationInfo").textContent = `Showing ${Math.min((pg.current-1)*pg.per_page+1, pg.total)}–${Math.min(pg.current*pg.per_page, pg.total)} of ${pg.total} requests`;
        const ctrl = document.getElementById("paginationControls");
        if (!ctrl) return;
        let html = `<button class="page-btn" onclick="Requests.goPage(${pg.current-1})" ${!pg.has_prev?"disabled":""}><i class="bi bi-chevron-left"></i></button>`;
        for (let i=Math.max(1,pg.current-2); i<=Math.min(pg.total_pages, pg.current+2); i++) {
            html += `<button class="page-btn ${i===pg.current?"active":""}" onclick="Requests.goPage(${i})">${i}</button>`;
        }
        html += `<button class="page-btn" onclick="Requests.goPage(${pg.current+1})" ${!pg.has_next?"disabled":""}><i class="bi bi-chevron-right"></i></button>`;
        ctrl.innerHTML = html;
    },
    goPage(p) { this.currentPage = p; this.load(); },
    bindFilters() {
        let t; ["searchInput","statusFilter","urgencyFilter","categoryFilter","dateFrom","dateTo"].forEach(id => {
            document.getElementById(id)?.addEventListener("input", () => { clearTimeout(t); t = setTimeout(() => { this.currentPage=1; this.load(); }, 350); });
        });
        document.querySelectorAll(".sortable").forEach(th => {
            th.addEventListener("click", () => {
                const col = th.dataset.col;
                if (this.sortCol===col) this.sortDir = this.sortDir==="ASC"?"DESC":"ASC";
                else { this.sortCol=col; this.sortDir="DESC"; }
                this.load();
            });
        });
    },
    clearFilters() {
        ["searchInput","statusFilter","urgencyFilter","categoryFilter","dateFrom","dateTo"].forEach(id => { const el=document.getElementById(id); if(el) el.value=""; });
        this.currentPage=1; this.load();
    },
    filterByStatus(s) { const el=document.getElementById("statusFilter"); if(el){el.value=s;this.currentPage=1;this.load();} },
    toggleView() {
        this.currentView = this.currentView==="table"?"kanban":"table";
        document.getElementById("tableView").style.display = this.currentView==="table"?"block":"none";
        document.getElementById("kanbanView").style.display = this.currentView==="kanban"?"block":"none";
        const icon = document.getElementById("viewToggleIcon");
        if (icon) icon.className = this.currentView==="kanban"?"bi bi-table":"bi bi-kanban";
        this.load();
    },
    openStatusModal(id) {
        document.getElementById("sm-request-id").value = id;
        document.getElementById("sm-req-display").textContent = id;
        document.getElementById("sm-notes").value = "";
        Modal.open("statusModal");
    },
    async submitStatusUpdate() {
        const rid = document.getElementById("sm-request-id").value;
        const status = document.getElementById("sm-status").value;
        const notes  = document.getElementById("sm-notes").value;
        try {
            const data = await API.post("requests_api.php?action=update_status", { request_id: rid, status, resolution_info: notes });
            if (data?.success) { Toast.success("Status updated successfully."); Modal.close("statusModal"); this.load(); }
            else Toast.error(data?.message || "Update failed.");
        } catch(e) { Toast.error("Network error."); }
    },
};
const esc = s => s ? String(s).replace(/&/g,"&amp;").replace(/</g,"&lt;").replace(/>/g,"&gt;") : "";
document.addEventListener("DOMContentLoaded", () => Requests.init());
