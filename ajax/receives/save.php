<?php
/**
 * Maruf Traders - AJAX Save Cement Receive / Purchase
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

$companyId = (int)($_POST['company_id'] ?? 0);
$productId = (int)($_POST['product_id'] ?? 0);
$receiveDate = trim($_POST['receive_date'] ?? date('Y-m-d'));
$quantity = (float)($_POST['quantity'] ?? 0);
$purchaseRate = (float)($_POST['purchase_rate'] ?? 0.00);
$transportCost = (float)($_POST['transport_cost'] ?? 0.00);
$loadingCost = (float)($_POST['loading_cost'] ?? 0.00);
$otherCost = (float)($_POST['other_cost'] ?? 0.00);
$paidAmount = (float)($_POST['paid_amount'] ?? 0.00);
$paymentMethod = trim($_POST['payment_method'] ?? 'Cash');
$challanNo = trim($_POST['challan_no'] ?? '');
$vehicleNo = trim($_POST['vehicle_no'] ?? '');
$notes = trim($_POST['notes'] ?? '');

if (!$companyId) jsonResponse(false, 'Please select a Supplier / Company.');
if (!$productId) jsonResponse(false, 'Please select a Product.');
if ($quantity <= 0) jsonResponse(false, 'Received Quantity must be greater than 0.');
if ($purchaseRate <= 0) jsonResponse(false, 'Purchase Rate must be greater than 0.');

// Backend Financial Calculation
$purchaseValue = round($quantity * $purchaseRate, 2);
$totalCost = round($purchaseValue + $transportCost + $loadingCost + $otherCost, 2);
$unitCostBasis = round($totalCost / $quantity, 2);
$payableAmount = round($totalCost - $paidAmount, 2);
$paymentStatus = ($payableAmount <= 0) ? 'paid' : (($paidAmount > 0) ? 'partial' : 'due');

$db = Database::getConnection();
$tenantId = TenantContext::id();
TenantContext::requireWritable();

try {
    // 1. Assert Month is Open
    assertMonthOpen($receiveDate);

    $db->beginTransaction();

    // Verify company & product
    $cStmt = $db->prepare("SELECT id, name, current_payable FROM companies WHERE id = :id AND tenant_id = :tenant FOR UPDATE");
    $cStmt->execute([':id' => $companyId, ':tenant' => $tenantId]);
    $company = $cStmt->fetch();
    if (!$company) throw new Exception("Invalid company selected.");

    $pStmt = $db->prepare("SELECT id, name, current_stock FROM products WHERE id = :id AND company_id = :cid AND tenant_id = :tenant FOR UPDATE");
    $pStmt->execute([':id' => $productId, ':cid' => $companyId, ':tenant' => $tenantId]);
    $product = $pStmt->fetch();
    if (!$product) throw new Exception("Selected product does not belong to this company.");

    // Generate Receive Code
    $receiveCode = generateCode('cement_receives', 'receive_code', PREFIX_RECEIVE, 5);

    // Insert Cement Receive
    $rStmt = $db->prepare("INSERT INTO cement_receives 
        (tenant_id, receive_code, company_id, product_id, receive_date, quantity, purchase_rate, purchase_value, 
         transport_cost, loading_cost, other_cost, total_cost, unit_cost_basis, paid_amount, payable_amount, 
         payment_status, payment_method, challan_no, vehicle_no, status, notes, created_by)
        VALUES 
        (:tenant, :code, :cid, :pid, :rdate, :qty, :prate, :pval, :tcost, :lcost, :ocost, :tcost_tot, :ucost, :paid, :payable,
         :pstatus, :pmethod, :challan, :vehicle, 'active', :notes, :uid)");

    $rStmt->execute([
        ':tenant'    => $tenantId, ':code'      => $receiveCode,
        ':cid'       => $companyId,
        ':pid'       => $productId,
        ':rdate'     => $receiveDate,
        ':qty'       => $quantity,
        ':prate'     => $purchaseRate,
        ':pval'      => $purchaseValue,
        ':tcost'     => $transportCost,
        ':lcost'     => $loadingCost,
        ':ocost'     => $otherCost,
        ':tcost_tot' => $totalCost,
        ':ucost'     => $unitCostBasis,
        ':paid'      => $paidAmount,
        ':payable'   => $payableAmount,
        ':pstatus'   => $paymentStatus,
        ':pmethod'   => $paymentMethod,
        ':challan'   => $challanNo,
        ':vehicle'   => $vehicleNo,
        ':notes'     => $notes,
        ':uid'       => $_SESSION['user_id'] ?? null
    ]);

    // Increase Product Stock & Update Cost Basis
    $newStock = round((float)$product['current_stock'] + $quantity, 3);
    $updProdStmt = $db->prepare("UPDATE products 
                                 SET current_stock = :new_stock, default_purchase_price = :cost_basis 
                                 WHERE id = :pid AND tenant_id = :tenant");
    $updProdStmt->execute([
        ':new_stock'   => $newStock,
        ':cost_basis'  => $unitCostBasis,
        ':pid'         => $productId,
        ':tenant'      => $tenantId
    ]);

    // Record in Stock Ledger
    $slStmt = $db->prepare("INSERT INTO stock_ledger 
        (tenant_id, product_id, company_id, transaction_date, transaction_type, reference_id, stock_in, stock_out, running_balance, notes, created_by)
        VALUES (:tenant, :pid, :cid, :tdate, 'RECEIVE', :ref, :in_qty, 0, :run_bal, :notes, :uid)");
    $slStmt->execute([
        ':tenant'  => $tenantId, ':pid'     => $productId,
        ':cid'     => $companyId,
        ':tdate'   => $receiveDate,
        ':ref'     => $receiveCode,
        ':in_qty'  => $quantity,
        ':run_bal' => $newStock,
        ':notes'   => "Product received via Challan: " . ($challanNo ?: 'N/A'),
        ':uid'     => $_SESSION['user_id'] ?? null
    ]);

    // Update Company Current Payable
    $newPayable = (float)$company['current_payable'] + $payableAmount;
    $updCompStmt = $db->prepare("UPDATE companies SET current_payable = :payable WHERE id = :cid AND tenant_id = :tenant");
    $updCompStmt->execute([':payable' => $newPayable, ':cid' => $companyId, ':tenant' => $tenantId]);

    // Record in Company Ledger
    $clStmt = $db->prepare("INSERT INTO company_ledger 
        (tenant_id, company_id, transaction_date, transaction_type, reference_id, description, debit, credit, running_balance, created_by)
        VALUES (:tenant, :cid, :tdate, 'RECEIVE', :ref, :desc, :debit, :credit, :run_bal, :uid)");
    $clStmt->execute([
        ':tenant'  => $tenantId, ':cid'     => $companyId,
        ':tdate'   => $receiveDate,
        ':ref'     => $receiveCode,
        ':desc'    => "Received {$quantity} bags of {$product['name']}",
        ':debit'   => $totalCost,
        ':credit'  => $paidAmount,
        ':run_bal' => $newPayable,
        ':uid'     => $_SESSION['user_id'] ?? null
    ]);

    // Record Audit Log
    logAudit('cement_receives', 'create', $receiveCode, null, [
        'company' => $company['name'], 'product' => $product['name'], 'qty' => $quantity, 'total_cost' => $totalCost
    ]);

    $db->commit();
    jsonResponse(true, "Cement receive recorded successfully ({$receiveCode}). Stock updated by +{$quantity} bags.", ['code' => $receiveCode]);

} catch (Exception $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    jsonResponse(false, $e->getMessage(), [], 400);
}
