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
$paginatedIncidents = $model->getAll($filters, $perPage, $offset);
if ($search) {
    $paginatedIncidents = array_filter($paginatedIncidents, function($i) use ($search) {
        return str_contains(strtolower($i['title']), strtolower($search)) || 
               str_contains(strtolower($i['location']), strtolower($search));
    });
}
$totalFiltered = $model->countTotal($filters);
$totalPages    = ceil($totalFiltered / $perPage);

$stLabel = ['pending' => 'Pending', 'in_progress' => 'In Progress', 'resolved' => 'Resolved', 'closed' => 'Closed'];
?>
<!DOCTYPE html>
<html lang="fil">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Responder Center — QC-ALERTO</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="/irms/assets/css/theme-responder.css" rel="stylesheet">
    <script src="/irms/assets/js/theme-responder.js"></script>
    <style>
        body {
            font-family: 'Outfit', sans-serif;
            background: var(--bg-gradient);
            color: var(--text-main);
            min-height: 100vh;
            margin: 0;
            overflow-x: hidden;
        }

        /* ── Glassmorphism Sidebar ── */
        .sidebar {
            width: 260px;
            background: rgba(15, 23, 42, 0.8);
            backdrop-filter: blur(20px);
            border-right: 1px solid var(--glass-border);
            height: 100vh;
            position: fixed;
            left: 0; top: 0;
            z-index: 1000;
            padding: 24px;
        }
        .main-content { margin-left: 260px; padding: 32px; }

        .brand-box { display: flex; align-items: center; gap: 12px; margin-bottom: 48px; }
        .brand-box img { width: 40px; height: 40px; filter: drop-shadow(0 0 8px rgba(59, 130, 246, 0.5)); }
        .brand-name { font-weight: 800; font-size: 18px; letter-spacing: -0.5px; background: linear-gradient(to right, #fff, var(--qc-gold)); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }

        .nav-menu { list-style: none; padding: 0; margin: 0; }
        .nav-item { margin-bottom: 8px; }
        .nav-link-c {
            display: flex; align-items: center; gap: 12px;
            padding: 12px 16px; border-radius: 12px;
            color: var(--text-dim); text-decoration: none;
            transition: all 0.3s; font-weight: 500;
        }
        .nav-link-c:hover { background: var(--glass); color: #fff; transform: translateX(5px); }
        .nav-link-c.active { background: var(--qc-accent); color: #fff; box-shadow: 0 8px 20px rgba(59, 130, 246, 0.3); }

        /* ── Header ── */
        .dashboard-header { 
            display: flex; justify-content: space-between; align-items: flex-end; 
            margin-bottom: 40px; position: sticky; top: 0; z-index: 9999;
            background: rgba(15, 23, 42, 0.8); backdrop-filter: blur(20px);
            padding: 20px 0; border-bottom: 1px solid var(--glass-border);
        }
        .header-title { font-size: 28px; font-weight: 800; margin: 0; }
        .header-sub { color: var(--text-dim); font-size: 14px; }

        .live-clock {
            background: var(--glass); border: 1px solid var(--glass-border);
            padding: 8px 16px; border-radius: 100px; font-size: 13px; font-weight: 600;
            display: flex; align-items: center; gap: 8px; color: var(--qc-gold);
        }
        .pulse-dot { width: 8px; height: 8px; background: var(--qc-gold); border-radius: 50%; box-shadow: 0 0 10px var(--qc-gold); animation: pulse 2s infinite; }
        @keyframes pulse { 0% { opacity: 1; } 50% { opacity: 0.3; } 100% { opacity: 1; } }

        /* ── KPI Cards ── */
        .kpi-grid { display: grid; grid-template-columns: repeat(5, 1fr); gap: 20px; margin-bottom: 40px; }
        .kpi-card {
            background: var(--glass); border: 1px solid var(--glass-border);
            padding: 24px; border-radius: 20px; text-decoration: none;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative; overflow: hidden;
        }
        .kpi-card::before { content: ''; position: absolute; top: 0; left: 0; width: 100%; height: 100%; background: linear-gradient(135deg, transparent, rgba(255,255,255,0.05)); opacity: 0; transition: opacity 0.4s; }
        .kpi-card:hover { transform: translateY(-5px); border-color: rgba(255,255,255,0.2); }
        .kpi-card:hover::before { opacity: 1; }
        .kpi-card.active { background: rgba(59, 130, 246, 0.1); border-color: var(--qc-accent); }

        .kpi-val { font-size: 32px; font-weight: 800; color: #fff; line-height: 1; margin-bottom: 8px; display: block; }
        .kpi-lbl { font-size: 12px; font-weight: 600; color: var(--text-dim); text-transform: uppercase; letter-spacing: 1px; }
        .kpi-icon { position: absolute; right: 20px; bottom: 20px; font-size: 24px; opacity: 0.2; }

        /* ── Table Area ── */
        .glass-panel {
            background: rgba(255, 255, 255, 0.02);
            backdrop-filter: blur(10px);
            border: 1px solid var(--glass-border);
            border-radius: 24px;
            padding: 32px;
        }
        .panel-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; }
        
        .search-wrap { position: relative; width: 300px; }
        .search-wrap input {
            width: 100%; background: rgba(0,0,0,0.2); border: 1px solid var(--glass-border);
            padding: 10px 16px 10px 40px; border-radius: 12px; color: #fff; font-size: 14px;
            transition: all 0.3s;
        }
        .search-wrap input:focus { border-color: var(--qc-accent); background: rgba(0,0,0,0.4); outline: none; }
        .search-wrap i { position: absolute; left: 14px; top: 50%; transform: translateY(-50%); color: var(--text-dim); }

        .modern-table { width: 100%; border-collapse: separate; border-spacing: 0 12px; margin-top: -12px; }
        .modern-table th { color: var(--text-dim); font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 1.5px; padding: 0 20px; border: none; }
        .modern-table tr { transition: all 0.3s; }
        .modern-table td { background: rgba(255,255,255,0.03); padding: 16px 20px; border-top: 1px solid var(--glass-border); border-bottom: 1px solid var(--glass-border); }
        .modern-table td:first-child { border-left: 1px solid var(--glass-border); border-radius: 16px 0 0 16px; }
        .modern-table td:last-child { border-right: 1px solid var(--glass-border); border-radius: 0 16px 16px 0; }
        .modern-table tr:hover td { background: rgba(255,255,255,0.08); transform: scale(1.005); }

        .badge-modern { padding: 6px 12px; border-radius: 8px; font-size: 11px; font-weight: 700; display: inline-flex; align-items: center; gap: 6px; }
        .st-pending { background: rgba(251, 191, 36, 0.1); color: var(--qc-gold); }
        .st-in_progress { background: rgba(59, 130, 246, 0.1); color: var(--qc-accent); }
        .st-resolved { background: rgba(34, 197, 94, 0.1); color: #22c55e; }
        .st-closed { background: rgba(148, 163, 184, 0.1); color: var(--text-dim); }

        .btn-action {
            background: var(--qc-accent); color: #fff; padding: 8px 20px;
            border-radius: 100px; font-size: 12px; font-weight: 700;
            text-decoration: none; transition: all 0.3s;
            box-shadow: 0 4px 15px rgba(59, 130, 246, 0.2);
        }
        .btn-action:hover { background: #2563eb; color: #fff; transform: translateY(-2px); box-shadow: 0 6px 20px rgba(59, 130, 246, 0.4); }

        /* ── Toasts ── */
        .toast-container { position: fixed; top: 24px; right: 24px; z-index: 2000; }
        .qc-toast {
            background: rgba(15, 23, 42, 0.95); backdrop-filter: blur(10px);
            border: 1px solid var(--qc-accent); border-left: 4px solid var(--qc-accent);
            padding: 16px 20px; border-radius: 12px; color: #fff;
            display: flex; align-items: center; gap: 15px; margin-bottom: 12px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.5);
            animation: slideIn 0.5s ease-out;
        }
        @keyframes slideIn { from { transform: translateX(100%); opacity: 0; } to { transform: translateX(0); opacity: 1; } }

        /* ── Responsive ── */
        @media (max-width: 1200px) { .kpi-grid { grid-template-columns: repeat(3, 1fr); } }
        @media (max-width: 768px) {
            .sidebar { display: none; }
            .main-content { margin-left: 0; padding: 16px; }
            .kpi-grid { grid-template-columns: 1fr 1fr; }
            .dashboard-header { flex-direction: column; align-items: flex-start; gap: 16px; }
            .panel-header { flex-direction: column; gap: 16px; }
            .search-wrap { width: 100%; }
        }
    </style>
</head>
<body>

<?php include __DIR__ . '/../../includes/sidebar_responder.php'; ?>

<main class="main-content">
    <div class="dashboard-header">
        <div>
            <h1 class="header-title">Command Center</h1>
            <div class="header-sub">Responder Portal &middot; Assigned Incidents</div>
        </div>
        <div class="live-clock">
            <?php include __DIR__ . '/../../includes/notification_bell.php'; ?>
            <div style="width: 1px; height: 20px; background: var(--glass-border); margin: 0 10px;"></div>
            <div class="pulse-dot"></div>
            <span id="current-time">00:00:00 AM</span>
            <div style="width: 1px; height: 20px; background: var(--glass-border); margin: 0 10px;"></div>
            <button class="theme-toggle-btn border-0 p-0" onclick="toggleTheme()" title="Toggle Dark/Light Mode" style="background:transparent; color: var(--text-dim);">
                <i class="bi bi-moon-stars-fill dark-only"></i>
                <i class="bi bi-sun-fill light-only"></i>
            </button>
        </div>
    </div>

    <!-- KPI Grid -->
    <div class="kpi-grid">
        <a href="?status=" class="kpi-card <?= empty($_GET['status']) ? 'active' : '' ?>">
            <span class="kpi-val"><?= $counts['all'] ?></span>
            <span class="kpi-lbl">Total Reports</span>
            <i class="bi bi-collection-fill kpi-icon"></i>
        </a>
        <a href="?status=pending" class="kpi-card <?= ($_GET['status']??'') === 'pending' ? 'active' : '' ?>">
            <span class="kpi-val" style="color: var(--qc-gold);"><?= $counts['pending'] ?></span>
            <span class="kpi-lbl">New Alerts</span>
            <i class="bi bi-bell-fill kpi-icon" style="color: var(--qc-gold);"></i>
        </a>
        <a href="?status=in_progress" class="kpi-card <?= ($_GET['status']??'') === 'in_progress' ? 'active' : '' ?>">
            <span class="kpi-val" style="color: var(--qc-accent);"><?= $counts['in_progress'] ?></span>
            <span class="kpi-lbl">In Progress</span>
            <i class="bi bi-activity kpi-icon" style="color: var(--qc-accent);"></i>
        </a>
        <a href="?status=resolved" class="kpi-card <?= ($_GET['status']??'') === 'resolved' ? 'active' : '' ?>">
            <span class="kpi-val" style="color: #22c55e;"><?= $counts['resolved'] ?></span>
            <span class="kpi-lbl">Resolved</span>
            <i class="bi bi-check-all kpi-icon" style="color: #22c55e;"></i>
        </a>
        <a href="?status=closed" class="kpi-card <?= ($_GET['status']??'') === 'closed' ? 'active' : '' ?>">
            <span class="kpi-val"><?= $counts['closed'] ?></span>
            <span class="kpi-lbl">Archived</span>
            <i class="bi bi-archive-fill kpi-icon"></i>
        </a>
    </div>

    <!-- Main Panel -->
    <div class="glass-panel">
        <div class="panel-header">
            <h5 style="font-weight: 700; margin: 0;">Incident Log</h5>
            <form method="GET" class="search-wrap">
                <input type="hidden" name="status" value="<?= htmlspecialchars($_GET['status']??'') ?>">
                <i class="bi bi-search"></i>
                <input type="text" name="search" placeholder="Search reports..." value="<?= htmlspecialchars($search) ?>">
            </form>
        </div>

        <?php if (empty($paginatedIncidents)): ?>
            <div style="text-align: center; padding: 80px 0;">
                <i class="bi bi-cloud-check" style="font-size: 64px; color: var(--glass-border); display: block; margin-bottom: 20px;"></i>
                <h5 style="font-weight: 700;">No Incidents Found</h5>
                <p style="color: var(--text-dim);">Lahat ng cases ay cleared o walang tugma sa search.</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="modern-table">
                    <thead>
                        <tr>
                            <th>Ref #</th>
                            <th>Details</th>
                            <th>Category</th>
                            <th>Status</th>
                            <th>Date</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($paginatedIncidents as $inc): 
                            $stClass = 'st-' . $inc['status'];
                        ?>
                            <tr>
                                <td style="font-family: monospace; font-weight: 700; color: var(--qc-gold);">
                                    #<?= str_pad($inc['id'], 5, '0', STR_PAD_LEFT) ?>
                                </td>
                                <td>
                                    <div style="font-weight: 700; font-size: 14px; margin-bottom: 4px;"><?= htmlspecialchars($inc['title']) ?></div>
                                    <div style="font-size: 12px; color: var(--text-dim);">
                                        <i class="bi bi-geo-alt-fill me-1"></i><?= htmlspecialchars(mb_strimwidth($inc['location'], 0, 45, "...")) ?>
                                    </div>
                                </td>
                                <td>
                                    <span style="font-size: 12px; font-weight: 600; color: var(--text-dim);">
                                        <i class="bi bi-tag-fill me-1 opacity-50"></i><?= htmlspecialchars($inc['category_name']) ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="badge-modern <?= $stClass ?>">
                                        <i class="bi bi-dot" style="font-size: 24px; line-height: 0;"></i>
                                        <?= $stLabel[$inc['status']] ?? ucfirst($inc['status']) ?>
                                    </span>
                                </td>
                                <td style="font-size: 13px; color: var(--text-dim);">
                                    <?= date('M d, Y', strtotime($inc['reported_at'])) ?>
                                </td>
                                <td>
                                    <a href="/irms/portal/responder/view_incident.php?id=<?= $inc['id'] ?>" class="btn-action">Deploy</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <?php if ($totalPages > 1): ?>
                <div class="d-flex justify-content-between align-items-center mt-4 pt-3 border-top border-secondary opacity-75">
                    <div style="font-size: 12px; color: var(--text-dim);">
                        Showing Page <?= $page ?> of <?= $totalPages ?>
                    </div>
                    <div class="d-flex gap-2">
                        <?php if ($page > 1): ?>
                            <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>" class="btn-action" style="padding: 6px 16px; background: var(--glass);">Prev</a>
                        <?php endif; ?>
                        <?php if ($page < $totalPages): ?>
                            <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>" class="btn-action" style="padding: 6px 16px; background: var(--glass);">Next</a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</main>

<div class="toast-container" id="toast-container"></div>

<!-- Audio for notifications -->
<audio id="alert-sound" preload="auto">
    <source src="https://assets.mixkit.co/active_storage/sfx/2869/2869-preview.mp3" type="audio/mpeg">
</audio>

<script>
    // ── Clock Logic ──
    function updateClock() {
        const now = new Date();
        const time = now.toLocaleTimeString('en-US', { hour12: true, hour: '2-digit', minute: '2-digit', second: '2-digit' });
        document.getElementById('current-time').innerText = time;
    }
    setInterval(updateClock, 1000);
    updateClock();

    // ── Polling Logic ──
    let lastCount = <?= $counts['pending'] ?>;
    
    function checkNewAssignments() {
        fetch(`/irms/ajax/check_assignments.php?last_count=${lastCount}`)
            .then(res => res.json())
            .then(data => {
                if (data.new_detected) {
                    showToast("New Incident Assigned!", "May bago kang report na kailangang aksyunan agad.");
                    playAlert();
                    // Optional: Auto refresh table after 3 seconds
                    setTimeout(() => location.reload(), 3000);
                }
                lastCount = data.current_count;
            })
            .catch(err => console.error("Polling Error:", err));
    }

    function showToast(title, msg) {
        const container = document.getElementById('toast-container');
        const toast = document.createElement('div');
        toast.className = 'qc-toast';
        toast.innerHTML = `
            <i class="bi bi-exclamation-triangle-fill" style="font-size: 24px; color: var(--qc-gold);"></i>
            <div>
                <div style="font-weight: 800; font-size: 15px;">${title}</div>
                <div style="font-size: 12px; opacity: 0.8;">${msg}</div>
            </div>
        `;
        container.appendChild(toast);
        setTimeout(() => toast.style.opacity = '0', 4500);
        setTimeout(() => toast.remove(), 5000);
    }

    function playAlert() {
        const audio = document.getElementById('alert-sound');
        audio.play().catch(e => console.log("Audio play blocked by browser. User interaction needed."));
    }

    // Start polling every 15 seconds
    setInterval(checkNewAssignments, 15000);
</script>

</body>
</html>