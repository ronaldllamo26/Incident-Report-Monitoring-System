<?php
require_once __DIR__ . '/../../includes/auth.php';
requireRole('admin');
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../models/User.php';

$userModel = new User();
$user      = currentUser();
$success   = $_GET['success'] ?? '';
$error     = $_GET['error']   ?? '';

// ── FILTERS ──────────────────────────────────────────
$filters = [
    'role'   => $_GET['role']   ?? '',
    'agency' => $_GET['agency'] ?? '',
    'search' => $_GET['search'] ?? '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validate_csrf();
    $action = $_POST['action'] ?? '';
    if ($action === 'add') {
        $name=trim($_POST['name']??'');
        $email=trim($_POST['email']??''); $password=$_POST['password']??'';
        $role=$_POST['role']??'citizen'; $phone=trim($_POST['phone']??'');
        $agency=trim($_POST['agency']??'');
        if ($name && $email && $password) {
            if ($userModel->emailExists($email)) { 
                header('Location: /irms/portal/admin/users.php?error='.urlencode('Ginagamit na ang email.')); exit; 
            }
            $userModel->create([
                'name'     => $name,
                'email'    => $email,
                'password' => $password,
                'phone'    => $phone,
                'address'  => $_POST['address'] ?? '',
                'verify_token' => bin2hex(random_bytes(16)),
                'verify_token_expires' => date('Y-m-d H:i:s', strtotime('+24 hours'))
            ]);
            // Force verify if added by admin? usually yes for staff
            if ($role !== 'citizen') {
                $pdo->prepare("UPDATE users SET email_verified=1 WHERE email=?")->execute([$email]);
            }
            header('Location: /irms/portal/admin/users.php?success='.urlencode('Na-add na ang user.')); exit;
        }
    }
    if ($action === 'toggle') {
        $uid=(int)($_POST['user_id']??0);
        if ($uid && $uid!=$_SESSION['user_id']) $pdo->prepare("UPDATE users SET is_active=NOT is_active WHERE id=?")->execute([$uid]);
        header('Location: /irms/portal/admin/users.php?success='.urlencode('Na-update ang user status.')); exit;
    }
    if ($action === 'change_role') {
        $uid=(int)($_POST['user_id']??0); $role=$_POST['role']??'';
        if ($uid && in_array($role,['citizen','responder','admin'])) $pdo->prepare("UPDATE users SET role=? WHERE id=?")->execute([$role,$uid]);
        header('Location: /irms/portal/admin/users.php?success='.urlencode('Na-update ang role.')); exit;
    }
}

$users = $userModel->getAll($filters);
?>
<!DOCTYPE html>
<html lang="fil">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">
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
                        style="display:none;align-items:center;justify-content:center;width:36px;height:36px;padding:0;"
                        onclick="toggleSidebar()">
                    <i class="bi bi-list fs-5"></i>
                </button>
                <h6 class="fw-semibold mb-0">User Management</h6>
            </div>
            <!-- BELL + ADD USER BUTTON -->
            <div class="d-flex align-items-center gap-2">
                <?php include __DIR__ . '/../../includes/notification_bell.php'; ?>
                <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addUserModal">
                    <i class="bi bi-plus-lg me-1"></i> Add User
                </button>
            </div>
        </div>
            <div class="p-4 pt-1">
                <?php if ($success): ?>
                    <div class="alert alert-success alert-dismissible fade show py-2 small mb-3">
                        <i class="bi bi-check-circle me-1"></i><?= htmlspecialchars($success) ?>
                        <button type="button" class="btn-close btn-sm" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                <?php if ($error): ?>
                    <div class="alert alert-danger alert-dismissible fade show py-2 small mb-3">
                        <i class="bi bi-exclamation-circle me-1"></i><?= htmlspecialchars($error) ?>
                        <button type="button" class="btn-close btn-sm" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <!-- ── FILTERS & SEARCH ────────────────────────── -->
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-body p-3">
                        <form method="GET" class="row g-2 align-items-center">
                            <div class="col-md-4">
                                <div class="input-group input-group-sm">
                                    <span class="input-group-text bg-white border-end-0"><i class="bi bi-search"></i></span>
                                    <input type="text" name="search" class="form-control border-start-0" 
                                           placeholder="Hanapin sa pangalan o email..." 
                                           value="<?= htmlspecialchars($filters['search']) ?>">
                                </div>
                            </div>
                            <div class="col-md-2">
                                <select name="role" class="form-select form-select-sm">
                                    <option value="">Lahat ng Roles</option>
                                    <option value="admin" <?= $filters['role']==='admin'?'selected':'' ?>>Admin</option>
                                    <option value="responder" <?= $filters['role']==='responder'?'selected':'' ?>>Responder</option>
                                    <option value="citizen" <?= $filters['role']==='citizen'?'selected':'' ?>>Citizen</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <select name="agency" class="form-select form-select-sm">
                                    <option value="">Lahat ng Agency</option>
                                    <?php 
                                    $agencies = $pdo->query("SELECT DISTINCT agency FROM users WHERE agency IS NOT NULL")->fetchAll(PDO::FETCH_COLUMN);
                                    foreach ($agencies as $a):
                                    ?>
                                        <option value="<?= $a ?>" <?= $filters['agency']===$a?'selected':'' ?>><?= $a ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-auto">
                                <button type="submit" class="btn btn-primary btn-sm">Filter</button>
                                <a href="/irms/portal/admin/users.php" class="btn btn-outline-secondary btn-sm">Reset</a>
                            </div>
                        </form>
                    </div>
                </div>
            <div class="card border-0 shadow-sm">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th class="ps-3 small">#</th>
                                    <th class="small">Name</th>
                                    <th class="small">Agency</th>
                                    <th class="small">Role</th>
                                    <th class="small">Verification</th>
                                    <th class="small">Status</th>
                                    <th class="small">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($users as $u): ?>
                                <tr>
                                    <td class="ps-3 text-muted small"><?= $u['id'] ?></td>
                                    <td>
                                        <div class="small fw-bold text-slate"><?= htmlspecialchars($u['name']) ?></div>
                                        <div class="x-small text-muted" style="font-size: 11px;"><?= htmlspecialchars($u['email']) ?></div>
                                    </td>
                                    <td class="small">
                                        <?php if ($u['agency']): ?>
                                            <span class="badge bg-info text-dark x-small"><?= htmlspecialchars($u['agency']) ?></span>
                                        <?php else: ?><span class="text-muted">—</span><?php endif; ?>
                                    </td>
                                    <td>
                                        <form action="" method="POST" class="d-flex gap-1 align-items-center">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="action" value="change_role">
                                            <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                            <select name="role" class="form-select form-select-sm x-small" style="width:100px;font-size:11px;" onchange="this.form.submit()">
                                                <?php foreach (['citizen','responder','admin'] as $r): ?>
                                                    <option value="<?= $r ?>" <?= $u['role']===$r?'selected':''?>><?= ucfirst($r) ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </form>
                                    </td>
                                    <td>
                                        <?php if ($u['email_verified']): ?>
                                            <span class="text-success small" title="Email Verified">
                                                <i class="bi bi-patch-check-fill me-1"></i> Verified
                                            </span>
                                        <?php else: ?>
                                            <span class="text-muted small" title="Pending Verification">
                                                <i class="bi bi-clock me-1"></i> Pending
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td><span class="badge bg-<?= $u['is_active']?'success':'secondary' ?> x-small"><?= $u['is_active']?'Active':'Inactive' ?></span></td>
                                    <td>
                                        <div class="d-flex gap-1">
                                            <button type="button" class="btn btn-sm btn-outline-primary" 
                                                    title="View Details"
                                                    onclick="viewUser(<?= htmlspecialchars(json_encode($u)) ?>)">
                                                <i class="bi bi-eye"></i>
                                            </button>
                                            
                                            <?php if ($u['id']!=$_SESSION['user_id']): ?>
                                            <form action="" method="POST" class="d-inline">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="action" value="toggle">
                                                <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-<?= $u['is_active']?'danger':'success' ?>"
                                                        title="<?= $u['is_active']?'Deactivate':'Activate' ?>"
                                                        onclick="return confirm('<?= $u['is_active']?'I-deactivate':'I-activate' ?> ang user na ito?')">
                                                    <i class="bi bi-<?= $u['is_active']?'person-x':'person-check' ?>"></i>
                                                </button>
                                            </form>
                                            <?php endif; ?>
                                        </div>
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

<div class="modal fade" id="addUserModal" tabindex="-1">
    <div class="modal-dialog"><div class="modal-content">
        <div class="modal-header">
            <h6 class="modal-title fw-semibold">Add New User</h6>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <form action="" method="POST">
                        <?= csrf_field() ?>
            <input type="hidden" name="action" value="add">
            <div class="modal-body">
                <div class="mb-3"><label class="form-label small fw-medium">Full Name <span class="text-danger">*</span></label><input type="text" name="name" class="form-control" required></div>
                <div class="mb-3"><label class="form-label small fw-medium">Email <span class="text-danger">*</span></label><input type="email" name="email" class="form-control" required></div>
                <div class="mb-3"><label class="form-label small fw-medium">Phone</label><input type="text" name="phone" class="form-control" placeholder="09XXXXXXXXX"></div>
                <div class="mb-3">
                    <label class="form-label small fw-medium">Role</label>
                    <select name="role" class="form-select" id="role-select" onchange="toggleAgency(this.value)">
                        <option value="citizen">Citizen</option><option value="responder" selected>Responder</option><option value="admin">Admin</option>
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
                        <option value="MMDA">MMDA</option><option value="DPWH">DPWH</option><option value="Other">Other</option>
                    </select>
                </div>
                <div class="mb-3"><label class="form-label small fw-medium">Password <span class="text-danger">*</span></label><input type="password" name="password" class="form-control" placeholder="Minimum 8 characters" required></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-person-plus me-1"></i> Add User</button>
            </div>
        </form>
    </div></div>
</div>

<!-- User Details Modal -->
<div class="modal fade" id="userModal" tabindex="-1">
    <div class="modal-dialog"><div class="modal-content">
        <div class="modal-header">
            <h6 class="modal-title fw-bold" id="modal-name">User Details</h6>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
            <div class="mb-3">
                <label class="x-small text-muted text-uppercase fw-bold">Contact Information</label>
                <div class="p-3 bg-light rounded-3 mt-1">
                    <div class="mb-2"><i class="bi bi-envelope me-2"></i> <span id="modal-email"></span></div>
                    <div class="mb-2"><i class="bi bi-telephone me-2"></i> <span id="modal-phone"></span></div>
                    <div class="mb-0"><i class="bi bi-geo-alt me-2"></i> <span id="modal-address"></span></div>
                </div>
            </div>
            <div class="row g-2">
                <div class="col-6">
                    <div class="p-2 border rounded-3 text-center">
                        <div class="x-small text-muted">Role</div>
                        <div class="fw-bold small" id="modal-role"></div>
                    </div>
                </div>
                <div class="col-6">
                    <div class="p-2 border rounded-3 text-center">
                        <div class="x-small text-muted">Joined</div>
                        <div class="fw-bold small" id="modal-joined"></div>
                    </div>
                </div>
            </div>
            <div class="mt-3 p-2 bg-slate text-white rounded-3 text-center">
                <div class="x-small opacity-75">Incidents Reported / Handled</div>
                <div class="fs-4 fw-bold" id="modal-reports">0</div>
            </div>
        </div>
    </div></div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
function toggleAgency(role) { document.getElementById('agency-field').style.display=role==='responder'?'block':'none'; }
function viewUser(u) {
    document.getElementById('modal-name').innerText = u.name;
    document.getElementById('modal-email').innerText = u.email;
    document.getElementById('modal-phone').innerText = u.phone || 'N/A';
    document.getElementById('modal-address').innerText = u.address || 'N/A';
    document.getElementById('modal-role').innerText = u.role.toUpperCase();
    document.getElementById('modal-joined').innerText = u.created_at;
    document.getElementById('modal-reports').innerText = u.report_count;
    new bootstrap.Modal(document.getElementById('userModal')).show();
}
document.addEventListener('DOMContentLoaded',function(){ toggleAgency(document.getElementById('role-select').value); });
</script>
</body>
</html>