<?php
define('APP_INIT', true);
require_once __DIR__ . '/../config/app.php';
$_SESSION['user_id'] = 1;
$_SESSION['tenant_id'] = 1;

$pages = [
    'modules/dashboard/index.php',
    'modules/sales/new.php',
    'modules/stock/index.php',
    'modules/expenses/index.php',
    'modules/reports/monthly.php',
    'modules/reports/yearly.php',
    'modules/reports/commission.php'
];

foreach ($pages as $p) {
    ob_start();
    try {
        include __DIR__ . '/../' . $p;
        $out = ob_get_clean();
        echo "$p => OK (Length: " . strlen($out) . ")\n";
    } catch (Throwable $e) {
        ob_end_clean();
        echo "$p => ERROR: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine() . "\n";
    }
}
