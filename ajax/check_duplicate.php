<?php
/**
 * ajax/check_duplicate.php
 * Real-time duplicate check — tinatawag habang nag-fi-fill ang user ng report form
 * 
 * POST params:
 *   lat      — latitude ng pinned location
 *   lng      — longitude ng pinned location
 *   category — category_id
 * 
 * Returns JSON:
 *   { duplicate: false }
 *   { duplicate: true, incident: { id, title, tracking_number, status, created_at, location } }
 */

require_once __DIR__ . '/../config/db.php';

header('Content-Type: application/json');

// Only accept POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['duplicate' => false]);
    exit;
}

$lat      = floatval($_POST['lat']      ?? 0);
$lng      = floatval($_POST['lng']      ?? 0);
$category = intval($_POST['category']   ?? 0);

// Need valid coords and category
if (!$lat || !$lng || !$category) {
    echo json_encode(['duplicate' => false]);
    exit;
}

/**
 * HAVERSINE FORMULA — calculates distance in meters between two lat/lng points
 * Standard formula used by Google Maps, Waze, etc.
 */
$sql = "
    SELECT 
        id,
        title,
        tracking_number,
        status,
        location,
        latitude,
        longitude,
        created_at,
        (
            6371000 * ACOS(
                COS(RADIANS(:lat1)) * COS(RADIANS(latitude)) *
                COS(RADIANS(longitude) - RADIANS(:lng1)) +
                SIN(RADIANS(:lat2)) * SIN(RADIANS(latitude))
            )
        ) AS distance_meters
    FROM incidents
    WHERE
        category_id  = :category
        AND status   NOT IN ('closed', 'rejected')
        AND is_duplicate = 0
        AND created_at  >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
        AND latitude  IS NOT NULL
        AND longitude IS NOT NULL
    HAVING
        distance_meters <= 50
    ORDER BY
        distance_meters ASC,
        created_at DESC
    LIMIT 1
";

$stmt = $pdo->prepare($sql);
$stmt->execute([
    ':lat1'     => $lat,
    ':lng1'     => $lng,
    ':lat2'     => $lat,
    ':category' => $category,
]);

$found = $stmt->fetch(PDO::FETCH_ASSOC);

if ($found) {
    echo json_encode([
        'duplicate' => true,
        'incident'  => [
            'id'              => (int)$found['id'],
            'title'           => $found['title'],
            'tracking_number' => $found['tracking_number'],
            'status'          => $found['status'],
            'location'        => $found['location'],
            'created_at'      => date('M d, Y g:i A', strtotime($found['created_at'])),
            'distance_meters' => round($found['distance_meters']),
        ]
    ]);
} else {
    echo json_encode(['duplicate' => false]);
}