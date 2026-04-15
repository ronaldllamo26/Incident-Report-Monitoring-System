<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/db.php';
requireRole('citizen');

$user    = currentUser();
$success = '';
$error   = '';

// ── Handle form submissions ──────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validate_csrf();
    $action = $_POST['action'] ?? '';

    // ── UPDATE PROFILE ──────────────────────────────────────
    if ($action === 'update_profile') {
        $name    = trim($_POST['name']    ?? '');
        $phone   = trim($_POST['phone']   ?? '');
        $address = trim($_POST['address'] ?? '');

        if (mb_strlen($name) < 2 || mb_strlen($name) > 100) {
            $error = 'Ang pangalan ay dapat 2–100 characters.';
        } else {
            $pdo->prepare("
                UPDATE users SET name = ?, phone = ?, address = ? WHERE id = ?
            ")->execute([$name, $phone, $address, $user['id']]);

            // Refresh session data
            $_SESSION['user_name'] = $name;
            $success = 'profile';
        }
    }

    // ── CHANGE PASSWORD ──────────────────────────────────────
    elseif ($action === 'change_password') {
        $current = $_POST['current_password'] ?? '';
        $new     = $_POST['new_password']     ?? '';
        $confirm = $_POST['confirm_password'] ?? '';

        if (!password_verify($current, $user['password'])) {
            $error = 'Mali ang kasalukuyang password.';
        } elseif (strlen($new) < 8) {
            $error = 'Ang bagong password ay dapat minimum 8 characters.';
        } elseif ($new !== $confirm) {
            $error = 'Hindi magkapareho ang bagong password.';
        } elseif (password_verify($new, $user['password'])) {
            $error = 'Ang bagong password ay dapat iba sa kasalukuyan.';
        } else {
            $pdo->prepare("
                UPDATE users SET password = ? WHERE id = ?
            ")->execute([password_hash($new, PASSWORD_BCRYPT), $user['id']]);
            $success = 'password';
        }
    }

    // Re-fetch updated user data
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$user['id']]);
    $user = $stmt->fetch();
}
?>
<!DOCTYPE html>
<html lang="fil">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Settings — QC-ALERTO</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        /* Hide browser native password reveal button */
        input[type="password"]::-ms-reveal,
        input[type="password"]::-ms-clear { display: none; }
        input[type="password"]::-webkit-credentials-auto-fill-button { visibility: hidden; }

        * { box-sizing: border-box; }
        body { font-family: 'Plus Jakarta Sans', sans-serif; background: #f1f5f9; margin: 0; }
        .topbar { background: #1e293b; padding: 0; position: sticky; top: 0; z-index: 100;
                  border-bottom: 3px solid #CE1126; }
        .topbar-inner { max-width: 900px; margin: 0 auto; padding: 0 20px;
                        display: flex; align-items: center; justify-content: space-between; height: 58px; }
        .brand { font-size: 17px; font-weight: 800; color: #fff; text-decoration: none;
                 display: flex; align-items: center; gap: 8px; }
        .brand span { color: #ef4444; }
        .back-btn { color: #94a3b8; text-decoration: none; font-size: 13px; font-weight: 600;
                    display: flex; align-items: center; gap: 6px; transition: color 0.2s; }
        .back-btn:hover { color: #fff; }

        .main-wrap { max-width: 900px; margin: 0 auto; padding: 28px 20px; }

        /* Page header */
        .page-header { margin-bottom: 24px; }
        .page-header h4 { font-size: 20px; font-weight: 800; color: #0f172a; margin: 0 0 4px; }
        .page-header p { color: #64748b; font-size: 13px; margin: 0; }

        /* Profile avatar at top */
        .profile-hero {
            background: linear-gradient(135deg, #0f172a, #1e3a5f);
            border-radius: 16px; padding: 28px; margin-bottom: 24px;
            display: flex; align-items: center; gap: 20px; flex-wrap: wrap;
        }
        .big-avatar { width: 72px; height: 72px; background: #ef4444; border-radius: 50%;
                      display: flex; align-items: center; justify-content: center;
                      font-size: 28px; font-weight: 800; color: #fff; flex-shrink: 0;
                      box-shadow: 0 4px 16px rgba(239,68,68,0.4); }
        .avatar-info .name { font-size: 18px; font-weight: 800; color: #fff; margin: 0 0 2px; }
        .avatar-info .email { font-size: 13px; color: #94a3b8; }
        .avatar-info .joined { font-size: 11px; color: #475569; margin-top: 6px; }

        /* Section cards */
        .section-card { background: #fff; border-radius: 14px; border: 1px solid #e2e8f0;
                        overflow: hidden; margin-bottom: 20px; }
        .section-header { padding: 18px 24px; border-bottom: 1px solid #f1f5f9;
                          display: flex; align-items: center; gap: 10px; }
        .section-icon { width: 36px; height: 36px; border-radius: 10px;
                        display: flex; align-items: center; justify-content: center; font-size: 16px; }
        .section-title { font-size: 14px; font-weight: 700; color: #0f172a; margin: 0; }
        .section-body { padding: 24px; }

        /* Form */
        .form-label-c { font-size: 13px; font-weight: 600; color: #374151; margin-bottom: 6px; display: block; }
        .form-control-c {
            width: 100%; padding: 10px 14px; border: 1.5px solid #e5e7eb;
            border-radius: 10px; font-size: 14px; color: #111827; outline: none;
            transition: border-color .2s, box-shadow .2s; font-family: inherit;
        }
        .form-control-c:focus { border-color: #1e293b; box-shadow: 0 0 0 3px rgba(30,41,59,0.12); }
        .form-control-c:disabled { background: #f9fafb; color: #6b7280; }
        .input-wrap { position: relative; }
        .input-icon-l { position: absolute; left: 12px; top: 50%; transform: translateY(-50%);
                        color: #9ca3af; font-size: 15px; }
        .has-icon-l { padding-left: 38px; }
        .pass-toggle { position: absolute; right: 12px; top: 50%; transform: translateY(-50%);
                       background: none; border: none; color: #9ca3af; cursor: pointer; padding: 4px; }
        .pass-toggle:hover { color: #3b82f6; }

        /* Strength bar */
        .strength-wrap { height: 4px; background: #e5e7eb; border-radius: 4px; margin-top: 8px; overflow: hidden; }
        .strength-fill { height: 100%; border-radius: 4px; width: 0; transition: width .3s, background .3s; }
        .strength-text { font-size: 11px; color: #6b7280; margin-top: 4px; }

        /* Buttons */
        .btn-save { background: #1e293b; color: #fff; border: none; padding: 10px 24px;
                    border-radius: 10px; font-size: 13px; font-weight: 700; cursor: pointer;
                    transition: background .2s; display: inline-flex; align-items: center; gap: 6px; }
        .btn-save:hover { background: #111827; }
        .btn-save-danger { background: #ef4444; }
        .btn-save-danger:hover { background: #dc2626; }

        /* Alert */
        .alert-ok { background: #f0fdf4; border: 1px solid #bbf7d0; border-left: 4px solid #16a34a;
                    border-radius: 10px; padding: 12px 16px; font-size: 13px; color: #166534;
                    display: flex; align-items: center; gap: 10px; margin-bottom: 20px; }
        .alert-err { background: #fef2f2; border: 1px solid #fecaca; border-left: 4px solid #dc2626;
                     border-radius: 10px; padding: 12px 16px; font-size: 13px; color: #991b1b;
                     display: flex; align-items: center; gap: 10px; margin-bottom: 20px; }
    </style>
</head>
<body>

<!-- Topbar -->
<nav class="topbar">
    <div class="topbar-inner">
        <a href="/irms/citizen/dashboard.php" class="brand">
            <img src="/irms/assets/img/QC_LOGO_CIRCLE.png"
                 style="height:28px;width:28px;object-fit:contain;" alt="">
            QC-<span>ALERTO</span>
        </a>
        <a href="/irms/citizen/dashboard.php" class="back-btn">
            <i class="bi bi-arrow-left"></i> Bumalik sa Dashboard
        </a>
    </div>
</nav>

<div class="main-wrap">
    <div class="page-header">
        <h4><i class="bi bi-person-gear me-2" style="color:#3b82f6;"></i>Settings ng Account</h4>
        <p>I-update ang iyong profile at baguhin ang password.</p>
    </div>

    <!-- Alerts -->
    <?php if ($success === 'profile'): ?>
        <div class="alert-ok">
            <i class="bi bi-check-circle-fill" style="font-size:18px;color:#16a34a;flex-shrink:0;"></i>
            <div><strong>Na-update na ang profile!</strong> Nai-save na ang iyong mga pagbabago.</div>
        </div>
    <?php elseif ($success === 'password'): ?>
        <div class="alert-ok">
            <i class="bi bi-check-circle-fill" style="font-size:18px;color:#16a34a;flex-shrink:0;"></i>
            <div><strong>Na-update na ang password!</strong> Gamitin ang bagong password sa susunod na login.</div>
        </div>
    <?php elseif ($error): ?>
        <div class="alert-err">
            <i class="bi bi-x-circle-fill" style="font-size:18px;flex-shrink:0;"></i>
            <div><?= htmlspecialchars($error) ?></div>
        </div>
    <?php endif; ?>

    <!-- Profile Hero -->
    <div class="profile-hero">
        <div class="big-avatar"><?= strtoupper(substr($user['name'], 0, 1)) ?></div>
        <div class="avatar-info">
            <div class="name"><?= htmlspecialchars($user['name']) ?></div>
            <div class="email"><?= htmlspecialchars($user['email']) ?></div>
            <div class="joined">
                <i class="bi bi-calendar3 me-1"></i>
                Member simula <?= date('F Y', strtotime($user['created_at'] ?? 'now')) ?>
            </div>
        </div>
    </div>

    <!-- ── Section 1: Update Profile ──────────────────────── -->
    <div class="section-card">
        <div class="section-header">
            <div class="section-icon" style="background:#eef2ff;">
                <i class="bi bi-person" style="color:#1e293b;"></i>
            </div>
            <div>
                <p class="section-title">Personal na Impormasyon</p>
                <p style="font-size:12px;color:#94a3b8;margin:0;">I-update ang iyong pangalan, numero, at address</p>
            </div>
        </div>
        <div class="section-body">
            <form method="POST" autocomplete="off">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="update_profile">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label-c">Buong Pangalan</label>
                        <div class="input-wrap">
                            <i class="bi bi-person input-icon-l"></i>
                            <input type="text" name="name" class="form-control-c has-icon-l"
                                   value="<?= htmlspecialchars($user['name']) ?>" required maxlength="100">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label-c">Email Address</label>
                        <div class="input-wrap">
                            <i class="bi bi-envelope input-icon-l"></i>
                            <input type="email" class="form-control-c has-icon-l"
                                   value="<?= htmlspecialchars($user['email']) ?>" disabled>
                        </div>
                        <p style="font-size:11px;color:#94a3b8;margin:4px 0 0;">Hindi mababago ang email address.</p>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label-c">Phone Number</label>
                        <div class="input-wrap">
                            <i class="bi bi-phone input-icon-l"></i>
                            <input type="text" name="phone" class="form-control-c has-icon-l"
                                   value="<?= htmlspecialchars($user['phone'] ?? '') ?>"
                                   placeholder="09xxxxxxxxx" maxlength="20">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label-c">Address</label>
                        <div class="input-wrap">
                            <i class="bi bi-geo-alt input-icon-l"></i>
                            <input type="text" name="address" class="form-control-c has-icon-l"
                                   value="<?= htmlspecialchars($user['address'] ?? '') ?>"
                                   placeholder="Barangay, Quezon City" maxlength="255">
                        </div>
                    </div>
                    <div class="col-12">
                        <button type="submit" class="btn-save">
                            <i class="bi bi-check-lg"></i> I-save ang Pagbabago
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- ── Section 2: Change Password ────────────────────── -->
    <div class="section-card" id="password">
        <div class="section-header">
            <div class="section-icon" style="background:#fef2f2;">
                <i class="bi bi-key" style="color:#ef4444;"></i>
            </div>
            <div>
                <p class="section-title">Baguhin ang Password</p>
                <p style="font-size:12px;color:#94a3b8;margin:0;">Siguraduhing matibay ang bagong password mo</p>
            </div>
        </div>
        <div class="section-body">
            <form method="POST" autocomplete="off">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="change_password">
                <div class="row g-3">
                    <div class="col-12">
                        <label class="form-label-c">Kasalukuyang Password</label>
                        <div class="input-wrap">
                            <i class="bi bi-lock input-icon-l"></i>
                            <input type="password" name="current_password" id="cur-pass"
                                   class="form-control-c has-icon-l" placeholder="••••••••" required
                                   style="padding-right:40px;">
                            <button type="button" class="pass-toggle"
                                    onclick="togglePass('cur-pass','eye-cur')">
                                <i class="bi bi-eye" id="eye-cur"></i>
                            </button>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label-c">Bagong Password</label>
                        <div class="input-wrap">
                            <i class="bi bi-lock-fill input-icon-l"></i>
                            <input type="password" name="new_password" id="new-pass"
                                   class="form-control-c has-icon-l" placeholder="••••••••"
                                   required minlength="8" oninput="checkStr(this.value)"
                                   style="padding-right:40px;">
                            <button type="button" class="pass-toggle"
                                    onclick="togglePass('new-pass','eye-new')">
                                <i class="bi bi-eye" id="eye-new"></i>
                            </button>
                        </div>
                        <div class="strength-wrap">
                            <div class="strength-fill" id="str-bar"></div>
                        </div>
                        <div class="strength-text" id="str-label">Minimum 8 characters</div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label-c">Kumpirmahin ang Bagong Password</label>
                        <div class="input-wrap">
                            <i class="bi bi-lock-fill input-icon-l"></i>
                            <input type="password" name="confirm_password" id="conf-pass"
                                   class="form-control-c has-icon-l" placeholder="••••••••"
                                   required style="padding-right:40px;">
                            <button type="button" class="pass-toggle"
                                    onclick="togglePass('conf-pass','eye-conf')">
                                <i class="bi bi-eye" id="eye-conf"></i>
                            </button>
                        </div>
                    </div>
                    <div class="col-12">
                        <button type="submit" class="btn-save btn-save-danger">
                            <i class="bi bi-shield-lock"></i> Baguhin ang Password
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
function togglePass(fieldId, iconId) {
    var f = document.getElementById(fieldId);
    var i = document.getElementById(iconId);
    f.type = f.type === 'password' ? 'text' : 'password';
    i.className = f.type === 'password' ? 'bi bi-eye' : 'bi bi-eye-slash';
}

function checkStr(val) {
    var bar = document.getElementById('str-bar');
    var lbl = document.getElementById('str-label');
    var score = 0;
    if (val.length >= 8)  score++;
    if (val.length >= 12) score++;
    if (/[A-Z]/.test(val)) score++;
    if (/[0-9]/.test(val)) score++;
    if (/[^A-Za-z0-9]/.test(val)) score++;
    var cfg = [
        { w:'0%',   c:'#e5e7eb', t:'Minimum 8 characters' },
        { w:'25%',  c:'#dc2626', t:'Mahina' },
        { w:'50%',  c:'#f59e0b', t:'Katamtaman' },
        { w:'75%',  c:'#3b82f6', t:'Malakas' },
        { w:'90%',  c:'#16a34a', t:'Napakalakas' },
        { w:'100%', c:'#16a34a', t:'✅ Perpekto!' },
    ][Math.min(score, 5)];
    bar.style.width = val.length === 0 ? '0%' : cfg.w;
    bar.style.background = cfg.c;
    lbl.textContent = val.length === 0 ? 'Minimum 8 characters' : cfg.t;
    lbl.style.color = val.length === 0 ? '#6b7280' : cfg.c;
}

// Auto-scroll to #password section if in URL hash
if (window.location.hash === '#password') {
    document.getElementById('password').scrollIntoView({ behavior: 'smooth', block: 'start' });
}
</script>
</body>
</html>
