<?php
/**
 * Maruf Traders - AJAX Cancel / Void Sales Invoice
 */

define('APP_INIT', true);
define('IS_AJAX', true);
require_once __DIR__ . '/../../config/app.php';
require_once ROOT_PATH . '/includes/auth_check.php';
require_once ROOT_PATH . '/includes/permission_check.php';

requirePermission('sales.cancel');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(false, 'Invalid request method.', [], 405);
}

$csrfToken = $_POST['csrf_token'] ?? '';
if (!verifyCsrfToken($csrfToken)) {
    jsonResponse(false, 'Invalid CSRF token.', [], 403);
}

$saleId = (int)($_POST['id'] ?? 0);
$reason = trim($_POST['reason'] ?? 'Invoice cancelled');

if (!$saleId) {
    jsonResponse(false, 'Sale ID is required.');
}

$db = Database::getConnection();
$tenantId = TenantContext::id();
TenantContext::requireWritable();

try {
    $db->beginTransaction();

    $stmt = $db->prepare("SELECT * FROM sales WHERE id = :id AND tenant_id = :tenant FOR UPDATE");
    $stmt->execute([':id' => $saleId, ':tenant' => $tenantId]);
    $sale = $stmt->fetch();

    if (!$sale) throw new Exception("Sales invoice not found.");
    if ($sale['status'] === 'cancelled') throw new Exception("This invoice is already cancelled.");

    // Assert Month is open
    assertMonthOpen($sale['sale_date']);

    // Fetch Sale Items
    $siStmt = $db->prepare("SELECT * FROM sale_items WHERE sale_id = :sid AND tenant_id = :tenant");
    $siStmt->execute([':sid' => $saleId, ':tenant' => $tenantId]);
    $items = $siStmt->fetchAll();

    $affectedCompanies = [];

    // 1. Restore Stock & Record Stock Ledger
    $uStock = $db->prepare("UPDATE products SET current_stock = current_stock + :qty WHERE id = :id AND tenant_id = :tenant");
    $pStockStmt = $db->prepare("SELECT current_stock FROM products WHERE id = :id AND tenant_id = :tenant");
    
    $slStmt = $db->prepare("INSERT INTO stock_ledger 
        (tenant_id, product_id, company_id, transaction_date, transaction_type, reference_id, stock_in, stock_out, running_balance, notes, created_by)
        VALUES (:tenant, :pid, :cid, CURDATE(), 'SALE_CANCEL', :ref, :sin, 0, :run_bal, :notes, :uid)");

    foreach ($items as $item) {
        $uStock->execute([':qty' => $item['quantity'], ':id' => $item['product_id'], ':tenant' => $tenantId]);
        
        $pStockStmt->execute([':id' => $item['product_id'], ':tenant' => $tenantId]);
        $newProdStock = (int)$pStockStmt->fetchColumn();

        $slStmt->execute([
            ':tenant'  => $tenantId, ':pid'     => $item['product_id'],
            ':cid'     => $item['company_id'],
            ':ref'     => 'REV-' . $sale['invoice_no'],
            ':sin'     => $item['quantity'],
            ':run_bal' => $newProdStock,
            ':notes'   => "Sale cancelled: {$reason}",
            ':uid'     => $_SESSION['user_id'] ?? null
        ]);

        $affectedCompanies[(int)$item['company_id']] = true;
    }

    // 2. Reverse Retailer Balances & Ledger
    $rStmt = $db->prepare("SELECT * FROM retailers WHERE id = :id AND tenant_id = :tenant FOR UPDATE");
    $rStmt->execute([':id' => $sale['retailer_id'], ':tenant' => $tenantId]);
    $retailer = $rStmt->fetch();

    $dueToRevert = (float)$sale['due_amount'];
    $advToRevert = (float)$sale['advance_deducted'];

    $newDue = max(0, (float)$retailer['current_due'] - $dueToRevert);
    $newAdv = (float)$retailer['advance_balance'] + $advToRevert;

    $uRet = $db->prepare("UPDATE retailers SET current_due = :due, advance_balance = :adv WHERE id = :id");
    $uRet->execute([':due' => $newDue, ':adv' => $newAdv, ':id' => $retailer['id']]);

    // Insert Retailer Ledger Reversal Entry
    $rlStmt = $db->prepare("INSERT INTO retailer_ledger 
        (tenant_id, retailer_id, transaction_date, transaction_type, reference_id, description, debit, credit, running_balance, created_by)
        VALUES (:tenant, :rid, CURDATE(), 'SALE_CANCEL', :ref, :desc, 0.00, :credit, :run_bal, :uid)");

    $rlStmt->execute([
        ':tenant'  => $tenantId, ':rid'     => $retailer['id'],
        ':ref'     => 'REV-' . $sale['invoice_no'],
        ':desc'    => "Sale cancelled: {$reason} (Due reversed: ৳" . number_format($dueToRevert, 2) . ")",
        ':credit'  => $dueToRevert,
        ':run_bal' => $newDue,
        ':uid'     => $_SESSION['user_id'] ?? null
    ]);

    // 3. Mark any collections generated with this sale as cancelled
    $cCanStmt = $db->prepare("UPDATE collections 
                             SET status = 'cancelled', cancel_reason = :reason, cancelled_by = :uid, cancelled_at = NOW() 
                             WHERE sale_id = :sid AND tenant_id = :tenant AND status = 'active'");
    $cCanStmt->execute([
        ':reason' => "Parent sale invoice {$sale['invoice_no']} cancelled",
        ':uid'    => $_SESSION['user_id'] ?? null,
        ':sid'    => $saleId, ':tenant' => $tenantId
    ]);

    // 4. Mark Sale as Cancelled
    $canSaleStmt = $db->prepare("UPDATE sales 
                                SET status = 'cancelled', cancel_reason = :reason, cancelled_by = :uid, cancelled_at = NOW() 
                                WHERE id = :id AND tenant_id = :tenant");
    $canSaleStmt->execute([
        ':reason' => $reason,
        ':uid'    => $_SESSION['user_id'] ?? null,
        ':id'     => $saleId, ':tenant' => $tenantId
    ]);

    // 5. Recalculate Monthly Targets & Commission
    $saleMonth = (int)date('n', strtotime($sale['sale_date']));
    $saleYear = (int)date('Y', strtotime($sale['sale_date']));

    foreach (array_keys($affectedCompanies) as $cid) {
        recalculateTargetAndCommission($db, $cid, null, $saleMonth, $saleYear);
    }

    logAudit('sales', 'cancel', $sale['invoice_no'], $sale, ['reason' => $reason]);

    $db->commit();
    jsonResponse(true, "Sales invoice {$sale['invoice_no']} has been successfully cancelled. Stock and financial balances reversed.");

} catch (Exception $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    jsonResponse(false, 'Cancellation failed: ' . $e->getMessage(), [], 400);
}
