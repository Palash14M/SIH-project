<?php
require 'backend/config/Database.php';
$pdo = Database::getConnection();
$otps = $pdo->query("SELECT * FROM otp_codes ORDER BY id DESC LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
print_r($otps);
