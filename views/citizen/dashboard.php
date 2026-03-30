<?php
require_once __DIR__ . '/../../includes/auth.php';
requireRole('citizen');

require_once __DIR__ . '/../../config/db.php';

$user = currentUser();

// Kunin ang reports ng citizen
$stmt = $pdo->prepare("
    SELECT i.*, c.name AS category_name 
    FROM incidents i
    JOIN categories c ON i.category_id = c.id
    WHERE i.reporter_id = ?
    ORDER BY i.reported_at DESC
");
$stmt->execute([$user['id']]);
$reports = $stmt->fetchAll();

// Counts per status
$counts = ['pending' => 0, 'in_progress' => 0, 'resolved' => 0, 'closed' => 0];
foreach ($reports as $r) {
    if (isset($counts[$r['status']])) $counts[$r['status']]++;
}
?>
<!DOCTYPE html>
<html lang="fil">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>My Dashboard — IRMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
</head>
<body class="bg-light">

<!-- Navbar -->
<nav class="navbar navbar-expand-lg navbar-dark bg-primary">
    <div class="container">
        <a class="navbar-brand fw-semibold" href="#">
            <i class="bi bi-shield-check me-1"></i> IRMS
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

    <!-- Welcome -->
    <div class="mb-4">
        <h5 class="fw-semibold mb-0">Magandang araw, <?= htmlspecialchars(explode(' ', $user['name'])[0]) ?>!</h5>
        <p class="text-muted small mb-0">Dito makikita mo ang lahat ng iyong mga report.</p>
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

    <!-- New report button -->
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h6 class="fw-semibold mb-0">Aking mga Report</h6>
        <a href="/irms/views/citizen/report.php" class="btn btn-primary btn-sm">
            <i class="bi bi-plus-lg me-1"></i> Mag-report ng Insidente
        </a>
    </div>

    <!-- Reports table -->
    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
            <?php if (empty($reports)): ?>
                <div class="text-center py-5 text-muted">
                    <i class="bi bi-inbox fs-1 d-block mb-2"></i>
                    Wala ka pang naka-submit na report.
                    <div class="mt-2">
                        <a href="/irms/views/citizen/report.php" class="btn btn-primary btn-sm">
                            Mag-report ngayon
                        </a>
                    </div>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th class="ps-3 small">#</th>
                                <th class="small">Title</th>
                                <th class="small">Category</th>
                                <th class="small">Severity</th>
                                <th class="small">Status</th>
                                <th class="small">Date</th>
                                <th class="small">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($reports as $i => $r): ?>
                            <tr>
                                <td class="ps-3 text-muted small"><?= $i + 1 ?></td>
                                <td>
                                    <span class="fw-medium small">
                                        <?= htmlspecialchars($r['title']) ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="badge bg-light text-dark border small">
                                        <?= htmlspecialchars($r['category_name']) ?>
                                    </span>
                                </td>
                                <td>
                                    <?php
                                    $sev = ['low' => 'success', 'medium' => 'warning',
                                            'high' => 'danger', 'critical' => 'dark'];
                                    $sc  = $sev[$r['severity']] ?? 'secondary';
                                    ?>
                                    <span class="badge bg-<?= $sc ?> small">
                                        <?= ucfirst($r['severity']) ?>
                                    </span>
                                </td>
                                <td>
                                    <?php
                                    $st = ['pending' => 'warning', 'in_progress' => 'primary',
                                           'resolved' => 'success', 'closed' => 'secondary'];
                                    $sc = $st[$r['status']] ?? 'secondary';
                                    $label = str_replace('_', ' ', ucfirst($r['status']));
                                    ?>
                                    <span class="badge bg-<?= $sc ?> small"><?= $label ?></span>
                                </td>
                                <td class="small text-muted">
                                    <?= date('M d, Y', strtotime($r['reported_at'])) ?>
                                </td>
                                <td>
                                    <a href="/irms/views/citizen/view_report.php?id=<?= $r['id'] ?>"
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

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>