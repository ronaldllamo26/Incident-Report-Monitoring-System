<?php
/**
 * ajax/check_escalations.php
 * Background worker to flag breached SLAs and escalate them.
 */
require_once __DIR__ . '/../includes/auth.php';
requireRole('admin'); // Only admins can trigger this check via dashboard
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../models/Incident.php';

header('Content-Type: application/json');

$model = new Incident();
$breached = $model->getBreachedUnescalated();
$count = 0;

foreach ($breached as $inc) {
    $model->markEscalated($inc['id']);
    
    // Log in timeline
    $pdo->prepare("INSERT INTO status_logs (incident_id, changed_by, old_status, new_status, remarks) 
                   VALUES (?, NULL, ?, ?, '⚠️ Auto-Escalated: SLA Breach detected by Background Monitor.')")
        ->execute([$inc['id'], $inc['status'], $inc['status']]);
        
    // Create notification for all admins
    $adminStmt = $pdo->prepare("SELECT id FROM users WHERE role = 'admin' AND is_active = 1");
    $adminStmt->execute();
    while ($admin = $adminStmt->fetch()) {
        createNotification(
            $pdo,
            (int)$admin['id'],
            '🔴 SLA BREACH: Incident #' . $inc['id'],
            'Ang report "' . $inc['title'] . '" ay lumagpas na sa deadline! Auto-escalated to Command Center.',
            $inc['id']
        );
    }
    $count++;
}

echo json_encode(['status' => 'success', 'escalated' => $count]);