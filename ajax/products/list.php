<?php
/**
 * Maruf Traders - AJAX Get Products List
 */

define('APP_INIT', true);
define('IS_AJAX', true);
require_once __DIR__ . '/../../config/app.php';
require_once ROOT_PATH . '/includes/auth_check.php';
require_once ROOT_PATH . '/includes/permission_check.php';

requirePermission('products.manage');

$companyId = (int)($_GET['company_id'] ?? 0);
$status = trim($_GET['status'] ?? '');

$db = Database::getConnection();

$sql = "SELECT p.*, c.name as company_name 
        FROM products p
        JOIN companies c ON p.company_id = c.id
        WHERE p.tenant_id = :tenant";
$params = [':tenant' => TenantContext::id()];

if ($companyId) {
    $sql .= " AND p.company_id = :cid";
    $params[':cid'] = $companyId;
}

if (!empty($status)) {
    $sql .= " AND p.status = :st";
    $params[':st'] = $status;
}

$sql .= " ORDER BY p.id DESC";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$products = $stmt->fetchAll();

jsonResponse(true, 'Products loaded.', ['products' => $products]);
