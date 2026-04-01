<?php
require_once __DIR__ . '/../../includes/auth.php';
requireRole('admin');
require_once __DIR__ . '/../../config/db.php';

$success = $_GET['success'] ?? '';
$error   = $_GET['error']   ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $name         = trim($_POST['name']                  ?? '');
        $desc         = trim($_POST['description']           ?? '');
        $responder_id = (int)($_POST['default_responder_id'] ?? 0) ?: null;
        $sla_critical = (int)($_POST['sla_critical']         ?? 30);
        $sla_high     = (int)($_POST['sla_high']             ?? 120);
        $sla_medium   = (int)($_POST['sla_medium']           ?? 1440);
        $sla_low      = (int)($_POST['sla_low']              ?? 4320);

        if ($name) {
            $pdo->prepare("
                INSERT INTO categories
                    (name, description, default_responder_id,
                     sla_critical, sla_high, sla_medium, sla_low)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ")->execute([$name, $desc, $responder_id,
                         $sla_critical, $sla_high, $sla_medium, $sla_low]);
            header('Location: /irms/portal/admin/categories.php?success=' .
                   urlencode('Na-add ang category.'));
            exit;
        }
    }

    if ($action === 'edit') {
        $id           = (int)($_POST['id']                   ?? 0);
        $name         = trim($_POST['name']                  ?? '');
        $desc         = trim($_POST['description']           ?? '');
        $responder_id = (int)($_POST['default_responder_id'] ?? 0) ?: null;
        $sla_critical = (int)($_POST['sla_critical']         ?? 30);
        $sla_high     = (int)($_POST['sla_high']             ?? 120);
        $sla_medium   = (int)($_POST['sla_medium']           ?? 1440);
        $sla_low      = (int)($_POST['sla_low']              ?? 4320);

        if ($id && $name) {
            $pdo->prepare("
                UPDATE categories
                SET name                 = ?,
                    description          = ?,
                    default_responder_id = ?,
                    sla_critical         = ?,
                    sla_high             = ?,
                    sla_medium           = ?,
                    sla_low              = ?
                WHERE id = ?
            ")->execute([$name, $desc, $responder_id,
                         $sla_critical, $sla_high, $sla_medium, $sla_low,
                         $id]);
            header('Location: /irms/portal/admin/categories.php?success=' .
                   urlencode('Na-update ang category.'));
            exit;
        }
    }

    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        $check = $pdo->prepare("SELECT COUNT(*) FROM incidents WHERE category_id = ?");
        $check->execute([$id]);
        if ($check->fetchColumn() > 0) {
            header('Location: /irms/portal/admin/categories.php?error=' .
                   urlencode('Hindi pwedeng i-delete — may incidents na gumagamit nito.'));
            exit;
        }
        $pdo->prepare("DELETE FROM categories WHERE id = ?")->execute([$id]);
        header('Location: /irms/portal/admin/categories.php?success=' .
               urlencode('Na-delete ang category.'));
        exit;
    }
}

$categories = $pdo->query("
    SELECT c.*,
           COUNT(i.id)  AS incident_count,
           u.name       AS default_responder_name
    FROM categories c
    LEFT JOIN incidents i ON i.category_id = c.id
    LEFT JOIN users u ON c.default_responder_id = u.id
    GROUP BY c.id
    ORDER BY c.name
")->fetchAll();

$responders = $pdo->query("
    SELECT id, name FROM users
    WHERE role = 'responder' AND is_active = 1
    ORDER BY name
")->fetchAll();

$user = currentUser();
?>
<!DOCTYPE html>
<html lang="fil">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Categories — IRMS Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <?php include __DIR__ . '/../../includes/sidebar_style.php'; ?>
    <style>
        .sla-badge { font-size: 10px; font-weight: 500; padding: 2px 6px; border-radius: 4px; }
    </style>
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
                <h6 class="fw-semibold mb-0">Categories</h6>
            </div>
            <button class="btn btn-primary btn-sm" data-bs-toggle="modal"
                    data-bs-target="#addCatModal">
                <i class="bi bi-plus-lg me-1"></i> Add Category
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

            <div class="alert alert-info py-2 small mb-3">
                <i class="bi bi-info-circle me-1"></i>
                <strong>Tip:</strong> I-set ang <strong>Default Responder</strong> per category
                para awtomatikong ma-assign ang tamang responder pag may bagong incident.
                Halimbawa: Fire Incident → BFP Responder.
                Ang <strong>SLA</strong> ay ang deadline ng response based sa severity.
            </div>

            <div class="card border-0 shadow-sm">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th class="ps-3 small">#</th>
                                    <th class="small">Category</th>
                                    <th class="small">Default Responder</th>
                                    <th class="small">SLA (minutes)</th>
                                    <th class="small">Incidents</th>
                                    <th class="small">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($categories as $cat): ?>
                                <tr>
                                    <td class="ps-3 text-muted small"><?= $cat['id'] ?></td>
                                    <td>
                                        <div class="small fw-medium">
                                            <?= htmlspecialchars($cat['name']) ?>
                                        </div>
                                        <?php if ($cat['description']): ?>
                                        <div class="text-muted" style="font-size:11px;">
                                            <?= htmlspecialchars($cat['description']) ?>
                                        </div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($cat['default_responder_name']): ?>
                                            <span class="badge bg-success small">
                                                <i class="bi bi-person-check me-1"></i>
                                                <?= htmlspecialchars($cat['default_responder_name']) ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="text-muted small">
                                                <i class="bi bi-person-x me-1"></i>
                                                Walang default
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="d-flex flex-wrap gap-1">
                                            <span class="sla-badge bg-dark text-white">C: <?= $cat['sla_critical'] ?>m</span>
                                            <span class="sla-badge bg-danger text-white">H: <?= $cat['sla_high'] ?>m</span>
                                            <span class="sla-badge bg-warning text-dark">M: <?= $cat['sla_medium'] ?>m</span>
                                            <span class="sla-badge bg-success text-white">L: <?= $cat['sla_low'] ?>m</span>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="badge bg-light text-dark border small">
                                            <?= $cat['incident_count'] ?>
                                        </span>
                                    </td>
                                    <td class="d-flex gap-1">
                                        <button class="btn btn-outline-primary btn-sm"
                                            onclick="editCat(
                                                <?= $cat['id'] ?>,
                                                '<?= addslashes($cat['name']) ?>',
                                                '<?= addslashes($cat['description'] ?? '') ?>',
                                                '<?= $cat['default_responder_id'] ?? '' ?>',
                                                <?= $cat['sla_critical'] ?>,
                                                <?= $cat['sla_high'] ?>,
                                                <?= $cat['sla_medium'] ?>,
                                                <?= $cat['sla_low'] ?>
                                            )">
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                        <form action="" method="POST" class="d-inline">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="id" value="<?= $cat['id'] ?>">
                                            <button type="submit"
                                                    class="btn btn-outline-danger btn-sm"
                                                    onclick="return confirm('I-delete ang category na ito?')">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </form>
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

<!-- ADD MODAL -->
<div class="modal fade" id="addCatModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title fw-semibold">Add Category</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="" method="POST">
                <input type="hidden" name="action" value="add">
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-medium">Category Name <span class="text-danger">*</span></label>
                            <input type="text" name="name" class="form-control" required placeholder="e.g. Fire Incident">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-medium">Default Responder</label>
                            <select name="default_responder_id" class="form-select">
                                <option value="">-- Walang default --</option>
                                <?php foreach ($responders as $r): ?>
                                    <option value="<?= $r['id'] ?>"><?= htmlspecialchars($r['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label small fw-medium">Description</label>
                            <textarea name="description" class="form-control" rows="2"></textarea>
                        </div>
                        <div class="col-12">
                            <label class="form-label small fw-medium">SLA Deadlines <span class="text-muted fw-normal">(in minutes)</span></label>
                            <div class="row g-2">
                                <div class="col-6 col-md-3">
                                    <div class="input-group input-group-sm">
                                        <span class="input-group-text bg-dark text-white" style="font-size:11px;min-width:65px;">Critical</span>
                                        <input type="number" name="sla_critical" class="form-control" value="30" min="1">
                                    </div>
                                </div>
                                <div class="col-6 col-md-3">
                                    <div class="input-group input-group-sm">
                                        <span class="input-group-text bg-danger text-white" style="font-size:11px;min-width:65px;">High</span>
                                        <input type="number" name="sla_high" class="form-control" value="120" min="1">
                                    </div>
                                </div>
                                <div class="col-6 col-md-3">
                                    <div class="input-group input-group-sm">
                                        <span class="input-group-text bg-warning text-dark" style="font-size:11px;min-width:65px;">Medium</span>
                                        <input type="number" name="sla_medium" class="form-control" value="1440" min="1">
                                    </div>
                                </div>
                                <div class="col-6 col-md-3">
                                    <div class="input-group input-group-sm">
                                        <span class="input-group-text bg-success text-white" style="font-size:11px;min-width:65px;">Low</span>
                                        <input type="number" name="sla_low" class="form-control" value="4320" min="1">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg me-1"></i> Add Category</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- EDIT MODAL -->
<div class="modal fade" id="editCatModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title fw-semibold">Edit Category</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="" method="POST">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="id" id="edit-cat-id">
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-medium">Category Name <span class="text-danger">*</span></label>
                            <input type="text" name="name" id="edit-cat-name" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-medium">Default Responder</label>
                            <select name="default_responder_id" id="edit-cat-responder" class="form-select">
                                <option value="">-- Walang default --</option>
                                <?php foreach ($responders as $r): ?>
                                    <option value="<?= $r['id'] ?>"><?= htmlspecialchars($r['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label small fw-medium">Description</label>
                            <textarea name="description" id="edit-cat-desc" class="form-control" rows="2"></textarea>
                        </div>
                        <div class="col-12">
                            <label class="form-label small fw-medium">SLA Deadlines <span class="text-muted fw-normal">(in minutes)</span></label>
                            <div class="row g-2">
                                <div class="col-6 col-md-3">
                                    <div class="input-group input-group-sm">
                                        <span class="input-group-text bg-dark text-white" style="font-size:11px;min-width:65px;">Critical</span>
                                        <input type="number" name="sla_critical" id="edit-sla-critical" class="form-control" min="1">
                                    </div>
                                </div>
                                <div class="col-6 col-md-3">
                                    <div class="input-group input-group-sm">
                                        <span class="input-group-text bg-danger text-white" style="font-size:11px;min-width:65px;">High</span>
                                        <input type="number" name="sla_high" id="edit-sla-high" class="form-control" min="1">
                                    </div>
                                </div>
                                <div class="col-6 col-md-3">
                                    <div class="input-group input-group-sm">
                                        <span class="input-group-text bg-warning text-dark" style="font-size:11px;min-width:65px;">Medium</span>
                                        <input type="number" name="sla_medium" id="edit-sla-medium" class="form-control" min="1">
                                    </div>
                                </div>
                                <div class="col-6 col-md-3">
                                    <div class="input-group input-group-sm">
                                        <span class="input-group-text bg-success text-white" style="font-size:11px;min-width:65px;">Low</span>
                                        <input type="number" name="sla_low" id="edit-sla-low" class="form-control" min="1">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-check2 me-1"></i> Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
function editCat(id, name, desc, responderId, slaCritical, slaHigh, slaMedium, slaLow) {
    document.getElementById('edit-cat-id').value        = id;
    document.getElementById('edit-cat-name').value      = name;
    document.getElementById('edit-cat-desc').value      = desc;
    document.getElementById('edit-cat-responder').value = responderId;
    document.getElementById('edit-sla-critical').value  = slaCritical;
    document.getElementById('edit-sla-high').value      = slaHigh;
    document.getElementById('edit-sla-medium').value    = slaMedium;
    document.getElementById('edit-sla-low').value       = slaLow;
    new bootstrap.Modal(document.getElementById('editCatModal')).show();
}
</script>
</body>
</html>