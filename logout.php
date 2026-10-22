<?php
/**
 * Maruf Traders - Logout Handler
 */

define('APP_INIT', true);
require_once __DIR__ . '/config/app.php';

if (!empty($_SESSION['user_id'])) {
    logAudit('auth', 'logout', (string)$_SESSION['user_id'], null, ['username' => $_SESSION['user_username'] ?? 'unknown']);
}

// Unset all session variables
$_SESSION = [];

// Destroy session cookie
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Destroy session
session_destroy();

header("Location: " . BASE_URL . "/login.php");
exit;
