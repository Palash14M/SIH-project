<?php

require_once __DIR__ . '/config.php';

class Database {
    private static ?PDO $pdo = null;

    public static function getConnection(): PDO {
        if (self::$pdo !== null) {
            return self::$pdo;
        }

        $connection = Config::get('DB_CONNECTION', 'sqlite');

        try {
            if ($connection === 'sqlite') {
                $dbFile = Config::get('DB_FILE', 'storage/database.sqlite');
                if (!str_starts_with($dbFile, '/') && !preg_match('/^[A-Za-z]:/', $dbFile)) {
                    $dbFile = dirname(__DIR__) . '/' . $dbFile;
                }

                $dir = dirname($dbFile);
                if (!is_dir($dir)) {
                    mkdir($dir, 0777, true);
                }

                $dsn = "sqlite:{$dbFile}";
                self::$pdo = new PDO($dsn, null, null, [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ]);

                // Enable foreign keys and WAL mode for SQLite
                self::$pdo->exec('PRAGMA foreign_keys = ON;');
                self::$pdo->exec('PRAGMA journal_mode = WAL;');
            } else {
                $host = Config::get('DB_HOST', '127.0.0.1');
                $port = Config::get('DB_PORT', '3306');
                $dbname = Config::get('DB_DATABASE', 'smart_inspection');
                $user = Config::get('DB_USERNAME', 'root');
                $pass = Config::get('DB_PASSWORD', '');

                $dsn = "mysql:host={$host};port={$port};dbname={$dbname};charset=utf8mb4";
                self::$pdo = new PDO($dsn, $user, $pass, [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ]);
            }

            return self::$pdo;
        } catch (PDOException $e) {
            throw new RuntimeException("Database connection error: " . $e->getMessage(), (int)$e->getCode(), $e);
        }
    }

    public static function reset(): void {
        self::$pdo = null;
    }
}
