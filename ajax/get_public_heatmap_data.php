<?php
require_once __DIR__ . '/../config/db.php';

header('Content-Type: application/json');

// Only allow specific statuses to prevent rejected/spam from cluttering the map
$allowedStatuses = "('pending', 'in_progress', 'resolved', 'closed')";

// Query incidents that have valid coordinates
$query = "
    SELECT 
        i.id,
        i.title,
        i.severity,
        i.status,
        i.latitude,
        i.longitude,
        i.reported_at,
        c.id AS category_id,
        c.name AS category_name,
        c.icon AS category_icon
    FROM incidents i
    JOIN categories c ON i.category_id = c.id
    WHERE i.latitude IS NOT NULL 
      AND i.longitude IS NOT NULL
      AND i.status IN {$allowedStatuses}
";

// Optional filter by category
if (!empty($_GET['category'])) {
    $catId = (int)$_GET['category'];
    $query .= " AND i.category_id = {$catId}";
}

// Optional filter by severity
if (!empty($_GET['severity'])) {
    // Basic sanitization
    $sev = in_array($_GET['severity'], ['low','medium','high','critical']) ? $_GET['severity'] : '';
    if ($sev) {
        $query .= " AND i.severity = '{$sev}'";
    }
}

// Optional filter by status
if (!empty($_GET['status'])) {
    $stat = in_array($_GET['status'], ['pending','in_progress','resolved','closed']) ? $_GET['status'] : '';
    if ($stat) {
        $query .= " AND i.status = '{$stat}'";
    }
}

$query .= " ORDER BY i.reported_at DESC";

try {
    $stmt = $pdo->prepare($query);
    $stmt->execute();
    $incidents = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Process results to send safe data
    $data = array_map(function($row) {
        return [
            'id' => (int)$row['id'],
            'lat' => (float)$row['latitude'],
            'lng' => (float)$row['longitude'],
            'title' => htmlspecialchars($row['title']),
            'severity' => $row['severity'],
            'status' => $row['status'],
            'category_name' => $row['category_name'],
            'category_icon' => $row['category_icon'],
            'timestamp' => date('M j, Y h:i A', strtotime($row['reported_at']))
        ];
    }, $incidents);

    echo json_encode([
        'status' => 'success',
        'count' => count($data),
        'data' => $data
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Database error']);
}
