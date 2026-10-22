<?php
/**
 * Maruf Traders - Other Income (Manual / Non-Retailer Income Entries)
 *
 * For income that does NOT come from retailer sales, collections, or
 * advances - e.g. old commissions earned before this software was in use
 * (no sales data exists for them), rent, asset sales, interest, etc.
 *
 * Every active entry here counts toward Net Profit for the period whose
 * date range contains its income_date - same recognition pattern as
 * Quarterly/Yearly received commissions, but with no pending/received
 * workflow: an entry counts as soon as it's added, dated as of income_date.
 */

define('APP_INIT', true);
$pageTitle = 'Other Income';
$breadcrumb = 'Other Income';
$activeMenu = 'other_income';

require_once __DIR__ . '/../../includes/header.php';
requirePermission('other_income.manage');

$db = Database::getConnection();

$categoryFilter = trim($_GET['category'] ?? '');
$fromFilter = trim($_GET['from_date'] ?? '');
$toFilter = trim($_GET['to_date'] ?? '');

$cStmt = $db->query("SELECT id, name FROM companies WHERE status = 'active' ORDER BY name ASC");
$allCompanies = $cStmt->fetchAll();

$categories = [
    'old_commission' => 'Old Commission (Pre-Software)',
    'rent'            => 'Rent Income',
    'asset_sale'      => 'Asset Sale',
    'interest'        => 'Interest / Bank Profit',
    'other'           => 'Other',
];

$sql = "SELECT oi.*, c.name as company_name
        FROM other_incomes oi
        LEFT JOIN companies c ON oi.company_id = c.id
        WHERE oi.status = 'active'";
$params = [];

if ($categoryFilter !== '' && isset($categories[$categoryFilter])) {
    $sql .= " AND oi.category = :cat";
    $params[':cat'] = $categoryFilter;
}
if ($fromFilter !== '') {
    $sql .= " AND oi.income_date >= :from";
    $params[':from'] = $fromFilter;
}
if ($toFilter !== '') {
    $sql .= " AND oi.income_date <= :to";
    $params[':to'] = $toFilter;
}

$sql .= " ORDER BY oi.income_date DESC, oi.id DESC";
$stmt = $db->prepare($sql);
$stmt->execute($params);
$incomes = $stmt->fetchAll();

$totalAmount = 0;
foreach ($incomes as $inc) {
    $totalAmount += (float)$inc['amount'];
}
?>

<div class="page-header-container no-print">
    <div>
        <h2 class="page-title">Other Income (অন্যান্য আয়)</h2>
        <div class="page-subtitle">Manual income entries not tied to retailer sales, collections, or advances — old pre-software commissions, rent, asset sale, etc. Counted toward Net Profit as of the Income Date.</div>
    </div>
    <div class="d-flex gap-2">
        <button type="button" class="btn btn-primary-custom" onclick="openIncomeModal()">
            <i class="fa-solid fa-plus me-1"></i> Add Other Income
        </button>
    </div>
</div>

<!-- Filter Bar -->
<div class="dark-card mb-4 no-print">
    <form method="GET" action="" class="row g-3 align-items-end">
        <div class="col-md-3">
            <label for="category" class="form-label-custom">Category</label>
            <select name="category" id="category" class="form-select form-select-custom">
                <option value="">-- All Categories --</option>
                <?php foreach ($categories as $key => $label): ?>
                    <option value="<?php echo $key; ?>" <?php echo ($categoryFilter === $key) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($label); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-3">
            <label for="from_date" class="form-label-custom">From Date</label>
            <input type="date" name="from_date" id="from_date" class="form-control form-control-custom" value="<?php echo htmlspecialchars($fromFilter); ?>">
        </div>
        <div class="col-md-3">
            <label for="to_date" class="form-label-custom">To Date</label>
            <input type="date" name="to_date" id="to_date" class="form-control form-control-custom" value="<?php echo htmlspecialchars($toFilter); ?>">
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
    <div class="col-md-12">
        <div class="dark-card py-3 text-center">
            <div class="text-secondary small fw-bold text-uppercase">Total Other Income (Filtered)</div>
            <div class="fs-4 fw-bold text-success"><?php echo formatBDT($totalAmount); ?></div>
        </div>
    </div>
</div>

<!-- Income Table -->
<div class="dark-card">
    <div class="card-header-clean">
        <div class="card-title-clean">
            <i class="fa-solid fa-coins text-primary-light"></i>
            <span>Other Income Records</span>
        </div>
    </div>

    <div class="table-responsive">
        <table class="dark-table">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Title</th>
                    <th>Category</th>
                    <th>Related Company</th>
                    <th class="text-end">Amount</th>
                    <th>Notes</th>
                    <th class="text-end no-print">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($incomes)): ?>
                    <tr>
                        <td colspan="7" class="text-center text-muted py-4">No other income records yet. Click "Add Other Income" to add one.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($incomes as $inc): ?>
                        <tr>
                            <td style="color: var(--text-primary);"><?php echo formatDate($inc['income_date']); ?></td>
                            <td class="fw-bold" style="color: var(--text-primary);"><?php echo htmlspecialchars($inc['title']); ?></td>
                            <td><span class="badge-custom badge-primary"><?php echo htmlspecialchars($categories[$inc['category']] ?? ucfirst($inc['category'])); ?></span></td>
                            <td class="text-secondary"><?php echo $inc['company_name'] ? htmlspecialchars($inc['company_name']) : '—'; ?></td>
                            <td class="text-end fw-bold text-success"><?php echo formatBDT($inc['amount']); ?></td>
                            <td class="text-secondary small"><?php echo $inc['notes'] ? htmlspecialchars($inc['notes']) : '—'; ?></td>
                            <td class="text-end no-print">
                                <button type="button" class="btn btn-outline-danger btn-sm"
                                    onclick="confirmDeleteIncome(<?php echo $inc['id']; ?>, '<?php echo htmlspecialchars($inc['title'], ENT_QUOTES); ?>', '<?php echo htmlspecialchars(formatBDT($inc['amount']), ENT_QUOTES); ?>')">
                                    <i class="fa-solid fa-trash"></i>
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Add Other Income Modal -->
<div class="modal fade" id="incomeModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content dark-modal">
            <div class="modal-header dark-modal-header">
                <h5 class="modal-title" style="color: var(--text-primary);">Add Other Income</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="incomeForm">
                <?php echo csrfField(); ?>

                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label for="oi_date" class="form-label-custom">Income Date <span class="required">*</span></label>
                        <input type="date" class="form-control form-control-custom" id="oi_date" name="income_date" required value="<?php echo date('Y-m-d'); ?>">
                        <small class="text-secondary" style="font-size: 0.75rem;">This is the date used to count this income toward Net Profit (Today/Week/Month/Year filters).</small>
                    </div>

                    <div class="mb-3">
                        <label for="oi_title" class="form-label-custom">Title / Description <span class="required">*</span></label>
                        <input type="text" class="form-control form-control-custom" id="oi_title" name="title" required placeholder="e.g. 2023 Yearly Commission - ABC Cement (pre-software)">
                    </div>

                    <div class="mb-3">
                        <label for="oi_category" class="form-label-custom">Category <span class="required">*</span></label>
                        <select class="form-select form-select-custom" id="oi_category" name="category" required>
                            <?php foreach ($categories as $key => $label): ?>
                                <option value="<?php echo $key; ?>"><?php echo htmlspecialchars($label); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label for="oi_company" class="form-label-custom">Related Company (Optional)</label>
                        <select class="form-select form-select-custom" id="oi_company" name="company_id">
                            <option value="">-- None / Not Applicable --</option>
                            <?php foreach ($allCompanies as $comp): ?>
                                <option value="<?php echo $comp['id']; ?>"><?php echo htmlspecialchars($comp['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label for="oi_amount" class="form-label-custom">Amount (৳) <span class="required">*</span></label>
                        <input type="number" step="0.01" class="form-control form-control-custom" id="oi_amount" name="amount" min="0.01" required placeholder="e.g. 20000">
                    </div>

                    <div class="mb-3">
                        <label for="oi_notes" class="form-label-custom">Notes</label>
                        <textarea class="form-control form-control-custom" id="oi_notes" name="notes" rows="2" placeholder="Optional notes"></textarea>
                    </div>

                    <div class="alert alert-danger py-2 small mb-0" id="incomeFormError" style="display: none;"></div>
                </div>

                <div class="modal-footer dark-modal-footer">
                    <button type="button" class="btn btn-secondary-custom" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary-custom" id="saveIncomeBtn">
                        <i class="fa-solid fa-floppy-disk me-1"></i> Save Income
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Confirm Delete Modal -->
<div class="modal fade" id="deleteIncomeModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content dark-modal">
            <div class="modal-header dark-modal-header">
                <h5 class="modal-title" style="color: var(--text-primary);">
                    <i class="fa-solid fa-triangle-exclamation text-danger me-2"></i>Delete Income Entry?
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <p style="color: var(--text-primary);">You're about to remove:</p>
                <div class="dark-card py-3 px-3 mb-3" style="background: rgba(255,255,255,0.03);">
                    <div class="fw-bold mb-1" id="di_title" style="color: var(--text-primary);">-</div>
                    <div class="fs-5 fw-bold text-danger" id="di_amount">৳0</div>
                </div>
                <div class="alert alert-warning py-2 small mb-0" style="background: rgba(251,191,36,0.1); border-color: rgba(251,191,36,0.25); color: #fbbf24;">
                    This will remove it from Net Profit calculations. This cannot be undone.
                </div>
            </div>
            <div class="modal-footer dark-modal-footer">
                <button type="button" class="btn btn-secondary-custom" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-danger" id="confirmDeleteIncomeBtn">
                    <i class="fa-solid fa-trash me-1"></i> Delete
                </button>
            </div>
        </div>
    </div>
</div>

<script>
let incomeModalInstance = null;
let deleteIncomeModalInstance = null;
let pendingDeleteId = null;
const CSRF_TOKEN = '<?php echo getCsrfToken(); ?>';

function openIncomeModal() {
    document.getElementById('incomeForm').reset();
    document.getElementById('oi_date').value = '<?php echo date('Y-m-d'); ?>';
    const errBox = document.getElementById('incomeFormError');
    errBox.style.display = 'none';
    errBox.textContent = '';

    if (!incomeModalInstance) {
        incomeModalInstance = new bootstrap.Modal(document.getElementById('incomeModal'));
    }
    incomeModalInstance.show();
}

document.getElementById('incomeForm').addEventListener('submit', async function (e) {
    e.preventDefault();

    const form = e.target;
    const formData = new FormData(form);
    const saveBtn = document.getElementById('saveIncomeBtn');
    const originalBtnHtml = saveBtn.innerHTML;
    const errBox = document.getElementById('incomeFormError');

    saveBtn.disabled = true;
    saveBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin me-1"></i> Saving...';
    errBox.style.display = 'none';

    try {
        const res = await fetch(`${window.BASE_URL}/ajax/other_income/save.php`, {
            method: 'POST',
            body: formData,
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        });

        const rawText = await res.text();
        let result;
        try {
            result = JSON.parse(rawText);
        } catch (parseErr) {
            errBox.textContent = 'Server returned an invalid response (HTTP ' + res.status + ').';
            errBox.style.display = 'block';
            return;
        }

        if (result.success) {
            if (incomeModalInstance) incomeModalInstance.hide();
            setTimeout(() => window.location.reload(), 400);
        } else {
            errBox.textContent = result.message || 'Failed to save income entry.';
            errBox.style.display = 'block';
        }
    } catch (err) {
        console.error('Other income save failed:', err);
        errBox.textContent = 'Something went wrong while saving. Check the browser console for details.';
        errBox.style.display = 'block';
    } finally {
        saveBtn.disabled = false;
        saveBtn.innerHTML = originalBtnHtml;
    }
});

function confirmDeleteIncome(id, title, amountDisplay) {
    pendingDeleteId = id;
    document.getElementById('di_title').textContent = title;
    document.getElementById('di_amount').textContent = amountDisplay;

    if (!deleteIncomeModalInstance) {
        deleteIncomeModalInstance = new bootstrap.Modal(document.getElementById('deleteIncomeModal'));
    }
    deleteIncomeModalInstance.show();
}

document.getElementById('confirmDeleteIncomeBtn').addEventListener('click', async function () {
    if (!pendingDeleteId) return;

    const btn = this;
    const originalHtml = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin me-1"></i> Deleting...';

    try {
        const formData = new FormData();
        formData.append('id', pendingDeleteId);
        formData.append('csrf_token', CSRF_TOKEN);

        const res = await fetch(`${window.BASE_URL}/ajax/other_income/delete.php`, {
            method: 'POST',
            body: formData,
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        });

        const rawText = await res.text();
        let result;
        try {
            result = JSON.parse(rawText);
        } catch (parseErr) {
            alert('Server returned an invalid response (HTTP ' + res.status + ').');
            return;
        }

        if (result.success) {
            window.location.reload();
        } else {
            alert(result.message || 'Failed to delete income entry.');
            btn.disabled = false;
            btn.innerHTML = originalHtml;
        }
    } catch (err) {
        console.error('Delete income failed:', err);
        alert('Something went wrong. Check console for details.');
        btn.disabled = false;
        btn.innerHTML = originalHtml;
    } finally {
        pendingDeleteId = null;
    }
});
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>