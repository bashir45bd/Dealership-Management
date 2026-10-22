<?php
define('APP_INIT', true);
require_once __DIR__ . '/../config/app.php';
function check(string $name, float $actual, float $expected): void { if (abs($actual-$expected)>.001) { fwrite(STDERR,"FAIL $name: $actual != $expected\n"); exit(1); } echo "PASS $name\n"; }
$slabs=[['from_value'=>0,'to_value'=>999,'rate'=>5,'rate_type'=>'fixed'],['from_value'=>1000,'to_value'=>2999,'rate'=>8,'rate_type'=>'fixed'],['from_value'=>3000,'to_value'=>4999,'rate'=>10,'rate_type'=>'fixed'],['from_value'=>5000,'to_value'=>null,'rate'=>12,'rate_type'=>'fixed']];
$scheme=['basis'=>'quantity','calculation_method'=>'volume_slab','slab_mode'=>'whole'];
check('whole slab 5500', CommissionEngine::evaluate($scheme,$slabs,['quantity'=>5500])['amount'],66000);
$scheme['slab_mode']='progressive';
check('progressive slab 5500', CommissionEngine::evaluate($scheme,$slabs,['quantity'=>5500])['amount'],47012);
check('fixed base', CommissionEngine::evaluate(['basis'=>'quantity','calculation_method'=>'fixed_rate','rate'=>5],[],['quantity'=>100])['amount'],500);
check('percentage', CommissionEngine::evaluate(['basis'=>'sales_amount','calculation_method'=>'percentage','rate'=>1],[],['sales_amount'=>50000])['amount'],500);
