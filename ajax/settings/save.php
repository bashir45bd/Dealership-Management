<?php
/**
 * Maruf Traders - AJAX Save Business & System Settings
 */

define('APP_INIT', true);
define('IS_AJAX', true);
require_once __DIR__ . '/../../config/app.php';
require_once ROOT_PATH . '/includes/auth_check.php';
require_once ROOT_PATH . '/includes/permission_check.php';

requirePermission('settings.manage');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(false, 'Invalid request method.', [], 405);
}

$csrfToken = $_POST['csrf_token'] ?? '';
if (!verifyCsrfToken($csrfToken)) {
    jsonResponse(false, 'Invalid CSRF token.', [], 403);
}

$allowedKeys = [
    'business_name', 'business_title', 'business_address', 'business_mobile', 
    'business_email', 'currency_symbol', 'currency_code', 'default_payment_method', 
    'payment_methods', 'default_credit_limit', 'allow_negative_stock', 
    'low_stock_threshold', 'default_cement_unit', 'allow_company_overpayment', 
    'commission_calculation_mode', 'session_timeout_minutes'
];

$db = Database::getConnection();

try {
    $db->beginTransaction();

    $updatedKeys = [];
    foreach ($allowedKeys as $key) {
        if (isset($_POST[$key])) {
            $val = trim($_POST[$key]);
            updateSetting($key, $val);
            $updatedKeys[$key] = $val;
        }
    }

    logAudit('settings', 'update', 'business_settings', null, $updatedKeys);

    $db->commit();
    jsonResponse(true, 'Business & system settings saved successfully.');

} catch (Exception $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    jsonResponse(false, 'Failed to save settings: ' . $e->getMessage(), [], 400);
}
