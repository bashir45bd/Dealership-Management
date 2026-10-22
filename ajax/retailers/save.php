<?php
/**
 * Maruf Traders - AJAX Save Retailer Endpoint
 */

define('APP_INIT', true);
define('IS_AJAX', true);
require_once __DIR__ . '/../../config/app.php';
require_once ROOT_PATH . '/includes/auth_check.php';
require_once ROOT_PATH . '/includes/permission_check.php';

requirePermission('retailers.manage');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(false, 'Invalid request method.', [], 405);
}

$csrfToken = $_POST['csrf_token'] ?? '';
if (!verifyCsrfToken($csrfToken)) {
    jsonResponse(false, 'Invalid security token (CSRF).', [], 403);
}

$id = !empty($_POST['id']) ? (int)$_POST['id'] : null;
$name = trim($_POST['name'] ?? '');
$customerType = trim($_POST['customer_type'] ?? 'Retailer');
$mobile = trim($_POST['mobile'] ?? '');
$address = trim($_POST['address'] ?? '');
$creditLimit = (float)($_POST['credit_limit'] ?? 100000.00);
$openingBalance = (float)($_POST['opening_balance'] ?? 0.00);
$advanceBalance = (float)($_POST['advance_balance'] ?? 0.00);
$status = in_array($_POST['status'] ?? 'active', ['active', 'inactive']) ? $_POST['status'] : 'active';
$notes = trim($_POST['notes'] ?? '');

if (empty($name)) {
    jsonResponse(false, 'Customer Name is required.');
}
if (empty($mobile)) {
    jsonResponse(false, 'Mobile Number is required.');
}

$db = Database::getConnection();
$tenantId = TenantContext::id();
TenantContext::requireWritable();

try {
    $db->beginTransaction();

    if ($id) {
        // Update Existing Customer
        $stmt = $db->prepare("SELECT * FROM retailers WHERE id = :id AND tenant_id = :tenant FOR UPDATE");
        $stmt->execute([':id' => $id, ':tenant' => $tenantId]);
        $oldRetailer = $stmt->fetch();

        if (!$oldRetailer) {
            $db->rollBack();
            jsonResponse(false, 'Customer not found.');
        }

        $uStmt = $db->prepare("UPDATE retailers 
                               SET name = :name, customer_type = :ctype, mobile = :mobile, address = :address, 
                                   credit_limit = :credit_limit, status = :status, notes = :notes 
                               WHERE id = :id AND tenant_id = :tenant");
        $uStmt->execute([
            ':name'         => $name,
            ':ctype'        => $customerType,
            ':mobile'       => $mobile,
            ':address'      => $address,
            ':credit_limit' => $creditLimit,
            ':status'       => $status,
            ':notes'        => $notes,
            ':id'           => $id,
            ':tenant'       => $tenantId
        ]);

        logAudit('retailers', 'update', $oldRetailer['retailer_code'], $oldRetailer, [
            'name' => $name, 'customer_type' => $customerType, 'mobile' => $mobile, 'credit_limit' => $creditLimit, 'status' => $status
        ]);

        $db->commit();
        jsonResponse(true, 'Customer details updated successfully.', ['id' => $id, 'code' => $oldRetailer['retailer_code']]);

    } else {
        SubscriptionService::assertCanCreate($db, 'customer');
        // Create New Customer with Auto ID
        $code = generateCode('retailers', 'retailer_code', PREFIX_RETAILER, 3, $tenantId);
        $currentDue = $openingBalance;

        $insStmt = $db->prepare("INSERT INTO retailers 
            (tenant_id, retailer_code, name, customer_type, mobile, address, opening_balance, advance_balance, current_due, credit_limit, status, notes) 
            VALUES (:tenant, :code, :name, :ctype, :mobile, :address, :opening, :advance, :due, :limit, :status, :notes)");

        $insStmt->execute([
            ':tenant'  => $tenantId, ':code'    => $code,
            ':name'    => $name,
            ':ctype'   => $customerType,
            ':mobile'  => $mobile,
            ':address' => $address,
            ':opening' => $openingBalance,
            ':advance' => $advanceBalance,
            ':due'     => $currentDue,
            ':limit'   => $creditLimit,
            ':status'  => $status,
            ':notes'   => $notes
        ]);

        $newId = (int)$db->lastInsertId();

        // Create Ledger Entry for Opening Balance if > 0
        if ($openingBalance > 0) {
            $lStmt = $db->prepare("INSERT INTO retailer_ledger 
                (tenant_id, retailer_id, transaction_date, transaction_type, reference_id, description, debit, credit, running_balance, created_by)
                VALUES (:tenant, :rid, CURDATE(), 'OPENING', :ref, 'Opening Due Balance Recorded', :debit, 0.00, :run_bal, :uid)");
            
            $lStmt->execute([
                ':tenant'  => $tenantId,
                ':rid'     => $newId,
                ':ref'     => 'INIT-DUE-' . $code,
                ':debit'   => $openingBalance,
                ':run_bal' => $openingBalance,
                ':uid'     => $_SESSION['user_id'] ?? null
            ]);
        }

        // Create Ledger Entry for Advance Balance if > 0
        if ($advanceBalance > 0) {
            $advRunBal = $openingBalance - $advanceBalance;
            $aStmt = $db->prepare("INSERT INTO retailer_ledger 
                (tenant_id, retailer_id, transaction_date, transaction_type, reference_id, description, debit, credit, running_balance, created_by)
                VALUES (:tenant, :rid, CURDATE(), 'ADVANCE', :ref, 'Opening Advance Balance Deposited', 0.00, :credit, :run_bal, :uid)");
            
            $aStmt->execute([
                ':tenant'  => $tenantId,
                ':rid'     => $newId,
                ':ref'     => 'INIT-ADV-' . $code,
                ':credit'  => $advanceBalance,
                ':run_bal' => $advRunBal,
                ':uid'     => $_SESSION['user_id'] ?? null
            ]);
        }

        logAudit('retailers', 'create', $code, null, [
            'name' => $name, 'mobile' => $mobile, 'opening_balance' => $openingBalance, 'credit_limit' => $creditLimit
        ]);

        $db->commit();
        jsonResponse(true, 'Retailer added successfully.', ['id' => $newId, 'code' => $code]);
    }
} catch (Exception $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    jsonResponse(false, 'Failed to save retailer: ' . $e->getMessage(), [], 500);
}
