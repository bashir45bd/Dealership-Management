<?php
/**
 * Maruf Traders - Secure Portal Authentication
 */

define('APP_INIT', true);
require_once __DIR__ . '/config/app.php';

// Redirect if already logged in
if (!empty($_SESSION['user_id'])) {
    header("Location: " . BASE_URL . "/modules/dashboard/index.php");
    exit;
}

$errorMessage = '';
$expiredNotice = isset($_GET['expired']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $csrfToken = $_POST['csrf_token'] ?? '';

    if (!verifyCsrfToken($csrfToken)) {
        $errorMessage = 'Invalid security token (CSRF). Please refresh and try again.';
    } elseif (empty($username) || empty($password)) {
        $errorMessage = 'Please provide both username and password.';
    } else {
        try {
            $db = Database::getConnection();
            $stmt = $db->prepare("SELECT u.*, r.slug as role_slug, r.name as role_name 
                                  FROM users u
                                  JOIN roles r ON u.role_id = r.id
                                  WHERE u.username = :uname AND u.is_platform_admin = 0 LIMIT 1");
            $stmt->execute([':uname' => $username]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password'])) {
                if ($user['status'] !== 'active') {
                    $errorMessage = 'Your account has been deactivated. Please contact the administrator.';
                } else {
                    // Login successful: initialize secure session
                    session_regenerate_id(true);

                    $_SESSION['user_id'] = (int)$user['id'];
                    $_SESSION['user_name'] = $user['name'];
                    $_SESSION['user_username'] = $user['username'];
                    $_SESSION['user_role_id'] = (int)$user['role_id'];
                    $_SESSION['user_role_slug'] = $user['role_slug'];
                    $_SESSION['user_role_name'] = $user['role_name'];
                    // Tenant identity is never taken from a browser field.
                    $_SESSION['tenant_id'] = (int)($user['tenant_id'] ?? 1);
                    $_SESSION['is_platform_admin'] = false;
                    $_SESSION['last_activity'] = time();

                    // Load user permissions into session
                    require_once ROOT_PATH . '/includes/permission_check.php';
                    $_SESSION['user_permissions'] = loadUserPermissions((int)$user['role_id']);

                    // Update user last login
                    $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
                    $uStmt = $db->prepare("UPDATE users SET last_login = NOW(), last_ip = :ip WHERE id = :id");
                    $uStmt->execute([':ip' => $ip, ':id' => $user['id']]);

                    // Audit log
                    logAudit('auth', 'login', (string)$user['id'], null, ['username' => $username, 'status' => 'success']);

                    header("Location: " . BASE_URL . "/modules/dashboard/index.php");
                    exit;
                }
            } else {
                $errorMessage = 'Invalid username or password. Please try again.';
                logAudit('auth', 'failed_login', null, null, ['username' => $username, 'reason' => 'Invalid credentials']);
            }
        } catch (Exception $e) {
            $errorMessage = 'Database connection error. Please ensure MySQL is running.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login — <?php echo APP_NAME; ?></title>

    <!-- Bootstrap 5 CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <!-- Font Awesome 6 -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <!-- Custom Dark Design System -->
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/style.css?v=<?php echo APP_VERSION; ?>">

    <style>
        body {
            background: radial-gradient(circle at top center, #19243D 0%, #0B1020 70%);
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            padding: 20px;
        }

        .login-card {
            background-color: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-lg);
            box-shadow: 0 25px 60px -15px rgba(0, 0, 0, 0.7);
            width: 100%;
            max-width: 440px;
            padding: 40px 36px;
            position: relative;
            overflow: hidden;
        }

        .login-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--primary), var(--cyan));
        }

        .login-brand-icon {
            width: 58px;
            height: 58px;
            background: linear-gradient(135deg, var(--primary), var(--primary-light));
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            font-size: 1.6rem;
            font-weight: 700;
            box-shadow: 0 8px 20px var(--primary-glow);
            margin: 0 auto 16px auto;
        }
    </style>
</head>
<body>
    <div class="login-card">
        <div class="text-center mb-4">
            <div class="login-brand-icon">DS</div>
            <h3 class="fw-bold text-white mb-1">Dealership SaaS</h3>
            <p class="text-secondary" style="font-size: 0.85rem;">Your business workspace</p>
            <div class="badge-custom badge-primary mt-1">
                <i class="fa-solid fa-shield-halved me-1"></i> Secure customer login
            </div>
        </div>

        <?php if ($expiredNotice): ?>
            <div class="alert alert-warning py-2 text-center" style="font-size: 0.85rem; background: var(--warning-bg); border-color: rgba(245,158,11,0.3); color: var(--warning);">
                <i class="fa-solid fa-clock-rotate-left me-1"></i> Session expired due to inactivity. Please login again.
            </div>
        <?php endif; ?>

        <?php if (!empty($errorMessage)): ?>
            <div class="alert alert-danger py-2 text-center" style="font-size: 0.85rem; background: var(--danger-bg); border-color: rgba(239,68,68,0.3); color: var(--danger);">
                <i class="fa-solid fa-circle-exclamation me-1"></i> <?php echo htmlspecialchars($errorMessage); ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="">
            <?php echo csrfField(); ?>

            <div class="mb-3">
                <label for="username" class="form-label-custom">Username / ব্যবহারকারীর নাম <span class="required">*</span></label>
                <div class="input-group">
                    <span class="input-group-text border-secondary border-opacity-25" style="background: #0F172A; color: var(--text-secondary);">
                        <i class="fa-solid fa-user"></i>
                    </span>
                    <input type="text" class="form-control form-control-custom" id="username" name="username" placeholder="e.g. admin" required autofocus autocomplete="username">
                </div>
            </div>

            <div class="mb-4">
                <label for="password" class="form-label-custom">Password / পাসওয়ার্ড <span class="required">*</span></label>
                <div class="input-group">
                    <span class="input-group-text border-secondary border-opacity-25" style="background: #0F172A; color: var(--text-secondary);">
                        <i class="fa-solid fa-lock"></i>
                    </span>
                    <input type="password" class="form-control form-control-custom" id="password" name="password" placeholder="••••••••" required autocomplete="current-password">
                </div>
            </div>

            <button type="submit" class="btn btn-primary-custom w-100 justify-content-center py-2 fs-6">
                <i class="fa-solid fa-right-to-bracket me-2"></i> Sign In to Portal
            </button>
        </form>


    </div>
</body>
</html>
