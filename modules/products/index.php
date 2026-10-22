<?php
/**
 * Product Management Module (Generalized Multi-Company & Multi-Category)
 */

define('APP_INIT', true);
$pageTitle = 'Product Management';
$breadcrumb = 'Products';
$activeMenu = 'products';

require_once __DIR__ . '/../../includes/header.php';
requirePermission('products.manage');

$db = Database::getConnection();
$tenantId = TenantContext::id();

// Fetch active companies for dropdown
$cStmt = $db->prepare("SELECT id, name FROM companies WHERE tenant_id = :tenant AND status = 'active' ORDER BY name ASC");
$cStmt->execute([':tenant' => $tenantId]);
$activeCompanies = $cStmt->fetchAll();

// Fetch categories
$catStmt = $db->prepare("SELECT id, name, code FROM product_categories WHERE (tenant_id = :tenant OR tenant_id IS NULL) AND status = 'active' ORDER BY name ASC");
$catStmt->execute([':tenant' => $tenantId]);
$categories = $catStmt->fetchAll();

// Fetch units
$uStmt = $db->prepare("SELECT id, name, code, allows_fraction FROM units WHERE (tenant_id = :tenant OR tenant_id IS NULL) AND status = 'active' ORDER BY name ASC");
$uStmt->execute([':tenant' => $tenantId]);
$units = $uStmt->fetchAll();

// Fetch products with company and category names
$stmt = $db->prepare("SELECT p.*, c.name as company_name, cat.name as category_name 
                      FROM products p 
                      JOIN companies c ON p.company_id = c.id 
                      LEFT JOIN product_categories cat ON p.category_id = cat.id
                      WHERE p.tenant_id = :tenant 
                      ORDER BY p.id DESC");
$stmt->execute([':tenant' => $tenantId]);
$products = $stmt->fetchAll();

$totalStockUnits = 0;
$lowStockCount = 0;
foreach ($products as $p) {
    $totalStockUnits += (float)$p['current_stock'];
    if ((float)$p['current_stock'] <= (float)$p['low_stock_limit']) {
        $lowStockCount++;
    }
}
?>

<div class="page-header-container">
    <div>
        <h2 class="page-title">Products (পণ্যসমূহ)</h2>
        <div class="page-subtitle">Manage multi-company products, categories (Cement, Rod, etc.), units, pricing and inventory.</div>
    </div>
    <div class="d-flex gap-2">
        <a href="<?php echo BASE_URL; ?>/modules/stock/index.php" class="btn btn-secondary-custom">
            <i class="fa-solid fa-boxes-stacked me-1"></i> Stock Ledger
        </a>
        <button type="button" class="btn btn-primary-custom" onclick="openProductModal()">
            <i class="fa-solid fa-plus me-1"></i> Add Product
        </button>
    </div>
</div>

<!-- KPI Strip -->
<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="dark-card py-3">
            <div class="text-secondary small fw-bold text-uppercase">Total Registered Products</div>
            <div class="fs-4 fw-bold" style="color: var(--text-primary);"><?php echo count($products); ?> Items</div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="dark-card py-3">
            <div class="text-secondary small fw-bold text-uppercase">Total Available Stock</div>
            <div class="fs-4 fw-bold text-cyan"><?php echo number_format($totalStockUnits, 2); ?> Units</div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="dark-card py-3">
            <div class="text-secondary small fw-bold text-uppercase">Low Stock Alert Items</div>
            <div class="fs-4 fw-bold <?php echo ($lowStockCount > 0) ? 'text-danger' : 'text-success'; ?>">
                <?php echo $lowStockCount; ?> Products
            </div>
        </div>
    </div>
</div>

<!-- Products Table -->
<div class="dark-card">
    <div class="card-header-clean">
        <div class="card-title-clean">
            <i class="fa-solid fa-cubes-stacked text-primary-light"></i>
            <span>All Products</span>
        </div>
        <input type="text" id="productSearchInput" class="form-control-custom" placeholder="Search product, category, brand..." style="width: 250px;">
    </div>

    <div class="table-responsive">
        <table class="dark-table" id="productsTable">
            <thead>
                <tr>
                    <th>Code</th>
                    <th>Product / Brand</th>
                    <th>Category</th>
                    <th>Company / Supplier</th>
                    <th>Unit</th>
                    <th class="text-end">Purchase Rate</th>
                    <th class="text-end">Sale Rate</th>
                    <th class="text-end">Current Stock</th>
                    <th class="text-center">Stock Status</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($products)): ?>
                    <tr>
                        <td colspan="10" class="text-center text-muted py-4">No products added yet. Click "Add Product" to create one.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($products as $p): ?>
                        <?php 
                            $stockNum = (float)$p['current_stock'];
                            $limitNum = (float)$p['low_stock_limit'];
                            $isLowStock = ($stockNum <= $limitNum);
                            $isOutOfStock = ($stockNum <= 0);
                        ?>
                        <tr class="product-row">
                            <td class="fw-bold text-info"><?php echo htmlspecialchars($p['product_code']); ?></td>
                            <td>
                                <div class="fw-bold" style="color: var(--text-primary);"><?php echo htmlspecialchars($p['name']); ?></div>
                                <div class="text-secondary small"><?php echo htmlspecialchars($p['brand']); ?></div>
                            </td>
                            <td>
                                <span class="badge bg-secondary"><?php echo htmlspecialchars($p['category_name'] ?? 'General'); ?></span>
                            </td>
                            <td>
                                <span class="badge-custom badge-primary"><?php echo htmlspecialchars($p['company_name']); ?></span>
                            </td>
                            <td><?php echo htmlspecialchars($p['unit'] ?? 'Bag'); ?></td>
                            <td class="text-end"><?php echo formatBDT($p['default_purchase_price']); ?></td>
                            <td class="text-end fw-bold text-cyan"><?php echo formatBDT($p['default_sale_price']); ?></td>
                            <td class="text-end fw-bold fs-6 <?php echo $isOutOfStock ? 'text-danger' : ($isLowStock ? 'text-warning' : ''); ?>" <?php echo (!$isOutOfStock && !$isLowStock) ? 'style="color: var(--text-primary);"' : ''; ?>>
                                <?php echo rtrim(rtrim(number_format($stockNum, 3), '0'), '.'); ?> <?php echo htmlspecialchars($p['unit'] ?? ''); ?>
                            </td>
                            <td class="text-center">
                                <?php if ($isOutOfStock): ?>
                                    <span class="badge-custom badge-danger"><i class="fa-solid fa-circle-xmark"></i> Out of Stock</span>
                                <?php elseif ($isLowStock): ?>
                                    <span class="badge-custom badge-warning"><i class="fa-solid fa-triangle-exclamation"></i> Low Stock</span>
                                <?php else: ?>
                                    <span class="badge-custom badge-success"><i class="fa-solid fa-check"></i> In Stock</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-end">
                                <button type="button" class="btn btn-secondary-custom btn-sm" onclick="editProduct(<?php echo $p['id']; ?>)" title="Edit Product">
                                    <i class="fa-solid fa-pen-to-square text-primary-light"></i>
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Add / Edit Product Modal -->
<div class="modal fade" id="productModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content dark-modal">
            <div class="modal-header dark-modal-header">
                <h5 class="modal-title" style="color: var(--text-primary);" id="productModalTitle">Add Product</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="productForm">
                <input type="hidden" name="id" id="prod_id">
                <?php echo csrfField(); ?>

                <div class="modal-body p-4">
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label for="prod_company_id" class="form-label-custom">Company / Brand / Supplier <span class="required">*</span></label>
                            <select class="form-select form-select-custom" id="prod_company_id" name="company_id" required>
                                <option value="">-- Select Company --</option>
                                <?php foreach ($activeCompanies as $comp): ?>
                                    <option value="<?php echo $comp['id']; ?>"><?php echo htmlspecialchars($comp['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="prod_category_id" class="form-label-custom">Product Category <span class="required">*</span></label>
                            <select class="form-select form-select-custom" id="prod_category_id" name="category_id" required>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?php echo $cat['id']; ?>"><?php echo htmlspecialchars($cat['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-8">
                            <label for="prod_name" class="form-label-custom">Product Title <span class="required">*</span></label>
                            <input type="text" class="form-control form-control-custom" id="prod_name" name="name" required placeholder="e.g. BSRM 10mm Rod / Shah Cement 50KG">
                        </div>
                        <div class="col-md-4">
                            <label for="prod_brand" class="form-label-custom">Brand Name <span class="required">*</span></label>
                            <input type="text" class="form-control form-control-custom" id="prod_brand" name="brand" required placeholder="e.g. BSRM / Shah Cement">
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label for="prod_unit" class="form-label-custom">Unit of Measure <span class="required">*</span></label>
                            <select class="form-select form-select-custom" id="prod_unit" name="unit" required>
                                <?php foreach ($units as $u): ?>
                                    <option value="<?php echo htmlspecialchars($u['name']); ?>"><?php echo htmlspecialchars($u['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="prod_bag_size" class="form-label-custom">Weight / Specification (Optional)</label>
                            <input type="number" step="0.01" class="form-control form-control-custom" id="prod_bag_size" name="bag_size_kg" value="50.00" placeholder="e.g. 50 (for 50 KG bag)">
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label for="prod_purchase_price" class="form-label-custom">Default Purchase Price (৳)</label>
                            <input type="number" step="0.01" class="form-control form-control-custom" id="prod_purchase_price" name="default_purchase_price" value="0.00">
                        </div>
                        <div class="col-md-6">
                            <label for="prod_sale_price" class="form-label-custom">Default Sale Price (৳)</label>
                            <input type="number" step="0.01" class="form-control form-control-custom" id="prod_sale_price" name="default_sale_price" value="0.00">
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6" id="prodOpeningStockRow">
                            <label for="prod_opening_stock" class="form-label-custom">Opening Stock</label>
                            <input type="number" step="0.001" class="form-control form-control-custom" id="prod_opening_stock" name="opening_stock" value="0">
                        </div>
                        <div class="col-md-6">
                            <label for="prod_low_stock_limit" class="form-label-custom">Low Stock Alert Limit</label>
                            <input type="number" step="0.001" class="form-control form-control-custom" id="prod_low_stock_limit" name="low_stock_limit" value="10">
                        </div>
                    </div>

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="prod_status" class="form-label-custom">Status</label>
                            <select class="form-select form-select-custom" id="prod_status" name="status">
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="prod_notes" class="form-label-custom">Notes / Description</label>
                            <input type="text" class="form-control form-control-custom" id="prod_notes" name="notes" placeholder="Optional notes">
                        </div>
                    </div>
                </div>

                <div class="modal-footer dark-modal-footer">
                    <button type="button" class="btn btn-secondary-custom" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary-custom" id="saveProdBtn">
                        <i class="fa-solid fa-floppy-disk me-1"></i> Save Product
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
let prodModalInstance = null;

function openProductModal() {
    document.getElementById('productForm').reset();
    document.getElementById('prod_id').value = '';
    document.getElementById('productModalTitle').innerText = 'Add Product';
    document.getElementById('prodOpeningStockRow').style.display = 'block';
    
    if (!prodModalInstance) {
        prodModalInstance = new bootstrap.Modal(document.getElementById('productModal'));
    }
    prodModalInstance.show();
}

async function editProduct(id) {
    try {
        const res = await apiRequest(`${window.BASE_URL}/ajax/products/get.php?id=${id}`);
        if (res.success && res.data.product) {
            const p = res.data.product;
            document.getElementById('prod_id').value = p.id;
            document.getElementById('prod_company_id').value = p.company_id;
            if (p.category_id && document.getElementById('prod_category_id')) {
                document.getElementById('prod_category_id').value = p.category_id;
            }
            document.getElementById('prod_name').value = p.name;
            document.getElementById('prod_brand').value = p.brand;
            if (p.unit && document.getElementById('prod_unit')) {
                document.getElementById('prod_unit').value = p.unit;
            }
            document.getElementById('prod_bag_size').value = p.bag_size_kg;
            document.getElementById('prod_purchase_price').value = p.default_purchase_price;
            document.getElementById('prod_sale_price').value = p.default_sale_price;
            document.getElementById('prod_low_stock_limit').value = p.low_stock_limit;
            document.getElementById('prod_status').value = p.status;
            document.getElementById('prod_notes').value = p.notes || '';
            
            document.getElementById('prodOpeningStockRow').style.display = 'none';
            document.getElementById('productModalTitle').innerText = 'Edit Product: ' + p.product_code;

            if (!prodModalInstance) {
                prodModalInstance = new bootstrap.Modal(document.getElementById('productModal'));
            }
            prodModalInstance.show();
        }
    } catch (e) {
        console.error(e);
    }
}

document.getElementById('productSearchInput').addEventListener('input', function() {
    const val = this.value.toLowerCase().trim();
    document.querySelectorAll('#productsTable tbody tr.product-row').forEach(r => {
        r.style.display = r.textContent.toLowerCase().includes(val) ? '' : 'none';
    });
});

document.getElementById('productForm').addEventListener('submit', async function (e) {
    e.preventDefault();

    const form = e.target;
    const formData = new FormData(form);
    const saveBtn = document.getElementById('saveProdBtn');
    const originalBtnHtml = saveBtn.innerHTML;

    saveBtn.disabled = true;
    saveBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin me-1"></i> Saving...';

    try {
        const res = await fetch(`${window.BASE_URL}/ajax/products/save.php`, {
            method: 'POST',
            body: formData,
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        });

        const result = await res.json();

        if (result.success) {
            if (prodModalInstance) prodModalInstance.hide();
            setTimeout(() => window.location.reload(), 500);
        } else {
            alert(result.message || 'Failed to save product.');
        }
    } catch (err) {
        console.error('Product save failed:', err);
        alert('Something went wrong while saving. Check browser console for details.');
    } finally {
        saveBtn.disabled = false;
        saveBtn.innerHTML = originalBtnHtml;
    }
});
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
