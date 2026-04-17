<?php
require_once __DIR__ . '/../includes/auth.php';
requireRole('responder');
require_once __DIR__ . '/../config/db.php';

$user = currentUser();
$lastCount = (int)($_GET['last_count'] ?? 0);

// Check current count of PENDING incidents assigned to this responder
$stmt = $pdo->prepare("SELECT COUNT(*) FROM incidents WHERE assigned_to = ? AND status = 'pending'");
$stmt->execute([$user['id']]);
$currentCount = (int)$stmt->fetchColumn();

$newDetected = ($currentCount > $lastCount);

header('Content-Type: application/json');
echo json_encode([
    'current_count' => $currentCount,
    'new_detected'  => $newDetected,
    'timestamp'     => date('Y-m-d H:i:s')
]);
