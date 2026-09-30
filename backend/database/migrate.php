<?php

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';

echo "=== RUNNING DATABASE MIGRATIONS ===\n";

$isFresh = in_array('--fresh', $argv ?? [], true);
$pdo = Database::getConnection();

if ($isFresh) {
    echo "Fresh migration requested: Dropping existing tables...\n";
    $driver = Config::get('DB_CONNECTION', 'sqlite');

    if ($driver === 'sqlite') {
        $tables = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'")->fetchAll(PDO::FETCH_COLUMN);
        $pdo->exec('PRAGMA foreign_keys = OFF;');
        foreach ($tables as $table) {
            $pdo->exec("DROP TABLE IF EXISTS `{$table}`");
            echo " - Dropped table: {$table}\n";
        }
        $pdo->exec('PRAGMA foreign_keys = ON;');
    } else {
        $pdo->exec('SET FOREIGN_KEY_CHECKS = 0;');
        $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
        foreach ($tables as $table) {
            $pdo->exec("DROP TABLE IF EXISTS `{$table}`");
            echo " - Dropped table: {$table}\n";
        }
        $pdo->exec('SET FOREIGN_KEY_CHECKS = 1;');
    }
}

$schemaFile = __DIR__ . '/schema.sql';
if (!file_exists($schemaFile)) {
    die("Schema file not found at {$schemaFile}\n");
}

$sql = file_get_contents($schemaFile);

// Strip multi-line and single-line SQL comments cleanly
$sqlClean = preg_replace('/--.*$/m', '', $sql);
$sqlClean = preg_replace('/\/\*.*?\*\//s', '', $sqlClean);

// Parse SQL statements by semicolon
$queries = explode(';', $sqlClean);
$executed = 0;

$pdo->beginTransaction();
try {
    foreach ($queries as $query) {
        $trimmed = trim($query);
        if ($trimmed === '') {
            continue;
        }

        $pdo->exec($trimmed);
        $executed++;
    }
    $pdo->commit();
    echo "\n[SUCCESS] Successfully executed {$executed} schema statements.\n";
} catch (Throwable $e) {
    $pdo->rollBack();
    echo "\n[ERROR] Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}
