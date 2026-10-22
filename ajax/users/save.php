<?php
/**
 * Maruf Traders - AJAX Save / Create System User
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

$id = !empty($_POST['id']) ? (int)$_POST['id'] : null;
$roleId = (int)($_POST['role_id'] ?? 0);
$name = trim($_POST['name'] ?? '');
$username = strtolower(trim($_POST['username'] ?? ''));
$email = trim($_POST['email'] ?? '');
$phone = trim($_POST['phone'] ?? '');
$password = trim($_POST['password'] ?? '');
$status = in_array($_POST['status'] ?? 'active', ['active', 'inactive', 'suspended']) ? $_POST['status'] : 'active';

if (empty($name)) jsonResponse(false, 'Full Name is required.');
if (empty($username)) jsonResponse(false, 'Username is required.');
if (!$roleId) jsonResponse(false, 'Role is required.');

$db = Database::getConnection();
$tenantId = TenantContext::id();
TenantContext::requireWritable();

try {
    $db->beginTransaction();

    if ($id) {
        // Update user
        $stmt = $db->prepare("SELECT * FROM users WHERE id = :id AND tenant_id = :tenant FOR UPDATE");
        $stmt->execute([':id' => $id, ':tenant' => $tenantId]);
        $oldUser = $stmt->fetch();

        if (!$oldUser) throw new Exception("User not found.");

        // Check unique username
        $chk = $db->prepare("SELECT id FROM users WHERE username = :u AND tenant_id = :tenant AND id != :id");
        $chk->execute([':u' => $username, ':tenant' => $tenantId, ':id' => $id]);
        if ($chk->fetch()) throw new Exception("Username '{$username}' is already taken.");

        if (!empty($password)) {
            $hashed = password_hash($password, PASSWORD_BCRYPT);
            $uStmt = $db->prepare("UPDATE users 
                                   SET role_id = :role_id, name = :name, username = :username, 
                                       email = :email, phone = :phone, password = :pass, status = :status 
                                   WHERE id = :id");
            $uStmt->execute([
                ':role_id'  => $roleId,
                ':name'     => $name,
                ':username' => $username,
                ':email'    => $email,
                ':phone'    => $phone,
                ':pass'     => $hashed,
                ':status'   => $status,
                ':id'       => $id
            ]);
        } else {
            $uStmt = $db->prepare("UPDATE users 
                                   SET role_id = :role_id, name = :name, username = :username, 
                                       email = :email, phone = :phone, status = :status 
                                   WHERE id = :id");
            $uStmt->execute([
                ':role_id'  => $roleId,
                ':name'     => $name,
                ':username' => $username,
                ':email'    => $email,
                ':phone'    => $phone,
                ':status'   => $status,
                ':id'       => $id
            ]);
        }

        logAudit('users', 'update', $username, $oldUser, ['name' => $name, 'role_id' => $roleId, 'status' => $status]);

        $db->commit();
        jsonResponse(true, "User '{$username}' updated successfully.");

    } else {
        SubscriptionService::assertCanCreate($db, 'user');
        // Create user
        if (empty($password)) throw new Exception("Password is required for new user.");

        $chk = $db->prepare("SELECT id FROM users WHERE username = :u AND tenant_id = :tenant");
        $chk->execute([':u' => $username, ':tenant' => $tenantId]);
        if ($chk->fetch()) throw new Exception("Username '{$username}' is already taken.");

        $hashed = password_hash($password, PASSWORD_BCRYPT);

        $insStmt = $db->prepare("INSERT INTO users 
            (tenant_id, role_id, name, username, email, phone, password, status) 
            VALUES (:tenant, :role_id, :name, :username, :email, :phone, :pass, :status)");

        $insStmt->execute([
            ':tenant'   => $tenantId, ':role_id'  => $roleId,
            ':name'     => $name,
            ':username' => $username,
            ':email'    => $email,
            ':phone'    => $phone,
            ':pass'     => $hashed,
            ':status'   => $status
        ]);

        $newId = (int)$db->lastInsertId();
        logAudit('users', 'create', $username, null, ['name' => $name, 'role_id' => $roleId, 'status' => $status]);

        $db->commit();
        jsonResponse(true, "User '{$username}' created successfully.", ['id' => $newId]);
    }
} catch (Exception $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    jsonResponse(false, 'User save failed: ' . $e->getMessage(), [], 400);
}
