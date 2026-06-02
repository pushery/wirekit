<?php

declare(strict_types=1);

namespace Pushery\WireKit\Components;

use Illuminate\View\Component;
use Illuminate\View\View;
use Pushery\WireKit\Charts\ChartManager;
use Pushery\WireKit\Charts\TypeNotSupportedException;
use RuntimeException;

/**
 * Chart component — renders a chart canvas (Chart.js) or div mount (ApexCharts)
 * with Alpine.js integration.
 *
 * Delegates data normalization, theming, type-validation, and DOM-mount
 * shape to the active ChartAdapter. Neither Chart.js nor ApexCharts is
 * bundled by WireKit — both are user-installed via npm.
 */
final class Chart extends Component
{
    /** @var array<string, mixed> */
    public array $chartConfig = [];

    public string $alpineComponent = '';

    /** Mount element: 'canvas' (Chart.js) or 'div' (ApexCharts). */
    public string $mountElement = 'canvas';

    /**
     * True when no chart adapter is configured AND the constructor took
     * the debug-mode soft-fallback path (rendered a placeholder div
     * instead of throwing). Used by render() to switch to the
     * placeholder view. Always false in production (the constructor
     * throws hard there).
     */
    public bool $chartsDisabled = false;

    /**
     * @param  string  $type  Chart type — validated against the active adapter's supportedTypes().
     * @param  array<int, string>  $labels  X-axis / segment labels.
     * @param  array<int, array<string, mixed>>  $datasets  Dataset array (adapter-specific shape).
     * @param  array<string, mixed>  $options  Adapter-specific options, deep-merged on top of adapter defaults.
     * @param  string  $height  CSS height value (e.g. '380px', '24rem'). Default '380px' is the minimum that comfortably hosts an ApexCharts legend (~50px top), plot area, and x-axis labels (~40px bottom) without bottom-clipping when the chart wrapper carries `overflow: hidden` (which it does, to prevent SVG line overshoots from escaping the wrapper into surrounding page content).
     * @param  string|null  $wireStream  Livewire event name to subscribe to for real-time data
     *                                   appending. When set, the Alpine factory listens for the
     *                                   event and pipes incoming `point` payloads into the chart
     *                                   via the library's imperative API (Chart.js: data.datasets[i].data.push;
     *                                   ApexCharts: chart.appendSeries / chart.appendData). When null
     *                                   (default), the chart renders normally with no streaming behaviour.
     * @param  string  $wireStreamMode  'strict' (default — caps the dataset at wireStreamCap points
     *                                  FIFO) or 'stream' (unbounded growth — developer responsibility).
     * @param  int  $wireStreamCap  Max data-point count per dataset under 'strict' mode. Default 100.
     * @param  array<int, array<string, mixed>>  $annotations  Annotation specs passed through to the
     *                                                         chart options. ApexCharts: maps to options.annotations.
     *                                                         Chart.js: requires chartjs-plugin-annotation; graceful
     *                                                         degradation when the plugin is absent (console.warn).
     * @param  bool  $replayable  Opt INTO the docs site's replay-button surface. When true, the chart
     *                            root emits `data-replayable="true"` so a `↻ Replay` button appears
     *                            on previews that detect the attribute. Auto-detects when `wireStream`
     *                            is bound (every streaming chart is replay-worthy — clicking replay
     *                            resets the live ticker). For non-streaming charts, set explicitly
     *                            when the entrance animation is worth re-watching (bar-grow / line-
     *                            trace / slice-sweep). On a developer app the prop is a no-op unless
     *                            the developer has wired its own replay-button surface; the prop only
     *                            changes the rendered HTML to include the data attribute.
     * @param  string|null  $library  Per-instance chart-library override. Accepts the same values as
     *                                `config('wirekit.charts.library')` — the built-in keys `chartjs`
     *                                / `apexcharts`, OR a fully qualified class name implementing
     *                                `ChartAdapter`. When `null` (default), the chart uses whatever
     *                                the global `wirekit.charts.library` config resolves to, matching
     *                                the pre-v2.3.0 behaviour. When set, this chart instance binds
     *                                to the named library regardless of the app default — two charts
     *                                on the same page can use different libraries. Use this when a
     *                                specific chart type requires a specific library (e.g. `boxplot`
     *                                / `candlestick` / `heatmap` / `treemap` are ApexCharts-only and
     *                                throw `TypeNotSupportedException` against the Chart.js adapter)
     *                                in an app whose default is the OTHER library. Removes the need
     *                                for path-based library-config switching in renderers (docs
     *                                sites, sample apps, developer integrations).
     */
    public function __construct(
        public string $type = 'bar',
        public array $labels = [],
        public array $datasets = [],
        public array $options = [],
        public string $height = '380px',
        public ?string $wireStream = null,
        public string $wireStreamMode = 'strict',
        public int $wireStreamCap = 100,
        public array $annotations = [],
        // When true, render the wrapper as `<span style="display: inline-block">`
        // (and the SVG mount as `<span>`) instead of the default `<div>`. Required
        // for placing a chart inside a `<p>` — HTML5's parser auto-closes a `<p>`
        // the moment it encounters a `<div>` descendant, breaking inline-sparkline
        // flow into separate paragraphs. Phrasing-content shape keeps the chart
        // legal as inline content.
        public bool $inline = false,
        public bool $replayable = false,
        public ?string $library = null,
        // Tooltip numeric formatting (ApexCharts only — see the sentinel-keyed
        // injection below). `valueDecimals` rounds raw float y-values in the
        // custom tooltip to N decimal places; `valuePrefix` / `valueSuffix`
        // wrap each formatted number (e.g. "€", " ms", "%"). All three are
        // null by default → today's behaviour (raw value, no affix) preserved
        // byte-for-byte. `valueDecimals` is typed `int|string|null` so both
        // `valueDecimals="2"` (plain attribute) and `:valueDecimals="2"`
        // (bound int) work — strict_types would reject a string on a bare
        // `?int` param.
        public int|string|null $valueDecimals = null,
        public ?string $valuePrefix = null,
        public ?string $valueSuffix = null,
    ) {
        /** @var ChartManager $manager Resolved from the container singleton */
        $manager = app(ChartManager::class);

        // Per-instance library override wins over the global config. When the
        // caller passes `library="apexcharts"` (or "chartjs", or a custom
        // FQCN), the chart binds to that adapter regardless of the app's
        // default. When omitted, fall through to the configured adapter.
        $adapter = $library !== null
            ? $manager->adapterFor($library)
            : $manager->adapter();

        if ($adapter === null) {
            // In production: throw hard. Silent fallback to a blank
            // placeholder would hide a real misconfiguration from end
            // users (charts that should render don't, and the developer
            // has no signal).
            if (! config('app.debug')) {
                throw new RuntimeException(
                    "WireKit: Charts are disabled. Set 'charts.library' in config/wirekit.php "
                    ."to enable charts. Example: 'charts' => ['library' => 'chartjs']"
                );
            }

            // In debug: render a developer-visible placeholder instead
            // of crashing the whole page on first-run "I just dropped
            // <x-wirekit-chart> into a fresh app" UX. The placeholder
            // states the missing-config step inline. `wirekit:doctor`
            // surfaces the same diagnostic at install time.
            $this->chartsDisabled = true;

            return;
        }

        // Validate the requested type against the active adapter's surface.
        // Throws TypeNotSupportedException with a switch-library hint when the
        // developer requests something the active library cannot render.
        if (! in_array($type, $adapter->supportedTypes(), true)) {
            throw TypeNotSupportedException::for($adapter, $type);
        }

        // Normalize data via adapter.
        $normalized = $adapter->normalizeData($type, $labels, $datasets);

        // Merge default options with user options (user options win).
        $mergedOptions = array_replace_recursive(
            $adapter->defaultOptions($type),
            $options,
        );

        // Annotations passthrough — the adapters consume the options.annotations
        // (ApexCharts native) / options.plugins.annotation (Chart.js plugin)
        // shapes via different keys, but the developer feeds a single array of
        // specs. We attach to BOTH locations so whichever adapter is active
        // picks it up; the inactive shape is harmless.
        if (! empty($annotations)) {
            $mergedOptions['annotations'] = $annotations;
            $mergedOptions['plugins'] = array_merge(
                $mergedOptions['plugins'] ?? [],
                ['annotation' => ['annotations' => $annotations]],
            );
        }

        // Tooltip numeric formatting — thread valueDecimals / valuePrefix /
        // valueSuffix into the tooltip config, but ONLY when the merged options
        // still carry the ApexCharts `WIREKIT_DEFAULT_TOOLTIP` sentinel. That
        // sentinel is emitted exclusively by ApexChartsAdapter::defaultOptions(),
        // so keying off it scopes the feature to the apex adapter automatically
        // (Chart.js never emits it) without an instanceof check or an interface
        // change. A developer who overrides `tooltip.custom` with their own JS
        // formatter opts out — their override wins via array_replace_recursive,
        // the sentinel is gone, and we skip the injection.
        if (($mergedOptions['tooltip']['custom'] ?? null) === 'WIREKIT_DEFAULT_TOOLTIP') {
            // Normalize valueDecimals to an int in [0, 100] or null. is_numeric
            // accepts "2" / 2 / "0"; anything else (null, "", "x") → null = unset.
            // The 0–100 clamp is mandatory: the JS side feeds this to
            // Number.prototype.toFixed(), which throws a RangeError for any
            // argument outside 0–100 — an unclamped `valueDecimals="200"` would
            // crash the tooltip renderer at hover time.
            $decimals = is_numeric($valueDecimals) ? min(100, max(0, (int) $valueDecimals)) : null;

            // Only attach the wk* keys when at least one is meaningful — keeps
            // the emitted config byte-for-byte identical to pre-feature output
            // when the developer passes none of the three props.
            if ($decimals !== null || $valuePrefix !== null || $valueSuffix !== null) {
                $mergedOptions['tooltip']['wkValueDecimals'] = $decimals;
                $mergedOptions['tooltip']['wkValuePrefix'] = $valuePrefix;
                $mergedOptions['tooltip']['wkValueSuffix'] = $valueSuffix;
            }
        }

        $this->chartConfig = array_merge($normalized, ['options' => $mergedOptions]);
        $this->alpineComponent = $adapter->alpineComponent();
        $this->mountElement = $adapter->rendersTo();
    }

    public function render(): View
    {
        if ($this->chartsDisabled) {
            // Resolved via the `wirekit::internal.chart-disabled` view name —
            // a purely internal render target, NOT exposed as a Blade
            // component (`<x-wirekit::chart-disabled>` does not exist).
            return view('wirekit::internal.chart-disabled');
        }

        return view('wirekit::components.chart');
    }
}
