<?php
/**
 * Maruf Traders - Monthly Targets & Commission Engine
 * (Updated: adds Fixed Per-Bag Commission — achievement % এর সাথে সম্পর্কহীন,
 *  proportional commission-এর উপরে stacked হিসেবে যোগ হয়)
 */

define('APP_INIT', true);
$pageTitle = 'Monthly Targets & Commission';
$breadcrumb = 'Monthly Targets';
$activeMenu = 'targets';

require_once __DIR__ . '/../../includes/header.php';
requirePermission('targets.manage');

$db = Database::getConnection();

$selectedMonth = (int)($_GET['month'] ?? date('n'));
$selectedYear = (int)($_GET['year'] ?? date('Y'));

$cStmt = $db->query("SELECT id, name FROM companies WHERE status = 'active' ORDER BY name ASC");
$allCompanies = $cStmt->fetchAll();

// Sync/Recalculate all targets for selected period
foreach ($allCompanies as $comp) {
    recalculateTargetAndCommission($db, (int)$comp['id'], null, $selectedMonth, $selectedYear);
}

// Fetch targets
$stmt = $db->prepare("SELECT mt.*, c.name as company_name, p.name as product_name 
                      FROM monthly_targets mt
                      JOIN companies c ON mt.company_id = c.id
                      LEFT JOIN products p ON mt.product_id = p.id
                      WHERE mt.target_month = :m AND mt.target_year = :y
                      ORDER BY mt.id DESC");
$stmt->execute([':m' => $selectedMonth, ':y' => $selectedYear]);
$targets = $stmt->fetchAll();

$totalTargetBags = 0;
$totalActualBags = 0;
$totalEstimatedCommission = 0;

foreach ($targets as $t) {
    $totalTargetBags += (int)$t['target_quantity'];
    $totalActualBags += (int)$t['actual_sales_quantity'];
    $totalEstimatedCommission += (float)$t['estimated_commission'];
}

$overallAchievement = $totalTargetBags > 0 ? round(($totalActualBags / $totalTargetBags) * 100, 1) : 0;

// Never allow overall achievement to exceed 100%
$overallAchievement = min($overallAchievement, 100);
?>

<div class="page-header-container no-print">
    <div>
        <h2 class="page-title">Monthly Target & Commission (মাসিক টার্গেট ও কমিশন ইঞ্জিন)</h2>
        <div class="page-subtitle">Track sales performance against supplier monthly volume targets and automatic proportional + fixed commission calculation.</div>
    </div>
    <div class="d-flex gap-2">
        <a href="<?php echo BASE_URL; ?>/modules/commission/index.php" class="btn btn-secondary-custom">
            <i class="fa-solid fa-gear me-1"></i> Commission Rules
        </a>
        <button type="button" class="btn btn-primary-custom" onclick="openTargetModal()">
            <i class="fa-solid fa-plus me-1"></i> Set New Target
        </button>
    </div>
</div>

<!-- Period Filter -->
<div class="dark-card mb-4 no-print">
    <form method="GET" action="" class="row g-3 align-items-end">
        <div class="col-md-5">
            <label for="month" class="form-label-custom">Select Month</label>
            <select name="month" id="month" class="form-select form-select-custom" onchange="this.form.submit()">
                <?php for ($m = 1; $m <= 12; $m++): ?>
                    <option value="<?php echo $m; ?>" <?php echo ($m == $selectedMonth) ? 'selected' : ''; ?>>
                        <?php echo date('F', mktime(0, 0, 0, $m, 10)); ?>
                    </option>
                <?php endfor; ?>
            </select>
        </div>

        <div class="col-md-5">
            <label for="year" class="form-label-custom">Select Year</label>
            <select name="year" id="year" class="form-select form-select-custom" onchange="this.form.submit()">
                <?php for ($y = date('Y') - 1; $y <= date('Y') + 2; $y++): ?>
                    <option value="<?php echo $y; ?>" <?php echo ($y == $selectedYear) ? 'selected' : ''; ?>>
                        <?php echo $y; ?>
                    </option>
                <?php endfor; ?>
            </select>
        </div>

        <div class="col-md-2">
            <button type="submit" class="btn btn-primary-custom w-100">
                <i class="fa-solid fa-arrows-rotate me-1"></i> Sync
            </button>
        </div>
    </form>
</div>

<!-- KPI Strip -->
<div class="row g-3 mb-4">

    <div class="col-md-3">
        <div class="dark-card py-3 text-center">
            <div class="text-secondary small fw-bold text-uppercase">Total Target Quantity</div>
            <div class="fs-4 fw-bold" style="color: var(--text-primary);">
                <?php echo number_format($totalTargetBags); ?> Bags
            </div>
        </div>
    </div>

    <div class="col-md-3">
        <div class="dark-card py-3 text-center">
            <div class="text-secondary small fw-bold text-uppercase">Actual Sales Achieved</div>
            <div class="fs-4 fw-bold text-cyan">
                <?php echo number_format($totalActualBags); ?> Bags
            </div>
        </div>
    </div>

    <div class="col-md-3">
        <div class="dark-card py-3 text-center">
            <div class="text-secondary small fw-bold text-uppercase">Overall Achievement</div>
            <div class="fs-4 fw-bold <?php echo ($overallAchievement >= 100) ? 'text-success' : 'text-warning'; ?>">
                <?php echo $overallAchievement; ?>%
            </div>
        </div>
    </div>

    <div class="col-md-3">
        <div class="dark-card py-3 text-center">
            <div class="text-secondary small fw-bold text-uppercase">Total Est. Commission Income</div>
            <div class="fs-4 fw-bold text-success">
                <?php echo formatBDT($totalEstimatedCommission); ?>
            </div>
        </div>
    </div>

</div>

<!-- Target Cards Grid -->
<div class="row g-4 mb-4">

    <?php if (empty($targets)): ?>

        <div class="col-12">
            <div class="dark-card text-center py-5 text-muted">

                <i class="fa-solid fa-bullseye fa-3x mb-3 text-secondary opacity-50"></i>

                <h5>
                    No sales targets defined for
                    <?php echo date('F Y', mktime(0,0,0,$selectedMonth,10,$selectedYear)); ?>
                </h5>

                <p class="small">
                    Set a monthly target for cement companies to enable automatic proportional commission tracking.
                </p>

                <button type="button" class="btn btn-primary-custom mt-2" onclick="openTargetModal()">
                    <i class="fa-solid fa-plus me-1"></i> Create Target Now
                </button>

            </div>
        </div>

    <?php else: ?>

        <?php foreach ($targets as $t): ?>

            <?php 
                /*
                 * Achievement percentage is capped at 100%.
                 *
                 * Example:
                 * 1000 target / 1000 sold = 100%
                 * 1000 target / 1020 sold = 100%
                 * 1000 target / 1100 sold = 100%
                 */
                $ach = min((float)$t['achievement_percentage'], 100);

                $targetBags = (int)$t['target_quantity'];
                $actualBags = (int)$t['actual_sales_quantity'];

                $remainingBags = max(0, $targetBags - $actualBags);

                $fullRate = (float)$t['commission_rate'];
                $fixedRate = (float)$t['fixed_commission_per_bag']; // NEW: flat per-bag, no relation to achievement %

                /*
                 * Before 100%:
                 *     Proportional rate increases proportionally.
                 *
                 * At 100% and above:
                 *     Full proportional rate applies.
                 *
                 * IMPORTANT:
                 * The proportional rate never becomes higher than the full rate.
                 * The fixed rate is UNAFFECTED by achievement % — always full,
                 * multiplied straight by actual bags sold.
                 */
                if ($ach >= 100) {
                    $adjustedRate = $fullRate;
                } else {
                    $adjustedRate = ($ach > 0)
                        ? ($fullRate * ($ach / 100))
                        : 0;
                }

                // NEW: Fixed commission is flat -- actual_bags x fixed_rate, always
                $fixedCommissionTotal = $actualBags * $fixedRate;

                /*
                 * Display commission calculation.
                 *
                 * At 100% or above:
                 *     (Actual bags × Full Rate) + (Actual bags × Fixed Rate)
                 *
                 * Below 100%:
                 *     (Actual bags × Adjusted Rate) + (Actual bags × Fixed Rate)
                 *
                 * Example:
                 * Target = 1000, Actual = 1020, Rate = 20, Fixed = 10
                 * Proportional = 1020 × 20 = 20,400
                 * Fixed        = 1020 × 10 = 10,200
                 * Commission   = 30,600
                 */
                if ($ach >= 100) {
                    $proportionalCommission = $actualBags * $fullRate;
                } else {
                    $proportionalCommission = $actualBags * $adjustedRate;
                }

                $displayCommission = $proportionalCommission + $fixedCommissionTotal;
            ?>

            <div class="col-md-6">

                <div class="dark-card h-100">

                    <div class="d-flex justify-content-between align-items-start mb-3 border-bottom border-secondary border-opacity-25 pb-3">

                        <div>

                            <h5 class="fw-bold mb-1" style="color: var(--text-primary);">
                                <?php echo htmlspecialchars($t['company_name']); ?>
                            </h5>

                            <span class="badge-custom badge-primary font-monospace">
                                <?php echo htmlspecialchars($t['target_code']); ?>
                            </span>

                        </div>

                        <div class="text-end">

                            <span class="badge-custom <?php echo ($ach >= 100) ? 'badge-success' : 'badge-warning'; ?> fs-6">

                                <?php echo $ach; ?>% Achieved

                            </span>

                        </div>

                    </div>


                    <!-- Progress Bar -->
                    <div class="mb-3">

                        <div class="d-flex justify-content-between small text-secondary mb-1">

                            <span>
                                Progress:
                                <?php echo number_format($actualBags); ?>
                                /
                                <?php echo number_format($targetBags); ?>
                                Bags
                            </span>

                            <span class="<?php echo $remainingBags === 0 ? 'text-success fw-bold' : ''; ?>">

                                <?php echo $remainingBags > 0
                                    ? number_format($remainingBags) . ' bags remaining'
                                    : 'Target Exceeded 🎉'; ?>

                            </span>

                        </div>


                        <div class="stock-progress" style="height: 10px;">

                            <div
                                class="stock-progress-bar <?php echo ($ach >= 100) ? 'bar-green' : 'bar-purple'; ?>"
                                style="width: <?php echo min($ach, 100); ?>%;">
                            </div>

                        </div>

                    </div>


                    <!-- Commission Breakdown Formula Box -->
                    <div class="profit-breakdown-box">

                        <div class="profit-item">

                            <span class="text-secondary">
                                Full Commission Rate:
                            </span>

                            <span style="color: var(--text-primary);">
                                ৳ <?php echo number_format($fullRate, 2); ?> / Bag
                            </span>

                        </div>


                        <div class="profit-item">

                            <span class="text-secondary">
                                <?php echo ($ach >= 100)
                                    ? 'Full Rate (100%+):'
                                    : 'Adjusted Rate (Rate × ' . $ach . '%):'; ?>
                            </span>

                            <span class="text-cyan fw-bold">
                                ৳ <?php echo number_format($adjustedRate, 2); ?> / Bag
                            </span>

                        </div>

                        <div class="profit-item">
                            <span class="text-secondary">
                                Proportional Commission (<?php echo number_format($actualBags); ?> bags):
                            </span>
                            <span style="color: var(--text-primary);">
                                ৳ <?php echo number_format($proportionalCommission, 2); ?>
                            </span>
                        </div>

                        <?php if ($fixedRate > 0): ?>
                        <div class="profit-item">
                            <span class="text-secondary">
                                Fixed Commission (৳<?php echo number_format($fixedRate, 2); ?> × <?php echo number_format($actualBags); ?> bags, achievement-independent):
                            </span>
                            <span class="text-cyan fw-bold">
                                ৳ <?php echo number_format($fixedCommissionTotal, 2); ?>
                            </span>
                        </div>
                        <?php endif; ?>


                        <div class="profit-item net-profit">

                            <span style="color: var(--text-primary);">
                                Estimated Commission Income:
                            </span>

                            <span class="text-success fw-bold fs-5">
                                <?php echo formatBDT($displayCommission); ?>
                            </span>

                        </div>

                    </div>

                </div>

            </div>

        <?php endforeach; ?>

    <?php endif; ?>

</div>

<!-- Add Target Modal -->
<div class="modal fade" id="targetModal" tabindex="-1" aria-hidden="true">

    <div class="modal-dialog modal-dialog-centered">

        <div class="modal-content dark-modal">

            <div class="modal-header dark-modal-header">

                <h5 class="modal-title" style="color: var(--text-primary);">
                    Set Monthly Sales Target
                </h5>

                <button
                    type="button"
                    class="btn-close btn-close-white"
                    data-bs-dismiss="modal"
                    aria-label="Close">
                </button>

            </div>


            <form id="targetForm">

                <?php echo csrfField(); ?>

                <div class="modal-body p-4">

                    <div class="mb-3">

                        <label for="tgt_company_id" class="form-label-custom">
                            Company / Supplier
                            <span class="required">*</span>
                        </label>

                        <select
                            class="form-select form-select-custom"
                            id="tgt_company_id"
                            name="company_id"
                            required>

                            <option value="">
                                -- Select Company --
                            </option>

                            <?php foreach ($allCompanies as $comp): ?>

                                <option value="<?php echo $comp['id']; ?>">
                                    <?php echo htmlspecialchars($comp['name']); ?>
                                </option>

                            <?php endforeach; ?>

                        </select>

                    </div>


                    <div class="row g-3 mb-3">

                        <div class="col-md-6">

                            <label for="tgt_month" class="form-label-custom">
                                Target Month
                                <span class="required">*</span>
                            </label>

                            <select
                                class="form-select form-select-custom"
                                id="tgt_month"
                                name="target_month"
                                required>

                                <?php for ($m = 1; $m <= 12; $m++): ?>

                                    <option
                                        value="<?php echo $m; ?>"
                                        <?php echo ($m == $selectedMonth) ? 'selected' : ''; ?>>

                                        <?php echo date('F', mktime(0, 0, 0, $m, 10)); ?>

                                    </option>

                                <?php endfor; ?>

                            </select>

                        </div>


                        <div class="col-md-6">

                            <label for="tgt_year" class="form-label-custom">
                                Target Year
                                <span class="required">*</span>
                            </label>

                            <input
                                type="number"
                                class="form-control form-control-custom"
                                id="tgt_year"
                                name="target_year"
                                value="<?php echo $selectedYear; ?>"
                                required>

                        </div>

                    </div>


                    <div class="row g-3 mb-3">

                        <div class="col-md-6">

                            <label for="tgt_quantity" class="form-label-custom">
                                Target Quantity (Bags)
                                <span class="required">*</span>
                            </label>

                            <input
                                type="number"
                                class="form-control form-control-custom"
                                id="tgt_quantity"
                                name="target_quantity"
                                min="1"
                                required
                                placeholder="e.g. 1000">

                        </div>


                        <div class="col-md-6">

                            <label for="tgt_commission_rate" class="form-label-custom">
                                Full Commission Rate / Bag (৳)
                            </label>

                            <input
                                type="number"
                                step="0.01"
                                class="form-control form-control-custom"
                                id="tgt_commission_rate"
                                name="commission_rate"
                                value="38.00">

                        </div>

                    </div>

                    <div class="row g-3 mb-3">

                        <div class="col-md-6">

                            <label for="tgt_fixed_commission" class="form-label-custom">
                                Fixed Commission / Bag (৳)
                            </label>

                            <input
                                type="number"
                                step="0.01"
                                class="form-control form-control-custom"
                                id="tgt_fixed_commission"
                                name="fixed_commission_per_bag"
                                value="0.00"
                                placeholder="e.g. 10.00">

                            <small class="text-secondary d-block mt-1">
                                Achievement % এর সাথে সম্পর্কহীন — যত বস্তা বিক্রি হবে ততই যোগ হবে।
                            </small>

                        </div>

                        <div class="col-md-6">

                            <label for="tgt_commission_mode" class="form-label-custom">
                                Commission Mode
                            </label>

                            <select
                                class="form-select form-select-custom"
                                id="tgt_commission_mode"
                                name="commission_mode">

                                <option value="proportional">
                                    Proportional Achievement Mode (Rate × Achievement %)
                                </option>

                                <option value="fixed_per_bag">
                                    Fixed Rate per Eligible Bag
                                </option>

                            </select>

                        </div>

                    </div>


                    <div class="mb-3">

                        <label for="tgt_notes" class="form-label-custom">
                            Notes
                        </label>

                        <input
                            type="text"
                            class="form-control form-control-custom"
                            id="tgt_notes"
                            name="notes"
                            placeholder="Optional notes">

                    </div>

                </div>


                <div class="modal-footer dark-modal-footer">

                    <button
                        type="button"
                        class="btn btn-secondary-custom"
                        data-bs-dismiss="modal">

                        Cancel

                    </button>


                    <button
                        type="submit"
                        class="btn btn-primary-custom"
                        id="saveTgtBtn">

                        <i class="fa-solid fa-floppy-disk me-1"></i>
                        Save Target

                    </button>

                </div>

            </form>

        </div>

    </div>

</div>


<script>

let tgtModalInstance = null;

function openTargetModal() {

    document.getElementById('targetForm').reset();

    if (!tgtModalInstance) {

        tgtModalInstance = new bootstrap.Modal(
            document.getElementById('targetModal')
        );

    }

    tgtModalInstance.show();

}


/**
 * Direct fetch()-based AJAX submit handler.
 * setupAjaxForm() was not preventing the browser's default form submission
 * (GET request, page navigation), so targets were never actually saved.
 */
document.getElementById('targetForm').addEventListener('submit', async function (e) {

    e.preventDefault();

    const form = e.target;
    const formData = new FormData(form);

    const saveBtn = document.getElementById('saveTgtBtn');
    const originalBtnHtml = saveBtn.innerHTML;

    saveBtn.disabled = true;

    saveBtn.innerHTML =
        '<i class="fa-solid fa-spinner fa-spin me-1"></i> Saving...';

    try {

        const res = await fetch(
            `${window.BASE_URL}/ajax/targets/save.php`,
            {
                method: 'POST',
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            }
        );

        const result = await res.json();

        if (result.success) {

            if (tgtModalInstance) {
                tgtModalInstance.hide();
            }

            setTimeout(
                () => window.location.reload(),
                500
            );

        } else {

            alert(
                result.message ||
                'Failed to save target.'
            );

        }

    } catch (err) {

        console.error(
            'Target save failed:',
            err
        );

        alert(
            'Something went wrong while saving. Check the browser console for details.'
        );

    } finally {

        saveBtn.disabled = false;
        saveBtn.innerHTML = originalBtnHtml;

    }

});

</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>