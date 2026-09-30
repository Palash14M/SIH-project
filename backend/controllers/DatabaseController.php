<?php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../utils/Response.php';
require_once __DIR__ . '/../middleware/AuthMiddleware.php';
require_once __DIR__ . '/../utils/Audit.php';

class DatabaseController {
    public function getStatus(): void {
        $pdo = Database::getConnection();
        $dbFile = Config::get('DB_FILE', 'storage/database.sqlite');
        if (!str_starts_with($dbFile, '/') && !preg_match('/^[A-Za-z]:/', $dbFile)) {
            $dbFile = dirname(__DIR__) . '/' . $dbFile;
        }

        $fileSize = file_exists($dbFile) ? filesize($dbFile) : 0;
        $fileSizeFormatted = $fileSize > 1048576 
            ? round($fileSize / 1048576, 2) . ' MB' 
            : round($fileSize / 1024, 2) . ' KB';
        $lastModified = file_exists($dbFile) ? date('Y-m-d H:i:s', filemtime($dbFile)) : 'N/A';

        // Get table counts
        $tablesStmt = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%' ORDER BY name");
        $tables = $tablesStmt->fetchAll(PDO::FETCH_COLUMN);

        $totalRows = 0;
        $tableSummaries = [];
        foreach ($tables as $t) {
            $count = (int)$pdo->query("SELECT COUNT(*) FROM \"{$t}\"")->fetchColumn();
            $totalRows += $count;
            $tableSummaries[$t] = $count;
        }

        // Integrity check
        $integrity = $pdo->query("PRAGMA integrity_check")->fetchColumn();
        $journalMode = $pdo->query("PRAGMA journal_mode")->fetchColumn();
        $foreignKeys = (int)$pdo->query("PRAGMA foreign_keys")->fetchColumn() === 1 ? 'ENABLED' : 'DISABLED';

        Response::success([
            'status' => 'CONNECTED',
            'engine' => 'SQLite 3.x (WAL Architecture)',
            'database_file' => basename($dbFile),
            'full_path' => $dbFile,
            'file_size' => $fileSizeFormatted,
            'file_size_bytes' => $fileSize,
            'last_modified' => $lastModified,
            'journal_mode' => strtoupper($journalMode),
            'foreign_keys' => $foreignKeys,
            'integrity_status' => $integrity === 'ok' ? 'HEALTHY (100% Valid)' : $integrity,
            'total_tables' => count($tables),
            'total_rows' => $totalRows,
            'tables' => $tableSummaries,
        ], 'Database status and metrics retrieved successfully.');
    }

    public function getTables(): void {
        $pdo = Database::getConnection();
        $tablesStmt = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%' ORDER BY name");
        $tables = $tablesStmt->fetchAll(PDO::FETCH_COLUMN);

        $result = [];
        foreach ($tables as $t) {
            $count = (int)$pdo->query("SELECT COUNT(*) FROM \"{$t}\"")->fetchColumn();
            $cols = $pdo->query("PRAGMA table_info(\"{$t}\")")->fetchAll(PDO::FETCH_ASSOC);

            $result[] = [
                'name' => $t,
                'rows_count' => $count,
                'columns_count' => count($cols),
                'columns' => array_map(fn($c) => [
                    'name' => $c['name'],
                    'type' => $c['type'],
                    'notnull' => (bool)$c['notnull'],
                    'pk' => (bool)$c['pk'],
                ], $cols),
            ];
        }

        Response::success($result, 'Database tables and schemas retrieved.');
    }

    public function getTableData(): void {
        $table = trim($_GET['name'] ?? ($_GET['table'] ?? ''));
        if (empty($table)) {
            Response::error('Table name is required.', 400);
        }

        $pdo = Database::getConnection();

        // Check table exists in sqlite_master
        $chk = $pdo->prepare("SELECT name FROM sqlite_master WHERE type='table' AND name = :t LIMIT 1");
        $chk->execute([':t' => $table]);
        if (!$chk->fetch()) {
            Response::notFound("Table '{$table}' does not exist.");
        }

        $limit = max(1, min(100, (int)($_GET['limit'] ?? 30)));
        $offset = max(0, (int)($_GET['offset'] ?? 0));
        $search = trim($_GET['search'] ?? '');

        // Columns
        $cols = $pdo->query("PRAGMA table_info(\"{$table}\")")->fetchAll(PDO::FETCH_ASSOC);
        $totalRows = (int)$pdo->query("SELECT COUNT(*) FROM \"{$table}\"")->fetchColumn();

        if (!empty($search)) {
            // Filter across text columns
            $textCols = array_filter($cols, fn($c) => stripos($c['type'], 'TEXT') !== false || stripos($c['type'], 'CHAR') !== false);
            if (!empty($textCols)) {
                $clauses = [];
                $params = [];
                foreach ($textCols as $idx => $c) {
                    $paramName = ":s_{$idx}";
                    $clauses[] = "\"{$c['name']}\" LIKE {$paramName}";
                    $params[$paramName] = "%{$search}%";
                }
                $where = 'WHERE ' . implode(' OR ', $clauses);
                $stmt = $pdo->prepare("SELECT * FROM \"{$table}\" {$where} LIMIT {$limit} OFFSET {$offset}");
                $stmt->execute($params);
                $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

                $countStmt = $pdo->prepare("SELECT COUNT(*) FROM \"{$table}\" {$where}");
                $countStmt->execute($params);
                $filteredCount = (int)$countStmt->fetchColumn();
            } else {
                $rows = $pdo->query("SELECT * FROM \"{$table}\" LIMIT {$limit} OFFSET {$offset}")->fetchAll(PDO::FETCH_ASSOC);
                $filteredCount = $totalRows;
            }
        } else {
            $rows = $pdo->query("SELECT * FROM \"{$table}\" LIMIT {$limit} OFFSET {$offset}")->fetchAll(PDO::FETCH_ASSOC);
            $filteredCount = $totalRows;
        }

        Response::success([
            'table' => $table,
            'columns' => $cols,
            'rows' => $rows,
            'total_rows' => $totalRows,
            'filtered_count' => $filteredCount,
            'limit' => $limit,
            'offset' => $offset,
        ], "Retrieved rows from '{$table}'.");
    }

    public function runIntegrityCheck(): void {
        $pdo = Database::getConnection();
        $integrity = $pdo->query("PRAGMA integrity_check")->fetchAll(PDO::FETCH_COLUMN);
        $quick = $pdo->query("PRAGMA quick_check")->fetchAll(PDO::FETCH_COLUMN);

        $isOk = ($integrity[0] ?? '') === 'ok' && ($quick[0] ?? '') === 'ok';

        Response::success([
            'passed' => $isOk,
            'integrity_check' => $integrity,
            'quick_check' => $quick,
            'status' => $isOk ? '100% HEALTHY' : 'WARNINGS FOUND',
            'checked_at' => date('Y-m-d H:i:s'),
        ], $isOk ? 'Database integrity check passed successfully.' : 'Integrity check reported issues.');
    }

    public function exportDatabase(): void {
        $pdo = Database::getConnection();
        $tablesStmt = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%' ORDER BY name");
        $tables = $tablesStmt->fetchAll(PDO::FETCH_COLUMN);

        $export = [
            'export_metadata' => [
                'generated_at' => date('Y-m-d H:i:s'),
                'system' => 'MoSJE Field Inspection & Monitoring Portal',
                'database_type' => 'SQLite 3.x',
                'tables_count' => count($tables),
            ],
            'tables' => [],
        ];

        foreach ($tables as $t) {
            $rows = $pdo->query("SELECT * FROM \"{$t}\"")->fetchAll(PDO::FETCH_ASSOC);
            $cols = $pdo->query("PRAGMA table_info(\"{$t}\")")->fetchAll(PDO::FETCH_ASSOC);
            $export['tables'][$t] = [
                'columns' => $cols,
                'rows_count' => count($rows),
                'data' => $rows,
            ];
        }

        Response::success($export, 'Full database exported successfully.');
    }
}

// Register Database Management Routes
Router::add('GET', '/api/admin/database/status', [DatabaseController::class, 'getStatus']);
Router::add('GET', '/api/admin/database/tables', [DatabaseController::class, 'getTables']);
Router::add('GET', '/api/admin/database/table', [DatabaseController::class, 'getTableData']);
Router::add('POST', '/api/admin/database/integrity-check', [DatabaseController::class, 'runIntegrityCheck']);
Router::add('GET', '/api/admin/database/export', [DatabaseController::class, 'exportDatabase']);
