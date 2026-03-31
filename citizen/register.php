<?php

require_once __DIR__ . '/../includes/auth.php';
if (isLoggedIn()) redirectByRole();

$error = $_GET['error'] ?? '';
?>
<!DOCTYPE html>
<html lang="fil">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Register — IRMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="/irms/assets/css/style.css" rel="stylesheet">
</head>
<body class="bg-light d-flex align-items-center min-vh-100 py-4">

<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-6 col-lg-5">

            <div class="text-center mb-4">
                <i class="bi bi-shield-check fs-1 text-primary"></i>
                <h4 class="fw-semibold mt-2">Gumawa ng Account</h4>
                <p class="text-muted small">Para ma-report ang mga insidente sa iyong komunidad</p>
            </div>

            <div class="card border-0 shadow-sm">
                <div class="card-body p-4">

                    <?php if ($error): ?>
                        <div class="alert alert-danger alert-dismissible fade show py-2 small">
                            <i class="bi bi-exclamation-circle me-1"></i>
                            <?= htmlspecialchars($error) ?>
                            <button type="button" class="btn-close btn-sm" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>

                    <form action="/irms/controllers/AuthController.php?action=register" method="POST">
                        <div class="mb-3">
                            <label class="form-label small fw-medium">Buong Pangalan <span class="text-danger">*</span></label>
                            <input type="text" name="name" class="form-control"
                                placeholder="Juan dela Cruz" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label small fw-medium">Email <span class="text-danger">*</span></label>
                            <input type="email" name="email" class="form-control"
                                placeholder="email@example.com" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label small fw-medium">Phone Number</label>
                            <input type="text" name="phone" class="form-control"
                                placeholder="09XXXXXXXXX">
                        </div>
                        <div class="mb-3">
                            <label class="form-label small fw-medium">Address</label>
                            <input type="text" name="address" class="form-control"
                                placeholder="Barangay, Lungsod">
                        </div>
                        <div class="mb-3">
                            <label class="form-label small fw-medium">Password <span class="text-danger">*</span></label>
                            <input type="password" name="password" id="password"
                                class="form-control" placeholder="Minimum 8 characters" required>
                        </div>
                        <div class="mb-4">
                            <label class="form-label small fw-medium">Confirm Password <span class="text-danger">*</span></label>
                            <input type="password" name="confirm_password"
                                class="form-control" placeholder="Ulitin ang password" required>
                        </div>
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="bi bi-person-plus me-1"></i> Mag-register
                        </button>
                    </form>

                </div>
            </div>

            <p class="text-center text-muted small mt-3">
                May account na?
                <a href="/irms/citizen/login.php" class="text-decoration-none">Mag-login</a>
            </p>

        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>