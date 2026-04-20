<?php
require_once __DIR__ . '/../../includes/auth.php';
requireRole(['admin', 'responder']);
require_once __DIR__ . '/../../config/db.php';

$user = currentUser();
$success = $_GET['success'] ?? '';
$error = $_GET['error'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $current_pass = $_POST['current_password'] ?? '';
    $new_pass = $_POST['new_password'] ?? '';
    $confirm_pass = $_POST['confirm_password'] ?? '';

    try {
        // Update basic info
        $stmt = $pdo->prepare("UPDATE users SET name = ?, email = ? WHERE id = ?");
        $stmt->execute([$name, $email, $user['id']]);

        // Password update logic if requested
        if (!empty($new_pass)) {
            if (empty($current_pass)) throw new Exception("Kailangan ang current password para makapagpalit ng bago.");
            
            // Re-verify user to check current password
            $checkStmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
            $checkStmt->execute([$user['id']]);
            $userData = $checkStmt->fetch();

            if (!password_verify($current_pass, $userData['password'])) {
                throw new Exception("Mali ang iyong current password.");
            }

            if ($new_pass !== $confirm_pass) {
                throw new Exception("Hindi nagtutugma ang new password at confirmation.");
            }

            if (strlen($new_pass) < 6) {
                throw new Exception("Dapat hindi bababa sa 6 characters ang bagong password.");
            }

            $hashed = password_hash($new_pass, PASSWORD_DEFAULT);
            $updPass = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
            $updPass->execute([$hashed, $user['id']]);
        }

        header("Location: profile.php?success=" . urlencode("Na-update na ang iyong profile."));
        exit;
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Redirect style based on role
$is_admin = $_SESSION['role'] === 'admin';
$sidebar_include = $is_admin ? __DIR__ . '/../../includes/sidebar_admin.php' : __DIR__ . '/../../portal/responder/sidebar_responder_shim.php'; // I'll create a shim for responder
?>
<!DOCTYPE html>
<html lang="fil">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>My Profile — QC-ALERTO</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <?php if (!$is_admin): ?>
        <link href="/irms/assets/css/theme-responder.css" rel="stylesheet">
        <script src="/irms/assets/js/theme-responder.js"></script>
    <?php endif; ?>
    <?php include __DIR__ . '/../../includes/sidebar_style.php'; ?>
    <style>
        .profile-card {
            background: #fff;
            border-radius: 24px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.05);
            overflow: hidden;
            border: 1px solid #f1f5f9;
        }
        .profile-header {
            background: linear-gradient(135deg, #1e293b, #0f172a);
            padding: 60px 40px;
            text-align: center;
            color: #fff;
        }
        .avatar-lg {
            width: 100px; height: 100px;
            background: rgba(255,255,255,0.1);
            border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            font-size: 40px; font-weight: 800;
            margin: 0 auto 15px;
            border: 4px solid rgba(255,255,255,0.2);
        }
        .form-label-p { font-size: 13px; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 8px; }
        .input-p { padding: 12px 16px; border-radius: 12px; border: 1px solid #e2e8f0; font-size: 14px; transition: all 0.3s; }
        .input-p:focus { border-color: #3b82f6; box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.1); outline: none; }
    </style>
</head>
<body class="bg-light <?= $is_admin ? 'admin-view' : 'responder-view' ?>">
<div class="d-flex">
    <?php 
    if ($is_admin) {
        include __DIR__ . '/../../includes/sidebar_admin.php'; 
    } else {
        include __DIR__ . '/../../portal/responder/sidebar_responder_shim.php';
    }
    ?>
    <div class="main-content">
        <div class="top-nav">
            <h6 class="top-nav-title"><?= $is_admin ? 'Account Management' : 'Responder Profile' ?></h6>
            <div class="d-flex align-items-center gap-3">
                <?php include __DIR__ . '/../../includes/notification_bell.php'; ?>
                <?php if (!$is_admin): ?>
                    <button class="theme-toggle-btn border-0 p-0" onclick="toggleTheme()" title="Toggle Dark/Light Mode" style="background:transparent; color: var(--text-dim);">
                        <i class="bi bi-moon-stars-fill dark-only"></i>
                        <i class="bi bi-sun-fill light-only"></i>
                    </button>
                <?php endif; ?>
            </div>
        </div>

        <div class="container py-5">
            <div class="profile-card mx-auto" style="max-width: 800px;">
                <div class="profile-header">
                    <div class="avatar-lg"><?= strtoupper(substr($user['name'], 0, 1)) ?></div>
                    <h3 class="fw-bold mb-1"><?= htmlspecialchars($user['name']) ?></h3>
                    <div class="badge bg-primary-subtle text-primary border border-primary-subtle px-3 py-2" style="font-size: 11px; text-transform: uppercase; letter-spacing: 1px;">
                        <?= strtoupper($user['role']) ?> Account
                    </div>
                </div>

                <div class="p-5">
                    <?php if ($success): ?>
                        <div class="alert alert-success border-0 shadow-sm mb-4" style="border-radius:12px;">
                            <i class="bi bi-check-circle-fill me-2"></i> <?= htmlspecialchars($success) ?>
                        </div>
                    <?php elseif ($error): ?>
                        <div class="alert alert-danger border-0 shadow-sm mb-4" style="border-radius:12px;">
                            <i class="bi bi-exclamation-octagon-fill me-2"></i> <?= htmlspecialchars($error) ?>
                        </div>
                    <?php endif; ?>

                    <form action="profile.php" method="POST">
                        <div class="row g-4">
                            <div class="col-md-6">
                                <label class="form-label-p">Full Name</label>
                                <input type="text" name="name" class="form-control input-p" value="<?= htmlspecialchars($user['name']) ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label-p">Email Address</label>
                                <input type="email" name="email" class="form-control input-p" value="<?= htmlspecialchars($user['email']) ?>" required>
                            </div>

                            <hr class="my-4 opacity-50">
                            
                            <div class="col-12">
                                <h6 class="fw-bold mb-3"><i class="bi bi-shield-lock me-2 text-primary"></i> Change Password</h6>
                                <p class="text-muted small">Iwanang blanko kung hindi gustong palitan ang password.</p>
                            </div>

                            <div class="col-md-12">
                                <label class="form-label-p">Current Password</label>
                                <input type="password" name="current_password" class="form-control input-p" placeholder="Required if changing password">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label-p">New Password</label>
                                <input type="password" name="new_password" class="form-control input-p" placeholder="Min. 6 characters">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label-p">Confirm New Password</label>
                                <input type="password" name="confirm_password" class="form-control input-p" placeholder="Repeat new password">
                            </div>

                            <div class="col-12 mt-5">
                                <button type="submit" class="btn btn-primary btn-lg w-100 py-3 fw-bold" style="border-radius: 16px;">
                                    <i class="bi bi-save-fill me-2"></i> Save Changes
                                </button>
                                <?php if (!$is_admin): ?>
                                    <a href="/irms/portal/responder/dashboard.php" class="btn btn-link w-100 mt-3 text-muted text-decoration-none small">
                                        <i class="bi bi-arrow-left me-1"></i> Back to Dashboard
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
</body>
</html>
