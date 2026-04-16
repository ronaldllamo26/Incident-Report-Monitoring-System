<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../models/Incident.php';
require_once __DIR__ . '/../config/mailer.php';
require_once __DIR__ . '/../includes/functions.php';
requireRole(['responder', 'admin']);
validate_csrf();

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
    $validTransitions = [
        'pending'     => ['in_progress', 'closed'],
        'in_progress' => ['resolved', 'closed', 'pending'],
        'resolved'    => ['closed'],
        'closed'      => [],
    ];

    if (!in_array($newStatus, $validTransitions[$oldStatus] ?? [])) {
        header('Location: ' . $back . '&error=' .
               urlencode('Hindi pwedeng i-change ang status mula ' .
                         ucwords(str_replace('_', ' ', $oldStatus)) . ' papunta ' .
                         ucwords(str_replace('_', ' ', $newStatus)) . '.'));
        exit;
    }
    // ──────────────────────────────────────────────────

    // ── MANDATORY RESOLUTION PROOF ────────────────────
    if ($newStatus === 'resolved') {
        if (empty($_FILES['evidence']['name'][0])) {
            header('Location: ' . $back . '&error=' .
                   urlencode('Bawal i-resolve nang walang Proof of Resolution (Photo/Video).'));
            exit;
        }
    }
    // ──────────────────────────────────────────────────

    // I-update ang status at mag-log
    $model->updateStatus($id, $newStatus, $user['id'], $oldStatus, $remarks);

    // ── MEDIA UPLOAD FOR RESOLUTION ───────────────────
    if ($newStatus === 'resolved' && !empty($_FILES['evidence']['name'][0])) {
        $uploadDir = __DIR__ . '/../uploads/';
        foreach ($_FILES['evidence']['tmp_name'] as $i => $tmp) {
            if ($_FILES['evidence']['error'][$i] !== UPLOAD_ERR_OK) continue;

            $check = validateUploadedMedia($tmp, $_FILES['evidence']['name'][$i]);
            if (!$check['valid']) {
                // If invalid evidence during resolution, maybe log it but we already required it
                logAudit($pdo, $user['id'], 'res_upload_rejected', 'incident', $id,
                    'Resolution file rejected: ' . $_FILES['evidence']['name'][$i] . ' — ' . $check['error']);
                continue;
            }

            $filename = uniqid('res_', true) . '.' . $check['ext'];
            if (move_uploaded_file($tmp, $uploadDir . $filename)) {
                $pdo->prepare("
                    INSERT INTO attachments (incident_id, file_name, file_path, file_type, stage)
                    VALUES (?, ?, ?, ?, 'resolution')
                ")->execute([
                    $id,
                    $_FILES['evidence']['name'][$i],
                    'uploads/' . $filename,
                    $check['mime'],
                ]);
            }
        }
    }
    // ──────────────────────────────────────────────────

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

        // ── IN-APP NOTIFICATION — Notify citizen pag may status update ──
        if (!empty($fullIncident['reporter_id'])) {
            createNotification(
                $pdo,
                (int)$fullIncident['reporter_id'],
                'Status Update — ' . ucwords(str_replace('_', ' ', $newStatus)),
                'Ang iyong report na "' . $fullIncident['title'] . '" ay na-update na.',
                $id
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

    // ── IN-APP NOTIFICATION — Notify citizen pag may response ──
    if (!empty($incident['reporter_id'])) {
        createNotification(
            $pdo,
            (int)$incident['reporter_id'],
            'Bagong Response sa Report',
            'May nag-respond sa iyong report na "' . $incident['title'] . '".',
            $id
        );
    }

    header('Location: ' . $back . '&success=' .
           urlencode('Na-send na ang response.'));
    exit;
}

// Fallback
header('Location: ' . $back);
exit;