<?php
// public/api/auto-punchout.php
// Called by client JS when GPS is switched off during an active shift
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: https://hrms-ecovista.vercel.app');

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/helpers.php';
session_start();

$user = authUser();
if (!$user) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit;
}

$db = getDBConnection();
$today = date('Y-m-d');
$now = date('Y-m-d H:i:s');

// Check if user is in an active shift today
$att = $db->prepare("SELECT id, clock_in, clock_out, total_hours FROM attendance WHERE user_id = ? AND date = ?");
$att->execute([$user['id'], $today]);
$record = $att->fetch(PDO::FETCH_ASSOC);

if (!$record || $record['clock_out'] !== null) {
    echo json_encode(['success' => false, 'msg' => 'No active shift found.']);
    exit;
}

// Calculate total hours
$clockIn = strtotime($record['clock_in']);
$clockOut = time();
$hoursWorked = round(($clockOut - $clockIn) / 3600, 2);

// Clock out active sessions
$db->prepare("
    UPDATE attendance_sessions 
    SET clock_out = ?, ended_by = 'auto_gps_off' 
    WHERE attendance_id = ? AND clock_out IS NULL
")->execute([$now, $record['id']]);

// Clock out attendance record
$db->prepare("
    UPDATE attendance 
    SET clock_out = ?, total_hours = ?, notes = CONCAT(IFNULL(notes,''), ' [AUTO PUNCH-OUT: GPS location disabled by user]')
    WHERE id = ?
")->execute([$now, $hoursWorked, $record['id']]);

// Destroy session active shift flag
if (session_status() === PHP_SESSION_ACTIVE) {
    $_SESSION['shift_active'] = false;
}

echo json_encode([
    'success' => true,
    'msg'     => 'Auto punched out due to GPS location being disabled.',
    'hours'   => $hoursWorked,
    'at'      => $now
]);