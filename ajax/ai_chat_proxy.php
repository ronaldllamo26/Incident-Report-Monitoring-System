<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../models/Incident.php';
require_once __DIR__ . '/../includes/AIService.php';

header('Content-Type: application/json');

// Only allow XHR
if (!isset($_SERVER['HTTP_X_REQUESTED_WITH']) && !isset($_POST['message'])) {
    die(json_encode(['error' => 'Direct access not allowed']));
}

$message = $_POST['message'] ?? '';

if (empty($message)) {
    echo json_encode(['response' => 'Pasensya na po, wala akong natanggap na tanong.']);
    exit;
}

try {
    $incModel = new Incident($pdo);
    $recent = $incModel->getRecentPublicReports(10);
    
    $response = AIService::askAI($message, $recent);
    echo json_encode(['response' => $response]);
} catch (Exception $e) {
    echo json_encode(['error' => 'AI System Error: ' . $e->getMessage()]);
}
