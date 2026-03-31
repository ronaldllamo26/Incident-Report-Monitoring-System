<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../models/Incident.php';
require_once __DIR__ . '/../includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /irms/public/anonymous_report.php');
    exit;
}

$title    = trim($_POST['title']        ?? '');
$cat      = (int)($_POST['category_id'] ?? 0);
$severity = $_POST['severity']          ?? '';
$desc     = trim($_POST['description']  ?? '');
$location = trim($_POST['location']     ?? '');
$lat      = $_POST['latitude']          ?? null;
$lng      = $_POST['longitude']         ?? null;
$anonName  = trim($_POST['anon_name']   ?? '');
$anonEmail = trim($_POST['anon_email']  ?? '');
$anonPhone = trim($_POST['anon_phone']  ?? '');

// Validation
if (!$title || !$cat || !$severity || !$desc || !$location) {
    header('Location: /irms/public/anonymous_report.php?error=' .
           urlencode('Punan ang lahat ng required fields.'));
    exit;
}

if (!$lat || !$lng) {
    header('Location: /irms/public/anonymous_report.php?error=' .
           urlencode('I-pin muna ang lokasyon sa mapa.'));
    exit;
}

if ($anonEmail && !filter_var($anonEmail, FILTER_VALIDATE_EMAIL)) {
    header('Location: /irms/public/anonymous_report.php?error=' .
           urlencode('Hindi valid ang email address.'));
    exit;
}

// Generate unique tracking number — format: IRMS-YYYYMMDD-XXXXX
$tracking = 'IRMS-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -5));

// Insert incident
$stmt = $pdo->prepare("
    INSERT INTO incidents
        (category_id, title, description, location,
         latitude, longitude, severity, status,
         is_anonymous, anon_name, anon_email, anon_phone,
         tracking_number)
    VALUES (?, ?, ?, ?, ?, ?, ?, 'pending', 1, ?, ?, ?, ?)
");
$stmt->execute([
    $cat, $title, $desc, $location,
    $lat, $lng, $severity,
    $anonName ?: null,
    $anonEmail ?: null,
    $anonPhone ?: null,
    $tracking
]);
$incidentId = $pdo->lastInsertId();

// Auto-assign + SLA + Priority
$model = new Incident();
$model->processNewIncident($incidentId, $cat, $severity);

// Initial status log (user_id = NULL kasi anonymous)
$pdo->prepare("
    INSERT INTO status_logs (incident_id, changed_by, old_status, new_status, remarks)
    VALUES (?, 1, NULL, 'pending', 'Anonymous incident report submitted.')
")->execute([$incidentId]);

// Handle photo uploads
if (!empty($_FILES['photos']['name'][0])) {
    $uploadDir = __DIR__ . '/../uploads/';
    $allowed   = ['jpg','jpeg','png','gif','webp'];
    foreach ($_FILES['photos']['tmp_name'] as $i => $tmp) {
        if ($_FILES['photos']['error'][$i] !== UPLOAD_ERR_OK) continue;
        $ext = strtolower(pathinfo($_FILES['photos']['name'][$i], PATHINFO_EXTENSION));
        if (!in_array($ext, $allowed)) continue;
        $filename = uniqid('anon_', true) . '.' . $ext;
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

// Audit log
logAudit($pdo, null, 'anonymous_report_submitted', 'incident', $incidentId,
    "Anonymous report submitted. Tracking: {$tracking}");

// Redirect sa success page na may tracking number
header('Location: /irms/citizen/report_success.php?tracking=' .
       urlencode($tracking) . '&id=' . $incidentId);
exit;