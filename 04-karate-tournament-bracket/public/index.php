<?php
// Public bracket viewer. Logged-in organisers can click a fighter to record the winner.
declare(strict_types=1);
require __DIR__ . '/../src/bootstrap.php';
global $config;
$isOrganiser = current_user_id() !== null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Brackets — <?= htmlspecialchars($config['tournament_name']) ?></title>
<link rel="stylesheet" href="style.css">
</head>
<body>
<header>
  <h1><?= htmlspecialchars($config['tournament_name']) ?></h1>
  <nav><?= $isOrganiser ? '<a href="admin.php">Participants</a> · <a href="logout.php">Log out</a>' : '<a href="login.php">Organiser login</a>' ?></nav>
</header>
<main>
  <div class="card">
    <label for="category">Category</label>
    <select id="category"></select>
    <?php if ($isOrganiser): ?><button id="generate">Generate bracket</button><?php endif; ?>
    <p class="hint" id="info"></p>
  </div>
  <div id="champion" class="champion hidden"></div>
  <div class="bracket" id="bracket"></div>
</main>
<script>
const IS_ORGANISER = <?= json_encode($isOrganiser) ?>;
const CSRF = <?= json_encode($isOrganiser ? csrf_token() : '') ?>;
const $ = id => document.getElementById(id);
let categories = [];

async function api(url, body) {
  const res = await fetch(url, body ? { method: 'POST', credentials: 'same-origin',
    headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CSRF }, body: JSON.stringify(body) } : {});
  return { ok: res.ok, status: res.status, data: await res.json() };
}

function roundName(index, total) {
  const fromEnd = total - index;
  return fromEnd === 1 ? 'Final' : fromEnd === 2 ? 'Semi-final' : fromEnd === 3 ? 'Quarter-final' : `Round ${index + 1}`;
}

function fighter(match, side) {
  const div = document.createElement('div');
  const id = match[side + '_id'];
  div.className = 'fighter ' + side;
  if (!id) {
    // Empty slot: either a bye (round 1) or waiting for an earlier match
    div.classList.add('empty');
    div.textContent = match.round === 1 ? 'BYE' : '—';
    return div;
  }
  const name = document.createElement('strong'); name.textContent = match[side + '_name'];
  const dojo = document.createElement('small'); dojo.textContent = match[side + '_dojo'];
  div.append(name, dojo);
  if (match.winner_id) div.classList.add(match.winner_id === id ? 'winner' : 'loser');
  if (IS_ORGANISER && match.red_id && match.white_id) {
    div.classList.add('clickable');
    div.title = 'Click to record as winner';
    div.onclick = async () => {
      const { ok, data } = await api('api/bracket.php', { action: 'winner', match_id: match.id, winner_id: id });
      ok ? loadBracket() : alert(data.message);
    };
  }
  return div;
}

async function loadBracket() {
  const key = $('category').value;
  const cat = categories.find(c => c.key === key);
  $('info').textContent = cat ? `${cat.participants} participant(s)` : '';
  $('bracket').replaceChildren();
  $('champion').classList.add('hidden');
  if (!key) return;
  const { ok, data } = await api('api/bracket.php?category=' + encodeURIComponent(key));
  if (!ok) { $('info').textContent += ' · ' + data.message; return; }
  const rounds = data.data.rounds;
  rounds.forEach((matches, i) => {
    const col = document.createElement('div'); col.className = 'round';
    const h = document.createElement('h3'); h.textContent = roundName(i, rounds.length); col.append(h);
    matches.forEach(m => {
      m.id = Number(m.id); m.round = Number(m.round);
      ['red_id', 'white_id', 'winner_id'].forEach(k => { m[k] = m[k] === null ? null : Number(m[k]); });
      const box = document.createElement('div'); box.className = 'match';
      box.append(fighter(m, 'red'), fighter(m, 'white'));
      col.append(box);
    });
    $('bracket').append(col);
  });
  if (data.data.champion) {
    $('champion').textContent = '🏆 Champion: ' + data.data.champion;
    $('champion').classList.remove('hidden');
  }
}

async function loadCategories(selected) {
  const { data } = await api('api/bracket.php');
  categories = data.data;
  $('category').replaceChildren(...categories.map(c => new Option(`${c.label} (${c.participants})${c.has_bracket ? '' : ' — no bracket yet'}`, c.key)));
  if (selected) $('category').value = selected;
  if (!categories.length) $('info').textContent = 'No participants registered yet.';
  loadBracket();
}

$('category').onchange = loadBracket;
if (IS_ORGANISER) {
  $('generate').onclick = async () => {
    const key = $('category').value;
    let res = await api('api/bracket.php', { action: 'generate', category: key });
    if (res.status === 409 && confirm(res.data.message + '\n\nRebuild and lose the recorded results?')) {
      res = await api('api/bracket.php', { action: 'generate', category: key, force: true });
    }
    if (!res.ok && res.status !== 409) alert(res.data.message);
    loadCategories(key);
  };
}
loadCategories(new URLSearchParams(location.search).get('category'));
</script>
</body>
</html>
