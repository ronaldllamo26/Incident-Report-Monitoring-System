<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../models/Incident.php';
requireLogin();

header('Content-Type: application/json');

$model     = new Incident();
$incidents = $model->getForMap();

$colors = [
    'pending'     => '#f59e0b',
    'in_progress' => '#3b82f6',
    'resolved'    => '#10b981',
    'closed'      => '#6b7280',
];

$output = array_map(function($i) use ($colors) {
    return [
        'id'       => $i['id'],
        'title'    => $i['title'],
        'status'   => $i['status'],
        'severity' => $i['severity'],
        'location' => $i['location'],
        'lat'      => (float)$i['latitude'],
        'lng'      => (float)$i['longitude'],
        'color'    => $colors[$i['status']] ?? '#6b7280',
    ];
}, $incidents);

echo json_encode($output);