<?php
/**
 * Maruf Traders - Monthly Financial Report
 * (Updated: adds a professional print-only letterhead/memo header,
 *  matching the retailer ledger & daily report's print design.)
 *
 * Includes:
 * - Monthly Sales
 * - Collections
 * - Expenses
 * - Monthly Target Commission
 * - Quarterly / Yearly Received Commission
 * - Other Income
 * - Net Profit
 *
 * Commission Rule:
 * - Monthly Commission remains unchanged.
 * - Quarterly / Yearly Commission affects Net Profit only
 *   in the month when it is marked as RECEIVED.
 * - Other Income affects Net Profit in the month of income_date.
 */

defined('APP_INIT') or define('APP_INIT', true);

$pageTitle = 'Monthly Business Report';
$breadcrumb = 'Monthly Report';
$activeMenu = 'report_monthly';

require_once __DIR__ . '/../../includes/header.php';
requirePermission('reports.financial');

$db = Database::getConnection();

/*
|--------------------------------------------------------------------------
| Selected Month / Year
|--------------------------------------------------------------------------
*/

$selectedMonth = (int)($_GET['month'] ?? date('n'));
$selectedYear  = (int)($_GET['year'] ?? date('Y'));

/*
|--------------------------------------------------------------------------
| Validate Month / Year
|--------------------------------------------------------------------------
*/

if ($selectedMonth < 1 || $selectedMonth > 12) {
    $selectedMonth = (int)date('n');
}

if ($selectedYear < 2000 || $selectedYear > 2100) {
    $selectedYear = (int)date('Y');
}

/*
|--------------------------------------------------------------------------
| Date Range
|--------------------------------------------------------------------------
*/

$startDate = sprintf(
    '%04d-%02d-01',
    $selectedYear,
    $selectedMonth
);

$endDate = date(
    'Y-m-t',
    strtotime($startDate)
);

$tenantId = TenantContext::id();

/*
|--------------------------------------------------------------------------
| SALES
|--------------------------------------------------------------------------
*/

$sStmt = $db->prepare("
    SELECT
        COALESCE(SUM(total_amount), 0) AS total_sales,
        COALESCE(SUM(paid_amount), 0) AS total_paid,
        COALESCE(SUM(due_amount), 0) AS total_due,
        COALESCE(SUM(gross_profit), 0) AS gross_profit,
        COUNT(id) AS total_invoices
    FROM sales
    WHERE tenant_id = :tenant
      AND sale_date BETWEEN :start AND :end
      AND status = 'active'
");

$sStmt->execute([
    ':tenant' => $tenantId,
    ':start'  => $startDate,
    ':end'    => $endDate
]);

$sales = $sStmt->fetch();

/*
|--------------------------------------------------------------------------
| COLLECTIONS
|--------------------------------------------------------------------------
*/

$cStmt = $db->prepare("
    SELECT COALESCE(SUM(amount), 0)
    FROM collections
    WHERE tenant_id = :tenant
      AND collection_date BETWEEN :start AND :end
      AND status = 'active'
");

$cStmt->execute([
    ':tenant' => $tenantId,
    ':start'  => $startDate,
    ':end'    => $endDate
]);

$collections = (float)$cStmt->fetchColumn();

/*
|--------------------------------------------------------------------------
| EXPENSES
|--------------------------------------------------------------------------
*/

$eStmt = $db->prepare("
    SELECT COALESCE(SUM(amount), 0)
    FROM expenses
    WHERE tenant_id = :tenant
      AND expense_date BETWEEN :start AND :end
      AND status = 'active'
");

$eStmt->execute([
    ':tenant' => $tenantId,
    ':start'  => $startDate,
    ':end'    => $endDate
]);

$expenses = (float)$eStmt->fetchColumn();

/*
|--------------------------------------------------------------------------
| OTHER INCOME
|--------------------------------------------------------------------------
*/

$oiStmt = $db->prepare("
    SELECT
        COALESCE(SUM(amount), 0) AS total_other_income,
        COUNT(id) AS other_income_count
    FROM other_incomes
    WHERE tenant_id = :tenant
      AND income_date BETWEEN :start AND :end
      AND status = 'active'
");

$oiStmt->execute([
    ':tenant' => $tenantId,
    ':start'  => $startDate,
    ':end'    => $endDate
]);

$otherIncomeData = $oiStmt->fetch();

$otherIncome = (float)($otherIncomeData['total_other_income'] ?? 0);
$otherIncomeCount = (int)($otherIncomeData['other_income_count'] ?? 0);

/*
|--------------------------------------------------------------------------
| TARGETS & MONTHLY COMMISSION
|--------------------------------------------------------------------------
*/

$tStmt = $db->prepare("
    SELECT
        COALESCE(SUM(target_quantity), 0) AS tgt_bags,
        COALESCE(SUM(actual_sales_quantity), 0) AS act_bags,
        COALESCE(SUM(estimated_commission), 0) AS commission
    FROM monthly_targets
    WHERE tenant_id = :tenant
      AND target_month = :m
      AND target_year = :y
");

$tStmt->execute([
    ':tenant' => $tenantId,
    ':m'      => $selectedMonth,
    ':y'      => $selectedYear
]);

$target = $tStmt->fetch();

/*
|--------------------------------------------------------------------------
| QUARTERLY / YEARLY COMMISSION
|--------------------------------------------------------------------------
*/

$pcReceivedStmt = $db->prepare("
    SELECT
        COALESCE(SUM(total_amount), 0) AS received_amount,
        COUNT(id) AS received_count
    FROM company_period_commissions
    WHERE tenant_id = :tenant
      AND status = 'received'
      AND received_date BETWEEN :start AND :end
");

$pcReceivedStmt->execute([
    ':tenant' => $tenantId,
    ':start'  => $startDate,
    ':end'    => $endDate
]);

$periodCommissionReceived = $pcReceivedStmt->fetch();

$periodCommission = (float)(
    $periodCommissionReceived['received_amount'] ?? 0
);

$periodCommissionReceivedCount = (int)(
    $periodCommissionReceived['received_count'] ?? 0
);

/*
|--------------------------------------------------------------------------
| PENDING QUARTERLY / YEARLY COMMISSION
|--------------------------------------------------------------------------
*/

$pcPendingStmt = $db->prepare("
    SELECT
        COALESCE(SUM(total_amount), 0) AS pending_amount,
        COUNT(id) AS pending_count
    FROM company_period_commissions
    WHERE tenant_id = :tenant
      AND status = 'pending'
");
$pcPendingStmt->execute([':tenant' => $tenantId]);

$periodCommissionPending = $pcPendingStmt->fetch();

$pendingPeriodCommission = (float)(
    $periodCommissionPending['pending_amount'] ?? 0
);

$pendingPeriodCommissionCount = (int)(
    $periodCommissionPending['pending_count'] ?? 0
);

/*
|--------------------------------------------------------------------------
| FINANCIAL CALCULATIONS
|--------------------------------------------------------------------------
*/

$grossProfit = (float)($sales['gross_profit'] ?? 0);

$monthlyCommission = (float)(
    $target['commission'] ?? 0
);

/*
 * Monthly Commission + Received Quarterly/Yearly Commission
 */
$totalCommissionIncome = round(
    $monthlyCommission + $periodCommission,
    2
);

/*
|--------------------------------------------------------------------------
| NET PROFIT
|--------------------------------------------------------------------------
|
| Gross Profit
| + Monthly Commission
| + Received Quarterly / Yearly Commission
| + Other Income
| - Expenses
|
*/

$netProfit = round(
    $grossProfit
    - $expenses
    + $totalCommissionIncome
    + $otherIncome,
    2
);

/*
|--------------------------------------------------------------------------
| DEALER INFO FROM SETTINGS (same keys as the ledger & daily report pages —
| verify against your `settings` table if they don't match)
|--------------------------------------------------------------------------
*/

$dealerName    = getSetting('business_name', '');
$dealerTagline = getSetting('business_title', '');
$dealerAddress = getSetting('business_address', '');
$dealerPhone   = getSetting('business_mobile', '');
$dealerEmail   = getSetting('business_email', '');
$dealerLogo    = getSetting('logo_path', '');

$dealerLogoUrl = '';
if (!empty($dealerLogo)) {
    $dealerLogoUrl = (stripos($dealerLogo, 'http://') === 0 || stripos($dealerLogo, 'https://') === 0)
        ? $dealerLogo
        : rtrim(BASE_URL, '/') . '/' . ltrim($dealerLogo, '/');
}

$printedBy = $_SESSION['user_name'] ?? '';
$printedAt = date('d M, Y h:i A');

?>

<style>
    .print-only-block { display: none; }

    @media print {
        @page {
            size: A4;
            margin: 12mm 10mm;
        }

        body { background: #FFFFFF !important; }

        .print-only-block { display: block !important; }
        .no-print { display: none !important; }

        .dark-card {
            background: #FFFFFF !important;
            border: none !important;
            box-shadow: none !important;
            padding: 0 !important;
            color: #000000 !important;
            margin-bottom: 12px !important;
        }

        .row.g-3, .row.g-4 { margin: 0 !important; }
        .row.g-3 > div, .row.g-4 > div { padding: 4px !important; }

        .profit-breakdown-box {
            border: 1px solid #CBD5E1 !important;
            padding: 8px 10px !important;
            border-radius: 4px;
        }

        .profit-item {
            display: flex !important;
            justify-content: space-between !important;
            padding: 4px 0 !important;
            border-bottom: 1px dashed #E2E8F0 !important;
            font-size: 11px !important;
        }

        .profit-item.net-profit {
            border-top: 2px solid #0F172A !important;
            border-bottom: none !important;
            padding-top: 6px !important;
            margin-top: 4px !important;
        }

        .text-danger   { color: #B91C1C !important; }
        .text-success  { color: #15803D !important; }
        .text-cyan     { color: #0369A1 !important; }
        .text-info     { color: #0369A1 !important; }
        .text-primary-light { color: #1D4ED8 !important; }
        .text-secondary { color: #475569 !important; }
        .text-warning  { color: #B45309 !important; }

        .badge-custom {
            display: inline-block;
            border: 1px solid #94A3B8 !important;
            background: #FFFFFF !important;
            color: #0F172A !important;
            font-size: 8px !important;
            padding: 1px 5px !important;
        }

        .dark-card { break-inside: avoid; page-break-inside: avoid; }

        /* ---------- Letterhead ---------- */
        .print-letterhead {
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 3px solid #0F172A;
            padding-bottom: 10px;
            margin-bottom: 12px;
        }

        .print-letterhead .dealer-logo {
            max-height: 60px;
            max-width: 150px;
            object-fit: contain;
            margin-right: 14px;
        }

        .print-letterhead .dealer-block {
            display: flex;
            align-items: center;
        }

        .print-letterhead .dealer-name {
            font-size: 20px;
            font-weight: 800;
            color: #0F172A;
            margin: 0 0 2px 0;
        }

        .print-letterhead .dealer-tagline {
            font-size: 11px;
            color: #475569;
            margin: 0 0 4px 0;
        }

        .print-letterhead .dealer-contact {
            font-size: 10.5px;
            color: #334155;
            line-height: 1.4;
        }

        .print-letterhead .doc-type-box { text-align: right; }

        .print-letterhead .doc-type-box .doc-title {
            font-size: 14px;
            font-weight: 700;
            color: #0F172A;
            text-transform: uppercase;
            letter-spacing: 1px;
            border: 2px solid #0F172A;
            padding: 4px 12px;
            display: inline-block;
        }

        .print-letterhead .doc-type-box .doc-date {
            font-size: 10.5px;
            color: #475569;
            margin-top: 5px;
        }

        /* ---------- Meta line ---------- */
        .print-meta-grid {
            display: flex;
            justify-content: space-between;
            margin-bottom: 14px;
            font-size: 12px;
        }

        .print-meta-grid .meta-label {
            font-size: 9.5px;
            text-transform: uppercase;
            letter-spacing: 0.4px;
            color: #64748B;
            font-weight: 700;
        }

        .print-meta-grid .meta-value {
            font-size: 14px;
            color: #0F172A;
            font-weight: 800;
        }

        /* ---------- KPI summary strip ---------- */
        .print-summary-strip {
            display: flex;
            gap: 10px;
            margin-bottom: 14px;
        }

        .print-summary-strip .sum-box {
            flex: 1;
            border: 1px solid #CBD5E1;
            border-radius: 4px;
            padding: 7px 9px;
            text-align: center;
        }

        .print-summary-strip .sum-box .sum-label {
            font-size: 9px;
            text-transform: uppercase;
            color: #64748B;
            font-weight: 700;
        }

        .print-summary-strip .sum-box .sum-value {
            font-size: 14px;
            font-weight: 800;
            color: #0F172A;
            margin-top: 2px;
        }

        .print-summary-strip .sum-box.net-box { border-color: #0F172A; }

        /* ---------- Section title ---------- */
        .print-section-title {
            font-size: 11.5px;
            font-weight: 800;
            color: #0F172A;
            text-transform: uppercase;
            letter-spacing: 0.4px;
            border-bottom: 1px solid #94A3B8;
            padding-bottom: 3px;
            margin: 10px 0 6px 0;
        }

        /* ---------- Pending commission note (informational, shown in print too) ---------- */
        .print-pending-note {
            border: 1px dashed #B45309;
            border-radius: 4px;
            padding: 6px 10px;
            font-size: 10.5px;
            color: #7C2D12;
            margin-bottom: 12px;
        }

        /* ---------- Footer ---------- */
        .print-footer-block {
            margin-top: 30px;
            display: flex;
            justify-content: space-between;
            font-size: 11px;
        }

        .print-footer-block .sig-box { width: 42%; text-align: center; }

        .print-footer-block .sig-line {
            border-top: 1px solid #0F172A;
            margin-top: 36px;
            padding-top: 4px;
            font-weight: 600;
            color: #0F172A;
        }

        .print-generated-note {
            margin-top: 20px;
            font-size: 9px;
            color: #94A3B8;
            text-align: center;
            border-top: 1px dashed #CBD5E1;
            padding-top: 6px;
        }
    }
</style>

<div class="page-header-container no-print">
    <div>
        <h2 class="page-title">
            Monthly Business Report (মাসিক ব্যবসায়িক রিপোর্ট)
        </h2>

        <div class="page-subtitle">
            Monthly sales volume, collection, expenses, commission,
            other income, and net profit overview.
        </div>
    </div>

    <div class="d-flex gap-2">
        <button
            type="button"
            class="btn btn-primary-custom"
            onclick="window.print()"
        >
            <i class="fa-solid fa-print me-1"></i>
            Print Monthly Summary
        </button>
    </div>
</div>

<div class="no-print" style="font-size: 12px; color: var(--text-secondary, #64748B); margin-top: -10px; margin-bottom: 16px;">
    <i class="fa-solid fa-circle-info me-1"></i>
    আপনার ব্রাউজারের Print ডায়ালগে "Headers and footers" অপশনটা বন্ধ (uncheck) রাখুন — নাহলে ব্রাউজার নিজে থেকে উপরে পেজ টাইটেল আর নিচে URL দেখাবে, যেটা এই ডিজাইনের অংশ না।
</div>


<!-- ============================================================
     MONTH SELECTOR
============================================================ -->

<div class="dark-card mb-4 no-print">

    <form method="GET" action="" class="row g-3 align-items-end">

        <div class="col-md-5">

            <label
                for="month"
                class="form-label-custom"
            >
                Select Month
            </label>

            <select
                name="month"
                id="month"
                class="form-select form-select-custom"
                onchange="this.form.submit()"
            >

                <?php for ($m = 1; $m <= 12; $m++): ?>

                    <option
                        value="<?php echo $m; ?>"
                        <?php echo ($m == $selectedMonth) ? 'selected' : ''; ?>
                    >
                        <?php
                        echo date(
                            'F',
                            mktime(0, 0, 0, $m, 10)
                        );
                        ?>
                    </option>

                <?php endfor; ?>

            </select>

        </div>


        <div class="col-md-5">

            <label
                for="year"
                class="form-label-custom"
            >
                Select Year
            </label>

            <select
                name="year"
                id="year"
                class="form-select form-select-custom"
                onchange="this.form.submit()"
            >

                <?php
                for (
                    $y = date('Y') - 1;
                    $y <= date('Y') + 2;
                    $y++
                ):
                ?>

                    <option
                        value="<?php echo $y; ?>"
                        <?php echo ($y == $selectedYear) ? 'selected' : ''; ?>
                    >
                        <?php echo $y; ?>
                    </option>

                <?php endfor; ?>

            </select>

        </div>


        <div class="col-md-2">

            <button
                type="submit"
                class="btn btn-primary-custom w-100"
            >
                <i class="fa-solid fa-filter me-1"></i>
                View
            </button>

        </div>

    </form>

</div>


<!-- ============================================================
     PRINT-ONLY LETTERHEAD / MEMO HEADER
============================================================ -->

<div class="print-only-block">
    <div class="print-letterhead">
        <div class="dealer-block">
            <?php if ($dealerLogoUrl): ?>
                <img src="<?php echo htmlspecialchars($dealerLogoUrl); ?>" alt="<?php echo htmlspecialchars($dealerName); ?>" class="dealer-logo">
            <?php endif; ?>
            <div>
                <div class="dealer-name"><?php echo htmlspecialchars($dealerName); ?></div>
                <?php if ($dealerTagline): ?><div class="dealer-tagline"><?php echo htmlspecialchars($dealerTagline); ?></div><?php endif; ?>
                <div class="dealer-contact">
                    <?php if ($dealerAddress): ?><?php echo htmlspecialchars($dealerAddress); ?><br><?php endif; ?>
                    <?php if ($dealerPhone): ?>Phone: <?php echo htmlspecialchars($dealerPhone); ?><?php endif; ?>
                    <?php if ($dealerPhone && $dealerEmail): ?> &nbsp;|&nbsp; <?php endif; ?>
                    <?php if ($dealerEmail): ?>Email: <?php echo htmlspecialchars($dealerEmail); ?><?php endif; ?>
                </div>
            </div>
        </div>
        <div class="doc-type-box">
            <div class="doc-title">Monthly Financial Summary</div>
            <div class="doc-date">Printed: <?php echo htmlspecialchars($printedAt); ?><?php echo $printedBy ? ' by ' . htmlspecialchars($printedBy) : ''; ?></div>
        </div>
    </div>

    <div class="print-meta-grid">
        <div>
            <div class="meta-label">Reporting Period</div>
            <div class="meta-value"><?php echo date('F Y', strtotime($startDate)); ?></div>
        </div>
        <div style="text-align:right;">
            <div class="meta-label">Total Invoices</div>
            <div class="meta-value"><?php echo (int)($sales['total_invoices'] ?? 0); ?></div>
        </div>
    </div>

    <div class="print-summary-strip">
        <div class="sum-box">
            <div class="sum-label">Monthly Sales Volume</div>
            <div class="sum-value"><?php echo formatBDT($sales['total_sales'] ?? 0); ?></div>
        </div>
        <div class="sum-box">
            <div class="sum-label">Gross Profit</div>
            <div class="sum-value"><?php echo formatBDT($grossProfit); ?></div>
        </div>
        <div class="sum-box">
            <div class="sum-label">Commission Income</div>
            <div class="sum-value"><?php echo formatBDT($totalCommissionIncome); ?></div>
        </div>
        <div class="sum-box net-box">
            <div class="sum-label">Net Profit</div>
            <div class="sum-value"><?php echo formatBDT($netProfit); ?></div>
        </div>
    </div>

    <?php if ($pendingPeriodCommission > 0): ?>
        <div class="print-pending-note">
            <strong><?php echo $pendingPeriodCommissionCount; ?> quarterly/yearly commission period(s) pending</strong>
            (<?php echo formatBDT($pendingPeriodCommission); ?>) — informational only, not included in Net Profit above until marked received.
        </div>
    <?php endif; ?>
</div>


<!-- ============================================================
     KPI STRIP (screen only)
============================================================ -->

<div class="row g-3 mb-4 no-print">

    <!-- SALES -->

    <div class="col-md-3">

        <div class="dark-card py-3 text-center">

            <div class="text-secondary small fw-bold text-uppercase">
                Monthly Sales Volume
            </div>

            <div
                class="fs-4 fw-bold"
                style="color: var(--text-primary);"
            >
                <?php
                echo formatBDT(
                    $sales['total_sales'] ?? 0
                );
                ?>
            </div>

        </div>

    </div>


    <!-- GROSS PROFIT -->

    <div class="col-md-3">

        <div class="dark-card py-3 text-center">

            <div class="text-secondary small fw-bold text-uppercase">
                Monthly Gross Profit
            </div>

            <div class="fs-4 fw-bold text-success">

                <?php
                echo formatBDT($grossProfit);
                ?>

            </div>

        </div>

    </div>


    <!-- COMMISSION -->

    <div class="col-md-3">

        <div class="dark-card py-3 text-center">

            <div class="text-secondary small fw-bold text-uppercase">
                Commission Income
            </div>

            <div class="fs-4 fw-bold text-primary-light">

                <?php
                echo formatBDT($totalCommissionIncome);
                ?>

            </div>


        </div>

    </div>


    <!-- NET PROFIT -->

    <div class="col-md-3">

        <div class="dark-card py-3 text-center">

            <div class="text-secondary small fw-bold text-uppercase">
                Monthly Net Profit
            </div>

            <div
                class="fs-4 fw-bold
                <?php
                echo ($netProfit >= 0)
                    ? 'text-cyan'
                    : 'text-danger';
                ?>"
            >

                <?php
                echo formatBDT($netProfit);
                ?>

            </div>

        </div>

    </div>

</div>

<!-- Print-only section title for the breakdown card below -->
<div class="print-only-block"><div class="print-section-title">Executive Performance Summary — <?php echo date('F Y', strtotime($startDate)); ?></div></div>

<!-- ============================================================
     MONTHLY PERFORMANCE BREAKDOWN
============================================================ -->

<div class="dark-card p-4 mb-4">

    <div class="card-header-clean no-print">

        <div class="card-title-clean">

            <i class="fa-solid fa-chart-pie text-cyan"></i>

            <span>
                Executive Performance Summary —
                <?php
                echo date(
                    'F Y',
                    strtotime($startDate)
                );
                ?>
            </span>

        </div>

    </div>


    <div class="row g-4">


        <!-- ====================================================
             REVENUE & VOLUME
        ==================================================== -->

        <div class="col-md-6">

            <h6
                class="fw-bold mb-3"
                style="color: var(--text-primary);"
            >
                Revenue & Volume Metrics
            </h6>


            <div class="profit-breakdown-box">


                <!-- INVOICES -->

                <div class="profit-item">

                    <span class="text-secondary">
                        Total Invoices Created:
                    </span>

                    <span
                        class="fw-bold"
                        style="color: var(--text-primary);"
                    >

                        <?php
                        echo (int)(
                            $sales['total_invoices'] ?? 0
                        );
                        ?>

                        Invoices

                    </span>

                </div>


                <!-- SOLD BAGS -->

                <div class="profit-item">

                    <span class="text-secondary">
                        Total Bags Sold:
                    </span>

                    <span class="text-cyan fw-bold">

                        <?php
                        echo number_format(
                            $target['act_bags'] ?? 0
                        );
                        ?>

                        Bags

                    </span>

                </div>


                <!-- TARGET BAGS -->

                <div class="profit-item">

                    <span class="text-secondary">
                        Target Bags:
                    </span>

                    <span style="color: var(--text-primary);">

                        <?php
                        echo number_format(
                            $target['tgt_bags'] ?? 0
                        );
                        ?>

                        Bags

                    </span>

                </div>


                <!-- COLLECTION -->

                <div class="profit-item">

                    <span class="text-secondary">
                        Direct Collections Received:
                    </span>

                    <span class="text-success fw-bold">

                        <?php
                        echo formatBDT($collections);
                        ?>

                    </span>

                </div>


                <!-- OTHER INCOME -->

                <div class="profit-item">

                    <span class="text-secondary">
                        Other Income:
                    </span>

                    <span class="text-success fw-bold">

                        <?php
                        echo formatBDT($otherIncome);
                        ?>

                    </span>

                </div>


            </div>

        </div>


        <!-- ====================================================
             PROFITABILITY
        ==================================================== -->

        <div class="col-md-6">

            <h6
                class="fw-bold mb-3"
                style="color: var(--text-primary);"
            >
                Profitability Calculation
            </h6>


            <div class="profit-breakdown-box">


                <!-- GROSS PROFIT -->

                <div class="profit-item">

                    <span class="text-secondary">
                        Gross Profit (+):
                    </span>

                    <span class="text-success fw-bold">

                        <?php
                        echo formatBDT($grossProfit);
                        ?>

                    </span>

                </div>


                <!-- MONTHLY COMMISSION -->

                <div class="profit-item">

                    <span class="text-secondary">
                        Monthly Target Commission (+):
                    </span>

                    <span class="text-primary-light fw-bold">

                        <?php
                        echo formatBDT($monthlyCommission);
                        ?>

                    </span>

                </div>


                <!-- QUARTERLY / YEARLY COMMISSION -->

                <div class="profit-item">

                    <span class="text-secondary">

                        Quarterly/Yearly Commission Received (+):

                        <?php
                        if (
                            $periodCommissionReceivedCount > 0
                        ):
                        ?>

                            <span
                                class="badge-custom badge-primary ms-1"
                            >
                                <?php
                                echo $periodCommissionReceivedCount;
                                ?>
                            </span>

                        <?php endif; ?>

                    </span>


                    <span class="text-primary-light fw-bold">

                        <?php
                        echo formatBDT($periodCommission);
                        ?>

                    </span>

                </div>


                <!-- OTHER INCOME -->

                <div class="profit-item">

                    <span class="text-secondary">
                        Other Income (+):
                    </span>

                    <span class="text-success fw-bold">

                        <?php
                        echo formatBDT($otherIncome);
                        ?>

                    </span>

                </div>


                <!-- EXPENSES -->

                <div class="profit-item">

                    <span class="text-secondary">
                        Operating Expenses (-):
                    </span>

                    <span class="text-danger fw-bold">

                        <?php
                        echo formatBDT($expenses);
                        ?>

                    </span>

                </div>


                <!-- NET PROFIT -->

                <div class="profit-item net-profit">

                    <span
                        style="color: var(--text-primary);"
                    >
                        Net Operating Profit:
                    </span>

                    <span class="text-cyan fw-bold fs-5">

                        <?php
                        echo formatBDT($netProfit);
                        ?>

                    </span>

                </div>


            </div>

        </div>

    </div>

</div>


<!-- ============================================================
     PENDING PERIOD COMMISSIONS (screen only — shown in print via
     print-pending-note in the letterhead block above)
============================================================ -->

<?php if ($pendingPeriodCommission > 0): ?>

    <div
        class="dark-card p-4 no-print"
        style="
            border-left:
            3px solid
            var(--bs-warning, #ffc107);
        "
    >

        <div
            class="d-flex
                   justify-content-between
                   align-items-center
                   flex-wrap
                   gap-2"
        >

            <div>

                <h6
                    class="fw-bold mb-1"
                    style="color: var(--text-primary);"
                >

                    <i
                        class="fa-solid
                               fa-hourglass-half
                               text-warning
                               me-1"
                    ></i>

                    Pending Quarterly/Yearly Commissions

                </h6>


                <div class="small text-secondary">

                    <?php
                    echo $pendingPeriodCommissionCount;
                    ?>

                    commission period(s) still pending —

                    these are NOT included in Net Profit until
                    marked "received", and will only affect
                    the month's report in which they are received.

                </div>

            </div>


            <div class="text-end">

                <div class="fs-5 fw-bold text-warning">

                    <?php
                    echo formatBDT(
                        $pendingPeriodCommission
                    );
                    ?>

                </div>

            </div>

        </div>

    </div>

<?php endif; ?>


<!-- ============================================================
     PRINT-ONLY FOOTER: signature lines + generated-by note
============================================================ -->

<div class="print-only-block">
    <div class="print-footer-block">
        <div class="sig-box">
            <div class="sig-line">Prepared By</div>
        </div>
        <div class="sig-box">
            <div class="sig-line">Checked / Approved By — <?php echo htmlspecialchars($dealerName); ?></div>
        </div>
    </div>
    <div class="print-generated-note">
        This is a computer-generated monthly summary from <?php echo htmlspecialchars($dealerName); ?>'s accounting system and does not require a physical stamp unless otherwise requested.
    </div>
</div>

<script>
    (function () {
        var originalTitle = document.title;
        var printTitle = <?php echo json_encode(($dealerName ? $dealerName . ' — ' : '') . 'Monthly Financial Summary — ' . date('F Y', strtotime($startDate))); ?>;

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