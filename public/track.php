<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../models/Incident.php';

$tracking = trim($_GET['tracking'] ?? $_POST['tracking'] ?? '');
$incident = null;
$logs     = null;
$model    = new Incident();

if ($tracking) {
    $stmt = $pdo->prepare("
        SELECT i.*, c.name AS category_name
        FROM incidents i
        JOIN categories c ON i.category_id = c.id
        WHERE i.tracking_number = ?
    ");
    $stmt->execute([$tracking]);
    $incident = $stmt->fetch();

    if ($incident) {
        $logs = $model->getStatusLogs($incident['id']);
    }
}

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
    <title>I-track ang Report — IRMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body { background: #f1f5f9; }
        .brand-bar { background: #1e293b; padding: 14px 0; }
        .timeline { position: relative; padding-left: 24px; }
        .timeline::before { content:''; position:absolute; left:7px; top:0;
                            bottom:0; width:1px; background:#dee2e6; }
        .tl-item { position:relative; margin-bottom:16px; }
        .tl-dot { position:absolute; left:-20px; top:4px; width:10px; height:10px;
                  border-radius:50%; background:#0d6efd; border:2px solid #fff;
                  box-shadow:0 0 0 1px #0d6efd; }
        .tl-dot.done { background:#198754; box-shadow:0 0 0 1px #198754; }
    </style>
</head>
<body>

<div class="brand-bar">
    <div class="container d-flex justify-content-between align-items-center">
        <div class="text-white fw-semibold">
            <i class="bi bi-shield-check me-2"></i>IRMS
        </div>
        <div class="d-flex gap-2">
            <a href="/irms/citizen/anonymous_report.php"
               class="btn btn-outline-light btn-sm">
                <i class="bi bi-plus-lg me-1"></i> Mag-report
            </a>
            <a href="/irms/citizen/login.php"
               class="btn btn-light btn-sm">
                <i class="bi bi-box-arrow-in-right me-1"></i> Login
            </a>
        </div>
    </div>
</div>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-7">

            <h5 class="fw-semibold mb-1">I-track ang iyong Report</h5>
            <p class="text-muted small mb-4">
                Ilagay ang tracking number na natanggap mo pagkatapos mag-submit.
            </p>

            <!-- Search form -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body p-3">
                    <form method="GET" class="d-flex gap-2">
                        <input type="text" name="tracking" class="form-control"
                               placeholder="IRMS-YYYYMMDD-XXXXX"
                               value="<?= htmlspecialchars($tracking) ?>"
                               style="font-family: monospace; letter-spacing: 2px;"
                               required>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-search me-1"></i> Hanapin
                        </button>
                    </form>
                </div>
            </div>

            <!-- Results -->
            <?php if ($tracking && !$incident): ?>
                <div class="alert alert-warning py-3">
                    <i class="bi bi-exclamation-triangle me-2"></i>
                    Walang nahanap na report para sa tracking number na
                    <strong><?= htmlspecialchars($tracking) ?></strong>.
                    Siguraduhing tama ang tracking number.
                </div>

            <?php elseif ($incident): ?>

                <!-- Incident summary -->
                <div class="card border-0 shadow-sm mb-3">
                    <div class="card-body p-4">
                        <div class="d-flex justify-content-between align-items-start mb-3">
                            <div>
                                <p class="text-muted small mb-1">
                                    <?= htmlspecialchars($incident['tracking_number']) ?>
                                </p>
                                <h5 class="fw-semibold mb-0">
                                    <?= htmlspecialchars($incident['title']) ?>
                                </h5>
                            </div>
                            <span class="badge bg-<?= $statusColor[$incident['status']] ?> fs-6">
                                <?= ucwords(str_replace('_',' ',$incident['status'])) ?>
                            </span>
                        </div>

                        <div class="d-flex flex-wrap gap-2 mb-3">
                            <span class="badge bg-light text-dark border">
                                <i class="bi bi-tag me-1"></i>
                                <?= htmlspecialchars($incident['category_name']) ?>
                            </span>
                            <span class="badge bg-<?= $sevColor[$incident['severity']] ?>">
                                <?= ucfirst($incident['severity']) ?> severity
                            </span>
                            <span class="badge bg-light text-dark border">
                                <i class="bi bi-calendar me-1"></i>
                                <?= date('M d, Y g:i A', strtotime($incident['reported_at'])) ?>
                            </span>
                        </div>

                        <p class="small text-muted mb-2">
                            <?= nl2br(htmlspecialchars($incident['description'])) ?>
                        </p>
                        <p class="small text-muted mb-0">
                            <i class="bi bi-geo-alt me-1"></i>
                            <?= htmlspecialchars($incident['location']) ?>
                        </p>
                    </div>
                </div>

                <!-- SLA info -->
                <?php if ($incident['sla_deadline'] &&
                          !in_array($incident['status'], ['resolved','closed'])): ?>
                    <?php
                    $minsLeft = round((strtotime($incident['sla_deadline']) - time()) / 60);
                    $breached = $minsLeft <= 0;
                    ?>
                    <div class="alert alert-<?= $breached ? 'danger' : 'info' ?> py-2 small mb-3">
                        <i class="bi bi-clock me-1"></i>
                        <strong>Response Deadline:</strong>
                        <?= date('M d, Y g:i A', strtotime($incident['sla_deadline'])) ?>
                        <?php if ($breached): ?>
                            <span class="ms-2 badge bg-danger">SLA Breached</span>
                        <?php else: ?>
                            <span class="ms-2 text-primary">
                                (<?= $minsLeft < 60
                                    ? "{$minsLeft} mins left"
                                    : round($minsLeft/60,1) . ' hrs left' ?>)
                            </span>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <!-- Status timeline -->
                <div class="card border-0 shadow-sm">
                    <div class="card-body p-3">
                        <p class="small fw-medium mb-3">
                            <i class="bi bi-clock-history me-1"></i> Status History
                        </p>
                        <?php if (empty($logs)): ?>
                            <p class="text-muted small mb-0">Wala pang update.</p>
                        <?php else: ?>
                            <div class="timeline">
                                <?php foreach ($logs as $log): ?>
                                    <div class="tl-item">
                                        <div class="tl-dot <?= $log['new_status'] === 'resolved'
                                            ? 'done' : '' ?>"></div>
                                        <div class="small fw-medium">
                                            <?= ucwords(str_replace('_',' ',
                                                $log['new_status'])) ?>
                                        </div>
                                        <div class="text-muted" style="font-size:11px;">
                                            <?= date('M d, Y g:i A',
                                                strtotime($log['changed_at'])) ?>
                                        </div>
                                        <?php if ($log['remarks']): ?>
                                            <div class="text-muted small mt-1 fst-italic">
                                                "<?= htmlspecialchars($log['remarks']) ?>"
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

            <?php endif; ?>

        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>