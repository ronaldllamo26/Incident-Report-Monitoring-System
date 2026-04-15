<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'error' => 'Not logged in']);
    exit;
}

// CSRF protection — token comes via X-CSRF-TOKEN header from notification_bell.php
validate_csrf();

try {
    $userId  = $_SESSION['user_id'];
    $notifId = (int)($_POST['id'] ?? 0);
    $action  = $_POST['action'] ?? 'read'; // 'read' or 'clear'

    if ($action === 'clear') {
        // ── DELETE ALL notifications for this user ──
        $stmt = $pdo->prepare("DELETE FROM notifications WHERE user_id = ?");
        $stmt->execute([$userId]);
        echo json_encode(['success' => true, 'cleared' => $stmt->rowCount()]);
        exit;
    }

    // ── MARK AS READ ──
    if ($notifId) {
        // Mark specific notification as read
        $stmt = $pdo->prepare("
            UPDATE notifications SET is_read = 1
            WHERE id = ? AND user_id = ?
        ");
        $stmt->execute([$notifId, $userId]);
    } else {
        // Mark ALL as read
        $stmt = $pdo->prepare("
            UPDATE notifications SET is_read = 1
            WHERE user_id = ? AND is_read = 0
        ");
        $stmt->execute([$userId]);
    }

    echo json_encode(['success' => true, 'marked' => $stmt->rowCount()]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}