<?php
/**
 * Maruf Traders - Customer Advance Deposits Module
 */

define('APP_INIT', true);
$pageTitle = 'Retailer Advances';
$breadcrumb = 'Advances';
$activeMenu = 'advances';

require_once __DIR__ . '/../../includes/header.php';
requirePermission('advances.manage');

$db = Database::getConnection();
$tenantId = TenantContext::id();

$startDate = $_GET['start_date'] ?? date('Y-m-01');
$endDate = $_GET['end_date'] ?? date('Y-m-d');
$retailerId = (int)($_GET['retailer_id'] ?? 0);

try {
    $rStmt = $db->prepare("SELECT id, name, retailer_code, advance_balance FROM retailers WHERE tenant_id = :tenant AND status = 'active' AND deleted_at IS NULL ORDER BY name ASC");
    $rStmt->execute([':tenant' => $tenantId]);
    $allRetailers = $rStmt->fetchAll();
} catch (PDOException $e) {
    $rStmt = $db->prepare("SELECT id, name, retailer_code, advance_balance FROM retailers WHERE tenant_id = :tenant AND status = 'active' ORDER BY name ASC");
    $rStmt->execute([':tenant' => $tenantId]);
    $allRetailers = $rStmt->fetchAll();
}

$sql = "SELECT a.*, r.retailer_code, r.name as retailer_name, r.mobile as retailer_mobile 
        FROM advances a
        JOIN retailers r ON a.retailer_id = r.id
        WHERE a.tenant_id = :tenant AND a.advance_date BETWEEN :start AND :end";
$params = [':tenant' => $tenantId, ':start' => $startDate, ':end' => $endDate];

if ($retailerId) {
    $sql .= " AND a.retailer_id = :rid";
    $params[':rid'] = $retailerId;
}

$sql .= " ORDER BY a.id DESC";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$advances = $stmt->fetchAll();

$totalAdvanceDeposits = 0;
$totalRemaining = 0;

foreach ($advances as $adv) {
    if ($adv['status'] === 'active') {
        $totalAdvanceDeposits += (float)$adv['amount'];
        $totalRemaining += (float)$adv['remaining_amount'];
    }
}
?>

<div class="page-header-container no-print">
    <div>
        <h2 class="page-title">Advance Deposits (কাস্টমার অগ্রিম জমা)</h2>
        <div class="page-subtitle">Track customer advance security balances, deposit receipts, and usage against sales invoices.</div>
    </div>
    <div class="d-flex gap-2">
        <button type="button" class="btn btn-primary-custom" onclick="openAdvanceModal()">
            <i class="fa-solid fa-plus me-1"></i> Add Advance Deposit
        </button>
    </div>
</div>

<!-- Filter Bar -->
<div class="dark-card mb-4 no-print">
    <form method="GET" action="" class="row g-3 align-items-end">
        <div class="col-md-4">
            <label for="retailer_id" class="form-label-custom">Filter Retailer</label>
            <select name="retailer_id" id="retailer_id" class="form-select form-select-custom">
                <option value="">-- All Retailers --</option>
                <?php foreach ($allRetailers as $r): ?>
                    <option value="<?php echo $r['id']; ?>" <?php echo ($r['id'] == $retailerId) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($r['retailer_code'] . ' — ' . $r['name']); ?> (Adv: <?php echo formatBDT($r['advance_balance']); ?>)
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
        <div class="dark-card py-3 text-center">
            <div class="text-secondary small fw-bold text-uppercase">Total Period Advances Deposited</div>
            <div class="fs-4 fw-bold text-cyan"><?php echo formatBDT($totalAdvanceDeposits); ?></div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="dark-card py-3 text-center">
            <div class="text-secondary small fw-bold text-uppercase">Currently Available Advance</div>
            <div class="fs-4 fw-bold text-success"><?php echo formatBDT($totalRemaining); ?></div>
        </div>
    </div>
</div>

<!-- Advances Table -->
<div class="dark-card">
    <div class="card-header-clean">
        <div class="card-title-clean">
            <i class="fa-solid fa-credit-card text-primary-light"></i>
            <span>Advance Deposit Records</span>
        </div>
    </div>

    <div class="table-responsive">
        <table class="dark-table">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Advance Code</th>
                    <th>Retailer</th>
                    <th>Payment Method</th>
                    <th>Transaction Ref</th>
                    <th class="text-end">Deposited Amount (৳)</th>
                    <th class="text-end">Used in Sales (৳)</th>
                    <th class="text-end">Remaining (৳)</th>
                    <th class="text-center">Status</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($advances)): ?>
                    <tr>
                        <td colspan="9" class="text-center text-muted py-4">No advance deposits found for this period.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($advances as $adv): ?>
                        <tr>
                            <td><?php echo formatDate($adv['advance_date']); ?></td>
                            <td class="fw-bold text-info"><?php echo htmlspecialchars($adv['advance_code']); ?></td>
                            <td>
                                <div class="fw-bold text-white"><?php echo htmlspecialchars($adv['retailer_name']); ?></div>
                                <div class="text-secondary small"><?php echo htmlspecialchars($adv['retailer_code']); ?></div>
                            </td>
                            <td>
                                <span class="badge-custom badge-primary"><?php echo htmlspecialchars($adv['payment_method']); ?></span>
                            </td>
                            <td class="text-secondary"><?php echo htmlspecialchars($adv['transaction_ref'] ?: '—'); ?></td>
                            <td class="text-end fw-bold text-cyan"><?php echo formatBDT($adv['amount']); ?></td>
                            <td class="text-end text-secondary"><?php echo formatBDT($adv['used_amount']); ?></td>
                            <td class="text-end fw-bold text-success fs-6"><?php echo formatBDT($adv['remaining_amount']); ?></td>
                            <td class="text-center">
                                <span class="badge-custom <?php echo ($adv['status'] === 'active') ? 'badge-success' : 'badge-danger'; ?>">
                                    <?php echo ucfirst($adv['status']); ?>
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Add Advance Modal -->
<div class="modal fade" id="advanceModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content dark-modal">
            <div class="modal-header dark-modal-header">
                <h5 class="modal-title text-white">Record Customer Advance Deposit</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="advanceForm">
                <?php echo csrfField(); ?>

                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label for="adv_retailer_id" class="form-label-custom">Retailer / Customer <span class="required">*</span></label>
                        <select class="form-select form-select-custom" id="adv_retailer_id" name="retailer_id" required>
                            <option value="">-- Select Retailer --</option>
                            <?php foreach ($allRetailers as $r): ?>
                                <option value="<?php echo $r['id']; ?>">
                                    <?php echo htmlspecialchars($r['retailer_code'] . ' — ' . $r['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label for="adv_date" class="form-label-custom">Advance Date <span class="required">*</span></label>
                            <input type="date" class="form-control form-control-custom" id="adv_date" name="advance_date" value="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label for="adv_amount" class="form-label-custom">Amount (৳) <span class="required">*</span></label>
                            <input type="number" step="0.01" class="form-control form-control-custom" id="adv_amount" name="amount" min="1" required placeholder="0.00">
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label for="adv_pmethod" class="form-label-custom">Payment Method</label>
                            <select class="form-select form-select-custom" id="adv_pmethod" name="payment_method">
                                <option value="Cash">Cash</option>
                                <option value="bKash">bKash</option>
                                <option value="Nagad">Nagad</option>
                                <option value="Bank">Bank Transfer</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="adv_txref" class="form-label-custom">TrxID / Cheque / Bank Info</label>
                            <input type="text" class="form-control form-control-custom" id="adv_txref" name="transaction_ref" placeholder="Optional reference">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="adv_notes" class="form-label-custom">Notes</label>
                        <input type="text" class="form-control form-control-custom" id="adv_notes" name="notes" placeholder="Optional notes">
                    </div>
                </div>

                <div class="modal-footer dark-modal-footer">
                    <button type="button" class="btn btn-secondary-custom" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary-custom" id="saveAdvBtn">
                        <i class="fa-solid fa-floppy-disk me-1"></i> Save Advance
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
let advModalInstance = null;

function openAdvanceModal() {
    document.getElementById('advanceForm').reset();
    if (!advModalInstance) {
        advModalInstance = new bootstrap.Modal(document.getElementById('advanceModal'));
    }
    advModalInstance.show();
}

/**
 * Direct fetch()-based AJAX submit handler.
 * setupAjaxForm() was not preventing the browser's default form submission
 * (GET request, page navigation), so advances were never actually saved.
 */
document.getElementById('advanceForm').addEventListener('submit', async function (e) {
    e.preventDefault();

    const form = e.target;
    const formData = new FormData(form);
    const saveBtn = document.getElementById('saveAdvBtn');
    const originalBtnHtml = saveBtn.innerHTML;

    saveBtn.disabled = true;
    saveBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin me-1"></i> Saving...';

    try {
        const res = await fetch(`${window.BASE_URL}/ajax/advances/save.php`, {
            method: 'POST',
            body: formData,
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        });

        const result = await res.json();

        if (result.success) {
            if (advModalInstance) advModalInstance.hide();
            setTimeout(() => window.location.reload(), 500);
        } else {
            alert(result.message || 'Failed to save advance deposit.');
        }
    } catch (err) {
        console.error('Advance save failed:', err);
        alert('Something went wrong while saving. Check the browser console for details.');
    } finally {
        saveBtn.disabled = false;
        saveBtn.innerHTML = originalBtnHtml;
    }
});
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>