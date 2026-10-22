<?php
define('APP_INIT', true); define('IS_AJAX', true);
require_once __DIR__ . '/../../config/app.php';
require_once ROOT_PATH . '/includes/auth_check.php';
require_once ROOT_PATH . '/includes/permission_check.php';
requirePermission('commission.manage');
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verifyCsrfToken($_POST['csrf_token'] ?? '')) jsonResponse(false, 'Invalid request.', [], 403);
$tenantId = TenantContext::id(); TenantContext::requireWritable(); $db = Database::getConnection();
$name = trim($_POST['name'] ?? ''); $basis = $_POST['basis'] ?? ''; $method = $_POST['calculation_method'] ?? '';
$allowedBasis = ['quantity','sales_amount','purchase_amount','gross_profit','target_achievement','collection_amount','collection_percentage','growth_percentage','invoice_count'];
$allowedMethod = ['fixed_rate','percentage','target_achievement','volume_slab','amount_slab','growth','collection','payment_timing'];
if ($name === '' || !in_array($basis,$allowedBasis,true) || !in_array($method,$allowedMethod,true)) jsonResponse(false, 'Name, basis, and calculation method are required.', [], 422);
$slabs = json_decode($_POST['slabs'] ?? '[]', true); if (!is_array($slabs)) jsonResponse(false, 'Invalid slabs.', [], 422);
try {
 $db->beginTransaction();
 $stmt=$db->prepare("INSERT INTO commission_schemes (tenant_id,company_id,category_id,product_id,name,scheme_type,basis,calculation_method,slab_mode,stacking_mode,priority,effective_from,effective_to,status,notes,created_by) VALUES (:tenant,:company,:category,:product,:name,:type,:basis,:method,:slab_mode,:stacking,:priority,:from,:to,:status,:notes,:user)");
 $stmt->execute([':tenant'=>$tenantId,':company'=>($_POST['company_id']?:null),':category'=>($_POST['category_id']?:null),':product'=>($_POST['product_id']?:null),':name'=>$name,':type'=>trim($_POST['scheme_type']??'Base / General Commission'),':basis'=>$basis,':method'=>$method,':slab_mode'=>in_array($_POST['slab_mode']??'whole',['whole','progressive'],true)?$_POST['slab_mode']:'whole',':stacking'=>in_array($_POST['stacking_mode']??'stack',['stack','highest_only','exclusive','conditional_stack'],true)?$_POST['stacking_mode']:'stack',':priority'=>(int)($_POST['priority']??100),':from'=>$_POST['effective_from']??date('Y-m-d'),':to'=>($_POST['effective_to']??'')?:null,':status'=>in_array($_POST['status']??'draft',['draft','active'],true)?$_POST['status']:'draft',':notes'=>trim($_POST['notes']??''),':user'=>$_SESSION['user_id']]);
 $schemeId=(int)$db->lastInsertId(); $slabStmt=$db->prepare('INSERT INTO commission_slabs (scheme_id,from_value,to_value,rate,rate_type,bonus_amount) VALUES (:scheme,:from,:to,:rate,:type,:bonus)');
 foreach($slabs as $slab){ if (!isset($slab['from_value'],$slab['rate'])) throw new InvalidArgumentException('Each slab needs a starting value and rate.'); $slabStmt->execute([':scheme'=>$schemeId,':from'=>(float)$slab['from_value'],':to'=>($slab['to_value']??'')===''?null:(float)$slab['to_value'],':rate'=>(float)$slab['rate'],':type'=>($slab['rate_type']??'fixed')==='percentage'?'percentage':'fixed',':bonus'=>(float)($slab['bonus_amount']??0)]); }
 logAudit('commission_schemes','create',(string)$schemeId,null,['name'=>$name,'method'=>$method]); $db->commit(); jsonResponse(true,'Commission scheme saved.',['id'=>$schemeId]);
} catch(Throwable $e){if($db->inTransaction())$db->rollBack(); jsonResponse(false,'Could not save scheme: '.$e->getMessage(),[],422);}
