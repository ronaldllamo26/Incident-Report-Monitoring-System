<?php
require_once __DIR__ . '/../../includes/auth.php';
requireRole('admin');
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../models/Incident.php';

$user  = currentUser();
$model = new Incident();

$counts     = $model->getCountsByStatus();
$total      = array_sum($counts);
$recent     = $model->getAll();
$recent     = array_slice($recent, 0, 10);

$kpis       = $model->getGlobalKPIs();
$trend      = $model->getTrendData(30);
$sevDist    = $model->getSeverityDist();

$catStats = $pdo->query("
    SELECT c.name, COUNT(i.id) AS count
    FROM categories c
    LEFT JOIN incidents i ON i.category_id = c.id
    GROUP BY c.id, c.name
    ORDER BY count DESC
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
?>
<!DOCTYPE html>
<html lang="fil">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Admin Dashboard — IRMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.min.css" rel="stylesheet">
    <?php include __DIR__ . '/../../includes/sidebar_style.php'; ?>
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
                <h6 class="fw-semibold mb-0">Dashboard</h6>
            </div>
            <div class="d-flex align-items-center gap-2">
                <?php include __DIR__ . '/../../includes/notification_bell.php'; ?>
                <span class="text-muted small"><?= date('F d, Y') ?></span>
            </div>
        </div>

        <div class="p-4">

            <?php
            $slaAlerts = $pdo->query("
                SELECT i.*, c.name AS category_name, u.name AS responder_name
                FROM incidents i
                JOIN categories c ON i.category_id = c.id
                LEFT JOIN users u ON i.assigned_to = u.id
                WHERE i.sla_deadline IS NOT NULL
                  AND i.status NOT IN ('resolved','closed')
                  AND i.sla_deadline < DATE_ADD(NOW(), INTERVAL 2 HOUR)
                ORDER BY i.sla_deadline ASC
                LIMIT 5
            ")->fetchAll();
            ?>

            <?php if (!empty($slaAlerts)): ?>
            <div class="card border-0 shadow-sm mb-4 border-start border-danger border-3">
                <div class="card-body p-3">
                    <p class="small fw-medium text-danger mb-2">
                        <i class="bi bi-exclamation-triangle-fill me-1"></i>
                        SLA Alerts — <?= count($slaAlerts) ?> incident(s) need immediate attention
                    </p>
                    <div class="d-flex flex-column gap-2">
                        <?php foreach ($slaAlerts as $alert):
                            $deadline   = strtotime($alert['sla_deadline']);
                            $minsLeft   = round(($deadline - time()) / 60);
                            $isBreached = $minsLeft <= 0;
                            $badgeColor = $isBreached ? 'danger' : ($minsLeft <= 30 ? 'warning' : 'info');
                            $timeLabel  = $isBreached
                                ? 'BREACHED'
                                : ($minsLeft < 60 ? "{$minsLeft} mins left" : round($minsLeft/60,1)." hrs left");
                        ?>
                        <div class="d-flex align-items-center justify-content-between
                                    p-2 rounded border border-<?= $badgeColor ?>
                                    bg-<?= $isBreached ? 'danger' : 'light' ?>
                                    bg-opacity-10">
                            <div>
                                <span class="small fw-medium <?= $isBreached ? 'text-danger' : '' ?>">
                                    #<?= $alert['id'] ?> — <?= htmlspecialchars($alert['title']) ?>
                                </span>
                                <span class="text-muted ms-2" style="font-size:11px;">
                                    <?= htmlspecialchars($alert['category_name']) ?>
                                    · <?= $alert['responder_name'] ? htmlspecialchars($alert['responder_name']) : 'Unassigned' ?>
                                </span>
                            </div>
                            <div class="d-flex align-items-center gap-2">
                                <span class="badge bg-<?= $badgeColor ?>"><?= $timeLabel ?></span>
                                <a href="/irms/portal/admin/view_incident.php?id=<?= $alert['id'] ?>"
                                   class="btn btn-outline-<?= $badgeColor ?> btn-sm py-0">View</a>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <div class="row g-3 mb-4">
            <!-- ── Top KPI Cards ──────────────────────────────── -->
            <div class="row g-3 mb-4">
                <div class="col-6 col-md-4 col-xl-2">
                    <div class="card border-0 shadow-sm border-start border-primary border-3">
                        <div class="card-body py-3">
                            <div class="text-muted small mb-1">Total Reports</div>
                            <div class="fs-4 fw-bold"><?= $total ?></div>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-md-4 col-xl-2">
                    <div class="card border-0 shadow-sm border-start border-warning border-3">
                        <div class="card-body py-3">
                            <div class="text-muted small mb-1">Action Needed</div>
                            <div class="fs-4 fw-bold text-warning"><?= $counts['pending'] ?></div>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-md-4 col-xl-2">
                    <div class="card border-0 shadow-sm border-start border-info border-3">
                        <div class="card-body py-3">
                            <div class="text-muted small mb-1">In Progress</div>
                            <div class="fs-4 fw-bold text-info"><?= $counts['in_progress'] ?></div>
                        </div>
                    </div>
                </div>
                <!-- Standard KPIs -->
                <div class="col-6 col-md-4 col-xl-2">
                    <div class="card border-0 shadow-sm border-start border-success border-3">
                        <div class="card-body py-3">
                            <div class="text-muted small mb-1">SLA Success</div>
                            <div class="fs-4 fw-bold text-success"><?= $kpis['sla'] ?>%</div>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-md-4 col-xl-2">
                    <div class="card border-0 shadow-sm border-start border-dark border-3">
                        <div class="card-body py-3">
                            <div class="text-muted small mb-1">Avg Response</div>
                            <div class="fs-4 fw-bold"><?= $kpis['mtta'] ?> <small class="fw-normal fs-6">mins</small></div>
                        </div>
                    </div>
                </div>
                <!-- Citizen CSAT Score -->
                <div class="col-6 col-md-4 col-xl-2">
                    <div class="card border-0 shadow-sm border-start border-3" style="border-color: #f59e0b !important;">
                        <div class="card-body py-3">
                            <div class="text-muted small mb-1">Citizen CSAT</div>
                            <div class="fs-4 fw-bold" style="color: #f59e0b;">
                                <?= $kpis['csat'] ?> <i class="bi bi-star-fill fs-6"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ── Trend Analysis & Status ──────────────────────── -->
            <div class="row g-3 mb-4">
                <div class="col-lg-8">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center mb-4">
                                <p class="small fw-medium mb-0">Trend Analysis (Last 30 Days)</p>
                                <span class="badge bg-light text-dark border">Standard KPI</span>
                            </div>
                            <div style="height: 250px;">
                                <canvas id="trendChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-4">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body">
                            <p class="small fw-medium mb-4">Incident Status</p>
                            <div style="height: 250px;">
                                <canvas id="statusChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ── Categories & Severity ────────────────────────── -->
            <div class="row g-3 mb-4">
                <div class="col-md-7">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body">
                            <p class="small fw-medium mb-3">Volume by Category</p>
                            <div style="height: 250px;">
                                <canvas id="categoryChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-5">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body">
                            <p class="small fw-medium mb-3">Severity Breakdown</p>
                            <div style="height: 250px;">
                                <canvas id="severityChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body">
                    <p class="small fw-medium mb-2"><i class="bi bi-map me-1"></i> Incident Map</p>
                    <div id="map"></div>
                </div>
            </div>

            <div class="card border-0 shadow-sm">
                <div class="card-body p-0">
                    <div class="d-flex justify-content-between align-items-center p-3 border-bottom">
                        <p class="small fw-medium mb-0">Recent Incidents</p>
                        <a href="/irms/portal/admin/incidents.php" class="btn btn-outline-primary btn-sm">View All</a>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th class="ps-3 small">#</th>
                                    <th class="small">Title</th>
                                    <th class="small">Category</th>
                                    <th class="small">Severity</th>
                                    <th class="small">Status</th>
                                    <th class="small">Reported</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recent as $inc): ?>
                                <tr>
                                    <td class="ps-3 text-muted small"><?= $inc['id'] ?></td>
                                        <td class="small fw-medium">
                                            <?= htmlspecialchars($inc['title']) ?>
                                            <?php if (!empty($inc['ai_summary'])): ?>
                                                <div class="text-muted mt-1" style="font-size: 0.75rem; font-style: italic;">
                                                    <i class="bi bi-stars" style="color: #6366f1;"></i> <?= htmlspecialchars($inc['ai_summary']) ?>
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                    <td><span class="badge bg-light text-dark border small"><?= htmlspecialchars($inc['category_name']) ?></span></td>
                                    <td><span class="badge bg-<?= $sevColor[$inc['severity']] ?> small"><?= ucfirst($inc['severity']) ?></span></td>
                                    <td><span class="badge bg-<?= $statusColor[$inc['status']] ?> small"><?= ucwords(str_replace('_',' ',$inc['status'])) ?></span></td>
                                    <td class="small text-muted"><?= date('M d, Y', strtotime($inc['reported_at'])) ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Fix Leaflet broken default icons when pulling from CDN
delete L.Icon.Default.prototype._getIconUrl;
L.Icon.Default.mergeOptions({
    iconRetinaUrl: 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/images/marker-icon-2x.png',
    iconUrl: 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/images/marker-icon.png',
    shadowUrl: 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/images/marker-shadow.png'
});

// Trend Chart (Line)
new Chart(document.getElementById('trendChart'), {
    type: 'line',
    data: {
        labels: [<?php foreach($trend as $t) echo '"'.date('M d', strtotime($t['date'])).'",'; ?>],
        datasets: [{
            label: 'Incidents per Day',
            data: [<?php foreach($trend as $t) echo $t['count'].','; ?>],
            borderColor: '#0d6efd',
            backgroundColor: 'rgba(13, 110, 253, 0.1)',
            fill: true,
            tension: 0.4,
            borderWidth: 2,
            pointRadius: 3
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { display: false } },
        scales: {
            y: { beginAtZero: true, grid: { display: false }, ticks: { stepSize: 1 } },
            x: { grid: { display: false } }
        }
    }
});

// Status Chart (Doughnut)
new Chart(document.getElementById('statusChart'), {
    type: 'doughnut',
    data: {
        labels: ['Pending', 'In Progress', 'Resolved', 'Closed'],
        datasets: [{
            data: [<?= $counts['pending'] ?>, <?= $counts['in_progress'] ?>, <?= $counts['resolved'] ?>, <?= $counts['closed'] ?>],
            backgroundColor: ['#ffc107', '#0d6efd', '#198754', '#6c757d'],
            borderWidth: 0,
            hoverOffset: 10
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { position: 'bottom', labels: { boxWidth: 12, padding: 15, font: { size: 11 } } } },
        cutout: '70%'
    }
});

// Category Chart (Bar)
new Chart(document.getElementById('categoryChart'), {
    type: 'bar',
    data: {
        labels: [<?php foreach($catStats as $c) echo '"'.addslashes($c['name']).'",'; ?>],
        datasets: [{
            label: 'Incidents',
            data: [<?php foreach($catStats as $c) echo $c['count'].','; ?>],
            backgroundColor: '#0d6efd',
            borderRadius: 5,
            borderWidth: 0
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { display: false } },
        scales: {
            y: { beginAtZero: true, grid: { borderDash: [2, 2] }, ticks: { stepSize: 1 } },
            x: { grid: { display: false } }
        }
    }
});

// Severity Chart (Pie)
new Chart(document.getElementById('severityChart'), {
    type: 'pie',
    data: {
        labels: ['Low', 'Medium', 'High', 'Critical'],
        datasets: [{
            data: [
                <?= $sevDist['low'] ?? 0 ?>,
                <?= $sevDist['medium'] ?? 0 ?>,
                <?= $sevDist['high'] ?? 0 ?>,
                <?= $sevDist['critical'] ?? 0 ?>
            ],
            backgroundColor: ['#198754', '#ffc107', '#fd7e14', '#dc3545'],
            borderWidth: 1,
            borderColor: '#fff'
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: { position: 'right', labels: { boxWidth: 12, padding: 15, font: { size: 11 } } }
        }
    }
});

// Map Logic
var map = L.map('map').setView([14.6760, 121.0437], 12); // Centered on Quezon City
L.tileLayer('https://{s}.basemaps.cartocdn.com/light_all/{z}/{x}/{y}{r}.png', {
    attribution: '© CARTO'
}).addTo(map);

fetch('/irms/ajax/get_incidents_map.php').then(r=>r.json()).then(data=>{
    data.forEach(inc=>{
        var c = L.circleMarker([inc.lat,inc.lng],{
            radius: 8,
            fillColor: inc.color,
            color: '#fff',
            weight: 2,
            opacity: 1,
            fillOpacity: 0.9
        }).addTo(map);
        c.bindPopup('<div style="min-width:180px"><strong>'+inc.title+'</strong><br><span style="font-size:11px;color:#666;">'+inc.location+'</span><br><span class="badge" style="background:'+inc.color+';color:#fff;padding:2px 6px;border-radius:4px;margin-top:4px;display:inline-block;">'+inc.status.replace('_',' ')+'</span></div>');
    });
});

setInterval(function(){
    fetch('/irms/ajax/check_escalations.php').then(r=>r.json()).then(data=>{ if(data.escalated>0) location.reload(); }).catch(()=>{});
}, 60000);
</script>
</body>
</html>