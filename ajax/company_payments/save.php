<?php
/**
 * Maruf Traders - AJAX Save Company Payment
 */

define('APP_INIT', true);
define('IS_AJAX', true);
require_once __DIR__ . '/../../config/app.php';
require_once ROOT_PATH . '/includes/auth_check.php';
require_once ROOT_PATH . '/includes/permission_check.php';

requirePermission('company_payments.manage');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(false, 'Invalid request method.', [], 405);
}

$csrfToken = $_POST['csrf_token'] ?? '';
if (!verifyCsrfToken($csrfToken)) {
    jsonResponse(false, 'Invalid CSRF token.', [], 403);
}

$companyId = (int)($_POST['company_id'] ?? 0);
$paymentDate = trim($_POST['payment_date'] ?? date('Y-m-d'));
$amount = (float)($_POST['amount'] ?? 0.00);
$paymentMethod = trim($_POST['payment_method'] ?? 'Bank');
$referenceNo = trim($_POST['reference_no'] ?? '');
$bankName = trim($_POST['bank_name'] ?? '');
$notes = trim($_POST['notes'] ?? '');

if (!$companyId) jsonResponse(false, 'Please select a Company / Supplier.');
if ($amount <= 0) jsonResponse(false, 'Payment amount must be greater than 0.');

$db = Database::getConnection();
$tenantId = TenantContext::id(); TenantContext::requireWritable();

try {
    assertMonthOpen($paymentDate);

    $db->beginTransaction();

    $cStmt = $db->prepare("SELECT * FROM companies WHERE id = :id AND tenant_id = :tenant FOR UPDATE");
    $cStmt->execute([':id' => $companyId, ':tenant' => $tenantId]);
    $company = $cStmt->fetch();

    if (!$company) throw new Exception("Company not found.");

    $currentPayable = (float)$company['current_payable'];
    $allowOverpay = strtolower(getSetting('allow_company_overpayment', 'no')) === 'yes';

    if (!$allowOverpay && $amount > $currentPayable) {
        throw new Exception("Payment amount (৳" . number_format($amount, 2) . ") exceeds current payable balance (৳" . number_format($currentPayable, 2) . "). Overpayment is restricted in settings.");
    }

    $paymentCode = generateCode('company_payments', 'payment_code', PREFIX_COMPANY_PAYMENT, 5);

    // Insert Company Payment
    $pStmt = $db->prepare("INSERT INTO company_payments 
        (tenant_id, payment_code, company_id, payment_date, amount, payment_method, reference_no, bank_name, status, notes, created_by)
        VALUES (:tenant, :code, :cid, :pdate, :amt, :pmethod, :ref, :bank, 'active', :notes, :uid)");

    $pStmt->execute([
        ':tenant'  => $tenantId,
        ':code'    => $paymentCode,
        ':cid'     => $companyId,
        ':pdate'   => $paymentDate,
        ':amt'     => $amount,
        ':pmethod' => $paymentMethod,
        ':ref'     => $referenceNo,
        ':bank'    => $bankName,
        ':notes'   => $notes,
        ':uid'     => $_SESSION['user_id'] ?? null
    ]);

    // Update Company Current Payable
    $newPayable = $currentPayable - $amount;
    $uStmt = $db->prepare("UPDATE companies SET current_payable = :payable WHERE id = :id AND tenant_id = :tenant");
    $uStmt->execute([':payable' => $newPayable, ':id' => $companyId, ':tenant' => $tenantId]);

    // Record Company Ledger Entry
    $clStmt = $db->prepare("INSERT INTO company_ledger 
        (tenant_id, company_id, transaction_date, transaction_type, reference_id, description, debit, credit, running_balance, created_by)
        VALUES (:tenant, :cid, :tdate, 'PAYMENT', :ref, :desc, 0.00, :credit, :run_bal, :uid)");

    $clStmt->execute([
        ':tenant'  => $tenantId,
        ':cid'     => $companyId,
        ':tdate'   => $paymentDate,
        ':ref'     => $paymentCode,
        ':desc'    => "Company Payment via {$paymentMethod}" . ($referenceNo ? " (Ref: {$referenceNo})" : ""),
        ':credit'  => $amount,
        ':run_bal' => $newPayable,
        ':uid'     => $_SESSION['user_id'] ?? null
    ]);

    logAudit('company_payments', 'create', $paymentCode, null, [
        'company' => $company['name'], 'amount' => $amount, 'method' => $paymentMethod
    ]);

    $db->commit();
    jsonResponse(true, "Payment of " . formatBDT($amount) . " to {$company['name']} recorded successfully ({$paymentCode}).", ['code' => $paymentCode]);

} catch (Exception $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    jsonResponse(false, 'Company payment failed: ' . $e->getMessage(), [], 400);
}
