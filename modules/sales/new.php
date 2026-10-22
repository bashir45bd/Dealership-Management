<?php
/**
 * vv Maruf Traders - New Sales Invoice / POS Entry
 */

define('APP_INIT', true);
$pageTitle = 'New Sales Invoice';
$breadcrumb = 'New Sale';
$activeMenu = 'sales';

require_once __DIR__ . '/../../includes/header.php';
requirePermission('sales.create');

$db = Database::getConnection();
$tenantId = TenantContext::id();

// Fetch active retailers (Customers)
$rStmt = $db->prepare("SELECT id, retailer_code, name, mobile, address, current_due, advance_balance, credit_limit 
                     FROM retailers WHERE tenant_id = :tenant AND status = 'active' ORDER BY name ASC");
$rStmt->execute([':tenant' => $tenantId]);
$retailers = $rStmt->fetchAll();

// Fetch active products with stock, company name, category and unit
$pStmt = $db->prepare("SELECT p.id, p.company_id, p.name, p.brand, p.unit, p.default_sale_price, p.current_stock, 
                             c.name as company_name, cat.name as category_name
                      FROM products p
                      JOIN companies c ON p.company_id = c.id
                      LEFT JOIN categories cat ON p.category_id = cat.id
                      WHERE p.tenant_id = :tenant AND p.status = 'active' 
                      ORDER BY c.name ASC, p.name ASC");
$pStmt->execute([':tenant' => $tenantId]);
$products = $pStmt->fetchAll();
?>

<div class="page-header-container">
    <div>
        <h2 class="page-title">New Sales Invoice (নতুন বিক্রয় চালান)</h2>
        <div class="page-subtitle">Create cement sales invoices, deduct inventory in real time, and update customer ledgers.</div>
    </div>
    <div>
        <a href="<?php echo BASE_URL; ?>/modules/sales/index.php" class="btn btn-secondary-custom">
            <i class="fa-solid fa-arrow-left me-1"></i> Back to Sales List
        </a>
    </div>
</div>

<form id="salesInvoiceForm">
    <?php echo csrfField(); ?>

    <div class="row">
        <!-- Main Form Details -->
        <div class="col-lg-8">
            <!-- Customer & Sale Header -->
            <div class="dark-card mb-4">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label for="retailer_id" class="form-label-custom">Select Retailer / Customer <span class="required">*</span></label>
                        <select class="form-select form-select-custom" id="retailer_id" name="retailer_id" required onchange="onRetailerChange()">
                            <option value="">-- Choose Retailer --</option>
                            <?php foreach ($retailers as $r): ?>
                                <option value="<?php echo $r['id']; ?>" 
                                        data-due="<?php echo $r['current_due']; ?>" 
                                        data-advance="<?php echo $r['advance_balance']; ?>" 
                                        data-limit="<?php echo $r['credit_limit']; ?>"
                                        data-mobile="<?php echo htmlspecialchars($r['mobile']); ?>"
                                        data-address="<?php echo htmlspecialchars($r['address'] ?? ''); ?>">
                                    <?php echo htmlspecialchars($r['retailer_code'] . ' — ' . $r['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-6">
                        <label for="sale_date" class="form-label-custom">Sale Date <span class="required">*</span></label>
                        <input type="date" class="form-control form-control-custom" id="sale_date" name="sale_date" value="<?php echo date('Y-m-d'); ?>" required>
                    </div>
                </div>

                <!-- Retailer Info Strip -->
                <div id="retailerInfoStrip" class="p-3 rounded mt-3" style="display: none; background: var(--bg-input); border: 1px solid var(--border-color);">
                    <div class="row text-center text-md-start">
                        <div class="col-md-4">
                            <span class="text-secondary small">Current Due:</span>
                            <span class="fw-bold text-danger ms-1" id="disp_retailer_due">৳ 0.00</span>
                        </div>
                        <div class="col-md-4">
                            <span class="text-secondary small">Advance Balance:</span>
                            <span class="fw-bold text-success ms-1" id="disp_retailer_adv">৳ 0.00</span>
                        </div>
                        <div class="col-md-4">
                            <span class="text-secondary small">Credit Limit:</span>
                            <span class="fw-bold ms-1" style="color: var(--text-primary);" id="disp_retailer_limit">৳ 0.00</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Sale Items Table -->
            <div class="dark-card mb-4">
                <div class="card-header-clean">
                    <div class="card-title-clean">
                        <i class="fa-solid fa-cart-shopping text-cyan"></i>
                        <span>Invoice Line Items (পণ্যসমূহ)</span>
                    </div>
                    <button type="button" class="btn btn-secondary-custom btn-sm" onclick="addItemRow()">
                        <i class="fa-solid fa-plus me-1"></i> Add Product Line
                    </button>
                </div>

                <div class="table-responsive">
                    <table class="dark-table" id="itemsTable">
                        <thead>
                            <tr>
                                <th style="width: 40%;">Company / Product Details</th>
                                <th style="width: 15%;" class="text-end">Stock</th>
                                <th style="width: 15%;" class="text-end">Quantity</th>
                                <th style="width: 15%;" class="text-end">Rate (৳)</th>
                                <th style="width: 15%;" class="text-end">Total (৳)</th>
                                <th style="width: 5%;"></th>
                            </tr>
                        </thead>
                        <tbody id="itemsTableBody">
                            <!-- Items added dynamically by JS -->
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Additional Landed Costs -->
            <div class="dark-card mb-4">
                <div class="card-header-clean">
                    <div class="card-title-clean fs-6">
                        <i class="fa-solid fa-truck text-cyan"></i>
                        <span>Additional Landed Costs (যোগান খরচ)</span>
                    </div>
                </div>

                <div class="row g-3">
                    <div class="col-md-4">
                        <label for="transport_cost" class="form-label-custom">Transport / Truck Fare (৳)</label>
                        <input type="number" step="0.01" class="form-control form-control-custom" id="transport_cost" name="transport_cost" value="0.00" oninput="distributeExtraCosts()">
                        <small class="text-secondary">Total quantity দিয়ে ভাগ হয়ে প্রতি ব্যাগের rate-এ যোগ হবে</small>
                    </div>
                    <div class="col-md-4">
                        <label for="loading_cost" class="form-label-custom">Loading / Unloading Labor (৳)</label>
                        <input type="number" step="0.01" class="form-control form-control-custom" id="loading_cost" name="loading_cost" value="0.00" oninput="distributeExtraCosts()">
                    </div>
                    <div class="col-md-4">
                        <label for="other_cost" class="form-label-custom">Other / Gate Pass (৳)</label>
                        <input type="number" step="0.01" class="form-control form-control-custom" id="other_cost" name="other_cost" value="0.00" oninput="distributeExtraCosts()">
                    </div>
                </div>
            </div>

            <!-- Delivery & Notes Card -->
            <div class="dark-card mb-4">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label for="delivery_address" class="form-label-custom">Delivery Site / Unloading Address</label>
                        <input type="text" class="form-control form-control-custom" id="delivery_address" name="delivery_address" placeholder="e.g. সুন্দরপুর বাজার সাইট">
                    </div>
                    <div class="col-md-6">
                        <label for="driver_info" class="form-label-custom">Transport / Driver Info</label>
                        <input type="text" class="form-control form-control-custom" id="driver_info" name="driver_info" placeholder="e.g. ট্রাক নং: হবিগঞ্জ-ট ১১-২২৩৩, চালক: করিম">
                    </div>
                    <div class="col-12">
                        <label for="notes" class="form-label-custom">Notes / Order Reference</label>
                        <input type="text" class="form-control form-control-custom" id="notes" name="notes" placeholder="Optional notes">
                    </div>
                </div>
            </div>
        </div>

        <!-- Financial Summary Panel -->
        <div class="col-lg-4">
            <div class="dark-card sticky-top" style="top: 90px;">
                <h5 class="fw-bold mb-3" style="color: var(--text-primary);">
                    <i class="fa-solid fa-file-invoice text-primary-light me-2"></i> Invoice Financial Summary
                </h5>

                <div class="profit-breakdown-box mb-3">
                    <div class="profit-item">
                        <span class="text-secondary">Subtotal Amount:</span>
                        <span class="fw-bold" style="color: var(--text-primary);" id="disp_subtotal">৳ 0.00</span>
                    </div>

                    <div class="profit-item">
                        <span class="text-secondary">Landed Costs (Transport+Loading+Other):</span>
                        <span style="color: var(--text-primary);" id="disp_extra_costs">৳ 0.00</span>
                    </div>

                    <div class="profit-item">
                        <span class="text-secondary">Special Discount (৳):</span>
                        <input type="number" step="0.01" class="form-control-custom text-end py-1 px-2" id="discount" name="discount" value="0.00" style="width: 120px;" oninput="calculateInvoice()">
                    </div>

                    <div class="profit-item">
                        <span class="text-secondary">Others (৳):</span>
                        <input type="number" step="0.01" class="form-control-custom text-end py-1 px-2" id="others_charge" name="others_charge" value="0.00" style="width: 120px;" oninput="calculateInvoice()">
                    </div>

                    <div class="profit-item">
                        <span class="text-secondary fw-bold">Net Total Amount:</span>
                        <span class="text-cyan fw-bold fs-6" id="disp_total_amount">৳ 0.00</span>
                    </div>

                    <div class="profit-item" id="advDeductRow" style="display: none;">
                        <span class="text-secondary">Deduct from Advance:</span>
                        <input type="number" step="0.01" class="form-control-custom text-end py-1 px-2 text-success" id="advance_deducted" name="advance_deducted" value="0.00" style="width: 120px;" oninput="calculateInvoice()">
                    </div>

                    <div class="profit-item">
                        <span class="text-secondary">Cash / Paid Amount (৳):</span>
                        <input type="number" step="0.01" class="form-control-custom text-end py-1 px-2 text-success fw-bold" id="paid_amount" name="paid_amount" value="0.00" style="width: 120px;" oninput="calculateInvoice()">
                    </div>

                    <div class="profit-item">
                        <span class="text-secondary">Payment Method:</span>
                        <select class="form-select-custom py-1 px-2" id="payment_method" name="payment_method" style="width: 120px;">
                            <option value="Cash">Cash</option>
                            <option value="bKash">bKash</option>
                            <option value="Nagad">Nagad</option>
                            <option value="Bank">Bank Transfer</option>
                        </select>
                    </div>

                    <div class="profit-item net-profit">
                        <span style="color: var(--text-primary);">Due Added to Customer:</span>
                        <span class="text-danger fw-bold fs-5" id="disp_due_amount">৳ 0.00</span>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary-custom w-100 justify-content-center py-2 fs-6" id="submitSaleBtn">
                    <i class="fa-solid fa-floppy-disk me-2"></i> Confirm & Generate Invoice
                </button>
            </div>
        </div>
    </div>
</form>

<script>
const productsList = <?php echo json_encode($products); ?>;
let rowCounter = 0;

function onRetailerChange() {
    const sel = document.getElementById('retailer_id');
    const opt = sel.options[sel.selectedIndex];
    const infoStrip = document.getElementById('retailerInfoStrip');
    const advRow = document.getElementById('advDeductRow');

    if (opt && opt.value) {
        infoStrip.style.display = 'block';
        document.getElementById('disp_retailer_due').innerText = formatBDT(opt.dataset.due || 0);
        document.getElementById('disp_retailer_adv').innerText = formatBDT(opt.dataset.advance || 0);
        document.getElementById('disp_retailer_limit').innerText = formatBDT(opt.dataset.limit || 0);

        const adv = parseFloat(opt.dataset.advance) || 0;
        if (adv > 0) {
            advRow.style.display = 'flex';
        } else {
            advRow.style.display = 'none';
            document.getElementById('advance_deducted').value = '0.00';
        }
    } else {
        infoStrip.style.display = 'none';
        advRow.style.display = 'none';
    }
    calculateInvoice();
}

function addItemRow() {
    rowCounter++;
    const tbody = document.getElementById('itemsTableBody');
    const tr = document.createElement('tr');
    tr.id = `item_row_${rowCounter}`;
    tr.dataset.basePrice = 0;
    tr.dataset.unit = 'Unit';

    tr.innerHTML = `
        <td>
            <select class="form-select form-select-custom prod-select" name="items[${rowCounter}][product_id]" required onchange="onItemProductChange(${rowCounter})">
                <option value="">-- Select Product --</option>
                ${productsList.map(p => `<option value="${p.id}" data-stock="${p.current_stock}" data-price="${p.default_sale_price}" data-unit="${p.unit || 'Bag'}" data-company="${p.company_name}">${p.company_name} — ${p.name} (${p.brand || 'Standard'}) [${p.unit || 'Bag'}]</option>`).join('')}
            </select>
        </td>
        <td class="text-end">
            <span class="fw-bold stock-badge text-secondary" id="stock_${rowCounter}">—</span>
        </td>
        <td>
            <div class="input-group input-group-sm">
                <input type="number" step="0.001" min="0.001" class="form-control form-control-custom text-end item-qty" name="items[${rowCounter}][quantity]" value="1" required oninput="distributeExtraCosts()">
                <span class="input-group-text bg-dark border-secondary text-secondary small unit-addon" id="unit_addon_${rowCounter}">Unit</span>
            </div>
        </td>
        <td>
            <input type="number" step="0.01" class="form-control form-control-custom text-end item-rate" name="items[${rowCounter}][unit_price]" value="0.00" required oninput="calculateInvoice()">
        </td>
        <td class="text-end fw-bold text-cyan item-total" id="total_${rowCounter}">
            ৳ 0.00
        </td>
        <td class="text-center">
            <button type="button" class="btn btn-danger-custom btn-sm py-1 px-2" onclick="removeItemRow(${rowCounter})">
                <i class="fa-solid fa-trash"></i>
            </button>
        </td>
    `;
    tbody.appendChild(tr);
}

function removeItemRow(rowId) {
    const row = document.getElementById(`item_row_${rowId}`);
    if (row) row.remove();
    distributeExtraCosts();
}

function onItemProductChange(rowId) {
    const row = document.getElementById(`item_row_${rowId}`);
    const sel = row.querySelector('.prod-select');
    const opt = sel.options[sel.selectedIndex];
    const stockSpan = document.getElementById(`stock_${rowId}`);
    const unitAddon = document.getElementById(`unit_addon_${rowId}`);

    if (opt && opt.value) {
        const stock = parseFloat(opt.dataset.stock) || 0;
        const price = parseFloat(opt.dataset.price) || 0;
        const unit = opt.dataset.unit || 'Unit';

        stockSpan.innerText = stock.toLocaleString(undefined, {minimumFractionDigits: 0, maximumFractionDigits: 3}) + ' ' + unit;
        stockSpan.className = stock > 0 ? 'fw-bold text-success' : 'fw-bold text-danger';
        row.dataset.basePrice = price;
        row.dataset.unit = unit;
        if (unitAddon) unitAddon.innerText = unit;

        const rateInput = row.querySelector('.item-rate');
        if (rateInput && (!parseFloat(rateInput.value) || parseFloat(rateInput.value) === 0)) {
            rateInput.value = price.toFixed(2);
        }
    } else {
        stockSpan.innerText = '—';
        row.dataset.basePrice = 0;
        if (unitAddon) unitAddon.innerText = 'Unit';
    }
    distributeExtraCosts();
}

/**
 * Sums Transport + Loading/Unloading + Other landed costs, splits the total
 * across the combined quantity of all selected lines, and folds the
 * per-bag share into each line's rate (mirrors the Cement Receive form's
 * "Additional Landed Costs" allocation).
 */
function distributeExtraCosts() {
    const transportCost = parseFloat(document.getElementById('transport_cost').value) || 0;
    const loadingCost = parseFloat(document.getElementById('loading_cost').value) || 0;
    const otherCost = parseFloat(document.getElementById('other_cost').value) || 0;
    const totalExtraCost = transportCost + loadingCost + otherCost;

    const rows = document.querySelectorAll('#itemsTableBody tr');

    let totalQty = 0;
    rows.forEach(r => {
        const sel = r.querySelector('.prod-select');
        if (sel && sel.value) {
            totalQty += parseFloat(r.querySelector('.item-qty')?.value) || 0;
        }
    });

    const perUnitExtra = (totalExtraCost > 0 && totalQty > 0) ? (totalExtraCost / totalQty) : 0;

    rows.forEach(r => {
        const sel = r.querySelector('.prod-select');
        const rateInput = r.querySelector('.item-rate');
        if (sel && sel.value) {
            const basePrice = parseFloat(r.dataset.basePrice) || 0;
            rateInput.value = (basePrice + perUnitExtra).toFixed(2);
        }
    });

    document.getElementById('disp_extra_costs').innerText = formatBDT(totalExtraCost);
    calculateInvoice();
}

function calculateInvoice() {
    let subtotal = 0;
    const rows = document.querySelectorAll('#itemsTableBody tr');

    rows.forEach(r => {
        const qty = parseFloat(r.querySelector('.item-qty')?.value) || 0;
        const rate = parseFloat(r.querySelector('.item-rate')?.value) || 0;
        const lineTotal = qty * rate;
        subtotal += lineTotal;

        const totCol = r.querySelector('.item-total');
        if (totCol) totCol.innerText = formatBDT(lineTotal);
    });

    const discount = parseFloat(document.getElementById('discount').value) || 0;
    const othersCharge = parseFloat(document.getElementById('others_charge').value) || 0;
    const totalAmount = Math.max(0, subtotal - discount + othersCharge);
    const advanceDeducted = parseFloat(document.getElementById('advance_deducted')?.value) || 0;
    const paidAmount = parseFloat(document.getElementById('paid_amount').value) || 0;

    const totalCredits = advanceDeducted + paidAmount;
    const dueAmount = Math.max(0, totalAmount - totalCredits);

    document.getElementById('disp_subtotal').innerText = formatBDT(subtotal);
    document.getElementById('disp_total_amount').innerText = formatBDT(totalAmount);
    document.getElementById('disp_due_amount').innerText = formatBDT(dueAmount);
}

document.addEventListener('DOMContentLoaded', () => {
    // Add first row by default
    addItemRow();
});

// Setup Form Submission
const form = document.getElementById('salesInvoiceForm');
form.addEventListener('submit', async function (e) {
    e.preventDefault();

    const submitBtn = document.getElementById('submitSaleBtn');
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span> Processing Sale...';

    // Collect Line Items
    const items = [];
    const rows = document.querySelectorAll('#itemsTableBody tr');
    
    if (rows.length === 0) {
        showToast('error', 'Please add at least one product.');
        submitBtn.disabled = false;
        submitBtn.innerHTML = '<i class="fa-solid fa-floppy-disk me-2"></i> Confirm & Generate Invoice';
        return;
    }

    rows.forEach(r => {
        const pid = r.querySelector('.prod-select')?.value;
        const qty = r.querySelector('.item-qty')?.value;
        const rate = r.querySelector('.item-rate')?.value;
        if (pid && qty && rate) {
            items.push({
                product_id: parseInt(pid),
                quantity: parseFloat(qty),
                unit_price: parseFloat(rate)
            });
        }
    });

    const formData = new FormData(form);
    formData.set('items', JSON.stringify(items));

    try {
        const result = await apiRequest(`${window.BASE_URL}/ajax/sales/save.php`, {
            method: 'POST',
            body: formData
        });

        if (result.success && result.data.id) {
            showToast('success', result.message);
            setTimeout(() => {
                window.location.href = `${window.BASE_URL}/modules/sales/invoice.php?id=${result.data.id}`;
            }, 1000);
        } else {
            showToast('error', result.message || 'Failed to record sale');
            submitBtn.disabled = false;
            submitBtn.innerHTML = '<i class="fa-solid fa-floppy-disk me-2"></i> Confirm & Generate Invoice';
        }
    } catch (err) {
        submitBtn.disabled = false;
        submitBtn.innerHTML = '<i class="fa-solid fa-floppy-disk me-2"></i> Confirm & Generate Invoice';
    }
});
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>