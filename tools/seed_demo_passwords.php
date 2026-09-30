<?php
require_once __DIR__ . '/../backend/config/Database.php';

$pdo = Database::getConnection();

$hashDemo = password_hash('Demo@123', PASSWORD_BCRYPT);
$hashAdmin = password_hash('admin', PASSWORD_BCRYPT);

// 1. Update named officers to Demo@123
$emails = [
    'inspector.rajesh@mosje.gov.in',
    'inspector.demo@mosje.gov.in',
    'district.nagpur@mosje.gov.in',
    'district.demo@mosje.gov.in',
    'state.maharashtra@mosje.gov.in',
    'state.demo@mosje.gov.in',
    'state.mh@mosje.gov.in',
    'mosje.admin@gov.in',
    'demo.ngo@example.org'
];

$stmt = $pdo->prepare("UPDATE users SET password_hash = :hash WHERE email = :email");
foreach ($emails as $em) {
    $stmt->execute([':hash' => $hashDemo, ':email' => $em]);
    echo "Updated {$em} to Demo@123\n";
}

// 2. Ensure Master Admin user 1 has username 'admin', email 'admin@master.gov.in', password 'admin'
$stmtMaster = $pdo->prepare("UPDATE users SET username = 'admin', password_hash = :hash, role = 'MASTER_ADMIN', status = 'ACTIVE' WHERE id = 1");
$stmtMaster->execute([':hash' => $hashAdmin]);
echo "Updated Master Admin (ID 1) with username=admin and password=admin\n";

// 3. For Public OTP demo, ensure valid unexpired OTP code 123456
$pdo->exec("DELETE FROM otp_codes WHERE phone = '9821004567'");
$otpStmt = $pdo->prepare("INSERT INTO otp_codes (phone, code, expires_at, created_at) VALUES ('9821004567', '123456', datetime('now', '+10 years'), datetime('now'))");
$otpStmt->execute();
echo "Inserted active Demo OTP for 9821004567\n";

echo "ALL SEED DEMO PASSWORDS AND ACCOUNTS SYNCED SUCCESSFULLY!\n";
