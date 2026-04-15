<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../config/mailer.php';

$error   = '';
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validate_csrf();

    $email = trim($_POST['email'] ?? '');

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Hindi valid ang email address.';
    } else {
        // Find unverified account
        $stmt = $pdo->prepare("
            SELECT id, name, email_verified
            FROM users
            WHERE email = ? AND is_active = 1
            LIMIT 1
        ");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if (!$user || $user['email_verified']) {
            // Don't reveal if account exists or is already verified —
            // just show the same success message (security best practice)
            $success = true;
        } else {
            // Generate fresh token
            $token   = bin2hex(random_bytes(32));
            $expires = date('Y-m-d H:i:s', strtotime('+24 hours'));

            $pdo->prepare("
                UPDATE users
                SET verify_token = ?, verify_token_expires = ?
                WHERE id = ?
            ")->execute([$token, $expires, $user['id']]);

            // Send email
            $scheme  = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
            $baseUrl = $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');

            try {
                sendMail(
                    $email,
                    'I-verify ang iyong email — IRMS',
                    mailVerifyEmail($user['name'], $token, $baseUrl)
                );
            } catch (Exception $e) {
                error_log('Resend verify failed for ' . $email . ': ' . $e->getMessage());
            }

            $success = true;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fil">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>I-resend ang Verification — QC-ALERTO</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; background: #f1f5f9; min-height: 100vh;
               display: flex; align-items: center; justify-content: center; }
        .verify-card { background: #fff; border-radius: 16px; box-shadow: 0 8px 40px rgba(0,0,0,0.10);
                       padding: 40px 36px; max-width: 420px; width: 100%; }
        .brand-logo { width: 64px; height: 64px; object-fit: contain; }
        .btn-primary-custom { background: #1e293b; border: none; color: #fff; padding: 12px;
                              border-radius: 10px; font-weight: 600; width: 100%; font-size: 15px;
                              transition: background 0.2s; }
        .btn-primary-custom:hover { background: #111827; }
        .form-control:focus { border-color: #1e293b; box-shadow: 0 0 0 3px rgba(0,45,122,0.12); }
    </style>
</head>
<body>
<div class="verify-card text-center">
    <img src="/irms/assets/img/QC_LOGO_CIRCLE.png" class="brand-logo mb-3" alt="QC-ALERTO">
    <h5 class="fw-bold mb-1" style="color:#1e293b;">I-resend ang Verification Email</h5>
    <p class="text-muted small mb-4">
        Ilagay ang email na ginamit mo sa pag-register.
        Magpapadala kami ng bagong verification link.
    </p>

    <?php if ($success): ?>
        <div class="alert alert-success text-start" style="border-radius:10px;">
            <i class="bi bi-check-circle-fill me-2 text-success"></i>
            <strong>Nasend na!</strong><br>
            <span class="small">Kung may account kang naka-register sa email na iyon,
            makakatanggap ka ng bagong verification link sa loob ng ilang minuto.
            Tingnan din ang iyong <strong>Spam</strong> folder.</span>
        </div>
        <a href="/irms/citizen/login.php" class="btn btn-outline-secondary w-100" style="border-radius:10px;">
            <i class="bi bi-arrow-left me-1"></i> Bumalik sa Login
        </a>
    <?php else: ?>
        <?php if ($error): ?>
            <div class="alert alert-danger text-start small" style="border-radius:10px;">
                <i class="bi bi-x-circle-fill me-1"></i><?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>
        <form method="POST">
            <?= csrf_field() ?>
            <div class="mb-3 text-start">
                <label class="form-label small fw-medium">Email Address</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-envelope"></i></span>
                    <input type="email" name="email" class="form-control"
                           placeholder="email@example.com" required
                           value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
                </div>
            </div>
            <button type="submit" class="btn-primary-custom mb-3">
                <i class="bi bi-envelope-arrow-up me-2"></i> Magpadala ng Verification Link
            </button>
        </form>
        <a href="/irms/citizen/login.php" class="text-muted small text-decoration-none">
            <i class="bi bi-arrow-left me-1"></i> Bumalik sa Login
        </a>
    <?php endif; ?>
</div>
</body>
</html>
