<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/db.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['count' => 0, 'notifications' => []]);
    exit;
}

$userId = $_SESSION['user_id'];

// Get unread count + latest 10 notifications
$stmt = $pdo->prepare("
    SELECT id, title, message, incident_id, is_read, created_at
    FROM notifications
    WHERE user_id = ?
    ORDER BY created_at DESC
    LIMIT 10
");
$stmt->execute([$userId]);
$notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Unread count
$countStmt = $pdo->prepare("
    SELECT COUNT(*) FROM notifications
    WHERE user_id = ? AND is_read = 0
");
$countStmt->execute([$userId]);
$unreadCount = (int)$countStmt->fetchColumn();

// Format dates
foreach ($notifications as &$notif) {
    $notif['time_ago'] = timeAgo($notif['created_at']);
    $notif['is_read']  = (bool)$notif['is_read'];
}

echo json_encode([
    'count'         => $unreadCount,
    'notifications' => $notifications
]);

function timeAgo(string $datetime): string {
    $diff = time() - strtotime($datetime);
    if ($diff < 60)     return 'Ngayon lang';
    if ($diff < 3600)   return round($diff / 60) . 'm ang nakalipas';
    if ($diff < 86400)  return round($diff / 3600) . 'h ang nakalipas';
    return round($diff / 86400) . 'd ang nakalipas';
}