<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../config/mailer.php';

if (isLoggedIn()) { header('Location: /irms/citizen/dashboard.php'); exit; }

$error   = '';
$success = false;

// Handle POST submission here (before HTML so headers can be sent if needed)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validate_csrf();

    $email = trim($_POST['email'] ?? '');

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Hindi valid ang email address.';
    } else {
        // Always show success (prevents account enumeration)
        $success = true;

        // Find active, verified account
        $stmt = $pdo->prepare("
            SELECT id, name, email_verified
            FROM users
            WHERE email = ? AND is_active = 1
            LIMIT 1
        ");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && $user['email_verified']) {
            // Generate secure reset token
            $token   = bin2hex(random_bytes(32)); // 64 chars
            $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));

            $pdo->prepare("
                UPDATE users
                SET reset_token = ?, reset_token_expires = ?
                WHERE id = ?
            ")->execute([$token, $expires, $user['id']]);

            // Build base URL dynamically
            $scheme  = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
            $baseUrl = $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');

            // Send reset email
            try {
                sendMail(
                    $email,
                    'I-reset ang iyong password — IRMS',
                    mailPasswordReset($user['name'], $token, $baseUrl)
                );
            } catch (Exception $e) {
                error_log('Password reset email failed for ' . $email . ': ' . $e->getMessage());
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fil">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Nakalimutan ang Password — QC-ALERTO</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        * { box-sizing: border-box; }
        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #001a4d 0%, #1e293b 50%, #111827 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .card-wrap {
            background: #fff;
            border-radius: 20px;
            box-shadow: 0 24px 64px rgba(0,0,0,0.25);
            padding: 44px 40px;
            width: 100%;
            max-width: 420px;
        }
        .brand-logo { width: 64px; height: 64px; object-fit: contain; }
        .brand-title { color: #1e293b; font-size: 22px; font-weight: 800; letter-spacing: -0.5px; }
        .brand-sub   { color: #6b7280; font-size: 12px; }
        .section-title { color: #111827; font-size: 16px; font-weight: 700; margin: 0 0 4px; }
        .section-desc  { color: #6b7280; font-size: 13px; margin-bottom: 24px; }
        .form-label-custom { font-size: 13px; font-weight: 600; color: #374151; margin-bottom: 6px; display: block; }
        .input-wrap { position: relative; }
        .input-wrap .icon { position: absolute; left: 14px; top: 50%; transform: translateY(-50%); color: #9ca3af; font-size: 16px; }
        .input-custom {
            width: 100%; padding: 12px 14px 12px 40px;
            border: 1.5px solid #e5e7eb; border-radius: 10px;
            font-size: 14px; color: #111827;
            transition: border-color .2s, box-shadow .2s;
            outline: none;
        }
        .input-custom:focus { border-color: #1e293b; box-shadow: 0 0 0 3px rgba(0,45,122,0.12); }
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
        .back-link { font-size: 13px; color: #6b7280; text-decoration: none; display: inline-flex; align-items: center; gap: 6px; }
        .back-link:hover { color: #1e293b; }
        .divider { text-align: center; margin: 20px 0; color: #d1d5db; font-size: 12px; }
    </style>
</head>
<body>
<div class="card-wrap">

    <!-- Brand header -->
    <div class="text-center mb-4">
        <img src="/irms/assets/img/QC_LOGO_CIRCLE.png" class="brand-logo mb-2" alt="QC-ALERTO">
        <div class="brand-title">QC-ALERTO</div>
        <div class="brand-sub">Incident Report & Monitoring System</div>
    </div>

    <p class="section-title"><i class="bi bi-key me-2" style="color:#1e293b;"></i>I-reset ang Password</p>
    <p class="section-desc">
        Ilagay ang email na ginamit mo sa pag-register.
        Magpapadala kami ng link para ma-reset ang iyong password.
    </p>

    <?php
    // POST already handled above before HTML output
    ?>

    <?php if ($error): ?>
        <div class="alert-err">
            <i class="bi bi-x-circle-fill" style="font-size:16px;flex-shrink:0;"></i>
            <span><?= htmlspecialchars($error) ?></span>
        </div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="alert-ok">
            <strong><i class="bi bi-envelope-check me-2"></i>Nasend na!</strong><br>
            Kung may registered at verified na account sa email na iyon, makakatanggap ka ng
            reset link sa loob ng ilang minuto.<br>
            <span style="font-size:12px;margin-top:6px;display:block;color:#4b7c58;">
                Tingnan din ang iyong <strong>Spam / Junk</strong> folder.
                Ang link ay valid sa loob ng <strong>1 oras</strong>.
            </span>
        </div>
        <a href="/irms/citizen/login.php" class="btn-primary-custom" style="text-decoration:none;">
            <i class="bi bi-box-arrow-in-right"></i> Bumalik sa Login
        </a>
    <?php else: ?>
        <form method="POST" novalidate>
            <?= csrf_field() ?>
            <div class="mb-4">
                <label class="form-label-custom">Email Address</label>
                <div class="input-wrap">
                    <i class="bi bi-envelope icon"></i>
                    <input type="email" name="email" class="input-custom"
                           placeholder="email@example.com" required
                           value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
                </div>
            </div>
            <button type="submit" class="btn-primary-custom mb-4">
                <i class="bi bi-send"></i> Magpadala ng Reset Link
            </button>
        </form>
    <?php endif; ?>

    <div class="divider">—</div>
    <div class="text-center">
        <a href="/irms/citizen/login.php" class="back-link">
            <i class="bi bi-arrow-left"></i> Bumalik sa Login
        </a>
    </div>
</div>
</body>
</html>
