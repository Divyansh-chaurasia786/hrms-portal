<?php
// database/upgrade_leaves_schema.php
require_once __DIR__ . '/../config/database.php';

$db = getDBConnection();
$db->exec("PRAGMA foreign_keys = OFF;");
$db->exec("BEGIN TRANSACTION;");

$db->exec("
    CREATE TABLE IF NOT EXISTS leave_applications_new (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER NOT NULL,
        leave_type_id INTEGER NOT NULL,
        start_date TEXT NOT NULL,
        end_date TEXT NOT NULL,
        total_days REAL NOT NULL,
        reason TEXT NOT NULL,
        status TEXT DEFAULT 'pending_tl_review' CHECK(status IN ('pending_tl_review', 'pending_hr_approval', 'approved', 'rejected')),
        tl_reviewed INTEGER DEFAULT 0,
        tl_recommendation TEXT DEFAULT 'neutral',
        tl_remarks TEXT,
        tl_reviewed_at DATETIME,
        hr_action_by INTEGER,
        hr_action_at DATETIME,
        hr_remarks TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (leave_type_id) REFERENCES leave_types(id) ON DELETE CASCADE
    );
");

// Migrate old data if exists
$db->exec("
    INSERT OR IGNORE INTO leave_applications_new (id, user_id, leave_type_id, start_date, end_date, total_days, reason, status, created_at)
    SELECT id, user_id, leave_type_id, start_date, end_date, total_days, reason, 
           CASE WHEN status = 'pending' THEN 'pending_tl_review' ELSE status END, created_at 
    FROM leave_applications;
");

$db->exec("DROP TABLE leave_applications;");
$db->exec("ALTER TABLE leave_applications_new RENAME TO leave_applications;");

$db->exec("COMMIT;");
$db->exec("PRAGMA foreign_keys = ON;");

// Insert a sample leave for testing
$db->exec("
    INSERT OR IGNORE INTO leave_applications (user_id, leave_type_id, start_date, end_date, total_days, reason, status)
    VALUES (3, 1, '2026-08-28', '2026-08-29', 2, 'Family wedding in hometown. All backend tasks delegated.', 'pending_tl_review');
");

echo "Leave schema upgraded to TL Review -> HR Approval workflow!\n";
