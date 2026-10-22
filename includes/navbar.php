<?php
/**
 * Maruf Traders - Top Sticky Navbar
 */
defined('APP_INIT') or define('APP_INIT', true);
$breadcrumb = $breadcrumb ?? 'Dashboard';
?>
<header class="app-navbar">
    <div class="navbar-left">
        <button type="button" class="sidebar-toggle-btn" id="sidebarToggle" title="Toggle Navigation">
            <i class="fa-solid fa-bars"></i>
        </button>
        <div class="breadcrumb-area">
            <a href="<?php echo BASE_URL; ?>/modules/dashboard/index.php"><i class="fa-solid fa-house me-1"></i> MT</a>
            <i class="fa-solid fa-chevron-right text-muted" style="font-size: 0.7rem;"></i>
            <span class="active"><?php echo htmlspecialchars($breadcrumb); ?></span>
        </div>
    </div>

    <div class="navbar-right">
        <!-- Quick Location Indicator (pulled from settings table) -->
        <div class="d-none d-lg-flex align-items-center gap-2 text-secondary" style="font-size: 0.8rem;">
            <i class="fa-solid fa-location-dot text-danger"></i>
            <span><?php echo htmlspecialchars(getSetting('business_address', 'সুন্দরপুর বাজার, চুনারুঘাট')); ?></span>
        </div>

        <!-- Dark / Light Theme Toggle -->
        <button type="button" class="theme-toggle-btn" id="themeToggleBtn" title="Toggle theme">
            <i class="fa-solid fa-sun"></i>
            <i class="fa-solid fa-moon"></i>
        </button>
        <button type="button" class="theme-toggle-btn" data-language-toggle title="Switch language">বাংলা</button>

        <!-- User Profile Dropdown -->
        <div class="dropdown">
            <div class="user-profile-menu" data-bs-toggle="dropdown" aria-expanded="false">
                <div class="user-avatar">
                    <?php echo $currentUser['avatar']; ?>
                </div>
                <div class="user-meta">
                    <div class="user-name"><?php echo htmlspecialchars($currentUser['name']); ?></div>
                    <div class="user-role-badge"><?php echo htmlspecialchars($currentUser['role']); ?></div>
                </div>
                <i class="fa-solid fa-chevron-down ms-1 text-muted" style="font-size: 0.75rem;"></i>
            </div>
            <ul class="dropdown-menu dropdown-menu-end dropdown-menu-dark-custom shadow">
                <li>
                    <a class="dropdown-item dropdown-item-dark" href="<?php echo BASE_URL; ?>/modules/users/profile.php">
                        <i class="fa-solid fa-user me-2 text-primary-light"></i> My Profile
                    </a>
                </li>
                <?php if (hasPermission('settings.manage')): ?>
                <li>
                    <a class="dropdown-item dropdown-item-dark" href="<?php echo BASE_URL; ?>/modules/settings/index.php">
                        <i class="fa-solid fa-sliders me-2 text-info"></i> System Settings
                    </a>
                </li>
                <?php endif; ?>
                <li><hr class="dropdown-divider dropdown-divider-dark"></li>
                <li>
                    <a class="dropdown-item dropdown-item-dark text-danger" href="<?php echo BASE_URL; ?>/logout.php">
                        <i class="fa-solid fa-right-from-bracket me-2"></i> Logout
                    </a>
                </li>
            </ul>
        </div>
    </div>
</header>
