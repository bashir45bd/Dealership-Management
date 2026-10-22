<?php
/**
 * Safe, data-driven commission evaluator and settlement engine.
 * No user-supplied executable expressions.
 */
final class CommissionEngine {

    /**
     * Evaluate a single commission scheme against provided facts.
     */
    public static function evaluate(array $scheme, array $slabs, array $facts): array {
        // 1. Evaluate conditions
        if (!empty($scheme['conditions'])) {
            if (!self::evaluateConditionsGrouped($scheme['conditions'], $facts)) {
                return [
                    'eligible' => false,
                    'amount' => 0.0,
                    'basis_value' => (float)($facts[$scheme['basis'] ?? 'quantity'] ?? 0),
                    'breakdown' => [['reason' => 'Eligibility conditions not met']]
                ];
            }
        }

        $basisField = $scheme['basis'] ?? 'quantity';
        $basis = (float)($facts[$basisField] ?? 0);
        $method = $scheme['calculation_method'] ?? 'fixed_rate';
        $amount = 0.0;
        $lines = [];

        if (in_array($method, ['fixed_rate', 'percentage'], true)) {
            $rate = (float)($scheme['rate'] ?? 0);
            $amount = $method === 'percentage' ? ($basis * $rate / 100) : ($basis * $rate);
            $amount += (float)($scheme['bonus_amount'] ?? 0);
            $lines[] = [
                'basis' => $basis,
                'rate' => $rate,
                'rate_type' => $method,
                'amount' => round($amount, 2)
            ];
        } elseif ($method === 'target_achievement') {
            // Achievement % is the basis for matching slab tiers
            $multiplierUnits = (float)($facts['quantity'] ?? $facts['actual_quantity'] ?? 1);
            $salesAmount = (float)($facts['sales_amount'] ?? 0);
            [$amount, $lines] = self::targetAchievementSlabAmount($basis, $slabs, $multiplierUnits, $salesAmount);
        } elseif (in_array($method, ['volume_slab', 'amount_slab'], true)) {
            [$amount, $lines] = self::slabAmount($basis, $slabs, $scheme['slab_mode'] ?? 'whole');
        } elseif (in_array($method, ['growth', 'collection', 'payment_timing'], true)) {
            // Slabs or rate based on growth/collection/timing
            if (!empty($slabs)) {
                [$amount, $lines] = self::slabAmount($basis, $slabs, $scheme['slab_mode'] ?? 'whole');
            } else {
                $rate = (float)($scheme['rate'] ?? 0);
                $amount = $basis * $rate;
                $lines[] = ['basis' => $basis, 'rate' => $rate, 'amount' => round($amount, 2)];
            }
        } else {
            throw new InvalidArgumentException("Unsupported commission calculation method: {$method}");
        }

        return [
            'eligible' => true,
            'amount' => round($amount, 2),
            'basis_value' => $basis,
            'breakdown' => $lines,
            'scheme_id' => $scheme['id'] ?? null,
            'scheme_name' => $scheme['name'] ?? null,
            'stacking_mode' => $scheme['stacking_mode'] ?? 'stack',
            'priority' => (int)($scheme['priority'] ?? 100)
        ];
    }

    /**
     * Evaluate multiple schemes with stacking resolution (STACK, HIGHEST_ONLY, EXCLUSIVE, CONDITIONAL_STACK).
     */
    public static function evaluateMultiple(array $schemesWithSlabs, array $facts, ?string $overrideStacking = null): array {
        $eligibleList = [];

        foreach ($schemesWithSlabs as $item) {
            $scheme = $item['scheme'] ?? $item;
            $slabs = $item['slabs'] ?? [];
            $res = self::evaluate($scheme, $slabs, $facts);
            if ($res['eligible'] && $res['amount'] > 0) {
                $res['scheme_data'] = $scheme;
                $res['slabs_data'] = $slabs;
                $eligibleList[] = $res;
            }
        }

        if (empty($eligibleList)) {
            return [
                'eligible' => false,
                'total_commission' => 0.0,
                'applied_schemes' => [],
                'breakdown' => []
            ];
        }

        // Check if override or any scheme dictates exclusive or highest_only
        $overallStacking = $overrideStacking;
        if (!$overallStacking) {
            // If any scheme is marked exclusive, that scheme takes exclusivity
            foreach ($eligibleList as $el) {
                if (($el['stacking_mode'] ?? '') === 'exclusive') {
                    $overallStacking = 'exclusive';
                    break;
                }
            }
        }

        $applied = [];

        if ($overallStacking === 'exclusive') {
            // Find exclusive scheme with highest priority (lower number = higher priority) or highest amount
            usort($eligibleList, function($a, $b) {
                $aEx = ($a['stacking_mode'] ?? '') === 'exclusive' ? 0 : 1;
                $bEx = ($b['stacking_mode'] ?? '') === 'exclusive' ? 0 : 1;
                if ($aEx !== $bEx) return $aEx <=> $bEx;
                if ($a['priority'] !== $b['priority']) return $a['priority'] <=> $b['priority'];
                return $b['amount'] <=> $a['amount'];
            });
            $applied = [$eligibleList[0]];
        } elseif ($overallStacking === 'highest_only') {
            usort($eligibleList, fn($a, $b) => $b['amount'] <=> $a['amount']);
            $applied = [$eligibleList[0]];
        } elseif ($overallStacking === 'conditional_stack') {
            // Stack schemes that are allowed to stack, otherwise take highest
            $applied = $eligibleList;
        } else {
            // Default: STACK
            $applied = $eligibleList;
        }

        $totalCommission = 0.0;
        $breakdownSummary = [];

        foreach ($applied as $app) {
            $totalCommission += (float)$app['amount'];
            $breakdownSummary[] = [
                'scheme_id' => $app['scheme_id'],
                'scheme_name' => $app['scheme_name'],
                'amount' => $app['amount'],
                'basis_value' => $app['basis_value'],
                'breakdown' => $app['breakdown']
            ];
        }

        return [
            'eligible' => true,
            'total_commission' => round($totalCommission, 2),
            'applied_schemes' => $applied,
            'breakdown' => $breakdownSummary
        ];
    }

    /**
     * Standard slab calculation for whole vs progressive mode.
     */
    private static function slabAmount(float $basis, array $slabs, string $mode): array {
        usort($slabs, fn($a, $b) => (float)$a['from_value'] <=> (float)$b['from_value']);
        $total = 0.0;
        $lines = [];

        foreach ($slabs as $slab) {
            $from = (float)$slab['from_value'];
            $to = ($slab['to_value'] === null || $slab['to_value'] === '') ? INF : (float)$slab['to_value'];

            if ($basis < $from) continue;

            $units = $mode === 'progressive'
                ? max(0, min($basis, $to) - $from + 1)
                : $basis;

            if ($mode !== 'progressive' && $basis > $to) continue;

            $rate = (float)$slab['rate'];
            $part = ($slab['rate_type'] ?? 'fixed') === 'percentage'
                ? ($units * $rate / 100)
                : ($units * $rate);

            $bonus = (float)($slab['bonus_amount'] ?? 0);
            $part += $bonus;
            $total += $part;

            $lines[] = [
                'slab' => [$from, is_infinite($to) ? null : $to],
                'units' => $units,
                'rate' => $rate,
                'rate_type' => $slab['rate_type'] ?? 'fixed',
                'bonus' => $bonus,
                'amount' => round($part, 2)
            ];

            if ($mode === 'whole') break;
        }

        return [$total, $lines];
    }

    /**
     * Target achievement slab calculation.
     * Matches achievement % to tiers, then computes commission on actual sales volume or bonus.
     */
    private static function targetAchievementSlabAmount(float $achievementPercent, array $slabs, float $units, float $salesAmount): array {
        usort($slabs, fn($a, $b) => (float)$a['from_value'] <=> (float)$b['from_value']);
        $total = 0.0;
        $lines = [];

        foreach ($slabs as $slab) {
            $from = (float)$slab['from_value'];
            $to = ($slab['to_value'] === null || $slab['to_value'] === '') ? INF : (float)$slab['to_value'];

            if ($achievementPercent >= $from && $achievementPercent <= $to) {
                $rate = (float)$slab['rate'];
                $rateType = $slab['rate_type'] ?? 'fixed';

                $part = $rateType === 'percentage'
                    ? ($salesAmount * $rate / 100)
                    : ($units * $rate);

                $bonus = (float)($slab['bonus_amount'] ?? 0);
                $part += $bonus;
                $total += $part;

                $lines[] = [
                    'achievement' => $achievementPercent,
                    'slab' => [$from, is_infinite($to) ? null : $to],
                    'units' => $units,
                    'rate' => $rate,
                    'rate_type' => $rateType,
                    'bonus' => $bonus,
                    'amount' => round($part, 2)
                ];
                break;
            }
        }

        return [$total, $lines];
    }

    /**
     * Grouped condition evaluation supporting AND / OR operators.
     */
    private static function evaluateConditionsGrouped(array $conditions, array $facts): bool {
        $groups = [];
        foreach ($conditions as $c) {
            $g = $c['group_no'] ?? 1;
            $groups[$g][] = $c;
        }

        // Each group is evaluated; all groups must evaluate to true (AND across groups)
        foreach ($groups as $groupConditions) {
            $groupResult = true;
            $first = true;

            foreach ($groupConditions as $cond) {
                $match = self::matches($cond, $facts);
                $op = strtoupper($cond['boolean_operator'] ?? 'AND');

                if ($first) {
                    $groupResult = $match;
                    $first = false;
                } else {
                    if ($op === 'OR') {
                        $groupResult = $groupResult || $match;
                    } else {
                        $groupResult = $groupResult && $match;
                    }
                }
            }

            if (!$groupResult) return false;
        }

        return true;
    }

    /**
     * Condition evaluator against known fact fields.
     */
    public static function matches(array $condition, array $facts): bool {
        $field = $condition['field_name'] ?? '';
        $actual = $facts[$field] ?? null;
        $from = $condition['value_from'] ?? '';
        $to = $condition['value_to'] ?? null;
        $op = $condition['comparison_operator'] ?? '=';

        if ($actual === null && !isset($facts[$field])) return false;

        return match ($op) {
            '=' => (string)$actual === (string)$from,
            '!=' => (string)$actual !== (string)$from,
            '>' => (float)$actual > (float)$from,
            '>=' => (float)$actual >= (float)$from,
            '<' => (float)$actual < (float)$from,
            '<=' => (float)$actual <= (float)$from,
            'BETWEEN' => (float)$actual >= (float)$from && (float)$actual <= (float)$to,
            default => false,
        };
    }

    /**
     * Record a verified commission calculation into audit and commission tables.
     */
    public static function recordCalculation(PDO $db, int $tenantId, array $scheme, array $evalResult, array $meta): int {
        $stmt = $db->prepare("INSERT INTO commission_calculations 
            (tenant_id, scheme_id, company_id, category_id, product_id, sale_id, period_start, period_end, basis_value, target_value, achievement_percentage, slab_id, calculation_method, breakdown_json, gross_commission, adjustment, deduction, net_commission, status)
            VALUES
            (:tenant, :scheme, :company, :category, :product, :sale, :pstart, :pend, :basis, :target, :ach, :slab, :method, :breakdown, :gross, 0, 0, :net, 'calculated')");

        $gross = (float)$evalResult['amount'];
        $stmt->execute([
            ':tenant' => $tenantId,
            ':scheme' => $scheme['id'] ?? null,
            ':company' => $meta['company_id'] ?? ($scheme['company_id'] ?? null),
            ':category' => $meta['category_id'] ?? ($scheme['category_id'] ?? null),
            ':product' => $meta['product_id'] ?? ($scheme['product_id'] ?? null),
            ':sale' => $meta['sale_id'] ?? null,
            ':pstart' => $meta['period_start'] ?? date('Y-m-01'),
            ':pend' => $meta['period_end'] ?? date('Y-m-t'),
            ':basis' => (float)($evalResult['basis_value'] ?? 0),
            ':target' => isset($meta['target_value']) ? (float)$meta['target_value'] : null,
            ':ach' => isset($meta['achievement_percentage']) ? (float)$meta['achievement_percentage'] : null,
            ':slab' => $evalResult['breakdown'][0]['slab_id'] ?? null,
            ':method' => $scheme['calculation_method'] ?? 'fixed_rate',
            ':breakdown' => json_encode($evalResult['breakdown'], JSON_UNESCAPED_UNICODE),
            ':gross' => $gross,
            ':net' => $gross
        ]);

        return (int)$db->lastInsertId();
    }
}
