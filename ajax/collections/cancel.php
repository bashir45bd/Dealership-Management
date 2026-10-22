<?php
/**
 * Maruf Traders - AJAX Cancel / Void Collection Receipt
 *
 * Mirrors ajax/sales/cancel.php's pattern (row lock, already-cancelled guard,
 * assertMonthOpen, ledger reversal entry, audit log, admin-only permission),
 * but the balance reversal itself is collection-specific:
 *
 * ajax/collections/save.php has TWO possible effects on a retailer, not one:
 *   1. It reduces current_due (up to the amount, capped at old due)
 *   2. If amount > old due, the EXTRA spills into advance_balance
 *
 * So cancelling must undo BOTH pieces correctly, not just add the amount
 * back to current_due. Since the collections table itself doesn't store how
 * the amount was split at creation time, we recover that split from the
 * audit_logs entry save.php wrote on creation (module='collections',
 * action='create', reference_id=collection_code, new_data contains
 * old_due/new_due/old_advance/new_advance).
 *
 * From that snapshot we compute, exactly as save.php's own logic implies:
 *   dueRestored     = old_due - new_due   (amount that had reduced the due)
 *   advanceToRemove = new_advance - old_advance (amount that had spilled to advance)
 *   (dueRestored + advanceToRemove always equals the collection amount)
 *
 * These deltas are then applied against the retailer's CURRENT live balance
 * at cancel time (same "reverse the delta, not a full historical restore"
 * approach used by ajax/sales/cancel.php), so other transactions that
 * happened in between are respected.
 *
 * Fallback: if no matching audit log is found (e.g. very old data predating
 * this audit entry, or audit logging failed at creation time), we fall back
 * to treating the whole amount as a due reversal -- the old, simpler (and
 * only correct-when-no-advance-was-created) behavior -- and flag it in the
 * response message so it can be checked manually if needed.
 */

define('APP_INIT', true);
define('IS_AJAX', true);
require_once __DIR__ . '/../../config/app.php';
require_once ROOT_PATH . '/includes/auth_check.php';
require_once ROOT_PATH . '/includes/permission_check.php';

requirePermission('collections.cancel'); // Admin / Super Admin only — same pattern as sales.cancel

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(false, 'Invalid request method.', [], 405);
}

$csrfToken = $_POST['csrf_token'] ?? '';
if (!verifyCsrfToken($csrfToken)) {
    jsonResponse(false, 'Invalid CSRF token.', [], 403);
}

$collectionId = (int)($_POST['id'] ?? 0);
$reason = trim($_POST['reason'] ?? 'Collection cancelled');

if (!$collectionId) {
    jsonResponse(false, 'Collection ID is required.');
}

$db = Database::getConnection();

try {
    $db->beginTransaction();

    $stmt = $db->prepare("SELECT * FROM collections WHERE id = :id FOR UPDATE");
    $stmt->execute([':id' => $collectionId]);
    $collection = $stmt->fetch();

    if (!$collection) throw new Exception("Collection receipt not found.");
    if ($collection['status'] === 'cancelled') throw new Exception("This collection is already cancelled.");

    // Assert Month is open (based on the collection's own date)
    assertMonthOpen($collection['collection_date']);

    $amount = (float)$collection['amount'];

    // 1. Lock retailer row (same as save.php does on creation)
    $rStmt = $db->prepare("SELECT * FROM retailers WHERE id = :id FOR UPDATE");
    $rStmt->execute([':id' => $collection['retailer_id']]);
    $retailer = $rStmt->fetch();

    if (!$retailer) throw new Exception("Retailer for this collection was not found.");

    $currentDue = max(0, (float)$retailer['current_due']);
    $currentAdvance = max(0, (float)$retailer['advance_balance']);

    // 2. Recover exactly how this collection was split between due/advance at
    //    creation time, from the audit log save.php wrote.
    $alStmt = $db->prepare("SELECT new_data FROM audit_logs 
                            WHERE module = 'collections' AND action = 'create' AND reference_id = :ref 
                            ORDER BY id DESC LIMIT 1");
    $alStmt->execute([':ref' => $collection['collection_code']]);
    $auditRow = $alStmt->fetch();

    $usedFallback = false;
    $dueRestored = 0.00;
    $advanceToRemove = 0.00;

    if ($auditRow && !empty($auditRow['new_data'])) {
        $snapshot = json_decode($auditRow['new_data'], true);
        if (is_array($snapshot)
            && isset($snapshot['old_due'], $snapshot['new_due'], $snapshot['old_advance'], $snapshot['new_advance'])) {

            $dueRestored = round((float)$snapshot['old_due'] - (float)$snapshot['new_due'], 2);
            $advanceToRemove = round((float)$snapshot['new_advance'] - (float)$snapshot['old_advance'], 2);

            // Sanity guard: these two must add up to the collection amount.
            // If they don't (corrupted/unexpected audit data), fall back safely.
            if (round($dueRestored + $advanceToRemove, 2) !== round($amount, 2)) {
                $usedFallback = true;
            }
        } else {
            $usedFallback = true;
        }
    } else {
        $usedFallback = true;
    }

    if ($usedFallback) {
        // Old/simple behavior: assume the whole amount reduced due, nothing went to advance.
        $dueRestored = $amount;
        $advanceToRemove = 0.00;
    }

    // 3. Apply the reversal against the retailer's CURRENT live balance
    $newDue = round($currentDue + $dueRestored, 2);
    $newAdvance = max(0, round($currentAdvance - $advanceToRemove, 2));

    $uRet = $db->prepare("UPDATE retailers SET current_due = :due, advance_balance = :adv WHERE id = :id");
    $uRet->execute([':due' => $newDue, ':adv' => $newAdvance, ':id' => $retailer['id']]);

    // 4. Ledger reversal entry (running_balance always reflects current_due, per save.php's convention)
    $desc = "Collection cancelled: {$reason} (Due restored: ৳" . number_format($dueRestored, 2) . ")";
    if ($advanceToRemove > 0) {
        $desc .= " | Advance reduced by ৳" . number_format($advanceToRemove, 2);
    }
    if ($usedFallback) {
        $desc .= " [audit snapshot not found — reversed as full-due, please verify advance balance manually]";
    }

    $rlStmt = $db->prepare("INSERT INTO retailer_ledger 
        (retailer_id, transaction_date, transaction_type, reference_id, description, debit, credit, running_balance, created_by)
        VALUES (:rid, CURDATE(), 'REVERSAL', :ref, :desc, :debit, 0.00, :run_bal, :uid)");

    $rlStmt->execute([
        ':rid'     => $retailer['id'],
        ':ref'     => 'REV-' . $collection['collection_code'],
        ':desc'    => $desc,
        ':debit'   => $dueRestored,
        ':run_bal' => $newDue,
        ':uid'     => $_SESSION['user_id'] ?? null
    ]);

    // 5. If this collection was ever linked to a specific sale (sale_id) -- note:
    //    ajax/collections/save.php as currently written NEVER sets sale_id, so this
    //    will normally be a no-op. Kept defensively in case another entry point
    //    (or a future feature) links collections to sales directly.
    if (!empty($collection['sale_id'])) {
        $sStmt = $db->prepare("SELECT * FROM sales WHERE id = :id FOR UPDATE");
        $sStmt->execute([':id' => $collection['sale_id']]);
        $sale = $sStmt->fetch();

        if ($sale && $sale['status'] === 'active') {
            $newPaid = max(0, round((float)$sale['paid_amount'] - $amount, 2));
            $newSaleDue = max(0, round((float)$sale['total_amount'] - $newPaid - (float)$sale['advance_deducted'], 2));

            if ($newSaleDue <= 0) {
                $newPaymentStatus = 'paid';
            } elseif ($newPaid > 0 || (float)$sale['advance_deducted'] > 0) {
                $newPaymentStatus = 'partial';
            } else {
                $newPaymentStatus = 'due';
            }

            $uSale = $db->prepare("UPDATE sales 
                                   SET paid_amount = :paid, due_amount = :due, payment_status = :pstat 
                                   WHERE id = :id");
            $uSale->execute([
                ':paid'  => $newPaid,
                ':due'   => $newSaleDue,
                ':pstat' => $newPaymentStatus,
                ':id'    => $sale['id']
            ]);
        }
    }

    // 6. Mark Collection as Cancelled
    $canColStmt = $db->prepare("UPDATE collections 
                                SET status = 'cancelled', cancel_reason = :reason, cancelled_by = :uid, cancelled_at = NOW() 
                                WHERE id = :id");
    $canColStmt->execute([
        ':reason' => $reason,
        ':uid'    => $_SESSION['user_id'] ?? null,
        ':id'     => $collectionId
    ]);

    logAudit('collections', 'cancel', $collection['collection_code'], $collection, [
        'reason'            => $reason,
        'due_restored'      => $dueRestored,
        'advance_removed'   => $advanceToRemove,
        'used_fallback'     => $usedFallback
    ]);

    $db->commit();

    $successMsg = "Collection {$collection['collection_code']} has been successfully cancelled. Due restored: " . formatBDT($dueRestored) . ".";
    if ($advanceToRemove > 0) {
        $successMsg .= " Advance reduced by " . formatBDT($advanceToRemove) . ".";
    }
    if ($usedFallback) {
        $successMsg .= " NOTE: original due/advance split could not be found — please verify this retailer's advance balance.";
    }

    jsonResponse(true, $successMsg);

} catch (Exception $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    jsonResponse(false, 'Cancellation failed: ' . $e->getMessage(), [], 400);
}