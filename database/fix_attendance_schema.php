<?php
// database/fix_attendance_schema.php
require_once __DIR__ . '/../config/database.php';

$db = getDBConnection();

// SQLite table recreation to remove NOT NULL from clock_in
$db->exec("PRAGMA foreign_keys = OFF;");
$db->exec("BEGIN TRANSACTION;");

$db->exec("
    CREATE TABLE IF NOT EXISTS attendance_new (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER NOT NULL,
        date TEXT NOT NULL,
        clock_in DATETIME,
        clock_out DATETIME,
        total_hours REAL DEFAULT 0,
        status TEXT DEFAULT 'present' CHECK(status IN ('present', 'half_day', 'wfh', 'absent')),
        notes TEXT,
        tl_approved INTEGER DEFAULT 0,
        tl_approved_at DATETIME,
        tl_approved_by INTEGER,
        hr_corrected INTEGER DEFAULT 0,
        hr_alert_message TEXT,
        locked_by_hr INTEGER DEFAULT 0,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        UNIQUE(user_id, date)
    );
");

$db->exec("INSERT OR IGNORE INTO attendance_new SELECT id, user_id, date, clock_in, clock_out, total_hours, status, notes, tl_approved, tl_approved_at, tl_approved_by, hr_corrected, hr_alert_message, locked_by_hr FROM attendance;");

$db->exec("DROP TABLE attendance;");
$db->exec("ALTER TABLE attendance_new RENAME TO attendance;");

$db->exec("COMMIT;");
$db->exec("PRAGMA foreign_keys = ON;");

echo "Attendance schema constraint fixed successfully!\n";
