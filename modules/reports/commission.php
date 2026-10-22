<?php
/**
 * Dealership Management & Accounting SaaS - Comprehensive Commission & Incentive Report
 */

define('APP_INIT', true);
$pageTitle = 'Commission & Incentive Reports';
$breadcrumb = 'Commission Report';
$activeMenu = 'report_commission';

require_once __DIR__ . '/../../includes/header.php';
requirePermission('reports.financial');

$db = Database::getConnection();
$tenantId = TenantContext::id();

$companyId = (int)($_GET['company_id'] ?? 0);
$startDate = $_GET['start_date'] ?? date('Y-m-01');
$endDate   = $_GET['end_date'] ?? date('Y-m-t');

// Fetch tenant companies
$cStmt = $db->prepare("SELECT id, name FROM companies WHERE tenant_id = :tenant AND status = 'active' ORDER BY name ASC");
$cStmt->execute([':tenant' => $tenantId]);
$companies = $cStmt->fetchAll();

// 1. Calculations summary by scheme & company
$calSql = "SELECT cc.*, cs.name as scheme_name, cs.scheme_type, cs.basis, c.name as company_name 
           FROM commission_calculations cc
           JOIN commission_schemes cs ON cc.scheme_id = cs.id
           JOIN companies c ON cc.company_id = c.id
           WHERE cc.tenant_id = :tenant AND cc.period_start >= :start AND cc.period_end <= :end";
$calParams = [':tenant' => $tenantId, ':start' => $startDate, ':end' => $endDate];
if ($companyId) {
    $calSql .= " AND cc.company_id = :cid";
    $calParams[':cid'] = $companyId;
}
$calSql .= " ORDER BY cc.id DESC";
$calStmt = $db->prepare($calSql);
$calStmt->execute($calParams);
$calculations = $calStmt->fetchAll();

// 2. Company-wise totals
$compTotalsSql = "SELECT c.name as company_name, 
                         COUNT(cc.id) as calc_count, 
                         COALESCE(SUM(cc.amount), 0) as total_earned
                  FROM companies c
                  LEFT JOIN commission_calculations cc ON c.id = cc.company_id 
                       AND cc.tenant_id = :tenant 
                       AND cc.period_start >= :start 
                       AND cc.period_end <= :end
                  WHERE c.tenant_id = :tenant2 AND c.status = 'active'";
$compParams = [':tenant' => $tenantId, ':start' => $startDate, ':end' => $endDate, ':tenant2' => $tenantId];
if ($companyId) {
    $compTotalsSql .= " AND c.id = :cid";
    $compParams[':cid'] = $companyId;
}
$compTotalsSql .= " GROUP BY c.id ORDER BY total_earned DESC";
$ctStmt = $db->prepare($compTotalsSql);
$ctStmt->execute($compParams);
$companyTotals = $ctStmt->fetchAll();

$totalCommissionEarned = 0.0;
foreach ($calculations as $c) {
    $totalCommissionEarned += (float)$c['amount'];
}
?>

<div class="page-header-container no-print">
    <div>
        <h2 class="page-title">Commission & Incentive Reports (কমিশন ও ইনসেন্টিভ রিপোর্ট)</h2>
        <div class="page-subtitle">Track scheme-wise, company-wise and product-wise commission accruals and incentive earnings.</div>
    </div>
    <div>
        <button type="button" class="btn btn-primary-custom" onclick="window.print()">
            <i class="fa-solid fa-print me-1"></i> Print Report
        </button>
    </div>
</div>

<!-- Filter Bar -->
<div class="dark-card mb-4 no-print">
    <form method="GET" action="" class="row g-3 align-items-end">
        <div class="col-md-4">
            <label class="form-label-custom">Company</label>
            <select name="company_id" class="form-select form-select-custom">
                <option value="">-- All Companies --</option>
                <?php foreach ($companies as $comp): ?>
                    <option value="<?php echo $comp['id']; ?>" <?php echo ($comp['id'] == $companyId) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($comp['name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-3">
            <label class="form-label-custom">Period Start</label>
            <input type="date" name="start_date" class="form-control form-control-custom" value="<?php echo htmlspecialchars($startDate); ?>">
        </div>
        <div class="col-md-3">
            <label class="form-label-custom">Period End</label>
            <input type="date" name="end_date" class="form-control form-control-custom" value="<?php echo htmlspecialchars($endDate); ?>">
        </div>
        <div class="col-md-2">
            <button type="submit" class="btn btn-primary-custom w-100">
                <i class="fa-solid fa-filter me-1"></i> Filter
            </button>
        </div>
    </form>
</div>

<!-- KPI Cards -->
<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="dark-card py-3 text-center">
            <div class="text-secondary small">Total Accrued Commission</div>
            <div class="fs-4 fw-bold text-cyan">৳ <?php echo number_format($totalCommissionEarned, 2); ?></div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="dark-card py-3 text-center">
            <div class="text-secondary small">Calculation Entries</div>
            <div class="fs-4 fw-bold text-primary-light"><?php echo count($calculations); ?></div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="dark-card py-3 text-center">
            <div class="text-secondary small">Active Partner Companies</div>
            <div class="fs-4 fw-bold text-success"><?php echo count($companyTotals); ?></div>
        </div>
    </div>
</div>

<div class="row g-4">
    <!-- Company Performance Summary -->
    <div class="col-lg-5">
        <div class="dark-card">
            <div class="card-header-clean">
                <div class="card-title-clean">
                    <i class="fa-solid fa-chart-pie text-cyan"></i>
                    <span>Company-Wise Commission Share</span>
                </div>
            </div>
            <div class="table-responsive">
                <table class="dark-table">
                    <thead>
                        <tr>
                            <th>Company</th>
                            <th class="text-end">Schemes Triggered</th>
                            <th class="text-end">Total Commission (৳)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($companyTotals as $ct): ?>
                            <tr>
                                <td class="fw-bold"><?php echo htmlspecialchars($ct['company_name']); ?></td>
                                <td class="text-end"><?php echo (int)$ct['calc_count']; ?></td>
                                <td class="text-end text-cyan fw-bold font-monospace"><?php echo number_format($ct['total_earned'], 2); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Scheme-Wise Calculation Audit -->
    <div class="col-lg-7">
        <div class="dark-card">
            <div class="card-header-clean">
                <div class="card-title-clean">
                    <i class="fa-solid fa-list-check text-primary-light"></i>
                    <span>Accrued Scheme Calculations</span>
                </div>
            </div>
            <div class="table-responsive">
                <table class="dark-table">
                    <thead>
                        <tr>
                            <th>Company</th>
                            <th>Scheme Name</th>
                            <th>Type</th>
                            <th>Period</th>
                            <th class="text-end">Amount (৳)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($calculations)): ?>
                            <tr><td colspan="5" class="text-center py-4 text-secondary">No commission calculations found for selected filter.</td></tr>
                        <?php else: ?>
                            <?php foreach ($calculations as $cal): ?>
                                <tr>
                                    <td class="fw-bold"><?php echo htmlspecialchars($cal['company_name']); ?></td>
                                    <td><?php echo htmlspecialchars($cal['scheme_name']); ?></td>
                                    <td><span class="badge bg-secondary"><?php echo htmlspecialchars($cal['scheme_type']); ?></span></td>
                                    <td class="small text-secondary"><?php echo htmlspecialchars($cal['period_start'] . ' to ' . $cal['period_end']); ?></td>
                                    <td class="text-end fw-bold text-success font-monospace">৳ <?php echo number_format($cal['amount'], 2); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
