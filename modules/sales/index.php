<?php
/**
 * Maruf Traders - Sales Management Module
 */

define('APP_INIT', true);
$pageTitle = 'Sales Invoices';
$breadcrumb = 'Sales List';
$activeMenu = 'sales';

require_once __DIR__ . '/../../includes/header.php';
requirePermission('sales.view');

$db = Database::getConnection();

$startDate = $_GET['start_date'] ?? date('Y-m-01');
$endDate = $_GET['end_date'] ?? date('Y-m-d');
$retailerId = (int)($_GET['retailer_id'] ?? 0);
$status = trim($_GET['status'] ?? '');

$tenantId = TenantContext::id();

$rStmt = $db->prepare("SELECT id, name, retailer_code FROM retailers WHERE tenant_id = :tenant ORDER BY name ASC");
$rStmt->execute([':tenant' => $tenantId]);
$allRetailers = $rStmt->fetchAll();

$sql = "SELECT s.*, r.retailer_code, r.name as retailer_name, r.mobile as retailer_mobile 
        FROM sales s
        JOIN retailers r ON s.retailer_id = r.id
        WHERE s.tenant_id = :tenant AND s.sale_date BETWEEN :start AND :end";
$params = [':tenant' => $tenantId, ':start' => $startDate, ':end' => $endDate];

if ($retailerId) {
    $sql .= " AND s.retailer_id = :rid";
    $params[':rid'] = $retailerId;
}
if (!empty($status)) {
    if ($status === 'cancelled') {
        $sql .= " AND s.status = 'cancelled'";
    } else {
        $sql .= " AND s.status = 'active' AND s.payment_status = :pstatus";
        $params[':pstatus'] = $status;
    }
}

$sql .= " ORDER BY s.id DESC";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$sales = $stmt->fetchAll();

$totalSalesVal = 0;
$totalPaidVal = 0;
$totalDueVal = 0;
$totalGrossProfitVal = 0;

foreach ($sales as $s) {
    if ($s['status'] === 'active') {
        $totalSalesVal += (float)$s['total_amount'];
        $totalPaidVal += (float)$s['paid_amount'] + (float)$s['advance_deducted'];
        $totalDueVal += (float)$s['due_amount'];
        $totalGrossProfitVal += (float)$s['gross_profit'];
    }
}
?>

<div class="page-header-container no-print">
    <div>
        <h2 class="page-title">Sales Management (সিমেন্ট বিক্রয় ও ইনভয়েস)</h2>
        <div class="page-subtitle">Track sales invoices, payments received, market credit dues, and gross profit.</div>
    </div>
    <div class="d-flex gap-2">
        <?php if (hasPermission('sales.create')): ?>
            <a href="<?php echo BASE_URL; ?>/modules/sales/new.php" class="btn btn-primary-custom">
                <i class="fa-solid fa-cart-plus me-1"></i> New Sale Invoice
            </a>
        <?php endif; ?>
    </div>
</div>

<!-- Filter Bar -->
<div class="dark-card mb-4 no-print">
    <form method="GET" action="" class="row g-3 align-items-end">
        <div class="col-md-3">
            <label for="retailer_id" class="form-label-custom">Filter Retailer</label>
            <select name="retailer_id" id="retailer_id" class="form-select form-select-custom">
                <option value="">-- All Retailers --</option>
                <?php foreach ($allRetailers as $r): ?>
                    <option value="<?php echo $r['id']; ?>" <?php echo ($r['id'] == $retailerId) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($r['retailer_code'] . ' — ' . $r['name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-2">
            <label for="status" class="form-label-custom">Payment Status</label>
            <select name="status" id="status" class="form-select form-select-custom">
                <option value="">-- All Statuses --</option>
                <option value="paid" <?php echo ($status === 'paid') ? 'selected' : ''; ?>>Paid</option>
                <option value="partial" <?php echo ($status === 'partial') ? 'selected' : ''; ?>>Partial</option>
                <option value="due" <?php echo ($status === 'due') ? 'selected' : ''; ?>>Due</option>
                <option value="cancelled" <?php echo ($status === 'cancelled') ? 'selected' : ''; ?>>Cancelled / Voided</option>
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
        <div class="col-md-3">
            <button type="submit" class="btn btn-primary-custom w-100">
                <i class="fa-solid fa-filter me-1"></i> Filter
            </button>
        </div>
    </form>
</div>

<!-- KPI Strip -->
<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="dark-card py-3 text-center">
            <div class="text-secondary small fw-bold text-uppercase">Total Sales Invoiced</div>
            <div class="fs-4 fw-bold" style="color: var(--text-primary);"><?php echo formatBDT($totalSalesVal); ?></div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="dark-card py-3 text-center">
            <div class="text-secondary small fw-bold text-uppercase">Collected & Advances</div>
            <div class="fs-4 fw-bold text-success"><?php echo formatBDT($totalPaidVal); ?></div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="dark-card py-3 text-center">
            <div class="text-secondary small fw-bold text-uppercase">Market Due Added</div>
            <div class="fs-4 fw-bold text-danger"><?php echo formatBDT($totalDueVal); ?></div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="dark-card py-3 text-center">
            <div class="text-secondary small fw-bold text-uppercase">Period Gross Profit</div>
            <div class="fs-4 fw-bold text-primary-light"><?php echo formatBDT($totalGrossProfitVal); ?></div>
        </div>
    </div>
</div>

<!-- Sales Table -->
<div class="dark-card">
    <div class="card-header-clean">
        <div class="card-title-clean">
            <i class="fa-solid fa-file-invoice-dollar text-primary-light"></i>
            <span>Sales Invoices</span>
        </div>
    </div>

    <div class="table-responsive">
        <table class="dark-table">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Invoice No</th>
                    <th>Retailer</th>
                    <th class="text-end">Total (৳)</th>
                    <th class="text-end">Paid (৳)</th>
                    <th class="text-end">Due (৳)</th>
                    <th class="text-end">Gross Profit (৳)</th>
                    <th class="text-center">Status</th>
                    <th class="text-end no-print">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($sales)): ?>
                    <tr>
                        <td colspan="9" class="text-center text-muted py-4">No sales invoices found for this period.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($sales as $s): ?>
                        <?php 
                            $isVoid = ($s['status'] === 'cancelled');
                            $paidTotal = (float)$s['paid_amount'] + (float)$s['advance_deducted'];
                        ?>
                        <tr>
                            <td><?php echo formatDate($s['sale_date']); ?></td>
                            <td class="fw-bold text-info">
                                <a href="<?php echo BASE_URL; ?>/modules/sales/invoice.php?id=<?php echo $s['id']; ?>" class="text-info">
                                    <?php echo htmlspecialchars($s['invoice_no']); ?>
                                </a>
                            </td>
                            <td>
                                <div class="fw-bold" style="color: var(--text-primary);"><?php echo htmlspecialchars($s['retailer_name']); ?></div>
                                <div class="text-secondary small"><?php echo htmlspecialchars($s['retailer_code']); ?></div>
                            </td>
                            <td class="text-end fw-bold" style="color: var(--text-primary);"><?php echo formatBDT($s['total_amount']); ?></td>
                            <td class="text-end text-success fw-bold"><?php echo formatBDT($paidTotal); ?></td>
                            <td class="text-end fw-bold <?php echo ((float)$s['due_amount'] > 0) ? 'text-danger' : 'text-secondary'; ?>">
                                <?php echo formatBDT($s['due_amount']); ?>
                            </td>
                            <td class="text-end text-primary-light fw-bold"><?php echo formatBDT($s['gross_profit']); ?></td>
                            <td class="text-center">
                                <?php if ($isVoid): ?>
                                    <span class="badge-custom badge-danger">Cancelled</span>
                                <?php elseif ($s['payment_status'] === 'paid'): ?>
                                    <span class="badge-custom badge-success">Paid</span>
                                <?php elseif ($s['payment_status'] === 'partial'): ?>
                                    <span class="badge-custom badge-warning">Partial</span>
                                <?php else: ?>
                                    <span class="badge-custom badge-danger">Due</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-end no-print">
                                <div class="btn-group btn-group-sm">
                                    <a href="<?php echo BASE_URL; ?>/modules/sales/invoice.php?id=<?php echo $s['id']; ?>" class="btn btn-secondary-custom" title="View & Print Invoice">
                                        <i class="fa-solid fa-print text-cyan"></i>
                                    </a>
                                    <?php if (!$isVoid && hasPermission('sales.cancel')): ?>
                                        <button type="button" class="btn btn-danger-custom" onclick="cancelSale(<?php echo $s['id']; ?>, '<?php echo $s['invoice_no']; ?>')" title="Cancel Sale">
                                            <i class="fa-solid fa-ban"></i>
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
async function cancelSale(id, invoiceNo) {
    // Theme-aware SweetAlert2 colors: read the same data-theme the page is using
    const isLight = document.documentElement.getAttribute('data-theme') === 'light';
    const swalBg = isLight ? '#FFFFFF' : '#151F36';
    const swalColor = isLight ? '#0F172A' : '#F8FAFC';

    const { value: reason } = await Swal.fire({
        title: `Cancel Invoice: ${invoiceNo}?`,
        text: 'This will reverse product stock, restore customer due/advance balances, and recalculate monthly sales targets.',
        input: 'text',
        inputPlaceholder: 'Enter cancellation reason...',
        inputValidator: (value) => {
            if (!value) return 'A cancellation reason is required!';
        },
        showCancelButton: true,
        confirmButtonColor: '#EF4444',
        cancelButtonColor: '#475569',
        confirmButtonText: 'Yes, Cancel Invoice',
        background: swalBg,
        color: swalColor
    });

    if (reason) {
        try {
            const formData = new FormData();
            formData.append('id', id);
            formData.append('reason', reason);
            formData.append('csrf_token', getCsrfToken());

            const res = await apiRequest(`${window.BASE_URL}/ajax/sales/cancel.php`, {
                method: 'POST',
                body: formData
            });

            if (res.success) {
                showToast('success', res.message);
                setTimeout(() => window.location.reload(), 1000);
            }
        } catch (e) {
            console.error(e);
        }
    }
}
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>