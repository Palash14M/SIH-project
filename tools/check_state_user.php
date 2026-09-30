<?php
require 'backend/config/Database.php';
$pdo = Database::getConnection();
$user = $pdo->query("SELECT id, name, email, phone, role FROM users WHERE email = 'state.maharashtra@mosje.gov.in'")->fetch(PDO::FETCH_ASSOC);
print_r($user);
