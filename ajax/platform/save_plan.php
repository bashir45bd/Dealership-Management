<?php
define('APP_INIT', true); define('IS_AJAX', true);
require_once __DIR__.'/../../config/app.php'; require_once ROOT_PATH.'/includes/auth_check.php'; require_once ROOT_PATH.'/includes/permission_check.php'; require_once ROOT_PATH.'/includes/platform_check.php';
requirePlatformAdmin(); requirePermission('platform.manage');
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verifyCsrfToken($_POST['csrf_token'] ?? '')) jsonResponse(false,'Invalid request.',[],403);
$id=(int)($_POST['id']??0); $code=strtoupper(trim($_POST['code']??'')); $name=trim($_POST['name']??'');
$limits=['company_limit','product_limit','customer_limit','user_limit']; $data=[];
foreach($limits as $limit){ $v=trim($_POST[$limit]??''); $data[$limit]=$v===''?null:(int)$v; if($data[$limit]!==null&&$data[$limit]<0) jsonResponse(false,'Limits cannot be negative.',[],422); }
if (!preg_match('/^[A-Z0-9_]{2,30}$/',$code) || $name==='') jsonResponse(false,'Provide a valid plan code and name.',[],422);
$monthly=max(0,(float)($_POST['price']??0)); $yearly=max(0,(float)($_POST['yearly_price']??0)); $trial=max(0,(int)($_POST['trial_days']??14)); $status=($_POST['status']??'active')==='inactive'?'inactive':'active';
$db=Database::getConnection();
try {
  $sql=$id ? 'UPDATE subscription_plans SET code=:code,name=:name,price=:price,yearly_price=:yearly_price,trial_days=:trial_days,company_limit=:company_limit,product_limit=:product_limit,customer_limit=:customer_limit,user_limit=:user_limit,status=:status WHERE id=:id' : 'INSERT INTO subscription_plans(code,name,price,yearly_price,trial_days,billing_period,company_limit,product_limit,customer_limit,user_limit,status) VALUES(:code,:name,:price,:yearly_price,:trial_days,"monthly",:company_limit,:product_limit,:customer_limit,:user_limit,:status)';
  $params=[':code'=>$code,':name'=>$name,':price'=>$monthly,':yearly_price'=>$yearly,':trial_days'=>$trial,':company_limit'=>$data['company_limit'],':product_limit'=>$data['product_limit'],':customer_limit'=>$data['customer_limit'],':user_limit'=>$data['user_limit'],':status'=>$status];
  if($id) $params[':id']=$id; $db->prepare($sql)->execute($params); logPlatformAudit('plans',$id?'updated':'created',null,$id?(string)$id:null,null,['code'=>$code,'name'=>$name]);
  jsonResponse(true,'Plan saved.',['id'=>$id?:$db->lastInsertId()]);
} catch(Throwable $e) { jsonResponse(false,'Plan save failed: '.$e->getMessage(),[],422); }
