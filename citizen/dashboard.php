<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/db.php';
requireRole('citizen');

$user = currentUser();

// ── Fetch reports ────────────────────────────────────────────────
$stmt = $pdo->prepare("
    SELECT i.*, c.name AS category_name
    FROM incidents i
    JOIN categories c ON i.category_id = c.id
    WHERE i.reporter_id = ?
    ORDER BY i.reported_at DESC
");
$stmt->execute([$user['id']]);
$reports = $stmt->fetchAll();

$counts = ['all' => count($reports), 'pending' => 0, 'in_progress' => 0, 'resolved' => 0, 'closed' => 0];
foreach ($reports as $r) {
    if (isset($counts[$r['status']])) $counts[$r['status']]++;
}

$sevColor = ['low' => 'success', 'medium' => 'warning', 'high' => 'danger', 'critical' => 'dark'];
$stColor  = ['pending' => 'warning', 'in_progress' => 'primary', 'resolved' => 'success', 'closed' => 'secondary'];
$stLabel  = ['pending' => 'Pending', 'in_progress' => 'In Progress', 'resolved' => 'Resolved', 'closed' => 'Closed'];
?>
<!DOCTYPE html>
<html lang="fil">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>My Dashboard — QC-ALERTO</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        * { box-sizing: border-box; }
        :root {
            --qc-blue: #1e293b;
            --qc-red:  #CE1126;
            --navy: #0f172a; --navy2: #1e293b; --accent: var(--qc-red);
            --muted: #94a3b8; --border: rgba(255,255,255,0.08);
        }
        body { font-family: 'Plus Jakarta Sans', sans-serif; background: #f1f5f9; margin: 0; }

        /* ── Topbar ─────────────────────────────────────────── */
        .topbar {
            background: var(--qc-blue); padding: 0;
            position: sticky; top: 0; z-index: 200;
            border-bottom: 3px solid var(--qc-red);
            box-shadow: 0 2px 20px rgba(0,0,0,0.3);
        }
        .topbar-inner { max-width: 1200px; margin: 0 auto; padding: 0 20px;
                        display: flex; align-items: center; justify-content: space-between; height: 58px; }
        .brand { font-size: 18px; font-weight: 800; color: #fff; text-decoration: none;
                 display: flex; align-items: center; gap: 8px; }
        .brand span { color: var(--accent); }
        .citizen-pill { background: rgba(99,102,241,0.15); border: 1px solid rgba(99,102,241,0.3);
                        color: #a5b4fc; font-size: 10px; font-weight: 700; padding: 3px 9px;
                        border-radius: 20px; letter-spacing: 0.5px; }

        /* Profile dropdown */
        .profile-btn { background: rgba(255,255,255,0.07); border: 1px solid rgba(255,255,255,0.1);
                       color: #fff; border-radius: 10px; padding: 6px 12px; cursor: pointer;
                       display: flex; align-items: center; gap: 8px; font-size: 13px;
                       font-weight: 600; transition: background 0.2s; position: relative; }
        .profile-btn:hover { background: rgba(255,255,255,0.12); }
        .profile-avatar { width: 28px; height: 28px; background: var(--accent);
                          border-radius: 50%; display: flex; align-items: center;
                          justify-content: center; font-size: 12px; font-weight: 800; color: #fff; }
        .profile-dropdown {
            position: absolute; top: calc(100% + 10px); right: 0;
            background: #fff; border-radius: 12px; box-shadow: 0 12px 40px rgba(0,0,0,0.15);
            border: 1px solid #e2e8f0; min-width: 200px; overflow: hidden;
            opacity: 0; visibility: hidden; transform: translateY(-8px);
            transition: all 0.2s; z-index: 300;
        }
        .profile-dropdown.open { opacity: 1; visibility: visible; transform: translateY(0); }
        .dropdown-header { padding: 14px 16px; border-bottom: 1px solid #f1f5f9; }
        .dropdown-header .name { font-size: 13px; font-weight: 700; color: #0f172a; }
        .dropdown-header .email { font-size: 11px; color: #64748b; }
        .dropdown-item-custom { display: flex; align-items: center; gap: 10px; padding: 10px 16px;
                                font-size: 13px; color: #374151; text-decoration: none;
                                transition: background 0.15s; cursor: pointer; border: none;
                                background: none; width: 100%; }
        .dropdown-item-custom:hover { background: #f8fafc; color: #1e293b; }
        .dropdown-item-custom.danger { color: #dc2626; }
        .dropdown-item-custom.danger:hover { background: #fef2f2; }
        .dropdown-divider { height: 1px; background: #f1f5f9; }

        /* ── Welcome Hero ───────────────────────────────────── */
        .hero {
            background: linear-gradient(135deg, #1e293b 0%, #111827 100%);
            position: relative; overflow: hidden; padding: 28px 0;
        }
        .hero::before {
            content: ''; position: absolute; top: -40%; right: -10%; width: 300px; height: 300px;
            background: radial-gradient(circle, rgba(239,68,68,0.15) 0%, transparent 70%);
            border-radius: 50%;
        }
        .hero-content { max-width: 1200px; margin: 0 auto; padding: 0 20px;
                        display: flex; justify-content: space-between; align-items: center;
                        flex-wrap: wrap; gap: 16px; position: relative; }
        .hero h5 { color: #fff; font-size: 20px; font-weight: 800; margin: 0 0 4px; }
        .hero p { color: var(--muted); font-size: 13px; margin: 0; }
        .btn-new-report {
            background: var(--accent); color: #fff; border: none;
            padding: 10px 20px; border-radius: 10px; font-size: 13px; font-weight: 700;
            text-decoration: none; display: inline-flex; align-items: center; gap: 6px;
            transition: all 0.2s; white-space: nowrap;
        }
        .btn-new-report:hover { background: #dc2626; color: #fff; transform: translateY(-1px);
                                box-shadow: 0 6px 20px rgba(239,68,68,0.35); }

        /* ── Stat Cards ─────────────────────────────────────── */
        .stat-card { background: #fff; border-radius: 12px; padding: 18px 20px;
                     border: 1px solid #e2e8f0; transition: transform 0.2s, box-shadow 0.2s;
                     cursor: pointer; }
        .stat-card:hover { transform: translateY(-2px); box-shadow: 0 6px 20px rgba(0,0,0,0.08); }
        .stat-card.active-filter { border-color: #1e293b; box-shadow: 0 0 0 3px rgba(30,41,59,0.10); }
        .stat-num { font-size: 30px; font-weight: 800; line-height: 1; margin-bottom: 4px; }
        .stat-label { font-size: 12px; color: #64748b; font-weight: 500; }

        /* ── Reports Table Card ─────────────────────────────── */
        .reports-card { background: #fff; border-radius: 14px; box-shadow: 0 1px 4px rgba(0,0,0,0.06);
                        border: 1px solid #e2e8f0; overflow: hidden; }
        .reports-card-header {
            padding: 16px 20px; border-bottom: 1px solid #f1f5f9;
            display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 12px;
        }

        /* ── Filter Tabs ────────────────────────────────────── */
        .filter-tabs { display: flex; gap: 4px; flex-wrap: wrap; }
        .filter-tab {
            padding: 5px 14px; border-radius: 20px; font-size: 12px; font-weight: 600;
            border: 1.5px solid #e2e8f0; background: #f8fafc; color: #64748b;
            cursor: pointer; transition: all 0.15s; white-space: nowrap;
        }
        .filter-tab:hover { border-color: #1e293b; color: #1e293b; background: rgba(30,41,59,0.05); }
        .filter-tab.active { background: #1e293b; border-color: #1e293b; color: #fff; }

        /* ── Search Bar ─────────────────────────────────────── */
        .search-box { position: relative; }
        .search-box input {
            padding: 7px 12px 7px 34px; border: 1.5px solid #e2e8f0;
            border-radius: 8px; font-size: 13px; outline: none;
            transition: border-color 0.2s; background: #f8fafc; min-width: 200px;
        }
        .search-box input:focus { border-color: #3b82f6; background: #fff; }
        .search-box .search-icon { position: absolute; left: 10px; top: 50%; transform: translateY(-50%);
                                   color: #94a3b8; font-size: 13px; }

        /* ── Table ──────────────────────────────────────────── */
        .table th { font-size: 11px; font-weight: 700; color: #64748b;
                    text-transform: uppercase; letter-spacing: 0.5px; }
        .table td { font-size: 13px; vertical-align: middle; }
        .tracking-chip { font-size: 11px; background: #f1f5f9; color: #475569;
                         padding: 2px 8px; border-radius: 6px; font-family: monospace;
                         font-weight: 600; letter-spacing: 0.5px; }
        .row-hidden { display: none; }

        /* ── Empty State ────────────────────────────────────── */
        .empty-state { text-align: center; padding: 60px 20px; }
        .empty-icon { font-size: 48px; color: #cbd5e1; margin-bottom: 12px; }

        /* ── Quick Links ────────────────────────────────────── */
        .quick-link {
            display: flex; align-items: center; gap: 12px; padding: 16px;
            background: #fff; border-radius: 12px; border: 1px solid #e2e8f0;
            text-decoration: none; color: #1e293b; transition: all 0.2s;
        }
        .quick-link:hover { border-color: currentColor; transform: translateY(-1px);
                            box-shadow: 0 4px 12px rgba(0,0,0,0.07); }
        .quick-link-icon { width: 40px; height: 40px; border-radius: 10px;
                           display: flex; align-items: center; justify-content: center;
                           font-size: 18px; flex-shrink: 0; }
        .ql-title { font-size: 13px; font-weight: 600; margin-bottom: 2px; }
        .ql-sub { font-size: 11px; color: #64748b; }

        .main-wrap { max-width: 1200px; margin: 0 auto; padding: 24px 20px; }
    </style>
</head>
<body>

<!-- ── Topbar ─────────────────────────────────────────────────── -->
<nav class="topbar">
    <div class="topbar-inner">
        <div class="d-flex align-items-center gap-3">
            <a href="/irms/citizen/dashboard.php" class="brand">
                <img src="/irms/assets/img/QC_LOGO_CIRCLE.png"
                     style="height:30px;width:30px;object-fit:contain;" alt="">
                QC-<span>ALERTO</span>
            </a>
            <span class="citizen-pill">CITIZEN</span>
        </div>

        <div class="d-flex align-items-center gap-3">
            <?php include __DIR__ . '/../includes/notification_bell.php'; ?>

            <!-- Profile dropdown -->
            <div style="position:relative;" id="profile-menu-wrap">
                <button class="profile-btn" onclick="toggleDropdown()" id="profile-toggle">
                    <div class="profile-avatar">
                        <?= strtoupper(substr($user['name'], 0, 1)) ?>
                    </div>
                    <span class="d-none d-md-inline">
                        <?= htmlspecialchars(explode(' ', $user['name'])[0]) ?>
                    </span>
                    <i class="bi bi-chevron-down" style="font-size:11px;opacity:0.7;"></i>
                </button>
                <div class="profile-dropdown" id="profile-dropdown">
                    <div class="dropdown-header">
                        <div class="name"><?= htmlspecialchars($user['name']) ?></div>
                        <div class="email"><?= htmlspecialchars($user['email']) ?></div>
                    </div>
                    <a href="/irms/citizen/settings.php" class="dropdown-item-custom">
                        <i class="bi bi-person-gear"></i> I-edit ang Profile
                    </a>
                    <a href="/irms/citizen/settings.php#password" class="dropdown-item-custom">
                        <i class="bi bi-key"></i> Baguhin ang Password
                    </a>
                    <div class="dropdown-divider"></div>
                    <a href="/irms/controllers/AuthController.php?action=logout" class="dropdown-item-custom danger">
                        <i class="bi bi-box-arrow-right"></i> Mag-logout
                    </a>
                </div>
            </div>
        </div>
    </div>
</nav>

<!-- ── Hero ───────────────────────────────────────────────────── -->
<div class="hero">
    <div class="hero-content">
        <div>
            <h5>Magandang araw, <?= htmlspecialchars(explode(' ', $user['name'])[0]) ?>!</h5>
            <p>Dito makikita mo ang lahat ng iyong mga incident report sa Quezon City.</p>
        </div>
    <a href="/irms/citizen/report.php" class="btn-new-report">
                <i class="bi bi-megaphone-fill"></i> Mag-report ng Insidente
            </a>
    </div>
</div>

<div class="main-wrap">

    <!-- ── Stats ──────────────────────────────────────────────── -->
    <div class="row g-3 mb-4">
        <div class="col-6 col-md-3">
            <div class="stat-card active-filter" id="stat-all" onclick="filterByStatus('all', this)">
                <div class="stat-num" style="color:#3b82f6;"><?= $counts['all'] ?></div>
                <div class="stat-label">Lahat ng Reports</div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-card" id="stat-pending" onclick="filterByStatus('pending', this)">
                <div class="stat-num text-warning"><?= $counts['pending'] ?></div>
                <div class="stat-label">Pending</div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-card" id="stat-in_progress" onclick="filterByStatus('in_progress', this)">
                <div class="stat-num text-primary"><?= $counts['in_progress'] ?></div>
                <div class="stat-label">In Progress</div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-card" id="stat-resolved" onclick="filterByStatus('resolved', this)">
                <div class="stat-num text-success"><?= $counts['resolved'] ?></div>
                <div class="stat-label">Resolved</div>
            </div>
        </div>
    </div>

    <!-- ── Reports Table ──────────────────────────────────────── -->
    <div class="reports-card mb-4">
        <div class="reports-card-header">
            <div class="d-flex align-items-center gap-3 flex-wrap">
                <h6 class="fw-bold mb-0" style="font-size:14px;">
                    <i class="bi bi-list-ul me-1 text-primary"></i> Aking mga Report
                </h6>
                <div class="filter-tabs" id="filter-tabs">
                    <span class="filter-tab active" data-status="all" onclick="filterTab('all', this)">
                        Lahat <span class="ms-1 badge bg-primary rounded-pill" style="font-size:10px;"><?= $counts['all'] ?></span>
                    </span>
                    <span class="filter-tab" data-status="pending" onclick="filterTab('pending', this)">
                        Pending <span class="ms-1 badge bg-warning text-dark rounded-pill" style="font-size:10px;"><?= $counts['pending'] ?></span>
                    </span>
                    <span class="filter-tab" data-status="in_progress" onclick="filterTab('in_progress', this)">
                        In Progress <span class="ms-1 badge bg-primary rounded-pill" style="font-size:10px;"><?= $counts['in_progress'] ?></span>
                    </span>
                    <span class="filter-tab" data-status="resolved" onclick="filterTab('resolved', this)">
                        Resolved <span class="ms-1 badge bg-success rounded-pill" style="font-size:10px;"><?= $counts['resolved'] ?></span>
                    </span>
                    <span class="filter-tab" data-status="closed" onclick="filterTab('closed', this)">
                        Closed <span class="ms-1 badge bg-secondary rounded-pill" style="font-size:10px;"><?= $counts['closed'] ?></span>
                    </span>
                </div>
            </div>
            <!-- Search -->
            <div class="search-box">
                <i class="bi bi-search search-icon"></i>
                <input type="text" id="search-reports" placeholder="Hanapin ang report..."
                       oninput="searchReports(this.value)">
            </div>
        </div>

        <?php if (empty($reports)): ?>
            <div class="empty-state">
                <div class="empty-icon"><i class="bi bi-inbox"></i></div>
                <p class="fw-medium text-muted mb-3">Wala ka pang naka-submit na report.</p>
                <a href="/irms/citizen/report.php" class="btn-new-report">
                    <i class="bi bi-plus-lg"></i> Mag-report ngayon
                </a>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0" id="reports-table">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-3">#</th>
                            <th>Tracking #</th>
                            <th>Pamagat</th>
                            <th>Kategorya</th>
                            <th>Kalubhaan</th>
                            <th>Status</th>
                            <th>Petsa</th>
                            <th class="text-center">Aksyon</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($reports as $i => $r): ?>
                        <tr data-status="<?= $r['status'] ?>"
                            data-title="<?= strtolower(htmlspecialchars($r['title'])) ?>"
                            class="report-row">
                            <td class="ps-3 text-muted small"><?= $i + 1 ?></td>
                            <td>
                                <?php if (!empty($r['tracking_number'])): ?>
                                    <span class="tracking-chip"><?= htmlspecialchars($r['tracking_number']) ?></span>
                                <?php else: ?>
                                    <span class="text-muted small">—</span>
                                <?php endif; ?>
                            </td>
                            <td class="fw-semibold" style="max-width:200px;">
                                <div class="text-truncate" title="<?= htmlspecialchars($r['title']) ?>">
                                    <?= htmlspecialchars($r['title']) ?>
                                </div>
                            </td>
                            <td>
                                <span class="badge bg-light text-dark border" style="font-size:11px;">
                                    <?= htmlspecialchars($r['category_name']) ?>
                                </span>
                            </td>
                            <td>
                                <span class="badge bg-<?= $sevColor[$r['severity']] ?? 'secondary' ?>"
                                      style="font-size:11px;">
                                    <?= ucfirst($r['severity']) ?>
                                </span>
                            </td>
                            <td>
                                <span class="badge bg-<?= $stColor[$r['status']] ?? 'secondary' ?>"
                                      style="font-size:11px;">
                                    <?= $stLabel[$r['status']] ?? ucfirst($r['status']) ?>
                                </span>
                            </td>
                            <td class="text-muted small">
                                <?= date('M d, Y', strtotime($r['reported_at'])) ?>
                            </td>
                            <td class="text-center">
                                <a href="/irms/citizen/view_report.php?id=<?= $r['id'] ?>"
                                   class="btn btn-outline-primary btn-sm"
                                   title="Tingnan">
                                    <i class="bi bi-eye"></i>
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <!-- Empty filter result -->
            <div id="no-results" class="empty-state" style="display:none;">
                <div class="empty-icon"><i class="bi bi-search"></i></div>
                <p class="text-muted">Walang report na nahanap.</p>
            </div>
        <?php endif; ?>
    </div>

    <!-- ── Quick Links ────────────────────────────────────────── -->
    <div class="row g-3">
        <div class="col-md-4">
            <a href="/irms/public/track.php" class="quick-link" style="color:#3b82f6;">
                <div class="quick-link-icon" style="background:#eff6ff;">
                    <i class="bi bi-search" style="color:#3b82f6;"></i>
                </div>
                <div>
                    <div class="ql-title">I-track ang Report</div>
                    <div class="ql-sub">Gamit ang tracking number</div>
                </div>
            </a>
        </div>
        <div class="col-md-4">
            <a href="/irms/citizen/settings.php" class="quick-link" style="color:#8b5cf6;">
                <div class="quick-link-icon" style="background:#f5f3ff;">
                    <i class="bi bi-person-gear" style="color:#8b5cf6;"></i>
                </div>
                <div>
                    <div class="ql-title">I-edit ang Profile</div>
                    <div class="ql-sub">Impormasyon at password</div>
                </div>
            </a>
        </div>
        <div class="col-md-4">
            <a href="/irms/citizen/report.php" class="quick-link" style="color:#ef4444;">
                <div class="quick-link-icon" style="background:#fef2f2;">
                    <i class="bi bi-megaphone" style="color:#ef4444;"></i>
                </div>
                <div>
                    <div class="ql-title">Mag-report ng Bago</div>
                    <div class="ql-sub">Mag-file ng incident report</div>
                </div>
            </a>
        </div>
    </div>

</div><!-- /main-wrap -->

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
// ── Profile Dropdown ──────────────────────────────────────────
function toggleDropdown() {
    document.getElementById('profile-dropdown').classList.toggle('open');
}
document.addEventListener('click', function(e) {
    var wrap = document.getElementById('profile-menu-wrap');
    if (wrap && !wrap.contains(e.target))
        document.getElementById('profile-dropdown').classList.remove('open');
});

// ── Filter by Status (stat cards + tabs) ─────────────────────
var currentFilter = 'all';

function filterByStatus(status, cardEl) {
    document.querySelectorAll('.stat-card').forEach(c => c.classList.remove('active-filter'));
    if (cardEl) cardEl.classList.add('active-filter');
    // sync tab
    document.querySelectorAll('.filter-tab').forEach(t => {
        t.classList.toggle('active', t.dataset.status === status);
    });
    currentFilter = status;
    applyFilter();
}

function filterTab(status, tabEl) {
    document.querySelectorAll('.filter-tab').forEach(t => t.classList.remove('active'));
    tabEl.classList.add('active');
    // sync stat card
    document.querySelectorAll('.stat-card').forEach(c => c.classList.remove('active-filter'));
    var card = document.getElementById('stat-' + status);
    if (card) card.classList.add('active-filter');
    currentFilter = status;
    applyFilter();
}

function searchReports(query) {
    applyFilter(query.toLowerCase().trim());
}

function applyFilter(searchQuery) {
    if (searchQuery === undefined) searchQuery = document.getElementById('search-reports').value.toLowerCase().trim();
    var rows = document.querySelectorAll('.report-row');
    var visible = 0;
    rows.forEach(function(row) {
        var matchStatus = currentFilter === 'all' || row.dataset.status === currentFilter;
        var matchSearch = !searchQuery || row.dataset.title.includes(searchQuery);
        if (matchStatus && matchSearch) { row.style.display = ''; visible++; }
        else row.style.display = 'none';
    });
    var noResult = document.getElementById('no-results');
    if (noResult) noResult.style.display = visible === 0 ? 'block' : 'none';
}
</script>
</body>
</html>