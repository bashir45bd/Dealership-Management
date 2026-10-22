<?php
/**
 * Maruf Traders - Collections Management Module
 * (Updated: adds Cancel/Void action for collection receipts, mirroring
 *  the Sales module's cancel flow -- reverses retailer due, and if the
 *  collection was linked to a specific sale invoice, reverses that too.)
 */

define('APP_INIT', true);
$pageTitle = 'Collections & Money Receipts';
$breadcrumb = 'Collections';
$activeMenu = 'collections';

require_once __DIR__ . '/../../includes/header.php';
requirePermission('collections.manage');

$db = Database::getConnection();
$tenantId = TenantContext::id();

$startDate = $_GET['start_date'] ?? date('Y-m-01');
$endDate = $_GET['end_date'] ?? date('Y-m-d');
$retailerId = (int)($_GET['retailer_id'] ?? 0);
$status = trim($_GET['status'] ?? ''); // NEW: optional status filter (parity with sales)

try {
    $rStmt = $db->prepare("SELECT id, name, retailer_code, current_due FROM retailers WHERE tenant_id = :tenant AND status = 'active' AND deleted_at IS NULL ORDER BY name ASC");
    $rStmt->execute([':tenant' => $tenantId]);
    $allRetailers = $rStmt->fetchAll();
} catch (PDOException $e) {
    $rStmt = $db->prepare("SELECT id, name, retailer_code, current_due FROM retailers WHERE tenant_id = :tenant AND status = 'active' ORDER BY name ASC");
    $rStmt->execute([':tenant' => $tenantId]);
    $allRetailers = $rStmt->fetchAll();
}

$sql = "SELECT c.*, r.retailer_code, r.name as retailer_name, r.mobile as retailer_mobile 
        FROM collections c
        JOIN retailers r ON c.retailer_id = r.id
        WHERE c.tenant_id = :tenant AND c.collection_date BETWEEN :start AND :end";
$params = [':tenant' => $tenantId, ':start' => $startDate, ':end' => $endDate];

if ($retailerId) {
    $sql .= " AND c.retailer_id = :rid";
    $params[':rid'] = $retailerId;
}

// NEW: status filter (parity with sales list — 'active' or 'cancelled')
if ($status === 'cancelled') {
    $sql .= " AND c.status = 'cancelled'";
} elseif ($status === 'active') {
    $sql .= " AND c.status = 'active'";
}

$sql .= " ORDER BY c.id DESC";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$collections = $stmt->fetchAll();

$totalCollection = 0;
foreach ($collections as $col) {
    if ($col['status'] === 'active') {
        $totalCollection += (float)$col['amount'];
    }
}
?>

<div class="page-header-container no-print">
    <div>
        <h2 class="page-title">Collections Management (কাস্টমার বকেয়া আদায়)</h2>
        <div class="page-subtitle">Record cash, bKash, and bank collections from retailers against market dues.</div>
    </div>
    <div class="d-flex gap-2">
        <button type="button" class="btn btn-primary-custom" onclick="openCollectionModal()">
            <i class="fa-solid fa-hand-holding-dollar me-1"></i> New Collection
        </button>
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
            <label for="status" class="form-label-custom">Status</label>
            <select name="status" id="status" class="form-select form-select-custom">
                <option value="">-- All --</option>
                <option value="active" <?php echo ($status === 'active') ? 'selected' : ''; ?>>Active</option>
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
    <div class="col-md-6">
        <div class="dark-card py-3">
            <div class="text-secondary small fw-bold text-uppercase">Period Receipts Count</div>
            <div class="fs-4 fw-bold" style="color: var(--text-primary);"><?php echo count($collections); ?> Receipts</div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="dark-card py-3">
            <div class="text-secondary small fw-bold text-uppercase">Total Period Collection</div>
            <div class="fs-4 fw-bold text-success"><?php echo formatBDT($totalCollection); ?></div>
        </div>
    </div>
</div>

<!-- Collections Table -->
<div class="dark-card">
    <div class="card-header-clean">
        <div class="card-title-clean">
            <i class="fa-solid fa-receipt text-primary-light"></i>
            <span>Collection Receipts</span>
        </div>
    </div>

    <div class="table-responsive">
        <table class="dark-table">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Receipt Code</th>
                    <th>Retailer</th>
                    <th>Payment Method</th>
                    <th>Transaction Ref / Bank</th>
                    <th class="text-end">Amount (৳)</th>
                    <th class="text-center">Status</th>
                    <th>Notes</th>
                    <th class="text-end no-print">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($collections)): ?>
                    <tr>
                        <td colspan="9" class="text-center text-muted py-4">No collections found for the selected period.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($collections as $c): ?>
                        <?php $isVoid = ($c['status'] === 'cancelled'); ?>
                        <tr>
                            <td><?php echo formatDate($c['collection_date']); ?></td>
                            <td class="fw-bold text-info"><?php echo htmlspecialchars($c['collection_code']); ?></td>
                            <td>
                                <div class="fw-bold" style="color: var(--text-primary);"><?php echo htmlspecialchars($c['retailer_name']); ?></div>
                                <div class="text-secondary small"><?php echo htmlspecialchars($c['retailer_code']); ?></div>
                            </td>
                            <td>
                                <span class="badge-custom badge-primary"><?php echo htmlspecialchars($c['payment_method']); ?></span>
                            </td>
                            <td class="text-secondary"><?php echo htmlspecialchars($c['transaction_ref'] ?: ($c['bank_account'] ?: '—')); ?></td>
                            <td class="text-end fw-bold text-success fs-6"><?php echo formatBDT($c['amount']); ?></td>
                            <td class="text-center">
                                <span class="badge-custom <?php echo ($c['status'] === 'active') ? 'badge-success' : 'badge-danger'; ?>">
                                    <?php echo ucfirst($c['status']); ?>
                                </span>
                            </td>
                            <td class="text-secondary small"><?php echo htmlspecialchars($c['notes'] ?: '—'); ?></td>
                            <td class="text-end no-print">
                                <?php if (!$isVoid && hasPermission('collections.manage')): ?>
                                    <button type="button" class="btn btn-danger-custom btn-sm" onclick="cancelCollection(<?php echo $c['id']; ?>, '<?php echo htmlspecialchars($c['collection_code'], ENT_QUOTES); ?>')" title="Cancel Collection">
                                        <i class="fa-solid fa-ban"></i>
                                    </button>
                                <?php elseif ($isVoid): ?>
                                    <span class="text-secondary small" title="<?php echo htmlspecialchars($c['cancel_reason'] ?: ''); ?>">
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

<!-- New Collection Modal -->
<div class="modal fade" id="collectionModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content dark-modal">
            <div class="modal-header dark-modal-header">
                <h5 class="modal-title" style="color: var(--text-primary);">Record Retailer Collection</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="collectionForm">
                <?php echo csrfField(); ?>

                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label for="col_retailer_id" class="form-label-custom">Retailer / Customer <span class="required">*</span></label>
                        <select class="form-select form-select-custom" id="col_retailer_id" name="retailer_id" required onchange="onColRetailerChange()">
                            <option value="">-- Select Retailer --</option>
                            <?php foreach ($allRetailers as $r): ?>
                                <option value="<?php echo $r['id']; ?>" data-due="<?php echo $r['current_due']; ?>">
                                    <?php echo htmlspecialchars($r['retailer_code'] . ' — ' . $r['name']); ?> (Due: <?php echo formatBDT($r['current_due']); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label for="col_date" class="form-label-custom">Collection Date <span class="required">*</span></label>
                            <input type="date" class="form-control form-control-custom" id="col_date" name="collection_date" value="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label for="col_amount" class="form-label-custom">Amount (৳) <span class="required">*</span></label>
                            <input type="number" step="0.01" class="form-control form-control-custom" id="col_amount" name="amount" min="1" required placeholder="0.00">
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label for="col_pmethod" class="form-label-custom">Payment Method</label>
                            <select class="form-select form-select-custom" id="col_pmethod" name="payment_method">
                                <option value="Cash">Cash</option>
                                <option value="bKash">bKash</option>
                                <option value="Nagad">Nagad</option>
                                <option value="Bank">Bank Deposit</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="col_txref" class="form-label-custom">TrxID / Cheque / Bank Info</label>
                            <input type="text" class="form-control form-control-custom" id="col_txref" name="transaction_ref" placeholder="Optional reference">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="col_notes" class="form-label-custom">Notes</label>
                        <input type="text" class="form-control form-control-custom" id="col_notes" name="notes" placeholder="Optional notes">
                    </div>
                </div>

                <div class="modal-footer dark-modal-footer">
                    <button type="button" class="btn btn-secondary-custom" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary-custom" id="saveColBtn">
                        <i class="fa-solid fa-floppy-disk me-1"></i> Save Collection
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
let colModalInstance = null;

function openCollectionModal() {
    document.getElementById('collectionForm').reset();
    if (!colModalInstance) {
        colModalInstance = new bootstrap.Modal(document.getElementById('collectionModal'));
    }
    colModalInstance.show();
}

function onColRetailerChange() {
    const sel = document.getElementById('col_retailer_id');
    const opt = sel.options[sel.selectedIndex];
    if (opt && opt.dataset.due) {
        const due = parseFloat(opt.dataset.due) || 0;
        if (due > 0) {
            document.getElementById('col_amount').placeholder = `Max Due: ${due}`;
        }
    }
}

/**
 * Direct fetch()-based AJAX submit handler.
 *
 * IMPORTANT: this version reads the response as raw TEXT first, then tries
 * to parse it as JSON. If the server returned anything other than valid
 * JSON (a PHP fatal error, a warning printed before the JSON, an HTML error
 * page, a 404, etc.), the raw response text is shown directly in the alert
 * box instead of a generic "something went wrong" message. That raw text
 * IS the diagnostic info needed to find the real problem - no DevTools
 * required.
 */
document.getElementById('collectionForm').addEventListener('submit', async function (e) {
    e.preventDefault();

    const form = e.target;
    const formData = new FormData(form);
    const saveBtn = document.getElementById('saveColBtn');
    const originalBtnHtml = saveBtn.innerHTML;

    saveBtn.disabled = true;
    saveBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin me-1"></i> Saving...';

    let rawResponseText = '';

    try {
        const res = await fetch(`${window.BASE_URL}/ajax/collections/save.php`, {
            method: 'POST',
            body: formData,
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        });

        rawResponseText = await res.text();

        let result;
        try {
            result = JSON.parse(rawResponseText);
        } catch (parseErr) {
            // Server did not return valid JSON. Show exactly what it DID
            // return (truncated) so the real error is visible immediately.
            const preview = rawResponseText.trim().substring(0, 600) || '(empty response)';
            alert(
                `Server returned an invalid response (HTTP ${res.status}).\n\n` +
                `This usually means a PHP error occurred in save.php.\n\n` +
                `Raw response from server:\n${preview}`
            );
            return;
        }

        if (result.success) {
            if (colModalInstance) colModalInstance.hide();
            setTimeout(() => window.location.reload(), 500);
        } else {
            alert(result.message || 'Failed to save collection.');
        }
    } catch (err) {
        // This branch means the request itself failed (network error, CORS,
        // wrong URL, server unreachable) - not a JSON parsing issue.
        console.error('Collection save request failed:', err);
        alert(
            'The request to save the collection failed before getting a response.\n\n' +
            `Error: ${err.message}\n\n` +
            `URL attempted: ${window.BASE_URL}/ajax/collections/save.php`
        );
    } finally {
        saveBtn.disabled = false;
        saveBtn.innerHTML = originalBtnHtml;
    }
});

/**
 * Cancel Collection -- mirrors cancelSale() from the Sales module.
 * Same theme-aware SweetAlert2 confirmation, requires a reason, then
 * calls ajax/collections/cancel.php.
 */
async function cancelCollection(id, collectionCode) {
    const isLight = document.documentElement.getAttribute('data-theme') === 'light';
    const swalBg = isLight ? '#FFFFFF' : '#151F36';
    const swalColor = isLight ? '#0F172A' : '#F8FAFC';

    const { value: reason } = await Swal.fire({
        title: `Cancel Collection: ${collectionCode}?`,
        text: 'This will restore the retailer\'s due balance (and reverse the linked sale invoice\'s paid amount, if any).',
        input: 'text',
        inputPlaceholder: 'Enter cancellation reason...',
        inputValidator: (value) => {
            if (!value) return 'A cancellation reason is required!';
        },
        showCancelButton: true,
        confirmButtonColor: '#EF4444',
        cancelButtonColor: '#475569',
        confirmButtonText: 'Yes, Cancel Collection',
        background: swalBg,
        color: swalColor
    });

    if (reason) {
        try {
            const formData = new FormData();
            formData.append('id', id);
            formData.append('reason', reason);
            formData.append('csrf_token', getCsrfToken());

            const res = await apiRequest(`${window.BASE_URL}/ajax/collections/cancel.php`, {
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