<?php
/**
 * Maruf Traders - Company Period Commissions (Quarterly / Yearly, per-bag)
 *
 * These are separate from the monthly proportional target commission engine.
 * A company may pay a per-bag bonus/commission over a custom period
 * (e.g. "every 3 months" or "once a year") at an irregular, unknown date.
 * Records here sit as PENDING (never affecting Net Profit) until the user
 * clicks "Mark as Received" - at that point the amount is added to the
 * Profit & Loss statement for the month in which it was marked received.
 *
 * Rules enforced (server-side in save.php, mirrored client-side here):
 *   - Quarterly period duration: max 3 months
 *   - Yearly period duration:    max 12 months
 *   - No two periods of the SAME commission_type for the SAME company
 *     may cover overlapping dates (prevents duplicate/overlapping claims
 *     for the same month(s)/year).
 *   - A PENDING period can be edited (company/type/dates/rate/label/notes).
 *     A RECEIVED period is frozen and can never be edited (matches the
 *     existing rule that received rows are never recalculated).
 */

define('APP_INIT', true);
$pageTitle = 'Period Commissions (3-Month / Yearly)';
$breadcrumb = 'Period Commissions';
$activeMenu = 'commission_periods';

require_once __DIR__ . '/../../includes/header.php';
requirePermission('commission_periods.manage');

$db = Database::getConnection();

$companyFilter = (int)($_GET['company_id'] ?? 0);
$statusFilter = trim($_GET['status'] ?? '');

$cStmt = $db->query("SELECT id, name FROM companies WHERE status = 'active' ORDER BY name ASC");
$allCompanies = $cStmt->fetchAll();

// Fetch all period commission records
$sql = "SELECT cpc.*, c.name as company_name, c.company_code
        FROM company_period_commissions cpc
        JOIN companies c ON cpc.company_id = c.id
        WHERE 1=1";
$params = [];

if ($companyFilter) {
    $sql .= " AND cpc.company_id = :cid";
    $params[':cid'] = $companyFilter;
}
if ($statusFilter === 'pending' || $statusFilter === 'received') {
    $sql .= " AND cpc.status = :status";
    $params[':status'] = $statusFilter;
}

$sql .= " ORDER BY cpc.status ASC, cpc.end_date DESC, cpc.id DESC";
$stmt = $db->prepare($sql);
$stmt->execute($params);
$periods = $stmt->fetchAll();

/*
 * Live-recalculate bags/amount for all still-PENDING records so the
 * displayed numbers always reflect current sales data (same pattern as
 * recalculateTargetAndCommission() for the monthly target engine).
 * RECEIVED records are frozen and never recalculated here.
 */
foreach ($periods as &$p) {
    if ($p['status'] === 'pending') {
        $bagsStmt = $db->prepare(
            "SELECT COALESCE(SUM(si.quantity), 0) AS total_qty
             FROM sale_items si
             JOIN sales s ON si.sale_id = s.id
             WHERE si.company_id = :cid
               AND s.status = 'active'
               AND s.sale_date BETWEEN :start AND :end"
        );
        $bagsStmt->execute([
            ':cid'   => $p['company_id'],
            ':start' => $p['start_date'],
            ':end'   => $p['end_date']
        ]);
        $liveBags = (int)$bagsStmt->fetchColumn();
        $liveAmount = round($liveBags * (float)$p['rate_per_bag'], 2);

        if ($liveBags != (int)$p['total_bags'] || abs($liveAmount - (float)$p['total_amount']) > 0.001) {
            $uStmt = $db->prepare("UPDATE company_period_commissions SET total_bags = :bags, total_amount = :amt WHERE id = :id");
            $uStmt->execute([':bags' => $liveBags, ':amt' => $liveAmount, ':id' => $p['id']]);
        }
        $p['total_bags'] = $liveBags;
        $p['total_amount'] = $liveAmount;
    }
}
unset($p);

$totalPending = 0;
$totalReceived = 0;
foreach ($periods as $p) {
    if ($p['status'] === 'pending') {
        $totalPending += (float)$p['total_amount'];
    } else {
        $totalReceived += (float)$p['total_amount'];
    }
}

// Lightweight, UNFILTERED list of every existing period's date range,
// used purely for client-side duration/overlap validation in the modal.
// (The authoritative check always happens server-side in save.php.)
$allPeriodsForJsStmt = $db->query(
    "SELECT id, company_id, commission_type, start_date, end_date, period_label
     FROM company_period_commissions"
);
$allPeriodsForJs = $allPeriodsForJsStmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="page-header-container no-print">
    <div>
        <h2 class="page-title">Period Commissions (৩-মাস ও বার্ষিক প্রতি বস্তা কমিশন)</h2>
        <div class="page-subtitle">Track irregular company commission payouts (quarterly/yearly per-bag bonus). These do NOT affect Net Profit until marked as received.</div>
    </div>
    <div class="d-flex gap-2">
        <button type="button" class="btn btn-primary-custom" onclick="openPeriodModal()">
            <i class="fa-solid fa-plus me-1"></i> New Period Commission
        </button>
    </div>
</div>

<!-- Filter Bar -->
<div class="dark-card mb-4 no-print">
    <form method="GET" action="" class="row g-3 align-items-end">
        <div class="col-md-5">
            <label for="company_id" class="form-label-custom">Filter Company</label>
            <select name="company_id" id="company_id" class="form-select form-select-custom">
                <option value="">-- All Companies --</option>
                <?php foreach ($allCompanies as $comp): ?>
                    <option value="<?php echo $comp['id']; ?>" <?php echo ($comp['id'] == $companyFilter) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($comp['name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-5">
            <label for="status" class="form-label-custom">Filter Status</label>
            <select name="status" id="status" class="form-select form-select-custom">
                <option value="">-- All Statuses --</option>
                <option value="pending" <?php echo ($statusFilter === 'pending') ? 'selected' : ''; ?>>Pending</option>
                <option value="received" <?php echo ($statusFilter === 'received') ? 'selected' : ''; ?>>Received</option>
            </select>
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
            <div class="text-secondary small fw-bold text-uppercase">Total Pending (Not in Profit Yet)</div>
            <div class="fs-4 fw-bold text-warning"><?php echo formatBDT($totalPending); ?></div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="dark-card py-3 text-center">
            <div class="text-secondary small fw-bold text-uppercase">Total Received (Already in Profit)</div>
            <div class="fs-4 fw-bold text-success"><?php echo formatBDT($totalReceived); ?></div>
        </div>
    </div>
</div>

<!-- Periods Table -->
<div class="dark-card">
    <div class="card-header-clean">
        <div class="card-title-clean">
            <i class="fa-solid fa-sack-dollar text-primary-light"></i>
            <span>Period Commission Records</span>
        </div>
    </div>

    <div class="table-responsive">
        <table class="dark-table">
            <thead>
                <tr>
                    <th>Company</th>
                    <th>Type</th>
                    <th>Period</th>
                    <th class="text-end">Rate/Bag</th>
                    <th class="text-end">Bags Sold</th>
                    <th class="text-end">Amount</th>
                    <th class="text-center">Status</th>
                    <th class="text-end no-print">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($periods)): ?>
                    <tr>
                        <td colspan="8" class="text-center text-muted py-4">No period commission records yet. Click "New Period Commission" to add one.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($periods as $p): ?>
                        <?php $isPending = ($p['status'] === 'pending'); ?>
                        <tr>
                            <td class="fw-bold" style="color: var(--text-primary);"><?php echo htmlspecialchars($p['company_name']); ?></td>
                            <td>
                                <span class="badge-custom <?php echo ($p['commission_type'] === 'yearly') ? 'badge-cyan' : 'badge-primary'; ?>">
                                    <?php echo ($p['commission_type'] === 'yearly') ? 'Yearly' : '3-Month'; ?>
                                </span>
                            </td>
                            <td>
                                <div style="color: var(--text-primary);"><?php echo htmlspecialchars($p['period_label']); ?></div>
                                <div class="text-secondary small"><?php echo formatDate($p['start_date']); ?> — <?php echo formatDate($p['end_date']); ?></div>
                            </td>
                            <td class="text-end"><?php echo formatBDT($p['rate_per_bag']); ?></td>
                            <td class="text-end fw-bold text-cyan"><?php echo number_format($p['total_bags']); ?> Bags</td>
                            <td class="text-end fw-bold fs-6 <?php echo $isPending ? 'text-warning' : 'text-success'; ?>"><?php echo formatBDT($p['total_amount']); ?></td>
                            <td class="text-center">
                                <?php if ($isPending): ?>
                                    <span class="badge-custom badge-warning">Pending</span>
                                <?php else: ?>
                                    <span class="badge-custom badge-success">Received</span>
                                    <div class="text-secondary small mt-1"><?php echo formatDate($p['received_date']); ?></div>
                                <?php endif; ?>
                            </td>
                            <td class="text-end no-print">
                                <?php if ($isPending): ?>
                                    <div class="d-flex gap-1 justify-content-end">
                                        <button type="button" class="btn btn-secondary-custom btn-sm"
                                            onclick='openPeriodModal(<?php echo json_encode([
                                                "id"               => (int)$p["id"],
                                                "company_id"       => (int)$p["company_id"],
                                                "commission_type"  => $p["commission_type"],
                                                "start_date"       => $p["start_date"],
                                                "end_date"         => $p["end_date"],
                                                "rate_per_bag"     => $p["rate_per_bag"],
                                                "period_label"     => $p["period_label"],
                                                "notes"            => $p["notes"],
                                            ], JSON_HEX_APOS | JSON_HEX_QUOT); ?>)'>
                                            <i class="fa-solid fa-pen me-1"></i> Edit
                                        </button>
                                        <button type="button" class="btn btn-primary-custom btn-sm"
                                            onclick="markReceived(<?php echo $p['id']; ?>, '<?php echo htmlspecialchars($p['company_name'], ENT_QUOTES); ?>', '<?php echo htmlspecialchars($p['period_label'], ENT_QUOTES); ?>', '<?php echo $p['commission_type']; ?>', '<?php echo htmlspecialchars(formatBDT($p['total_amount']), ENT_QUOTES); ?>', '<?php echo number_format($p['total_bags']); ?>')">
                                            <i class="fa-solid fa-check me-1"></i> Mark as Received
                                        </button>
                                    </div>
                                <?php else: ?>
                                    <span class="text-muted small">Locked</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- New / Edit Period Commission Modal -->
<div class="modal fade" id="periodModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content dark-modal">
            <div class="modal-header dark-modal-header">
                <h5 class="modal-title" id="periodModalLabel" style="color: var(--text-primary);">New Period Commission</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="periodForm">
                <?php echo csrfField(); ?>
                <input type="hidden" id="pc_id" name="id" value="">

                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label for="pc_company_id" class="form-label-custom">Company / Supplier <span class="required">*</span></label>
                        <select class="form-select form-select-custom" id="pc_company_id" name="company_id" required>
                            <option value="">-- Select Company --</option>
                            <?php foreach ($allCompanies as $comp): ?>
                                <option value="<?php echo $comp['id']; ?>"><?php echo htmlspecialchars($comp['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label for="pc_type" class="form-label-custom">Commission Type <span class="required">*</span></label>
                        <select class="form-select form-select-custom" id="pc_type" name="commission_type" required>
                            <option value="quarterly">3-Month Commission</option>
                            <option value="yearly">Yearly Commission</option>
                        </select>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label for="pc_start" class="form-label-custom">Period Start Date <span class="required">*</span></label>
                            <input type="date" class="form-control form-control-custom" id="pc_start" name="start_date" required>
                        </div>
                        <div class="col-md-6">
                            <label for="pc_end" class="form-label-custom">Period End Date <span class="required">*</span></label>
                            <input type="date" class="form-control form-control-custom" id="pc_end" name="end_date" required>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="pc_rate" class="form-label-custom">Rate per Bag (৳) <span class="required">*</span></label>
                        <input type="number" step="0.01" class="form-control form-control-custom" id="pc_rate" name="rate_per_bag" min="0.01" required placeholder="e.g. 2.00">
                        <small class="text-secondary" style="font-size: 0.75rem;">This rate can be different every year/period — set it fresh each time you create a new period.</small>
                    </div>

                    <div class="mb-3">
                        <label for="pc_label" class="form-label-custom">Period Label</label>
                        <input type="text" class="form-control form-control-custom" id="pc_label" name="period_label" placeholder="e.g. Year 2026, or Q1 2026 — leave blank to auto-generate">
                    </div>

                    <div class="mb-3">
                        <label for="pc_notes" class="form-label-custom">Notes</label>
                        <input type="text" class="form-control form-control-custom" id="pc_notes" name="notes" placeholder="Optional notes">
                    </div>

                    <div class="alert alert-danger py-2 small mb-3" id="periodFormError" style="display: none;"></div>

                    <div class="alert alert-info py-2 small" style="background: rgba(34,211,238,0.1); border-color: rgba(34,211,238,0.2); color: var(--cyan);">
                        <i class="fa-solid fa-info-circle me-1"></i> Bags sold and total amount are calculated automatically from your Sales records for this company within the selected dates, and stay updated until you mark it as received.
                        <br><br>
                        <i class="fa-solid fa-circle-exclamation me-1"></i> 3-Month commission periods cannot exceed <strong>3 months</strong>, and Yearly periods cannot exceed <strong>12 months</strong>. A company also cannot have two periods of the same type covering overlapping dates.
                        <br><br>
                        <i class="fa-solid fa-pen me-1"></i> Only <strong>Pending</strong> periods can be edited. Once a period is marked as Received it is locked and frozen.
                    </div>
                </div>

                <div class="modal-footer dark-modal-footer">
                    <button type="button" class="btn btn-secondary-custom" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary-custom" id="savePeriodBtn">
                        <i class="fa-solid fa-floppy-disk me-1"></i> Save Period Commission
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Confirm Receive Modal (replaces native confirm()) -->
<div class="modal fade" id="receiveConfirmModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content dark-modal">
            <div class="modal-header dark-modal-header">
                <h5 class="modal-title" style="color: var(--text-primary);">
                    <i class="fa-solid fa-circle-check text-success me-2"></i>Confirm Commission Receipt
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <div class="text-center mb-4">
                    <div class="text-secondary small text-uppercase fw-bold mb-1">Amount to be Added to Net Profit</div>
                    <div class="fs-2 fw-bold text-success" id="rc_amount">৳0</div>
                </div>

                <div class="dark-card py-3 px-3 mb-3" style="background: rgba(255,255,255,0.03);">
                    <div class="d-flex justify-content-between py-1">
                        <span class="text-secondary small">Company</span>
                        <span class="fw-bold" id="rc_company" style="color: var(--text-primary);">-</span>
                    </div>
                    <div class="d-flex justify-content-between py-1">
                        <span class="text-secondary small">Type</span>
                        <span class="fw-bold" id="rc_type" style="color: var(--text-primary);">-</span>
                    </div>
                    <div class="d-flex justify-content-between py-1">
                        <span class="text-secondary small">Period</span>
                        <span class="fw-bold text-end" id="rc_period" style="color: var(--text-primary);">-</span>
                    </div>
                    <div class="d-flex justify-content-between py-1">
                        <span class="text-secondary small">Bags Sold</span>
                        <span class="fw-bold" id="rc_bags" style="color: var(--text-primary);">-</span>
                    </div>
                </div>

                <div class="alert alert-warning py-2 small mb-0" style="background: rgba(251,191,36,0.1); border-color: rgba(251,191,36,0.25); color: #fbbf24;">
                    <i class="fa-solid fa-triangle-exclamation me-1"></i>
                    This will be recorded as received on <strong id="rc_today">today</strong> and added to that period's Net Profit. This action cannot be undone.
                </div>
            </div>
            <div class="modal-footer dark-modal-footer">
                <button type="button" class="btn btn-secondary-custom" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary-custom" id="confirmReceiveBtn">
                    <i class="fa-solid fa-check me-1"></i> Confirm &amp; Add to Net Profit
                </button>
            </div>
        </div>
    </div>
</div>

<script>
let periodModalInstance = null;
let receiveConfirmModalInstance = null;
let pendingReceiveId = null;
const CSRF_TOKEN = '<?php echo getCsrfToken(); ?>';

// All existing periods, used for client-side duration/overlap validation.
// (Authoritative validation always happens server-side in save.php.)
const EXISTING_PERIODS = <?php echo json_encode($allPeriodsForJs); ?>;

/**
 * Opens the period modal.
 * Call with no argument for "New Period Commission" (create mode).
 * Call with a record object for "Edit Period Commission" (edit mode) —
 * the record must include: id, company_id, commission_type, start_date,
 * end_date, rate_per_bag, period_label, notes.
 */
function openPeriodModal(record) {
    const form = document.getElementById('periodForm');
    form.reset();

    const errBox = document.getElementById('periodFormError');
    errBox.style.display = 'none';
    errBox.textContent = '';
    document.getElementById('savePeriodBtn').disabled = false;

    const titleEl = document.getElementById('periodModalLabel');
    const saveBtn = document.getElementById('savePeriodBtn');

    if (record && record.id) {
        // Edit mode
        document.getElementById('pc_id').value = record.id;
        document.getElementById('pc_company_id').value = record.company_id;
        document.getElementById('pc_type').value = record.commission_type;
        document.getElementById('pc_start').value = record.start_date;
        document.getElementById('pc_end').value = record.end_date;
        document.getElementById('pc_rate').value = record.rate_per_bag;
        document.getElementById('pc_label').value = record.period_label || '';
        document.getElementById('pc_notes').value = record.notes || '';

        titleEl.textContent = 'Edit Period Commission';
        saveBtn.innerHTML = '<i class="fa-solid fa-floppy-disk me-1"></i> Update Period Commission';
    } else {
        // Create mode
        document.getElementById('pc_id').value = '';
        titleEl.textContent = 'New Period Commission';
        saveBtn.innerHTML = '<i class="fa-solid fa-floppy-disk me-1"></i> Save Period Commission';
    }

    if (!periodModalInstance) {
        periodModalInstance = new bootstrap.Modal(document.getElementById('periodModal'));
    }
    periodModalInstance.show();
}

function validatePeriodForm() {
    const editingId = document.getElementById('pc_id').value;
    const companyId = document.getElementById('pc_company_id').value;
    const type = document.getElementById('pc_type').value;
    const start = document.getElementById('pc_start').value;
    const end = document.getElementById('pc_end').value;
    const errBox = document.getElementById('periodFormError');
    const saveBtn = document.getElementById('savePeriodBtn');

    errBox.style.display = 'none';
    errBox.textContent = '';
    saveBtn.disabled = false;

    if (!companyId || !type || !start || !end) {
        return;
    }

    const startD = new Date(start + 'T00:00:00');
    const endD = new Date(end + 'T00:00:00');

    if (endD < startD) {
        errBox.textContent = 'End date cannot be before start date.';
        errBox.style.display = 'block';
        saveBtn.disabled = true;
        return;
    }

    // Duration check: quarterly max 3 months, yearly max 12 months
    const maxMonths = (type === 'yearly') ? 12 : 3;
    const maxEnd = new Date(startD);
    maxEnd.setMonth(maxEnd.getMonth() + maxMonths);
    maxEnd.setDate(maxEnd.getDate() - 1);

    if (endD > maxEnd) {
        const label = (type === 'yearly') ? '12 months (1 year)' : '3 months';
        errBox.textContent = `The selected period exceeds the maximum allowed duration for ${type === 'yearly' ? 'Yearly' : '3-Month'} commission (${label}). Please choose a shorter date range.`;
        errBox.style.display = 'block';
        saveBtn.disabled = true;
        return;
    }

    // Overlap check: same company + same commission_type + overlapping dates.
    // When editing, the record being edited is excluded from this check
    // against itself (matched by id).
    const overlap = EXISTING_PERIODS.some(p => {
        if (editingId && String(p.id) === String(editingId)) {
            return false;
        }
        if (String(p.company_id) !== String(companyId) || p.commission_type !== type) {
            return false;
        }
        const pStart = new Date(p.start_date + 'T00:00:00');
        const pEnd = new Date(p.end_date + 'T00:00:00');
        return !(endD < pStart || startD > pEnd);
    });

    if (overlap) {
        errBox.textContent = `An existing ${type === 'yearly' ? 'Yearly' : '3-Month'} commission period for this company already covers part of this date range. Each period must cover a distinct, non-overlapping range.`;
        errBox.style.display = 'block';
        saveBtn.disabled = true;
    }
}

['pc_company_id', 'pc_type', 'pc_start', 'pc_end'].forEach(id => {
    document.getElementById(id).addEventListener('change', validatePeriodForm);
});

document.getElementById('periodForm').addEventListener('submit', async function (e) {
    e.preventDefault();

    // Final guard — do not submit if client-side validation currently flags an error
    validatePeriodForm();
    if (document.getElementById('savePeriodBtn').disabled) {
        return;
    }

    const form = e.target;
    const formData = new FormData(form);
    const isEditing = !!document.getElementById('pc_id').value;
    const saveBtn = document.getElementById('savePeriodBtn');
    const originalBtnHtml = saveBtn.innerHTML;

    saveBtn.disabled = true;
    saveBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin me-1"></i> Saving...';

    try {
        const res = await fetch(`${window.BASE_URL}/ajax/commission_periods/save.php`, {
            method: 'POST',
            body: formData,
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        });

        const rawText = await res.text();
        let result;
        try {
            result = JSON.parse(rawText);
        } catch (parseErr) {
            alert('Server returned an invalid response (HTTP ' + res.status + '):\n\n' + rawText.substring(0, 500));
            return;
        }

        if (result.success) {
            if (periodModalInstance) periodModalInstance.hide();
            setTimeout(() => window.location.reload(), 500);
        } else {
            const errBox = document.getElementById('periodFormError');
            errBox.textContent = result.message || (isEditing ? 'Failed to update period commission.' : 'Failed to save period commission.');
            errBox.style.display = 'block';
        }
    } catch (err) {
        console.error('Period commission save failed:', err);
        const errBox = document.getElementById('periodFormError');
        errBox.textContent = 'Something went wrong while saving. Check the browser console for details.';
        errBox.style.display = 'block';
    } finally {
        saveBtn.disabled = false;
        saveBtn.innerHTML = originalBtnHtml;
    }
});

function markReceived(id, companyName, periodLabel, type, amountDisplay, bagsDisplay) {
    pendingReceiveId = id;

    document.getElementById('rc_company').textContent = companyName;
    document.getElementById('rc_type').textContent = (type === 'yearly') ? 'Yearly' : '3-Month';
    document.getElementById('rc_period').textContent = periodLabel;
    document.getElementById('rc_bags').textContent = bagsDisplay + ' Bags';
    document.getElementById('rc_amount').textContent = amountDisplay;
    document.getElementById('rc_today').textContent = new Date().toLocaleDateString('en-GB', {
        day: 'numeric', month: 'long', year: 'numeric'
    });

    if (!receiveConfirmModalInstance) {
        receiveConfirmModalInstance = new bootstrap.Modal(document.getElementById('receiveConfirmModal'));
    }
    receiveConfirmModalInstance.show();
}

document.getElementById('confirmReceiveBtn').addEventListener('click', async function () {
    if (!pendingReceiveId) return;

    const btn = this;
    const originalHtml = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin me-1"></i> Processing...';

    try {
        const formData = new FormData();
        formData.append('id', pendingReceiveId);
        formData.append('csrf_token', CSRF_TOKEN);

        const res = await fetch(`${window.BASE_URL}/ajax/commission_periods/receive.php`, {
            method: 'POST',
            body: formData,
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        });

        const rawText = await res.text();
        let result;
        try {
            result = JSON.parse(rawText);
        } catch (parseErr) {
            alert('Server returned an invalid response (HTTP ' + res.status + '):\n\n' + rawText.substring(0, 500));
            return;
        }

        if (result.success) {
            window.location.reload();
        } else {
            alert(result.message || 'Failed to mark as received.');
            btn.disabled = false;
            btn.innerHTML = originalHtml;
        }
    } catch (err) {
        console.error('Mark received failed:', err);
        alert('Something went wrong. Check console for details.');
        btn.disabled = false;
        btn.innerHTML = originalHtml;
    } finally {
        pendingReceiveId = null;
    }
});
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>