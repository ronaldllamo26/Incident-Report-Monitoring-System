<?php
require_once __DIR__ . '/../includes/auth.php';
requireRole('admin');
require_once __DIR__ . '/../config/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method not allowed.');
}

validate_csrf();

$adminId    = currentUser()['id'];
$incidentId = (int)($_POST['incident_id'] ?? 0);

if (!$incidentId) {
    header('Location: /irms/portal/admin/dashboard.php?error=' . urlencode('Invalid incident ID.'));
    exit;
}

// 1. Fetch incident details
$stmt = $pdo->prepare("SELECT id, reporter_id, is_anonymous, ip_address, status FROM incidents WHERE id = ?");
$stmt->execute([$incidentId]);
$incident = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$incident) {
    header('Location: /irms/portal/admin/dashboard.php?error=' . urlencode('Report not found.'));
    exit;
}

if ($incident['status'] === 'rejected' || $incident['status'] === 'closed') {
    header('Location: /irms/portal/admin/view_incident.php?id=' . $incidentId . '&error=' . urlencode('Irereject na dapat pero naka-close na ito.'));
    exit;
}

$pdo->beginTransaction();
try {
    // 2. Ban IP if it exists
    if (!empty($incident['ip_address'])) {
        $ipStmt = $pdo->prepare("INSERT IGNORE INTO banned_ips (ip_address, reason, banned_by) VALUES (?, ?, ?)");
        $ipStmt->execute([$incident['ip_address'], 'Spam / Malicious Video Upload via Incident #' . $incidentId, $adminId]);
    }

    // 3. Ban User Account if Citizen
    if (!$incident['is_anonymous'] && !empty($incident['reporter_id'])) {
        $userStmt = $pdo->prepare("UPDATE users SET is_active = 0 WHERE id = ? AND role = 'citizen'");
        $userStmt->execute([$incident['reporter_id']]);
    }

    // 4. Purge Files
    $attStmt = $pdo->prepare("SELECT id, file_path FROM attachments WHERE incident_id = ?");
    $attStmt->execute([$incidentId]);
    $attachments = $attStmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($attachments as $att) {
        $fullPath = __DIR__ . '/../' . $att['file_path'];
        if (file_exists($fullPath) && is_file($fullPath)) {
            unlink($fullPath);
        }
    }
    
    // Delete from DB
    $delAttStmt = $pdo->prepare("DELETE FROM attachments WHERE incident_id = ?");
    $delAttStmt->execute([$incidentId]);

    // 5. Update Status
    $upStmt = $pdo->prepare("UPDATE incidents SET status = 'rejected' WHERE id = ?");
    $upStmt->execute([$incidentId]);

    // 6. Force log to trail
    $logStmt = $pdo->prepare("INSERT INTO status_logs (incident_id, changed_by, old_status, new_status, remarks) VALUES (?, ?, ?, 'rejected', ?)");
    $logStmt->execute([
        $incidentId, 
        $adminId, 
        $incident['status'], 
        'Flagged as Malicious/Spam. IP and User banned permanently. Evidence files purged.'
    ]);

    $pdo->commit();
    header('Location: /irms/portal/admin/dashboard.php?success=' . urlencode('Matagumpay na nai-ban ang user at nabura ang video!'));
    exit;

} catch (Exception $e) {
    $pdo->rollBack();
    header('Location: /irms/portal/admin/view_incident.php?id=' . $incidentId . '&error=' . urlencode('System error during ban hammer execution.'));
    exit;
}
