<?php
require_once __DIR__ . '/../../includes/auth.php';
requireRole('admin');
require_once __DIR__ . '/../../config/db.php';

$success = $_GET['success'] ?? '';
$error   = $_GET['error']   ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $name = trim($_POST['name'] ?? '');
        $desc = trim($_POST['description'] ?? '');
        if ($name) {
            $pdo->prepare("INSERT INTO categories (name, description) VALUES (?, ?)")
                ->execute([$name, $desc]);
            header('Location: /irms/views/admin/categories.php?success=' .
                   urlencode('Na-add ang category.'));
            exit;
        }
    }

    if ($action === 'edit') {
        $id   = (int)($_POST['id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $desc = trim($_POST['description'] ?? '');
        if ($id && $name) {
            $pdo->prepare("UPDATE categories SET name = ?, description = ? WHERE id = ?")
                ->execute([$name, $desc, $id]);
            header('Location: /irms/views/admin/categories.php?success=' .
                   urlencode('Na-update ang category.'));
            exit;
        }
    }

    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        // Check kung may incidents
        $check = $pdo->prepare("SELECT COUNT(*) FROM incidents WHERE category_id = ?");
        $check->execute([$id]);
        if ($check->fetchColumn() > 0) {
            header('Location: /irms/views/admin/categories.php?error=' .
                   urlencode('Hindi pwedeng i-delete — may incidents na gumagamit nito.'));
            exit;
        }
        $pdo->prepare("DELETE FROM categories WHERE id = ?")->execute([$id]);
        header('Location: /irms/views/admin/categories.php?success=' .
               urlencode('Na-delete ang category.'));
        exit;
    }
}

$categories = $pdo->query("
    SELECT c.*, COUNT(i.id) AS incident_count
    FROM categories c
    LEFT JOIN incidents i ON i.category_id = c.id
    GROUP BY c.id
    ORDER BY c.name
")->fetchAll();
?>
<!DOCTYPE html>
<html lang="fil">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Categories — IRMS Admin</title>
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
            <a href="/irms/views/admin/dashboard.php" class="nav-link">
                <i class="bi bi-speedometer2 me-2"></i> Dashboard
            </a>
            <a href="/irms/views/admin/incidents.php" class="nav-link">
                <i class="bi bi-exclamation-triangle me-2"></i> Incidents
            </a>
            <a href="/irms/views/admin/users.php" class="nav-link">
                <i class="bi bi-people me-2"></i> Users
            </a>
            <a href="/irms/views/admin/categories.php" class="nav-link active">
                <i class="bi bi-tags me-2"></i> Categories
            </a>
            <a href="/irms/views/admin/reports.php" class="nav-link">
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
            <h6 class="fw-semibold mb-0">Categories</h6>
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

            <div class="card border-0 shadow-sm">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th class="ps-3 small">#</th>
                                    <th class="small">Name</th>
                                    <th class="small">Description</th>
                                    <th class="small">Incidents</th>
                                    <th class="small">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($categories as $cat): ?>
                                <tr>
                                    <td class="ps-3 text-muted small"><?= $cat['id'] ?></td>
                                    <td class="small fw-medium">
                                        <?= htmlspecialchars($cat['name']) ?>
                                    </td>
                                    <td class="small text-muted">
                                        <?= htmlspecialchars($cat['description'] ?? '—') ?>
                                    </td>
                                    <td>
                                        <span class="badge bg-light text-dark border small">
                                            <?= $cat['incident_count'] ?>
                                        </span>
                                    </td>
                                    <td class="d-flex gap-1">
                                        <!-- Edit button -->
                                        <button class="btn btn-outline-primary btn-sm"
                                                onclick="editCat(<?= $cat['id'] ?>, '<?= addslashes($cat['name']) ?>', '<?= addslashes($cat['description'] ?? '') ?>')">
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                        <!-- Delete button -->
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

<!-- Add Category Modal -->
<div class="modal fade" id="addCatModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title fw-semibold">Add Category</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="" method="POST">
                <input type="hidden" name="action" value="add">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label small fw-medium">
                            Category Name <span class="text-danger">*</span>
                        </label>
                        <input type="text" name="name" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-medium">Description</label>
                        <textarea name="description" class="form-control" rows="2"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary btn-sm"
                            data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary btn-sm">
                        <i class="bi bi-plus-lg me-1"></i> Add
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Category Modal -->
<div class="modal fade" id="editCatModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title fw-semibold">Edit Category</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="" method="POST">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="id" id="edit-cat-id">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label small fw-medium">
                            Category Name <span class="text-danger">*</span>
                        </label>
                        <input type="text" name="name" id="edit-cat-name"
                               class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-medium">Description</label>
                        <textarea name="description" id="edit-cat-desc"
                                  class="form-control" rows="2"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary btn-sm"
                            data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary btn-sm">
                        <i class="bi bi-check2 me-1"></i> Save Changes
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
function editCat(id, name, desc) {
    document.getElementById('edit-cat-id').value   = id;
    document.getElementById('edit-cat-name').value = name;
    document.getElementById('edit-cat-desc').value = desc;
    new bootstrap.Modal(document.getElementById('editCatModal')).show();
}
</script>
</body>
</html>