<?php
require_once __DIR__ . '/../../includes/auth.php';
requireRole('responder');
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../models/Incident.php';

$user  = currentUser();
$model = new Incident();

$filters = ['assigned_to' => $user['id']];
if (!empty($_GET['status'])) $filters['status'] = $_GET['status'];
$search = trim($_GET['search'] ?? '');

$perPage    = 20;
$page       = max(1, (int)($_GET['page'] ?? 1));
$offset     = ($page - 1) * $perPage;

// We need counts for ALL statuses for the header cards
$allIncidents = $model->getAll(['assigned_to' => $user['id']]);
$counts = ['all' => count($allIncidents), 'pending' => 0, 'in_progress' => 0, 'resolved' => 0, 'closed' => 0];
foreach ($allIncidents as $inc) {
    if (isset($counts[$inc['status']])) $counts[$inc['status']]++;
}

// Now get the specific paginated results (with optional status filter)
// Note: We'll implement a simple PHP search if provided
$paginatedIncidents = $model->getAll($filters, $perPage, $offset);
// Simple search filtering if needed (though Incident::getAll could be updated for LIKE)
if ($search) {
    $paginatedIncidents = array_filter($paginatedIncidents, function($i) use ($search) {
        return str_contains(strtolower($i['title']), strtolower($search)) || 
               str_contains(strtolower($i['location']), strtolower($search));
    });
}
$totalFiltered = $model->countTotal($filters);
$totalPages    = ceil($totalFiltered / $perPage);

$stLabel = ['pending' => 'Pending', 'in_progress' => 'In Progress', 'resolved' => 'Resolved', 'closed' => 'Closed'];
$stStyle = [
    'pending'     => 'background:#fef3c7;color:#92400e;',
    'in_progress' => 'background:#dbeafe;color:#1e40af;',
    'resolved'    => 'background:#dcfce7;color:#166534;',
    'closed'      => 'background:#f3f4f6;color:#4b5563;',
];
$sevColor = ['low' => '#16a34a', 'medium' => '#d97706', 'high' => '#ea580c', 'critical' => '#CE1126'];
$sevBg    = ['low' => '#f0fdf4', 'medium' => '#fffbeb', 'high' => '#fff7ed', 'critical' => '#fef2f2'];
?>
<!DOCTYPE html>
<html lang="fil">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Responder Dashboard — QC-ALERTO</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --qc-blue:  #1e293b;
            --qc-navy:  #133C52;
            --qc-red:   #CE1126;
            --bg:       #f4f6f9;
            --border:   #e2e8f0;
            --text:     #1e293b;
            --muted:    #64748b;
        }
        * { box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: var(--bg); margin: 0; color: var(--text); }

        /* ── Topbar ─────────────────────────────────────────── */
        .topbar {
            background: var(--qc-blue);
            border-bottom: 3px solid var(--qc-red);
            position: sticky; top: 0; z-index: 100;
        }
        .topbar-inner {
            max-width: 1200px; margin: 0 auto; padding: 0 24px;
            display: flex; align-items: center; justify-content: space-between; height: 56px;
        }
        .brand { font-size: 16px; font-weight: 700; color: #fff; text-decoration: none;
                 display: flex; align-items: center; gap: 10px; letter-spacing: 0.2px; }
        .brand img { height: 30px; width: 30px; object-fit: contain; }
        .role-badge { background: rgba(255,255,255,0.15); border: 1px solid rgba(255,255,255,0.25);
                      color: #fff; font-size: 10px; font-weight: 600; padding: 2px 8px;
                      border-radius: 4px; letter-spacing: 0.8px; text-transform: uppercase; }
        .topbar-right { display: flex; align-items: center; gap: 16px; }
        .user-label { font-size: 13px; color: rgba(255,255,255,0.85); font-weight: 500;
                      display: flex; align-items: center; gap: 6px; }
        .logout-link { font-size: 12px; color: rgba(255,255,255,0.7); text-decoration: none;
                       display: flex; align-items: center; gap: 5px; padding: 5px 10px;
                       border: 1px solid rgba(255,255,255,0.2); border-radius: 6px; transition: all 0.2s; }
        .logout-link:hover { color: #fff; background: rgba(255,255,255,0.1); }

        /* ── Page Header ────────────────────────────────────── */
        .page-header {
            background: #fff; border-bottom: 1px solid var(--border);
            padding: 20px 0;
        }
        .page-header-inner { max-width: 1200px; margin: 0 auto; padding: 0 24px; }
        .page-header h4 { font-size: 18px; font-weight: 700; color: var(--text); margin: 0 0 2px; }
        .page-header p { font-size: 13px; color: var(--muted); margin: 0; }

        .main-wrap { max-width: 1200px; margin: 0 auto; padding: 24px; }

        /* ── Stat Cards ─────────────────────────────────────── */
        .stat-card {
            background: #fff; border: 1px solid var(--border); border-radius: 10px;
            padding: 18px 20px; cursor: pointer; transition: border-color 0.2s, box-shadow 0.2s;
            display: flex; align-items: center; gap: 14px;
        }
        .stat-card:hover { border-color: var(--qc-blue); box-shadow: 0 0 0 3px rgba(0,56,168,0.08); }
        .stat-card.active { border-color: var(--qc-blue); box-shadow: 0 0 0 3px rgba(30,41,59,0.12);
                            background: #f0f4ff; }
        .stat-icon { width: 42px; height: 42px; border-radius: 8px; display: flex;
                     align-items: center; justify-content: center; font-size: 18px; flex-shrink: 0; }
        .stat-num { font-size: 26px; font-weight: 700; line-height: 1; color: var(--text); }
        .stat-lbl { font-size: 12px; color: var(--muted); font-weight: 500; margin-top: 2px; }

        /* ── Incidents Table Card ───────────────────────────── */
        .table-card { background: #fff; border: 1px solid var(--border); border-radius: 10px; overflow: hidden; }
        .table-card-header { padding: 14px 20px; border-bottom: 1px solid var(--border);
                             display: flex; align-items: center; justify-content: space-between;
                             flex-wrap: wrap; gap: 12px; background: #fafbfc; }
        .section-title { font-size: 14px; font-weight: 600; color: var(--text); }

        /* Filter tabs */
        .filter-tabs { display: flex; gap: 4px; flex-wrap: wrap; }
        .ftab { padding: 5px 13px; border-radius: 6px; font-size: 12px; font-weight: 500;
                border: 1px solid var(--border); background: #fff; color: var(--muted);
                cursor: pointer; transition: all 0.15s; }
        .ftab:hover { border-color: var(--qc-blue); color: var(--qc-blue); }
        .ftab.active { background: var(--qc-blue); border-color: var(--qc-blue);
                       color: #fff; font-weight: 600; }

        /* Search */
        .search-box { position: relative; }
        .search-box input { padding: 7px 12px 7px 32px; border: 1px solid var(--border);
                            border-radius: 6px; font-size: 13px; outline: none; background: #fff;
                            transition: border-color 0.2s; width: 200px; }
        .search-box input:focus { border-color: var(--qc-blue); }
        .search-box .si { position: absolute; left: 10px; top: 50%; transform: translateY(-50%);
                          color: var(--muted); font-size: 13px; }

        /* Table */
        .table { margin: 0; }
        .table th { font-size: 11px; font-weight: 600; color: var(--muted);
                    text-transform: uppercase; letter-spacing: 0.4px; padding: 10px 16px;
                    background: #fafbfc; border-bottom: 1px solid var(--border); }
        .table td { font-size: 13px; padding: 13px 16px; vertical-align: middle;
                    border-color: #f1f5f9; color: var(--text); }
        .table tbody tr { transition: background 0.1s; }
        .table tbody tr:hover { background: #f8fafc; }

        .sev-dot { width: 8px; height: 8px; border-radius: 50%; display: inline-block; flex-shrink: 0; }
        .badge-status { padding: 3px 9px; border-radius: 5px; font-size: 11px; font-weight: 600; }
        .badge-sev    { padding: 3px 9px; border-radius: 5px; font-size: 11px; font-weight: 600; }
        .tracking-num { font-family: monospace; font-size: 11px; background: #f1f5f9;
                        color: #475569; padding: 2px 7px; border-radius: 4px; }
        .btn-view { padding: 5px 14px; background: var(--qc-blue); color: #fff; border-radius: 6px;
                    font-size: 12px; font-weight: 600; text-decoration: none; transition: opacity 0.2s; }
        .btn-view:hover { opacity: 0.85; color: #fff; }

        /* Empty state */
        .empty-state { text-align: center; padding: 60px 20px; }
        #no-results { display: none; }
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
        <div class="topbar-right">
            <?php include __DIR__ . '/../../includes/notification_bell.php'; ?>
            <span class="user-label">
                <i class="bi bi-person-circle"></i>
                <?= htmlspecialchars($user['name']) ?>
            </span>
            <a href="/irms/controllers/AuthController.php?action=logout" class="logout-link">
                <i class="bi bi-box-arrow-right"></i> Logout
            </a>
        </div>
    </div>
</nav>

<!-- Page Header -->
<div class="page-header">
    <div class="page-header-inner">
        <h4>Assigned Incidents</h4>
        <p>Lahat ng incidents na naka-assign sa iyo bilang responder.</p>
    </div>
</div>

<div class="main-wrap">

    <!-- Stat Cards -->
    <div class="row g-3 mb-4">
        <div class="col-6 col-md-2" style="width: 20%;">
            <a href="?status=" class="stat-card <?= empty($_GET['status']) ? 'active' : '' ?>" style="text-decoration:none;">
                <div class="stat-icon" style="background:#f0f4ff;">
                    <i class="bi bi-clipboard-list" style="color:var(--qc-blue);"></i>
                </div>
                <div>
                    <div class="stat-num"><?= $counts['all'] ?></div>
                    <div class="stat-lbl">Lahat</div>
                </div>
            </a>
        </div>
        <div class="col-6 col-md-2" style="width: 20%;">
            <a href="?status=pending" class="stat-card <?= ($_GET['status']??'') === 'pending' ? 'active' : '' ?>" style="text-decoration:none;">
                <div class="stat-icon" style="background:#fffbeb;">
                    <i class="bi bi-hourglass-split" style="color:#d97706;"></i>
                </div>
                <div>
                    <div class="stat-num"><?= $counts['pending'] ?></div>
                    <div class="stat-lbl">Pending</div>
                </div>
            </a>
        </div>
        <div class="col-6 col-md-2" style="width: 20%;">
            <a href="?status=in_progress" class="stat-card <?= ($_GET['status']??'') === 'in_progress' ? 'active' : '' ?>" style="text-decoration:none;">
                <div class="stat-icon" style="background:#eff6ff;">
                    <i class="bi bi-arrow-repeat" style="color:#1d4ed8;"></i>
                </div>
                <div>
                    <div class="stat-num"><?= $counts['in_progress'] ?></div>
                    <div class="stat-lbl">In Progress</div>
                </div>
            </a>
        </div>
        <div class="col-6 col-md-2" style="width: 20%;">
            <a href="?status=resolved" class="stat-card <?= ($_GET['status']??'') === 'resolved' ? 'active' : '' ?>" style="text-decoration:none;">
                <div class="stat-icon" style="background:#f0fdf4;">
                    <i class="bi bi-check-circle" style="color:#16a34a;"></i>
                </div>
                <div>
                    <div class="stat-num"><?= $counts['resolved'] ?></div>
                    <div class="stat-lbl">Resolved</div>
                </div>
            </a>
        </div>
        <div class="col-6 col-md-2" style="width: 20%;">
            <a href="?status=closed" class="stat-card <?= ($_GET['status']??'') === 'closed' ? 'active' : '' ?>" style="text-decoration:none;">
                <div class="stat-icon" style="background:#f3f4f6;">
                    <i class="bi bi-lock-fill" style="color:#64748b;"></i>
                </div>
                <div>
                    <div class="stat-num"><?= $counts['closed'] ?></div>
                    <div class="stat-lbl">Closed</div>
                </div>
            </a>
        </div>
    </div>

    <!-- Table -->
    <div class="table-card">
        <div class="table-card-header">
            <div class="d-flex align-items-center gap-3 flex-wrap">
                <span class="section-title">
                    <i class="bi bi-list-ul me-1" style="color:var(--qc-blue);"></i>
                    Listahan ng Incidents
                </span>
                    <div class="filter-tabs">
                        <a href="?status=" class="ftab <?= empty($_GET['status']) ? 'active' : '' ?>" style="text-decoration:none;">Lahat (<?= $counts['all'] ?>)</a>
                        <a href="?status=pending" class="ftab <?= ($_GET['status']??'') === 'pending' ? 'active' : '' ?>" style="text-decoration:none;">Pending (<?= $counts['pending'] ?>)</a>
                        <a href="?status=in_progress" class="ftab <?= ($_GET['status']??'') === 'in_progress' ? 'active' : '' ?>" style="text-decoration:none;">In Progress (<?= $counts['in_progress'] ?>)</a>
                        <a href="?status=resolved" class="ftab <?= ($_GET['status']??'') === 'resolved' ? 'active' : '' ?>" style="text-decoration:none;">Resolved (<?= $counts['resolved'] ?>)</a>
                        <a href="?status=closed" class="ftab <?= ($_GET['status']??'') === 'closed' ? 'active' : '' ?>" style="text-decoration:none;">Closed (<?= $counts['closed'] ?>)</a>
                    </div>
                </div>
                <form method="GET" class="search-box">
                    <input type="hidden" name="status" value="<?= htmlspecialchars($_GET['status']??'') ?>">
                    <i class="bi bi-search si"></i>
                    <input type="text" name="search" placeholder="Hanapin..." value="<?= htmlspecialchars($search) ?>">
                </form>
        </div>

        <?php if (empty($paginatedIncidents)): ?>
            <div class="empty-state">
                <i class="bi bi-inbox" style="font-size:40px;color:#cbd5e1;display:block;margin-bottom:12px;"></i>
                <p class="text-muted mb-0">Wala kang nahanap na incident.</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover" id="inc-table">
                    <thead>
                        <tr>
                            <th style="width:40px;">Sev.</th>
                            <th>Incident</th>
                            <th>Kategorya</th>
                            <th>Status</th>
                            <th>Petsa</th>
                            <th style="width:80px;"></th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($paginatedIncidents as $inc):
                        $sc = $sevColor[$inc['severity']] ?? '#64748b';
                        $ss = $stStyle[$inc['status']] ?? '';
                    ?>
                        <tr>
                            <td>
                                <span class="sev-dot" style="background:<?= $sc ?>;"></span>
                            </td>
                            <td>
                                <div style="font-weight:600;font-size:13px;margin-bottom:2px;">
                                    <?= htmlspecialchars($inc['title']) ?>
                                </div>
                                <div style="font-size:11px;color:var(--muted);">
                                    <i class="bi bi-geo-alt"></i>
                                    <?= htmlspecialchars(mb_substr($inc['location'], 0, 50)) ?>
                                </div>
                            </td>
                            <td>
                                <span style="font-size:12px;background:#f1f5f9;color:#374151;
                                             padding:3px 9px;border-radius:5px;font-weight:500;">
                                    <?= htmlspecialchars($inc['category_name']) ?>
                                </span>
                            </td>
                            <td>
                                <span class="badge-status" style="<?= $ss ?>">
                                    <?= $stLabel[$inc['status']] ?? ucfirst($inc['status']) ?>
                                </span>
                            </td>
                            <td style="color:var(--muted);font-size:12px;">
                                <?= date('M d, Y', strtotime($inc['reported_at'])) ?>
                            </td>
                            <td>
                                <a href="/irms/portal/responder/view_incident.php?id=<?= $inc['id'] ?>"
                                   class="btn-view">Tingnan</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div id="no-results" class="empty-state" style="<?= empty($paginatedIncidents) ? 'display:block' : 'display:none' ?>">
                <i class="bi bi-search" style="font-size:36px;color:#cbd5e1;display:block;margin-bottom:10px;"></i>
                <p class="text-muted mb-0">Walang nahanap na incident.</p>
            </div>

            <!-- Pagination -->
            <?php if ($totalPages > 1): ?>
            <div class="d-flex justify-content-between align-items-center px-4 py-3 border-top" style="background:#fafbfc;">
                <div class="text-muted small">
                    Showing <?= number_format($offset + 1) ?>–<?= number_format(min($offset + $perPage, $totalFiltered)) ?> of <?= number_format($totalFiltered) ?>
                </div>
                <nav>
                    <ul class="pagination pagination-sm mb-0">
                        <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                            <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>">
                                <i class="bi bi-chevron-left"></i>
                            </a>
                        </li>
                        <?php
                        $start = max(1, $page - 2);
                        $end   = min($totalPages, $page + 2);
                        for ($p = $start; $p <= $end; $p++):
                        ?>
                        <li class="page-item <?= $p == $page ? 'active' : '' ?>">
                            <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $p])) ?>">
                                <?= $p ?>
                            </a>
                        </li>
                        <?php endfor; ?>
                        <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
                            <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>">
                                <i class="bi bi-chevron-right"></i>
                            </a>
                        </li>
                    </ul>
                </nav>
            </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>

</div>

<style>
/* Add a little styling for the pagination buttons inside the card */
.pagination .page-link { color: var(--qc-blue); border-color: var(--border); }
.pagination .page-item.active .page-link { background: var(--qc-blue); border-color: var(--qc-blue); color: #fff; }
</style>
</body>
</html>