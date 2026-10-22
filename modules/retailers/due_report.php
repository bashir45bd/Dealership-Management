
<?php
/**
 * Maruf Traders - Retailer Due Summary Report
 *
 * Professional retailer due report with:
 * - Dealer information from settings
 * - Outstanding retailer dues
 * - Credit limit
 * - Current due
 * - Print-only professional letterhead
 *
 * Updated:
 * - Removed Limit Status column
 * - Removed credit utilization progress bar
 * - Fixed table text overlapping
 * - Reduced Total Market Due font size
 * - Improved responsive/print table layout
 */

define('APP_INIT', true);

$pageTitle = 'Retailer Due Report';
$breadcrumb = 'Retailer Dues';
$activeMenu = 'report_retailer';

require_once __DIR__ . '/../../includes/header.php';
requirePermission('reports.financial');

$db = Database::getConnection();
$tenantId = TenantContext::id();

/**
 * ============================================================
 * GET RETAILERS WITH OUTSTANDING DUES
 * ============================================================
 */
$stmt = $db->prepare("
    SELECT *
    FROM retailers
    WHERE current_due > 0 AND tenant_id = :tenant
    ORDER BY current_due DESC
");
$stmt->execute([':tenant' => $tenantId]);

$dueRetailers = $stmt->fetchAll();

/**
 * ============================================================
 * TOTALS
 * ============================================================
 */
$totalDue = 0;
$totalCreditLimit = 0;

foreach ($dueRetailers as $r) {
    $totalDue += (float) $r['current_due'];
    $totalCreditLimit += (float) $r['credit_limit'];
}

/**
 * ============================================================
 * DEALER INFO FROM SETTINGS
 * ============================================================
 */
$dealerName    = getSetting('business_name', 'Maruf Traders');
$dealerTagline = getSetting('business_title', 'Cement Dealership & Distribution');
$dealerAddress = getSetting(
    'business_address',
    'সুন্দরপুর বাজার, সাটিয়াজুরি, চুনারুঘাট, হবিগঞ্জ'
);
$dealerPhone   = getSetting('business_mobile', '');
$dealerEmail   = getSetting('business_email', '');
$dealerLogo    = getSetting('logo_path', '');

$dealerLogoUrl = '';

if (!empty($dealerLogo)) {
    $dealerLogoUrl = (
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
       SCREEN TABLE
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
        white-space: nowrap;
        vertical-align: middle;
    }

    .dark-table td {
        vertical-align: middle;
        overflow-wrap: anywhere;
        word-break: break-word;
    }

    /*
     * Prevent unnecessary wrapping for numeric fields.
     */
    .dark-table .amount-cell,
    .dark-table .due-cell {
        white-space: nowrap;
    }

    .dark-table .retailer-mobile {
        white-space: nowrap;
    }

    /*
     * Retailer name can wrap naturally if it is long.
     */
    .dark-table .retailer-name {
        overflow-wrap: anywhere;
        word-break: break-word;
    }

    /*
     * Smaller Due amount.
     */
    .dark-table .due-cell {
        font-size: 13px !important;
        font-weight: 700;
    }

    /*
     * Smaller Total Market Due.
     */
    .dark-table .total-due {
        font-size: 12px !important;
        font-weight: 700 !important;
        white-space: nowrap !important;
    }


    /* ============================================================
       PRINT-ONLY PROFESSIONAL LETTERHEAD
       ============================================================ */

    .print-only-block {
        display: none;
    }

    @media print {

        @page {
            size: A4;
            margin: 14mm 12mm;
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

        .dark-table,
        .dark-table th,
        .dark-table td {
            box-sizing: border-box !important;
        }

        /*
         * 6 visible print columns:
         *
         * # | Code | Name | Mobile | Credit Limit | Due
         *
         * Actions is .no-print
         */
        .dark-table col.col-num {
            width: 5%;
        }

        .dark-table col.col-code {
            width: 12%;
        }

        .dark-table col.col-name {
            width: 25%;
        }

        .dark-table col.col-mobile {
            width: 16%;
        }

        .dark-table col.col-limit {
            width: 20%;
        }

        .dark-table col.col-due {
            width: 22%;
        }

        .dark-table th {
            background: #F1F5F9 !important;
            color: #0F172A !important;
            border: 1px solid #94A3B8 !important;

            font-size: 9px !important;
            padding: 6px 4px !important;

            text-transform: uppercase;
            letter-spacing: 0.1px;
            line-height: 1.3;

            vertical-align: middle;

            /*
             * Header should not overlap.
             */
            white-space: normal !important;
            overflow-wrap: break-word;
            word-break: normal;
        }

        .dark-table td {
            border: 1px solid #CBD5E1 !important;
            color: #0F172A !important;

            font-size: 9px !important;
            padding: 6px 4px !important;

            line-height: 1.35;
            vertical-align: middle;

            white-space: normal !important;
            overflow-wrap: anywhere !important;
            word-break: break-word !important;

            overflow: visible !important;
            text-overflow: unset !important;
        }

        /*
         * Retailer code.
         */
        .dark-table td.retailer-code {
            white-space: nowrap !important;
            overflow: hidden !important;
            text-overflow: ellipsis !important;
        }

        /*
         * Retailer name.
         */
        .dark-table td.retailer-name {
            overflow-wrap: anywhere !important;
            word-break: break-word !important;
        }

        /*
         * Mobile number stays in one line.
         */
        .dark-table td.retailer-mobile {
            white-space: nowrap !important;
            word-break: normal !important;
            overflow-wrap: normal !important;
            font-size: 8.5px !important;
        }

        /*
         * Credit Limit and Due.
         * Keep financial figures together.
         */
        .dark-table td.amount-cell,
        .dark-table td.due-cell {
            white-space: nowrap !important;
            word-break: normal !important;
            overflow-wrap: normal !important;

            font-size: 8.5px !important;
        }

        /*
         * Due is slightly emphasized.
         */
        .dark-table td.due-cell {
            font-size: 9px !important;
            font-weight: 700 !important;
        }

        /*
         * Total Market Due.
         */
        .dark-table .total-due {
            font-size: 10px !important;
            font-weight: 700 !important;
            white-space: nowrap !important;
        }

        .dark-table tfoot tr {
            background: #F8FAFC !important;
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
           TABLE RESPONSIVE
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

            border-bottom: 3px solid #0F172A;

            padding-bottom: 12px;
            margin-bottom: 14px;

            gap: 15px;
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

            min-width: 0;
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

        .print-letterhead .doc-type-box {
            text-align: right;
            flex-shrink: 0;
        }

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


        /* ========================================================
           SUMMARY STRIP
           ======================================================== */

        .print-summary-strip {
            display: flex;

            gap: 12px;

            margin-bottom: 16px;
        }

        .print-summary-strip .sum-box {
            flex: 1;

            border: 1px solid #CBD5E1;

            border-radius: 4px;

            padding: 8px 10px;

            text-align: center;

            min-width: 0;
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

            white-space: nowrap;
        }


        /* ========================================================
           FOOTER / SIGNATURES
           ======================================================== */

        .print-footer-block {
            margin-top: 40px;

            display: flex;

            justify-content: space-between;

            font-size: 11px;
        }

        .print-footer-block .sig-box {
            width: 42%;

            text-align: center;
        }

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
     PAGE HEADER
     ============================================================ -->

<div class="page-header-container no-print">

    <div>
        <h2 class="page-title">
            Retailer Due Report (রিটেইলার বকেয়া রিপোর্ট)
        </h2>

        <div class="page-subtitle">
            Summary of all outstanding retailer dues, credit limits, and outstanding balances.
        </div>
    </div>

    <div class="d-flex gap-2">

        <a
            href="<?php echo BASE_URL; ?>/modules/retailers/index.php"
            class="btn btn-secondary-custom"
        >
            <i class="fa-solid fa-users me-1"></i>
            Retailers List
        </a>

        <button
            type="button"
            class="btn btn-primary-custom"
            onclick="window.print()"
        >
            <i class="fa-solid fa-print me-1"></i>
            Print Due List
        </button>

    </div>

</div>


<!-- ============================================================
     PRINT INFORMATION
     ============================================================ -->

<div
    class="no-print"
    style="
        font-size: 12px;
        color: var(--text-secondary, #64748B);
        margin-top: -10px;
        margin-bottom: 16px;
    "
>
    <i class="fa-solid fa-circle-info me-1"></i>

    আপনার ব্রাউজারের Print ডায়ালগে
    "Headers and footers"
    অপশনটা বন্ধ (uncheck) রাখুন —
    নাহলে ব্রাউজার নিজে থেকে উপরে পেজ টাইটেল আর নিচে URL দেখাবে,
    যেটা এই ডিজাইনের অংশ না।
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
                    <?php echo htmlspecialchars($dealerName); ?>
                </div>

                <?php if ($dealerTagline): ?>

                    <div class="dealer-tagline">
                        <?php echo htmlspecialchars($dealerTagline); ?>
                    </div>

                <?php endif; ?>

                <div class="dealer-contact">

                    <?php if ($dealerAddress): ?>

                        <?php echo htmlspecialchars($dealerAddress); ?><br>

                    <?php endif; ?>

                    <?php if ($dealerPhone): ?>

                        Phone:
                        <?php echo htmlspecialchars($dealerPhone); ?>

                    <?php endif; ?>

                    <?php if ($dealerPhone && $dealerEmail): ?>

                        &nbsp;|&nbsp;

                    <?php endif; ?>

                    <?php if ($dealerEmail): ?>

                        Email:
                        <?php echo htmlspecialchars($dealerEmail); ?>

                    <?php endif; ?>

                </div>

            </div>

        </div>


        <div class="doc-type-box">

            <div class="doc-title">
                Retailer Due Report
            </div>

            <div class="doc-date">

                Printed:
                <?php echo htmlspecialchars($printedAt); ?>

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
                Parties with Due
            </div>

            <div class="sum-value">
                <?php echo count($dueRetailers); ?>
            </div>

        </div>


        <div class="sum-box">

            <div class="sum-label">
                Total Credit Limit Extended
            </div>

            <div class="sum-value">
                <?php echo formatBDT($totalCreditLimit); ?>
            </div>

        </div>


        <div
            class="sum-box"
            style="border-color:#0F172A;"
        >

            <div class="sum-label">
                Total Market Due Receivable
            </div>

            <div class="sum-value">
                <?php echo formatBDT($totalDue); ?>
            </div>

        </div>

    </div>

</div>


<!-- ============================================================
     SCREEN-ONLY SUMMARY CARDS
     ============================================================ -->

<div class="row g-3 mb-4 no-print">

    <div class="col-md-6">

        <div class="dark-card py-3">

            <div class="text-secondary small fw-bold text-uppercase">
                Parties with Due
            </div>

            <div
                class="fs-3 fw-bold"
                style="color: var(--text-primary);"
            >
                <?php echo count($dueRetailers); ?>
                Retailers
            </div>

        </div>

    </div>


    <div class="col-md-6">

        <div class="dark-card py-3">

            <div class="text-secondary small fw-bold text-uppercase">
                Total Market Due Receivable
            </div>

            <div class="fs-3 fw-bold text-danger">
                <?php echo formatBDT($totalDue); ?>
            </div>

        </div>

    </div>

</div>


<!-- ============================================================
     RETAILER DUE TABLE
     ============================================================ -->

<div class="dark-card">

    <div class="card-header-clean no-print">

        <div class="card-title-clean">

            <i class="fa-solid fa-triangle-exclamation text-warning"></i>

            <span>
                Outstanding Retailer Accounts
            </span>

        </div>

    </div>


    <div class="table-responsive">

        <table class="dark-table">

            <!-- ==================================================
                 COLUMN WIDTH
                 Limit Status column removed
                 ================================================== -->

            <colgroup>

                <col class="col-num">
                <col class="col-code">
                <col class="col-name">
                <col class="col-mobile">
                <col class="col-limit">
                <col class="col-due">

            </colgroup>


            <!-- ==================================================
                 TABLE HEADER
                 ================================================== -->

            <thead>

                <tr>

                    <th>
                        #
                    </th>

                    <th>
                        Code
                    </th>

                    <th>
                        Name
                    </th>

                    <th>
                        Mobile
                    </th>

                    <th class="text-end">
                        Credit Limit
                    </th>

                    <th class="text-end">
                        Due
                    </th>

                    <!-- Actions remains on screen only -->
                    <th class="text-end no-print">
                        Actions
                    </th>

                </tr>

            </thead>


            <!-- ==================================================
                 TABLE BODY
                 ================================================== -->

            <tbody>

                <?php if (empty($dueRetailers)): ?>

                    <tr>

                        <td
                            colspan="7"
                            class="text-center text-muted py-4"
                        >
                            No outstanding dues found!
                            All accounts are fully settled.
                        </td>

                    </tr>

                <?php else: ?>

                    <?php
                    $i = 1;

                    foreach ($dueRetailers as $r):

                        $creditLimit = (float) $r['credit_limit'];
                        $currentDue  = (float) $r['current_due'];
                    ?>

                        <tr>

                            <!-- Number -->
                            <td>
                                <?php echo $i++; ?>
                            </td>


                            <!-- Retailer Code -->
                            <td
                                class="fw-bold text-info retailer-code"
                            >
                                <?php
                                echo htmlspecialchars(
                                    $r['retailer_code']
                                );
                                ?>
                            </td>


                            <!-- Retailer Name -->
                            <td
                                class="fw-bold retailer-name"
                                style="color: var(--text-primary);"
                            >
                                <?php
                                echo htmlspecialchars(
                                    $r['name']
                                );
                                ?>
                            </td>


                            <!-- Mobile -->
                            <td class="retailer-mobile">

                                <?php
                                echo htmlspecialchars(
                                    $r['mobile']
                                );
                                ?>

                            </td>


                            <!-- Credit Limit -->
                            <td class="text-end amount-cell">

                                <?php
                                echo formatBDT($creditLimit);
                                ?>

                            </td>


                            <!-- Current Due -->
                            <td
                                class="text-end fw-bold text-danger due-cell"
                            >

                                <?php
                                echo formatBDT($currentDue);
                                ?>

                            </td>


                            <!-- Actions -->
                            <td class="text-end no-print">

                                <a
                                    href="<?php
                                        echo BASE_URL;
                                    ?>/modules/retailers/ledger.php?id=<?php
                                        echo $r['id'];
                                    ?>"
                                    class="btn btn-secondary-custom btn-sm"
                                >

                                    <i
                                        class="fa-solid fa-book-open text-cyan me-1"
                                    ></i>

                                    Ledger

                                </a>

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
                        colspan="5"
                        class="text-end"
                    >
                        Total Market Due:
                    </td>

                    <td
                        class="text-end text-danger total-due"
                    >
                        <?php
                        echo formatBDT($totalDue);
                        ?>
                    </td>

                    <td class="no-print"></td>

                </tr>

            </tfoot>

        </table>

    </div>

</div>


<!-- ============================================================
     PRINT-ONLY FOOTER
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
                echo htmlspecialchars($dealerName);
                ?>

            </div>

        </div>

    </div>


    <div class="print-generated-note">

        This is a computer-generated report from
        <?php echo htmlspecialchars($dealerName); ?>'s
        accounting system and does not require a physical stamp
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
                ($dealerName ? $dealerName . ' — ' : '') .
                'Retailer Due Report'
            );
            ?>;

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

