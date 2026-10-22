<?php
/**
 * Maruf Traders - Global Utility & Financial Business Logic Functions
 */

defined('APP_INIT') or define('APP_INIT', true);

/**
 * Standardized JSON API Response
 */
function jsonResponse(bool $success, string $message = '', array $data = [], int $statusCode = 200): void {
    if (!headers_sent()) {
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=utf-8');
    }
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'data'    => $data
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

/**
 * Fetch a setting from database with caching
 */
function currentSettingsTenantId(): ?int {
    if (!empty($_SESSION['is_platform_admin'])) return null;
    $tenantId = (int)($_SESSION['tenant_id'] ?? 0);
    return $tenantId > 0 ? $tenantId : null;
}

function getSetting(string $key, ?string $default = null): ?string {
    static $settingsCache = [];
    $tenantId = currentSettingsTenantId();
    $cacheKey = $tenantId === null ? 'platform-defaults' : 'tenant-'.$tenantId;

    if (!isset($settingsCache[$cacheKey])) {
        try {
            $db = Database::getConnection();
            if ($tenantId !== null) {
                $stmt = $db->prepare('SELECT s.setting_key, COALESCE(ts.setting_value,s.setting_value) setting_value FROM settings s LEFT JOIN tenant_settings ts ON BINARY ts.setting_key=BINARY s.setting_key AND ts.tenant_id=:tenant');
                $stmt->execute([':tenant'=>$tenantId]);
            } else {
                $stmt = $db->query('SELECT setting_key, setting_value FROM settings');
            }
            $settingsCache[$cacheKey] = [];
            while ($row = $stmt->fetch()) $settingsCache[$cacheKey][$row['setting_key']] = $row['setting_value'];
        } catch (Exception $e) { return $default; }
    }
    return $settingsCache[$cacheKey][$key] ?? $default;
}

/**
 * Update a setting in database
 */
function updateSetting(string $key, string $value): bool {
    try {
        $db = Database::getConnection();
        $tenantId = currentSettingsTenantId();
        if ($tenantId !== null) {
            $stmt = $db->prepare('INSERT INTO tenant_settings (tenant_id,setting_key,setting_value) VALUES (:tenant,:k,:v) ON DUPLICATE KEY UPDATE setting_value=:v2');
            return $stmt->execute([':tenant'=>$tenantId,':k'=>$key,':v'=>$value,':v2'=>$value]);
        }
        $stmt = $db->prepare("INSERT INTO settings (setting_key, setting_value) 
                              VALUES (:k, :v) 
                              ON DUPLICATE KEY UPDATE setting_value = :v2");
        return $stmt->execute([':k' => $key, ':v' => $value, ':v2' => $value]);
    } catch (Exception $e) {
        return false;
    }
}

/**
 * Format currency in Bangladeshi Taka (৳ / Tk)
 */
function formatBDT(float|int|string $amount, bool $showSymbol = true): string {
    $num = (float)$amount;
    $formatted = number_format($num, 2, '.', ',');
    return $showSymbol ? '৳ ' . $formatted : $formatted;
}

/**
 * Format date nicely
 */
function formatDate(?string $dateStr, string $format = 'd M, Y'): string {
    if (empty($dateStr) || $dateStr === '0000-00-00') {
        return '—';
    }
    $timestamp = strtotime($dateStr);
    return $timestamp ? date($format, $timestamp) : '—';
}

/**
 * Format datetime nicely
 */
function formatDateTime(?string $dateStr, string $format = 'd M, Y h:i A'): string {
    if (empty($dateStr) || $dateStr === '0000-00-00 00:00:00') {
        return '—';
    }
    $timestamp = strtotime($dateStr);
    return $timestamp ? date($format, $timestamp) : '—';
}

/**
 * Clean & sanitize user input
 */
function sanitizeInput(mixed $data): mixed {
    if (is_array($data)) {
        foreach ($data as $key => $value) {
            $data[$key] = sanitizeInput($value);
        }
        return $data;
    }
    return htmlspecialchars(trim((string)$data), ENT_QUOTES, 'UTF-8');
}

/**
 * Generate sequential unique business code / ID (e.g. SALE-00001, RET-001)
 */
function generateCode(string $table, string $column, string $prefix, int $padLength = 5, ?int $tenantId = null): string {
    $db = Database::getConnection();
    if ($tenantId === null) {
        $tenantId = !empty($_SESSION['tenant_id']) ? (int)$_SESSION['tenant_id'] : null;
    }

    $hasTenant = false;
    if ($tenantId !== null) {
        try {
            $chk = $db->query("SHOW COLUMNS FROM {$table} LIKE 'tenant_id'");
            $hasTenant = (bool)$chk->fetch();
        } catch (Exception $e) { $hasTenant = false; }
    }

    if ($hasTenant && $tenantId !== null) {
        $sql = "SELECT {$column} FROM {$table} WHERE {$column} LIKE :pattern AND tenant_id = :tenant ORDER BY id DESC LIMIT 1";
        $stmt = $db->prepare($sql);
        $stmt->execute([':pattern' => $prefix . '-%', ':tenant' => $tenantId]);
    } else {
        $sql = "SELECT {$column} FROM {$table} WHERE {$column} LIKE :pattern ORDER BY id DESC LIMIT 1";
        $stmt = $db->prepare($sql);
        $stmt->execute([':pattern' => $prefix . '-%']);
    }
    $lastCode = $stmt->fetchColumn();

    if ($lastCode) {
        if (preg_match('/' . preg_quote($prefix, '/') . '-(\d+)/i', $lastCode, $matches)) {
            $nextNum = (int)$matches[1] + 1;
        } else {
            $nextNum = 1;
        }
    } else {
        $nextNum = 1;
    }

    return sprintf("%s-%0" . $padLength . "d", $prefix, $nextNum);
}

/**
 * Check if an accounting month is closed
 */
function isMonthClosed(string $dateStr, ?int $tenantId = null): bool {
    $timestamp = strtotime($dateStr);
    if (!$timestamp) {
        $timestamp = time();
    }

    $month = (int)date('n', $timestamp);
    $year = (int)date('Y', $timestamp);
    if ($tenantId === null) {
        $tenantId = !empty($_SESSION['tenant_id']) ? (int)$_SESSION['tenant_id'] : 1;
    }

    try {
        $db = Database::getConnection();
        $stmt = $db->prepare("SELECT id FROM monthly_closings 
                              WHERE closing_month = :m AND closing_year = :y AND status = 'closed' AND tenant_id = :tenant LIMIT 1");
        $stmt->execute([':m' => $month, ':y' => $year, ':tenant' => $tenantId]);
        return (bool)$stmt->fetchColumn();
    } catch (Exception $e) {
        return false;
    }
}

/**
 * Assert that the month for a transaction date is open. Throws Exception if closed.
 */
function assertMonthOpen(string $dateStr, ?int $tenantId = null): void {
    if (isMonthClosed($dateStr, $tenantId)) {
        $monthName = date('F Y', strtotime($dateStr));
        throw new Exception("Transaction rejected: The accounting period for {$monthName} is CLOSED. Please contact an Administrator.");
    }
}

/**
 * Record an audit log entry
 */
function logAudit(string $module, string $action, ?string $refId = null, mixed $oldData = null, mixed $newData = null): void {
    try {
        $db = Database::getConnection();
        $userId = $_SESSION['user_id'] ?? null;
        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        $ua = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';

        // Redact any sensitive keys (e.g. passwords)
        $cleanData = function ($data) {
            if (is_array($data)) {
                unset($data['password'], $data['csrf_token']);
                return json_encode($data, JSON_UNESCAPED_UNICODE);
            }
            return is_string($data) ? $data : json_encode($data, JSON_UNESCAPED_UNICODE);
        };

        $stmt = $db->prepare("INSERT INTO audit_logs (tenant_id, user_id, module, action, reference_id, old_data, new_data, ip_address, user_agent)
                              VALUES (:tenant, :uid, :mod, :act, :ref, :old, :new, :ip, :ua)");
        $stmt->execute([
            ':tenant' => $_SESSION['tenant_id'] ?? 1,
            ':uid' => $userId,
            ':mod' => $module,
            ':act' => $action,
            ':ref' => $refId,
            ':old' => $oldData ? $cleanData($oldData) : null,
            ':new' => $newData ? $cleanData($newData) : null,
            ':ip'  => $ip,
            ':ua'  => substr($ua, 0, 500)
        ]);
    } catch (Exception $e) {
        // Silently prevent audit logger from crashing main business operations
        error_log("Audit log failed: " . $e->getMessage());
    }
}

/**
 * Recalculate Monthly Target Achievement and Commission
 */
function recalculateTargetAndCommission(PDO $db, int $companyId, ?int $productId, int $month, int $year, ?int $tenantId = null): void {
    if ($tenantId === null) {
        $tenantId = !empty($_SESSION['tenant_id']) ? (int)$_SESSION['tenant_id'] : 1;
    }

    // 1. Calculate actual sales quantity for this company (and optionally product) in this month/year
    $query = "SELECT COALESCE(SUM(si.quantity), 0) AS total_qty
              FROM sale_items si
              JOIN sales s ON si.sale_id = s.id
              WHERE si.company_id = :comp_id
                AND s.status = 'active'
                AND s.tenant_id = :tenant
                AND MONTH(s.sale_date) = :m
                AND YEAR(s.sale_date) = :y";

    $params = [':comp_id' => $companyId, ':tenant' => $tenantId, ':m' => $month, ':y' => $year];

    if ($productId !== null) {
        $query .= " AND si.product_id = :prod_id";
        $params[':prod_id'] = $productId;
    }

    $stmt = $db->prepare($query);
    $stmt->execute($params);
    $actualSalesQty = (float)$stmt->fetchColumn();

    // 2. Fetch existing monthly target record if any
    $tQuery = "SELECT id, target_quantity, commission_mode, commission_rate, fixed_commission_per_bag 
               FROM monthly_targets 
               WHERE company_id = :comp_id AND target_month = :m AND target_year = :y AND tenant_id = :tenant";
    if ($productId !== null) {
        $tQuery .= " AND product_id = :prod_id";
    } else {
        $tQuery .= " AND product_id IS NULL";
    }

    $tStmt = $db->prepare($tQuery);
    $tStmt->execute($params);
    $target = $tStmt->fetch();

    if ($target) {
        $targetQty = (float)$target['target_quantity'];
        $commissionRate = (float)$target['commission_rate'];
        $commissionMode = $target['commission_mode'] ?: 'proportional';
        $fixedRatePerBag = (float)($target['fixed_commission_per_bag'] ?? 0.00);

        // Calculate achievement percentage
        $achievement = $targetQty > 0 ? round(($actualSalesQty / $targetQty) * 100, 2) : 0.00;
        $achievementCapped = min($achievement, 100.00);

        $proportionalCommission = 0.00;
        if ($commissionMode === 'proportional') {
            if ($achievementCapped >= 100) {
                $adjustedRate = $commissionRate;
            } else {
                $adjustedRate = $commissionRate * ($achievementCapped / 100);
            }
            $proportionalCommission = $actualSalesQty * $adjustedRate;
        } elseif ($commissionMode === 'fixed_per_bag') {
            $proportionalCommission = $actualSalesQty * $commissionRate;
        }

        $fixedCommission = $actualSalesQty * $fixedRatePerBag;
        $estimatedCommission = round($proportionalCommission + $fixedCommission, 2);

        $uStmt = $db->prepare("UPDATE monthly_targets 
                               SET actual_sales_quantity = :act_qty, 
                                   achievement_percentage = :ach, 
                                   estimated_commission = :comm 
                               WHERE id = :id AND tenant_id = :tenant");
        $uStmt->execute([
            ':act_qty' => $actualSalesQty,
            ':ach'     => $achievement,
            ':comm'    => $estimatedCommission,
            ':id'      => $target['id'],
            ':tenant'  => $tenantId
        ]);
    }
}
