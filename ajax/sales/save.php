<?php
/**
 * Maruf Traders - AJAX Save Sales Invoice (Multi-Item Atomic Financial Engine)
 */

define('APP_INIT', true);
define('IS_AJAX', true);
require_once __DIR__ . '/../../config/app.php';
require_once ROOT_PATH . '/includes/auth_check.php';
require_once ROOT_PATH . '/includes/permission_check.php';

requirePermission('sales.create');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(false, 'Invalid request method.', [], 405);
}

$csrfToken = $_POST['csrf_token'] ?? '';
if (!verifyCsrfToken($csrfToken)) {
    jsonResponse(false, 'Invalid CSRF token.', [], 403);
}

$retailerId = (int)($_POST['retailer_id'] ?? 0);
$saleDate = trim($_POST['sale_date'] ?? date('Y-m-d'));
$itemsJson = $_POST['items'] ?? '[]';
$items = is_array($itemsJson) ? $itemsJson : json_decode($itemsJson, true);
$discount = (float)($_POST['discount'] ?? 0.00);
$paidAmount = (float)($_POST['paid_amount'] ?? 0.00);
$advanceDeducted = (float)($_POST['advance_deducted'] ?? 0.00);
$paymentMethod = trim($_POST['payment_method'] ?? 'Cash');
$deliveryAddress = trim($_POST['delivery_address'] ?? '');
$driverInfo = trim($_POST['driver_info'] ?? '');
$notes = trim($_POST['notes'] ?? '');

if (!$retailerId) jsonResponse(false, 'Please select a Retailer.');
if (empty($items) || !is_array($items)) jsonResponse(false, 'Please add at least one cement product to the invoice.');

$db = Database::getConnection();
$tenantId = TenantContext::id();
TenantContext::requireWritable();

try {
    // 1. Period Check
    assertMonthOpen($saleDate);

    $db->beginTransaction();

    // 2. Fetch & Lock Retailer
    $rStmt = $db->prepare("SELECT * FROM retailers WHERE id = :id AND tenant_id = :tenant FOR UPDATE");
    $rStmt->execute([':id' => $retailerId, ':tenant' => $tenantId]);
    $retailer = $rStmt->fetch();
    if (!$retailer) throw new Exception("Retailer account not found.");
    if ($retailer['status'] !== 'active') throw new Exception("Selected retailer account is inactive.");

    // Validate Advance Deduction
    if ($advanceDeducted > 0 && $advanceDeducted > (float)$retailer['advance_balance']) {
        throw new Exception("Advance deduction (৳" . number_format($advanceDeducted, 2) . ") exceeds retailer's available advance balance (৳" . number_format($retailer['advance_balance'], 2) . ").");
    }

    $allowNegative = strtolower(getSetting('allow_negative_stock', 'no')) === 'yes';

    // 3. Process Items & Validate Stock
    $subtotal = 0.00;
    $totalCogs = 0.00;
    $processedItems = [];
    $affectedCompanies = [];

    foreach ($items as $item) {
        $productId = (int)($item['product_id'] ?? 0);
        $qty = (float)($item['quantity'] ?? 0.0);
        $unitPrice = (float)($item['unit_price'] ?? 0.00);

        if (!$productId || $qty <= 0 || $unitPrice <= 0) {
            throw new Exception("Invalid item line: quantity and unit price must be greater than zero.");
        }

        // Lock product
        $pStmt = $db->prepare("SELECT id, company_id, name, unit, current_stock, default_purchase_price FROM products WHERE id = :id AND tenant_id = :tenant FOR UPDATE");
        $pStmt->execute([':id' => $productId, ':tenant' => $tenantId]);
        $prod = $pStmt->fetch();

        if (!$prod) throw new Exception("Product ID {$productId} not found.");

        $unitLabel = $prod['unit'] ?? 'Unit';
        if (($prod['current_stock'] < $qty) && !$allowNegative) {
            throw new Exception("Insufficient stock for {$prod['name']}. Available: " . (float)$prod['current_stock'] . " {$unitLabel}, Requested: {$qty} {$unitLabel}.");
        }

        $itemTotal = round($qty * $unitPrice, 2);
        $unitCost = (float)$prod['default_purchase_price'];
        $itemCogs = round($qty * $unitCost, 2);
        $itemProfit = round($itemTotal - $itemCogs, 2);

        $subtotal += $itemTotal;
        $totalCogs += $itemCogs;

        $processedItems[] = [
            'product_id'   => $prod['id'],
            'company_id'   => (int)$prod['company_id'],
            'prod_name'    => $prod['name'],
            'unit'         => $unitLabel,
            'quantity'     => $qty,
            'unit_price'   => $unitPrice,
            'unit_cost'    => $unitCost,
            'total_price'  => $itemTotal,
            'item_cogs'    => $itemCogs,
            'item_profit'  => $itemProfit,
            'prev_stock'   => (float)$prod['current_stock'],
            'new_stock'    => round((float)$prod['current_stock'] - $qty, 3)
        ];

        $affectedCompanies[(int)$prod['company_id']] = true;
    }

    // 4. Calculate Final Financials
    $totalAmount = max(0, round($subtotal - $discount, 2));
    $grossProfit = round($totalAmount - $totalCogs, 2);
    $totalCredits = round($paidAmount + $advanceDeducted, 2);
    $dueAmount = max(0, round($totalAmount - $totalCredits, 2));

    $paymentStatus = ($dueAmount <= 0) ? 'paid' : (($totalCredits > 0) ? 'partial' : 'due');

    // Credit Limit Check
    $newRetailerDue = (float)$retailer['current_due'] + $dueAmount;
    $creditLimit = (float)$retailer['credit_limit'];
    // Note: Alert if exceeded, but allow sale if business permits

    // 5. Generate Invoice Code
    $invoiceNo = generateCode('sales', 'invoice_no', PREFIX_SALE, 5);

    // 6. Insert Main Sale Record
    $sStmt = $db->prepare("INSERT INTO sales 
        (tenant_id, invoice_no, retailer_id, sale_date, subtotal, discount, total_amount, paid_amount, due_amount, advance_deducted, 
         payment_method, payment_status, delivery_address, driver_info, total_cogs, gross_profit, status, notes, created_by)
        VALUES 
        (:tenant, :inv, :rid, :sdate, :subtotal, :disc, :total, :paid, :due, :adv, 
         :pmethod, :pstatus, :address, :driver, :cogs, :gprofit, 'active', :notes, :uid)");

    $sStmt->execute([
        ':tenant'   => $tenantId, ':inv'      => $invoiceNo,
        ':rid'      => $retailerId,
        ':sdate'    => $saleDate,
        ':subtotal' => $subtotal,
        ':disc'     => $discount,
        ':total'    => $totalAmount,
        ':paid'     => $paidAmount,
        ':due'      => $dueAmount,
        ':adv'      => $advanceDeducted,
        ':pmethod'  => $paymentMethod,
        ':pstatus'  => $paymentStatus,
        ':address'  => $deliveryAddress,
        ':driver'   => $driverInfo,
        ':cogs'     => $totalCogs,
        ':gprofit'  => $grossProfit,
        ':notes'    => $notes,
        ':uid'      => $_SESSION['user_id'] ?? null
    ]);

    $saleId = (int)$db->lastInsertId();

    // 7. Insert Sale Items & Deduct Stock & Record Stock Ledger
    $siStmt = $db->prepare("INSERT INTO sale_items 
        (tenant_id, sale_id, product_id, company_id, quantity, unit_price, unit_cost, total_price, item_cogs, item_profit)
        VALUES (:tenant, :sid, :pid, :cid, :qty, :uprice, :ucost, :totprice, :cogs, :profit)");

    $updStockStmt = $db->prepare("UPDATE products SET current_stock = :stock WHERE id = :id AND tenant_id = :tenant");

    $slStmt = $db->prepare("INSERT INTO stock_ledger 
        (tenant_id, product_id, company_id, transaction_date, transaction_type, reference_id, stock_in, stock_out, running_balance, notes, created_by)
        VALUES (:tenant, :pid, :cid, :tdate, 'SALE', :ref, 0, :sout, :run_bal, :notes, :uid)");

    foreach ($processedItems as $pi) {
        $siStmt->execute([
            ':tenant'   => $tenantId, ':sid'      => $saleId,
            ':pid'      => $pi['product_id'],
            ':cid'      => $pi['company_id'],
            ':qty'      => $pi['quantity'],
            ':uprice'   => $pi['unit_price'],
            ':ucost'    => $pi['unit_cost'],
            ':totprice' => $pi['total_price'],
            ':cogs'     => $pi['item_cogs'],
            ':profit'   => $pi['item_profit']
        ]);

        // Deduct Stock
        $updStockStmt->execute([':stock' => $pi['new_stock'], ':id' => $pi['product_id'], ':tenant' => $tenantId]);

        // Insert Stock Ledger
        $slStmt->execute([
            ':tenant'  => $tenantId, ':pid'     => $pi['product_id'],
            ':cid'     => $pi['company_id'],
            ':tdate'   => $saleDate,
            ':ref'     => $invoiceNo,
            ':sout'    => $pi['quantity'],
            ':run_bal' => $pi['new_stock'],
            ':notes'   => "Sold to {$retailer['name']} (Inv: {$invoiceNo})",
            ':uid'     => $_SESSION['user_id'] ?? null
        ]);
    }

    // 8. Update Retailer Balances & Record Retailer Ledger
    $newAdvanceBalance = (float)$retailer['advance_balance'] - $advanceDeducted;
    $uRetStmt = $db->prepare("UPDATE retailers 
                             SET current_due = :due, advance_balance = :adv 
                             WHERE id = :id AND tenant_id = :tenant");
    $uRetStmt->execute([
        ':due'    => $newRetailerDue,
        ':adv'    => $newAdvanceBalance,
        ':id'     => $retailerId,
        ':tenant' => $tenantId
    ]);

    // Insert Retailer Ledger Entry
    $rlStmt = $db->prepare("INSERT INTO retailer_ledger 
        (tenant_id, retailer_id, transaction_date, transaction_type, reference_id, description, debit, credit, running_balance, created_by)
        VALUES (:tenant, :rid, :tdate, 'SALE', :ref, :desc, :debit, :credit, :run_bal, :uid)");

    $rlStmt->execute([
        ':tenant'  => $tenantId, ':rid'     => $retailerId,
        ':tdate'   => $saleDate,
        ':ref'     => $invoiceNo,
        ':desc'    => "Sale Invoice (Total: " . formatBDT($totalAmount) . ")",
        ':debit'   => $totalAmount,
        ':credit'  => $totalCredits,
        ':run_bal' => $newRetailerDue,
        ':uid'     => $_SESSION['user_id'] ?? null
    ]);

    // 9. If paid amount > 0, record in collections
    if ($paidAmount > 0) {
        $colCode = generateCode('collections', 'collection_code', PREFIX_COLLECTION, 5);
        $cInsStmt = $db->prepare("INSERT INTO collections 
            (tenant_id, collection_code, retailer_id, sale_id, collection_date, amount, payment_method, status, notes, created_by)
            VALUES (:tenant, :code, :rid, :sid, :cdate, :amt, :pmethod, 'active', :notes, :uid)");
        $cInsStmt->execute([
            ':tenant'  => $tenantId, ':code'    => $colCode,
            ':rid'     => $retailerId,
            ':sid'     => $saleId,
            ':cdate'   => $saleDate,
            ':amt'     => $paidAmount,
            ':pmethod' => $paymentMethod,
            ':notes'   => "Payment received against {$invoiceNo}",
            ':uid'     => $_SESSION['user_id'] ?? null
        ]);
    }

    // 10. Recalculate Monthly Targets & Commission for affected companies
    $saleMonth = (int)date('n', strtotime($saleDate));
    $saleYear = (int)date('Y', strtotime($saleDate));

    foreach (array_keys($affectedCompanies) as $cid) {
        recalculateTargetAndCommission($db, $cid, null, $saleMonth, $saleYear);
    }

    // 11. Audit Log
    logAudit('sales', 'create', $invoiceNo, null, [
        'retailer' => $retailer['name'], 'total' => $totalAmount, 'paid' => $paidAmount, 'due' => $dueAmount
    ]);

    $db->commit();
    jsonResponse(true, "Sale invoice {$invoiceNo} recorded successfully! Stock and retailer due updated.", [
        'id'         => $saleId,
        'invoice_no' => $invoiceNo
    ]);

} catch (Exception $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    jsonResponse(false, 'Sale failed: ' . $e->getMessage(), [], 400);
}
