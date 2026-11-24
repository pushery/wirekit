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
 *
 * The values of one point of a candlestick or a boxplot are named in the cell, with the names the
 * tooltip gives them in the application's language: four or five numbers in a row are otherwise
 * numbers without a meaning for a reader who hears them.
 *
 * A scatter and a bubble chart read by point instead: one row per point, named by its dataset, with
 * its `x`, its `y` and, for a bubble, its size in columns of their own, headed by the axis titles
 * where the chart has them. A row by position put the points of different datasets beside each
 * other there, under the `x` of the first, and a bubble's size stood nowhere.
 */
final class ChartDataTable
{
    /**
     * @param  list<mixed>  $labels  The chart's labels, as its axis reads them (`ChartDates::onAxis()`).
     * @param  list<mixed>  $datasets  The datasets without their addresses (`ChartPointLinks::extract()`).
     * @param  list<bool>  $rangePairs  Per dataset, whether a point written as a pair is a range (`ChartPointLinks::rangePairs()`).
     * @param  string|null  $dateZone  The zone the x axis writes its dates in, null where it holds none (`ChartPointLinks::dateZone()`).
     * @param  string|null  $type  The chart's type: a candlestick or a boxplot names the values of each point, a scatter and a bubble take a row per point.
     * @param  string|null  $xTitle  The title of the x axis, which heads the `x` column of a scatter or a bubble.
     * @param  string|null  $yTitle  The title of the y axis, which heads its `y` column.
     * @return array{columns: list<string>, rows: list<array{label: string, cells: list<string>}>}
     */
    public static function from(array $labels, array $datasets, ?int $decimals = null, ?string $prefix = null, ?string $suffix = null, array $rangePairs = [], ?string $dateZone = null, ?string $type = null, ?string $xTitle = null, ?string $yTitle = null): array
    {
        $parts = self::partNames($type);
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

        if ($type === 'scatter' || $type === 'bubble') {
            return self::byPoint($columns, $points, $places, $prefix, $suffix, $dateZone, $type === 'bubble', $none, $xTitle, $yTitle);
        }

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

                $y = is_array($point) ? ($point['y'] ?? null) : $point;

                // The values of one candlestick or boxplot point, each with its name and the
                // chart's affixes: "Open price: $126.40; High: $130.50; …". A part is `mixed`
                // because it is a value of the developer's chart data, whatever it holds there,
                // which `ChartPointLinks::value()` writes as text.
                if ($parts !== null && is_array($y) && array_is_list($y) && count($y) === count($parts)) {
                    $cells[] = implode('; ', array_map(
                        static fn (string $name, mixed $part): string => $name.': '.(($value = ChartPointLinks::value($part, $places[$d])) === '' ? $none : $prefix.$value.$suffix),
                        $parts,
                        $y,
                    ));

                    continue;
                }

                $written = ChartPointLinks::value($y, $places[$d]);
                $cells[] = $written === '' ? $none : $prefix.$written.$suffix;
            }

            $label = ($dateZone !== null && (is_string($x) || is_int($x) || is_float($x)) ? ChartPointLinks::date($x, $dateZone) : null)
                ?? ChartPointLinks::text($x)
                ?? (string) ($p + 1);

            $rows[] = ['label' => $label, 'cells' => $cells];
        }

        return ['columns' => $columns, 'rows' => $rows];
    }

    /**
     * A scatter or a bubble chart, one row per point: named by its dataset, and by its position
     * where the dataset holds more than one, with `x`, `y` and a bubble's size in their own columns,
     * as the tooltip writes a point. The affixes of the chart's values go with `y`, the value the
     * chart plots; an `x` on a date axis is written as its date.
     *
     * @param  list<string>  $names
     * @param  list<list<mixed>>  $points
     * @param  list<int|null>  $places
     * @return array{columns: list<string>, rows: list<array{label: string, cells: list<string>}>}
     */
    private static function byPoint(array $names, array $points, array $places, ?string $prefix, ?string $suffix, ?string $dateZone, bool $bubble, string $none, ?string $xTitle, ?string $yTitle): array
    {
        $columns = [
            ChartPointLinks::text($xTitle) ?? 'X',
            ChartPointLinks::text($yTitle) ?? 'Y',
            ...($bubble ? [self::text(__('wirekit::Size'), 'Size')] : []),
        ];
        $rows = [];

        foreach ($points as $d => $list) {
            foreach ($list as $n => $point) {
                [$x, $y, $z] = self::coordinates($point);

                $date = $dateZone !== null && (is_string($x) || is_int($x) || is_float($x)) ? ChartPointLinks::date($x, $dateZone) : null;
                $writtenX = $date ?? ChartPointLinks::value($x, $places[$d]);
                $writtenY = ChartPointLinks::value($y, $places[$d]);
                $cells = [
                    $writtenX === '' ? $none : $writtenX,
                    $writtenY === '' ? $none : $prefix.$writtenY.$suffix,
                ];

                if ($bubble) {
                    $writtenZ = ChartPointLinks::value($z, $places[$d]);
                    $cells[] = $writtenZ === '' ? $none : $writtenZ;
                }

                $rows[] = ['label' => count($list) > 1 ? $names[$d].' '.($n + 1) : $names[$d], 'cells' => $cells];
            }
        }

        return ['columns' => $columns, 'rows' => $rows];
    }

    /**
     * The `x`, `y` and `z` of a point in any of the forms the libraries take: an object, a list of
     * two or three, or a bare number, which is a `y` with no `x`. The point is `mixed` because it is
     * a point of the developer's chart data, in whatever form it stands there.
     *
     * @return array{0: mixed, 1: mixed, 2: mixed}
     */
    private static function coordinates(mixed $point): array
    {
        if (is_array($point) && array_is_list($point)) {
            return [$point[0] ?? null, $point[1] ?? null, $point[2] ?? null];
        }

        if (is_array($point)) {
            return [$point['x'] ?? null, $point['y'] ?? null, $point['z'] ?? ($point['r'] ?? null)];
        }

        return [null, $point, null];
    }

    /**
     * The names of the values of one point, in the order the data holds them: open, high, low and
     * close for a candlestick, minimum, quartiles and maximum for a boxplot. The names come from the
     * catalog the tooltip reads; the quartile symbols Q1 and Q3 read the same in every shipped
     * language. Null for every other type.
     *
     * @return list<string>|null
     */
    private static function partNames(?string $type): ?array
    {
        // Each name is a literal key, so the translation catalog sees every one it has to carry.
        return match ($type) {
            'candlestick' => [
                self::text(__('wirekit::Open price'), 'Open price'),
                self::text(__('wirekit::High'), 'High'),
                self::text(__('wirekit::Low'), 'Low'),
                self::text(__('wirekit::Close price'), 'Close price'),
            ],
            'boxplot' => [
                self::text(__('wirekit::Min'), 'Min'),
                'Q1',
                self::text(__('wirekit::Median'), 'Median'),
                'Q3',
                self::text(__('wirekit::Max'), 'Max'),
            ],
            default => null,
        };
    }

    /**
     * A translation as text, or the English name where the catalog gave something else.
     *
     * @param  array<array-key, mixed>|string|null  $translation
     */
    private static function text(array|string|null $translation, string $fallback): string
    {
        return is_string($translation) ? $translation : $fallback;
    }
}
