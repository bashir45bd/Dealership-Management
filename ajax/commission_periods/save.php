<?php
/**
 * Maruf Traders - AJAX Save Period Commission (3-Month / Yearly)
 *
 * Handles BOTH:
 *   - Create: no "id" posted (or id="0") -> INSERT new pending record
 *   - Edit:   "id" posted, belonging to an existing PENDING record -> UPDATE
 *
 * A RECEIVED record can never be edited here - it is rejected outright
 * even if someone tampers with the posted id, since a received record's
 * bags/amount are frozen and already baked into a past month's Net Profit.
 *
 * Enforces (authoritative, server-side):
 *   - Quarterly ("3-Month") periods may span at most 3 months.
 *   - Yearly periods may span at most 12 months.
 *   - No two periods of the SAME commission_type for the SAME company
 *     may cover overlapping dates (prevents double-claiming the same
 *     month(s)/year more than once). When editing, the record's own id
 *     is excluded from this check against itself.
 */

define('APP_INIT', true);
define('IS_AJAX', true);
require_once __DIR__ . '/../../config/app.php';
require_once ROOT_PATH . '/includes/auth_check.php';
require_once ROOT_PATH . '/includes/permission_check.php';

requirePermission('commission_periods.manage');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(false, 'Invalid request method.', [], 405);
}

$csrfToken = $_POST['csrf_token'] ?? '';
if (!verifyCsrfToken($csrfToken)) {
    jsonResponse(false, 'Invalid security token (CSRF).', [], 403);
}

$id = (int)($_POST['id'] ?? 0);
$companyId = (int)($_POST['company_id'] ?? 0);
$commissionType = trim($_POST['commission_type'] ?? '');
$startDate = trim($_POST['start_date'] ?? '');
$endDate = trim($_POST['end_date'] ?? '');
$ratePerBag = (float)($_POST['rate_per_bag'] ?? 0);
$periodLabel = trim($_POST['period_label'] ?? '');
$notes = trim($_POST['notes'] ?? '');

if (!$companyId) {
    jsonResponse(false, 'Please select a Company.');
}
if (!in_array($commissionType, ['quarterly', 'yearly'], true)) {
    jsonResponse(false, 'Invalid commission type.');
}
if (empty($startDate) || empty($endDate)) {
    jsonResponse(false, 'Please provide both start and end dates.');
}
if (strtotime($startDate) === false || strtotime($endDate) === false) {
    jsonResponse(false, 'Invalid date format.');
}
if (strtotime($endDate) < strtotime($startDate)) {
    jsonResponse(false, 'End date cannot be before start date.');
}
if ($ratePerBag <= 0) {
    jsonResponse(false, 'Rate per bag must be greater than 0.');
}

// --------------------------------------------------------------
// Duration validation
//   - quarterly ("3-Month") -> max 3 months
//   - yearly                -> max 12 months
// --------------------------------------------------------------
$maxMonths = ($commissionType === 'yearly') ? 12 : 3;
$maxAllowedEnd = date('Y-m-d', strtotime($startDate . " +{$maxMonths} months -1 day"));

if (strtotime($endDate) > strtotime($maxAllowedEnd)) {
    $durationLabel = ($commissionType === 'yearly') ? '12 months (1 year)' : '3 months';
    $typeLabel = ($commissionType === 'yearly') ? 'Yearly' : '3-Month';
    jsonResponse(false, "The selected period exceeds the maximum allowed duration for {$typeLabel} commission ({$durationLabel}). Please choose a shorter date range.");
}

$db = Database::getConnection();
$isEditing = $id > 0;

try {
    // Verify the company exists
    $cStmt = $db->prepare("SELECT id, name FROM companies WHERE id = :id");
    $cStmt->execute([':id' => $companyId]);
    $company = $cStmt->fetch();
    if (!$company) {
        jsonResponse(false, 'Selected company not found.');
    }

    $existing = null;
    if ($isEditing) {
        // Must exist and must still be pending - received records are frozen.
        $exStmt = $db->prepare("SELECT * FROM company_period_commissions WHERE id = :id");
        $exStmt->execute([':id' => $id]);
        $existing = $exStmt->fetch();

        if (!$existing) {
            jsonResponse(false, 'Period commission record not found.', [], 404);
        }
        if ($existing['status'] !== 'pending') {
            jsonResponse(false, 'This period commission has already been marked as received and can no longer be edited.');
        }
    }

    // --------------------------------------------------------------
    // Overlap validation — same company + same commission_type +
    // overlapping date range is not allowed (covers both pending and
    // already-received records, since a received record already
    // "used up" that date range for that company/type). When editing,
    // the record's own id is excluded from this check against itself.
    // --------------------------------------------------------------
    $ovSql = "SELECT id, period_label, start_date, end_date
              FROM company_period_commissions
              WHERE company_id = :cid
                AND commission_type = :ctype
                AND NOT (end_date < :start OR start_date > :end)";
    $ovParams = [
        ':cid'   => $companyId,
        ':ctype' => $commissionType,
        ':start' => $startDate,
        ':end'   => $endDate
    ];
    if ($isEditing) {
        $ovSql .= " AND id != :selfId";
        $ovParams[':selfId'] = $id;
    }
    $ovSql .= " LIMIT 1";

    $ovStmt = $db->prepare($ovSql);
    $ovStmt->execute($ovParams);
    $conflict = $ovStmt->fetch();

    if ($conflict) {
        $typeLabel = ($commissionType === 'yearly') ? 'Yearly' : '3-Month';
        jsonResponse(false, "A {$typeLabel} commission period for {$company['name']} already exists covering this date range (\"{$conflict['period_label']}\", " .
            date('d M Y', strtotime($conflict['start_date'])) . ' - ' . date('d M Y', strtotime($conflict['end_date'])) .
            "). Each period must cover a distinct, non-overlapping range.");
    }

    if (empty($periodLabel)) {
        $periodLabel = ($commissionType === 'yearly' ? 'Yearly' : '3-Month') . ' Commission (' .
            date('d M Y', strtotime($startDate)) . ' - ' . date('d M Y', strtotime($endDate)) . ')';
    }

    if ($isEditing) {
        $updStmt = $db->prepare(
            "UPDATE company_period_commissions
             SET company_id = :cid,
                 commission_type = :ctype,
                 period_label = :label,
                 start_date = :start,
                 end_date = :end,
                 rate_per_bag = :rate,
                 notes = :notes
             WHERE id = :id AND status = 'pending'"
        );
        $updStmt->execute([
            ':cid'   => $companyId,
            ':ctype' => $commissionType,
            ':label' => $periodLabel,
            ':start' => $startDate,
            ':end'   => $endDate,
            ':rate'  => $ratePerBag,
            ':notes' => $notes,
            ':id'    => $id
        ]);

        if ($updStmt->rowCount() === 0) {
            // Either nothing changed, or it flipped to received between our
            // check and this UPDATE — re-check status to give an accurate message.
            $recheck = $db->prepare("SELECT status FROM company_period_commissions WHERE id = :id");
            $recheck->execute([':id' => $id]);
            $stillPending = $recheck->fetchColumn();
            if ($stillPending !== 'pending') {
                jsonResponse(false, 'This period commission was just marked as received and can no longer be edited.');
            }
            // rowCount()===0 with no actual field changes is fine - fall through as success.
        }

        logAudit('commission_periods', 'update', (string)$id, $existing, [
            'company' => $company['name'],
            'type' => $commissionType,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'rate_per_bag' => $ratePerBag
        ]);

        jsonResponse(true, 'Period commission updated. Bags and amount will recalculate automatically from sales data.', ['id' => $id]);
    } else {
        $insStmt = $db->prepare(
            "INSERT INTO company_period_commissions
                (company_id, commission_type, period_label, start_date, end_date, rate_per_bag, total_bags, total_amount, status, notes, created_by)
             VALUES
                (:cid, :ctype, :label, :start, :end, :rate, 0, 0.00, 'pending', :notes, :uid)"
        );
        $insStmt->execute([
            ':cid'   => $companyId,
            ':ctype' => $commissionType,
            ':label' => $periodLabel,
            ':start' => $startDate,
            ':end'   => $endDate,
            ':rate'  => $ratePerBag,
            ':notes' => $notes,
            ':uid'   => $_SESSION['user_id'] ?? null
        ]);

        $newId = (int)$db->lastInsertId();

        logAudit('commission_periods', 'create', (string)$newId, null, [
            'company' => $company['name'],
            'type' => $commissionType,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'rate_per_bag' => $ratePerBag
        ]);

        jsonResponse(true, 'Period commission created. Bags and amount will calculate automatically from sales data.', ['id' => $newId]);
    }

} catch (Exception $e) {
    jsonResponse(false, 'Failed to save period commission: ' . $e->getMessage(), [], 500);
}