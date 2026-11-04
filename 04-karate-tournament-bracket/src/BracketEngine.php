<?php
declare(strict_types=1);

/**
 * Builds single-elimination brackets and assigns participants to categories.
 */
final class BracketEngine
{
    public const EVENTS = ['kumite' => 'Kumite', 'kata' => 'Kata'];
    public const SEXES = ['M' => 'Male', 'F' => 'Female'];

    /** Age group on the tournament date. */
    public static function ageGroup(string $birthDate, string $onDate): string
    {
        $age = (new DateTimeImmutable($birthDate))->diff(new DateTimeImmutable($onDate))->y;
        return $age < 13 ? 'children' : ($age < 18 ? 'juniors' : 'adults');
    }

    /** Weight classes only apply to kumite (fighting); kata is judged on form. */
    public static function weightClass(float $kg): string
    {
        return $kg < 70 ? 'light' : ($kg <= 80 ? 'middle' : 'heavy');
    }

    public static function categoryKey(array $p, string $onDate): string
    {
        $key = $p['event'] . '-' . $p['sex'] . '-' . self::ageGroup($p['birth_date'], $onDate);
        return $p['event'] === 'kumite' ? $key . '-' . self::weightClass((float) $p['weight_kg']) : $key;
    }

    public static function categoryLabel(string $key): string
    {
        $parts = explode('-', $key);
        $label = self::EVENTS[$parts[0]] . ' · ' . self::SEXES[$parts[1]] . ' · ' . ucfirst($parts[2]);
        $weights = ['light' => 'Light (<70 kg)', 'middle' => 'Middle (70–80 kg)', 'heavy' => 'Heavy (>80 kg)'];
        return isset($parts[3]) ? $label . ' · ' . $weights[$parts[3]] : $label;
    }

    /**
     * Returns first-round pairs [[red, white|null], ...] for a single-elimination bracket.
     * null means a bye (the other fighter advances automatically).
     *
     * The bracket is split in halves recursively. At every split each dojo's fighters are
     * divided as evenly as possible between the two halves, so club-mates meet as late as possible.
     * Each half always gets at least half as many fighters as its slots, so no match is empty.
     */
    public static function firstRound(array $participants): array
    {
        $n = count($participants);
        if ($n < 2) {
            throw new InvalidArgumentException('A bracket needs at least 2 participants');
        }
        $size = 1;
        while ($size < $n) {
            $size *= 2;
        }
        return array_chunk(self::place(array_values($participants), $size), 2);
    }

    private static function place(array $players, int $size): array
    {
        if ($size === 2) {
            return [$players[0], $players[1] ?? null];
        }
        // Largest dojo first, so its members are spread before smaller clubs fill the gaps
        $byDojo = [];
        foreach ($players as $p) {
            $byDojo[$p['dojo']][] = $p;
        }
        uasort($byDojo, fn($a, $b) => count($b) <=> count($a));

        $capacity = [intdiv(count($players) + 1, 2), intdiv(count($players), 2)];
        $halves = [[], []];
        foreach ($byDojo as $dojo => $members) {
            $sameDojo = [0, 0];
            foreach ($members as $p) {
                $free = array_filter([0, 1], fn($h) => count($halves[$h]) < $capacity[$h]);
                usort($free, fn($x, $y) => [$sameDojo[$x], count($halves[$x])] <=> [$sameDojo[$y], count($halves[$y])]);
                $side = $free[0];
                $halves[$side][] = $p;
                $sameDojo[$side]++;
            }
        }
        return array_merge(self::place($halves[0], $size / 2), self::place($halves[1], $size / 2));
    }
}
