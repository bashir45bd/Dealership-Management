
<?php
/**
 * Maruf Traders - Inventory Valuation & Stock Report
 *
 * Updated:
 * - Professional print layout
 * - Fixed table text overlapping
 * - Improved column widths
 * - Long product/company names wrap safely
 * - Financial amount font optimized
 * - Cleaner total row
 * - Print-only professional letterhead
 */

define('APP_INIT', true);

$pageTitle = 'Stock Valuation Report';
$breadcrumb = 'Stock Report';
$activeMenu = 'report_stock';

require_once __DIR__ . '/../../includes/header.php';
requirePermission('reports.financial');

$db = Database::getConnection();
$tenantId = TenantContext::id();

/**
 * ============================================================
 * GET PRODUCTS
 * ============================================================
 */
$stmt = $db->prepare("
    SELECT 
        p.*,
        c.name AS company_name
    FROM products p
    JOIN companies c ON p.company_id = c.id
    WHERE p.tenant_id = :tenant
    ORDER BY p.company_id ASC, p.name ASC
");
$stmt->execute([':tenant' => $tenantId]);

$products = $stmt->fetchAll();


/**
 * ============================================================
 * TOTAL STOCK VALUATION
 * ============================================================
 */
$totalBags = 0.0;
$totalPurchaseValuation = 0;
$totalSaleValuation = 0;

foreach ($products as $p) {

    $stock = (float) $p['current_stock'];

    $purchaseRate = (float) $p['default_purchase_price'];
    $saleRate     = (float) $p['default_sale_price'];

    $totalBags += $stock;

    $totalPurchaseValuation += ($stock * $purchaseRate);

    $totalSaleValuation += ($stock * $saleRate);
}


/**
 * Potential realizable profit
 */
$estimatedPotentialProfit =
    $totalSaleValuation - $totalPurchaseValuation;


/**
 * ============================================================
 * DEALER INFORMATION
 * ============================================================
 */
$dealerName = getSetting(
    'business_name',
    'Maruf Traders'
);

$dealerTagline = getSetting(
    'business_title',
    'Cement Dealership & Distribution'
);

$dealerAddress = getSetting(
    'business_address',
    'সুন্দরপুর বাজার, সাটিয়াজুরি, চুনারুঘাট, হবিগঞ্জ'
);

$dealerPhone = getSetting(
    'business_mobile',
    ''
);

$dealerEmail = getSetting(
    'business_email',
    ''
);

$dealerLogo = getSetting(
    'logo_path',
    ''
);

$dealerLogoUrl = '';

if (!empty($dealerLogo)) {

    $dealerLogoUrl =
        (
            stripos($dealerLogo, 'http://') === 0 ||
            stripos($dealerLogo, 'https://') === 0
        )
        ? $dealerLogo
        : rtrim(BASE_URL, '/') . '/' . ltrim($dealerLogo, '/');
}


$printedBy = $_SESSION['user_name'] ?? '';

$printedAt = date('d M, Y h:i A');

?>

<style>

    /* ============================================================
       GENERAL TABLE
       ============================================================ */

    .dark-table {
        width: 100%;
        table-layout: fixed;
        border-collapse: collapse;
    }

    .dark-table th,
    .dark-table td {
        box-sizing: border-box;
    }

    .dark-table th {
        vertical-align: middle;
        white-space: normal;
        overflow-wrap: break-word;
        word-break: normal;
    }

    .dark-table td {
        vertical-align: middle;
        overflow-wrap: anywhere;
        word-break: break-word;
    }


    /* ============================================================
       PRODUCT / COMPANY TEXT
       ============================================================ */

    .stock-product-name {
        overflow-wrap: anywhere;
        word-break: break-word;
        line-height: 1.35;
    }

    .stock-brand {
        line-height: 1.3;
    }

    .stock-company {
        display: inline-block;
        max-width: 100%;
        white-space: normal;
        overflow-wrap: anywhere;
        word-break: break-word;
        line-height: 1.25;
    }


    /* ============================================================
       NUMERIC COLUMNS
       ============================================================ */

    .stock-number,
    .stock-amount {
        white-space: nowrap;
        word-break: normal;
        overflow-wrap: normal;
    }

    .stock-amount {
        font-size: 13px !important;
    }


    /* ============================================================
       SCREEN TOTAL
       ============================================================ */

    .stock-total-value {
        font-size: 13px !important;
        font-weight: 700 !important;
        white-space: nowrap;
    }


    /* ============================================================
       PRINT ONLY
       ============================================================ */

    .print-only-block {
        display: none;
    }


    /* ============================================================
       PRINT STYLES
       ============================================================ */

    @media print {

        @page {
            size: A4;
            margin: 12mm 10mm;
        }


        body {
            background: #FFFFFF !important;
        }


        .print-only-block {
            display: block !important;
        }


        .no-print {
            display: none !important;
        }


        .dark-card {
            background: #FFFFFF !important;
            border: none !important;
            box-shadow: none !important;
            padding: 0 !important;
            color: #000000 !important;
        }


        /* ========================================================
           PRINT TABLE
           ======================================================== */

        .dark-table {
            width: 100% !important;

            table-layout: fixed !important;

            border-collapse: collapse !important;

            border-spacing: 0 !important;

            color: #000000 !important;
        }


        .dark-table th,
        .dark-table td {
            box-sizing: border-box !important;
        }


        /*
         * 8 columns:
         *
         * Code
         * Product
         * Company
         * Stock
         * Cost / Bag
         * Sale / Bag
         * Total Cost
         * Total Retail
         */

        .dark-table col.col-code {
            width: 9%;
        }

        .dark-table col.col-product {
            width: 23%;
        }

        .dark-table col.col-company {
            width: 15%;
        }

        .dark-table col.col-stock {
            width: 10%;
        }

        .dark-table col.col-cost-rate {
            width: 10%;
        }

        .dark-table col.col-sale-rate {
            width: 10%;
        }

        .dark-table col.col-total-cost {
            width: 11.5%;
        }

        .dark-table col.col-total-sale {
            width: 11.5%;
        }


        /* ========================================================
           PRINT HEADER
           ======================================================== */

        .dark-table th {

            background: #F1F5F9 !important;

            color: #0F172A !important;

            border: 1px solid #94A3B8 !important;

            font-size: 7.5px !important;

            font-weight: 700 !important;

            padding: 5px 3px !important;

            line-height: 1.25;

            vertical-align: middle;

            text-transform: uppercase;

            letter-spacing: 0;

            white-space: normal !important;

            overflow-wrap: break-word;

            word-break: normal;
        }


        /* ========================================================
           PRINT BODY
           ======================================================== */

        .dark-table td {

            border: 1px solid #CBD5E1 !important;

            color: #0F172A !important;

            font-size: 7.8px !important;

            padding: 5px 3px !important;

            line-height: 1.3;

            vertical-align: middle;

            white-space: normal !important;

            overflow-wrap: anywhere !important;

            word-break: break-word !important;

            overflow: visible !important;

            text-overflow: unset !important;
        }


        /* Product code */

        .dark-table td.stock-code {

            white-space: nowrap !important;

            overflow: hidden !important;

            text-overflow: ellipsis !important;

            word-break: normal !important;

            font-size: 7.5px !important;
        }


        /* Product name */

        .dark-table td.stock-product {

            overflow-wrap: anywhere !important;

            word-break: break-word !important;
        }


        /* Brand text */

        .dark-table td .stock-brand {

            font-size: 7px !important;

            line-height: 1.2;

        }


        /* Company */

        .dark-table td.stock-company-cell {

            overflow-wrap: anywhere !important;

            word-break: break-word !important;

        }


        /* Numeric */

        .dark-table td.stock-number,
        .dark-table td.stock-amount {

            white-space: nowrap !important;

            word-break: normal !important;

            overflow-wrap: normal !important;

            font-size: 7.5px !important;

        }


        /* Stock quantity */

        .dark-table td.stock-number {

            font-weight: 700 !important;

        }


        /* Total cost / sale */

        .dark-table td.stock-total-cost,
        .dark-table td.stock-total-sale {

            white-space: nowrap !important;

            font-size: 7.5px !important;

            font-weight: 700 !important;

        }


        /* ========================================================
           TABLE FOOTER
           ======================================================== */

        .dark-table tfoot tr {

            background: #F8FAFC !important;

        }

        .dark-table tfoot td {

            font-size: 8px !important;

            font-weight: 700 !important;

            padding: 6px 3px !important;

            white-space: nowrap !important;

        }

        .dark-table tfoot .total-value {

            font-size: 8.5px !important;

            font-weight: 700 !important;

            white-space: nowrap !important;

        }


        /* ========================================================
           COLORS
           ======================================================== */

        .text-danger {
            color: #B91C1C !important;
        }

        .text-success {
            color: #15803D !important;
        }

        .text-warning {
            color: #B45309 !important;
        }

        .text-info {
            color: #0369A1 !important;
        }

        .text-cyan {
            color: #0369A1 !important;
        }

        .text-secondary {
            color: #475569 !important;
        }


        /* ========================================================
           RESPONSIVE TABLE
           ======================================================== */

        .table-responsive {

            overflow: visible !important;

            width: 100% !important;

        }


        /* ========================================================
           LETTERHEAD
           ======================================================== */

        .print-letterhead {

            display: flex;

            justify-content: space-between;

            align-items: center;

            gap: 15px;

            border-bottom: 3px solid #0F172A;

            padding-bottom: 10px;

            margin-bottom: 12px;
        }


        .print-letterhead .dealer-block {

            display: flex;

            align-items: center;

            min-width: 0;

        }


        .print-letterhead .dealer-logo {

            max-height: 65px;

            max-width: 145px;

            object-fit: contain;

            margin-right: 14px;

        }


        .print-letterhead .dealer-name {

            font-size: 20px;

            font-weight: 800;

            color: #0F172A;

            margin: 0 0 2px 0;

            letter-spacing: 0.2px;

        }


        .print-letterhead .dealer-tagline {

            font-size: 10px;

            color: #475569;

            margin: 0 0 3px 0;

        }


        .print-letterhead .dealer-contact {

            font-size: 9.5px;

            color: #334155;

            line-height: 1.4;

        }


        .print-letterhead .doc-type-box {

            text-align: right;

            flex-shrink: 0;

        }


        .print-letterhead .doc-type-box .doc-title {

            font-size: 13px;

            font-weight: 700;

            color: #0F172A;

            text-transform: uppercase;

            letter-spacing: 0.7px;

            border: 2px solid #0F172A;

            padding: 4px 10px;

            display: inline-block;

        }


        .print-letterhead .doc-type-box .doc-date {

            font-size: 9px;

            color: #475569;

            margin-top: 5px;

        }


        /* ========================================================
           PRINT KPI SUMMARY
           ======================================================== */

        .print-summary-strip {

            display: flex;

            gap: 8px;

            margin-bottom: 12px;
        }


        .print-summary-strip .sum-box {

            flex: 1;

            min-width: 0;

            border: 1px solid #CBD5E1;

            border-radius: 4px;

            padding: 6px 8px;

            text-align: center;
        }


        .print-summary-strip .sum-box .sum-label {

            font-size: 7px;

            text-transform: uppercase;

            color: #64748B;

            font-weight: 700;

            line-height: 1.2;

        }


        .print-summary-strip .sum-box .sum-value {

            font-size: 11px;

            font-weight: 800;

            color: #0F172A;

            margin-top: 2px;

            white-space: nowrap;

        }


        /* ========================================================
           PRINT FOOTER
           ======================================================== */

        .print-footer-block {

            margin-top: 30px;

            display: flex;

            justify-content: space-between;

            font-size: 9px;

        }


        .print-footer-block .sig-box {

            width: 40%;

            text-align: center;

        }


        .print-footer-block .sig-line {

            border-top: 1px solid #0F172A;

            margin-top: 35px;

            padding-top: 4px;

            font-weight: 600;

            color: #0F172A;

        }


        .print-generated-note {

            margin-top: 18px;

            font-size: 7.5px;

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
            Stock Valuation & Inventory Report
            (মজুদ স্টক মূল্যায়ন)
        </h2>

        <div class="page-subtitle">
            Current cement stock valuation based on landed purchase
            cost basis and retail sales value.
        </div>

    </div>


    <div class="d-flex gap-2">

        <button
            type="button"
            class="btn btn-primary-custom"
            onclick="window.print()"
        >

            <i class="fa-solid fa-print me-1"></i>

            Print Stock Valuation

        </button>

    </div>

</div>


<!-- ============================================================
     PRINT-ONLY LETTERHEAD
     ============================================================ -->

<div class="print-only-block">

    <div class="print-letterhead">

        <div class="dealer-block">

            <?php if ($dealerLogoUrl): ?>

                <img
                    src="<?php echo htmlspecialchars($dealerLogoUrl); ?>"
                    alt="<?php echo htmlspecialchars($dealerName); ?>"
                    class="dealer-logo"
                >

            <?php endif; ?>


            <div>

                <div class="dealer-name">

                    <?php
                    echo htmlspecialchars($dealerName);
                    ?>

                </div>


                <?php if ($dealerTagline): ?>

                    <div class="dealer-tagline">

                        <?php
                        echo htmlspecialchars($dealerTagline);
                        ?>

                    </div>

                <?php endif; ?>


                <div class="dealer-contact">

                    <?php if ($dealerAddress): ?>

                        <?php
                        echo htmlspecialchars($dealerAddress);
                        ?><br>

                    <?php endif; ?>


                    <?php if ($dealerPhone): ?>

                        Phone:
                        <?php
                        echo htmlspecialchars($dealerPhone);
                        ?>

                    <?php endif; ?>


                    <?php if ($dealerPhone && $dealerEmail): ?>

                        &nbsp;|&nbsp;

                    <?php endif; ?>


                    <?php if ($dealerEmail): ?>

                        Email:
                        <?php
                        echo htmlspecialchars($dealerEmail);
                        ?>

                    <?php endif; ?>

                </div>

            </div>

        </div>


        <div class="doc-type-box">

            <div class="doc-title">
                Stock Valuation Report
            </div>


            <div class="doc-date">

                Printed:
                <?php
                echo htmlspecialchars($printedAt);
                ?>


                <?php

                echo $printedBy
                    ? ' by ' . htmlspecialchars($printedBy)
                    : '';

                ?>

            </div>

        </div>

    </div>


    <!-- ========================================================
         PRINT SUMMARY
         ======================================================== -->

    <div class="print-summary-strip">


        <div class="sum-box">

            <div class="sum-label">
                Total Cement Stock
            </div>

            <div class="sum-value">

                <?php
                echo number_format($totalBags);
                ?>

                Bags

            </div>

        </div>


        <div class="sum-box">

            <div class="sum-label">
                Stock Cost Valuation
            </div>

            <div class="sum-value">

                <?php
                echo formatBDT($totalPurchaseValuation);
                ?>

            </div>

        </div>


        <div class="sum-box">

            <div class="sum-label">
                Stock Market Sale Value
            </div>

            <div class="sum-value">

                <?php
                echo formatBDT($totalSaleValuation);
                ?>

            </div>

        </div>


        <div
            class="sum-box"
            style="border-color:#0F172A;"
        >

            <div class="sum-label">
                Potential Realizable Margin
            </div>

            <div class="sum-value">

                <?php
                echo formatBDT($estimatedPotentialProfit);
                ?>

            </div>

        </div>


    </div>

</div>


<!-- ============================================================
     KPI SUMMARY STRIP - SCREEN
     ============================================================ -->

<div class="row g-3 mb-4 no-print">


    <!-- Total Stock -->

    <div class="col-md-3">

        <div class="dark-card py-3 text-center">

            <div class="text-secondary small fw-bold text-uppercase">

                Total Cement in Godown

            </div>


            <div
                class="fs-4 fw-bold"
                style="color: var(--text-primary);"
            >

                <?php
                echo number_format($totalBags);
                ?>

                Bags

            </div>

        </div>

    </div>


    <!-- Cost Valuation -->

    <div class="col-md-3">

        <div class="dark-card py-3 text-center">

            <div class="text-secondary small fw-bold text-uppercase">

                Stock Cost Valuation (COGS)

            </div>


            <div class="fs-4 fw-bold text-danger">

                <?php
                echo formatBDT($totalPurchaseValuation);
                ?>

            </div>

        </div>

    </div>


    <!-- Sale Value -->

    <div class="col-md-3">

        <div class="dark-card py-3 text-center">

            <div class="text-secondary small fw-bold text-uppercase">

                Stock Market Sale Value

            </div>


            <div class="fs-4 fw-bold text-cyan">

                <?php
                echo formatBDT($totalSaleValuation);
                ?>

            </div>

        </div>

    </div>


    <!-- Potential Profit -->

    <div class="col-md-3">

        <div class="dark-card py-3 text-center">

            <div class="text-secondary small fw-bold text-uppercase">

                Potential Realizable Margin

            </div>


            <div class="fs-4 fw-bold text-success">

                <?php
                echo formatBDT($estimatedPotentialProfit);
                ?>

            </div>

        </div>

    </div>

</div>


<!-- ============================================================
     STOCK VALUATION TABLE
     ============================================================ -->

<div class="dark-card">


    <div class="card-header-clean no-print">

        <div class="card-title-clean">

            <i class="fa-solid fa-boxes-stacked text-cyan"></i>

            <span>
                Brand-Wise Cement Inventory Valuation
            </span>

        </div>

    </div>


    <div class="table-responsive">


        <table class="dark-table">


            <!-- ==================================================
                 COLUMN WIDTH
                 ================================================== -->

            <colgroup>

                <col class="col-code">

                <col class="col-product">

                <col class="col-company">

                <col class="col-stock">

                <col class="col-cost-rate">

                <col class="col-sale-rate">

                <col class="col-total-cost">

                <col class="col-total-sale">

            </colgroup>


            <!-- ==================================================
                 TABLE HEADER
                 ================================================== -->

            <thead>

                <tr>

                    <th>
                        Code
                    </th>


                    <th>
                        Cement Product & Brand
                    </th>


                    <th>
                        Supplier / Company
                    </th>


                    <th class="text-end">
                        Current Stock
                    </th>


                    <th class="text-end">
                        Cost Basis / Bag (৳)
                    </th>


                    <th class="text-end">
                        Sale Rate / Bag (৳)
                    </th>


                    <th class="text-end">
                        Total Cost Value (৳)
                    </th>


                    <th class="text-end">
                        Total Retail Value (৳)
                    </th>

                </tr>

            </thead>


            <!-- ==================================================
                 TABLE BODY
                 ================================================== -->

            <tbody>


                <?php if (empty($products)): ?>


                    <tr>

                        <td
                            colspan="8"
                            class="text-center text-muted py-4"
                        >

                            No products found.

                        </td>

                    </tr>


                <?php else: ?>


                    <?php foreach ($products as $p): ?>


                        <?php

                        $stk =
                            (int) $p['current_stock'];

                        $pRate =
                            (float) $p['default_purchase_price'];

                        $sRate =
                            (float) $p['default_sale_price'];

                        $totCost =
                            $stk * $pRate;

                        $totSale =
                            $stk * $sRate;

                        ?>


                        <tr>


                            <!-- Product Code -->

                            <td
                                class="fw-bold text-info stock-code"
                            >

                                <?php

                                echo htmlspecialchars(
                                    $p['product_code']
                                );

                                ?>

                            </td>


                            <!-- Product -->

                            <td class="stock-product">

                                <div
                                    class="fw-bold stock-product-name"
                                    style="color: var(--text-primary);"
                                >

                                    <?php

                                    echo htmlspecialchars(
                                        $p['name']
                                    );

                                    ?>

                                </div>


                                <div
                                    class="text-secondary small stock-brand"
                                >

                                    <?php

                                    echo htmlspecialchars(
                                        $p['brand']
                                    );

                                    ?>

                                    (<?php
                                    echo (float) $p['bag_size_kg'];
                                    ?> kg)

                                </div>

                            </td>


                            <!-- Company -->

                            <td class="stock-company-cell">

                                <span
                                    class="badge-custom badge-primary stock-company"
                                >

                                    <?php

                                    echo htmlspecialchars(
                                        $p['company_name']
                                    );

                                    ?>

                                </span>

                            </td>


                            <!-- Current Stock -->

                            <td
                                class="text-end fw-bold text-cyan stock-number"
                            >

                                <?php

                                echo number_format($stk);

                                ?>

                                Bags

                            </td>


                            <!-- Purchase Rate -->

                            <td
                                class="text-end stock-amount"
                            >

                                <?php

                                echo formatBDT($pRate);

                                ?>

                            </td>


                            <!-- Sale Rate -->

                            <td
                                class="text-end stock-amount"
                                style="color: var(--text-primary);"
                            >

                                <?php

                                echo formatBDT($sRate);

                                ?>

                            </td>


                            <!-- Total Cost -->

                            <td
                                class="text-end fw-bold text-danger stock-total-cost"
                            >

                                <?php

                                echo formatBDT($totCost);

                                ?>

                            </td>


                            <!-- Total Retail -->

                            <td
                                class="text-end fw-bold text-success stock-total-sale"
                            >

                                <?php

                                echo formatBDT($totSale);

                                ?>

                            </td>


                        </tr>


                    <?php endforeach; ?>


                <?php endif; ?>


            </tbody>


            <!-- ==================================================
                 TABLE FOOTER
                 ================================================== -->

            <tfoot>

                <tr
                    class="fw-bold"
                    style="background: var(--bg-input);"
                >


                    <td
                        colspan="3"
                        class="text-end"
                    >

                        Godown Total:

                    </td>


                    <td
                        class="text-end text-cyan stock-number"
                    >

                        <?php

                        echo number_format($totalBags);

                        ?>

                        Bags

                    </td>


                    <td colspan="2"></td>


                    <td
                        class="text-end text-danger total-value"
                    >

                        <?php

                        echo formatBDT(
                            $totalPurchaseValuation
                        );

                        ?>

                    </td>


                    <td
                        class="text-end text-success total-value"
                    >

                        <?php

                        echo formatBDT(
                            $totalSaleValuation
                        );

                        ?>

                    </td>


                </tr>

            </tfoot>


        </table>

    </div>

</div>


<!-- ============================================================
     PRINT FOOTER
     ============================================================ -->

<div class="print-only-block">


    <div class="print-footer-block">


        <div class="sig-box">

            <div class="sig-line">

                Prepared By

            </div>

        </div>


        <div class="sig-box">

            <div class="sig-line">

                Authorized Signature —
                <?php

                echo htmlspecialchars(
                    $dealerName
                );

                ?>

            </div>

        </div>


    </div>


    <div class="print-generated-note">

        This is a computer-generated report from

        <?php

        echo htmlspecialchars(
            $dealerName
        );

        ?>

        's accounting system and does not require a physical stamp
        unless otherwise requested.

    </div>


</div>


<!-- ============================================================
     PRINT TITLE
     ============================================================ -->

<script>

(function () {

    var originalTitle = document.title;


    var printTitle =
        <?php

        echo json_encode(
            (
                $dealerName
                    ? $dealerName . ' — '
                    : ''
            )
            . 'Stock Valuation Report'
        );

        ?>;


    window.addEventListener(
        'beforeprint',
        function () {

            document.title = printTitle;

        }
    );


    window.addEventListener(
        'afterprint',
        function () {

            document.title = originalTitle;

        }
    );

})();

</script>


<?php

require_once __DIR__ . '/../../includes/footer.php';

?>

