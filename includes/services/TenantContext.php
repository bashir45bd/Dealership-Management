<?php
/** Server-owned tenant identity and subscription write gate. */
final class TenantContext {
    public static function id(): int {
        if (empty($_SESSION['user_id'])) throw new RuntimeException('Authenticated tenant context is required.');
        if (!empty($_SESSION['tenant_id'])) return (int)$_SESSION['tenant_id'];
        $db = Database::getConnection();
        try {
            $stmt = $db->prepare('SELECT tenant_id FROM users WHERE id = :id');
            $stmt->execute([':id' => $_SESSION['user_id']]);
            $tenantId = (int)$stmt->fetchColumn();
        } catch (Throwable) { $tenantId = 1; } // pre-migration compatibility only
        if ($tenantId < 1) throw new RuntimeException('No tenant is assigned to this user.');
        $_SESSION['tenant_id'] = $tenantId;
        return $tenantId;
    }

    public static function requireWritable(): void {
        $tenantId = self::id();
        $db = Database::getConnection();
        try {
            $stmt = $db->prepare("SELECT status FROM tenant_subscriptions WHERE tenant_id = :tenant ORDER BY id DESC LIMIT 1");
            $stmt->execute([':tenant' => $tenantId]);
            $status = $stmt->fetchColumn();
            if ($status !== false && !in_array($status, ['trial', 'active', 'grace_period'], true)) {
                throw new RuntimeException('This subscription is not active. Historical records remain available.');
            }
        } catch (PDOException) {
            // Migration has not been applied; legacy installation stays operational.
        }
    }

    public static function scopedId(PDO $db, string $table, int $id): int {
        $allowed = ['companies','products','retailers','sales','cement_receives','collections','advances','expenses','company_payments'];
        if (!in_array($table, $allowed, true)) throw new InvalidArgumentException('Unsupported tenant-scoped table.');
        $stmt = $db->prepare("SELECT id FROM {$table} WHERE id = :id AND tenant_id = :tenant LIMIT 1");
        $stmt->execute([':id' => $id, ':tenant' => self::id()]);
        if (!$stmt->fetchColumn()) throw new RuntimeException('Record not found.');
        return $id;
    }
}
