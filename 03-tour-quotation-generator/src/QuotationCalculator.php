<?php
declare(strict_types=1);

/**
 * Pricing engine for a group tour quotation. All amounts are converted to IDR.
 *
 * Main & optional items  nett_price = nett_vendor × rate ÷ divide_by
 *                        margin     = nett_price × profit%
 *                        selling    = nett_price + margin            (per paying traveller)
 * Variable costs         total      = nett × rate × qty_days ÷ divide_by   (per paying traveller)
 *   divide_by = the group size the vendor price covers (defaults to the tour's minimum paying pax;
 *               use 1 for prices that are already per person).
 *
 * Package (per paying traveller)
 *   selling            = Σ main selling
 *   gross margin       = Σ main margin
 *   FOC cost           = FOC travellers × Σ main nett_price ÷ paying pax   (free places, e.g. tour leader)
 *   net margin         = gross margin − Σ variable costs − FOC cost
 *   final selling      = rounded price chosen by the user (defaults to selling)
 *   adjustment         = final selling − selling, added to net margin
 * Optional items are add-ons and are reported separately, not added to the package price.
 */
final class QuotationCalculator
{
    public const CURRENCIES = ['IDR', 'USD', 'RMB'];

    public static function rate(string $currency, array $tour): float
    {
        return match ($currency) {
            'USD' => (float) $tour['rate_usd'],
            'RMB' => (float) $tour['rate_rmb'],
            default => 1.0,
        };
    }

    public static function calculate(array $tour, array $items): array
    {
        $minPax = max(1, (int) $tour['min_pax']);
        $foc = max(0, (int) $tour['foc']);
        $out = [];
        $sum = ['main_nett' => 0.0, 'main_margin' => 0.0, 'main_selling' => 0.0, 'variable' => 0.0, 'optional_selling' => 0.0];

        foreach ($items as $item) {
            $rate = self::rate($item['currency'], $tour);
            $divide = max(1, (int) ($item['divide_by'] ?: $minPax));
            $row = $item + ['rate' => $rate, 'divide_by' => $divide];
            if ($item['type'] === 'variable') {
                $qty = max(0, (float) $item['qty_days']);
                $row['total'] = round((float) $item['nett_vendor'] * $rate * $qty / $divide, 2);
                $row['nett_price'] = $row['margin'] = $row['selling'] = null;
                $sum['variable'] += $row['total'];
            } else {
                $nett = (float) $item['nett_vendor'] * $rate / $divide;
                $margin = $nett * (float) $item['profit_percent'] / 100;
                $row['nett_price'] = round($nett, 2);
                $row['margin'] = round($margin, 2);
                $row['selling'] = round($nett + $margin, 2);
                $row['total'] = null;
                if ($item['type'] === 'main') {
                    $sum['main_nett'] += $nett;
                    $sum['main_margin'] += $margin;
                    $sum['main_selling'] += $nett + $margin;
                } else {
                    $sum['optional_selling'] += $nett + $margin;
                }
            }
            $out[] = $row;
        }

        $focCost = $foc * $sum['main_nett'] / $minPax;
        $selling = round($sum['main_selling'], 2);
        $final = isset($tour['final_selling']) && $tour['final_selling'] !== null && $tour['final_selling'] !== ''
            ? (float) $tour['final_selling'] : $selling;
        $adjustment = $final - $selling;
        $netMargin = $sum['main_margin'] - $sum['variable'] - $focCost + $adjustment;

        return [
            'items' => $out,
            'summary' => [
                'nett_per_pax' => round($sum['main_nett'], 2),
                'selling_per_pax' => $selling,
                'gross_margin_per_pax' => round($sum['main_margin'], 2),
                'variable_cost_per_pax' => round($sum['variable'], 2),
                'foc_cost_per_pax' => round($focCost, 2),
                'final_selling_per_pax' => round($final, 2),
                'adjustment_per_pax' => round($adjustment, 2),
                'net_margin_per_pax' => round($netMargin, 2),
                'net_margin_percent' => $final > 0 ? round($netMargin / $final * 100, 2) : 0.0,
                'group_revenue' => round($final * $minPax, 2),
                'group_net_profit' => round($netMargin * $minPax, 2),
                'optional_selling_total' => round($sum['optional_selling'], 2),
            ],
        ];
    }
}
