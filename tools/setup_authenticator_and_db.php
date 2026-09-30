<?php
require_once __DIR__ . '/../backend/config/Database.php';
require_once __DIR__ . '/../backend/services/TotpService.php';

$pdo = Database::getConnection();

// 1. Add totp_secret column to users table if missing
$cols = $pdo->query("PRAGMA table_info(users)")->fetchAll(PDO::FETCH_ASSOC);
$colNames = array_column($cols, 'name');

if (!in_array('totp_secret', $colNames)) {
    $pdo->exec("ALTER TABLE users ADD COLUMN totp_secret VARCHAR(64) DEFAULT NULL");
    echo "Added column 'totp_secret' to users table.\n";
} else {
    echo "Column 'totp_secret' already exists in users table.\n";
}

// 2. Set predefined standard TOTP secrets for each official demo account
$demoSecrets = [
    'admin@master.gov.in'            => 'MOSJEMASTERADMIN2026SECKEY',
    'admin'                          => 'MOSJEMASTERADMIN2026SECKEY',
    'mosje.admin@gov.in'             => 'MOSJECENTRALDIR2026TOTPKEY',
    'state.maharashtra@mosje.gov.in' => 'MAHARASHTRASTATEOFFICER26',
    'district.nagpur@mosje.gov.in'   => 'NAGPURDISTRICTOFFICER2026',
    'district.demo@mosje.gov.in'     => 'NAGPURDISTRICTOFFICER2026',
    'inspector.rajesh@mosje.gov.in'  => 'RAJESHMESHRAMINSPNAGPUR26',
    'inspector.demo@mosje.gov.in'    => 'RAJESHMESHRAMINSPNAGPUR26',
    'demo.ngo@example.org'           => 'SEWABHARATINGOAUDITOR2026',
];

$updStmt = $pdo->prepare("UPDATE users SET totp_secret = :sec WHERE email = :id OR username = :id");
foreach ($demoSecrets as $id => $sec) {
    // Ensure base32 valid
    $base32Clean = preg_replace('/[^A-Z2-7]/', 'A', strtoupper($sec));
    $updStmt->execute([':sec' => $base32Clean, ':id' => $id]);
    $code = TotpService::getCode($base32Clean);
    echo "Configured Authenticator for {$id}: Secret={$base32Clean} | Active TOTP={$code}\n";
}

echo "AUTHENTICATOR SECRETS SETUP COMPLETE!\n";
