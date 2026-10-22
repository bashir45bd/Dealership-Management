<?php
/**
 * Maruf Traders - AJAX Get Sales Invoice Details
 */

define('APP_INIT', true);
define('IS_AJAX', true);
require_once __DIR__ . '/../../config/app.php';
require_once ROOT_PATH . '/includes/auth_check.php';
require_once ROOT_PATH . '/includes/permission_check.php';

requirePermission('sales.view');

$id = (int)($_GET['id'] ?? 0);
if (!$id) jsonResponse(false, 'Sale ID is required.');

$db = Database::getConnection();
$tenantId = TenantContext::id();

// Fetch Sale
$stmt = $db->prepare("SELECT s.*, r.retailer_code, r.name as retailer_name, r.mobile as retailer_mobile, r.address as retailer_address, u.name as creator_name 
                      FROM sales s
                      JOIN retailers r ON s.retailer_id = r.id
                      LEFT JOIN users u ON s.created_by = u.id
                      WHERE s.id = :id AND s.tenant_id = :tenant LIMIT 1");
$stmt->execute([':id' => $id, ':tenant' => $tenantId]);
$sale = $stmt->fetch();

if (!$sale) jsonResponse(false, 'Sales invoice not found.');

// Fetch Items
$iStmt = $db->prepare("SELECT si.*, p.name as product_name, p.brand, p.unit, p.bag_size_kg, c.name as company_name 
                       FROM sale_items si
                       JOIN products p ON si.product_id = p.id
                       JOIN companies c ON si.company_id = c.id
                       WHERE si.sale_id = :sid AND si.tenant_id = :tenant");
$iStmt->execute([':sid' => $id, ':tenant' => $tenantId]);
$items = $iStmt->fetchAll();

jsonResponse(true, 'Sale details loaded.', [
    'sale'  => $sale,
    'items' => $items
]);
