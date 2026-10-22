<?php
/**
 * Maruf Traders - Retailer Ledger & Customer Statement
 * (Updated: adds a professional print-only letterhead pulling dealer
 *  info + logo from the `settings` table via getSetting().)
 *
 * ⚠️ IMPORTANT — VERIFY YOUR SETTING KEYS:
 * I don't have your settings module's code, so I don't know your exact
 * `setting_key` values. I've used common/likely names below with sensible
 * fallback defaults (2nd argument to getSetting()) so the page never
 * breaks even if a key is missing -- it just falls back to the default
 * text/blank. Please check your settings table:
 *     SELECT setting_key FROM settings;
 * ...and adjust the getSetting('...') calls in the "DEALER INFO FROM
 * SETTINGS" block below if your key names differ.
 *
 * Logo path assumption: stored as a relative path (e.g. "uploads/logo.png")
 * that needs BASE_URL prefixed to become a usable <img src>. If your logo
 * setting already stores a full URL, remove the BASE_URL concatenation.
 */

define('APP_INIT', true);
$pageTitle = 'Retailer Ledger & Statement';
$breadcrumb = 'Retailer Ledger';
$activeMenu = 'retailers';

require_once __DIR__ . '/../../includes/header.php';
requirePermission('retailers.manage');

$db = Database::getConnection();
$tenantId = TenantContext::id();

$retailerId = (int)($_GET['id'] ?? 0);
$startDate = $_GET['start_date'] ?? date('Y-m-01');
$endDate = $_GET['end_date'] ?? date('Y-m-d');

// Fetch all retailers for dropdown selector
$rListStmt = $db->prepare("SELECT id, retailer_code, name, mobile, current_due FROM retailers WHERE tenant_id = :tenant ORDER BY name ASC");
$rListStmt->execute([':tenant' => $tenantId]);
$allRetailers = $rListStmt->fetchAll();

if (!$retailerId && !empty($allRetailers)) {
    $retailerId = (int)$allRetailers[0]['id'];
}

$selectedRetailer = null;
$ledgerEntries = [];
$totalDebit = 0;
$totalCredit = 0;

if ($retailerId) {
    // Fetch Retailer Info
    $stmt = $db->prepare("SELECT * FROM retailers WHERE id = :id AND tenant_id = :tenant LIMIT 1");
    $stmt->execute([':id' => $retailerId, ':tenant' => $tenantId]);
    $selectedRetailer = $stmt->fetch();

    if ($selectedRetailer) {
        // Fetch Ledger Entries for Date Range
        $lSql = "SELECT * FROM retailer_ledger 
                 WHERE retailer_id = :rid 
                   AND tenant_id = :tenant
                   AND transaction_date BETWEEN :start AND :end 
                 ORDER BY transaction_date ASC, id ASC";
        $lStmt = $db->prepare($lSql);
        $lStmt->execute([
            ':rid'    => $retailerId,
            ':tenant' => $tenantId,
            ':start'  => $startDate,
            ':end'    => $endDate
        ]);
        $ledgerEntries = $lStmt->fetchAll();

        foreach ($ledgerEntries as $e) {
            $totalDebit += (float)$e['debit'];
            $totalCredit += (float)$e['credit'];
        }
    }
}

// ============================================================
// DEALER INFO FROM SETTINGS (matches actual settings table keys)
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
       when printing, the normal app chrome + on-screen card headers
       are hidden and this letterhead takes over.
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

        /* Force everything printable to plain black-on-white, borders only */
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

        /* CRITICAL: without border-box, each cell's padding+border adds ON TOP
           of its %-width, so consecutive fixed-width columns silently grow
           past their allotted space and bleed into the next column — this
           was the real cause of the "overlap". */
        .dark-table, .dark-table th, .dark-table td {
            box-sizing: border-box !important;
        }

        .dark-table col.col-date    { width: 10%; }
        .dark-table col.col-type    { width: 10%; }
        .dark-table col.col-desc    { width: 24%; }
        .dark-table col.col-debit   { width: 18%; }
        .dark-table col.col-credit  { width: 18%; }
        .dark-table col.col-balance { width: 20%; }

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

        /* Description: the longest free-text column — keep it a touch
           smaller than the rest so it never pushes into neighboring cells */
        .dark-table td.print-desc-cell {
            font-size: 10.5px;
            line-height: 1.35;
            color: #334155 !important;
        }

        /* Amount columns: numbers must stay FULLY VISIBLE (never truncated
           with ellipsis — that would hide real money figures). They get
           extra column width above, and are allowed to wrap onto a second
           line in the rare case a very large figure still doesn't fit on
           one line -- wrapping (ugly but complete) is safer than clipping
           (inaccurate). word-break:normal keeps it from breaking in the
           middle of a digit group. */
        .dark-table td.text-end {
            font-size: 10.5px;
            white-space: normal !important;
            word-break: normal;
            overflow: visible;
            text-overflow: unset;
        }

        /* Type badge: shrink to fit its narrow column instead of
           overflowing into the next column's border */
        .dark-table .badge-custom {
            display: inline-block;
            max-width: 100%;
            font-size: 6.5px !important;
            padding: 2px 5px !important;
            line-height: 1.3;
            white-space: normal;
            word-break: break-word;
            box-sizing: border-box;
        }

        .dark-table tfoot tr {
            background: #F8FAFC !important;
        }

        /* Neutralize theme text-color utility classes for print legibility */
        .text-danger   { color: #B91C1C !important; }
        .text-success  { color: #15803D !important; }
        .text-info     { color: #0369A1 !important; }
        .text-cyan     { color: #0369A1 !important; }
        .text-primary-light { color: #1D4ED8 !important; }
        .text-secondary { color: #475569 !important; }

        .badge-custom {
            border: 1px solid #94A3B8 !important;
            background: #FFFFFF !important;
            color: #0F172A !important;
        }

        /* Prevent the screen's horizontal-scroll wrapper from clipping
           table columns when printing -- let the table breathe at full width */
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

        /* ---------- Customer / statement meta block ---------- */
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
        <h2 class="page-title">Retailer Ledger (রিটেইলার লেজার ও স্টেটমেন্ট)</h2>
        <div class="page-subtitle">Transaction history, debits, credits and running customer due balance.</div>
    </div>
    <div class="d-flex gap-2">
        <a href="<?php echo BASE_URL; ?>/modules/retailers/index.php" class="btn btn-secondary-custom">
            <i class="fa-solid fa-arrow-left me-1"></i> Back to Retailers
        </a>
        <button type="button" class="btn btn-primary-custom" onclick="window.print()">
            <i class="fa-solid fa-print me-1"></i> Print Statement
        </button>
    </div>
</div>

<div class="no-print" style="font-size: 12px; color: var(--text-secondary, #64748B); margin-top: -10px; margin-bottom: 16px;">
    <i class="fa-solid fa-circle-info me-1"></i>
    আপনার ব্রাউজারের Print ডায়ালগে "Headers and footers" অপশনটা বন্ধ (uncheck) রাখুন — নাহলে ব্রাউজার নিজে থেকে উপরে পেজ টাইটেল আর নিচে URL দেখাবে, যেটা এই ডিজাইনের অংশ না।
</div>

<!-- Filter Bar -->
<div class="dark-card mb-4 no-print">
    <form method="GET" action="" class="row g-3 align-items-end">
        <div class="col-md-4">
            <label for="id" class="form-label-custom">Select Retailer</label>
            <select name="id" id="id" class="form-select form-select-custom" onchange="this.form.submit()">
                <?php foreach ($allRetailers as $ar): ?>
                    <option value="<?php echo $ar['id']; ?>" <?php echo ($ar['id'] == $retailerId) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($ar['retailer_code'] . ' — ' . $ar['name']); ?> (Due: <?php echo formatBDT($ar['current_due']); ?>)
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-3">
            <label for="start_date" class="form-label-custom">From Date</label>
            <input type="date" name="start_date" id="start_date" class="form-control form-control-custom" value="<?php echo htmlspecialchars($startDate); ?>">
        </div>
        <div class="col-md-3">
            <label for="end_date" class="form-label-custom">To Date</label>
            <input type="date" name="end_date" id="end_date" class="form-control form-control-custom" value="<?php echo htmlspecialchars($endDate); ?>">
        </div>
        <div class="col-md-2">
            <button type="submit" class="btn btn-primary-custom w-100">
                <i class="fa-solid fa-filter me-1"></i> Filter
            </button>
        </div>
    </form>
</div>

<?php if ($selectedRetailer): ?>

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
                <div class="doc-title">Customer Statement</div>
                <div class="doc-date">Printed: <?php echo htmlspecialchars($printedAt); ?><?php echo $printedBy ? ' by ' . htmlspecialchars($printedBy) : ''; ?></div>
            </div>
        </div>

        <div class="print-meta-grid">
            <div class="meta-col">
                <div class="meta-label">Customer / Retailer</div>
                <div class="meta-value"><?php echo htmlspecialchars($selectedRetailer['name']); ?></div>
                <div style="font-size:11px; color:#475569; margin-top:2px;">
                    Code: <?php echo htmlspecialchars($selectedRetailer['retailer_code']); ?><br>
                    Mobile: <?php echo htmlspecialchars($selectedRetailer['mobile']); ?><br>
                    Address: <?php echo htmlspecialchars($selectedRetailer['address'] ?: '—'); ?>
                </div>
            </div>
            <div class="meta-col" style="text-align:right;">
                <div class="meta-label">Statement Period</div>
                <div class="meta-value"><?php echo formatDate($startDate); ?> &nbsp;to&nbsp; <?php echo formatDate($endDate); ?></div>
            </div>
        </div>

        <div class="print-summary-strip">
            <div class="sum-box">
                <div class="sum-label">Total Debit (Sales)</div>
                <div class="sum-value"><?php echo formatBDT($totalDebit); ?></div>
            </div>
            <div class="sum-box">
                <div class="sum-label">Total Credit (Paid)</div>
                <div class="sum-value"><?php echo formatBDT($totalCredit); ?></div>
            </div>
            <div class="sum-box">
                <div class="sum-label">Advance Balance</div>
                <div class="sum-value"><?php echo formatBDT($selectedRetailer['advance_balance']); ?></div>
            </div>
            <div class="sum-box" style="border-color:#0F172A;">
                <div class="sum-label">Current Outstanding Due</div>
                <div class="sum-value"><?php echo formatBDT($selectedRetailer['current_due']); ?></div>
            </div>
        </div>
    </div>

    <!-- Screen-only Printable Statement Header (unchanged on-screen UX) -->
    <div class="dark-card mb-4 no-print">
        <div class="row align-items-center">
            <div class="col-md-6">
                <h4 class="fw-bold mb-1" style="color: var(--text-primary);"><?php echo htmlspecialchars($selectedRetailer['name']); ?></h4>
                <div class="text-secondary small mb-1">
                    <i class="fa-solid fa-id-badge text-cyan me-1"></i> Retailer Code: <strong><?php echo htmlspecialchars($selectedRetailer['retailer_code']); ?></strong>
                </div>
                <div class="text-secondary small mb-1">
                    <i class="fa-solid fa-phone text-cyan me-1"></i> Mobile: <?php echo htmlspecialchars($selectedRetailer['mobile']); ?>
                </div>
                <div class="text-secondary small">
                    <i class="fa-solid fa-location-dot text-cyan me-1"></i> Address: <?php echo htmlspecialchars($selectedRetailer['address'] ?: '—'); ?>
                </div>
            </div>
            <div class="col-md-6 text-md-end mt-3 mt-md-0">
                <div class="text-secondary small">Statement Period:</div>
                <div class="fw-bold mb-2" style="color: var(--text-primary);"><?php echo formatDate($startDate); ?> to <?php echo formatDate($endDate); ?></div>
                <div class="p-2 px-3 rounded d-inline-block" style="background: rgba(239, 68, 68, 0.1); border: 1px solid rgba(239, 68, 68, 0.3);">
                    <div class="text-secondary small fw-bold">TOTAL OUTSTANDING DUE</div>
                    <div class="fs-4 fw-bold text-danger"><?php echo formatBDT($selectedRetailer['current_due']); ?></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Summary Metrics (screen only) -->
    <div class="row g-3 mb-4 no-print">
        <div class="col-md-4">
            <div class="dark-card py-3 text-center">
                <div class="text-secondary small fw-bold text-uppercase">Period Total Sales (Debit)</div>
                <div class="fs-4 fw-bold text-danger"><?php echo formatBDT($totalDebit); ?></div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="dark-card py-3 text-center">
                <div class="text-secondary small fw-bold text-uppercase">Period Total Paid (Credit)</div>
                <div class="fs-4 fw-bold text-success"><?php echo formatBDT($totalCredit); ?></div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="dark-card py-3 text-center">
                <div class="text-secondary small fw-bold text-uppercase">Advance Balance</div>
                <div class="fs-4 fw-bold text-info"><?php echo formatBDT($selectedRetailer['advance_balance']); ?></div>
            </div>
        </div>
    </div>

    <!-- Ledger Table (shown both on screen AND in print) -->
    <div class="dark-card">
        <div class="card-header-clean no-print">
            <div class="card-title-clean">
                <i class="fa-solid fa-receipt text-primary-light"></i>
                <span>Detailed Transaction Ledger</span>
            </div>
        </div>

        <div class="table-responsive">
            <table class="dark-table">
                <colgroup>
                    <col class="col-date">
                    <col class="col-type">
                    <col class="col-desc">
                    <col class="col-debit">
                    <col class="col-credit">
                    <col class="col-balance">
                </colgroup>
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Type</th>
                        <th>Description</th>
                        <th class="text-end">Debit (+ Due)</th>
                        <th class="text-end">Credit (- Due)</th>
                        <th class="text-end">Balance</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($ledgerEntries)): ?>
                        <tr>
                            <td colspan="6" class="text-center text-muted py-4">No transactions found for the selected period.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($ledgerEntries as $row): ?>
                            <tr>
                                <td><?php echo formatDate($row['transaction_date']); ?></td>
                                <td>
                                    <?php 
                                        $badgeClass = 'badge-primary';
                                        if ($row['transaction_type'] === 'COLLECTION') $badgeClass = 'badge-success';
                                        if ($row['transaction_type'] === 'ADVANCE') $badgeClass = 'badge-cyan';
                                        if ($row['transaction_type'] === 'SALE_CANCEL') $badgeClass = 'badge-danger';
                                        if ($row['transaction_type'] === 'REVERSAL') $badgeClass = 'badge-warning';
                                    ?>
                                    <span class="badge-custom <?php echo $badgeClass; ?>">
                                        <?php echo htmlspecialchars($row['transaction_type']); ?>
                                    </span>
                                </td>
                                <td class="text-secondary print-desc-cell"><?php echo htmlspecialchars($row['description'] ?: '—'); ?></td>
                                <td class="text-end text-danger fw-bold">
                                    <?php echo ((float)$row['debit'] > 0) ? formatBDT($row['debit']) : '—'; ?>
                                </td>
                                <td class="text-end text-success fw-bold">
                                    <?php echo ((float)$row['credit'] > 0) ? formatBDT($row['credit']) : '—'; ?>
                                </td>
                                <td class="text-end fw-bold <?php echo ((float)$row['running_balance'] > 0) ? 'text-danger' : 'text-success'; ?>">
                                    <?php echo formatBDT($row['running_balance']); ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
                <tfoot>
                    <tr class="fw-bold" style="background: var(--bg-input);">
                        <td colspan="3" class="text-end">Period Totals:</td>
                        <td class="text-end text-danger"><?php echo formatBDT($totalDebit); ?></td>
                        <td class="text-end text-success"><?php echo formatBDT($totalCredit); ?></td>
                        <td class="text-end" style="color: var(--text-primary);"><?php echo formatBDT($selectedRetailer['current_due']); ?></td>
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
                <div class="sig-line">Customer Signature</div>
            </div>
            <div class="sig-box">
                <div class="sig-line">Authorized Signature — <?php echo htmlspecialchars($dealerName); ?></div>
            </div>
        </div>
        <div class="print-generated-note">
            This is a computer-generated statement from <?php echo htmlspecialchars($dealerName); ?>'s accounting system and does not require a physical stamp unless otherwise requested.
        </div>
    </div>

<?php endif; ?>

<?php if ($selectedRetailer): ?>
<script>
    // If the browser's own "Headers and footers" print option is left ON
    // (a print-dialog setting we cannot disable from the page itself),
    // at least make the title it shows clean and meaningful instead of
    // the raw page title / URL.
    (function () {
        var originalTitle = document.title;
        var printTitle = <?php echo json_encode(($dealerName ? $dealerName . ' — ' : '') . 'Customer Statement — ' . $selectedRetailer['name']); ?>;

        window.addEventListener('beforeprint', function () {
            document.title = printTitle;
        });
        window.addEventListener('afterprint', function () {
            document.title = originalTitle;
        });
    })();
</script>
<?php endif; ?>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>