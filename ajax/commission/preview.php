<?php
define('APP_INIT', true); define('IS_AJAX', true);
require_once __DIR__ . '/../../config/app.php';
require_once ROOT_PATH . '/includes/auth_check.php';
require_once ROOT_PATH . '/includes/permission_check.php';
requirePermission('commission.manage');
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verifyCsrfToken($_POST['csrf_token'] ?? '')) jsonResponse(false, 'Invalid request.', [], 403);
$scheme = json_decode($_POST['scheme'] ?? '{}', true);
$slabs = json_decode($_POST['slabs'] ?? '[]', true);
$facts = json_decode($_POST['facts'] ?? '{}', true);
if (!is_array($scheme) || !is_array($slabs) || !is_array($facts)) jsonResponse(false, 'Invalid preview data.', [], 422);
try { jsonResponse(true, 'Preview calculated.', CommissionEngine::evaluate($scheme, $slabs, $facts)); }
catch (Throwable $e) { jsonResponse(false, 'Preview failed: ' . $e->getMessage(), [], 422); }
