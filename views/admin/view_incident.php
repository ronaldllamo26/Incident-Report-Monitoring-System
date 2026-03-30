<?php
require_once __DIR__ . '/../../includes/auth.php';
requireRole('admin');
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../models/Incident.php';

$user     = currentUser();
$id       = (int)($_GET['id'] ?? 0);
$model    = new Incident();
$incident = $model->getById($id);

if (!$incident) {
    header('Location: /irms/views/admin/incidents.php');
    exit;
}

$attachments = $model->getAttachments($id);
$logs        = $model->getStatusLogs($id);
$responses   = $model->getResponses($id);
$responders  = $pdo->query("
    SELECT id, name FROM users
    WHERE role = 'responder' AND is_active = 1
")->fetchAll();

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
    <title>Incident #<?= $id ?> — IRMS Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.min.css" rel="stylesheet">
    <style>
        .sidebar { width: 220px; min-height: 100vh; background: #1e293b; }
        .sidebar .nav-link { color: #94a3b8; font-size: 14px; padding: 10px 20px;
                             border-radius: 6px; margin: 2px 8px; }
        .sidebar .nav-link:hover,
        .sidebar .nav-link.active { background: #334155; color: #fff; }
        .main-content { flex: 1; overflow-y: auto; }
        .top-nav { background: #fff; border-bottom: 1px solid #e2e8f0; padding: 12px 24px; }
        #map { height: 220px; border-radius: 8px; border: 1px solid #dee2e6; }
        .timeline { position: relative; padding-left: 24px; }
        .timeline::before { content:''; position:absolute; left:7px; top:0; bottom:0;
                            width:1px; background:#dee2e6; }
        .tl-item { position:relative; margin-bottom:16px; }
        .tl-dot { position:absolute; left:-20px; top:4px; width:10px; height:10px;
                  border-radius:50%; background:#0d6efd; border:2px solid #fff;
                  box-shadow:0 0 0 1px #0d6efd; }
        .tl-dot.done { background:#198754; box-shadow:0 0 0 1px #198754; }
        .chat-bubble { background:#f8f9fa; border-radius:0 12px 12px 12px;
                       padding:10px 14px; border:0.5px solid #dee2e6; }
        .attach-img { width:80px; height:80px; object-fit:cover;
                      border-radius:8px; border:1px solid #dee2e6; cursor:pointer; }
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
            <a href="/irms/views/admin/dashboard.php" class="nav-link">
                <i class="bi bi-speedometer2 me-2"></i> Dashboard
            </a>
            <a href="/irms/views/admin/incidents.php" class="nav-link active">
                <i class="bi bi-exclamation-triangle me-2"></i> Incidents
            </a>
            <a href="/irms/views/admin/users.php" class="nav-link">
                <i class="bi bi-people me-2"></i> Users
            </a>
            <a href="/irms/views/admin/categories.php" class="nav-link">
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

    <!-- Main -->
    <div class="main-content">
        <div class="top-nav d-flex justify-content-between align-items-center">
            <div class="d-flex align-items-center gap-2">
                <a href="/irms/views/admin/incidents.php"
                   class="btn btn-outline-secondary btn-sm">
                    <i class="bi bi-arrow-left"></i>
                </a>
                <h6 class="fw-semibold mb-0">Incident #<?= $id ?></h6>
            </div>
            <span class="badge bg-<?= $statusColor[$incident['status']] ?>">
                <?= ucwords(str_replace('_',' ',$incident['status'])) ?>
            </span>
        </div>

        <div class="p-4">

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

            <div class="row g-4">

                <!-- LEFT -->
                <div class="col-lg-8">

                    <!-- Incident details -->
                    <div class="card border-0 shadow-sm mb-3">
                        <div class="card-body p-4">
                            <h5 class="fw-semibold mb-3">
                                <?= htmlspecialchars($incident['title']) ?>
                            </h5>
                            <div class="d-flex flex-wrap gap-2 mb-3">
                                <span class="badge bg-light text-dark border">
                                    <i class="bi bi-tag me-1"></i>
                                    <?= htmlspecialchars($incident['category_name']) ?>
                                </span>
                                <span class="badge bg-<?= $sevColor[$incident['severity']] ?>">
                                    <?= ucfirst($incident['severity']) ?> severity
                                </span>
                                <span class="badge bg-light text-dark border">
                                    <i class="bi bi-person me-1"></i>
                                    <?= htmlspecialchars($incident['reporter_name']) ?>
                                </span>
                                <span class="badge bg-light text-dark border">
                                    <i class="bi bi-calendar me-1"></i>
                                    <?= date('M d, Y g:i A', strtotime($incident['reported_at'])) ?>
                                </span>
                            </div>
                            <p class="mb-2">
                                <?= nl2br(htmlspecialchars($incident['description'])) ?>
                            </p>
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
                                <i class="bi bi-map me-1"></i> Lokasyon
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
                                <i class="bi bi-images me-1"></i>
                                Mga Larawan (<?= count($attachments) ?>)
                            </p>
                            <div class="d-flex flex-wrap gap-2">
                                <?php foreach ($attachments as $a): ?>
                                    <a href="/irms/<?= htmlspecialchars($a['file_path']) ?>"
                                       target="_blank">
                                        <img src="/irms/<?= htmlspecialchars($a['file_path']) ?>"
                                             class="attach-img" alt="attachment">
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Admin response form -->
                    <div class="card border-0 shadow-sm mb-3">
                        <div class="card-body p-3">
                            <p class="small fw-medium mb-2">
                                <i class="bi bi-chat-dots me-1"></i> Mag-respond bilang Admin
                            </p>
                            <form action="/irms/ajax/update_status.php" method="POST">
                                <input type="hidden" name="incident_id" value="<?= $id ?>">
                                <input type="hidden" name="action" value="respond">
                                <textarea name="message" class="form-control mb-2" rows="3"
                                    placeholder="I-type ang mensahe para sa citizen o responder..."
                                    required></textarea>
                                <button type="submit" class="btn btn-primary btn-sm">
                                    <i class="bi bi-send me-1"></i> Ipadala
                                </button>
                            </form>
                        </div>
                    </div>

                    <!-- Responses -->
                    <?php if ($responses): ?>
                    <div class="card border-0 shadow-sm">
                        <div class="card-body p-3">
                            <p class="small fw-medium mb-3">
                                <i class="bi bi-chat-left-dots me-1"></i>
                                Mga Response (<?= count($responses) ?>)
                            </p>
                            <div class="d-flex flex-column gap-3">
                                <?php foreach ($responses as $r): ?>
                                    <div>
                                        <div class="d-flex align-items-center gap-2 mb-1">
                                            <div style="width:28px;height:28px;border-radius:50%;
                                                 background:#e0f2fe;display:flex;align-items:center;
                                                 justify-content:center;font-size:12px;
                                                 font-weight:500;color:#0369a1;">
                                                <?= strtoupper(substr($r['responder_name'],0,1)) ?>
                                            </div>
                                            <span class="small fw-medium">
                                                <?= htmlspecialchars($r['responder_name']) ?>
                                            </span>
                                            <span class="text-muted" style="font-size:11px;">
                                                <?= date('M d, Y g:i A',
                                                    strtotime($r['responded_at'])) ?>
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
                        </div>
                    </div>
                    <?php endif; ?>

                </div>

                <!-- RIGHT -->
                <div class="col-lg-4">

                    <!-- Update status -->
                    <div class="card border-0 shadow-sm mb-3">
                        <div class="card-body p-3">
                            <p class="small fw-medium mb-2">
                                <i class="bi bi-arrow-repeat me-1"></i> I-update ang Status
                            </p>
                            <form action="/irms/ajax/update_status.php" method="POST">
                                <input type="hidden" name="incident_id" value="<?= $id ?>">
                                <input type="hidden" name="action" value="update_status">
                                <input type="hidden" name="old_status"
                                       value="<?= $incident['status'] ?>">
                                <div class="mb-2">
                                    <select name="new_status"
                                            class="form-select form-select-sm" required>
                                        <option value="">-- Piliin ang bagong status --</option>
                                        <?php
                                        foreach (['pending','in_progress','resolved','closed'] as $s):
                                            if ($s === $incident['status']) continue;
                                        ?>
                                            <option value="<?= $s ?>">
                                                <?= ucwords(str_replace('_',' ',$s)) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="mb-2">
                                    <textarea name="remarks" class="form-control form-control-sm"
                                        rows="2" placeholder="Remarks (optional)..."></textarea>
                                </div>
                                <button type="submit" class="btn btn-primary btn-sm w-100">
                                    <i class="bi bi-check2 me-1"></i> I-update
                                </button>
                            </form>
                        </div>
                    </div>

                    <!-- Assign responder -->
                    <div class="card border-0 shadow-sm mb-3">
                        <div class="card-body p-3">
                            <p class="small fw-medium mb-2">
                                <i class="bi bi-person-badge me-1"></i> Assign Responder
                            </p>
                            <form action="/irms/ajax/assign_responder.php" method="POST">
                                <input type="hidden" name="incident_id" value="<?= $id ?>">
                                <div class="mb-2">
                                    <select name="responder_id" class="form-select form-select-sm">
                                        <option value="">-- Unassigned --</option>
                                        <?php foreach ($responders as $r): ?>
                                            <option value="<?= $r['id'] ?>"
                                                <?= $incident['assigned_to'] == $r['id']
                                                    ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($r['name']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <button type="submit" class="btn btn-success btn-sm w-100">
                                    <i class="bi bi-check2 me-1"></i> I-assign
                                </button>
                            </form>
                        </div>
                    </div>

                    <!-- Reporter info -->
                    <div class="card border-0 shadow-sm mb-3">
                        <div class="card-body p-3">
                            <p class="small fw-medium mb-2">
                                <i class="bi bi-person me-1"></i> Reporter Info
                            </p>
                            <div class="small">
                                <div class="fw-medium mb-1">
                                    <?= htmlspecialchars($incident['reporter_name']) ?>
                                </div>
                                <div class="text-muted">
                                    <i class="bi bi-envelope me-1"></i>
                                    <?= htmlspecialchars($incident['reporter_email']) ?>
                                </div>
                                <?php if ($incident['reporter_phone']): ?>
                                <div class="text-muted mt-1">
                                    <i class="bi bi-telephone me-1"></i>
                                    <?= htmlspecialchars($incident['reporter_phone']) ?>
                                </div>
                                <?php endif; ?>
                            </div>
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
                                            <div class="tl-dot <?= $log['new_status'] === 'resolved'
                                                ? 'done' : '' ?>">
                                            </div>
                                            <div class="small fw-medium">
                                                <?= ucwords(str_replace('_',' ',
                                                    $log['new_status'])) ?>
                                            </div>
                                            <div class="text-muted" style="font-size:11px;">
                                                <?= date('M d, Y g:i A',
                                                    strtotime($log['changed_at'])) ?>
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
    </div>
</div>

<?php if ($incident['latitude'] && $incident['longitude']): ?>
<script src="https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.min.js"></script>
<script>
var map = L.map('map', { zoomControl:true, dragging:false, scrollWheelZoom:false })
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