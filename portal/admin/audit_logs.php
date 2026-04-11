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
    <style>
        .sidebar { width: 220px; min-height: 100vh; background: #1e293b; }
        .sidebar .nav-link { color: #94a3b8; font-size: 14px; padding: 10px 20px; border-radius: 6px; margin: 2px 8px; }
        .sidebar .nav-link:hover,
        .sidebar .nav-link.active { background: #334155; color: #fff; }
        .sidebar .nav-link i { width: 20px; }
        .main-content { flex: 1; overflow-y: auto; }
        .top-nav { background: #fff; border-bottom: 1px solid #e2e8f0; padding: 12px 24px; }

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
    <div class="sidebar d-flex flex-column py-3">
        <div class="px-4 mb-4">
            <div class="text-white fw-semibold fs-6">
                <i class="bi bi-shield-check me-2"></i>IRMS
            </div>
            <div class="text-secondary" style="font-size:11px;">Admin Panel</div>
        </div>
        <nav class="flex-column nav">
            <a href="/irms/portal/admin/dashboard.php"   class="nav-link"><i class="bi bi-speedometer2 me-2"></i> Dashboard</a>
            <a href="/irms/portal/admin/incidents.php"   class="nav-link"><i class="bi bi-exclamation-triangle me-2"></i> Incidents</a>
            <a href="/irms/portal/admin/users.php"       class="nav-link"><i class="bi bi-people me-2"></i> Users</a>
            <a href="/irms/portal/admin/categories.php"  class="nav-link"><i class="bi bi-tags me-2"></i> Categories</a>
            <a href="/irms/portal/admin/reports.php"     class="nav-link"><i class="bi bi-file-earmark-bar-graph me-2"></i> Reports</a>
            <a href="/irms/portal/admin/audit_logs.php"  class="nav-link active"><i class="bi bi-journal-text me-2"></i> Audit Logs</a>
        </nav>
        <div class="mt-auto px-3">
            <div class="text-secondary small px-2 mb-2">
                <i class="bi bi-person-circle me-1"></i>
                <?= htmlspecialchars($_SESSION['name']) ?>
            </div>
            <a href="/irms/controllers/AuthController.php?action=logout"
               class="nav-link text-danger">
                <i class="bi bi-box-arrow-right me-2"></i> Logout
            </a>
        </div>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <div class="top-nav d-flex justify-content-between align-items-center">
            <div>
                <h6 class="fw-semibold mb-0">Audit Logs</h6>
                <div class="text-muted" style="font-size:11px;">
                    Lahat ng aksyon sa sistema — <?= number_format($total) ?> records
                </div>
            </div>
            <a href="/irms/portal/admin/audit_logs.php"
               class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-arrow-counterclockwise me-1"></i> I-reset ang Filters
            </a>
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
</script>
</body>
</html>