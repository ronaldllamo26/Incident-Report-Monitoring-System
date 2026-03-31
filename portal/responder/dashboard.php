<?php

require_once __DIR__ . '/../includes/auth.php'; // para sa portal/admin/ at portal/responder/
requireRole('responder');
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../models/Incident.php';

$user  = currentUser();
$model = new Incident();

// Incidents assigned sa responder na to
$incidents = $model->getAll(['assigned_to' => $user['id']]);

$counts = ['pending' => 0, 'in_progress' => 0, 'resolved' => 0, 'closed' => 0];
foreach ($incidents as $i) {
    if (isset($counts[$i['status']])) $counts[$i['status']]++;
}

$statusColor = [
    'pending'     => 'warning',
    'in_progress' => 'primary',
    'resolved'    => 'success',
    'closed'      => 'secondary',
];
$sevColor = [
    'low'      => 'success',
    'medium'   => 'warning',
    'high'     => 'danger',
    'critical' => 'dark',
];
?>
<!DOCTYPE html>
<html lang="fil">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Responder Dashboard — IRMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
</head>
<body class="bg-light">

<nav class="navbar navbar-dark bg-success">
    <div class="container">
        <a class="navbar-brand fw-semibold">
            <i class="bi bi-shield-check me-1"></i> IRMS
            <span class="badge bg-light text-success ms-2" style="font-size:11px;">Responder</span>
        </a>
        <div class="d-flex align-items-center gap-3">
            <span class="text-white small">
                <i class="bi bi-person-circle me-1"></i>
                <?= htmlspecialchars($user['name']) ?>
            </span>
            <a href="/irms/controllers/AuthController.php?action=logout"
               class="btn btn-outline-light btn-sm">
                <i class="bi bi-box-arrow-right me-1"></i> Logout
            </a>
        </div>
    </div>
</nav>

<div class="container py-4">

    <div class="mb-4">
        <h5 class="fw-semibold mb-0">Assigned Incidents</h5>
        <p class="text-muted small">Lahat ng incidents na naka-assign sa iyo.</p>
    </div>

    <!-- Summary cards -->
    <div class="row g-3 mb-4">
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm text-center py-3">
                <div class="fs-3 fw-bold text-warning"><?= $counts['pending'] ?></div>
                <div class="small text-muted">Pending</div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm text-center py-3">
                <div class="fs-3 fw-bold text-primary"><?= $counts['in_progress'] ?></div>
                <div class="small text-muted">In Progress</div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm text-center py-3">
                <div class="fs-3 fw-bold text-success"><?= $counts['resolved'] ?></div>
                <div class="small text-muted">Resolved</div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm text-center py-3">
                <div class="fs-3 fw-bold text-secondary"><?= $counts['closed'] ?></div>
                <div class="small text-muted">Closed</div>
            </div>
        </div>
    </div>

    <!-- Filter tabs -->
    <div class="d-flex gap-2 mb-3 flex-wrap">
        <button class="btn btn-sm btn-primary filter-btn active" data-filter="all">
            All <span class="badge bg-white text-primary ms-1"><?= count($incidents) ?></span>
        </button>
        <button class="btn btn-sm btn-outline-warning filter-btn" data-filter="pending">
            Pending <span class="badge bg-warning text-dark ms-1"><?= $counts['pending'] ?></span>
        </button>
        <button class="btn btn-sm btn-outline-primary filter-btn" data-filter="in_progress">
            In Progress <span class="badge bg-primary ms-1"><?= $counts['in_progress'] ?></span>
        </button>
        <button class="btn btn-sm btn-outline-success filter-btn" data-filter="resolved">
            Resolved <span class="badge bg-success ms-1"><?= $counts['resolved'] ?></span>
        </button>
    </div>

    <!-- Incidents list -->
    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
            <?php if (empty($incidents)): ?>
                <div class="text-center py-5 text-muted">
                    <i class="bi bi-inbox fs-1 d-block mb-2"></i>
                    Wala kang assigned na incident ngayon.
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0" id="incidents-table">
                        <thead class="table-light">
                            <tr>
                                <th class="ps-3 small">#</th>
                                <th class="small">Incident</th>
                                <th class="small">Category</th>
                                <th class="small">Severity</th>
                                <th class="small">Status</th>
                                <th class="small">Reported</th>
                                <th class="small">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($incidents as $i => $inc): ?>
                            <tr data-status="<?= $inc['status'] ?>">
                                <td class="ps-3 text-muted small"><?= $inc['id'] ?></td>
                                <td>
                                    <div class="small fw-medium">
                                        <?= htmlspecialchars($inc['title']) ?>
                                    </div>
                                    <div class="text-muted" style="font-size:11px;">
                                        <i class="bi bi-geo-alt"></i>
                                        <?= htmlspecialchars(substr($inc['location'], 0, 40)) ?>...
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
                                        <?= ucwords(str_replace('_', ' ', $inc['status'])) ?>
                                    </span>
                                </td>
                                <td class="small text-muted">
                                    <?= date('M d, Y', strtotime($inc['reported_at'])) ?>
                                </td>
                                <td>
                                    <a href="/irms/portal/responder/view_incident.php?id=<?= $inc['id'] ?>"
                                       class="btn btn-outline-success btn-sm">
                                        <i class="bi bi-eye"></i> View
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

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Filter tabs
document.querySelectorAll('.filter-btn').forEach(function(btn) {
    btn.addEventListener('click', function() {
        document.querySelectorAll('.filter-btn').forEach(b => {
            b.classList.remove('active', 'btn-primary', 'btn-warning',
                               'btn-success', 'btn-secondary');
            b.classList.add('btn-outline-' + (b.dataset.filter === 'all' ? 'primary' :
                            b.dataset.filter === 'pending' ? 'warning' :
                            b.dataset.filter === 'in_progress' ? 'primary' : 'success'));
        });
        this.classList.add('active');

        var filter = this.dataset.filter;
        document.querySelectorAll('#incidents-table tbody tr').forEach(function(row) {
            row.style.display = (filter === 'all' || row.dataset.status === filter) ? '' : 'none';
        });
    });
});
</script>
</body>
</html>