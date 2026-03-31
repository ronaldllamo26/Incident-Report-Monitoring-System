<?php

require_once __DIR__ . '/../../includes/auth.php'; // para sa portal/admin/ at portal/responder/
requireRole('admin');
require_once __DIR__ . '/../../config/db.php';

$success = $_GET['success'] ?? '';

// Filters
$dateFrom   = $_GET['date_from']   ?? date('Y-m-01');
$dateTo     = $_GET['date_to']     ?? date('Y-m-d');
$statusF    = $_GET['status']      ?? '';
$categoryF  = $_GET['category_id'] ?? '';
$severityF  = $_GET['severity']    ?? '';

// Build query
$where  = ["i.reported_at BETWEEN ? AND ?"];
$params = [$dateFrom . ' 00:00:00', $dateTo . ' 23:59:59'];

if ($statusF)   { $where[] = 'i.status = ?';      $params[] = $statusF; }
if ($categoryF) { $where[] = 'i.category_id = ?'; $params[] = $categoryF; }
if ($severityF) { $where[] = 'i.severity = ?';    $params[] = $severityF; }

$sql = "
    SELECT i.*, c.name AS category_name,
           u.name AS reporter_name,
           a.name AS responder_name
    FROM incidents i
    JOIN categories c ON i.category_id = c.id
    JOIN users u ON i.reporter_id = u.id
    LEFT JOIN users a ON i.assigned_to = a.id
    WHERE " . implode(' AND ', $where) . "
    ORDER BY i.reported_at DESC
";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$incidents = $stmt->fetchAll();

// Summary counts
$counts = ['pending' => 0, 'in_progress' => 0, 'resolved' => 0, 'closed' => 0];
foreach ($incidents as $i) {
    if (isset($counts[$i['status']])) $counts[$i['status']]++;
}

$categories = $pdo->query("SELECT * FROM categories ORDER BY name")->fetchAll();

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
?>
<!DOCTYPE html>
<html lang="fil">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Reports — IRMS Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        .sidebar { width: 220px; min-height: 100vh; background: #1e293b; }
        .sidebar .nav-link { color: #94a3b8; font-size: 14px; padding: 10px 20px;
                             border-radius: 6px; margin: 2px 8px; }
        .sidebar .nav-link:hover,
        .sidebar .nav-link.active { background: #334155; color: #fff; }
        .main-content { flex: 1; overflow-y: auto; }
        .top-nav { background: #fff; border-bottom: 1px solid #e2e8f0; padding: 12px 24px; }
    </style>
</head>
<body class="bg-light">
<div class="d-flex">

    <!-- Sidebar -->
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
    <a href="/irms/portal/admin/users.php" class="nav-link ">
        <i class="bi bi-people me-2"></i> Users
    </a>
    <a href="/irms/portal/admin/categories.php" class="nav-link">
        <i class="bi bi-tags me-2"></i> Categories
    </a>
    <a href="/irms/portal/admin/reports.php" class="nav-link active">
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

    <!-- Main -->
    <div class="main-content">
        <div class="top-nav d-flex justify-content-between align-items-center">
            <h6 class="fw-semibold mb-0">Reports & Export</h6>
        </div>

        <div class="p-4">

            <!-- Filter form -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body p-3">
                    <form method="GET" class="row g-2 align-items-end">
                        <div class="col-md-2">
                            <label class="form-label small fw-medium mb-1">Date From</label>
                            <input type="date" name="date_from" class="form-control form-control-sm"
                                   value="<?= $dateFrom ?>">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label small fw-medium mb-1">Date To</label>
                            <input type="date" name="date_to" class="form-control form-control-sm"
                                   value="<?= $dateTo ?>">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label small fw-medium mb-1">Status</label>
                            <select name="status" class="form-select form-select-sm">
                                <option value="">All</option>
                                <?php foreach (['pending','in_progress','resolved','closed'] as $s): ?>
                                    <option value="<?= $s ?>"
                                        <?= $statusF === $s ? 'selected' : '' ?>>
                                        <?= ucwords(str_replace('_',' ',$s)) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label small fw-medium mb-1">Category</label>
                            <select name="category_id" class="form-select form-select-sm">
                                <option value="">All</option>
                                <?php foreach ($categories as $c): ?>
                                    <option value="<?= $c['id'] ?>"
                                        <?= $categoryF == $c['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($c['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label small fw-medium mb-1">Severity</label>
                            <select name="severity" class="form-select form-select-sm">
                                <option value="">All</option>
                                <?php foreach (['low','medium','high','critical'] as $s): ?>
                                    <option value="<?= $s ?>"
                                        <?= $severityF === $s ? 'selected' : '' ?>>
                                        <?= ucfirst($s) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2 d-flex gap-2">
                            <button type="submit" class="btn btn-primary btn-sm flex-fill">
                                <i class="bi bi-funnel me-1"></i> Filter
                            </button>
                            <a href="/irms/portal/admin/reports.php"
                               class="btn btn-outline-secondary btn-sm">
                                <i class="bi bi-x"></i>
                            </a>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Summary cards -->
            <div class="row g-3 mb-4">
                <div class="col-6 col-md-3">
                    <div class="card border-0 shadow-sm text-center py-3">
                        <div class="fs-4 fw-bold"><?= count($incidents) ?></div>
                        <div class="small text-muted">Total</div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="card border-0 shadow-sm text-center py-3">
                        <div class="fs-4 fw-bold text-warning"><?= $counts['pending'] ?></div>
                        <div class="small text-muted">Pending</div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="card border-0 shadow-sm text-center py-3">
                        <div class="fs-4 fw-bold text-primary"><?= $counts['in_progress'] ?></div>
                        <div class="small text-muted">In Progress</div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="card border-0 shadow-sm text-center py-3">
                        <div class="fs-4 fw-bold text-success"><?= $counts['resolved'] ?></div>
                        <div class="small text-muted">Resolved</div>
                    </div>
                </div>
            </div>

            <!-- Export button -->
            <div class="d-flex justify-content-between align-items-center mb-3">
                <p class="small fw-medium mb-0">
                    Showing <strong><?= count($incidents) ?></strong> incidents
                </p>
                <?php if (!empty($incidents)): ?>
                <a href="/irms/controllers/ReportController.php?action=export_pdf&<?= http_build_query([
                    'date_from'   => $dateFrom,
                    'date_to'     => $dateTo,
                    'status'      => $statusF,
                    'category_id' => $categoryF,
                    'severity'    => $severityF,
                ]) ?>"
                   class="btn btn-danger btn-sm" target="_blank">
                    <i class="bi bi-file-earmark-pdf me-1"></i> Export PDF
                </a>
                <?php endif; ?>
            </div>

            <!-- Table preview -->
            <div class="card border-0 shadow-sm">
                <div class="card-body p-0">
                    <?php if (empty($incidents)): ?>
                        <div class="text-center py-5 text-muted">
                            <i class="bi bi-inbox fs-1 d-block mb-2"></i>
                            Walang incidents sa napiling filters.
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
                                    <th class="small">Reporter</th>
                                    <th class="small">Responder</th>
                                    <th class="small">Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($incidents as $inc): ?>
                                <tr>
                                    <td class="ps-3 text-muted small"><?= $inc['id'] ?></td>
                                    <td class="small fw-medium">
                                        <?= htmlspecialchars($inc['title']) ?>
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
                                    <td class="small">
                                        <?= htmlspecialchars($inc['reporter_name']) ?>
                                    </td>
                                    <td class="small text-muted">
                                        <?= htmlspecialchars($inc['responder_name'] ?? '—') ?>
                                    </td>
                                    <td class="small text-muted">
                                        <?= date('M d, Y', strtotime($inc['reported_at'])) ?>
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