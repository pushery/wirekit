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
    public array $chartConfig;

    public string $alpineComponent;

    /** Mount element: 'canvas' (Chart.js) or 'div' (ApexCharts). */
    public string $mountElement;

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
     *                                  FIFO) or 'stream' (unbounded growth — consumer responsibility).
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
     *                            trace / slice-sweep). On a consumer app the prop is a no-op unless
     *                            the consumer has wired its own replay-button surface; the prop only
     *                            changes the rendered HTML to include the data attribute.
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
    ) {
        /** @var ChartManager $manager Resolved from the container singleton */
        $manager = app(ChartManager::class);

        $adapter = $manager->adapter();

        if ($adapter === null) {
            throw new RuntimeException(
                "WireKit: Charts are disabled. Set 'charts.library' in config/wirekit.php "
                ."to enable charts. Example: 'charts' => ['library' => 'chartjs']"
            );
        }

        // Validate the requested type against the active adapter's surface.
        // Throws TypeNotSupportedException with a switch-library hint when the
        // consumer requests something the active library cannot render.
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
        // shapes via different keys, but the consumer feeds a single array of
        // specs. We attach to BOTH locations so whichever adapter is active
        // picks it up; the inactive shape is harmless.
        if (! empty($annotations)) {
            $mergedOptions['annotations'] = $annotations;
            $mergedOptions['plugins'] = array_merge(
                $mergedOptions['plugins'] ?? [],
                ['annotation' => ['annotations' => $annotations]],
            );
        }

        $this->chartConfig = array_merge($normalized, ['options' => $mergedOptions]);
        $this->alpineComponent = $adapter->alpineComponent();
        $this->mountElement = $adapter->rendersTo();
    }

    public function render(): View
    {
        return view('wirekit::components.chart');
    }
}
