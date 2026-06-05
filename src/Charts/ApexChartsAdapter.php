<?php

declare(strict_types=1);

namespace Pushery\WireKit\Charts;

use Pushery\WireKit\Contracts\ChartAdapter;

/**
 * ApexCharts adapter — normalizes WireKit data into the ApexCharts options shape.
 *
 * License notice — ApexCharts is NOT MIT-licensed. The free Community License
 * covers personal use, non-profits, education, and organizations under
 * $2M USD annual revenue. Above that threshold a Commercial License must be
 * purchased directly from ApexCharts. WireKit ships only this adapter glue
 * (MIT); the JS library is the developer's responsibility to install + license.
 * See https://apexcharts.com/license/ for the current terms.
 *
 * The developer installs `apexcharts` via npm; WireKit does not bundle it.
 */
final class ApexChartsAdapter implements ChartAdapter
{
    public function name(): string
    {
        return 'apexcharts';
    }

    /**
     * ApexCharts is installed and imported by the user via npm.
     * WireKit does not deliver the JS library.
     */
    public function scripts(): array
    {
        return [];
    }

    /**
     * WireKit's simplified format → ApexCharts options object.
     *
     * Key shape difference vs. Chart.js:
     *   ApexCharts: { series: [{ name, data }], chart: { type }, xaxis: { categories } }
     *   Chart.js:   { type, data: { labels, datasets } }
     *
     * Pie/donut variants use a top-level `labels` array (per slice) plus a
     * single-array `series` of numeric values (no per-slice name field) —
     * we emit both shapes so the JS factory can pick whichever the chart
     * type expects.
     */
    public function normalizeData(string $type, array $labels, array $datasets): array
    {
        $apexType = $this->mapType($type);

        // Pie/donut/radial variants flatten datasets[0].data into a top-level
        // `series` array (numeric) plus top-level `labels` (per slice).
        if (in_array($apexType, ['pie', 'donut', 'radialBar', 'polarArea'], true)) {
            $first = $datasets[0]['data'] ?? [];

            return [
                'series' => is_array($first) ? array_values($first) : [],
                'labels' => $labels,
                'chart' => ['type' => $apexType],
            ];
        }

        // Standard cartesian / radar / scatter / bubble shape:
        // series = [{ name, data }, ...]
        $series = [];
        foreach ($datasets as $index => $dataset) {
            $series[] = array_merge(
                [
                    'name' => $dataset['label'] ?? "Series {$index}",
                    'data' => $dataset['data'] ?? [],
                ],
                // Pass through extra keys (color, type per-series for mixed, etc.)
                array_diff_key($dataset, array_flip(['label', 'data'])),
            );
        }

        return [
            'series' => $series,
            'chart' => ['type' => $apexType],
            'xaxis' => ['categories' => $labels],
        ];
    }

    public function defaultOptions(string $type): array
    {
        // Static defaults — the dynamic CSS-var-driven theming happens
        // client-side in the wirekitApexChart Alpine factory.
        //
        // chart.width/height = 100% — without these defaults ApexCharts uses
        // its own auto-sizing which can land at < 50% of the available width
        // (the "tiny chart in a wide preview frame" failure mode reported on
        // /components/charts-apex/mixed). Setting both to 100% makes the
        // chart fill its wrapper, which is height-pinned by the chart Blade
        // component's inline style.
        $base = [
            'chart' => [
                'width' => '100%',
                'height' => '100%',
                'toolbar' => ['show' => false],
                'zoom' => ['enabled' => false],
                'animations' => [
                    'enabled' => true,
                    'speed' => 400,
                    // No `dynamicAnimation` override here — ApexCharts' own
                    // default (350 ms, enabled) handles `chart.updateSeries()`
                    // animations cleanly, including the streaming path's
                    // FIFO slide-left. The previous 250 ms override caused
                    // a visible lag on hover-heavy charts (heatmap cells,
                    // pie slices) because ApexCharts fires an internal
                    // dynamic-animation cycle on every cell hover for
                    // tooltip-positioning purposes; with the override
                    // each hover queued a 250 ms re-interpolation and
                    // the tooltip "trailed" the cursor. Letting Apex use
                    // its native default keeps the streaming path smooth
                    // AND restores hover responsiveness everywhere else.
                ],
                'background' => 'transparent',
            ],
            'dataLabels' => ['enabled' => false],
            'legend' => [
                'show' => true,
                'position' => 'top',
            ],
            // Tooltip — uniform BEHAVIOR + STYLING across every apex demo,
            // modeled on the /components/charts-apex/scatter-bubble Basic
            // Example which the user signaled as canonical.
            //
            // `shared: false` + `intersect: true`: tooltip fires when the
            // cursor is ON a data element (bar / bubble / line point / pie
            // slice) and shows ONE tooltip for that exact element. Native
            // ApexCharts defaults differ per chart type (bar / line use
            // `shared: true, intersect: false`; scatter / bubble use
            // `shared: false, intersect: true`) which produced visibly
            // different tooltip placement between, say, /charts-apex/bar
            // (panel anchored at the column edge) and /charts-apex/scatter-
            // bubble (panel anchored at the bubble center). Forcing the
            // scatter-style defaults across all types gives every demo
            // the same hover affordance.
            //
            // `fillSeriesColor: false` + `marker.show: true` standardize
            // the color-swatch as an unfilled circle. `style.fontSize:
            // '12px'` pins the tooltip typography so the panel reads
            // identically regardless of chart type.
            'tooltip' => $this->defaultTooltip($type) + ['custom' => 'WIREKIT_DEFAULT_TOOLTIP'],
        ];

        // Stroke is ONLY emitted when the chart type genuinely wants one
        // (line / area). For everything else — bar / column / pie / radar /
        // scatter / etc. — `defaultStroke()` returns `[]`. Before the
        // chart-apex.js options-flatten landed, that empty array was
        // silently dropped along with every other user `:options` value.
        // After the flatten, `stroke: []` reached ApexCharts at top-level
        // and broke bar / column / pie rendering (ApexCharts expects
        // `stroke` to be a plain object; an array short-circuits the
        // stroke-config reader so the renderer can't compute fill paths).
        // Conditional inclusion keeps line/area working while letting
        // bar-family charts fall back to ApexCharts' own defaults
        // (which is what bar charts want anyway — no stroke around the
        // fill rectangles).
        $stroke = $this->defaultStroke($type);
        if (! empty($stroke)) {
            $base['stroke'] = $stroke;
        }

        return $base;
    }

    public function alpineComponent(): string
    {
        return 'wirekitApexChart';
    }

    public function rendersTo(): string
    {
        return 'div';
    }

    public function supportedTypes(): array
    {
        return [
            // Direct mappings — types where ApexCharts and Chart.js use the same vocabulary.
            'bar', 'line', 'area', 'pie', 'doughnut', 'radar', 'polarArea', 'scatter', 'bubble',
            // ApexCharts-only types (Section 6.2)
            'candlestick', 'boxplot', 'range-bar', 'range-area', 'heatmap', 'treemap',
            'funnel', 'radial-bar', 'sparkline',
            // ApexCharts has no native 'column' type — vertical bars are `bar` with
            // plotOptions.bar.horizontal: false (the default). Accept `column` on
            // input as a synonym for vertical bars and rely on mapType() to translate.
            'column',
            // Promoted-extension types (Section 12)
            'mixed',
            'annotated',
        ];
    }

    /**
     * WireKit canonical type → ApexCharts chart.type vocabulary.
     * Handles the spelling differences (doughnut → donut, radial-bar → radialBar)
     * so the public API stays consistent across adapters.
     */
    private function mapType(string $type): string
    {
        return match ($type) {
            'doughnut' => 'donut',
            'radial-bar' => 'radialBar',
            'range-bar' => 'rangeBar',
            'range-area' => 'rangeArea',
            'boxplot' => 'boxPlot',
            'sparkline' => 'line',
            'mixed' => 'line',
            'annotated' => 'line',
            // Vertical-bar chart — ApexCharts has no native `column` type. Maps
            // to `bar` and the developer sets plotOptions.bar.horizontal: false
            // (the default, so most demos require no extra options).
            'column' => 'bar',
            default => $type,
        };
    }

    /**
     * Type-specific stroke defaults. Lines/areas get a smooth curve; bars
     * get no stroke (default). Developer overrides win via array_replace_recursive
     * in the Chart component.
     */
    /**
     * Type-aware tooltip defaults. Uniform STYLING (marker shape, font, etc.)
     * across every chart type — `shared` / `intersect` BEHAVIOR is per-type.
     *
     * Cursor-following behavior (`shared: true, intersect: false` — tooltip
     * tracks the cursor's x-position, shows the closest data point's value)
     * is the right UX for charts with a continuous x-axis: line / area /
     * bar / column / candlestick / boxplot / mixed / annotated. Users
     * sweep the cursor across the chart and expect the tooltip to update.
     *
     * Per-element anchoring (`shared: false, intersect: true` — tooltip
     * fires only when the cursor is ON a discrete element, anchored at
     * that element) is the right UX for charts where each data unit is a
     * spatially distinct shape: scatter / bubble / radar / polarArea /
     * pie / doughnut / heatmap / treemap / funnel / radialBar / range-bar
     * / range-area. Users hover a specific cell / slice / point.
     *
     * The earlier global `shared: false, intersect: true` override broke
     * line/area hover (tooltip stuck on first point, didn't follow cursor).
     * The earlier no-override path left radar / treemap / heatmap with
     * cursor-following defaults that the user reported as "ruckelnd".
     * Branch by canonical ApexCharts type so each chart family gets the
     * shape its users expect.
     */
    private function defaultTooltip(string $type): array
    {
        $base = [
            'enabled' => true,
            'fillSeriesColor' => false,
            'marker' => ['show' => true],
            'style' => [
                'fontSize' => '12px',
            ],
        ];

        $perElementTypes = [
            'scatter', 'bubble', 'radar', 'polarArea', 'pie', 'doughnut',
            'funnel', 'radial-bar', 'range-bar', 'range-area',
            'heatmap', 'treemap',
        ];

        // Radar specifically also needs `followCursor: false` — even with
        // `intersect: true` ApexCharts radar internally cursor-tracks
        // until the data-vertex is hit, producing a "tooltip not pinned"
        // symptom. Explicit follow-cursor opt-out fixes it.
        if (in_array($type, ['radar', 'heatmap', 'treemap'], true)) {
            $base['followCursor'] = false;
        }

        // Mixed-mode charts (line + bar combinations) need explicit
        // `shared: true, intersect: false` because ApexCharts' native
        // mixed-renderer defers to per-series intersect logic — the bar
        // series catches all hover events while the line series sits
        // unhoverable. Forcing shared cursor-x tracking makes hovering
        // ANYWHERE on the chart show ALL series' values at that x,
        // matching the developer expectation for combined-series charts.
        $sharedCursorTypes = ['mixed', 'annotated'];

        if (in_array($type, $perElementTypes, true)) {
            $base['shared'] = false;
            $base['intersect'] = true;
        } elseif (in_array($type, $sharedCursorTypes, true)) {
            $base['shared'] = true;
            $base['intersect'] = false;
        }

        // Heatmap + treemap are intentionally NOT in either branch above.
        // ApexCharts doesn't expose a "anchor tooltip at hovered cell
        // center" API — `intersect: true, followCursor: false` makes the
        // tooltip render in a chart-corner fallback far from the hovered
        // cell; `followCursor: true` (the native default) keeps the
        // tooltip near the cursor but with visible jitter as the mouse
        // moves within the cell. The cursor-follow native default is
        // the lesser evil — the tooltip stays USEFUL (near where the
        // user is looking) at the cost of some jitter. A proper center-
        // of-cell anchor would need a custom positioning layer outside
        // the ApexCharts tooltip API (post-hover DOM measurement +
        // direct style.left/top mutation).

        return $base;
    }

    private function defaultStroke(string $type): array
    {
        return match ($this->mapType($type)) {
            'line', 'area' => ['curve' => 'smooth', 'width' => 2],
            default => [],
        };
    }
}
