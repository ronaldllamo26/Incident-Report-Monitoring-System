<?php
/**
 * ajax/reverse_geocode.php
 * Converts Coordinates (Lat/Lng) to Human-readable Address via Nominatim
 */
header('Content-Type: application/json');

$lat = $_GET['lat'] ?? null;
$lng = $_GET['lng'] ?? null;

if (!$lat || !$lng) {
    echo json_encode(['error' => 'Missing coordinates']);
    exit;
}

$url = "https://nominatim.openstreetmap.org/reverse?format=json&lat={$lat}&lon={$lng}&zoom=18&addressdetails=1";

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_USERAGENT, 'QC-ALERTO-IRMS/1.0 (Local Testing)');
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_TIMEOUT, 5);

$response = curl_exec($ch);
curl_close($ch);

if (!$response) {
    echo json_encode(['error' => 'Geocoding service unavailable']);
    exit;
}

$data = json_decode($response, true);
$address = $data['display_name'] ?? 'Unknown Location';

echo json_encode([
    'address' => $address,
    'details' => $data['address'] ?? []
]);
