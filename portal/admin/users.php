<?php

require_once __DIR__ . '/../includes/auth.php'; // para sa portal/admin/ at portal/responder/
requireRole('admin');
require_once __DIR__ . '/../config/db.php';

$success = $_GET['success'] ?? '';
$error   = $_GET['error']   ?? '';

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $name     = trim($_POST['name']     ?? '');
        $email    = trim($_POST['email']    ?? '');
        $password = $_POST['password']      ?? '';
        $role     = $_POST['role']          ?? 'citizen';
        $phone    = trim($_POST['phone']    ?? '');

        if ($name && $email && $password) {
            $check = $pdo->prepare("SELECT id FROM users WHERE email = ?");
            $check->execute([$email]);
            if ($check->fetch()) {
                header('Location: /irms/portal/admin/users.php?error=' .
                       urlencode('Ginagamit na ang email.'));
                exit;
            }
            $stmt = $pdo->prepare("
                INSERT INTO users (name, email, password, role, phone)
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $name, $email,
                password_hash($password, PASSWORD_BCRYPT),
                $role, $phone
            ]);
            header('Location: /irms/portal/admin/users.php?success=' .
                   urlencode('Na-add na ang user.'));
            exit;
        }
    }

    if ($action === 'toggle') {
        $uid = (int)($_POST['user_id'] ?? 0);
        if ($uid && $uid != $_SESSION['user_id']) {
            $stmt = $pdo->prepare("
                UPDATE users SET is_active = NOT is_active WHERE id = ?
            ");
            $stmt->execute([$uid]);
        }
        header('Location: /irms/portal/admin/users.php?success=' .
               urlencode('Na-update ang user status.'));
        exit;
    }

    if ($action === 'change_role') {
        $uid  = (int)($_POST['user_id'] ?? 0);
        $role = $_POST['role'] ?? '';
        if ($uid && in_array($role, ['citizen','responder','admin'])) {
            $pdo->prepare("UPDATE users SET role = ? WHERE id = ?")
                ->execute([$role, $uid]);
        }
        header('Location: /irms/portal/admin/users.php?success=' .
               urlencode('Na-update ang role.'));
        exit;
    }
}

$users = $pdo->query("
    SELECT u.*,
        (SELECT COUNT(*) FROM incidents WHERE reporter_id = u.id) AS report_count
    FROM users u
    ORDER BY u.created_at DESC
")->fetchAll();

$roleColor = [
    'admin'     => 'danger',
    'responder' => 'success',
    'citizen'   => 'primary',
];
?>
<!DOCTYPE html>
<html lang="fil">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Users — IRMS Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        .sidebar { width: 220px; min-height: 100vh; background: #1e293b; }
        .sidebar .nav-link { color: #94a3b8; font-size: 14px; padding: 10px 20px; border-radius: 6px; margin: 2px 8px; }
        .sidebar .nav-link:hover, .sidebar .nav-link.active { background: #334155; color: #fff; }
        .main-content { flex: 1; overflow-y: auto; }
        .top-nav { background: #fff; border-bottom: 1px solid #e2e8f0; padding: 12px 24px; }
    </style>
</head>
<body class="bg-light">
<div class="d-flex">

    <div class="sidebar d-flex flex-column py-3">
        <div class="px-4 mb-4">
            <div class="text-white fw-semibold fs-6">
                <i class="bi bi-shield-check me-2"></i>IRMS
            </div>
            <div class="text-secondary" style="font-size:11px;">Admin Panel</div>
        </div>
         <nav class="flex-column nav">
    <a href="/irms/portal/admin/dashboard.php" class="nav-link">
        <i class="bi bi-speedometer2 me-2"></i> Dashboard
    </a>
    <a href="/irms/portal/admin/incidents.php" class="nav-link">
        <i class="bi bi-exclamation-triangle me-2"></i> Incidents
    </a>
    <a href="/irms/portal/admin/users.php" class="nav-link active">
        <i class="bi bi-people me-2"></i> Users
    </a>
    <a href="/irms/portal/admin/categories.php" class="nav-link">
        <i class="bi bi-tags me-2"></i> Categories
    </a>
    <a href="/irms/portal/admin/reports.php" class="nav-link">
        <i class="bi bi-file-earmark-bar-graph me-2"></i> Reports
    </a>
</nav>
        <div class="mt-auto px-3">
            <div class="text-secondary small px-2 mb-2">
                <i class="bi bi-person-circle me-1"></i>
                <?= htmlspecialchars($_SESSION['name']) ?>
            </div>
            <a href="/irms/controllers/AuthController.php?action=logout"
               class="nav-link text-danger">
                <i class="bi bi-box-arrow-right me-2"></i> Logout
            </a>
        </div>
    </div>

    <div class="main-content">
        <div class="top-nav d-flex justify-content-between align-items-center">
            <h6 class="fw-semibold mb-0">User Management</h6>
            <button class="btn btn-primary btn-sm" data-bs-toggle="modal"
                    data-bs-target="#addUserModal">
                <i class="bi bi-plus-lg me-1"></i> Add User
            </button>
        </div>

        <div class="p-4">

            <?php if ($success): ?>
                <div class="alert alert-success alert-dismissible fade show py-2 small">
                    <i class="bi bi-check-circle me-1"></i><?= htmlspecialchars($success) ?>
                    <button type="button" class="btn-close btn-sm" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="alert alert-danger alert-dismissible fade show py-2 small">
                    <i class="bi bi-exclamation-circle me-1"></i><?= htmlspecialchars($error) ?>
                    <button type="button" class="btn-close btn-sm" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <div class="card border-0 shadow-sm">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th class="ps-3 small">#</th>
                                    <th class="small">Name</th>
                                    <th class="small">Email</th>
                                    <th class="small">Role</th>
                                    <th class="small">Reports</th>
                                    <th class="small">Status</th>
                                    <th class="small">Joined</th>
                                    <th class="small">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($users as $u): ?>
                                <tr>
                                    <td class="ps-3 text-muted small"><?= $u['id'] ?></td>
                                    <td class="small fw-medium">
                                        <?= htmlspecialchars($u['name']) ?>
                                    </td>
                                    <td class="small text-muted">
                                        <?= htmlspecialchars($u['email']) ?>
                                    </td>
                                    <td>
                                        <!-- Change role form -->
                                        <form action="" method="POST"
                                              class="d-flex gap-1 align-items-center">
                                            <input type="hidden" name="action" value="change_role">
                                            <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                            <select name="role"
                                                    class="form-select form-select-sm"
                                                    style="width:110px;font-size:12px;"
                                                    onchange="this.form.submit()">
                                                <?php foreach (['citizen','responder','admin'] as $r): ?>
                                                    <option value="<?= $r ?>"
                                                        <?= $u['role'] === $r ? 'selected' : '' ?>>
                                                        <?= ucfirst($r) ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </form>
                                    </td>
                                    <td class="small text-center">
                                        <?= $u['report_count'] ?>
                                    </td>
                                    <td>
                                        <span class="badge bg-<?= $u['is_active'] ? 'success' : 'secondary' ?> small">
                                            <?= $u['is_active'] ? 'Active' : 'Inactive' ?>
                                        </span>
                                    </td>
                                    <td class="small text-muted">
                                        <?= date('M d, Y', strtotime($u['created_at'])) ?>
                                    </td>
                                    <td>
                                        <?php if ($u['id'] != $_SESSION['user_id']): ?>
                                        <form action="" method="POST" class="d-inline">
                                            <input type="hidden" name="action" value="toggle">
                                            <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                            <button type="submit"
                                                    class="btn btn-sm btn-outline-<?= $u['is_active'] ? 'danger' : 'success' ?>"
                                                    onclick="return confirm('<?= $u['is_active'] ? 'I-deactivate' : 'I-activate' ?> ang user na ito?')">
                                                <i class="bi bi-<?= $u['is_active'] ? 'person-x' : 'person-check' ?>"></i>
                                            </button>
                                        </form>
                                        <?php else: ?>
                                            <span class="text-muted small">You</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Add User Modal -->
<div class="modal fade" id="addUserModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title fw-semibold">Add New User</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="" method="POST">
                <input type="hidden" name="action" value="add">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label small fw-medium">
                            Full Name <span class="text-danger">*</span>
                        </label>
                        <input type="text" name="name" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-medium">
                            Email <span class="text-danger">*</span>
                        </label>
                        <input type="email" name="email" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-medium">Phone</label>
                        <input type="text" name="phone" class="form-control"
                               placeholder="09XXXXXXXXX">
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-medium">Role</label>
                        <select name="role" class="form-select">
                            <option value="citizen">Citizen</option>
                            <option value="responder">Responder</option>
                            <option value="admin">Admin</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-medium">
                            Password <span class="text-danger">*</span>
                        </label>
                        <input type="password" name="password" class="form-control"
                               placeholder="Minimum 8 characters" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary btn-sm"
                            data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary btn-sm">
                        <i class="bi bi-person-plus me-1"></i> Add User
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>