<?php
/** Guard for the SaaS operator console; tenant admins cannot access it. */
defined('APP_INIT') or define('APP_INIT', true);
function requirePlatformAdmin(): void {
    if (empty($_SESSION['user_id'])) {
        http_response_code(401); exit('Authentication required.');
    }
    $db = Database::getConnection();
    $stmt = $db->prepare('SELECT is_platform_admin FROM users WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $_SESSION['user_id']]);
    if (!(bool)$stmt->fetchColumn()) {
        http_response_code(403); exit('Platform administrator access required.');
    }
}

function logPlatformAudit(string $module, string $action, ?int $tenantId = null, ?string $referenceId = null, mixed $old = null, mixed $new = null): void {
    try {
        $db = Database::getConnection();
        $stmt = $db->prepare('INSERT INTO platform_audit_logs (actor_user_id,tenant_id,action,module,reference_id,old_value,new_value,ip_address) VALUES (:actor,:tenant,:action,:module,:reference,:old,:new,:ip)');
        $stmt->execute([':actor'=>$_SESSION['user_id']??null,':tenant'=>$tenantId,':action'=>$action,':module'=>$module,':reference'=>$referenceId,':old'=>$old===null?null:json_encode($old),':new'=>$new===null?null:json_encode($new),':ip'=>$_SERVER['REMOTE_ADDR']??null]);
    } catch (Throwable $e) { error_log('Platform audit failed: '.$e->getMessage()); }
}
