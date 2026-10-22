<?php
/**
 * Maruf Traders - Profit & Loss Financial Statement
 * (Updated: print output now uses the same standard black/white
 *  letterhead + statement layout as the ledger/daily/monthly/yearly
 *  reports, instead of the colorful gradient design. The colorful
 *  design remains for on-screen viewing only.)
 *
 * PROFIT CALCULATION
 * ------------------
 * Gross Profit
 * - Operating Expenses
 * + Monthly Target Commission
 * + Received Quarterly/Yearly Commission
 * + Other Income
 * = Net Business Profit
 */

define('APP_INIT', true);

$pageTitle = 'Profit & Loss Statement';
$breadcrumb = 'Profit Report';
$activeMenu = 'report_profit';

require_once __DIR__ . '/../../includes/header.php';
requirePermission('reports.financial');

$db = Database::getConnection();

$startDate = $_GET['start_date'] ?? date('Y-m-01');
$endDate   = $_GET['end_date'] ?? date('Y-m-d');

$diagnosticErrors = [];

/*
|--------------------------------------------------------------------------
| Dealer / Business Letterhead Info — pulled from the `settings` table
|--------------------------------------------------------------------------
| Key names match the confirmed `settings` table columns used across
| the other report pages: `logo_path` + `logo_updated_at` (cache-bust).
*/
$bizName    = getSetting('business_name', defined('APP_NAME') ? APP_NAME : 'Maruf Traders');
$bizTagline = getSetting('business_title', 'Cement Dealership Management & Accounting');
$bizAddress = getSetting('business_address', defined('BUSINESS_LOCATION') ? BUSINESS_LOCATION : '');
$bizMobile  = getSetting('business_mobile', '');
$bizEmail   = getSetting('business_email', '');
$bizLogoPath = trim((string) getSetting('logo_path', ''));
$bizLogoUpdatedAt = getSetting('logo_updated_at', '');

$bizLogoUrl = '';
if ($bizLogoPath !== '') {
    if (preg_match('#^https?://#i', $bizLogoPath)) {
        $bizLogoUrl = $bizLogoPath;
    } else {
        $bizLogoUrl = rtrim(BASE_URL, '/') . '/' . ltrim($bizLogoPath, '/');
    }

    if ($bizLogoUrl !== '' && !empty($bizLogoUpdatedAt)) {
        $bizLogoUrl .= '?v=' . (int)$bizLogoUpdatedAt;
    }
}
$bizInitials = htmlspecialchars(strtoupper(substr(trim($bizName), 0, 2)));

$printedBy = $_SESSION['user_name'] ?? '';
$printedAt = date('d M, Y h:i A');


/*
|--------------------------------------------------------------------------
| Validate Dates
|--------------------------------------------------------------------------
*/

$startTimestamp = strtotime($startDate);
$endTimestamp   = strtotime($endDate);

if (!$startTimestamp) { $startDate = date('Y-m-01'); }
if (!$endTimestamp) { $endDate = date('Y-m-d'); }

if (strtotime($startDate) > strtotime($endDate)) {
    $tmp = $startDate;
    $startDate = $endDate;
    $endDate = $tmp;
}


$tenantId = TenantContext::id();

/*
|--------------------------------------------------------------------------
| 1. SALES + COGS + GROSS PROFIT
|--------------------------------------------------------------------------
*/

$totalSales  = 0;
$totalCogs   = 0;
$grossProfit = 0;

try {
    $sStmt = $db->prepare("
        SELECT
            COALESCE(SUM(total_amount), 0) AS total_sales,
            COALESCE(SUM(total_cogs), 0) AS total_cogs,
            COALESCE(SUM(gross_profit), 0) AS gross_profit
        FROM sales
        WHERE tenant_id = :tenant AND sale_date >= :start AND sale_date <= :end AND status = 'active'
    ");
    $sStmt->execute([':tenant' => $tenantId, ':start' => $startDate, ':end' => $endDate]);
    $salesData = $sStmt->fetch(PDO::FETCH_ASSOC);

    if ($salesData) {
        $totalSales  = (float)($salesData['total_sales'] ?? 0);
        $totalCogs   = (float)($salesData['total_cogs'] ?? 0);
        $grossProfit = (float)($salesData['gross_profit'] ?? 0);
    }
} catch (PDOException $e) {
    $diagnosticErrors[] = "Sales/COGS section: " . $e->getMessage();
    error_log("P&L report - sales query failed: " . $e->getMessage());
}


/*
|--------------------------------------------------------------------------
| 2. MONTHLY TARGET COMMISSION
|--------------------------------------------------------------------------
*/

$commissionIncome = 0;

try {
    $startMonth = (int)date('n', strtotime($startDate));
    $startYear = (int)date('Y', strtotime($startDate));
    $endMonth = (int)date('n', strtotime($endDate));
    $endYear = (int)date('Y', strtotime($endDate));

    $cStmt = $db->prepare("
        SELECT COALESCE(SUM(estimated_commission), 0)
        FROM monthly_targets
        WHERE tenant_id = :tenant AND
            ((target_year > :sy1 OR (target_year = :sy2 AND target_month >= :sm))
            AND
            (target_year < :ey1 OR (target_year = :ey2 AND target_month <= :em)))
    ");
    $cStmt->execute([
        ':tenant' => $tenantId,
        ':sy1' => $startYear, ':sy2' => $startYear,
        ':ey1' => $endYear,   ':ey2' => $endYear,
        ':sm' => $startMonth, ':em' => $endMonth
    ]);
    $commissionIncome = (float)$cStmt->fetchColumn();
} catch (PDOException $e) {
    $diagnosticErrors[] = "Monthly Commission section: " . $e->getMessage();
    error_log("P&L report - commission query failed: " . $e->getMessage());
}


/*
|--------------------------------------------------------------------------
| 3. OPERATING EXPENSES
|--------------------------------------------------------------------------
*/

$expenseBreakdown = [];
$totalExpenses = 0;

try {
    $expStmt = $db->prepare("
        SELECT c.name AS category_name, COALESCE(SUM(e.amount), 0) AS cat_total
        FROM expenses e
        JOIN expense_categories c ON e.category_id = c.id
        WHERE e.tenant_id = :tenant AND e.expense_date >= :start AND e.expense_date <= :end AND e.status = 'active'
        GROUP BY c.id, c.name
        ORDER BY cat_total DESC
    ");
    $expStmt->execute([':tenant' => $tenantId, ':start' => $startDate, ':end' => $endDate]);
    $expenseBreakdown = $expStmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($expenseBreakdown as $eb) {
        $totalExpenses += (float)($eb['cat_total'] ?? 0);
    }
} catch (PDOException $e) {
    $diagnosticErrors[] = "Operating Expenses section: " . $e->getMessage();
    error_log("P&L report - expenses query failed: " . $e->getMessage());
}


/*
|--------------------------------------------------------------------------
| 4. QUARTERLY / YEARLY COMMISSION RECEIVED
|--------------------------------------------------------------------------
*/

$periodCommissionIncome = 0;
$periodCommissionBreakdown = [];

try {
    $pcStmt = $db->prepare("
        SELECT cpc.commission_type, c.name AS company_name, cpc.period_label, cpc.total_amount, cpc.received_date
        FROM company_period_commissions cpc
        JOIN companies c ON cpc.company_id = c.id
        WHERE cpc.tenant_id = :tenant AND cpc.status = 'received' AND cpc.received_date >= :start AND cpc.received_date <= :end
        ORDER BY cpc.received_date ASC
    ");
    $pcStmt->execute([':tenant' => $tenantId, ':start' => $startDate, ':end' => $endDate]);
    $periodCommissionBreakdown = $pcStmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($periodCommissionBreakdown as $pc) {
        $periodCommissionIncome += (float)($pc['total_amount'] ?? 0);
    }
} catch (PDOException $e) {
    error_log("P&L report - period commissions query failed: " . $e->getMessage());
}


/*
|--------------------------------------------------------------------------
| 5. OTHER INCOME
|--------------------------------------------------------------------------
*/

$otherIncome = 0;
$otherIncomeBreakdown = [];
$otherIncomeCount = 0;

try {
    $oiStmt = $db->prepare("
        SELECT COALESCE(SUM(amount), 0) AS total_other_income, COUNT(id) AS income_count
        FROM other_incomes
        WHERE tenant_id = :tenant AND income_date >= :start AND income_date < DATE_ADD(:end, INTERVAL 1 DAY) AND status = 'active'
    ");
    $oiStmt->execute([':tenant' => $tenantId, ':start' => $startDate, ':end' => $endDate]);
    $oiData = $oiStmt->fetch(PDO::FETCH_ASSOC);

    if ($oiData) {
        $otherIncome = (float)($oiData['total_other_income'] ?? 0);
        $otherIncomeCount = (int)($oiData['income_count'] ?? 0);
    }

    $oiBreakdownStmt = $db->prepare("
        SELECT COALESCE(NULLIF(category, ''), 'Other') AS category_name,
               COALESCE(SUM(amount), 0) AS category_total, COUNT(id) AS record_count
        FROM other_incomes
        WHERE tenant_id = :tenant AND income_date >= :start AND income_date < DATE_ADD(:end, INTERVAL 1 DAY) AND status = 'active'
        GROUP BY COALESCE(NULLIF(category, ''), 'Other')
        ORDER BY category_total DESC
    ");
    $oiBreakdownStmt->execute([':tenant' => $tenantId, ':start' => $startDate, ':end' => $endDate]);
    $otherIncomeBreakdown = $oiBreakdownStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $diagnosticErrors[] = "Other Income section: " . $e->getMessage();
    error_log("P&L report - other income query failed: " . $e->getMessage());
}


/*
|--------------------------------------------------------------------------
| 6 & 7. NET PROFIT / TOTAL COMMISSION
|--------------------------------------------------------------------------
*/

$netProfit = round($grossProfit - $totalExpenses + $commissionIncome + $periodCommissionIncome + $otherIncome, 2);
$totalCommissionIncome = round($commissionIncome + $periodCommissionIncome, 2);

?>

<!-- ============================================================
     STYLES
     - Colorful gradient design: SCREEN ONLY (.pnl-screen-sheet, no-print)
     - Standard black/white letterhead + statement: PRINT ONLY
       (.print-only-block), matching the ledger/daily/monthly/yearly
       report pages.
============================================================ -->
<style>
    .print-only-block { display: none; }

    /* ---------- Colorful screen design (unchanged) ---------- */
    .pnl-print-sheet { border-radius: 16px; overflow: hidden; }

    .pnl-letterhead {
        background: linear-gradient(120deg, #6D28D9 0%, #7C3AED 45%, #0891B2 100%);
        border-radius: 14px;
        padding: 20px 24px;
        margin-bottom: 20px;
        color: #fff;
        box-shadow: 0 10px 25px -8px rgba(109,40,217,0.45);
    }
    .pnl-letterhead * { color: #fff !important; }
    .pnl-letterhead .biz-logo {
        width: 58px; height: 58px; border-radius: 14px;
        background: rgba(255,255,255,0.18);
        border: 1px solid rgba(255,255,255,0.35);
        display: flex; align-items: center; justify-content: center;
        font-weight: 800; font-size: 1.3rem; overflow: hidden; flex-shrink: 0;
    }
    .pnl-letterhead .biz-logo img { width: 100%; height: 100%; object-fit: cover; display: block; }
    .pnl-letterhead .biz-name { font-size: 1.25rem; font-weight: 800; letter-spacing: 0.3px; margin-bottom: 2px; }
    .pnl-letterhead .biz-tagline,
    .pnl-letterhead .biz-address,
    .pnl-letterhead .biz-contact { font-size: 0.75rem; opacity: 0.92; line-height: 1.5; }
    .pnl-letterhead .doc-title {
        font-size: 0.8rem; font-weight: 800; text-transform: uppercase; letter-spacing: 1px;
        background: rgba(255,255,255,0.2); padding: 6px 14px; border-radius: 20px; display: inline-block;
    }
    .pnl-letterhead .doc-period { font-size: 0.78rem; opacity: 0.92; margin-top: 8px; }

    .pnl-section-card {
        display: flex; align-items: center; gap: 10px;
        border-radius: 10px; padding: 9px 14px;
        margin-top: 16px; margin-bottom: 8px;
        font-weight: 700; font-size: 0.85rem;
    }
    .pnl-section-card .icon-chip {
        width: 30px; height: 30px; border-radius: 8px;
        display: flex; align-items: center; justify-content: center;
        font-size: 0.85rem; flex-shrink: 0;
    }
    .pnl-section-card.revenue    { background: rgba(34,197,94,0.12); color: #16A34A; }
    .pnl-section-card.revenue .icon-chip { background: rgba(34,197,94,0.22); }
    .pnl-section-card.cogs       { background: rgba(239,68,68,0.10); color: #DC2626; }
    .pnl-section-card.cogs .icon-chip { background: rgba(239,68,68,0.20); }
    .pnl-section-card.commission { background: rgba(139,92,246,0.12); color: #7C3AED; }
    .pnl-section-card.commission .icon-chip { background: rgba(139,92,246,0.22); }
    .pnl-section-card.other      { background: rgba(34,197,94,0.12); color: #16A34A; }
    .pnl-section-card.other .icon-chip { background: rgba(34,197,94,0.22); }
    .pnl-section-card.expense    { background: rgba(239,68,68,0.10); color: #DC2626; }
    .pnl-section-card.expense .icon-chip { background: rgba(239,68,68,0.20); }

    .pnl-statement-box {
        border: 1px solid var(--border-color);
        border-radius: 12px;
        background: var(--bg-input);
        padding: 18px 20px;
    }

    .pnl-highlight-green {
        background: linear-gradient(90deg, rgba(34,197,94,0.16), rgba(34,197,94,0.06));
        border: 1px solid rgba(34,197,94,0.25);
        border-radius: 10px; padding: 12px 16px;
        color: #16A34A;
    }
    .pnl-highlight-net {
        background: linear-gradient(90deg, rgba(34,211,238,0.22), rgba(139,92,246,0.16));
        border: 1px solid rgba(34,211,238,0.3);
        border-radius: 12px; padding: 16px 18px;
        color: #0E7490;
    }
    .pnl-highlight-net.negative {
        background: linear-gradient(90deg, rgba(239,68,68,0.18), rgba(239,68,68,0.08));
        border-color: rgba(239,68,68,0.3);
        color: #DC2626;
    }
    [data-theme="light"] .pnl-highlight-green,
    [data-theme="light"] .pnl-highlight-net { color: inherit; }
    [data-theme="light"] .pnl-highlight-green { color: #15803D; }
    [data-theme="light"] .pnl-highlight-net { color: #0E7490; }
    [data-theme="light"] .pnl-highlight-net.negative { color: #B91C1C; }

    .pnl-calc-summary {
        border: 1px solid var(--border-color);
        border-radius: 12px;
        background: var(--bg-input);
    }

    .pnl-signatures { margin-top: 34px; }

    /* ---------- PRINT: standard black/white letterhead + statement ---------- */
    @media print {
        @page { size: A4; margin: 14mm 12mm; }

        body { background: #FFFFFF !important; }

        .print-only-block { display: block !important; }
        .no-print { display: none !important; }

        /* Hide the colorful screen version entirely when printing */
        .pnl-screen-only { display: none !important; }

        .text-danger   { color: #B91C1C !important; }
        .text-success  { color: #15803D !important; }
        .text-cyan     { color: #0369A1 !important; }
        .text-primary-light { color: #1D4ED8 !important; }
        .text-secondary { color: #475569 !important; }

        /* ---------- Letterhead ---------- */
        .print-letterhead {
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 3px solid #0F172A;
            padding-bottom: 12px;
            margin-bottom: 14px;
        }

        .print-letterhead .dealer-logo {
            max-height: 70px;
            max-width: 160px;
            object-fit: contain;
            margin-right: 16px;
        }

        .print-letterhead .dealer-block {
            display: flex;
            align-items: center;
        }

        .print-letterhead .dealer-name {
            font-size: 22px;
            font-weight: 800;
            color: #0F172A;
            margin: 0 0 2px 0;
            letter-spacing: 0.3px;
        }

        .print-letterhead .dealer-tagline {
            font-size: 11.5px;
            color: #475569;
            margin: 0 0 4px 0;
        }

        .print-letterhead .dealer-contact {
            font-size: 11px;
            color: #334155;
            line-height: 1.5;
        }

        .print-letterhead .doc-type-box { text-align: right; }

        .print-letterhead .doc-type-box .doc-title {
            font-size: 15px;
            font-weight: 700;
            color: #0F172A;
            text-transform: uppercase;
            letter-spacing: 1px;
            border: 2px solid #0F172A;
            padding: 5px 14px;
            display: inline-block;
        }

        .print-letterhead .doc-type-box .doc-date {
            font-size: 11px;
            color: #475569;
            margin-top: 6px;
        }

        /* ---------- Period meta line ---------- */
        .print-meta-grid {
            display: flex;
            justify-content: space-between;
            margin-bottom: 16px;
            font-size: 12px;
        }

        .print-meta-grid .meta-label {
            font-size: 10px;
            text-transform: uppercase;
            letter-spacing: 0.4px;
            color: #64748B;
            font-weight: 700;
            margin-bottom: 2px;
        }

        .print-meta-grid .meta-value {
            font-size: 13px;
            color: #0F172A;
            font-weight: 700;
        }

        /* ---------- Statement sections ---------- */
        .print-section-title {
            font-size: 11.5px;
            font-weight: 800;
            color: #0F172A;
            text-transform: uppercase;
            letter-spacing: 0.4px;
            border-bottom: 1px solid #94A3B8;
            padding-bottom: 3px;
            margin: 14px 0 6px 0;
        }

        .print-line-item {
            display: flex;
            justify-content: space-between;
            font-size: 11px;
            padding: 3px 0;
            border-bottom: 1px dashed #E2E8F0;
        }

        .print-line-item.sub {
            padding-left: 14px;
            color: #64748B;
            font-size: 10px;
        }

        .print-subtotal {
            display: flex;
            justify-content: space-between;
            font-weight: 800;
            font-size: 13px;
            border: 1px solid #94A3B8;
            border-radius: 4px;
            padding: 6px 10px;
            margin: 8px 0 4px 0;
            background: #F8FAFC;
        }

        .print-net-profit {
            display: flex;
            justify-content: space-between;
            font-weight: 800;
            font-size: 16px;
            border: 2px solid #0F172A;
            border-radius: 4px;
            padding: 10px 12px;
            margin-top: 16px;
            background: #F1F5F9;
        }

        .print-calc-box {
            margin-top: 16px;
            border: 1px solid #CBD5E1;
            border-radius: 6px;
            padding: 10px 12px;
        }

        .print-calc-box .calc-title {
            font-size: 10px;
            text-transform: uppercase;
            font-weight: 700;
            color: #64748B;
            margin-bottom: 6px;
        }

        .print-calc-box .calc-row {
            display: flex;
            justify-content: space-between;
            font-size: 11px;
            padding: 2px 0;
        }

        .print-calc-box .calc-row.total {
            border-top: 1px solid #94A3B8;
            margin-top: 4px;
            padding-top: 5px;
            font-weight: 800;
            font-size: 12.5px;
        }

        /* ---------- Footer ---------- */
        .print-footer-block {
            margin-top: 40px;
            display: flex;
            justify-content: space-between;
            font-size: 11px;
        }

        .print-footer-block .sig-box { width: 42%; text-align: center; }

        .print-footer-block .sig-line {
            border-top: 1px solid #0F172A;
            margin-top: 40px;
            padding-top: 4px;
            font-weight: 600;
            color: #0F172A;
        }

        .print-generated-note {
            margin-top: 24px;
            font-size: 9.5px;
            color: #94A3B8;
            text-align: center;
            border-top: 1px dashed #CBD5E1;
            padding-top: 8px;
        }
    }
</style>

<!-- ============================================================
     PAGE HEADER (screen only)
============================================================ -->

<div class="page-header-container no-print">
    <div>
        <h2 class="page-title">Profit & Loss Financial Statement (লাভ-ক্ষতি বিবরণী)</h2>
        <div class="page-subtitle">Standard financial income statement showing Gross Profit, Commission, Other Income, Expenses, and Net Profit.</div>
    </div>
    <div class="d-flex gap-2">
        <button type="button" class="btn btn-primary-custom" onclick="window.print()">
            <i class="fa-solid fa-print me-1"></i> Print P&L Statement
        </button>
    </div>
</div>

<div class="no-print" style="font-size: 12px; color: var(--text-secondary, #64748B); margin-top: -10px; margin-bottom: 16px;">
    <i class="fa-solid fa-circle-info me-1"></i>
    আপনার ব্রাউজারের Print ডায়ালগে "Headers and footers" অপশনটা বন্ধ (uncheck) রাখুন — নাহলে ব্রাউজার নিজে থেকে উপরে পেজ টাইটেল আর নিচে URL দেখাবে, যেটা এই ডিজাইনের অংশ না।
</div>


<!-- ============================================================
     DIAGNOSTIC ERRORS
============================================================ -->

<?php if (!empty($diagnosticErrors)): ?>
    <div class="alert alert-danger no-print" role="alert">
        <strong><i class="fa-solid fa-triangle-exclamation me-1"></i> This report has incomplete data due to a database error:</strong>
        <ul class="mb-0 mt-2">
            <?php foreach ($diagnosticErrors as $err): ?>
                <li class="small font-monospace"><?php echo htmlspecialchars($err); ?></li>
            <?php endforeach; ?>
        </ul>
        <div class="small mt-2">Please verify that all required tables and columns exist in your database.</div>
    </div>
<?php endif; ?>

<?php if ($bizLogoPath !== '' && !$bizLogoUrl): ?>
    <div class="alert alert-warning no-print" role="alert">
        <i class="fa-solid fa-triangle-exclamation me-1"></i>
        A logo path is set in Settings (<code><?php echo htmlspecialchars($bizLogoPath); ?></code>) but it doesn't look like a valid URL or file path — showing the initials badge instead.
    </div>
<?php endif; ?>


<!-- ============================================================
     DATE FILTER
============================================================ -->

<div class="dark-card mb-4 no-print">
    <form method="GET" action="" class="row g-3 align-items-end">
        <div class="col-md-5">
            <label for="start_date" class="form-label-custom">From Date</label>
            <input type="date" name="start_date" id="start_date" class="form-control form-control-custom" value="<?php echo htmlspecialchars($startDate); ?>" required>
        </div>
        <div class="col-md-5">
            <label for="end_date" class="form-label-custom">To Date</label>
            <input type="date" name="end_date" id="end_date" class="form-control form-control-custom" value="<?php echo htmlspecialchars($endDate); ?>" required>
        </div>
        <div class="col-md-2">
            <button type="submit" class="btn btn-primary-custom w-100">
                <i class="fa-solid fa-filter me-1"></i> Generate
            </button>
        </div>
    </form>
</div>


<!-- ============================================================
     QUICK FINANCIAL SUMMARY (screen only)
============================================================ -->

<div class="row g-3 mb-4 no-print">
    <div class="col-md-3">
        <div class="dark-card py-3 text-center">
            <div class="text-secondary small fw-bold text-uppercase">Gross Profit</div>
            <div class="fs-4 fw-bold text-success"><?php echo formatBDT($grossProfit); ?></div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="dark-card py-3 text-center">
            <div class="text-secondary small fw-bold text-uppercase">Commission Income</div>
            <div class="fs-4 fw-bold text-primary-light"><?php echo formatBDT($totalCommissionIncome); ?></div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="dark-card py-3 text-center" style="border-left: 3px solid var(--success);">
            <div class="text-secondary small fw-bold text-uppercase">Other Income</div>
            <div class="fs-4 fw-bold text-success"><?php echo formatBDT($otherIncome); ?></div>
        
        </div>
    </div>
    <div class="col-md-3">
        <div class="dark-card py-3 text-center">
            <div class="text-secondary small fw-bold text-uppercase">Net Business Profit</div>
            <div class="fs-4 fw-bold <?php echo ($netProfit >= 0) ? 'text-cyan' : 'text-danger'; ?>"><?php echo formatBDT($netProfit); ?></div>
        </div>
    </div>
</div>


<!-- ============================================================
     COLORFUL P&L STATEMENT — SCREEN ONLY
============================================================ -->

<div class="dark-card p-4 p-md-5 pnl-print-sheet pnl-screen-only" style="max-width: 850px; margin: 0 auto;">

    <div class="pnl-letterhead d-flex flex-column flex-sm-row justify-content-between align-items-sm-center gap-3">

        <div class="d-flex align-items-center gap-3">
            <div class="biz-logo" id="bizLogoBox">
                <?php if ($bizLogoUrl): ?>
                    <img src="<?php echo htmlspecialchars($bizLogoUrl); ?>"
                         alt="<?php echo htmlspecialchars($bizName); ?> logo"
                         onerror="this.remove(); document.getElementById('bizLogoFallback').style.display='flex';">
                    <span id="bizLogoFallback" style="display:none; align-items:center; justify-content:center; width:100%; height:100%;"><?php echo $bizInitials; ?></span>
                <?php else: ?>
                    <?php echo $bizInitials; ?>
                <?php endif; ?>
            </div>

            <div>
                <div class="biz-name"><?php echo htmlspecialchars($bizName); ?></div>
                <?php if ($bizTagline): ?><div class="biz-tagline"><?php echo htmlspecialchars($bizTagline); ?></div><?php endif; ?>
                <?php if ($bizAddress): ?><div class="biz-address"><?php echo htmlspecialchars($bizAddress); ?></div><?php endif; ?>
                <?php if ($bizMobile || $bizEmail): ?>
                    <div class="biz-contact">
                        <?php if ($bizMobile): ?><i class="fa-solid fa-phone me-1"></i> <?php echo htmlspecialchars($bizMobile); ?><?php endif; ?>
                        <?php if ($bizMobile && $bizEmail): ?> &nbsp;|&nbsp; <?php endif; ?>
                        <?php if ($bizEmail): ?><i class="fa-solid fa-envelope me-1"></i> <?php echo htmlspecialchars($bizEmail); ?><?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="text-sm-end">
            <span class="doc-title">Statement of Profit &amp; Loss</span>
            <div class="doc-period">
                For the period from <strong><?php echo formatDate($startDate); ?></strong>
                to <strong><?php echo formatDate($endDate); ?></strong>
            </div>
        </div>

    </div>


    <div class="pnl-statement-box">

        <div class="pnl-section-card revenue">
            <span class="icon-chip"><i class="fa-solid fa-cart-shopping"></i></span>
            <span>1. Operating Revenue (বিক্রয় রাজস্ব)</span>
        </div>
        <div class="d-flex justify-content-between fw-bold small mb-1" style="color: var(--text-primary);">
            <span>Total Cement Sales Invoiced</span>
            <span><?php echo formatBDT($totalSales); ?></span>
        </div>

        <div class="pnl-section-card cogs">
            <span class="icon-chip"><i class="fa-solid fa-truck-ramp-box"></i></span>
            <span>2. Cost of Goods Sold (COGS - পন্যের ক্রয়মূল্য)</span>
        </div>
        <div class="d-flex justify-content-between text-danger fw-bold small mb-1">
            <span>Purchase & Landed Cost of Cement Sold</span>
            <span>- <?php echo formatBDT($totalCogs); ?></span>
        </div>

        <div class="pnl-highlight-green d-flex justify-content-between fw-bold fs-5 my-3">
            <span>GROSS PROFIT (মোট লাভ)</span>
            <span><?php echo formatBDT($grossProfit); ?></span>
        </div>

        <div class="pnl-section-card commission">
            <span class="icon-chip"><i class="fa-solid fa-handshake"></i></span>
            <span>3. Supplier Target Incentive & Commission Income (কমিশন আয়)</span>
        </div>
        <div class="d-flex justify-content-between text-primary-light fw-bold small mb-1">
            <span>Proportional Monthly Target Achievement Commission</span>
            <span>+ <?php echo formatBDT($commissionIncome); ?></span>
        </div>

        <?php if ($periodCommissionIncome > 0): ?>
            <div class="d-flex justify-content-between text-primary-light fw-bold small mt-2 mb-1">
                <span>3b. Period Commission Received (৩-মাস / বার্ষিক কমিশন)</span>
                <span>+ <?php echo formatBDT($periodCommissionIncome); ?></span>
            </div>
            <?php foreach ($periodCommissionBreakdown as $pc): ?>
                <div class="d-flex justify-content-between text-secondary ps-3 py-1 small">
                    <span>
                        <?php echo htmlspecialchars($pc['company_name'] ?? ''); ?>
                        — <?php echo htmlspecialchars($pc['period_label'] ?? ''); ?>
                        (<?php echo (($pc['commission_type'] ?? '') === 'yearly') ? 'Yearly' : '3-Month'; ?>,
                        received <?php echo formatDate($pc['received_date']); ?>)
                    </span>
                    <span><?php echo formatBDT($pc['total_amount'] ?? 0); ?></span>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>

        <div class="pnl-section-card other">
            <span class="icon-chip"><i class="fa-solid fa-sack-dollar"></i></span>
            <span>3c. Other Income (অন্যান্য আয়)</span>
        </div>
        <?php if (empty($otherIncomeBreakdown)): ?>
            <div class="text-secondary py-1 small">No other income recorded in this period.</div>
        <?php else: ?>
            <?php foreach ($otherIncomeBreakdown as $oi): ?>
                <div class="d-flex justify-content-between text-success fw-bold small py-1">
                    <span>
                        <?php echo htmlspecialchars($oi['category_name'] ?? 'Other'); ?>
                        <?php if (isset($oi['record_count']) && (int)$oi['record_count'] > 1): ?>
                            <span class="opacity-75 fw-normal">(<?php echo (int)$oi['record_count']; ?> records)</span>
                        <?php endif; ?>
                    </span>
                    <span>+ <?php echo formatBDT($oi['category_total'] ?? 0); ?></span>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>

        <div class="pnl-section-card expense">
            <span class="icon-chip"><i class="fa-solid fa-wallet"></i></span>
            <span>4. Operating & Administrative Expenses (পরিচালন ব্যয়)</span>
        </div>
        <?php if (empty($expenseBreakdown)): ?>
            <div class="text-secondary py-1 small">No expenses recorded in this period.</div>
        <?php else: ?>
            <?php foreach ($expenseBreakdown as $eb): ?>
                <div class="d-flex justify-content-between text-danger fw-bold small py-1">
                    <span><?php echo htmlspecialchars($eb['category_name'] ?? ''); ?></span>
                    <span>- <?php echo formatBDT($eb['cat_total'] ?? 0); ?></span>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>

        <div class="pnl-highlight-net <?php echo ($netProfit < 0) ? 'negative' : ''; ?> d-flex justify-content-between fw-bold fs-4 mt-4">
            <span>NET BUSINESS PROFIT (নিট মুনাফা)</span>
            <span><?php echo formatBDT($netProfit); ?></span>
        </div>

    </div>


    <div class="mt-4 p-3 pnl-calc-summary">
        <div class="small text-secondary mb-2 fw-semibold">Net Profit Calculation</div>

        <div class="d-flex justify-content-between small" style="color: var(--text-primary);">
            <span>Gross Profit</span>
            <span><?php echo formatBDT($grossProfit); ?></span>
        </div>
        <div class="d-flex justify-content-between small text-danger">
            <span>Less: Operating Expenses</span>
            <span>- <?php echo formatBDT($totalExpenses); ?></span>
        </div>
        <div class="d-flex justify-content-between small text-primary-light">
            <span>Add: Monthly Commission</span>
            <span>+ <?php echo formatBDT($commissionIncome); ?></span>
        </div>
        <div class="d-flex justify-content-between small text-primary-light">
            <span>Add: Received Quarterly/Yearly Commission</span>
            <span>+ <?php echo formatBDT($periodCommissionIncome); ?></span>
        </div>
        <div class="d-flex justify-content-between small text-success">
            <span>Add: Other Income</span>
            <span>+ <?php echo formatBDT($otherIncome); ?></span>
        </div>
        <div class="d-flex justify-content-between fw-bold mt-2 pt-2 border-top border-secondary border-opacity-25" style="color: var(--text-primary);">
            <span>Net Business Profit</span>
            <span class="<?php echo ($netProfit >= 0) ? 'text-cyan' : 'text-danger'; ?>"><?php echo formatBDT($netProfit); ?></span>
        </div>
    </div>


    <div class="row pt-5 mt-4 text-center text-secondary small pnl-signatures">
        <div class="col-6">
            <div class="border-top border-secondary border-opacity-50 pt-2 mx-auto" style="max-width: 180px;">Prepared by Accountant</div>
        </div>
        <div class="col-6">
            <div class="border-top border-secondary border-opacity-50 pt-2 mx-auto" style="max-width: 180px;">Proprietor / Managing Director</div>
        </div>
    </div>

</div>


<!-- ============================================================
     PRINT-ONLY STATEMENT — standard letterhead, matching the
     ledger / daily / monthly / yearly report pages
============================================================ -->

<div class="print-only-block">

    <div class="print-letterhead">
        <div class="dealer-block">
            <?php if ($bizLogoUrl): ?>
                <img src="<?php echo htmlspecialchars($bizLogoUrl); ?>" alt="<?php echo htmlspecialchars($bizName); ?>" class="dealer-logo">
            <?php endif; ?>
            <div>
                <div class="dealer-name"><?php echo htmlspecialchars($bizName); ?></div>
                <?php if ($bizTagline): ?><div class="dealer-tagline"><?php echo htmlspecialchars($bizTagline); ?></div><?php endif; ?>
                <div class="dealer-contact">
                    <?php if ($bizAddress): ?><?php echo htmlspecialchars($bizAddress); ?><br><?php endif; ?>
                    <?php if ($bizMobile): ?>Phone: <?php echo htmlspecialchars($bizMobile); ?><?php endif; ?>
                    <?php if ($bizMobile && $bizEmail): ?> &nbsp;|&nbsp; <?php endif; ?>
                    <?php if ($bizEmail): ?>Email: <?php echo htmlspecialchars($bizEmail); ?><?php endif; ?>
                </div>
            </div>
        </div>
        <div class="doc-type-box">
            <div class="doc-title">Statement of Profit &amp; Loss</div>
            <div class="doc-date">Printed: <?php echo htmlspecialchars($printedAt); ?><?php echo $printedBy ? ' by ' . htmlspecialchars($printedBy) : ''; ?></div>
        </div>
    </div>

    <div class="print-meta-grid">
        <div>
            <div class="meta-label">Statement Period</div>
            <div class="meta-value"><?php echo formatDate($startDate); ?> &nbsp;to&nbsp; <?php echo formatDate($endDate); ?></div>
        </div>
        <div style="text-align:right;">
            <div class="meta-label">Net Business Profit</div>
            <div class="meta-value"><?php echo formatBDT($netProfit); ?></div>
        </div>
    </div>

    <!-- 1. Revenue -->
    <div class="print-section-title">1. Operating Revenue (বিক্রয় রাজস্ব)</div>
    <div class="print-line-item">
        <span>Total Cement Sales Invoiced</span>
        <span><?php echo formatBDT($totalSales); ?></span>
    </div>

    <!-- 2. COGS -->
    <div class="print-section-title">2. Cost of Goods Sold (COGS)</div>
    <div class="print-line-item">
        <span>Purchase & Landed Cost of Cement Sold</span>
        <span>- <?php echo formatBDT($totalCogs); ?></span>
    </div>

    <div class="print-subtotal">
        <span>GROSS PROFIT (মোট লাভ)</span>
        <span><?php echo formatBDT($grossProfit); ?></span>
    </div>

    <!-- 3. Commission -->
    <div class="print-section-title">3. Commission Income (কমিশন আয়)</div>
    <div class="print-line-item">
        <span>Monthly Target Achievement Commission</span>
        <span>+ <?php echo formatBDT($commissionIncome); ?></span>
    </div>

    <?php if ($periodCommissionIncome > 0): ?>
        <div class="print-line-item">
            <span>Quarterly/Yearly Commission Received</span>
            <span>+ <?php echo formatBDT($periodCommissionIncome); ?></span>
        </div>
        <?php foreach ($periodCommissionBreakdown as $pc): ?>
            <div class="print-line-item sub">
                <span>
                    <?php echo htmlspecialchars($pc['company_name'] ?? ''); ?>
                    — <?php echo htmlspecialchars($pc['period_label'] ?? ''); ?>
                    (<?php echo (($pc['commission_type'] ?? '') === 'yearly') ? 'Yearly' : '3-Month'; ?>,
                    received <?php echo formatDate($pc['received_date']); ?>)
                </span>
                <span><?php echo formatBDT($pc['total_amount'] ?? 0); ?></span>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>

    <!-- 3c. Other Income -->
    <div class="print-section-title">4. Other Income (অন্যান্য আয়)</div>
    <?php if (empty($otherIncomeBreakdown)): ?>
        <div class="print-line-item"><span>No other income recorded in this period.</span><span>—</span></div>
    <?php else: ?>
        <?php foreach ($otherIncomeBreakdown as $oi): ?>
            <div class="print-line-item">
                <span>
                    <?php echo htmlspecialchars($oi['category_name'] ?? 'Other'); ?>
                    <?php if (isset($oi['record_count']) && (int)$oi['record_count'] > 1): ?>
                        (<?php echo (int)$oi['record_count']; ?> records)
                    <?php endif; ?>
                </span>
                <span>+ <?php echo formatBDT($oi['category_total'] ?? 0); ?></span>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>

    <!-- 5. Expenses -->
    <div class="print-section-title">5. Operating & Administrative Expenses (পরিচালন ব্যয়)</div>
    <?php if (empty($expenseBreakdown)): ?>
        <div class="print-line-item"><span>No expenses recorded in this period.</span><span>—</span></div>
    <?php else: ?>
        <?php foreach ($expenseBreakdown as $eb): ?>
            <div class="print-line-item">
                <span><?php echo htmlspecialchars($eb['category_name'] ?? ''); ?></span>
                <span>- <?php echo formatBDT($eb['cat_total'] ?? 0); ?></span>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>

    <div class="print-net-profit">
        <span>NET BUSINESS PROFIT (নিট মুনাফা)</span>
        <span><?php echo formatBDT($netProfit); ?></span>
    </div>

    <!-- Calculation summary -->
    <div class="print-calc-box">
        <div class="calc-title">Net Profit Calculation</div>
        <div class="calc-row"><span>Gross Profit</span><span><?php echo formatBDT($grossProfit); ?></span></div>
        <div class="calc-row"><span>Less: Operating Expenses</span><span>- <?php echo formatBDT($totalExpenses); ?></span></div>
        <div class="calc-row"><span>Add: Monthly Commission</span><span>+ <?php echo formatBDT($commissionIncome); ?></span></div>
        <div class="calc-row"><span>Add: Received Quarterly/Yearly Commission</span><span>+ <?php echo formatBDT($periodCommissionIncome); ?></span></div>
        <div class="calc-row"><span>Add: Other Income</span><span>+ <?php echo formatBDT($otherIncome); ?></span></div>
        <div class="calc-row total"><span>Net Business Profit</span><span><?php echo formatBDT($netProfit); ?></span></div>
    </div>

    <div class="print-footer-block">
        <div class="sig-box">
            <div class="sig-line">Prepared by Accountant</div>
        </div>
        <div class="sig-box">
            <div class="sig-line">Proprietor / Managing Director — <?php echo htmlspecialchars($bizName); ?></div>
        </div>
    </div>

    <div class="print-generated-note">
        This is a computer-generated financial statement from <?php echo htmlspecialchars($bizName); ?>'s accounting system and does not require a physical stamp unless otherwise requested.
    </div>

</div>

<script>
    (function () {
        var originalTitle = document.title;
        var printTitle = <?php echo json_encode(($bizName ? $bizName . ' — ' : '') . 'Statement of Profit & Loss — ' . formatDate($startDate) . ' to ' . formatDate($endDate)); ?>;

        window.addEventListener('beforeprint', function () {
            document.title = printTitle;
        });
        window.addEventListener('afterprint', function () {
            document.title = originalTitle;
        });
    })();
</script>


<?php
require_once __DIR__ . '/../../includes/footer.php';
?>