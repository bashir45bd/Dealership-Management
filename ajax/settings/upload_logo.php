<?php
/**
 * Maruf Traders - AJAX Upload Business Logo
 */

define('APP_INIT', true);
define('IS_AJAX', true);
require_once __DIR__ . '/../../config/app.php';
require_once ROOT_PATH . '/includes/auth_check.php';
require_once ROOT_PATH . '/includes/permission_check.php';

requirePermission('settings.manage');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(false, 'Invalid request method.', [], 405);
}

$csrfToken = $_POST['csrf_token'] ?? '';
if (!verifyCsrfToken($csrfToken)) {
    jsonResponse(false, 'Invalid security token (CSRF).', [], 403);
}

if (empty($_FILES['logo']) || $_FILES['logo']['error'] === UPLOAD_ERR_NO_FILE) {
    jsonResponse(false, 'Please choose a logo image to upload.');
}

$file = $_FILES['logo'];

if ($file['error'] !== UPLOAD_ERR_OK) {
    jsonResponse(false, 'File upload failed (error code: ' . $file['error'] . ').');
}

// Validate file size (max 2MB)
$maxSizeBytes = 2 * 1024 * 1024;
if ($file['size'] > $maxSizeBytes) {
    jsonResponse(false, 'Logo file is too large. Maximum allowed size is 2MB.');
}

// Validate actual image type (don't trust the client-sent MIME type)
$imageInfo = @getimagesize($file['tmp_name']);
if ($imageInfo === false) {
    jsonResponse(false, 'The uploaded file is not a valid image.');
}

$allowedMimes = [
    'image/png'  => 'png',
    'image/jpeg' => 'jpg',
    'image/webp' => 'webp',
];

$detectedMime = $imageInfo['mime'];
if (!isset($allowedMimes[$detectedMime])) {
    jsonResponse(false, 'Unsupported image type. Please upload a PNG, JPG, or WEBP file.');
}

$extension = $allowedMimes[$detectedMime];
$tenantId = (int)($_SESSION['tenant_id'] ?? 0);
if ($tenantId < 1 || !empty($_SESSION['is_platform_admin'])) {
    jsonResponse(false, 'A customer business workspace is required.', [], 403);
}

try {
    $uploadDir = ROOT_PATH . '/assets/uploads/branding';
    if (!is_dir($uploadDir)) {
        if (!mkdir($uploadDir, 0755, true) && !is_dir($uploadDir)) {
            throw new Exception('Could not create upload directory. Check folder permissions.');
        }
    }

    // Each tenant owns a separate logo file. Never overwrite another workspace.
    foreach (['png', 'jpg', 'jpeg', 'webp'] as $ext) {
        $oldFile = $uploadDir . '/tenant-' . $tenantId . '.' . $ext;
        if (is_file($oldFile)) {
            @unlink($oldFile);
        }
    }

    $newFileName = 'tenant-' . $tenantId . '.' . $extension;
    $destination = $uploadDir . '/' . $newFileName;

    if (!move_uploaded_file($file['tmp_name'], $destination)) {
        throw new Exception('Failed to save the uploaded file to disk.');
    }

    // Store relative path (from web root) + a cache-busting version timestamp
    $relativePath = '/assets/uploads/branding/' . $newFileName;
    $updated1 = updateSetting('logo_path', $relativePath);
    $updated2 = updateSetting('logo_updated_at', (string)time());

    if (!$updated1) {
        throw new Exception('Logo file was saved, but failed to update the setting record in the database.');
    }

    logAudit('settings', 'logo_upload', 'logo_path', null, ['logo_path' => $relativePath]);

    jsonResponse(true, 'Logo uploaded successfully.', ['logo_path' => $relativePath, 'version' => $updated2]);

} catch (Exception $e) {
    jsonResponse(false, 'Logo upload failed: ' . $e->getMessage(), [], 500);
}
