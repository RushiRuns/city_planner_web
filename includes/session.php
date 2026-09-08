<?php
// ============================================================
// City Planner Web Admin — Secure Session Management
// ============================================================
require_once __DIR__ . '/config.php';

function startSecureSession(): void {
    if (session_status() === PHP_SESSION_ACTIVE) return;

    $cookieParams = [
        'lifetime' => SESSION_LIFETIME,
        'path'     => '/',
        'domain'   => '',
        'secure'   => isset($_SERVER['HTTPS']),
        'httponly' => true,
        'samesite' => 'Strict',
    ];

    session_name(SESSION_NAME);
    session_set_cookie_params($cookieParams);
    session_start();

    // Regenerate session ID periodically to prevent fixation
    if (empty($_SESSION['_initiated'])) {
        session_regenerate_id(true);
        $_SESSION['_initiated'] = true;
        $_SESSION['_created']   = time();
    }

    // Session expiry check
    if (isset($_SESSION['_last_activity'])) {
        if (time() - $_SESSION['_last_activity'] > SESSION_LIFETIME) {
            destroySession();
            return;
        }
    }
    $_SESSION['_last_activity'] = time();

    // Session fingerprinting (prevent session hijacking)
    $fingerprint = md5($_SERVER['HTTP_USER_AGENT'] ?? '' . substr($_SERVER['REMOTE_ADDR'] ?? '', 0, 10));
    if (isset($_SESSION['_fingerprint'])) {
        if ($_SESSION['_fingerprint'] !== $fingerprint) {
            destroySession();
            return;
        }
    } else {
        $_SESSION['_fingerprint'] = $fingerprint;
    }
}

function destroySession(): void {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params['path'], $params['domain'],
            $params['secure'], $params['httponly']
        );
    }
    session_destroy();
}

function isLoggedIn(): bool {
    return !empty($_SESSION['admin_id']) && !empty($_SESSION['admin_email']);
}

function getSessionAdmin(): ?array {
    if (!isLoggedIn()) return null;
    return [
        'id'         => $_SESSION['admin_id'],
        'name'       => $_SESSION['admin_name'],
        'email'      => $_SESSION['admin_email'],
        'role'       => $_SESSION['admin_role'],
        'categories' => $_SESSION['admin_categories'] ?? [],
        'station_id' => $_SESSION['admin_station_id'] ?? null,
        'department' => $_SESSION['admin_department'] ?? '',
    ];
}

function setSessionAdmin(array $admin): void {
    $_SESSION['admin_id']          = $admin['id'];
    $_SESSION['admin_name']        = $admin['name'];
    $_SESSION['admin_email']       = $admin['email'];
    $_SESSION['admin_role']        = $admin['role'];
    $_SESSION['admin_categories']  = is_string($admin['categories']) ? json_decode($admin['categories'], true) : ($admin['categories'] ?? []);
    $_SESSION['admin_station_id']  = $admin['station_id'] ?? null;
    $_SESSION['admin_department']  = $admin['department'] ?? '';
    $_SESSION['login_time']        = time();
}
