<?php
/**
 * Maruf Traders - Yearly Comparative Business Report
 * (Updated: adds a professional print-only letterhead/memo header,
 *  matching the ledger/daily/monthly reports' print design, plus
 *  logo cache-busting via logo_updated_at.)
 *
 * Includes:
 * - Monthly Sales
 * - Monthly Gross Profit
 * - Operating Expenses
 * - Monthly Target Commission
 * - Quarterly / Yearly Received Commission
 * - Other Income
 * - Monthly Net Profit
 * - Annual Totals
 *
 * IMPORTANT COMMISSION RULE:
 * - Existing Monthly Commission logic remains unchanged.
 * - Quarterly / Yearly Commission affects Net Profit ONLY
 *   in the month when it is marked as RECEIVED.
 * - Pending Quarterly / Yearly Commission is excluded from Net Profit.
 *
 * OTHER INCOME RULE:
 * - Other Income is counted according to income_date.
 * - Only status = 'active' records are included.
 * - Other Income affects the Net Profit of the month
 *   in which the income_date falls.
 */

defined('APP_INIT') or define('APP_INIT', true);

$pageTitle = 'Yearly Business Report';
$breadcrumb = 'Yearly Report';
$activeMenu = 'report_yearly';

require_once __DIR__ . '/../../includes/header.php';
requirePermission('reports.financial');

$db = Database::getConnection();

/*
|--------------------------------------------------------------------------
| Selected Year
|--------------------------------------------------------------------------
*/

$selectedYear = (int)($_GET['year'] ?? date('Y'));

/*
|--------------------------------------------------------------------------
| Validate Year
|--------------------------------------------------------------------------
*/

if ($selectedYear < 2000 || $selectedYear > 2100) {
    $selectedYear = (int)date('Y');
}

/*
|--------------------------------------------------------------------------
| Monthly Breakdown
|--------------------------------------------------------------------------
*/

$monthlyData = [];

for ($m = 1; $m <= 12; $m++) {

    $startDate = sprintf(
        '%04d-%02d-01',
        $selectedYear,
        $m
    );

    $endDate = date(
        'Y-m-t',
        strtotime($startDate)
    );


    $tenantId = TenantContext::id();

    /*
    |--------------------------------------------------------------------------
    | SALES & GROSS PROFIT
    |--------------------------------------------------------------------------
    */

    $sStmt = $db->prepare("
        SELECT
            COALESCE(SUM(total_amount), 0) AS sales,
            COALESCE(SUM(gross_profit), 0) AS gross_profit
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

    $sRow = $sStmt->fetch();


    /*
    |--------------------------------------------------------------------------
    | EXPENSES
    |--------------------------------------------------------------------------
    */

    $eStmt = $db->prepare("
        SELECT
            COALESCE(SUM(amount), 0)
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

    $eRow = (float)$eStmt->fetchColumn();


    /*
    |--------------------------------------------------------------------------
    | OTHER INCOME
    |--------------------------------------------------------------------------
    |
    | Other Income belongs to the month according to income_date.
    | Only ACTIVE records are included.
    |
    */

    $oiStmt = $db->prepare("
        SELECT
            COALESCE(SUM(amount), 0) AS other_income,
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

    $oiRow = $oiStmt->fetch();

    $otherIncome = (float)(
        $oiRow['other_income'] ?? 0
    );

    $otherIncomeCount = (int)(
        $oiRow['other_income_count'] ?? 0
    );


    /*
    |--------------------------------------------------------------------------
    | MONTHLY TARGET COMMISSION
    |--------------------------------------------------------------------------
    |
    | Existing logic remains unchanged.
    |
    */

    $cStmt = $db->prepare("
        SELECT
            COALESCE(SUM(estimated_commission), 0)
        FROM monthly_targets
        WHERE tenant_id = :tenant
          AND target_month = :m
          AND target_year = :y
    ");

    $cStmt->execute([
        ':tenant' => $tenantId,
        ':m'      => $m,
        ':y'      => $selectedYear
    ]);

    $cRow = (float)$cStmt->fetchColumn();


    /*
    |--------------------------------------------------------------------------
    | QUARTERLY / YEARLY COMMISSION
    |--------------------------------------------------------------------------
    |
    | ONLY received commissions are included.
    | The received_date determines the month.
    |
    */

    $pcStmt = $db->prepare("
        SELECT
            COALESCE(SUM(total_amount), 0) AS received_amount
        FROM company_period_commissions
        WHERE tenant_id = :tenant
          AND status = 'received'
          AND received_date BETWEEN :start AND :end
    ");

    $pcStmt->execute([
        ':tenant' => $tenantId,
        ':start'  => $startDate,
        ':end'    => $endDate
    ]);

    $periodCommission = (float)$pcStmt->fetchColumn();


    /*
    |--------------------------------------------------------------------------
    | MONTHLY FINANCIAL CALCULATION
    |--------------------------------------------------------------------------
    */

    $gp = (float)(
        $sRow['gross_profit'] ?? 0
    );


    /*
     * Total commission for this month:
     *
     * Monthly Commission
     * +
     * Received Quarterly / Yearly Commission
     */

    $totalCommission = round(
        $cRow + $periodCommission,
        2
    );


    /*
     * Net Profit:
     *
     * Gross Profit
     * - Operating Expenses
     * + Monthly Commission
     * + Received Quarterly / Yearly Commission
     * + Other Income
     */

    $np = round(
        $gp
        - $eRow
        + $totalCommission
        + $otherIncome,
        2
    );


    /*
    |--------------------------------------------------------------------------
    | STORE MONTHLY DATA
    |--------------------------------------------------------------------------
    */

    $monthlyData[$m] = [

        'month_name' => date(
            'F',
            mktime(0, 0, 0, $m, 10)
        ),

        'sales' => (float)(
            $sRow['sales'] ?? 0
        ),

        'gross_profit' => $gp,

        'expense' => $eRow,

        'monthly_commission' => $cRow,

        'period_commission' => $periodCommission,

        'commission' => $totalCommission,

        'other_income' => $otherIncome,

        'other_income_count' => $otherIncomeCount,

        'net_profit' => $np
    ];
}


/*
|--------------------------------------------------------------------------
| YEAR TOTALS
|--------------------------------------------------------------------------
*/

$totYearSales = array_sum(
    array_column(
        $monthlyData,
        'sales'
    )
);

$totYearGrossProfit = array_sum(
    array_column(
        $monthlyData,
        'gross_profit'
    )
);

$totYearExpense = array_sum(
    array_column(
        $monthlyData,
        'expense'
    )
);

$totYearMonthlyCommission = array_sum(
    array_column(
        $monthlyData,
        'monthly_commission'
    )
);

$totYearPeriodCommission = array_sum(
    array_column(
        $monthlyData,
        'period_commission'
    )
);

$totYearCommission = array_sum(
    array_column(
        $monthlyData,
        'commission'
    )
);


/*
|--------------------------------------------------------------------------
| TOTAL OTHER INCOME
|--------------------------------------------------------------------------
*/

$totYearOtherIncome = array_sum(
    array_column(
        $monthlyData,
        'other_income'
    )
);

$totYearOtherIncomeCount = array_sum(
    array_column(
        $monthlyData,
        'other_income_count'
    )
);


/*
|--------------------------------------------------------------------------
| TOTAL YEAR NET PROFIT
|--------------------------------------------------------------------------
*/

$totYearNetProfit = array_sum(
    array_column(
        $monthlyData,
        'net_profit'
    )
);


/*
|--------------------------------------------------------------------------
| PENDING QUARTERLY / YEARLY COMMISSIONS
|--------------------------------------------------------------------------
|
| Informational only.
| Not included in any month's Net Profit.
|
*/

$pcPendingStmt = $db->query("
    SELECT
        COALESCE(SUM(total_amount), 0) AS pending_amount,
        COUNT(id) AS pending_count
    FROM company_period_commissions
    WHERE status = 'pending'
");

$periodCommissionPending = $pcPendingStmt->fetch();

$pendingPeriodCommission = (float)(
    $periodCommissionPending['pending_amount'] ?? 0
);

$pendingPeriodCommissionCount = (int)(
    $periodCommissionPending['pending_count'] ?? 0
);

/*
|--------------------------------------------------------------------------
| DEALER INFO FROM SETTINGS (same keys as other report pages)
|--------------------------------------------------------------------------
*/

$dealerName    = getSetting('business_name', 'Maruf Traders');
$dealerTagline = getSetting('business_title', 'Cement Dealership & Distribution');
$dealerAddress = getSetting('business_address', 'সুন্দরপুর বাজার, সাটিয়াজুরি, চুনারুঘাট, হবিগঞ্জ');
$dealerPhone   = getSetting('business_mobile', '');
$dealerEmail   = getSetting('business_email', '');
$dealerLogo    = getSetting('logo_path', '');
$logoUpdatedAt = getSetting('logo_updated_at', '');

$dealerLogoUrl = '';
if (!empty($dealerLogo)) {
    $dealerLogoUrl = (stripos($dealerLogo, 'http://') === 0 || stripos($dealerLogo, 'https://') === 0)
        ? $dealerLogo
        : rtrim(BASE_URL, '/') . '/' . ltrim($dealerLogo, '/');

    // Cache-bust so a newly uploaded logo shows immediately, not a stale cached copy
    if (!empty($logoUpdatedAt)) {
        $dealerLogoUrl .= '?v=' . (int)$logoUpdatedAt;
    }
}

$printedBy = $_SESSION['user_name'] ?? '';
$printedAt = date('d M, Y h:i A');

?>

<style>
    .print-only-block { display: none; }

    @media print {
        @page {
            size: A4 landscape;
            margin: 10mm 8mm;
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
            margin-bottom: 10px !important;
        }

        .dark-table {
            width: 100% !important;
            border-collapse: collapse !important;
            border-spacing: 0 !important;
            color: #000000 !important;
        }

        .dark-table, .dark-table th, .dark-table td {
            box-sizing: border-box !important;
        }

        .dark-table th {
            background: #F1F5F9 !important;
            color: #0F172A !important;
            border: 1px solid #94A3B8 !important;
            font-size: 8.5px;
            padding: 5px 4px !important;
            text-transform: uppercase;
            letter-spacing: 0.1px;
        }

        .dark-table td {
            border: 1px solid #CBD5E1 !important;
            color: #0F172A !important;
            font-size: 9px;
            padding: 5px 4px !important;
            line-height: 1.3;
            vertical-align: top;
            white-space: normal !important;
            word-wrap: break-word;
        }

        .dark-table tfoot tr {
            background: #F8FAFC !important;
        }

        .text-danger   { color: #B91C1C !important; }
        .text-success  { color: #15803D !important; }
        .text-cyan     { color: #0369A1 !important; }
        .text-primary-light { color: #1D4ED8 !important; }
        .text-secondary { color: #475569 !important; }
        .text-warning  { color: #B45309 !important; }

        .badge-custom {
            border: 1px solid #94A3B8 !important;
            background: #FFFFFF !important;
            color: #0F172A !important;
        }

        .table-responsive { overflow: visible !important; width: 100% !important; }

        .dark-card { break-inside: avoid; page-break-inside: avoid; }

        /* ---------- Letterhead ---------- */
        .print-letterhead {
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 3px solid #0F172A;
            padding-bottom: 8px;
            margin-bottom: 10px;
        }

        .print-letterhead .dealer-logo {
            max-height: 55px;
            max-width: 140px;
            object-fit: contain;
            margin-right: 14px;
        }

        .print-letterhead .dealer-block {
            display: flex;
            align-items: center;
        }

        .print-letterhead .dealer-name {
            font-size: 19px;
            font-weight: 800;
            color: #0F172A;
            margin: 0 0 2px 0;
        }

        .print-letterhead .dealer-tagline {
            font-size: 10.5px;
            color: #475569;
            margin: 0 0 3px 0;
        }

        .print-letterhead .dealer-contact {
            font-size: 10px;
            color: #334155;
            line-height: 1.35;
        }

        .print-letterhead .doc-type-box { text-align: right; }

        .print-letterhead .doc-type-box .doc-title {
            font-size: 13px;
            font-weight: 700;
            color: #0F172A;
            text-transform: uppercase;
            letter-spacing: 1px;
            border: 2px solid #0F172A;
            padding: 4px 12px;
            display: inline-block;
        }

        .print-letterhead .doc-type-box .doc-date {
            font-size: 10px;
            color: #475569;
            margin-top: 5px;
        }

        /* ---------- Meta / KPI strip ---------- */
        .print-meta-grid {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
            font-size: 12px;
        }

        .print-meta-grid .meta-label {
            font-size: 9px;
            text-transform: uppercase;
            letter-spacing: 0.4px;
            color: #64748B;
            font-weight: 700;
        }

        .print-meta-grid .meta-value {
            font-size: 13px;
            color: #0F172A;
            font-weight: 800;
        }

        .print-summary-strip {
            display: flex;
            gap: 8px;
            margin-bottom: 10px;
        }

        .print-summary-strip .sum-box {
            flex: 1;
            border: 1px solid #CBD5E1;
            border-radius: 4px;
            padding: 6px 8px;
            text-align: center;
        }

        .print-summary-strip .sum-box .sum-label {
            font-size: 8.5px;
            text-transform: uppercase;
            color: #64748B;
            font-weight: 700;
        }

        .print-summary-strip .sum-box .sum-value {
            font-size: 13px;
            font-weight: 800;
            color: #0F172A;
            margin-top: 2px;
        }

        .print-summary-strip .sum-box.net-box { border-color: #0F172A; }

        .print-pending-note {
            border: 1px dashed #B45309;
            border-radius: 4px;
            padding: 5px 9px;
            font-size: 9.5px;
            color: #7C2D12;
            margin-bottom: 10px;
        }

        /* ---------- Footer ---------- */
        .print-footer-block {
            margin-top: 24px;
            display: flex;
            justify-content: space-between;
            font-size: 10px;
        }

        .print-footer-block .sig-box { width: 42%; text-align: center; }

        .print-footer-block .sig-line {
            border-top: 1px solid #0F172A;
            margin-top: 30px;
            padding-top: 4px;
            font-weight: 600;
            color: #0F172A;
        }

        .print-generated-note {
            margin-top: 16px;
            font-size: 8.5px;
            color: #94A3B8;
            text-align: center;
            border-top: 1px dashed #CBD5E1;
            padding-top: 6px;
        }
    }
</style>

<!-- ============================================================
     PAGE HEADER
============================================================ -->

<div class="page-header-container no-print">

    <div>

        <h2 class="page-title">
            Yearly Business Report
            (বার্ষিক আর্থিক প্রতিবেদন)
        </h2>

        <div class="page-subtitle">
            Month-by-month financial performance, annual revenue,
            other income, commission and total net profitability
            for <?php echo $selectedYear; ?>.
        </div>

    </div>


    <div class="d-flex gap-2">

        <button
            type="button"
            class="btn btn-primary-custom"
            onclick="window.print()"
        >
            <i class="fa-solid fa-print me-1"></i>
            Print Annual Report
        </button>

    </div>

</div>

<div class="no-print" style="font-size: 12px; color: var(--text-secondary, #64748B); margin-top: -10px; margin-bottom: 16px;">
    <i class="fa-solid fa-circle-info me-1"></i>
    আপনার ব্রাউজারের Print ডায়ালগে "Headers and footers" অপশনটা বন্ধ (uncheck) রাখুন, এবং landscape orientation বেছে নিন — এই টেবিলটা wide, portrait-এ কলাম কেটে যেতে পারে।
</div>


<!-- ============================================================
     YEAR SELECTOR
============================================================ -->

<div class="dark-card mb-4 no-print">

    <form
        method="GET"
        action=""
        class="row g-3 align-items-end"
    >

        <div class="col-md-6">

            <label
                for="year"
                class="form-label-custom"
            >
                Select Financial Year
            </label>

            <select
                name="year"
                id="year"
                class="form-select form-select-custom"
                onchange="this.form.submit()"
            >

                <?php
                for (
                    $y = date('Y') - 2;
                    $y <= date('Y') + 2;
                    $y++
                ):
                ?>

                    <option
                        value="<?php echo $y; ?>"
                        <?php
                        echo ($y == $selectedYear)
                            ? 'selected'
                            : '';
                        ?>
                    >
                        Year <?php echo $y; ?>
                    </option>

                <?php endfor; ?>

            </select>

        </div>


        <div class="col-md-6 text-end">

            <span
                class="badge-custom badge-primary fs-6"
            >
                Financial Year:
                <?php echo $selectedYear; ?>
            </span>

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
            <div class="doc-title">Annual Financial Report</div>
            <div class="doc-date">Printed: <?php echo htmlspecialchars($printedAt); ?><?php echo $printedBy ? ' by ' . htmlspecialchars($printedBy) : ''; ?></div>
        </div>
    </div>

    <div class="print-meta-grid">
        <div>
            <div class="meta-label">Financial Year</div>
            <div class="meta-value"><?php echo $selectedYear; ?></div>
        </div>
        <div style="text-align:right;">
            <div class="meta-label">Coverage</div>
            <div class="meta-value">January – December <?php echo $selectedYear; ?></div>
        </div>
    </div>

    <div class="print-summary-strip">
        <div class="sum-box">
            <div class="sum-label">Annual Sales Volume</div>
            <div class="sum-value"><?php echo formatBDT($totYearSales); ?></div>
        </div>
        <div class="sum-box">
            <div class="sum-label">Annual Gross Profit</div>
            <div class="sum-value"><?php echo formatBDT($totYearGrossProfit); ?></div>
        </div>
        <div class="sum-box">
            <div class="sum-label">Annual Commission Income</div>
            <div class="sum-value"><?php echo formatBDT($totYearCommission); ?></div>
        </div>
        <div class="sum-box net-box">
            <div class="sum-label">Annual Net Profit</div>
            <div class="sum-value"><?php echo formatBDT($totYearNetProfit); ?></div>
        </div>
    </div>

    <?php if ($pendingPeriodCommission > 0): ?>
        <div class="print-pending-note">
            <strong><?php echo $pendingPeriodCommissionCount; ?> quarterly/yearly commission period(s) pending (all companies)</strong>
            (<?php echo formatBDT($pendingPeriodCommission); ?>) — informational only, not reflected in any month's Net Profit above.
        </div>
    <?php endif; ?>
</div>


<!-- ============================================================
     KPI STRIP (screen only)
============================================================ -->

<div class="row g-3 mb-4 no-print">


    <!-- ANNUAL SALES -->

    <div class="col-md-3">

        <div class="dark-card py-3 text-center">

            <div class="text-secondary small fw-bold text-uppercase">
                Annual Sales Volume
            </div>

            <div
                class="fs-4 fw-bold"
                style="color: var(--text-primary);"
            >
                <?php
                echo formatBDT($totYearSales);
                ?>
            </div>

        </div>

    </div>


    <!-- GROSS PROFIT -->

    <div class="col-md-3">

        <div class="dark-card py-3 text-center">

            <div class="text-secondary small fw-bold text-uppercase">
                Annual Gross Profit
            </div>

            <div class="fs-4 fw-bold text-success">

                <?php
                echo formatBDT($totYearGrossProfit);
                ?>

            </div>

        </div>

    </div>


    <!-- COMMISSION -->

    <div class="col-md-3">

        <div class="dark-card py-3 text-center">

            <div class="text-secondary small fw-bold text-uppercase">
                Annual Commission Income
            </div>

            <div class="fs-4 fw-bold text-primary-light">

                <?php
                echo formatBDT($totYearCommission);
                ?>

            </div>

        </div>

    </div>


    <!-- NET PROFIT -->

    <div class="col-md-3">

        <div class="dark-card py-3 text-center">

            <div class="text-secondary small fw-bold text-uppercase">
                Annual Net Profit
            </div>

            <div
                class="fs-4 fw-bold
                <?php
                echo ($totYearNetProfit >= 0)
                    ? 'text-cyan'
                    : 'text-danger';
                ?>"
            >

                <?php
                echo formatBDT(
                    $totYearNetProfit
                );
                ?>

            </div>

        </div>

    </div>

</div>

<!-- Print-only section title -->
<div class="print-only-block"><div class="print-section-title" style="font-size: 11px; font-weight: 800; color: #0F172A; text-transform: uppercase; letter-spacing: 0.4px; border-bottom: 1px solid #94A3B8; padding-bottom: 3px; margin: 8px 0 6px 0;">Monthly Financial Matrix — <?php echo $selectedYear; ?></div></div>

<!-- ============================================================
     YEARLY BREAKDOWN TABLE
============================================================ -->

<div class="dark-card mb-4">

    <div class="card-header-clean no-print">

        <div class="card-title-clean">

            <i
                class="fa-solid
                       fa-calendar-days
                       text-primary-light"
            ></i>

            <span>
                Monthly Financial Matrix
                (January – December
                <?php echo $selectedYear; ?>)
            </span>

        </div>

    </div>


    <div class="table-responsive">

        <table class="dark-table">

            <thead>

                <tr>

                    <th>
                        Month
                    </th>

                    <th class="text-end">
                        Sales
                    </th>

                    <th class="text-end">
                        G.Profit
                    </th>

                    <th class="text-end">
                        Expenses
                    </th>

                    <th class="text-end">
                        Monthly Comm.
                    </th>

                    <th class="text-end">
                        3M/Yearly Comm.
                    </th>

                    <th class="text-end">
                        Others
                    </th>

                    <th class="text-end">
                        Net Profit
                    </th>

                </tr>

            </thead>


            <tbody>

                <?php foreach ($monthlyData as $row): ?>

                    <tr>

                        <!-- MONTH -->

                        <td
                            class="fw-bold"
                            style="color: var(--text-primary);"
                        >

                            <?php
                            echo $row['month_name'];
                            ?>

                        </td>


                        <!-- SALES -->

                        <td class="text-end">

                            <?php
                            echo formatBDT(
                                $row['sales']
                            );
                            ?>

                        </td>


                        <!-- GROSS PROFIT -->

                        <td class="text-end text-success">

                            <?php
                            echo formatBDT(
                                $row['gross_profit']
                            );
                            ?>

                        </td>


                        <!-- EXPENSE -->

                        <td class="text-end text-danger">

                            <?php
                            echo formatBDT(
                                $row['expense']
                            );
                            ?>

                        </td>


                        <!-- MONTHLY COMMISSION -->

                        <td
                            class="
                                text-end
                                text-primary-light
                            "
                        >

                            <?php
                            echo formatBDT(
                                $row['monthly_commission']
                            );
                            ?>

                        </td>


                        <!-- PERIOD COMMISSION -->

                        <td
                            class="
                                text-end
                                text-primary-light
                            "
                        >

                            <?php
                            echo formatBDT(
                                $row['period_commission']
                            );
                            ?>

                        </td>


                        <!-- OTHER INCOME -->

                        <td
                            class="
                                text-end
                                text-success
                                fw-semibold
                            "
                        >

                            <?php
                            echo formatBDT(
                                $row['other_income']
                            );
                            ?>

                        </td>


                        <!-- NET PROFIT -->

                        <td
                            class="
                                text-end
                                fw-bold
                                <?php
                                echo ($row['net_profit'] >= 0)
                                    ? 'text-cyan'
                                    : 'text-danger';
                                ?>
                            "
                        >

                            <?php
                            echo formatBDT(
                                $row['net_profit']
                            );
                            ?>

                        </td>

                    </tr>

                <?php endforeach; ?>

            </tbody>


            <!-- =================================================
                 YEAR TOTAL
            ================================================== -->

            <tfoot>

                <tr
                    class="fw-bold fs-6"
                    style="background: var(--bg-input);"
                >

                    <td
                        style="
                            color:
                            var(--text-primary);
                        "
                    >
                        Year Total:
                    </td>


                    <!-- SALES TOTAL -->

                    <td
                        class="text-end"
                        style="
                            color:
                            var(--text-primary);
                        "
                    >

                        <?php
                        echo formatBDT(
                            $totYearSales
                        );
                        ?>

                    </td>


                    <!-- GROSS PROFIT TOTAL -->

                    <td class="text-end text-success">

                        <?php
                        echo formatBDT(
                            $totYearGrossProfit
                        );
                        ?>

                    </td>


                    <!-- EXPENSE TOTAL -->

                    <td class="text-end text-danger">

                        <?php
                        echo formatBDT(
                            $totYearExpense
                        );
                        ?>

                    </td>


                    <!-- MONTHLY COMMISSION TOTAL -->

                    <td class="text-end text-primary-light">

                        <?php
                        echo formatBDT(
                            $totYearMonthlyCommission
                        );
                        ?>

                    </td>


                    <!-- PERIOD COMMISSION TOTAL -->

                    <td class="text-end text-primary-light">

                        <?php
                        echo formatBDT(
                            $totYearPeriodCommission
                        );
                        ?>

                    </td>


                    <!-- OTHER INCOME TOTAL -->

                    <td class="text-end text-success">

                        <?php
                        echo formatBDT(
                            $totYearOtherIncome
                        );
                        ?>

                    </td>


                    <!-- NET PROFIT TOTAL -->

                    <td class="text-end text-cyan">

                        <?php
                        echo formatBDT(
                            $totYearNetProfit
                        );
                        ?>

                    </td>

                </tr>

            </tfoot>

        </table>

    </div>

</div>


<!-- ============================================================
     PENDING PERIOD COMMISSIONS (screen only — print version in
     letterhead block above as print-pending-note)
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
            class="
                d-flex
                justify-content-between
                align-items-center
                flex-wrap
                gap-2
            "
        >

            <div>

                <h6
                    class="fw-bold mb-1"
                    style="
                        color:
                        var(--text-primary);
                    "
                >

                    <i
                        class="
                            fa-solid
                            fa-hourglass-half
                            text-warning
                            me-1
                        "
                    ></i>

                    Pending Quarterly/Yearly
                    Commissions (All Companies)

                </h6>


                <div class="small text-secondary">

                    <?php
                    echo $pendingPeriodCommissionCount;
                    ?>

                    commission period(s) still pending —

                    not yet reflected in any month's
                    Net Profit above.

                    Each will be added to the Net Profit
                    of the month it's marked "received" in.

                </div>

            </div>


            <div class="text-end">

                <div
                    class="
                        fs-5
                        fw-bold
                        text-warning
                    "
                >

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
     PRINT-ONLY FOOTER
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
        This is a computer-generated annual report from <?php echo htmlspecialchars($dealerName); ?>'s accounting system and does not require a physical stamp unless otherwise requested.
    </div>
</div>

<script>
    (function () {
        var originalTitle = document.title;
        var printTitle = <?php echo json_encode(($dealerName ? $dealerName . ' — ' : '') . 'Annual Financial Report — ' . $selectedYear); ?>;

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