/* ============================================================
   CITY PLANNER — APP CORE JAVASCRIPT
   Global initialization, sidebar, theme, search
   ============================================================ */
'use strict';

const App = {
    baseUrl: (typeof window !== 'undefined' && window.BASE_URL) || document.querySelector('meta[name="base-url"]')?.content || './',
    apiUrl:  ((typeof window !== 'undefined' && window.BASE_URL) || document.querySelector('meta[name="base-url"]')?.content || './') + 'api/',
    sidebar: null,
    mainContent: null,
    isCollapsed: false,

    init() {
        this.sidebar     = document.getElementById('sidebar');
        this.mainContent = document.getElementById('mainContent');

        this.restoreCollapsedState();
        this.bindSidebarToggle();
        this.bindMobileMenu();
        this.bindGlobalSearch();
        this.bindEmergencyPanel();
        this.initActiveNav();
        this.startLiveClock();
    },

    // ── Sidebar ───────────────────────────────────────────────
    restoreCollapsedState() {
        const collapsed = localStorage.getItem('sidebar_collapsed') === 'true';
        if (collapsed) this.setSidebarCollapsed(true, false);
    },

    setSidebarCollapsed(collapsed, animate = true) {
        this.isCollapsed = collapsed;
        if (!animate) {
            this.sidebar?.classList.add('no-transition');
            this.mainContent?.classList.add('no-transition');
        }
        this.sidebar?.classList.toggle('collapsed', collapsed);
        this.mainContent?.classList.toggle('collapsed', collapsed);
        const icon = document.getElementById('sidebarToggleIcon');
        if (icon) icon.className = collapsed ? 'bi bi-layout-sidebar' : 'bi bi-layout-sidebar-reverse';
        localStorage.setItem('sidebar_collapsed', collapsed);
        if (!animate) {
            requestAnimationFrame(() => {
                this.sidebar?.classList.remove('no-transition');
                this.mainContent?.classList.remove('no-transition');
            });
        }
    },

    bindSidebarToggle() {
        document.getElementById('sidebarToggle')?.addEventListener('click', () => {
            this.setSidebarCollapsed(!this.isCollapsed);
        });
    },

    bindMobileMenu() {
        document.getElementById('mobileMenuBtn')?.addEventListener('click', () => {
            this.sidebar?.classList.toggle('mobile-open');
        });
        // Close on outside click
        document.addEventListener('click', (e) => {
            if (window.innerWidth < 900 && this.sidebar?.classList.contains('mobile-open')) {
                if (!this.sidebar.contains(e.target) && !document.getElementById('mobileMenuBtn')?.contains(e.target)) {
                    this.sidebar.classList.remove('mobile-open');
                }
            }
        });
    },

    // ── Active Nav ────────────────────────────────────────────
    initActiveNav() {
        const current = window.location.pathname.split('/').pop();
        document.querySelectorAll('.nav-item').forEach(item => {
            const href = item.getAttribute('href')?.split('/').pop();
            if (href === current) item.classList.add('active');
        });
    },

    // ── Global Search ─────────────────────────────────────────
    bindGlobalSearch() {
        const input = document.getElementById('globalSearch');
        if (!input) return;
        let debounceTimer;
        input.addEventListener('input', (e) => {
            clearTimeout(debounceTimer);
            const q = e.target.value.trim();
            if (q.length < 2) { this.hideSearchResults(); return; }
            debounceTimer = setTimeout(() => this.runSearch(q), 300);
        });
        input.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') { this.hideSearchResults(); input.blur(); }
        });
    },

    async runSearch(q) {
        // Quick search — navigate to requests with filter
        if (q.length >= 3) {
            window.location.href = `${this.apiUrl.replace('api/', '')}requests.php?search=${encodeURIComponent(q)}`;
        }
    },

    hideSearchResults() { /* placeholder */ },

    // ── Emergency Panel ───────────────────────────────────────
    bindEmergencyPanel() {
        document.getElementById('emergencyBtn')?.addEventListener('click', () => {
            window.location.href = this.apiUrl.replace('api/', '') + 'requests.php?urgency=emergency';
        });
    },

    // ── Live Clock ────────────────────────────────────────────
    startLiveClock() {
        const el = document.getElementById('liveClock');
        if (!el) return;
        const update = () => {
            const now = new Date();
            el.textContent = now.toLocaleTimeString('en-IN', { hour12: false });
        };
        update();
        setInterval(update, 1000);
    },
};

// ── Toast System ──────────────────────────────────────────────
const Toast = {
    container: null,

    init() {
        this.container = document.getElementById('toastContainer');
    },

    show(message, type = 'info', duration = 4000) {
        if (!this.container) return;
        const icons = {
            critical: 'bi-exclamation-triangle-fill',
            warning:  'bi-exclamation-circle-fill',
            success:  'bi-check-circle-fill',
            info:     'bi-info-circle-fill',
        };
        const colors = {
            critical: 'var(--color-critical)',
            warning:  'var(--color-warning)',
            success:  'var(--color-success)',
            info:     'var(--color-info)',
        };

        const toast = document.createElement('div');
        toast.className = `toast toast-${type}`;
        toast.innerHTML = `
            <i class="bi ${icons[type] || icons.info} toast-icon" style="color:${colors[type] || colors.info}"></i>
            <span class="toast-message">${message}</span>
            <button class="toast-close" onclick="this.closest('.toast').remove()">
                <i class="bi bi-x"></i>
            </button>
        `;
        this.container.appendChild(toast);

        if (duration > 0) {
            setTimeout(() => {
                toast.classList.add('out');
                setTimeout(() => toast.remove(), 250);
            }, duration);
        }
        return toast;
    },

    success(msg, dur) { return this.show(msg, 'success', dur); },
    error(msg, dur)   { return this.show(msg, 'critical', dur); },
    warning(msg, dur) { return this.show(msg, 'warning', dur); },
    info(msg, dur)    { return this.show(msg, 'info', dur); },
};

// ── Modal System ──────────────────────────────────────────────
const Modal = {
    open(id) {
        const m = document.getElementById(id);
        if (!m) return;
        m.classList.add('active');
        document.body.style.overflow = 'hidden';
    },

    close(id) {
        const m = document.getElementById(id);
        if (!m) return;
        m.classList.remove('active');
        document.body.style.overflow = '';
    },

    closeAll() {
        document.querySelectorAll('.modal-backdrop.active').forEach(m => {
            m.classList.remove('active');
        });
        document.body.style.overflow = '';
    },
};

// ── Confirm Dialog ─────────────────────────────────────────────
function confirmAction(title, message, onConfirm, danger = true) {
    const id = '_confirm_modal_' + Date.now();
    const html = `
    <div class="modal-backdrop" id="${id}" style="display:flex;">
        <div class="modal" style="max-width:440px;">
            <div class="modal-header">
                <span class="modal-title">${title}</span>
                <button class="modal-close" onclick="document.getElementById('${id}').remove();document.body.style.overflow='';">
                    <i class="bi bi-x"></i>
                </button>
            </div>
            <div class="modal-body">
                <p style="color:var(--text-secondary);font-size:var(--font-size-sm);">${message}</p>
            </div>
            <div class="modal-footer">
                <button class="btn btn-ghost" onclick="document.getElementById('${id}').remove();document.body.style.overflow='';">Cancel</button>
                <button class="btn ${danger ? 'btn-danger' : 'btn-primary'}" id="${id}_confirm">Confirm</button>
            </div>
        </div>
    </div>`;
    document.body.insertAdjacentHTML('beforeend', html);
    const backdrop = document.getElementById(id);
    setTimeout(() => backdrop.classList.add('active'), 10);
    document.body.style.overflow = 'hidden';
    document.getElementById(id + '_confirm').addEventListener('click', () => {
        backdrop.remove();
        document.body.style.overflow = '';
        onConfirm();
    });
    backdrop.addEventListener('click', (e) => {
        if (e.target === backdrop) { backdrop.remove(); document.body.style.overflow = ''; }
    });
}

// ── Initialize ─────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', () => {
    App.init();
    Toast.init();

    // Close open modals when clicking on the backdrop
    document.addEventListener('click', (e) => {
        if (e.target.classList && e.target.classList.contains('modal-backdrop') && e.target.classList.contains('active')) {
            Modal.close(e.target.id);
        }
    });
});
