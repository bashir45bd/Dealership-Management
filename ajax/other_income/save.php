<?php
/**
 * Maruf Traders - AJAX Save Other Income
 */

define('APP_INIT', true);
define('IS_AJAX', true);
require_once __DIR__ . '/../../config/app.php';
require_once ROOT_PATH . '/includes/auth_check.php';
require_once ROOT_PATH . '/includes/permission_check.php';

requirePermission('other_income.manage');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(false, 'Invalid request method.', [], 405);
}

$csrfToken = $_POST['csrf_token'] ?? '';
if (!verifyCsrfToken($csrfToken)) {
    jsonResponse(false, 'Invalid security token (CSRF).', [], 403);
}

$incomeDate = trim($_POST['income_date'] ?? '');
$title = trim($_POST['title'] ?? '');
$category = trim($_POST['category'] ?? '');
$companyId = trim($_POST['company_id'] ?? '');
$amount = (float)($_POST['amount'] ?? 0);
$notes = trim($_POST['notes'] ?? '');

$allowedCategories = ['old_commission', 'rent', 'asset_sale', 'interest', 'other'];

if (empty($incomeDate) || strtotime($incomeDate) === false) {
    jsonResponse(false, 'Please provide a valid Income Date.');
}
if ($title === '') {
    jsonResponse(false, 'Please provide a Title / Description.');
}
if (!in_array($category, $allowedCategories, true)) {
    jsonResponse(false, 'Invalid category.');
}
if ($amount <= 0) {
    jsonResponse(false, 'Amount must be greater than 0.');
}

$companyId = ($companyId !== '' && (int)$companyId > 0) ? (int)$companyId : null;

$db = Database::getConnection();

try {
    if ($companyId !== null) {
        $cStmt = $db->prepare("SELECT id FROM companies WHERE id = :id");
        $cStmt->execute([':id' => $companyId]);
        if (!$cStmt->fetch()) {
            jsonResponse(false, 'Selected company not found.');
        }
    }

    $insStmt = $db->prepare(
        "INSERT INTO other_incomes
            (income_date, title, category, company_id, amount, notes, status, created_by)
         VALUES
            (:idate, :title, :cat, :cid, :amt, :notes, 'active', :uid)"
    );
    $insStmt->execute([
        ':idate' => $incomeDate,
        ':title' => $title,
        ':cat'   => $category,
        ':cid'   => $companyId,
        ':amt'   => $amount,
        ':notes' => $notes,
        ':uid'   => $_SESSION['user_id'] ?? null
    ]);

    $newId = (int)$db->lastInsertId();

    logAudit('other_incomes', 'create', (string)$newId, null, [
        'income_date' => $incomeDate,
        'title' => $title,
        'category' => $category,
        'amount' => $amount
    ]);

    jsonResponse(true, 'Other income entry saved and added to Net Profit for that date.', ['id' => $newId]);

} catch (Exception $e) {
    jsonResponse(false, 'Failed to save income entry: ' . $e->getMessage(), [], 500);
}