<?php
require_once __DIR__ . '/../../includes/auth.php';
requireRole('admin');
require_once __DIR__ . '/../../config/db.php';

$user    = currentUser();
$success = $_GET['success'] ?? '';
$error   = $_GET['error']   ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $name     = trim($_POST['name']     ?? '');
        $email    = trim($_POST['email']    ?? '');
        $password = $_POST['password']      ?? '';
        $role     = $_POST['role']          ?? 'citizen';
        $phone    = trim($_POST['phone']    ?? '');
        $agency   = trim($_POST['agency']   ?? '');

        if ($name && $email && $password) {
            $check = $pdo->prepare("SELECT id FROM users WHERE email = ?");
            $check->execute([$email]);
            if ($check->fetch()) {
                header('Location: /irms/portal/admin/users.php?error=' .
                       urlencode('Ginagamit na ang email.'));
                exit;
            }
            $pdo->prepare("
                INSERT INTO users (name, email, password, role, phone, agency)
                VALUES (?, ?, ?, ?, ?, ?)
            ")->execute([
                $name, $email,
                password_hash($password, PASSWORD_BCRYPT),
                $role, $phone, $agency ?: null
            ]);
            header('Location: /irms/portal/admin/users.php?success=' .
                   urlencode('Na-add na ang user.'));
            exit;
        }
    }

    if ($action === 'toggle') {
        $uid = (int)($_POST['user_id'] ?? 0);
        if ($uid && $uid != $_SESSION['user_id']) {
            $pdo->prepare("UPDATE users SET is_active = NOT is_active WHERE id = ?")
                ->execute([$uid]);
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
?>
<!DOCTYPE html>
<html lang="fil">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Users — IRMS Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <?php include __DIR__ . '/../../includes/sidebar_style.php'; ?>
</head>
<body class="bg-light">
<div class="d-flex">

    <?php include __DIR__ . '/../../includes/sidebar_admin.php'; ?>

    <div class="main-content">
        <div class="top-nav d-flex justify-content-between align-items-center">
            <div class="d-flex align-items-center gap-3">
                <button class="hamburger btn btn-sm btn-outline-secondary"
                        style="display:none;align-items:center;justify-content:center;
                               width:36px;height:36px;padding:0;"
                        onclick="toggleSidebar()">
                    <i class="bi bi-list fs-5"></i>
                </button>
                <h6 class="fw-semibold mb-0">User Management</h6>
            </div>
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
                                    <th class="small">Agency</th>
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
                                    <td class="small">
                                        <?php if ($u['agency']): ?>
                                            <span class="badge bg-info text-dark small">
                                                <?= htmlspecialchars($u['agency']) ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="text-muted">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
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
                                    <td class="small text-center"><?= $u['report_count'] ?></td>
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
                        <select name="role" class="form-select" id="role-select"
                                onchange="toggleAgency(this.value)">
                            <option value="citizen">Citizen</option>
                            <option value="responder" selected>Responder</option>
                            <option value="admin">Admin</option>
                        </select>
                    </div>
                    <div class="mb-3" id="agency-field">
                        <label class="form-label small fw-medium">Agency</label>
                        <select name="agency" class="form-select">
                            <option value="">-- Piliin ang Agency --</option>
                            <option value="BFP">Bureau of Fire Protection (BFP)</option>
                            <option value="PNP">Philippine National Police (PNP)</option>
                            <option value="NDRRMC">NDRRMC / LDRRMO</option>
                            <option value="DOH">Department of Health (DOH)</option>
                            <option value="MMDA">MMDA</option>
                            <option value="DPWH">DPWH</option>
                            <option value="Other">Other</option>
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
<script>
function toggleAgency(role) {
    var agencyField = document.getElementById('agency-field');
    agencyField.style.display = role === 'responder' ? 'block' : 'none';
}
document.addEventListener('DOMContentLoaded', function() {
    toggleAgency(document.getElementById('role-select').value);
});
</script>
</body>
</html>