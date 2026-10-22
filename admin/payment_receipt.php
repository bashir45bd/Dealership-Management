<?php
define('APP_INIT', true);
require_once __DIR__.'/../config/app.php';
require_once ROOT_PATH.'/includes/auth_check.php';
require_once ROOT_PATH.'/includes/platform_check.php';
requirePlatformAdmin();

$id=(int)($_GET['id']??0);
$db=Database::getConnection();
$stmt=$db->prepare('SELECT attachment_path FROM subscription_payments WHERE id=:id LIMIT 1');
$stmt->execute([':id'=>$id]);
$path=(string)$stmt->fetchColumn();
$privateBase=realpath(PRIVATE_STORAGE_PATH.'/payment_receipts');
$legacyBase=realpath(ROOT_PATH.'/storage/payment_receipts');
$file=$path!==''?realpath(str_starts_with($path,'storage/') ? ROOT_PATH.'/'.$path : PRIVATE_STORAGE_PATH.'/'.$path):false;
$isPrivate=$privateBase && $file && str_starts_with($file,$privateBase.DIRECTORY_SEPARATOR);
$isLegacy=$legacyBase && $file && str_starts_with($file,$legacyBase.DIRECTORY_SEPARATOR);
if ((!$isPrivate && !$isLegacy) || !is_file($file)) { http_response_code(404); exit('Receipt not found.'); }
$mime=(new finfo(FILEINFO_MIME_TYPE))->file($file);
if (!in_array($mime,['image/jpeg','image/png','image/webp'],true)) { http_response_code(415); exit('Unsupported receipt.'); }
header('Content-Type: '.$mime); header('Content-Length: '.filesize($file)); header('X-Content-Type-Options: nosniff'); header('Cache-Control: private, no-store');
readfile($file);
