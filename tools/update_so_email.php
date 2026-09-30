<?php
require 'backend/config/Database.php';
$pdo = Database::getConnection();
$hashDemo = password_hash('Demo@123', PASSWORD_BCRYPT);
$pdo->exec("UPDATE users SET email = 'state.maharashtra@mosje.gov.in', password_hash = '$hashDemo' WHERE id = 4");
echo "Updated user 4 to state.maharashtra@mosje.gov.in with Demo@123\n";
$user = $pdo->query("SELECT id, name, email, phone, role FROM users WHERE id = 4")->fetch(PDO::FETCH_ASSOC);
print_r($user);
