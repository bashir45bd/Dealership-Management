<?php
/**
 * Maruf Traders - Commission Rules & Architecture
 */

define('APP_INIT', true);
$pageTitle = 'Commission Rules';
$breadcrumb = 'Commission Rules';
$activeMenu = 'targets';

require_once __DIR__ . '/../../includes/header.php';
requirePermission('targets.manage');

$db = Database::getConnection();
$stmt = $db->query("SELECT cr.*, c.name as company_name, p.name as product_name 
                    FROM commission_rules cr
                    JOIN companies c ON cr.company_id = c.id
                    LEFT JOIN products p ON cr.product_id = p.id
                    ORDER BY cr.id ASC");
$rules = $stmt->fetchAll();
?>

<div class="page-header-container no-print">
    <div>
        <h2 class="page-title">Commission Rules & Logic Engine (কমিশন পলিসি ও নিয়মাবলী)</h2>
        <div class="page-subtitle">Configurable company commission calculation formulas, incentive slabs, and business logic.</div>
    </div>
    <div>
        <a href="<?php echo BASE_URL; ?>/modules/targets/index.php" class="btn btn-secondary-custom">
            <i class="fa-solid fa-bullseye me-1"></i> Monthly Targets
        </a>
    </div>
</div>

<!-- Business Formula Card -->
<div class="dark-card mb-4 border-primary border-opacity-25">
    <div class="card-header-clean">
        <div class="card-title-clean text-primary-light">
            <i class="fa-solid fa-calculator"></i>
            <span>Proportional Achievement Commission Formula Architecture</span>
        </div>
    </div>

    <div class="row g-4 align-items-center">
        <div class="col-lg-6">
            <h6 class="fw-bold mb-2" style="color: var(--text-primary);">Core Mathematical Formula:</h6>
            <div class="p-3 rounded font-monospace small mb-3" style="background: var(--bg-input); border-left: 3px solid var(--primary-light);">
                <div>1. Achievement % = (Actual Sales Bags / Target Bags) × 100</div>
                <div class="mt-1">2. Adjusted Rate / Bag = Full Rate × (Achievement % / 100)</div>
                <div class="mt-1">3. Estimated Commission = Actual Eligible Sales × Adjusted Rate</div>
            </div>
            <p class="text-secondary small mb-0">
                This incentive structure rewards high volume fulfillment by scaling the per-bag commission earned linearly with the achievement ratio.
            </p>
        </div>
        <div class="col-lg-6">
            <div class="p-3 rounded" style="background: rgba(139, 92, 246, 0.08); border: 1px solid rgba(139, 92, 246, 0.25);">
                <div class="fw-bold small text-uppercase mb-2" style="color: var(--text-primary);">Example Calculation (80% Achievement):</div>
                <div class="d-flex justify-content-between small text-secondary py-1 border-bottom border-secondary border-opacity-10">
                    <span>Monthly Target:</span>
                    <span class="fw-bold" style="color: var(--text-primary);">1,000 Bags</span>
                </div>
                <div class="d-flex justify-content-between small text-secondary py-1 border-bottom border-secondary border-opacity-10">
                    <span>Actual Sales:</span>
                    <span class="text-cyan fw-bold">800 Bags (80%)</span>
                </div>
                <div class="d-flex justify-content-between small text-secondary py-1 border-bottom border-secondary border-opacity-10">
                    <span>Full Base Rate:</span>
                    <span style="color: var(--text-primary);">৳ 38.00 / Bag</span>
                </div>
                <div class="d-flex justify-content-between small text-secondary py-1 border-bottom border-secondary border-opacity-10">
                    <span>Adjusted Rate (38 × 80%):</span>
                    <span class="text-primary-light fw-bold">৳ 30.40 / Bag</span>
                </div>
                <div class="d-flex justify-content-between small py-2 fw-bold" style="color: var(--text-primary);">
                    <span>Earned Commission (800 × 30.40):</span>
                    <span class="text-success fs-6">৳ 24,320.00</span>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Active Rules Table -->
<div class="dark-card">
    <div class="card-header-clean">
        <div class="card-title-clean">
            <i class="fa-solid fa-list-check text-cyan"></i>
            <span>Active Company Commission Rules</span>
        </div>
    </div>

    <div class="table-responsive">
        <table class="dark-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Company / Manufacturer</th>
                    <th>Rule Title</th>
                    <th>Calculation Mode</th>
                    <th class="text-end">Full Rate / Bag</th>
                    <th class="text-center">Status</th>
                    <th>Description</th>
                </tr>
            </thead>
            <tbody>
                <?php $i = 1; foreach ($rules as $r): ?>
                    <tr>
                        <td><?php echo $i++; ?></td>
                        <td class="fw-bold" style="color: var(--text-primary);"><?php echo htmlspecialchars($r['company_name']); ?></td>
                        <td class="fw-bold text-info"><?php echo htmlspecialchars($r['rule_name']); ?></td>
                        <td>
                            <span class="badge-custom badge-primary"><?php echo ucfirst($r['commission_type']); ?></span>
                        </td>
                        <td class="text-end fw-bold text-success fs-6"><?php echo formatBDT($r['full_rate_per_bag']); ?></td>
                        <td class="text-center">
                            <span class="badge-custom <?php echo ($r['status'] === 'active') ? 'badge-success' : 'badge-danger'; ?>">
                                <?php echo ucfirst($r['status']); ?>
                            </span>
                        </td>
                        <td class="text-secondary small"><?php echo htmlspecialchars($r['notes'] ?: '—'); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>