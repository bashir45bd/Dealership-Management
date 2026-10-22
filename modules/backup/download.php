<?php
/**
 * Maruf Traders - Database Backup Export
 * Streams a full .sql dump (structure + data) of the current database
 * generated via PDO — no shell_exec/mysqldump dependency (works on
 * shared hosting where shell access is disabled).
 *
 * This reuses includes/header.php purely for its session start / DB
 * connection / auth setup — its HTML layout output is captured in a
 * buffer and discarded, since this script must output raw SQL only.
 */

define('APP_INIT', true);

ob_start();
require_once __DIR__ . '/../../includes/header.php';
requirePermission('backup.manage');
ob_end_clean();

set_time_limit(0);
ini_set('memory_limit', '512M');

$db = Database::getConnection();

$dbNameRow = $db->query("SELECT DATABASE() AS db_name")->fetch();
$dbName = $dbNameRow['db_name'] ?? 'maruf_traders';

$filename = 'maruf_traders_backup_' . date('Y-m-d_His') . '.sql';

header('Content-Type: application/sql; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Pragma: no-cache');

$ROWS_PER_INSERT = 200; // batch size for INSERT statements

echo "-- Maruf Traders - Full Database Backup\n";
echo "-- Database: {$dbName}\n";
echo "-- Generated: " . date('Y-m-d H:i:s') . "\n";
echo "-- Generator: Maruf Traders Backup Module (PDO export)\n\n";

echo "SET NAMES utf8mb4;\n";
echo "SET FOREIGN_KEY_CHECKS = 0;\n";
echo "SET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO';\n";
echo "SET time_zone = '+00:00';\n\n";

$tables = $db->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);

foreach ($tables as $table) {
    $tableEsc = "`" . str_replace("`", "``", $table) . "`";

    // ---- Structure ----
    echo "-- --------------------------------------------------------\n";
    echo "-- Table structure for {$table}\n";
    echo "-- --------------------------------------------------------\n\n";

    echo "DROP TABLE IF EXISTS {$tableEsc};\n";

    $createRow = $db->query("SHOW CREATE TABLE {$tableEsc}")->fetch(PDO::FETCH_ASSOC);
    $createSql = $createRow['Create Table'] ?? $createRow['Create View'] ?? null;

    if ($createSql) {
        echo $createSql . ";\n\n";
    }

    // ---- Data ----
    $countStmt = $db->query("SELECT COUNT(*) AS cnt FROM {$tableEsc}");
    $rowCount = (int)$countStmt->fetchColumn();

    if ($rowCount > 0) {
        echo "-- Data for {$table} ({$rowCount} rows)\n";

        $colStmt = $db->query("SHOW COLUMNS FROM {$tableEsc}");
        $columns = $colStmt->fetchAll(PDO::FETCH_ASSOC);
        $colNames = array_map(fn($c) => "`" . str_replace("`", "``", $c['Field']) . "`", $columns);
        $colList = implode(', ', $colNames);

        $offset = 0;
        $limit = 500; // rows fetched per DB round-trip (memory-safe)

        $batchBuffer = [];
        $flushCount = 0;

        while ($offset < $rowCount) {
            $dataStmt = $db->query("SELECT * FROM {$tableEsc} LIMIT {$limit} OFFSET {$offset}");
            $rows = $dataStmt->fetchAll(PDO::FETCH_ASSOC);
            if (!$rows) {
                break;
            }

            foreach ($rows as $row) {
                $values = [];
                foreach ($row as $value) {
                    if ($value === null) {
                        $values[] = 'NULL';
                    } elseif (is_numeric($value) && !preg_match('/^0[0-9]/', (string)$value)) {
                        $values[] = $value;
                    } else {
                        $values[] = $db->quote($value);
                    }
                }
                $batchBuffer[] = '(' . implode(', ', $values) . ')';
                $flushCount++;

                if ($flushCount >= $ROWS_PER_INSERT) {
                    echo "INSERT INTO {$tableEsc} ({$colList}) VALUES\n" . implode(",\n", $batchBuffer) . ";\n";
                    $batchBuffer = [];
                    $flushCount = 0;
                    flush();
                }
            }

            $offset += $limit;
        }

        if (!empty($batchBuffer)) {
            echo "INSERT INTO {$tableEsc} ({$colList}) VALUES\n" . implode(",\n", $batchBuffer) . ";\n";
        }

        echo "\n";
    } else {
        echo "-- (no rows)\n\n";
    }

    flush();
}

echo "SET FOREIGN_KEY_CHECKS = 1;\n";
echo "-- Backup completed: " . date('Y-m-d H:i:s') . "\n";

// Optional audit trail — only fires if the project's audit logger exists
if (function_exists('logAuditEvent')) {
    logAuditEvent($_SESSION['user_id'] ?? null, 'database_backup', "Downloaded full database backup ({$dbName})");
} elseif (function_exists('logAudit')) {
    logAudit('database_backup', "Downloaded full database backup ({$dbName})");
}

exit;