<?php
/**
 * Maruf Traders - One-Click Database Setup & Migrator
 */

define('APP_INIT', true);
require_once __DIR__ . '/config/app.php';

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $dbHost = trim($_POST['db_host'] ?? 'localhost');
    $dbPort = trim($_POST['db_port'] ?? '3306');
    $dbName = trim($_POST['db_name'] ?? 'dealership_saas');
    $dbUser = trim($_POST['db_user'] ?? 'root');
    $dbPass = trim($_POST['db_pass'] ?? '');

    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid security token. Please refresh and try again.';
    } elseif (!preg_match('/^[A-Za-z0-9_]+$/', $dbName)) {
        $error = 'Database name may contain only letters, numbers, and underscores.';
    } else {

    try {
        // Connect to MySQL server without database first
        $pdo = new PDO("mysql:host={$dbHost};port={$dbPort};charset=utf8mb4", $dbUser, $dbPass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
        ]);

        // Create Database if not exists
        $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$dbName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        $pdo->exec("USE `{$dbName}`");

        // Fresh-install schema only. Upgrades must use the versioned CLI migrations.
        $schemaSql = file_get_contents(__DIR__ . '/database/finaldatabase.sql');
        $pdo->exec($schemaSql);

        // Read and execute seed.sql
        $seedSql = file_get_contents(__DIR__ . '/database/seed.sql');
        $pdo->exec($seedSql);

        // Bring a fresh install to the same version as an upgraded install.
        foreach (glob(__DIR__ . '/database/migrations/*.sql') as $migration) {
            $pdo->exec(file_get_contents($migration));
        }

        $message = "Database '{$dbName}' installed and seeded successfully! Default Admin: <code>admin</code> / <code>password123</code>";
    } catch (Exception $e) {
        error_log('Setup failed: ' . $e->getMessage());
        $error = 'Installation failed. Check the server error log and database credentials.';
    }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Database Setup — <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/style.css">
    <style>
        body { background: #0B1020; min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 20px; }
        .setup-card { background: #151F36; border: 1px solid var(--border-color); border-radius: 16px; width: 100%; max-width: 500px; padding: 36px; }
    </style>
</head>
<body>
    <div class="setup-card">
        <div class="text-center mb-4">
            <div class="brand-icon mx-auto mb-2" style="width: 52px; height: 52px; font-size: 1.4rem;">MT</div>
            <h4 class="text-white fw-bold">Maruf Traders</h4>
            <div class="text-secondary small">Database Setup & Installer</div>
        </div>

        <?php if ($message): ?>
            <div class="alert alert-success py-3 text-center" style="background: rgba(34,197,94,0.15); border-color: rgba(34,197,94,0.3); color: #22C55E;">
                <i class="fa-solid fa-circle-check me-1"></i> <?php echo $message; ?>
                <div class="mt-3">
                    <a href="<?php echo BASE_URL; ?>/login.php" class="btn btn-primary-custom btn-sm">Proceed to Login</a>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-danger py-2 text-center" style="background: rgba(239,68,68,0.15); border-color: rgba(239,68,68,0.3); color: #EF4444;">
                <i class="fa-solid fa-circle-exclamation me-1"></i> <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="">
            <?php echo csrfField(); ?>
            <div class="row g-3 mb-3">
                <div class="col-8">
                    <label class="form-label-custom">DB Host</label>
                    <input type="text" name="db_host" class="form-control form-control-custom" value="localhost" required>
                </div>
                <div class="col-4">
                    <label class="form-label-custom">Port</label>
                    <input type="text" name="db_port" class="form-control form-control-custom" value="3306" required>
                </div>
            </div>

            <div class="mb-3">
                <label class="form-label-custom">Database Name</label>
                <input type="text" name="db_name" class="form-control form-control-custom" value="dealership_saas" required>
            </div>

            <div class="row g-3 mb-4">
                <div class="col-6">
                    <label class="form-label-custom">Username</label>
                    <input type="text" name="db_user" class="form-control form-control-custom" value="root" required>
                </div>
                <div class="col-6">
                    <label class="form-label-custom">Password</label>
                    <input type="password" name="db_pass" class="form-control form-control-custom" placeholder="(empty)">
                </div>
            </div>

            <button type="submit" class="btn btn-primary-custom w-100 justify-content-center py-2">
                Run Setup & Seed Database
            </button>
        </form>

        <div class="mt-4 pt-3 border-top border-secondary border-opacity-10 text-center text-muted small">
            For upgrades, back up first and run the versioned CLI migration command instead of this fresh-install tool.
        </div>
    </div>
</body>
</html>
