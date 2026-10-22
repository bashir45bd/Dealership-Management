<?php
/**
 * Maruf Traders - CSRF Protection Helper
 */

defined('APP_INIT') or define('APP_INIT', true);

/**
 * Generate and store a CSRF token in session
 *
 * @return string
 */
function generateCsrfToken(): string {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Get current CSRF token
 *
 * @return string
 */
function getCsrfToken(): string {
    return generateCsrfToken();
}

/**
 * Verify submitted CSRF token
 *
 * @param string|null $token
 * @return bool
 */
function verifyCsrfToken(?string $token): bool {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    if (empty($_SESSION['csrf_token']) || empty($token)) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Render a hidden HTML input field containing the CSRF token
 *
 * @return string
 */
function csrfField(): string {
    $token = getCsrfToken();
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($token, ENT_QUOTES, 'UTF-8') . '">';
}
