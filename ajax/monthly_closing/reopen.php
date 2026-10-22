<?php
/**
 * Maruf Traders - AJAX Reopen Accounting Month
 */

define('APP_INIT', true);
define('IS_AJAX', true);
require_once __DIR__ . '/../../config/app.php';
require_once ROOT_PATH . '/includes/auth_check.php';
require_once ROOT_PATH . '/includes/permission_check.php';

requirePermission('closing.manage');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(false, 'Invalid request method.', [], 405);
}

$csrfToken = $_POST['csrf_token'] ?? '';
if (!verifyCsrfToken($csrfToken)) {
    jsonResponse(false, 'Invalid CSRF token.', [], 403);
}

$month = (int)($_POST['month'] ?? 0);
$year = (int)($_POST['year'] ?? 0);
$reason = trim($_POST['reason'] ?? 'Reopened for adjustment');

if ($month < 1 || $month > 12 || $year < 2000) {
    jsonResponse(false, 'Invalid month or year.');
}

$db = Database::getConnection();

try {
    $db->beginTransaction();

    $stmt = $db->prepare("SELECT * FROM monthly_closings WHERE closing_month = :m AND closing_year = :y FOR UPDATE");
    $stmt->execute([':m' => $month, ':y' => $year]);
    $closing = $stmt->fetch();

    if (!$closing || $closing['status'] !== 'closed') {
        throw new Exception("This period is not currently closed.");
    }

    $userId = $_SESSION['user_id'] ?? 1;

    $uStmt = $db->prepare("UPDATE monthly_closings 
                           SET status = 'reopened', reopened_by = :uid, reopened_at = NOW() 
                           WHERE id = :id");
    $uStmt->execute([':uid' => $userId, ':id' => $closing['id']]);

    $monthName = date('F Y', mktime(0, 0, 0, $month, 10, $year));
    logAudit('monthly_closing', 'reopen_month', "{$month}/{$year}", $closing, ['reason' => $reason]);

    $db->commit();
    jsonResponse(true, "Accounting period for {$monthName} is now REOPENED. Modifications are allowed.");

} catch (Exception $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    jsonResponse(false, 'Failed to reopen month: ' . $e->getMessage(), [], 400);
}
