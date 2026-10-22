<?php
/**
 * Maruf Traders - User Profile & Password Change
 */

define('APP_INIT', true);
$pageTitle = 'My Profile';
$breadcrumb = 'Profile';
$activeMenu = 'users';

require_once __DIR__ . '/../../includes/header.php';

$userId = (int)$_SESSION['user_id'];
$db = Database::getConnection();

$stmt = $db->prepare("SELECT u.*, r.name as role_name FROM users u JOIN roles r ON u.role_id = r.id WHERE u.id = :id");
$stmt->execute([':id' => $userId]);
$profile = $stmt->fetch();
?>

<div class="page-header-container">
    <div>
        <h2 class="page-title">My Profile & Security (প্রোফাইল ও নিরাপত্তা)</h2>
        <div class="page-subtitle">View your account credentials and update your system login password.</div>
    </div>
</div>

<div class="row g-4">
    <!-- Profile Info Card -->
    <div class="col-lg-5">
        <div class="dark-card text-center py-5">
            <div class="user-avatar mx-auto mb-3" style="width: 80px; height: 80px; font-size: 2.2rem;">
                <?php echo strtoupper(substr($profile['name'], 0, 1)); ?>
            </div>
            <h4 class="fw-bold text-white mb-1"><?php echo htmlspecialchars($profile['name']); ?></h4>
            <div class="badge-custom badge-primary mb-3">
                <i class="fa-solid fa-shield me-1"></i> <?php echo htmlspecialchars($profile['role_name']); ?>
            </div>
            <div class="text-secondary small font-monospace">@<?php echo htmlspecialchars($profile['username']); ?></div>
            <div class="text-secondary small mt-1"><?php echo htmlspecialchars($profile['email'] ?: 'No email registered'); ?></div>
            <div class="text-secondary small mt-1">Mobile: <?php echo htmlspecialchars($profile['phone'] ?: '—'); ?></div>
            <hr class="border-secondary border-opacity-25 my-4">
            <div class="text-secondary small">
                Last Login: <strong><?php echo formatDateTime($profile['last_login']); ?></strong>
            </div>
        </div>
    </div>

    <!-- Change Password Form -->
    <div class="col-lg-7">
        <div class="dark-card">
            <div class="card-header-clean">
                <div class="card-title-clean">
                    <i class="fa-solid fa-key text-primary-light"></i>
                    <span>Update Account Password</span>
                </div>
            </div>

            <form id="profilePasswordForm">
                <input type="hidden" name="id" value="<?php echo $profile['id']; ?>">
                <input type="hidden" name="role_id" value="<?php echo $profile['role_id']; ?>">
                <input type="hidden" name="name" value="<?php echo htmlspecialchars($profile['name']); ?>">
                <input type="hidden" name="username" value="<?php echo htmlspecialchars($profile['username']); ?>">
                <input type="hidden" name="email" value="<?php echo htmlspecialchars($profile['email'] ?? ''); ?>">
                <input type="hidden" name="phone" value="<?php echo htmlspecialchars($profile['phone'] ?? ''); ?>">
                <input type="hidden" name="status" value="<?php echo $profile['status']; ?>">
                <?php echo csrfField(); ?>

                <div class="mb-3">
                    <label for="new_password" class="form-label-custom">New Password <span class="required">*</span></label>
                    <input type="password" class="form-control form-control-custom" id="new_password" name="password" required minlength="6" placeholder="At least 6 characters">
                </div>

                <div class="mb-4">
                    <label for="confirm_password" class="form-label-custom">Confirm New Password <span class="required">*</span></label>
                    <input type="password" class="form-control form-control-custom" id="confirm_password" required placeholder="Repeat new password">
                </div>

                <button type="submit" class="btn btn-primary-custom" id="changePassBtn">
                    <i class="fa-solid fa-floppy-disk me-1"></i> Update Password
                </button>
            </form>
        </div>
    </div>
</div>

<script>
function notify(type, message) {
    if (typeof showToast === 'function') {
        showToast(type, message);
    } else {
        alert(message);
    }
}

document.getElementById('profilePasswordForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    const p1 = document.getElementById('new_password').value;
    const p2 = document.getElementById('confirm_password').value;

    if (p1 !== p2) {
        notify('error', 'New passwords do not match!');
        return;
    }

    const btn = document.getElementById('changePassBtn');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span> Updating...';

    const formData = new FormData(this);
    try {
        const res = await fetch(`${window.BASE_URL}/ajax/users/save.php`, {
            method: 'POST',
            body: formData,
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        });
        const result = await res.json();

        if (result.success) {
            notify('success', 'Password updated successfully!');
            document.getElementById('new_password').value = '';
            document.getElementById('confirm_password').value = '';
        } else {
            notify('error', result.message || 'Failed to update password.');
        }
    } catch (e) {
        console.error(e);
        alert('Something went wrong. Check console for details.');
    } finally {
        btn.disabled = false;
        btn.innerHTML = '<i class="fa-solid fa-floppy-disk me-1"></i> Update Password';
    }
});
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>