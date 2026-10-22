<?php
/**
 * Maruf Traders - Role Based Access Control (RBAC) Permission Helper
 */

defined('APP_INIT') or define('APP_INIT', true);

/**
 * Load and cache permissions in user session
 */
function loadUserPermissions(int $roleId): array {
    $db = Database::getConnection();
    $stmt = $db->prepare("SELECT p.slug 
                          FROM permissions p
                          JOIN role_permissions rp ON p.id = rp.permission_id
                          WHERE rp.role_id = :role_id");
    $stmt->execute([':role_id' => $roleId]);
    return $stmt->fetchAll(PDO::FETCH_COLUMN);
}

/**
 * Check if the currently logged in user has a specific permission
 */
function hasPermission(string $permSlug): bool {
    if (empty($_SESSION['user_id'])) {
        return false;
    }

    // Super Admin has all permissions
    if (isset($_SESSION['user_role_slug']) && $_SESSION['user_role_slug'] === ROLE_SUPER_ADMIN) {
        return true;
    }

    if (!isset($_SESSION['user_permissions'])) {
        if (!empty($_SESSION['user_role_id'])) {
            $_SESSION['user_permissions'] = loadUserPermissions((int)$_SESSION['user_role_id']);
        } else {
            $_SESSION['user_permissions'] = [];
        }
    }

    return in_array($permSlug, $_SESSION['user_permissions'], true);
}

/**
 * Require a specific permission; aborts with 403 if unauthorized
 */
function requirePermission(string $permSlug): void {
    if (!hasPermission($permSlug)) {
        if ((defined('IS_AJAX') && IS_AJAX) || (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')) {
            jsonResponse(false, 'Forbidden: You do not have permission to perform this action.', [], 403);
        }

        http_response_code(403);
        echo '<!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <title>403 Forbidden - Maruf Traders</title>
            <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
            <style>
                body { background-color: #0B1020; color: #F8FAFC; display: flex; align-items: center; justify-content: center; height: 100vh; font-family: sans-serif; }
                .error-card { background: #151F36; border: 1px solid rgba(148,163,184,0.12); border-radius: 16px; padding: 40px; text-align: center; max-width: 480px; }
                .btn-purple { background: #8B5CF6; color: #fff; border-radius: 10px; padding: 10px 24px; text-decoration: none; display: inline-block; margin-top: 20px; }
            </style>
        </head>
        <body>
            <div class="error-card">
                <h1 class="display-4 text-danger fw-bold">403</h1>
                <h4 class="mb-3">Access Denied / অনুমতি নেই</h4>
                <p class="text-secondary">You do not have the required permission (<code>' . htmlspecialchars($permSlug) . '</code>) to access this page.</p>
                <a href="' . BASE_URL . '/modules/dashboard/index.php" class="btn-purple">Return to Dashboard</a>
            </div>
        </body>
        </html>';
        exit;
    }
}
