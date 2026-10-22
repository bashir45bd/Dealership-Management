<?php
/**
 * Maruf Traders - AJAX Cancel / Void Cement Receive
 */

define('APP_INIT', true);
define('IS_AJAX', true);
require_once __DIR__ . '/../../config/app.php';
require_once ROOT_PATH . '/includes/auth_check.php';
require_once ROOT_PATH . '/includes/permission_check.php';

requirePermission('receives.manage');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(false, 'Invalid request method.', [], 405);
}

$csrfToken = $_POST['csrf_token'] ?? '';
if (!verifyCsrfToken($csrfToken)) {
    jsonResponse(false, 'Invalid CSRF token.', [], 403);
}

$receiveId = (int)($_POST['id'] ?? 0);
$reason = trim($_POST['reason'] ?? 'Cancelled by administrator');

if (!$receiveId) {
    jsonResponse(false, 'Receive ID is required.');
}

$db = Database::getConnection();

try {
    $db->beginTransaction();

    $stmt = $db->prepare("SELECT * FROM cement_receives WHERE id = :id FOR UPDATE");
    $stmt->execute([':id' => $receiveId]);
    $rec = $stmt->fetch();

    if (!$rec) throw new Exception("Receive record not found.");
    if ($rec['status'] === 'cancelled') throw new Exception("This receive has already been cancelled.");

    // Assert month is open
    assertMonthOpen($rec['receive_date']);

    // Check product stock
    $pStmt = $db->prepare("SELECT id, name, current_stock FROM products WHERE id = :pid FOR UPDATE");
    $pStmt->execute([':pid' => $rec['product_id']]);
    $product = $pStmt->fetch();

    if (!$product) throw new Exception("Associated product not found.");

    $qty = (int)$rec['quantity'];
    $allowNegative = strtolower(getSetting('allow_negative_stock', 'no')) === 'yes';

    if (($product['current_stock'] - $qty < 0) && !$allowNegative) {
        throw new Exception("Cannot cancel receive: Available stock ({$product['current_stock']} bags) is less than the received quantity ({$qty} bags). Bags may have already been sold.");
    }

    $newStock = (int)$product['current_stock'] - $qty;

    // Deduct stock
    $uProd = $db->prepare("UPDATE products SET current_stock = :stock WHERE id = :id");
    $uProd->execute([':stock' => $newStock, ':id' => $product['id']]);

    // Insert Stock Ledger Reversal
    $slStmt = $db->prepare("INSERT INTO stock_ledger 
        (product_id, company_id, transaction_date, transaction_type, reference_id, stock_in, stock_out, running_balance, notes, created_by)
        VALUES (:pid, :cid, CURDATE(), 'RECEIVE_CANCEL', :ref, 0, :out_qty, :run_bal, :notes, :uid)");
    $slStmt->execute([
        ':pid'     => $product['id'],
        ':cid'     => $rec['company_id'],
        ':ref'     => 'REV-' . $rec['receive_code'],
        ':out_qty' => $qty,
        ':run_bal' => $newStock,
        ':notes'   => "Receive cancelled: " . $reason,
        ':uid'     => $_SESSION['user_id'] ?? null
    ]);

    // Revert Company Payable
    $cStmt = $db->prepare("SELECT id, name, current_payable FROM companies WHERE id = :cid FOR UPDATE");
    $cStmt->execute([':cid' => $rec['company_id']]);
    $company = $cStmt->fetch();

    $newPayable = (float)$company['current_payable'] - (float)$rec['payable_amount'];
    $uComp = $db->prepare("UPDATE companies SET current_payable = :payable WHERE id = :id");
    $uComp->execute([':payable' => $newPayable, ':id' => $company['id']]);

    // Insert Company Ledger Reversal
    $clStmt = $db->prepare("INSERT INTO company_ledger 
        (company_id, transaction_date, transaction_type, reference_id, description, debit, credit, running_balance, created_by)
        VALUES (:cid, CURDATE(), 'RECEIVE_CANCEL', :ref, :desc, 0.00, :credit, :run_bal, :uid)");
    $clStmt->execute([
        ':cid'     => $company['id'],
        ':ref'     => 'REV-' . $rec['receive_code'],
        ':desc'    => "Receive cancelled: {$reason}",
        ':credit'  => (float)$rec['payable_amount'],
        ':run_bal' => $newPayable,
        ':uid'     => $_SESSION['user_id'] ?? null
    ]);

    // Mark Receive Record as Cancelled
    $canStmt = $db->prepare("UPDATE cement_receives 
                             SET status = 'cancelled', cancel_reason = :reason, cancelled_by = :uid, cancelled_at = NOW() 
                             WHERE id = :id");
    $canStmt->execute([
        ':reason' => $reason,
        ':uid'    => $_SESSION['user_id'] ?? null,
        ':id'     => $receiveId
    ]);

    logAudit('cement_receives', 'cancel', $rec['receive_code'], $rec, ['reason' => $reason]);

    $db->commit();
    jsonResponse(true, "Cement receive {$rec['receive_code']} has been successfully cancelled and reversed.");

} catch (Exception $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    jsonResponse(false, 'Cancellation failed: ' . $e->getMessage(), [], 400);
}
