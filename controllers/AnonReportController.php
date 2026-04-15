<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../models/Incident.php';
require_once __DIR__ . '/../includes/functions.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /irms/public/report.php');
    exit;
}

validate_csrf();

// ── RATE LIMITING (Anonymous Reports) ────────────────────────
// Max 3 anonymous reports per IP per hour.
// Prevents spam/bot flooding without requiring login.
$_anonIp = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
if (str_contains($_anonIp, ',')) {
    $_anonIp = trim(explode(',', $_anonIp)[0]);
}
if (isRateLimited($pdo, $_anonIp, 'anon_report', 3, 3600)) {
    header('Location: /irms/public/report.php?error=' .
           urlencode('Masyadong maraming reports mula sa iyong koneksyon. Subukan ulit pagkatapos ng isang oras.'));
    exit;
}
// ─────────────────────────────────────────────────────────────

$title     = trim($_POST['title']        ?? '');
$cat       = (int)($_POST['category_id'] ?? 0);
$severity  = $_POST['severity']          ?? '';
$desc      = trim($_POST['description']  ?? '');
$location  = trim($_POST['location']     ?? '');
$lat       = $_POST['latitude']          ?? null;
$lng       = $_POST['longitude']         ?? null;
$anonName  = trim($_POST['anon_name']    ?? '');
$anonEmail = trim($_POST['anon_email']   ?? '');
$anonPhone = trim($_POST['anon_phone']   ?? '');

// Basic validation
if (!$title || !$cat || !$severity || !$desc || !$location) {
    header('Location: /irms/public/report.php?error=' .
           urlencode('Punan ang lahat ng required fields.'));
    exit;
}

// ── INPUT LENGTH LIMITS ───────────────────────────────────────
// Server-side guard — HTML maxlength can be bypassed by curl/Postman
if (mb_strlen($title) > 150) {
    header('Location: /irms/public/report.php?error=' .
           urlencode('Ang pamagat ay hindi dapat hihigit sa 150 characters.'));
    exit;
}
if (mb_strlen($desc) > 3000) {
    header('Location: /irms/public/report.php?error=' .
           urlencode('Ang deskripsyon ay hindi dapat hihigit sa 3000 characters.'));
    exit;
}
if (mb_strlen($location) > 255) {
    header('Location: /irms/public/report.php?error=' .
           urlencode('Ang lokasyon ay hindi dapat hihigit sa 255 characters.'));
    exit;
}
if ($anonName && mb_strlen($anonName) > 100) {
    header('Location: /irms/public/report.php?error=' .
           urlencode('Ang pangalan ay hindi dapat hihigit sa 100 characters.'));
    exit;
}
if ($anonPhone && mb_strlen($anonPhone) > 20) {
    header('Location: /irms/public/report.php?error=' .
           urlencode('Hindi valid ang phone number.'));
    exit;
}
// ─────────────────────────────────────────────────────────────
if (!$lat || !$lng) {
    header('Location: /irms/public/report.php?error=' .
           urlencode('I-pin muna ang lokasyon sa mapa.'));
    exit;
}
if ($anonEmail && !filter_var($anonEmail, FILTER_VALIDATE_EMAIL)) {
    header('Location: /irms/public/report.php?error=' .
           urlencode('Hindi valid ang email address.'));
    exit;
}

// QC bounds validation
$lat = floatval($lat);
$lng = floatval($lng);
if ($lat < 14.4764 || $lat > 14.7800 || $lng < 120.9980 || $lng > 121.1764) {
    header('Location: /irms/public/report.php?error=' .
           urlencode('Hindi pwedeng mag-submit — ang lokasyon ay nasa labas ng Quezon City.'));
    exit;
}

// ── RECORD RATE HIT (only after all validation passes) ───────
// We count only valid, well-formed submissions — not typo retries.
recordRateHit($pdo, $_anonIp, 'anon_report', 3600);
// ─────────────────────────────────────────────────────────────


// ── DUPLICATE DETECTION ────────────────────────────────
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

// Check if user clicked "Ituloy pa rin"
$forceProceed = ($_POST['force_proceed'] ?? '') === '1';

if ($duplicate && !$forceProceed) {
    header('Location: /irms/public/report.php?duplicate=1' .
           '&dup_id='       . urlencode($duplicate['id']) .
           '&dup_tracking=' . urlencode($duplicate['tracking_number']) .
           '&dup_title='    . urlencode($duplicate['title']) .
           '&dup_status='   . urlencode($duplicate['status']) .
           '&dup_location=' . urlencode($duplicate['location']));
    exit;
}

// Generate tracking number
$tracking = 'IRMS-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -5));

$isDuplicate = ($duplicate && $forceProceed) ? 1 : 0;
$duplicateOf = ($duplicate && $forceProceed) ? $duplicate['id'] : null;

// Insert
$stmt = $pdo->prepare("
    INSERT INTO incidents
        (category_id, title, description, location,
         latitude, longitude, severity, status,
         is_anonymous, anon_name, anon_email, anon_phone,
         tracking_number, is_duplicate, duplicate_of)
    VALUES (?, ?, ?, ?, ?, ?, ?, 'pending', 1, ?, ?, ?, ?, ?, ?)
");
$stmt->execute([
    $cat, $title, $desc, $location,
    $lat, $lng, $severity,
    $anonName  ?: null,
    $anonEmail ?: null,
    $anonPhone ?: null,
    $tracking,
    $isDuplicate,
    $duplicateOf
]);
$incidentId = $pdo->lastInsertId();

// Auto-assign + SLA + Priority
$model = new Incident();
$model->processNewIncident($incidentId, $cat, $severity);

// Status log
$remarks = 'Anonymous incident report submitted.';
if ($isDuplicate) {
    $remarks .= ' Flagged as possible duplicate of Incident #' . $duplicateOf . '.';
}
$pdo->prepare("
    INSERT INTO status_logs (incident_id, changed_by, old_status, new_status, remarks)
    VALUES (?, NULL, NULL, 'pending', ?)
")->execute([$incidentId, $remarks]);

// Photo uploads (with strict MIME type + magic byte validation)
if (!empty($_FILES['photos']['name'][0])) {
    $uploadDir = __DIR__ . '/../uploads/';
    foreach ($_FILES['photos']['tmp_name'] as $i => $tmp) {
        if ($_FILES['photos']['error'][$i] !== UPLOAD_ERR_OK) continue;

        $check = validateUploadedImage($tmp, $_FILES['photos']['name'][$i]);
        if (!$check['valid']) {
            // Skip invalid file — log the rejection
            logAudit($pdo, null, 'upload_rejected', 'incident', $incidentId,
                'File rejected: ' . $_FILES['photos']['name'][$i] . ' — ' . $check['error']);
            continue;
        }

        $filename = uniqid('anon_', true) . '.' . $check['ext'];
        if (move_uploaded_file($tmp, $uploadDir . $filename)) {
            $pdo->prepare("
                INSERT INTO attachments (incident_id, file_name, file_path, file_type)
                VALUES (?, ?, ?, ?)
            ")->execute([
                $incidentId,
                $_FILES['photos']['name'][$i],
                'uploads/' . $filename,
                $check['mime'], // Server-detected MIME, NOT client-supplied
            ]);
        }
    }
}

// ── IN-APP NOTIFICATIONS ─────────────────────────────────
// Notify all admins about the new anonymous report
$adminStmt = $pdo->prepare("SELECT id FROM users WHERE role = 'admin' AND is_active = 1");
$adminStmt->execute();
$admins = $adminStmt->fetchAll();

$reporterLabel = $anonName ?: 'Anonymous';
foreach ($admins as $admin) {
    createNotification(
        $pdo,
        (int)$admin['id'],
        'Bagong Incident Report',
        'May bagong anonymous report: "' . $title . '" mula kay ' . $reporterLabel . '.',
        $incidentId
    );
}

// Notify auto-assigned responder (if any)
$fullIncident = $model->getById($incidentId);
if ($fullIncident && !empty($fullIncident['assigned_to'])) {
    createNotification(
        $pdo,
        (int)$fullIncident['assigned_to'],
        'Bagong Assigned Incident',
        'Na-assign sa iyo ang incident: "' . $title . '".',
        $incidentId
    );
}
// ──────────────────────────────────────────────────────────

// Audit log
logAudit($pdo, null, 'anonymous_report_submitted', 'incident', $incidentId,
    "Anonymous report submitted. Tracking: {$tracking}" .
    ($isDuplicate ? " | Possible duplicate of #{$duplicateOf}" : ""));

header('Location: /irms/public/report_success.php?tracking=' .
       urlencode($tracking) . '&id=' . $incidentId);
exit;