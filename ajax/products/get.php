<?php
/**
 * Maruf Traders - AJAX Get Single Product Details
 */

define('APP_INIT', true);
define('IS_AJAX', true);
require_once __DIR__ . '/../../config/app.php';
require_once ROOT_PATH . '/includes/auth_check.php';
require_once ROOT_PATH . '/includes/permission_check.php';

$id = (int)($_GET['id'] ?? 0);
if (!$id) {
    jsonResponse(false, 'Product ID is required.');
}

$db = Database::getConnection();
$stmt = $db->prepare("SELECT p.*, c.name as company_name 
                      FROM products p 
                      JOIN companies c ON p.company_id = c.id 
                      WHERE p.id = :id AND p.tenant_id = :tenant LIMIT 1");
$stmt->execute([':id' => $id, ':tenant' => TenantContext::id()]);
$product = $stmt->fetch();

if (!$product) {
    jsonResponse(false, 'Product not found.');
}

jsonResponse(true, 'Product details retrieved.', ['product' => $product]);
