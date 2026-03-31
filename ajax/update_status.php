<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../models/Incident.php';
require_once __DIR__ . '/../config/mailer.php';
requireRole(['responder', 'admin']);

$model  = new Incident();
$action = $_POST['action'] ?? '';
$id     = (int)($_POST['incident_id'] ?? 0);
$user   = currentUser();

if (!$id) {
    header('Location: /irms/portal/responder/dashboard.php');
    exit;
}

$incident = $model->getById($id);
if (!$incident) {
    header('Location: /irms/portal/responder/dashboard.php');
    exit;
}

// Determine redirect back — admin o responder
$back = $_SESSION['role'] === 'admin'
    ? '/irms/portal/admin/view_incident.php?id=' . $id
    : '/irms/portal/responder/view_incident.php?id=' . $id;

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

    // ── EMAIL NOTIFICATION ─────────────────────
    $fullIncident = $model->getById($id);

    if ($fullIncident) {
        $citizenEmail = null;

        if ($fullIncident['reporter_id']) {
            $stmt = $pdo->prepare("SELECT email, name FROM users WHERE id = ?");
            $stmt->execute([$fullIncident['reporter_id']]);
            $citizen = $stmt->fetch();
            if ($citizen) {
                $citizenEmail = $citizen['email'];
                $fullIncident['reporter_name'] = $citizen['name'];
            }
        } elseif (!empty($fullIncident['anon_email'])) {
            $citizenEmail = $fullIncident['anon_email'];
            $fullIncident['reporter_name'] = $fullIncident['anon_name'] ?: 'Anonymous';
        }

        if ($citizenEmail) {
            sendMail(
                $citizenEmail,
                'Status Update sa Iyong Report #' . $id . ' — IRMS',
                mailStatusUpdate($fullIncident, $newStatus, $remarks)
            );
        }
    }
    // ──────────────────────────────────────────

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