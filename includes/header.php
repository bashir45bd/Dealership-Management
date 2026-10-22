<?php
/**
 * Maruf Traders - Master Page Header
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/auth_check.php';
require_once __DIR__ . '/permission_check.php';

$pageTitle = $pageTitle ?? 'Dashboard';
$currentUser = [
    'id'       => $_SESSION['user_id'] ?? 1,
    'name'     => $_SESSION['user_name'] ?? 'Administrator',
    'username' => $_SESSION['user_username'] ?? 'admin',
    'role'     => $_SESSION['user_role_name'] ?? 'User',
    'avatar'   => strtoupper(substr($_SESSION['user_name'] ?? 'A', 0, 1))
];
$workspaceName = getSetting('business_name', APP_NAME);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?php echo htmlspecialchars(getCsrfToken()); ?>">
    <title><?php echo htmlspecialchars($pageTitle); ?> — <?php echo htmlspecialchars($workspaceName); ?></title>

    <!-- Theme: apply saved preference BEFORE any CSS paints, avoids flash -->
    <script>
        (function () {
            var t = localStorage.getItem('mt_theme') || 'dark';
            document.documentElement.setAttribute('data-theme', t);
        })();
    </script>

    <!-- Bootstrap 5 CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <!-- Font Awesome 6 -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <!-- SweetAlert2 CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">

    <!-- Custom Dark + Light Design System -->
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/style.css?v=<?php echo APP_VERSION; ?>">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/dashboard.css?v=<?php echo APP_VERSION; ?>">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/responsive.css?v=<?php echo APP_VERSION; ?>">

    <script>
        window.BASE_URL = "<?php echo BASE_URL; ?>";
    </script>
    <script src="<?php echo BASE_URL; ?>/assets/js/theme.js?v=<?php echo APP_VERSION; ?>"></script>
    <script defer src="<?php echo BASE_URL; ?>/assets/js/language.js?v=<?php echo APP_VERSION; ?>"></script>
</head>
<body>
    <div class="app-wrapper">
        <!-- Sidebar Navigation -->
        <?php require_once __DIR__ . '/sidebar.php'; ?>

        <!-- Main Content Area -->
        <div class="main-content">
            <!-- Top Navbar -->
            <?php require_once __DIR__ . '/navbar.php'; ?>
            
            <!-- Body Container -->
            <div class="content-body">
