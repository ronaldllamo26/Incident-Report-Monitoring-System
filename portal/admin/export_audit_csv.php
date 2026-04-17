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

$sql = "
    SELECT al.*,
           COALESCE(u.name, 'System / Anonymous') AS user_name,
           u.role AS user_role
    FROM audit_logs al
    LEFT JOIN users u ON al.user_id = u.id
    $whereSql
    ORDER BY al.created_at DESC
";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$logs = $stmt->fetchAll();

// ── CSV GENERATION ─────────────────────────────────────
$filename = 'IRMS_AuditLogs_' . date('Ymd_His') . '.csv';

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=' . $filename);

$output = fopen('php://output', 'w');

// BOM for Excel UTF-8 support
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

// Header
fputcsv($output, [
    'Date & Time',
    'User',
    'Role',
    'Action',
    'Target Type',
    'Target ID',
    'Details',
    'IP Address'
]);

// Data
foreach ($logs as $log) {
    fputcsv($output, [
        $log['created_at'],
        $log['user_name'],
        $log['user_role'] ?: 'N/A',
        $log['action'],
        $log['target_type'] ?: 'N/A',
        $log['target_id'] ?: 'N/A',
        $log['details'],
        $log['ip_address'] ?: 'N/A'
    ]);
}

fclose($output);
exit;
