<?php
require_once __DIR__ . '/../backend/config/Database.php';

$pdo = Database::getConnection();

$hashDemo = password_hash('demo@123', PASSWORD_BCRYPT);
$hashAdmin = password_hash('admin', PASSWORD_BCRYPT);

// 1. Update ALL existing users to have demo@123 password and must_change_password = 0
$stmt = $pdo->prepare("UPDATE users SET password_hash = :hash, must_change_password = 0, status = 'ACTIVE'");
$stmt->execute([':hash' => $hashDemo]);
echo "Updated all users to password: demo@123\n";

// 2. Set Master Admin (ID 1)
$stmtMaster = $pdo->prepare("UPDATE users SET username = 'admin', email = 'admin@master.gov.in', password_hash = :hash, role = 'MASTER_ADMIN', status = 'ACTIVE', must_change_password = 0 WHERE id = 1");
$stmtMaster->execute([':hash' => $hashAdmin]);
echo "Master Admin set (username: admin, password: admin)\n";

// 3. Set aliases and clean emails for standard demo users
$aliases = [
    ['inspector', 'inspector.rajesh@mosje.gov.in'],
    ['district', 'district.nagpur@mosje.gov.in'],
    ['state', 'state.mh@mosje.gov.in'],
    ['mosje', 'mosje.admin@gov.in'],
    ['ngo', 'demo.ngo@example.org'],
];

foreach ($aliases as [$uname, $email]) {
    $uStmt = $pdo->prepare("UPDATE users SET username = :uname WHERE email = :email");
    $uStmt->execute([':uname' => $uname, ':email' => $email]);
    echo "Set username '{$uname}' for {$email}\n";
}

// 4. Ensure Contractor user exists in `users` table
$checkContractor = $pdo->query("SELECT id FROM users WHERE role = 'CONTRACTOR' OR email = 'contractor@larseninfra.com' LIMIT 1")->fetch();
if (!$checkContractor) {
    $stmtC = $pdo->prepare("
        INSERT INTO users (username, name, email, phone, password_hash, role, state_id, district_id, status, must_change_password, created_at, updated_at)
        VALUES ('contractor', 'Rajesh Patel (Contractor)', 'contractor@larseninfra.com', '9822012345', :hash, 'CONTRACTOR', 1, 1, 'ACTIVE', 0, datetime('now'), datetime('now'))
    ");
    $stmtC->execute([':hash' => $hashDemo]);
    echo "Created new CONTRACTOR user: contractor@larseninfra.com / contractor (password: demo@123)\n";
} else {
    $stmtC = $pdo->prepare("UPDATE users SET username = 'contractor', email = 'contractor@larseninfra.com', password_hash = :hash, role = 'CONTRACTOR', status = 'ACTIVE', must_change_password = 0 WHERE id = :id");
    $stmtC->execute([':hash' => $hashDemo, ':id' => $checkContractor['id']]);
    echo "Updated existing CONTRACTOR user ID {$checkContractor['id']}\n";
}

// 5. Ensure valid Demo OTP for citizens
$pdo->exec("DELETE FROM otp_codes WHERE phone IN ('9821004567', '9876543210')");
$otpStmt = $pdo->prepare("INSERT INTO otp_codes (phone, code, expires_at, created_at) VALUES (:phone, '123456', datetime('now', '+10 years'), datetime('now'))");
$otpStmt->execute([':phone' => '9821004567']);
$otpStmt->execute([':phone' => '9876543210']);
echo "Inserted active Demo OTP 123456 for citizen phones\n";

echo "=== ALL DEMO ACCOUNTS AND PASSWORDS FULLY RESET & SYNCED ===\n";
