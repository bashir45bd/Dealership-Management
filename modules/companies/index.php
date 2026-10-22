<?php
/**
 * Maruf Traders - Company / Supplier Management Module
 */

define('APP_INIT', true);
$pageTitle = 'Company Management';
$breadcrumb = 'Companies';
$activeMenu = 'companies';

require_once __DIR__ . '/../../includes/header.php';
requirePermission('companies.manage');

$db = Database::getConnection();
$tenantId = TenantContext::id();
$stmt = $db->prepare("SELECT * FROM companies WHERE tenant_id = :tenant ORDER BY id DESC");
$stmt->execute([':tenant' => $tenantId]);
$companies = $stmt->fetchAll();

$totalPayable = 0;
foreach ($companies as $c) {
    $totalPayable += (float)$c['current_payable'];
}
?>

<div class="page-header-container">
    <div>
        <h2 class="page-title">Company / Brand Management (কোম্পানি / ব্র্যান্ড)</h2>
        <div class="page-subtitle">Manage manufacturers, dealership brands, suppliers, contact details and payable ledger accounts.</div>
    </div>
    <div class="d-flex gap-2">
        <button type="button" class="btn btn-primary-custom" onclick="openCompanyModal()">
            <i class="fa-solid fa-plus me-1"></i> Add Company
        </button>
    </div>
</div>

<!-- KPI Strip -->
<div class="row g-3 mb-4">
    <div class="col-md-6">
        <div class="dark-card py-3">
            <div class="text-secondary small fw-bold text-uppercase">Total Partner Companies</div>
            <div class="fs-4 fw-bold" style="color: var(--text-primary);"><?php echo count($companies); ?> Suppliers</div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="dark-card py-3">
            <div class="text-secondary small fw-bold text-uppercase">Total Outstanding Company Payable</div>
            <div class="fs-4 fw-bold text-danger"><?php echo formatBDT($totalPayable); ?></div>
        </div>
    </div>
</div>

<!-- Companies Table -->
<div class="dark-card">
    <div class="card-header-clean">
        <div class="card-title-clean">
            <i class="fa-solid fa-industry text-primary-light"></i>
            <span>Registered Cement Companies</span>
        </div>
        <input type="text" id="companySearchInput" class="form-control-custom" placeholder="Search company..." style="width: 250px;">
    </div>

    <div class="table-responsive">
        <table class="dark-table" id="companiesTable">
            <thead>
                <tr>
                    <th>Code</th>
                    <th>Company Name</th>
                    <th>Type</th>
                    <th>Contact Person</th>
                    <th>Mobile</th>
                    <th>Address</th>
                    <th class="text-end">Current Payable</th>
                    <th class="text-center">Status</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($companies)): ?>
                    <tr>
                        <td colspan="9" class="text-center text-muted py-4">No companies registered yet. Click "Add Company" to add one.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($companies as $c): ?>
                        <tr class="company-row">
                            <td class="fw-bold text-info"><?php echo htmlspecialchars($c['company_code']); ?></td>
                            <td class="fw-bold" style="color: var(--text-primary);"><?php echo htmlspecialchars($c['name']); ?></td>
                            <td><span class="badge bg-secondary"><?php echo htmlspecialchars(ucfirst($c['company_type'] ?? 'Brand')); ?></span></td>
                            <td><?php echo htmlspecialchars($c['contact_person'] ?: '—'); ?></td>
                            <td><?php echo htmlspecialchars($c['mobile']); ?></td>
                            <td class="text-secondary small"><?php echo htmlspecialchars($c['address'] ?: '—'); ?></td>
                            <td class="text-end fw-bold <?php echo ((float)$c['current_payable'] > 0) ? 'text-danger' : 'text-success'; ?>">
                                <?php echo formatBDT($c['current_payable']); ?>
                            </td>
                            <td class="text-center">
                                <span class="badge-custom <?php echo ($c['status'] === 'active') ? 'badge-success' : 'badge-danger'; ?>">
                                    <?php echo ucfirst($c['status']); ?>
                                </span>
                            </td>
                            <td class="text-end">
                                <div class="btn-group btn-group-sm">
                                    <a href="<?php echo BASE_URL; ?>/modules/companies/statement.php?id=<?php echo $c['id']; ?>" class="btn btn-secondary-custom" title="View Statement">
                                        <i class="fa-solid fa-file-invoice text-cyan"></i>
                                    </a>
                                    <button type="button" class="btn btn-secondary-custom" onclick="editCompany(<?php echo $c['id']; ?>)" title="Edit">
                                        <i class="fa-solid fa-pen-to-square text-primary-light"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Add/Edit Company Modal -->
<div class="modal fade" id="companyModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content dark-modal">
            <div class="modal-header dark-modal-header">
                <h5 class="modal-title" style="color: var(--text-primary);" id="companyModalTitle">Add New Company</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="companyForm">
                <input type="hidden" name="id" id="comp_id">
                <?php echo csrfField(); ?>

                <div class="modal-body p-4">
                    <div class="row g-3 mb-3">
                        <div class="col-md-8">
                            <label for="comp_name" class="form-label-custom">Company / Brand Name <span class="required">*</span></label>
                            <input type="text" class="form-control form-control-custom" id="comp_name" name="name" required placeholder="e.g. Shah Cement / BSRM">
                        </div>
                        <div class="col-md-4">
                            <label for="comp_type" class="form-label-custom">Company Type</label>
                            <select class="form-select form-select-custom" id="comp_type" name="company_type">
                                <option value="brand">Brand</option>
                                <option value="manufacturer">Manufacturer</option>
                                <option value="supplier">Supplier</option>
                                <option value="dealership">Dealership Source</option>
                            </select>
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label for="comp_contact_person" class="form-label-custom">Contact Person</label>
                            <input type="text" class="form-control form-control-custom" id="comp_contact_person" name="contact_person" placeholder="e.g. Area Manager">
                        </div>
                        <div class="col-md-6">
                            <label for="comp_mobile" class="form-label-custom">Mobile Number <span class="required">*</span></label>
                            <input type="text" class="form-control form-control-custom" id="comp_mobile" name="mobile" required placeholder="017xxxxxxxx">
                        </div>
                    </div>

                    <div class="mb-3" id="compOpeningRow">
                        <label for="comp_opening_payable" class="form-label-custom">Opening Payable Balance (৳)</label>
                        <input type="number" step="0.01" class="form-control form-control-custom" id="comp_opening_payable" name="opening_payable" value="0.00">
                    </div>

                    <div class="mb-3">
                        <label for="comp_address" class="form-label-custom">Address / Regional Office</label>
                        <textarea class="form-control form-control-custom" id="comp_address" name="address" rows="2" placeholder="e.g. Tejgaon, Dhaka"></textarea>
                    </div>

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="comp_status" class="form-label-custom">Status</label>
                            <select class="form-select form-select-custom" id="comp_status" name="status">
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="comp_notes" class="form-label-custom">Notes</label>
                            <input type="text" class="form-control form-control-custom" id="comp_notes" name="notes" placeholder="Optional notes">
                        </div>
                    </div>
                </div>

                <div class="modal-footer dark-modal-footer">
                    <button type="button" class="btn btn-secondary-custom" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary-custom" id="saveCompBtn">
                        <i class="fa-solid fa-floppy-disk me-1"></i> Save Company
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
let compModalInstance = null;

function openCompanyModal() {
    document.getElementById('companyForm').reset();
    document.getElementById('comp_id').value = '';
    document.getElementById('comp_type').value = 'brand';
    document.getElementById('companyModalTitle').innerText = 'Add New Company';
    document.getElementById('compOpeningRow').style.display = 'block';
    
    if (!compModalInstance) {
        compModalInstance = new bootstrap.Modal(document.getElementById('companyModal'));
    }
    compModalInstance.show();
}

async function editCompany(id) {
    try {
        const res = await apiRequest(`${window.BASE_URL}/ajax/companies/get.php?id=${id}`);
        if (res.success && res.data.company) {
            const c = res.data.company;
            document.getElementById('comp_id').value = c.id;
            document.getElementById('comp_name').value = c.name;
            document.getElementById('comp_type').value = c.company_type || 'brand';
            document.getElementById('comp_contact_person').value = c.contact_person || '';
            document.getElementById('comp_mobile').value = c.mobile;
            document.getElementById('comp_address').value = c.address || '';
            document.getElementById('comp_status').value = c.status;
            document.getElementById('comp_notes').value = c.notes || '';
            
            document.getElementById('compOpeningRow').style.display = 'none';
            document.getElementById('companyModalTitle').innerText = 'Edit Company: ' + c.company_code;

            if (!compModalInstance) {
                compModalInstance = new bootstrap.Modal(document.getElementById('companyModal'));
            }
            compModalInstance.show();
        }
    } catch (e) {
        console.error(e);
    }
}

document.getElementById('companySearchInput').addEventListener('input', function() {
    const val = this.value.toLowerCase().trim();
    document.querySelectorAll('#companiesTable tbody tr.company-row').forEach(r => {
        r.style.display = r.textContent.toLowerCase().includes(val) ? '' : 'none';
    });
});

/**
 * Direct fetch()-based AJAX submit handler.
 * setupAjaxForm() was not preventing the browser's default form submission
 * (GET request, page navigation), so companies were never actually saved.
 */
document.getElementById('companyForm').addEventListener('submit', async function (e) {
    e.preventDefault();

    const form = e.target;
    const formData = new FormData(form);
    const saveBtn = document.getElementById('saveCompBtn');
    const originalBtnHtml = saveBtn.innerHTML;

    saveBtn.disabled = true;
    saveBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin me-1"></i> Saving...';

    try {
        const res = await fetch(`${window.BASE_URL}/ajax/companies/save.php`, {
            method: 'POST',
            body: formData,
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        });

        const result = await res.json();

        if (result.success) {
            if (compModalInstance) compModalInstance.hide();
            setTimeout(() => window.location.reload(), 500);
        } else {
            alert(result.message || 'Failed to save company.');
        }
    } catch (err) {
        console.error('Company save failed:', err);
        alert('Something went wrong while saving. Check the browser console for details.');
    } finally {
        saveBtn.disabled = false;
        saveBtn.innerHTML = originalBtnHtml;
    }
});
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>