<?php
/**
 * Maruf Traders - Expenses Management Module
 * (Updated: adds Cancel/Void action for expense vouchers, admin/super-admin only.)
 */

define('APP_INIT', true);
$pageTitle = 'Operating Expenses';
$breadcrumb = 'Expenses';
$activeMenu = 'expenses';

require_once __DIR__ . '/../../includes/header.php';
requirePermission('expenses.manage');

$db = Database::getConnection();
$tenantId = TenantContext::id();

$startDate = $_GET['start_date'] ?? date('Y-m-01');
$endDate = $_GET['end_date'] ?? date('Y-m-d');
$categoryId = (int)($_GET['category_id'] ?? 0);

// Fetch categories
$catStmt = $db->prepare("SELECT * FROM expense_categories WHERE tenant_id = :tenant AND status = 'active' ORDER BY name ASC");
$catStmt->execute([':tenant' => $tenantId]);
$categories = $catStmt->fetchAll();

$sql = "SELECT e.*, c.name as category_name, u.name as creator_name 
        FROM expenses e
        JOIN expense_categories c ON e.category_id = c.id
        LEFT JOIN users u ON e.created_by = u.id
        WHERE e.tenant_id = :tenant AND e.expense_date BETWEEN :start AND :end";
$params = [':tenant' => $tenantId, ':start' => $startDate, ':end' => $endDate];

if ($categoryId) {
    $sql .= " AND e.category_id = :cid";
    $params[':cid'] = $categoryId;
}

$sql .= " ORDER BY e.id DESC";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$expenses = $stmt->fetchAll();

$totalExpense = 0;
foreach ($expenses as $exp) {
    if ($exp['status'] === 'active') {
        $totalExpense += (float)$exp['amount'];
    }
}
?>

<div class="page-header-container no-print">
    <div>
        <h2 class="page-title">Operating Expenses (ব্যবসায়িক পরিচালন খরচ)</h2>
        <div class="page-subtitle">Track shop rent, electricity, labor wages, transport, stationery and other operating expenses.</div>
    </div>
    <div class="d-flex gap-2">
        <button type="button" class="btn btn-primary-custom" onclick="openExpenseModal()">
            <i class="fa-solid fa-plus me-1"></i> Record Expense
        </button>
    </div>
</div>

<!-- Filter Bar -->
<div class="dark-card mb-4 no-print">
    <form method="GET" action="" class="row g-3 align-items-end">
        <div class="col-md-4">
            <label for="category_id" class="form-label-custom">Filter Category</label>
            <select name="category_id" id="category_id" class="form-select form-select-custom">
                <option value="">-- All Categories --</option>
                <?php foreach ($categories as $cat): ?>
                    <option value="<?php echo $cat['id']; ?>" <?php echo ($cat['id'] == $categoryId) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($cat['name']); ?>
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
            <div class="text-secondary small fw-bold text-uppercase">Expense Vouchers Count</div>
            <div class="fs-4 fw-bold" style="color: var(--text-primary);"><?php echo count($expenses); ?> Entries</div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="dark-card py-3">
            <div class="text-secondary small fw-bold text-uppercase">Total Period Operating Expenses</div>
            <div class="fs-4 fw-bold text-danger"><?php echo formatBDT($totalExpense); ?></div>
        </div>
    </div>
</div>

<!-- Expenses Table -->
<div class="dark-card">
    <div class="card-header-clean">
        <div class="card-title-clean">
            <i class="fa-solid fa-wallet text-primary-light"></i>
            <span>Expense Vouchers</span>
        </div>
    </div>

    <div class="table-responsive">
        <table class="dark-table">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Voucher Code</th>
                    <th>Category</th>
                    <th>Expense Title / Purpose</th>
                    <th>Paid To</th>
                    <th>Payment Method</th>
                    <th class="text-end">Amount (৳)</th>
                    <th class="text-center">Status</th>
                    <th class="text-end no-print">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($expenses)): ?>
                    <tr>
                        <td colspan="9" class="text-center text-muted py-4">No expenses recorded for the selected period.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($expenses as $e): ?>
                        <?php $isVoid = ($e['status'] === 'cancelled'); ?>
                        <tr>
                            <td><?php echo formatDate($e['expense_date']); ?></td>
                            <td class="fw-bold text-info"><?php echo htmlspecialchars($e['expense_code']); ?></td>
                            <td>
                                <span class="badge-custom badge-primary"><?php echo htmlspecialchars($e['category_name']); ?></span>
                            </td>
                            <td>
                                <div class="fw-bold" style="color: var(--text-primary);"><?php echo htmlspecialchars($e['title']); ?></div>
                                <?php if ($e['voucher_no']): ?>
                                    <div class="text-secondary small">Voucher: <?php echo htmlspecialchars($e['voucher_no']); ?></div>
                                <?php endif; ?>
                            </td>
                            <td><?php echo htmlspecialchars($e['paid_to'] ?: '—'); ?></td>
                            <td><?php echo htmlspecialchars($e['payment_method']); ?></td>
                            <td class="text-end fw-bold text-danger fs-6"><?php echo formatBDT($e['amount']); ?></td>
                            <td class="text-center">
                                <span class="badge-custom <?php echo ($e['status'] === 'active') ? 'badge-success' : 'badge-danger'; ?>">
                                    <?php echo ucfirst($e['status']); ?>
                                </span>
                            </td>
                            <td class="text-end no-print">
                                <?php if (!$isVoid && hasPermission('expenses.cancel')): ?>
                                    <button type="button" class="btn btn-danger-custom btn-sm" onclick="cancelExpense(<?php echo $e['id']; ?>, '<?php echo htmlspecialchars($e['expense_code'], ENT_QUOTES); ?>')" title="Cancel Expense">
                                        <i class="fa-solid fa-ban"></i>
                                    </button>
                                <?php elseif ($isVoid): ?>
                                    <span class="text-secondary small" title="<?php echo htmlspecialchars($e['cancel_reason'] ?: ''); ?>">
                                        <i class="fa-solid fa-circle-info"></i> Voided
                                    </span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Add Expense Modal -->
<div class="modal fade" id="expenseModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content dark-modal">
            <div class="modal-header dark-modal-header">
                <h5 class="modal-title" style="color: var(--text-primary);">Record Operating Expense</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="expenseForm">
                <?php echo csrfField(); ?>

                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label for="exp_category_id" class="form-label-custom">Expense Category <span class="required">*</span></label>
                        <select class="form-select form-select-custom" id="exp_category_id" name="category_id" required>
                            <option value="">-- Select Category --</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?php echo $cat['id']; ?>"><?php echo htmlspecialchars($cat['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label for="exp_title" class="form-label-custom">Expense Purpose / Title <span class="required">*</span></label>
                        <input type="text" class="form-control form-control-custom" id="exp_title" name="title" required placeholder="e.g. Shop monthly godown rent for August">
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label for="exp_date" class="form-label-custom">Expense Date <span class="required">*</span></label>
                            <input type="date" class="form-control form-control-custom" id="exp_date" name="expense_date" value="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label for="exp_amount" class="form-label-custom">Amount (৳) <span class="required">*</span></label>
                            <input type="number" step="0.01" class="form-control form-control-custom" id="exp_amount" name="amount" min="1" required placeholder="0.00">
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label for="exp_pmethod" class="form-label-custom">Payment Method</label>
                            <select class="form-select form-select-custom" id="exp_pmethod" name="payment_method">
                                <option value="Cash">Cash</option>
                                <option value="bKash">bKash</option>
                                <option value="Nagad">Nagad</option>
                                <option value="Bank">Bank</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="exp_paid_to" class="form-label-custom">Paid To / Recipient</label>
                            <input type="text" class="form-control form-control-custom" id="exp_paid_to" name="paid_to" placeholder="e.g. Landlord / Shop Staff">
                        </div>
                    </div>

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="exp_voucher" class="form-label-custom">Voucher / Bill No</label>
                            <input type="text" class="form-control form-control-custom" id="exp_voucher" name="voucher_no" placeholder="e.g. V-102">
                        </div>
                        <div class="col-md-6">
                            <label for="exp_notes" class="form-label-custom">Notes</label>
                            <input type="text" class="form-control form-control-custom" id="exp_notes" name="notes" placeholder="Optional notes">
                        </div>
                    </div>
                </div>

                <div class="modal-footer dark-modal-footer">
                    <button type="button" class="btn btn-secondary-custom" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary-custom" id="saveExpBtn">
                        <i class="fa-solid fa-floppy-disk me-1"></i> Save Expense
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
let expModalInstance = null;

function openExpenseModal() {
    document.getElementById('expenseForm').reset();
    if (!expModalInstance) {
        expModalInstance = new bootstrap.Modal(document.getElementById('expenseModal'));
    }
    expModalInstance.show();
}

/**
 * Direct fetch()-based AJAX submit handler.
 * setupAjaxForm() was not preventing the browser's default form submission
 * (GET request, page navigation), so expenses were never actually saved.
 */
document.getElementById('expenseForm').addEventListener('submit', async function (e) {
    e.preventDefault();

    const form = e.target;
    const formData = new FormData(form);
    const saveBtn = document.getElementById('saveExpBtn');
    const originalBtnHtml = saveBtn.innerHTML;

    saveBtn.disabled = true;
    saveBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin me-1"></i> Saving...';

    try {
        const res = await fetch(`${window.BASE_URL}/ajax/expenses/save.php`, {
            method: 'POST',
            body: formData,
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        });

        const result = await res.json();

        if (result.success) {
            if (expModalInstance) expModalInstance.hide();
            setTimeout(() => window.location.reload(), 500);
        } else {
            alert(result.message || 'Failed to save expense.');
        }
    } catch (err) {
        console.error('Expense save failed:', err);
        alert('Something went wrong while saving. Check the browser console for details.');
    } finally {
        saveBtn.disabled = false;
        saveBtn.innerHTML = originalBtnHtml;
    }
});

/**
 * Cancel Expense -- mirrors cancelSale() from the Sales module.
 */
async function cancelExpense(id, expenseCode) {
    const isLight = document.documentElement.getAttribute('data-theme') === 'light';
    const swalBg = isLight ? '#FFFFFF' : '#151F36';
    const swalColor = isLight ? '#0F172A' : '#F8FAFC';

    const { value: reason } = await Swal.fire({
        title: `Cancel Expense: ${expenseCode}?`,
        text: 'This will exclude the voucher from profit reports.',
        input: 'text',
        inputPlaceholder: 'Enter cancellation reason...',
        inputValidator: (value) => {
            if (!value) return 'A cancellation reason is required!';
        },
        showCancelButton: true,
        confirmButtonColor: '#EF4444',
        cancelButtonColor: '#475569',
        confirmButtonText: 'Yes, Cancel Expense',
        background: swalBg,
        color: swalColor
    });

    if (reason) {
        try {
            const formData = new FormData();
            formData.append('id', id);
            formData.append('reason', reason);
            formData.append('csrf_token', getCsrfToken());

            const res = await apiRequest(`${window.BASE_URL}/ajax/expenses/cancel.php`, {
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