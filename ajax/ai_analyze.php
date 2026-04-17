<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/AIService.php';

header('Content-Type: application/json');

// Limit AI analysis to authenticated users or validated session-based anonymous reporters
// (Basic rate limiting and CSRF should be applied if possible)

$title = trim($_POST['title'] ?? '');
$desc  = trim($_POST['description'] ?? '');

if (empty($title) || empty($desc)) {
    echo json_encode(['error' => 'Missing title or description']);
    exit;
}

// 1. Fetch all categories to give AI the context
$cats = $pdo->query("SELECT id, name FROM categories ORDER BY name")->fetchAll();

// 2. Classify using LLM
$result = AIService::classifyIncident($title, $desc, $cats);

// 3. Map AI name back to ID
$matchedId = null;
if (!empty($result['category'])) {
    foreach ($cats as $c) {
        if (strcasecmp($c['name'], $result['category']) === 0) {
            $matchedId = $c['id'];
            break;
        }
    }
}

echo json_encode([
    'category_id' => $matchedId,
    'severity'    => $result['severity'] ?? 'medium',
    'confidence'  => $result['confidence'] ?? 0,
    'location_suggestion' => $result['location_suggestion'] ?? null,
    'confidence_location' => $result['confidence_location'] ?? 0,
    'reason'      => $result['reason'] ?? '',
    'ai_category_name' => $result['category'] ?? null
]);
