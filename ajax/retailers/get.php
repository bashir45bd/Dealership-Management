<?php
/**
 * Maruf Traders - AJAX Get Single Retailer Details
 */

define('APP_INIT', true);
define('IS_AJAX', true);
require_once __DIR__ . '/../../config/app.php';
require_once ROOT_PATH . '/includes/auth_check.php';
require_once ROOT_PATH . '/includes/permission_check.php';

requirePermission('retailers.manage');

$id = (int)($_GET['id'] ?? 0);
if (!$id) {
    jsonResponse(false, 'Retailer ID is required.');
}

$db = Database::getConnection();
$stmt = $db->prepare("SELECT * FROM retailers WHERE id = :id AND tenant_id = :tenant LIMIT 1");
$stmt->execute([':id' => $id, ':tenant' => TenantContext::id()]);
$retailer = $stmt->fetch();

if (!$retailer) {
    jsonResponse(false, 'Retailer not found.');
}

jsonResponse(true, 'Retailer retrieved.', ['retailer' => $retailer]);
