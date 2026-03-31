<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../models/Incident.php';
requireRole('admin');

$model      = new Incident();
$incidentId = (int)($_POST['incident_id']  ?? 0);
$responderId = (int)($_POST['responder_id'] ?? 0);

if (!$incidentId) {
    header('Location: /irms/portal/admin/incidents.php?error=Invalid+incident.');
    exit;
}

// Pag walang napili, i-unassign
if ($responderId) {
    $model->assignResponder($incidentId, $responderId);
} else {
    $pdo = (function() { require __DIR__ . '/../config/db.php'; return $pdo; })();
    $pdo->prepare("UPDATE incidents SET assigned_to = NULL WHERE id = ?")
        ->execute([$incidentId]);
}

header('Location: /irms/portal/admin/incidents.php?success=' .
       urlencode('Na-update na ang assignment.'));
exit;