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
        :root {
            --primary: #2563eb;
            --bg-main: #f8fafc;
            --text-main: #0f172a;
            --text-muted: #64748b;
            --card-bg: #ffffff;
            --border: #e2e8f0;
        }

        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background-color: var(--bg-main);
            color: var(--text-main);
            margin: 0;
            line-height: 1.5;
        }

        /* ── Navigation ── */
        .topbar {
            background: #fff;
            border-bottom: 1px solid var(--border);
            position: sticky; top: 0; z-index: 1000;
        }
        .topbar-inner { max-width: 1200px; margin: 0 auto; padding: 0 24px;
                        display: flex; align-items: center; justify-content: space-between; height: 64px; }
        .brand { font-size: 18px; font-weight: 800; color: var(--text-main); text-decoration: none;
                 display: flex; align-items: center; gap: 10px; }
        .brand span { color: var(--primary); }
        .citizen-pill { background: #eff6ff; border: 1px solid #dbeafe;
                        color: var(--primary); font-size: 10px; font-weight: 700; padding: 2px 10px;
                        border-radius: 6px; text-transform: uppercase; letter-spacing: 0.5px; }

        /* Profile Btn */
        .profile-btn { background: none; border: 1px solid var(--border);
                       color: var(--text-main); border-radius: 8px; padding: 6px 12px; cursor: pointer;
                       display: flex; align-items: center; gap: 8px; font-size: 13px;
                       font-weight: 600; transition: all 0.2s; }
        .profile-btn:hover { background: #f1f5f9; }
        .profile-avatar { width: 26px; height: 26px; background: var(--primary);
                          border-radius: 50%; display: flex; align-items: center;
                          justify-content: center; font-size: 11px; font-weight: 800; color: #fff; }

        .profile-dropdown {
            position: absolute; top: calc(100% + 8px); right: 0;
            background: #fff; border-radius: 12px; box-shadow: 0 10px 25px rgba(0,0,0,0.1);
            border: 1px solid var(--border); min-width: 220px; overflow: hidden;
            opacity: 0; visibility: hidden; transform: translateY(-5px);
            transition: all 0.2s; z-index: 1100;
        }
        .profile-dropdown.open { opacity: 1; visibility: visible; transform: translateY(0); }
        .dropdown-item-custom { display: flex; align-items: center; gap: 10px; padding: 12px 16px;
                                font-size: 13px; color: var(--text-main); text-decoration: none;
                                transition: background 0.2s; border: none; background: none; width: 100%; text-align: left; }
        .dropdown-item-custom:hover { background: #f8fafc; }

        /* ── Hero ── */
        .hero { padding: 40px 0 20px; }
        .hero-content { max-width: 1200px; margin: 0 auto; padding: 0 24px;
                        display: flex; justify-content: space-between; align-items: center; }
        .hero h2 { font-size: 24px; font-weight: 800; margin: 0; }
        .hero p { color: var(--text-muted); font-size: 14px; margin: 5px 0 0; }
        
        .btn-new-report {
            background: var(--primary); color: #fff; border: none;
            padding: 10px 20px; border-radius: 8px; font-size: 13px; font-weight: 700;
            text-decoration: none; display: inline-flex; align-items: center; gap: 8px;
            transition: background 0.2s;
        }
        .btn-new-report:hover { background: #1d4ed8; color: #fff; }

        /* ── Stats ── */
        .stat-card { background: #fff; border: 1px solid var(--border);
                     padding: 20px; border-radius: 12px; transition: all 0.2s; cursor: pointer; }
        .stat-card:hover { border-color: var(--primary); box-shadow: 0 4px 12px rgba(0,0,0,0.05); }
        .stat-card.active-filter { border-color: var(--primary); background: #eff6ff; }
        .stat-num { font-size: 28px; font-weight: 800; margin-bottom: 2px; }
        .stat-label { font-size: 11px; color: var(--text-muted); font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; }

        /* ── Table Card ── */
        .reports-card { background: #fff; border-radius: 12px; border: 1px solid var(--border); overflow: hidden; margin-top: 24px; }
        .reports-card-header { padding: 20px 24px; border-bottom: 1px solid var(--border); 
                               display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px; }

        /* ── Filters & Search ── */
        .filter-tabs { display: flex; gap: 6px; }
        .filter-tab {
            padding: 6px 14px; border-radius: 8px; font-size: 12px; font-weight: 600;
            border: 1px solid var(--border); background: #fff; color: var(--text-muted);
            cursor: pointer; transition: all 0.2s;
        }
        .filter-tab.active { background: var(--text-main); border-color: var(--text-main); color: #fff; }
        
        .search-box input {
            background: #f8fafc; border: 1px solid var(--border);
            padding: 8px 12px 8px 36px; border-radius: 8px; font-size: 13px; width: 240px;
        }
        .search-box .search-icon { color: var(--text-muted); left: 12px; }

        /* ── Table ── */
        .table thead th { background: #f8fafc; color: var(--text-muted); font-size: 11px; text-transform: uppercase; letter-spacing: 1px; padding: 16px 20px; }
        .table tbody td { padding: 16px 20px; border-bottom: 1px solid var(--border); font-size: 13px; }
        
        .tracking-chip { background: #f1f5f9; color: var(--text-main); padding: 3px 8px; border-radius: 4px; font-family: monospace; font-weight: 700; border: 1px solid var(--border); }

        /* ── Quick Links ── */
        .quick-link {
            background: #fff; border: 1px solid var(--border); padding: 20px; border-radius: 12px;
            color: var(--text-main); text-decoration: none; transition: all 0.2s; display: block;
        }
        .quick-link:hover { border-color: var(--primary); box-shadow: 0 4px 12px rgba(0,0,0,0.05); }
        .quick-link-icon { font-size: 20px; margin-bottom: 12px; color: var(--primary); }
        .ql-title { font-size: 14px; font-weight: 700; margin-bottom: 2px; }
        .ql-sub { font-size: 11px; color: var(--text-muted); }

        .main-wrap { max-width: 1200px; margin: 0 auto; padding: 0 24px 60px; }
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
                                <div class="d-flex justify-content-center gap-1">
                                    <a href="/irms/citizen/view_report.php?id=<?= $r['id'] ?>"
                                       class="btn btn-outline-primary btn-sm"
                                       title="Tingnan">
                                        <i class="bi bi-eye"></i>
                                    </a>
                                    <?php if ($r['status'] === 'resolved' && empty($r['rating'])): ?>
                                        <button type="button" 
                                                class="btn btn-warning btn-sm fw-bold"
                                                onclick="openFeedbackModal(<?= $r['id'] ?>, '<?= addslashes($r['title']) ?>')"
                                                title="Mag-rate">
                                            <i class="bi bi-star-fill"></i>
                                        </button>
                                    <?php elseif (!empty($r['rating'])): ?>
                                        <span class="badge bg-light text-warning border" title="Naka-rate na">
                                            <?= $r['rating'] ?> <i class="bi bi-star-fill"></i>
                                        </span>
                                    <?php endif; ?>
                                </div>
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
    <div class="row g-4 mt-2">
        <div class="col-md-4">
            <a href="/irms/public/track.php" class="quick-link">
                <div class="quick-link-icon"><i class="bi bi-search"></i></div>
                <div class="ql-title">I-track ang Report</div>
                <div class="ql-sub">Mabilisang pag-check gamit ang tracking number.</div>
            </a>
        </div>
        <div class="col-md-4">
            <a href="/irms/citizen/settings.php" class="quick-link">
                <div class="quick-link-icon"><i class="bi bi-person-gear"></i></div>
                <div class="ql-title">I-edit ang Profile</div>
                <div class="ql-sub">Baguhin ang iyong impormasyon at password.</div>
            </a>
        </div>
        <div class="col-md-4">
            <a href="/irms/citizen/report.php" class="quick-link">
                <div class="quick-link-icon"><i class="bi bi-megaphone"></i></div>
                <div class="ql-title">Mag-report ng Bago</div>
                <div class="ql-sub">I-sumite ang iyong mga obserbasyon sa paligid.</div>
            </a>
        </div>
    </div>

    <div class="text-center mt-5 pt-4">
        <a href="/irms/public/privacy.php" class="text-muted small text-decoration-none">
            <i class="bi bi-shield-check me-1"></i> Privacy Policy & Terms of Service
        </a>
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
    <!-- ── Privacy Consent Overlay (Standard for PWA/Mobile) ── -->
    <div id="privacy-consent-overlay" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(15,23,42,0.95); z-index:999999; backdrop-filter:blur(10px); align-items:center; justify-content:center; padding:20px;">
        <div style="background:#fff; width:100%; max-width:400px; border-radius:24px; padding:32px; box-shadow:0 25px 50px -12px rgba(0,0,0,0.5); text-align:center;">
            <img src="/irms/assets/img/QC_LOGO_CIRCLE.png" style="width:80px; margin-bottom:20px;" alt="QC Logo">
            <h3 style="color:#0f172a; font-weight:800; margin-bottom:12px;">Maligayang Pagdating!</h3>
            <p style="color:#64748b; font-size:14px; margin-bottom:24px;">
                Upang maprotektahan ang iyong impormasyon alinsunod sa <strong>Data Privacy Act of 2012</strong>, kailangan naming makuha ang iyong pagsang-ayon sa aming Privacy Policy bago gamitin ang QC-ALERTO app.
            </p>
            <div style="background:#f8fafc; border-radius:12px; padding:16px; margin-bottom:24px; text-align:left;">
                <div style="font-size:12px; color:#1e293b; margin-bottom:8px;"><i class="bi bi-check-circle-fill text-success me-2"></i> Protektado ang iyong personal data.</div>
                <div style="font-size:12px; color:#1e293b; margin-bottom:8px;"><i class="bi bi-check-circle-fill text-success me-2"></i> GPS location ay gagamitim lamang sa dispatch.</div>
                <div style="font-size:12px; color:#1e293b;"><i class="bi bi-check-circle-fill text-success me-2"></i> Secure ang pag-upload ng ebidensya.</div>
            </div>
            <button onclick="agreeToPrivacy()" style="width:100%; background:#2563eb; color:#fff; border:none; padding:14px; border-radius:12px; font-weight:700; cursor:pointer; margin-bottom:12px;">
                Sumasang-ayon ako (I Agree)
            </button>
            <a href="/irms/public/privacy.php" target="_blank" style="font-size:13px; color:#64748b; text-decoration:none;">
                Basahin ang buong Privacy Policy
            </a>
        </div>
    </div>

    <script>
        function agreeToPrivacy() {
            localStorage.setItem('qc_privacy_agreed', 'true');
            document.getElementById('privacy-consent-overlay').style.display = 'none';
        }

        document.addEventListener('DOMContentLoaded', function() {
            setTimeout(function() {
                if (!localStorage.getItem('qc_privacy_agreed')) {
                    document.getElementById('privacy-consent-overlay').style.display = 'flex';
                }
            }, 1000);
        });
    </script>
    <!-- ── Feedback Modal ── -->
    <div class="modal fade" id="feedbackModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg" style="border-radius:20px;">
                <div class="modal-header border-0 pb-0">
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4 text-center">
                    <div class="mb-3">
                        <i class="bi bi-chat-square-heart text-primary" style="font-size: 48px;"></i>
                    </div>
                    <h5 class="fw-bold mb-1">Kumusta ang aming serbisyo?</h5>
                    <p class="text-muted small mb-4" id="feedback-report-title"></p>
                    
                    <form id="feedbackForm">
                        <input type="hidden" name="incident_id" id="feedback-incident-id">
                        
                        <div class="mb-4">
                            <label class="form-label small fw-bold d-block mb-3">I-rate ang aming response:</label>
                            <div class="d-flex justify-content-center gap-3">
                                <?php for($i=1; $i<=5; $i++): ?>
                                    <label class="star-rating">
                                        <input type="radio" name="rating" value="<?= $i ?>" required style="display:none;">
                                        <i class="bi bi-star-fill fs-2 cursor-pointer star-icon" data-value="<?= $i ?>" style="color: #e2e8f0; transition: color 0.2s;"></i>
                                    </label>
                                <?php endfor; ?>
                            </div>
                        </div>

                        <div class="mb-4">
                            <label class="form-label small fw-bold d-block text-start">Iba pang komento (Optional):</label>
                            <textarea name="comment" class="form-control border-0 bg-light" rows="3" 
                                      placeholder="Ano ang masasabi mo sa bilis at kalidad ng aming serbisyo?" 
                                      style="border-radius:12px; font-size:13px;"></textarea>
                        </div>

                        <button type="submit" class="btn btn-primary w-100 py-2 fw-bold" style="border-radius:12px;">
                            I-submit ang Feedback
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <style>
        .star-icon.active { color: #f59e0b !important; }
        .cursor-pointer { cursor: pointer; }
    </style>

    <script>
        function openFeedbackModal(id, title) {
            document.getElementById('feedback-incident-id').value = id;
            document.getElementById('feedback-report-title').textContent = 'Para sa: ' + title;
            
            // Reset stars
            document.querySelectorAll('.star-icon').forEach(s => s.classList.remove('active'));
            document.getElementById('feedbackForm').reset();
            
            const modal = new bootstrap.Modal(document.getElementById('feedbackModal'));
            modal.show();
        }

        // Star Interaction
        document.querySelectorAll('.star-icon').forEach(star => {
            star.addEventListener('click', function() {
                const val = this.dataset.value;
                document.querySelectorAll('.star-icon').forEach(s => {
                    if (s.dataset.value <= val) s.classList.add('active');
                    else s.classList.remove('active');
                });
                // Check the radio button
                document.querySelector(`input[name="rating"][value="${val}"]`).checked = true;
            });
        });

        // Submit Feedback
        document.getElementById('feedbackForm').addEventListener('submit', function(e) {
            e.preventDefault();
            const btn = this.querySelector('button[type="submit"]');
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Isinusumite...';

            const formData = new FormData(this);
            fetch('/irms/ajax/submit_feedback.php', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    alert('Salamat sa iyong feedback! Malaking tulong ito para mapabuti ang ating serbisyo. ✨');
                    location.reload();
                } else {
                    alert('Error: ' + data.message);
                    btn.disabled = false;
                    btn.innerHTML = 'I-submit ang Feedback';
                }
            })
            .catch(err => {
                console.error(err);
                alert('May problema sa pag-submit. Pakisubukang muli.');
                btn.disabled = false;
                btn.innerHTML = 'I-submit ang Feedback';
            });
        });
    </script>
</body>
</html>