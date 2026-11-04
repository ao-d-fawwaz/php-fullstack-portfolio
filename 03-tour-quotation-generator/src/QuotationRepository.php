<?php
declare(strict_types=1);

/** Validation + persistence for quotations. Calculated values are always recomputed on the server. */
final class QuotationRepository
{
    private const TOUR_FIELDS = ['tour_code', 'tour_name', 'period_start', 'period_end', 'min_pax', 'foc', 'rate_usd', 'rate_rmb', 'pic', 'final_selling'];
    private const ITEM_TYPES = ['main', 'variable', 'optional'];

    public function __construct(private PDO $db)
    {
    }

    /** @return array{0: array, 1: array, 2: array} [tour, items, errors] */
    public function validate(array $in): array
    {
        $e = [];
        $t = [];
        foreach (self::TOUR_FIELDS as $f) {
            $t[$f] = is_string($in[$f] ?? null) ? trim($in[$f]) : ($in[$f] ?? null);
        }
        if (!preg_match('/^[A-Z0-9-]{2,20}$/i', (string) $t['tour_code'])) $e['tour_code'] = '2-20 letters, digits or dashes';
        if ($t['tour_name'] === null || $t['tour_name'] === '' || mb_strlen((string) $t['tour_name']) > 150) $e['tour_name'] = 'Required (max 150 characters)';
        foreach (['period_start', 'period_end'] as $f) {
            $d = DateTimeImmutable::createFromFormat('!Y-m-d', (string) $t[$f]);
            if (!$d || $d->format('Y-m-d') !== $t[$f]) $e[$f] = 'Use YYYY-MM-DD';
        }
        if (!isset($e['period_start']) && !isset($e['period_end']) && $t['period_end'] < $t['period_start']) $e['period_end'] = 'Must be on or after the start date';
        if (filter_var($t['min_pax'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 500]]) === false) $e['min_pax'] = 'Whole number 1-500';
        if (filter_var($t['foc'] ?? 0, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => 50]]) === false) $e['foc'] = 'Whole number 0-50';
        foreach (['rate_usd', 'rate_rmb'] as $f) {
            if (!is_numeric($t[$f]) || $t[$f] <= 0) $e[$f] = 'Positive number required';
        }
        if ($t['final_selling'] !== null && $t['final_selling'] !== '' && (!is_numeric($t['final_selling']) || $t['final_selling'] < 0)) $e['final_selling'] = 'Must be a positive number';
        $t['pic'] = mb_substr((string) ($t['pic'] ?? ''), 0, 100);
        $t['foc'] = (int) ($t['foc'] ?? 0);
        $t['final_selling'] = ($t['final_selling'] === '' ? null : $t['final_selling']);

        $items = [];
        foreach (array_values((array) ($in['items'] ?? [])) as $i => $it) {
            $row = [
                'type' => (string) ($it['type'] ?? ''),
                'description' => trim((string) ($it['description'] ?? '')),
                'currency' => strtoupper((string) ($it['currency'] ?? 'IDR')),
                'nett_vendor' => $it['nett_vendor'] ?? null,
                'divide_by' => (int) ($it['divide_by'] ?? 0),
                'profit_percent' => $it['profit_percent'] ?? 0,
                'qty_days' => $it['qty_days'] ?? 1,
                'remark' => mb_substr(trim((string) ($it['remark'] ?? '')), 0, 255),
            ];
            if (!in_array($row['type'], self::ITEM_TYPES, true)) $e["items.$i.type"] = 'main, variable or optional';
            if ($row['description'] === '') $e["items.$i.description"] = 'Required';
            if (!in_array($row['currency'], QuotationCalculator::CURRENCIES, true)) $e["items.$i.currency"] = 'IDR, USD or RMB';
            if (!is_numeric($row['nett_vendor']) || $row['nett_vendor'] < 0) $e["items.$i.nett_vendor"] = 'Positive number required';
            if ($row['divide_by'] < 0) $e["items.$i.divide_by"] = 'Cannot be negative';
            if (!is_numeric($row['profit_percent']) || $row['profit_percent'] < 0 || $row['profit_percent'] > 500) $e["items.$i.profit_percent"] = '0-500';
            if (!is_numeric($row['qty_days']) || $row['qty_days'] < 0) $e["items.$i.qty_days"] = 'Positive number required';
            $items[] = $row;
        }
        if (!array_filter($items, fn($r) => $r['type'] === 'main')) $e['items'] = 'Add at least one main item';
        return [$t, $items, $e];
    }

    public function save(?int $id, array $tour, array $items): int
    {
        $calc = QuotationCalculator::calculate($tour, $items);
        $this->db->beginTransaction();
        try {
            $values = [$tour['tour_code'], $tour['tour_name'], $tour['period_start'], $tour['period_end'], (int) $tour['min_pax'],
                $tour['foc'], $tour['rate_usd'], $tour['rate_rmb'], $tour['pic'], $tour['final_selling'],
                $calc['summary']['selling_per_pax'], $calc['summary']['net_margin_per_pax'], date('Y-m-d H:i:s')];
            if ($id === null) {
                $this->db->prepare(
                    'INSERT INTO quotations (tour_code, tour_name, period_start, period_end, min_pax, foc, rate_usd, rate_rmb, pic,
                                             final_selling, selling_per_pax, net_margin_per_pax, updated_at)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
                )->execute($values);
                $id = (int) $this->db->lastInsertId();
            } else {
                $values[] = $id;
                $this->db->prepare(
                    'UPDATE quotations SET tour_code = ?, tour_name = ?, period_start = ?, period_end = ?, min_pax = ?, foc = ?,
                     rate_usd = ?, rate_rmb = ?, pic = ?, final_selling = ?, selling_per_pax = ?, net_margin_per_pax = ?, updated_at = ?
                     WHERE id = ?'
                )->execute($values);
                $this->db->prepare('DELETE FROM quotation_items WHERE quotation_id = ?')->execute([$id]);
            }
            $stmt = $this->db->prepare(
                'INSERT INTO quotation_items (quotation_id, position, type, description, currency, nett_vendor, divide_by,
                                              profit_percent, qty_days, remark)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );
            foreach ($items as $pos => $it) {
                $stmt->execute([$id, $pos, $it['type'], $it['description'], $it['currency'], $it['nett_vendor'],
                    $it['divide_by'] ?: null, $it['profit_percent'], $it['qty_days'], $it['remark']]);
            }
            $this->db->commit();
            return $id;
        } catch (Throwable $ex) {
            $this->db->rollBack();
            throw $ex;
        }
    }

    public function list(string $search = ''): array
    {
        $sql = 'SELECT id, tour_code, tour_name, period_start, period_end, min_pax, pic, final_selling, selling_per_pax,
                       net_margin_per_pax, updated_at FROM quotations';
        $params = [];
        if ($search !== '') {
            $sql .= ' WHERE tour_code LIKE ? OR tour_name LIKE ?';
            $params = ["%$search%", "%$search%"];
        }
        $stmt = $this->db->prepare($sql . ' ORDER BY updated_at DESC, id DESC');
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /** Quotation with recalculated items and summary, or null. */
    public function find(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM quotations WHERE id = ?');
        $stmt->execute([$id]);
        $tour = $stmt->fetch();
        if (!$tour) {
            return null;
        }
        $stmt = $this->db->prepare('SELECT type, description, currency, nett_vendor, divide_by, profit_percent, qty_days, remark
                                    FROM quotation_items WHERE quotation_id = ? ORDER BY position');
        $stmt->execute([$id]);
        $items = array_map(fn($r) => ['divide_by' => (int) $r['divide_by']] + $r, $stmt->fetchAll());
        return ['quotation' => $tour] + QuotationCalculator::calculate($tour, $items);
    }

    public function delete(int $id): bool
    {
        $stmt = $this->db->prepare('DELETE FROM quotations WHERE id = ?');
        $stmt->execute([$id]);
        return $stmt->rowCount() > 0;
    }
}
