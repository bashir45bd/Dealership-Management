<?php
/**
 * Maruf Traders - AJAX Dashboard Real-Time Financial Statistics API
 */

define('APP_INIT', true);
define('IS_AJAX', true);
require_once __DIR__ . '/../../config/app.php';
require_once ROOT_PATH . '/includes/auth_check.php';
require_once ROOT_PATH . '/includes/permission_check.php';

requirePermission('dashboard.view');

$period = trim($_GET['period'] ?? 'this_month');
$db = Database::getConnection();
$tenantId = TenantContext::id();

// Determine date range
$today = date('Y-m-d');
$currentMonth = (int)date('n');
$currentYear = (int)date('Y');

if ($period === 'today') {
    $startDate = $today;
    $endDate = $today;
} elseif ($period === 'this_week') {
    $startDate = date('Y-m-d', strtotime('monday this week'));
    $endDate = $today;
} elseif ($period === 'this_year') {
    $startDate = date('Y-01-01');
    $endDate = date('Y-12-31');
} else { // 'this_month'
    $startDate = date('Y-m-01');
    $endDate = date('Y-m-t');
}

// 1. Today's Sales
$sStmt = $db->prepare("SELECT COALESCE(SUM(total_amount), 0) FROM sales WHERE tenant_id = :tenant AND sale_date = :today AND status = 'active'");
$sStmt->execute([':tenant' => $tenantId, ':today' => $today]);
$todaySales = (float)$sStmt->fetchColumn();

// 2. Today's Collection
$cStmt = $db->prepare("SELECT COALESCE(SUM(amount), 0) FROM collections WHERE tenant_id = :tenant AND collection_date = :today AND status = 'active'");
$cStmt->execute([':tenant' => $tenantId, ':today' => $today]);
$todayCollection = (float)$cStmt->fetchColumn();

// 3. Current Stock
$stkStmt = $db->prepare("SELECT COALESCE(SUM(current_stock), 0) FROM products WHERE tenant_id = :tenant AND status = 'active'");
$stkStmt->execute([':tenant' => $tenantId]);
$currentStock = (int)$stkStmt->fetchColumn();

// 4. Total Retailer Due
$dueStmt = $db->prepare("SELECT COALESCE(SUM(current_due), 0) FROM retailers WHERE tenant_id = :tenant AND status = 'active'");
$dueStmt->execute([':tenant' => $tenantId]);
$totalDue = (float)$dueStmt->fetchColumn();

// 5. Period Sales & COGS & Gross Profit
$pSalesStmt = $db->prepare("SELECT COALESCE(SUM(total_amount), 0) as total_sales, 
                                   COALESCE(SUM(total_cogs), 0) as total_cogs, 
                                   COALESCE(SUM(gross_profit), 0) as gross_profit 
                            FROM sales 
                            WHERE tenant_id = :tenant AND sale_date BETWEEN :start AND :end AND status = 'active'");
$pSalesStmt->execute([':tenant' => $tenantId, ':start' => $startDate, ':end' => $endDate]);
$pSales = $pSalesStmt->fetch();

$monthSales = (float)$pSales['total_sales'];
$grossProfit = (float)$pSales['gross_profit'];

// 6. Period Operating Expenses
$expStmt = $db->prepare("SELECT COALESCE(SUM(amount), 0) FROM expenses 
                         WHERE tenant_id = :tenant AND expense_date BETWEEN :start AND :end AND status = 'active'");
$expStmt->execute([':tenant' => $tenantId, ':start' => $startDate, ':end' => $endDate]);
$expense = (float)$expStmt->fetchColumn();

// 7. Monthly Commission Income & Targets for current month
// ============================================================
// UNCHANGED — Existing Monthly Commission logic. Do not modify.
// ============================================================
// Sync target actual sales
$allCompanyStmt = $db->prepare("SELECT id FROM companies WHERE tenant_id = :tenant AND status = 'active'");
$allCompanyStmt->execute([':tenant' => $tenantId]);
$allCompanies = $allCompanyStmt->fetchAll(PDO::FETCH_COLUMN);
foreach ($allCompanies as $cid) {
    recalculateTargetAndCommission($db, (int)$cid, null, $currentMonth, $currentYear);
}

$tgtStmt = $db->prepare("SELECT COALESCE(SUM(target_quantity), 0) as total_target,
                                COALESCE(SUM(actual_sales_quantity), 0) as total_actual,
                                COALESCE(SUM(estimated_commission), 0) as total_commission,
                                AVG(commission_rate) as avg_rate
                         FROM monthly_targets 
                         WHERE tenant_id = :tenant AND target_month = :m AND target_year = :y");
$tgtStmt->execute([':tenant' => $tenantId, ':m' => $currentMonth, ':y' => $currentYear]);
$tgt = $tgtStmt->fetch();

$targetQty = (float)$tgt['total_target'];
$actualSalesQty = (float)$tgt['total_actual'];
$monthlyCommissionIncome = (float)$tgt['total_commission']; // renamed only — value/logic unchanged
$commissionRate = (float)$tgt['avg_rate'];
$targetAchievement = $targetQty > 0 ? round(($actualSalesQty / $targetQty) * 100, 1) : 0;
// ============================================================
// END unchanged Monthly Commission block
// ============================================================

// 7b. Quarterly / Yearly Commission Income
$qtrStmt = $db->prepare("SELECT COALESCE(SUM(total_amount), 0)
                          FROM company_period_commissions
                          WHERE tenant_id = :tenant
                            AND commission_type = 'quarterly'
                            AND status = 'received'
                            AND received_date IS NOT NULL
                            AND received_date BETWEEN :start AND :end");
$qtrStmt->execute([':tenant' => $tenantId, ':start' => $startDate, ':end' => $endDate]);
$quarterlyCommissionIncome = (float)$qtrStmt->fetchColumn();

$yrStmt = $db->prepare("SELECT COALESCE(SUM(total_amount), 0)
                         FROM company_period_commissions
                         WHERE tenant_id = :tenant
                           AND commission_type = 'yearly'
                           AND status = 'received'
                           AND received_date IS NOT NULL
                           AND received_date BETWEEN :start AND :end");
$yrStmt->execute([':tenant' => $tenantId, ':start' => $startDate, ':end' => $endDate]);
$yearlyCommissionIncome = (float)$yrStmt->fetchColumn();

// 7c. Combined Commission Income (Monthly + Received Quarterly + Received Yearly)
$commissionIncome = round($monthlyCommissionIncome + $quarterlyCommissionIncome + $yearlyCommissionIncome, 2);

// 7d. Other Income
$oiStmt = $db->prepare("SELECT COALESCE(SUM(amount), 0) FROM other_incomes 
                        WHERE tenant_id = :tenant AND income_date BETWEEN :start AND :end AND status = 'active'");
$oiStmt->execute([':tenant' => $tenantId, ':start' => $startDate, ':end' => $endDate]);
$otherIncome = (float)$oiStmt->fetchColumn();
// ============================================================
// END Other Income
// ============================================================

// 8. Net Profit = Gross Profit + Commission Income + Other Income - Operating Expenses
// (Commission Income already includes Monthly + Received Quarterly + Received Yearly,
//  per the Final Net Profit Formula in the spec — Pending Quarterly/Yearly contribute 0.)
$netProfit = round($grossProfit - $expense + $commissionIncome + $otherIncome, 2);

// 9. Chart Timeline Data (Daily breakdown for current month or 14 days)
// ============================================================
// UNCHANGED — Sales/Collections chart logic. Quarterly/Yearly
// commission data must never be mixed into this.
// ============================================================
$chartLabels = [];
$chartSales = [];
$chartCollections = [];

$chartDays = 14;
for ($i = $chartDays - 1; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-{$i} days"));
    $chartLabels[] = date('d M', strtotime($date));

    // Day Sales
    $dsStmt = $db->prepare("SELECT COALESCE(SUM(total_amount), 0) FROM sales WHERE tenant_id = :tenant AND sale_date = :d AND status = 'active'");
    $dsStmt->execute([':tenant' => $tenantId, ':d' => $date]);
    $chartSales[] = (float)$dsStmt->fetchColumn();

    // Day Collection
    $dcStmt = $db->prepare("SELECT COALESCE(SUM(amount), 0) FROM collections WHERE tenant_id = :tenant AND collection_date = :d AND status = 'active'");
    $dcStmt->execute([':tenant' => $tenantId, ':d' => $date]);
    $chartCollections[] = (float)$dcStmt->fetchColumn();
}

// 10. Low Stock Items
$lowStmt = $db->prepare("SELECT name, brand, current_stock, low_stock_limit FROM products WHERE tenant_id = :tenant AND current_stock <= low_stock_limit AND status = 'active'");
$lowStmt->execute([':tenant' => $tenantId]);
$lowStockItems = $lowStmt->fetchAll();

jsonResponse(true, 'Dashboard stats calculated.', [
    'today_sales'        => $todaySales,
    'today_collection'   => $todayCollection,
    'current_stock'      => $currentStock,
    'total_due'          => $totalDue,
    'month_sales'        => $monthSales,
    'gross_profit'       => $grossProfit,
    'commission_income'  => $commissionIncome,
    'other_income'       => $otherIncome,
    'expense'            => $expense,
    'net_profit'         => $netProfit,
    'target_qty'         => $targetQty,
    'actual_sales_qty'   => $actualSalesQty,
    'target_achievement' => $targetAchievement,
    'commission_rate'    => number_format($commissionRate, 2),
    'chart_labels'       => $chartLabels,
    'chart_sales'        => $chartSales,
    'chart_collections'  => $chartCollections,
    'low_stock_items'    => $lowStockItems
]);
