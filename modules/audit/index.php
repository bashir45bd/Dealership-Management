<?php
/**
 * Maruf Traders - System Audit Logs & Activity Trail
 */

define('APP_INIT', true);
$pageTitle = 'Audit Logs';
$breadcrumb = 'Audit Logs';
$activeMenu = 'audit';

require_once __DIR__ . '/../../includes/header.php';
requirePermission('audit.view');

$db = Database::getConnection();

$startDate = $_GET['start_date'] ?? date('Y-m-01');
$endDate = $_GET['end_date'] ?? date('Y-m-d');
$module = trim($_GET['module'] ?? '');
$action = trim($_GET['action'] ?? '');

$sql = "SELECT al.*, u.name as user_name, u.username 
        FROM audit_logs al
        LEFT JOIN users u ON al.user_id = u.id
        WHERE al.created_at >= :start AND al.created_at <= :end";
$params = [':start' => $startDate . ' 00:00:00', ':end' => $endDate . ' 23:59:59'];

if (!empty($module)) {
    $sql .= " AND al.module = :mod";
    $params[':mod'] = $module;
}
if (!empty($action)) {
    $sql .= " AND al.action = :act";
    $params[':act'] = $action;
}

$sql .= " ORDER BY al.id DESC LIMIT 150";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$logs = $stmt->fetchAll();
?>

<div class="page-header-container no-print">
    <div>
        <h2 class="page-title">System Audit Logs (নিরীক্ষা ও নিরাপত্তা লগ)</h2>
        <div class="page-subtitle">Track all operational activities, transactions, deletions, cancellations, login history, and configuration updates.</div>
    </div>
    <div>
        <button type="button" class="btn btn-primary-custom" onclick="window.print()">
            <i class="fa-solid fa-print me-1"></i> Print Audit Trail
        </button>
    </div>
</div>

<!-- Filter Bar -->
<div class="dark-card mb-4 no-print">
    <form method="GET" action="" class="row g-3 align-items-end">
        <div class="col-md-3">
            <label for="module" class="form-label-custom">Module</label>
            <select name="module" id="module" class="form-select form-select-custom">
                <option value="">-- All Modules --</option>
                <option value="auth" <?php echo ($module === 'auth') ? 'selected' : ''; ?>>Authentication (Login/Logout)</option>
                <option value="sales" <?php echo ($module === 'sales') ? 'selected' : ''; ?>>Sales</option>
                <option value="retailers" <?php echo ($module === 'retailers') ? 'selected' : ''; ?>>Retailers</option>
                <option value="companies" <?php echo ($module === 'companies') ? 'selected' : ''; ?>>Companies</option>
                <option value="products" <?php echo ($module === 'products') ? 'selected' : ''; ?>>Products</option>
                <option value="cement_receives" <?php echo ($module === 'cement_receives') ? 'selected' : ''; ?>>Cement Receives</option>
                <option value="collections" <?php echo ($module === 'collections') ? 'selected' : ''; ?>>Collections</option>
                <option value="advances" <?php echo ($module === 'advances') ? 'selected' : ''; ?>>Advances</option>
                <option value="expenses" <?php echo ($module === 'expenses') ? 'selected' : ''; ?>>Expenses</option>
                <option value="company_payments" <?php echo ($module === 'company_payments') ? 'selected' : ''; ?>>Company Payments</option>
                <option value="stock" <?php echo ($module === 'stock') ? 'selected' : ''; ?>>Stock Adjustments</option>
                <option value="monthly_closing" <?php echo ($module === 'monthly_closing') ? 'selected' : ''; ?>>Monthly Closing</option>
                <option value="settings" <?php echo ($module === 'settings') ? 'selected' : ''; ?>>Settings</option>
            </select>
        </div>
        <div class="col-md-3">
            <label for="start_date" class="form-label-custom">From Date</label>
            <input type="date" name="start_date" id="start_date" class="form-control form-control-custom" value="<?php echo htmlspecialchars($startDate); ?>">
        </div>
        <div class="col-md-3">
            <label for="end_date" class="form-label-custom">To Date</label>
            <input type="date" name="end_date" id="end_date" class="form-control form-control-custom" value="<?php echo htmlspecialchars($endDate); ?>">
        </div>
        <div class="col-md-3">
            <button type="submit" class="btn btn-primary-custom w-100">
                <i class="fa-solid fa-filter me-1"></i> Filter Logs
            </button>
        </div>
    </form>
</div>

<!-- Logs Table -->
<div class="dark-card">
    <div class="card-header-clean">
        <div class="card-title-clean">
            <i class="fa-solid fa-shield-halved text-cyan"></i>
            <span>Recorded Audit Trails (Latest 150 Events)</span>
        </div>
    </div>

    <div class="table-responsive">
        <table class="dark-table">
            <thead>
                <tr>
                    <th>Timestamp</th>
                    <th>User</th>
                    <th>Module</th>
                    <th>Action</th>
                    <th>Reference</th>
                    <th>IP Address</th>
                    <th class="text-end no-print">Data Diff</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($logs)): ?>
                    <tr><td colspan="7" class="text-center text-muted py-4">No audit logs recorded for this period.</td></tr>
                <?php else: ?>
                    <?php foreach ($logs as $l): ?>
                        <tr>
                            <td class="text-secondary small"><?php echo formatDateTime($l['created_at']); ?></td>
                            <td>
                                <?php if ($l['user_name']): ?>
                                    <div class="fw-bold text-white"><?php echo htmlspecialchars($l['user_name']); ?></div>
                                    <div class="text-secondary small">@<?php echo htmlspecialchars($l['username']); ?></div>
                                <?php else: ?>
                                    <span class="text-muted">System / Unauthenticated</span>
                                <?php endif; ?>
                            </td>
                            <td><span class="badge-custom badge-primary font-monospace"><?php echo htmlspecialchars($l['module']); ?></span></td>
                            <td>
                                <?php 
                                    $actClass = 'badge-primary';
                                    if (str_contains($l['action'], 'create') || str_contains($l['action'], 'save')) $actClass = 'badge-success';
                                    if (str_contains($l['action'], 'cancel') || str_contains($l['action'], 'delete') || str_contains($l['action'], 'close')) $actClass = 'badge-danger';
                                    if (str_contains($l['action'], 'reopen') || str_contains($l['action'], 'update')) $actClass = 'badge-warning';
                                ?>
                                <span class="badge-custom <?php echo $actClass; ?>"><?php echo htmlspecialchars($l['action']); ?></span>
                            </td>
                            <td class="fw-bold text-info font-monospace"><?php echo htmlspecialchars($l['reference_id'] ?: '—'); ?></td>
                            <td class="text-secondary small"><?php echo htmlspecialchars($l['ip_address']); ?></td>
                            <td class="text-end no-print">
                                <?php if ($l['old_data'] || $l['new_data']): ?>
                                    <button type="button" class="btn btn-secondary-custom btn-sm py-1 px-2" 
                                            onclick="viewAuditDiff('<?php echo htmlspecialchars(addslashes($l['old_data'] ?? '')); ?>', '<?php echo htmlspecialchars(addslashes($l['new_data'] ?? '')); ?>')">
                                        <i class="fa-solid fa-code text-cyan me-1"></i> Diff
                                    </button>
                                <?php else: ?>
                                    <span class="text-muted small">—</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Diff Viewer Modal -->
<div class="modal fade" id="diffModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content dark-modal">
            <div class="modal-header dark-modal-header">
                <h5 class="modal-title text-white"><i class="fa-solid fa-code-compare text-primary-light me-2"></i> Audit Payload Data</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label-custom text-danger">Previous / Old Data</label>
                        <pre id="oldDataBox" class="p-3 rounded small" style="background: #0F172A; border: 1px solid var(--border-color); color: #F8FAFC; max-height: 300px; overflow-y: auto;">None</pre>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label-custom text-success">New / Updated Data</label>
                        <pre id="newDataBox" class="p-3 rounded small" style="background: #0F172A; border: 1px solid var(--border-color); color: #F8FAFC; max-height: 300px; overflow-y: auto;">None</pre>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
let diffModalInstance = null;

function viewAuditDiff(oldData, newData) {
    const formatJson = (str) => {
        if (!str) return 'No data recorded.';
        try {
            const obj = JSON.parse(str);
            return JSON.stringify(obj, null, 2);
        } catch (e) {
            return str;
        }
    };

    document.getElementById('oldDataBox').innerText = formatJson(oldData);
    document.getElementById('newDataBox').innerText = formatJson(newData);

    if (!diffModalInstance) {
        diffModalInstance = new bootstrap.Modal(document.getElementById('diffModal'));
    }
    diffModalInstance.show();
}
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
