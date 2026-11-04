<?php
declare(strict_types=1);

final class TournamentRepository
{
    public function __construct(private PDO $db, private array $config)
    {
    }

    // ---------- participants ----------

    /** @return array{0: array, 1: array} [clean data, errors] */
    public function validateParticipant(array $in): array
    {
        $d = [
            'name' => trim((string) ($in['name'] ?? '')),
            'sex' => (string) ($in['sex'] ?? ''),
            'birth_date' => (string) ($in['birth_date'] ?? ''),
            'weight_kg' => $in['weight_kg'] ?? null,
            'belt' => (string) ($in['belt'] ?? ''),
            'dojo' => trim((string) ($in['dojo'] ?? '')),
            'event' => (string) ($in['event'] ?? ''),
        ];
        $e = [];
        if ($d['name'] === '' || mb_strlen($d['name']) > 100) $e['name'] = 'Required (max 100 characters)';
        if (!isset(BracketEngine::SEXES[$d['sex']])) $e['sex'] = 'M or F';
        $birth = DateTimeImmutable::createFromFormat('!Y-m-d', $d['birth_date']);
        if (!$birth || $birth->format('Y-m-d') !== $d['birth_date']) {
            $e['birth_date'] = 'Use YYYY-MM-DD';
        } else {
            $age = $birth->diff(new DateTimeImmutable($this->config['tournament_date']))->y;
            if ($age < 6 || $age > 70) $e['birth_date'] = 'Age on tournament day must be 6-70';
        }
        if (!is_numeric($d['weight_kg']) || $d['weight_kg'] < 15 || $d['weight_kg'] > 200) $e['weight_kg'] = '15-200 kg';
        if (!in_array($d['belt'], $this->config['belts'], true)) $e['belt'] = 'Unknown belt';
        if ($d['dojo'] === '' || mb_strlen($d['dojo']) > 100) $e['dojo'] = 'Required';
        if (!isset(BracketEngine::EVENTS[$d['event']])) $e['event'] = 'kumite or kata';
        return [$d, $e];
    }

    public function addParticipant(array $d): int
    {
        $this->db->prepare('INSERT INTO participants (name, sex, birth_date, weight_kg, belt, dojo, event, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?)')
            ->execute([$d['name'], $d['sex'], $d['birth_date'], $d['weight_kg'], $d['belt'], $d['dojo'], $d['event'], date('Y-m-d H:i:s')]);
        return (int) $this->db->lastInsertId();
    }

    /** Participants with their category; $public hides private fields (birth date, weight). */
    public function participants(bool $public): array
    {
        $rows = $this->db->query('SELECT * FROM participants ORDER BY name')->fetchAll();
        return array_map(function (array $p) use ($public): array {
            $p['category'] = BracketEngine::categoryKey($p, $this->config['tournament_date']);
            $p['category_label'] = BracketEngine::categoryLabel($p['category']);
            return $public ? array_intersect_key($p, array_flip(['id', 'name', 'dojo', 'belt', 'category', 'category_label'])) : $p;
        }, $rows);
    }

    public function deleteParticipant(int $id): string
    {
        $stmt = $this->db->prepare('SELECT COUNT(*) FROM matches WHERE red_id = ? OR white_id = ?');
        $stmt->execute([$id, $id]);
        if ((int) $stmt->fetchColumn() > 0) {
            return 'in_bracket';
        }
        $stmt = $this->db->prepare('DELETE FROM participants WHERE id = ?');
        $stmt->execute([$id]);
        return $stmt->rowCount() ? 'deleted' : 'not_found';
    }

    // ---------- categories & brackets ----------

    public function categories(): array
    {
        $counts = [];
        foreach ($this->participants(true) as $p) {
            $counts[$p['category']] = ($counts[$p['category']] ?? 0) + 1;
        }
        $generated = $this->db->query('SELECT category_key FROM brackets')->fetchAll(PDO::FETCH_COLUMN);
        $out = [];
        foreach ($counts as $key => $n) {
            $out[] = ['key' => $key, 'label' => BracketEngine::categoryLabel($key), 'participants' => $n, 'has_bracket' => in_array($key, $generated, true)];
        }
        usort($out, fn($a, $b) => strcmp($a['key'], $b['key']));
        return $out;
    }

    /** Creates (or re-creates) the bracket of a category: all rounds, byes advanced automatically. */
    public function generate(string $categoryKey): void
    {
        $players = array_values(array_filter($this->participants(false), fn($p) => $p['category'] === $categoryKey));
        $pairs = BracketEngine::firstRound($players);

        $this->db->beginTransaction();
        try {
            $this->db->prepare('DELETE FROM brackets WHERE category_key = ?')->execute([$categoryKey]);
            $this->db->prepare('INSERT INTO brackets (category_key, created_at) VALUES (?, ?)')->execute([$categoryKey, date('Y-m-d H:i:s')]);
            $bracketId = (int) $this->db->lastInsertId();
            $insert = $this->db->prepare('INSERT INTO matches (bracket_id, round, position, red_id, white_id, winner_id) VALUES (?, ?, ?, ?, ?, ?)');
            $matchesInRound = count($pairs);
            for ($round = 1; $matchesInRound >= 1; $round++, $matchesInRound /= 2) {
                for ($pos = 0; $pos < $matchesInRound; $pos++) {
                    $insert->execute([$bracketId, $round, $pos, null, null, null]);
                }
            }
            foreach ($pairs as $pos => [$red, $white]) {
                $this->db->prepare('UPDATE matches SET red_id = ?, white_id = ? WHERE bracket_id = ? AND round = 1 AND position = ?')
                    ->execute([$red['id'], $white['id'] ?? null, $bracketId, $pos]);
                if ($white === null) {
                    $this->advance($bracketId, 1, $pos, (int) $red['id']);
                }
            }
            $this->db->commit();
        } catch (Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /** Records the winner and moves them into the next round. */
    public function setWinner(int $matchId, int $winnerId): string
    {
        $stmt = $this->db->prepare('SELECT * FROM matches WHERE id = ?');
        $stmt->execute([$matchId]);
        $m = $stmt->fetch();
        if (!$m) return 'not_found';
        if ($m['red_id'] === null || $m['white_id'] === null) return 'not_ready';
        if (!in_array($winnerId, [(int) $m['red_id'], (int) $m['white_id']], true)) return 'not_in_match';
        // Changing a result is only allowed while the next match has not been decided
        $next = $this->nextMatch((int) $m['bracket_id'], (int) $m['round'], (int) $m['position']);
        if ($next && $next['winner_id'] !== null) return 'locked';

        $this->db->beginTransaction();
        try {
            $this->advance((int) $m['bracket_id'], (int) $m['round'], (int) $m['position'], $winnerId);
            $this->db->commit();
        } catch (Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
        return 'ok';
    }

    private function nextMatch(int $bracketId, int $round, int $pos): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM matches WHERE bracket_id = ? AND round = ? AND position = ?');
        $stmt->execute([$bracketId, $round + 1, intdiv($pos, 2)]);
        return $stmt->fetch() ?: null;
    }

    private function advance(int $bracketId, int $round, int $pos, int $winnerId): void
    {
        $this->db->prepare('UPDATE matches SET winner_id = ? WHERE bracket_id = ? AND round = ? AND position = ?')
            ->execute([$winnerId, $bracketId, $round, $pos]);
        if ($this->nextMatch($bracketId, $round, $pos)) {
            $slot = $pos % 2 === 0 ? 'red_id' : 'white_id';
            $this->db->prepare("UPDATE matches SET $slot = ? WHERE bracket_id = ? AND round = ? AND position = ?")
                ->execute([$winnerId, $bracketId, $round + 1, intdiv($pos, 2)]);
        }
    }

    /** True when at least one real (non-bye) match already has a result. */
    public function hasResults(string $categoryKey): bool
    {
        $stmt = $this->db->prepare(
            'SELECT COUNT(*) FROM matches m JOIN brackets b ON b.id = m.bracket_id
             WHERE b.category_key = ? AND m.winner_id IS NOT NULL AND m.red_id IS NOT NULL AND m.white_id IS NOT NULL'
        );
        $stmt->execute([$categoryKey]);
        return (int) $stmt->fetchColumn() > 0;
    }

    public function bracket(string $categoryKey): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM brackets WHERE category_key = ?');
        $stmt->execute([$categoryKey]);
        $b = $stmt->fetch();
        if (!$b) return null;
        $stmt = $this->db->prepare(
            'SELECT m.id, m.round, m.position, m.red_id, m.white_id, m.winner_id,
                    r.name AS red_name, r.dojo AS red_dojo, w.name AS white_name, w.dojo AS white_dojo
             FROM matches m LEFT JOIN participants r ON r.id = m.red_id LEFT JOIN participants w ON w.id = m.white_id
             WHERE m.bracket_id = ? ORDER BY m.round, m.position'
        );
        $stmt->execute([$b['id']]);
        $rounds = [];
        foreach ($stmt->fetchAll() as $m) {
            $rounds[(int) $m['round'] - 1][] = $m;
        }
        $final = end($rounds)[0];
        return [
            'category' => $categoryKey,
            'label' => BracketEngine::categoryLabel($categoryKey),
            'rounds' => $rounds,
            'champion' => $final['winner_id'] ? ($final['winner_id'] == $final['red_id'] ? $final['red_name'] : $final['white_name']) : null,
        ];
    }
}
