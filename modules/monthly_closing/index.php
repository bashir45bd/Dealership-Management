<?php
/**
 * Maruf Traders - Monthly Closing & Period Lock Manager
 */

define('APP_INIT', true);
$pageTitle = 'Monthly Closing';
$breadcrumb = 'Monthly Closing';
$activeMenu = 'closing';

require_once __DIR__ . '/../../includes/header.php';
requirePermission('closing.manage');

$db = Database::getConnection();
$tenantId = TenantContext::id();

// Fetch all monthly closing records
$stmt = $db->prepare("SELECT mc.*, u1.name as closer_name, u2.name as reopener_name 
                    FROM monthly_closings mc
                    LEFT JOIN users u1 ON mc.closed_by = u1.id
                    LEFT JOIN users u2 ON mc.reopened_by = u2.id
                    WHERE mc.tenant_id = :tenant
                    ORDER BY mc.closing_year DESC, mc.closing_month DESC");
$stmt->execute([':tenant' => $tenantId]);
$closings = $stmt->fetchAll();
?>

<div class="page-header-container no-print">
    <div>
        <h2 class="page-title">Monthly Closing & Period Locking (মাসিক ক্লোজিং ও হিসাব লক)</h2>
        <div class="page-subtitle">Lock accounting months to prevent accidental modifications to finalized sales, stock, expenses, and ledger entries.</div>
    </div>
    <div class="d-flex gap-2">
        <button type="button" class="btn btn-danger-custom" onclick="openCloseMonthModal()">
            <i class="fa-solid fa-lock me-1"></i> Close / Lock Month
        </button>
    </div>
</div>

<div class="row g-4 mb-4">
    <div class="col-lg-12">
        <div class="dark-card">
            <div class="card-header-clean">
                <div class="card-title-clean">
                    <i class="fa-solid fa-calendar-check text-primary-light"></i>
                    <span>Closed & Locked Financial Periods</span>
                </div>
            </div>

            <div class="table-responsive">
                <table class="dark-table">
                    <thead>
                        <tr>
                            <th>Accounting Period</th>
                            <th>Status</th>
                            <th>Closed By</th>
                            <th>Closed Date & Time</th>
                            <th>Reopened By</th>
                            <th>Closing Notes</th>
                            <th class="text-end no-print">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($closings)): ?>
                            <tr>
                                <td colspan="7" class="text-center text-muted py-4">No monthly closing records yet. All months are currently OPEN.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($closings as $c): ?>
                                <?php 
                                    $monthName = date('F Y', mktime(0, 0, 0, (int)$c['closing_month'], 10, (int)$c['closing_year']));
                                    $isClosed = ($c['status'] === 'closed');
                                ?>
                                <tr>
                                    <td class="fw-bold text-white fs-6">
                                        <i class="fa-solid <?php echo $isClosed ? 'fa-lock text-danger' : 'fa-lock-open text-success'; ?> me-2"></i>
                                        <?php echo $monthName; ?>
                                    </td>
                                    <td>
                                        <span class="badge-custom <?php echo $isClosed ? 'badge-danger' : 'badge-success'; ?>">
                                            <?php echo strtoupper($c['status']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo htmlspecialchars($c['closer_name'] ?: 'System'); ?></td>
                                    <td><?php echo formatDateTime($c['closed_at']); ?></td>
                                    <td><?php echo $c['reopened_by'] ? htmlspecialchars($c['reopener_name'] . ' (' . formatDateTime($c['reopened_at']) . ')') : '—'; ?></td>
                                    <td class="text-secondary small"><?php echo htmlspecialchars($c['closing_notes'] ?: '—'); ?></td>
                                    <td class="text-end no-print">
                                        <?php if ($isClosed): ?>
                                            <button type="button" class="btn btn-secondary-custom btn-sm" onclick="reopenMonth(<?php echo $c['closing_month']; ?>, <?php echo $c['closing_year']; ?>)">
                                                <i class="fa-solid fa-lock-open text-success me-1"></i> Reopen
                                            </button>
                                        <?php else: ?>
                                            <button type="button" class="btn btn-danger-custom btn-sm" onclick="quickCloseMonth(<?php echo $c['closing_month']; ?>, <?php echo $c['closing_year']; ?>)">
                                                <i class="fa-solid fa-lock me-1"></i> Lock Again
                                            </button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Close Month Modal -->
<div class="modal fade" id="closeMonthModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content dark-modal">
            <div class="modal-header dark-modal-header">
                <h5 class="modal-title text-white">Close Financial Month</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="closeMonthForm">
                <?php echo csrfField(); ?>

                <div class="modal-body p-4">
                    <div class="alert alert-warning py-2 small" style="background: rgba(245,158,11,0.15); border-color: rgba(245,158,11,0.3); color: var(--warning);">
                        <i class="fa-solid fa-triangle-exclamation me-1"></i> Once locked, no new sales, collections, expenses, receives or adjustments can be recorded for this month on the PHP backend.
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label for="close_month" class="form-label-custom">Month <span class="required">*</span></label>
                            <select class="form-select form-select-custom" id="close_month" name="month" required>
                                <?php for ($m = 1; $m <= 12; $m++): ?>
                                    <option value="<?php echo $m; ?>" <?php echo ($m == (int)date('n')) ? 'selected' : ''; ?>>
                                        <?php echo date('F', mktime(0, 0, 0, $m, 10)); ?>
                                    </option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="close_year" class="form-label-custom">Year <span class="required">*</span></label>
                            <input type="number" class="form-control form-control-custom" id="close_year" name="year" value="<?php echo date('Y'); ?>" required>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="close_notes" class="form-label-custom">Closing Verification Notes</label>
                        <textarea class="form-control form-control-custom" id="close_notes" name="notes" rows="2" placeholder="e.g. Audited and verified all sales and collection ledger entries."></textarea>
                    </div>
                </div>

                <div class="modal-footer dark-modal-footer">
                    <button type="button" class="btn btn-secondary-custom" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger-custom" id="submitCloseBtn">
                        <i class="fa-solid fa-lock me-1"></i> Lock This Month
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
let closeMonthModalInstance = null;

// Embedded server-side. JS helpers like getCsrfToken() / confirmAction()
// referenced elsewhere on this page weren't reliably defined, so this page
// now uses its own token constant and SweetAlert2 (Swal), which is already
// confirmed available on this page (used by reopenMonth's dialog).
const CSRF_TOKEN = '<?php echo getCsrfToken(); ?>';

function openCloseMonthModal() {
    document.getElementById('closeMonthForm').reset();
    if (!closeMonthModalInstance) {
        closeMonthModalInstance = new bootstrap.Modal(document.getElementById('closeMonthModal'));
    }
    closeMonthModalInstance.show();
}

function notify(type, message) {
    if (typeof showToast === 'function') {
        showToast(type, message);
    } else {
        alert(message);
    }
}

async function reopenMonth(month, year) {
    const { value: reason } = await Swal.fire({
        title: `Reopen Month: ${month}/${year}?`,
        text: 'Reopening will allow authorized users to add or edit transactions for this period.',
        input: 'text',
        inputPlaceholder: 'Reason for reopening...',
        inputValidator: (v) => !v && 'Reopening reason is mandatory!',
        showCancelButton: true,
        confirmButtonColor: '#22C55E',
        cancelButtonColor: '#475569',
        confirmButtonText: 'Yes, Reopen Month',
        background: '#151F36',
        color: '#F8FAFC'
    });

    if (reason) {
        try {
            const formData = new FormData();
            formData.append('month', month);
            formData.append('year', year);
            formData.append('reason', reason);
            formData.append('csrf_token', CSRF_TOKEN);

            const res = await fetch(`${window.BASE_URL}/ajax/monthly_closing/reopen.php`, {
                method: 'POST',
                body: formData,
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            });
            const result = await res.json();

            if (result.success) {
                notify('success', result.message);
                setTimeout(() => window.location.reload(), 1000);
            } else {
                notify('error', result.message || 'Failed to reopen month.');
            }
        } catch (e) {
            console.error(e);
            alert('Something went wrong. Check console for details.');
        }
    }
}

async function quickCloseMonth(month, year) {
    const result = await Swal.fire({
        title: `Lock Month: ${month}/${year}?`,
        text: 'Are you sure you want to re-lock this period?',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#EF4444',
        cancelButtonColor: '#475569',
        confirmButtonText: 'Yes, Lock Period',
        background: '#151F36',
        color: '#F8FAFC'
    });

    if (!result.isConfirmed) return;

    try {
        const formData = new FormData();
        formData.append('month', month);
        formData.append('year', year);
        formData.append('csrf_token', CSRF_TOKEN);

        const res = await fetch(`${window.BASE_URL}/ajax/monthly_closing/close.php`, {
            method: 'POST',
            body: formData,
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        });
        const closeResult = await res.json();

        if (closeResult.success) {
            notify('success', closeResult.message);
            setTimeout(() => window.location.reload(), 1000);
        } else {
            notify('error', closeResult.message || 'Failed to lock month.');
        }
    } catch (e) {
        console.error(e);
        alert('Something went wrong. Check console for details.');
    }
}

/**
 * Direct fetch()-based AJAX submit handler.
 * setupAjaxForm() was not preventing the browser's default form submission
 * (GET request, page navigation), so month closures were never actually saved.
 */
document.getElementById('closeMonthForm').addEventListener('submit', async function (e) {
    e.preventDefault();

    const form = e.target;
    const formData = new FormData(form);
    const saveBtn = document.getElementById('submitCloseBtn');
    const originalBtnHtml = saveBtn.innerHTML;

    saveBtn.disabled = true;
    saveBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin me-1"></i> Locking...';

    try {
        const res = await fetch(`${window.BASE_URL}/ajax/monthly_closing/close.php`, {
            method: 'POST',
            body: formData,
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        });

        const result = await res.json();

        if (result.success) {
            if (closeMonthModalInstance) closeMonthModalInstance.hide();
            setTimeout(() => window.location.reload(), 500);
        } else {
            alert(result.message || 'Failed to lock month.');
        }
    } catch (err) {
        console.error('Month close failed:', err);
        alert('Something went wrong while saving. Check the browser console for details.');
    } finally {
        saveBtn.disabled = false;
        saveBtn.innerHTML = originalBtnHtml;
    }
});
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>