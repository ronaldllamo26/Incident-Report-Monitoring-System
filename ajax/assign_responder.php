<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/db.php';   // $pdo available na dito
require_once __DIR__ . '/../models/Incident.php';
requireRole('admin');

$model       = new Incident();
$incidentId  = (int)($_POST['incident_id']  ?? 0);
$responderId = (int)($_POST['responder_id'] ?? 0);

if (!$incidentId) {
    header('Location: /irms/portal/admin/incidents.php?error=' .
           urlencode('Invalid incident.'));
    exit;
}

if ($responderId) {
    // May piniling responder — i-assign
    $model->assignResponder($incidentId, $responderId);
} else {
    // Walang pinili — i-unassign
    $pdo->prepare("UPDATE incidents SET assigned_to = NULL, updated_at = NOW() WHERE id = ?")
        ->execute([$incidentId]);
}

header('Location: /irms/portal/admin/incidents.php?success=' .
       urlencode('Na-update na ang assignment.'));
exit;