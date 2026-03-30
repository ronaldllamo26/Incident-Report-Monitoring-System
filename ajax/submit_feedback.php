<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../models/Incident.php';
requireRole('citizen');

$model      = new Incident();
$user       = currentUser();
$incidentId = (int)($_POST['incident_id'] ?? 0);
$rating     = (int)($_POST['rating']      ?? 0);
$comment    = trim($_POST['comment']      ?? '');

if (!$incidentId || $rating < 1 || $rating > 5) {
    header('Location: /irms/views/citizen/dashboard.php');
    exit;
}

$incident = $model->getById($incidentId);

// Check na sa citizen to at resolved na
if (!$incident
    || $incident['reporter_id'] != $user['id']
    || $incident['status'] !== 'resolved') {
    header('Location: /irms/views/citizen/dashboard.php');
    exit;
}

$model->submitFeedback($incidentId, $user['id'], $rating, $comment);

header('Location: /irms/views/citizen/view_report.php?id=' . $incidentId .
       '&success=' . urlencode('Salamat sa iyong feedback!'));
exit;