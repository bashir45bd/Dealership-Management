<?php
/**
 * Maruf Traders - Database Backup Module
 * One-click full database export as .sql file
 */

define('APP_INIT', true);
$pageTitle = 'Database Backup';
$breadcrumb = 'System & Administration';
$activeMenu = 'backup';

require_once __DIR__ . '/../../includes/header.php';
requirePermission('backup.manage');

$db = Database::getConnection();

$dbNameRow = $db->query("SELECT DATABASE() AS db_name")->fetch();
$currentDb = $dbNameRow['db_name'] ?? 'maruf_traders';

$tables = $db->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
$tableCount = count($tables);

$totalRows = 0;
$totalSizeMb = 0;
try {
    $sizeStmt = $db->prepare("
        SELECT
            COALESCE(SUM(table_rows), 0) AS total_rows,
            COALESCE(SUM(data_length + index_length), 0) AS total_bytes
        FROM information_schema.tables
        WHERE table_schema = :db_name
    ");
    $sizeStmt->execute([':db_name' => $currentDb]);
    $sizeInfo = $sizeStmt->fetch();
    $totalRows = (int)($sizeInfo['total_rows'] ?? 0);
    $totalSizeMb = round(((int)($sizeInfo['total_bytes'] ?? 0)) / 1048576, 2);
} catch (Throwable $e) {
    // Non-fatal — stats are informational only
}
?>

<!-- Page Header -->
<div class="page-header-container">
    <div>
        <h2 class="page-title">Database Backup <span class="fw-normal text-secondary">(ডাটাবেজ ব্যাকআপ)</span></h2>
        <div class="page-subtitle">Download a complete SQL export of your business database — structure and all data included.</div>
    </div>
</div>

<div class="row g-4">
    <div class="col-lg-7">
        <div class="dark-card h-100">
            <div class="card-header-clean">
                <div class="card-title-clean">
                    <i class="fa-solid fa-database text-primary-light"></i>
                    <span>Full Database Export</span>
                </div>
            </div>

            <p class="text-secondary mb-4">
                This will generate a single <code>.sql</code> file containing every table's structure and all current data
                from <strong style="color: var(--text-primary);"><?php echo htmlspecialchars($currentDb); ?></strong>.
                The file can be restored later using phpMyAdmin, MySQL Workbench, or the <code>mysql</code> command line.
            </p>

            <div class="row g-3 mb-4">
                <div class="col-4">
                    <div class="p-3 rounded text-center" style="background: var(--bg-input); border: 1px solid var(--border-color);">
                        <div class="fw-bold fs-5" style="color: var(--text-primary);"><?php echo number_format($tableCount); ?></div>
                        <div class="text-secondary small">Tables</div>
                    </div>
                </div>
                <div class="col-4">
                    <div class="p-3 rounded text-center" style="background: var(--bg-input); border: 1px solid var(--border-color);">
                        <div class="fw-bold fs-5" style="color: var(--text-primary);">~<?php echo number_format($totalRows); ?></div>
                        <div class="text-secondary small">Rows</div>
                    </div>
                </div>
                <div class="col-4">
                    <div class="p-3 rounded text-center" style="background: var(--bg-input); border: 1px solid var(--border-color);">
                        <div class="fw-bold fs-5" style="color: var(--text-primary);"><?php echo number_format($totalSizeMb, 1); ?> MB</div>
                        <div class="text-secondary small">Est. Size</div>
                    </div>
                </div>
            </div>

            <button type="button" class="btn-backup-download" id="btnDownloadBackup">
                <i class="fa-solid fa-download me-2"></i> Download Database (.sql)
            </button>

            <p class="text-secondary small mt-3 mb-0">
                <i class="fa-solid fa-circle-info me-1"></i>
                Large databases may take a moment to generate. Do not close this tab until the download starts.
            </p>
        </div>
    </div>

    <div class="col-lg-5">
        <div class="dark-card h-100">
            <div class="card-header-clean">
                <div class="card-title-clean">
                    <i class="fa-solid fa-shield-halved text-cyan"></i>
                    <span>Notes (মনে রাখুন)</span>
                </div>
            </div>
            <ul class="text-secondary" style="padding-left: 1.1rem; line-height: 1.9;">
                <li>Backups contain sensitive business data — store the downloaded file securely.</li>
                <li>Only users with the <code>backup.manage</code> permission can access this page.</li>
                <li>This export does not affect your live data — it is read-only.</li>
                <li>For very large databases, run this during low-traffic hours.</li>
            </ul>
        </div>
    </div>
</div>

<style>
.btn-backup-download {
    background: #8B5CF6;
    color: #fff;
    border: none;
    border-radius: 10px;
    padding: 12px 26px;
    font-weight: 600;
}
.btn-backup-download:hover {
    background: #7C3AED;
    color: #fff;
}
.btn-backup-download:disabled {
    opacity: 0.6;
}
</style>

<script>
document.getElementById('btnDownloadBackup').addEventListener('click', function () {
    const btn = this;
    const originalHtml = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin me-2"></i> Preparing backup...';

    const iframe = document.createElement('iframe');
    iframe.style.display = 'none';
    iframe.src = '<?php echo BASE_URL; ?>/modules/backup/download.php';
    document.body.appendChild(iframe);

    setTimeout(function () {
        btn.disabled = false;
        btn.innerHTML = originalHtml;
        if (window.Swal) {
            Swal.fire({
                icon: 'success',
                title: 'Backup Ready',
                text: 'Your database backup has been downloaded.',
                confirmButtonColor: '#8B5CF6'
            });
        }
    }, 4000);
});
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>