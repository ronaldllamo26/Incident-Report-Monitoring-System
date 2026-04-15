<?php
require_once __DIR__ . '/../includes/auth.php';
requireRole('citizen');
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../models/Incident.php';

$user     = currentUser();
$id       = (int)($_GET['id'] ?? 0);
$model    = new Incident();
$incident = $model->getById($id);

if (!$incident || $incident['reporter_id'] != $user['id']) {
    header('Location: /irms/citizen/dashboard.php'); exit;
}

$attachments = $model->getAttachments($id);
$logs        = $model->getStatusLogs($id);
$responses   = $model->getResponses($id);
$feedback    = $model->getFeedback($id);
$canRate     = in_array($incident['status'], ['resolved','closed']) && !$feedback;

$curSt  = $incident['status'];
$curSev = $incident['severity'];

$stLabel = ['pending'=>'Pending','in_progress'=>'In Progress','resolved'=>'Resolved','closed'=>'Closed'];
$sevColor = ['low'=>'#16a34a','medium'=>'#d97706','high'=>'#ea580c','critical'=>'#CE1126'];
$sevBg    = ['low'=>'#f0fdf4','medium'=>'#fffbeb','high'=>'#fff7ed','critical'=>'#fef2f2'];
$sevLabel = ['low'=>'Low','medium'=>'Medium','high'=>'High','critical'=>'Critical'];

// ── Build timeline steps with timestamps from logs ────────────
$steps = [
    ['key' => 'submitted',    'label' => 'Naisumite',   'desc' => 'Natanggap ang iyong report'],
    ['key' => 'pending',      'label' => 'Nakarehistro', 'desc' => 'Nirerepaso ng admin'],
    ['key' => 'in_progress',  'label' => 'Ini-aksyon',  'desc' => 'Galaw na ang responder'],
    ['key' => 'resolved',     'label' => 'Nalutas',     'desc' => 'Natugunan na ang insidente'],
    ['key' => 'closed',       'label' => 'Sarado',      'desc' => 'Opisyal na naisara'],
];

// Map log timestamps
$logByStatus = [];
foreach ($logs as $l) {
    $logByStatus[$l['new_status']] = $l;
}
// "Submitted" is always the incident creation time
$logByStatus['submitted'] = ['changed_at' => $incident['reported_at'], 'remarks' => null, 'changed_by_name' => $user['name']];

// Determine step completion
$statusOrder = ['submitted' => 0, 'pending' => 1, 'in_progress' => 2, 'resolved' => 3, 'closed' => 4];
$curOrder    = $statusOrder[$curSt] ?? 1;
// "submitted" is always done (order 0), current status is active
for ($i = 0; $i < count($steps); $i++) {
    $sKey = $steps[$i]['key'];
    $sOrd = $statusOrder[$sKey] ?? $i;
    if ($sOrd < $curOrder) {
        $steps[$i]['state'] = 'done';
    } elseif ($sKey === $curSt || ($sKey === 'submitted' && $curOrder === 0)) {
        $steps[$i]['state'] = 'active';
    } elseif ($sKey === 'submitted') {
        $steps[$i]['state'] = 'done'; // always done
    } else {
        $steps[$i]['state'] = 'upcoming';
    }
    $steps[$i]['log'] = $logByStatus[$sKey] ?? null;
}
// Fix: submitted is always done
$steps[0]['state'] = 'done';
// Current status is always active (unless closed = done too)
if ($curSt === 'closed') {
    foreach ($steps as &$s) { $s['state'] = 'done'; }
    unset($s);
} else {
    foreach ($steps as &$s) {
        if ($s['key'] === $curSt) { $s['state'] = 'active'; break; }
    }
    unset($s);
}
?>
<!DOCTYPE html>
<html lang="fil">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Report #<?= str_pad($id,5,'0',STR_PAD_LEFT) ?> — QC-ALERTO</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root { --slate:#1e293b; --red:#CE1126; --bg:#f4f6f9; --border:#e2e8f0; --text:#1e293b; --muted:#64748b; }
        * { box-sizing:border-box; }
        body { font-family:'Inter',sans-serif; background:var(--bg); margin:0; color:var(--text); }

        /* Topbar */
        .topbar { background:var(--slate); border-bottom:3px solid var(--red);
                  position:sticky; top:0; z-index:100; }
        .topbar-inner { max-width:1100px; margin:0 auto; padding:0 24px;
                        display:flex; align-items:center; justify-content:space-between; height:56px; }
        .brand { font-size:16px; font-weight:700; color:#fff; text-decoration:none;
                 display:flex; align-items:center; gap:10px; }
        .brand img { height:28px; width:28px; object-fit:contain; }
        .back-btn { font-size:12px; color:rgba(255,255,255,0.8); text-decoration:none;
                    display:flex; align-items:center; gap:5px; padding:5px 12px;
                    border:1px solid rgba(255,255,255,0.2); border-radius:6px; transition:all .2s; }
        .back-btn:hover { color:#fff; background:rgba(255,255,255,0.1); }

        /* Title bar */
        .title-bar { background:#fff; border-bottom:1px solid var(--border); padding:18px 0; }
        .title-bar-inner { max-width:1100px; margin:0 auto; padding:0 24px; }
        .inc-ref { font-size:11px; color:var(--muted); font-weight:600; text-transform:uppercase;
                   letter-spacing:.5px; margin-bottom:4px; }
        .inc-title { font-size:19px; font-weight:700; color:var(--text); margin:0 0 10px; }

        .main-wrap { max-width:1100px; margin:0 auto; padding:24px; }

        /* ── Progress Tracker ──────────────────────────────── */
        .tracker-card { background:#fff; border:1px solid var(--border); border-radius:12px;
                        padding:28px 32px; margin-bottom:24px; }
        .tracker-title { font-size:14px; font-weight:600; color:var(--text); margin-bottom:28px;
                         display:flex; align-items:center; gap:8px; }
        .tracker-title i { color:var(--slate); }

        .steps { display:flex; position:relative; }
        .steps::before {
            content:''; position:absolute; top:20px; left:0; right:0; height:2px;
            background:var(--border); z-index:0;
        }
        .step { flex:1; display:flex; flex-direction:column; align-items:center; position:relative; z-index:1; }

        /* Progress line fill */
        .progress-fill {
            position:absolute; top:20px; left:0; height:2px;
            background:var(--slate); z-index:0; transition:width .5s;
        }

        /* Step dot */
        .step-dot {
            width:40px; height:40px; border-radius:50%; border:2px solid var(--border);
            background:#fff; display:flex; align-items:center; justify-content:center;
            font-size:16px; margin-bottom:10px; transition:all .3s; flex-shrink:0;
        }
        .step.done .step-dot { background:var(--slate); border-color:var(--slate); color:#fff; }
        .step.active .step-dot {
            background:#fff; border-color:var(--slate); border-width:3px;
            color:var(--slate); box-shadow:0 0 0 4px rgba(30,41,59,0.10);
        }
        .step.active .step-dot::after {
            content:''; width:10px; height:10px; border-radius:50%; background:var(--slate);
            position:absolute;
        }
        .step.upcoming .step-dot { background:#f8fafc; color:#cbd5e1; border-color:#e2e8f0; }

        /* Step labels */
        .step-label { font-size:12px; font-weight:600; color:var(--muted); text-align:center; margin-bottom:2px; }
        .step.done   .step-label { color:var(--slate); }
        .step.active .step-label { color:var(--slate); font-weight:700; }
        .step-time { font-size:10px; color:var(--muted); text-align:center; }
        .step.active .step-time { color:#CE1126; font-weight:600; }

        /* Mobile steps */
        @media (max-width:640px) {
            .steps { flex-direction:column; align-items:flex-start; gap:0; }
            .steps::before { display:none; }
            .progress-fill { display:none; }
            .step { flex-direction:row; align-items:flex-start; gap:14px; width:100%; padding-bottom:20px; }
            .step:last-child { padding-bottom:0; }
            .step-dot { flex-shrink:0; margin-bottom:0; margin-top:2px; width:36px; height:36px; font-size:14px; }
            .step-info { text-align:left; }
            .step-label { text-align:left; }
            .step-time { text-align:left; }
            /* Vertical line */
            .step::after { content:''; position:absolute; left:17px; top:42px; width:2px;
                           height:calc(100% - 42px); background:var(--border); }
            .step.done::after { background:var(--slate); }
            .step:last-child::after { display:none; }
        }

        /* Current status banner */
        .status-banner {
            border-radius:8px; padding:12px 16px; margin-top:20px;
            font-size:13px; font-weight:500;
            display:flex; align-items:center; gap:10px;
        }

        /* Cards */
        .card-c { background:#fff; border:1px solid var(--border); border-radius:10px;
                  margin-bottom:18px; overflow:hidden; }
        .card-h { padding:12px 18px; border-bottom:1px solid #f1f5f9;
                  font-size:13px; font-weight:600; color:var(--text);
                  display:flex; align-items:center; gap:8px; background:#fafbfc; }
        .card-h i { color:var(--slate); }
        .card-b { padding:18px; }

        #map { height:200px; border-radius:8px; }

        .photo-grid { display:flex; flex-wrap:wrap; gap:8px; }
        .photo-thumb { width:76px; height:76px; object-fit:cover; border-radius:8px;
                       border:1px solid var(--border); cursor:pointer; transition:transform .15s; }
        .photo-thumb:hover { transform:scale(1.05); border-color:var(--slate); }

        /* Response bubbles */
        .chat-item { display:flex; gap:10px; margin-bottom:14px; }
        .chat-av { width:30px; height:30px; background:#f0f4f8; border-radius:50%;
                   display:flex; align-items:center; justify-content:center;
                   font-size:11px; font-weight:700; color:var(--slate); flex-shrink:0; }
        .chat-bubble { flex:1; background:#f8fafc; border:1px solid var(--border);
                       border-radius:0 8px 8px 8px; padding:9px 12px; }
        .chat-name { font-size:11px; font-weight:600; color:var(--slate); margin-bottom:3px; }
        .chat-msg  { font-size:13px; color:var(--text); line-height:1.5; }
        .chat-time { font-size:10px; color:var(--muted); margin-top:4px; }
        .no-response { font-size:13px; color:var(--muted); display:flex; align-items:center;
                       gap:8px; padding:12px; background:#f8fafc; border-radius:8px;
                       border:1px solid var(--border); }

        /* Tracking chip */
        .tracking-chip { font-family:monospace; font-size:19px; font-weight:700;
                         letter-spacing:2px; color:var(--text); display:block;
                         text-align:center; margin:6px 0; }

        /* Rating */
        .star-btn { font-size:26px; cursor:pointer; color:#e2e8f0; transition:color .1s;
                    background:none; border:none; padding:0 2px; }
        .star-btn.lit { color:#f59e0b; }

        /* Info rows */
        .info-row { display:flex; gap:10px; align-items:flex-start; margin-bottom:9px; font-size:13px; }
        .info-lbl { font-size:10px; font-weight:600; color:var(--muted); text-transform:uppercase;
                    letter-spacing:.4px; min-width:64px; padding-top:2px; }
        .info-val { color:var(--text); }

        /* Responder pill */
        .responder-av { width:36px; height:36px; background:#f0f4f8; border-radius:50%;
                        display:flex; align-items:center; justify-content:center;
                        font-weight:700; font-size:13px; color:var(--slate); flex-shrink:0; }

        /* Btn */
        .btn-track { display:inline-flex; align-items:center; gap:6px; font-size:12px;
                     font-weight:600; color:var(--slate); border:1px solid var(--border);
                     border-radius:6px; padding:6px 14px; text-decoration:none; transition:all .2s; }
        .btn-track:hover { border-color:var(--slate); background:#f8fafc; color:var(--slate); }
        .btn-submit-rate { background:var(--slate); color:#fff; border:none; padding:8px 18px;
                           border-radius:7px; font-size:13px; font-weight:600; cursor:pointer;
                           display:inline-flex; align-items:center; gap:6px; transition:opacity .2s; }
        .btn-submit-rate:hover { opacity:.88; }
    </style>
</head>
<body>

<!-- Topbar -->
<nav class="topbar">
    <div class="topbar-inner">
        <a href="/irms/citizen/dashboard.php" class="brand">
            <img src="/irms/assets/img/QC_LOGO_CIRCLE.png" alt="QC">
            QC-ALERTO
        </a>
        <a href="/irms/citizen/dashboard.php" class="back-btn">
            <i class="bi bi-arrow-left"></i> Dashboard
        </a>
    </div>
</nav>

<!-- Title Bar -->
<div class="title-bar">
    <div class="title-bar-inner">
        <div class="inc-ref">
            Report #<?= str_pad($id,5,'0',STR_PAD_LEFT) ?>
            <?php if ($incident['tracking_number']): ?>
                &nbsp;&middot;&nbsp; <?= htmlspecialchars($incident['tracking_number']) ?>
            <?php endif; ?>
        </div>
        <h1 class="inc-title"><?= htmlspecialchars($incident['title']) ?></h1>
        <div class="d-flex flex-wrap gap-2">
            <span style="background:<?= $sevBg[$curSev]??'#f1f5f9' ?>;color:<?= $sevColor[$curSev]??'#64748b' ?>;
                         padding:3px 10px;border-radius:5px;font-size:11px;font-weight:600;">
                <i class="bi bi-circle-fill me-1" style="font-size:7px;"></i>
                <?= $sevLabel[$curSev]??ucfirst($curSev) ?> Severity
            </span>
            <?php
            $stBg = ['pending'=>'#fef3c7;color:#92400e','in_progress'=>'#dbeafe;color:#1e40af',
                     'resolved'=>'#dcfce7;color:#166534','closed'=>'#f3f4f6;color:#4b5563'];
            $stBgStr = $stBg[$curSt] ?? '#f3f4f6;color:#4b5563';
            ?>
            <span style="background:<?= $stBgStr ?>;padding:3px 10px;border-radius:5px;font-size:11px;font-weight:600;">
                <?= $stLabel[$curSt] ?? ucfirst($curSt) ?>
            </span>
            <span style="background:#f1f5f9;color:#374151;padding:3px 10px;border-radius:5px;font-size:11px;font-weight:500;">
                <i class="bi bi-tag me-1"></i><?= htmlspecialchars($incident['category_name']) ?>
            </span>
        </div>
    </div>
</div>

<div class="main-wrap">
<div class="row g-4">
<div class="col-lg-8">

    <!-- ── Status Progress Tracker ─────────────────────────── -->
    <div class="tracker-card">
        <div class="tracker-title">
            <i class="bi bi-map"></i> Status ng Inyong Report
        </div>

        <div style="position:relative;">
            <!-- Progress line fill -->
            <?php
            $doneCount = count(array_filter($steps, fn($s) => $s['state']==='done'));
            $totalSteps = count($steps);
            $fillPct = $totalSteps > 1 ? round(($doneCount / ($totalSteps - 1)) * 100) : 0;
            if ($curSt === 'closed') $fillPct = 100;
            ?>
            <div class="progress-fill" style="width:<?= $fillPct ?>%;"></div>

            <div class="steps">
            <?php foreach ($steps as $step):
                $isActive = $step['state'] === 'active';
                $isDone   = $step['state'] === 'done';
                $log      = $step['log'];
                $timeStr  = $log ? date('M j, g:i A', strtotime($log['changed_at'])) : '';
            ?>
                <div class="step <?= $step['state'] ?>">
                    <div class="step-dot">
                        <?php if ($isDone): ?>
                            <i class="bi bi-check-lg"></i>
                        <?php elseif ($isActive): ?>
                            <!-- Hollow active state — CSS handles it -->
                        <?php else: ?>
                            <i class="bi bi-dash-lg" style="font-size:12px;"></i>
                        <?php endif; ?>
                    </div>
                    <div class="step-info">
                        <div class="step-label"><?= $step['label'] ?></div>
                        <?php if ($timeStr): ?>
                            <div class="step-time"><?= $timeStr ?></div>
                        <?php elseif ($isActive): ?>
                            <div class="step-time" style="color:var(--slate);">Kasalukuyan</div>
                        <?php else: ?>
                            <div class="step-time">—</div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
            </div>
        </div>

        <!-- Current status context message -->
        <?php
        $banners = [
            'pending'     => ['bg'=>'#fffbeb','border'=>'#fde68a','color'=>'#92400e','icon'=>'bi-hourglass-split',
                              'msg'=>'Ang iyong report ay naghihintay ng pagsusuri ng admin at pagtatalaga ng responder.'],
            'in_progress' => ['bg'=>'#f0f4ff','border'=>'#c7d2fe','color'=>'#3730a3','icon'=>'bi-arrow-repeat',
                              'msg'=>'May responder na na nag-iimbestigahe ng iyong report. Abangan ang update.'],
            'resolved'    => ['bg'=>'#f0fdf4','border'=>'#bbf7d0','color'=>'#166534','icon'=>'bi-check-circle',
                              'msg'=>'Natugunan na ang inyong report! Maaari ka nang mag-rate ng serbisyo.'],
            'closed'      => ['bg'=>'#f8fafc','border'=>'#e2e8f0','color'=>'#374151','icon'=>'bi-archive',
                              'msg'=>'Opisyal nang naisara ang report na ito.'],
        ];
        $b = $banners[$curSt] ?? null;
        ?>
        <?php if ($b): ?>
        <div class="status-banner mt-4"
             style="background:<?= $b['bg'] ?>;border:1px solid <?= $b['border'] ?>;color:<?= $b['color'] ?>;">
            <i class="bi <?= $b['icon'] ?>" style="font-size:18px;flex-shrink:0;"></i>
            <span><?= $b['msg'] ?></span>
        </div>
        <?php endif; ?>

        <!-- Remarks from the latest log that has a remark -->
        <?php
        $latestRemark = null;
        foreach (array_reverse($logs) as $l) {
            if (!empty($l['remarks'])) { $latestRemark = $l; break; }
        }
        ?>
        <?php if ($latestRemark): ?>
        <div style="background:#f8fafc;border:1px solid var(--border);border-radius:8px;
                    padding:12px 14px;margin-top:12px;">
            <div style="font-size:11px;font-weight:600;color:var(--muted);text-transform:uppercase;
                        letter-spacing:.4px;margin-bottom:5px;">Pinakabagong Update</div>
            <div style="font-size:13px;color:var(--text);">
                "<?= htmlspecialchars($latestRemark['remarks']) ?>"
            </div>
            <div style="font-size:11px;color:var(--muted);margin-top:4px;">
                <?= htmlspecialchars($latestRemark['changed_by_name'] ?? 'System') ?>
                &middot; <?= date('M j, Y g:i A', strtotime($latestRemark['changed_at'])) ?>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- ── Incident Details ─────────────────────────────────── -->
    <div class="card-c">
        <div class="card-h"><i class="bi bi-file-text"></i> Detalye ng Insidente</div>
        <div class="card-b">
            <div class="info-row">
                <span class="info-lbl">Lokasyon</span>
                <span class="info-val"><?= htmlspecialchars($incident['location']) ?></span>
            </div>
            <div class="info-row">
                <span class="info-lbl">Petsa</span>
                <span class="info-val"><?= date('F j, Y — g:i A', strtotime($incident['reported_at'])) ?></span>
            </div>
            <hr style="border-color:#f1f5f9;margin:12px 0;">
            <p style="font-size:14px;color:#374151;line-height:1.7;margin:0;">
                <?= nl2br(htmlspecialchars($incident['description'])) ?>
            </p>
        </div>
    </div>

    <!-- ── Map ─────────────────────────────────────────────── -->
    <?php if ($incident['latitude'] && $incident['longitude']): ?>
    <div class="card-c">
        <div class="card-h"><i class="bi bi-geo-alt"></i> Lokasyon sa Mapa</div>
        <div class="card-b" style="padding:14px;"><div id="map"></div></div>
    </div>
    <?php endif; ?>

    <!-- ── Attachments ─────────────────────────────────────── -->
    <?php if ($attachments): ?>
    <div class="card-c">
        <div class="card-h">
            <i class="bi bi-images"></i> Mga Larawan
            <span style="font-size:11px;color:var(--muted);font-weight:400;">(<?= count($attachments) ?>)</span>
        </div>
        <div class="card-b">
            <div class="photo-grid">
                <?php foreach ($attachments as $a): ?>
                    <a href="/irms/<?= htmlspecialchars($a['file_path']) ?>" target="_blank">
                        <img src="/irms/<?= htmlspecialchars($a['file_path']) ?>" class="photo-thumb" alt="">
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- ── Responses ───────────────────────────────────────── -->
    <div class="card-c">
        <div class="card-h">
            <i class="bi bi-chat-left-text"></i> Mga Update mula sa Responder
            <?php if ($responses): ?>
                <span style="font-size:11px;color:var(--muted);font-weight:400;">(<?= count($responses) ?>)</span>
            <?php endif; ?>
        </div>
        <div class="card-b">
            <?php if (empty($responses)): ?>
                <div class="no-response">
                    <i class="bi bi-clock" style="font-size:16px;flex-shrink:0;"></i>
                    Wala pang response. Abangan ang update mula sa aming responders.
                </div>
            <?php else: ?>
                <div class="d-flex flex-column">
                    <?php foreach ($responses as $r): ?>
                    <div class="chat-item">
                        <div class="chat-av"><?= strtoupper(substr($r['responder_name'],0,1)) ?></div>
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
            <?php endif; ?>
        </div>
    </div>

    <!-- ── Rating Form ─────────────────────────────────────── -->
    <?php if ($canRate): ?>
    <div class="card-c" style="border-left:4px solid #f59e0b;">
        <div class="card-h"><i class="bi bi-star" style="color:#f59e0b;"></i> I-rate ang Serbisyo</div>
        <div class="card-b">
            <p style="font-size:13px;color:var(--muted);margin:0 0 14px;">
                Na-resolve na ang iyong report. Paano mo i-rate ang response ng aming responder?
            </p>
            <form action="/irms/ajax/submit_feedback.php" method="POST">
                <?= csrf_field() ?>
                <input type="hidden" name="incident_id" value="<?= $id ?>">
                <div class="d-flex gap-1 mb-3" id="star-row">
                    <?php for ($s = 1; $s <= 5; $s++): ?>
                        <input type="radio" name="rating" id="r<?= $s ?>" value="<?= $s ?>" class="d-none" required>
                        <button type="button" class="star-btn" id="sb<?= $s ?>"
                                onmouseover="hlStars(<?= $s ?>)" onmouseout="resetStars()"
                                onclick="pickStar(<?= $s ?>)">
                            <i class="bi bi-star-fill"></i>
                        </button>
                    <?php endfor; ?>
                </div>
                <textarea name="comment" rows="2"
                          style="width:100%;padding:9px 12px;border:1px solid var(--border);
                                 border-radius:8px;font-size:13px;outline:none;font-family:inherit;
                                 margin-bottom:12px;resize:vertical;"
                          placeholder="Anong masasabi mo sa response? (optional)"></textarea>
                <button type="submit" class="btn-submit-rate">
                    <i class="bi bi-send"></i> I-submit ang Rating
                </button>
            </form>
        </div>
    </div>

    <?php elseif ($feedback): ?>
    <div class="card-c" style="border-left:4px solid #16a34a;">
        <div class="card-h"><i class="bi bi-star-fill" style="color:#f59e0b;"></i> Iyong Rating</div>
        <div class="card-b">
            <div class="d-flex align-items-center gap-3 mb-2">
                <div class="d-flex gap-1">
                    <?php for ($s = 1; $s <= 5; $s++): ?>
                        <i class="bi bi-star-fill"
                           style="font-size:22px;color:<?= $s <= $feedback['rating'] ? '#f59e0b' : '#e2e8f0' ?>;"></i>
                    <?php endfor; ?>
                </div>
                <span style="font-size:13px;color:var(--muted);font-weight:500;">
                    <?= $feedback['rating'] ?> / 5
                </span>
            </div>
            <?php if ($feedback['comment']): ?>
                <p style="font-size:13px;color:var(--muted);font-style:italic;margin:0;">
                    "<?= htmlspecialchars($feedback['comment']) ?>"
                </p>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

</div><!-- /col-lg-8 -->

<!-- RIGHT -->
<div class="col-lg-4">

    <!-- Tracking Number -->
    <?php if ($incident['tracking_number']): ?>
    <div class="card-c">
        <div class="card-h"><i class="bi bi-qr-code"></i> Tracking Number</div>
        <div class="card-b" style="text-align:center;">
            <span class="tracking-chip"><?= htmlspecialchars($incident['tracking_number']) ?></span>
            <a href="/irms/public/track.php?tracking=<?= urlencode($incident['tracking_number']) ?>"
               class="btn-track mt-2" style="display:inline-flex;margin:0 auto;">
                <i class="bi bi-search"></i> I-track sa Public Portal
            </a>
        </div>
    </div>
    <?php endif; ?>

    <!-- Assigned Responder -->
    <div class="card-c">
        <div class="card-h"><i class="bi bi-person-badge"></i> Nakatalagang Responder</div>
        <div class="card-b">
            <?php if ($incident['responder_name']): ?>
                <div class="d-flex align-items-center gap-10" style="gap:12px;">
                    <div class="responder-av"><?= strtoupper(substr($incident['responder_name'],0,1)) ?></div>
                    <div>
                        <div style="font-size:13px;font-weight:600;"><?= htmlspecialchars($incident['responder_name']) ?></div>
                        <div style="font-size:11px;color:var(--muted);">Incident Responder</div>
                    </div>
                </div>
            <?php else: ?>
                <div class="no-response">
                    <i class="bi bi-hourglass-split" style="font-size:16px;flex-shrink:0;"></i>
                    Naghihintay pa ng assignment.
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Detailed Status Log -->
    <?php if (!empty($logs)): ?>
    <div class="card-c">
        <div class="card-h"><i class="bi bi-clock-history"></i> Detalyadong History</div>
        <div class="card-b">
            <?php
            $tlColors=['pending'=>'#d97706','in_progress'=>'var(--slate)','resolved'=>'#16a34a','closed'=>'#6b7280'];
            $allLogs = array_merge(
                [['new_status'=>'submitted','changed_at'=>$incident['reported_at'],'changed_by_name'=>$user['name'],'remarks'=>null]],
                $logs
            );
            ?>
            <div style="position:relative;padding-left:22px;">
                <div style="position:absolute;left:7px;top:6px;bottom:6px;width:2px;background:var(--border);"></div>
                <?php foreach ($allLogs as $l):
                    $lc = $tlColors[$l['new_status']] ?? 'var(--slate)';
                    $lLabel = $stLabel[$l['new_status']] ?? ucwords(str_replace('_',' ',$l['new_status']));
                    if ($l['new_status']==='submitted') { $lLabel='Naisumite'; $lc='var(--slate)'; }
                ?>
                <div style="position:relative;margin-bottom:18px;">
                    <div style="position:absolute;left:-18px;top:3px;width:12px;height:12px;
                                border-radius:50%;background:<?= $lc ?>;border:2px solid #fff;
                                box-shadow:0 0 0 2px <?= $lc ?>;"></div>
                    <div style="font-size:13px;font-weight:600;color:var(--text);"><?= $lLabel ?></div>
                    <div style="font-size:11px;color:var(--muted);">
                        <?= date('M j, Y — g:i A', strtotime($l['changed_at'])) ?>
                        <?php if (!empty($l['changed_by_name'])): ?>
                            &middot; <?= htmlspecialchars($l['changed_by_name']) ?>
                        <?php endif; ?>
                    </div>
                    <?php if (!empty($l['remarks'])): ?>
                    <div style="font-size:12px;color:#4b5563;background:#f8fafc;border:1px solid var(--border);
                                border-radius:6px;padding:5px 9px;margin-top:5px;">
                        <?= htmlspecialchars($l['remarks']) ?>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>

</div>
</div><!-- /row -->
</div><!-- /main-wrap -->

<?php if ($incident['latitude'] && $incident['longitude']): ?>
<script src="https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.min.js"></script>
<script>
var map = L.map('map',{zoomControl:true,dragging:true,scrollWheelZoom:false})
           .setView([<?= $incident['latitude'] ?>,<?= $incident['longitude'] ?>],16);
L.tileLayer('https://{s}.basemaps.cartocdn.com/light_all/{z}/{x}/{y}{r}.png',
    {attribution:'© CARTO',maxZoom:19}).addTo(map);
L.marker([<?= $incident['latitude'] ?>,<?= $incident['longitude'] ?>])
 .addTo(map)
 .bindPopup('<b><?= addslashes(htmlspecialchars($incident['title'])) ?></b>')
 .openPopup();
</script>
<?php endif; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
var selectedStar = 0;
function hlStars(n) {
    for (var i=1;i<=5;i++)
        document.getElementById('sb'+i).classList.toggle('lit', i<=n);
}
function resetStars() { hlStars(selectedStar); }
function pickStar(n) {
    selectedStar=n; hlStars(n);
    document.getElementById('r'+n).checked=true;
}
</script>
</body>
</html>