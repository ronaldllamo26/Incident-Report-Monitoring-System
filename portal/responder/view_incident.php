<?php
require_once __DIR__ . '/../../includes/auth.php';
requireRole('responder');
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../models/Incident.php';

$user     = currentUser();
$id       = (int)($_GET['id'] ?? 0);
$model    = new Incident();
$incident = $model->getById($id);

if (!$incident || $incident['assigned_to'] != $user['id']) {
    header('Location: /irms/portal/responder/dashboard.php'); exit;
}

$attachments = $model->getAttachments($id);
$logs        = $model->getStatusLogs($id);
$responses   = $model->getResponses($id);

$curSt  = $incident['status'];
$curSev = $incident['severity'];

$stLabel = ['pending' => 'Pending', 'in_progress' => 'In Progress', 'resolved' => 'Resolved', 'closed' => 'Closed'];
$stStyle = [
    'pending'     => 'background:#fef3c7;color:#92400e;',
    'in_progress' => 'background:#dbeafe;color:#1e40af;',
    'resolved'    => 'background:#dcfce7;color:#166534;',
    'closed'      => 'background:#f3f4f6;color:#4b5563;',
];
$sevColor = ['low' => '#16a34a', 'medium' => '#d97706', 'high' => '#ea580c', 'critical' => '#CE1126'];
$sevLabel = ['low' => 'Low', 'medium' => 'Medium', 'high' => 'High', 'critical' => 'Critical'];

$success = $_GET['success'] ?? '';
$error   = $_GET['error']   ?? '';
?>
<!DOCTYPE html>
<html lang="fil">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Incident #<?= str_pad($id, 5, '0', STR_PAD_LEFT) ?> — QC-ALERTO</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --qc-blue: #1e293b;
            --qc-red:  #CE1126;
            --bg:      #f4f6f9;
            --border:  #e2e8f0;
            --text:    #1e293b;
            --muted:   #64748b;
        }
        * { box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: var(--bg); margin: 0; color: var(--text); }

        /* Topbar */
        .topbar { background: var(--qc-blue); border-bottom: 3px solid var(--qc-red);
                  position: sticky; top: 0; z-index: 100; }
        .topbar-inner { max-width: 1200px; margin: 0 auto; padding: 0 24px;
                        display: flex; align-items: center; justify-content: space-between; height: 56px; }
        .brand { font-size: 16px; font-weight: 700; color: #fff; text-decoration: none;
                 display: flex; align-items: center; gap: 10px; }
        .brand img { height: 30px; width: 30px; object-fit: contain; }
        .role-badge { background: rgba(255,255,255,0.15); border: 1px solid rgba(255,255,255,0.25);
                      color: #fff; font-size: 10px; font-weight: 600; padding: 2px 8px;
                      border-radius: 4px; letter-spacing: 0.8px; text-transform: uppercase; }
        .back-btn { font-size: 12px; color: rgba(255,255,255,0.85); text-decoration: none;
                    display: flex; align-items: center; gap: 5px; padding: 5px 12px;
                    border: 1px solid rgba(255,255,255,0.2); border-radius: 6px; transition: all 0.2s; }
        .back-btn:hover { background: rgba(255,255,255,0.1); color: #fff; }

        /* Incident title bar */
        .inc-bar {
            background: #fff; border-bottom: 1px solid var(--border); padding: 18px 0;
        }
        .inc-bar-inner { max-width: 1200px; margin: 0 auto; padding: 0 24px; }
        .inc-ref { font-size: 11px; color: var(--muted); font-weight: 600;
                   text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 4px; }
        .inc-title { font-size: 20px; font-weight: 700; color: var(--text); margin: 0 0 10px; }
        .badge-sev { padding: 3px 10px; border-radius: 5px; font-size: 11px; font-weight: 600; }
        .badge-st  { padding: 3px 10px; border-radius: 5px; font-size: 11px; font-weight: 600; }
        .cat-tag  { padding: 3px 10px; border-radius: 5px; font-size: 11px; font-weight: 500;
                    background: #f1f5f9; color: #374151; }

        .main-wrap { max-width: 1200px; margin: 0 auto; padding: 24px; }

        /* Cards */
        .card-c { background: #fff; border: 1px solid var(--border); border-radius: 10px;
                  margin-bottom: 18px; overflow: hidden; }
        .card-h { padding: 13px 18px; border-bottom: 1px solid #f1f5f9;
                  font-size: 13px; font-weight: 600; color: var(--text);
                  display: flex; align-items: center; gap: 8px; background: #fafbfc; }
        .card-h i { color: var(--qc-blue); }
        .card-b { padding: 18px; }

        /* Map */
        #map { height: 220px; border-radius: 8px; }

        /* Photos */
        .photo-grid { display: flex; flex-wrap: wrap; gap: 10px; }
        .photo-thumb { width: 80px; height: 80px; object-fit: cover; border-radius: 8px;
                       border: 1px solid var(--border); cursor: pointer; transition: transform 0.15s; }
        .photo-thumb:hover { transform: scale(1.05); border-color: var(--qc-blue); }

        /* Status action panel */
        .action-card { background: #fff; border: 1px solid var(--border); border-radius: 10px;
                       margin-bottom: 18px; overflow: hidden; }
        .action-h { padding: 13px 18px; border-bottom: 1px solid var(--border);
                    font-size: 13px; font-weight: 600; color: var(--text); background: #fafbfc; }
        .action-b { padding: 18px; }

        .btn-acknowledge {
            width: 100%; padding: 11px; border-radius: 8px; border: none;
            background: #d97706; color: #fff; font-size: 14px; font-weight: 600;
            cursor: pointer; display: flex; align-items: center; justify-content: center;
            gap: 7px; transition: background 0.2s;
        }
        .btn-acknowledge:hover { background: #b45309; }
        .btn-resolve {
            width: 100%; padding: 11px; border-radius: 8px; border: none;
            background: var(--qc-blue); color: #fff; font-size: 14px; font-weight: 600;
            cursor: pointer; display: flex; align-items: center; justify-content: center;
            gap: 7px; transition: background 0.2s;
        }
        .btn-resolve:hover { background: #111827; }

        .textarea-c { width: 100%; padding: 9px 12px; border: 1px solid var(--border);
                      border-radius: 8px; font-size: 13px; outline: none; resize: vertical;
                      font-family: inherit; transition: border-color 0.2s; }
        .textarea-c:focus { border-color: var(--qc-blue); }

        /* Info rows */
        .info-row { display: flex; gap: 12px; align-items: flex-start; margin-bottom: 10px; }
        .info-label { font-size: 11px; font-weight: 600; color: var(--muted); min-width: 72px;
                      text-transform: uppercase; letter-spacing: 0.4px; padding-top: 1px; }
        .info-val { font-size: 13px; color: var(--text); }

        /* Responses */
        .chat-item { display: flex; gap: 10px; margin-bottom: 14px; }
        .chat-av { width: 32px; height: 32px; background: #f0f4ff; border-radius: 50%;
                   display: flex; align-items: center; justify-content: center;
                   font-size: 12px; font-weight: 700; color: var(--qc-blue); flex-shrink: 0; }
        .chat-bubble { flex: 1; background: #f8fafc; border: 1px solid var(--border);
                       border-radius: 0 8px 8px 8px; padding: 10px 13px; }
        .chat-name { font-size: 12px; font-weight: 600; color: var(--qc-blue); margin-bottom: 3px; }
        .chat-msg  { font-size: 13px; color: var(--text); line-height: 1.5; }
        .chat-time { font-size: 11px; color: var(--muted); margin-top: 5px; }

        /* Timeline */
        .timeline { position: relative; padding-left: 26px; }
        .timeline::before { content: ''; position: absolute; left: 7px; top: 6px; bottom: 6px;
                             width: 2px; background: var(--border); }
        .tl-item { position: relative; margin-bottom: 18px; }
        .tl-dot { position: absolute; left: -22px; top: 4px; width: 12px; height: 12px;
                  border-radius: 50%; border: 2px solid #fff; box-shadow: 0 0 0 2px currentColor; }
        .tl-label { font-size: 13px; font-weight: 600; color: var(--text); margin-bottom: 2px; }
        .tl-time  { font-size: 11px; color: var(--muted); margin-bottom: 5px; }
        .tl-note  { font-size: 12px; color: #4b5563; background: #f8fafc;
                    border: 1px solid var(--border); border-radius: 6px; padding: 6px 10px; }

        /* Alerts */
        .alert-ok  { background: #f0fdf4; border: 1px solid #bbf7d0; border-left: 4px solid #16a34a;
                     border-radius: 8px; padding: 11px 14px; font-size: 13px; color: #166534;
                     display: flex; align-items: center; gap: 10px; margin-bottom: 18px; }
        .alert-err { background: #fef2f2; border: 1px solid #fecaca; border-left: 4px solid var(--qc-red);
                     border-radius: 8px; padding: 11px 14px; font-size: 13px; color: #991b1b;
                     display: flex; align-items: center; gap: 10px; margin-bottom: 18px; }

        /* Send btn */
        .btn-send { background: var(--qc-blue); color: #fff; border: none; padding: 8px 18px;
                    border-radius: 7px; font-size: 13px; font-weight: 600; cursor: pointer;
                    display: inline-flex; align-items: center; gap: 6px; transition: background 0.2s; }
        .btn-send:hover { background: #111827; }

        /* Reporter card */
        .reporter-row { display: flex; align-items: center; gap: 12px; }
        .reporter-av { width: 40px; height: 40px; background: #f0f4ff; border-radius: 50%;
                       display: flex; align-items: center; justify-content: center;
                       font-size: 15px; font-weight: 700; color: var(--qc-blue); }
        .reporter-name { font-size: 14px; font-weight: 600; }
        .reporter-detail { font-size: 12px; color: var(--muted); }
    </style>
</head>
<body>

<!-- Topbar -->
<nav class="topbar">
    <div class="topbar-inner">
        <a href="/irms/portal/responder/dashboard.php" class="brand">
            <img src="/irms/assets/img/QC_LOGO_CIRCLE.png" alt="QC">
            QC-ALERTO
            <span class="role-badge">Responder</span>
        </a>
        <div class="d-flex align-items-center gap-3">
            <?php include __DIR__ . '/../../includes/notification_bell.php'; ?>
            <a href="/irms/portal/responder/dashboard.php" class="back-btn">
                <i class="bi bi-arrow-left"></i> Dashboard
            </a>
        </div>
    </div>
</nav>

<!-- Incident Title Bar -->
<div class="inc-bar">
    <div class="inc-bar-inner">
        <div class="inc-ref">
            Incident #<?= str_pad($id, 5, '0', STR_PAD_LEFT) ?>
            <?php if (!empty($incident['tracking_number'])): ?>
                &nbsp;&middot;&nbsp; <?= htmlspecialchars($incident['tracking_number']) ?>
            <?php endif; ?>
        </div>
        <h1 class="inc-title"><?= htmlspecialchars($incident['title']) ?></h1>
        <div class="d-flex flex-wrap gap-2">
            <span class="badge-sev" style="background:<?= $sevColor[$curSev] ?? '#64748b' ?>22;color:<?= $sevColor[$curSev] ?? '#64748b' ?>;">
                <i class="bi bi-circle-fill me-1" style="font-size:7px;"></i>
                <?= $sevLabel[$curSev] ?? ucfirst($curSev) ?> Severity
            </span>
            <span class="badge-st" style="<?= $stStyle[$curSt] ?? '' ?>">
                <?= $stLabel[$curSt] ?? ucfirst($curSt) ?>
            </span>
            <span class="cat-tag">
                <i class="bi bi-tag me-1"></i><?= htmlspecialchars($incident['category_name']) ?>
            </span>
        </div>
    </div>
</div>

<div class="main-wrap">

    <?php if ($success): ?>
        <div class="alert-ok">
            <i class="bi bi-check-circle-fill" style="flex-shrink:0;"></i>
            <?= htmlspecialchars($success) ?>
        </div>
    <?php elseif ($error): ?>
        <div class="alert-err">
            <i class="bi bi-exclamation-circle-fill" style="flex-shrink:0;"></i>
            <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <div class="row g-4">

        <!-- LEFT -->
        <div class="col-lg-8">

            <!-- Details -->
            <div class="card-c">
                <div class="card-h"><i class="bi bi-file-text"></i> Detalye ng Insidente</div>
                <div class="card-b">
                    <div class="info-row">
                        <span class="info-label">Lokasyon</span>
                        <span class="info-val"><?= htmlspecialchars($incident['location']) ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Petsa</span>
                        <span class="info-val"><?= date('F j, Y — g:i A', strtotime($incident['reported_at'])) ?></span>
                    </div>
                    <hr style="border-color:#f1f5f9;margin:14px 0;">
                    <p style="font-size:14px;color:#374151;line-height:1.7;margin:0;">
                        <?= nl2br(htmlspecialchars($incident['description'])) ?>
                    </p>
                </div>
            </div>

            <!-- Map -->
            <?php if ($incident['latitude'] && $incident['longitude']): ?>
            <div class="card-c">
                <div class="card-h"><i class="bi bi-geo-alt"></i> Lokasyon sa Mapa</div>
                <div class="card-b" style="padding:14px;"><div id="map"></div></div>
            </div>
            <?php endif; ?>

            <!-- Evidences (Attachments) -->
            <?php if ($attachments): ?>
            <div class="card-c">
                <div class="card-h">
                    <i class="bi bi-folder2-open"></i> Mga Ebidensya
                    <span style="font-size:11px;color:var(--muted);font-weight:400;margin-left:4px;">(<?= count($attachments) ?>)</span>
                </div>
                <div class="card-b">
                    <div class="d-flex flex-wrap gap-3 align-items-start">
                        <?php foreach ($attachments as $a): ?>
                            <?php if (str_starts_with($a['file_type'] ?? '', 'video/')): ?>
                                <div style="max-width: 250px; flex: 1 1 200px;">
                                    <video controls class="rounded border shadow-sm" style="width: 100%; max-height: 200px; background: #000;">
                                        <source src="/irms/<?= htmlspecialchars($a['file_path']) ?>" type="<?= htmlspecialchars($a['file_type']) ?>">
                                        Hindi compatible ang video sa browser mo.
                                    </video>
                                </div>
                            <?php else: ?>
                                <a href="/irms/<?= htmlspecialchars($a['file_path']) ?>" target="_blank">
                                    <img src="/irms/<?= htmlspecialchars($a['file_path']) ?>" class="photo-thumb shadow-sm" alt="photo">
                                </a>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Respond form -->
            <?php if (!in_array($curSt, ['resolved', 'closed'])): ?>
            <div class="card-c">
                <div class="card-h"><i class="bi bi-chat-dots"></i> Mag-update ng Mensahe sa Citizen</div>
                <div class="card-b">
                    <form action="/irms/ajax/update_status.php" method="POST">
                        <?= csrf_field() ?>
                        <input type="hidden" name="incident_id" value="<?= $id ?>">
                        <input type="hidden" name="action" value="respond">
                        <textarea name="message" class="textarea-c mb-3" rows="3" required
                            placeholder="I-type ang iyong update para sa citizen reporter..."></textarea>
                        <button type="submit" class="btn-send">
                            <i class="bi bi-send"></i> Ipadala
                        </button>
                    </form>
                </div>
            </div>
            <?php endif; ?>

            <!-- Previous responses -->
            <?php if ($responses): ?>
            <div class="card-c">
                <div class="card-h">
                    <i class="bi bi-chat-left-text"></i> Mga Dati nang Response
                    <span style="font-size:11px;color:var(--muted);font-weight:400;">(<?= count($responses) ?>)</span>
                </div>
                <div class="card-b">
                    <?php foreach ($responses as $r): ?>
                    <div class="chat-item">
                        <div class="chat-av"><?= strtoupper(substr($r['responder_name'], 0, 1)) ?></div>
                        <div class="chat-bubble">
                            <div class="chat-name"><?= htmlspecialchars($r['responder_name']) ?></div>
                            <div class="chat-msg"><?= nl2br(htmlspecialchars($r['message'])) ?></div>
                            <div class="chat-time">
                                <i class="bi bi-clock me-1"></i>
                                <?= date('M j, Y — g:i A', strtotime($r['responded_at'])) ?>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

        </div>

        <!-- RIGHT -->
        <div class="col-lg-4">

            <!-- Action Panel -->
            <?php if ($curSt !== 'closed'): ?>
            <div class="action-card">
                <div class="action-h"><i class="bi bi-arrow-repeat me-1" style="color:var(--qc-blue);"></i>I-update ang Status</div>
                <div class="action-b">
                <?php if ($curSt === 'pending'): ?>
                    <div style="background:#fffbeb;border:1px solid #fde68a;border-radius:8px;
                                padding:11px 13px;margin-bottom:14px;font-size:12px;color:#92400e;">
                        <i class="bi bi-exclamation-circle me-1"></i>
                        Hindi pa naka-acknowledge ang incident na ito.
                    </div>
                    <form action="/irms/ajax/update_status.php" method="POST"
                          onsubmit="return confirm('I-acknowledge ang incident na ito?')">
                        <?= csrf_field() ?>
                        <input type="hidden" name="incident_id"  value="<?= $id ?>">
                        <input type="hidden" name="action"       value="update_status">
                        <input type="hidden" name="old_status"   value="pending">
                        <input type="hidden" name="new_status"   value="in_progress">
                        <input type="hidden" name="remarks"      value="Incident acknowledged by responder.">
                        <button type="submit" class="btn-acknowledge">
                            <i class="bi bi-check2-circle"></i> I-Acknowledge
                        </button>
                    </form>

                <?php elseif ($curSt === 'in_progress'): ?>
                    <div style="background:#f0f4ff;border:1px solid #c7d2fe;border-radius:8px;
                                padding:11px 13px;margin-bottom:14px;font-size:12px;color:#3730a3;">
                        <i class="bi bi-info-circle me-1"></i>
                        In Progress — i-resolve kapag naalagaan na ang insidente.
                    </div>
                    <form action="/irms/ajax/update_status.php" method="POST"
                          onsubmit="return confirm('I-resolve na ang incident?')">
                        <?= csrf_field() ?>
                        <input type="hidden" name="incident_id" value="<?= $id ?>">
                        <input type="hidden" name="action"      value="update_status">
                        <input type="hidden" name="old_status"  value="in_progress">
                        <input type="hidden" name="new_status"  value="resolved">
                        <textarea name="remarks" class="textarea-c mb-3" rows="3" required
                                  placeholder="Findings at aksyon na ginawa... (required)"></textarea>
                        <button type="submit" class="btn-resolve">
                            <i class="bi bi-check-circle"></i> I-Resolve ang Incident
                        </button>
                    </form>

                <?php elseif ($curSt === 'resolved'): ?>
                    <div style="background:#f0fdf4;border:1px solid #bbf7d0;border-radius:8px;
                                padding:14px;text-align:center;color:#166534;">
                        <i class="bi bi-check-circle-fill" style="font-size:24px;display:block;margin-bottom:6px;"></i>
                        <div style="font-weight:600;font-size:13px;">Na-resolve na</div>
                        <div style="font-size:12px;margin-top:3px;color:#4b7c58;">Hinihintay ang admin na i-close.</div>
                    </div>
                <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Reporter -->
            <div class="card-c">
                <div class="card-h"><i class="bi bi-person"></i> Reporter</div>
                <div class="card-b">
                    <div class="reporter-row">
                        <div class="reporter-av"><?= strtoupper(substr($incident['reporter_name'], 0, 1)) ?></div>
                        <div>
                            <div class="reporter-name"><?= htmlspecialchars($incident['reporter_name']) ?></div>
                            <div class="reporter-detail"><?= htmlspecialchars($incident['reporter_email']) ?></div>
                            <?php if ($incident['reporter_phone']): ?>
                            <div class="reporter-detail"><?= htmlspecialchars($incident['reporter_phone']) ?></div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Timeline -->
            <div class="card-c">
                <div class="card-h"><i class="bi bi-clock-history"></i> Status History</div>
                <div class="card-b">
                    <?php if (empty($logs)): ?>
                        <p class="text-muted small mb-0">Walang log pa.</p>
                    <?php else:
                        $tlC = ['pending'=>'#d97706','in_progress'=>'#1e293b','resolved'=>'#16a34a','closed'=>'#6b7280'];
                    ?>
                    <div class="timeline">
                        <?php foreach ($logs as $log):
                            $c = $tlC[$log['new_status']] ?? '#64748b';
                        ?>
                        <div class="tl-item">
                            <div class="tl-dot" style="background:<?= $c ?>;color:<?= $c ?>;"></div>
                            <div class="tl-label"><?= $stLabel[$log['new_status']] ?? ucwords(str_replace('_',' ',$log['new_status'])) ?></div>
                            <div class="tl-time">
                                <?= date('M j, Y — g:i A', strtotime($log['changed_at'])) ?>
                                <?php if (!empty($log['changed_by_name'])): ?>
                                    &middot; <?= htmlspecialchars($log['changed_by_name']) ?>
                                <?php endif; ?>
                            </div>
                            <?php if ($log['remarks']): ?>
                                <div class="tl-note"><?= htmlspecialchars($log['remarks']) ?></div>
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
// Fix Leaflet broken default icons when pulling from CDN
delete L.Icon.Default.prototype._getIconUrl;
L.Icon.Default.mergeOptions({
    iconRetinaUrl: 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/images/marker-icon-2x.png',
    iconUrl: 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/images/marker-icon.png',
    shadowUrl: 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/images/marker-shadow.png'
});

var map = L.map('map', { zoomControl: true, dragging: true, scrollWheelZoom: false })
           .setView([<?= $incident['latitude'] ?>, <?= $incident['longitude'] ?>], 16);
L.tileLayer('https://{s}.basemaps.cartocdn.com/light_all/{z}/{x}/{y}{r}.png',
    { attribution: '© CARTO', maxZoom: 19 }).addTo(map);
L.marker([<?= $incident['latitude'] ?>, <?= $incident['longitude'] ?>])
 .addTo(map)
 .bindPopup('<b><?= addslashes(htmlspecialchars($incident['title'])) ?></b><br><?= addslashes(htmlspecialchars($incident['location'])) ?>')
 .openPopup();
</script>
<?php endif; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>