<?php
/**
 * Maruf Traders - AJAX Get Retailers List
 */

define('APP_INIT', true);
define('IS_AJAX', true);
require_once __DIR__ . '/../../config/app.php';
require_once ROOT_PATH . '/includes/auth_check.php';
require_once ROOT_PATH . '/includes/permission_check.php';

requirePermission('retailers.manage');

$search = trim($_GET['search'] ?? '');
$status = trim($_GET['status'] ?? '');

$db = Database::getConnection();

$sql = "SELECT id, retailer_code, name, mobile, address, opening_balance, advance_balance, current_due, credit_limit, status, created_at 
        FROM retailers 
        WHERE tenant_id = :tenant";
$params = [':tenant' => TenantContext::id()];

if (!empty($search)) {
    $sql .= " AND (name LIKE :s1 OR retailer_code LIKE :s2 OR mobile LIKE :s3 OR address LIKE :s4)";
    $params[':s1'] = "%{$search}%";
    $params[':s2'] = "%{$search}%";
    $params[':s3'] = "%{$search}%";
    $params[':s4'] = "%{$search}%";
}

if (!empty($status)) {
    $sql .= " AND status = :st";
    $params[':st'] = $status;
}

$sql .= " ORDER BY id DESC";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$retailers = $stmt->fetchAll();

jsonResponse(true, 'Retailers loaded successfully.', ['retailers' => $retailers]);
