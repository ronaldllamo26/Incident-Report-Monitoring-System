<?php
require_once __DIR__ . '/../config/db.php';

$tracking = $_GET['tracking'] ?? '';
$id       = (int)($_GET['id'] ?? 0);

if (!$tracking || !$id) {
    header('Location: /irms/public/anonymous_report.php');
    exit;
}

// Verify na totoo ang tracking number
$stmt = $pdo->prepare("SELECT * FROM incidents WHERE tracking_number = ? AND id = ?");
$stmt->execute([$tracking, $id]);
$incident = $stmt->fetch();

if (!$incident) {
    header('Location: /irms/public/anonymous_report.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="fil">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Report Submitted — IRMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body { background: #f1f5f9; }
        .brand-bar { background: #1e293b; padding: 14px 0; }
        .tracking-card { background: #1e293b; border-radius: 16px; padding: 32px; text-align: center; }
        .tracking-number { font-size: 32px; font-weight: 700; letter-spacing: 6px;
                           color: #34d399; font-family: monospace; }
        .success-icon { width: 72px; height: 72px; background: #d1fae5;
                        border-radius: 50%; display: flex; align-items: center;
                        justify-content: center; margin: 0 auto 20px; }
    </style>
</head>
<body>

<div class="brand-bar">
    <div class="container">
        <div class="text-white fw-semibold">
            <i class="bi bi-shield-check me-2"></i>IRMS
        </div>
    </div>
</div>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-6">

            <!-- Success message -->
            <div class="text-center mb-4">
                <div class="success-icon">
                    <i class="bi bi-check-lg text-success fs-2"></i>
                </div>
                <h4 class="fw-semibold">Na-submit na ang iyong Report!</h4>
                <p class="text-muted">
                    Natanggap na namin ang iyong incident report.
                    Magiging aksyon kami sa lalong madaling panahon.
                </p>
            </div>

            <!-- Tracking card -->
            <div class="tracking-card mb-4">
                <p class="text-secondary small mb-2">Iyong Tracking Number</p>
                <div class="tracking-number mb-3" id="tracking-num">
                    <?= htmlspecialchars($tracking) ?>
                </div>
                <p class="text-secondary small mb-3">
                    I-save o i-screenshot ang tracking number na ito.
                    Gagamitin mo ito para ma-check ang status ng iyong report.
                </p>
                <button class="btn btn-outline-light btn-sm" onclick="copyTracking()">
                    <i class="bi bi-clipboard me-1"></i> I-copy ang Tracking Number
                </button>
            </div>

            <!-- Incident summary -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body">
                    <h6 class="fw-semibold mb-3">Detalye ng Report</h6>
                    <table class="table table-sm mb-0">
                        <tr>
                            <td class="text-muted small" style="width:40%">Pamagat</td>
                            <td class="small fw-medium">
                                <?= htmlspecialchars($incident['title']) ?>
                            </td>
                        </tr>
                        <tr>
                            <td class="text-muted small">Severity</td>
                            <td>
                                <?php
                                $sevColor = ['low'=>'success','medium'=>'warning',
                                             'high'=>'danger','critical'=>'dark'];
                                ?>
                                <span class="badge bg-<?= $sevColor[$incident['severity']] ?> small">
                                    <?= ucfirst($incident['severity']) ?>
                                </span>
                            </td>
                        </tr>
                        <tr>
                            <td class="text-muted small">Status</td>
                            <td>
                                <span class="badge bg-warning small">Pending</span>
                            </td>
                        </tr>
                        <tr>
                            <td class="text-muted small">Submitted</td>
                            <td class="small text-muted">
                                <?= date('M d, Y g:i A', strtotime($incident['reported_at'])) ?>
                            </td>
                        </tr>
                        <?php if ($incident['sla_deadline']): ?>
                        <tr>
                            <td class="text-muted small">Response Deadline</td>
                            <td class="small text-danger fw-medium">
                                <?= date('M d, Y g:i A', strtotime($incident['sla_deadline'])) ?>
                            </td>
                        </tr>
                        <?php endif; ?>
                    </table>
                </div>
            </div>

            <!-- Next steps -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body">
                    <h6 class="fw-semibold mb-3">Ano ang susunod?</h6>
                    <div class="d-flex flex-column gap-2">
                        <div class="d-flex gap-2 align-items-start">
                            <div class="badge bg-primary rounded-circle" style="min-width:24px;height:24px;display:flex;align-items:center;justify-content:center;">1</div>
                            <div class="small">Isinumite na ang iyong report sa aming sistema at matatanggap ng admin.</div>
                        </div>
                        <div class="d-flex gap-2 align-items-start">
                            <div class="badge bg-primary rounded-circle" style="min-width:24px;height:24px;display:flex;align-items:center;justify-content:center;">2</div>
                            <div class="small">Ia-assign ang tamang responder base sa uri ng insidente.</div>
                        </div>
                        <div class="d-flex gap-2 align-items-start">
                            <div class="badge bg-primary rounded-circle" style="min-width:24px;height:24px;display:flex;align-items:center;justify-content:center;">3</div>
                            <div class="small">Makakatanggap ka ng update sa pamamagitan ng tracking number mo.</div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="d-flex gap-2">
                <a href="/irms/public/track.php?tracking=<?= urlencode($tracking) ?>"
                   class="btn btn-primary flex-fill">
                    <i class="bi bi-search me-1"></i> I-track ang Report
                </a>
                <a href="/irms/public/anonymous_report.php"
                   class="btn btn-outline-secondary">
                    Mag-report pa
                </a>
            </div>

        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
function copyTracking() {
    var text = document.getElementById('tracking-num').textContent.trim();
    navigator.clipboard.writeText(text).then(function() {
        alert('Na-copy na ang tracking number: ' + text);
    });
}
</script>
</body>
</html>