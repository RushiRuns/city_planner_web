<?php
// ============================================================
// City Planner Web Admin — Authentication API
// POST /api/auth.php?action=login|logout|check
// ============================================================
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/rbac.php';
require_once __DIR__ . '/../includes/audit.php';

header('Content-Type: application/json; charset=UTF-8');
startSecureSession();

$action = $_GET['action'] ?? ($_POST['action'] ?? 'login');
$input  = json_decode(file_get_contents('php://input'), true) ?? [];
$action = $action ?: ($input['action'] ?? 'login');

switch ($action) {

    // ── LOGIN ─────────────────────────────────────────────────
    case 'login':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            jsonResponse(['success' => false, 'message' => 'Method not allowed.'], 405);
        }

        $email    = strtolower(trim($input['email'] ?? ($_POST['email'] ?? '')));
        $password = $input['password'] ?? ($_POST['password'] ?? '');

        if (empty($email) || empty($password)) {
            jsonResponse(['success' => false, 'message' => 'Email and password are required.'], 400);
        }

        // ── Brute-force protection ────────────────────────────
        $ip  = getClientIp();
        $db  = getDB();
        $esc = $db->real_escape_string($email);
        $ipE = $db->real_escape_string($ip);

        $attempts = $db->query(
            "SELECT COUNT(*) AS cnt FROM login_attempts
             WHERE email='$esc' AND ip_address='$ipE'
             AND attempted_at > DATE_SUB(NOW(), INTERVAL " . LOGIN_LOCKOUT_MINUTES . " MINUTE)"
        );
        $attRow = ($attempts && $attempts instanceof mysqli_result) ? $attempts->fetch_assoc() : ['cnt' => 0];
        if ((int)($attRow['cnt'] ?? 0) >= MAX_LOGIN_ATTEMPTS) {
            jsonResponse(['success' => false, 'message' => 'Too many failed attempts. Please wait ' . LOGIN_LOCKOUT_MINUTES . ' minutes.'], 429);
        }

        // ── Check web_admins table (bcrypt passwords) ─────────
        $stmt = $db->prepare("SELECT * FROM web_admins WHERE email = ? AND is_active = 1 LIMIT 1");
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $admin = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        $authenticated = false;

        if ($admin) {
            // Try bcrypt first
            if (password_verify($password, $admin['password'])) {
                $authenticated = true;
            }
            // Fallback: plaintext comparison for legacy seeded admins
            elseif ($admin['password'] === $password) {
                $authenticated = true;
                // Upgrade to bcrypt
                $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
                $sid  = (int)$admin['id'];
                $db->query("UPDATE web_admins SET password='$hash' WHERE id=$sid");
            }
        }

        // ── Fallback: Check existing sub_admins table ─────────
        if (!$authenticated && !$admin) {
            $stmt2 = $db->prepare("SELECT * FROM sub_admins WHERE email = ? AND is_active = 1 LIMIT 1");
            $stmt2->bind_param('s', $email);
            $stmt2->execute();
            $sa = $stmt2->get_result()->fetch_assoc();
            $stmt2->close();

            if ($sa && $sa['password'] === $password) {
                // Map sub_admin to web admin format
                $admin = [
                    'id'         => 'sa_' . $sa['id'],
                    'name'       => $sa['name'],
                    'email'      => $sa['email'],
                    'phone'      => $sa['phone'],
                    'role'       => 'dept_admin',
                    'categories' => json_encode(array_filter(array_map('trim', explode(',', $sa['categories'] ?? '')))),
                    'station_id' => $sa['station_id'] ?? null,
                    'department' => '',
                ];
                $authenticated = true;
            }
        }

        if (!$authenticated) {
            // Record failed attempt
            $db->query("INSERT INTO login_attempts (email, ip_address) VALUES ('$esc', '$ipE')");
            logAudit('login_failed', 'auth', $email, '', 'Invalid credentials');
            jsonResponse(['success' => false, 'message' => 'Invalid email or password.'], 401);
        }

        // ── Success — create session ───────────────────────────
        setSessionAdmin($admin);

        // Update last login
        if (is_numeric(substr((string)$admin['id'], 0, 1))) {
            $adminIdInt = (int)$admin['id'];
            $db->query("UPDATE web_admins SET last_login=NOW(), login_count=login_count+1 WHERE id=$adminIdInt");
        }

        // Clear failed attempts
        $db->query("DELETE FROM login_attempts WHERE email='$esc' AND ip_address='$ipE'");

        logAudit('login', 'auth', $email, '', 'Login successful');

        jsonResponse([
            'success' => true,
            'message' => 'Login successful.',
            'admin'   => [
                'name'       => $admin['name'],
                'email'      => $admin['email'],
                'role'       => $admin['role'],
                'department' => $admin['department'] ?? '',
                'categories' => json_decode($admin['categories'] ?? '[]', true),
            ],
            'redirect' => BASE_URL . 'dashboard.php',
        ]);
        break;

    // ── LOGOUT ────────────────────────────────────────────────
    case 'logout':
        logAudit('logout', 'auth', $_SESSION['admin_email'] ?? '');
        destroySession();
        jsonResponse(['success' => true, 'redirect' => BASE_URL . 'index.php']);
        break;

    // ── CHECK SESSION ─────────────────────────────────────────
    case 'check':
        jsonResponse([
            'success'      => isLoggedIn(),
            'authenticated' => isLoggedIn(),
            'admin'        => getSessionAdmin(),
        ]);
        break;

    default:
        jsonResponse(['success' => false, 'message' => 'Unknown action.'], 400);
}
