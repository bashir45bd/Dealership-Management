<?php
/**
 * Maruf Traders - Company Payments Module
 */

define('APP_INIT', true);
$pageTitle = 'Company Payments';
$breadcrumb = 'Company Payments';
$activeMenu = 'company_payments';

require_once __DIR__ . '/../../includes/header.php';
requirePermission('company_payments.manage');

$db = Database::getConnection();
$tenantId = TenantContext::id();

$startDate = $_GET['start_date'] ?? date('Y-m-01');
$endDate = $_GET['end_date'] ?? date('Y-m-d');
$companyId = (int)($_GET['company_id'] ?? 0);

$cStmt = $db->prepare("SELECT id, name, company_code, current_payable FROM companies WHERE tenant_id = :tenant AND status = 'active' ORDER BY name ASC");
$cStmt->execute([':tenant' => $tenantId]);
$allCompanies = $cStmt->fetchAll();

$sql = "SELECT cp.*, c.company_code, c.name as company_name 
        FROM company_payments cp
        JOIN companies c ON cp.company_id = c.id
        WHERE cp.tenant_id = :tenant AND cp.payment_date BETWEEN :start AND :end";
$params = [':tenant' => $tenantId, ':start' => $startDate, ':end' => $endDate];

if ($companyId) {
    $sql .= " AND cp.company_id = :cid";
    $params[':cid'] = $companyId;
}

$sql .= " ORDER BY cp.id DESC";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$payments = $stmt->fetchAll();

$totalPaid = 0;
foreach ($payments as $p) {
    if ($p['status'] === 'active') {
        $totalPaid += (float)$p['amount'];
    }
}
?>

<div class="page-header-container no-print">
    <div>
        <h2 class="page-title">Company Payments (কোম্পানি পেমেন্ট ও পরিশোধ)</h2>
        <div class="page-subtitle">Record bank transfers, cheques and cash payments made to cement manufacturers.</div>
    </div>
    <div class="d-flex gap-2">
        <button type="button" class="btn btn-primary-custom" onclick="openPaymentModal()">
            <i class="fa-solid fa-money-bill-transfer me-1"></i> Make Company Payment
        </button>
    </div>
</div>

<!-- Filter Bar -->
<div class="dark-card mb-4 no-print">
    <form method="GET" action="" class="row g-3 align-items-end">
        <div class="col-md-4">
            <label for="company_id" class="form-label-custom">Filter Company</label>
            <select name="company_id" id="company_id" class="form-select form-select-custom">
                <option value="">-- All Companies --</option>
                <?php foreach ($allCompanies as $comp): ?>
                    <option value="<?php echo $comp['id']; ?>" <?php echo ($comp['id'] == $companyId) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($comp['company_code'] . ' — ' . $comp['name']); ?> (Payable: <?php echo formatBDT($comp['current_payable']); ?>)
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
    <div class="col-md-6">
        <div class="dark-card py-3">
            <div class="text-secondary small fw-bold text-uppercase">Payments Made Count</div>
            <div class="fs-4 fw-bold" style="color: var(--text-primary);"><?php echo count($payments); ?> Vouchers</div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="dark-card py-3">
            <div class="text-secondary small fw-bold text-uppercase">Total Period Company Payments</div>
            <div class="fs-4 fw-bold text-success"><?php echo formatBDT($totalPaid); ?></div>
        </div>
    </div>
</div>

<!-- Payments Table -->
<div class="dark-card">
    <div class="card-header-clean">
        <div class="card-title-clean">
            <i class="fa-solid fa-building-columns text-primary-light"></i>
            <span>Payment Vouchers</span>
        </div>
    </div>

    <div class="table-responsive">
        <table class="dark-table">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Payment Code</th>
                    <th>Company / Supplier</th>
                    <th>Payment Method</th>
                    <th>Bank / Cheque Ref</th>
                    <th class="text-end">Amount Paid (৳)</th>
                    <th class="text-center">Status</th>
                    <th>Notes</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($payments)): ?>
                    <tr>
                        <td colspan="8" class="text-center text-muted py-4">No payments recorded for the selected period.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($payments as $p): ?>
                        <tr>
                            <td><?php echo formatDate($p['payment_date']); ?></td>
                            <td class="fw-bold text-info"><?php echo htmlspecialchars($p['payment_code']); ?></td>
                            <td class="fw-bold" style="color: var(--text-primary);"><?php echo htmlspecialchars($p['company_name']); ?></td>
                            <td>
                                <span class="badge-custom badge-primary"><?php echo htmlspecialchars($p['payment_method']); ?></span>
                            </td>
                            <td class="text-secondary"><?php echo htmlspecialchars($p['reference_no'] ?: ($p['bank_name'] ?: '—')); ?></td>
                            <td class="text-end fw-bold text-success fs-6"><?php echo formatBDT($p['amount']); ?></td>
                            <td class="text-center">
                                <span class="badge-custom <?php echo ($p['status'] === 'active') ? 'badge-success' : 'badge-danger'; ?>">
                                    <?php echo ucfirst($p['status']); ?>
                                </span>
                            </td>
                            <td class="text-secondary small"><?php echo htmlspecialchars($p['notes'] ?: '—'); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Make Payment Modal -->
<div class="modal fade" id="paymentModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content dark-modal">
            <div class="modal-header dark-modal-header">
                <h5 class="modal-title" style="color: var(--text-primary);">Record Company Payment</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="paymentForm">
                <?php echo csrfField(); ?>

                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label for="pay_company_id" class="form-label-custom">Company / Supplier <span class="required">*</span></label>
                        <select class="form-select form-select-custom" id="pay_company_id" name="company_id" required onchange="onCompanyPayChange()">
                            <option value="">-- Select Company --</option>
                            <?php foreach ($allCompanies as $comp): ?>
                                <option value="<?php echo $comp['id']; ?>" data-payable="<?php echo $comp['current_payable']; ?>">
                                    <?php echo htmlspecialchars($comp['company_code'] . ' — ' . $comp['name']); ?> (Payable: <?php echo formatBDT($comp['current_payable']); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label for="pay_date" class="form-label-custom">Payment Date <span class="required">*</span></label>
                            <input type="date" class="form-control form-control-custom" id="pay_date" name="payment_date" value="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label for="pay_amount" class="form-label-custom">Amount (৳) <span class="required">*</span></label>
                            <input type="number" step="0.01" class="form-control form-control-custom" id="pay_amount" name="amount" min="1" required placeholder="0.00">
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label for="pay_pmethod" class="form-label-custom">Payment Method</label>
                            <select class="form-select form-select-custom" id="pay_pmethod" name="payment_method">
                                <option value="Bank">Bank Transfer / Online</option>
                                <option value="Cheque">Cheque</option>
                                <option value="Cash">Cash</option>
                                <option value="bKash">bKash</option>
                                <option value="Nagad">Nagad</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="pay_ref" class="form-label-custom">Cheque / TrxID / Deposit Ref</label>
                            <input type="text" class="form-control form-control-custom" id="pay_ref" name="reference_no" placeholder="e.g. Cheque #492812">
                        </div>
                    </div>

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="pay_bank" class="form-label-custom">Bank Name / Branch</label>
                            <input type="text" class="form-control form-control-custom" id="pay_bank" name="bank_name" placeholder="e.g. Islami Bank, Chunarughat">
                        </div>
                        <div class="col-md-6">
                            <label for="pay_notes" class="form-label-custom">Notes</label>
                            <input type="text" class="form-control form-control-custom" id="pay_notes" name="notes" placeholder="Optional notes">
                        </div>
                    </div>
                </div>

                <div class="modal-footer dark-modal-footer">
                    <button type="button" class="btn btn-secondary-custom" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary-custom" id="savePayBtn">
                        <i class="fa-solid fa-floppy-disk me-1"></i> Save Payment
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
let payModalInstance = null;

function openPaymentModal() {
    document.getElementById('paymentForm').reset();
    if (!payModalInstance) {
        payModalInstance = new bootstrap.Modal(document.getElementById('paymentModal'));
    }
    payModalInstance.show();
}

function onCompanyPayChange() {
    const sel = document.getElementById('pay_company_id');
    const opt = sel.options[sel.selectedIndex];
    if (opt && opt.dataset.payable) {
        const payable = parseFloat(opt.dataset.payable) || 0;
        document.getElementById('pay_amount').placeholder = `Current Payable: ${payable}`;
    }
}

/**
 * Direct fetch()-based AJAX submit handler.
 * setupAjaxForm() was not preventing the browser's default form submission
 * (GET request, page navigation), so company payments were never actually saved.
 */
document.getElementById('paymentForm').addEventListener('submit', async function (e) {
    e.preventDefault();

    const form = e.target;
    const formData = new FormData(form);
    const saveBtn = document.getElementById('savePayBtn');
    const originalBtnHtml = saveBtn.innerHTML;

    saveBtn.disabled = true;
    saveBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin me-1"></i> Saving...';

    try {
        const res = await fetch(`${window.BASE_URL}/ajax/company_payments/save.php`, {
            method: 'POST',
            body: formData,
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        });

        const result = await res.json();

        if (result.success) {
            if (payModalInstance) payModalInstance.hide();
            setTimeout(() => window.location.reload(), 500);
        } else {
            alert(result.message || 'Failed to save company payment.');
        }
    } catch (err) {
        console.error('Company payment save failed:', err);
        alert('Something went wrong while saving. Check the browser console for details.');
    } finally {
        saveBtn.disabled = false;
        saveBtn.innerHTML = originalBtnHtml;
    }
});
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>