<?php
require_once __DIR__ . '/../config/database.php';
$db = getDBConnection();
$cols = [
    "login_otp TEXT",
    "login_otp_expires_at DATETIME",
    "login_otp_last_sent_at DATETIME",
    "otp_sent_count_today INTEGER DEFAULT 0",
    "otp_last_sent_date TEXT",
    "is_otp_blocked_today INTEGER DEFAULT 0"
];
foreach ($cols as $col) {
    try {
        $db->exec("ALTER TABLE users ADD COLUMN " . $col);
        echo "Added column: $col\n";
    } catch (Exception $e) {
        echo "Column status ($col): " . $e->getMessage() . "\n";
    }
}
echo "MIGRATION_COMPLETE\n";
