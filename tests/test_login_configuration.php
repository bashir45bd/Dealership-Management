<?php
/** Read-only smoke test for the configured database authentication record. */
define('APP_INIT', true);
require_once __DIR__ . '/../config/app.php';
$db = Database::getConnection();
$stmt = $db->prepare('SELECT username, password, tenant_id, status FROM users WHERE username = :username AND is_platform_admin = 0 LIMIT 1');
$stmt->execute([':username' => 'sales']);
$user = $stmt->fetch();
if (!$user || !password_verify('password123', $user['password']) || $user['status'] !== 'active' || (int)$user['tenant_id'] < 1) {
    fwrite(STDERR, "Configured login smoke test failed.\n");
    exit(1);
}
echo "PASS configured tenant-user authentication record and tenant assignment\n";
