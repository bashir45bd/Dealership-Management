<?php
/**
 * Maruf Traders - Authentication and Session Check Guard
 */

defined('APP_INIT') or define('APP_INIT', true);

// Verify user session
if (empty($_SESSION['user_id'])) {
    if ((defined('IS_AJAX') && IS_AJAX) || (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')) {
        jsonResponse(false, 'Unauthorized. Please login to continue.', [], 401);
    }
    
    $redirectUrl = (defined('BASE_URL') ? BASE_URL : '') . '/login.php';
    header("Location: {$redirectUrl}");
    exit;
}

// Platform accounts have a separate route and may never enter tenant modules.
if (!empty($_SESSION['is_platform_admin']) && !preg_match('#/(admin|modules/platform|ajax/platform)(/|$)#', $_SERVER['SCRIPT_NAME'] ?? '')) {
    header('Location: ' . BASE_URL . '/admin/');
    exit;
}

// Check session inactivity timeout
$timeoutMinutes = (int)(getSetting('session_timeout_minutes', '120'));
$timeoutSeconds = $timeoutMinutes * 60;

if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > $timeoutSeconds)) {
    // Session expired
    $userId = $_SESSION['user_id'];
    logAudit('auth', 'session_timeout', (string)$userId, null, ['reason' => 'Inactive timeout']);
    
    $_SESSION = [];
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    session_destroy();

    if ((defined('IS_AJAX') && IS_AJAX) || (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')) {
        jsonResponse(false, 'Your session has expired due to inactivity. Please login again.', [], 401);
    }

    $redirectUrl = (defined('BASE_URL') ? BASE_URL : '') . '/login.php?expired=1';
    header("Location: {$redirectUrl}");
    exit;
}

$_SESSION['last_activity'] = time();
