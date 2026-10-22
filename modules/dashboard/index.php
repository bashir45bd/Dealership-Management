<?php
/**
 * Maruf Traders - Master Executive Dashboard
 */

define('APP_INIT', true);
$pageTitle = 'Executive Dashboard';
$breadcrumb = 'Overview';
$activeMenu = 'dashboard';

require_once __DIR__ . '/../../includes/header.php';
requirePermission('dashboard.view');

$db = Database::getConnection();
$tenantId = TenantContext::id();

// Fetch Recent Sales
$sStmt = $db->prepare("SELECT s.*, r.name as retailer_name, r.retailer_code 
                     FROM sales s
                     JOIN retailers r ON s.retailer_id = r.id
                     WHERE s.tenant_id = :tenant
                     ORDER BY s.id DESC LIMIT 5");
$sStmt->execute([':tenant' => $tenantId]);
$recentSales = $sStmt->fetchAll();

// Fetch Top Selling Products
$tpStmt = $db->prepare("SELECT p.name, p.brand, p.unit, c.name as company_name, 
                             COALESCE(SUM(si.quantity), 0) as total_bags, 
                             COALESCE(SUM(si.total_price), 0) as total_amount 
                      FROM products p
                      JOIN companies c ON p.company_id = c.id
                      LEFT JOIN sale_items si ON p.id = si.product_id AND si.tenant_id = :tenant
                      WHERE p.tenant_id = :tenant
                      GROUP BY p.id
                      ORDER BY total_bags DESC LIMIT 4");
$tpStmt->execute([':tenant' => $tenantId]);
$topProducts = $tpStmt->fetchAll();
?>

<!-- Low Stock Warning Banner -->
<div id="lowStockAlertWrapper" class="low-stock-alert-panel" style="display: none;">
    <div class="low-stock-info">
        <div class="alert-bell-icon">
            <i class="fa-solid fa-triangle-exclamation"></i>
        </div>
        <div>
            <h6 class="fw-bold mb-1" style="color: var(--text-primary);">Low Stock Warning (স্টক সতর্কতা)</h6>
            <div class="text-secondary small" id="lowStockAlertText">Some cement brands are running below minimum inventory thresholds.</div>
        </div>
    </div>
    <a href="<?php echo BASE_URL; ?>/modules/stock/index.php" class="btn btn-danger-custom btn-sm">
        <i class="fa-solid fa-boxes-stacked me-1"></i> View Stock
    </a>
</div>

<!-- Dashboard Header -->
<div class="page-header-container">
    <div>
        <h2 class="page-title">Welcome back, <?php echo htmlspecialchars($currentUser['name']); ?> 👋</h2>
        <div class="page-subtitle">Here is your cement dealership business overview and financial analytics performance.</div>
    </div>
    <div class="d-flex align-items-center gap-2">
        <select id="dashboardPeriodFilter" class="form-select form-select-custom" style="width: 160px;">
            <option value="this_month" selected>This Month</option>
            <option value="today">Today</option>
            <option value="this_week">This Week</option>
            <option value="this_year">This Year</option>
        </select>
        <button type="button" class="btn btn-secondary-custom" id="dashboardRefreshBtn" title="Refresh Data">
            <i class="fa-solid fa-arrows-rotate"></i>
        </button>
    </div>
</div>

<!-- Row 1: KPI Cards -->
<div class="kpi-grid">
    <!-- Today's Sales -->
    <div class="kpi-card kpi-purple">
        <div class="kpi-header">
            <div class="kpi-title">Today's Sales (আজকের বিক্রয়)</div>
            <div class="kpi-icon-wrap"><i class="fa-solid fa-cart-shopping"></i></div>
        </div>
        <div class="kpi-value" id="kpi_today_sales">৳ 0.00</div>
        <div class="kpi-footer">
            <span class="trend-badge trend-up"><i class="fa-solid fa-arrow-up"></i> Live</span>
            <span>Real-time billing</span>
        </div>
    </div>

    <!-- Today's Collection -->
    <div class="kpi-card kpi-green">
        <div class="kpi-header">
            <div class="kpi-title">Today's Collection (আজকের আদায়)</div>
            <div class="kpi-icon-wrap"><i class="fa-solid fa-hand-holding-dollar"></i></div>
        </div>
        <div class="kpi-value" id="kpi_today_collection">৳ 0.00</div>
        <div class="kpi-footer">
            <span class="trend-badge trend-up"><i class="fa-solid fa-check"></i> Verified</span>
            <span>Cash & online receipts</span>
        </div>
    </div>

    <!-- Current Stock -->
    <div class="kpi-card kpi-blue">
        <div class="kpi-header">
            <div class="kpi-title">Current Cement Stock (মজুদ)</div>
            <div class="kpi-icon-wrap"><i class="fa-solid fa-cubes-stacked"></i></div>
        </div>
        <div class="kpi-value" id="kpi_current_stock">0 Bags</div>
        <div class="kpi-footer">
            <span class="trend-badge trend-up"><i class="fa-solid fa-warehouse"></i> Godown</span>
            <span>All brands total</span>
        </div>
    </div>

    <!-- Total Retailer Due -->
    <div class="kpi-card kpi-orange">
        <div class="kpi-header">
            <div class="kpi-title">Total Market Due (মোট বকেয়া)</div>
            <div class="kpi-icon-wrap"><i class="fa-solid fa-file-invoice-dollar"></i></div>
        </div>
        <div class="kpi-value text-danger" id="kpi_total_due">৳ 0.00</div>
        <div class="kpi-footer">
            <span class="trend-badge trend-down"><i class="fa-solid fa-triangle-exclamation"></i> Market</span>
            <span>Receivable from retailers</span>
        </div>
    </div>
</div>

<!-- Row 2: KPI Financial Cards -->
<div class="kpi-grid">
    <!-- Month Sales -->
    <div class="kpi-card kpi-cyan">
        <div class="kpi-header">
            <div class="kpi-title">Period Total Sales (মোট বিক্রয়)</div>
            <div class="kpi-icon-wrap"><i class="fa-solid fa-chart-simple"></i></div>
        </div>
        <div class="kpi-value" id="kpi_month_sales">৳ 0.00</div>
        <div class="kpi-footer">
            <span>Invoiced volume</span>
        </div>
    </div>

    <!-- Gross Profit -->
    <div class="kpi-card kpi-green">
        <div class="kpi-header">
            <div class="kpi-title">Gross Profit (মোট লাভ)</div>
            <div class="kpi-icon-wrap"><i class="fa-solid fa-sack-dollar"></i></div>
        </div>
        <div class="kpi-value text-success" id="kpi_gross_profit">৳ 0.00</div>
        <div class="kpi-footer">
            <span>Sales minus COGS</span>
        </div>
    </div>

    <!-- Commission Income -->
    <div class="kpi-card kpi-purple">
        <div class="kpi-header">
            <div class="kpi-title">Commission Income (কমিশন আয়)</div>
            <div class="kpi-icon-wrap"><i class="fa-solid fa-coins"></i></div>
        </div>
        <div class="kpi-value text-primary-light" id="kpi_commission_income">৳ 0.00</div>
        <div class="kpi-footer">
            <span>Target incentive bonus</span>
        </div>
    </div>

    <!-- Net Profit -->
    <div class="kpi-card kpi-green">
        <div class="kpi-header">
            <div class="kpi-title">Net Profit (নিট লাভ)</div>
            <div class="kpi-icon-wrap"><i class="fa-solid fa-money-bill-trend-up"></i></div>
        </div>
        <div class="kpi-value text-cyan" id="kpi_net_profit">৳ 0.00</div>
        <div class="kpi-footer">
            <span>Gross + All Commission + Others Income - Expense</span>
        </div>
    </div>
</div>

<!-- Main Analytics & Charts Section -->
<div class="row g-4 mb-4">
    <!-- Chart Card -->
    <div class="col-lg-8">
        <div class="dark-card h-100">
            <div class="card-header-clean">
                <div class="card-title-clean">
                    <i class="fa-solid fa-chart-line text-primary-light"></i>
                    <span>Sales & Collection Overview (বিক্রয় ও আদায় গ্রাফ)</span>
                </div>
            </div>
            <div class="chart-container-canvas" style="height: 320px; position: relative;">
                <canvas id="salesAnalyticsChart"></canvas>
            </div>
        </div>
    </div>

    <!-- Profit Overview Breakdown Card -->
    <div class="col-lg-4">
        <div class="dark-card h-100 d-flex flex-column justify-content-between">
            <div>
                <div class="card-header-clean">
                    <div class="card-title-clean">
                        <i class="fa-solid fa-calculator text-cyan"></i>
                        <span>Profit Overview (লাভ-ক্ষতি হিসাব)</span>
                    </div>
                </div>

                <div class="profit-breakdown-box">
                    <div class="profit-item">
                        <span class="text-secondary">Gross Profit (Sales - COGS):</span>
                        <span class="fw-bold" style="color: var(--text-primary);" id="po_gross_profit">৳ 0.00</span>
                    </div>
                    <div class="profit-item">
                        <span class="text-secondary">Commission Income (+):</span>
                        <span class="text-primary-light fw-bold" id="po_commission">৳ 0.00</span>
                    </div>
                    <div class="profit-item">
                        <span class="text-secondary">Operating Expenses (-):</span>
                        <span class="text-danger fw-bold" id="po_expense">৳ 0.00</span>
                    </div>
                    <div class="profit-item net-profit">
                        <span style="color: var(--text-primary);">Net Business Profit:</span>
                        <span class="text-cyan fw-bold fs-5" id="po_net_profit">৳ 0.00</span>
                    </div>
                </div>
            </div>

            <!-- Target Quick Overview -->
            <div class="p-3 rounded mt-3" style="background: var(--bg-input); border: 1px solid var(--border-color);">
                <div class="d-flex justify-content-between align-items-center mb-1">
                    <span class="small text-secondary fw-bold text-uppercase">Monthly Target Achievement</span>
                    <span class="badge-custom badge-primary" id="target_ach_percent">0%</span>
                </div>
                <div class="d-flex justify-content-between small text-secondary">
                    <span>Sold: <strong style="color: var(--text-primary);" id="target_actual_display">0 Bags</strong></span>
                    <span>Target: <strong style="color: var(--text-primary);" id="target_qty_display">0 Bags</strong></span>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Bottom Section: Recent Sales & Top Products -->
<div class="row g-4">
    <!-- Recent Sales Table -->
    <div class="col-lg-8">
        <div class="dark-card">
            <div class="card-header-clean">
                <div class="card-title-clean">
                    <i class="fa-solid fa-clock-rotate-left text-primary-light"></i>
                    <span>Recent Sales (সাম্প্রতিক বিক্রয়)</span>
                </div>
                <a href="<?php echo BASE_URL; ?>/modules/sales/index.php" class="btn btn-secondary-custom btn-sm">
                    View All Sales
                </a>
            </div>

            <div class="table-responsive">
                <table class="dark-table table-stack">
                    <thead>
                        <tr>
                            <th>Invoice</th>
                            <th>Retailer</th>
                            <th class="text-end">Total (৳)</th>
                            <th class="text-end">Due (৳)</th>
                            <th class="text-center">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($recentSales)): ?>
                            <tr>
                                <td colspan="5" class="text-center text-muted py-4">No recent sales records.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($recentSales as $rs): ?>
                                <tr>
                                    <td class="fw-bold text-info" data-label="Invoice">
                                        <a href="<?php echo BASE_URL; ?>/modules/sales/invoice.php?id=<?php echo $rs['id']; ?>" class="text-info">
                                            <?php echo htmlspecialchars($rs['invoice_no']); ?>
                                        </a>
                                    </td>
                                    <td class="fw-bold" style="color: var(--text-primary);" data-label="Retailer"><?php echo htmlspecialchars($rs['retailer_name']); ?></td>
                                    <td class="text-end" style="color: var(--text-primary);" data-label="Total (৳)"><?php echo formatBDT($rs['total_amount']); ?></td>
                                    <td class="text-end fw-bold text-danger" data-label="Due (৳)"><?php echo formatBDT($rs['due_amount']); ?></td>
                                    <td class="text-center" data-label="Status">
                                        <span class="badge-custom <?php echo ($rs['payment_status'] === 'paid') ? 'badge-success' : (($rs['payment_status'] === 'partial') ? 'badge-warning' : 'badge-danger'); ?>">
                                            <?php echo ucfirst($rs['payment_status']); ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Top Selling Brands -->
    <div class="col-lg-4">
        <div class="dark-card">
            <div class="card-header-clean">
                <div class="card-title-clean">
                    <i class="fa-solid fa-trophy text-warning"></i>
                    <span>Top Selling Cement</span>
                </div>
            </div>

            <?php if (empty($topProducts)): ?>
                <div class="text-center text-muted py-4">No sales data available.</div>
            <?php else: ?>
                <?php foreach ($topProducts as $tp): ?>
                    <div class="stock-item">
                        <div class="stock-item-header">
                            <div>
                                <span class="fw-bold" style="color: var(--text-primary);"><?php echo htmlspecialchars($tp['name']); ?></span>
                                <div class="text-secondary" style="font-size: 0.72rem;"><?php echo htmlspecialchars($tp['company_name']); ?></div>
                            </div>
                            <span class="fw-bold text-cyan"><?php echo number_format($tp['total_bags']); ?> Bags</span>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Chart.js and Dashboard Controller -->
<script src="<?php echo BASE_URL; ?>/assets/js/dashboard.js?v=<?php echo APP_VERSION; ?>"></script>

<script>
/*
 * Target Achievement Protection
 *
 * The dashboard.js may calculate:
 *     Actual Bags / Target Bags × 100
 *
 * Once the target reaches 100%, the display must stay at 100%.
 *
 * Example:
 * Target = 1000
 * Sold   = 1020
 * Raw    = 102%
 * Display = 100%
 */
(function () {

    function capTargetAchievement() {

        const targetElement = document.getElementById('target_ach_percent');

        if (!targetElement) {
            return;
        }

        const text = targetElement.textContent || '';
        const match = text.match(/[\d.]+/);

        if (!match) {
            return;
        }

        const percentage = parseFloat(match[0]);

        if (!isNaN(percentage) && percentage > 100) {
            targetElement.textContent = '100%';
        }
    }

    // Check immediately
    capTargetAchievement();

    // dashboard.js may update this value after AJAX.
    // Watch the element and cap it whenever it changes.
    const targetElement = document.getElementById('target_ach_percent');

    if (targetElement) {

        const observer = new MutationObserver(function () {
            capTargetAchievement();
        });

        observer.observe(targetElement, {
            childList: true,
            characterData: true,
            subtree: true
        });

    }

})();
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>