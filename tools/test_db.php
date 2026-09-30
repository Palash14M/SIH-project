<?php

require_once __DIR__ . '/../backend/config/config.php';
require_once __DIR__ . '/../backend/config/database.php';

$pdo = Database::getConnection();
echo "Connected PDO driver: " . $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) . "\n";

$tables = $pdo->query("SELECT name FROM sqlite_master WHERE type='table'")->fetchAll(PDO::FETCH_COLUMN);
echo "Tables found: " . count($tables) . "\n";
print_r($tables);
