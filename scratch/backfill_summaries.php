<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/AIService.php';

try {
    $stmt = $pdo->query("
        SELECT i.id, i.title, i.description, i.location, c.name as category_name 
        FROM incidents i 
        JOIN categories c ON i.category_id = c.id 
        WHERE i.ai_summary IS NULL
    ");
    $incidents = $stmt->fetchAll();
    
    echo "Backfilling " . count($incidents) . " incidents..." . PHP_EOL;

    $update = $pdo->prepare("UPDATE incidents SET ai_summary = ? WHERE id = ?");

    foreach ($incidents as $inc) {
        $summary = AIService::generateSummary(
            $inc['category_name'],
            $inc['location'],
            $inc['description']
        );
        $update->execute([$summary, $inc['id']]);
    }

    echo "SUCCESS: Backfill complete." . PHP_EOL;
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . PHP_EOL;
}
