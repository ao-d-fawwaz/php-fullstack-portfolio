<?php
// php tests/BracketEngineTest.php
declare(strict_types=1);
require __DIR__ . '/../src/BracketEngine.php';
$fail = 0;
function check(string $name, $got, $expected): void {
    global $fail;
    $ok = $got === $expected;
    $fail += $ok ? 0 : 1;
    echo ($ok ? '  ok   ' : '  FAIL ') . $name . ($ok ? '' : ' (expected ' . json_encode($expected) . ', got ' . json_encode($got) . ')') . "\n";
}
$p = fn($name, $dojo) => ['name' => $name, 'dojo' => $dojo];

check('age group 12 -> children', BracketEngine::ageGroup('2014-06-01', '2026-12-01'), 'children');
check('age group 17 -> juniors', BracketEngine::ageGroup('2009-01-01', '2026-12-01'), 'juniors');
check('age group 18 -> adults', BracketEngine::ageGroup('2008-12-01', '2026-12-01'), 'adults');
check('70 kg is middle', BracketEngine::weightClass(70.0), 'middle');
check('kata has no weight class', BracketEngine::categoryKey(['event' => 'kata', 'sex' => 'F', 'birth_date' => '2000-01-01', 'weight_kg' => 90], '2026-12-01'), 'kata-F-adults');
check('kumite has weight class', BracketEngine::categoryKey(['event' => 'kumite', 'sex' => 'M', 'birth_date' => '2000-01-01', 'weight_kg' => 85], '2026-12-01'), 'kumite-M-adults-heavy');

// 5 players -> bracket of 8 with 3 byes, given to the top seeds
$five = [$p('A', 'X'), $p('B', 'X'), $p('C', 'Y'), $p('D', 'Z'), $p('E', 'Y')];
$r1 = BracketEngine::firstRound($five);
check('5 players -> 4 first-round matches', count($r1), 4);
check('3 byes', count(array_filter($r1, fn($m) => $m[1] === null)), 3);
check('only one real match', count(array_filter($r1, fn($m) => $m[0] && $m[1])), 1);

// 4 players, two dojos: club-mates must not meet in round 1
$four = [$p('A1', 'Alpha'), $p('A2', 'Alpha'), $p('B1', 'Beta'), $p('B2', 'Beta')];
$sameDojo = 0;
foreach (BracketEngine::firstRound($four) as [$a, $b]) { $sameDojo += ($b && $a['dojo'] === $b['dojo']) ? 1 : 0; }
check('no same-dojo first-round match (2x2)', $sameDojo, 0);

// 8 players, 4 from one dojo: they should be spread over all 4 quarters
$eight = [];
foreach (['A', 'A', 'A', 'A', 'B', 'B', 'C', 'D'] as $i => $d) { $eight[] = $p("P$i", $d); }
$quarters = array_map(fn($m) => $m[0]['dojo'] === 'A' || $m[1]['dojo'] === 'A' ? 1 : 0, BracketEngine::firstRound($eight));
check('4 club-mates in 4 different matches', array_sum($quarters), 4);

// 6 players, 3 dojos x 2: no club-mates in round 1, nobody gets an empty match
$six = [$p('a', 'A'), $p('a2', 'A'), $p('b', 'B'), $p('b2', 'B'), $p('c', 'C'), $p('c2', 'C')];
$r = BracketEngine::firstRound($six);
check('6 players -> 2 byes', count(array_filter($r, fn($m) => $m[1] === null)), 2);
check('6 players -> no empty match', count(array_filter($r, fn($m) => $m[0] === null)), 0);
$clash = 0;
foreach ($r as [$a, $b]) { $clash += ($b && $a['dojo'] === $b['dojo']) ? 1 : 0; }
check('6 players -> no same-dojo first-round match', $clash, 0);
// Larger random check: every player appears exactly once
$many = [];
for ($i = 0; $i < 13; $i++) { $many[] = $p("p$i", 'D' . ($i % 4)); }
$names = [];
foreach (BracketEngine::firstRound($many) as $m) { foreach ($m as $x) { if ($x) $names[] = $x['name']; } }
sort($names); $all = array_column($many, 'name'); sort($all);
check('13 players -> all placed once', $names, $all);

try { BracketEngine::firstRound([$p('solo', 'X')]); $threw = false; } catch (InvalidArgumentException) { $threw = true; }
check('1 participant rejected', $threw, true);
echo $fail ? "FAILED: $fail\n" : "all bracket engine checks passed\n";
exit($fail ? 1 : 0);
