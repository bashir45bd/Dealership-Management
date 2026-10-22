<?php
/**
 * Maruf Traders - AJAX Stock Adjustment Handler
 */

define('APP_INIT', true);
define('IS_AJAX', true);
require_once __DIR__ . '/../../config/app.php';
require_once ROOT_PATH . '/includes/auth_check.php';
require_once ROOT_PATH . '/includes/permission_check.php';

requirePermission('stock.adjust');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(false, 'Invalid request method.', [], 405);
}

$csrfToken = $_POST['csrf_token'] ?? '';
if (!verifyCsrfToken($csrfToken)) {
    jsonResponse(false, 'Invalid CSRF token.', [], 403);
}

$productId = (int)($_POST['product_id'] ?? 0);
$adjustmentDate = trim($_POST['adjustment_date'] ?? date('Y-m-d'));
$adjustmentType = trim($_POST['adjustment_type'] ?? 'Damaged');
$action = trim($_POST['action'] ?? 'decrease'); // 'increase' or 'decrease'
$quantity = (float)($_POST['quantity'] ?? 0);
$reason = trim($_POST['reason'] ?? '');
$notes = trim($_POST['notes'] ?? '');

if (!$productId) jsonResponse(false, 'Please select a product.');
if ($quantity <= 0) jsonResponse(false, 'Adjustment quantity must be greater than 0.');
if (empty($reason)) jsonResponse(false, 'Please provide a reason for the adjustment.');

$db = Database::getConnection();
$tenantId = TenantContext::id(); TenantContext::requireWritable();

try {
    assertMonthOpen($adjustmentDate);

    $db->beginTransaction();

    $pStmt = $db->prepare("SELECT id, name, unit, company_id, current_stock FROM products WHERE id = :id AND tenant_id = :tenant FOR UPDATE");
    $pStmt->execute([':id' => $productId, ':tenant' => $tenantId]);
    $product = $pStmt->fetch();

    if (!$product) throw new Exception("Product not found.");

    $currentStock = (float)$product['current_stock'];
    $unitLabel = $product['unit'] ?? 'Unit';
    $allowNegative = strtolower(getSetting('allow_negative_stock', 'no')) === 'yes';

    if ($action === 'decrease') {
        if (($currentStock - $quantity < 0) && !$allowNegative) {
            throw new Exception("Cannot deduct {$quantity} {$unitLabel}. Current stock is only {$currentStock} {$unitLabel}.");
        }
        $newStock = round($currentStock - $quantity, 3);
        $stockIn = 0;
        $stockOut = $quantity;
        $ledgerType = 'ADJUSTMENT_SUB';
    } else {
        $newStock = round($currentStock + $quantity, 3);
        $stockIn = $quantity;
        $stockOut = 0;
        $ledgerType = 'ADJUSTMENT_ADD';
    }

    // Generate Adjustment Code
    $adjCode = generateCode('stock_adjustments', 'adjustment_code', PREFIX_STOCK_ADJUSTMENT, 5);

    // Insert Adjustment Record
    $aStmt = $db->prepare("INSERT INTO stock_adjustments 
        (tenant_id, adjustment_code, product_id, adjustment_date, adjustment_type, action, quantity, reason, notes, created_by)
        VALUES (:tenant, :code, :pid, :adate, :atype, :act, :qty, :reason, :notes, :uid)");
    $aStmt->execute([
        ':tenant' => $tenantId,
        ':code'   => $adjCode,
        ':pid'    => $productId,
        ':adate'  => $adjustmentDate,
        ':atype'  => $adjustmentType,
        ':act'    => $action,
        ':qty'    => $quantity,
        ':reason' => $reason,
        ':notes'  => $notes,
        ':uid'    => $_SESSION['user_id'] ?? null
    ]);

    // Update Product Stock
    $uStmt = $db->prepare("UPDATE products SET current_stock = :stock WHERE id = :id AND tenant_id = :tenant");
    $uStmt->execute([':stock' => $newStock, ':id' => $productId, ':tenant' => $tenantId]);

    // Insert Stock Ledger Record
    $slStmt = $db->prepare("INSERT INTO stock_ledger 
        (tenant_id, product_id, company_id, transaction_date, transaction_type, reference_id, stock_in, stock_out, running_balance, notes, created_by)
        VALUES (:tenant, :pid, :cid, :tdate, :ttype, :ref, :sin, :sout, :run_bal, :notes, :uid)");
    $slStmt->execute([
        ':tenant'  => $tenantId,
        ':pid'     => $productId,
        ':cid'     => $product['company_id'],
        ':tdate'   => $adjustmentDate,
        ':ttype'   => $ledgerType,
        ':ref'     => $adjCode,
        ':sin'     => $stockIn,
        ':sout'    => $stockOut,
        ':run_bal' => $newStock,
        ':notes'   => "Stock {$action} ({$adjustmentType}): {$reason}",
        ':uid'     => $_SESSION['user_id'] ?? null
    ]);

    logAudit('stock', 'adjust', $adjCode, null, [
        'product' => $product['name'], 'action' => $action, 'qty' => $quantity, 'reason' => $reason
    ]);

    $db->commit();
    jsonResponse(true, "Stock adjustment {$adjCode} recorded. Current stock is now {$newStock} {$unitLabel}.");

} catch (Exception $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    jsonResponse(false, 'Adjustment failed: ' . $e->getMessage(), [], 400);
}
