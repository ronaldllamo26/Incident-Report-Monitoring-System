<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../models/Incident.php';
require_once __DIR__ . '/../config/mailer.php';

header('Content-Type: application/json');

$model    = new Incident();
$breached = $model->getBreachedUnescalated();
$count    = 0;

// Kunin ang lahat ng admin emails
$admins = $pdo->query("
    SELECT email, name FROM users
    WHERE role = 'admin' AND is_active = 1
")->fetchAll();

foreach ($breached as $inc) {
    $model->markEscalated($inc['id']);
    $count++;

    // Log the escalation
    $pdo->prepare("
        INSERT INTO status_logs (incident_id, changed_by, old_status, new_status, remarks)
        VALUES (?, NULL, ?, ?, 'SLA breached — auto-escalated to admin.')
    ")->execute([
        $inc['id'],
        $inc['status'],
        $inc['status'],
    ]);

    // ── EMAIL SA LAHAT NG ADMIN ────────────────
    foreach ($admins as $admin) {
        sendMail(
            $admin['email'],
            '🚨 SLA Breach — Incident #' . $inc['id'] . ' Escalated',
            mailEscalationAlert($inc)
        );
    }
    // ──────────────────────────────────────────
}

echo json_encode([
    'escalated' => $count,
    'incidents' => array_map(fn($i) => [
        'id'       => $i['id'],
        'title'    => $i['title'],
        'severity' => $i['severity'],
    ], $breached),
]);