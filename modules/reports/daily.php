<?php
/**
 * Maruf Traders - Daily Dealership Business Report
 * (Updated: adds a professional print-only letterhead/memo header,
 *  matching the retailer ledger statement's print design.)
 */

define('APP_INIT', true);
$pageTitle = 'Daily Business Report';
$breadcrumb = 'Daily Report';
$activeMenu = 'report_daily';

require_once __DIR__ . '/../../includes/header.php';
requirePermission('reports.financial');

$reportDate = $_GET['report_date'] ?? date('Y-m-d');
$db = Database::getConnection();
$tenantId = TenantContext::id();

// Daily Sales
$sStmt = $db->prepare("SELECT s.*, r.name as retailer_name, r.retailer_code 
                       FROM sales s 
                       JOIN retailers r ON s.retailer_id = r.id 
                       WHERE s.tenant_id = :tenant AND s.sale_date = :d AND s.status = 'active' 
                       ORDER BY s.id DESC");
$sStmt->execute([':tenant' => $tenantId, ':d' => $reportDate]);
$dailySales = $sStmt->fetchAll();

// Daily Collections
$cStmt = $db->prepare("SELECT c.*, r.name as retailer_name, r.retailer_code 
                       FROM collections c 
                       JOIN retailers r ON c.retailer_id = r.id 
                       WHERE c.tenant_id = :tenant AND c.collection_date = :d AND c.status = 'active' 
                       ORDER BY c.id DESC");
$cStmt->execute([':tenant' => $tenantId, ':d' => $reportDate]);
$dailyCollections = $cStmt->fetchAll();

// Daily Expenses
$eStmt = $db->prepare("SELECT e.*, c.name as category_name 
                       FROM expenses e 
                       JOIN expense_categories c ON e.category_id = c.id 
                       WHERE e.tenant_id = :tenant AND e.expense_date = :d AND e.status = 'active' 
                       ORDER BY e.id DESC");
$eStmt->execute([':tenant' => $tenantId, ':d' => $reportDate]);
$dailyExpenses = $eStmt->fetchAll();

// Daily Purchases / Receives
$rStmt = $db->prepare("SELECT cr.*, c.name as company_name, p.name as product_name, p.unit 
                       FROM cement_receives cr 
                       JOIN companies c ON cr.company_id = c.id 
                       JOIN products p ON cr.product_id = p.id 
                       WHERE cr.tenant_id = :tenant AND cr.receive_date = :d AND cr.status = 'active' 
                       ORDER BY cr.id DESC");
$rStmt->execute([':tenant' => $tenantId, ':d' => $reportDate]);
$dailyReceives = $rStmt->fetchAll();

// Totals
$totSales = 0; $totPaidSales = 0; $totDueSales = 0;
foreach ($dailySales as $s) { $totSales += (float)$s['total_amount']; $totPaidSales += (float)$s['paid_amount']; $totDueSales += (float)$s['due_amount']; }

$totCol = 0;
foreach ($dailyCollections as $c) { $totCol += (float)$c['amount']; }

$totExp = 0;
foreach ($dailyExpenses as $e) { $totExp += (float)$e['amount']; }

$totRecCost = 0; $totRecPaid = 0;
foreach ($dailyReceives as $r) { $totRecCost += (float)$r['total_cost']; $totRecPaid += (float)$r['paid_amount']; }

$netCashFlow = ($totPaidSales + $totCol) - ($totExp + $totRecPaid);

// ============================================================
// DEALER INFO FROM SETTINGS (same keys used on the ledger page —
// verify these against your `settings` table if they don't match)
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
            font-size: 9.5px;
            padding: 5px 5px !important;
            text-transform: uppercase;
            letter-spacing: 0.15px;
        }

        .dark-table td {
            border: 1px solid #CBD5E1 !important;
            color: #0F172A !important;
            font-size: 10px;
            padding: 5px 5px !important;
            line-height: 1.35;
            vertical-align: top;
            word-wrap: break-word;
            white-space: normal !important;
        }

        .dark-table td.text-end {
            white-space: normal !important;
            word-break: normal;
        }

        .badge-custom {
            display: inline-block;
            border: 1px solid #94A3B8 !important;
            background: #FFFFFF !important;
            color: #0F172A !important;
            font-size: 8px !important;
            padding: 2px 5px !important;
        }

        .text-danger   { color: #B91C1C !important; }
        .text-success  { color: #15803D !important; }
        .text-info     { color: #0369A1 !important; }
        .text-primary-light { color: #1D4ED8 !important; }
        .text-secondary { color: #475569 !important; }

        .table-responsive { overflow: visible !important; width: 100% !important; }

        /* Avoid splitting a section's card across a page break where possible */
        .dark-card { break-inside: avoid; page-break-inside: avoid; }

        /* ---------- Letterhead (memo header) ---------- */
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

        /* ---------- Memo meta line ---------- */
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
            font-size: 13px;
            color: #0F172A;
            font-weight: 700;
        }

        /* ---------- Summary strip ---------- */
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

        /* ---------- Section title used only in print ---------- */
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
        <h2 class="page-title">Daily Business & Cash Flow Report (দৈনিক হিসাব বিবরণী)</h2>
        <div class="page-subtitle">Daily summary of cement billing, customer collections, expenses, and cash movement.</div>
    </div>
    <div class="d-flex gap-2">
        <button type="button" class="btn btn-primary-custom" onclick="window.print()">
            <i class="fa-solid fa-print me-1"></i> Print Daily Sheet
        </button>
    </div>
</div>

<div class="no-print" style="font-size: 12px; color: var(--text-secondary, #64748B); margin-top: -10px; margin-bottom: 16px;">
    <i class="fa-solid fa-circle-info me-1"></i>
    আপনার ব্রাউজারের Print ডায়ালগে "Headers and footers" অপশনটা বন্ধ (uncheck) রাখুন — নাহলে ব্রাউজার নিজে থেকে উপরে পেজ টাইটেল আর নিচে URL দেখাবে, যেটা এই ডিজাইনের অংশ না।
</div>

<!-- Date Selector -->
<div class="dark-card mb-4 no-print">
    <form method="GET" action="" class="row g-3 align-items-end">
        <div class="col-md-6">
            <label for="report_date" class="form-label-custom">Select Reporting Date</label>
            <input type="date" name="report_date" id="report_date" class="form-control form-control-custom" value="<?php echo htmlspecialchars($reportDate); ?>" onchange="this.form.submit()">
        </div>
        <div class="col-md-6 text-end">
            <div class="text-secondary small">Viewing Report for:</div>
            <h4 class="text-cyan fw-bold mb-0"><?php echo formatDate($reportDate, 'l, d F, Y'); ?></h4>
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
            <div class="doc-title">Daily Business Memo</div>
            <div class="doc-date">Printed: <?php echo htmlspecialchars($printedAt); ?><?php echo $printedBy ? ' by ' . htmlspecialchars($printedBy) : ''; ?></div>
        </div>
    </div>

    <div class="print-meta-grid">
        <div>
            <div class="meta-label">Report Date</div>
            <div class="meta-value"><?php echo formatDate($reportDate, 'l, d F, Y'); ?></div>
        </div>
        <div style="text-align:right;">
            <div class="meta-label">Total Invoices Today</div>
            <div class="meta-value"><?php echo count($dailySales); ?></div>
        </div>
    </div>

    <div class="print-summary-strip">
        <div class="sum-box">
            <div class="sum-label">Total Sales Invoiced</div>
            <div class="sum-value"><?php echo formatBDT($totSales); ?></div>
        </div>
        <div class="sum-box">
            <div class="sum-label">Direct Collections</div>
            <div class="sum-value"><?php echo formatBDT($totCol); ?></div>
        </div>
        <div class="sum-box">
            <div class="sum-label">Daily Expenses</div>
            <div class="sum-value"><?php echo formatBDT($totExp); ?></div>
        </div>
        <div class="sum-box net-box">
            <div class="sum-label">Net Cash Inflow / (Outflow)</div>
            <div class="sum-value"><?php echo formatBDT($netCashFlow); ?></div>
        </div>
    </div>
</div>

<!-- Summary Strip (screen only) -->
<div class="row g-3 mb-4 no-print">
    <div class="col-md-3">
        <div class="dark-card py-3 text-center">
            <div class="text-secondary small fw-bold text-uppercase">Total Sales Invoiced</div>
            <div class="fs-4 fw-bold" style="color: var(--text-primary);"><?php echo formatBDT($totSales); ?></div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="dark-card py-3 text-center">
            <div class="text-secondary small fw-bold text-uppercase">Direct Collections</div>
            <div class="fs-4 fw-bold text-success"><?php echo formatBDT($totCol); ?></div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="dark-card py-3 text-center">
            <div class="text-secondary small fw-bold text-uppercase">Daily Expenses</div>
            <div class="fs-4 fw-bold text-danger"><?php echo formatBDT($totExp); ?></div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="dark-card py-3 text-center">
            <div class="text-secondary small fw-bold text-uppercase">Net Cash Inflow / (Outflow)</div>
            <div class="fs-4 fw-bold <?php echo ($netCashFlow >= 0) ? 'text-success' : 'text-danger'; ?>">
                <?php echo formatBDT($netCashFlow); ?>
            </div>
        </div>
    </div>
</div>

<!-- Print-only section label for Sales -->
<div class="print-only-block"><div class="print-section-title">Daily Sales Invoices</div></div>

<!-- Daily Sales Section -->
<div class="dark-card mb-4">
    <div class="card-header-clean no-print">
        <div class="card-title-clean">
            <i class="fa-solid fa-cart-shopping text-primary-light"></i>
            <span>Daily Sales Invoices (<?php echo count($dailySales); ?> Invoices)</span>
        </div>
    </div>
    <div class="table-responsive">
        <table class="dark-table">
            <thead>
                <tr>
                    <th>Invoice No</th>
                    <th>Customer Name</th>
                    <th class="text-end">Total Amount</th>
                    <th class="text-end">Paid Amount</th>
                    <th class="text-end">Due Amount</th>
                    <th class="text-center">Status</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($dailySales)): ?>
                    <tr><td colspan="6" class="text-center text-muted py-3">No sales recorded on this day.</td></tr>
                <?php else: ?>
                    <?php foreach ($dailySales as $s): ?>
                        <tr>
                            <td class="fw-bold text-info"><?php echo htmlspecialchars($s['invoice_no']); ?></td>
                            <td class="fw-bold" style="color: var(--text-primary);"><?php echo htmlspecialchars($s['retailer_name']); ?></td>
                            <td class="text-end fw-bold"><?php echo formatBDT($s['total_amount']); ?></td>
                            <td class="text-end text-success"><?php echo formatBDT($s['paid_amount']); ?></td>
                            <td class="text-end text-danger"><?php echo formatBDT($s['due_amount']); ?></td>
                            <td class="text-center">
                                <span class="badge-custom <?php echo ($s['payment_status'] === 'paid') ? 'badge-success' : 'badge-danger'; ?>">
                                    <?php echo ucfirst($s['payment_status']); ?>
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
            <?php if (!empty($dailySales)): ?>
            <tfoot>
                <tr class="fw-bold" style="background: var(--bg-input);">
                    <td colspan="2" class="text-end">Day's Totals:</td>
                    <td class="text-end"><?php echo formatBDT($totSales); ?></td>
                    <td class="text-end text-success"><?php echo formatBDT($totPaidSales); ?></td>
                    <td class="text-end text-danger"><?php echo formatBDT($totDueSales); ?></td>
                    <td></td>
                </tr>
            </tfoot>
            <?php endif; ?>
        </table>
    </div>
</div>

<!-- Print-only section label for Collections & Expenses -->
<div class="print-only-block"><div class="print-section-title">Collections & Expenses</div></div>

<!-- Daily Collections & Expenses Split -->
<div class="row g-4 mb-4">
    <div class="col-md-6">
        <div class="dark-card h-100">
            <div class="card-header-clean no-print">
                <div class="card-title-clean">
                    <i class="fa-solid fa-hand-holding-dollar text-success"></i>
                    <span>Customer Collections (Total: <?php echo formatBDT($totCol); ?>)</span>
                </div>
            </div>
            <div class="table-responsive">
                <table class="dark-table">
                    <thead>
                        <tr><th>Receipt</th><th>Retailer</th><th class="text-end">Amount</th></tr>
                    </thead>
                    <tbody>
                        <?php if (empty($dailyCollections)): ?>
                            <tr><td colspan="3" class="text-center text-muted py-3">No collections on this date.</td></tr>
                        <?php else: ?>
                            <?php foreach ($dailyCollections as $c): ?>
                                <tr>
                                    <td class="text-info"><?php echo htmlspecialchars($c['collection_code']); ?></td>
                                    <td style="color: var(--text-primary);"><?php echo htmlspecialchars($c['retailer_name']); ?></td>
                                    <td class="text-end fw-bold text-success"><?php echo formatBDT($c['amount']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                    <?php if (!empty($dailyCollections)): ?>
                    <tfoot>
                        <tr class="fw-bold" style="background: var(--bg-input);">
                            <td colspan="2" class="text-end">Total:</td>
                            <td class="text-end text-success"><?php echo formatBDT($totCol); ?></td>
                        </tr>
                    </tfoot>
                    <?php endif; ?>
                </table>
            </div>
        </div>
    </div>

    <div class="col-md-6">
        <div class="dark-card h-100">
            <div class="card-header-clean no-print">
                <div class="card-title-clean">
                    <i class="fa-solid fa-wallet text-danger"></i>
                    <span>Operating Expenses (Total: <?php echo formatBDT($totExp); ?>)</span>
                </div>
            </div>
            <div class="table-responsive">
                <table class="dark-table">
                    <thead>
                        <tr><th>Category</th><th>Purpose</th><th class="text-end">Amount</th></tr>
                    </thead>
                    <tbody>
                        <?php if (empty($dailyExpenses)): ?>
                            <tr><td colspan="3" class="text-center text-muted py-3">No expenses recorded on this date.</td></tr>
                        <?php else: ?>
                            <?php foreach ($dailyExpenses as $e): ?>
                                <tr>
                                    <td><span class="badge-custom badge-primary"><?php echo htmlspecialchars($e['category_name']); ?></span></td>
                                    <td style="color: var(--text-primary);"><?php echo htmlspecialchars($e['title']); ?></td>
                                    <td class="text-end fw-bold text-danger"><?php echo formatBDT($e['amount']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                    <?php if (!empty($dailyExpenses)): ?>
                    <tfoot>
                        <tr class="fw-bold" style="background: var(--bg-input);">
                            <td colspan="2" class="text-end">Total:</td>
                            <td class="text-end text-danger"><?php echo formatBDT($totExp); ?></td>
                        </tr>
                    </tfoot>
                    <?php endif; ?>
                </table>
            </div>
        </div>
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
            <div class="sig-line">Checked / Approved By — <?php echo htmlspecialchars($dealerName); ?></div>
        </div>
    </div>
    <div class="print-generated-note">
        This is a computer-generated daily memo from <?php echo htmlspecialchars($dealerName); ?>'s accounting system and does not require a physical stamp unless otherwise requested.
    </div>
</div>

<script>
    (function () {
        var originalTitle = document.title;
        var printTitle = <?php echo json_encode(($dealerName ? $dealerName . ' — ' : '') . 'Daily Business Memo — ' . formatDate($reportDate, 'd M, Y')); ?>;

        window.addEventListener('beforeprint', function () {
            document.title = printTitle;
        });
        window.addEventListener('afterprint', function () {
            document.title = originalTitle;
        });
    })();
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>