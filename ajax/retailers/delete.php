<?php
/**
 * Maruf Traders - AJAX Deactivate/Delete Retailer
 */

define('APP_INIT', true);
define('IS_AJAX', true);
require_once __DIR__ . '/../../config/app.php';
require_once ROOT_PATH . '/includes/auth_check.php';
require_once ROOT_PATH . '/includes/permission_check.php';

requirePermission('retailers.delete');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(false, 'Invalid request method.', [], 405);
}

$csrfToken = $_POST['csrf_token'] ?? '';
if (!verifyCsrfToken($csrfToken)) {
    jsonResponse(false, 'Invalid CSRF token.', [], 403);
}

$id = (int)($_POST['id'] ?? 0);
if (!$id) {
    jsonResponse(false, 'Retailer ID is required.');
}

$db = Database::getConnection();
$tenantId = TenantContext::id();

try {
    $stmt = $db->prepare("SELECT * FROM retailers WHERE id = :id AND tenant_id = :tenant");
    $stmt->execute([':id' => $id, ':tenant' => $tenantId]);
    $retailer = $stmt->fetch();

    if (!$retailer) {
        jsonResponse(false, 'Retailer not found.');
    }

    // Toggle or deactivate
    $newStatus = $retailer['status'] === 'active' ? 'inactive' : 'active';
    $uStmt = $db->prepare("UPDATE retailers SET status = :status WHERE id = :id");
    $uStmt->execute([':status' => $newStatus, ':id' => $id]);

    logAudit('retailers', 'toggle_status', $retailer['retailer_code'], ['status' => $retailer['status']], ['status' => $newStatus]);

    jsonResponse(true, "Retailer status changed to {$newStatus}.", ['status' => $newStatus]);
} catch (Exception $e) {
    jsonResponse(false, 'Error updating status: ' . $e->getMessage(), [], 500);
}
