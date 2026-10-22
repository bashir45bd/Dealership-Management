<?php
/**
 * Maruf Traders - AJAX Save Operating Expense
 */

define('APP_INIT', true);
define('IS_AJAX', true);
require_once __DIR__ . '/../../config/app.php';
require_once ROOT_PATH . '/includes/auth_check.php';
require_once ROOT_PATH . '/includes/permission_check.php';

requirePermission('expenses.manage');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(false, 'Invalid request method.', [], 405);
}

$csrfToken = $_POST['csrf_token'] ?? '';
if (!verifyCsrfToken($csrfToken)) {
    jsonResponse(false, 'Invalid CSRF token.', [], 403);
}

$categoryId = (int)($_POST['category_id'] ?? 0);
$expenseDate = trim($_POST['expense_date'] ?? date('Y-m-d'));
$title = trim($_POST['title'] ?? '');
$amount = (float)($_POST['amount'] ?? 0.00);
$paymentMethod = trim($_POST['payment_method'] ?? 'Cash');
$paidTo = trim($_POST['paid_to'] ?? '');
$voucherNo = trim($_POST['voucher_no'] ?? '');
$notes = trim($_POST['notes'] ?? '');

if (!$categoryId) jsonResponse(false, 'Please select an Expense Category.');
if (empty($title)) jsonResponse(false, 'Expense title is required.');
if ($amount <= 0) jsonResponse(false, 'Expense amount must be greater than 0.');

$db = Database::getConnection();
$tenantId = TenantContext::id(); TenantContext::requireWritable();

try {
    assertMonthOpen($expenseDate);

    $db->beginTransaction();

    // Verify category belongs to tenant
    $cStmt = $db->prepare("SELECT id FROM expense_categories WHERE id = :cid AND tenant_id = :tenant");
    $cStmt->execute([':cid' => $categoryId, ':tenant' => $tenantId]);
    if (!$cStmt->fetch()) throw new Exception("Invalid expense category selected.");

    $expCode = generateCode('expenses', 'expense_code', PREFIX_EXPENSE, 5);

    $stmt = $db->prepare("INSERT INTO expenses 
        (tenant_id, expense_code, category_id, expense_date, title, amount, payment_method, paid_to, voucher_no, status, notes, created_by)
        VALUES (:tenant, :code, :cid, :edate, :title, :amt, :pmethod, :paidto, :vouch, 'active', :notes, :uid)");

    $stmt->execute([
        ':tenant'  => $tenantId,
        ':code'    => $expCode,
        ':cid'     => $categoryId,
        ':edate'   => $expenseDate,
        ':title'   => $title,
        ':amt'     => $amount,
        ':pmethod' => $paymentMethod,
        ':paidto'  => $paidTo,
        ':vouch'   => $voucherNo,
        ':notes'   => $notes,
        ':uid'     => $_SESSION['user_id'] ?? null
    ]);

    logAudit('expenses', 'create', $expCode, null, [
        'title' => $title, 'amount' => $amount, 'category_id' => $categoryId
    ]);

    $db->commit();
    jsonResponse(true, "Expense of " . formatBDT($amount) . " recorded successfully ({$expCode}).", ['code' => $expCode]);

} catch (Exception $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    jsonResponse(false, 'Expense entry failed: ' . $e->getMessage(), [], 400);
}
