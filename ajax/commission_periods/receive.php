<?php
/**
 * Maruf Traders - AJAX Mark Period Commission as Received
 *
 * Freezes the final bags/amount at the moment of receipt and stamps
 * received_date = today. This received_date is what the P&L report uses
 * to include the amount in that month's Net Profit - so whichever month
 * this button is clicked in, that's the month the income lands in.
 */

define('APP_INIT', true);
define('IS_AJAX', true);
require_once __DIR__ . '/../../config/app.php';
require_once ROOT_PATH . '/includes/auth_check.php';
require_once ROOT_PATH . '/includes/permission_check.php';

requirePermission('commission_periods.manage');

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
    $db->beginTransaction();

    $stmt = $db->prepare("SELECT * FROM company_period_commissions WHERE id = :id FOR UPDATE");
    $stmt->execute([':id' => $id]);
    $period = $stmt->fetch();

    if (!$period) {
        $db->rollBack();
        jsonResponse(false, 'Record not found.', [], 404);
    }

    if ($period['status'] === 'received') {
        $db->rollBack();
        jsonResponse(false, 'This period commission has already been marked as received.');
    }

    // Final live recalculation before freezing the amount
    $bagsStmt = $db->prepare(
        "SELECT COALESCE(SUM(si.quantity), 0) AS total_qty
         FROM sale_items si
         JOIN sales s ON si.sale_id = s.id
         WHERE si.company_id = :cid
           AND s.status = 'active'
           AND s.sale_date BETWEEN :start AND :end"
    );
    $bagsStmt->execute([
        ':cid'   => $period['company_id'],
        ':start' => $period['start_date'],
        ':end'   => $period['end_date']
    ]);
    $finalBags = (int)$bagsStmt->fetchColumn();
    $finalAmount = round($finalBags * (float)$period['rate_per_bag'], 2);

    $uStmt = $db->prepare(
        "UPDATE company_period_commissions
         SET total_bags = :bags,
             total_amount = :amt,
             status = 'received',
             received_date = CURDATE(),
             received_by = :uid
         WHERE id = :id"
    );
    $uStmt->execute([
        ':bags' => $finalBags,
        ':amt'  => $finalAmount,
        ':uid'  => $_SESSION['user_id'] ?? null,
        ':id'   => $id
    ]);

    logAudit('commission_periods', 'mark_received', (string)$id, $period, [
        'total_bags' => $finalBags,
        'total_amount' => $finalAmount,
        'received_date' => date('Y-m-d')
    ]);

    $db->commit();

    jsonResponse(true, 'Marked as received. ' . formatBDT($finalAmount) . ' added to this month\'s Net Profit.', [
        'total_bags' => $finalBags,
        'total_amount' => $finalAmount
    ]);

} catch (Exception $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    jsonResponse(false, 'Failed to mark as received: ' . $e->getMessage(), [], 500);
}