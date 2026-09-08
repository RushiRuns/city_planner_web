<?php
// ============================================================
// Shared sidebar + header include — Smart Command Platform
// Usage: require_once 'includes/layout.php';
// Expects: $pageTitle, $activePage variables to be set
// ============================================================
if (!defined('BASE_URL')) require_once __DIR__ . '/config.php';
require_once __DIR__ . '/session.php';
require_once __DIR__ . '/rbac.php';
requireLogin();

$admin         = getSessionAdmin();
$adminName     = htmlspecialchars($admin['name']);
$adminEmail    = htmlspecialchars($admin['email']);
$adminRole     = $admin['role'];
$adminInitials = strtoupper(substr($admin['name'], 0, 1) . (strpos($admin['name'], ' ') ? substr(substr($admin['name'], strpos($admin['name'], ' ') + 1), 0, 1) : ''));
$isSuperAdmin  = ($adminRole === 'super_admin');

// Resolve active agency identity profile (Police, Fire, EMS, Rescue, Municipal, Superadmin)
$agencyProfile = getAdminAgencyProfile($admin);
$agencyKey     = $agencyProfile['key'];

// Notification count
$db = getDB();
$adminId = is_numeric($admin['id']) ? (int)$admin['id'] : 0;
$notifCount = 0;
if ($adminId > 0) {
    $nr = $db->query("SELECT COUNT(*) AS cnt FROM web_notifications WHERE (admin_id=$adminId OR admin_id IS NULL) AND is_read=0");
    if ($nr && $nr instanceof mysqli_result) $notifCount = (int)$nr->fetch_assoc()['cnt'];
}

// Nav items definition
$navItems = [
    ['icon' => 'bi-grid-fill',         'label' => 'Dashboard',      'href' => 'dashboard.php',      'page' => 'dashboard',      'perm' => 'dashboard.view'],
    ['icon' => 'bi-broadcast-pin',     'label' => 'Live Command',   'href' => 'live_command.php',   'page' => 'live_command',   'perm' => 'tracking.view'],
    ['icon' => 'bi-clipboard2-pulse',  'label' => $agencyProfile['incident_label'] ?? 'Requests', 'href' => 'requests.php',       'page' => 'requests',       'perm' => 'requests.view'],
    ['icon' => 'bi-grid-3x3-gap-fill', 'label' => 'Services',       'href' => 'services.php',       'page' => 'services',       'perm' => 'services.view'],
    ['icon' => 'bi-building-fill',     'label' => 'Departments',    'href' => 'departments.php',    'page' => 'departments',    'perm' => 'departments.view'],
    ['icon' => 'bi-geo-alt-fill',      'label' => $agencyProfile['station_label'] ?? 'Stations', 'href' => 'stations.php',       'page' => 'stations',       'perm' => 'stations.view'],
    ['icon' => 'bi-person-plus-fill', 'label' => $agencyProfile['unit_label'] ?? 'Add Employee', 'href' => 'employees.php',      'page' => 'employees',      'perm' => 'employees.view'],
    ['icon' => 'bi-person-lines-fill', 'label' => 'Citizens',       'href' => 'citizens.php',       'page' => 'citizens',       'perm' => 'citizens.view'],
];
$adminNavItems = [
    ['icon' => 'bi-shield-lock-fill',  'label' => 'Admin Management', 'href' => 'admins.php',         'page' => 'admins',         'perm' => 'admins.view', 'super_only' => true, 'pill' => 'Central'],
    ['icon' => 'bi-bar-chart-fill',    'label' => 'Analytics',        'href' => 'analytics.php',      'page' => 'analytics',      'perm' => 'analytics.view'],
    ['icon' => 'bi-file-earmark-text', 'label' => 'Reports',          'href' => 'reports.php',        'page' => 'reports',        'perm' => 'reports.view'],
];
$systemNavItems = [
    ['icon' => 'bi-bell-fill',         'label' => 'Notifications',  'href' => 'notifications.php',  'page' => 'notifications',  'perm' => 'notifications.view', 'badge' => $notifCount],
    ['icon' => 'bi-journal-text',      'label' => 'Audit Logs',     'href' => 'audit_logs.php',     'page' => 'audit_logs',     'perm' => 'audit_logs.view'],
    ['icon' => 'bi-activity',          'label' => 'System Health',  'href' => 'system_health.php',  'page' => 'system_health',  'perm' => 'system_health.view'],
    ['icon' => 'bi-wifi-off',          'label' => 'Offline Sync',   'href' => 'offline_sync.php',   'page' => 'offline_sync',   'perm' => 'offline_sync.view'],
    ['icon' => 'bi-gear-fill',         'label' => 'Settings',       'href' => 'settings.php',       'page' => 'settings',       'perm' => 'settings.manage'],
];

function renderNavItem(array $item, string $activePage, bool $collapsed = false): string {
    if (!empty($item['super_only']) && !isSuperAdmin()) return '';
    if (!hasPermission($item['perm'])) return '';
    $active  = ($item['page'] === $activePage) ? 'active' : '';
    $href    = htmlspecialchars(BASE_URL . $item['href']);
    $icon    = htmlspecialchars($item['icon']);
    $label   = htmlspecialchars($item['label']);
    $badge   = '';
    if (isset($item['badge']) && $item['badge'] > 0) {
        $badge = '<span class="nav-badge">' . min(99, $item['badge']) . '</span>';
    } elseif (!empty($item['pill'])) {
        $badge = '<span class="nav-badge" style="background:rgba(99,102,241,0.25);color:#818cf8;border:1px solid rgba(99,102,241,0.4);font-size:9px;padding:2px 5px;letter-spacing:0.5px;font-weight:700;">' . htmlspecialchars($item['pill']) . '</span>';
    }
    return "<a href='$href' class='nav-item $active'><i class='bi $icon nav-icon'></i><span class='nav-label'>$label</span>$badge</a>";
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark" data-agency="<?= htmlspecialchars($agencyKey) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle ?? 'Dashboard') ?> — City Planner Admin</title>
    <meta name="description" content="<?= htmlspecialchars($agencyProfile['slogan']) ?>">
    <meta name="robots" content="noindex, nofollow">
    <meta name="agency" content="<?= htmlspecialchars($agencyKey) ?>">
    <meta name="base-url" content="<?= BASE_URL ?>">
    <meta name="csrf-token" content="<?= htmlspecialchars(generateCsrfToken()) ?>">

    <script>
    window.BASE_URL = "<?= BASE_URL ?>";
    </script>

    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&family=JetBrains+Mono:wght@400;500;700&family=Outfit:wght@400;600;700;800&display=swap" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <!-- Leaflet CSS -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
    <!-- Design System -->
    <link rel="stylesheet" href="<?= BASE_URL ?>css/design_system.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>css/layout.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>css/components.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>css/pages.css">

    <script>
    (function() {
        var t = localStorage.getItem('cp_theme') || 'dark';
        document.documentElement.setAttribute('data-theme', t);
    })();
    </script>

    <style>
    /* Inline critical above-fold styles */
    body { background: var(--bg-base); font-family: 'Inter', -apple-system, sans-serif; }
    h1, h2, h3, .page-title, .stat-value { font-family: 'Outfit', 'Inter', sans-serif; }
    </style>
</head>
<body>

<!-- ── Toast Container ──────────────────────────────────────── -->
<div class="toast-container" id="toastContainer"></div>

<!-- ── App Shell ────────────────────────────────────────────── -->
<div class="app-shell">

<!-- ── Sidebar ──────────────────────────────────────────────── -->
<aside class="sidebar" id="sidebar">

    <!-- Brand -->
    <a href="<?= BASE_URL ?>dashboard.php" class="sidebar-brand">
        <div class="sidebar-logo">🏙️</div>
        <div class="sidebar-brand-text">
            <div class="sidebar-brand-title">City Planner</div>
            <div class="sidebar-brand-sub"><?= htmlspecialchars($agencyProfile['callsign']) ?></div>
        </div>
    </a>

    <!-- Navigation -->
    <nav class="sidebar-nav">

        <!-- Main Nav -->
        <div class="nav-section">
            <div class="nav-section-label">Operations</div>
            <?php foreach ($navItems as $item): echo renderNavItem($item, $activePage ?? ''); endforeach; ?>
        </div>

        <?php if ($isSuperAdmin): ?>
        <!-- Central Admin Section -->
        <div class="nav-section">
            <div class="nav-section-label" style="color:var(--brand-primary);">
                Central Admin
            </div>
            <?= renderNavItem(['icon' => 'bi-shield-lock-fill', 'label' => 'Admin Management', 'href' => 'admins.php', 'page' => 'admins', 'perm' => 'admins.view', 'super_only' => true, 'pill' => 'Central'], $activePage ?? '') ?>
        </div>
        <?php endif; ?>

        <!-- Management Nav -->
        <div class="nav-section">
            <div class="nav-section-label">Management</div>
            <?php 
            foreach ($adminNavItems as $item): 
                if ($isSuperAdmin && $item['page'] === 'admins') continue; // Avoid duplicate since it's in Central Admin section
                echo renderNavItem($item, $activePage ?? ''); 
            endforeach; 
            ?>
        </div>

        <!-- System Nav -->
        <div class="nav-section">
            <div class="nav-section-label">System</div>
            <?php foreach ($systemNavItems as $item): echo renderNavItem($item, $activePage ?? ''); endforeach; ?>
        </div>

    </nav>

    <!-- User Info -->
    <div class="sidebar-user">
        <div class="sidebar-avatar" style="background:var(--agency-gradient, var(--brand-secondary));"><?= $adminInitials ?></div>
        <div class="sidebar-user-info">
            <div class="sidebar-user-name"><?= $adminName ?></div>
            <div class="sidebar-user-role"><?= htmlspecialchars($admin['role'] === 'super_admin' ? 'Super Administrator' : ($agencyProfile['name'] ?? 'Officer')) ?></div>
        </div>
        <a href="<?= BASE_URL ?>logout.php" class="sidebar-user-logout btn-icon"
           style="color:rgba(255,255,255,0.4);font-size:16px;background:none;border:none;cursor:pointer;"
           title="Logout">
            <i class="bi bi-box-arrow-right"></i>
        </a>
    </div>

    <!-- Collapse Toggle -->
    <div class="sidebar-toggle">
        <button class="sidebar-toggle-btn" id="sidebarToggle" title="Collapse sidebar">
            <i class="bi bi-layout-sidebar-reverse" id="sidebarToggleIcon"></i>
        </button>
    </div>
</aside>

<!-- ── Main Content ──────────────────────────────────────────── -->
<div class="main-content" id="mainContent">

    <!-- Top Header -->
    <header class="top-header">
        <!-- Mobile menu button -->
        <button class="header-action-btn hide-desktop" id="mobileMenuBtn" style="border:none;margin-right:4px;">
            <i class="bi bi-list"></i>
        </button>

        <!-- Right Actions -->
        <div class="header-actions">
            <!-- Theme Mode Toggle (Dark / Light) -->
            <button class="header-action-btn" id="themeToggle" title="Toggle Dark / Light Mode">
                <i class="bi bi-moon-fill" id="themeIcon"></i>
            </button>

            <!-- Notifications -->
            <a href="<?= BASE_URL ?>notifications.php" class="header-action-btn" title="Notifications">
                <i class="bi bi-bell"></i>
                <?php if ($notifCount > 0): ?>
                <span class="header-notif-count"></span>
                <?php endif; ?>
            </a>

            <!-- Emergency Alert -->
            <a href="<?= BASE_URL ?>requests.php?urgency=emergency" class="header-action-btn" id="emergencyBtn" title="Emergency Queue"
               style="border-color:rgba(239,68,68,0.4);color:var(--color-critical);background:rgba(239,68,68,0.1);">
                <i class="bi bi-exclamation-triangle-fill"></i>
            </a>
        </div>
    </header>

    <!-- Page Content injected below -->
    <main class="page-content" id="pageContent">

