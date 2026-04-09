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

// Determine redirect — admin o responder
$back = $_SESSION['role'] === 'admin'
    ? '/irms/portal/admin/view_incident.php?id=' . $id
    : '/irms/portal/responder/view_incident.php?id=' . $id;

// ── UPDATE STATUS ──────────────────────────────────────
if ($action === 'update_status') {
    $newStatus = $_POST['new_status'] ?? '';
    $remarks   = trim($_POST['remarks'] ?? '');
    $oldStatus = $incident['status']; // Kuha sa DB — hindi sa POST para sure

    $allowed = ['pending', 'in_progress', 'resolved', 'closed'];
    if (!in_array($newStatus, $allowed)) {
        header('Location: ' . $back . '&error=' . urlencode('Invalid status.'));
        exit;
    }

    // ── STATUS TRANSITION VALIDATION ──────────────────
    // Prevent illegal transitions
    $validTransitions = [
        'pending'     => ['in_progress', 'closed'],
        'in_progress' => ['resolved', 'closed', 'pending'],
        'resolved'    => ['closed'],
        'closed'      => [], // closed = final, walang pwedeng transition
    ];

    if (!in_array($newStatus, $validTransitions[$oldStatus] ?? [])) {
        header('Location: ' . $back . '&error=' .
               urlencode('Hindi pwedeng i-change ang status mula ' .
                         ucwords(str_replace('_', ' ', $oldStatus)) . ' papunta ' .
                         ucwords(str_replace('_', ' ', $newStatus)) . '.'));
        exit;
    }
    // ──────────────────────────────────────────────────

    // I-update ang status at mag-log
    $model->updateStatus($id, $newStatus, $user['id'], $oldStatus, $remarks);

    // ── EMAIL NOTIFICATION ─────────────────────────────
    $fullIncident = $model->getById($id);

    if ($fullIncident) {
        $citizenEmail = null;
        $citizenName  = 'Anonymous';

        if (!empty($fullIncident['reporter_id'])) {
            $stmt = $pdo->prepare("SELECT email, name FROM users WHERE id = ?");
            $stmt->execute([$fullIncident['reporter_id']]);
            $citizen = $stmt->fetch();
            if ($citizen) {
                $citizenEmail = $citizen['email'];
                $citizenName  = $citizen['name'];
            }
        } elseif (!empty($fullIncident['anon_email'])) {
            $citizenEmail = $fullIncident['anon_email'];
            $citizenName  = $fullIncident['anon_name'] ?: 'Anonymous';
        }

        $fullIncident['reporter_name'] = $citizenName;

        if ($citizenEmail) {
            sendMail(
                $citizenEmail,
                'Status Update sa Iyong Report #' . $id . ' — QC-ALERTO',
                mailStatusUpdate($fullIncident, $newStatus, $remarks)
            );
        }
    }
    // ──────────────────────────────────────────────────

    header('Location: ' . $back . '&success=' .
           urlencode('Na-update na ang status sa ' .
                     ucwords(str_replace('_', ' ', $newStatus)) . '.'));
    exit;
}

// ── ADD RESPONSE / COMMENT ─────────────────────────────
if ($action === 'respond') {
    $message = trim($_POST['message'] ?? '');

    if (empty($message)) {
        header('Location: ' . $back . '&error=' .
               urlencode('Hindi pwedeng blank ang response.'));
        exit;
    }

    $model->addResponse($id, $user['id'], $message);

    header('Location: ' . $back . '&success=' .
           urlencode('Na-send na ang response.'));
    exit;
}

// Fallback
header('Location: ' . $back);
exit;