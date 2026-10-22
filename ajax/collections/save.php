<?php
/**
 * Maruf Traders - AJAX Save Collection
 */

define('APP_INIT', true);
define('IS_AJAX', true);
require_once __DIR__ . '/../../config/app.php';
require_once ROOT_PATH . '/includes/auth_check.php';
require_once ROOT_PATH . '/includes/permission_check.php';

requirePermission('collections.manage');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(false, 'Invalid request method.', [], 405);
}

$csrfToken = $_POST['csrf_token'] ?? '';
if (!verifyCsrfToken($csrfToken)) {
    jsonResponse(false, 'Invalid CSRF token.', [], 403);
}

$retailerId = (int)($_POST['retailer_id'] ?? 0);
$collectionDate = trim($_POST['collection_date'] ?? date('Y-m-d'));
$amount = (float)($_POST['amount'] ?? 0.00);
$paymentMethod = trim($_POST['payment_method'] ?? 'Cash');
$bankAccount = trim($_POST['bank_account'] ?? '');
$transactionRef = trim($_POST['transaction_ref'] ?? '');
$notes = trim($_POST['notes'] ?? '');

if (!$retailerId) {
    jsonResponse(false, 'Please select a Retailer.');
}

if ($amount <= 0) {
    jsonResponse(false, 'Collection amount must be greater than 0.');
}

$db = Database::getConnection();
$tenantId = TenantContext::id();
TenantContext::requireWritable();

try {

    assertMonthOpen($collectionDate);

    $db->beginTransaction();

    /*
     * Lock retailer row so two collections cannot
     * update the same balance incorrectly at the same time.
     */
    $rStmt = $db->prepare(
        "SELECT * FROM retailers WHERE id = :id AND tenant_id = :tenant FOR UPDATE"
    );

    $rStmt->execute([
        ':id' => $retailerId, ':tenant' => $tenantId
    ]);

    $retailer = $rStmt->fetch();

    if (!$retailer) {
        throw new Exception("Retailer not found.");
    }


    /*
     * ---------------------------------------------------------
     * BALANCE CALCULATION
     * ---------------------------------------------------------
     *
     * current_due     = retailer owes us
     * advance_balance = retailer has extra money deposited
     *
     * Collection logic:
     *
     * 1. Collection first clears current_due.
     * 2. If collection is greater than current_due,
     *    the extra amount becomes advance.
     * 3. Existing advance is preserved.
     *
     * Example:
     *
     * Due = 20,000
     * Advance = 0
     * Collection = 30,000
     *
     * New Due = 0
     * New Advance = 10,000
     *
     * ---------------------------------------------------------
     */

    $oldDue = max(
        0,
        (float)$retailer['current_due']
    );

    $oldAdvance = max(
        0,
        (float)$retailer['advance_balance']
    );


    if ($amount <= $oldDue) {

        /*
         * Collection is enough only to reduce
         * the existing due.
         */
        $newDue = $oldDue - $amount;
        $newAdvance = $oldAdvance;

    } else {

        /*
         * Due is completely paid.
         *
         * Remaining amount becomes Advance.
         */
        $extraAmount = $amount - $oldDue;

        $newDue = 0;
        $newAdvance = $oldAdvance + $extraAmount;
    }


    // Generate Collection Code
    $colCode = generateCode(
        'collections',
        'collection_code',
        PREFIX_COLLECTION,
        5
    );


    // Insert Collection Record
    $cStmt = $db->prepare(
        "INSERT INTO collections
        (
            tenant_id,
            collection_code,
            retailer_id,
            collection_date,
            amount,
            payment_method,
            bank_account,
            transaction_ref,
            status,
            notes,
            created_by
        )
        VALUES
        (
            :tenant,
            :code,
            :rid,
            :cdate,
            :amt,
            :pmethod,
            :bank,
            :txref,
            'active',
            :notes,
            :uid
        )"
    );

    $cStmt->execute([
        ':tenant'  => $tenantId, ':code'    => $colCode,
        ':rid'     => $retailerId,
        ':cdate'   => $collectionDate,
        ':amt'     => $amount,
        ':pmethod' => $paymentMethod,
        ':bank'    => $bankAccount,
        ':txref'   => $transactionRef,
        ':notes'   => $notes,
        ':uid'     => $_SESSION['user_id'] ?? null
    ]);


    /*
     * ---------------------------------------------------------
     * UPDATE RETAILER BALANCE
     * ---------------------------------------------------------
     */
    $uStmt = $db->prepare(
        "UPDATE retailers
         SET
            current_due = :due,
            advance_balance = :advance
         WHERE id = :id AND tenant_id = :tenant"
    );

    $uStmt->execute([
        ':due'     => $newDue,
        ':advance' => $newAdvance,
        ':id'      => $retailerId,
        ':tenant'  => $tenantId
    ]);


    /*
     * ---------------------------------------------------------
     * RETAILER LEDGER
     * ---------------------------------------------------------
     *
     * Ledger running_balance represents CURRENT DUE.
     *
     * If collection creates advance, the due becomes 0.
     * Advance is stored separately in retailers.advance_balance.
     *
     * ---------------------------------------------------------
     */
    $rlStmt = $db->prepare(
        "INSERT INTO retailer_ledger
        (
            tenant_id,
            retailer_id,
            transaction_date,
            transaction_type,
            reference_id,
            description,
            debit,
            credit,
            running_balance,
            created_by
        )
        VALUES
        (
            :tenant,
            :rid,
            :tdate,
            'COLLECTION',
            :ref,
            :desc,
            0.00,
            :credit,
            :run_bal,
            :uid
        )"
    );

    $description =
        "Due Collection via {$paymentMethod}" .
        ($transactionRef
            ? " (Ref: {$transactionRef})"
            : ""
        );

    if ($amount > $oldDue) {

        $extraAmount = $amount - $oldDue;

        $description .=
            " | Due cleared; " .
            formatBDT($extraAmount) .
            " added to Advance";

    }

    $rlStmt->execute([
        ':tenant'  => $tenantId,
        ':rid'     => $retailerId,
        ':tdate'   => $collectionDate,
        ':ref'     => $colCode,
        ':desc'    => $description,
        ':credit'  => $amount,
        ':run_bal' => $newDue,
        ':uid'     => $_SESSION['user_id'] ?? null
    ]);


    /*
     * Audit Log
     */
    logAudit(
        'collections',
        'create',
        $colCode,
        null,
        [
            'retailer'       => $retailer['name'],
            'amount'         => $amount,
            'method'         => $paymentMethod,
            'old_due'        => $oldDue,
            'new_due'        => $newDue,
            'old_advance'    => $oldAdvance,
            'new_advance'    => $newAdvance
        ]
    );


    $db->commit();


    /*
     * Success message
     */
    $successMessage =
        "Collection of " .
        formatBDT($amount) .
        " received successfully ({$colCode}).";


    if ($amount > $oldDue) {

        $extraAmount = $amount - $oldDue;

        $successMessage .=
            " Due cleared and " .
            formatBDT($extraAmount) .
            " added to Advance Balance.";
    }


    jsonResponse(
        true,
        $successMessage,
        [
            'code'           => $colCode,
            'current_due'    => $newDue,
            'advance_balance' => $newAdvance
        ]
    );


} catch (Exception $e) {

    if ($db->inTransaction()) {
        $db->rollBack();
    }

    jsonResponse(
        false,
        'Collection failed: ' . $e->getMessage(),
        [],
        400
    );
}
