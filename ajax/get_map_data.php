<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../models/Incident.php';

header('Content-Type: application/json');

try {
    $model = new Incident();
    // Get last 50 incidents for heatmap/preview
    $data = $model->getForMap(); 
    echo json_encode($data);
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
