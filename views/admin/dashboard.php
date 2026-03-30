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

// Counts by category para sa chart
$catStats = $pdo->query("
    SELECT c.name, COUNT(i.id) AS count
    FROM categories c
    LEFT JOIN incidents i ON i.category_id = c.id
    GROUP BY c.id, c.name
    ORDER BY count DESC
")->fetchAll();

// Counts by severity
$sevStats = $pdo->query("
    SELECT severity, COUNT(*) AS count
    FROM incidents
    GROUP BY severity
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
    <style>
        #map { height: 400px; border-radius: 8px; border: 1px solid #dee2e6; }
        .sidebar { width: 220px; min-height: 100vh; background: #1e293b; }
        .sidebar .nav-link { color: #94a3b8; font-size: 14px; padding: 10px 20px; border-radius: 6px; margin: 2px 8px; }
        .sidebar .nav-link:hover, .sidebar .nav-link.active { background: #334155; color: #fff; }
        .sidebar .nav-link i { width: 20px; }
        .main-content { flex: 1; overflow-y: auto; }
        .top-nav { background: #fff; border-bottom: 1px solid #e2e8f0; padding: 12px 24px; }
    </style>
</head>
<body class="bg-light">
<div class="d-flex">

    <div class="sidebar d-flex flex-column py-3">
        <div class="px-4 mb-4">
            <div class="text-white fw-semibold fs-6">
                <i class="bi bi-shield-check me-2"></i>IRMS
            </div>
            <div class="text-secondary" style="font-size:11px;">Admin Panel</div>
        </div>
        <nav class="flex-column nav">
            <a href="/irms/views/admin/dashboard.php" class="nav-link active">
                <i class="bi bi-speedometer2 me-2"></i> Dashboard
            </a>
            <a href="/irms/views/admin/incidents.php" class="nav-link">
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
                <?= htmlspecialchars($user['name']) ?>
            </div>
            <a href="/irms/controllers/AuthController.php?action=logout" class="nav-link text-danger">
                <i class="bi bi-box-arrow-right me-2"></i> Logout
            </a>
        </div>
    </div>

    <div class="main-content">
        <div class="top-nav d-flex justify-content-between align-items-center">
            <h6 class="fw-semibold mb-0">Dashboard</h6>
            <span class="text-muted small"><?= date('F d, Y') ?></span>
        </div>

        <div class="p-4">

            <?php
            // Kunin ang incidents na malapit na o nag-breach na ng SLA
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
                                <a href="/irms/views/admin/view_incident.php?id=<?= $alert['id'] ?>" 
                                   class="btn btn-outline-<?= $badgeColor ?> btn-sm py-0">
                                    View
                                </a>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <div class="row g-3 mb-4">
                <div class="col-6 col-xl-3">
                    <div class="card border-0 shadow-sm">
                        <div class="card-body py-3">
                            <div class="text-muted small mb-1">Total Incidents</div>
                            <div class="fs-3 fw-bold"><?= $total ?></div>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-xl-3">
                    <div class="card border-0 shadow-sm">
                        <div class="card-body py-3">
                            <div class="text-muted small mb-1">Pending</div>
                            <div class="fs-3 fw-bold text-warning"><?= $counts['pending'] ?></div>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-xl-3">
                    <div class="card border-0 shadow-sm">
                        <div class="card-body py-3">
                            <div class="text-muted small mb-1">In Progress</div>
                            <div class="fs-3 fw-bold text-primary"><?= $counts['in_progress'] ?></div>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-xl-3">
                    <div class="card border-0 shadow-sm">
                        <div class="card-body py-3">
                            <div class="text-muted small mb-1">Resolved</div>
                            <div class="fs-3 fw-bold text-success"><?= $counts['resolved'] ?></div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row g-3 mb-4">
                <div class="col-md-6">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body">
                            <p class="small fw-medium mb-3">Incidents by Status</p>
                            <canvas id="statusChart" height="200"></canvas>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body">
                            <p class="small fw-medium mb-3">Incidents by Category</p>
                            <canvas id="categoryChart" height="200"></canvas>
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
                        <a href="/irms/views/admin/incidents.php" class="btn btn-outline-primary btn-sm">View All</a>
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
                                    <td class="small fw-medium"><?= htmlspecialchars($inc['title']) ?></td>
                                    <td>
                                        <span class="badge bg-light text-dark border small"><?= htmlspecialchars($inc['category_name']) ?></span>
                                    </td>
                                    <td>
                                        <span class="badge bg-<?= $sevColor[$inc['severity']] ?> small"><?= ucfirst($inc['severity']) ?></span>
                                    </td>
                                    <td>
                                        <span class="badge bg-<?= $statusColor[$inc['status']] ?> small"><?= ucwords(str_replace('_',' ',$inc['status'])) ?></span>
                                    </td>
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
// ── STATUS CHART ───────────────────────────────────────
new Chart(document.getElementById('statusChart'), {
    type: 'doughnut',
    data: {
        labels: ['Pending', 'In Progress', 'Resolved', 'Closed'],
        datasets: [{
            data: [<?= $counts['pending'] ?>, <?= $counts['in_progress'] ?>, <?= $counts['resolved'] ?>, <?= $counts['closed'] ?>],
            backgroundColor: ['#f59e0b','#3b82f6','#10b981','#6b7280'],
            borderWidth: 0
        }]
    },
    options: { plugins: { legend: { position: 'bottom' } }, cutout: '65%' }
});

// ── CATEGORY CHART ─────────────────────────────────────
new Chart(document.getElementById('categoryChart'), {
    type: 'bar',
    data: {
        labels: [<?php foreach($catStats as $c) echo '"'.addslashes($c['name']).'",'; ?>],
        datasets: [{
            label: 'Incidents',
            data: [<?php foreach($catStats as $c) echo $c['count'].','; ?>],
            backgroundColor: '#3b82f6',
            borderRadius: 4,
            borderWidth: 0
        }]
    },
    options: {
        plugins: { legend: { display: false } },
        scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } }
    }
});

// ── INCIDENT MAP ───────────────────────────────────────
var map = L.map('map').setView([14.5995, 120.9842], 7);
L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '© OpenStreetMap contributors'
}).addTo(map);

fetch('/irms/ajax/get_incidents_map.php')
.then(function(res) { return res.json(); })
.then(function(data) {
    data.forEach(function(inc) {
        var circle = L.circleMarker([inc.lat, inc.lng], {
            radius: 8, fillColor: inc.color, color: '#fff', weight: 2, opacity: 1, fillOpacity: 0.9
        }).addTo(map);
        circle.bindPopup('<div style="min-width:180px"><strong>'+inc.title+'</strong><br><span style="font-size:11px;color:#666;">'+inc.location+'</span><br><span class="badge" style="background:'+inc.color+';color:#fff;padding:2px 6px;border-radius:4px;margin-top:4px;display:inline-block;">'+inc.status.replace('_',' ')+'</span></div>');
    });
});

// ── STEP 6: AUTO-CHECK ESCALATIONS (60 SECONDS) ────────
setInterval(function() {
    fetch('/irms/ajax/check_escalations.php')
    .then(function(res) { return res.json(); })
    .then(function(data) {
        if (data.escalated > 0) {
            location.reload();
        }
    })
    .catch(function() {});
}, 60000);
</script>
</body>
</html>