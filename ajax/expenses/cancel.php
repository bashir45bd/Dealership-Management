<?php
/**
 * Maruf Traders - AJAX Cancel / Void Expense Voucher
 *
 * Mirrors ajax/sales/cancel.php's pattern (row lock, already-cancelled guard,
 * assertMonthOpen, audit log, admin-only permission).
 *
 * Simplest of the cancel flows: an expense voucher is a standalone record
 * (per expenses table) with no retailer, company, stock, or ledger impact
 * anywhere in the schema/reports -- it only ever appears as a simple SUM()
 * filtered on status = 'active' (see report_monthly.php / report_yearly.php).
 * So cancelling it is just a status flip; once it's 'cancelled', those SUM()
 * queries automatically exclude it and Net Profit corrects itself on the
 * next page load. No balances to reverse.
 */

define('APP_INIT', true);
define('IS_AJAX', true);
require_once __DIR__ . '/../../config/app.php';
require_once ROOT_PATH . '/includes/auth_check.php';
require_once ROOT_PATH . '/includes/permission_check.php';

requirePermission('expenses.cancel'); // Admin / Super Admin only — same pattern as sales.cancel

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(false, 'Invalid request method.', [], 405);
}

$csrfToken = $_POST['csrf_token'] ?? '';
if (!verifyCsrfToken($csrfToken)) {
    jsonResponse(false, 'Invalid CSRF token.', [], 403);
}

$expenseId = (int)($_POST['id'] ?? 0);
$reason = trim($_POST['reason'] ?? 'Expense voucher cancelled');

if (!$expenseId) {
    jsonResponse(false, 'Expense ID is required.');
}

$db = Database::getConnection();

try {
    $db->beginTransaction();

    $stmt = $db->prepare("SELECT * FROM expenses WHERE id = :id FOR UPDATE");
    $stmt->execute([':id' => $expenseId]);
    $expense = $stmt->fetch();

    if (!$expense) throw new Exception("Expense voucher not found.");
    if ($expense['status'] === 'cancelled') throw new Exception("This expense voucher is already cancelled.");

    // Assert Month is open (based on the expense's own date)
    assertMonthOpen($expense['expense_date']);

    // Mark Expense as Cancelled
    $canExpStmt = $db->prepare("UPDATE expenses 
                                SET status = 'cancelled', cancel_reason = :reason, cancelled_by = :uid, cancelled_at = NOW() 
                                WHERE id = :id");
    $canExpStmt->execute([
        ':reason' => $reason,
        ':uid'    => $_SESSION['user_id'] ?? null,
        ':id'     => $expenseId
    ]);

    logAudit('expenses', 'cancel', $expense['expense_code'], $expense, ['reason' => $reason]);

    $db->commit();
    jsonResponse(true, "Expense voucher {$expense['expense_code']} (" . formatBDT($expense['amount']) . ") has been successfully cancelled and excluded from profit reports.");

} catch (Exception $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    jsonResponse(false, 'Cancellation failed: ' . $e->getMessage(), [], 400);
}