<?php

declare(strict_types=1);

namespace Pushery\WireKit\Components;

use Illuminate\View\Component;
use Illuminate\View\View;
use Pushery\WireKit\Charts\ChartManager;
use RuntimeException;

/**
 * Chart component — renders a chart canvas with Alpine.js integration.
 *
 * Delegates data normalization and theming to the active ChartAdapter.
 * Chart.js is not bundled — users install it via npm.
 */
final class Chart extends Component
{
    /** @var array<string, mixed> */
    public array $chartConfig;

    public string $alpineComponent;

    /**
     * @param  string  $type  Chart type: 'bar', 'line', 'pie', 'doughnut' (adapter-dependent)
     * @param  array<int, string>  $labels  X-axis / segment labels
     * @param  array<int, array<string, mixed>>  $datasets  Dataset array (adapter-specific shape; Chart.js: [['label' => ..., 'data' => [...]]])
     * @param  array<string, mixed>  $options  Adapter-specific options, merged recursively on top of adapter defaults
     * @param  string  $height  CSS height value applied as inline style (e.g. '300px', '16rem')
     */
    public function __construct(
        public string $type = 'bar',
        public array $labels = [],
        public array $datasets = [],
        public array $options = [],
        public string $height = '300px',
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

        // Normalize data via adapter
        $normalized = $adapter->normalizeData($type, $labels, $datasets);

        // Merge default options with user options (user options win)
        $mergedOptions = array_replace_recursive(
            $adapter->defaultOptions($type),
            $options,
        );

        $this->chartConfig = array_merge($normalized, ['options' => $mergedOptions]);
        $this->alpineComponent = $adapter->alpineComponent();
    }

    public function render(): View
    {
        return view('wirekit::components.chart');
    }
}
