<?php
// Unit test for the pricing engine with hand-checked numbers: php tests/CalculatorTest.php
declare(strict_types=1);
require __DIR__ . '/../src/QuotationCalculator.php';

$tour = ['min_pax' => 10, 'foc' => 1, 'rate_usd' => 16000, 'rate_rmb' => 2200, 'final_selling' => 9000000];
$items = [
    // Hotel: USD 2,000 for the whole group of 10 -> 2,000 x 16,000 / 10 = 3,200,000 per pax; 20% margin = 640,000
    ['type' => 'main', 'description' => 'Hotel 4N', 'currency' => 'USD', 'nett_vendor' => 2000, 'divide_by' => 0, 'profit_percent' => 20, 'qty_days' => 1],
    // Flight: IDR 4,500,000 per person (divide_by 1); 10% margin = 450,000
    ['type' => 'main', 'description' => 'Flight', 'currency' => 'IDR', 'nett_vendor' => 4500000, 'divide_by' => 1, 'profit_percent' => 10, 'qty_days' => 1],
    // Guide: RMB 600 per day for the group, 5 days -> 600 x 2,200 x 5 / 10 = 660,000 per pax
    ['type' => 'variable', 'description' => 'Guide', 'currency' => 'RMB', 'nett_vendor' => 600, 'divide_by' => 0, 'profit_percent' => 0, 'qty_days' => 5],
    // Optional show: RMB 300 per person, 15% -> 660,000 + 99,000 = 759,000 (not in package price)
    ['type' => 'optional', 'description' => 'Night show', 'currency' => 'RMB', 'nett_vendor' => 300, 'divide_by' => 1, 'profit_percent' => 15, 'qty_days' => 1],
];
$s = QuotationCalculator::calculate($tour, $items)['summary'];
$expected = [
    'nett_per_pax' => 7700000.0,            // 3,200,000 + 4,500,000
    'selling_per_pax' => 8790000.0,         // 3,840,000 + 4,950,000
    'gross_margin_per_pax' => 1090000.0,    // 640,000 + 450,000
    'variable_cost_per_pax' => 660000.0,
    'foc_cost_per_pax' => 770000.0,         // 1 x 7,700,000 / 10
    'final_selling_per_pax' => 9000000.0,
    'adjustment_per_pax' => 210000.0,       // 9,000,000 - 8,790,000
    'net_margin_per_pax' => -130000.0,      // 1,090,000 - 660,000 - 770,000 + 210,000
    'group_revenue' => 90000000.0,
    'group_net_profit' => -1300000.0,
    'optional_selling_total' => 759000.0,
];
$fail = 0;
foreach ($expected as $k => $v) {
    $ok = abs($s[$k] - $v) < 0.01;
    $fail += $ok ? 0 : 1;
    printf("  %s %-24s expected %s got %s\n", $ok ? 'ok  ' : 'FAIL', $k, number_format($v), number_format($s[$k]));
}
echo $fail ? "FAILED: $fail\n" : "all calculator checks passed\n";
exit($fail ? 1 : 0);
