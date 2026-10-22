<?php
/**
 * Maruf Traders - AJAX Save Customer Advance Deposit
 */

define('APP_INIT', true);
define('IS_AJAX', true);
require_once __DIR__ . '/../../config/app.php';
require_once ROOT_PATH . '/includes/auth_check.php';
require_once ROOT_PATH . '/includes/permission_check.php';

requirePermission('advances.manage');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(false, 'Invalid request method.', [], 405);
}

$csrfToken = $_POST['csrf_token'] ?? '';
if (!verifyCsrfToken($csrfToken)) {
    jsonResponse(false, 'Invalid CSRF token.', [], 403);
}

$retailerId = (int)($_POST['retailer_id'] ?? 0);
$advanceDate = trim($_POST['advance_date'] ?? date('Y-m-d'));
$amount = (float)($_POST['amount'] ?? 0.00);
$paymentMethod = trim($_POST['payment_method'] ?? 'Cash');
$transactionRef = trim($_POST['transaction_ref'] ?? '');
$notes = trim($_POST['notes'] ?? '');

if (!$retailerId) jsonResponse(false, 'Please select a Retailer.');
if ($amount <= 0) jsonResponse(false, 'Advance deposit amount must be greater than 0.');

$db = Database::getConnection();
$tenantId = TenantContext::id(); TenantContext::requireWritable();

try {
    assertMonthOpen($advanceDate);

    $db->beginTransaction();

    $rStmt = $db->prepare("SELECT * FROM retailers WHERE id = :id AND tenant_id = :tenant FOR UPDATE");
    $rStmt->execute([':id' => $retailerId, ':tenant' => $tenantId]);
    $retailer = $rStmt->fetch();

    if (!$retailer) throw new Exception("Retailer not found.");

    // Generate Advance Code
    $advCode = generateCode('advances', 'advance_code', PREFIX_ADVANCE, 5);

    // Insert Advance Record
    $aStmt = $db->prepare("INSERT INTO advances 
        (tenant_id, advance_code, retailer_id, advance_date, amount, used_amount, remaining_amount, payment_method, transaction_ref, status, notes, created_by)
        VALUES (:tenant, :code, :rid, :adate, :amt, 0.00, :rem, :pmethod, :txref, 'active', :notes, :uid)");

    $aStmt->execute([
        ':tenant'  => $tenantId,
        ':code'    => $advCode,
        ':rid'     => $retailerId,
        ':adate'   => $advanceDate,
        ':amt'     => $amount,
        ':rem'     => $amount,
        ':pmethod' => $paymentMethod,
        ':txref'   => $transactionRef,
        ':notes'   => $notes,
        ':uid'     => $_SESSION['user_id'] ?? null
    ]);

    // Update Retailer Advance Balance
    $newAdvBal = (float)$retailer['advance_balance'] + $amount;
    $uStmt = $db->prepare("UPDATE retailers SET advance_balance = :adv WHERE id = :id AND tenant_id = :tenant");
    $uStmt->execute([':adv' => $newAdvBal, ':id' => $retailerId, ':tenant' => $tenantId]);

    // Insert Retailer Ledger Record
    $rlStmt = $db->prepare("INSERT INTO retailer_ledger 
        (tenant_id, retailer_id, transaction_date, transaction_type, reference_id, description, debit, credit, running_balance, created_by)
        VALUES (:tenant, :rid, :tdate, 'ADVANCE', :ref, :desc, 0.00, :credit, :run_bal, :uid)");

    $runningBal = (float)$retailer['current_due'] - $amount;
    $rlStmt->execute([
        ':tenant'  => $tenantId,
        ':rid'     => $retailerId,
        ':tdate'   => $advanceDate,
        ':ref'     => $advCode,
        ':desc'    => "Advance Deposit via {$paymentMethod}" . ($transactionRef ? " (Ref: {$transactionRef})" : ""),
        ':credit'  => $amount,
        ':run_bal' => $runningBal,
        ':uid'     => $_SESSION['user_id'] ?? null
    ]);

    logAudit('advances', 'create', $advCode, null, [
        'retailer' => $retailer['name'], 'amount' => $amount, 'method' => $paymentMethod
    ]);

    $db->commit();
    jsonResponse(true, "Advance deposit of " . formatBDT($amount) . " added ({$advCode}). Customer advance balance updated.", ['code' => $advCode]);

} catch (Exception $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    jsonResponse(false, 'Advance deposit failed: ' . $e->getMessage(), [], 400);
}
