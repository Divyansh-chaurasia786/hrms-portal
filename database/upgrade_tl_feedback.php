<?php
// database/upgrade_tl_feedback.php
require_once __DIR__ . '/../config/database.php';

$db = getDBConnection();

$db->exec("
    CREATE TABLE IF NOT EXISTS tl_feedbacks (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        tl_id INTEGER NOT NULL,
        hr_id INTEGER NOT NULL,
        message TEXT NOT NULL,
        priority TEXT DEFAULT 'normal' CHECK(priority IN ('normal', 'important', 'urgent')),
        status TEXT DEFAULT 'unread' CHECK(status IN ('unread', 'acknowledged')),
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (tl_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (hr_id) REFERENCES users(id) ON DELETE CASCADE
    );
");

// Add demo feedback from HR to TL Vikram
$db->exec("
    INSERT INTO tl_feedbacks (tl_id, hr_id, message, priority, status)
    VALUES (2, 1, 'Great job on completing the Design System prototype ahead of schedule. Please ensure the Backend Auth API sprint is reviewed by Thursday.', 'important', 'unread');
");

echo "TL Feedback schema and demo records initialized successfully!\n";
