<?php
/**
 * Maruf Traders - Premium Left Sidebar
 */
defined('APP_INIT') or define('APP_INIT', true);
$activeMenu = $activeMenu ?? '';

$logoPath = getSetting('logo_path');
$logoVer = getSetting('logo_updated_at', '0');
?>
<aside class="app-sidebar">
    <!-- Brand Logo Area -->
   <div class="sidebar-brand">
    <div class="brand-icon" <?php echo $logoPath ? 'style="background: transparent; box-shadow: none; border-radius: 50%; overflow: hidden;"' : ''; ?>>
        <?php if ($logoPath): ?>
            <img src="<?php echo BASE_URL . htmlspecialchars($logoPath) . '?v=' . htmlspecialchars($logoVer); ?>" alt="Logo" style="width: 100%; height: 100%; object-fit: cover; background: transparent; border-radius: 50%;">
        <?php else: ?>
            <span>MT</span>
        <?php endif; ?>
    </div>
    <div class="brand-info">
        <div class="brand-name"><?php echo htmlspecialchars(getSetting('business_name', 'Maruf Traders')); ?></div>
        <div class="brand-subtitle"><?php echo htmlspecialchars(getSetting('business_title', 'Cement Dealership System')); ?></div>
    </div>
</div>

    <!-- Live Menu Search Box -->
    <div class="sidebar-search-box">
        <input type="text" id="sidebarMenuSearch" class="sidebar-search-input" placeholder="🔍 Search menu...">
    </div>

    <!-- Menu Links Wrapper -->
    <div class="sidebar-menu-wrapper">
        <!-- Main Dashboard -->
        <div class="sidebar-nav-item">
            <a href="<?php echo BASE_URL; ?>/modules/dashboard/index.php" class="sidebar-nav-link <?php echo ($activeMenu === 'dashboard') ? 'active' : ''; ?>">
                <i class="fa-solid fa-chart-pie"></i>
                <span class="nav-text" title="Dashboard (ড্যাশবোর্ড)">Dashboard (ড্যাশবোর্ড)</span>
            </a>
        </div>

        <!-- BUSINESS MANAGEMENT -->
        <div class="menu-category-title">Business Management</div>

        <?php if (hasPermission('retailers.manage')): ?>
        <div class="sidebar-nav-item">
            <a href="<?php echo BASE_URL; ?>/modules/retailers/index.php" class="sidebar-nav-link <?php echo ($activeMenu === 'retailers') ? 'active' : ''; ?>">
                <i class="fa-solid fa-users"></i>
                <span class="nav-text" title="Customers / Parties">Customers / Parties</span>
            </a>
        </div>
        <?php endif; ?>

        <?php if (hasPermission('sales.view') || hasPermission('sales.create')): ?>
        <div class="sidebar-nav-item">
            <a href="<?php echo BASE_URL; ?>/modules/sales/index.php" class="sidebar-nav-link <?php echo ($activeMenu === 'sales') ? 'active' : ''; ?>">
                <i class="fa-solid fa-file-invoice-dollar"></i>
                <span class="nav-text" title="Sales (বিক্রয়)">Sales (বিক্রয়)</span>
            </a>
        </div>
        <?php endif; ?>

        <?php if (hasPermission('collections.manage')): ?>
        <div class="sidebar-nav-item">
            <a href="<?php echo BASE_URL; ?>/modules/collections/index.php" class="sidebar-nav-link <?php echo ($activeMenu === 'collections') ? 'active' : ''; ?>">
                <i class="fa-solid fa-hand-holding-dollar"></i>
                <span class="nav-text" title="Collections (আদায়)">Collections (আদায়)</span>
            </a>
        </div>
        <?php endif; ?>

        <?php if (hasPermission('advances.manage')): ?>
        <div class="sidebar-nav-item">
            <a href="<?php echo BASE_URL; ?>/modules/advances/index.php" class="sidebar-nav-link <?php echo ($activeMenu === 'advances') ? 'active' : ''; ?>">
                <i class="fa-solid fa-credit-card"></i>
                <span class="nav-text" title="Advances (অগ্রিম)">Advances (অগ্রিম)</span>
            </a>
        </div>
        <?php endif; ?>

        <!-- INVENTORY -->
        <div class="menu-category-title">Inventory & Stock</div>

        <?php if (hasPermission('companies.manage')): ?>
        <div class="sidebar-nav-item">
            <a href="<?php echo BASE_URL; ?>/modules/companies/index.php" class="sidebar-nav-link <?php echo ($activeMenu === 'companies') ? 'active' : ''; ?>">
                <i class="fa-solid fa-industry"></i>
                <span class="nav-text" title="Companies (কোম্পানি)">Companies (কোম্পানি)</span>
            </a>
        </div>
        <?php endif; ?>

        <?php if (hasPermission('products.manage')): ?>
        <div class="sidebar-nav-item">
            <a href="<?php echo BASE_URL; ?>/modules/products/index.php" class="sidebar-nav-link <?php echo ($activeMenu === 'products') ? 'active' : ''; ?>">
                <i class="fa-solid fa-cubes-stacked"></i>
                <span class="nav-text" title="Products (পণ্য)">Products (পণ্য)</span>
            </a>
        </div>
        <?php endif; ?>

        <?php if (hasPermission('receives.manage')): ?>
        <div class="sidebar-nav-item">
            <a href="<?php echo BASE_URL; ?>/modules/receives/index.php" class="sidebar-nav-link <?php echo ($activeMenu === 'receives') ? 'active' : ''; ?>">
                <i class="fa-solid fa-truck-ramp-box"></i>
                <span class="nav-text" title="Purchases / Receive">Purchases / Receive</span>
            </a>
        </div>
        <?php endif; ?>

        <div class="sidebar-nav-item">
            <a href="<?php echo BASE_URL; ?>/modules/stock/index.php" class="sidebar-nav-link <?php echo ($activeMenu === 'stock') ? 'active' : ''; ?>">
                <i class="fa-solid fa-boxes-stacked"></i>
                <span class="nav-text" title="Stock Ledger (স্টক লেজার)">Stock Ledger (স্টক লেজার)</span>
            </a>
        </div>

        <!-- FINANCIAL MANAGEMENT -->
        <div class="menu-category-title">Financial Management</div>

        <?php if (hasPermission('expenses.manage')): ?>
        <div class="sidebar-nav-item">
            <a href="<?php echo BASE_URL; ?>/modules/expenses/index.php" class="sidebar-nav-link <?php echo ($activeMenu === 'expenses') ? 'active' : ''; ?>">
                <i class="fa-solid fa-wallet"></i>
                <span class="nav-text" title="Expenses (খরচ)">Expenses (খরচ)</span>
            </a>
        </div>
        <?php endif; ?>

        
        <?php if (hasPermission('other_income.manage')): ?>
        <div class="sidebar-nav-item">
            <a href="<?php echo BASE_URL; ?>/modules/other_income/index.php" class="sidebar-nav-link <?php echo ($activeMenu === 'other_income') ? 'active' : ''; ?>">
                <i class="fa-solid fa-coins"></i>
                <span class="nav-text" title="Other Income (অন্যান্য আয়)">Other Income (অন্যান্য আয়)</span>
            </a>
        </div>
        <?php endif; ?>

        <?php if (hasPermission('company_payments.manage')): ?>
        <div class="sidebar-nav-item">
            <a href="<?php echo BASE_URL; ?>/modules/company_payments/index.php" class="sidebar-nav-link <?php echo ($activeMenu === 'company_payments') ? 'active' : ''; ?>">
                <i class="fa-solid fa-building-columns"></i>
                <span class="nav-text" title="Company Payment (কোম্পানি পেমেন্ট)">Company Payment (কোম্পানি পেমেন্ট)</span>
            </a>
        </div>
        <?php endif; ?>

        <?php if (hasPermission('targets.manage')): ?>
        <div class="sidebar-nav-item">
            <a href="<?php echo BASE_URL; ?>/modules/targets/index.php" class="sidebar-nav-link <?php echo ($activeMenu === 'targets') ? 'active' : ''; ?>">
                <i class="fa-solid fa-bullseye"></i>
                <span class="nav-text" title="Target & Commission (টার্গেট ও কমিশন)">Target & Commission (টার্গেট ও কমিশন)</span>
            </a>
        </div>
        <?php endif; ?>

        <?php if (hasPermission('commission.manage')): ?>
        <div class="sidebar-nav-item">
            <a href="<?php echo BASE_URL; ?>/modules/commission_engine/index.php" class="sidebar-nav-link <?php echo ($activeMenu === 'commission_engine') ? 'active' : ''; ?>">
                <i class="fa-solid fa-sitemap"></i><span class="nav-text">Commission Builder</span>
            </a>
        </div>
        <div class="sidebar-nav-item">
            <a href="<?php echo BASE_URL; ?>/modules/commission_engine/reconciliation.php" class="sidebar-nav-link <?php echo ($activeMenu === 'commission_reconciliation') ? 'active' : ''; ?>">
                <i class="fa-solid fa-scale-balanced"></i><span class="nav-text">Reconciliation & Settle</span>
            </a>
        </div>
        <?php endif; ?>

        <?php if (hasPermission('commission_periods.manage')): ?>
        <div class="sidebar-nav-item">
            <a href="<?php echo BASE_URL; ?>/modules/commission_periods/index.php" class="sidebar-nav-link <?php echo ($activeMenu === 'commission_periods') ? 'active' : ''; ?>">
                <i class="fa-solid fa-calendar-check"></i>
                <span class="nav-text" title="Commission (3M/Yearly) (কমিশন - ৩ মাস/বার্ষিক)">Commission (3M/Yearly) (কমিশন - ৩ মাস/বার্ষিক)</span>
            </a>
        </div>
        <?php endif; ?>


     
<!-- REPORTS -->
<?php if (hasPermission('reports.financial')): ?>
<div class="menu-category-title">Reports & Analytics</div>

<div class="sidebar-nav-item">
    <a href="<?php echo BASE_URL; ?>/modules/reports/daily.php" class="sidebar-nav-link <?php echo ($activeMenu === 'report_daily') ? 'active' : ''; ?>">
        <i class="fa-solid fa-calendar-day"></i>
        <span class="nav-text" title="Daily Report (দৈনিক রিপোর্ট)">Daily Report (দৈনিক রিপোর্ট)</span>
    </a>
</div>

<div class="sidebar-nav-item">
    <a href="<?php echo BASE_URL; ?>/modules/reports/monthly.php" class="sidebar-nav-link <?php echo ($activeMenu === 'report_monthly') ? 'active' : ''; ?>">
        <i class="fa-solid fa-chart-line"></i>
        <span class="nav-text" title="Monthly Report (মাসিক রিপোর্ট)">Monthly Report (মাসিক রিপোর্ট)</span>
    </a>
</div>

<div class="sidebar-nav-item">
    <a href="<?php echo BASE_URL; ?>/modules/reports/yearly.php" class="sidebar-nav-link <?php echo ($activeMenu === 'report_yearly') ? 'active' : ''; ?>">
        <i class="fa-solid fa-calendar"></i>
        <span class="nav-text" title="Yearly Report (বার্ষিক রিপোর্ট)">Yearly Report (বার্ষিক রিপোর্ট)</span>
    </a>
</div>

<div class="sidebar-nav-item">
    <a href="<?php echo BASE_URL; ?>/modules/reports/commission.php" class="sidebar-nav-link <?php echo ($activeMenu === 'report_commission') ? 'active' : ''; ?>">
        <i class="fa-solid fa-receipt"></i>
        <span class="nav-text" title="Commission Reports (কমিশন রিপোর্ট)">Commission Reports (কমিশন রিপোর্ট)</span>
    </a>
</div>

<div class="sidebar-nav-item">
    <a href="<?php echo BASE_URL; ?>/modules/reports/company.php" class="sidebar-nav-link <?php echo ($activeMenu === 'report_company') ? 'active' : ''; ?>">
        <i class="fa-solid fa-building"></i>
        <span class="nav-text" title="Company Report (কোম্পানি রিপোর্ট)">Company Report (কোম্পানি রিপোর্ট)</span>
    </a>
</div>

     <div class="sidebar-nav-item">
            <a href="<?php echo BASE_URL; ?>/modules/reports/stock.php" class="sidebar-nav-link <?php echo ($activeMenu === 'report_stock') ? 'active' : ''; ?>">
                <i class="fa-solid fa-boxes-stacked"></i>
                <span class="nav-text" title="Stock Report (স্টক রিপোর্ট)">Stock Report (স্টক রিপোর্ট)</span>
            </a>
        </div>

<div class="sidebar-nav-item">
    <a href="<?php echo BASE_URL; ?>/modules/reports/profit.php" class="sidebar-nav-link <?php echo ($activeMenu === 'report_profit') ? 'active' : ''; ?>">
        <i class="fa-solid fa-sack-dollar"></i>
        <span class="nav-text" title="Profit & Loss (লাভ ও ক্ষতি)">Profit & Loss (লাভ ও ক্ষতি)</span>
    </a>
</div>

<div class="sidebar-nav-item">
    <a href="<?php echo BASE_URL; ?>/modules/retailers/due_report.php" class="sidebar-nav-link <?php echo ($activeMenu === 'report_retailer') ? 'active' : ''; ?>">
        <i class="fa-solid fa-file-invoice"></i>
        <span class="nav-text" title="Retailer Due Report (রিটেইলার বকেয়া রিপোর্ট)">Retailer Due Report (রিটেইলার বকেয়া রিপোর্ট)</span>
    </a>
</div>

<?php endif; ?>



        <!-- SYSTEM -->
        <div class="menu-category-title">System & Administration</div>

        <?php if (!empty($_SESSION['user_id'])): ?>
        <?php $platformCheck = Database::getConnection()->prepare('SELECT is_platform_admin FROM users WHERE id = :id'); $platformCheck->execute([':id' => $_SESSION['user_id']]); ?>
        <?php if ((bool)$platformCheck->fetchColumn()): ?>
        <div class="sidebar-nav-item">
            <a href="<?php echo BASE_URL; ?>/modules/platform/index.php" class="sidebar-nav-link <?php echo ($activeMenu === 'platform') ? 'active' : ''; ?>">
                <i class="fa-solid fa-building-shield"></i><span class="nav-text">SaaS Platform Admin</span>
            </a>
        </div>
        <?php endif; ?>
        <?php endif; ?>

        <?php if (hasPermission('closing.manage')): ?>
        <div class="sidebar-nav-item">
            <a href="<?php echo BASE_URL; ?>/modules/monthly_closing/index.php" class="sidebar-nav-link <?php echo ($activeMenu === 'closing') ? 'active' : ''; ?>">
                <i class="fa-solid fa-lock"></i>
                <span class="nav-text" title="Monthly Closing (মাসিক ক্লোজিং)">Monthly Closing (মাসিক ক্লোজিং)</span>
            </a>
        </div>
        <?php endif; ?>

        <?php if (hasPermission('users.manage')): ?>
        <div class="sidebar-nav-item">
            <a href="<?php echo BASE_URL; ?>/modules/users/index.php" class="sidebar-nav-link <?php echo ($activeMenu === 'users') ? 'active' : ''; ?>">
                <i class="fa-solid fa-user-shield"></i>
                <span class="nav-text" title="User Management (ইউজার ম্যানেজমেন্ট)">User Management (ইউজার ম্যানেজমেন্ট)</span>
            </a>
        </div>
        <?php endif; ?>

        <?php if (hasPermission('settings.manage')): ?>
        <div class="sidebar-nav-item">
            <a href="<?php echo BASE_URL; ?>/modules/settings/index.php" class="sidebar-nav-link <?php echo ($activeMenu === 'settings') ? 'active' : ''; ?>">
                <i class="fa-solid fa-sliders"></i>
                <span class="nav-text" title="Business Settings (বিজনেস সেটিংস)">Business Settings (বিজনেস সেটিংস)</span>
            </a>
        </div>
        <?php endif; ?>

        <div class="sidebar-nav-item">
            <a href="<?php echo BASE_URL; ?>/modules/subscription/index.php" class="sidebar-nav-link <?php echo ($activeMenu === 'subscription') ? 'active' : ''; ?>">
                <i class="fa-solid fa-receipt"></i><span class="nav-text">My Subscription</span>
            </a>
        </div>

        <?php if (hasPermission('audit.view')): ?>
        <div class="sidebar-nav-item">
            <a href="<?php echo BASE_URL; ?>/modules/audit/index.php" class="sidebar-nav-link <?php echo ($activeMenu === 'audit') ? 'active' : ''; ?>">
                <i class="fa-solid fa-shield-halved"></i>
                <span class="nav-text" title="Audit Logs (অডিট লগ)">Audit Logs (অডিট লগ)</span>
            </a>
        </div>
        <?php endif; ?>

        <?php if (hasPermission('backup.manage')): ?>
        <div class="sidebar-nav-item">
            <a href="<?php echo BASE_URL; ?>/modules/backup/index.php" class="sidebar-nav-link <?php echo ($activeMenu === 'backup') ? 'active' : ''; ?>">
                <i class="fa-solid fa-cloud-arrow-down"></i>
                <span class="nav-text" title="Database Backup (ব্যাকআপ)">Database Backup (ব্যাকআপ)</span>
            </a>
        </div>
        <?php endif; ?>


        
    </div>
</aside>
