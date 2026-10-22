<?php
/**
 * Maruf Traders - Cement Receive / Purchase List Module
 */

define('APP_INIT', true);
$pageTitle = 'Cement Receive History';
$breadcrumb = 'Cement Receive';
$activeMenu = 'receives';

require_once __DIR__ . '/../../includes/header.php';
requirePermission('receives.manage');

$db = Database::getConnection();

$startDate = $_GET['start_date'] ?? date('Y-m-01');
$endDate = $_GET['end_date'] ?? date('Y-m-d');
$companyId = (int)($_GET['company_id'] ?? 0);

$tenantId = TenantContext::id();

$cStmt = $db->prepare("SELECT id, name FROM companies WHERE tenant_id = :tenant AND status = 'active' ORDER BY name ASC");
$cStmt->execute([':tenant' => $tenantId]);
$allCompanies = $cStmt->fetchAll();

$sql = "SELECT cr.*, c.name as company_name, p.name as product_name, p.brand, p.unit 
        FROM cement_receives cr
        JOIN companies c ON cr.company_id = c.id
        JOIN products p ON cr.product_id = p.id
        WHERE cr.tenant_id = :tenant AND cr.receive_date BETWEEN :start AND :end";
$params = [':tenant' => $tenantId, ':start' => $startDate, ':end' => $endDate];

if ($companyId) {
    $sql .= " AND cr.company_id = :cid";
    $params[':cid'] = $companyId;
}

$sql .= " ORDER BY cr.id DESC";
$stmt = $db->prepare($sql);
$stmt->execute($params);
$receives = $stmt->fetchAll();

$totalBags = 0.0;
$totalCostSum = 0;
$totalPaidSum = 0;
$totalPayableSum = 0;

foreach ($receives as $r) {
    if ($r['status'] === 'active') {
        $totalBags += (float)$r['quantity'];
        $totalCostSum += (float)$r['total_cost'];
        $totalPaidSum += (float)$r['paid_amount'];
        $totalPayableSum += (float)$r['payable_amount'];
    }
}
?>

<div class="page-header-container no-print">
    <div>
        <h2 class="page-title">Cement Receive & Purchases (সিমেন্ট ক্রয় ও রিসিভ)</h2>
        <div class="page-subtitle">Track incoming cement shipments, transport/labor costs, cost basis and company payable dues.</div>
    </div>
    <div class="d-flex gap-2">
        <a href="<?php echo BASE_URL; ?>/modules/receives/new.php" class="btn btn-primary-custom">
            <i class="fa-solid fa-plus me-1"></i> New Cement Receive
        </a>
    </div>
</div>

<!-- Filter Bar -->
<div class="dark-card mb-4 no-print">
    <form method="GET" action="" class="row g-3 align-items-end">
        <div class="col-md-4">
            <label for="company_id" class="form-label-custom">Filter by Company</label>
            <select name="company_id" id="company_id" class="form-select form-select-custom">
                <option value="">-- All Companies --</option>
                <?php foreach ($allCompanies as $comp): ?>
                    <option value="<?php echo $comp['id']; ?>" <?php echo ($comp['id'] == $companyId) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($comp['name']); ?>
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

<!-- KPI Strip -->
<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="dark-card py-3 text-center">
            <div class="text-secondary small fw-bold text-uppercase">Period Total Bags</div>
            <div class="fs-4 fw-bold text-cyan"><?php echo number_format($totalBags); ?> Bags</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="dark-card py-3 text-center">
            <div class="text-secondary small fw-bold text-uppercase">Period Total Cost</div>
            <div class="fs-4 fw-bold" style="color: var(--text-primary);"><?php echo formatBDT($totalCostSum); ?></div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="dark-card py-3 text-center">
            <div class="text-secondary small fw-bold text-uppercase">Paid at Receive</div>
            <div class="fs-4 fw-bold text-success"><?php echo formatBDT($totalPaidSum); ?></div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="dark-card py-3 text-center">
            <div class="text-secondary small fw-bold text-uppercase">Added to Payable</div>
            <div class="fs-4 fw-bold text-danger"><?php echo formatBDT($totalPayableSum); ?></div>
        </div>
    </div>
</div>

<!-- Receives Table -->
<div class="dark-card">
    <div class="card-header-clean">
        <div class="card-title-clean">
            <i class="fa-solid fa-list-check text-primary-light"></i>
            <span>Cement Receive History</span>
        </div>
    </div>

    <div class="table-responsive">
        <table class="dark-table">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Receive Code</th>
                    <th>Company</th>
                    <th>Cement Product</th>
                    <th class="text-end">Quantity</th>
                    <th class="text-end">Cost / Bag</th>
                    <th class="text-end">Total Cost</th>
                    <th class="text-end">Paid Amount</th>
                    <th class="text-end">Payable</th>
                    <th class="text-center">Status</th>
                    <th class="text-end no-print">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($receives)): ?>
                    <tr>
                        <td colspan="11" class="text-center text-muted py-4">No cement receives found for this period.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($receives as $r): ?>
                        <tr>
                            <td><?php echo formatDate($r['receive_date']); ?></td>
                            <td class="fw-bold text-info"><?php echo htmlspecialchars($r['receive_code']); ?></td>
                            <td class="fw-bold" style="color: var(--text-primary);"><?php echo htmlspecialchars($r['company_name']); ?></td>
                            <td><?php echo htmlspecialchars($r['product_name']); ?></td>
                            <td class="text-end fw-bold text-cyan"><?php echo number_format($r['quantity']); ?> Bags</td>
                            <td class="text-end"><?php echo formatBDT($r['unit_cost_basis']); ?></td>
                            <td class="text-end fw-bold" style="color: var(--text-primary);"><?php echo formatBDT($r['total_cost']); ?></td>
                            <td class="text-end text-success"><?php echo formatBDT($r['paid_amount']); ?></td>
                            <td class="text-end text-danger fw-bold"><?php echo formatBDT($r['payable_amount']); ?></td>
                            <td class="text-center">
                                <?php if ($r['status'] === 'cancelled'): ?>
                                    <span class="badge-custom badge-danger">Cancelled</span>
                                <?php else: ?>
                                    <span class="badge-custom badge-success">Active</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-end no-print">
                                <?php if ($r['status'] === 'active'): ?>
                                    <button type="button" class="btn btn-danger-custom btn-sm" onclick="cancelReceive(<?php echo $r['id']; ?>, '<?php echo htmlspecialchars($r['receive_code'], ENT_QUOTES); ?>')" title="Cancel / Void Receive">
                                        <i class="fa-solid fa-ban me-1"></i> Cancel
                                    </button>
                                <?php else: ?>
                                    <span class="text-muted small">Voided</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
// Embedded server-side, since a JS getCsrfToken() helper isn't reliably
// available on every page. This guarantees the token is always correct.
const CSRF_TOKEN = '<?php echo getCsrfToken(); ?>';

async function cancelReceive(id, code) {
    // Theme-aware SweetAlert2 colors: read the same data-theme the page is using
    const isLight = document.documentElement.getAttribute('data-theme') === 'light';
    const swalBg = isLight ? '#FFFFFF' : '#151F36';
    const swalColor = isLight ? '#0F172A' : '#F8FAFC';

    const { value: reason } = await Swal.fire({
        title: `Cancel Receive: ${code}?`,
        text: 'This will reverse stock inventory and deduct the payable amount from company ledger.',
        input: 'text',
        inputPlaceholder: 'Enter reason for cancellation...',
        inputValidator: (value) => {
            if (!value) return 'You must provide a cancellation reason!';
        },
        showCancelButton: true,
        confirmButtonColor: '#EF4444',
        cancelButtonColor: '#475569',
        confirmButtonText: 'Yes, Cancel & Revert',
        background: swalBg,
        color: swalColor
    });

    if (reason) {
        try {
            const formData = new FormData();
            formData.append('id', id);
            formData.append('reason', reason);
            formData.append('csrf_token', CSRF_TOKEN);

            const res = await fetch(`${window.BASE_URL}/ajax/receives/cancel.php`, {
                method: 'POST',
                body: formData,
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            });
            const result = await res.json();

            if (result.success) {
                if (typeof showToast === 'function') {
                    showToast('success', result.message);
                } else {
                    alert(result.message);
                }
                setTimeout(() => window.location.reload(), 1000);
            } else {
                if (typeof showToast === 'function') {
                    showToast('error', result.message || 'Failed to cancel receive.');
                } else {
                    alert(result.message || 'Failed to cancel receive.');
                }
            }
        } catch (e) {
            console.error(e);
            alert('Something went wrong. Check console for details.');
        }
    }
}
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>