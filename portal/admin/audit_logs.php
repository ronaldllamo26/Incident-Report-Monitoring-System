<?php
require_once __DIR__ . '/../../includes/auth.php';
requireRole('admin');
require_once __DIR__ . '/../../config/db.php';

// ── FILTERS ────────────────────────────────────────────
$action      = $_GET['action']      ?? '';
$target_type = $_GET['target_type'] ?? '';
$user_id     = (int)($_GET['user_id'] ?? 0);
$date_from   = $_GET['date_from']   ?? '';
$date_to     = $_GET['date_to']     ?? '';
$search      = trim($_GET['search'] ?? '');

// ── BUILD QUERY ────────────────────────────────────────
$where  = [];
$params = [];

if ($action)      { $where[] = 'al.action = ?';      $params[] = $action; }
if ($target_type) { $where[] = 'al.target_type = ?'; $params[] = $target_type; }
if ($user_id)     { $where[] = 'al.user_id = ?';     $params[] = $user_id; }
if ($date_from)   { $where[] = 'DATE(al.created_at) >= ?'; $params[] = $date_from; }
if ($date_to)     { $where[] = 'DATE(al.created_at) <= ?'; $params[] = $date_to; }
if ($search)      { $where[] = '(al.action LIKE ? OR al.details LIKE ? OR al.ip_address LIKE ?)';
                    $params[] = "%$search%"; $params[] = "%$search%"; $params[] = "%$search%"; }

$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

// Pagination
$perPage = 25;
$page    = max(1, (int)($_GET['page'] ?? 1));
$offset  = ($page - 1) * $perPage;

// Total count
$countSql  = "SELECT COUNT(*) FROM audit_logs al $whereSql";
$countStmt = $pdo->prepare($countSql);
$countStmt->execute($params);
$total     = (int)$countStmt->fetchColumn();
$totalPages = ceil($total / $perPage);

// Logs with user JOIN
$sql = "
    SELECT al.*,
           COALESCE(u.name, 'System / Anonymous') AS user_name,
           u.role AS user_role
    FROM audit_logs al
    LEFT JOIN users u ON al.user_id = u.id
    $whereSql
    ORDER BY al.created_at DESC
    LIMIT $perPage OFFSET $offset
";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$logs = $stmt->fetchAll();

// ── ANALYTICS DATA ─────────────────────────────────────
$trendDays = 14;
$trendStmt = $pdo->prepare("
    SELECT DATE(created_at) as date, COUNT(*) as count
    FROM audit_logs
    WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
    GROUP BY DATE(created_at)
    ORDER BY date ASC
");
$trendStmt->execute([$trendDays]);
$trendData = $trendStmt->fetchAll(PDO::FETCH_KEY_PAIR);

// Fill gaps in dates
$dates = [];
$counts = [];
for ($i = $trendDays; $i >= 0; $i--) {
    $d = date('Y-m-d', strtotime("-$i days"));
    $dates[]  = date('M d', strtotime($d));
    $counts[] = $trendData[$d] ?? 0;
}

// Filter options
$actions      = $pdo->query("SELECT DISTINCT action FROM audit_logs ORDER BY action")->fetchAll(PDO::FETCH_COLUMN);
$target_types = $pdo->query("SELECT DISTINCT target_type FROM audit_logs WHERE target_type IS NOT NULL ORDER BY target_type")->fetchAll(PDO::FETCH_COLUMN);
$users        = $pdo->query("SELECT id, name, role FROM users ORDER BY name")->fetchAll();

// ── ACTION COLOR/ICON MAP ──────────────────────────────
function getActionStyle(string $action): array {
    if (str_contains($action, 'login'))    return ['bg-success',  'bi-box-arrow-in-right', 'Login'];
    if (str_contains($action, 'logout'))   return ['bg-secondary','bi-box-arrow-right',    'Logout'];
    if (str_contains($action, 'create') || str_contains($action, 'submit') || str_contains($action, 'register'))
                                           return ['bg-primary',  'bi-plus-circle',        'Create'];
    if (str_contains($action, 'update') || str_contains($action, 'assign') || str_contains($action, 'status'))
                                           return ['bg-warning',  'bi-pencil',             'Update'];
    if (str_contains($action, 'delete'))   return ['bg-danger',   'bi-trash',              'Delete'];
    if (str_contains($action, 'escalat'))  return ['bg-danger',   'bi-exclamation-triangle','Escalate'];
    if (str_contains($action, 'report'))   return ['bg-info',     'bi-file-earmark-text',  'Report'];
    return ['bg-secondary', 'bi-circle', 'Action'];
}
?>
<!DOCTYPE html>
<html lang="fil">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Audit Logs — IRMS Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <?php include __DIR__ . '/../../includes/sidebar_style.php'; ?>
    <style>
        /* Log timeline style */
        .log-row { transition: background 0.15s; }
        .log-row:hover { background: #f8faff !important; }

        .action-badge {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            font-size: 11px;
            font-weight: 600;
            padding: 3px 8px;
            border-radius: 4px;
            white-space: nowrap;
        }

        .target-chip {
            font-size: 11px;
            background: #f1f5f9;
            color: #475569;
            padding: 2px 8px;
            border-radius: 4px;
            border: 1px solid #e2e8f0;
            white-space: nowrap;
        }

        .ip-text {
            font-family: monospace;
            font-size: 11px;
            color: #94a3b8;
        }

        .details-text {
            font-size: 12px;
            color: #64748b;
            max-width: 280px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        /* Stats cards */
        .stat-mini {
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            padding: 14px 18px;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .stat-mini-icon {
            width: 40px; height: 40px;
            border-radius: 8px;
            display: flex; align-items: center; justify-content: center;
            font-size: 18px;
            flex-shrink: 0;
        }
        .stat-mini-num  { font-size: 20px; font-weight: 700; line-height: 1; }
        .stat-mini-label { font-size: 11px; color: #94a3b8; margin-top: 2px; }

        /* Pagination */
        .page-link { font-size: 13px; }
    </style>
</head>
<body class="bg-light">
<div class="d-flex">

    <!-- Sidebar -->
    <?php include __DIR__ . '/../../includes/sidebar_admin.php'; ?>

    <!-- Main Content -->
    <div class="main-content">
        <div class="top-nav d-flex justify-content-between align-items-center">
            <div class="d-flex align-items-center gap-3">
                <button class="hamburger btn btn-sm btn-outline-secondary"
                        style="display:none;align-items:center;justify-content:center;
                               width:36px;height:36px;padding:0;"
                        onclick="toggleSidebar()">
                    <i class="bi bi-list fs-5"></i>
                </button>
                <div>
                    <h6 class="fw-semibold mb-0">Audit Logs</h6>
                    <div class="text-muted" style="font-size:11px;">
                        Lahat ng aksyon sa sistema — <?= number_format($total) ?> records
                    </div>
                </div>
            </div>
            <div class="d-flex align-items-center gap-2">
                <a href="/irms/portal/admin/export_audit_csv.php?<?= http_build_query($_GET) ?>"
                   class="btn btn-success btn-sm me-1"
                   title="Export to CSV">
                    <i class="bi bi-file-earmark-spreadsheet me-1"></i> Export CSV
                </a>
                <a href="/irms/portal/admin/audit_logs.php"
                   class="btn btn-outline-secondary btn-sm me-2 d-none d-md-inline-block">
                    <i class="bi bi-arrow-counterclockwise me-1"></i> I-reset
                </a>
                <?php include __DIR__ . '/../../includes/notification_bell.php'; ?>
            </div>
        </div>

        <div class="p-4">

            <!-- Stats row -->
            <?php
            $todayCount  = (int)$pdo->query("SELECT COUNT(*) FROM audit_logs WHERE DATE(created_at) = CURDATE()")->fetchColumn();
            $weekCount   = (int)$pdo->query("SELECT COUNT(*) FROM audit_logs WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)")->fetchColumn();
            $loginCount  = (int)$pdo->query("SELECT COUNT(*) FROM audit_logs WHERE action LIKE '%login%' AND DATE(created_at) = CURDATE()")->fetchColumn();
            $errorCount  = (int)$pdo->query("SELECT COUNT(*) FROM audit_logs WHERE action LIKE '%error%' OR action LIKE '%fail%'")->fetchColumn();
            ?>
            <div class="row g-3 mb-4">
                <div class="col-6 col-md-3">
                    <div class="stat-mini">
                        <div class="stat-mini-icon" style="background:#dbeafe;color:#1d4ed8;">
                            <i class="bi bi-calendar-day"></i>
                        </div>
                        <div>
                            <div class="stat-mini-num"><?= number_format($todayCount) ?></div>
                            <div class="stat-mini-label">Ngayon</div>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="stat-mini">
                        <div class="stat-mini-icon" style="background:#dcfce7;color:#16a34a;">
                            <i class="bi bi-calendar-week"></i>
                        </div>
                        <div>
                            <div class="stat-mini-num"><?= number_format($weekCount) ?></div>
                            <div class="stat-mini-label">Nitong 7 Araw</div>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="stat-mini">
                        <div class="stat-mini-icon" style="background:#f0fdf4;color:#15803d;">
                            <i class="bi bi-box-arrow-in-right"></i>
                        </div>
                        <div>
                            <div class="stat-mini-num"><?= number_format($loginCount) ?></div>
                            <div class="stat-mini-label">Logins Ngayon</div>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="stat-mini">
                        <div class="stat-mini-icon" style="background:#fef2f2;color:#dc2626;">
                            <i class="bi bi-exclamation-triangle"></i>
                        </div>
                        <div>
                            <div class="stat-mini-num"><?= number_format($errorCount) ?></div>
                            <div class="stat-mini-label">Errors / Fails</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Analytics Chart -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white border-0 pt-4 px-4 d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="fw-bold mb-0">Activity Peak Insights</h6>
                        <small class="text-muted">System load analysis for the last <?= $trendDays ?> days</small>
                    </div>
                    <div class="badge bg-primary-subtle text-primary border border-primary-subtle px-3 py-2" style="background: rgba(13, 110, 253, 0.1) !important;">
                        <i class="bi bi-stars me-1"></i> AI Optimization Suggestion Active
                    </div>
                </div>
                <div class="card-body px-4 pb-4">
                    <div style="height: 220px;">
                        <canvas id="auditTrendChart"></canvas>
                    </div>
                </div>
            </div>

            <!-- Filters -->
            <div class="card border-0 shadow-sm mb-3">
                <div class="card-body p-3">
                    <form method="GET" class="row g-2 align-items-end">
                        <!-- Search -->
                        <div class="col-md-3">
                            <label class="form-label small fw-medium mb-1">Maghanap</label>
                            <input type="text" name="search" class="form-control form-control-sm"
                                   placeholder="Action, details, IP..."
                                   value="<?= htmlspecialchars($search) ?>">
                        </div>
                        <!-- Action -->
                        <div class="col-md-2">
                            <label class="form-label small fw-medium mb-1">Action</label>
                            <select name="action" class="form-select form-select-sm">
                                <option value="">Lahat</option>
                                <?php foreach ($actions as $a): ?>
                                    <option value="<?= htmlspecialchars($a) ?>"
                                        <?= $action === $a ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($a) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <!-- Target Type -->
                        <div class="col-md-2">
                            <label class="form-label small fw-medium mb-1">Target</label>
                            <select name="target_type" class="form-select form-select-sm">
                                <option value="">Lahat</option>
                                <?php foreach ($target_types as $t): ?>
                                    <option value="<?= htmlspecialchars($t) ?>"
                                        <?= $target_type === $t ? 'selected' : '' ?>>
                                        <?= ucfirst(htmlspecialchars($t)) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <!-- User -->
                        <div class="col-md-2">
                            <label class="form-label small fw-medium mb-1">User</label>
                            <select name="user_id" class="form-select form-select-sm">
                                <option value="">Lahat ng Users</option>
                                <?php foreach ($users as $u): ?>
                                    <option value="<?= $u['id'] ?>"
                                        <?= $user_id === (int)$u['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($u['name']) ?>
                                        (<?= $u['role'] ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <!-- Date From -->
                        <div class="col-md-1">
                            <label class="form-label small fw-medium mb-1">Mula</label>
                            <input type="date" name="date_from" class="form-control form-control-sm"
                                   value="<?= htmlspecialchars($date_from) ?>">
                        </div>
                        <!-- Date To -->
                        <div class="col-md-1">
                            <label class="form-label small fw-medium mb-1">Hanggang</label>
                            <input type="date" name="date_to" class="form-control form-control-sm"
                                   value="<?= htmlspecialchars($date_to) ?>">
                        </div>
                        <!-- Buttons -->
                        <div class="col-md-1 d-flex gap-1">
                            <button type="submit" class="btn btn-primary btn-sm flex-fill">
                                <i class="bi bi-funnel"></i>
                            </button>
                            <a href="/irms/portal/admin/audit_logs.php"
                               class="btn btn-outline-secondary btn-sm">
                                <i class="bi bi-x"></i>
                            </a>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Logs Table -->
            <div class="card border-0 shadow-sm">
                <div class="card-body p-0">
                    <?php if (empty($logs)): ?>
                        <div class="text-center py-5 text-muted">
                            <i class="bi bi-journal-x fs-1 d-block mb-2"></i>
                            Walang audit logs na nahanap.
                        </div>
                    <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th class="ps-3 small text-muted fw-medium" style="width:160px;">Petsa / Oras</th>
                                    <th class="small text-muted fw-medium" style="width:140px;">User</th>
                                    <th class="small text-muted fw-medium" style="width:160px;">Action</th>
                                    <th class="small text-muted fw-medium" style="width:120px;">Target</th>
                                    <th class="small text-muted fw-medium">Details</th>
                                    <th class="small text-muted fw-medium pe-3" style="width:120px;">IP Address</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($logs as $log):
                                    [$badgeClass, $icon, $label] = getActionStyle($log['action']);
                                ?>
                                <tr class="log-row">
                                    <!-- Date/Time -->
                                    <td class="ps-3">
                                        <div class="small fw-medium">
                                            <?= date('M d, Y', strtotime($log['created_at'])) ?>
                                        </div>
                                        <div class="text-muted" style="font-size:11px;">
                                            <?= date('h:i:s A', strtotime($log['created_at'])) ?>
                                        </div>
                                    </td>

                                    <!-- User -->
                                    <td>
                                        <div class="small fw-medium">
                                            <?= htmlspecialchars($log['user_name']) ?>
                                        </div>
                                        <?php if ($log['user_role']): ?>
                                        <div style="font-size:10px;">
                                            <span class="badge bg-light text-dark border">
                                                <?= ucfirst($log['user_role']) ?>
                                            </span>
                                        </div>
                                        <?php endif; ?>
                                    </td>

                                    <!-- Action badge -->
                                    <td>
                                        <span class="action-badge text-white <?= $badgeClass ?>">
                                            <i class="bi <?= $icon ?>"></i>
                                            <?= htmlspecialchars($log['action']) ?>
                                        </span>
                                    </td>

                                    <!-- Target -->
                                    <td>
                                        <?php if ($log['target_type']): ?>
                                        <span class="target-chip">
                                            <?= ucfirst(htmlspecialchars($log['target_type'])) ?>
                                            <?php if ($log['target_id']): ?>
                                                #<?= $log['target_id'] ?>
                                            <?php endif; ?>
                                        </span>
                                        <?php else: ?>
                                            <span class="text-muted small">—</span>
                                        <?php endif; ?>
                                    </td>

                                    <!-- Details -->
                                    <td>
                                        <?php if ($log['details']): ?>
                                        <div class="details-text" title="<?= htmlspecialchars($log['details']) ?>">
                                            <?= htmlspecialchars($log['details']) ?>
                                        </div>
                                        <?php else: ?>
                                            <span class="text-muted small">—</span>
                                        <?php endif; ?>
                                    </td>

                                    <!-- IP Address -->
                                    <td class="pe-3">
                                        <span class="ip-text">
                                            <?= htmlspecialchars($log['ip_address'] ?? '—') ?>
                                        </span>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination -->
                    <?php if ($totalPages > 1): ?>
                    <div class="d-flex justify-content-between align-items-center px-3 py-3 border-top">
                        <div class="text-muted small">
                            Showing <?= number_format($offset + 1) ?>–<?= number_format(min($offset + $perPage, $total)) ?>
                            of <?= number_format($total) ?> records
                        </div>
                        <nav>
                            <ul class="pagination pagination-sm mb-0">
                                <!-- Previous -->
                                <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                                    <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>">
                                        <i class="bi bi-chevron-left"></i>
                                    </a>
                                </li>

                                <?php
                                // Show max 5 page buttons
                                $startPage = max(1, $page - 2);
                                $endPage   = min($totalPages, $page + 2);
                                for ($p = $startPage; $p <= $endPage; $p++):
                                ?>
                                <li class="page-item <?= $p === $page ? 'active' : '' ?>">
                                    <a class="page-link"
                                       href="?<?= http_build_query(array_merge($_GET, ['page' => $p])) ?>">
                                        <?= $p ?>
                                    </a>
                                </li>
                                <?php endfor; ?>

                                <!-- Next -->
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

        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Auto-submit form on filter change (optional UX improvement)
document.querySelectorAll('select[name="action"], select[name="target_type"], select[name="user_id"]')
    .forEach(function(el) {
        el.addEventListener('change', function() {
            this.closest('form').submit();
        });
    });

// Audit Trend Chart
const ctx = document.getElementById('auditTrendChart').getContext('2d');
const gradient = ctx.createLinearGradient(0, 0, 0, 400);
gradient.addColorStop(0, 'rgba(13, 110, 253, 0.2)');
gradient.addColorStop(1, 'rgba(13, 110, 253, 0)');

new Chart(ctx, {
    type: 'line',
    data: {
        labels: <?= json_encode($dates) ?>,
        datasets: [{
            label: 'System Actions',
            data: <?= json_encode($counts) ?>,
            borderColor: '#0d6efd',
            borderWidth: 3,
            backgroundColor: gradient,
            fill: true,
            tension: 0.4,
            pointRadius: 4,
            pointBackgroundColor: '#fff',
            pointBorderColor: '#0d6efd',
            pointHoverRadius: 6
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: { display: false },
            tooltip: {
                backgroundColor: '#1e293b',
                padding: 12,
                cornerRadius: 8,
                titleFont: { size: 13, weight: 'bold' },
                bodyFont: { size: 12 }
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                grid: { color: '#f1f5f9' },
                ticks: { font: { size: 11 } }
            },
            x: {
                grid: { display: false },
                ticks: { font: { size: 11 } }
            }
        }
    }
});
</script>
</body>
</html>