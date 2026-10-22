<?php
/**
 * Maruf Traders - Application Configuration
 */

// Define execution entry guard
defined('APP_INIT') or define('APP_INIT', true);

// Set default timezone for Bangladesh
date_default_timezone_set('Asia/Dhaka');

// Error reporting settings
error_reporting(E_ALL & ~E_DEPRECATED & ~E_STRICT);
ini_set('display_errors', '0');
ini_set('log_errors', '1');

// Start secure session if not started
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', '1');
    ini_set('session.use_only_cookies', '1');
    // Enable cookie_secure if HTTPS
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
        ini_set('session.cookie_secure', '1');
    }
    session_start();
}

// Application Metadata
define('APP_NAME', 'Dealership SaaS');
define('APP_TAGLINE', 'Multi-Company Dealership Management & Accounting SaaS');
define('APP_VERSION', '3.1.0');
define('BUSINESS_LOCATION', 'Bangladesh');
define('DEFAULT_CURRENCY', 'BDT');
define('DEFAULT_CURRENCY_SYMBOL', '৳');

// Dynamic & Accurate Base URL Calculation
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443)) ? "https://" : "http://";
$hostName = $_SERVER['HTTP_HOST'] ?? 'localhost';

// Get clean script directory relative to document root
$scriptName = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
$scriptDir = dirname($scriptName);

// Normalize base directory
$baseDir = ($scriptDir === '/' || $scriptDir === '\\') ? '' : rtrim($scriptDir, '/');

// Trim subdirectories if app config is required from inside internal modules
if (preg_match('#/(modules|ajax|admin|config|includes|assets|database)(/.*)?$#i', $baseDir, $matches)) {
    $baseDir = substr($baseDir, 0, strpos($baseDir, $matches[1]));
    $baseDir = rtrim($baseDir, '/');
}

define('BASE_URL', $protocol . $hostName . $baseDir);
define('ROOT_PATH', realpath(__DIR__ . '/..'));
// Keep payment evidence outside the public web root. Set PRIVATE_STORAGE_PATH
// in deployment when a different protected volume is required.
define('PRIVATE_STORAGE_PATH', getenv('PRIVATE_STORAGE_PATH') ?: dirname(ROOT_PATH, 2) . DIRECTORY_SEPARATOR . 'dealership-private-storage');

// Autoload core configs and helpers
require_once __DIR__ . '/constants.php';
require_once __DIR__ . '/database.php';
require_once ROOT_PATH . '/includes/csrf.php';
require_once ROOT_PATH . '/includes/functions.php';
require_once ROOT_PATH . '/includes/services/TenantContext.php';
require_once ROOT_PATH . '/includes/services/CommissionEngine.php';
require_once ROOT_PATH . '/includes/services/SubscriptionService.php';

// Public pages do not share the application header. Attach the bilingual UI
// layer centrally so landing, registration and customer login stay consistent.
$publicLanguagePage = basename($_SERVER['SCRIPT_NAME'] ?? '');
if (in_array($publicLanguagePage, ['index.php','register.php','login.php','pricing.php'], true) || str_ends_with(str_replace('\\','/',$_SERVER['SCRIPT_NAME'] ?? ''), '/admin/manage.php')) {
    ob_start(function (string $html): string {
        if (str_contains($html, '/assets/js/language.js')) return $html;
        $tag = '<script src="' . BASE_URL . '/assets/js/language.js?v=' . APP_VERSION . '"></script>';
        return str_replace('</body>', $tag . '</body>', $html);
    });
}
