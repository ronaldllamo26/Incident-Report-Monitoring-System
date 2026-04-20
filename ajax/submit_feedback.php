<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json');

validate_csrf();

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$user = currentUser();
$incidentId = $_POST['incident_id'] ?? 0;
$rating     = $_POST['rating']      ?? 0;
$comment    = $_POST['comment']     ?? '';

if (!$incidentId || !$rating) {
    echo json_encode(['success' => false, 'message' => 'Mangyaring magbigay ng rating.']);
    exit;
}

try {
    // 1. Check if incident exists and belongs to the user
    $stmt = $pdo->prepare("SELECT status FROM incidents WHERE id = ? AND reporter_id = ?");
    $stmt->execute([$incidentId, $user['id']]);
    $incident = $stmt->fetch();

    if (!$incident) {
        echo json_encode(['success' => false, 'message' => 'Incident not found or unauthorized.']);
        exit;
    }

    if ($incident['status'] !== 'resolved' && $incident['status'] !== 'closed') {
        echo json_encode(['success' => false, 'message' => 'Pwede lang mag-rate kapag Resolved na ang report.']);
        exit;
    }

    $pdo->beginTransaction();

    // 2. Update Incidents table (New columns)
    $upd = $pdo->prepare("UPDATE incidents SET rating = ?, citizen_feedback = ? WHERE id = ?");
    $upd->execute([$rating, $comment, $incidentId]);

    // 3. Insert into Feedback table (Existing sync)
    $feed = $pdo->prepare("
        INSERT INTO feedback (incident_id, citizen_id, rating, comment) 
        VALUES (?, ?, ?, ?) 
        ON DUPLICATE KEY UPDATE rating = VALUES(rating), comment = VALUES(comment)
    ");
    $feed->execute([$incidentId, $user['id'], $rating, $comment]);

    $pdo->commit();

    echo json_encode(['success' => true]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}