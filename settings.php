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

require_once __DIR__ . '/includes/layout.php';
?>

<div class="page-header">
    <div>
        <div class="page-back-wrapper">
            <a href="javascript:history.back()" onclick="if(window.history.length > 1 && document.referrer && document.referrer.indexOf(window.location.host) !== -1){ window.history.back(); return false; } else { window.location.href='<?= BASE_URL ?>dashboard.php'; return false; }" class="btn-back">
                <i class="bi bi-arrow-left"></i> Back
            </a>
        </div>
        <h1 class="page-title"><i class="bi bi-gear-fill text-primary"></i> Settings</h1>
        <p class="page-subtitle">System preferences, appearance, and SLA thresholds</p>
    </div>
</div>

<?php if (!empty($successMsg)): ?>
<div class="alert alert-success mb-4"><?= htmlspecialchars($successMsg) ?></div>
<?php endif; ?>

<!-- ── Appearance & Theme Management ─────────────────────────────── -->
<div class="card mb-6">
    <div class="card-header">
        <div class="card-title"><i class="bi bi-palette-fill text-primary"></i> Theme</div>
    </div>
    <div class="card-body">
        <div style="display:flex;gap:12px;flex-wrap:wrap;">
            <div class="theme-card" data-set-theme="dark" role="button" tabindex="0" style="flex:1;min-width:200px;">
                <div class="theme-card-header">
                    <div class="theme-card-title"><i class="bi bi-moon-stars-fill text-primary"></i> Dark Mode</div>
                    <div class="theme-card-check"><i class="bi bi-check-lg"></i></div>
                </div>
                <p class="theme-card-desc">Optimized for low-light environments and extended use.</p>
            </div>
            <div class="theme-card" data-set-theme="light" role="button" tabindex="0" style="flex:1;min-width:200px;">
                <div class="theme-card-header">
                    <div class="theme-card-title"><i class="bi bi-sun-fill text-warning"></i> Light Mode</div>
                    <div class="theme-card-check"><i class="bi bi-check-lg"></i></div>
                </div>
                <p class="theme-card-desc">High-contrast mode for bright office environments.</p>
            </div>
        </div>
    </div>
</div>

<div class="grid grid-cols-2 gap-6 mb-6">
    <!-- General Settings Form -->
    <div class="card">
        <div class="card-header">
            <div class="card-title"><i class="bi bi-sliders text-primary"></i> General Municipal Parameters</div>
        </div>
        <div class="card-body">
            <form method="POST" action="">
                <input type="hidden" name="<?= CSRF_TOKEN_NAME ?>" value="<?= htmlspecialchars($csrfToken) ?>">
                <input type="hidden" name="save_settings" value="1">
                
                <div class="form-group mb-3">
                    <label class="form-label">Municipality / City Name</label>
                    <input type="text" name="settings[city_name]" class="form-control" value="<?= htmlspecialchars($settings['city_name'] ?? 'Smart City Administration') ?>">
                </div>
                
                <div class="grid grid-cols-2 gap-3 mb-3">
                    <div class="form-group">
                        <label class="form-label">Default Map Latitude</label>
                        <input type="text" name="settings[map_default_lat]" class="form-control" value="<?= htmlspecialchars($settings['map_default_lat'] ?? '18.5204') ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Default Map Longitude</label>
                        <input type="text" name="settings[map_default_lng]" class="form-control" value="<?= htmlspecialchars($settings['map_default_lng'] ?? '73.8567') ?>">
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-3 mb-4">
                    <div class="form-group">
                        <label class="form-label">Dashboard Refresh (Seconds)</label>
                        <input type="number" name="settings[dashboard_refresh_seconds]" class="form-control" value="<?= htmlspecialchars($settings['dashboard_refresh_seconds'] ?? '30') ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Emergency Polling (Seconds)</label>
                        <input type="number" name="settings[emergency_refresh_seconds]" class="form-control" value="<?= htmlspecialchars($settings['emergency_refresh_seconds'] ?? '15') ?>">
                    </div>
                </div>

                <button type="submit" class="btn btn-primary w-full">Save System Settings</button>
            </form>
        </div>
    </div>

    <!-- SLA Thresholds Overview -->
    <div class="card">
        <div class="card-header">
            <div class="card-title"><i class="bi bi-clock-history text-warning"></i> SLA Dispatch Response Targets</div>
        </div>
        <div class="table-wrapper" style="border:none;border-radius:0;">
            <table class="table">
                <thead>
                    <tr>
                        <th>Category</th>
                        <th>Urgency</th>
                        <th>Max Response</th>
                        <th>Target Resolution</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach (array_slice($slaList, 0, 10) as $sla): ?>
                    <tr>
                        <td><code class="mono"><?= htmlspecialchars($sla['category_id']) ?></code></td>
                        <td><span class="badge badge-<?= $sla['urgency_level'] === 'emergency' ? 'critical' : $sla['urgency_level'] ?>"><?= htmlspecialchars($sla['urgency_level']) ?></span></td>
                        <td><strong><?= $sla['response_time_minutes'] ?> mins</strong></td>
                        <td><?= $sla['resolution_time_hours'] ?> hours</td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/layout_end.php'; ?>
