<?php
$pageTitle  = 'Departments Command';
$activePage = 'departments';
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/session.php';
require_once __DIR__ . '/includes/rbac.php';
startSecureSession(); requireLogin(); requirePermission('departments.view');

$db = getDB();
$admin = getSessionAdmin();
$selectedCat = $_GET['cat'] ?? '';

// Build category metrics
$categoryStats = [];
$dbServices = getDbServices();
foreach ($dbServices as $catId => $catInfo) {
    if ($admin['role'] !== 'super_admin' && !empty($admin['categories']) && !in_array($catId, $admin['categories'])) {
        continue;
    }
    $table = $catInfo['table'] ?? 'general_emergency_requests';
    
    // Check if table exists in DB before querying
    $stats = ['total'=>0, 'active'=>0, 'critical'=>0, 'resolved'=>0];
    try {
        $cntRes = $db->query("SELECT 
            COUNT(*) AS total,
            SUM(status NOT IN ('resolved','cancelled')) AS active,
            SUM(urgency = 'emergency' AND status NOT IN ('resolved','cancelled')) AS critical,
            SUM(status = 'resolved') AS resolved
            FROM `$table` WHERE category_id = '$catId'");
        if ($cntRes) $stats = $cntRes->fetch_assoc();
    } catch (\Throwable $e) {}
    
    // Station count for this service
    $stationsCount = 0;
    try {
        $stCnt = $db->query("SELECT COUNT(*) AS cnt FROM service_stations WHERE service_id = '$catId'");
        if ($stCnt) $stationsCount = (int)$stCnt->fetch_assoc()['cnt'];
    } catch (\Throwable $e) {}

    $categoryStats[$catId] = [
        'info' => $catInfo,
        'stats' => $stats,
        'stations' => $stationsCount,
    ];
}

$showBackButton = true;
$extraScripts = [];
require_once __DIR__ . '/includes/layout.php';
?>

<style>
/* ── Minimalist Departments Toolbar ─────────────────────────── */
.dept-toolbar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    flex-wrap: wrap;
    margin-bottom: 20px;
    padding: 12px 16px;
    background: var(--bg-surface-1);
    border: 1px solid var(--border-subtle);
    border-radius: var(--radius-lg);
}
.dept-search-wrapper {
    position: relative;
    flex: 1;
    min-width: 220px;
    max-width: 360px;
}
.dept-search-wrapper i {
    position: absolute;
    left: 12px;
    top: 50%;
    transform: translateY(-50%);
    color: var(--text-muted);
    font-size: 13px;
}
.dept-search-input {
    width: 100%;
    padding: 7px 12px 7px 34px;
    border-radius: var(--radius-md);
    border: 1px solid var(--border-subtle);
    background: var(--bg-surface-2);
    color: var(--text-primary);
    font-size: 13px;
    outline: none;
    transition: border-color 0.15s ease;
}
.dept-search-input:focus {
    border-color: var(--brand-primary);
}
.dept-segment-group {
    display: flex;
    align-items: center;
    gap: 6px;
    background: var(--bg-surface-2);
    padding: 3px;
    border-radius: var(--radius-md);
    border: 1px solid var(--border-subtle);
}
.dept-segment-btn {
    padding: 5px 12px;
    font-size: 12px;
    font-weight: 600;
    border-radius: var(--radius-sm);
    border: none;
    background: transparent;
    color: var(--text-muted);
    cursor: pointer;
    transition: all 0.15s ease;
}
.dept-segment-btn:hover {
    color: var(--text-primary);
}
.dept-segment-btn.active {
    background: var(--bg-surface-1);
    color: var(--brand-primary);
    box-shadow: var(--shadow-xs);
}

/* ── Compact Department Cards ────────────────────────────────── */
.dept-card {
    background: var(--bg-surface-1);
    border: 1px solid var(--border-subtle);
    border-radius: var(--radius-lg);
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    padding: 16px;
    position: relative;
    overflow: visible;
    z-index: 1;
    transition: transform 0.15s ease, box-shadow 0.15s ease, border-color 0.15s ease;
}
.dept-card:hover {
    transform: translateY(-2px);
    border-color: var(--border-strong);
    box-shadow: var(--shadow-sm);
    z-index: 5;
}
.dept-card.has-menu-open,
.dept-card:has(.dept-dropdown-menu.show) {
    z-index: 100 !important;
}
.dept-card-top-bar {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 3px;
    border-radius: var(--radius-lg) var(--radius-lg) 0 0;
}
.dept-icon-avatar {
    width: 36px;
    height: 36px;
    border-radius: var(--radius-md);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 16px;
    flex-shrink: 0;
}

/* ── Inline Metric Pills ─────────────────────────────────────── */
.dept-metrics-inline {
    display: flex;
    align-items: center;
    gap: 6px;
    flex-wrap: wrap;
    margin: 10px 0 14px 0;
}
.dept-pill {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 3px 8px;
    border-radius: var(--radius-full);
    font-size: 11px;
    font-weight: 600;
}
.dept-pill.active-pill {
    background: rgba(239, 68, 68, 0.1);
    color: var(--color-critical);
    border: 1px solid rgba(239, 68, 68, 0.2);
}
.dept-pill.zero-active {
    background: var(--bg-surface-2);
    color: var(--text-muted);
    border: 1px solid var(--border-subtle);
}
.dept-pill.resolved-pill {
    background: rgba(16, 185, 129, 0.1);
    color: var(--color-success);
    border: 1px solid rgba(16, 185, 129, 0.2);
}

/* ── Contextual Dropdown Menus ───────────────────────────────── */
.dept-actions-wrapper {
    position: relative;
    display: flex;
    align-items: center;
    gap: 6px;
}
.dept-dropdown-menu {
    position: absolute;
    right: 0;
    top: 100%;
    margin-top: 6px;
    background: var(--bg-surface, #ffffff);
    background-color: var(--bg-surface, #ffffff);
    border: 1px solid var(--border-medium, #d0d0d0);
    border-radius: var(--radius-md, 6px);
    box-shadow: 0 10px 25px rgba(0, 0, 0, 0.15), 0 4px 6px rgba(0, 0, 0, 0.08);
    min-width: 180px;
    z-index: 1000;
    padding: 6px;
    display: none;
    text-align: left;
    opacity: 1 !important;
}
[data-theme="dark"] .dept-dropdown-menu {
    background: var(--bg-surface-2, #2e2e2e);
    background-color: var(--bg-surface-2, #2e2e2e);
    border: 1px solid var(--border-strong, #4a4a4a);
    box-shadow: 0 10px 25px rgba(0, 0, 0, 0.5), 0 4px 6px rgba(0, 0, 0, 0.3);
}
.dept-dropdown-menu.show {
    display: block;
}
.dept-dropdown-item {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 8px 12px;
    border-radius: var(--radius-sm, 4px);
    font-size: 13px;
    font-weight: 500;
    color: var(--text-primary);
    text-decoration: none;
    cursor: pointer;
    background: transparent;
    border: none;
    width: 100%;
    text-align: left;
    transition: background 0.15s ease, color 0.15s ease;
}
.dept-dropdown-item:hover {
    background: var(--bg-surface-2, #f5f5f5);
    color: var(--brand-primary);
}
[data-theme="dark"] .dept-dropdown-item:hover {
    background: var(--bg-elevated, #383838);
    color: #ffffff;
}
</style>

<!-- ── Page Header ──────────────────────────────────────────── -->
<div class="page-header">
    <div>
        <h1 class="page-title"><i class="bi bi-building-fill" style="color:var(--brand-secondary);"></i> Departments</h1>
        <p class="page-subtitle">Department performance and active request overview</p>
    </div>
    <div class="page-actions" style="position:relative;">
        <button class="btn btn-surface btn-sm" onclick="DeptManager.toggleHeaderMenu(event)" title="Department actions">
            <i class="bi bi-three-dots"></i> Options
        </button>
        <!-- Header Options Menu -->
        <div class="dept-dropdown-menu" id="headerOptionsMenu">
            <a href="<?= BASE_URL ?>services.php" class="dept-dropdown-item">
                <i class="bi bi-grid-3x3-gap-fill text-primary"></i> Manage Services
            </a>
            <a href="<?= BASE_URL ?>requests.php" class="dept-dropdown-item">
                <i class="bi bi-list-task text-secondary"></i> View All Requests
            </a>
        </div>
    </div>
</div>

<!-- ── Minimalist Toolbar: Search & Category Filter ─────────── -->
<div class="dept-toolbar">
    <div class="dept-search-wrapper">
        <i class="bi bi-search"></i>
        <input type="text" id="deptSearchInput" class="dept-search-input" 
               placeholder="Search departments..." onkeyup="DeptManager.handleSearchKeyup(event)">
    </div>
    
    <div class="dept-segment-group">
        <button class="dept-segment-btn active" data-filter="all" onclick="DeptManager.setSegment('all', this)">All</button>
        <button class="dept-segment-btn" data-filter="Emergency" onclick="DeptManager.setSegment('Emergency', this)">Emergency Services</button>
        <button class="dept-segment-btn" data-filter="Citizen" onclick="DeptManager.setSegment('Citizen', this)">Municipal Services</button>
    </div>

    <div class="text-xs text-muted" id="deptCountLabel">
        <?= count($categoryStats) ?> departments
    </div>
</div>

<!-- ── Compact Department Cards Grid ────────────────────────── -->
<div class="grid grid-cols-auto gap-4 mb-6" id="deptCardsGrid">
    <?php foreach ($categoryStats as $catId => $data): 
        $info = $data['info'];
        $stats = $data['stats'];
        $deptName = $info['service_name'] ?? $info['name'];
        $categoryDomain = $info['category'] ?? (in_array($catId, ['fire', 'fire_bus', 'ambulance', 'rescue', 'police', 'tracker', 'emergency']) ? 'Emergency' : 'Citizen');
    ?>
    <div class="dept-card" 
         data-cat-id="<?= htmlspecialchars($catId) ?>" 
         data-name="<?= htmlspecialchars(strtolower($deptName)) ?>" 
         data-category="<?= htmlspecialchars($categoryDomain) ?>">
        <div class="dept-card-top-bar" style="background:<?= $info['color'] ?>;"></div>
        
        <!-- Card Header: Icon, Titles, SOS Badge -->
        <div>
            <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:8px;margin-bottom:8px;">
                <div style="display:flex;align-items:center;gap:10px;">
                    <div class="dept-icon-avatar" style="background:<?= $info['color'] ?>15;color:<?= $info['color'] ?>;">
                        <i class="bi <?= $info['icon'] ?>"></i>
                    </div>
                    <div>
                        <h3 style="font-size:14px;font-weight:700;margin:0;color:var(--text-primary);line-height:1.2;">
                            <?= htmlspecialchars($deptName) ?>
                        </h3>
                        <span class="text-xs text-muted">
                            <?= $data['stations'] ?> Active <?= $data['stations'] === 1 ? 'Station' : 'Stations' ?>
                        </span>
                    </div>
                </div>
                <?php if (!empty($stats['critical']) && $stats['critical'] > 0): ?>
                <span class="badge badge-critical dot-pulse" style="font-size:10px;padding:2px 7px;">
                    <i class="bi bi-exclamation-circle"></i> <?= $stats['critical'] ?> SOS
                </span>
                <?php endif; ?>
            </div>

            <!-- Inline Condensed Metrics -->
            <div class="dept-metrics-inline">
                <?php if ((int)$stats['active'] > 0): ?>
                <span class="dept-pill active-pill">
                    <span class="dot dot-critical"></span> <?= (int)$stats['active'] ?> Active
                </span>
                <?php else: ?>
                <span class="dept-pill zero-active">
                    0 Active
                </span>
                <?php endif; ?>

                <span class="dept-pill resolved-pill">
                    <?= (int)$stats['resolved'] ?> Resolved
                </span>

                <span class="text-xs text-muted" style="margin-left:auto;">
                    Total: <b><?= (int)$stats['total'] ?></b>
                </span>
            </div>
        </div>

        <!-- Card Footer Actions: Cases & Context Menu (•••) -->
        <div class="dept-actions-wrapper">
            <a href="<?= BASE_URL ?>requests.php?category=<?= urlencode($catId) ?>" class="btn btn-surface btn-xs w-full" style="justify-content:center;">
                <i class="bi bi-clipboard-data"></i> Cases
            </a>
            
            <button class="btn btn-ghost btn-xs" onclick="DeptManager.toggleCardMenu('<?= htmlspecialchars($catId) ?>', event)" title="More options">
                <i class="bi bi-three-dots"></i>
            </button>

            <!-- Card Context Dropdown -->
            <div class="dept-dropdown-menu" id="cardMenu-<?= htmlspecialchars($catId) ?>">
                <a href="<?= BASE_URL ?>stations.php?service=<?= urlencode($catId) ?>" class="dept-dropdown-item">
                    <i class="bi bi-geo-alt text-primary"></i> View Stations (<?= $data['stations'] ?>)
                </a>
                <a href="<?= BASE_URL ?>services.php" class="dept-dropdown-item">
                    <i class="bi bi-sliders text-secondary"></i> Service Settings
                </a>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<!-- Empty State Container -->
<div id="deptEmptyState" class="card" style="display:none;padding:40px 20px;text-align:center;margin-bottom:24px;">
    <div style="font-size:28px;margin-bottom:8px;">🏢</div>
    <div style="font-weight:700;font-size:15px;margin-bottom:4px;">No Departments Found</div>
    <div class="text-xs text-muted">Try adjusting your search query or segment filter.</div>
</div>

<script>
const DeptManager = {
    activeSegment: 'all',
    searchTimer: null,

    init() {
        document.addEventListener('click', (e) => {
            if (!e.target.closest('.page-actions') && !e.target.closest('.dept-actions-wrapper')) {
                this.closeAllMenus();
            }
        });
    },

    closeAllMenus() {
        document.querySelectorAll('.dept-dropdown-menu.show').forEach(m => m.classList.remove('show'));
        document.querySelectorAll('.dept-card.has-menu-open').forEach(c => c.classList.remove('has-menu-open'));
    },

    toggleHeaderMenu(e) {
        if (e) e.stopPropagation();
        const menu = document.getElementById('headerOptionsMenu');
        if (!menu) return;
        const isOpen = menu.classList.contains('show');
        this.closeAllMenus();
        if (!isOpen) menu.classList.add('show');
    },

    toggleCardMenu(catId, e) {
        if (e) e.stopPropagation();
        const menu = document.getElementById('cardMenu-' + catId);
        if (!menu) return;
        const card = menu.closest('.dept-card');
        const isOpen = menu.classList.contains('show');
        this.closeAllMenus();
        if (!isOpen) {
            menu.classList.add('show');
            if (card) card.classList.add('has-menu-open');
        }
    },

    handleSearchKeyup(e) {
        clearTimeout(this.searchTimer);
        this.searchTimer = setTimeout(() => {
            this.filter();
        }, 120);
    },

    setSegment(segment, btn) {
        this.activeSegment = segment;
        document.querySelectorAll('.dept-segment-btn').forEach(b => b.classList.remove('active'));
        if (btn) btn.classList.add('active');
        this.filter();
    },

    filter() {
        const query = (document.getElementById('deptSearchInput').value || '').trim().toLowerCase();
        const cards = document.querySelectorAll('.dept-card');
        let visibleCount = 0;

        cards.forEach(card => {
            const name = card.getAttribute('data-name') || '';
            const cat = card.getAttribute('data-category') || '';

            const matchesQuery = !query || name.includes(query);
            const matchesSegment = (this.activeSegment === 'all') || (cat.toLowerCase() === this.activeSegment.toLowerCase());

            if (matchesQuery && matchesSegment) {
                card.style.display = 'flex';
                visibleCount++;
            } else {
                card.style.display = 'none';
            }
        });

        // Update count label & empty state
        const countLabel = document.getElementById('deptCountLabel');
        if (countLabel) countLabel.textContent = `${visibleCount} ${visibleCount === 1 ? 'department' : 'departments'}`;

        const emptyState = document.getElementById('deptEmptyState');
        if (emptyState) {
            emptyState.style.display = visibleCount === 0 ? 'block' : 'none';
        }
    }
};

document.addEventListener('DOMContentLoaded', () => DeptManager.init());
</script>

<?php require_once __DIR__ . '/includes/layout_end.php'; ?>

