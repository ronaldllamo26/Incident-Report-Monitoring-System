<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

if (isLoggedIn()) { header('Location: /irms/citizen/dashboard.php'); exit; }

$token = trim($_GET['token'] ?? '');
$error = '';
$success = false;
$validToken = false;
$userId = null;

// ── Validate token on page load ───────────────────────────────
if ($token) {
    if (strlen($token) !== 64 || !ctype_xdigit($token)) {
        $error = 'Invalid reset link.';
    } else {
        $stmt = $pdo->prepare("
            SELECT id, name, reset_token_expires
            FROM users
            WHERE reset_token = ? AND is_active = 1
            LIMIT 1
        ");
        $stmt->execute([$token]);
        $user = $stmt->fetch();

        if (!$user) {
            $error = 'Invalid o hindi na valid ang reset link. Humingi ng bagong reset link.';
        } elseif (strtotime($user['reset_token_expires']) < time()) {
            $error = 'Expired na ang reset link (valid 1 oras lang). Humingi ng bagong reset link.';
        } else {
            $validToken = true;
            $userId = $user['id'];
        }
    }
} else {
    header('Location: /irms/citizen/forgot_password.php');
    exit;
}

// ── Handle new password submission ────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $validToken) {
    validate_csrf();

    $password = $_POST['password'] ?? '';
    $confirm  = $_POST['confirm_password'] ?? '';

    if (strlen($password) < 8) {
        $error = 'Dapat 8 characters minimum ang password.';
    } elseif ($password !== $confirm) {
        $error = 'Hindi magkapareho ang password.';
    } else {
        // Update password, clear reset token
        $pdo->prepare("
            UPDATE users
            SET password            = ?,
                reset_token         = NULL,
                reset_token_expires = NULL
            WHERE id = ?
        ")->execute([password_hash($password, PASSWORD_BCRYPT), $userId]);

        $success = true;
    }
}
?>
<!DOCTYPE html>
<html lang="fil">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Bagong Password — QC-ALERTO</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        /* Hide browser native password reveal button */
        input[type="password"]::-ms-reveal,
        input[type="password"]::-ms-clear { display: none; }
        input[type="password"]::-webkit-credentials-auto-fill-button { visibility: hidden; }

        * { box-sizing: border-box; }
        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #001a4d 0%, #1e293b 50%, #111827 100%);
            min-height: 100vh; display: flex; align-items: center;
            justify-content: center; padding: 20px;
        }
        .card-wrap {
            background: #fff; border-radius: 20px;
            box-shadow: 0 24px 64px rgba(0,0,0,0.25);
            padding: 44px 40px; width: 100%; max-width: 420px;
        }
        .brand-logo { width: 64px; height: 64px; object-fit: contain; }
        .brand-title { color: #1e293b; font-size: 22px; font-weight: 800; letter-spacing: -0.5px; }
        .brand-sub   { color: #6b7280; font-size: 12px; }
        .form-label-custom { font-size: 13px; font-weight: 600; color: #374151; margin-bottom: 6px; display: block; }
        .input-wrap { position: relative; }
        .input-wrap .icon { position: absolute; left: 14px; top: 50%; transform: translateY(-50%); color: #9ca3af; font-size: 16px; }
        .input-custom {
            width: 100%; padding: 12px 42px 12px 40px;
            border: 1.5px solid #e5e7eb; border-radius: 10px;
            font-size: 14px; color: #111827;
            transition: border-color .2s, box-shadow .2s; outline: none;
        }
        .input-custom:focus { border-color: #1e293b; box-shadow: 0 0 0 3px rgba(0,45,122,0.12); }
        .toggle-btn {
            position: absolute; right: 12px; top: 50%; transform: translateY(-50%);
            background: none; border: none; color: #9ca3af; cursor: pointer; padding: 4px;
        }
        .toggle-btn:hover { color: #1e293b; }
        .btn-primary-custom {
            background: linear-gradient(135deg, #1e293b, #111827);
            color: #fff; border: none; border-radius: 10px;
            padding: 13px; width: 100%; font-size: 15px; font-weight: 700;
            cursor: pointer; transition: opacity .2s, transform .1s;
            display: flex; align-items: center; justify-content: center; gap: 8px;
        }
        .btn-primary-custom:hover { opacity: .92; transform: translateY(-1px); }
        .alert-err {
            background: #fef2f2; border: 1px solid #fecaca; border-left: 4px solid #dc2626;
            border-radius: 10px; padding: 12px 14px; font-size: 13px; color: #991b1b;
            display: flex; align-items: flex-start; gap: 10px; margin-bottom: 18px;
        }
        .alert-ok {
            background: #f0fdf4; border: 1px solid #bbf7d0; border-left: 4px solid #16a34a;
            border-radius: 10px; padding: 16px 18px; font-size: 13px; color: #166534;
            margin-bottom: 18px;
        }
        /* Password strength bar */
        .strength-bar-wrap { height: 4px; background: #e5e7eb; border-radius: 4px; margin-top: 8px; overflow: hidden; }
        .strength-bar { height: 100%; border-radius: 4px; width: 0; transition: width .3s, background .3s; }
        .strength-label { font-size: 11px; color: #6b7280; margin-top: 4px; }
        .back-link { font-size: 13px; color: #6b7280; text-decoration: none; display: inline-flex; align-items: center; gap: 6px; }
        .back-link:hover { color: #1e293b; }
    </style>
</head>
<body>
<div class="card-wrap">

    <div class="text-center mb-4">
        <img src="/irms/assets/img/QC_LOGO_CIRCLE.png" class="brand-logo mb-2" alt="QC-ALERTO">
        <div class="brand-title">QC-ALERTO</div>
        <div class="brand-sub">Incident Report & Monitoring System</div>
    </div>

    <?php if ($success): ?>
        <!-- ── SUCCESS STATE ─────────────────────────── -->
        <div class="text-center mb-4">
            <div style="width:64px;height:64px;background:#f0fdf4;border-radius:50%;
                        display:flex;align-items:center;justify-content:center;margin:0 auto 12px;">
                <i class="bi bi-check-lg" style="font-size:28px;color:#16a34a;"></i>
            </div>
            <h5 style="font-weight:800;color:#111827;margin-bottom:6px;">Na-reset na ang Password! 🎉</h5>
            <p style="color:#6b7280;font-size:13px;margin:0;">
                Matagumpay na nabago ang iyong password. Pwede ka nang mag-login.
            </p>
        </div>
        <a href="/irms/citizen/login.php" class="btn-primary-custom" style="text-decoration:none;">
            <i class="bi bi-box-arrow-in-right"></i> Mag-login Ngayon
        </a>

    <?php elseif (!$validToken): ?>
        <!-- ── INVALID / EXPIRED TOKEN ───────────────── -->
        <div class="alert-err">
            <i class="bi bi-x-circle-fill" style="font-size:16px;flex-shrink:0;"></i>
            <div>
                <?= htmlspecialchars($error) ?>
                <div style="margin-top:8px;">
                    <a href="/irms/citizen/forgot_password.php"
                       style="color:#991b1b;font-weight:600;font-size:12px;">
                        <i class="bi bi-arrow-repeat me-1"></i>Humingi ng bagong reset link
                    </a>
                </div>
            </div>
        </div>
        <div class="text-center">
            <a href="/irms/citizen/login.php" class="back-link">
                <i class="bi bi-arrow-left"></i> Bumalik sa Login
            </a>
        </div>

    <?php else: ?>
        <!-- ── NEW PASSWORD FORM ─────────────────────── -->
        <p style="font-size:16px;font-weight:700;color:#111827;margin-bottom:4px;">
            <i class="bi bi-shield-lock me-2" style="color:#1e293b;"></i>Bagong Password
        </p>
        <p style="color:#6b7280;font-size:13px;margin-bottom:24px;">
            Pumili ng matibay na password — minimum 8 characters.
        </p>

        <?php if ($error): ?>
            <div class="alert-err">
                <i class="bi bi-x-circle-fill" style="font-size:16px;flex-shrink:0;"></i>
                <span><?= htmlspecialchars($error) ?></span>
            </div>
        <?php endif; ?>

        <form method="POST" novalidate>
            <?= csrf_field() ?>
            <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">

            <!-- New password -->
            <div class="mb-3">
                <label class="form-label-custom">Bagong Password</label>
                <div class="input-wrap">
                    <i class="bi bi-lock icon"></i>
                    <input type="password" name="password" id="password"
                           class="input-custom" placeholder="••••••••"
                           required minlength="8" oninput="checkStrength(this.value)">
                    <button type="button" class="toggle-btn" onclick="togglePass('password','eye1')">
                        <i class="bi bi-eye" id="eye1"></i>
                    </button>
                </div>
                <!-- Password strength indicator -->
                <div class="strength-bar-wrap">
                    <div class="strength-bar" id="strength-bar"></div>
                </div>
                <div class="strength-label" id="strength-label">Minimum 8 characters</div>
            </div>

            <!-- Confirm password -->
            <div class="mb-4">
                <label class="form-label-custom">Kumpirmahin ang Password</label>
                <div class="input-wrap">
                    <i class="bi bi-lock-fill icon"></i>
                    <input type="password" name="confirm_password" id="confirm_password"
                           class="input-custom" placeholder="••••••••" required>
                    <button type="button" class="toggle-btn" onclick="togglePass('confirm_password','eye2')">
                        <i class="bi bi-eye" id="eye2"></i>
                    </button>
                </div>
            </div>

            <button type="submit" class="btn-primary-custom mb-4">
                <i class="bi bi-check-lg"></i> I-save ang Bagong Password
            </button>
        </form>
    <?php endif; ?>

</div>

<script>
function togglePass(fieldId, iconId) {
    var f = document.getElementById(fieldId);
    var i = document.getElementById(iconId);
    if (f.type === 'password') {
        f.type = 'text';
        i.className = 'bi bi-eye-slash';
    } else {
        f.type = 'password';
        i.className = 'bi bi-eye';
    }
}

function checkStrength(val) {
    var bar   = document.getElementById('strength-bar');
    var label = document.getElementById('strength-label');
    var score = 0;
    if (val.length >= 8)  score++;
    if (val.length >= 12) score++;
    if (/[A-Z]/.test(val)) score++;
    if (/[0-9]/.test(val)) score++;
    if (/[^A-Za-z0-9]/.test(val)) score++;

    var configs = [
        { w: '0%',   c: '#e5e7eb', t: 'Minimum 8 characters' },
        { w: '25%',  c: '#dc2626', t: 'Mahina' },
        { w: '50%',  c: '#f59e0b', t: 'Katamtaman' },
        { w: '75%',  c: '#3b82f6', t: 'Malakas' },
        { w: '90%',  c: '#16a34a', t: 'Napakalakas' },
        { w: '100%', c: '#16a34a', t: '✅ Perpekto!' },
    ];
    var cfg = configs[Math.min(score, configs.length - 1)];
    bar.style.width      = val.length === 0 ? '0%' : cfg.w;
    bar.style.background = cfg.c;
    label.textContent    = val.length === 0 ? 'Minimum 8 characters' : cfg.t;
    label.style.color    = val.length === 0 ? '#6b7280' : cfg.c;
}
</script>
</body>
</html>
