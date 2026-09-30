<?php
$db = new PDO('sqlite:backend/storage/database.sqlite');
$stmt = $db->query('SELECT id, name, email, role, password_hash, status FROM users WHERE role IN ("INSPECTOR", "DISTRICT_OFFICER", "STATE_OFFICER", "MOSJE_ADMIN", "MASTER_ADMIN")');
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);
foreach ($users as $u) {
    $matchesDemo = password_verify('Demo@123', $u['password_hash']);
    $matchesAdmin = password_verify('admin', $u['password_hash']);
    echo "ID: {$u['id']} | Name: {$u['name']} | Email: {$u['email']} | Role: {$u['role']} | Matches Demo@123: " . ($matchesDemo ? 'YES' : 'NO') . " | Matches admin: " . ($matchesAdmin ? 'YES' : 'NO') . "\n";
}
