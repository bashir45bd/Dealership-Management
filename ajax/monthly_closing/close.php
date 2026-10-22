<?php
/**
 * Maruf Traders - AJAX Close Accounting Month
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
$notes = trim($_POST['notes'] ?? 'Monthly accounts verified and locked');

if ($month < 1 || $month > 12 || $year < 2000 || $year > 2100) {
    jsonResponse(false, 'Please specify a valid Month and Year.');
}

$db = Database::getConnection();

try {
    $db->beginTransaction();

    $stmt = $db->prepare("SELECT * FROM monthly_closings WHERE closing_month = :m AND closing_year = :y FOR UPDATE");
    $stmt->execute([':m' => $month, ':y' => $year]);
    $existing = $stmt->fetch();

    $userId = $_SESSION['user_id'] ?? 1;

    if ($existing) {
        $uStmt = $db->prepare("UPDATE monthly_closings 
                               SET status = 'closed', closed_by = :uid, closed_at = NOW(), closing_notes = :notes 
                               WHERE id = :id");
        $uStmt->execute([
            ':uid'   => $userId,
            ':notes' => $notes,
            ':id'    => $existing['id']
        ]);
    } else {
        $insStmt = $db->prepare("INSERT INTO monthly_closings 
            (closing_month, closing_year, status, closed_by, closed_at, closing_notes)
            VALUES (:m, :y, 'closed', :uid, NOW(), :notes)");
        $insStmt->execute([
            ':m'     => $month,
            ':y'     => $year,
            ':uid'   => $userId,
            ':notes' => $notes
        ]);
    }

    $monthName = date('F Y', mktime(0, 0, 0, $month, 10, $year));
    logAudit('monthly_closing', 'close_month', "{$month}/{$year}", null, ['month' => $month, 'year' => $year, 'notes' => $notes]);

    $db->commit();
    jsonResponse(true, "Accounting period for {$monthName} is now CLOSED. All financial transactions are locked.");

} catch (Exception $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    jsonResponse(false, 'Failed to close month: ' . $e->getMessage(), [], 400);
}
