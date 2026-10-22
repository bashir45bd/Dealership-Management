<?php
/**
 * AJAX Save Commission Reconciliation
 */

define('APP_INIT', true);
define('IS_AJAX', true);
require_once __DIR__ . '/../../config/app.php';
require_once ROOT_PATH . '/includes/auth_check.php';
require_once ROOT_PATH . '/includes/permission_check.php';

requirePermission('commission.manage');

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verifyCsrfToken($_POST['csrf_token'] ?? '')) {
    jsonResponse(false, 'Invalid request.', [], 403);
}

$tenantId = TenantContext::id();
TenantContext::requireWritable();
$db = Database::getConnection();

$companyId = (int)($_POST['company_id'] ?? 0);
$periodStart = trim($_POST['period_start'] ?? '');
$periodEnd = trim($_POST['period_end'] ?? '');
$statementAmount = (float)($_POST['statement_amount'] ?? 0.0);
$referenceNo = trim($_POST['reference_no'] ?? '');
$notes = trim($_POST['notes'] ?? '');

if (!$companyId || empty($periodStart) || empty($periodEnd)) {
    jsonResponse(false, 'Company and period dates are required.', [], 422);
}

try {
    $db->beginTransaction();

    // 1. Calculate system commission for this company and period
    $cStmt = $db->prepare("SELECT COALESCE(SUM(amount), 0) FROM commission_calculations 
                           WHERE tenant_id = :tenant AND company_id = :cid 
                           AND period_start >= :start AND period_end <= :end");
    $cStmt->execute([':tenant' => $tenantId, ':cid' => $companyId, ':start' => $periodStart, ':end' => $periodEnd]);
    $calculatedAmount = (float)$cStmt->fetchColumn();

    $adjustmentAmount = round($statementAmount - $calculatedAmount, 2);
    $finalAmount = $statementAmount;

    // 2. Insert or update commission_settlements
    $sStmt = $db->prepare("INSERT INTO commission_settlements 
        (tenant_id, company_id, period_start, period_end, calculated_amount, statement_amount, adjustment_amount, final_amount, reference_no, status, notes, created_by)
        VALUES (:tenant, :cid, :pstart, :pend, :calc, :stmt_amt, :adj, :final_amt, :ref, 'approved', :notes, :uid)");
    
    $sStmt->execute([
        ':tenant'    => $tenantId,
        ':cid'       => $companyId,
        ':pstart'    => $periodStart,
        ':pend'      => $periodEnd,
        ':calc'      => $calculatedAmount,
        ':stmt_amt'  => $statementAmount,
        ':adj'       => $adjustmentAmount,
        ':final_amt' => $finalAmount,
        ':ref'       => $referenceNo,
        ':notes'     => $notes,
        ':uid'       => $_SESSION['user_id'] ?? null
    ]);
    $settlementId = (int)$db->lastInsertId();

    // 3. Post to commission_ledger
    $lastBalStmt = $db->prepare("SELECT running_balance FROM commission_ledger WHERE tenant_id = :tenant AND company_id = :cid ORDER BY id DESC LIMIT 1");
    $lastBalStmt->execute([':tenant' => $tenantId, ':cid' => $companyId]);
    $prevBal = (float)($lastBalStmt->fetchColumn() ?: 0);
    $newBal = $prevBal + $finalAmount;

    $lStmt = $db->prepare("INSERT INTO commission_ledger 
        (tenant_id, company_id, settlement_id, entry_date, entry_type, debit, credit, running_balance, reference_no, description)
        VALUES (:tenant, :cid, :sid, :edate, 'approval', :debit, 0.00, :run_bal, :ref, :desc)");
    $lStmt->execute([
        ':tenant'  => $tenantId,
        ':cid'     => $companyId,
        ':sid'     => $settlementId,
        ':edate'   => date('Y-m-d'),
        ':debit'   => $finalAmount,
        ':run_bal' => $newBal,
        ':ref'     => $referenceNo,
        ':desc'    => "Reconciled Commission Statement ({$periodStart} to {$periodEnd})"
    ]);

    $db->commit();
    jsonResponse(true, "Commission statement reconciled. Final approved amount: ৳" . number_format($finalAmount, 2), ['id' => $settlementId]);

} catch (Exception $e) {
    if ($db->inTransaction()) $db->rollBack();
    jsonResponse(false, 'Reconciliation failed: ' . $e->getMessage(), [], 400);
}
