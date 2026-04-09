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

    // Basic validation
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

    // QC bounds validation
    $lat = floatval($lat);
    $lng = floatval($lng);
    if ($lat < 14.4764 || $lat > 14.7800 || $lng < 120.9980 || $lng > 121.1764) {
        header('Location: /irms/citizen/report.php?error=' .
               urlencode('Hindi pwedeng mag-submit — ang lokasyon ay nasa labas ng Quezon City.'));
        exit;
    }

    // ── DUPLICATE DETECTION ────────────────────────────
    $dupStmt = $pdo->prepare("
        SELECT
            id, title, tracking_number, status, location, created_at,
            (
                6371000 * ACOS(
                    COS(RADIANS(:lat1)) * COS(RADIANS(latitude)) *
                    COS(RADIANS(longitude) - RADIANS(:lng1)) +
                    SIN(RADIANS(:lat2)) * SIN(RADIANS(latitude))
                )
            ) AS distance_meters
        FROM incidents
        WHERE
            category_id = :cat
            AND status  NOT IN ('closed', 'rejected')
            AND is_duplicate = 0
            AND created_at  >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
            AND latitude  IS NOT NULL
            AND longitude IS NOT NULL
        HAVING distance_meters <= 50
        ORDER BY distance_meters ASC, created_at DESC
        LIMIT 1
    ");
    $dupStmt->execute([
        ':lat1' => $lat, ':lng1' => $lng,
        ':lat2' => $lat, ':cat'  => $cat
    ]);
    $duplicate = $dupStmt->fetch(PDO::FETCH_ASSOC);

    // Check if user clicked "Ituloy pa rin" from duplicate warning page
    $forceProceed = ($_POST['force_proceed'] ?? '') === '1';

    if ($duplicate && !$forceProceed) {
        // Send back to form with duplicate info — user decides
        header('Location: /irms/citizen/report.php?duplicate=1' .
               '&dup_id='       . urlencode($duplicate['id']) .
               '&dup_tracking=' . urlencode($duplicate['tracking_number']) .
               '&dup_title='    . urlencode($duplicate['title']) .
               '&dup_status='   . urlencode($duplicate['status']) .
               '&dup_location=' . urlencode($duplicate['location']));
        exit;
    }

    // Generate tracking number
    $tracking = 'IRMS-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -5));

    // If forced despite duplicate — flag it
    $isDuplicate = ($duplicate && $forceProceed) ? 1 : 0;
    $duplicateOf = ($duplicate && $forceProceed) ? $duplicate['id'] : null;

    // Insert incident
    $stmt = $pdo->prepare("
        INSERT INTO incidents
            (reporter_id, category_id, title, description,
             location, latitude, longitude, severity, status,
             tracking_number, is_duplicate, duplicate_of)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending', ?, ?, ?)
    ");
    $stmt->execute([
        $user['id'], $cat, $title, $desc,
        $location, $lat, $lng, $severity,
        $tracking, $isDuplicate, $duplicateOf
    ]);
    $incidentId = $pdo->lastInsertId();

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

    // Status log
    $remarks = 'Incident submitted by citizen.';
    if ($isDuplicate) {
        $remarks .= ' Flagged as possible duplicate of Incident #' . $duplicateOf . '.';
    }
    $pdo->prepare("
        INSERT INTO status_logs (incident_id, changed_by, old_status, new_status, remarks)
        VALUES (?, ?, NULL, 'pending', ?)
    ")->execute([$incidentId, $user['id'], $remarks]);

    // Email
    $fullIncident = $model->getById($incidentId);
    if ($fullIncident && !empty($fullIncident['reporter_email'])) {
        sendMail(
            $fullIncident['reporter_email'],
            'Report Confirmation — IRMS #' . $incidentId,
            mailReportConfirmation($fullIncident, $tracking)
        );
    }
    if ($fullIncident && $fullIncident['assigned_to']) {
        $respStmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
        $respStmt->execute([$fullIncident['assigned_to']]);
        $responder = $respStmt->fetch();
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