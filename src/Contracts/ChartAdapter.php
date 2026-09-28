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
     * Stable library identifier, used in error messages and as the value of the chart's
     * `data-wk-chart` marker, which a host's lazy-loader keys on. MUST be stable across
     * patch releases — developer config and those loaders depend on it.
     *
     * Examples: 'chartjs' / 'apexcharts' / developer-defined slug.
     */
    public function name(): string;

    /**
     * Script URLs the page needs for this library, as full URLs or asset() paths — the
     * library itself when the developer does not install it from npm, in the order they
     * must run. Return an empty array when the developer installs it, as both built-in
     * adapters do.
     *
     * The chart component emits one `<script src defer>` per URL, next to the first chart
     * in a response that uses this adapter, and never twice for the same URL, however many
     * charts ask for it. Each tag carries the page's CSP nonce and `data-navigate-once`.
     * Deferred scripts run once the document is parsed, in the order listed, and before
     * Livewire's injected script starts Alpine on DOMContentLoaded. A script that arrives in
     * a Livewire update does not run, so a chart that first appears through an update needs
     * its library on the page already: load it in the layout, or install it from npm.
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
