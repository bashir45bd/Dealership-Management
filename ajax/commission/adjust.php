<?php
/**
 * AJAX Save Commission Adjustment
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

$settlementId = (int)($_POST['settlement_id'] ?? 0);
$companyId = (int)($_POST['company_id'] ?? 0);
$periodStart = trim($_POST['period_start'] ?? date('Y-m-01'));
$periodEnd = trim($_POST['period_end'] ?? date('Y-m-t'));
$amount = (float)($_POST['amount'] ?? 0.0);
$description = trim($_POST['description'] ?? '');

if (!$companyId || $amount == 0 || empty($description)) {
    jsonResponse(false, 'Company, non-zero adjustment amount, and reason are required.', [], 422);
}

try {
    $db->beginTransaction();

    // 1. Insert commission_adjustments
    $aStmt = $db->prepare("INSERT INTO commission_adjustments 
        (tenant_id, company_id, period_start, period_end, amount, description, status, created_by)
        VALUES (:tenant, :cid, :pstart, :pend, :amt, :desc, 'approved', :uid)");
    $aStmt->execute([
        ':tenant' => $tenantId,
        ':cid'    => $companyId,
        ':pstart' => $periodStart,
        ':pend'   => $periodEnd,
        ':amt'    => $amount,
        ':desc'   => $description,
        ':uid'    => $_SESSION['user_id'] ?? null
    ]);
    $adjustmentId = (int)$db->lastInsertId();

    // 2. If linked to settlement, update settlement
    if ($settlementId > 0) {
        $updSet = $db->prepare("UPDATE commission_settlements 
                                SET adjustment_amount = adjustment_amount + :amt, 
                                    final_amount = final_amount + :amt 
                                WHERE id = :sid AND tenant_id = :tenant");
        $updSet->execute([':amt' => $amount, ':sid' => $settlementId, ':tenant' => $tenantId]);
    }

    // 3. Post to commission_ledger
    $lastBalStmt = $db->prepare("SELECT running_balance FROM commission_ledger WHERE tenant_id = :tenant AND company_id = :cid ORDER BY id DESC LIMIT 1");
    $lastBalStmt->execute([':tenant' => $tenantId, ':cid' => $companyId]);
    $prevBal = (float)($lastBalStmt->fetchColumn() ?: 0);
    $newBal = $prevBal + $amount;

    $debit = $amount > 0 ? $amount : 0.00;
    $credit = $amount < 0 ? abs($amount) : 0.00;

    $lStmt = $db->prepare("INSERT INTO commission_ledger 
        (tenant_id, company_id, adjustment_id, settlement_id, entry_date, entry_type, debit, credit, running_balance, description)
        VALUES (:tenant, :cid, :aid, :sid, :edate, 'adjustment', :debit, :credit, :run_bal, :desc)");
    $lStmt->execute([
        ':tenant'  => $tenantId,
        ':cid'     => $companyId,
        ':aid'     => $adjustmentId,
        ':sid'     => $settlementId ?: null,
        ':edate'   => date('Y-m-d'),
        ':debit'   => $debit,
        ':credit'  => $credit,
        ':run_bal' => $newBal,
        ':desc'    => "Commission Adjustment: {$description}"
    ]);

    $db->commit();
    jsonResponse(true, "Adjustment of ৳" . number_format($amount, 2) . " applied successfully.", ['id' => $adjustmentId]);

} catch (Exception $e) {
    if ($db->inTransaction()) $db->rollBack();
    jsonResponse(false, 'Adjustment failed: ' . $e->getMessage(), [], 400);
}
