<?php
// database/upgrade_attendance_workflow.php
require_once __DIR__ . '/../config/database.php';

$db = getDBConnection();

$cols = [
    'tl_approved' => 'INTEGER DEFAULT 0',
    'tl_approved_at' => 'DATETIME',
    'tl_approved_by' => 'INTEGER',
    'hr_corrected' => 'INTEGER DEFAULT 0',
    'hr_alert_message' => 'TEXT',
    'locked_by_hr' => 'INTEGER DEFAULT 0'
];

foreach ($cols as $col => $type) {
    try {
        $db->exec("ALTER TABLE attendance ADD COLUMN {$col} {$type}");
    } catch (Exception $e) {}
}

// Update existing attendance to be TL approved for demo continuity, with one sample HR corrected record
$today = date('Y-m-d');
$db->exec("UPDATE attendance SET tl_approved = 1, tl_approved_at = datetime('now'), tl_approved_by = 2 WHERE date = '$today'");

// Make Rahul's attendance an example of HR correction with red alert
$db->exec("UPDATE attendance SET hr_corrected = 1, locked_by_hr = 1, hr_alert_message = 'Wrong attendance marked (Clock-in time was delayed by 30 mins) - Corrected & Locked by HR', notes = 'Adjusted to 09:15 AM after verifying Slack check-in' WHERE user_id = 3 AND date = '$today'");

echo "Attendance workflow schema upgraded successfully!\n";
