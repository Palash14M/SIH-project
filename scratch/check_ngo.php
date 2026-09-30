<?php
require_once __DIR__ . '/../backend/config/database.php';
$pdo = Database::getConnection();
$ngos = $pdo->query("SELECT * FROM ngos")->fetchAll();
print_r($ngos);
$users = $pdo->query("SELECT id, email, role, status FROM users WHERE role = 'NGO'")->fetchAll();
print_r($users);
