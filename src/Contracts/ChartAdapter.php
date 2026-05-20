<?php

declare(strict_types=1);

namespace Pushery\WireKit\Contracts;

/**
 * Chart adapter contract — bridges WireKit's simplified data shape into a
 * library-specific config + Alpine factory pair.
 *
 * Two built-in adapters: `ChartJsAdapter` (raster / Chart.js) and
 * `ApexChartsAdapter` (SVG / ApexCharts). Custom adapters implement this
 * interface directly; the developer sets `config('wirekit.charts.library')`
 * to either a built-in key or a fully-qualified class name.
 *
 * Seven methods total — every implementation declares all seven explicitly
 * (no abstract base class because the methods are inherently adapter-specific).
 */
interface ChartAdapter
{
    /**
     * Stable library identifier used in error messages, license-tier guards,
     * and config-comparison shortcuts. MUST be stable across patch releases —
     * developer config compatibility depends on it.
     *
     * Examples: 'chartjs' / 'apexcharts' / developer-defined slug.
     */
    public function name(): string;

    /**
     * JavaScript assets that must be loaded in the <head>.
     * Returns URLs or asset() paths. Empty array when the developer installs
     * the JS library themselves via npm (current built-in adapters return []).
     *
     * @return array<string>
     */
    public function scripts(): array;

    /**
     * Normalize WireKit's simplified data format into the library-specific
     * config shape.
     *
     * WireKit input:
     *   type: 'bar'
     *   labels: ['Jan', 'Feb', 'Mar']
     *   datasets: [
     *       ['label' => 'Revenue', 'data' => [12, 19, 3]],
     *       ['label' => 'Costs',   'data' => [7, 11, 5]],
     *   ]
     *
     * Output shape varies per adapter:
     *   - Chart.js: ['type' => ..., 'data' => ['labels' => ..., 'datasets' => ...]]
     *   - ApexCharts: ['series' => [...], 'chart' => ['type' => ...], 'xaxis' => ['categories' => ...]]
     *
     * @param  array<int, string>  $labels
     * @param  array<int, array<string, mixed>>  $datasets
     * @return array<string, mixed>
     */
    public function normalizeData(string $type, array $labels, array $datasets): array;

    /**
     * Default options with WireKit theming (colors from CSS variables, dark mode etc.).
     * Merged with user options (user options win on conflicts).
     *
     * @return array<string, mixed>
     */
    public function defaultOptions(string $type): array;

    /**
     * Name of the Alpine.js component used for x-data.
     * Example: 'wirekitChartJs' -> <div x-data="wirekitChartJs({...})">
     */
    public function alpineComponent(): string;

    /**
     * DOM element the chart library mounts into.
     * 'canvas' for raster libraries (Chart.js); 'div' for SVG libraries
     * (ApexCharts). Drives the Blade template branch in chart.blade.php.
     */
    public function rendersTo(): string;

    /**
     * Canonical type list this adapter can render. The Chart component
     * validates `type=` against this list at construction time and throws
     * `Pushery\WireKit\Charts\TypeNotSupportedException` with a helpful
     * library-switch hint when the developer requests something the active
     * adapter cannot handle.
     *
     * @return array<int, string>
     */
    public function supportedTypes(): array;
}
