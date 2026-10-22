<?php
define('APP_INIT', true); define('IS_AJAX', true);
require_once __DIR__.'/../../config/app.php'; require_once ROOT_PATH.'/includes/auth_check.php'; require_once ROOT_PATH.'/includes/permission_check.php'; require_once ROOT_PATH.'/includes/platform_check.php';
requirePlatformAdmin(); requirePermission('platform.manage');
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verifyCsrfToken($_POST['csrf_token']??'')) jsonResponse(false,'Invalid request.',[],403);
$name=trim($_POST['name']??''); $owner=trim($_POST['owner_name']??''); $phone=trim($_POST['owner_phone']??''); $email=trim($_POST['owner_email']??''); $planId=(int)($_POST['plan_id']??0); $username=strtolower(trim($_POST['username']??'')); $password=$_POST['password']??'';
if ($name==='' || $owner==='' || !filter_var($email,FILTER_VALIDATE_EMAIL) || $planId<1 || !preg_match('/^[a-z0-9_.-]{3,50}$/',$username) || strlen($password)<8) jsonResponse(false,'Provide business, owner email, valid username, plan and an 8-character password.',[],422);
$slug=strtolower(trim(preg_replace('/[^a-z0-9]+/i','-',$name),'-')); $db=Database::getConnection();
try {
  $db->beginTransaction();
  $plan=$db->prepare('SELECT trial_days FROM subscription_plans WHERE id=:id AND status="active"'); $plan->execute([':id'=>$planId]); $planRow=$plan->fetch(); if(!$planRow) throw new Exception('Invalid plan.');
  $base=$slug; for($n=2;;$n++){ $q=$db->prepare('SELECT id FROM tenants WHERE slug=:slug'); $q->execute([':slug'=>$slug]); if(!$q->fetchColumn()) break; $slug=$base.'-'.$n; }
  $tenantStmt=$db->prepare('INSERT INTO tenants(name,slug,owner_name,owner_phone,owner_email,status) VALUES(:name,:slug,:owner,:phone,:email,"trial")');
  $tenantStmt->execute([':name'=>$name,':slug'=>$slug,':owner'=>$owner,':phone'=>$phone,':email'=>$email]); $tenant=(int)$db->lastInsertId();
  $setting=$db->prepare('INSERT INTO tenant_settings(tenant_id,setting_key,setting_value) VALUES(:tenant,:key,:value) ON DUPLICATE KEY UPDATE setting_value=:value2');
  foreach(['business_name'=>$name,'business_mobile'=>$phone,'business_email'=>$email] as $key=>$value) $setting->execute([':tenant'=>$tenant,':key'=>$key,':value'=>$value,':value2'=>$value]);
  $role=(int)$db->query("SELECT id FROM roles WHERE slug='admin' LIMIT 1")->fetchColumn();
  $user=$db->prepare('INSERT INTO users(tenant_id,role_id,name,username,email,phone,password,status) VALUES(:tenant,:role,:name,:username,:email,:phone,:password,"active")');
  $user->execute([':tenant'=>$tenant,':role'=>$role,':name'=>$owner,':username'=>$username,':email'=>$email,':phone'=>$phone,':password'=>password_hash($password,PASSWORD_DEFAULT)]);
  $sub=$db->prepare('INSERT INTO tenant_subscriptions(tenant_id,plan_id,status,starts_at,ends_at) VALUES(:tenant,:plan,"trial",CURDATE(),DATE_ADD(CURDATE(),INTERVAL :days DAY))'); $sub->bindValue(':tenant',$tenant); $sub->bindValue(':plan',$planId); $sub->bindValue(':days',(int)$planRow['trial_days'],PDO::PARAM_INT); $sub->execute();
  logPlatformAudit('tenants','created',$tenant,(string)$tenant,null,['name'=>$name,'plan_id'=>$planId]); $db->commit(); jsonResponse(true,'Tenant and administrator created.',['tenant_id'=>$tenant,'slug'=>$slug]);
} catch(Throwable $e) { if($db->inTransaction())$db->rollBack(); jsonResponse(false,'Tenant creation failed: '.$e->getMessage(),[],422); }
