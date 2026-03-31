<?php
require_once __DIR__ . '/../includes/auth.php';

// Pag naka-login na at staff, i-redirect
if (isLoggedIn() && in_array($_SESSION['role'], ['admin','responder'])) {
    redirectByRole();
}
// Pag naka-login na pero citizen, i-redirect sa citizen portal
if (isLoggedIn() && $_SESSION['role'] === 'citizen') {
    header('Location: /irms/index.php');
    exit;
}

$error   = $_GET['error']   ?? '';
$success = $_GET['success'] ?? '';
?>
<!DOCTYPE html>
<html lang="fil">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Staff Portal — IRMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        * { box-sizing: border-box; }
        :root {
            --navy:  #0f172a;
            --navy2: #1e293b;
            --navy3: #334155;
            --text:  #f8fafc;
            --muted: #94a3b8;
            --border: rgba(255,255,255,0.08);
            --accent: #6366f1;
            --accent2: #4f46e5;
        }
        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background: var(--navy);
            color: var(--text);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0;
            padding: 20px;
            position: relative;
            overflow: hidden;
        }
        body::before {
            content: '';
            position: fixed;
            top: -200px; right: -200px;
            width: 600px; height: 600px;
            background: radial-gradient(circle, rgba(99,102,241,0.06) 0%, transparent 70%);
            pointer-events: none;
        }
        body::after {
            content: '';
            position: fixed;
            bottom: -100px; left: -100px;
            width: 400px; height: 400px;
            background: radial-gradient(circle, rgba(99,102,241,0.04) 0%, transparent 70%);
            pointer-events: none;
        }

        .login-wrap {
            width: 100%;
            max-width: 420px;
            position: relative;
            z-index: 1;
        }

        /* Brand */
        .brand {
            text-align: center;
            margin-bottom: 32px;
        }
        .brand-icon {
            width: 64px; height: 64px;
            background: rgba(99,102,241,0.12);
            border: 1px solid rgba(99,102,241,0.2);
            border-radius: 16px;
            display: flex; align-items: center; justify-content: center;
            font-size: 28px;
            margin: 0 auto 16px;
            color: #a5b4fc;
        }
        .brand-title {
            font-size: 24px;
            font-weight: 800;
            letter-spacing: -0.5px;
            margin-bottom: 4px;
        }
        .brand-sub {
            font-size: 14px;
            color: var(--muted);
        }

        /* Staff badge */
        .staff-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: rgba(99,102,241,0.1);
            border: 1px solid rgba(99,102,241,0.2);
            color: #a5b4fc;
            font-size: 11px;
            font-weight: 700;
            padding: 4px 12px;
            border-radius: 20px;
            letter-spacing: 1px;
            text-transform: uppercase;
            margin-bottom: 8px;
        }

        /* Card */
        .login-card {
            background: rgba(255,255,255,0.04);
            border: 1px solid var(--border);
            border-radius: 20px;
            padding: 32px;
            backdrop-filter: blur(8px);
        }

        /* Form */
        .form-label-custom {
            font-size: 13px;
            font-weight: 600;
            color: var(--muted);
            margin-bottom: 6px;
            display: block;
        }
        .form-control-custom {
            background: rgba(255,255,255,0.05);
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 10px;
            color: var(--text);
            font-size: 14px;
            padding: 12px 16px;
            width: 100%;
            font-family: 'Plus Jakarta Sans', sans-serif;
            transition: all 0.2s;
        }
        .form-control-custom:focus {
            outline: none;
            border-color: rgba(99,102,241,0.5);
            background: rgba(255,255,255,0.07);
            box-shadow: 0 0 0 3px rgba(99,102,241,0.1);
        }
        .form-control-custom::placeholder { color: #475569; }
        .input-group-custom {
            position: relative;
        }
        .input-icon {
            position: absolute;
            left: 14px; top: 50%;
            transform: translateY(-50%);
            color: #475569;
            font-size: 16px;
            pointer-events: none;
        }
        .form-control-custom.has-icon { padding-left: 42px; }
        .toggle-pass {
            position: absolute;
            right: 14px; top: 50%;
            transform: translateY(-50%);
            color: #475569;
            cursor: pointer;
            background: none;
            border: none;
            padding: 0;
            font-size: 16px;
            transition: color 0.2s;
        }
        .toggle-pass:hover { color: var(--muted); }

        /* Button */
        .btn-login {
            background: var(--accent);
            color: #fff;
            border: none;
            padding: 13px 20px;
            border-radius: 10px;
            font-size: 15px;
            font-weight: 700;
            width: 100%;
            font-family: 'Plus Jakarta Sans', sans-serif;
            cursor: pointer;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            box-shadow: 0 4px 16px rgba(99,102,241,0.25);
        }
        .btn-login:hover {
            background: var(--accent2);
            transform: translateY(-1px);
            box-shadow: 0 6px 20px rgba(99,102,241,0.35);
        }
        .btn-login:active { transform: translateY(0); }

        /* Alert */
        .alert-custom {
            background: rgba(239,68,68,0.1);
            border: 1px solid rgba(239,68,68,0.2);
            border-radius: 10px;
            padding: 12px 16px;
            font-size: 13px;
            color: #fca5a5;
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 20px;
        }
        .alert-unauthorized {
            background: rgba(245,158,11,0.1);
            border: 1px solid rgba(245,158,11,0.2);
            color: #fcd34d;
        }

        /* Role info */
        .role-info {
            background: rgba(255,255,255,0.02);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 14px 16px;
            margin-top: 20px;
        }
        .role-info-title {
            font-size: 11px;
            font-weight: 700;
            color: var(--muted);
            letter-spacing: 1px;
            text-transform: uppercase;
            margin-bottom: 10px;
        }
        .role-badge-item {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 13px;
            color: var(--muted);
            padding: 4px 0;
        }
        .role-dot {
            width: 8px; height: 8px;
            border-radius: 50%;
            flex-shrink: 0;
        }

        /* Back link */
        .back-link {
            text-align: center;
            margin-top: 20px;
        }
        .back-link a {
            font-size: 13px;
            color: var(--muted);
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            transition: color 0.2s;
        }
        .back-link a:hover { color: var(--text); }

        /* Divider */
        .form-divider {
            display: flex;
            align-items: center;
            gap: 12px;
            margin: 20px 0;
        }
        .form-divider::before,
        .form-divider::after {
            content: '';
            flex: 1;
            height: 1px;
            background: var(--border);
        }
        .form-divider span {
            font-size: 11px;
            color: var(--muted);
            white-space: nowrap;
        }
    </style>
</head>
<body>

<div class="login-wrap">

    <!-- Brand -->
    <div class="brand">
        <div class="brand-icon">
            <i class="bi bi-shield-lock"></i>
        </div>
        <div class="staff-badge">
            <i class="bi bi-lock-fill" style="font-size:9px;"></i>
            Staff Portal
        </div>
        <div class="brand-title">IRMS Staff Portal</div>
        <div class="brand-sub">Para lamang sa mga admin at responder</div>
    </div>

    <!-- Login card -->
    <div class="login-card">

        <?php if ($error === 'unauthorized'): ?>
            <div class="alert-custom alert-unauthorized">
                <i class="bi bi-exclamation-triangle-fill"></i>
                Walang access. Staff credentials lang ang tinatanggap dito.
            </div>
        <?php elseif ($error): ?>
            <div class="alert-custom">
                <i class="bi bi-x-circle-fill"></i>
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <form action="/irms/controllers/AuthController.php?action=login&portal=staff"
              method="POST" id="login-form">

            <div class="mb-4">
                <label class="form-label-custom">Email Address</label>
                <div class="input-group-custom">
                    <i class="bi bi-envelope input-icon"></i>
                    <input type="email" name="email" class="form-control-custom has-icon"
                           placeholder="staff@irms.local" required autofocus>
                </div>
            </div>

            <div class="mb-5">
                <label class="form-label-custom">Password</label>
                <div class="input-group-custom">
                    <i class="bi bi-lock input-icon"></i>
                    <input type="password" name="password" id="password"
                           class="form-control-custom has-icon"
                           placeholder="••••••••" required>
                    <button type="button" class="toggle-pass" onclick="togglePassword()">
                        <i class="bi bi-eye" id="eye-icon"></i>
                    </button>
                </div>
            </div>

            <!-- Hidden field para malaman ng controller na staff portal ito -->
            <input type="hidden" name="portal" value="staff">

            <button type="submit" class="btn-login">
                <i class="bi bi-box-arrow-in-right"></i>
                Mag-login sa Staff Portal
            </button>
        </form>

        <!-- Role info -->
        <div class="role-info">
            <div class="role-info-title">Mga may access dito</div>
            <div class="role-badge-item">
                <div class="role-dot" style="background:#ef4444;"></div>
                <span><strong style="color:var(--text);">Admin</strong> — Full system access, manage incidents, users, reports</span>
            </div>
            <div class="role-badge-item">
                <div class="role-dot" style="background:#10b981;"></div>
                <span><strong style="color:var(--text);">Responder</strong> — View at i-update ang assigned incidents</span>
            </div>
        </div>

    </div>

    <!-- Back link -->
    <div class="back-link">
        <a href="/irms/index.php">
            <i class="bi bi-arrow-left"></i> Bumalik sa Public Portal
        </a>
    </div>
    <div class="back-link" style="margin-top:8px;">
        <a href="/irms/citizen/login.php" style="color:#6366f1;">
            <i class="bi bi-person-circle"></i> Citizen? Mag-login dito
        </a>
    </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
function togglePassword() {
    var input = document.getElementById('password');
    var icon  = document.getElementById('eye-icon');
    if (input.type === 'password') {
        input.type = 'text';
        icon.className = 'bi bi-eye-slash';
    } else {
        input.type = 'password';
        icon.className = 'bi bi-eye';
    }
}
</script>
</body>
</html>