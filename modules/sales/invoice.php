
<?php
/**
 * Maruf Traders - Printable Sales Invoice & Money Receipt
 *
 * Updated:
 * - Professional print-only letterhead
 * - Same logo/header positioning as Retailer Ledger
 * - Dealer information pulled from settings table
 * - Logo preserves original aspect ratio
 * - Invoice information aligned to the right
 * - Clean A4 black/white print layout
 * - Fixed table column overlap
 * - Amounts never truncated
 * - Screen UI remains unchanged
 */

define('APP_INIT', true);
$pageTitle = 'Sales Invoice';
$breadcrumb = 'Invoice View';
$activeMenu = 'sales';

require_once __DIR__ . '/../../includes/header.php';
requirePermission('sales.view');

$id = (int)($_GET['id'] ?? 0);

if (!$id) {
    echo '<div class="alert alert-danger">Invoice ID is missing.</div>';
    require_once __DIR__ . '/../../includes/footer.php';
    exit;
}

$db = Database::getConnection();

/* ============================================================
   FETCH SALE & RETAILER
   ============================================================ */

$stmt = $db->prepare("
    SELECT
        s.*,
        r.retailer_code,
        r.name AS retailer_name,
        r.mobile AS retailer_mobile,
        r.address AS retailer_address,
        r.current_due AS retailer_total_due,
        u.name AS creator_name
    FROM sales s
    JOIN retailers r ON s.retailer_id = r.id
    LEFT JOIN users u ON s.created_by = u.id
    WHERE s.id = :id AND s.tenant_id = :tenant
    LIMIT 1
");

$stmt->execute([
    ':id'     => $id,
    ':tenant' => TenantContext::id()
]);

$sale = $stmt->fetch();

if (!$sale) {
    echo '<div class="alert alert-danger">Invoice not found.</div>';
    require_once __DIR__ . '/../../includes/footer.php';
    exit;
}

/* ============================================================
   FETCH SALE ITEMS
   ============================================================ */

$iStmt = $db->prepare("
    SELECT
        si.*,
        p.name AS product_name,
        p.brand,
        p.unit,
        p.bag_size_kg,
        c.name AS company_name
    FROM sale_items si
    JOIN products p ON si.product_id = p.id
    JOIN companies c ON si.company_id = c.id
    WHERE si.sale_id = :sid AND si.tenant_id = :tenant
");

$iStmt->execute([
    ':sid'    => $id,
    ':tenant' => TenantContext::id()
]);

$items = $iStmt->fetchAll();

/* ============================================================
   TOTAL QUANTITY
   ============================================================ */

$totalBags = 0.0;

foreach ($items as $item) {
    $totalBags += (float)$item['quantity'];
}

/* ============================================================
   DEALER INFO FROM SETTINGS
   Confirmed setting keys:
   - business_name
   - business_title
   - business_address
   - business_mobile
   - business_email
   - logo_path
   ============================================================ */

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
    defined('BUSINESS_LOCATION')
        ? BUSINESS_LOCATION
        : ''
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

/* ============================================================
   DEALER LOGO URL
   Supports:
   - Full URL
   - Relative path
   ============================================================ */

$dealerLogoUrl = '';

if (!empty($dealerLogo)) {

    if (
        stripos($dealerLogo, 'http://') === 0 ||
        stripos($dealerLogo, 'https://') === 0
    ) {

        $dealerLogoUrl = $dealerLogo;

    } else {

        $dealerLogoUrl =
            rtrim(BASE_URL, '/') .
            '/' .
            ltrim($dealerLogo, '/');
    }
}

/* ============================================================
   DEALER INITIALS
   Used only for screen fallback
   ============================================================ */

$dealerInitials = '';

foreach (explode(' ', trim($dealerName)) as $word) {

    if ($word !== '') {
        $dealerInitials .= strtoupper(
            mb_substr($word, 0, 1)
        );
    }

    if (mb_strlen($dealerInitials) >= 2) {
        break;
    }
}

if ($dealerInitials === '') {
    $dealerInitials = 'MT';
}

$printedBy = $_SESSION['user_name'] ?? '';
$printedAt = date('d M, Y h:i A');

?>

<style>

/* ============================================================
   SCREEN
   ============================================================ */

.print-only-invoice-header {
    display: none;
}


/* ============================================================
   PRINT
   ============================================================ */

@media print {

    /* --------------------------------------------------------
       PAGE
       -------------------------------------------------------- */

    @page {
        size: A4;
        margin: 12mm 12mm;
    }

    html,
    body {
        background: #FFFFFF !important;
        color: #000000 !important;
    }

    body {
        margin: 0 !important;
        padding: 0 !important;
    }


    /* --------------------------------------------------------
       HIDE SCREEN-ONLY ELEMENTS
       -------------------------------------------------------- */

    .no-print {
        display: none !important;
    }

    .invoice-screen-business-header {
        display: none !important;
    }


    /* --------------------------------------------------------
       SHOW PRINT HEADER
       -------------------------------------------------------- */

    .print-only-invoice-header {
        display: block !important;
    }


    /* ========================================================
       PROFESSIONAL INVOICE LETTERHEAD
       Same concept/position as Retailer Ledger
       ======================================================== */

    .invoice-letterhead {

        display: flex !important;

        justify-content: space-between;

        align-items: center;

        width: 100%;

        border-bottom: 3px solid #0F172A;

        padding-bottom: 12px;

        margin-bottom: 16px;

        box-sizing: border-box;
    }


    /* --------------------------------------------------------
       DEALER LEFT BLOCK
       -------------------------------------------------------- */

    .invoice-letterhead .dealer-block {

        display: flex !important;

        align-items: center;

        flex: 1 1 auto;

        min-width: 0;

        box-sizing: border-box;
    }


    /* --------------------------------------------------------
       DEALER LOGO
       IMPORTANT:
       Do NOT force 48x48.
       Preserve original aspect ratio.
       -------------------------------------------------------- */

    .invoice-dealer-logo {

        display: block !important;

        width: auto !important;

        height: auto !important;

        max-width: 160px !important;

        max-height: 70px !important;

        object-fit: contain !important;

        object-position: center !important;

        background: transparent !important;

        border: none !important;

        margin-right: 16px !important;

        flex: 0 0 auto;

        box-sizing: border-box;
    }


    /* --------------------------------------------------------
       DEALER INFORMATION
       -------------------------------------------------------- */

    .invoice-letterhead .dealer-info {

        min-width: 0;

        max-width: 520px;
    }


    .invoice-letterhead .dealer-name {

        font-size: 22px;

        font-weight: 800;

        line-height: 1.2;

        color: #0F172A !important;

        margin: 0 0 2px 0;

        padding: 0;

        letter-spacing: 0.3px;

        word-wrap: break-word;

        overflow-wrap: break-word;
    }


    .invoice-letterhead .dealer-tagline {

        font-size: 11.5px;

        font-weight: 500;

        line-height: 1.35;

        color: #475569 !important;

        margin: 0 0 4px 0;

        padding: 0;

        word-wrap: break-word;

        overflow-wrap: break-word;
    }


    .invoice-letterhead .dealer-contact {

        font-size: 10.5px;

        line-height: 1.5;

        color: #334155 !important;

        word-wrap: break-word;

        overflow-wrap: break-word;
    }


    /* --------------------------------------------------------
       INVOICE RIGHT BLOCK
       -------------------------------------------------------- */

    .invoice-document-box {

        flex: 0 0 auto;

        min-width: 195px;

        margin-left: 20px;

        text-align: right;

        box-sizing: border-box;
    }


    .invoice-document-title {

        display: inline-block;

        font-size: 15px;

        font-weight: 700;

        line-height: 1.2;

        color: #0F172A !important;

        text-transform: uppercase;

        letter-spacing: 1px;

        border: 2px solid #0F172A;

        padding: 5px 14px;

        box-sizing: border-box;
    }


    .invoice-number {

        font-size: 11px;

        line-height: 1.4;

        color: #334155 !important;

        margin-top: 7px;
    }


    .invoice-number strong {

        color: #0F172A !important;

        font-weight: 700;
    }


    .invoice-date {

        font-size: 11px;

        line-height: 1.4;

        color: #475569 !important;

        margin-top: 3px;
    }


    /* --------------------------------------------------------
       INVOICE STATUS
       -------------------------------------------------------- */

    .invoice-status {

        display: inline-block;

        margin-top: 6px;

        padding: 3px 9px;

        border: 1px solid #94A3B8;

        font-size: 8.5px;

        font-weight: 700;

        line-height: 1.2;

        letter-spacing: 0.5px;

        text-transform: uppercase;

        box-sizing: border-box;
    }


    .invoice-status-paid {

        color: #15803D !important;

        border-color: #15803D !important;
    }


    .invoice-status-due {

        color: #B45309 !important;

        border-color: #B45309 !important;
    }


    .invoice-status-cancelled {

        color: #B91C1C !important;

        border-color: #B91C1C !important;
    }


    /* ========================================================
       GENERAL PRINT CARD
       ======================================================== */

    .dark-card {

        background: #FFFFFF !important;

        border: none !important;

        box-shadow: none !important;

        color: #000000 !important;

        max-width: 100% !important;

        padding: 0 !important;
    }


    /* ========================================================
       TEXT COLORS
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

    .text-primary-light {

        color: #1D4ED8 !important;
    }

    .text-secondary,
    .text-muted {

        color: #475569 !important;
    }

    [style*="var(--text-primary)"] {

        color: #0F172A !important;
    }


    /* ========================================================
       BADGES
       ======================================================== */

    .badge-custom {

        border: 1px solid #94A3B8 !important;

        background: #FFFFFF !important;

        color: #0F172A !important;
    }

    .badge-custom.badge-success {

        border-color: #15803D !important;

        color: #15803D !important;
    }

    .badge-custom.badge-warning {

        border-color: #B45309 !important;

        color: #B45309 !important;
    }

    .badge-custom.badge-danger {

        border-color: #B91C1C !important;

        color: #B91C1C !important;
    }


    /* ========================================================
       ITEMS TABLE
       ======================================================== */

    .table-responsive {

        width: 100% !important;

        overflow: visible !important;
    }


    .dark-table {

        width: 100% !important;

        table-layout: fixed !important;

        border-collapse: collapse !important;

        border-spacing: 0 !important;

        color: #000000 !important;

        box-sizing: border-box !important;
    }


    .dark-table,
    .dark-table th,
    .dark-table td {

        box-sizing: border-box !important;
    }


    /* --------------------------------------------------------
       COLUMN WIDTHS
       -------------------------------------------------------- */

    .dark-table col.col-idx {

        width: 5% !important;
    }

    .dark-table col.col-prod {

        width: 29% !important;
    }

    .dark-table col.col-mfg {

        width: 14% !important;
    }

    .dark-table col.col-qty {

        width: 15% !important;
    }

    .dark-table col.col-rate {

        width: 17% !important;
    }

    .dark-table col.col-total {

        width: 20% !important;
    }


    /* --------------------------------------------------------
       TABLE HEADER
       -------------------------------------------------------- */

    .dark-table th {

        background: #F1F5F9 !important;

        color: #0F172A !important;

        border: 1px solid #94A3B8 !important;

        font-size: 9.5px !important;

        font-weight: 700 !important;

        padding: 6px 5px !important;

        text-transform: uppercase;

        letter-spacing: 0.15px;

        line-height: 1.3;

        vertical-align: middle;

        white-space: normal !important;

        word-wrap: break-word;

        overflow-wrap: break-word;
    }


    /* --------------------------------------------------------
       TABLE BODY
       -------------------------------------------------------- */

    .dark-table td {

        border: 1px solid #CBD5E1 !important;

        color: #0F172A !important;

        font-size: 10.5px !important;

        padding: 6px 5px !important;

        line-height: 1.4;

        vertical-align: top;

        white-space: normal !important;

        word-wrap: break-word;

        overflow-wrap: break-word;

        word-break: normal;

        overflow: visible !important;

        text-overflow: unset !important;
    }


    /* --------------------------------------------------------
       PRODUCT CELL
       -------------------------------------------------------- */

    .dark-table td div {

        max-width: 100% !important;

        white-space: normal !important;

        word-wrap: break-word !important;

        overflow-wrap: break-word !important;

        word-break: normal !important;
    }


    .dark-table td .fw-bold {

        line-height: 1.35 !important;

        margin-bottom: 2px;
    }


    .dark-table td .small {

        font-size: 8.5px !important;

        line-height: 1.3 !important;
    }


    /* --------------------------------------------------------
       AMOUNT COLUMNS
       Never truncate money figures.
       -------------------------------------------------------- */

    .dark-table td.text-end {

        white-space: normal !important;

        word-break: normal !important;

        overflow: visible !important;

        text-overflow: unset !important;
    }


    /* --------------------------------------------------------
       TABLE FOOTER
       -------------------------------------------------------- */

    .dark-table tfoot tr {

        background: #F1F5F9 !important;
    }


    .dark-table tfoot td {

        font-weight: 700 !important;
    }


    /* ========================================================
       PROFIT / FINANCIAL BREAKDOWN
       ======================================================== */

    .profit-breakdown-box {

        background: #F8FAFC !important;

        border: 1px solid #CBD5E1 !important;

        color: #0F172A !important;
    }


    .profit-item {

        color: #0F172A !important;
    }


    /* ========================================================
       PRINT GENERATED NOTE
       ======================================================== */

    .print-generated-note {

        margin-top: 18px;

        padding-top: 6px;

        border-top: 1px dashed #CBD5E1;

        font-size: 9px;

        line-height: 1.4;

        color: #94A3B8 !important;

        text-align: center;
    }


    /* ========================================================
       SIGNATURE AREA
       ======================================================== */

    .invoice-signature-area {

        margin-top: 45px !important;

        padding-top: 20px !important;

        border-top: 1px solid #CBD5E1 !important;
    }


    .invoice-signature-line {

        max-width: 160px;

        margin: 40px auto 0 auto;

        border-top: 1px solid #0F172A;

        padding-top: 5px;

        color: #0F172A !important;

        font-size: 10px;

        line-height: 1.3;
    }


    /* ========================================================
       AVOID BAD PAGE BREAKS
       ======================================================== */

    .dark-table thead {

        display: table-header-group;
    }

    .dark-table tfoot {

        display: table-footer-group;
    }

    .dark-table tr {

        page-break-inside: avoid !important;
    }

    .profit-breakdown-box {

        page-break-inside: avoid !important;
    }

    .invoice-signature-area {

        page-break-inside: avoid !important;
    }

}


/* ============================================================
   SMALL PRINT PAPER / NARROW WIDTH SAFETY
   ============================================================ */

@media print and (max-width: 800px) {

    .invoice-letterhead .dealer-name {

        font-size: 18px;
    }

    .invoice-letterhead .dealer-tagline {

        font-size: 10px;
    }

    .invoice-letterhead .dealer-contact {

        font-size: 9px;
    }

    .invoice-dealer-logo {

        max-width: 120px !important;

        max-height: 60px !important;

        margin-right: 10px !important;
    }

    .invoice-document-box {

        min-width: 165px;

        margin-left: 12px;
    }

    .invoice-document-title {

        font-size: 12px;

        padding: 4px 9px;
    }

}

</style>


<!-- ============================================================
     SCREEN PAGE HEADER
     ============================================================ -->

<div class="page-header-container no-print">

    <div>

        <h2 class="page-title">
            Sales Invoice:
            <?php echo htmlspecialchars($sale['invoice_no']); ?>
        </h2>

        <div class="page-subtitle">
            Printable invoice & customer delivery challan.
        </div>

    </div>

    <div class="d-flex gap-2">

        <a
            href="<?php echo BASE_URL; ?>/modules/sales/index.php"
            class="btn btn-secondary-custom"
        >
            <i class="fa-solid fa-arrow-left me-1"></i>
            Back to Sales
        </a>

        <button
            type="button"
            class="btn btn-primary-custom"
            onclick="window.print()"
        >
            <i class="fa-solid fa-print me-1"></i>
            Print Invoice
        </button>

    </div>

</div>


<!-- ============================================================
     PRINT INSTRUCTION
     ============================================================ -->

<div
    class="no-print"
    style="
        font-size:12px;
        color:var(--text-secondary,#64748B);
        margin-top:-10px;
        margin-bottom:16px;
    "
>

    <i class="fa-solid fa-circle-info me-1"></i>

    আপনার ব্রাউজারের Print ডায়ালগে
    <strong>"Headers and footers"</strong>
    অপশনটি বন্ধ (uncheck) রাখুন —
    নাহলে ব্রাউজার নিজে থেকে উপরে page title
    এবং নিচে URL দেখাবে।

</div>


<?php if ($sale): ?>


<!-- ============================================================
     PRINT-ONLY PROFESSIONAL LETTERHEAD
     SAME POSITION / STYLE AS RETAILER LEDGER
     ============================================================ -->

<div class="print-only-invoice-header">

    <div class="invoice-letterhead">

        <!-- ====================================================
             LEFT: LOGO + DEALER INFORMATION
             ==================================================== -->

        <div class="dealer-block">

            <?php if ($dealerLogoUrl): ?>

                <img
                    src="<?php echo htmlspecialchars($dealerLogoUrl); ?>"
                    alt="<?php echo htmlspecialchars($dealerName); ?>"
                    class="invoice-dealer-logo"
                >

            <?php endif; ?>


            <div class="dealer-info">

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

                        <span>
                            <?php echo htmlspecialchars($dealerAddress); ?>
                        </span>

                    <?php endif; ?>


                    <?php if ($dealerPhone): ?>

                        <?php if ($dealerAddress): ?>
                            &nbsp;|&nbsp;
                        <?php endif; ?>

                        <span>
                            Phone:
                            <?php echo htmlspecialchars($dealerPhone); ?>
                        </span>

                    <?php endif; ?>


                    <?php if ($dealerEmail): ?>

                        <?php if ($dealerAddress || $dealerPhone): ?>
                            &nbsp;|&nbsp;
                        <?php endif; ?>

                        <span>
                            Email:
                            <?php echo htmlspecialchars($dealerEmail); ?>
                        </span>

                    <?php endif; ?>

                </div>

            </div>

        </div>


        <!-- ====================================================
             RIGHT: DOCUMENT INFORMATION
             ==================================================== -->

        <div class="invoice-document-box">

            <div class="invoice-document-title">
                SALES INVOICE
            </div>


            <div class="invoice-number">

                Invoice No:

                <strong>
                    <?php echo htmlspecialchars($sale['invoice_no']); ?>
                </strong>

            </div>


            <div class="invoice-date">

                Invoice Date:

                <?php echo formatDate($sale['sale_date']); ?>

            </div>


            <?php if ($sale['status'] === 'cancelled'): ?>

                <div class="invoice-status invoice-status-cancelled">
                    VOID / CANCELLED
                </div>

            <?php elseif ($sale['payment_status'] === 'paid'): ?>

                <div class="invoice-status invoice-status-paid">
                    PAID
                </div>

            <?php else: ?>

                <div class="invoice-status invoice-status-due">
                    <?php echo strtoupper($sale['payment_status']); ?>
                </div>

            <?php endif; ?>

        </div>

    </div>

</div>


<!-- ============================================================
     MAIN INVOICE CARD
     ============================================================ -->

<div
    class="dark-card p-4 p-md-5"
    style="
        max-width:900px;
        margin:0 auto;
    "
>


    <!-- ========================================================
         ORIGINAL SCREEN BUSINESS HEADER
         Hidden during print
         ======================================================== -->

    <div
        class="
            row
            align-items-center
            pb-4
            mb-4
            border-bottom
            border-secondary
            border-opacity-25
            invoice-screen-business-header
        "
    >

        <div class="col-sm-7">

            <div class="d-flex align-items-center gap-3 mb-2">

                <?php if ($dealerLogoUrl): ?>

                    <img
                        src="<?php echo htmlspecialchars($dealerLogoUrl); ?>"
                        alt="<?php echo htmlspecialchars($dealerName); ?>"
                        style="
                            width:48px;
                            height:48px;
                            object-fit:contain;
                            background:transparent;
                        "
                    >

                <?php else: ?>

                    <div
                        class="brand-icon"
                        style="
                            width:48px;
                            height:48px;
                            font-size:1.3rem;
                        "
                    >
                        <?php echo htmlspecialchars($dealerInitials); ?>
                    </div>

                <?php endif; ?>


                <div>

                    <h3
                        class="fw-bold mb-0"
                        style="color:var(--text-primary);"
                    >
                        <?php echo htmlspecialchars(mb_strtoupper($dealerName)); ?>
                    </h3>

                    <div class="text-secondary small font-monospace">
                        <?php echo htmlspecialchars($dealerTagline); ?>
                    </div>

                </div>

            </div>


            <div class="text-secondary small">

                <?php if ($dealerAddress): ?>

                    <div>

                        <i class="fa-solid fa-location-dot me-1 text-danger"></i>

                        <?php echo htmlspecialchars($dealerAddress); ?>

                    </div>

                <?php endif; ?>


                <?php if ($dealerPhone): ?>

                    <div>

                        <i class="fa-solid fa-phone me-1 text-cyan"></i>

                        <?php echo htmlspecialchars($dealerPhone); ?>

                    </div>

                <?php endif; ?>


                <?php if ($dealerEmail): ?>

                    <div>

                        <i class="fa-solid fa-envelope me-1 text-primary-light"></i>

                        <?php echo htmlspecialchars($dealerEmail); ?>

                    </div>

                <?php endif; ?>

            </div>

        </div>


        <div class="col-sm-5 text-sm-end mt-3 mt-sm-0">

            <div
                class="
                    badge-custom
                    <?php
                    echo
                        ($sale['status'] === 'cancelled')
                            ? 'badge-danger'
                            :
                            (
                                ($sale['payment_status'] === 'paid')
                                    ? 'badge-success'
                                    : 'badge-warning'
                            );
                    ?>
                    fs-6
                    mb-2
                "
            >

                <?php
                echo strtoupper(
                    $sale['status'] === 'cancelled'
                        ? 'VOID / CANCELLED'
                        : $sale['payment_status']
                );
                ?>

            </div>


            <div class="text-secondary small">
                Invoice Number:
            </div>


            <div class="fw-bold text-cyan fs-5 font-monospace">

                <?php echo htmlspecialchars($sale['invoice_no']); ?>

            </div>


            <div class="text-secondary small mt-1">

                Invoice Date:

                <strong>
                    <?php echo formatDate($sale['sale_date']); ?>
                </strong>

            </div>

        </div>

    </div>


    <!-- ========================================================
         BILL TO & DELIVERY META
         ======================================================== -->

    <div class="row mb-4">


        <!-- BILL TO -->

        <div class="col-sm-6">

            <div class="text-secondary text-uppercase fw-bold small mb-2">

                Bill To (ক্রেতার বিবরণ):

            </div>


            <h5
                class="fw-bold mb-1"
                style="color:var(--text-primary);"
            >
                <?php echo htmlspecialchars($sale['retailer_name']); ?>
            </h5>


            <div class="text-secondary small mb-1">

                Retailer Code:

                <strong>
                    <?php echo htmlspecialchars($sale['retailer_code']); ?>
                </strong>

            </div>


            <div class="text-secondary small mb-1">

                Mobile:

                <?php echo htmlspecialchars($sale['retailer_mobile']); ?>

            </div>


            <div class="text-secondary small">

                Address:

                <?php
                echo htmlspecialchars(
                    $sale['retailer_address'] ?: '—'
                );
                ?>

            </div>

        </div>


        <!-- DELIVERY -->

        <div class="col-sm-6 text-sm-end mt-3 mt-sm-0">

            <div class="text-secondary text-uppercase fw-bold small mb-2">

                Shipment / Delivery:

            </div>


            <div class="text-secondary small">

                Delivery Site:

                <strong style="color:var(--text-primary);">

                    <?php
                    echo htmlspecialchars(
                        $sale['delivery_address']
                            ?: 'Customer Site'
                    );
                    ?>

                </strong>

            </div>


            <div class="text-secondary small">

                Transport / Truck:

                <?php
                echo htmlspecialchars(
                    $sale['driver_info']
                        ?: 'Regular Delivery'
                );
                ?>

            </div>


            <div class="text-secondary small">

                Billed By:

                <?php
                echo htmlspecialchars(
                    $sale['creator_name']
                        ?: 'System'
                );
                ?>

            </div>

        </div>

    </div>


    <!-- ========================================================
         ITEMS TABLE
         ======================================================== -->

    <div class="table-responsive mb-4">

        <table class="dark-table">

            <colgroup>

                <col class="col-idx">

                <col class="col-prod">

                <col class="col-mfg">

                <col class="col-qty">

                <col class="col-rate">

                <col class="col-total">

            </colgroup>


            <thead>

                <tr>

                    <th>
                        #
                    </th>

                    <th>
                        Cement Brand
                    </th>

                    <th>
                        Manuf.
                    </th>

                    <th class="text-end">
                        Quantity
                    </th>

                    <th class="text-end">
                        Unit Rate (৳)
                    </th>

                    <th class="text-end">
                        Total Amount (৳)
                    </th>

                </tr>

            </thead>


            <tbody>

                <?php
                $idx = 1;
                ?>

                <?php foreach ($items as $item): ?>

                    <tr>

                        <td>

                            <?php echo $idx++; ?>

                        </td>


                        <td>

                            <div
                                class="fw-bold"
                                style="color:var(--text-primary);"
                            >

                                <?php
                                echo htmlspecialchars(
                                    $item['product_name']
                                );
                                ?>

                            </div>


                            <div class="text-secondary small">

                                <?php
                                echo htmlspecialchars(
                                    $item['brand']
                                );
                                ?>

                                (
                                <?php
                                echo (float)$item['bag_size_kg'];
                                ?>
                                kg)

                            </div>

                        </td>


                        <td>

                            <?php
                            echo htmlspecialchars(
                                $item['company_name']
                            );
                            ?>

                        </td>


                        <td class="text-end fw-bold text-cyan">

                            <?php
                            echo number_format(
                                $item['quantity']
                            );
                            ?>

                            Bags

                        </td>


                        <td class="text-end">

                            <?php
                            echo formatBDT(
                                $item['unit_price']
                            );
                            ?>

                        </td>


                        <td
                            class="text-end fw-bold"
                            style="color:var(--text-primary);"
                        >

                            <?php
                            echo formatBDT(
                                $item['total_price']
                            );
                            ?>

                        </td>

                    </tr>

                <?php endforeach; ?>

            </tbody>


            <tfoot>

                <tr
                    class="fw-bold"
                    style="background:var(--bg-input);"
                >

                    <td colspan="3" class="text-end">

                        Total Bags:

                    </td>


                    <td class="text-end text-cyan">

                        <?php
                        echo number_format($totalBags);
                        ?>

                        Bags

                    </td>


                    <td class="text-end">

                        Subtotal:

                    </td>


                    <td
                        class="text-end"
                        style="color:var(--text-primary);"
                    >

                        <?php
                        echo formatBDT(
                            $sale['subtotal']
                        );
                        ?>

                    </td>

                </tr>

            </tfoot>

        </table>

    </div>


    <!-- ========================================================
         FINANCIAL BREAKDOWN
         ======================================================== -->

    <div class="row justify-content-end mb-4">

        <div class="col-md-6">

            <div class="profit-breakdown-box">


                <!-- SUBTOTAL -->

                <div class="profit-item">

                    <span class="text-secondary">
                        Subtotal Amount:
                    </span>

                    <span style="color:var(--text-primary);">

                        <?php
                        echo formatBDT(
                            $sale['subtotal']
                        );
                        ?>

                    </span>

                </div>


                <!-- DISCOUNT -->

                <?php if ((float)$sale['discount'] > 0): ?>

                    <div class="profit-item">

                        <span class="text-secondary">
                            Special Discount:
                        </span>

                        <span class="text-warning">

                            -
                            <?php
                            echo formatBDT(
                                $sale['discount']
                            );
                            ?>

                        </span>

                    </div>

                <?php endif; ?>


                <!-- NET TOTAL -->

                <div class="profit-item">

                    <span class="text-secondary fw-bold">
                        Net Total Invoice:
                    </span>

                    <span class="text-cyan fw-bold fs-6">

                        <?php
                        echo formatBDT(
                            $sale['total_amount']
                        );
                        ?>

                    </span>

                </div>


                <!-- ADVANCE -->

                <?php if ((float)$sale['advance_deducted'] > 0): ?>

                    <div class="profit-item">

                        <span class="text-secondary">
                            Deducted from Advance:
                        </span>

                        <span class="text-success">

                            <?php
                            echo formatBDT(
                                $sale['advance_deducted']
                            );
                            ?>

                        </span>

                    </div>

                <?php endif; ?>


                <!-- PAID -->

                <div class="profit-item">

                    <span class="text-secondary">
                        Paid / Received:
                    </span>

                    <span class="text-success fw-bold">

                        <?php
                        echo formatBDT(
                            $sale['paid_amount']
                        );
                        ?>

                        (
                        <?php
                        echo htmlspecialchars(
                            $sale['payment_method']
                        );
                        ?>
                        )

                    </span>

                </div>


                <!-- DUE -->

                <div class="profit-item net-profit">

                    <span style="color:var(--text-primary);">

                        This Invoice Due:

                    </span>


                    <span class="text-danger fw-bold fs-5">

                        <?php
                        echo formatBDT(
                            $sale['due_amount']
                        );
                        ?>

                    </span>

                </div>


            </div>

        </div>

    </div>


    <!-- ========================================================
         SIGNATURES
         ======================================================== -->

    <div
        class="
            row
            invoice-signature-area
            text-center
            text-secondary
            small
        "
    >

        <div class="col-4">

            <div class="invoice-signature-line">

                Customer Signature

            </div>

        </div>


        <div class="col-4">

            <div class="invoice-signature-line">

                Delivery In-Charge

            </div>

        </div>


        <div class="col-4">

            <div class="invoice-signature-line">

                Authorized

                <?php
                echo htmlspecialchars($dealerName);
                ?>

            </div>

        </div>

    </div>


    <!-- ========================================================
         THANK YOU
         ======================================================== -->

    <div class="text-center text-muted small mt-4">

        Thank you for your business!

        /

        <?php
        echo htmlspecialchars($dealerName);
        ?>
        -এর সাথে ব্যবসা করার জন্য ধন্যবাদ।

    </div>


    <!-- ========================================================
         PRINT GENERATED NOTE
         ======================================================== -->

    <div class="print-generated-note">

        This is a computer-generated invoice from

        <?php
        echo htmlspecialchars($dealerName);
        ?>

        's billing system.

    </div>


</div>


<?php endif; ?>


<!-- ============================================================
     PRINT TITLE
     ============================================================ -->

<script>

(function () {

    var originalTitle = document.title;


    var printTitle =
        <?php
        echo json_encode(
            $dealerName .
            ' — Invoice ' .
            $sale['invoice_no']
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

