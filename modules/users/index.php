<?php
/**
 * Maruf Traders - User Management Module
 */

define('APP_INIT', true);
$pageTitle = 'User Management';
$breadcrumb = 'Users';
$activeMenu = 'users';

require_once __DIR__ . '/../../includes/header.php';
requirePermission('users.manage');

$db = Database::getConnection();

// Fetch roles
$rStmt = $db->query("SELECT * FROM roles ORDER BY id ASC");
$roles = $rStmt->fetchAll();

// Fetch users
$stmt = $db->query("SELECT u.*, r.name as role_name, r.slug as role_slug 
                    FROM users u 
                    JOIN roles r ON u.role_id = r.id 
                    ORDER BY u.id ASC");
$users = $stmt->fetchAll();
?>

<div class="page-header-container no-print">
    <div>
        <h2 class="page-title">User Management & Security Roles (ব্যবহারকারী ব্যবস্থাপনা)</h2>
        <div class="page-subtitle">Manage administrative, sales staff, accountant accounts and role-based permissions.</div>
    </div>
    <div class="d-flex gap-2">
        <button type="button" class="btn btn-primary-custom" onclick="openUserModal()">
            <i class="fa-solid fa-user-plus me-1"></i> Add System User
        </button>
    </div>
</div>

<!-- Users Table -->
<div class="dark-card">
    <div class="card-header-clean">
        <div class="card-title-clean">
            <i class="fa-solid fa-users-gear text-primary-light"></i>
            <span>System User Accounts</span>
        </div>
    </div>

    <div class="table-responsive">
        <table class="dark-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>User</th>
                    <th>Username</th>
                    <th>Assigned Role</th>
                    <th>Mobile</th>
                    <th>Last Login</th>
                    <th class="text-center">Status</th>
                    <th class="text-end no-print">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $u): ?>
                    <tr>
                        <td><?php echo $u['id']; ?></td>
                        <td>
                            <div class="d-flex align-items-center gap-2">
                                <div class="user-avatar" style="width: 32px; height: 32px; font-size: 0.8rem;">
                                    <?php echo strtoupper(substr($u['name'], 0, 1)); ?>
                                </div>
                                <div>
                                    <div class="fw-bold text-white"><?php echo htmlspecialchars($u['name']); ?></div>
                                    <div class="text-secondary small"><?php echo htmlspecialchars($u['email'] ?: '—'); ?></div>
                                </div>
                            </div>
                        </td>
                        <td class="fw-bold text-cyan">@<?php echo htmlspecialchars($u['username']); ?></td>
                        <td>
                            <span class="badge-custom badge-primary"><?php echo htmlspecialchars($u['role_name']); ?></span>
                        </td>
                        <td><?php echo htmlspecialchars($u['phone'] ?: '—'); ?></td>
                        <td class="text-secondary small"><?php echo formatDateTime($u['last_login']); ?></td>
                        <td class="text-center">
                            <span class="badge-custom <?php echo ($u['status'] === 'active') ? 'badge-success' : 'badge-danger'; ?>">
                                <?php echo ucfirst($u['status']); ?>
                            </span>
                        </td>
                        <td class="text-end no-print">
                            <div class="btn-group btn-group-sm">
                                <button type="button" class="btn btn-secondary-custom" onclick="editUser(<?php echo htmlspecialchars(json_encode($u)); ?>)" title="Edit User">
                                    <i class="fa-solid fa-pen-to-square text-primary-light"></i>
                                </button>
                                <?php if ($u['id'] !== 1 && $u['id'] !== (int)($_SESSION['user_id'] ?? 0)): ?>
                                    <button type="button" class="btn btn-secondary-custom" onclick="toggleUserStatus(<?php echo $u['id']; ?>)" title="Toggle Active/Inactive">
                                        <i class="fa-solid <?php echo ($u['status'] === 'active') ? 'fa-user-slash text-danger' : 'fa-user-check text-success'; ?>"></i>
                                    </button>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Add / Edit User Modal -->
<div class="modal fade" id="userModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content dark-modal">
            <div class="modal-header dark-modal-header">
                <h5 class="modal-title text-white" id="userModalTitle">Add New System User</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="userForm">
                <input type="hidden" name="id" id="usr_id">
                <?php echo csrfField(); ?>

                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label for="usr_role_id" class="form-label-custom">Select Role <span class="required">*</span></label>
                        <select class="form-select form-select-custom" id="usr_role_id" name="role_id" required>
                            <option value="">-- Choose Role --</option>
                            <?php foreach ($roles as $role): ?>
                                <option value="<?php echo $role['id']; ?>"><?php echo htmlspecialchars($role['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label for="usr_name" class="form-label-custom">Full Name <span class="required">*</span></label>
                        <input type="text" class="form-control form-control-custom" id="usr_name" name="name" required placeholder="e.g. Md. Maruf Hasan">
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label for="usr_username" class="form-label-custom">Username <span class="required">*</span></label>
                            <input type="text" class="form-control form-control-custom" id="usr_username" name="username" required placeholder="e.g. maruf">
                        </div>
                        <div class="col-md-6">
                            <label for="usr_phone" class="form-label-custom">Phone / Mobile</label>
                            <input type="text" class="form-control form-control-custom" id="usr_phone" name="phone" placeholder="017xxxxxxxx">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="usr_email" class="form-label-custom">Email Address</label>
                        <input type="email" class="form-control form-control-custom" id="usr_email" name="email" placeholder="e.g. user@maruftraders.com">
                    </div>

                    <div class="mb-3">
                        <label for="usr_password" class="form-label-custom">Password <span class="required" id="passReqStar">*</span></label>
                        <input type="password" class="form-control form-control-custom" id="usr_password" name="password" placeholder="••••••••">
                        <small class="text-secondary" id="passHint" style="display: none; font-size: 0.75rem;">Leave blank to keep existing password unchanged.</small>
                    </div>

                    <div class="mb-3">
                        <label for="usr_status" class="form-label-custom">Account Status</label>
                        <select class="form-select form-select-custom" id="usr_status" name="status">
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>
                </div>

                <div class="modal-footer dark-modal-footer">
                    <button type="button" class="btn btn-secondary-custom" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary-custom" id="saveUserBtn">
                        <i class="fa-solid fa-floppy-disk me-1"></i> Save User
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
let userModalInstance = null;

// Embedded server-side since a JS getCsrfToken() helper isn't reliably
// available. Guarantees the token is always correct on submit.
const CSRF_TOKEN = '<?php echo getCsrfToken(); ?>';

function notify(type, message) {
    if (typeof showToast === 'function') {
        showToast(type, message);
    } else {
        alert(message);
    }
}

function openUserModal() {
    document.getElementById('userForm').reset();
    document.getElementById('usr_id').value = '';
    document.getElementById('userModalTitle').innerText = 'Add New System User';
    document.getElementById('passReqStar').style.display = 'inline';
    document.getElementById('passHint').style.display = 'none';
    document.getElementById('usr_password').required = true;

    if (!userModalInstance) {
        userModalInstance = new bootstrap.Modal(document.getElementById('userModal'));
    }
    userModalInstance.show();
}

function editUser(u) {
    document.getElementById('userForm').reset();
    document.getElementById('usr_id').value = u.id;
    document.getElementById('usr_role_id').value = u.role_id;
    document.getElementById('usr_name').value = u.name;
    document.getElementById('usr_username').value = u.username;
    document.getElementById('usr_email').value = u.email || '';
    document.getElementById('usr_phone').value = u.phone || '';
    document.getElementById('usr_status').value = u.status;

    document.getElementById('userModalTitle').innerText = 'Edit User: @' + u.username;
    document.getElementById('passReqStar').style.display = 'none';
    document.getElementById('passHint').style.display = 'block';
    document.getElementById('usr_password').required = false;

    if (!userModalInstance) {
        userModalInstance = new bootstrap.Modal(document.getElementById('userModal'));
    }
    userModalInstance.show();
}

async function toggleUserStatus(id) {
    const result = await Swal.fire({
        title: 'Change User Status?',
        text: 'Do you want to toggle this user account active/inactive status?',
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#8B5CF6',
        cancelButtonColor: '#475569',
        confirmButtonText: 'Yes, Toggle Status',
        background: '#151F36',
        color: '#F8FAFC'
    });

    if (!result.isConfirmed) return;

    try {
        const formData = new FormData();
        formData.append('id', id);
        formData.append('csrf_token', CSRF_TOKEN);

        const res = await fetch(`${window.BASE_URL}/ajax/users/toggle_status.php`, {
            method: 'POST',
            body: formData,
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        });
        const data = await res.json();

        if (data.success) {
            notify('success', data.message);
            setTimeout(() => window.location.reload(), 800);
        } else {
            notify('error', data.message || 'Failed to toggle user status.');
        }
    } catch (e) {
        console.error(e);
        alert('Something went wrong. Check console for details.');
    }
}

/**
 * Direct fetch()-based AJAX submit handler.
 * setupAjaxForm() was not preventing the browser's default form submission
 * (GET request, page navigation), so users were never actually saved.
 */
document.getElementById('userForm').addEventListener('submit', async function (e) {
    e.preventDefault();

    const form = e.target;
    const formData = new FormData(form);
    const saveBtn = document.getElementById('saveUserBtn');
    const originalBtnHtml = saveBtn.innerHTML;

    saveBtn.disabled = true;
    saveBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin me-1"></i> Saving...';

    try {
        const res = await fetch(`${window.BASE_URL}/ajax/users/save.php`, {
            method: 'POST',
            body: formData,
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        });

        const result = await res.json();

        if (result.success) {
            if (userModalInstance) userModalInstance.hide();
            setTimeout(() => window.location.reload(), 500);
        } else {
            alert(result.message || 'Failed to save user.');
        }
    } catch (err) {
        console.error('User save failed:', err);
        alert('Something went wrong while saving. Check the browser console for details.');
    } finally {
        saveBtn.disabled = false;
        saveBtn.innerHTML = originalBtnHtml;
    }
});
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>