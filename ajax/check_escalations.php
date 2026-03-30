<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../models/Incident.php';
requireRole('admin');

header('Content-Type: application/json');

$model    = new Incident();
$breached = $model->getBreachedUnescalated();
$count    = 0;

foreach ($breached as $inc) {
    $model->markEscalated($inc['id']);
    $count++;

    // Log the escalation
    $pdo->prepare("
        INSERT INTO status_logs (incident_id, changed_by, old_status, new_status, remarks)
        VALUES (?, 1, ?, ?, 'SLA breached — auto-escalated to admin.')
    ")->execute([
        $inc['id'],
        $inc['status'],
        $inc['status'],
    ]);
}

echo json_encode([
    'escalated' => $count,
    'incidents' => array_map(fn($i) => [
        'id'       => $i['id'],
        'title'    => $i['title'],
        'severity' => $i['severity'],
    ], $breached),
]);