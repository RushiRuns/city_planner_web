<?php
// ============================================================
// City Planner Web Admin — Login Command Portal
// ============================================================
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/session.php';

startSecureSession();

// Redirect if already logged in
if (isLoggedIn()) {
    header('Location: ' . BASE_URL . 'dashboard.php');
    exit();
}

$csrfToken = generateCsrfToken();
$redirectTo = htmlspecialchars($_GET['redirect'] ?? BASE_URL . 'dashboard.php');
$logoutMsg = isset($_GET['msg']) && $_GET['msg'] === 'logged_out' ? 'You have been securely signed out.' : '';
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login — City Planner Command Center</title>
    <meta name="description" content="Secure login portal for City Planner Smart City Administrative Platform.">
    <meta name="robots" content="noindex, nofollow">
    <meta name="base-url" content="<?= BASE_URL ?>">
    <meta name="csrf-token" content="<?= htmlspecialchars($csrfToken) ?>">

    <script>
    window.BASE_URL = "<?= BASE_URL ?>";
    </script>

    <!-- Fonts & Icons -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&family=JetBrains+Mono:wght@500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>css/design_system.css">

    <style>
    /* ── Login Portal Exclusive Styles ───────────────────────── */
    * { box-sizing: border-box; margin: 0; padding: 0; }
    html, body {
        min-height: 100vh;
        width: 100%;
        background: #020617;
        color: #F1F5F9;
        font-family: 'Inter', -apple-system, sans-serif;
        overflow-x: hidden;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    /* Ambient animated background */
    .bg-canvas {
        position: fixed; inset: 0; pointer-events: none; z-index: 0;
        background: 
            radial-gradient(circle at 20% 20%, rgba(30, 64, 175, 0.25) 0%, transparent 50%),
            radial-gradient(circle at 80% 80%, rgba(124, 58, 237, 0.2) 0%, transparent 50%),
            radial-gradient(circle at 50% 50%, rgba(15, 23, 42, 0.8) 0%, #020617 100%);
    }

    .grid-overlay {
        position: fixed; inset: 0; pointer-events: none; z-index: 0;
        background-image: 
            linear-gradient(rgba(59, 130, 246, 0.05) 1px, transparent 1px),
            linear-gradient(90deg, rgba(59, 130, 246, 0.05) 1px, transparent 1px);
        background-size: 48px 48px;
    }

    /* Main Container Layout */
    .portal-wrapper {
        position: relative; z-index: 1;
        width: 100%;
        max-width: 1120px;
        min-height: 640px;
        margin: 24px;
        display: grid;
        grid-template-columns: 1.15fr 1fr;
        background: rgba(15, 23, 42, 0.75);
        backdrop-filter: blur(20px);
        -webkit-backdrop-filter: blur(20px);
        border: 1px solid rgba(255, 255, 255, 0.08);
        border-radius: 24px;
        box-shadow: 0 25px 60px -15px rgba(0, 0, 0, 0.7), 0 0 40px rgba(59, 130, 246, 0.1);
        overflow: hidden;
    }

    @media (max-width: 960px) {
        .portal-wrapper {
            grid-template-columns: 1fr;
            max-width: 500px;
            min-height: auto;
            margin: 16px;
        }
        .portal-branding { display: none !important; }
    }

    /* Left Branding Section */
    .portal-branding {
        padding: 48px;
        display: flex;
        flex-direction: column;
        justify-content: space-between;
        background: linear-gradient(145deg, rgba(30, 41, 59, 0.4) 0%, rgba(15, 23, 42, 0.8) 100%);
        border-right: 1px solid rgba(255, 255, 255, 0.06);
        position: relative;
    }

    .brand-pill {
        display: inline-flex; align-items: center; gap: 8px;
        background: rgba(59, 130, 246, 0.12);
        border: 1px solid rgba(59, 130, 246, 0.3);
        border-radius: 100px;
        padding: 6px 14px;
        font-size: 11px; font-weight: 700;
        color: #93C5FD;
        text-transform: uppercase;
        letter-spacing: 0.08em;
        width: fit-content;
        margin-bottom: 24px;
    }

    .brand-hero-title {
        font-size: 38px;
        font-weight: 900;
        line-height: 1.15;
        letter-spacing: -0.03em;
        color: #fff;
        margin-bottom: 16px;
    }
    .brand-hero-title span {
        background: linear-gradient(135deg, #60A5FA 0%, #3B82F6 100%);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
    }

    .brand-hero-desc {
        font-size: 14px;
        line-height: 1.6;
        color: #94A3B8;
        max-width: 420px;
        margin-bottom: 32px;
    }

    /* Operational Live Ticker */
    .live-status-card {
        background: rgba(2, 6, 23, 0.5);
        border: 1px solid rgba(255, 255, 255, 0.06);
        border-radius: 16px;
        padding: 16px 20px;
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 16px;
    }
    .status-metric { display: flex; flex-direction: column; }
    .metric-num { font-size: 20px; font-weight: 800; color: #F1F5F9; font-family: 'JetBrains Mono', monospace; }
    .metric-name { font-size: 10px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.06em; color: #64748B; margin-top: 2px; }

    /* Department Icons Bar */
    .dept-tags-bar {
        display: flex; flex-wrap: wrap; gap: 8px; margin-top: 24px;
    }
    .dept-tag {
        display: inline-flex; align-items: center; gap: 6px;
        background: rgba(255, 255, 255, 0.04);
        border: 1px solid rgba(255, 255, 255, 0.08);
        border-radius: 8px;
        padding: 5px 10px;
        font-size: 11px; font-weight: 600; color: #94A3B8;
    }

    /* Right Form Section */
    .portal-form-container {
        padding: 48px;
        display: flex;
        flex-direction: column;
        justify-content: center;
        background: rgba(10, 16, 30, 0.6);
    }

    @media (max-width: 600px) {
        .portal-form-container { padding: 32px 24px; }
    }

    .form-header { margin-bottom: 28px; }
    .form-header-title { font-size: 24px; font-weight: 800; color: #F1F5F9; letter-spacing: -0.02em; }
    .form-header-subtitle { font-size: 13px; color: #64748B; margin-top: 4px; }

    /* Field Styles */
    .input-group-custom {
        margin-bottom: 18px;
    }
    .input-label-custom {
        display: flex; justify-content: space-between; align-items: center;
        font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.08em;
        color: #94A3B8; margin-bottom: 8px;
    }
    .input-box {
        position: relative; width: 100%;
    }
    .input-icon-left {
        position: absolute; left: 14px; top: 50%; transform: translateY(-50%);
        color: #64748B; font-size: 16px; pointer-events: none;
    }
    .input-element {
        width: 100%;
        padding: 13px 14px 13px 44px;
        background: rgba(15, 23, 42, 0.6);
        border: 1.5px solid rgba(255, 255, 255, 0.1);
        border-radius: 12px;
        font-size: 14px;
        color: #F1F5F9;
        font-family: 'Inter', sans-serif;
        outline: none;
        transition: all 0.2s ease;
    }
    .input-element:focus {
        border-color: #3B82F6;
        background: rgba(15, 23, 42, 0.9);
        box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.2);
    }
    .input-element::placeholder { color: #475569; }

    .btn-toggle-eye {
        position: absolute; right: 12px; top: 50%; transform: translateY(-50%);
        background: none; border: none; color: #64748B; cursor: pointer; padding: 4px; font-size: 16px;
    }
    .btn-toggle-eye:hover { color: #94A3B8; }

    /* Submit Button */
    .btn-submit-portal {
        width: 100%;
        padding: 14px;
        background: linear-gradient(135deg, #1E40AF 0%, #3B82F6 100%);
        border: none;
        border-radius: 12px;
        font-size: 14px;
        font-weight: 700;
        color: #FFFFFF;
        cursor: pointer;
        display: flex; align-items: center; justify-content: center; gap: 8px;
        box-shadow: 0 4px 16px rgba(59, 130, 246, 0.35);
        transition: all 0.2s ease;
        margin-top: 8px;
        margin-bottom: 20px;
    }
    .btn-submit-portal:hover:not(:disabled) {
        transform: translateY(-1px);
        box-shadow: 0 6px 24px rgba(59, 130, 246, 0.5);
    }
    .btn-submit-portal:disabled { opacity: 0.6; cursor: not-allowed; }

    /* Quick Fill Selector */
    .quick-fill-box {
        background: rgba(2, 6, 23, 0.4);
        border: 1px solid rgba(255, 255, 255, 0.05);
        border-radius: 12px;
        padding: 12px 14px;
        margin-bottom: 20px;
    }
    .quick-fill-title {
        font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.08em; color: #64748B; margin-bottom: 8px; display: flex; align-items: center; justify-content: space-between;
    }
    .quick-pill-row {
        display: flex; flex-wrap: wrap; gap: 6px;
    }
    .quick-pill {
        background: rgba(255, 255, 255, 0.05);
        border: 1px solid rgba(255, 255, 255, 0.08);
        border-radius: 6px;
        padding: 4px 8px;
        font-size: 11px;
        font-weight: 600;
        color: #CBD5E1;
        cursor: pointer;
        transition: all 0.15s;
    }
    .quick-pill:hover {
        background: rgba(59, 130, 246, 0.2);
        border-color: #3B82F6;
        color: #fff;
    }

    /* Alert Banners */
    .portal-alert {
        display: flex; align-items: center; gap: 10px;
        padding: 12px 14px;
        border-radius: 10px;
        font-size: 13px;
        margin-bottom: 18px;
        animation: fadeIn 0.3s ease;
    }
    .portal-alert-error {
        background: rgba(239, 68, 68, 0.12);
        border: 1px solid rgba(239, 68, 68, 0.3);
        color: #FCA5A5;
    }
    .portal-alert-success {
        background: rgba(34, 197, 94, 0.12);
        border: 1px solid rgba(34, 197, 94, 0.3);
        color: #86EFAC;
    }

    .footer-note {
        display: flex; justify-content: space-between; align-items: center;
        font-size: 11px; color: #475569; padding-top: 16px; border-top: 1px solid rgba(255, 255, 255, 0.05);
    }
    </style>
</head>
<body>

<div class="bg-canvas"></div>
<div class="grid-overlay"></div>

<div class="portal-wrapper">
    <!-- Left Hero Section -->
    <div class="portal-branding">
        <div>
            <div class="brand-pill">
                <span class="dot dot-success dot-pulse" style="width:7px;height:7px;"></span>
                Municipal Operations Platform
            </div>

            <h1 class="brand-hero-title">
                Smart City<br>
                <span>Command & Control</span>
            </h1>

            <p class="brand-hero-desc">
                Unified real-time coordination across 14 municipal and emergency response services. Monitor dispatches, coordinate field officers, and resolve citizen grievances with zero latency.
            </p>

            <div class="dept-tags-bar">
                <span class="dept-tag"><i class="bi bi-fire" style="color:#EF4444"></i> Fire</span>
                <span class="dept-tag"><i class="bi bi-heart-pulse-fill" style="color:#EC4899"></i> Ambulance</span>
                <span class="dept-tag"><i class="bi bi-shield-check" style="color:#3B82F6"></i> Police</span>
                <span class="dept-tag"><i class="bi bi-life-preserver" style="color:#9333EA"></i> Rescue</span>
                <span class="dept-tag"><i class="bi bi-geo-alt-fill" style="color:#0D9488"></i> Tracker</span>
                <span class="dept-tag"><i class="bi bi-droplet-fill" style="color:#0EA5E9"></i> Water</span>
                <span class="dept-tag"><i class="bi bi-trash3-fill" style="color:#16A34A"></i> Waste</span>
                <span class="dept-tag"><i class="bi bi-sign-turn-right-fill" style="color:#EA580C"></i> Roads</span>
            </div>
        </div>

        <div>
            <div class="live-status-card">
                <div class="status-metric">
                    <span class="metric-num" id="liveCountNum">14</span>
                    <span class="metric-name">Active Services</span>
                </div>
                <div class="status-metric">
                    <span class="metric-num" style="color:#22C55E;">99.8%</span>
                    <span class="metric-name">SLA Uptime</span>
                </div>
                <div class="status-metric">
                    <span class="metric-num" style="color:#60A5FA;">Live</span>
                    <span class="metric-name">GPS Stream</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Right Login Form Section -->
    <div class="portal-form-container">
        <div class="form-header">
            <div style="display:flex;align-items:center;gap:10px;margin-bottom:12px;">
                <div style="width:40px;height:40px;border-radius:12px;background:linear-gradient(135deg,#1E40AF,#3B82F6);display:flex;align-items:center;justify-content:center;font-size:20px;">
                    🏙️
                </div>
                <span style="font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:0.1em;color:#60A5FA;">City Planner</span>
            </div>
            <h2 class="form-header-title">Operator Sign In</h2>
            <p class="form-header-subtitle">Enter your municipal credentials to access your dispatch center</p>
        </div>

        <?php if ($logoutMsg): ?>
        <div class="portal-alert portal-alert-success">
            <i class="bi bi-check-circle-fill"></i>
            <span><?= htmlspecialchars($logoutMsg) ?></span>
        </div>
        <?php endif; ?>

        <!-- Dynamic Error Box -->
        <div class="portal-alert portal-alert-error" id="errorBox" style="display:none;">
            <i class="bi bi-exclamation-triangle-fill"></i>
            <span id="errorMessage">Invalid email or password</span>
        </div>

        <!-- 1-Click Role Quick Fill -->
        <div class="quick-fill-box">
            <div class="quick-fill-title">
                <span>Quick-Fill Demo Credentials</span>
                <span style="font-size:9px;color:#3B82F6;">1-Click</span>
            </div>
            <div class="quick-pill-row">
                <button type="button" class="quick-pill" onclick="quickFill('superadmin@city.gov','CityAdmin@2026')">👑 Super Admin</button>
                <button type="button" class="quick-pill" onclick="quickFill('fire@city.gov','Fire@123')">🚒 Fire</button>
                <button type="button" class="quick-pill" onclick="quickFill('ambulance@city.gov','Ambulance@123')">🚑 Ambulance</button>
                <button type="button" class="quick-pill" onclick="quickFill('police@city.gov','Police@123')">👮 Police</button>
                <button type="button" class="quick-pill" onclick="quickFill('citizen@city.gov','Citizen@123')">🏛️ Civic Services</button>
            </div>
        </div>

        <form id="portalLoginForm" onsubmit="handleLoginSubmit(event)" novalidate>
            <input type="hidden" name="<?= CSRF_TOKEN_NAME ?>" value="<?= htmlspecialchars($csrfToken) ?>">

            <!-- Email Address -->
            <div class="input-group-custom">
                <div class="input-label-custom">
                    <span>Officer / Administrator Email</span>
                </div>
                <div class="input-box">
                    <i class="bi bi-envelope-fill input-icon-left"></i>
                    <input type="email" id="loginEmail" name="email" class="input-element" placeholder="admin@city.gov" value="superadmin@city.gov" required autocomplete="username">
                </div>
            </div>

            <!-- Password -->
            <div class="input-group-custom">
                <div class="input-label-custom">
                    <span>Secure Access Key</span>
                </div>
                <div class="input-box">
                    <i class="bi bi-shield-lock-fill input-icon-left"></i>
                    <input type="password" id="loginPassword" name="password" class="input-element" placeholder="••••••••••••" value="CityAdmin@2026" required autocomplete="current-password">
                    <button type="button" class="btn-toggle-eye" onclick="togglePassVisibility()" tabindex="-1">
                        <i class="bi bi-eye-fill" id="passEyeIcon"></i>
                    </button>
                </div>
            </div>

            <!-- Submit Button -->
            <button type="submit" class="btn-submit-portal" id="submitBtn">
                <span id="btnText">Access Command Center</span>
                <i class="bi bi-arrow-right-circle-fill" id="btnArrow"></i>
                <div class="loading-spinner" id="btnSpinner" style="display:none;width:16px;height:16px;border-width:2px;"></div>
            </button>
        </form>

        <div class="footer-note">
            <span><i class="bi bi-shield-shaded text-success"></i> 256-bit Encrypted Session</span>
            <span>City Planner OS v1.0</span>
        </div>
    </div>
</div>

<script>
function togglePassVisibility() {
    const pInput = document.getElementById('loginPassword');
    const eye = document.getElementById('passEyeIcon');
    if (pInput.type === 'password') {
        pInput.type = 'text';
        eye.className = 'bi bi-eye-slash-fill';
    } else {
        pInput.type = 'password';
        eye.className = 'bi bi-eye-fill';
    }
}

function quickFill(email, password) {
    document.getElementById('loginEmail').value = email;
    document.getElementById('loginPassword').value = password;
    hideError();
}

function showError(msg) {
    const box = document.getElementById('errorBox');
    const msgEl = document.getElementById('errorMessage');
    msgEl.textContent = msg;
    box.style.display = 'flex';
}

function hideError() {
    document.getElementById('errorBox').style.display = 'none';
}

async function handleLoginSubmit(e) {
    e.preventDefault();
    hideError();

    const email = document.getElementById('loginEmail').value.trim();
    const password = document.getElementById('loginPassword').value;
    const btn = document.getElementById('submitBtn');
    const btnText = document.getElementById('btnText');
    const btnArrow = document.getElementById('btnArrow');
    const btnSpinner = document.getElementById('btnSpinner');

    if (!email || !password) {
        showError('Please enter both email and password.');
        return;
    }

    // Set loading
    btn.disabled = true;
    btnText.textContent = 'Authenticating...';
    btnArrow.style.display = 'none';
    btnSpinner.style.display = 'block';

    try {
        const authUrl = (window.BASE_URL || '<?= BASE_URL ?>') + 'api/auth.php?action=login';
        const res = await fetch(authUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({
                email: email,
                password: password,
                '<?= CSRF_TOKEN_NAME ?>': '<?= htmlspecialchars($csrfToken) ?>'
            })
        });

        let data;
        try {
            data = await res.json();
        } catch (jsonErr) {
            console.error('Server response parse error:', jsonErr);
            throw new Error(`Server returned status HTTP ${res.status}. Please check server logs or network.`);
        }

        if (data && data.success) {
            btnText.textContent = 'Access Granted!';
            window.location.href = data.redirect || ((window.BASE_URL || '<?= BASE_URL ?>') + 'dashboard.php');
        } else {
            showError((data && data.message) ? data.message : 'Invalid credentials.');
            btn.disabled = false;
            btnText.textContent = 'Access Command Center';
            btnArrow.style.display = 'block';
            btnSpinner.style.display = 'none';
        }
    } catch (err) {
        console.error('Login error:', err);
        showError(err.message || 'Unable to connect to authentication gateway. Please check your connection.');
        btn.disabled = false;
        btnText.textContent = 'Access Command Center';
        btnArrow.style.display = 'block';
        btnSpinner.style.display = 'none';
    }
}
</script>
</body>
</html>
