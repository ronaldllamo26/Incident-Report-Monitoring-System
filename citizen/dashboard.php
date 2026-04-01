<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/db.php';
requireRole('citizen');

$user = currentUser();

$stmt = $pdo->prepare("
    SELECT i.*, c.name AS category_name
    FROM incidents i
    JOIN categories c ON i.category_id = c.id
    WHERE i.reporter_id = ?
    ORDER BY i.reported_at DESC
");
$stmt->execute([$user['id']]);
$reports = $stmt->fetchAll();

$counts = ['pending' => 0, 'in_progress' => 0, 'resolved' => 0, 'closed' => 0];
foreach ($reports as $r) {
    if (isset($counts[$r['status']])) $counts[$r['status']]++;
}
?>
<!DOCTYPE html>
<html lang="fil">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>My Dashboard — IRMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        * { box-sizing: border-box; }
        :root {
            --navy:  #0f172a;
            --navy2: #1e293b;
            --navy3: #334155;
            --text:  #f8fafc;
            --muted: #94a3b8;
            --border: rgba(255,255,255,0.08);
            --accent: #ef4444;
        }
        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background: #f1f5f9;
            margin: 0;
        }
        .topbar {
            background: var(--navy);
            padding: 14px 0;
            position: sticky;
            top: 0;
            z-index: 100;
            border-bottom: 1px solid var(--border);
        }
        .topbar-brand {
            font-size: 18px;
            font-weight: 800;
            color: #fff;
        }
        .topbar-brand span { color: var(--accent); }
        .citizen-badge {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            background: rgba(59,130,246,0.15);
            border: 1px solid rgba(59,130,246,0.2);
            color: #93c5fd;
            font-size: 11px;
            font-weight: 700;
            padding: 3px 10px;
            border-radius: 20px;
            letter-spacing: 0.5px;
        }
        .stat-card {
            background: #fff;
            border-radius: 12px;
            padding: 20px;
            border: 0.5px solid #e2e8f0;
            box-shadow: 0 1px 3px rgba(0,0,0,0.04);
        }
        .stat-num {
            font-size: 32px;
            font-weight: 800;
            line-height: 1;
            margin-bottom: 4px;
        }
        .stat-label {
            font-size: 12px;
            color: #64748b;
            font-weight: 500;
        }
        .btn-report-new {
            background: var(--accent);
            color: #fff;
            border: none;
            padding: 10px 20px;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 700;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: all 0.2s;
        }
        .btn-report-new:hover {
            background: #dc2626;
            color: #fff;
            transform: translateY(-1px);
        }
        .table th { font-size: 12px; font-weight: 600; color: #64748b; }
        .table td { font-size: 13px; vertical-align: middle; }
        .welcome-box {
            background: linear-gradient(135deg, var(--navy) 0%, var(--navy2) 100%);
            border-radius: 16px;
            padding: 24px 28px;
            color: #fff;
            margin-bottom: 24px;
        }
    </style>
</head>
<body>

<!-- Topbar -->
<div class="topbar">
    <div class="container d-flex justify-content-between align-items-center">
        <div class="d-flex align-items-center gap-3">
            <div class="topbar-brand">
                <i class="bi bi-shield-check me-1" style="color:var(--accent)"></i>
                IRM<span>S</span>
            </div>
            <span class="citizen-badge">
                <i class="bi bi-person-fill" style="font-size:9px;"></i>
                Citizen
            </span>
        </div>
        <div class="d-flex align-items-center gap-3">
            <span style="font-size:13px;color:var(--muted);">
                <i class="bi bi-person-circle me-1"></i>
                <?= htmlspecialchars($user['name']) ?>
            </span>
            <a href="/irms/controllers/AuthController.php?action=logout"
               style="font-size:13px;color:#fca5a5;text-decoration:none;
                      display:flex;align-items:center;gap:5px;">
                <i class="bi bi-box-arrow-right"></i> Logout
            </a>
        </div>
    </div>
</div>

<div class="container py-4">

    <!-- Welcome box -->
    <div class="welcome-box">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
            <div>
                <h5 class="fw-bold mb-1">
                    Magandang araw, <?= htmlspecialchars(explode(' ', $user['name'])[0]) ?>! 👋
                </h5>
                <p style="color:var(--muted);font-size:13px;margin:0;">
                    Dito makikita mo ang lahat ng iyong mga incident report.
                </p>
            </div>
            <a href="/irms/citizen/report.php" class="btn-report-new">
                <i class="bi bi-plus-lg"></i> Mag-report ng Insidente
            </a>
        </div>
    </div>

    <!-- Stats -->
    <div class="row g-3 mb-4">
        <div class="col-6 col-md-3">
            <div class="stat-card">
                <div class="stat-num text-warning"><?= $counts['pending'] ?></div>
                <div class="stat-label">Pending</div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-card">
                <div class="stat-num text-primary"><?= $counts['in_progress'] ?></div>
                <div class="stat-label">In Progress</div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-card">
                <div class="stat-num text-success"><?= $counts['resolved'] ?></div>
                <div class="stat-label">Resolved</div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-card">
                <div class="stat-num text-secondary"><?= $counts['closed'] ?></div>
                <div class="stat-label">Closed</div>
            </div>
        </div>
    </div>

    <!-- Reports -->
    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
            <div class="d-flex justify-content-between align-items-center p-3 border-bottom">
                <h6 class="fw-semibold mb-0">
                    <i class="bi bi-list-ul me-1"></i> Aking mga Report
                </h6>
                <span class="badge bg-light text-dark border">
                    <?= count($reports) ?> total
                </span>
            </div>

            <?php if (empty($reports)): ?>
                <div class="text-center py-5 text-muted">
                    <i class="bi bi-inbox fs-1 d-block mb-2 text-muted"></i>
                    <p class="mb-2">Wala ka pang naka-submit na report.</p>
                    <a href="/irms/citizen/report.php" class="btn-report-new">
                        <i class="bi bi-plus-lg"></i> Mag-report ngayon
                    </a>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th class="ps-3">#</th>
                                <th>Title</th>
                                <th>Category</th>
                                <th>Severity</th>
                                <th>Status</th>
                                <th>Date</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $sevColor = ['low'=>'success','medium'=>'warning','high'=>'danger','critical'=>'dark'];
                            $stColor  = ['pending'=>'warning','in_progress'=>'primary','resolved'=>'success','closed'=>'secondary'];
                            foreach ($reports as $i => $r):
                            ?>
                            <tr>
                                <td class="ps-3 text-muted"><?= $i + 1 ?></td>
                                <td class="fw-medium"><?= htmlspecialchars($r['title']) ?></td>
                                <td>
                                    <span class="badge bg-light text-dark border">
                                        <?= htmlspecialchars($r['category_name']) ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="badge bg-<?= $sevColor[$r['severity']] ?? 'secondary' ?>">
                                        <?= ucfirst($r['severity']) ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="badge bg-<?= $stColor[$r['status']] ?? 'secondary' ?>">
                                        <?= ucwords(str_replace('_',' ',$r['status'])) ?>
                                    </span>
                                </td>
                                <td class="text-muted">
                                    <?= date('M d, Y', strtotime($r['reported_at'])) ?>
                                </td>
                                <td>
                                    <a href="/irms/citizen/view_report.php?id=<?= $r['id'] ?>"
                                       class="btn btn-outline-primary btn-sm">
                                        <i class="bi bi-eye"></i>
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Quick links -->
    <div class="row g-3 mt-2">
        <div class="col-md-4">
            <a href="/irms/public/track.php"
               style="display:flex;align-items:center;gap:10px;padding:16px;
                      background:#fff;border-radius:12px;border:0.5px solid #e2e8f0;
                      text-decoration:none;color:#1e293b;transition:all 0.2s;"
               onmouseover="this.style.borderColor='#3b82f6'"
               onmouseout="this.style.borderColor='#e2e8f0'">
                <i class="bi bi-search text-primary fs-5"></i>
                <div>
                    <div style="font-size:13px;font-weight:600;">I-track ang Report</div>
                    <div style="font-size:11px;color:#64748b;">Gamit ang tracking number</div>
                </div>
            </a>
        </div>
        <div class="col-md-4">
            <a href="/irms/citizen/report.php"
               style="display:flex;align-items:center;gap:10px;padding:16px;
                      background:#fff;border-radius:12px;border:0.5px solid #e2e8f0;
                      text-decoration:none;color:#1e293b;transition:all 0.2s;"
               onmouseover="this.style.borderColor='#ef4444'"
               onmouseout="this.style.borderColor='#e2e8f0'">
                <i class="bi bi-megaphone text-danger fs-5"></i>
                <div>
                    <div style="font-size:13px;font-weight:600;">Mag-report ng Bago</div>
                    <div style="font-size:11px;color:#64748b;">I-report ang bagong insidente</div>
                </div>
            </a>
        </div>
        <div class="col-md-4">
            <a href="/irms/index.php"
               style="display:flex;align-items:center;gap:10px;padding:16px;
                      background:#fff;border-radius:12px;border:0.5px solid #e2e8f0;
                      text-decoration:none;color:#1e293b;transition:all 0.2s;"
               onmouseover="this.style.borderColor='#10b981'"
               onmouseout="this.style.borderColor='#e2e8f0'">
                <i class="bi bi-house text-success fs-5"></i>
                <div>
                    <div style="font-size:13px;font-weight:600;">Bumalik sa Homepage</div>
                    <div style="font-size:11px;color:#64748b;">Public portal</div>
                </div>
            </a>
        </div>
    </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>