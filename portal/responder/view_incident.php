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
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="/irms/assets/css/theme-responder.css" rel="stylesheet">
    <script src="/irms/assets/js/theme-responder.js"></script>
    <style>
        body {
            font-family: 'Outfit', sans-serif;
            background: var(--bg-gradient);
            color: var(--text-main);
            margin: 0;
            padding-bottom: 50px;
        }

        /* ── Header ── */
        .top-nav {
            background: rgba(15, 23, 42, 0.8);
            backdrop-filter: blur(20px);
            border-bottom: 1px solid var(--glass-border);
            padding: 16px 32px;
            position: sticky; top: 0; z-index: 9999;
            display: flex; justify-content: space-between; align-items: center;
        }
        .back-btn {
            color: var(--text-dim); text-decoration: none; font-size: 14px; font-weight: 600;
            display: flex; align-items: center; gap: 8px; transition: all 0.3s;
        }
        .back-btn:hover { color: #fff; transform: translateX(-5px); }

        .incident-header {
            background: linear-gradient(to bottom, rgba(30, 41, 59, 0.5), transparent);
            padding: 48px 32px;
        }
        .inc-badge-group { display: flex; gap: 10px; margin-bottom: 16px; }
        .badge-c {
            padding: 6px 14px; border-radius: 100px; font-size: 11px; font-weight: 800;
            text-transform: uppercase; letter-spacing: 1px; display: flex; align-items: center; gap: 6px;
        }
        .badge-sev-critical { background: rgba(239, 68, 68, 0.1); color: var(--qc-red); border: 1px solid rgba(239, 68, 68, 0.2); }
        .badge-sev-high { background: rgba(251, 191, 36, 0.1); color: var(--qc-gold); border: 1px solid rgba(251, 191, 36, 0.2); }
        .badge-st-progress { background: rgba(59, 130, 246, 0.1); color: var(--qc-accent); border: 1px solid rgba(59, 130, 246, 0.2); }

        .inc-title { font-size: 32px; font-weight: 800; margin: 0; letter-spacing: -1px; }
        .inc-meta { color: var(--text-dim); font-size: 14px; margin-top: 8px; display: flex; align-items: center; gap: 20px; }

        /* ── Main Layout ── */
        .content-grid { display: grid; grid-template-columns: 1fr 400px; gap: 32px; padding: 0 32px; margin-top: -20px; }

        .glass-card {
            background: rgba(255, 255, 255, 0.02);
            backdrop-filter: blur(10px);
            border: 1px solid var(--glass-border);
            border-radius: 24px;
            padding: 32px;
            margin-bottom: 32px;
        }
        .card-title { font-size: 16px; font-weight: 700; margin-bottom: 24px; display: flex; align-items: center; gap: 10px; }
        .card-title i { color: var(--qc-accent); }

        /* ── Details ── */
        .detail-row { display: grid; grid-template-columns: 140px 1fr; gap: 16px; margin-bottom: 16px; font-size: 14px; }
        .detail-lbl { color: var(--text-dim); font-weight: 500; }
        .detail-val { font-weight: 600; color: #fff; }

        #map { height: 300px; border-radius: 20px; border: 1px solid var(--glass-border); margin-top: 20px; }

        /* ── Photo Gallery ── */
        .gallery { display: grid; grid-template-columns: repeat(auto-fill, minmax(120px, 1fr)); gap: 16px; }
        .gallery-item {
            width: 100%; aspect-ratio: 1; border-radius: 16px; object-fit: cover;
            border: 1px solid var(--glass-border); transition: all 0.3s; cursor: pointer;
        }
        .gallery-item:hover { transform: scale(1.05); border-color: var(--qc-accent); box-shadow: 0 8px 24px rgba(0,0,0,0.5); }

        /* ── Action Panel ── */
        .action-panel { position: sticky; top: 100px; }
        .btn-main {
            width: 100%; padding: 16px; border-radius: 16px; border: none;
            font-size: 14px; font-weight: 800; text-transform: uppercase; letter-spacing: 1px;
            transition: all 0.3s; display: flex; align-items: center; justify-content: center; gap: 10px;
        }
        .btn-primary-c { background: var(--qc-accent); color: #fff; box-shadow: 0 8px 20px rgba(59, 130, 246, 0.3); }
        .btn-primary-c:hover { background: #2563eb; transform: translateY(-2px); }
        .btn-gold-c { background: var(--qc-gold); color: #000; box-shadow: 0 8px 20px rgba(251, 191, 36, 0.3); }
        .btn-gold-c:hover { background: #f59e0b; transform: translateY(-2px); }

        .form-label-c { font-size: 12px; font-weight: 700; color: var(--text-dim); text-transform: uppercase; letter-spacing: 1px; margin-bottom: 12px; display: block; }
        .input-c {
            width: 100%; background: rgba(0,0,0,0.2); border: 1px solid var(--glass-border);
            padding: 12px 16px; border-radius: 12px; color: #fff; font-size: 14px;
            transition: all 0.3s;
        }
        .input-c:focus { border-color: var(--qc-accent); outline: none; background: rgba(0,0,0,0.4); }

        .upload-zone {
            border: 2px dashed var(--glass-border); border-radius: 16px;
            padding: 32px; text-align: center; transition: all 0.3s; cursor: pointer;
            background: rgba(255,255,255,0.01);
        }
        .upload-zone:hover { border-color: var(--qc-accent); background: rgba(59, 130, 246, 0.05); }
        .upload-zone i { font-size: 32px; color: var(--text-dim); margin-bottom: 12px; display: block; }

        /* ── Timeline ── */
        .timeline-c { padding-left: 20px; border-left: 1px solid var(--glass-border); }
        .tl-item-c { position: relative; padding-bottom: 24px; padding-left: 24px; }
        .tl-item-c::before { content: ''; position: absolute; left: -5.5px; top: 5px; width: 11px; height: 11px; border-radius: 50%; background: var(--qc-accent); box-shadow: 0 0 10px var(--qc-accent); }
        .tl-date { font-size: 11px; color: var(--text-dim); margin-bottom: 4px; }
        .tl-title { font-size: 14px; font-weight: 700; margin-bottom: 4px; }
        .tl-desc { font-size: 12px; color: var(--text-dim); background: rgba(255,255,255,0.02); padding: 8px 12px; border-radius: 8px; border: 1px solid var(--glass-border); }

        @media (max-width: 1024px) { .content-grid { grid-template-columns: 1fr; } .action-panel { position: static; } }
    </style>
</head>
<body>

<nav class="top-nav">
    <a href="/irms/portal/responder/dashboard.php" class="back-btn">
        <i class="bi bi-arrow-left"></i> Dashboard Center
    </a>
    <div class="d-flex align-items-center gap-4">
        <?php include __DIR__ . '/../../includes/notification_bell.php'; ?>
        <button class="theme-toggle-btn border-0 p-0" onclick="toggleTheme()" title="Toggle Dark/Light Mode" style="background:transparent; color: var(--text-dim);">
            <i class="bi bi-moon-stars-fill dark-only"></i>
            <i class="bi bi-sun-fill light-only"></i>
        </button>
        <div style="font-size: 13px; font-weight: 600; color: var(--qc-gold);">
            <i class="bi bi-shield-fill-check me-1"></i> Active Deployment
        </div>
    </div>
</nav>

<div class="incident-header">
    <div class="inc-badge-group">
        <span class="badge-c badge-sev-<?= $curSev === 'critical' || $curSev === 'high' ? 'critical' : 'high' ?>">
            <i class="bi bi-exclamation-triangle-fill"></i> <?= $sevLabel[$curSev] ?? ucfirst($curSev) ?> Severity
        </span>
        <span class="badge-c badge-st-progress">
            <i class="bi bi-activity"></i> <?= $stLabel[$curSt] ?? ucfirst($curSt) ?>
        </span>
    </div>
    <h1 class="inc-title"><?= htmlspecialchars($incident['title']) ?></h1>
    <div class="inc-meta">
        <span><i class="bi bi-hash me-1"></i> #<?= str_pad($id, 5, '0', STR_PAD_LEFT) ?></span>
        <span><i class="bi bi-calendar3 me-1"></i> <?= date('F j, Y — g:i A', strtotime($incident['reported_at'])) ?></span>
        <span><i class="bi bi-tag-fill me-1"></i> <?= htmlspecialchars($incident['category_name']) ?></span>
    </div>
</div>

<div class="content-grid">
    <!-- LEFT COLUMN -->
    <div class="main-details">
        
        <?php if ($success): ?>
            <div class="alert alert-success border-0 shadow-sm mb-4" style="background: rgba(34, 197, 94, 0.1); color: #22c55e; border-radius: 16px;">
                <i class="bi bi-check-circle-fill me-2"></i> <?= htmlspecialchars($success) ?>
            </div>
        <?php elseif ($error): ?>
            <div class="alert alert-danger border-0 shadow-sm mb-4" style="background: rgba(239, 68, 68, 0.1); color: var(--qc-red); border-radius: 16px;">
                <i class="bi bi-exclamation-octagon-fill me-2"></i> <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <div class="glass-card">
            <div class="card-title"><i class="bi bi-info-circle-fill"></i> Incident Intelligence</div>
            <div class="detail-row">
                <span class="detail-lbl">Location</span>
                <span class="detail-val"><?= htmlspecialchars($incident['location']) ?></span>
            </div>
            <div class="detail-row">
                <span class="detail-lbl">Description</span>
                <span class="detail-val" style="font-weight: 400; line-height: 1.6;"><?= nl2br(htmlspecialchars($incident['description'])) ?></span>
            </div>
            
            <?php if ($incident['latitude'] && $incident['longitude']): ?>
                <div id="map"></div>
            <?php endif; ?>
        </div>

        <div class="glass-card">
            <div class="card-title"><i class="bi bi-images"></i> Visual Intelligence (Evidence)</div>
            <?php if ($attachments): ?>
                <div class="gallery">
                    <?php foreach ($attachments as $a): ?>
                        <?php if (str_starts_with($a['file_type'] ?? '', 'video/')): ?>
                            <video controls class="gallery-item" style="width: 100%; aspect-ratio: unset; max-height: 250px; background: #000;">
                                <source src="/irms/<?= htmlspecialchars($a['file_path']) ?>" type="<?= htmlspecialchars($a['file_type']) ?>">
                            </video>
                        <?php else: ?>
                            <a href="/irms/<?= htmlspecialchars($a['file_path']) ?>" target="_blank">
                                <img src="/irms/<?= htmlspecialchars($a['file_path']) ?>" class="gallery-item">
                            </a>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p class="text-center py-4 text-muted small">No visual attachments provided by the reporter.</p>
            <?php endif; ?>
        </div>

        <div class="glass-card">
            <div class="card-title"><i class="bi bi-clock-history"></i> Operational Timeline</div>
            <div class="timeline-c">
                <?php foreach ($logs as $log): ?>
                <div class="tl-item-c">
                    <div class="tl-date"><?= date('M d, Y &middot; g:i A', strtotime($log['changed_at'])) ?></div>
                    <div class="tl-title"><?= $stLabel[$log['new_status']] ?? ucwords($log['new_status']) ?></div>
                    <div class="tl-desc">
                        <i class="bi bi-person-fill me-1 opacity-50"></i> <?= htmlspecialchars($log['changed_by_name'] ?: 'System Alert') ?>
                        <?php if ($log['remarks']): ?>
                            <p class="mt-2 mb-0" style="color: #fff;"><?= htmlspecialchars($log['remarks']) ?></p>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- RIGHT COLUMN: ACTION PANEL -->
    <div class="action-panel">
        <div class="glass-card" style="border-top: 4px solid var(--qc-accent);">
            <div class="card-title"><i class="bi bi-lightning-fill"></i> Responder Actions</div>
            
            <?php if ($curSt === 'pending'): ?>
                <div class="alert mb-4" style="background: rgba(251, 191, 36, 0.05); color: var(--qc-gold); border: 1px solid rgba(251, 191, 36, 0.1); font-size: 13px; border-radius: 12px;">
                    <i class="bi bi-info-circle-fill me-2"></i> This incident requires immediate acknowledgement before deployment.
                </div>
                <form action="/irms/ajax/update_status.php" method="POST">
                    <?= csrf_field() ?>
                    <input type="hidden" name="incident_id"  value="<?= $id ?>">
                    <input type="hidden" name="action"       value="update_status">
                    <input type="hidden" name="old_status"   value="pending">
                    <input type="hidden" name="new_status"   value="in_progress">
                    <input type="hidden" name="remarks"      value="Responder has acknowledged and is preparing for deployment.">
                    <button type="submit" class="btn-main btn-gold-c">
                        <i class="bi bi-check-circle-fill"></i> Acknowledge & Deploy
                    </button>
                </form>

            <?php elseif ($curSt === 'in_progress'): ?>
                <form action="/irms/ajax/update_status.php" method="POST" enctype="multipart/form-data">
                    <?= csrf_field() ?>
                    <input type="hidden" name="incident_id" value="<?= $id ?>">
                    <input type="hidden" name="action"      value="update_status">
                    <input type="hidden" name="old_status"  value="in_progress">
                    <input type="hidden" name="new_status"  value="resolved">
                    
                    <div class="mb-4">
                        <label class="form-label-c">Operational Findings</label>
                        <textarea name="remarks" class="input-c" rows="4" required placeholder="Describe the actions taken and findings at the scene..."></textarea>
                    </div>

                    <div class="mb-4">
                        <label class="form-label-c">Proof of Resolution</label>
                        <label class="upload-zone" id="upload-label">
                            <i class="bi bi-cloud-upload-fill"></i>
                            <div style="font-size: 14px; font-weight: 700; color: #fff;">Upload Evidence</div>
                            <div style="font-size: 11px; color: var(--text-dim);">Photo/Video of the resolved scene</div>
                            <input type="file" name="evidence[]" id="file-input" multiple required accept="image/*,video/*" style="display: none;">
                        </label>
                        <div id="file-list" class="mt-2 small text-dim"></div>
                    </div>

                    <button type="submit" class="btn-main btn-primary-c">
                        <i class="bi bi-shield-check"></i> Submit Resolution
                    </button>
                </form>

            <?php elseif ($curSt === 'resolved'): ?>
                <div class="text-center py-4">
                    <i class="bi bi-check-circle-fill" style="font-size: 48px; color: #22c55e; display: block; margin-bottom: 16px;"></i>
                    <h5 style="font-weight: 800;">Mission Resolved</h5>
                    <p style="color: var(--text-dim); font-size: 14px;">Resolution report submitted. Awaiting administrative closure.</p>
                </div>
            <?php else: ?>
                <div class="text-center py-4 opacity-50">
                    <i class="bi bi-lock-fill" style="font-size: 48px; color: var(--text-dim); display: block; margin-bottom: 16px;"></i>
                    <h5 style="font-weight: 800;">Case Closed</h5>
                    <p style="color: var(--text-dim); font-size: 14px;">This incident is archived and read-only.</p>
                </div>
            <?php endif; ?>
        </div>

        <div class="glass-card">
            <div class="card-title"><i class="bi bi-person-fill"></i> Citizen Contact</div>
            <div style="display: flex; align-items: center; gap: 15px;">
                <div style="width: 48px; height: 48px; background: var(--qc-accent); border-radius: 12px; display: flex; align-items: center; justify-content: center; font-weight: 800; font-size: 18px;">
                    <?= strtoupper(substr($incident['reporter_name'], 0, 1)) ?>
                </div>
                <div>
                    <div style="font-weight: 700;"><?= htmlspecialchars($incident['reporter_name']) ?></div>
                    <div style="font-size: 12px; color: var(--text-dim);"><?= htmlspecialchars($incident['reporter_phone'] ?: 'No Phone Provided') ?></div>
                </div>
            </div>
            <hr style="border-color: var(--glass-border);">
            <form action="/irms/ajax/update_status.php" method="POST">
                <?= csrf_field() ?>
                <input type="hidden" name="incident_id" value="<?= $id ?>">
                <input type="hidden" name="action" value="respond">
                <label class="form-label-c">Send Update to Citizen</label>
                <textarea name="message" class="input-c mb-3" rows="3" required placeholder="Type a message to the reporter..."></textarea>
                <button type="submit" class="btn-main" style="background: var(--glass); border: 1px solid var(--glass-border); color: #fff;">
                    <i class="bi bi-send-fill"></i> Send Intelligence
                </button>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.min.js"></script>
<script>
<?php if ($incident['latitude'] && $incident['longitude']): ?>
    var map = L.map('map', { zoomControl: false }).setView([<?= $incident['latitude'] ?>, <?= $incident['longitude'] ?>], 16);
    
    // Fix marker icon issue
    delete L.Icon.Default.prototype._getIconUrl;
    L.Icon.Default.mergeOptions({
        iconRetinaUrl: 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/images/marker-icon-2x.png',
        iconUrl: 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/images/marker-icon.png',
        shadowUrl: 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/images/marker-shadow.png',
    });

    const darkTiles = 'https://{s}.basemaps.cartocdn.com/dark_all/{z}/{x}/{y}{r}.png';
    const lightTiles = 'https://{s}.basemaps.cartocdn.com/rastertiles/voyager/{z}/{x}/{y}{r}.png';
    
    let currentTheme = document.documentElement.getAttribute('data-theme') || 'dark';
    let tileLayer = L.tileLayer(currentTheme === 'dark' ? darkTiles : lightTiles, { 
        attribution: '© CARTO' 
    }).addTo(map);

    L.marker([<?= $incident['latitude'] ?>, <?= $incident['longitude'] ?>]).addTo(map);

    window.addEventListener('themeChanged', function(e) {
        map.removeLayer(tileLayer);
        tileLayer = L.tileLayer(e.detail.theme === 'dark' ? darkTiles : lightTiles, { 
            attribution: '© CARTO' 
        }).addTo(map);
    });
<?php endif; ?>

    const fileInput = document.getElementById('file-input');
    if(fileInput) {
        fileInput.addEventListener('change', function() {
            const list = document.getElementById('file-list');
            list.innerHTML = '';
            for(let f of this.files) {
                list.innerHTML += `<div><i class="bi bi-paperclip"></i> ${f.name}</div>`;
            }
            document.getElementById('upload-label').style.borderColor = 'var(--qc-accent)';
        });
    }
</script>
</body>
</html>
</body>
</html>