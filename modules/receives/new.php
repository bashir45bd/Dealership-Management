<?php
/**
 * Maruf Traders - New Cement Receive / Purchase Entry
 */

define('APP_INIT', true);
$pageTitle = 'New Cement Receive';
$breadcrumb = 'New Cement Receive';
$activeMenu = 'receives';

require_once __DIR__ . '/../../includes/header.php';
requirePermission('receives.manage');

$db = Database::getConnection();
$tenantId = TenantContext::id();

// Fetch companies
$cStmt = $db->prepare("SELECT id, name FROM companies WHERE tenant_id = :tenant AND status = 'active' ORDER BY name ASC");
$cStmt->execute([':tenant' => $tenantId]);
$companies = $cStmt->fetchAll();

// Fetch all products with unit
$pStmt = $db->prepare("SELECT p.id, p.company_id, p.name, p.brand, p.unit, p.default_purchase_price, p.current_stock 
                     FROM products p WHERE p.tenant_id = :tenant AND p.status = 'active' ORDER BY p.name ASC");
$pStmt->execute([':tenant' => $tenantId]);
$allProducts = $pStmt->fetchAll();
?>

<div class="page-header-container">
    <div>
        <h2 class="page-title">New Cement Receive (নতুন সিমেন্ট রিসিভ ও স্টক এন্ট্রি)</h2>
        <div class="page-subtitle">Record incoming cement shipments from manufacturers and calculate accurate landed cost per bag.</div>
    </div>
    <div>
        <a href="<?php echo BASE_URL; ?>/modules/receives/index.php" class="btn btn-secondary-custom">
            <i class="fa-solid fa-arrow-left me-1"></i> Back to History
        </a>
    </div>
</div>

<div class="row">
    <div class="col-lg-8">
        <div class="dark-card">
            <form id="receiveForm">
                <?php echo csrfField(); ?>

                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <label for="company_id" class="form-label-custom">Company / Supplier <span class="required">*</span></label>
                        <select class="form-select form-select-custom" id="company_id" name="company_id" required onchange="filterProductsByCompany()">
                            <option value="">-- Select Company --</option>
                            <?php foreach ($companies as $comp): ?>
                                <option value="<?php echo $comp['id']; ?>"><?php echo htmlspecialchars($comp['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label for="product_id" class="form-label-custom">Product / Brand <span class="required">*</span></label>
                        <select class="form-select form-select-custom" id="product_id" name="product_id" required onchange="onProductSelect()">
                            <option value="">-- Select Product --</option>
                        </select>
                    </div>
                </div>

                <div class="row g-3 mb-3">
                    <div class="col-md-4">
                        <label for="receive_date" class="form-label-custom">Receive Date <span class="required">*</span></label>
                        <input type="date" class="form-control form-control-custom" id="receive_date" name="receive_date" value="<?php echo date('Y-m-d'); ?>" required>
                    </div>
                    <div class="col-md-4">
                        <label for="quantity" class="form-label-custom">Quantity <span id="lbl_unit">(Units)</span> <span class="required">*</span></label>
                        <input type="number" step="0.001" min="0.001" class="form-control form-control-custom" id="quantity" name="quantity" placeholder="e.g. 500" required oninput="calculateTotals()">
                    </div>
                    <div class="col-md-4">
                        <label for="purchase_rate" class="form-label-custom">Purchase Rate / Unit (৳) <span class="required">*</span></label>
                        <input type="number" step="0.01" class="form-control form-control-custom" id="purchase_rate" name="purchase_rate" placeholder="0.00" required oninput="calculateTotals()">
                    </div>
                </div>

                <div class="card-header-clean mt-4 mb-3">
                    <div class="card-title-clean fs-6">
                        <i class="fa-solid fa-truck text-cyan"></i>
                        <span>Additional Landed Costs (যোগান খরচ)</span>
                    </div>
                </div>

                <div class="row g-3 mb-3">
                    <div class="col-md-4">
                        <label for="transport_cost" class="form-label-custom">Transport / Truck Fare (৳)</label>
                        <input type="number" step="0.01" class="form-control form-control-custom" id="transport_cost" name="transport_cost" value="0.00" oninput="calculateTotals()">
                    </div>
                    <div class="col-md-4">
                        <label for="loading_cost" class="form-label-custom">Loading / Unloading Labor (৳)</label>
                        <input type="number" step="0.01" class="form-control form-control-custom" id="loading_cost" name="loading_cost" value="0.00" oninput="calculateTotals()">
                    </div>
                    <div class="col-md-4">
                        <label for="other_cost" class="form-label-custom">Other / Gate Pass (৳)</label>
                        <input type="number" step="0.01" class="form-control form-control-custom" id="other_cost" name="other_cost" value="0.00" oninput="calculateTotals()">
                    </div>
                </div>

                <div class="card-header-clean mt-4 mb-3">
                    <div class="card-title-clean fs-6">
                        <i class="fa-solid fa-money-bill-wave text-success"></i>
                        <span>Payment at Shipment</span>
                    </div>
                </div>

                <div class="row g-3 mb-3">
                    <div class="col-md-4">
                        <label for="paid_amount" class="form-label-custom">Paid Amount During Receive (৳)</label>
                        <input type="number" step="0.01" class="form-control form-control-custom" id="paid_amount" name="paid_amount" value="0.00" oninput="calculateTotals()">
                    </div>
                    <div class="col-md-4">
                        <label for="payment_method" class="form-label-custom">Payment Method</label>
                        <select class="form-select form-select-custom" id="payment_method" name="payment_method">
                            <option value="Cash">Cash</option>
                            <option value="Bank">Bank Transfer</option>
                            <option value="bKash">bKash</option>
                            <option value="Nagad">Nagad</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label for="challan_no" class="form-label-custom">Challan / Invoice No</label>
                        <input type="text" class="form-control form-control-custom" id="challan_no" name="challan_no" placeholder="e.g. CH-9812">
                    </div>
                </div>

                <div class="row g-3 mb-4">
                    <div class="col-md-6">
                        <label for="vehicle_no" class="form-label-custom">Truck / Vehicle No</label>
                        <input type="text" class="form-control form-control-custom" id="vehicle_no" name="vehicle_no" placeholder="e.g. Dhaka Metro-Ta 11-2233">
                    </div>
                    <div class="col-md-6">
                        <label for="notes" class="form-label-custom">Notes / Remarks</label>
                        <input type="text" class="form-control form-control-custom" id="notes" name="notes" placeholder="Optional notes">
                    </div>
                </div>

                <div class="d-flex justify-content-end gap-2">
                    <a href="<?php echo BASE_URL; ?>/modules/receives/index.php" class="btn btn-secondary-custom">Cancel</a>
                    <button type="submit" class="btn btn-primary-custom" id="saveReceiveBtn">
                        <i class="fa-solid fa-check me-1"></i> Save & Update Stock
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Live Calculation Summary Panel -->
    <div class="col-lg-4 mt-3 mt-lg-0">
        <div class="dark-card">
            <h5 class="fw-bold mb-3" style="color: var(--text-primary);">
                <i class="fa-solid fa-calculator text-primary-light me-2"></i> Receive Calculation Summary
            </h5>

            <div class="profit-breakdown-box">
                <div class="profit-item">
                    <span class="text-secondary">Quantity:</span>
                    <span class="fw-bold" style="color: var(--text-primary);" id="disp_qty">0 Bags</span>
                </div>
                <div class="profit-item">
                    <span class="text-secondary">Purchase Value:</span>
                    <span style="color: var(--text-primary);" id="disp_purchase_val">৳ 0.00</span>
                </div>
                <div class="profit-item">
                    <span class="text-secondary">Transport & Labor:</span>
                    <span style="color: var(--text-primary);" id="disp_extra_costs">৳ 0.00</span>
                </div>
                <hr class="border-secondary border-opacity-25 my-2">
                <div class="profit-item">
                    <span class="text-secondary fw-bold">Total Shipment Cost:</span>
                    <span class="text-cyan fw-bold fs-6" id="disp_total_cost">৳ 0.00</span>
                </div>
                <div class="profit-item">
                    <span class="text-secondary fw-bold">Actual Cost Basis / Bag:</span>
                    <span class="text-primary-light fw-bold fs-6" id="disp_cost_per_bag">৳ 0.00</span>
                </div>
                <div class="profit-item">
                    <span class="text-secondary">Paid Amount:</span>
                    <span class="text-success" id="disp_paid">৳ 0.00</span>
                </div>
                <div class="profit-item net-profit">
                    <span style="color: var(--text-primary);">Payable to Company:</span>
                    <span class="text-danger" id="disp_payable">৳ 0.00</span>
                </div>
            </div>

            <div class="alert alert-info mt-3 py-2 small" style="background: rgba(34,211,238,0.1); border-color: rgba(34,211,238,0.2); color: var(--cyan);">
                <i class="fa-solid fa-info-circle me-1"></i> Landed cost per bag includes all transport and loading costs, ensuring accurate Gross Profit calculations upon sale.
            </div>
        </div>
    </div>
</div>

<script>
const productsCatalog = <?php echo json_encode($allProducts); ?>;

let currentProductUnit = 'Unit';

function filterProductsByCompany() {
    const compId = document.getElementById('company_id').value;
    const prodSelect = document.getElementById('product_id');
    
    prodSelect.innerHTML = '<option value="">-- Select Product --</option>';
    
    if (!compId) return;
    
    const filtered = productsCatalog.filter(p => p.company_id == compId);
    filtered.forEach(p => {
        const opt = document.createElement('option');
        opt.value = p.id;
        const u = p.unit || 'Unit';
        opt.textContent = `${p.name} (Stock: ${parseFloat(p.current_stock)} ${u})`;
        opt.dataset.price = p.default_purchase_price;
        opt.dataset.unit = u;
        prodSelect.appendChild(opt);
    });
}

function onProductSelect() {
    const prodSelect = document.getElementById('product_id');
    const selected = prodSelect.options[prodSelect.selectedIndex];
    if (selected && selected.value) {
        currentProductUnit = selected.dataset.unit || 'Unit';
        document.getElementById('lbl_unit').innerText = `(${currentProductUnit})`;
        if (selected.dataset.price) {
            document.getElementById('purchase_rate').value = selected.dataset.price;
        }
    } else {
        currentProductUnit = 'Unit';
        document.getElementById('lbl_unit').innerText = `(Units)`;
    }
    calculateTotals();
}

function calculateTotals() {
    const qty = parseFloat(document.getElementById('quantity').value) || 0;
    const rate = parseFloat(document.getElementById('purchase_rate').value) || 0;
    const transport = parseFloat(document.getElementById('transport_cost').value) || 0;
    const loading = parseFloat(document.getElementById('loading_cost').value) || 0;
    const other = parseFloat(document.getElementById('other_cost').value) || 0;
    const paid = parseFloat(document.getElementById('paid_amount').value) || 0;

    const purchaseVal = qty * rate;
    const extraCosts = transport + loading + other;
    const totalCost = purchaseVal + extraCosts;
    const costPerUnit = qty > 0 ? (totalCost / qty) : 0;
    const payable = totalCost - paid;

    document.getElementById('disp_qty').innerText = qty.toLocaleString(undefined, {minimumFractionDigits: 0, maximumFractionDigits: 3}) + ' ' + currentProductUnit;
    document.getElementById('disp_purchase_val').innerText = formatBDT(purchaseVal);
    document.getElementById('disp_extra_costs').innerText = formatBDT(extraCosts);
    document.getElementById('disp_total_cost').innerText = formatBDT(totalCost);
    document.getElementById('disp_cost_per_bag').innerText = formatBDT(costPerUnit);
    document.getElementById('disp_paid').innerText = formatBDT(paid);
    document.getElementById('disp_payable').innerText = formatBDT(payable);
}

/**
 * Direct fetch()-based AJAX submit handler.
 * setupAjaxForm() was not preventing the browser's default form submission
 * (GET request, page navigation), so receives were never actually saved.
 */
document.getElementById('receiveForm').addEventListener('submit', async function (e) {
    e.preventDefault();

    const form = e.target;
    const formData = new FormData(form);
    const saveBtn = document.getElementById('saveReceiveBtn');
    const originalBtnHtml = saveBtn.innerHTML;

    saveBtn.disabled = true;
    saveBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin me-1"></i> Saving...';

    try {
        const res = await fetch(`${window.BASE_URL}/ajax/receives/save.php`, {
            method: 'POST',
            body: formData,
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        });

        const result = await res.json();

        if (result.success) {
            setTimeout(() => {
                window.location.href = `${window.BASE_URL}/modules/receives/index.php`;
            }, 500);
        } else {
            alert(result.message || 'Failed to save receive.');
            saveBtn.disabled = false;
            saveBtn.innerHTML = originalBtnHtml;
        }
    } catch (err) {
        console.error('Receive save failed:', err);
        alert('Something went wrong while saving. Check the browser console for details.');
        saveBtn.disabled = false;
        saveBtn.innerHTML = originalBtnHtml;
    }
});
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>