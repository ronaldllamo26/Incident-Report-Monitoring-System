<?php
require_once __DIR__ . '/../../includes/auth.php';
requireRole('citizen');
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../models/Incident.php';

$user     = currentUser();
$id       = (int)($_GET['id'] ?? 0);
$model    = new Incident();
$incident = $model->getById($id);

// Siguraduhing report ng citizen to
if (!$incident || $incident['reporter_id'] != $user['id']) {
    header('Location: /irms/views/citizen/dashboard.php');
    exit;
}

$attachments = $model->getAttachments($id);
$logs        = $model->getStatusLogs($id);
$responses   = $model->getResponses($id);

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
    <title>Report #<?= $id ?> — IRMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.min.css" rel="stylesheet">
    <style>
        #map { height: 220px; border-radius: 8px; border: 1px solid #dee2e6; }
        .timeline { position: relative; padding-left: 24px; }
        .timeline::before { content: ''; position: absolute; left: 7px; top: 0; bottom: 0;
                             width: 1px; background: #dee2e6; }
        .tl-item { position: relative; margin-bottom: 16px; }
        .tl-dot { position: absolute; left: -20px; top: 4px; width: 10px; height: 10px;
                  border-radius: 50%; background: #0d6efd; border: 2px solid #fff;
                  box-shadow: 0 0 0 1px #0d6efd; }
        .tl-dot.done { background: #198754; box-shadow: 0 0 0 1px #198754; }
        .chat-bubble { background: #f8f9fa; border-radius: 0 12px 12px 12px;
                       padding: 10px 14px; border: 0.5px solid #dee2e6; }
        .attach-img { width: 90px; height: 90px; object-fit: cover; border-radius: 8px;
                      border: 1px solid #dee2e6; cursor: pointer; transition: opacity 0.15s; }
        .attach-img:hover { opacity: 0.85; }
    </style>
</head>
<body class="bg-light">

<nav class="navbar navbar-dark bg-primary">
    <div class="container">
        <a class="navbar-brand fw-semibold" href="/irms/views/citizen/dashboard.php">
            <i class="bi bi-shield-check me-1"></i> IRMS
        </a>
        <a href="/irms/views/citizen/dashboard.php" class="btn btn-outline-light btn-sm">
            <i class="bi bi-arrow-left me-1"></i> Back
        </a>
    </div>
</nav>

<div class="container py-4">
    <div class="row g-4">

        <!-- LEFT: Incident details -->
        <div class="col-lg-8">

            <!-- Header card -->
            <div class="card border-0 shadow-sm mb-3">
                <div class="card-body p-4">
                    <div class="d-flex justify-content-between align-items-start gap-2 mb-3">
                        <div>
                            <p class="text-muted small mb-1">Report #<?= $id ?></p>
                            <h5 class="fw-semibold mb-0">
                                <?= htmlspecialchars($incident['title']) ?>
                            </h5>
                        </div>
                        <span class="badge bg-<?= $statusColor[$incident['status']] ?> fs-6">
                            <?= ucwords(str_replace('_', ' ', $incident['status'])) ?>
                        </span>
                    </div>

                    <div class="d-flex flex-wrap gap-2 mb-3">
                        <span class="badge bg-light text-dark border">
                            <i class="bi bi-tag me-1"></i><?= htmlspecialchars($incident['category_name']) ?>
                        </span>
                        <span class="badge bg-<?= $sevColor[$incident['severity']] ?>">
                            <?= ucfirst($incident['severity']) ?> severity
                        </span>
                        <span class="badge bg-light text-dark border">
                            <i class="bi bi-calendar me-1"></i>
                            <?= date('M d, Y g:i A', strtotime($incident['reported_at'])) ?>
                        </span>
                    </div>

                    <p class="mb-3"><?= nl2br(htmlspecialchars($incident['description'])) ?></p>

                    <div class="text-muted small">
                        <i class="bi bi-geo-alt me-1"></i>
                        <?= htmlspecialchars($incident['location']) ?>
                    </div>
                </div>
            </div>

            <!-- Map -->
            <?php if ($incident['latitude'] && $incident['longitude']): ?>
            <div class="card border-0 shadow-sm mb-3">
                <div class="card-body p-3">
                    <p class="small fw-medium mb-2">
                        <i class="bi bi-map me-1"></i> Lokasyon sa Mapa
                    </p>
                    <div id="map"></div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Attachments -->
            <?php if ($attachments): ?>
            <div class="card border-0 shadow-sm mb-3">
                <div class="card-body p-3">
                    <p class="small fw-medium mb-2">
                        <i class="bi bi-images me-1"></i> Mga Larawan
                        <span class="text-muted fw-normal">(<?= count($attachments) ?>)</span>
                    </p>
                    <div class="d-flex flex-wrap gap-2">
                        <?php foreach ($attachments as $a): ?>
                            <a href="/irms/<?= htmlspecialchars($a['file_path']) ?>" target="_blank">
                                <img src="/irms/<?= htmlspecialchars($a['file_path']) ?>"
                                     class="attach-img" alt="attachment">
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Responses from responder -->
            <div class="card border-0 shadow-sm">
                <div class="card-body p-3">
                    <p class="small fw-medium mb-3">
                        <i class="bi bi-chat-dots me-1"></i> Mga Response ng Responder
                    </p>
                    <?php if (empty($responses)): ?>
                        <p class="text-muted small mb-0">
                            Wala pang response. Abangan ang update mula sa aming responders.
                        </p>
                    <?php else: ?>
                        <div class="d-flex flex-column gap-3">
                            <?php foreach ($responses as $r): ?>
                                <div>
                                    <div class="d-flex align-items-center gap-2 mb-1">
                                        <div style="width:28px;height:28px;border-radius:50%;
                                             background:#e8f0fe;display:flex;align-items:center;
                                             justify-content:center;font-size:12px;font-weight:500;
                                             color:#1a73e8;">
                                            <?= strtoupper(substr($r['responder_name'], 0, 1)) ?>
                                        </div>
                                        <span class="small fw-medium">
                                            <?= htmlspecialchars($r['responder_name']) ?>
                                        </span>
                                        <span class="text-muted" style="font-size:11px;">
                                            <?= date('M d, Y g:i A', strtotime($r['responded_at'])) ?>
                                        </span>
                                    </div>
                                    <div class="chat-bubble ms-4">
                                        <p class="small mb-0">
                                            <?= nl2br(htmlspecialchars($r['message'])) ?>
                                        </p>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

        </div>

        <!-- RIGHT: Status timeline + Info -->
        <div class="col-lg-4">

            <!-- Assigned responder -->
            <div class="card border-0 shadow-sm mb-3">
                <div class="card-body p-3">
                    <p class="small fw-medium mb-2">
                        <i class="bi bi-person-badge me-1"></i> Assigned Responder
                    </p>
                    <?php if ($incident['responder_name']): ?>
                        <div class="d-flex align-items-center gap-2">
                            <div style="width:34px;height:34px;border-radius:50%;
                                 background:#d1fae5;display:flex;align-items:center;
                                 justify-content:center;font-size:13px;font-weight:500;color:#065f46;">
                                <?= strtoupper(substr($incident['responder_name'], 0, 1)) ?>
                            </div>
                            <span class="small fw-medium">
                                <?= htmlspecialchars($incident['responder_name']) ?>
                            </span>
                        </div>
                    <?php else: ?>
                        <p class="text-muted small mb-0">
                            <i class="bi bi-hourglass me-1"></i>
                            Pending assignment pa.
                        </p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Status timeline -->
            <div class="card border-0 shadow-sm">
                <div class="card-body p-3">
                    <p class="small fw-medium mb-3">
                        <i class="bi bi-clock-history me-1"></i> Status History
                    </p>
                    <?php if (empty($logs)): ?>
                        <p class="text-muted small mb-0">Walang log pa.</p>
                    <?php else: ?>
                        <div class="timeline">
                            <?php foreach ($logs as $log): ?>
                                <div class="tl-item">
                                    <div class="tl-dot <?= $log['new_status'] === 'resolved' ? 'done' : '' ?>"></div>
                                    <div class="small fw-medium">
                                        <?= ucwords(str_replace('_', ' ', $log['new_status'])) ?>
                                    </div>
                                    <div class="text-muted" style="font-size:11px;">
                                        <?= date('M d, Y g:i A', strtotime($log['changed_at'])) ?>
                                        · <?= htmlspecialchars($log['changed_by_name']) ?>
                                    </div>
                                    <?php if ($log['remarks']): ?>
                                        <div class="text-muted small mt-1">
                                            "<?= htmlspecialchars($log['remarks']) ?>"
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

        </div>
    </div>
</div>

<?php if ($incident['latitude'] && $incident['longitude']): ?>
<script src="https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.min.js"></script>
<script>
var map = L.map('map', { zoomControl: true, dragging: false, scrollWheelZoom: false })
           .setView([<?= $incident['latitude'] ?>, <?= $incident['longitude'] ?>], 16);

L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '© OpenStreetMap contributors'
}).addTo(map);

L.marker([<?= $incident['latitude'] ?>, <?= $incident['longitude'] ?>])
 .addTo(map)
 .bindPopup('<?= addslashes(htmlspecialchars($incident['location'])) ?>')
 .openPopup();
</script>
<?php endif; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>