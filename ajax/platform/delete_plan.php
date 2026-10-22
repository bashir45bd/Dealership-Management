<?php
define('APP_INIT', true); define('IS_AJAX', true);
require_once __DIR__.'/../../config/app.php'; require_once ROOT_PATH.'/includes/auth_check.php'; require_once ROOT_PATH.'/includes/permission_check.php'; require_once ROOT_PATH.'/includes/platform_check.php';
requirePlatformAdmin(); requirePermission('platform.manage');
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verifyCsrfToken($_POST['csrf_token']??'')) jsonResponse(false,'Invalid request.',[],403);
$id=(int)($_POST['id']??0); if($id<1) jsonResponse(false,'Invalid plan.',[],422);
$db=Database::getConnection();
try {
  $db->beginTransaction();
  $plan=$db->prepare('SELECT id,name FROM subscription_plans WHERE id=:id FOR UPDATE'); $plan->execute([':id'=>$id]); $row=$plan->fetch(); if(!$row) throw new Exception('Plan not found.');
  $count=$db->prepare('SELECT COUNT(*) FROM tenant_subscriptions WHERE plan_id=:id'); $count->execute([':id'=>$id]); $inUse=(int)$count->fetchColumn();
  if($inUse>0) {
    $db->prepare("UPDATE subscription_plans SET status='inactive' WHERE id=:id")->execute([':id'=>$id]);
    $message='Plan is used by subscriptions, so it was safely archived instead of deleted.';
    $action='archived';
  } else {
    $db->prepare('DELETE FROM subscription_plans WHERE id=:id')->execute([':id'=>$id]);
    $message='Unused plan deleted.'; $action='deleted';
  }
  logPlatformAudit('plans',$action,null,(string)$id,['name'=>$row['name']],null); $db->commit(); jsonResponse(true,$message);
} catch(Throwable $e) { if($db->inTransaction())$db->rollBack(); jsonResponse(false,'Plan removal failed: '.$e->getMessage(),[],422); }
