<?php
/**
 * Maruf Traders - AJAX Save Monthly Target
 * (Updated: adds fixed_commission_per_bag — achievement % এর সাথে সম্পর্কহীন)
 */

define('APP_INIT', true);
define('IS_AJAX', true);
require_once __DIR__ . '/../../config/app.php';
require_once ROOT_PATH . '/includes/auth_check.php';
require_once ROOT_PATH . '/includes/permission_check.php';

requirePermission('targets.manage');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(false, 'Invalid request method.', [], 405);
}

$csrfToken = $_POST['csrf_token'] ?? '';
if (!verifyCsrfToken($csrfToken)) {
    jsonResponse(false, 'Invalid CSRF token.', [], 403);
}

$companyId = (int)($_POST['company_id'] ?? 0);
$productId = !empty($_POST['product_id']) ? (int)$_POST['product_id'] : null;
$month = (int)($_POST['target_month'] ?? date('n'));
$year = (int)($_POST['target_year'] ?? date('Y'));
$targetQty = (int)($_POST['target_quantity'] ?? 0);
$commissionMode = trim($_POST['commission_mode'] ?? 'proportional');
$commissionRate = (float)($_POST['commission_rate'] ?? 38.00);
$fixedCommissionPerBag = (float)($_POST['fixed_commission_per_bag'] ?? 0.00); // NEW
$notes = trim($_POST['notes'] ?? '');

if (!$companyId) jsonResponse(false, 'Please select a Company.');
if ($targetQty <= 0) jsonResponse(false, 'Target quantity must be greater than 0 bags.');
if ($month < 1 || $month > 12) jsonResponse(false, 'Invalid month.');
if ($fixedCommissionPerBag < 0) jsonResponse(false, 'Fixed commission cannot be negative.'); // NEW

$db = Database::getConnection();

try {
    $db->beginTransaction();

    // Check if target already exists for this company/product/month/year
    $chkStmt = $db->prepare("SELECT id, target_code FROM monthly_targets 
                             WHERE company_id = :cid AND target_month = :m AND target_year = :y 
                               AND (product_id = :pid OR (product_id IS NULL AND :pid2 IS NULL))");
    $chkStmt->execute([':cid' => $companyId, ':m' => $month, ':y' => $year, ':pid' => $productId, ':pid2' => $productId]);
    $existing = $chkStmt->fetch();

    if ($existing) {
        $uStmt = $db->prepare("UPDATE monthly_targets 
                               SET target_quantity = :tqty, commission_mode = :cmode, 
                                   commission_rate = :crate, fixed_commission_per_bag = :fcpb, notes = :notes 
                               WHERE id = :id");
        $uStmt->execute([
            ':tqty'  => $targetQty,
            ':cmode' => $commissionMode,
            ':crate' => $commissionRate,
            ':fcpb'  => $fixedCommissionPerBag,
            ':notes' => $notes,
            ':id'    => $existing['id']
        ]);
        $targetCode = $existing['target_code'];
    } else {
        $targetCode = generateCode('monthly_targets', 'target_code', PREFIX_TARGET, 5);
        $insStmt = $db->prepare("INSERT INTO monthly_targets 
            (target_code, company_id, product_id, target_month, target_year, target_quantity, 
             actual_sales_quantity, achievement_percentage, commission_mode, commission_rate, 
             fixed_commission_per_bag, estimated_commission, status, notes)
            VALUES 
            (:code, :cid, :pid, :m, :y, :tqty, 0, 0.00, :cmode, :crate, :fcpb, 0.00, 'active', :notes)");

        $insStmt->execute([
            ':code'  => $targetCode,
            ':cid'   => $companyId,
            ':pid'   => $productId,
            ':m'     => $month,
            ':y'     => $year,
            ':tqty'  => $targetQty,
            ':cmode' => $commissionMode,
            ':crate' => $commissionRate,
            ':fcpb'  => $fixedCommissionPerBag,
            ':notes' => $notes
        ]);
    }

    // Trigger auto-calculation with existing sales
    recalculateTargetAndCommission($db, $companyId, $productId, $month, $year);

    logAudit('targets', 'save', $targetCode, null, [
        'company_id' => $companyId, 'month' => $month, 'year' => $year,
        'target_quantity' => $targetQty, 'fixed_commission_per_bag' => $fixedCommissionPerBag
    ]);

    $db->commit();
    jsonResponse(true, "Monthly target {$targetCode} saved and synced with sales.", ['code' => $targetCode]);

} catch (Exception $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    jsonResponse(false, 'Failed to save monthly target: ' . $e->getMessage(), [], 400);
}