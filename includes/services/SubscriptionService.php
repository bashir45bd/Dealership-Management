<?php
/** Server-side plan enforcement. It never deletes or hides historical data. */
final class SubscriptionService {
    public static function activeSubscription(PDO $db, int $tenantId): array {
        $stmt=$db->prepare('SELECT ts.*, sp.company_limit,sp.product_limit,sp.customer_limit,sp.user_limit,sp.name AS plan_name FROM tenant_subscriptions ts JOIN subscription_plans sp ON sp.id=ts.plan_id WHERE ts.tenant_id=:tenant ORDER BY ts.id DESC LIMIT 1');
        $stmt->execute([':tenant'=>$tenantId]); $row=$stmt->fetch();
        if(!$row) throw new RuntimeException('No subscription is assigned to this tenant.');
        return $row;
    }
    public static function assertCanCreate(PDO $db, string $resource): void {
        $tenant=TenantContext::id(); $sub=self::activeSubscription($db,$tenant);
        if(!in_array($sub['status'],['trial','active','grace_period'],true)) throw new RuntimeException('Your subscription is read-only. Renew it to add new records.');
        $map=['company'=>['companies','company_limit'],'product'=>['products','product_limit'],'customer'=>['retailers','customer_limit'],'user'=>['users','user_limit']];
        if(!isset($map[$resource])) throw new InvalidArgumentException('Invalid subscription resource.');
        [$table,$column]=$map[$resource]; $limit=$sub[$column];
        if($limit===null) return;
        $count=$db->prepare("SELECT COUNT(*) FROM {$table} WHERE tenant_id=:tenant"); $count->execute([':tenant'=>$tenant]);
        if((int)$count->fetchColumn()>=(int)$limit) throw new RuntimeException("{$sub['plan_name']} plan limit reached for {$resource}s. Upgrade to add more.");
    }
}
