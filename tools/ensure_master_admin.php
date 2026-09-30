<?php
require_once __DIR__ . '/../backend/config/database.php';
$pdo = Database::getConnection();

// Check if user with username 'admin' exists
$stmt = $pdo->prepare("SELECT * FROM users WHERE username = 'admin' OR role = 'MASTER_ADMIN' LIMIT 1");
$stmt->execute();
$user = $stmt->fetch();

if (!$user) {
    echo "Master Admin not found. Inserting seed account...\n";
    $ins = $pdo->prepare("
        INSERT INTO users (username, name, email, phone, password_hash, role, must_change_password, status, created_at, updated_at)
        VALUES ('admin', 'Master Supreme Administrator', 'admin@master.gov.in', '9500000000', :hash, 'MASTER_ADMIN', 1, 'ACTIVE', datetime('now'), datetime('now'))
    ");
    $ins->execute([':hash' => password_hash('admin', PASSWORD_BCRYPT)]);
    echo "Master Admin inserted with ID: " . $pdo->lastInsertId() . "\n";
} else {
    echo "Found user: ID={$user['id']}, username={$user['username']}, role={$user['role']}\n";
    // Ensure password_hash is for 'admin' and username is 'admin' and role is 'MASTER_ADMIN'
    $upd = $pdo->prepare("UPDATE users SET username = 'admin', role = 'MASTER_ADMIN', password_hash = :hash, must_change_password = 1 WHERE id = :id");
    $upd->execute([':hash' => password_hash('admin', PASSWORD_BCRYPT), ':id' => $user['id']]);
    echo "Master Admin refreshed.\n";
}
