<?php
require_once __DIR__ . '/../../includes/auth.php'; // para sa portal/admin/ at portal/responder/
requireRole('admin');
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../models/Incident.php';

$model = new Incident();

// Filters
$filters = [];
if (!empty($_GET['status']))      $filters['status']      = $_GET['status'];
if (!empty($_GET['category_id'])) $filters['category_id'] = $_GET['category_id'];
if (!empty($_GET['severity']))    $filters['severity']    = $_GET['severity'];

$incidents   = $model->getAll($filters);
$categories  = $pdo->query("SELECT * FROM categories ORDER BY name")->fetchAll();
$responders  = $pdo->query("SELECT id, name FROM users WHERE role = 'responder' AND is_active = 1")->fetchAll();

$statusColor = [
    'pending'     => 'warning',
    'in_progress' => 'primary',
    'resolved'    => 'success',
    'closed'      => 'secondary',
];
$sevColor = [
    'low' => 'success', 'medium' => 'warning',
    'high' => 'danger', 'critical' => 'dark',
];

$success = $_GET['success'] ?? '';
$error   = $_GET['error']   ?? '';
?>
<!DOCTYPE html>
<html lang="fil">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Incidents — IRMS Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <?php include __DIR__ . '/../../includes/sidebar_style.php'; ?>
</head>
<body class="bg-light">
<div class="d-flex">

    <?php include __DIR__ . '/../../includes/sidebar_admin.php'; ?>

    <!-- Main -->
    <div class="main-content">
        <div class="top-nav d-flex justify-content-between align-items-center">
            <div class="d-flex align-items-center gap-3">
                <button class="hamburger btn btn-sm btn-outline-secondary"
                        style="display:none;align-items:center;justify-content:center;
                               width:36px;height:36px;padding:0;"
                        onclick="toggleSidebar()">
                    <i class="bi bi-list fs-5"></i>
                </button>
                <h6 class="fw-semibold mb-0">Incidents</h6>
            </div>
            <span class="text-muted small"><?= date('F d, Y') ?></span>
        </div>

        <div class="p-4">

            <?php if ($success): ?>
                <div class="alert alert-success alert-dismissible fade show py-2 small">
                    <i class="bi bi-check-circle me-1"></i><?= htmlspecialchars($success) ?>
                    <button type="button" class="btn-close btn-sm" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <!-- Filters -->
            <div class="card border-0 shadow-sm mb-3">
                <div class="card-body p-3">
                    <form method="GET" class="row g-2 align-items-end">
                        <div class="col-md-3">
                            <label class="form-label small fw-medium mb-1">Status</label>
                            <select name="status" class="form-select form-select-sm">
                                <option value="">All Status</option>
                                <?php foreach (['pending','in_progress','resolved','closed'] as $s): ?>
                                    <option value="<?= $s ?>"
                                        <?= ($_GET['status'] ?? '') === $s ? 'selected' : '' ?>>
                                        <?= ucwords(str_replace('_',' ',$s)) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small fw-medium mb-1">Category</label>
                            <select name="category_id" class="form-select form-select-sm">
                                <option value="">All Categories</option>
                                <?php foreach ($categories as $c): ?>
                                    <option value="<?= $c['id'] ?>"
                                        <?= ($_GET['category_id'] ?? '') == $c['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($c['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small fw-medium mb-1">Severity</label>
                            <select name="severity" class="form-select form-select-sm">
                                <option value="">All Severity</option>
                                <?php foreach (['low','medium','high','critical'] as $s): ?>
                                    <option value="<?= $s ?>"
                                        <?= ($_GET['severity'] ?? '') === $s ? 'selected' : '' ?>>
                                        <?= ucfirst($s) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3 d-flex gap-2">
                            <button type="submit" class="btn btn-primary btn-sm flex-fill">
                                <i class="bi bi-funnel me-1"></i> Filter
                            </button>
                            <a href="/irms/portal/admin/incidents.php"
                               class="btn btn-outline-secondary btn-sm">
                                <i class="bi bi-x"></i>
                            </a>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Table -->
            <div class="card border-0 shadow-sm">
                <div class="card-body p-0">
                    <?php if (empty($incidents)): ?>
                        <div class="text-center py-5 text-muted">
                            <i class="bi bi-inbox fs-1 d-block mb-2"></i>
                            Walang incidents na nahanap.
                        </div>
                    <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th class="ps-3 small">#</th>
                                    <th class="small">Incident</th>
                                    <th class="small">Category</th>
                                    <th class="small">Severity</th>
                                    <th class="small">Status</th>
                                    <th class="small">Assigned To</th>
                                    <th class="small">Date</th>
                                    <th class="small">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($incidents as $inc): ?>
                                <tr>
                                    <td class="ps-3 text-muted small"><?= $inc['id'] ?></td>
                                    <td>
                                        <div class="small fw-medium">
                                            <?= htmlspecialchars($inc['title']) ?>
                                        </div>
                                        <div class="text-muted" style="font-size:11px;">
                                            <?= htmlspecialchars($inc['reporter_name']) ?>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="badge bg-light text-dark border small">
                                            <?= htmlspecialchars($inc['category_name']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge bg-<?= $sevColor[$inc['severity']] ?> small">
                                            <?= ucfirst($inc['severity']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge bg-<?= $statusColor[$inc['status']] ?> small">
                                            <?= ucwords(str_replace('_',' ',$inc['status'])) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <!-- Assign responder dropdown -->
                                        <form action="/irms/ajax/assign_responder.php"
                                              method="POST" class="d-flex gap-1">
                                            <input type="hidden" name="incident_id"
                                                   value="<?= $inc['id'] ?>">
                                            <select name="responder_id"
                                                    class="form-select form-select-sm"
                                                    style="min-width:130px;font-size:12px;">
                                                <option value="">Unassigned</option>
                                                <?php foreach ($responders as $r): ?>
                                                    <option value="<?= $r['id'] ?>"
                                                        <?= $inc['assigned_to'] == $r['id'] ? 'selected' : '' ?>>
                                                        <?= htmlspecialchars($r['name']) ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                            <button type="submit"
                                                    class="btn btn-outline-secondary btn-sm"
                                                    title="Assign">
                                                <i class="bi bi-check2"></i>
                                            </button>
                                        </form>
                                    </td>
                                    <td class="small text-muted">
                                        <?= date('M d, Y', strtotime($inc['reported_at'])) ?>
                                    </td>
                                    <td>
                                        <a href="/irms/portal/admin/view_incident.php?id=<?= $inc['id'] ?>"
                                           class="btn btn-outline-primary btn-sm">
                                            <i class="bi bi-eye"></i>
                                        </a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>