<?php
/**
 * Maruf Traders - AJAX Get Companies List
 */

define('APP_INIT', true);
define('IS_AJAX', true);
require_once __DIR__ . '/../../config/app.php';
require_once ROOT_PATH . '/includes/auth_check.php';
require_once ROOT_PATH . '/includes/permission_check.php';

requirePermission('companies.manage');

$db = Database::getConnection();

$stmt = $db->prepare("SELECT id, company_code, name, contact_person, mobile, email, address, opening_payable, current_payable, status
                      FROM companies WHERE tenant_id = :tenant ORDER BY id DESC");
$stmt->execute([':tenant' => TenantContext::id()]);
$companies = $stmt->fetchAll();

jsonResponse(true, 'Companies loaded.', ['companies' => $companies]);
