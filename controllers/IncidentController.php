<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../models/Incident.php';
require_once __DIR__ . '/../config/mailer.php';
requireRole('citizen');

$action = $_GET['action'] ?? '';

if ($action === 'submit' && $_SERVER['REQUEST_METHOD'] === 'POST') {

    $user     = currentUser();
    $title    = trim($_POST['title']        ?? '');
    $cat      = (int)($_POST['category_id'] ?? 0);
    $severity = $_POST['severity']          ?? '';
    $desc     = trim($_POST['description']  ?? '');
    $location = trim($_POST['location']     ?? '');
    $lat      = $_POST['latitude']          ?? null;
    $lng      = $_POST['longitude']         ?? null;

    if (!$title || !$cat || !$severity || !$desc || !$location) {
        header('Location: /irms/citizen/report.php?error=' .
               urlencode('Punan ang lahat ng required fields.'));
        exit;
    }
    if (!$lat || !$lng) {
        header('Location: /irms/citizen/report.php?error=' .
               urlencode('I-pin muna ang lokasyon sa mapa.'));
        exit;
    }

    // Generate tracking number
    $tracking = 'IRMS-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -5));

    // Insert incident
    $stmt = $pdo->prepare("
        INSERT INTO incidents
            (reporter_id, category_id, title, description,
             location, latitude, longitude, severity, status, tracking_number)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending', ?)
    ");
    $stmt->execute([
        $user['id'], $cat, $title, $desc,
        $location, $lat, $lng, $severity, $tracking
    ]);
    $incidentId = $pdo->lastInsertId(); // ← DITO DAPAT ITO

    // Auto-assign + SLA + Priority
    $model = new Incident();
    $model->processNewIncident($incidentId, $cat, $severity);

    // Photo uploads
    if (!empty($_FILES['photos']['name'][0])) {
        $uploadDir = __DIR__ . '/../uploads/';
        $allowed   = ['jpg','jpeg','png','gif','webp'];

        foreach ($_FILES['photos']['tmp_name'] as $i => $tmp) {
            if ($_FILES['photos']['error'][$i] !== UPLOAD_ERR_OK) continue;
            $ext = strtolower(pathinfo($_FILES['photos']['name'][$i], PATHINFO_EXTENSION));
            if (!in_array($ext, $allowed)) continue;

            $filename = uniqid('inc_', true) . '.' . $ext;
            if (move_uploaded_file($tmp, $uploadDir . $filename)) {
                $pdo->prepare("
                    INSERT INTO attachments (incident_id, file_name, file_path, file_type)
                    VALUES (?, ?, ?, ?)
                ")->execute([
                    $incidentId,
                    $_FILES['photos']['name'][$i],
                    'uploads/' . $filename,
                    $_FILES['photos']['type'][$i],
                ]);
            }
        }
    }

    // Initial status log
    $pdo->prepare("
        INSERT INTO status_logs (incident_id, changed_by, old_status, new_status, remarks)
        VALUES (?, ?, NULL, 'pending', 'Incident submitted by citizen.')
    ")->execute([$incidentId, $user['id']]);

    // Email notifications
    $fullIncident = $model->getById($incidentId);

    if ($fullIncident && !empty($fullIncident['reporter_email'])) {
        sendMail(
            $fullIncident['reporter_email'],
            'Report Confirmation — IRMS #' . $incidentId,
            mailReportConfirmation($fullIncident, $tracking)
        );
    }

    if ($fullIncident && $fullIncident['assigned_to']) {
        $responderStmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
        $responderStmt->execute([$fullIncident['assigned_to']]);
        $responder = $responderStmt->fetch();

        if ($responder) {
            sendMail(
                $responder['email'],
                '🚨 Bagong Assigned Incident #' . $incidentId . ' — ' . $fullIncident['title'],
                mailResponderAssigned($fullIncident, $responder)
            );
        }
    }

    header('Location: /irms/public/report_success.php?tracking=' .
           urlencode($tracking) . '&id=' . $incidentId);
    exit;
}