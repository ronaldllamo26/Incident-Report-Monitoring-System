<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../models/Incident.php';
require_once __DIR__ . '/../config/mailer.php';
require_once __DIR__ . '/../includes/functions.php';
requireRole('citizen');

$action = $_GET['action'] ?? '';

if ($action === 'submit' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    validate_csrf();

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

    // ── INPUT LENGTH LIMITS ───────────────────────────────────
    // Server-side guard — HTML maxlength can be bypassed by curl/Postman
    if (mb_strlen($title) > 150) {
        header('Location: /irms/citizen/report.php?error=' .
               urlencode('Ang pamagat ay hindi dapat hihigit sa 150 characters.'));
        exit;
    }
    if (mb_strlen($desc) > 3000) {
        header('Location: /irms/citizen/report.php?error=' .
               urlencode('Ang deskripsyon ay hindi dapat hihigit sa 3000 characters.'));
        exit;
    }
    if (mb_strlen($location) > 255) {
        header('Location: /irms/citizen/report.php?error=' .
               urlencode('Ang lokasyon ay hindi dapat hihigit sa 255 characters.'));
        exit;
    }
    // ─────────────────────────────────────────────────────────
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

    $citezIp = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    if (str_contains($citezIp, ',')) {
        $citezIp = trim(explode(',', $citezIp)[0]);
    }

    // Insert incident
    $stmt = $pdo->prepare("
        INSERT INTO incidents
            (reporter_id, category_id, title, description,
             location, latitude, longitude, severity, status,
             tracking_number, is_duplicate, duplicate_of, ip_address)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending', ?, ?, ?, ?)
    ");
    $stmt->execute([
        $user['id'], $cat, $title, $desc,
        $location, $lat, $lng, $severity,
        $tracking, $isDuplicate, $duplicateOf, $citezIp
    ]);
    $incidentId = $pdo->lastInsertId();

    // Auto-assign + SLA + Priority
    $model = new Incident();
    $model->processNewIncident($incidentId, $cat, $severity);

    // Media uploads (Photos/Videos with strict MIME type + magic byte validation)
    if (!empty($_FILES['evidence']['name'][0])) {
        $uploadDir = __DIR__ . '/../uploads/';
        foreach ($_FILES['evidence']['tmp_name'] as $i => $tmp) {
            if ($_FILES['evidence']['error'][$i] !== UPLOAD_ERR_OK) continue;

            $check = validateUploadedMedia($tmp, $_FILES['evidence']['name'][$i]);
            if (!$check['valid']) {
                // Skip invalid file silently — or log it
                logAudit($pdo, $user['id'], 'upload_rejected', 'incident', $incidentId,
                    'File rejected: ' . $_FILES['evidence']['name'][$i] . ' — ' . $check['error']);
                continue;
            }

            $filename = uniqid('inc_', true) . '.' . $check['ext'];
            if (move_uploaded_file($tmp, $uploadDir . $filename)) {
                $pdo->prepare("
                    INSERT INTO attachments (incident_id, file_name, file_path, file_type)
                    VALUES (?, ?, ?, ?)
                ")->execute([
                    $incidentId,
                    $_FILES['evidence']['name'][$i],
                    'uploads/' . $filename,
                    $check['mime'], // Server-detected MIME, NOT client-supplied
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

    // ── IN-APP NOTIFICATIONS (run FIRST — before email which may fail) ──
    $fullIncident = $model->getById($incidentId);

    // Notify all admins about the new report
    $adminStmt = $pdo->prepare("SELECT id FROM users WHERE role = 'admin' AND is_active = 1");
    $adminStmt->execute();
    $admins = $adminStmt->fetchAll();
    foreach ($admins as $admin) {
        createNotification(
            $pdo,
            (int)$admin['id'],
            'Bagong Incident Report',
            'May bagong report: "' . $title . '" mula kay ' . $user['name'] . '.',
            $incidentId
        );
    }

    // Notify auto-assigned responder
    if ($fullIncident && !empty($fullIncident['assigned_to'])) {
        createNotification(
            $pdo,
            (int)$fullIncident['assigned_to'],
            'Bagong Assigned Incident',
            'Na-assign sa iyo ang incident: "' . $title . '".',
            $incidentId
        );
    }

    // Notify citizen — confirmation
    createNotification(
        $pdo,
        (int)$user['id'],
        'Report Submitted',
        'Ang report mo na "' . $title . '" ay na-submit na. Tracking: ' . $tracking,
        $incidentId
    );
    // ──────────────────────────────────────────────────────

    // ── EMAIL NOTIFICATIONS (wrapped in try-catch) ───────
    try {
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
    } catch (Exception $e) {
        // Email failed — okay lang, in-app notification na-send na
    }
    // ──────────────────────────────────────────────────────

    header('Location: /irms/public/report_success.php?tracking=' .
           urlencode($tracking) . '&id=' . $incidentId);
    exit;
}