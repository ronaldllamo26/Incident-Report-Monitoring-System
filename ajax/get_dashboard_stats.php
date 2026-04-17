<?php
require_once __DIR__ . '/../includes/auth.php';
requireRole('admin');
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../models/Incident.php';

$model = new Incident();
$counts = $model->getCountsByStatus();
$kpis   = $model->getGlobalKPIs();
$total  = array_sum($counts);

// Get current active SLA alerts count
$slaCount = (int)$pdo->query("
    SELECT COUNT(*) FROM incidents 
    WHERE sla_deadline IS NOT NULL 
      AND status NOT IN ('resolved','closed')
      AND sla_deadline < DATE_ADD(NOW(), INTERVAL 2 HOUR)
")->fetchColumn();

header('Content-Type: application/json');
echo json_encode([
    'total'    => $total,
    'pending'  => $counts['pending'],
    'progress' => $counts['in_progress'],
    'sla'      => $kpis['sla'],
    'mtta'     => $kpis['mtta'],
    'csat'     => $kpis['csat'],
    'alerts'   => $slaCount
]);
exit;
