<?php

declare(strict_types=1);

namespace Pushery\WireKit\Charts;

use DateTimeInterface;

/**
 * The dates on a chart's date axis, written in the form the axis reads as the time they name.
 *
 * Both libraries read a date as an instant and label it in a zone of their own: ApexCharts in UTC
 * unless `xaxis.labels.datetimeUTC` is false, that axis and a Chart.js time scale in the reader's.
 * Three forms were read as another time than the one written:
 *
 * - `json_encode()` writes a date object of Carbon as its instant in UTC and a plain `DateTime` as
 *   an object no library reads. The 25th of December from a `date` cast of an application in
 *   Berlin reached an axis in UTC as the 24th, 23:00.
 * - A browser reads a date and time without a zone in its own zone, so an axis in UTC showed each
 *   reader another hour, and none of them the one written.
 * - A browser reads a date alone as midnight in UTC, so an axis in the reader's zone showed the
 *   day before to every reader west of UTC.
 *
 * On an axis in UTC a date object is written as its time with `Z`, and so is a date and time
 * without a zone. On an axis in the reader's zone a date object is written as its instant with its
 * offset, which is what such an axis shows, and a date alone as midnight without a zone. Text that
 * names its zone and a number of milliseconds name an instant and pass as they came.
 *
 * @internal Called by the chart component while it prepares its data; not a promise to a developer.
 */
final class ChartDates
{
    /** A date and a time without a zone: ISO 8601 with a `T` or a space, seconds and a fraction optional. */
    private const DATE_AND_TIME = '/^\d{4}-\d{2}-\d{2}[T ]\d{2}:\d{2}(?::\d{2}(?:\.\d+)?)?$/';

    /** A date alone. */
    private const DATE = '/^\d{4}-\d{2}-\d{2}$/';

    /** The chart types that name a row by `x` and draw the two dates of `y` as the ends of a bar. */
    private const RANGE_BARS = ['range-bar', 'rangeBar'];

    /**
     * The labels and the datasets with each date of the x axis written for the zone the axis labels
     * in (ChartPointLinks::dateZone()), and as they came where the axis holds no dates.
     *
     * A date sits in a label, in the `x` of a point and in the first value of a pair, or at both
     * ends of a range: a pair the library draws as one, and the `y` of a range bar, which names its
     * row by `x` and its labels.
     *
     * @param  list<array<array-key, mixed>|object|string|int|float|bool|null>  $labels
     * @param  array<array-key, mixed>  $datasets  The datasets as Chart::datasetsFrom() gives them.
     * @param  list<bool>  $rangePairs  Per dataset, whether a pair is a range (ChartPointLinks::rangePairs()).
     * @return array{0: list<array<array-key, mixed>|object|string|int|float|bool|null>, 1: array<array-key, mixed>}
     */
    public static function onAxis(array $labels, array $datasets, ?string $zone, string $type, array $rangePairs = []): array
    {
        if ($zone === null) {
            return [$labels, $datasets];
        }

        $utc = $zone === 'UTC';
        $write = static fn (array|object|string|int|float|bool|null $date): array|object|string|int|float|bool|null => self::written($date, $utc);

        if (! in_array($type, self::RANGE_BARS, true)) {
            $labels = array_map($write, $labels);
        }

        foreach ($datasets as $d => $dataset) {
            if (! is_array($dataset) || ! is_array($dataset['data'] ?? null)) {
                continue;
            }

            // A dataset's own type picks its kind in a mixed chart.
            $rangeBar = in_array(is_string($dataset['type'] ?? null) ? $dataset['type'] : $type, self::RANGE_BARS, true);

            foreach ($dataset['data'] as $p => $point) {
                if (! is_array($point)) {
                    continue;
                }

                if ($rangeBar) {
                    if (is_array($point['y'] ?? null) && array_is_list($point['y'])) {
                        $point['y'] = array_map($write, $point['y']);
                    }
                } elseif (array_key_exists('x', $point)) {
                    $point['x'] = $write($point['x']);
                } elseif (array_is_list($point) && count($point) === 2) {
                    $point = ($rangePairs[$d] ?? false) ? array_map($write, $point) : [$write($point[0]), $point[1]];
                }

                $datasets[$d]['data'][$p] = $point;
            }
        }

        return [$labels, $datasets];
    }

    /**
     * One value of the axis, written for an axis in UTC or in the reader's zone.
     *
     * @param  array<array-key, mixed>|object|string|int|float|bool|null  $date
     * @return array<array-key, mixed>|object|string|int|float|bool|null
     */
    private static function written(array|object|string|int|float|bool|null $date, bool $utc): array|object|string|int|float|bool|null
    {
        if ($date instanceof DateTimeInterface) {
            return $utc ? $date->format('Y-m-d\TH:i:s.v').'Z' : $date->format(DateTimeInterface::RFC3339_EXTENDED);
        }

        if (! is_string($date)) {
            return $date;
        }

        $text = trim($date);

        if ($utc && preg_match(self::DATE_AND_TIME, $text) === 1) {
            return str_replace(' ', 'T', $text).'Z';
        }

        if (! $utc && preg_match(self::DATE, $text) === 1) {
            return $text.'T00:00:00';
        }

        return $date;
    }
}
