<?php
// Printable quotation for the customer. Use the browser's "Save as PDF" to export.
// Internal figures (nett vendor prices, margins) are deliberately left out.
declare(strict_types=1);
require __DIR__ . '/../src/bootstrap.php';
if (current_user_id() === null) {
    header('Location: login.php');
    exit;
}
global $config;
$q = (new QuotationRepository(db()))->find((int) ($_GET['id'] ?? 0));
if ($q === null) {
    http_response_code(404);
    exit('Quotation not found');
}
$t = $q['quotation'];
$s = $q['summary'];
$e = fn($v) => htmlspecialchars((string) $v, ENT_QUOTES);
$idr = fn($n) => 'Rp ' . number_format((float) $n, 0, ',', '.');
$included = array_filter($q['items'], fn($i) => $i['type'] === 'main');
$optional = array_filter($q['items'], fn($i) => $i['type'] === 'optional');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Quotation <?= $e($t['tour_code']) ?></title>
<style>
  body { font-family: Georgia, "Times New Roman", serif; color:#111; max-width:800px; margin:30px auto; padding:0 20px; }
  .head { display:flex; justify-content:space-between; border-bottom:3px solid #12355b; padding-bottom:10px; }
  .head h1 { margin:0; color:#12355b; font-size:1.6rem; } .head p { margin:4px 0 0; color:#555; }
  h2 { font-size:1.1rem; margin:24px 0 8px; color:#12355b; }
  table { width:100%; border-collapse:collapse; font-family:system-ui, sans-serif; font-size:.92rem; }
  td, th { padding:7px 6px; border-bottom:1px solid #ddd; text-align:left; } td.num, th.num { text-align:right; }
  .price { margin-top:20px; padding:16px; background:#f1f5f9; border-left:4px solid #12355b; font-family:system-ui, sans-serif; }
  .price strong { font-size:1.4rem; }
  .meta td { border:0; padding:3px 6px; }
  .noprint { margin-bottom:20px; } @media print { .noprint { display:none; } body { margin:0; } }
</style>
</head>
<body>
<div class="noprint"><button onclick="print()">Print / Save as PDF</button></div>
<div class="head">
  <div><h1><?= $e($config['company_name']) ?></h1><p>Tour Quotation</p></div>
  <div style="text-align:right"><p>Code: <strong><?= $e($t['tour_code']) ?></strong></p><p>Date: <?= date('d M Y') ?></p></div>
</div>

<h2><?= $e($t['tour_name']) ?></h2>
<table class="meta">
  <tr><td>Travel period</td><td><?= $e(date('d M Y', strtotime($t['period_start']))) ?> – <?= $e(date('d M Y', strtotime($t['period_end']))) ?></td></tr>
  <tr><td>Minimum group size</td><td><?= (int) $t['min_pax'] ?> paying travellers<?= $t['foc'] ? ' + ' . (int) $t['foc'] . ' free of charge' : '' ?></td></tr>
  <?php if ($t['pic'] !== ''): ?><tr><td>Contact person</td><td><?= $e($t['pic']) ?></td></tr><?php endif; ?>
</table>

<h2>Package includes</h2>
<table>
  <?php foreach ($included as $i): ?>
  <tr><td><?= $e($i['description']) ?></td><td><?= $e($i['remark']) ?></td></tr>
  <?php endforeach; ?>
</table>

<div class="price">Price per person: <strong><?= $idr($s['final_selling_per_pax']) ?></strong><br>
  <small>Based on a minimum of <?= (int) $t['min_pax'] ?> paying travellers. Exchange rates: USD 1 = <?= $idr($t['rate_usd']) ?>, RMB 1 = <?= $idr($t['rate_rmb']) ?>.</small></div>

<?php if ($optional): ?>
<h2>Optional add-ons (per person)</h2>
<table>
  <?php foreach ($optional as $i): ?>
  <tr><td><?= $e($i['description']) ?></td><td class="num"><?= $idr($i['selling']) ?></td></tr>
  <?php endforeach; ?>
</table>
<?php endif; ?>
</body>
</html>
