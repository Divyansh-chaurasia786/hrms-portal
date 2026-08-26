<?php
// database/upgrade_kyc.php
require_once __DIR__ . '/../config/database.php';

$db = getDBConnection();

$cols = [
    'aadhaar_no' => 'TEXT',
    'pan_no' => 'TEXT',
    'bank_account_no' => 'TEXT',
    'bank_ifsc' => 'TEXT',
    'bank_name' => 'TEXT',
    'emergency_contact' => 'TEXT'
];

foreach ($cols as $col => $type) {
    try {
        $db->exec("ALTER TABLE users ADD COLUMN {$col} {$type}");
    } catch (Exception $e) {
        // already exists
    }
}

$db->exec("UPDATE users SET aadhaar_no = '4521 8892 1029', pan_no = 'ABCDE1234F', bank_account_no = '9876543210123', bank_ifsc = 'HDFC0001234', bank_name = 'HDFC Bank' WHERE emp_id = 'EMP002'");
$db->exec("UPDATE users SET aadhaar_no = '5521 3392 4021', pan_no = 'BKXPE9988K', bank_account_no = '1234567890123', bank_ifsc = 'SBIN0005678', bank_name = 'State Bank of India' WHERE emp_id = 'EMP003'");
$db->exec("UPDATE users SET aadhaar_no = '8821 7712 9012', pan_no = 'CPZPE4455L', bank_account_no = '4567891230123', bank_ifsc = 'ICIC0009988', bank_name = 'ICICI Bank' WHERE emp_id = 'EMP004'");

echo "KYC migration executed successfully!\n";
