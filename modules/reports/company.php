<?php
/**
 * Maruf Traders - Company Summary Report
 * (Updated: adds a professional print-only letterhead pulling dealer
 *  info + logo from the `settings` table via getSetting(), matching
 *  the retailer ledger/statement page's print design.)
 */

define('APP_INIT', true);
$pageTitle = 'Company Summary Report';
$breadcrumb = 'Company Report';
$activeMenu = 'report_company';

require_once __DIR__ . '/../../includes/header.php';
requirePermission('reports.financial');

$db = Database::getConnection();
$tenantId = TenantContext::id();

$stmt = $db->prepare("SELECT * FROM companies WHERE tenant_id = :tenant ORDER BY current_payable DESC");
$stmt->execute([':tenant' => $tenantId]);
$companies = $stmt->fetchAll();

$totOpening = 0;
$totPayable = 0;
foreach ($companies as $c) {
    $totOpening += (float)$c['opening_payable'];
    $totPayable += (float)$c['current_payable'];
}

// ============================================================
// DEALER INFO FROM SETTINGS (matches retailer ledger page)
// ============================================================
$dealerName    = getSetting('business_name', 'Maruf Traders');
$dealerTagline = getSetting('business_title', 'Cement Dealership & Distribution');
$dealerAddress = getSetting('business_address', 'সুন্দরপুর বাজার, সাটিয়াজুরি, চুনারুঘাট, হবিগঞ্জ');
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
    /* ============================================================
       PRINT-ONLY PROFESSIONAL LETTERHEAD STYLES
       Scoped to this page. On screen, .print-only-block is hidden;
       when printing, the normal app chrome is hidden and this
       letterhead takes over. Same pattern as retailer ledger page.
       ============================================================ */
    .print-only-block { display: none; }

    @media print {
        @page {
            size: A4;
            margin: 14mm 12mm;
        }

        body {
            background: #FFFFFF !important;
        }

        .print-only-block { display: block !important; }

        /* Hide anything not meant for the printed page */
        .no-print { display: none !important; }

        .dark-card {
            background: #FFFFFF !important;
            border: none !important;
            box-shadow: none !important;
            padding: 0 !important;
            color: #000000 !important;
        }

        .dark-table {
            width: 100% !important;
            table-layout: fixed !important;
            border-collapse: collapse !important;
            border-spacing: 0 !important;
            color: #000000 !important;
        }

        .dark-table, .dark-table th, .dark-table td {
            box-sizing: border-box !important;
        }

        /* 6 visible print columns (Statement column is .no-print) */
        .dark-table col.col-code    { width: 10%; }
        .dark-table col.col-name    { width: 26%; }
        .dark-table col.col-contact { width: 18%; }
        .dark-table col.col-mobile  { width: 14%; }
        .dark-table col.col-opening { width: 16%; }
        .dark-table col.col-current { width: 16%; }

        .dark-table th {
            background: #F1F5F9 !important;
            color: #0F172A !important;
            border: 1px solid #94A3B8 !important;
            font-size: 9.5px;
            padding: 6px 5px !important;
            text-transform: uppercase;
            letter-spacing: 0.15px;
            line-height: 1.3;
            vertical-align: middle;
            white-space: normal;
            word-wrap: break-word;
        }

        .dark-table td {
            border: 1px solid #CBD5E1 !important;
            color: #0F172A !important;
            font-size: 10.5px;
            padding: 6px 5px !important;
            line-height: 1.4;
            vertical-align: top;
            word-wrap: break-word;
            overflow-wrap: break-word;
            white-space: normal !important;
        }

        /* Amount columns: never truncate real money figures — allow
           wrap onto a second line instead of clipping */
        .dark-table td.text-end {
            font-size: 10.5px;
            white-space: normal !important;
            word-break: normal;
            overflow: visible;
            text-overflow: unset;
        }

        .text-danger   { color: #B91C1C !important; }
        .text-success  { color: #15803D !important; }
        .text-info     { color: #0369A1 !important; }
        .text-cyan     { color: #0369A1 !important; }
        .text-primary-light { color: #1D4ED8 !important; }
        .text-secondary { color: #475569 !important; }

        .table-responsive {
            overflow: visible !important;
            width: 100% !important;
        }

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

        .print-letterhead .doc-type-box {
            text-align: right;
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

        /* ---------- Report meta / summary ---------- */
        .print-meta-grid {
            display: flex;
            justify-content: space-between;
            gap: 24px;
            margin-bottom: 16px;
            font-size: 12px;
        }

        .print-meta-grid .meta-col { flex: 1; }

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
            font-weight: 600;
        }

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
        }

        .print-summary-strip .sum-box .sum-label {
            font-size: 9.5px;
            text-transform: uppercase;
            color: #64748B;
            font-weight: 700;
        }

        .print-summary-strip .sum-box .sum-value {
            font-size: 15px;
            font-weight: 800;
            color: #0F172A;
            margin-top: 2px;
        }

        /* ---------- Footer / signatures ---------- */
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

<div class="page-header-container no-print">
    <div>
        <h2 class="page-title">Company & Supplier Summary (কোম্পানি রিপোর্ট)</h2>
        <div class="page-subtitle">Summary of all cement manufacturers, current payable balances and supplier accounts.</div>
    </div>
    <div class="d-flex gap-2">
        <button type="button" class="btn btn-primary-custom" onclick="window.print()">
            <i class="fa-solid fa-print me-1"></i> Print Supplier Summary
        </button>
    </div>
</div>

<div class="no-print" style="font-size: 12px; color: var(--text-secondary, #64748B); margin-top: -10px; margin-bottom: 16px;">
    <i class="fa-solid fa-circle-info me-1"></i>
    আপনার ব্রাউজারের Print ডায়ালগে "Headers and footers" অপশনটা বন্ধ (uncheck) রাখুন — নাহলে ব্রাউজার নিজে থেকে উপরে পেজ টাইটেল আর নিচে URL দেখাবে, যেটা এই ডিজাইনের অংশ না।
</div>

<!-- ============================================================
     PRINT-ONLY LETTERHEAD (hidden on screen, shown only when printing)
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
            <div class="doc-title">Company Summary Report</div>
            <div class="doc-date">Printed: <?php echo htmlspecialchars($printedAt); ?><?php echo $printedBy ? ' by ' . htmlspecialchars($printedBy) : ''; ?></div>
        </div>
    </div>

    <div class="print-meta-grid">
        <div class="meta-col">
            <div class="meta-label">Report Type</div>
            <div class="meta-value">All Partner Manufacturers &amp; Suppliers</div>
        </div>
        <div class="meta-col" style="text-align:right;">
            <div class="meta-label">Total Companies Listed</div>
            <div class="meta-value"><?php echo count($companies); ?></div>
        </div>
    </div>

    <div class="print-summary-strip">
        <div class="sum-box">
            <div class="sum-label">Total Partner Manufacturers</div>
            <div class="sum-value"><?php echo count($companies); ?></div>
        </div>
        <div class="sum-box">
            <div class="sum-label">Total Opening Payable</div>
            <div class="sum-value"><?php echo formatBDT($totOpening); ?></div>
        </div>
        <div class="sum-box" style="border-color:#0F172A;">
            <div class="sum-label">Total Current Payable</div>
            <div class="sum-value"><?php echo formatBDT($totPayable); ?></div>
        </div>
    </div>
</div>

<!-- Screen-only summary cards -->
<div class="row g-3 mb-4 no-print">
    <div class="col-md-6">
        <div class="dark-card py-3">
            <div class="text-secondary small fw-bold text-uppercase">Total Partner Manufacturers</div>
            <div class="fs-4 fw-bold" style="color: var(--text-primary);"><?php echo count($companies); ?> Suppliers</div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="dark-card py-3">
            <div class="text-secondary small fw-bold text-uppercase">Total Supplier Payable</div>
            <div class="fs-4 fw-bold text-danger"><?php echo formatBDT($totPayable); ?></div>
        </div>
    </div>
</div>

<div class="dark-card">
    <div class="card-header-clean no-print">
        <div class="card-title-clean">
            <i class="fa-solid fa-industry text-primary-light"></i>
            <span>Supplier Balances</span>
        </div>
    </div>

    <div class="table-responsive">
        <table class="dark-table">
            <colgroup>
                <col class="col-code">
                <col class="col-name">
                <col class="col-contact">
                <col class="col-mobile">
                <col class="col-opening">
                <col class="col-current">
            </colgroup>
            <thead>
                <tr>
                    <th>Code</th>
                    <th>Name</th>
                    <th>Person</th>
                    <th>Mobile</th>
                    <th class="text-end">Op.Payable</th>
                    <th class="text-end">Balance</th>
                    <th class="text-end no-print">Statement</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($companies)): ?>
                    <tr>
                        <td colspan="7" class="text-center text-muted py-4">No companies found.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($companies as $c): ?>
                        <tr>
                            <td class="fw-bold text-info"><?php echo htmlspecialchars($c['company_code']); ?></td>
                            <td class="fw-bold" style="color: var(--text-primary);"><?php echo htmlspecialchars($c['name']); ?></td>
                            <td><?php echo htmlspecialchars($c['contact_person'] ?: '—'); ?></td>
                            <td><?php echo htmlspecialchars($c['mobile']); ?></td>
                            <td class="text-end"><?php echo formatBDT($c['opening_payable']); ?></td>
                            <td class="text-end fw-bold fs-6 <?php echo ((float)$c['current_payable'] > 0) ? 'text-danger' : 'text-success'; ?>">
                                <?php echo formatBDT($c['current_payable']); ?>
                            </td>
                            <td class="text-end no-print">
                                <a href="<?php echo BASE_URL; ?>/modules/companies/statement.php?id=<?php echo $c['id']; ?>" class="btn btn-secondary-custom btn-sm">
                                    <i class="fa-solid fa-file-invoice text-cyan me-1"></i> Ledger
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
            <tfoot>
                <tr class="fw-bold" style="background: var(--bg-input);">
                    <td colspan="4" class="text-end">Totals:</td>
                    <td class="text-end"><?php echo formatBDT($totOpening); ?></td>
                    <td class="text-end text-danger"><?php echo formatBDT($totPayable); ?></td>
                    <td class="no-print"></td>
                </tr>
            </tfoot>
        </table>
    </div>
</div>

<!-- ============================================================
     PRINT-ONLY FOOTER: signature lines + generated-by note
     ============================================================ -->
<div class="print-only-block">
    <div class="print-footer-block">
        <div class="sig-box">
            <div class="sig-line">Prepared By</div>
        </div>
        <div class="sig-box">
            <div class="sig-line">Authorized Signature — <?php echo htmlspecialchars($dealerName); ?></div>
        </div>
    </div>
    <div class="print-generated-note">
        This is a computer-generated report from <?php echo htmlspecialchars($dealerName); ?>'s accounting system and does not require a physical stamp unless otherwise requested.
    </div>
</div>

<script>
    (function () {
        var originalTitle = document.title;
        var printTitle = <?php echo json_encode(($dealerName ? $dealerName . ' — ' : '') . 'Company Summary Report'); ?>;

        window.addEventListener('beforeprint', function () {
            document.title = printTitle;
        });
        window.addEventListener('afterprint', function () {
            document.title = originalTitle;
        });
    })();
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
