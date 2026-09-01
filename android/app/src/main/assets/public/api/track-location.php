<?php
// public/api/track-location.php - Location Ping Receiver
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: https://hrms-ecovista.vercel.app');

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/helpers.php';

session_start();
$user = authUser();
if (!$user) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$payload = json_decode(file_get_contents('php://input'), true) ?: [];
$lat     = isset($payload['lat']) ? (float)$payload['lat'] : null;
$lng     = isset($payload['lng']) ? (float)$payload['lng'] : null;
$acc     = isset($payload['accuracy']) ? (float)$payload['accuracy'] : null;
$type    = in_array($payload['type'] ?? '', ['clock_in','clock_out','auto_ping','manual']) ? $payload['type'] : 'auto_ping';
$device  = substr(strip_tags($payload['device'] ?? ''), 0, 512);
$address = substr(strip_tags($payload['address'] ?? ''), 0, 512);

if (!$lat || !$lng) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Coordinates required']);
    exit;
}

$db = getDBConnection();
$db->prepare("
    INSERT INTO location_pings (user_id, latitude, longitude, accuracy, address, device_info, ping_type, session_date, created_at)
    VALUES (?, ?, ?, ?, ?, ?, ?, CURDATE(), NOW())
")->execute([$user['id'], $lat, $lng, $acc, $address, $device, $type]);

echo json_encode([
    'success'    => true,
    'message'    => 'Location tracked.',
    'user_id'    => $user['id'],
    'type'       => $type,
    'lat'        => $lat,
    'lng'        => $lng,
    'timestamp'  => date('Y-m-d H:i:s'),
]);