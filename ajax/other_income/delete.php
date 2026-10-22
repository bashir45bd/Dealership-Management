<?php
/**
 * Maruf Traders - AJAX Delete (soft) Other Income
 */

define('APP_INIT', true);
define('IS_AJAX', true);
require_once __DIR__ . '/../../config/app.php';
require_once ROOT_PATH . '/includes/auth_check.php';
require_once ROOT_PATH . '/includes/permission_check.php';

requirePermission('other_income.manage');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(false, 'Invalid request method.', [], 405);
}

$csrfToken = $_POST['csrf_token'] ?? '';
if (!verifyCsrfToken($csrfToken)) {
    jsonResponse(false, 'Invalid security token (CSRF).', [], 403);
}

$id = (int)($_POST['id'] ?? 0);
if (!$id) {
    jsonResponse(false, 'Invalid record ID.');
}

$db = Database::getConnection();

try {
    $stmt = $db->prepare("SELECT * FROM other_incomes WHERE id = :id AND status = 'active'");
    $stmt->execute([':id' => $id]);
    $income = $stmt->fetch();

    if (!$income) {
        jsonResponse(false, 'Record not found or already deleted.', [], 404);
    }

    $uStmt = $db->prepare("UPDATE other_incomes SET status = 'deleted' WHERE id = :id");
    $uStmt->execute([':id' => $id]);

    logAudit('other_incomes', 'delete', (string)$id, $income, ['status' => 'deleted']);

    jsonResponse(true, 'Income entry deleted and removed from Net Profit.');

} catch (Exception $e) {
    jsonResponse(false, 'Failed to delete income entry: ' . $e->getMessage(), [], 500);
}