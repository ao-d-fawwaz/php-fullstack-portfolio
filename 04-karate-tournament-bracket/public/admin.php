<?php
// Organiser page: register participants and see which category each one falls into.
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
<title>Participants — <?= htmlspecialchars($config['tournament_name']) ?></title>
<link rel="stylesheet" href="style.css">
</head>
<body>
<header>
  <h1><?= htmlspecialchars($config['tournament_name']) ?></h1>
  <nav><a href="index.php">Brackets</a> · <a href="logout.php">Log out</a></nav>
</header>
<main class="two-col">
  <form class="card" id="form">
    <h2>Register participant</h2>
    <label>Full name</label><input name="name" required maxlength="100">
    <div class="row">
      <div><label>Sex</label><select name="sex"><option value="M">Male</option><option value="F">Female</option></select></div>
      <div><label>Event</label><select name="event"><option value="kumite">Kumite</option><option value="kata">Kata</option></select></div>
    </div>
    <div class="row">
      <div><label>Date of birth</label><input name="birth_date" type="date" required></div>
      <div><label>Weight (kg)</label><input name="weight_kg" type="number" step="0.1" required></div>
    </div>
    <div class="row">
      <div><label>Belt</label><select name="belt"><?php foreach ($config['belts'] as $b): ?><option><?= htmlspecialchars($b) ?></option><?php endforeach; ?></select></div>
      <div><label>Dojo</label><input name="dojo" required maxlength="100" list="dojos"><datalist id="dojos"></datalist></div>
    </div>
    <button type="submit">Register</button>
    <p class="hint" id="msg"></p>
    <p class="hint">Age group is calculated on <?= htmlspecialchars($config['tournament_date']) ?>. Kumite is split by weight (&lt;70 / 70–80 / &gt;80 kg); kata is not.</p>
  </form>

  <section class="card">
    <h2>Participants <span class="hint" id="count"></span></h2>
    <table>
      <thead><tr><th>Name</th><th>Dojo</th><th>Belt</th><th>Category</th><th></th></tr></thead>
      <tbody id="list"></tbody>
    </table>
  </section>
</main>
<script>
const CSRF = <?= json_encode(csrf_token()) ?>;
const $ = id => document.getElementById(id);
const headers = { 'Content-Type': 'application/json', 'X-CSRF-Token': CSRF };

async function load() {
  const res = await fetch('api/participants.php', { credentials: 'same-origin' });
  const { data } = await res.json();
  $('count').textContent = `(${data.length})`;
  $('dojos').replaceChildren(...[...new Set(data.map(p => p.dojo))].map(d => new Option(d)));
  $('list').replaceChildren(...data.map(p => {
    const tr = document.createElement('tr');
    [p.name, p.dojo, p.belt].forEach(v => { const td = document.createElement('td'); td.textContent = v; tr.append(td); });
    const cat = document.createElement('td');
    const link = Object.assign(document.createElement('a'), { href: 'index.php?category=' + encodeURIComponent(p.category), textContent: p.category_label });
    cat.append(link); tr.append(cat);
    const act = document.createElement('td');
    act.append(Object.assign(document.createElement('button'), { className: 'small danger', textContent: 'Remove', onclick: async () => {
      if (!confirm(`Remove ${p.name}?`)) return;
      const r = await fetch('api/participants.php?id=' + p.id, { method: 'DELETE', headers, credentials: 'same-origin' });
      const d = await r.json(); r.ok ? load() : alert(d.message);
    } }));
    tr.append(act);
    return tr;
  }));
}

$('form').onsubmit = async e => {
  e.preventDefault();
  const body = Object.fromEntries(new FormData(e.target));
  const res = await fetch('api/participants.php', { method: 'POST', headers, credentials: 'same-origin', body: JSON.stringify(body) });
  const data = await res.json();
  $('msg').className = res.ok ? 'hint ok' : 'hint error';
  $('msg').textContent = res.ok ? `Registered in: ${data.category}` : Object.entries(data.errors || { error: data.message }).map(([k, v]) => `${k}: ${v}`).join(' · ');
  if (res.ok) { e.target.name.value = ''; e.target.weight_kg.value = ''; load(); }
};
load();
</script>
</body>
</html>
