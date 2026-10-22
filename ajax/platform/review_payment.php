<?php
define('APP_INIT',true); define('IS_AJAX',true);
require_once __DIR__.'/../../config/app.php'; require_once ROOT_PATH.'/includes/auth_check.php'; require_once ROOT_PATH.'/includes/permission_check.php'; require_once ROOT_PATH.'/includes/platform_check.php';
requirePlatformAdmin(); requirePermission('platform.manage');
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verifyCsrfToken($_POST['csrf_token']??'')) jsonResponse(false,'Invalid request.',[],403);
$id=(int)($_POST['id']??0); $status=$_POST['status']??'';
if (!$id || !in_array($status,['approved','rejected','refunded','cancelled'],true)) jsonResponse(false,'Invalid payment review.',[],422);
$db=Database::getConnection();
try {
  $db->beginTransaction();
  $p=$db->prepare('SELECT * FROM subscription_payments WHERE id=:id FOR UPDATE'); $p->execute([':id'=>$id]); $payment=$p->fetch();
  if (!$payment || $payment['status'] !== 'pending') throw new Exception('This payment is not available for review.');
  $db->prepare('UPDATE subscription_payments SET status=:status,reviewed_by=:user,reviewed_at=NOW(),admin_notes=:notes WHERE id=:id')->execute([':status'=>$status,':user'=>$_SESSION['user_id'],':notes'=>trim($_POST['notes']??''),':id'=>$id]);
  if ($status==='approved') {
    $interval=($payment['billing_period']??'monthly')==='yearly'?'1 YEAR':'1 MONTH';
    $db->prepare("UPDATE tenant_subscriptions SET status='active',starts_at=CURDATE(),ends_at=DATE_ADD(CURDATE(),INTERVAL $interval) WHERE id=:id")->execute([':id'=>$payment['subscription_id']]);
    $db->prepare('UPDATE tenants SET status="active" WHERE id=:id')->execute([':id'=>$payment['tenant_id']]);
  }
  $db->prepare('INSERT INTO subscription_events(tenant_id,subscription_id,actor_user_id,event_type,new_value) VALUES(:tenant,:subscription,:actor,:event,:value)')->execute([':tenant'=>$payment['tenant_id'],':subscription'=>$payment['subscription_id'],':actor'=>$_SESSION['user_id'],':event'=>'payment_'.$status,':value'=>json_encode(['payment_id'=>$id])]);
  logPlatformAudit('payments',$status,(int)$payment['tenant_id'],(string)$id,null,['payment_id'=>$id]);
  $db->commit(); jsonResponse(true,'Payment review saved.');
} catch(Throwable $e) { if($db->inTransaction())$db->rollBack(); jsonResponse(false,'Payment review failed: '.$e->getMessage(),[],422); }
