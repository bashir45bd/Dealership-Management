<?php
/** Run additive database migrations: php database/migrate.php */
define('APP_INIT', true);
require_once __DIR__ . '/../config/app.php';

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Migrations can only run from the CLI.');
}

$db = Database::getConnection();
$db->exec("CREATE TABLE IF NOT EXISTS schema_migrations (version VARCHAR(190) PRIMARY KEY, applied_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
$done = $db->query('SELECT version FROM schema_migrations')->fetchAll(PDO::FETCH_COLUMN);
foreach (glob(__DIR__ . '/migrations/*.sql') as $file) {
    $version = basename($file);
    if (in_array($version, $done, true)) { echo "Skipped {$version}\n"; continue; }
    $sql = file_get_contents($file);
    try {
        // MySQL DDL performs an implicit commit. Do not wrap schema files in
        // PDO transactions or a successful ALTER/CREATE would be reported as
        // a false failure when commit() is reached.
        $db->exec($sql);
        $db->prepare('INSERT INTO schema_migrations (version) VALUES (:version)')->execute([':version' => $version]);
        echo "Applied {$version}\n";
    } catch (Throwable $e) {
        if ($db->inTransaction()) $db->rollBack();
        fwrite(STDERR, "Failed {$version}: {$e->getMessage()}\n");
        exit(1);
    }
}
