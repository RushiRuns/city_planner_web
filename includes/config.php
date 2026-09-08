<?php
// ============================================================
// City Planner Web Admin — Configuration
// ============================================================

// Environment detection
define('APP_ENV', getenv('APP_ENV') ?: 'development');
define('APP_NAME', 'City Planner Admin');
define('APP_VERSION', '1.0.0');

// ── Database Configuration ────────────────────────────────────
define('DB_HOST', getenv('DB_HOST') ?: '45.199.139.15');
define('DB_USER', getenv('DB_USER') ?: 'aspryde_1_smart_city');
define('DB_PASS', getenv('DB_PASS') ?: 'RAMVP@5nssn');
define('DB_NAME', getenv('DB_NAME') ?: 'aspryde_1_smart_city');
define('DB_CHARSET', 'utf8mb4');

// ── Base URLs (Dynamic Auto-Detection with Env Override) ───────
if (!defined('BASE_URL')) {
    $envBase = getenv('BASE_URL');
    if ($envBase) {
        define('BASE_URL', rtrim($envBase, '/') . '/');
    } else {
        $scriptName = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
        $pos = stripos($scriptName, '/web_admin');
        if ($pos !== false) {
            $base = substr($scriptName, 0, $pos + strlen('/web_admin')) . '/';
        } else {
            $scriptDir = dirname($scriptName);
            if (basename($scriptDir) === 'api' || basename($scriptDir) === 'includes') {
                $scriptDir = dirname($scriptDir);
            }
            $base = rtrim($scriptDir, '/') . '/';
        }
        define('BASE_URL', $base);
    }
}
define('API_URL', BASE_URL . 'api/');
define('LEGACY_API_URL', BASE_URL . '../City_planner/php/');

// ── Security ─────────────────────────────────────────────────
define('SESSION_LIFETIME', 7200);         // 2 hours
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOGIN_LOCKOUT_MINUTES', 15);
define('CSRF_TOKEN_NAME', '_csrf_token');
define('SESSION_NAME', 'cp_admin_session');

// ── Timezone ─────────────────────────────────────────────────
date_default_timezone_set('Asia/Kolkata');

// ── Department & Category Definitions ────────────────────────
define('DEPARTMENTS', [
    'emergency' => [
        'name'       => 'Emergency Services',
        'color'      => '#EF4444',
        'icon'       => 'bi-exclamation-triangle-fill',
        'categories' => ['fire', 'fire_bus', 'ambulance', 'rescue', 'police', 'tracker', 'emergency'],
    ],
    'municipal' => [
        'name'       => 'Municipal Services',
        'color'      => '#3B82F6',
        'icon'       => 'bi-building',
        'categories' => ['road_management', 'water_management', 'waste_management', 'sewage_management', 'clean_management', 'citizen_issue'],
    ],
]);

define('CATEGORIES', [
    'fire'               => ['name' => 'Fire Dispatch',        'icon' => 'bi-fire',                  'color' => '#EF4444', 'table' => 'fire_bus_requests',          'urgency' => 'high'],
    'fire_bus'           => ['name' => 'Fire Bus',             'icon' => 'bi-truck',                  'color' => '#DC2626', 'table' => 'fire_bus_requests',          'urgency' => 'high'],
    'ambulance'          => ['name' => 'Ambulance',            'icon' => 'bi-heart-pulse-fill',       'color' => '#EC4899', 'table' => 'ambulance_requests',         'urgency' => 'high'],
    'rescue'             => ['name' => 'Rescue',               'icon' => 'bi-shield-fill',            'color' => '#9333EA', 'table' => 'rescue_requests',            'urgency' => 'high'],
    'police'             => ['name' => 'Police',               'icon' => 'bi-shield-check',           'color' => '#3B82F6', 'table' => 'police_requests',            'urgency' => 'high'],
    'tracker'            => ['name' => 'GPS Tracker',          'icon' => 'bi-geo-alt-fill',           'color' => '#0D9488', 'table' => 'tracker_requests',           'urgency' => 'medium'],
    'emergency'          => ['name' => 'General SOS',          'icon' => 'bi-exclamation-octagon',    'color' => '#F59E0B', 'table' => 'general_emergency_requests', 'urgency' => 'high'],
    'road_management'    => ['name' => 'Road Management',      'icon' => 'bi-sign-turn-right',        'color' => '#EA580C', 'table' => 'road_management_requests',   'urgency' => 'medium'],
    'water_management'   => ['name' => 'Water Management',     'icon' => 'bi-droplet-fill',           'color' => '#0EA5E9', 'table' => 'water_management_requests',  'urgency' => 'medium'],
    'waste_management'   => ['name' => 'Waste Management',     'icon' => 'bi-trash3-fill',            'color' => '#16A34A', 'table' => 'waste_management_requests',  'urgency' => 'medium'],
    'sewage_management'  => ['name' => 'Sewage Management',    'icon' => 'bi-moisture',               'color' => '#4F46E5', 'table' => 'sewage_management_requests', 'urgency' => 'medium'],
    'clean_management'   => ['name' => 'Street Cleaning',      'icon' => 'bi-brush-fill',             'color' => '#22C55E', 'table' => 'clean_management_requests',  'urgency' => 'medium'],
    'citizen_issue'      => ['name' => 'Citizen Issues',       'icon' => 'bi-person-raised-hand',     'color' => '#F97316', 'table' => 'citizen_issue_requests',     'urgency' => 'medium'],
]);

// ── Role & Department Agency Profiles ────────────────────────
define('AGENCY_PROFILES', [
    'police' => [
        'key'            => 'police',
        'name'           => 'Metro Police Tactical Command',
        'short_name'     => 'Police Command',
        'badge'          => '🚔',
        'icon'           => 'bi-shield-check',
        'callsign'       => 'POLICE-CAD-01',
        'theme'          => 'police',
        'color'          => '#3B82F6',
        'accent'         => '#F59E0B',
        'gradient'       => 'linear-gradient(135deg, #1D4ED8 0%, #3B82F6 50%, #60A5FA 100%)',
        'slogan'         => 'Public Safety, Law Enforcement & Tactical Dispatch',
        'cad_code'       => '10-8 IN SERVICE',
        'emergency_code' => '100 / 911 SOS',
        'unit_label'     => 'Patrol Units',
        'station_label'  => 'Police Precincts',
        'incident_label' => 'Police Incidents',
        'categories'     => ['police'],
    ],
    'fire' => [
        'key'            => 'fire',
        'name'           => 'Fire & Hazard Operations Command',
        'short_name'     => 'Fire & Rescue',
        'badge'          => '🚒',
        'icon'           => 'bi-fire',
        'callsign'       => 'FIRE-DISPATCH-02',
        'theme'          => 'fire',
        'color'          => '#EF4444',
        'accent'         => '#F97316',
        'gradient'       => 'linear-gradient(135deg, #B91C1C 0%, #EF4444 50%, #F97316 100%)',
        'slogan'         => 'Emergency Fire Suppression & HazMat Response',
        'cad_code'       => 'ALARM READY',
        'emergency_code' => '101 FIRE DISPATCH',
        'unit_label'     => 'Add Employee',
        'station_label'  => 'Fire Stations',
        'incident_label' => 'Fire & Hazard Alarms',
        'categories'     => ['fire', 'fire_bus'],
    ],
    'medical' => [
        'key'            => 'medical',
        'name'           => 'Emergency Medical Operations (EMS)',
        'short_name'     => 'EMS Operations',
        'badge'          => '🚑',
        'icon'           => 'bi-heart-pulse-fill',
        'callsign'       => 'MEDIC-CONTROL-03',
        'theme'          => 'medical',
        'color'          => '#06B6D4',
        'accent'         => '#EC4899',
        'gradient'       => 'linear-gradient(135deg, #0E7490 0%, #06B6D4 50%, #2DD4BF 100%)',
        'slogan'         => 'Paramedic Response, Triage & Critical Care Telemetry',
        'cad_code'       => 'TRIAGE ACTIVE',
        'emergency_code' => '108 AMBULANCE',
        'unit_label'     => 'Ambulance Units',
        'station_label'  => 'EMS Bases / Hospitals',
        'incident_label' => 'Medical Emergencies',
        'categories'     => ['ambulance'],
    ],
    'rescue' => [
        'key'            => 'rescue',
        'name'           => 'Search & Tactical Rescue Matrix',
        'short_name'     => 'Search & Rescue',
        'badge'          => '🚁',
        'icon'           => 'bi-crosshair',
        'callsign'       => 'SAR-TRACK-04',
        'theme'          => 'rescue',
        'color'          => '#8B5CF6',
        'accent'         => '#84CC16',
        'gradient'       => 'linear-gradient(135deg, #6D28D9 0%, #8B5CF6 50%, #A855F7 100%)',
        'slogan'         => 'Air & Ground SAR, GPS Telemetry & SOS Tracking',
        'cad_code'       => 'BEACON ONLINE',
        'emergency_code' => '112 SOS / SAR',
        'unit_label'     => 'SAR Squads',
        'station_label'  => 'SAR Outposts',
        'incident_label' => 'SAR Alerts & GPS Pings',
        'categories'     => ['rescue', 'tracker', 'emergency'],
    ],
    'municipal' => [
        'key'            => 'municipal',
        'name'           => 'Civic Infrastructure Operations',
        'short_name'     => 'Municipal Works',
        'badge'          => '🏛️',
        'icon'           => 'bi-building-gear',
        'callsign'       => 'CIVIC-GRID-05',
        'theme'          => 'municipal',
        'color'          => '#10B981',
        'accent'         => '#F59E0B',
        'gradient'       => 'linear-gradient(135deg, #047857 0%, #10B981 50%, #34D399 100%)',
        'slogan'         => 'Public Works, Sanitation, Water Grid & Urban Maintenance',
        'cad_code'       => 'GRID OPTIMAL',
        'emergency_code' => '1800-CITY-HELP',
        'unit_label'     => 'Service Crews',
        'station_label'  => 'Municipal Yards',
        'incident_label' => 'Civic Work Orders',
        'categories'     => ['road_management', 'water_management', 'waste_management', 'sewage_management', 'clean_management', 'citizen_issue'],
    ],
    'superadmin' => [
        'key'            => 'superadmin',
        'name'           => 'City Executive Command Center',
        'short_name'     => 'Executive HQ',
        'badge'          => '🏙️',
        'icon'           => 'bi-cpu-fill',
        'callsign'       => 'GLOBAL-MATRIX-00',
        'theme'          => 'superadmin',
        'color'          => '#6366F1',
        'accent'         => '#38BDF8',
        'gradient'       => 'linear-gradient(135deg, #4338CA 0%, #6366F1 50%, #8B5CF6 100%)',
        'slogan'         => 'Unified Multi-Agency Smart City Command & Operations',
        'cad_code'       => 'ALL SYSTEMS ONLINE',
        'emergency_code' => 'ALL EMERGENCY CHANNELS',
        'unit_label'     => 'All City Units',
        'station_label'  => 'All Stations & Depots',
        'incident_label' => 'Unified Incidents',
        'categories'     => ['fire', 'fire_bus', 'ambulance', 'rescue', 'police', 'tracker', 'emergency', 'road_management', 'water_management', 'waste_management', 'sewage_management', 'clean_management', 'citizen_issue'],
    ],
]);

// All request tables (used for UNION queries)
define('ALL_REQUEST_TABLES', [
    'fire_bus_requests', 'ambulance_requests', 'rescue_requests', 'police_requests',
    'tracker_requests', 'general_emergency_requests', 'waste_management_requests',
    'water_management_requests', 'sewage_management_requests', 'road_management_requests',
    'clean_management_requests', 'citizen_issue_requests', 'emergency_requests', 'citizen_requests',
]);

// Category → table mapping
define('CATEGORY_TABLE_MAP', [
    'fire'               => 'fire_bus_requests',
    'fire_bus'           => 'fire_bus_requests',
    'ambulance'          => 'ambulance_requests',
    'rescue'             => 'rescue_requests',
    'police'             => 'police_requests',
    'tracker'            => 'tracker_requests',
    'emergency'          => 'general_emergency_requests',
    'road_management'    => 'road_management_requests',
    'water_management'   => 'water_management_requests',
    'waste_management'   => 'waste_management_requests',
    'sewage_management'  => 'sewage_management_requests',
    'clean_management'   => 'clean_management_requests',
    'citizen_issue'      => 'citizen_issue_requests',
]);

// Status workflow
define('REQUEST_STATUSES', ['submitted', 'acknowledged', 'dispatched', 'inProgress', 'resolved', 'cancelled']);
define('URGENCY_LEVELS',   ['emergency' => 'Emergency', 'high' => 'High', 'medium' => 'Medium', 'low' => 'Low']);

// ── Database Connection (singleton) ──────────────────────────
function getDB(): mysqli {
    static $conn = null;
    if ($conn === null) {
        mysqli_report(MYSQLI_REPORT_OFF);
        $conn = @new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
        
        // Fallback attempt: if DB_HOST is remote and failed, try localhost / 127.0.0.1 in case running on local web server
        if ($conn->connect_error && DB_HOST !== 'localhost' && DB_HOST !== '127.0.0.1') {
            $fallback = @new mysqli('localhost', DB_USER, DB_PASS, DB_NAME);
            if (!$fallback->connect_error) {
                $conn = $fallback;
            }
        }

        if ($conn->connect_error) {
            error_log('DB Connection failed: ' . $conn->connect_error);
            if (!headers_sent()) {
                header('Content-Type: application/json');
                http_response_code(503);
            }
            die(json_encode(['success' => false, 'message' => 'Database connection failed: ' . $conn->connect_error]));
        }
        $conn->set_charset(DB_CHARSET);

        // Auto-initialize auxiliary tables if not existing
        $conn->query("CREATE TABLE IF NOT EXISTS `web_notifications` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `admin_id` INT NULL,
            `title` VARCHAR(255) NOT NULL,
            `message` TEXT NOT NULL,
            `severity` VARCHAR(50) DEFAULT 'info',
            `is_read` TINYINT(1) DEFAULT 0,
            `action_url` VARCHAR(255) NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $conn->query("CREATE TABLE IF NOT EXISTS `audit_logs` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `admin_id` INT NULL,
            `admin_name` VARCHAR(255) NULL,
            `action` VARCHAR(100) NOT NULL,
            `entity_type` VARCHAR(100) NOT NULL,
            `target_id` VARCHAR(100) NULL,
            `details` TEXT NULL,
            `ip_address` VARCHAR(50) NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $conn->query("CREATE TABLE IF NOT EXISTS `system_settings` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `setting_key` VARCHAR(100) NOT NULL UNIQUE,
            `setting_value` TEXT NULL,
            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $conn->query("CREATE TABLE IF NOT EXISTS `sla_config` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `category_id` VARCHAR(50) NOT NULL,
            `urgency_level` VARCHAR(50) NOT NULL,
            `response_time_minutes` INT NOT NULL DEFAULT 15,
            `resolution_time_hours` INT NOT NULL DEFAULT 24,
            UNIQUE KEY `cat_urg` (`category_id`, `urgency_level`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $conn->query("CREATE TABLE IF NOT EXISTS `web_admins` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `name` VARCHAR(255) NOT NULL,
            `email` VARCHAR(255) NOT NULL UNIQUE,
            `password` VARCHAR(255) NOT NULL,
            `role` VARCHAR(50) NOT NULL DEFAULT 'dept_admin',
            `department` VARCHAR(100) DEFAULT NULL,
            `categories` TEXT DEFAULT NULL,
            `station_id` VARCHAR(50) DEFAULT NULL,
            `phone` VARCHAR(50) DEFAULT NULL,
            `is_active` TINYINT(1) NOT NULL DEFAULT 1,
            `last_login` DATETIME DEFAULT NULL,
            `login_count` INT NOT NULL DEFAULT 0,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $conn->query("CREATE TABLE IF NOT EXISTS `login_attempts` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `email` VARCHAR(255) NOT NULL,
            `ip_address` VARCHAR(50) NOT NULL,
            `attempted_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        // Auto-migrate: Add geolocation columns to service_stations for map feature
        $colCheck = $conn->query("SELECT COUNT(*) AS cnt FROM information_schema.COLUMNS 
            WHERE TABLE_SCHEMA = '" . DB_NAME . "' AND TABLE_NAME = 'service_stations' AND COLUMN_NAME = 'latitude'");
        if ($colCheck && (int)$colCheck->fetch_assoc()['cnt'] === 0) {
            $conn->query("ALTER TABLE `service_stations` 
                ADD COLUMN `latitude` DECIMAL(10,7) NOT NULL DEFAULT 0 AFTER `city`,
                ADD COLUMN `longitude` DECIMAL(10,7) NOT NULL DEFAULT 0 AFTER `latitude`,
                ADD COLUMN `address` VARCHAR(500) DEFAULT NULL AFTER `longitude`");
        }
    }
    return $conn;
}

// ── JSON Response Helper ──────────────────────────────────────
function jsonResponse(array $data, int $code = 200): void {
    header('Content-Type: application/json; charset=UTF-8');
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit();
}

// ── Sanitize Input ────────────────────────────────────────────
function sanitize(string $str): string {
    return htmlspecialchars(trim($str), ENT_QUOTES, 'UTF-8');
}

// ── Generate CSRF Token ───────────────────────────────────────
function generateCsrfToken(): string {
    if (empty($_SESSION[CSRF_TOKEN_NAME])) {
        $_SESSION[CSRF_TOKEN_NAME] = bin2hex(random_bytes(32));
    }
    return $_SESSION[CSRF_TOKEN_NAME];
}

// ── Validate CSRF Token ───────────────────────────────────────
function validateCsrf(): bool {
    $token = $_POST[CSRF_TOKEN_NAME] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    return !empty($token) && hash_equals($_SESSION[CSRF_TOKEN_NAME] ?? '', $token);
}

// ── Pagination Helper ─────────────────────────────────────────
function getPagination(int $total, int $page, int $perPage): array {
    $totalPages = (int) ceil($total / $perPage);
    return [
        'total'       => $total,
        'per_page'    => $perPage,
        'current'     => $page,
        'total_pages' => $totalPages,
        'has_prev'    => $page > 1,
        'has_next'    => $page < $totalPages,
    ];
}

// ── Dynamic Services Helper (Database `services` table) ──────
function getDbServices(): array {
    static $services = null;
    if ($services !== null) {
        return $services;
    }
    $services = [];
    try {
        $db = getDB();
        $res = $db->query("SELECT * FROM `services` ORDER BY category ASC, id ASC");
        if ($res && $res->num_rows > 0) {
            while ($row = $res->fetch_assoc()) {
                $k = $row['service_key'];
                $meta = CATEGORIES[$k] ?? [];
                $services[$k] = [
                    'id'           => (int)$row['id'],
                    'service_key'  => $row['service_key'],
                    'service_name' => $row['service_name'],
                    'category'     => $row['category'],
                    'icon'         => $meta['icon'] ?? 'bi-gear-fill',
                    'color'        => $meta['color'] ?? '#6366F1',
                    'table'        => $meta['table'] ?? ($k . '_requests'),
                    'urgency'      => $meta['urgency'] ?? ($row['category'] === 'Emergency' ? 'high' : 'medium'),
                ];
            }
        }
    } catch (\Throwable $e) {
        error_log('getDbServices error: ' . $e->getMessage());
    }

    if (empty($services)) {
        // Fallback to static CATEGORIES definition
        foreach (CATEGORIES as $k => $c) {
            $isEmergency = in_array($k, ['fire', 'fire_bus', 'ambulance', 'rescue', 'police', 'tracker', 'emergency']);
            $services[$k] = [
                'id'           => 0,
                'service_key'  => $k,
                'service_name' => $c['name'],
                'category'     => $isEmergency ? 'Emergency' : 'Citizen',
                'icon'         => $c['icon'],
                'color'        => $c['color'],
                'table'        => $c['table'],
                'urgency'      => $c['urgency'],
            ];
        }
    }
    return $services;
}

// ── Get Single Service Metadata ───────────────────────────────
function getServiceInfo(string $serviceKey): array {
    $all = getDbServices();
    if (isset($all[$serviceKey])) {
        return $all[$serviceKey];
    }
    $meta = CATEGORIES[$serviceKey] ?? [];
    return [
        'id'           => 0,
        'service_key'  => $serviceKey,
        'service_name' => $meta['name'] ?? ucfirst(str_replace('_', ' ', $serviceKey)),
        'category'     => 'Citizen',
        'icon'         => $meta['icon'] ?? 'bi-gear-fill',
        'color'        => $meta['color'] ?? '#64748B',
        'table'        => $meta['table'] ?? ($serviceKey . '_requests'),
        'urgency'      => $meta['urgency'] ?? 'medium',
    ];
}

