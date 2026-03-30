<?php
require_once __DIR__ . '/../../includes/auth.php';
if (isLoggedIn()) redirectByRole();

$error   = $_GET['error']   ?? '';
$success = $_GET['success'] ?? '';
?>
<!DOCTYPE html>
<html lang="fil">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Login — IRMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="/irms/assets/css/style.css" rel="stylesheet">
</head>
<body class="bg-light d-flex align-items-center min-vh-100">

<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-5 col-lg-4">

            <div class="text-center mb-4">
                <i class="bi bi-shield-check fs-1 text-primary"></i>
                <h4 class="fw-semibold mt-2">IRMS</h4>
                <p class="text-muted small">Incident Report & Monitoring System</p>
            </div>

            <div class="card border-0 shadow-sm">
                <div class="card-body p-4">

                    <?php if ($error): ?>
                        <div class="alert alert-danger alert-dismissible fade show py-2 small" role="alert">
                            <i class="bi bi-exclamation-circle me-1"></i>
                            <?= htmlspecialchars($error) ?>
                            <button type="button" class="btn-close btn-sm" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>

                    <?php if ($success === 'registered'): ?>
                        <div class="alert alert-success py-2 small">
                            <i class="bi bi-check-circle me-1"></i>
                            Matagumpay na naka-register! Mag-login na.
                        </div>
                    <?php endif; ?>

                    <form action="/irms/controllers/AuthController.php?action=login" method="POST">
                        <div class="mb-3">
                            <label class="form-label small fw-medium">Email</label>
                            <input type="email" name="email" class="form-control"
                                placeholder="email@example.com" required autofocus>
                        </div>
                        <div class="mb-4">
                            <label class="form-label small fw-medium">Password</label>
                            <div class="input-group">
                                <input type="password" name="password" id="password"
                                    class="form-control" placeholder="••••••••" required>
                                <button type="button" class="btn btn-outline-secondary"
                                    onclick="togglePassword()">
                                    <i class="bi bi-eye" id="eye-icon"></i>
                                </button>
                            </div>
                        </div>
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="bi bi-box-arrow-in-right me-1"></i> Mag-login
                        </button>
                    </form>

                </div>
            </div>

            <p class="text-center text-muted small mt-3">
                Wala pang account?
                <a href="/irms/views/auth/register.php" class="text-decoration-none">Mag-register</a>
            </p>

        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
function togglePassword() {
    const input = document.getElementById('password');
    const icon  = document.getElementById('eye-icon');
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