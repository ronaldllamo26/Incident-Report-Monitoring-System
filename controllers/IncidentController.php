<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/db.php';
requireRole('citizen');

$action = $_GET['action'] ?? '';

if ($action === 'submit' && $_SERVER['REQUEST_METHOD'] === 'POST') {

    $user     = currentUser();
    $title    = trim($_POST['title']       ?? '');
    $cat      = (int)($_POST['category_id'] ?? 0);
    $severity = $_POST['severity']         ?? '';
    $desc     = trim($_POST['description'] ?? '');
    $location = trim($_POST['location']    ?? '');
    $lat      = $_POST['latitude']         ?? null;
    $lng      = $_POST['longitude']        ?? null;

    // Validation
    if (!$title || !$cat || !$severity || !$desc || !$location) {
        header('Location: /irms/views/citizen/report.php?error=' .
               urlencode('Punan ang lahat ng required fields.'));
        exit;
    }

    if (!$lat || !$lng) {
        header('Location: /irms/views/citizen/report.php?error=' .
               urlencode('I-pin muna ang lokasyon sa mapa.'));
        exit;
    }

    // Insert incident
    $stmt = $pdo->prepare("
        INSERT INTO incidents
            (reporter_id, category_id, title, description, location,
             latitude, longitude, severity, status)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending')
    ");
    $stmt->execute([
        $user['id'], $cat, $title, $desc,
        $location, $lat, $lng, $severity
    ]);
    $incidentId = $pdo->lastInsertId();

    // Handle photo uploads
    if (!empty($_FILES['photos']['name'][0])) {
        $uploadDir = __DIR__ . '/../uploads/';
        $allowed   = ['jpg','jpeg','png','gif','webp'];

        foreach ($_FILES['photos']['tmp_name'] as $i => $tmp) {
            if ($_FILES['photos']['error'][$i] !== UPLOAD_ERR_OK) continue;

            $ext = strtolower(pathinfo($_FILES['photos']['name'][$i], PATHINFO_EXTENSION));
            if (!in_array($ext, $allowed)) continue;

            // Unique filename
            $filename = uniqid('inc_', true) . '.' . $ext;
            $dest     = $uploadDir . $filename;

            if (move_uploaded_file($tmp, $dest)) {
                $s = $pdo->prepare("
                    INSERT INTO attachments (incident_id, file_name, file_path, file_type)
                    VALUES (?, ?, ?, ?)
                ");
                $s->execute([
                    $incidentId,
                    $_FILES['photos']['name'][$i],
                    'uploads/' . $filename,
                    $_FILES['photos']['type'][$i]
                ]);
            }
        }
    }

    // Log initial status
    $log = $pdo->prepare("
        INSERT INTO status_logs
            (incident_id, changed_by, old_status, new_status, remarks)
        VALUES (?, ?, NULL, 'pending', 'Incident submitted by citizen.')
    ");
    $log->execute([$incidentId, $user['id']]);

    header('Location: /irms/views/citizen/dashboard.php?success=submitted');
    exit;
}