<?php
declare(strict_types=1);
require __DIR__ . '/../src/bootstrap.php';
if (current_user_id() === null) {
    header('Location: login.php');
    exit;
}
global $config;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Tour Quotation Generator</title>
<style>
  :root { --navy:#12355b; --blue:#2563eb; --line:#dfe5ee; --bg:#f5f7fb; --muted:#64748b; }
  * { box-sizing:border-box; }
  body { margin:0; font-family:system-ui,-apple-system,"Segoe UI",sans-serif; background:var(--bg); color:#1e293b; font-size:14px; }
  header { background:var(--navy); color:#fff; padding:12px 24px; display:flex; justify-content:space-between; align-items:center; }
  header h1 { margin:0; font-size:1.1rem; } header a { color:#cbd5e1; }
  main { max-width:1280px; margin:20px auto; padding:0 16px; }
  .card { background:#fff; border:1px solid var(--line); border-radius:12px; padding:16px; margin-bottom:16px; }
  h2 { font-size:1rem; margin:0 0 12px; color:var(--navy); }
  table { width:100%; border-collapse:collapse; }
  th, td { padding:6px 8px; border-bottom:1px solid var(--line); text-align:left; vertical-align:middle; }
  th { font-size:.78rem; text-transform:uppercase; color:var(--muted); font-weight:600; }
  td.num, th.num { text-align:right; white-space:nowrap; }
  input, select { width:100%; padding:6px 8px; border:1px solid var(--line); border-radius:6px; font:inherit; }
  input.num { text-align:right; }
  .grid { display:grid; grid-template-columns:repeat(4, 1fr); gap:10px; }
  label { font-size:.78rem; color:var(--muted); display:block; margin-bottom:3px; }
  button { padding:7px 14px; border:0; border-radius:8px; background:var(--blue); color:#fff; cursor:pointer; font:inherit; }
  button.ghost { background:#e2e8f0; color:#1e293b; } button.danger { background:#dc2626; } button.small { padding:4px 9px; font-size:.8rem; }
  .toolbar { display:flex; gap:8px; align-items:center; margin-bottom:12px; } .toolbar input { max-width:260px; }
  .summary { display:grid; grid-template-columns:repeat(4, 1fr); gap:10px; }
  .summary div { background:var(--bg); border-radius:8px; padding:10px; } .summary span { display:block; color:var(--muted); font-size:.78rem; }
  .summary strong { font-size:1.05rem; } .neg { color:#dc2626; } .pos { color:#047857; }
  .errors { color:#b91c1c; font-size:.85rem; margin-top:8px; white-space:pre-line; }
  .hidden { display:none; }
  .section-head { display:flex; justify-content:space-between; align-items:center; margin:16px 0 6px; }
  .section-head h3 { margin:0; font-size:.9rem; color:var(--navy); }
  .hint { color:var(--muted); font-size:.8rem; margin:0 0 6px; }
  @media (max-width:900px) { .grid, .summary { grid-template-columns:1fr 1fr; } }
</style>
</head>
<body>
<header>
  <h1><?= htmlspecialchars($config['company_name']) ?> · Tour Quotation</h1>
  <span><?= htmlspecialchars($_SESSION['username'] ?? '') ?> · <a href="logout.php">Log out</a></span>
</header>
<main>
  <section class="card" id="listView">
    <div class="toolbar">
      <input id="search" placeholder="Search code or tour name...">
      <button id="newBtn">+ New quotation</button>
    </div>
    <table>
      <thead><tr><th>Code</th><th>Tour</th><th>Period</th><th class="num">Min pax</th><th>PIC</th><th class="num">Price / pax</th><th class="num">Net margin / pax</th><th></th></tr></thead>
      <tbody id="list"></tbody>
    </table>
  </section>

  <section id="editView" class="hidden">
    <div class="card">
      <h2 id="editTitle">New quotation</h2>
      <div class="grid">
        <div><label>Tour code</label><input id="tour_code" placeholder="JPN-07"></div>
        <div style="grid-column: span 3"><label>Tour name</label><input id="tour_name" placeholder="Tokyo & Kyoto Highlights"></div>
        <div><label>Period start</label><input id="period_start" type="date"></div>
        <div><label>Period end</label><input id="period_end" type="date"></div>
        <div><label>Min paying pax</label><input id="min_pax" type="number" min="1" value="10"></div>
        <div><label>FOC (free places)</label><input id="foc" type="number" min="0" value="0"></div>
        <div><label>Rate USD → IDR</label><input id="rate_usd" class="num" type="number" step="0.01" value="16000"></div>
        <div><label>Rate RMB → IDR</label><input id="rate_rmb" class="num" type="number" step="0.01" value="2200"></div>
        <div><label>PIC</label><input id="pic"></div>
        <div><label>Final selling / pax (optional)</label><input id="final_selling" class="num" type="number" placeholder="rounded price"></div>
      </div>

      <div class="section-head"><h3>Main items</h3><button class="small ghost" data-add="main">+ Row</button></div>
      <p class="hint">"Divide by" = how many people the vendor price covers. Leave empty to use min pax; enter 1 for per-person prices.</p>
      <table><thead><tr><th style="width:24%">Description</th><th>Cur.</th><th class="num">Nett vendor</th><th class="num">Divide by</th><th class="num">Profit %</th><th class="num">Nett / pax</th><th class="num">Margin</th><th class="num">Selling</th><th>Remark</th><th></th></tr></thead><tbody id="rows-main"></tbody></table>

      <div class="section-head"><h3>Variable costs</h3><button class="small ghost" data-add="variable">+ Row</button></div>
      <table><thead><tr><th style="width:24%">Description</th><th>Cur.</th><th class="num">Nett</th><th class="num">Divide by</th><th class="num">Qty / days</th><th class="num" colspan="3">Total / pax</th><th>Remark</th><th></th></tr></thead><tbody id="rows-variable"></tbody></table>

      <div class="section-head"><h3>Optional add-ons</h3><button class="small ghost" data-add="optional">+ Row</button></div>
      <table><thead><tr><th style="width:24%">Description</th><th>Cur.</th><th class="num">Nett vendor</th><th class="num">Divide by</th><th class="num">Profit %</th><th class="num">Nett / pax</th><th class="num">Margin</th><th class="num">Selling</th><th>Remark</th><th></th></tr></thead><tbody id="rows-optional"></tbody></table>
    </div>

    <div class="card">
      <h2>Summary per paying traveller</h2>
      <div class="summary" id="summary"></div>
      <div class="errors" id="errors"></div>
      <div class="toolbar" style="margin:14px 0 0">
        <button id="saveBtn">Save</button>
        <button class="ghost" id="printBtn" disabled>Print / PDF</button>
        <button class="ghost" id="backBtn">Back to list</button>
      </div>
    </div>
  </section>
</main>

<script>
const CSRF = <?= json_encode(csrf_token()) ?>;
const $ = id => document.getElementById(id);
const idr = n => (n === null || n === undefined) ? '' : 'Rp ' + Math.round(n).toLocaleString('id-ID');
const TOUR_FIELDS = ['tour_code', 'tour_name', 'period_start', 'period_end', 'min_pax', 'foc', 'rate_usd', 'rate_rmb', 'pic', 'final_selling'];
let currentId = null, previewTimer = null;

async function api(url, options = {}) {
  const res = await fetch(url, { ...options, credentials: 'same-origin',
    headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CSRF } });
  if (res.status === 401) { location.href = 'login.php'; }
  const data = await res.json();
  return { ok: res.ok, data };
}

function cell(text, cls) { const td = document.createElement('td'); td.textContent = text; if (cls) td.className = cls; return td; }

// ---------- list ----------
async function loadList() {
  const { data } = await api('api/quotations.php?q=' + encodeURIComponent($('search').value));
  $('list').replaceChildren(...data.data.map(q => {
    const tr = document.createElement('tr');
    tr.append(cell(q.tour_code), cell(q.tour_name), cell(`${q.period_start} → ${q.period_end}`), cell(q.min_pax, 'num'), cell(q.pic),
      cell(idr(q.final_selling ?? q.selling_per_pax), 'num'), cell(idr(q.net_margin_per_pax), 'num ' + (q.net_margin_per_pax < 0 ? 'neg' : 'pos')));
    const actions = document.createElement('td'); actions.className = 'num';
    const edit = Object.assign(document.createElement('button'), { textContent: 'Open', className: 'small ghost', onclick: () => openQuotation(q.id) });
    const print = Object.assign(document.createElement('button'), { textContent: 'Print', className: 'small ghost', onclick: () => window.open('print.php?id=' + q.id) });
    const del = Object.assign(document.createElement('button'), { textContent: 'Delete', className: 'small danger', onclick: async () => {
      if (confirm(`Delete quotation ${q.tour_code}?`)) { await api('api/quotations.php?id=' + q.id, { method: 'DELETE' }); loadList(); } } });
    actions.append(edit, ' ', print, ' ', del);
    tr.append(actions);
    return tr;
  }));
  if (!data.data.length) $('list').innerHTML = '<tr><td colspan="8" style="color:#64748b">No quotations yet.</td></tr>';
}

// ---------- editor ----------
function addRow(type, item = {}) {
  const tr = document.createElement('tr');
  tr.dataset.type = type;
  const input = (name, attrs = {}) => { const td = document.createElement('td'); const el = Object.assign(document.createElement('input'), attrs); el.dataset.f = name; el.value = 'value' in attrs ? attrs.value : (item[name] ?? ''); td.append(el); return td; };
  const cur = document.createElement('td'); cur.style.minWidth = '78px'; const sel = document.createElement('select'); sel.dataset.f = 'currency';
  ['IDR', 'USD', 'RMB'].forEach(c => sel.add(new Option(c, c, false, (item.currency || 'IDR') === c))); cur.append(sel);
  tr.append(input('description'), cur, input('nett_vendor', { type: 'number', className: 'num', step: '0.01' }),
    input('divide_by', { type: 'number', className: 'num', min: 0, value: item.divide_by || '' }));
  if (type === 'variable') {
    tr.append(input('qty_days', { type: 'number', className: 'num', step: '0.5', value: item.qty_days ?? 1 }));
    const total = cell('', 'num'); total.colSpan = 3; total.dataset.out = 'total'; tr.append(total);
  } else {
    tr.append(input('profit_percent', { type: 'number', className: 'num', step: '0.5', value: item.profit_percent ?? 0 }));
    ['nett_price', 'margin', 'selling'].forEach(o => { const c = cell('', 'num'); c.dataset.out = o; tr.append(c); });
  }
  tr.append(input('remark'));
  const rm = document.createElement('td'); rm.append(Object.assign(document.createElement('button'), { textContent: '✕', className: 'small ghost', onclick: () => { tr.remove(); schedulePreview(); } }));
  tr.append(rm);
  $('rows-' + type).append(tr);
}

function collect() {
  const body = Object.fromEntries(TOUR_FIELDS.map(f => [f, $(f).value]));
  body.items = [...document.querySelectorAll('#editView tr[data-type]')].map(tr => {
    const it = { type: tr.dataset.type };
    tr.querySelectorAll('[data-f]').forEach(el => { it[el.dataset.f] = el.value; });
    return it;
  });
  return body;
}

function render(result) {
  const rows = [...document.querySelectorAll('#editView tr[data-type]')];
  result.items.forEach((it, i) => rows[i] && rows[i].querySelectorAll('[data-out]').forEach(c => { c.textContent = idr(it[c.dataset.out]); }));
  const s = result.summary;
  const box = (label, value, cls = '') => `<div><span>${label}</span><strong class="${cls}">${value}</strong></div>`;
  $('summary').innerHTML = [
    box('Nett cost', idr(s.nett_per_pax)), box('Selling (calculated)', idr(s.selling_per_pax)),
    box('Final selling', idr(s.final_selling_per_pax)), box('Adjustment', idr(s.adjustment_per_pax)),
    box('Gross margin', idr(s.gross_margin_per_pax)), box('Variable costs', idr(s.variable_cost_per_pax)),
    box('FOC cost', idr(s.foc_cost_per_pax)), box('Net margin', `${idr(s.net_margin_per_pax)} (${s.net_margin_percent}%)`, s.net_margin_per_pax < 0 ? 'neg' : 'pos'),
    box('Group revenue', idr(s.group_revenue)), box('Group net profit', idr(s.group_net_profit), s.group_net_profit < 0 ? 'neg' : 'pos'),
    box('Optional add-ons (per pax)', idr(s.optional_selling_total)),
  ].join('');
}

function showErrors(errors) {
  $('errors').textContent = errors ? Object.entries(errors).map(([k, v]) => `${k}: ${v}`).join('\n') : '';
}

// Live preview uses the same server-side calculator as saving
function schedulePreview() {
  clearTimeout(previewTimer);
  previewTimer = setTimeout(async () => {
    const { ok, data } = await api('api/calculate.php', { method: 'POST', body: JSON.stringify(collect()) });
    if (ok) { render(data.data); showErrors(null); } else { showErrors(data.errors); }
  }, 350);
}

function openEditor(q = null) {
  currentId = q ? q.quotation.id : null;
  $('editTitle').textContent = q ? `Edit ${q.quotation.tour_code}` : 'New quotation';
  TOUR_FIELDS.forEach(f => { $(f).value = q ? (q.quotation[f] ?? '') : ({ min_pax: 10, foc: 0, rate_usd: 16000, rate_rmb: 2200 }[f] ?? ''); });
  ['main', 'variable', 'optional'].forEach(t => $('rows-' + t).replaceChildren());
  if (q) { q.items.forEach(it => addRow(it.type, it)); } else { addRow('main'); }
  $('printBtn').disabled = !q;
  $('summary').innerHTML = ''; showErrors(null);
  $('listView').classList.add('hidden'); $('editView').classList.remove('hidden');
  if (q) render(q);
}

async function openQuotation(id) {
  const { ok, data } = await api('api/quotations.php?id=' + id);
  if (ok) openEditor(data.data);
}

$('editView').addEventListener('input', schedulePreview);
document.querySelectorAll('[data-add]').forEach(b => b.onclick = () => { addRow(b.dataset.add); });
$('newBtn').onclick = () => openEditor();
$('backBtn').onclick = () => { $('editView').classList.add('hidden'); $('listView').classList.remove('hidden'); loadList(); };
$('printBtn').onclick = () => currentId && window.open('print.php?id=' + currentId);
$('search').oninput = () => loadList();
$('saveBtn').onclick = async () => {
  const url = 'api/quotations.php' + (currentId ? '?id=' + currentId : '');
  const { ok, data } = await api(url, { method: currentId ? 'PUT' : 'POST', body: JSON.stringify(collect()) });
  if (!ok) { showErrors(data.errors || { error: data.message }); return; }
  openEditor(data.data);
  $('errors').innerHTML = '<span style="color:#047857">Saved.</span>';
};
loadList();
</script>
</body>
</html>
