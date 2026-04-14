<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/db.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['success' => false]);
    exit;
}

$userId = $_SESSION['user_id'];
$notifId = (int)($_POST['id'] ?? 0);

if ($notifId) {
    // Mark specific notification as read — only own notifications
    $pdo->prepare("
        UPDATE notifications SET is_read = 1
        WHERE id = ? AND user_id = ?
    ")->execute([$notifId, $userId]);
} else {
    // Mark ALL as read
    $pdo->prepare("
        UPDATE notifications SET is_read = 1
        WHERE user_id = ? AND is_read = 0
    ")->execute([$userId]);
}

echo json_encode(['success' => true]);