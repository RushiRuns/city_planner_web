<?php
$pageTitle  = 'System Configuration & SLA Thresholds';
$activePage = 'settings';
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/session.php';
require_once __DIR__ . '/includes/rbac.php';
require_once __DIR__ . '/includes/audit.php';
startSecureSession(); requireLogin(); requireRole('super_admin');

$db = getDB();
$csrfToken = generateCsrfToken();

// Auto-initialize system_settings and sla_config tables if not existing
$db->query("CREATE TABLE IF NOT EXISTS `system_settings` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `setting_key` VARCHAR(100) NOT NULL UNIQUE,
    `setting_value` TEXT NULL,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$db->query("CREATE TABLE IF NOT EXISTS `sla_config` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `category_id` VARCHAR(50) NOT NULL,
    `urgency_level` VARCHAR(50) NOT NULL,
    `response_time_minutes` INT NOT NULL DEFAULT 15,
    `resolution_time_hours` INT NOT NULL DEFAULT 24,
    UNIQUE KEY `cat_urg` (`category_id`, `urgency_level`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// Handle Save
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_settings'])) {
    if (validateCsrf()) {
        foreach ($_POST['settings'] ?? [] as $k => $v) {
            $kEsc = $db->real_escape_string($k);
            $vEsc = $db->real_escape_string($v);
            $db->query("INSERT INTO system_settings (setting_key, setting_value) VALUES ('$kEsc', '$vEsc') ON DUPLICATE KEY UPDATE setting_value = '$vEsc'");
        }
        logAudit('update_settings', 'settings', 'global');
        $successMsg = 'Settings saved successfully.';
    }
}

// Fetch SLA configs with safe check
$slaList = [];
$slaQ = $db->query("SELECT * FROM sla_config ORDER BY category_id, urgency_level");
if ($slaQ && $slaQ instanceof mysqli_result) {
    while ($s = $slaQ->fetch_assoc()) {
        $slaList[] = $s;
    }
}

// If SLA config is empty, seed defaults
if (empty($slaList)) {
    $defaultSlas = [
        ['police', 'emergency', 5, 2],
        ['fire', 'emergency', 7, 3],
        ['ambulance', 'emergency', 8, 2],
        ['rescue', 'emergency', 10, 4],
        ['road_management', 'high', 30, 24],
        ['water_management', 'medium', 60, 48],
        ['waste_management', 'low', 120, 72],
        ['citizen_issue', 'medium', 60, 48]
    ];
    foreach ($defaultSlas as $def) {
        $db->query("INSERT IGNORE INTO `sla_config` (`category_id`, `urgency_level`, `response_time_minutes`, `resolution_time_hours`) 
                    VALUES ('{$def[0]}', '{$def[1]}', {$def[2]}, {$def[3]})");
    }
    $slaQ2 = $db->query("SELECT * FROM sla_config ORDER BY category_id, urgency_level");
    if ($slaQ2 && $slaQ2 instanceof mysqli_result) {
        while ($s = $slaQ2->fetch_assoc()) {
            $slaList[] = $s;
        }
    }
}

// Fetch system settings with safe check
$settings = [];
$settQ = $db->query("SELECT setting_key, setting_value FROM system_settings");
if ($settQ && $settQ instanceof mysqli_result) {
    while ($row = $settQ->fetch_assoc()) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
}

// Retain active tab after POST if provided
$postedTab = $_POST['active_tab'] ?? '';

$showBackButton = true;
require_once __DIR__ . '/includes/layout.php';
?>

<style>
/* ── Settings Segmented Tab Navigation ───────────────────────── */
.settings-tabs-bar {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 20px;
    padding: 6px;
    background: var(--bg-surface-1);
    border: 1px solid var(--border-subtle);
    border-radius: var(--radius-lg);
    width: fit-content;
    flex-wrap: wrap;
}
.settings-tab-btn {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 8px 16px;
    border-radius: var(--radius-md);
    font-size: 13px;
    font-weight: 600;
    color: var(--text-secondary);
    background: transparent;
    border: none;
    cursor: pointer;
    transition: all var(--transition-fast);
}
.settings-tab-btn:hover {
    color: var(--text-primary);
    background: var(--bg-surface-2);
}
.settings-tab-btn.active {
    background: var(--brand-primary);
    color: #ffffff;
    box-shadow: var(--shadow-xs);
}

/* ── Tab Panes ───────────────────────────────────────────────── */
.settings-tab-pane {
    display: none;
    animation: fadeIn 0.15s ease-in-out;
}
.settings-tab-pane.active {
    display: block;
}
@keyframes fadeIn {
    from { opacity: 0; transform: translateY(3px); }
    to { opacity: 1; transform: translateY(0); }
}

/* ── Collapsible Advanced Settings Drawer ────────────────────── */
.settings-advanced-toggle {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    color: var(--text-secondary);
    font-size: 13px;
    font-weight: 600;
    background: none;
    border: none;
    cursor: pointer;
    padding: 6px 0;
    margin-bottom: 12px;
}
.settings-advanced-toggle:hover {
    color: var(--brand-primary);
}
.settings-advanced-drawer {
    display: none;
    padding: 16px;
    margin-bottom: 16px;
    background: var(--bg-surface-2);
    border: 1px solid var(--border-subtle);
    border-radius: var(--radius-md);
}
.settings-advanced-drawer.expanded {
    display: block;
}
</style>

<!-- ── Page Header ──────────────────────────────────────────── -->
<div class="page-header">
    <div>
        <h1 class="page-title"><i class="bi bi-gear-fill text-primary"></i> Settings</h1>
        <p class="page-subtitle">System preferences, appearance, and SLA thresholds</p>
    </div>
</div>

<?php if (!empty($successMsg)): ?>
<div class="alert alert-success mb-4"><?= htmlspecialchars($successMsg) ?></div>
<?php endif; ?>

<!-- ── Segmented Navigation Tabs ────────────────────────────── -->
<div class="settings-tabs-bar">
    <button type="button" class="settings-tab-btn active" data-tab="theme" onclick="SettingsManager.switchTab('theme')">
        <i class="bi bi-palette-fill"></i> Appearance
    </button>
    <button type="button" class="settings-tab-btn" data-tab="general" onclick="SettingsManager.switchTab('general')">
        <i class="bi bi-sliders"></i> General Parameters
    </button>
    <button type="button" class="settings-tab-btn" data-tab="sla" onclick="SettingsManager.switchTab('sla')">
        <i class="bi bi-clock-history"></i> SLA Targets
    </button>
</div>

<!-- ══════════════════════════════════════════════════════════════ -->
<!-- TAB PANE 1: APPEARANCE & THEME                                 -->
<!-- ══════════════════════════════════════════════════════════════ -->
<div class="settings-tab-pane active" id="pane-theme">
    <div class="card">
        <div class="card-header">
            <div class="card-title"><i class="bi bi-palette-fill text-primary"></i> Interface Theme</div>
        </div>
        <div class="card-body">
            <p class="text-xs text-muted mb-4">Select your preferred color scheme for the command platform interface.</p>
            <div style="display:flex;gap:16px;flex-wrap:wrap;max-width:680px;">
                <div class="theme-card" data-set-theme="dark" role="button" tabindex="0" style="flex:1;min-width:220px;">
                    <div class="theme-card-header">
                        <div class="theme-card-title"><i class="bi bi-moon-stars-fill text-primary"></i> Dark Mode</div>
                        <div class="theme-card-check"><i class="bi bi-check-lg"></i></div>
                    </div>
                    <p class="theme-card-desc">Optimized for low-light environments and extended use.</p>
                </div>
                <div class="theme-card" data-set-theme="light" role="button" tabindex="0" style="flex:1;min-width:220px;">
                    <div class="theme-card-header">
                        <div class="theme-card-title"><i class="bi bi-sun-fill text-warning"></i> Light Mode</div>
                        <div class="theme-card-check"><i class="bi bi-check-lg"></i></div>
                    </div>
                    <p class="theme-card-desc">High-contrast mode for bright office environments.</p>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ══════════════════════════════════════════════════════════════ -->
<!-- TAB PANE 2: GENERAL MUNICIPAL PARAMETERS                       -->
<!-- ══════════════════════════════════════════════════════════════ -->
<div class="settings-tab-pane" id="pane-general">
    <div class="card" style="max-width:720px;">
        <div class="card-header">
            <div class="card-title"><i class="bi bi-sliders text-primary"></i> General Municipal Parameters</div>
        </div>
        <div class="card-body">
            <form method="POST" action="" id="generalSettingsForm">
                <input type="hidden" name="<?= CSRF_TOKEN_NAME ?>" value="<?= htmlspecialchars($csrfToken) ?>">
                <input type="hidden" name="save_settings" value="1">
                <input type="hidden" name="active_tab" id="activeTabInput" value="general">
                
                <!-- Primary Setting Upfront -->
                <div class="form-group mb-4">
                    <label class="form-label" style="font-weight:600;">Municipality / City Name</label>
                    <input type="text" name="settings[city_name]" class="form-control" 
                           placeholder="Smart City Administration"
                           value="<?= htmlspecialchars($settings['city_name'] ?? 'Smart City Administration') ?>" required>
                    <div class="text-xs text-muted" style="margin-top:4px;">Appears on official headers, incident exports, and system alerts.</div>
                </div>

                <!-- Expandable Drawer for Secondary Technical Settings -->
                <button type="button" class="settings-advanced-toggle" onclick="SettingsManager.toggleAdvanced()">
                    <i class="bi bi-chevron-down" id="advancedDrawerIcon"></i> Advanced Configuration (Map Coordinates & Polling)
                </button>

                <div class="settings-advanced-drawer" id="advancedParamsDrawer">
                    <div class="grid grid-cols-2 gap-3 mb-3">
                        <div class="form-group">
                            <label class="form-label text-xs">Default Map Latitude</label>
                            <input type="text" name="settings[map_default_lat]" class="form-control" value="<?= htmlspecialchars($settings['map_default_lat'] ?? '18.5204') ?>">
                        </div>
                        <div class="form-group">
                            <label class="form-label text-xs">Default Map Longitude</label>
                            <input type="text" name="settings[map_default_lng]" class="form-control" value="<?= htmlspecialchars($settings['map_default_lng'] ?? '73.8567') ?>">
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div class="form-group">
                            <label class="form-label text-xs">Dashboard Refresh (Seconds)</label>
                            <input type="number" min="5" name="settings[dashboard_refresh_seconds]" class="form-control" value="<?= htmlspecialchars($settings['dashboard_refresh_seconds'] ?? '30') ?>">
                        </div>
                        <div class="form-group">
                            <label class="form-label text-xs">Emergency Polling (Seconds)</label>
                            <input type="number" min="2" name="settings[emergency_refresh_seconds]" class="form-control" value="<?= htmlspecialchars($settings['emergency_refresh_seconds'] ?? '15') ?>">
                        </div>
                    </div>
                </div>

                <div style="margin-top:16px;">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-check-circle-fill"></i> Save System Settings
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ══════════════════════════════════════════════════════════════ -->
<!-- TAB PANE 3: SLA THRESHOLDS                                     -->
<!-- ══════════════════════════════════════════════════════════════ -->
<div class="settings-tab-pane" id="pane-sla">
    <div class="card">
        <div class="card-header">
            <div class="card-title"><i class="bi bi-clock-history text-warning"></i> SLA Dispatch Response Targets</div>
        </div>
        <div class="table-wrapper" style="border:none;border-radius:0;">
            <table class="table">
                <thead>
                    <tr>
                        <th>Category</th>
                        <th>Urgency Level</th>
                        <th>Target Dispatch Response</th>
                        <th>Maximum Target Resolution</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($slaList as $sla): ?>
                    <tr>
                        <td><code class="mono" style="font-weight:600;font-size:12px;"><?= htmlspecialchars($sla['category_id']) ?></code></td>
                        <td><span class="badge badge-<?= $sla['urgency_level'] === 'emergency' ? 'critical' : $sla['urgency_level'] ?>" style="font-size:11px;"><?= htmlspecialchars(strtoupper($sla['urgency_level'])) ?></span></td>
                        <td><strong><?= (int)$sla['response_time_minutes'] ?> mins</strong></td>
                        <td><?= (int)$sla['resolution_time_hours'] ?> hours</td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
const SettingsManager = {
    serverActiveTab: <?= json_encode($postedTab) ?>,

    init() {
        const hashTab = (window.location.hash || '').replace('#', '');
        const savedTab = localStorage.getItem('settings_active_tab');
        const initialTab = this.serverActiveTab || hashTab || savedTab || 'theme';
        this.switchTab(initialTab, false);
    },

    switchTab(tabKey, updateHash = true) {
        const validTabs = ['theme', 'general', 'sla'];
        if (!validTabs.includes(tabKey)) tabKey = 'theme';

        // Update Tab Buttons
        document.querySelectorAll('.settings-tab-btn').forEach(btn => {
            const btnTab = btn.getAttribute('data-tab');
            btn.classList.toggle('active', btnTab === tabKey);
        });

        // Update Tab Panes
        document.querySelectorAll('.settings-tab-pane').forEach(pane => {
            pane.classList.remove('active');
        });
        const activePane = document.getElementById('pane-' + tabKey);
        if (activePane) activePane.classList.add('active');

        // Persist
        localStorage.setItem('settings_active_tab', tabKey);
        const input = document.getElementById('activeTabInput');
        if (input) input.value = tabKey;

        if (updateHash && history.replaceState) {
            history.replaceState(null, '', '#' + tabKey);
        }
    },

    toggleAdvanced() {
        const drawer = document.getElementById('advancedParamsDrawer');
        const icon = document.getElementById('advancedDrawerIcon');
        if (!drawer) return;
        const isExpanded = drawer.classList.toggle('expanded');
        if (icon) {
            icon.className = isExpanded ? 'bi bi-chevron-up' : 'bi bi-chevron-down';
        }
    }
};

document.addEventListener('DOMContentLoaded', () => {
    SettingsManager.init();
});
</script>

<?php require_once __DIR__ . '/includes/layout_end.php'; ?>

