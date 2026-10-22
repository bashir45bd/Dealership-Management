<?php
/**
 * Maruf Traders - AJAX Get Single Company Details
 */

define('APP_INIT', true);
define('IS_AJAX', true);
require_once __DIR__ . '/../../config/app.php';
require_once ROOT_PATH . '/includes/auth_check.php';
require_once ROOT_PATH . '/includes/permission_check.php';

requirePermission('companies.manage');

$id = (int)($_GET['id'] ?? 0);
if (!$id) {
    jsonResponse(false, 'Company ID is required.');
}

$db = Database::getConnection();
$stmt = $db->prepare("SELECT * FROM companies WHERE id = :id AND tenant_id = :tenant LIMIT 1");
$stmt->execute([':id' => $id, ':tenant' => TenantContext::id()]);
$company = $stmt->fetch();

if (!$company) {
    jsonResponse(false, 'Company not found.');
}

jsonResponse(true, 'Company retrieved.', ['company' => $company]);
