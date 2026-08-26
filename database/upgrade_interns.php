<?php
// database/upgrade_interns.php
require_once __DIR__ . '/../config/database.php';

$db = getDBConnection();

$cols = [
    'employment_type' => "TEXT DEFAULT 'full_time'",
    'internship_duration' => 'TEXT',
    'internship_end_date' => 'TEXT'
];

foreach ($cols as $col => $type) {
    try {
        $db->exec("ALTER TABLE users ADD COLUMN {$col} {$type}");
    } catch (Exception $e) {}
}

// Add a demo Paid Intern and Unpaid Intern
$hash = password_hash('password123', PASSWORD_BCRYPT);
$tlId = 2; // Vikram

// 1. Paid Intern
try {
    $stmt = $db->prepare("
        INSERT INTO users (
            emp_id, name, email, password, role, department_id, reporting_tl_id,
            designation, phone, joining_date, salary_basic,
            employment_type, internship_duration,
            aadhaar_no, pan_no, bank_account_no, bank_ifsc, bank_name, status
        ) VALUES (
            'INT001', 'Ananya Sharma', 'ananya@company.com', ?, 'employee', 1, ?,
            'Backend Intern', '+91 9876543220', date('Y-m-d'), 10000,
            'intern_paid', '6 Months',
            '3321 4455 6677', 'ANXPS1122M', '6655443322110', 'HDFC0004321', 'HDFC Bank', 'active'
        )
    ");
    $stmt->execute([$hash, $tlId]);
} catch (Exception $e) {}

// 2. Unpaid Intern
try {
    $stmt = $db->prepare("
        INSERT INTO users (
            emp_id, name, email, password, role, department_id, reporting_tl_id,
            designation, phone, joining_date, salary_basic,
            employment_type, internship_duration,
            aadhaar_no, pan_no, bank_account_no, bank_ifsc, bank_name, status
        ) VALUES (
            'INT002', 'Rohan Mehta', 'rohan@company.com', ?, 'employee', 2, ?,
            'UI/UX Design Intern', '+91 9876543221', date('Y-m-d'), 0,
            'intern_unpaid', '3 Months',
            '7788 9900 1122', 'RMEPS9900K', 'N/A', 'N/A', 'N/A', 'active'
        )
    ");
    $stmt->execute([$hash, $tlId]);
} catch (Exception $e) {}

echo "Intern schema & demo accounts updated!\n";
