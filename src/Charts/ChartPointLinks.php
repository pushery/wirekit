<?php

declare(strict_types=1);

namespace Pushery\WireKit\Charts;

use BackedEnum;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Support\Arrayable;
use IntlDateFormatter;
use IntlDatePatternGenerator;
use Pushery\WireKit\Support\IcuLocale;
use Pushery\WireKit\Support\LocalizedNumber;
use Pushery\WireKit\Support\SafeUrl;
use Stringable;
use Throwable;

/**
 * The addresses a chart's data points lead to.
 *
 * A dataset names them in `urls`, one per point in the order of its `data`, and a point written
 * as an object names its own in `url`, the form a treemap or a bubble chart already writes its
 * points in. Where both name one, the point's own wins. Both are taken out of the data before it
 * reaches a chart library, which knows neither key, and every address passes `SafeUrl::href()`,
 * so a target that came from data cannot run script.
 *
 * What is left is a grid, dataset by point, which the chart's script reads to follow a click or a
 * tap, and a list of links in reading order, which the view renders after the chart for a
 * keyboard and a screen reader: no point drawn on a canvas or inside the chart's image can take
 * the focus.
 */
final class ChartPointLinks
{
    /**
     * The datasets without their addresses, and the grid of addresses, one row per dataset and one
     * entry per point, null where a point has none. The grid is empty when no point has one, so a
     * chart without links sends exactly the datasets it was given.
     *
     * @param  list<mixed>  $datasets
     * @return array{0: list<mixed>, 1: list<list<string|null>>}
     */
    public static function extract(array $datasets): array
    {
        $grid = [];
        $any = false;

        foreach ($datasets as $d => $dataset) {
            if (! is_array($dataset)) {
                $grid[] = [];

                continue;
            }

            // A list may arrive as a collection, filtered or not; its order is what counts.
            $urls = $dataset['urls'] ?? null;
            $urls = $urls instanceof Arrayable ? $urls->toArray() : $urls;
            $urls = is_array($urls) ? array_values($urls) : [];
            $data = is_array($dataset['data'] ?? null) ? $dataset['data'] : [];
            $row = [];
            $position = 0;

            // By position rather than by key: a library reads the points in their order, and the
            // order is all `urls` has to line up with.
            foreach ($data as $key => $point) {
                $url = $urls[$position] ?? null;
                $point = $point instanceof Arrayable ? $point->toArray() : $point;

                if (is_array($point) && array_key_exists('url', $point)) {
                    $url = $point['url'];
                    unset($point['url']);
                    $data[$key] = $point;
                }

                $safe = self::safe($url);
                $row[] = $safe;
                $any = $any || $safe !== null;
                $position++;
            }

            unset($dataset['urls']);

            if (array_key_exists('data', $dataset)) {
                $dataset['data'] = $data;
            }

            $datasets[$d] = $dataset;
            $grid[] = $row;
        }

        return [array_values($datasets), $any ? $grid : []];
    }

    /**
     * Per dataset, whether its library draws a point written as a pair as a range: Chart.js reads
     * the pair of a bar as its two ends, a floating bar, and the pair of any other type as `x` and
     * `y`, which is how ApexCharts reads every pair.
     *
     * @param  list<mixed>  $datasets  The datasets `extract()` returned.
     * @return list<bool>
     */
    public static function rangePairs(string $library, string $type, array $datasets): array
    {
        $ranges = [];

        foreach ($datasets as $dataset) {
            // A dataset's own type picks its controller in a mixed chart; `column` is a bar there.
            $own = is_array($dataset) && is_string($dataset['type'] ?? null) ? $dataset['type'] : $type;
            $ranges[] = $library === 'chartjs' && in_array($own, ['bar', 'column', 'mixed'], true);
        }

        return $ranges;
    }

    /**
     * The zone a chart writes the dates of its x axis in, or null where its x axis holds no dates:
     * ApexCharts with `xaxis.type: 'datetime'`, in UTC unless `xaxis.labels.datetimeUTC` is
     * false, and Chart.js with a `time` or `timeseries` x scale. Where the chart writes a date in
     * the reader's zone, which a page cannot know on the server, it is the application's zone.
     *
     * @param  array<mixed>  $options  The chart's options, merged with its adapter's defaults.
     */
    public static function dateZone(string $library, array $options): ?string
    {
        $configured = config('app.timezone');
        $application = is_string($configured) && $configured !== '' ? $configured : 'UTC';

        if ($library === 'apexcharts' && data_get($options, 'xaxis.type') === 'datetime') {
            return data_get($options, 'xaxis.labels.datetimeUTC') === false ? $application : 'UTC';
        }

        if ($library === 'chartjs' && in_array(data_get($options, 'scales.x.type'), ['time', 'timeseries'], true)) {
            return $application;
        }

        return null;
    }

    /**
     * The links of the grid in reading order, dataset by dataset, each named by what its point
     * shows: the dataset's name where the chart has more than one, the point's label, and its
     * value, formatted as the chart's tooltip formats it.
     *
     * @param  list<list<string|null>>  $grid
     * @param  list<mixed>  $labels
     * @param  list<mixed>  $datasets  The datasets `extract()` returned.
     * @param  list<bool>  $rangePairs  Per dataset, whether a point written as a pair is a range (`rangePairs()`).
     * @param  string|null  $dateZone  The zone the x axis writes its dates in, null where it holds none (`dateZone()`).
     * @return list<array{url: string, text: string, navigate: bool}>
     */
    public static function list(array $grid, array $labels, array $datasets, ?int $decimals = null, ?string $prefix = null, ?string $suffix = null, array $rangePairs = [], ?string $dateZone = null): array
    {
        $links = [];
        $named = count($datasets) > 1;

        foreach ($grid as $d => $row) {
            $dataset = is_array($datasets[$d] ?? null) ? $datasets[$d] : [];
            $points = is_array($dataset['data'] ?? null) ? array_values($dataset['data']) : [];
            $fallback = __('wirekit::Series :number', ['number' => $d + 1]);
            $series = self::text($dataset['label'] ?? null) ?? (is_string($fallback) ? $fallback : 'Series '.($d + 1));
            // A series that names its own decimals is written with them, as the tooltip writes it.
            $places = is_numeric($dataset['decimals'] ?? null) ? min(100, max(0, (int) $dataset['decimals'])) : $decimals;

            foreach ($row as $p => $url) {
                if ($url === null) {
                    continue;
                }

                $point = $points[$p] ?? null;

                // A pair, the form both libraries take besides an object: `x` and `y`, or the two
                // ends of a range where the library draws the pair as one.
                if (is_array($point) && array_is_list($point) && count($point) === 2) {
                    $point = ($rangePairs[$d] ?? false) ? ['y' => $point] : ['x' => $point[0], 'y' => $point[1]];
                }

                // The chart's label for the point, else the point's own `x`, written on a date axis
                // as the tooltip writes a date.
                $x = $labels[$p] ?? null;

                if (self::text($x) === null) {
                    $x = is_array($point) ? ($point['x'] ?? null) : null;
                }

                $label = ($dateZone !== null && (is_string($x) || is_int($x) || is_float($x)) ? self::date($x, $dateZone) : null)
                    ?? self::text($x)
                    ?? (string) ($p + 1);
                $value = $prefix.self::value(is_array($point) ? ($point['y'] ?? null) : $point, $places).$suffix;

                $links[] = [
                    'url' => $url,
                    // Written as the tooltip writes a row, the name before a colon, in every language.
                    'text' => ($named ? "{$series}, " : '')."{$label}: {$value}",
                    'navigate' => self::sameOrigin($url),
                ];
            }
        }

        return $links;
    }

    /**
     * The address as a link may carry it, or null when there is none or its scheme could run script.
     * `mixed` because it is whatever the developer's data holds at that place.
     */
    private static function safe(mixed $url): ?string
    {
        if (! ($url === null || is_string($url) || $url instanceof Stringable || $url instanceof BackedEnum)) {
            return null;
        }

        $safe = SafeUrl::href($url);

        return $safe === '' ? null : $safe;
    }

    /**
     * A label or a name as text, or null when it has none to give; `mixed` as it comes from the data.
     * ChartDataTable names its rows and columns with it as well.
     */
    public static function text(mixed $value): ?string
    {
        if (is_string($value) || is_int($value) || is_float($value) || $value instanceof Stringable) {
            $text = trim((string) $value);

            return $text === '' ? null : $text;
        }

        return null;
    }

    /**
     * A date of the x axis as the tooltip writes one without a format of its own: the month short,
     * the day in two digits and the year, in the application's language and in the axis's zone.
     * The time comes in milliseconds, as both libraries take it, or as an ISO 8601 date as
     * ChartDates wrote it: with its zone, or without one where the axis reads it in its own zone,
     * and so does this. Anything else is no date, and null. ChartDataTable writes its dates with it.
     */
    public static function date(string|int|float $x, string $zone): ?string
    {
        try {
            if (is_string($x) && ! is_numeric($x)) {
                if (preg_match('/^\d{4}-\d{2}-\d{2}/', $x) !== 1) {
                    return null;
                }

                $moment = CarbonImmutable::parse($x, $zone)->setTimezone($zone);
            } else {
                $moment = CarbonImmutable::createFromTimestampMs((float) $x, $zone);
            }

            $locale = IcuLocale::usable();

            // The pattern the browser's `Intl.DateTimeFormat` builds from the same fields, where
            // the intl extension can build it too.
            if (extension_loaded('intl')) {
                $pattern = (new IntlDatePatternGenerator($locale))->getBestPattern('MMMddyyyy');
                $written = is_string($pattern)
                    ? (new IntlDateFormatter($locale, IntlDateFormatter::NONE, IntlDateFormatter::NONE, $zone, IntlDateFormatter::GREGORIAN, $pattern))->format($moment)
                    : false;

                if (is_string($written) && $written !== '') {
                    return $written;
                }
            }

            return $moment->locale($locale)->isoFormat('ll');
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * A value as the tooltip writes it: a number in the page's locale, a range as its two ends.
     * `mixed`, here and for each end of a range, because a point holds what the chart's data holds.
     * ChartDataTable writes its cells with it.
     */
    public static function value(mixed $value, ?int $decimals): string
    {
        if (is_array($value)) {
            return implode(' – ', array_map(fn (mixed $part): string => self::value($part, $decimals), array_values($value)));
        }

        if (is_int($value) || is_float($value) || (is_string($value) && is_numeric($value))) {
            return $decimals === null
                ? LocalizedNumber::format((float) $value, maxPrecision: 3)
                : LocalizedNumber::format((float) $value, precision: $decimals);
        }

        return self::text($value) ?? '';
    }

    /**
     * Whether the address is on this site, which is where Livewire's navigation can take it: a path,
     * or a full address with the scheme, host and port of the request. Another site, and an address
     * that does not parse, is left to the browser.
     */
    private static function sameOrigin(string $url): bool
    {
        // A fragment of this page is a jump within it, not a page to navigate to.
        if (str_starts_with($url, '#')) {
            return false;
        }

        $parts = parse_url($url);

        if ($parts === false) {
            return false;
        }

        // A path, a query or a fragment of this page: the browser resolves it against this site.
        if (! isset($parts['scheme']) && ! isset($parts['host'])) {
            return true;
        }

        if (! app()->bound('request')) {
            return false;
        }

        $request = request();
        $scheme = strtolower($parts['scheme'] ?? $request->getScheme());
        $origin = $scheme.'://'.strtolower($parts['host'] ?? '').(isset($parts['port']) ? ':'.$parts['port'] : '');

        return in_array($scheme, ['http', 'https'], true) && $origin === strtolower($request->getSchemeAndHttpHost());
    }
}
