<?php

declare(strict_types=1);

namespace Pushery\WireKit\Charts;

/**
 * A chart's data as a table: one row per label, one column per dataset.
 *
 * A chart draws its numbers on a canvas or inside an image, and its accessible name is a short
 * summary, so a reader who cannot see the drawing has the numbers nowhere unless the page writes
 * them out as text (WCAG 1.1.1). This is that text in the shape of the chart's own data. Each row is
 * named by its label, written as the tooltip writes it, a date on a date axis included, else by the
 * `x` of its first point, else by its position. Each column is named by its dataset, and each cell
 * holds the value as the tooltip writes it, with the chart's decimals and affixes, or a dataset's
 * own decimals, and names a missing value rather than leaving the cell empty.
 *
 * Labels stand as rows whatever the chart's orientation, so a horizontal bar chart reads as a
 * vertical one does.
 */
final class ChartDataTable
{
    /**
     * @param  list<mixed>  $labels  The chart's labels, as its axis reads them (`ChartDates::onAxis()`).
     * @param  list<mixed>  $datasets  The datasets without their addresses (`ChartPointLinks::extract()`).
     * @param  list<bool>  $rangePairs  Per dataset, whether a point written as a pair is a range (`ChartPointLinks::rangePairs()`).
     * @param  string|null  $dateZone  The zone the x axis writes its dates in, null where it holds none (`ChartPointLinks::dateZone()`).
     * @return array{columns: list<string>, rows: list<array{label: string, cells: list<string>}>}
     */
    public static function from(array $labels, array $datasets, ?int $decimals = null, ?string $prefix = null, ?string $suffix = null, array $rangePairs = [], ?string $dateZone = null): array
    {
        $columns = [];
        $points = [];
        $places = [];

        foreach (array_values($datasets) as $d => $dataset) {
            $dataset = is_array($dataset) ? $dataset : [];
            $fallback = __('wirekit::Series :number', ['number' => $d + 1]);
            $columns[] = ChartPointLinks::text($dataset['label'] ?? null) ?? (is_string($fallback) ? $fallback : 'Series '.($d + 1));
            $points[] = is_array($dataset['data'] ?? null) ? array_values($dataset['data']) : [];
            // A dataset that names its own decimals is written with them, as the tooltip writes it.
            $places[] = is_numeric($dataset['decimals'] ?? null) ? min(100, max(0, (int) $dataset['decimals'])) : $decimals;
        }

        $none = __('wirekit::No value');
        $none = is_string($none) ? $none : 'No value';
        $count = max([count($labels), ...array_map(static fn (array $list): int => count($list), $points)]);
        $rows = [];

        for ($p = 0; $p < $count; $p++) {
            $x = $labels[$p] ?? null;
            $cells = [];

            foreach ($points as $d => $list) {
                $point = $list[$p] ?? null;

                // A pair, the form both libraries take besides an object: `x` and `y`, or the two
                // ends of a range where the library draws the pair as one.
                if (is_array($point) && array_is_list($point) && count($point) === 2) {
                    $point = ($rangePairs[$d] ?? false) ? ['y' => $point] : ['x' => $point[0], 'y' => $point[1]];
                }

                // Without a label, the row takes the `x` of the first point that has one.
                if (ChartPointLinks::text($x) === null && is_array($point)) {
                    $x = $point['x'] ?? null;
                }

                $written = ChartPointLinks::value(is_array($point) ? ($point['y'] ?? null) : $point, $places[$d]);
                $cells[] = $written === '' ? $none : $prefix.$written.$suffix;
            }

            $label = ($dateZone !== null && (is_string($x) || is_int($x) || is_float($x)) ? ChartPointLinks::date($x, $dateZone) : null)
                ?? ChartPointLinks::text($x)
                ?? (string) ($p + 1);

            $rows[] = ['label' => $label, 'cells' => $cells];
        }

        return ['columns' => $columns, 'rows' => $rows];
    }
}
