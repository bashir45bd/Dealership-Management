<?php
/**
 * Dealership Management & Accounting SaaS - Commission Reconciliation & Settlement Module
 */

define('APP_INIT', true);
$pageTitle = 'Commission Reconciliation & Settlement';
$breadcrumb = 'Reconciliation';
$activeMenu = 'commission_engine';

require_once __DIR__ . '/../../includes/header.php';
requirePermission('commission.manage');

$db = Database::getConnection();
$tenantId = TenantContext::id();

$companyId = (int)($_GET['company_id'] ?? 0);
$startDate = $_GET['start_date'] ?? date('Y-m-01');
$endDate   = $_GET['end_date'] ?? date('Y-m-t');

// Fetch tenant companies
$cStmt = $db->prepare("SELECT id, name FROM companies WHERE tenant_id = :tenant AND status = 'active' ORDER BY name ASC");
$cStmt->execute([':tenant' => $tenantId]);
$companies = $cStmt->fetchAll();

// Fetch settlements
$sSql = "SELECT cs.*, c.name as company_name 
         FROM commission_settlements cs
         JOIN companies c ON cs.company_id = c.id
         WHERE cs.tenant_id = :tenant";
$sParams = [':tenant' => $tenantId];

if ($companyId) {
    $sSql .= " AND cs.company_id = :cid";
    $sParams[':cid'] = $companyId;
}
$sSql .= " ORDER BY cs.id DESC";

$sStmt = $db->prepare($sSql);
$sStmt->execute($sParams);
$settlements = $sStmt->fetchAll();

// Fetch adjustments
$aSql = "SELECT ca.*, c.name as company_name 
         FROM commission_adjustments ca
         JOIN companies c ON ca.company_id = c.id
         WHERE ca.tenant_id = :tenant";
$aParams = [':tenant' => $tenantId];
if ($companyId) {
    $aSql .= " AND ca.company_id = :cid";
    $aParams[':cid'] = $companyId;
}
$aSql .= " ORDER BY ca.id DESC LIMIT 20";
$aStmt = $db->prepare($aSql);
$aStmt->execute($aParams);
$adjustments = $aStmt->fetchAll();

// Fetch recent commission ledger entries
$lSql = "SELECT cl.*, c.name as company_name 
         FROM commission_ledger cl
         JOIN companies c ON cl.company_id = c.id
         WHERE cl.tenant_id = :tenant";
$lParams = [':tenant' => $tenantId];
if ($companyId) {
    $lSql .= " AND cl.company_id = :cid";
    $lParams[':cid'] = $companyId;
}
$lSql .= " ORDER BY cl.id DESC LIMIT 30";
$lStmt = $db->prepare($lSql);
$lStmt->execute($lParams);
$ledgerEntries = $lStmt->fetchAll();
?>

<div class="page-header-container">
    <div>
        <h2 class="page-title">Commission Reconciliation & Settlement (কমিশন সমন্বয় ও সেটেলমেন্ট)</h2>
        <div class="page-subtitle">Compare software calculations with company statements, apply claim adjustments, and finalize settlements.</div>
    </div>
    <div class="d-flex gap-2">
        <a href="<?php echo BASE_URL; ?>/modules/commission_engine/index.php" class="btn btn-secondary-custom">
            <i class="fa-solid fa-sliders me-1"></i> Schemes Builder
        </a>
        <button type="button" class="btn btn-primary-custom" onclick="openReconcileModal()">
            <i class="fa-solid fa-scale-balanced me-1"></i> New Reconciliation
        </button>
    </div>
</div>

<!-- Filter Bar -->
<div class="dark-card mb-4 no-print">
    <form method="GET" action="" class="row g-3 align-items-end">
        <div class="col-md-4">
            <label class="form-label-custom">Company</label>
            <select name="company_id" class="form-select form-select-custom">
                <option value="">-- All Companies --</option>
                <?php foreach ($companies as $comp): ?>
                    <option value="<?php echo $comp['id']; ?>" <?php echo ($comp['id'] == $companyId) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($comp['name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-3">
            <label class="form-label-custom">Period Start</label>
            <input type="date" name="start_date" class="form-control form-control-custom" value="<?php echo htmlspecialchars($startDate); ?>">
        </div>
        <div class="col-md-3">
            <label class="form-label-custom">Period End</label>
            <input type="date" name="end_date" class="form-control form-control-custom" value="<?php echo htmlspecialchars($endDate); ?>">
        </div>
        <div class="col-md-2">
            <button type="submit" class="btn btn-primary-custom w-100">
                <i class="fa-solid fa-filter me-1"></i> Filter
            </button>
        </div>
    </form>
</div>

<div class="row g-4">
    <!-- Settlement Records -->
    <div class="col-12">
        <div class="dark-card">
            <div class="card-header-clean">
                <div class="card-title-clean">
                    <i class="fa-solid fa-handshake-angle text-cyan"></i>
                    <span>Settlements & Reconciliations</span>
                </div>
            </div>
            <div class="table-responsive">
                <table class="dark-table">
                    <thead>
                        <tr>
                            <th>Company</th>
                            <th>Period</th>
                            <th class="text-end">System Calc (৳)</th>
                            <th class="text-end">Statement (৳)</th>
                            <th class="text-end">Adjustment (৳)</th>
                            <th class="text-end">Final / Approved (৳)</th>
                            <th class="text-end">Received (৳)</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($settlements)): ?>
                            <tr><td colspan="9" class="text-center py-4 text-secondary">No settlements recorded yet.</td></tr>
                        <?php else: ?>
                            <?php foreach ($settlements as $s): ?>
                                <tr>
                                    <td class="fw-bold"><?php echo htmlspecialchars($s['company_name']); ?></td>
                                    <td><?php echo htmlspecialchars($s['period_start'] . ' to ' . $s['period_end']); ?></td>
                                    <td class="text-end text-cyan font-monospace"><?php echo number_format($s['calculated_amount'], 2); ?></td>
                                    <td class="text-end text-warning font-monospace"><?php echo $s['statement_amount'] !== null ? number_format($s['statement_amount'], 2) : '—'; ?></td>
                                    <td class="text-end text-info font-monospace"><?php echo number_format($s['adjustment_amount'], 2); ?></td>
                                    <td class="text-end fw-bold text-success font-monospace"><?php echo number_format($s['final_amount'], 2); ?></td>
                                    <td class="text-end font-monospace"><?php echo number_format($s['received_amount'], 2); ?></td>
                                    <td>
                                        <span class="badge-custom <?php echo $s['status'] === 'received' ? 'badge-success' : ($s['status'] === 'approved' ? 'badge-primary' : 'badge-warning'); ?>">
                                            <?php echo strtoupper($s['status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <button class="btn btn-secondary-custom btn-sm py-1 px-2" onclick="openAdjModal(<?php echo $s['id']; ?>, <?php echo $s['company_id']; ?>, '<?php echo $s['period_start']; ?>', '<?php echo $s['period_end']; ?>')">
                                            <i class="fa-solid fa-plus-minus me-1"></i> Adjust
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Commission Ledger View -->
    <div class="col-12">
        <div class="dark-card">
            <div class="card-header-clean">
                <div class="card-title-clean">
                    <i class="fa-solid fa-book-bookmark text-primary-light"></i>
                    <span>Commission Ledger History</span>
                </div>
            </div>
            <div class="table-responsive">
                <table class="dark-table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Company</th>
                            <th>Entry Type</th>
                            <th>Reference</th>
                            <th>Description</th>
                            <th class="text-end">Debit (৳)</th>
                            <th class="text-end">Credit (৳)</th>
                            <th class="text-end">Balance (৳)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($ledgerEntries)): ?>
                            <tr><td colspan="8" class="text-center py-4 text-secondary">No commission ledger transactions recorded yet.</td></tr>
                        <?php else: ?>
                            <?php foreach ($ledgerEntries as $l): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($l['entry_date']); ?></td>
                                    <td class="fw-bold"><?php echo htmlspecialchars($l['company_name']); ?></td>
                                    <td><span class="badge bg-secondary"><?php echo strtoupper($l['entry_type']); ?></span></td>
                                    <td><?php echo htmlspecialchars($l['reference_no'] ?? '—'); ?></td>
                                    <td><?php echo htmlspecialchars($l['description'] ?? ''); ?></td>
                                    <td class="text-end text-success font-monospace"><?php echo (float)$l['debit'] > 0 ? number_format($l['debit'], 2) : '—'; ?></td>
                                    <td class="text-end text-danger font-monospace"><?php echo (float)$l['credit'] > 0 ? number_format($l['credit'], 2) : '—'; ?></td>
                                    <td class="text-end fw-bold text-cyan font-monospace"><?php echo number_format($l['running_balance'], 2); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Modal: New Reconciliation / Settlement -->
<div class="modal fade" id="reconcileModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="background: var(--bg-card); border: 1px solid var(--border-color);">
            <div class="modal-header border-secondary border-opacity-25">
                <h5 class="modal-title text-white">Record Reconciliation Statement</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="reconcileForm">
                <?php echo csrfField(); ?>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label-custom">Company <span class="required">*</span></label>
                        <select name="company_id" id="rec_company_id" class="form-select form-select-custom" required>
                            <option value="">-- Choose Company --</option>
                            <?php foreach ($companies as $comp): ?>
                                <option value="<?php echo $comp['id']; ?>"><?php echo htmlspecialchars($comp['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label-custom">Period Start <span class="required">*</span></label>
                            <input type="date" name="period_start" class="form-control form-control-custom" value="<?php echo date('Y-m-01'); ?>" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label-custom">Period End <span class="required">*</span></label>
                            <input type="date" name="period_end" class="form-control form-control-custom" value="<?php echo date('Y-m-t'); ?>" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label-custom">Company Statement Amount (৳) <span class="required">*</span></label>
                        <input type="number" step="0.01" name="statement_amount" class="form-control form-control-custom" placeholder="e.g. 80000.00" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label-custom">Statement Reference / Memo No</label>
                        <input type="text" name="reference_no" class="form-control form-control-custom" placeholder="e.g. STMT-2026-10">
                    </div>
                    <div class="mb-3">
                        <label class="form-label-custom">Notes</label>
                        <textarea name="notes" class="form-control form-control-custom" rows="2" placeholder="Optional remarks"></textarea>
                    </div>
                </div>
                <div class="modal-footer border-secondary border-opacity-25">
                    <button type="button" class="btn btn-secondary-custom" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary-custom" id="saveRecBtn">Save Reconciliation</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function openReconcileModal() {
    new bootstrap.Modal(document.getElementById('reconcileModal')).show();
}

function openAdjModal(settlementId, companyId, start, end) {
    const amt = prompt('Enter adjustment amount in BDT (+ or -):');
    if (!amt) return;
    const reason = prompt('Enter adjustment reason:');
    if (!reason) return;

    const fd = new FormData();
    fd.append('settlement_id', settlementId);
    fd.append('company_id', companyId);
    fd.append('period_start', start);
    fd.append('period_end', end);
    fd.append('amount', amt);
    fd.append('description', reason);
    fd.append('csrf_token', '<?php echo generateCsrfToken(); ?>');

    fetch(`${window.BASE_URL}/ajax/commission/adjust.php`, {
        method: 'POST',
        body: fd
    }).then(r => r.json()).then(res => {
        if (res.success) {
            alert(res.message);
            window.location.reload();
        } else {
            alert(res.message || 'Error occurred');
        }
    });
}

document.getElementById('reconcileForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    const btn = document.getElementById('saveRecBtn');
    btn.disabled = true;
    const fd = new FormData(this);

    try {
        const res = await fetch(`${window.BASE_URL}/ajax/commission/reconcile.php`, {
            method: 'POST',
            body: fd
        });
        const json = await res.json();
        if (json.success) {
            alert(json.message);
            window.location.reload();
        } else {
            alert(json.message || 'Failed to save');
            btn.disabled = false;
        }
    } catch (err) {
        alert('Error saving reconciliation');
        btn.disabled = false;
    }
});
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
