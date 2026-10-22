<?php
/**
 * Maruf Traders - Stock Management & Stock Ledger
 * (Updated: adds a professional print-only letterhead pulling dealer
 *  info + logo from the `settings` table via getSetting(), matching
 *  the retailer & company statement pages.)
 *
 * ⚠️ Verify your actual setting_key values against:
 *     SELECT setting_key FROM settings;
 * and adjust the getSetting() calls below if your key names differ.
 */

define('APP_INIT', true);
$pageTitle = 'Stock Management & Ledger';
$breadcrumb = 'Stock Ledger';
$activeMenu = 'stock';

require_once __DIR__ . '/../../includes/header.php';

$db = Database::getConnection();
$tenantId = TenantContext::id();

$productId = (int)($_GET['product_id'] ?? 0);
$companyId = (int)($_GET['company_id'] ?? 0);
$startDate = $_GET['start_date'] ?? date('Y-m-01');
$endDate = $_GET['end_date'] ?? date('Y-m-d');
$transType = trim($_GET['transaction_type'] ?? '');

// Fetch products & companies for selectors
$pStmt = $db->prepare("SELECT p.*, c.name as company_name, cat.name as category_name 
                      FROM products p 
                      JOIN companies c ON p.company_id = c.id 
                      LEFT JOIN categories cat ON p.category_id = cat.id
                      WHERE p.tenant_id = :tenant AND p.status = 'active' 
                      ORDER BY c.name ASC, p.name ASC");
$pStmt->execute([':tenant' => $tenantId]);
$allProducts = $pStmt->fetchAll();

$cStmt = $db->prepare("SELECT id, name FROM companies WHERE tenant_id = :tenant AND status = 'active' ORDER BY name ASC");
$cStmt->execute([':tenant' => $tenantId]);
$allCompanies = $cStmt->fetchAll();

// Total stock and stock valuation
$totalAvailableStock = 0.0;
$totalStockValuation = 0.0;
foreach ($allProducts as $p) {
    $stk = (float)$p['current_stock'];
    $cost = (float)$p['default_purchase_price'];
    $totalAvailableStock += $stk;
    $totalStockValuation += ($stk * $cost);
}

// Fetch Stock Ledger
$sql = "SELECT sl.*, p.name as product_name, p.brand, p.unit, c.name as company_name 
        FROM stock_ledger sl
        JOIN products p ON sl.product_id = p.id
        JOIN companies c ON sl.company_id = c.id
        WHERE sl.tenant_id = :tenant AND sl.transaction_date BETWEEN :start AND :end";
$params = [':tenant' => $tenantId, ':start' => $startDate, ':end' => $endDate];

if ($productId) {
    $sql .= " AND sl.product_id = :pid";
    $params[':pid'] = $productId;
}
if ($companyId) {
    $sql .= " AND sl.company_id = :cid";
    $params[':cid'] = $companyId;
}
if (!empty($transType)) {
    $sql .= " AND sl.transaction_type = :ttype";
    $params[':ttype'] = $transType;
}

$sql .= " ORDER BY sl.transaction_date DESC, sl.id DESC";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$ledger = $stmt->fetchAll();

// Totals for the filtered period (used in the print summary strip)
$totalStockIn = 0.0;
$totalStockOut = 0.0;
foreach ($ledger as $e) {
    $totalStockIn += (float)$e['stock_in'];
    $totalStockOut += (float)$e['stock_out'];
}

// Human-readable filter labels for the print meta block
$filterProductLabel = 'All Products';
if ($productId) {
    foreach ($allProducts as $p) {
        if ((int)$p['id'] === $productId) {
            $filterProductLabel = $p['name'];
            break;
        }
    }
}
$filterCompanyLabel = 'All Companies';
if ($companyId) {
    foreach ($allCompanies as $c) {
        if ((int)$c['id'] === $companyId) {
            $filterCompanyLabel = $c['name'];
            break;
        }
    }
}

// ============================================================
// DEALER INFO FROM SETTINGS (same keys as retailer/company statement pages)
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
       (same structure/scope as the retailer & company statement pages)
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

        /* 7 columns: Date, Type, Product, Company,
           Stock In, Stock Out, Running Stock
           (Reference and Notes columns removed) */
        .dark-table col.col-date     { width: 10%; }
        .dark-table col.col-type     { width: 11%; }
        .dark-table col.col-product  { width: 20%; }
        .dark-table col.col-company  { width: 16%; }
        .dark-table col.col-in       { width: 15%; }
        .dark-table col.col-out      { width: 15%; }
        .dark-table col.col-running  { width: 13%; }

        .dark-table th {
            background: #F1F5F9 !important;
            color: #0F172A !important;
            border: 1px solid #94A3B8 !important;
            font-size: 8.5px;
            padding: 6px 4px !important;
            text-transform: uppercase;
            letter-spacing: 0.1px;
            line-height: 1.3;
            vertical-align: middle;
            white-space: normal;
            word-wrap: break-word;
        }

        .dark-table td {
            border: 1px solid #CBD5E1 !important;
            color: #0F172A !important;
            font-size: 9.5px;
            padding: 5px 4px !important;
            line-height: 1.4;
            vertical-align: top;
            word-wrap: break-word;
            overflow-wrap: break-word;
            white-space: normal !important;
        }

        .dark-table td.text-end {
            font-size: 9.5px;
            white-space: normal !important;
            word-break: normal;
            overflow: visible;
            text-overflow: unset;
        }

        .dark-table .badge-custom {
            display: inline-block;
            max-width: 100%;
            font-size: 6px !important;
            padding: 2px 4px !important;
            line-height: 1.3;
            white-space: normal;
            word-break: break-word;
            box-sizing: border-box;
        }

        .dark-table tfoot tr {
            background: #F8FAFC !important;
        }

        .text-danger   { color: #B91C1C !important; }
        .text-success  { color: #15803D !important; }
        .text-info     { color: #0369A1 !important; }
        .text-cyan     { color: #0369A1 !important; }
        .text-primary-light { color: #1D4ED8 !important; }
        .text-secondary { color: #475569 !important; }
        .text-warning  { color: #B45309 !important; }

        .badge-custom {
            border: 1px solid #94A3B8 !important;
            background: #FFFFFF !important;
            color: #0F172A !important;
        }

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
            font-size: 12px;
            font-weight: 700;
            color: #0F172A;
            text-transform: uppercase;
            letter-spacing: 0.6px;
            border: 2px solid #0F172A;
            padding: 4px 10px;
            display: inline-block;
        }

        .print-letterhead .doc-type-box .doc-date {
            font-size: 11px;
            color: #475569;
            margin-top: 6px;
        }

        /* ---------- Filter / statement meta block ---------- */
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

        /* Product inventory cards & modal are screen-only anyway via
           no-print, but keep this as a safety net for stray elements */
        .modal { display: none !important; }
    }
</style>

<div class="page-header-container no-print">
    <div>
        <h2 class="page-title">Stock Management & Ledger (মজুদ স্টক ও লেজার)</h2>
        <div class="page-subtitle">Real-time inventory levels, stock-in, stock-out audit trails, and stock adjustments.</div>
    </div>
    <div class="d-flex gap-2">
        <?php if (hasPermission('stock.adjust')): ?>
            <button type="button" class="btn btn-warning-custom" style="background: var(--warning); color: #1a1400; border-radius: var(--radius-sm); padding: 9px 18px; border: none; font-weight: 600;" onclick="openAdjustmentModal()">
                <i class="fa-solid fa-sliders me-1"></i> Stock Adjustment
            </button>
        <?php endif; ?>
        <button type="button" class="btn btn-primary-custom" onclick="window.print()">
            <i class="fa-solid fa-print me-1"></i> Print Ledger
        </button>
    </div>
</div>

<div class="no-print" style="font-size: 12px; color: var(--text-secondary, #64748B); margin-top: -10px; margin-bottom: 16px;">
    <i class="fa-solid fa-circle-info me-1"></i>
    আপনার ব্রাউজারের Print ডায়ালগে "Headers and footers" অপশনটা বন্ধ (uncheck) রাখুন — নাহলে ব্রাউজার নিজে থেকে উপরে পেজ টাইটেল আর নিচে URL দেখাবে, যেটা এই ডিজাইনের অংশ না।
</div>

<!-- Product Stock Visual Cards -->
<div class="dark-card mb-4 no-print">
    <div class="card-header-clean">
        <div class="card-title-clean">
            <i class="fa-solid fa-warehouse text-cyan"></i>
            <span>Current Multi-Product Inventory (Stock Valuation: ৳ <?php echo number_format($totalStockValuation, 2); ?>)</span>
        </div>
    </div>

    <div class="row g-3">
        <?php foreach ($allProducts as $prod): ?>
            <?php 
                $stock = (float)$prod['current_stock'];
                $limit = (float)$prod['low_stock_limit'];
                $unit = $prod['unit'] ?? 'Unit';
                $isLow = $stock <= $limit;
                $isOut = $stock <= 0;
            ?>
            <div class="col-md-4 col-lg-3">
                <div class="p-3 rounded" style="background: var(--bg-input); border: 1px solid var(--border-color);">
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <div>
                            <div class="fw-bold small" style="color: var(--text-primary);"><?php echo htmlspecialchars($prod['name']); ?></div>
                            <div class="text-secondary" style="font-size: 0.75rem;">
                                <?php echo htmlspecialchars($prod['company_name']); ?>
                                <?php if (!empty($prod['category_name'])): ?>
                                    <span class="badge bg-secondary ms-1" style="font-size: 0.65rem;"><?php echo htmlspecialchars($prod['category_name']); ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php if ($isOut): ?>
                            <span class="badge-custom badge-danger" style="font-size: 0.65rem;">Out</span>
                        <?php elseif ($isLow): ?>
                            <span class="badge-custom badge-warning" style="font-size: 0.65rem;">Low</span>
                        <?php else: ?>
                            <span class="badge-custom badge-success" style="font-size: 0.65rem;">OK</span>
                        <?php endif; ?>
                    </div>
                    <div class="fs-4 fw-bold <?php echo $isOut ? 'text-danger' : ($isLow ? 'text-warning' : 'text-cyan'); ?>">
                        <?php echo number_format($stock, (floor($stock) == $stock ? 0 : 3)); ?> <span class="fs-6 text-secondary font-monospace"><?php echo htmlspecialchars($unit); ?></span>
                    </div>
                    <div class="small text-secondary mt-1">
                        Valuation: ৳ <?php echo number_format($stock * (float)$prod['default_purchase_price'], 2); ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- Ledger Filter -->
<div class="dark-card mb-4 no-print">
    <form method="GET" action="" class="row g-3 align-items-end">
        <div class="col-md-3">
            <label for="product_id" class="form-label-custom">Filter Product</label>
            <select name="product_id" id="product_id" class="form-select form-select-custom">
                <option value="">-- All Products --</option>
                <?php foreach ($allProducts as $p): ?>
                    <option value="<?php echo $p['id']; ?>" <?php echo ($p['id'] == $productId) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($p['name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-3">
            <label for="company_id" class="form-label-custom">Filter Company</label>
            <select name="company_id" id="company_id" class="form-select form-select-custom">
                <option value="">-- All Companies --</option>
                <?php foreach ($allCompanies as $c): ?>
                    <option value="<?php echo $c['id']; ?>" <?php echo ($c['id'] == $companyId) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($c['name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-2">
            <label for="start_date" class="form-label-custom">From Date</label>
            <input type="date" name="start_date" id="start_date" class="form-control form-control-custom" value="<?php echo htmlspecialchars($startDate); ?>">
        </div>
        <div class="col-md-2">
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

<!-- ============================================================
     PRINT-ONLY LETTERHEAD
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
            <div class="doc-title">Stock Ledger</div>
            <div class="doc-date">Printed: <?php echo htmlspecialchars($printedAt); ?><?php echo $printedBy ? ' by ' . htmlspecialchars($printedBy) : ''; ?></div>
        </div>
    </div>

    <div class="print-meta-grid">
        <div class="meta-col">
            <div class="meta-label">Filters Applied</div>
            <div class="meta-value"><?php echo htmlspecialchars($filterProductLabel); ?></div>
            <div style="font-size:11px; color:#475569; margin-top:2px;">
                Company: <?php echo htmlspecialchars($filterCompanyLabel); ?>
            </div>
        </div>
        <div class="meta-col" style="text-align:right;">
            <div class="meta-label">Ledger Period</div>
            <div class="meta-value"><?php echo formatDate($startDate); ?> &nbsp;to&nbsp; <?php echo formatDate($endDate); ?></div>
        </div>
    </div>

    <div class="print-summary-strip">
        <div class="sum-box">
            <div class="sum-label">Total Stock In</div>
            <div class="sum-value"><?php echo number_format($totalStockIn); ?> Bags</div>
        </div>
        <div class="sum-box">
            <div class="sum-label">Total Stock Out</div>
            <div class="sum-value"><?php echo number_format($totalStockOut); ?> Bags</div>
        </div>
        <div class="sum-box" style="border-color:#0F172A;">
            <div class="sum-label">Current Total Stock</div>
            <div class="sum-value"><?php echo number_format($totalAvailableStock); ?> Bags</div>
        </div>
    </div>
</div>

<!-- Stock Ledger Table (shown both on screen AND in print) -->
<div class="dark-card">
    <div class="card-header-clean no-print">
        <div class="card-title-clean">
            <i class="fa-solid fa-list text-primary-light"></i>
            <span>Stock Ledger Records</span>
        </div>
    </div>

    <div class="table-responsive">
        <table class="dark-table">
            <colgroup>
                <col class="col-date">
                <col class="col-type">
                <col class="col-product">
                <col class="col-company">
                <col class="col-in">
                <col class="col-out">
                <col class="col-running">
            </colgroup>
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Type</th>
                    <th>Product</th>
                    <th>Company</th>
                    <th class="text-end">In(+)</th>
                    <th class="text-end">Out(-)</th>
                    <th class="text-end">Stock</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($ledger)): ?>
                    <tr>
                        <td colspan="7" class="text-center text-muted py-4">No stock ledger transactions found for this period.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($ledger as $row): ?>
                        <tr>
                            <td><?php echo formatDate($row['transaction_date']); ?></td>
                            <td>
                                <?php 
                                    $bClass = 'badge-primary';
                                    if ($row['transaction_type'] === 'RECEIVE') $bClass = 'badge-cyan';
                                    if ($row['transaction_type'] === 'SALE') $bClass = 'badge-success';
                                    if (str_contains($row['transaction_type'], 'ADJUSTMENT')) $bClass = 'badge-warning';
                                    if (str_contains($row['transaction_type'], 'CANCEL')) $bClass = 'badge-danger';
                                ?>
                                <span class="badge-custom <?php echo $bClass; ?>">
                                    <?php echo htmlspecialchars($row['transaction_type']); ?>
                                </span>
                            </td>
                            <td class="fw-bold" style="color: var(--text-primary);"><?php echo htmlspecialchars($row['product_name']); ?></td>
                            <td class="text-secondary small"><?php echo htmlspecialchars($row['company_name']); ?></td>
                            <td class="text-end text-success fw-bold">
                                <?php echo ((int)$row['stock_in'] > 0) ? '+' . number_format($row['stock_in']) : '—'; ?>
                            </td>
                            <td class="text-end text-danger fw-bold">
                                <?php echo ((int)$row['stock_out'] > 0) ? '-' . number_format($row['stock_out']) : '—'; ?>
                            </td>
                            <td class="text-end fw-bold fs-6" style="color: var(--text-primary);">
                                <?php echo number_format($row['running_balance']); ?> Bags
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- ============================================================
     PRINT-ONLY FOOTER: signature lines + generated-by note
     ============================================================ -->
<div class="print-only-block">
    <div class="print-footer-block">
        <div class="sig-box">
            <div class="sig-line">Store / Warehouse In-Charge</div>
        </div>
        <div class="sig-box">
            <div class="sig-line">Authorized Signature — <?php echo htmlspecialchars($dealerName); ?></div>
        </div>
    </div>
    <div class="print-generated-note">
        This is a computer-generated stock ledger from <?php echo htmlspecialchars($dealerName); ?>'s inventory system and does not require a physical stamp unless otherwise requested.
    </div>
</div>

<!-- Stock Adjustment Modal -->
<div class="modal fade" id="adjustmentModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content dark-modal">
            <div class="modal-header dark-modal-header">
                <h5 class="modal-title" style="color: var(--text-primary);">Stock Adjustment (স্টক সমন্বয়)</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="adjustmentForm">
                <?php echo csrfField(); ?>

                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label for="adj_product_id" class="form-label-custom">Select Product <span class="required">*</span></label>
                        <select class="form-select form-select-custom" id="adj_product_id" name="product_id" required>
                            <option value="">-- Select Product --</option>
                            <?php foreach ($allProducts as $prod): ?>
                                <option value="<?php echo $prod['id']; ?>">
                                    <?php echo htmlspecialchars($prod['name']); ?> (Current Stock: <?php echo $prod['current_stock']; ?> bags)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label for="adj_date" class="form-label-custom">Adjustment Date <span class="required">*</span></label>
                            <input type="date" class="form-control form-control-custom" id="adj_date" name="adjustment_date" value="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label for="adj_type" class="form-label-custom">Adjustment Type <span class="required">*</span></label>
                            <select class="form-select form-select-custom" id="adj_type" name="adjustment_type">
                                <option value="Damaged">Damaged (নষ্ট/ছেঁড়া)</option>
                                <option value="Lost">Lost / Missing (হারিয়ে যাওয়া)</option>
                                <option value="Expired">Expired (মেয়াদোত্তীর্ণ)</option>
                                <option value="Correction_Minus">Physical Count Lower (-)</option>
                                <option value="Correction_Plus">Physical Count Higher (+)</option>
                                <option value="Other">Other Reason</option>
                            </select>
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label for="adj_action" class="form-label-custom">Action <span class="required">*</span></label>
                            <select class="form-select form-select-custom" id="adj_action" name="action">
                                <option value="decrease">Decrease Stock (-)</option>
                                <option value="increase">Increase Stock (+)</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="adj_quantity" class="form-label-custom">Quantity in Bags <span class="required">*</span></label>
                            <input type="number" class="form-control form-control-custom" id="adj_quantity" name="quantity" min="1" required placeholder="e.g. 5">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="adj_reason" class="form-label-custom">Adjustment Reason <span class="required">*</span></label>
                        <input type="text" class="form-control form-control-custom" id="adj_reason" name="reason" required placeholder="e.g. 5 bags damaged by rain water during unloading">
                    </div>

                    <div class="mb-3">
                        <label for="adj_notes" class="form-label-custom">Additional Notes</label>
                        <textarea class="form-control form-control-custom" id="adj_notes" name="notes" rows="2" placeholder="Optional notes"></textarea>
                    </div>
                </div>

                <div class="modal-footer dark-modal-footer">
                    <button type="button" class="btn btn-secondary-custom" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary-custom" id="saveAdjBtn">
                        <i class="fa-solid fa-floppy-disk me-1"></i> Apply Adjustment
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
let adjModalInstance = null;

function openAdjustmentModal() {
    document.getElementById('adjustmentForm').reset();
    if (!adjModalInstance) {
        adjModalInstance = new bootstrap.Modal(document.getElementById('adjustmentModal'));
    }
    adjModalInstance.show();
}

/**
 * Direct fetch()-based AJAX submit handler.
 * setupAjaxForm() was not preventing the browser's default form submission
 * (GET request, page navigation), so stock adjustments were never actually saved.
 */
document.getElementById('adjustmentForm').addEventListener('submit', async function (e) {
    e.preventDefault();

    const form = e.target;
    const formData = new FormData(form);
    const saveBtn = document.getElementById('saveAdjBtn');
    const originalBtnHtml = saveBtn.innerHTML;

    saveBtn.disabled = true;
    saveBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin me-1"></i> Saving...';

    try {
        const res = await fetch(`${window.BASE_URL}/ajax/stock/adjustment.php`, {
            method: 'POST',
            body: formData,
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        });

        const result = await res.json();

        if (result.success) {
            if (adjModalInstance) adjModalInstance.hide();
            setTimeout(() => window.location.reload(), 500);
        } else {
            alert(result.message || 'Failed to apply stock adjustment.');
        }
    } catch (err) {
        console.error('Stock adjustment failed:', err);
        alert('Something went wrong while saving. Check the browser console for details.');
    } finally {
        saveBtn.disabled = false;
        saveBtn.innerHTML = originalBtnHtml;
    }
});
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>