<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../models/Incident.php';
requireRole(['responder', 'admin']);

$model  = new Incident();
$action = $_POST['action'] ?? '';
$id     = (int)($_POST['incident_id'] ?? 0);
$user   = currentUser();

if (!$id) {
    header('Location: /irms/views/responder/dashboard.php');
    exit;
}

$incident = $model->getById($id);
if (!$incident) {
    header('Location: /irms/views/responder/dashboard.php');
    exit;
}

// Determine redirect back — admin o responder
$back = $_SESSION['role'] === 'admin'
    ? '/irms/views/admin/incidents.php'
    : '/irms/views/responder/view_incident.php?id=' . $id;

if ($action === 'update_status') {
    $newStatus = $_POST['new_status'] ?? '';
    $remarks   = trim($_POST['remarks'] ?? '');
    $oldStatus = $_POST['old_status']  ?? $incident['status'];

    $allowed = ['pending', 'in_progress', 'resolved', 'closed'];
    if (!in_array($newStatus, $allowed)) {
        header('Location: ' . $back . '&error=' . urlencode('Invalid status.'));
        exit;
    }

    $model->updateStatus($id, $newStatus, $user['id'], $oldStatus, $remarks);
    header('Location: ' . $back . '&success=' . urlencode('Na-update na ang status.'));
    exit;
}

if ($action === 'respond') {
    $message = trim($_POST['message'] ?? '');
    if (empty($message)) {
        header('Location: ' . $back . '&error=' . urlencode('Hindi pwedeng blank ang response.'));
        exit;
    }
    $model->addResponse($id, $user['id'], $message);
    header('Location: ' . $back . '&success=' . urlencode('Na-send na ang response.'));
    exit;
}

header('Location: ' . $back);
exit;