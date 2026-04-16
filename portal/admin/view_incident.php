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
    header('Location: /irms/portal/admin/incidents.php');
    exit;
}

$attachments = $model->getAttachments($id);
$timeline    = $model->getFullTimeline($id);
$feedback    = $model->getFeedback($id);
$responders = $pdo->query("
    SELECT id, name FROM users
    WHERE role = 'responder'
    ORDER BY name
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
    <?php include __DIR__ . '/../../includes/sidebar_style.php'; ?>
    <style>
        #map { height: 220px; border-radius: 8px; border: 1px solid #dee2e6; }
        
        /* Professional Lifecycle Timeline */
        .lifecycle-container { position: relative; padding: 10px 0; }
        .lifecycle-container::before { 
            content: ''; position: absolute; left: 15px; top: 0; bottom: 0; 
            width: 2px; background: #e2e8f0; 
        }
        .event-item { position: relative; padding-left: 45px; margin-bottom: 24px; }
        .event-item:last-child { margin-bottom: 0; }
        .event-icon { 
            position: absolute; left: 0; top: 0; width: 32px; height: 32px; 
            border-radius: 50%; background: #fff; border: 2px solid #cbd5e1; 
            display: flex; align-items: center; justify-content: center; 
            z-index: 1; font-size: 14px; color: #64748b;
            box-shadow: 0 0 0 4px #fff;
        }
        .event-card { 
            background: #fff; border: 1px solid #e2e8f0; border-radius: 10px; 
            padding: 12px 16px; box-shadow: 0 1px 3px rgba(0,0,0,0.02);
            position: relative;
        }
        .event-card::before {
            content: ''; position: absolute; left: -6px; top: 12px;
            width: 10px; height: 10px; background: #fff;
            border-left: 1px solid #e2e8f0; border-bottom: 1px solid #e2e8f0;
            transform: rotate(45deg);
        }
        .event-meta { font-size: 11px; color: #94a3b8; margin-bottom: 4px; display: flex; align-items: center; gap: 8px; }
        .event-title { font-size: 13px; fw-bold; margin-bottom: 2px; color: #1e293b; font-weight: 600; }
        .event-content { font-size: 13px; color: #475569; line-height: 1.5; }
        .event-actor { color: #0f172a; font-weight: 600; }

        .attach-img { width:80px; height:80px; object-fit:cover;
                      border-radius:8px; border:1px solid #dee2e6; cursor:pointer; }
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
                <div class="d-flex align-items-center gap-2">
                    <a href="/irms/portal/admin/incidents.php"
                       class="btn btn-outline-secondary btn-sm">
                        <i class="bi bi-arrow-left"></i>
                    </a>
                    <h6 class="fw-semibold mb-0">Incident #<?= $id ?></h6>
                </div>
            </div>
         <div class="d-flex align-items-center gap-2">
                <?php include __DIR__ . '/../../includes/notification_bell.php'; ?>
                <a href="/irms/portal/admin/export_pdf.php?id=<?= $id ?>"
                   class="btn btn-sm btn-outline-secondary"
                   title="I-download bilang PDF" target="_blank">
                    <i class="bi bi-file-earmark-pdf me-1"></i> PDF
                </a>
                <span class="badge bg-<?= $statusColor[$incident['status']] ?>">
                    <?= ucwords(str_replace('_',' ',$incident['status'])) ?>
                </span>
            </div>
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

                <!-- MAIN INFO COLUMN (KALIWA) -->
                <div class="col-lg-8 col-xl-9">

                    <div class="card border-0 shadow-sm mb-4 overflow-hidden">
                        <div class="card-header bg-white py-3 border-bottom d-flex align-items-center justify-content-between">
                            <h6 class="fw-bold mb-0 text-slate">Detalyado ng Insidente</h6>
                            <span class="badge bg-<?= $sevColor[$incident['severity']] ?> px-3">
                                <?= ucfirst($incident['severity']) ?> Priority
                            </span>
                        </div>
                        <div class="card-body p-4">
                            <?php if ($incident['is_duplicate']): ?>
                                <div class="alert alert-indigo d-flex align-items-center mb-4" style="background-color: #f5f3ff; border: 1px solid #c4b5fd; color: #5b21b6;">
                                    <i class="bi bi-exclamation-triangle-fill me-2 fs-5"></i>
                                    <div class="small fw-medium">
                                        System Notice: Potential duplicate of 
                                        <a href="/irms/portal/admin/view_incident.php?id=<?= $incident['duplicate_of'] ?>" class="fw-bold text-decoration-none" style="color: #4c1d95;">
                                            Incident #<?= $incident['duplicate_of'] ?>
                                        </a> detected.
                                    </div>
                                </div>
                            <?php endif; ?>

                            <h4 class="fw-bold text-slate mb-2"><?= htmlspecialchars($incident['title']) ?></h4>
                            <div class="text-muted small mb-4 d-flex align-items-center gap-3">
                                <span><i class="bi bi-tag me-1"></i> <?= htmlspecialchars($incident['category_name']) ?></span>
                                <span><i class="bi bi-calendar3 me-1"></i> <?= date('M d, Y g:i A', strtotime($incident['reported_at'])) ?></span>
                            </div>

                            <p class="fs-6 text-slate mb-4" style="line-height: 1.7;">
                                <?= nl2br(htmlspecialchars($incident['description'])) ?>
                            </p>

                            <div class="p-3 bg-light rounded-3 d-flex align-items-start gap-2">
                                <i class="bi bi-geo-alt-fill text-danger mt-1"></i>
                                <div>
                                    <div class="fw-semibold small text-slate">Lokasyon:</div>
                                    <div class="text-muted small"><?= htmlspecialchars($incident['location']) ?></div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <?php if ($incident['ai_formal_report']): ?>
                    <!-- AI Official Formal Report -->
                    <div class="card border-0 shadow-sm mb-3 border-start border-4" style="border-color: #1e3a8a !important;">
                        <div class="card-body p-4">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h6 class="fw-bold mb-0 text-primary">
                                    <i class="bi bi-file-earmark-text-fill me-2"></i>Official AI Incident Disclosure (English)
                                </h6>
                                <button class="btn btn-outline-primary btn-sm" onclick="copyOfficialReport()">
                                    <i class="bi bi-clipboard me-1"></i> Copy Report
                                </button>
                            </div>
                            <div class="bg-light p-3 rounded" style="font-family: 'Courier New', Courier, monospace; font-size: 13px; border: 1px solid #d1d5db;">
                                <div id="formalReportText" style="white-space: pre-wrap; line-height: 1.6; color: #1e293b;"><?= htmlspecialchars($incident['ai_formal_report']) ?></div>
                            </div>
                            <div class="mt-2 text-muted" style="font-size: 10px;">
                                <i class="bi bi-info-circle me-1"></i> This report is automatically generated using formal government nomenclature for official documentation purposes.
                            </div>
                        </div>
                    </div>
                    <script>
                    function copyOfficialReport() {
                        const text = document.getElementById('formalReportText').innerText;
                        navigator.clipboard.writeText(text).then(() => {
                            const btn = event.currentTarget;
                            const original = btn.innerHTML;
                            btn.innerHTML = '<i class="bi bi-check2"></i> Copied!';
                            btn.classList.replace('btn-outline-primary', 'btn-success');
                            setTimeout(() => {
                                btn.innerHTML = original;
                                btn.classList.replace('btn-success', 'btn-outline-primary');
                            }, 2000);
                        });
                    }
                    </script>
                    <?php endif; ?>

                    <!-- Media Evidence -->
                    <?php
                    $citizenEv = array_filter($attachments, fn($a) => ($a['stage'] ?? 'report') === 'report');
                    $resProof  = array_filter($attachments, fn($a) => ($a['stage'] ?? 'report') === 'resolution');
                    ?>

                    <?php if ($citizenEv || $resProof): ?>
                    <div class="card border-0 shadow-sm mb-4">
                        <div class="card-header bg-white py-3 border-bottom">
                            <h6 class="fw-bold mb-0 text-slate"><i class="bi bi-images me-2 text-primary"></i>Mga Ebidensya at Patunay</h6>
                        </div>
                        <div class="card-body p-4">
                            <?php if ($citizenEv): ?>
                                <p class="small fw-bold text-muted text-uppercase mb-3" style="letter-spacing: 0.5px;">Mula sa Citizen</p>
                                <div class="d-flex flex-wrap gap-3 mb-4">
                                    <?php foreach ($citizenEv as $a): ?>
                                        <?php if (str_starts_with($a['file_type'] ?? '', 'video/')): ?>
                                            <div style="max-width: 250px;">
                                                <video controls class="rounded border shadow-sm w-100" style="max-height: 150px; background: #000;">
                                                    <source src="/irms/<?= htmlspecialchars($a['file_path']) ?>" type="<?= htmlspecialchars($a['file_type']) ?>">
                                                </video>
                                            </div>
                                        <?php else: ?>
                                            <a href="/irms/<?= htmlspecialchars($a['file_path']) ?>" target="_blank">
                                                <img src="/irms/<?= htmlspecialchars($a['file_path']) ?>" class="attach-img shadow-sm" style="width:100px; height:100px; border-radius:12px;" alt="attachment">
                                            </a>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>

                            <?php if ($resProof): ?>
                                <hr class="my-4">
                                <p class="small fw-bold text-success text-uppercase mb-3" style="letter-spacing: 0.5px;">Proof of Resolution (Responder)</p>
                                <div class="d-flex flex-wrap gap-3">
                                    <?php foreach ($resProof as $a): ?>
                                        <?php if (str_starts_with($a['file_type'] ?? '', 'video/')): ?>
                                            <div style="max-width: 250px;">
                                                <video controls class="rounded border shadow-sm w-100" style="max-height: 150px; background: #000;">
                                                    <source src="/irms/<?= htmlspecialchars($a['file_path']) ?>" type="<?= htmlspecialchars($a['file_type']) ?>">
                                                </video>
                                            </div>
                                        <?php else: ?>
                                            <a href="/irms/<?= htmlspecialchars($a['file_path']) ?>" target="_blank">
                                                <img src="/irms/<?= htmlspecialchars($a['file_path']) ?>" class="attach-img shadow-sm border-success border-2" style="width:100px; height:100px; border-radius:12px;" alt="attachment">
                                            </a>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endif; ?>


                    <div class="card border-0 shadow-sm mb-3">
                        <div class="card-body p-3">
                            <p class="small fw-medium mb-2">
                                <i class="bi bi-chat-dots me-1"></i> Mag-respond bilang Admin
                            </p>
                            <form action="/irms/ajax/update_status.php" method="POST">
                        <?= csrf_field() ?>
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

                    <div class="card border-0 shadow-sm mb-4">
                        <div class="card-header bg-white py-3 border-bottom-0">
                            <h6 class="fw-bold mb-0"><i class="bi bi-clock-history me-2 text-primary"></i>Incident Lifecycle Timeline</h6>
                        </div>
                        <div class="card-body px-4 pb-4 pt-1">
                            <div class="lifecycle-container">
                                <?php foreach (array_reverse($timeline) as $event): ?>
                                    <div class="event-item">
                                        <div class="event-icon" style="border-color: <?= $event['color'] ?>; color: <?= $event['color'] ?>;">
                                            <i class="bi <?= $event['icon'] ?>"></i>
                                        </div>
                                        <div class="event-card">
                                            <div class="event-meta">
                                                <span><i class="bi bi-calendar3 me-1"></i> <?= date('M d, Y · g:i A', strtotime($event['date'])) ?></span>
                                                <span>·</span>
                                                <span class="event-actor"><?= htmlspecialchars($event['actor']) ?></span>
                                            </div>
                                            <div class="event-title"><?= htmlspecialchars($event['title']) ?></div>
                                            <div class="event-content">
                                                <?php if ($event['type'] === 'feedback'): ?>
                                                    <?php 
                                                    // Extract rating number from content like "Rating: 5/5 Stars..."
                                                    preg_match('/Rating: (\d)\/5/', $event['content'], $matches);
                                                    $ratingVal = isset($matches[1]) ? (int)$matches[1] : 0;
                                                    $commentText = preg_replace('/Rating: \d\/5 Stars\. /', '', $event['content']);
                                                    ?>
                                                    <div class="mb-2 d-flex gap-1" style="color: #f59e0b;">
                                                        <?php for ($i=1; $i<=5; $i++): ?>
                                                            <i class="bi bi-star<?= $i <= $ratingVal ? '-fill' : '' ?>"></i>
                                                        <?php endfor; ?>
                                                    </div>
                                                    <div class="fst-italic text-muted"><?= htmlspecialchars($commentText) ?></div>
                                                <?php else: ?>
                                                    <?= nl2br(htmlspecialchars(str_replace('✨', '', $event['content']))) ?>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>

                </div>

                <!-- CONTROLS SIDEBAR (KANAN) -->
                <div class="col-lg-4 col-xl-3">

                    <!-- Map Card -->
                    <?php if ($incident['latitude'] && $incident['longitude']): ?>
                    <div class="card border-0 shadow-sm mb-4 overflow-hidden">
                        <div id="map" style="height: 200px;"></div>
                        <div class="card-body p-2 text-center">
                            <div class="small text-muted"><i class="bi bi-geo-alt me-1"></i> Incident Proximity View</div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Action Center -->
                    <div class="card border-0 shadow-sm mb-4">
                        <div class="card-header bg-white py-3 border-bottom">
                            <h6 class="fw-bold mb-0 text-slate"><i class="bi bi-tools me-2 text-primary"></i>Action Center</h6>
                        </div>
                        <div class="card-body p-4">
                            <!-- Update Status -->
                            <div class="mb-4">
                                <label class="small fw-bold text-muted text-uppercase mb-2 d-block">Incident Status</label>
                                <?php if ($incident['status'] === 'resolved'): ?>
                                    <form action="/irms/ajax/update_status.php" method="POST">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="incident_id" value="<?= $id ?>">
                                        <input type="hidden" name="action" value="update_status">
                                        <input type="hidden" name="old_status" value="resolved">
                                        <input type="hidden" name="new_status" value="closed">
                                        <button type="submit" class="btn btn-dark w-100" onclick="return confirm('I-close na ang incident?')">
                                            <i class="bi bi-lock me-1"></i> Close Incident
                                        </button>
                                    </form>
                                <?php elseif ($incident['status'] !== 'closed' && $incident['status'] !== 'rejected'): ?>
                                    <form action="/irms/ajax/update_status.php" method="POST">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="incident_id" value="<?= $id ?>">
                                        <input type="hidden" name="action" value="update_status">
                                        <input type="hidden" name="old_status" value="<?= $incident['status'] ?>">
                                        <select name="new_status" class="form-select mb-2" required>
                                            <option value="">-- Bagong Status --</option>
                                            <?php foreach (['pending','in_progress','resolved','closed'] as $s):
                                                if ($s === $incident['status']) continue; ?>
                                                <option value="<?= $s ?>"><?= ucwords(str_replace('_',' ',$s)) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                        <textarea name="remarks" class="form-control mb-2" rows="2" placeholder="Optional remarks..."></textarea>
                                        <button type="submit" class="btn btn-primary w-100">Update Status</button>
                                    </form>
                                <?php else: ?>
                                    <div class="alert alert-secondary py-2 small mb-0">Record is Closed</div>
                                <?php endif; ?>
                            </div>

                            <!-- Assign Responder -->
                            <div class="mb-0">
                                <label class="small fw-bold text-muted text-uppercase mb-2 d-block">Assign Authority</label>
                                <form action="/irms/ajax/assign_responder.php" method="POST">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="incident_id" value="<?= $id ?>">
                                    <select name="responder_id" class="form-select mb-2">
                                        <option value="">-- Select Responder --</option>
                                        <?php foreach ($responders as $r): ?>
                                            <option value="<?= $r['id'] ?>" <?= $incident['assigned_to'] == $r['id'] ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($r['name']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <button type="submit" class="btn btn-outline-primary w-100">Change Assignee</button>
                                </form>
                            </div>
                        </div>
                    </div>

                    <!-- SLA & Reporter Stats -->
                    <div class="card border-0 shadow-sm mb-4">
                        <div class="card-body p-4">
                            <div class="mb-4">
                                <label class="small fw-bold text-muted text-uppercase mb-2 d-block">SLA Performance</label>
                                <?php 
                                $sla = $model->getSlaStatus($incident); 
                                $pColor = $sla['status'] === 'breached' ? 'danger' : ($sla['status'] === 'warning' ? 'warning' : 'success');
                                ?>
                                <div class="progress mb-2" style="height: 10px; border-radius: 5px;">
                                    <div class="progress-bar bg-<?= $pColor ?>" data-bs-toggle="tooltip" title="<?= $sla['percent'] ?>%" style="width: <?= $sla['percent'] ?>%"></div>
                                </div>
                                <div class="d-flex justify-content-between small">
                                    <span class="fw-bold text-<?= $pColor ?>"><?= $sla['label'] ?></span>
                                    <span class="text-muted"><?= $sla['percent'] ?>%</span>
                                </div>
                            </div>

                            <hr class="my-4 op-10">

                            <div>
                                <label class="small fw-bold text-muted text-uppercase mb-2 d-block">Reporter Details</label>
                                <div class="d-flex align-items-center gap-3 mb-2">
                                    <div class="rounded-circle bg-slate text-white d-flex align-items-center justify-content-center" style="width: 38px; height: 38px; font-weight: 700;">
                                        <?= strtoupper(substr($incident['reporter_name'], 0, 1)) ?>
                                    </div>
                                    <div>
                                        <div class="fw-bold text-slate small"><?= htmlspecialchars($incident['reporter_name']) ?></div>
                                        <div class="text-muted" style="font-size: 11px;"><?= $incident['is_anonymous'] ? 'Anonymous' : 'Registered Citizen' ?></div>
                                    </div>
                                </div>
                                <?php if ($incident['reporter_email']): ?>
                                    <div class="small text-muted mb-1"><i class="bi bi-envelope me-2"></i><?= htmlspecialchars($incident['reporter_email']) ?></div>
                                <?php endif; ?>
                                <?php if ($incident['reporter_phone']): ?>
                                    <div class="small text-muted"><i class="bi bi-telephone me-2"></i><?= htmlspecialchars($incident['reporter_phone']) ?></div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Moderate -->
                    <?php if (!in_array($incident['status'], ['closed', 'rejected'])): ?>
                    <button class="btn btn-outline-danger btn-sm w-100 py-2 border-0" 
                            style="background: #fff5f5;"
                            onclick="if(confirm('BAN user and REJECT spam report?')) { document.getElementById('banForm').submit(); }">
                        <i class="bi bi-shield-x me-2"></i> Reject & Ban User
                    </button>
                    <form id="banForm" action="/irms/ajax/reject_ban.php" method="POST" style="display:none;">
                        <?= csrf_field() ?>
                        <input type="hidden" name="incident_id" value="<?= $id ?>">
                    </form>
                    <?php endif; ?>

                </div>
            </div>
        </div>
    </div>
</div>

<?php if ($incident['latitude'] && $incident['longitude']): ?>
<script src="https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.min.js"></script>
<script>
// Fix Leaflet broken default icons when pulling from CDN
delete L.Icon.Default.prototype._getIconUrl;
L.Icon.Default.mergeOptions({
    iconRetinaUrl: 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/images/marker-icon-2x.png',
    iconUrl: 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/images/marker-icon.png',
    shadowUrl: 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/images/marker-shadow.png'
});

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