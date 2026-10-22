<?php
/**
 * Maruf Traders - AJAX Toggle User Status
 */

define('APP_INIT', true);
define('IS_AJAX', true);
require_once __DIR__ . '/../../config/app.php';
require_once ROOT_PATH . '/includes/auth_check.php';
require_once ROOT_PATH . '/includes/permission_check.php';

requirePermission('users.manage');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(false, 'Invalid request method.', [], 405);
}

$csrfToken = $_POST['csrf_token'] ?? '';
if (!verifyCsrfToken($csrfToken)) {
    jsonResponse(false, 'Invalid CSRF token.', [], 403);
}

$id = (int)($_POST['id'] ?? 0);
if ($id === 1 || $id === (int)($_SESSION['user_id'] ?? 0)) {
    jsonResponse(false, 'Cannot deactivate the primary super admin account.');
}

$db = Database::getConnection();

try {
    $stmt = $db->prepare("SELECT * FROM users WHERE id = :id");
    $stmt->execute([':id' => $id]);
    $user = $stmt->fetch();

    if (!$user) jsonResponse(false, 'User not found.');

    $newStatus = ($user['status'] === 'active') ? 'inactive' : 'active';
    $uStmt = $db->prepare("UPDATE users SET status = :status WHERE id = :id");
    $uStmt->execute([':status' => $newStatus, ':id' => $id]);

    logAudit('users', 'toggle_status', $user['username'], ['status' => $user['status']], ['status' => $newStatus]);

    jsonResponse(true, "User {$user['username']} status updated to {$newStatus}.", ['status' => $newStatus]);
} catch (Exception $e) {
    jsonResponse(false, 'Failed to update user status: ' . $e->getMessage(), [], 500);
}
