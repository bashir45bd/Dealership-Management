<?php
/**
 * Maruf Traders - Business & System Settings Module
 */

define('APP_INIT', true);
$pageTitle = 'Business Settings';
$breadcrumb = 'Settings';
$activeMenu = 'settings';

require_once __DIR__ . '/../../includes/header.php';
requirePermission('settings.manage');

$db = Database::getConnection();
$stmt = $db->prepare('SELECT s.setting_key, COALESCE(ts.setting_value,s.setting_value) setting_value FROM settings s LEFT JOIN tenant_settings ts ON BINARY ts.setting_key=BINARY s.setting_key AND ts.tenant_id=:tenant');
$stmt->execute([':tenant'=>(int)$_SESSION['tenant_id']]);
$settings = [];
while ($row = $stmt->fetch()) {
    $settings[$row['setting_key']] = $row['setting_value'];
}
?>

<div class="page-header-container">
    <div>
        <h2 class="page-title">Business & Application Settings (সিস্টেম ও ব্যবসা সেটিংস)</h2>
        <div class="page-subtitle">Configure business identity, dealership financial parameters, inventory constraints, and commission rules.</div>
    </div>
</div>

<form id="settingsForm">
    <?php echo csrfField(); ?>

    <div class="row g-4">
        <!-- Business Profile -->
        <div class="col-lg-6">
            <div class="dark-card h-100">
                <div class="card-header-clean">
                    <div class="card-title-clean">
                        <i class="fa-solid fa-store text-primary-light"></i>
                        <span>Business Identity & Contact</span>
                    </div>
                </div>

                <!-- Logo Upload (separate mini-form, saved independently of the main settings form below) -->
                <div class="mb-4 p-3 rounded" style="background: var(--bg-input); border: 1px solid var(--border-color);">
                    <label class="form-label-custom d-block mb-2">Business Logo</label>
                    <div class="d-flex align-items-center gap-3 mb-3">
                        <div id="logoPreviewWrap" style="width: 64px; height: 64px; border-radius: 10px; overflow: hidden; background: transparent; display: flex; align-items: center; justify-content: center; border: 1px solid var(--border-color); flex-shrink: 0;">
                            <?php $logoPath = getSetting('logo_path'); $logoVer = getSetting('logo_updated_at', '0'); ?>
                            <?php if ($logoPath): ?>
                                <img id="logoPreviewImg" src="<?php echo BASE_URL . htmlspecialchars($logoPath) . '?v=' . htmlspecialchars($logoVer); ?>" alt="Logo" style="width: 100%; height: 100%; object-fit: contain; background: transparent;">
                            <?php else: ?>
                                <span id="logoPreviewFallback" class="text-secondary fw-bold">MT</span>
                            <?php endif; ?>
                        </div>
                        <div class="flex-grow-1">
                            <input type="file" class="form-control form-control-custom" id="logoFileInput" accept="image/png,image/jpeg,image/webp">
                            <small class="text-secondary" style="font-size: 0.75rem;">PNG, JPG or WEBP. Max 2MB. Square images look best.</small>
                        </div>
                    </div>
                    <button type="button" class="btn btn-secondary-custom btn-sm" id="uploadLogoBtn" onclick="uploadLogo()">
                        <i class="fa-solid fa-upload me-1"></i> Upload Logo
                    </button>
                </div>

                <div class="mb-3">
                    <label for="business_name" class="form-label-custom">Business Name <span class="required">*</span></label>
                    <input type="text" class="form-control form-control-custom" id="business_name" name="business_name" value="<?php echo htmlspecialchars($settings['business_name'] ?? 'Maruf Traders'); ?>" required>
                </div>

                <div class="mb-3">
                    <label for="business_title" class="form-label-custom">Business Tagline / Subtitle</label>
                    <input type="text" class="form-control form-control-custom" id="business_title" name="business_title" value="<?php echo htmlspecialchars($settings['business_title'] ?? 'Cement Dealership Management'); ?>">
                </div>

                <div class="mb-3">
                    <label for="business_address" class="form-label-custom">Dealership Address</label>
                    <textarea class="form-control form-control-custom" id="business_address" name="business_address" rows="2"><?php echo htmlspecialchars($settings['business_address'] ?? 'সুন্দরপুর বাজার, সাটিয়াজুরি, চুনারুঘাট, হবিগঞ্জ'); ?></textarea>
                </div>

                <div class="row g-3">
                    <div class="col-md-6">
                        <label for="business_mobile" class="form-label-custom">Contact Mobile Numbers</label>
                        <input type="text" class="form-control form-control-custom" id="business_mobile" name="business_mobile" value="<?php echo htmlspecialchars($settings['business_mobile'] ?? '01712-345678'); ?>">
                    </div>
                    <div class="col-md-6">
                        <label for="business_email" class="form-label-custom">Official Email</label>
                        <input type="email" class="form-control form-control-custom" id="business_email" name="business_email" value="<?php echo htmlspecialchars($settings['business_email'] ?? 'contact@maruftraders.com'); ?>">
                    </div>
                </div>
            </div>
        </div>

        <!-- Financial & Payment Settings -->
        <div class="col-lg-6">
            <div class="dark-card h-100">
                <div class="card-header-clean">
                    <div class="card-title-clean">
                        <i class="fa-solid fa-scale-balanced text-success"></i>
                        <span>Financial & Credit Rules</span>
                    </div>
                </div>

                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <label for="currency_symbol" class="form-label-custom">Currency Symbol</label>
                        <input type="text" class="form-control form-control-custom" id="currency_symbol" name="currency_symbol" value="<?php echo htmlspecialchars($settings['currency_symbol'] ?? '৳'); ?>">
                    </div>
                    <div class="col-md-6">
                        <label for="currency_code" class="form-label-custom">Currency Code</label>
                        <input type="text" class="form-control form-control-custom" id="currency_code" name="currency_code" value="<?php echo htmlspecialchars($settings['currency_code'] ?? 'BDT'); ?>">
                    </div>
                </div>

                <div class="mb-3">
                    <label for="default_credit_limit" class="form-label-custom">Default Retailer Credit Limit (৳)</label>
                    <input type="number" step="0.01" class="form-control form-control-custom" id="default_credit_limit" name="default_credit_limit" value="<?php echo htmlspecialchars($settings['default_credit_limit'] ?? '100000.00'); ?>">
                </div>

                <div class="mb-3">
                    <label for="allow_company_overpayment" class="form-label-custom">Allow Company Overpayment?</label>
                    <select class="form-select form-select-custom" id="allow_company_overpayment" name="allow_company_overpayment">
                        <option value="no" <?php echo (($settings['allow_company_overpayment'] ?? 'no') === 'no') ? 'selected' : ''; ?>>No (Prevent payments exceeding payable balance)</option>
                        <option value="yes" <?php echo (($settings['allow_company_overpayment'] ?? 'no') === 'yes') ? 'selected' : ''; ?>>Yes (Allow advance supplier payments)</option>
                    </select>
                </div>

                <div class="mb-3">
                    <label for="payment_methods" class="form-label-custom">Supported Payment Methods</label>
                    <input type="text" class="form-control form-control-custom" id="payment_methods" name="payment_methods" value="<?php echo htmlspecialchars($settings['payment_methods'] ?? 'Cash,bKash,Nagad,Bank Transfer'); ?>">
                </div>
            </div>
        </div>

        <!-- Inventory Rules -->
        <div class="col-lg-6">
            <div class="dark-card h-100">
                <div class="card-header-clean">
                    <div class="card-title-clean">
                        <i class="fa-solid fa-boxes-stacked text-cyan"></i>
                        <span>Inventory & Stock Constraints</span>
                    </div>
                </div>

                <div class="mb-3">
                    <label for="allow_negative_stock" class="form-label-custom">Allow Negative Stock Sales?</label>
                    <select class="form-select form-select-custom" id="allow_negative_stock" name="allow_negative_stock">
                        <option value="no" <?php echo (($settings['allow_negative_stock'] ?? 'no') === 'no') ? 'selected' : ''; ?>>No (Strictly block sales when zero stock)</option>
                        <option value="yes" <?php echo (($settings['allow_negative_stock'] ?? 'no') === 'yes') ? 'selected' : ''; ?>>Yes (Allow negative stock)</option>
                    </select>
                </div>

                <div class="row g-3">
                    <div class="col-md-6">
                        <label for="low_stock_threshold" class="form-label-custom">Global Low Stock Warning Limit (Bags)</label>
                        <input type="number" class="form-control form-control-custom" id="low_stock_threshold" name="low_stock_threshold" value="<?php echo htmlspecialchars($settings['low_stock_threshold'] ?? '100'); ?>">
                    </div>
                    <div class="col-md-6">
                        <label for="default_cement_unit" class="form-label-custom">Default Cement Unit</label>
                        <input type="text" class="form-control form-control-custom" id="default_cement_unit" name="default_cement_unit" value="<?php echo htmlspecialchars($settings['default_cement_unit'] ?? 'Bag'); ?>">
                    </div>
                </div>
            </div>
        </div>

        <!-- Commission & Security -->
        <div class="col-lg-6">
            <div class="dark-card h-100">
                <div class="card-header-clean">
                    <div class="card-title-clean">
                        <i class="fa-solid fa-shield-halved text-warning"></i>
                        <span>Commission Mode & Session Security</span>
                    </div>
                </div>

                <div class="mb-3">
                    <label for="commission_calculation_mode" class="form-label-custom">Default Commission Calculation Mode</label>
                    <select class="form-select form-select-custom" id="commission_calculation_mode" name="commission_calculation_mode">
                        <option value="proportional" <?php echo (($settings['commission_calculation_mode'] ?? 'proportional') === 'proportional') ? 'selected' : ''; ?>>Proportional Achievement Mode (Rate × Achievement %)</option>
                        <option value="fixed_per_bag" <?php echo (($settings['commission_calculation_mode'] ?? 'proportional') === 'fixed_per_bag') ? 'selected' : ''; ?>>Fixed Rate Per Bag Mode</option>
                    </select>
                </div>

                <div class="mb-3">
                    <label for="session_timeout_minutes" class="form-label-custom">Session Inactivity Timeout (Minutes)</label>
                    <input type="number" class="form-control form-control-custom" id="session_timeout_minutes" name="session_timeout_minutes" value="<?php echo htmlspecialchars($settings['session_timeout_minutes'] ?? '120'); ?>">
                </div>
            </div>
        </div>

        <!-- Save Button -->
        <div class="col-12 text-end">
            <button type="submit" class="btn btn-primary-custom px-4 py-2 fs-6" id="saveSettingsBtn">
                <i class="fa-solid fa-floppy-disk me-2"></i> Save All Settings
            </button>
        </div>
    </div>
</form>

<script>
// Embedded server-side so the standalone logo upload (outside settingsForm)
// always has a valid, current CSRF token.
const CSRF_TOKEN = '<?php echo getCsrfToken(); ?>';

async function uploadLogo() {
    const input = document.getElementById('logoFileInput');
    if (!input.files || input.files.length === 0) {
        alert('Please choose a logo image first.');
        return;
    }

    const btn = document.getElementById('uploadLogoBtn');
    const originalHtml = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin me-1"></i> Uploading...';

    const formData = new FormData();
    formData.append('logo', input.files[0]);
    formData.append('csrf_token', CSRF_TOKEN);

    try {
        const res = await fetch(`${window.BASE_URL}/ajax/settings/upload_logo.php`, {
            method: 'POST',
            body: formData,
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        });
        const result = await res.json();

        if (result.success) {
            // Update preview immediately without a full page reload
            const wrap = document.getElementById('logoPreviewWrap');
            const newUrl = `${window.BASE_URL}${result.data.logo_path}?v=${result.data.version || Date.now()}`;
            wrap.innerHTML = `<img src="${newUrl}" alt="Logo" style="width:100%;height:100%;object-fit:contain;">`;
            alert(result.message || 'Logo uploaded successfully.');
            input.value = '';
        } else {
            alert(result.message || 'Failed to upload logo.');
        }
    } catch (err) {
        console.error('Logo upload failed:', err);
        alert('Something went wrong while uploading. Check the browser console for details.');
    } finally {
        btn.disabled = false;
        btn.innerHTML = originalHtml;
    }
}

/**
 * Direct fetch()-based AJAX submit handler.
 * setupAjaxForm() was not preventing the browser's default form submission
 * (GET request, page navigation), so settings were never actually saved.
 */
document.getElementById('settingsForm').addEventListener('submit', async function (e) {
    e.preventDefault();

    const form = e.target;
    const formData = new FormData(form);
    const saveBtn = document.getElementById('saveSettingsBtn');
    const originalBtnHtml = saveBtn.innerHTML;

    saveBtn.disabled = true;
    saveBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin me-2"></i> Saving...';

    try {
        const res = await fetch(`${window.BASE_URL}/ajax/settings/save.php`, {
            method: 'POST',
            body: formData,
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        });

        const result = await res.json();

        if (result.success) {
            setTimeout(() => window.location.reload(), 500);
        } else {
            alert(result.message || 'Failed to save settings.');
            saveBtn.disabled = false;
            saveBtn.innerHTML = originalBtnHtml;
        }
    } catch (err) {
        console.error('Settings save failed:', err);
        alert('Something went wrong while saving. Check the browser console for details.');
        saveBtn.disabled = false;
        saveBtn.innerHTML = originalBtnHtml;
    }
});
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
