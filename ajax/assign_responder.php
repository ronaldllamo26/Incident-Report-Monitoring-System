<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/db.php';   // $pdo available na dito
require_once __DIR__ . '/../models/Incident.php';
require_once __DIR__ . '/../includes/functions.php';
requireRole('admin');
validate_csrf();

$model       = new Incident();
$incidentId  = (int)($_POST['incident_id']  ?? 0);
$responderId = (int)($_POST['responder_id'] ?? 0);

if (!$incidentId) {
    header('Location: /irms/portal/admin/incidents.php?error=' .
           urlencode('Invalid incident.'));
    exit;
}

$incident = $model->getById($incidentId);

if ($responderId) {
    try {
        // May piniling responder — i-assign
        $model->assignResponder($incidentId, $responderId);

        // ── IN-APP NOTIFICATION — Notify responder na na-assign siya ──
        $incidentTitle = $incident['title'] ?? 'Incident #' . $incidentId;
        createNotification(
            $pdo,
            $responderId,
            'Bagong Assigned Incident',
            'Na-assign sa iyo ang incident: "' . $incidentTitle . '".',
            $incidentId
        );

        // ── Notify citizen na may naka-assign na sa report nila ──
        if (!empty($incident['reporter_id'])) {
            createNotification(
                $pdo,
                (int)$incident['reporter_id'],
                'Responder Assigned',
                'May na-assign nang responder sa iyong report na "' . $incidentTitle . '".',
                $incidentId
            );
        }
    } catch (Exception $e) {
        error_log("Assignment Error: " . $e->getMessage());
        header('Location: /irms/portal/admin/incidents.php?error=' . urlencode('Failed to assign responder: ' . $e->getMessage()));
        exit;
    }
} else {
    // Walang pinili — i-unassign
    try {
        $pdo->prepare("UPDATE incidents SET assigned_to = NULL, updated_at = NOW() WHERE id = ?")
            ->execute([$incidentId]);
    } catch (Exception $e) {
        error_log("Unassignment Error: " . $e->getMessage());
    }
}

header('Location: /irms/portal/admin/incidents.php?success=' .
       urlencode('Na-update na ang assignment.'));
exit;